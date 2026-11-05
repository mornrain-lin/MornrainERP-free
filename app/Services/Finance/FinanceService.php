<?php

namespace App\Services\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\FinancePrepayment;
use Illuminate\Support\Facades\DB;

/**
 * 财务资金服务
 *
 * 约定（单式记账，轻量 ERP）：
 *  - 所有资金进出唯一入口 = record()：写流水 + 同步账户余额（同一事务）
 *  - 账户余额以 finance_accounts.balance 为准，流水解释「为什么变」
 *  - 预付款：创建时钱已离开/进入账户（记 CAT_PREPAY 流水）；
 *    核销只消耗额度、不再动余额；取消时按原方向反向退回余额
 *  - 一律人民币（CNY），与平台币 / 利润核算隔离
 */
class FinanceService
{
    /**
     * 记一笔收支流水并同步账户余额（资金进出的唯一入口）。
     *
     * $organizationId / $intercompanyId 仅供「跨组织合并核算」的镜像流水使用：
     * 显式指定组织可覆盖 BelongsToOrganization 的当前上下文回填，
     * 保证付款方与收款方流水分别落到各自组织账簿。普通业务调用不传，行为不变。
     */
    public function record(
        FinanceAccount $account,
        string $direction,
        string $category,
        float $amount,
        ?string $occurredOn = null,
        ?string $remark = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?int $userId = null,
        ?int $organizationId = null,
        ?int $intercompanyId = null,
    ): FinanceFlow {
        return DB::transaction(function () use ($account, $direction, $category, $amount, $occurredOn, $remark, $relatedType, $relatedId, $userId, $organizationId, $intercompanyId) {
            $signed = $direction === FinanceFlow::DIRECTION_EXPENSE ? -abs($amount) : abs($amount);

            // 内部往来会操作其它组织的账户，必须绕过单组织全局作用域
            $fresh = FinanceAccount::withoutGlobalScope('organization')->whereKey($account->id)->first() ?? $account;
            $fresh->forceFill(['balance' => (float) $fresh->balance + $signed])->save();

            $data = [
                'flow_no' => $this->nextFlowNo(),
                'direction' => $direction,
                'category' => $category,
                'amount' => abs($amount),
                'account_id' => $fresh->id,
                'occurred_on' => $occurredOn ?: now()->toDateString(),
                'related_type' => $relatedType ?: 'manual',
                'related_id' => $relatedId,
                'remark' => $remark,
                'created_by' => $userId,
            ];
            if ($organizationId !== null) {
                $data['organization_id'] = $organizationId;
            }
            if ($intercompanyId !== null) {
                $data['intercompany_id'] = $intercompanyId;
            }

            return FinanceFlow::create($data);
        });
    }

    /** 创建预付款 / 预收款（钱已离开或进入账户） */
    public function createPrepayment(
        FinanceAccount $account,
        string $direction,
        string $partyName,
        float $amount,
        ?string $occurredOn = null,
        ?string $remark = null,
        ?int $userId = null,
    ): FinancePrepayment {
        $flow = $this->record(
            $account,
            $direction === FinancePrepayment::DIRECTION_OUT ? FinanceFlow::DIRECTION_EXPENSE : FinanceFlow::DIRECTION_INCOME,
            FinanceFlow::CAT_PREPAY,
            $amount,
            $occurredOn,
            ($direction === FinancePrepayment::DIRECTION_OUT ? '预付供应商：' : '预收客户：') . $partyName . ($remark ? "（{$remark}）" : ''),
            'manual',
            null,
            $userId,
        );

        return DB::transaction(function () use ($flow, $direction, $partyName, $amount, $occurredOn, $remark, $userId) {
            return FinancePrepayment::create([
                'prepay_no' => $this->nextPrepayNo(),
                'direction' => $direction,
                'party_name' => $partyName,
                'amount' => abs($amount),
                'used_amount' => 0,
                'status' => FinancePrepayment::STATUS_PENDING,
                'account_id' => $flow->account_id,
                'occurred_on' => $occurredOn ?: now()->toDateString(),
                'remark' => $remark,
                'created_by' => $userId,
            ]);
        });
    }

