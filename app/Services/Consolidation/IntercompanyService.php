<?php

namespace App\Services\Consolidation;

use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\OrgIntercompanyTransaction;
use App\Models\Organization;
use App\Services\Finance\FinanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 组织间内部往来服务。
 *
 * 【会计口径 · 镜像流水法】
 * 登记一笔 A 组织付给 B 组织的内部往来，会生成两条金额相等、方向相反的流水：
 *   - A 组织：expense / intercompany_out  （A 的账户余额减少）
 *   - B 组织：income  / intercompany_in   （B 的账户余额增加）
 * 两条流水各自带上 organization_id 与同一个 intercompany_id。
 *
 * 这样一来：
 *   - 各组织独立账簿仍然平衡、账实相符，单组织报表完全正常；
 *   - 集团合并时，ConsolidationService 把「两端都在合并范围内」的这一对
 *     流水成对剔除，消除内部交易虚增的收入与支出（净额不受影响）；
 *   - 若合并范围只覆盖其中一端，则保留该端流水，视为对范围外的往来。
 *
 * 作废 = 成对红冲：反向冲销两条流水对应的账户余额后删除流水，登记置为
 * canceled，合并报表随即不再抵消该笔，历史审计记录仍保留。
 */
class IntercompanyService
{
    public function __construct(private FinanceService $finance) {}

    /**
     * 登记一笔内部往来。
     *
     * @param  array{
     *     from_organization_id: int, to_organization_id: int,
     *     from_account_id: int, to_account_id: int,
     *     amount: float|string, occurred_on?: string,
     *     category?: string, remark?: string
     * }  $data
     *
     * @throws ValidationException
     */
    public function record(array $data, ?int $userId = null): OrgIntercompanyTransaction
    {
        $fromOrgId = (int) ($data['from_organization_id'] ?? 0);
        $toOrgId = (int) ($data['to_organization_id'] ?? 0);
        $fromAccountId = (int) ($data['from_account_id'] ?? 0);
        $toAccountId = (int) ($data['to_account_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $occurredOn = (string) ($data['occurred_on'] ?? now()->toDateString());
        $category = (string) ($data['category'] ?? OrgIntercompanyTransaction::CAT_TRANSFER);
        $remark = $data['remark'] ?? null;

        $this->assertValid(
            $fromOrgId, $toOrgId, $fromAccountId, $toAccountId,
            $amount, $occurredOn, $category
        );

        $fromAccount = FinanceAccount::withoutGlobalScope('organization')->findOrFail($fromAccountId);
        $toAccount = FinanceAccount::withoutGlobalScope('organization')->findOrFail($toAccountId);

        return DB::transaction(function () use (
            $fromOrgId, $toOrgId, $fromAccount, $toAccount,
            $amount, $occurredOn, $category, $remark, $userId
        ) {
            $txn = OrgIntercompanyTransaction::create([
                'txn_no' => $this->nextTxnNo(),
                'from_organization_id' => $fromOrgId,
                'to_organization_id' => $toOrgId,
                'amount' => $amount,
                'currency' => config('consolidation.base_currency', 'CNY'),
                'occurred_on' => $occurredOn,
                'category' => $category,
                'status' => OrgIntercompanyTransaction::STATUS_CONFIRMED,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'remark' => $remark,
                'created_by' => $userId,
            ]);

            $label = OrgIntercompanyTransaction::categoryLabel($category);

            $outFlow = $this->finance->record(
                $fromAccount,
                FinanceFlow::DIRECTION_EXPENSE,
                config('consolidation.intercompany.expense_category', FinanceFlow::CAT_INTERCOMPANY_OUT),
                $amount,
                $occurredOn,
                '内部往来付出（'.$label.'）：'.$remark,
                'intercompany',
                $txn->id,
                $userId,
                $fromOrgId,
                $txn->id,
            );

            $inFlow = $this->finance->record(
                $toAccount,
                FinanceFlow::DIRECTION_INCOME,
                config('consolidation.intercompany.income_category', FinanceFlow::CAT_INTERCOMPANY_IN),
                $amount,
                $occurredOn,
                '内部往来收入（'.$label.'）：'.$remark,
                'intercompany',
                $txn->id,
                $userId,
                $toOrgId,
                $txn->id,
            );

            $txn->forceFill([
                'from_flow_id' => $outFlow->id,
                'to_flow_id' => $inFlow->id,
            ])->save();

            return $txn->refresh();
        });
    }

    /** 作废：成对红冲镜像流水并回滚账户余额（保留登记记录用于审计） */
    public function cancel(OrgIntercompanyTransaction $txn): OrgIntercompanyTransaction
    {
        if (! $txn->isConfirmed()) {
            return $txn;
        }

        return DB::transaction(function () use ($txn) {
            foreach ([$txn->from_flow_id, $txn->to_flow_id] as $flowId) {
                if (! $flowId) {
                    continue;
                }
                $flow = FinanceFlow::withoutGlobalScope('organization')->find($flowId);
                if ($flow) {
                    $this->finance->remove($flow, true);
                }
            }

            $txn->forceFill([
                'status' => OrgIntercompanyTransaction::STATUS_CANCELED,
                'from_flow_id' => null,
                'to_flow_id' => null,
            ])->save();

            return $txn->refresh();
        });
    }

    /** @throws ValidationException */
    private function assertValid(
        int $fromOrgId,
        int $toOrgId,
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        string $occurredOn,
        string $category,
    ): void {
        $errors = [];

        if ($fromOrgId <= 0 || $toOrgId <= 0) {
            $errors['from_organization_id'] = '请选择付款方与收款方组织';
        } elseif ($fromOrgId === $toOrgId) {
            $errors['to_organization_id'] = '内部往来的付款方与收款方不能是同一组织';
        } elseif (! Organization::whereKey($fromOrgId)->exists() || ! Organization::whereKey($toOrgId)->exists()) {
            $errors['to_organization_id'] = '所选组织不存在';
        }

        if ($amount <= 0) {
            $errors['amount'] = '金额必须大于 0';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurredOn)) {
            $errors['occurred_on'] = '发生日期格式应为 YYYY-MM-DD';
        }
        if (! array_key_exists($category, config('consolidation.categories', []))) {
            $errors['category'] = '未知的内部往来类型';
        }

        // 账户必须归属对应组织：否则镜像流水会记到别人的账簿上
        if (! $errors) {
            $fromAccount = FinanceAccount::withoutGlobalScope('organization')->find($fromAccountId);
            $toAccount = FinanceAccount::withoutGlobalScope('organization')->find($toAccountId);

            if (! $fromAccount || (int) $fromAccount->organization_id !== $fromOrgId) {
                $errors['from_account_id'] = '付款账户不存在，或不属于所选付款方组织';
            }
            if (! $toAccount || (int) $toAccount->organization_id !== $toOrgId) {
                $errors['to_account_id'] = '收款账户不存在，或不属于所选收款方组织';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function nextTxnNo(): string
    {
        $base = 'IC'.date('Ymd');
        $seq = OrgIntercompanyTransaction::whereDate('created_at', now())->count() + 1;
        do {
            $no = $base.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $exists = OrgIntercompanyTransaction::where('txn_no', $no)->exists();
            $seq++;
        } while ($exists);

        return $no;
    }
}
