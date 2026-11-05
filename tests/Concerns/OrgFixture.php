<?php

namespace Tests\Concerns;

use App\Models\ConsolidationSnapshot;
use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\OrgIntercompanyInventory;
use App\Models\OrgIntercompanyTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Services\Consolidation\ConsolidationService;
use App\Services\Consolidation\IntercompanyService;
use App\Services\Finance\FinanceService;
use App\Support\OrgContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 合并核算测试夹具。
 *
 * 项目测试不启用 RefreshDatabase，直接连已迁移的 SQLite 库，因此每个用例
 * 都用 CONS_ 前缀自建组织树并在 tearDown 精确清理，互不污染、也不触碰
 * 库里的既有业务数据。所有写入都显式指定 organization_id，避免受
 * OrgContext 静态状态影响。
 */
trait OrgFixture
{
    protected function orgPeriod(): array
    {
        return ['2026-11-01', '2026-11-30'];
    }

    protected function orgDay(): string
    {
        return '2026-11-05';
    }

    /** 建一棵「集团 → 甲公司 / 乙公司」并各配一个资金账户 */
    protected function orgFixture(): array
    {
        $s = Str::lower(Str::random(5));

        $group = $this->orgMake("CONS_G_{$s}", '测试集团', Organization::TYPE_GROUP, null);
        $a = $this->orgMake("CONS_A_{$s}", '甲公司', Organization::TYPE_COMPANY, $group->id);
        $b = $this->orgMake("CONS_B_{$s}", '乙公司', Organization::TYPE_COMPANY, $group->id);

        return [
            'group' => $group,
            'a' => $a,
            'b' => $b,
            'accA' => $this->orgAccount('CONS账户A_'.$s, (int) $a->id),
            'accB' => $this->orgAccount('CONS账户B_'.$s, (int) $b->id),
        ];
    }

    protected function orgMake(string $code, string $name, string $type, ?int $parentId): Organization
    {
        $org = Organization::create([
            'parent_id' => $parentId,
            'type' => $type,
            'name' => $name,
            'code' => $code,
            'sort' => 0,
            'status' => 1,
        ]);
        $org->computePath();
        $org->saveQuietly();

        return $org->refresh();
    }

    protected function orgAccount(string $name, int $orgId): FinanceAccount
    {
        return FinanceAccount::create([
            'name' => $name,
            'type' => FinanceAccount::TYPE_BANK,
            'balance' => 0,
            'is_active' => true,
            'organization_id' => $orgId,
        ]);
    }

    protected function orgFlow(
        FinanceAccount $account,
        int $orgId,
        string $direction,
        float $amount,
        string $category = FinanceFlow::CAT_SALES,
    ): FinanceFlow {
        return app(FinanceService::class)->record(
            $account, $direction, $category, $amount,
            $this->orgDay(), '合并核算测试', 'manual', null, null, $orgId,
        );
    }

    /** 两家公司各记一笔外部收入 + 一笔外部支出 */
    protected function orgBaseFlows(array $f): void
    {
        $this->orgFlow($f['accA'], (int) $f['a']->id, FinanceFlow::DIRECTION_INCOME, 1000);
        $this->orgFlow($f['accA'], (int) $f['a']->id, FinanceFlow::DIRECTION_EXPENSE, 300, FinanceFlow::CAT_PURCHASE);
        $this->orgFlow($f['accB'], (int) $f['b']->id, FinanceFlow::DIRECTION_INCOME, 500);
        $this->orgFlow($f['accB'], (int) $f['b']->id, FinanceFlow::DIRECTION_EXPENSE, 200, FinanceFlow::CAT_PURCHASE);
    }

    /** 甲 → 乙 的内部往来（生成镜像流水） */
    protected function orgIntercompany(array $f, float $amount = 400): OrgIntercompanyTransaction
    {
        return app(IntercompanyService::class)->record([
            'from_organization_id' => (int) $f['a']->id,
            'to_organization_id' => (int) $f['b']->id,
            'from_account_id' => (int) $f['accA']->id,
            'to_account_id' => (int) $f['accB']->id,
            'amount' => $amount,
            'occurred_on' => $this->orgDay(),
            'category' => OrgIntercompanyTransaction::CAT_TRANSFER,
            'remark' => '合并核算测试',
        ]);
    }

    /**
     * 甲 → 乙 的存货内部交易（未实现利润来源）。
     * unrealized_profit = (amount − cost) × unsold_ratio
     */
    protected function orgInventory(array $f, float $amount, float $cost, float $unsoldRatio): OrgIntercompanyInventory
    {
        [$from, $to] = $this->orgPeriod();
        $profit = round($amount - $cost, 2);

        return OrgIntercompanyInventory::create([
            'period_from' => $from,
            'period_to' => $to,
            'seller_org_id' => (int) $f['a']->id,
            'buyer_org_id' => (int) $f['b']->id,
            'amount' => $amount,
            'cost' => $cost,
            'profit' => $profit,
            'unsold_ratio' => $unsoldRatio,
            'unrealized_profit' => round($profit * $unsoldRatio, 2),
            'note' => '合并核算测试',
        ]);
    }

    protected function orgReport(int $rootId): array
    {
        [$from, $to] = $this->orgPeriod();

        return app(ConsolidationService::class)->build($rootId, $from, $to);
    }

    protected function orgAdmin(): User
    {
        return User::where('role', User::ROLE_ADMIN)->first()
            ?? User::create([
                'name' => '合并测试管理员',
                'email' => 'cons-admin@mornrain.local',
                'password' => User::hashPassword('admin1234'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);
    }

    protected function orgStaff(): User
    {
        return User::create([
            'name' => '合并测试运营',
            'email' => 'cons-staff-'.Str::lower(Str::random(6)).'@mornrain.local',
            'password' => User::hashPassword('staff1234'),
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
    }

    /** 精确清理本套夹具产生的数据（不触碰库中其它业务数据） */
    protected function orgCleanup(): void
    {
        $orgIds = Organization::where('code', 'like', 'CONS_%')
            ->pluck('id')->map('intval')->all();

        if ($orgIds !== []) {
            $txnIds = DB::table('org_intercompany_transactions')
                ->whereIn('from_organization_id', $orgIds)
                ->orWhereIn('to_organization_id', $orgIds)
                ->pluck('id')->map('intval')->all();

            if ($txnIds !== []) {
                DB::table('finance_flows')->whereIn('intercompany_id', $txnIds)->delete();
                DB::table('org_intercompany_transactions')->whereIn('id', $txnIds)->delete();
            }
            DB::table('finance_flows')->whereIn('organization_id', $orgIds)->delete();
            DB::table('finance_accounts')->whereIn('organization_id', $orgIds)->delete();
            DB::table('org_intercompany_inventory')
                ->whereIn('seller_org_id', $orgIds)
                ->orWhereIn('buyer_org_id', $orgIds)
                ->delete();
            DB::table('consolidation_snapshots')->whereIn('root_org_id', $orgIds)->delete();
            Organization::whereIn('id', $orgIds)->delete();
        }

        User::where('email', 'like', 'cons-%@mornrain.local')->delete();
        OrgContext::reset();
    }
}
