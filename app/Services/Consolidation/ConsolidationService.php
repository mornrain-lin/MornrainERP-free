<?php

namespace App\Services\Consolidation;

use App\Models\ConsolidationSnapshot;
use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\OrgIntercompanyInventory;
use App\Models\OrgIntercompanyTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrgContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 跨组织合并报表引擎（只读，不改动任何组织独立账簿）。
 *
 * 【合并范围】以某个组织节点为根，取其整棵子树（含自身）。
 *   集团 → 旗下所有法人公司及部门；公司 → 本公司及下属部门。
 *   默认只纳入启用组织（status = 1），停用组织的历史数据不进合并。
 *
 * 【期间】按 occurred_on 取流水；跨度受 config max_period_days 限制。
 *
 * 【抵消规则】—— 本引擎的核心
 *   1. 取合并范围内、期间内的全部流水（显式绕过单组织全局作用域）；
 *   2. 由这些流水的 intercompany_id 反查「已确认」的内部往来登记；
 *      （反查而非按登记日期查询，可避免登记日期与流水日期错配造成误抵）
 *   3. 登记两端都在合并范围内 → 该对镜像流水（付款方支出 + 收款方收入）
 *      成对剔除，抵消额按流水实际金额计；
 *   4. 登记只有一端在范围内 → 保留该端流水，视为对范围外的往来，不抵消；
 *   5. 手工录入、未登记为内部往来的流水一律不抵消。
 *
 * 【币别】按 finance_flows.currency 折算为 base_currency 后相加。
 *   当前系统一律 CNY、rates 为空，等价于直接相加；若出现未配置汇率的外币，
 *   抛出明确异常而非静默算错（财务口径 fail-closed）。
 *
 * 【资金余额】时点值，各组织账户余额直接相加即可，不额外抵消：
 *   内部往来的 A 账户 −X 与 B 账户 +X 在余额层面天然净额为零。
 */
class ConsolidationService
{
    /** 规范化查询期间（默认本月，起止倒置时交换，超长自动截断） */
    public function normalizePeriod(?string $from, ?string $to): array
    {
        $from = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
            ? $from : now()->startOfMonth()->toDateString();
        $to = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
            ? $to : now()->endOfMonth()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $max = (int) config('consolidation.max_period_days', 366);
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > $max) {
            $to = Carbon::parse($from)->addDays($max)->toDateString();
        }