    /** 核销预付款：消耗额度，不动余额 */
    public function applyPrepayment(FinancePrepayment $prepay, float $amount): FinancePrepayment
    {
        if (! in_array($prepay->status, [FinancePrepayment::STATUS_PENDING, FinancePrepayment::STATUS_PARTIAL], true)) {
            return $prepay;
        }
        $apply = min(abs($amount), $prepay->remaining);
        if ($apply <= 0) {
            return $prepay;
        }
        $used = round((float) $prepay->used_amount + $apply, 2);
        $prepay->used_amount = $used;
        $prepay->status = $used >= round((float) $prepay->amount, 2)
            ? FinancePrepayment::STATUS_USED
            : FinancePrepayment::STATUS_PARTIAL;
        $prepay->save();

        return $prepay;
    }

    /** 取消未核销的预付款：按原方向反向退回余额 */
    public function cancelPrepayment(FinancePrepayment $prepay, ?int $userId = null): FinancePrepayment
    {
        if ($prepay->status === FinancePrepayment::STATUS_CANCELED || $prepay->used_amount > 0) {
            return $prepay;
        }
        $account = $prepay->account;
        if ($account) {
            // 原 out（付款）→ 退回记收入；原 in（收款）→ 退回记支出
            $this->record(
                $account,
                $prepay->direction === FinancePrepayment::DIRECTION_OUT ? FinanceFlow::DIRECTION_INCOME : FinanceFlow::DIRECTION_EXPENSE,
                FinanceFlow::CAT_PREPAY,
                (float) $prepay->amount,
                null,
                '取消' . FinancePrepayment::directionLabel($prepay->direction) . '：' . $prepay->party_name,
                'manual',
                null,
                $userId,
            );
        }
        $prepay->status = FinancePrepayment::STATUS_CANCELED;
        $prepay->save();

        return $prepay;
    }

    /** 对账：比对系统余额与实盘余额 */
    public function reconcile(FinanceAccount $account, float $actualBalance, ?string $note = null, ?int $userId = null): \App\Models\FinanceReconciliation
    {
        $system = round((float) $account->balance, 2);
        $actual = round($actualBalance, 2);
        $diff = round($actual - $system, 2);

        return \App\Models\FinanceReconciliation::create([
            'account_id' => $account->id,
            'period_start' => null,
            'period_end' => now()->toDateString(),
            'system_balance' => $system,
            'actual_balance' => $actual,
            'difference' => $diff,
            'status' => $diff === 0.0 ? \App\Models\FinanceReconciliation::STATUS_BALANCED : \App\Models\FinanceReconciliation::STATUS_DIFF,
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    /**
     * 单号生成必须跨组织计数：多组织下若带单组织全局作用域，各组织都会从 001
     * 开始，撞上 flow_no 唯一索引。这里显式绕过并做避重循环。
     */
    private function nextFlowNo(): string
    {
        return $this->nextNo('FL', FinanceFlow::class, 'flow_no');
    }

    private function nextPrepayNo(): string
    {
        return $this->nextNo('PP', FinancePrepayment::class, 'prepay_no');
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function nextNo(string $prefix, string $model, string $column): string
    {
        $base = $prefix.date('Ymd');
        $seq = $model::withoutGlobalScope('organization')->whereDate('created_at', now())->count() + 1;
        do {
            $no = $base.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $exists = $model::withoutGlobalScope('organization')->where($column, $no)->exists();
            $seq++;
        } while ($exists);

        return $no;
    }

    /**
     * 删除一笔流水并反向冲销账户余额（红冲）
     * 删除收入 → 余额减少；删除支出 → 余额增加。
     *
     * 预付款流水（CAT_PREPAY）须走预付款取消流程；内部往来镜像流水须走
     * IntercompanyService::cancel() 成对红冲，否则会破坏合并抵消配对关系。
     * $force = true 仅供内部往来作废时成对调用。
     */
    public function remove(FinanceFlow $flow, bool $force = false): void
    {
        if ($flow->category === FinanceFlow::CAT_PREPAY) {
            return;
        }
        if (! $force && $flow->isIntercompany()) {
            return;
        }
        DB::transaction(function () use ($flow) {
            $signed = $flow->direction === FinanceFlow::DIRECTION_EXPENSE
                ? abs((float) $flow->amount)
                : -abs((float) $flow->amount);

            $fresh = FinanceAccount::withoutGlobalScope('organization')
                ->whereKey($flow->account_id)->first();
            if ($fresh) {
                $fresh->forceFill(['balance' => (float) $fresh->balance + $signed])->save();
            }
            $flow->delete();
        });
    }
}