        return [$from, $to];
    }

    /**
     * 解析合并范围。
     *
     * @return array{root: Organization, orgs: Collection<int, Organization>, orgIds: list<int>}
     */
    public function scopeFor(int $rootOrgId, bool $activeOnly = true): array
    {
        $root = Organization::findOrFail($rootOrgId);

        $query = Organization::where(function ($q) use ($root) {
            $q->where('path', $root->path)
                ->orWhere('path', 'like', $root->path.'/%');
        });

        if ($activeOnly) {
            $query->where('status', 1);
        }

        $orgs = $query->orderBy('level')->orderBy('sort')->orderBy('id')->get();

        return [
            'root' => $root,
            'orgs' => $orgs,
            'orgIds' => $orgs->pluck('id')->map('intval')->all(),
        ];
    }

    /**
     * 当前用户可作为合并根节点的组织（防止越权查看兄弟集团）。
     *
     * 刻意不复用 OrgContext::accessibleOrgIds()：该方法对「无归属组织的普通用户」
     * 会回退返回 ROOT，而以 ROOT 为合并根等于看到全集团账簿——这是越权。
     * 因此普通用户必须有明确归属组织，且可选根仅限其归属组织及子树。
     */
    public function rootCandidates(User $user): Collection
    {
        if (OrgContext::canViewAll($user)) {
            return Organization::where('status', 1)
                ->orderBy('level')->orderBy('sort')->orderBy('id')->get();
        }

        $homeId = $user->organization_id ? (int) $user->organization_id : null;
        if ($homeId === null) {
            return collect();
        }
        $home = Organization::find($homeId);
        if (! $home) {
            return collect();
        }

        return Organization::where('status', 1)
            ->where(function ($q) use ($home) {
                $q->where('path', $home->path)
                    ->orWhere('path', 'like', $home->path.'/%');
            })
            ->orderBy('level')->orderBy('sort')->orderBy('id')
            ->get();
    }

    public function userCanUseRoot(User $user, int $rootOrgId): bool
    {
        return $this->rootCandidates($user)->contains(fn (Organization $o) => (int) $o->id === $rootOrgId);
    }

    /**
     * 生成合并报表数据。
     *
     * @return array{
     *     root: Organization, orgs: Collection<int, Organization>, orgIds: list<int>,
     *     from: string, to: string, currency: string,
     *     rows: list<array<string, mixed>>, totals: array<string, float|int>,
     *     categories: list<array<string, mixed>>, monthly: list<array<string, mixed>>,
     *     eliminations: list<array<string, mixed>>, external: list<array<string, mixed>>,
     *     balances: array<int, float>, activeFlows: Collection<int, FinanceFlow>
     * }
     */
    public function build(int $rootOrgId, string $from, string $to, bool $activeOnly = true): array
    {
        [$from, $to] = $this->normalizePeriod($from, $to);
        $scope = $this->scopeFor($rootOrgId, $activeOnly);
        $orgIds = $scope['orgIds'];
        $orgs = $scope['orgs'];
        $orgNames = $orgs->pluck('name', 'id')->all();

        // ---- 1. 范围内、期间内的全部流水（显式绕过单组织作用域） ----
        $flows = FinanceFlow::withoutGlobalScope('organization')
            ->whereIn('organization_id', $orgIds)
            ->whereBetween('occurred_on', [$from, $to])
            ->orderBy('occurred_on')->orderBy('id')
            ->get();
        $flowById = $flows->keyBy('id');

        // ---- 2. 由流水反查已确认的内部往来登记 ----
        $txnIds = $flows->pluck('intercompany_id')->filter()->unique()->values()->all();
        $txns = $txnIds === []
            ? collect()
            : OrgIntercompanyTransaction::whereIn('id', $txnIds)
                ->confirmed()->orderBy('occurred_on')->orderBy('id')->get();

        $eliminatedIds = [];
        $eliminations = [];
        $external = [];

        foreach ($txns as $txn) {
            $outFlow = $txn->from_flow_id ? $flowById->get($txn->from_flow_id) : null;
            $inFlow = $txn->to_flow_id ? $flowById->get($txn->to_flow_id) : null;

            if ($txn->isFullyInside($orgIds)) {
                if (! $outFlow && ! $inFlow) {
                    continue; // 该笔在本期范围内已无流水（如红冲后残留登记）
                }
                if ($outFlow) {
                    $eliminatedIds[$outFlow->id] = true;
                }
                if ($inFlow) {
                    $eliminatedIds[$inFlow->id] = true;
                }
                $eliminations[] = [
                    'txn' => $txn,
                    'from' => $orgNames[$txn->from_organization_id] ?? '—',
                    'to' => $orgNames[$txn->to_organization_id] ?? '—',
                    'category' => OrgIntercompanyTransaction::categoryLabel($txn->category),
                    'amount' => (float) $txn->amount,
                    'eliminated_expense' => $outFlow ? $this->amountOf($outFlow) : 0.0,
                    'eliminated_income' => $inFlow ? $this->amountOf($inFlow) : 0.0,
                ];
            } elseif ($txn->isPartiallyInside($orgIds)) {
                $external[] = [
                    'txn' => $txn,
                    'from' => $orgNames[$txn->from_organization_id] ?? '（范围外）',
                    'to' => $orgNames[$txn->to_organization_id] ?? '（范围外）',
                    'amount' => (float) $txn->amount,
                    'inside_org' => $orgNames[$txn->from_organization_id] ?? $orgNames[$txn->to_organization_id] ?? '—',
                ];
            }
        }

        $activeFlows = $flows->reject(fn (FinanceFlow $f) => isset($eliminatedIds[$f->id]))->values();
        $eliminatedFlows = $flows->filter(fn (FinanceFlow $f) => isset($eliminatedIds[$f->id]))->values();

        // ---- 3. 逐组织：毛口径 / 抵消额 / 合并数 ----
        $rows = [];
        foreach ($orgs as $org) {
            $id = (int) $org->id;
            $all = $flows->filter(fn (FinanceFlow $f) => (int) $f->organization_id === $id);
            $elim = $all->filter(fn (FinanceFlow $f) => isset($eliminatedIds[$f->id]));
            $kept = $activeFlows->filter(fn (FinanceFlow $f) => (int) $f->organization_id === $id);

            $grossIn = $this->sumDirection($all, FinanceFlow::DIRECTION_INCOME);
            $grossOut = $this->sumDirection($all, FinanceFlow::DIRECTION_EXPENSE);
            $elimIn = $this->sumDirection($elim, FinanceFlow::DIRECTION_INCOME);
            $elimOut = $this->sumDirection($elim, FinanceFlow::DIRECTION_EXPENSE);
            $netIn = round($grossIn - $elimIn, 2);
            $netOut = round($grossOut - $elimOut, 2);

            $rows[] = [
                'org' => $org,
                'gross_income' => $grossIn,
                'gross_expense' => $grossOut,
                'eliminated_income' => $elimIn,
                'eliminated_expense' => $elimOut,
                'income' => $netIn,
                'expense' => $netOut,
                'net' => round($netIn - $netOut, 2),
                'flow_count' => $kept->count(),
                'balance' => 0.0, // 下方账户余额汇总回填
            ];
        }

        // ---- 4. 资金账户余额（时点值，直接相加不抵消） ----
        $accounts = FinanceAccount::withoutGlobalScope('organization')
            ->whereIn('organization_id', $orgIds)->get();
        $balances = [];
        foreach ($orgIds as $id) {
            $balances[$id] = round((float) $accounts
                ->filter(fn ($a) => (int) $a->organization_id === $id)
                ->sum('balance'), 2);
        }
        foreach ($rows as &$row) {
            $row['balance'] = $balances[(int) $row['org']->id] ?? 0.0;
        }
        unset($row);

        // ---- 4.5 少数股东权益 / 内部未实现利润 / 跨期抵消（深化口径） ----
        // 少数股东：按「集团持股比例」拆分各子公司净利润
        $minorityItems = [];
        $totalMinority = 0.0;
        foreach ($rows as &$row) {
            $org = $row['org'];
            $groupOwn = (float) $org->groupOwnershipPct();
            $row['group_ownership_pct'] = $groupOwn;
            $row['ownership_pct'] = (float) ($org->ownership_pct ?? 100);
            $minorityPct = max(0.0, (100.0 - $groupOwn) / 100.0);
            $row['minority_net'] = round($row['net'] * $minorityPct, 2);
            if ($row['minority_net'] != 0.0) {
                $minorityItems[] = [
                    'org_id' => (int) $org->id,
                    'org_name' => $org->name,
                    'type' => $org->typeLabel(),
                    'group_ownership_pct' => $groupOwn,
                    'net' => $row['net'],
                    'minority_net' => $row['minority_net'],
                ];
                $totalMinority += $row['minority_net'];
            }
        }
        unset($row);
        $totalMinority = round($totalMinority, 2);

        // 内部未实现利润（存货）：期末仍留存在下游的内部交易毛利需抵消
        $invEntries = OrgIntercompanyInventory::inPeriod($from, $to)
            ->whereIn('seller_org_id', $orgIds)
            ->whereIn('buyer_org_id', $orgIds)
            ->get();
        $unrealizedItems = [];
        $closingUnrealized = 0.0;
        foreach ($invEntries as $inv) {
            // 只有双方都在合并范围内才抵消；仅一端在内视为对外交易（已实现）
            if (! (in_array((int) $inv->seller_org_id, $orgIds, true)
                && in_array((int) $inv->buyer_org_id, $orgIds, true))) {
                continue;
            }
            $elim = (float) $inv->unrealized_profit;
            $closingUnrealized += $elim;
            $unrealizedItems[] = [
                'id' => (int) $inv->id,
                'seller' => $orgNames[$inv->seller_org_id] ?? '—',
                'buyer' => $orgNames[$inv->buyer_org_id] ?? '—',
                'amount' => (float) $inv->amount,
                'cost' => (float) $inv->cost,
                'profit' => (float) $inv->profit,
                'unsold_ratio' => (float) $inv->unsold_ratio,
                'unrealized_profit' => $elim,
            ];
        }
        $closingUnrealized = round($closingUnrealized, 2);

        // 跨期抵消：读取上一期快照留存的「期末未实现利润」作为本期期初；
        // 期初 > 期末（存货对外售出）的差额即本期实现，加回合并净利润。
        $openingUnrealized = 0.0;
        $prior = ConsolidationSnapshot::where('root_org_id', $rootOrgId)
            ->where('period_to', '<', $from)
            ->orderByDesc('period_to')
            ->first();
        if ($prior && is_array($prior->report_data)) {
            $openingUnrealized = (float) ($prior->report_data['closing_unrealized'] ?? 0.0);
        }
        $realized = round(max(0.0, $openingUnrealized - $closingUnrealized), 2);

        // ---- 5. 分类科目汇总（抵消后） ----
        $grossByCat = [];
        $elimByCat = [];
        foreach ($flows as $f) {
            $key = $f->direction.'|'.$f->category;
            $grossByCat[$key] = round(($grossByCat[$key] ?? 0) + $this->amountOf($f), 2);
        }
        foreach ($eliminatedFlows as $f) {
            $key = $f->direction.'|'.$f->category;
            $elimByCat[$key] = round(($elimByCat[$key] ?? 0) + $this->amountOf($f), 2);
        }
        $categories = [];
        foreach ($grossByCat as $key => $gross) {
            [$direction, $category] = explode('|', $key, 2);
            $elim = $elimByCat[$key] ?? 0.0;
            $categories[] = [
                'direction' => $direction,
                'direction_label' => FinanceFlow::directionLabel($direction),
                'label' => FinanceFlow::categoryLabel($category),
                'gross' => $gross,
                'eliminated' => $elim,
                'net' => round($gross - $elim, 2),
            ];
        }
        usort($categories, fn ($a, $b) => ($a['direction'] === $b['direction'])
            ? $b['net'] <=> $a['net']
            : ($a['direction'] === FinanceFlow::DIRECTION_INCOME ? -1 : 1));

        // ---- 6. 月度趋势（抵消后） ----
        $monthly = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $endMonth = Carbon::parse($to)->startOfMonth();
        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m');
            $inMonth = $activeFlows->filter(fn (FinanceFlow $f) => $f->occurred_on?->format('Y-m') === $key);
            $in = $this->sumDirection($inMonth, FinanceFlow::DIRECTION_INCOME);
            $out = $this->sumDirection($inMonth, FinanceFlow::DIRECTION_EXPENSE);
            $monthly[] = [
                'month' => $cursor->format('y年m月'),
                'key' => $key,
                'income' => $in,
                'expense' => $out,
                'net' => round($in - $out, 2),
            ];
            $cursor->addMonth();
        }

        // ---- 7. 合计 ----
        $grossIncome = round(array_sum(array_column($rows, 'gross_income')), 2);
        $grossExpense = round(array_sum(array_column($rows, 'gross_expense')), 2);
        $elimIncome = round(array_sum(array_column($rows, 'eliminated_income')), 2);
        $elimExpense = round(array_sum(array_column($rows, 'eliminated_expense')), 2);
        $income = round($grossIncome - $elimIncome, 2);
        $expense = round($grossExpense - $elimExpense, 2);

        $totals = [
            'gross_income' => $grossIncome,
            'gross_expense' => $grossExpense,
            'eliminated_income' => $elimIncome,
            'eliminated_expense' => $elimExpense,
            'income' => $income,
            'expense' => $expense,
            'net' => round($income - $expense, 2),
            'gross_net' => round($grossIncome - $grossExpense, 2),
            'flow_count' => $activeFlows->count(),
            'eliminated_count' => count($eliminations),
            'balance' => round(array_sum($balances), 2),
            'org_count' => count($rows),
        ];

        // 合并深化调整：净利润 = 基础净额 − 期末未实现利润 + 本期实现
        $consolidatedNet = round((float) $totals['net'] - $closingUnrealized + $realized, 2);
        $totals['net'] = $consolidatedNet;
        $totals['minority_interest'] = $totalMinority;
        $totals['net_attributable'] = round($consolidatedNet - $totalMinority, 2);
        $totals['unrealized_opening'] = $openingUnrealized;
        $totals['unrealized_closing'] = $closingUnrealized;
        $totals['unrealized_realized'] = $realized;

        return [
            'root' => $scope['root'],
            'orgs' => $orgs,
            'orgIds' => $orgIds,
            'from' => $from,
            'to' => $to,
            'currency' => (string) config('consolidation.base_currency', 'CNY'),
            'rows' => $rows,
            'totals' => $totals,
            'categories' => $categories,
            'monthly' => $monthly,
            'eliminations' => $eliminations,
            'external' => $external,
            'balances' => $balances,
            'activeFlows' => $activeFlows,
            'minority' => [
                'items' => $minorityItems,
                'total' => $totalMinority,
                'attributable_to_parent' => $totals['net_attributable'] ?? (float) ($totals['net'] ?? 0),
            ],
            'unrealized' => [
                'items' => $unrealizedItems,
                'opening' => $openingUnrealized,
                'closing' => $closingUnrealized,
                'realized' => $realized,
                'elimination' => $closingUnrealized,
            ],
        ];
    }

    /**
     * 保存合并快照：构建报表并把可序列化摘要写入 consolidation_snapshots。
     */
    public function snapshot(int $rootOrgId, string $from, string $to, ?int $userId): ConsolidationSnapshot
    {
        [$from, $to] = $this->normalizePeriod($from, $to);
        $report = $this->build($rootOrgId, $from, $to);
        $data = $this->snapshotData($report);

        return ConsolidationSnapshot::create([
            'root_org_id' => $rootOrgId,
            'period_from' => $from,
            'period_to' => $to,
            'generated_by' => $userId,
            'report_data' => $data,
        ]);
    }

    /** 把含模型的报表转换成纯数组（可 JSON 序列化、跨库一致） */
    public function snapshotData(array $report): array
    {
        return [
            'root_org_id' => (int) $report['root']->id,
            'root_name' => $report['root']->name,
            'from' => $report['from'],
            'to' => $report['to'],
            'currency' => $report['currency'],
            'totals' => $report['totals'],
            'minority' => $report['minority'],
            'unrealized' => $report['unrealized'],
            'closing_unrealized' => $report['unrealized']['closing'],
            'rows' => array_map(function ($r) {
                return [
                    'org_id' => (int) $r['org']->id,
                    'org_name' => $r['org']->name,
                    'type' => $r['org']->typeLabel(),
                    'gross_income' => $r['gross_income'],
                    'gross_expense' => $r['gross_expense'],
                    'income' => $r['income'],
                    'expense' => $r['expense'],
                    'net' => $r['net'],
                    'balance' => $r['balance'],
                    'group_ownership_pct' => $r['group_ownership_pct'] ?? 100.0,
                    'minority_net' => $r['minority_net'] ?? 0.0,
                ];
            }, $report['rows']),
            'eliminations' => array_map(fn ($e) => [
                'txn_no' => $e['txn']->txn_no,
                'from' => $e['from'],
                'to' => $e['to'],
                'category' => $e['category'],
                'amount' => $e['amount'],
            ], $report['eliminations']),
            'external' => array_map(fn ($e) => [
                'txn_no' => $e['txn']->txn_no,
                'from' => $e['from'],
                'to' => $e['to'],
                'amount' => $e['amount'],
            ], $report['external']),
        ];
    }

    /** 某合并根节点的全部快照（最新在前） */
    public function snapshotsFor(int $rootOrgId): Collection
    {
        return ConsolidationSnapshot::where('root_org_id', $rootOrgId)
            ->orderByDesc('period_to')->orderByDesc('id')->get();
    }

    /** 按方向汇总（自动折算本位币） */
    private function sumDirection(Collection $flows, string $direction): float
    {
        return round((float) $flows
            ->filter(fn (FinanceFlow $f) => $f->direction === $direction)
            ->sum(fn (FinanceFlow $f) => $this->amountOf($f)), 2);
    }

    private function amountOf(FinanceFlow $flow): float
    {
        return $this->convert((float) $flow->amount, (string) ($flow->currency ?: 'CNY'));
    }

    /** 折算为记账本位币；未配置汇率时 fail-closed，绝不静默算错 */
    private function convert(float $value, string $currency): float
    {
        $base = (string) config('consolidation.base_currency', 'CNY');
        if ($currency === $base) {
            return round($value, 2);
        }

        $rate = config('consolidation.rates.'.$currency);
        if ($rate === null || (float) $rate <= 0) {
            throw new RuntimeException(
                "合并核算缺少币别 {$currency} → {$base} 的汇率，请在 config/consolidation.php 的 rates 中配置后重试。"
            );
        }

        return round($value * (float) $rate, 2);
    }
}
