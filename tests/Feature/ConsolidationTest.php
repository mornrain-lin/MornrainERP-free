<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrgIntercompanyInventory;
use App\Services\Consolidation\ConsolidationService;
use App\Support\OrgContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\OrgFixture;
use Tests\TestCase;

/**
 * 跨组织合并报表回归测试。
 *
 * 验证核心会计性质：
 *  - 无内部往来时，合并数 = 各组织简单相加；
 *  - 两端都在合并范围内时，镜像流水成对抵消，净额与资金余额合计不变；
 *  - 只有一端在范围内时不抵消，并提示范围外往来；
 *  - 作废红冲后撤销抵消；
 *  - 权限与合并根范围校验、CSV 导出、外币缺汇率 fail-closed。
 */
class ConsolidationTest extends TestCase
{
    use OrgFixture;

    protected function setUp(): void
    {
        parent::setUp();
        OrgContext::reset();
    }

    protected function tearDown(): void
    {
        $this->orgCleanup();
        parent::tearDown();
    }

    public function test_no_intercompany_consolidation_is_plain_sum(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);

        $r = $this->orgReport((int) $f['group']->id);
        $t = $r['totals'];

        $this->assertSame(3, $t['org_count'], '合并范围应含集团 + 甲 + 乙');
        $this->assertSame(1500.0, $t['gross_income']);
        $this->assertSame(500.0, $t['gross_expense']);
        $this->assertSame(0.0, $t['eliminated_income']);
        $this->assertSame(0.0, $t['eliminated_expense']);
        $this->assertSame(1500.0, $t['income']);
        $this->assertSame(500.0, $t['expense']);
        $this->assertSame(1000.0, $t['net']);
        $this->assertSame(4, $t['flow_count']);
        $this->assertSame(1000.0, $t['balance'], '账户余额合计 = 期间净额');
        $this->assertCount(0, $r['eliminations']);
    }

    public function test_intercompany_is_eliminated_when_both_ends_in_scope(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);
        $this->orgIntercompany($f, 400);

        $r = $this->orgReport((int) $f['group']->id);
        $t = $r['totals'];

        // 毛口径被内部往来虚增了 400 收入 + 400 支出
        $this->assertSame(1900.0, $t['gross_income']);
        $this->assertSame(900.0, $t['gross_expense']);
        // 成对抵消
        $this->assertSame(400.0, $t['eliminated_income']);
        $this->assertSame(400.0, $t['eliminated_expense']);
        $this->assertSame(1500.0, $t['income']);
        $this->assertSame(500.0, $t['expense']);
        // 净额与余额合计不受抵消影响
        $this->assertSame(1000.0, $t['net']);
        $this->assertSame(1000.0, $t['balance']);
        $this->assertSame(4, $t['flow_count'], '抵消后只剩 4 笔外部流水');
        $this->assertSame(1, $t['eliminated_count']);
        $this->assertCount(1, $r['eliminations']);
        $this->assertCount(0, $r['external']);

        // 内部往来科目在合并数中归零
        $internal = collect($r['categories'])->firstWhere('label', '内部往来收入');
        $this->assertNotNull($internal);
        $this->assertSame(400.0, $internal['gross']);
        $this->assertSame(400.0, $internal['eliminated']);
        $this->assertSame(0.0, $internal['net']);
    }

    public function test_partially_in_scope_intercompany_is_not_eliminated(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);
        $this->orgIntercompany($f, 400);

        // 只合并甲公司：乙公司是范围外往来方，甲的 400 支出保留
        $r = $this->orgReport((int) $f['a']->id);
        $t = $r['totals'];

        $this->assertSame(1, $t['org_count']);
        $this->assertSame(1000.0, $t['income']);
        $this->assertSame(700.0, $t['expense'], '300 外部支出 + 400 对范围外支出');
        $this->assertSame(300.0, $t['net']);
        $this->assertSame(0.0, $t['eliminated_expense']);
        $this->assertSame(0, $t['eliminated_count']);
        $this->assertCount(1, $r['external']);
    }

    public function test_cancel_reverses_elimination_and_restores_balances(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);
        $txn = $this->orgIntercompany($f, 400);

        app(\App\Services\Consolidation\IntercompanyService::class)->cancel($txn);

        $t = $this->orgReport((int) $f['group']->id)['totals'];
        $this->assertSame(1500.0, $t['gross_income'], '红冲后镜像流水已删除');
        $this->assertSame(500.0, $t['gross_expense']);
        $this->assertSame(0.0, $t['eliminated_income']);
        $this->assertSame(1500.0, $t['income']);
        $this->assertSame(1000.0, $t['net']);
        $this->assertSame(1000.0, $t['balance'], '账户余额已回滚到往来登记前');
        $this->assertSame(4, $t['flow_count']);
    }

    public function test_report_page_requires_consolidation_permission(): void
    {
        $this->actingAs($this->orgStaff())
            ->get(route('consolidation.index'))
            ->assertStatus(403);
    }

    public function test_report_page_rejects_root_outside_accessible_scope(): void
    {
        $this->actingAs($this->orgAdmin())
            ->get(route('consolidation.index', ['root_org_id' => 99999999]))
            ->assertStatus(403);
    }

    public function test_admin_can_open_report_and_export_csv(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);

        $this->actingAs($this->orgAdmin())
            ->get(route('consolidation.index', ['root_org_id' => $f['group']->id]))
            ->assertStatus(200)
            ->assertSee('抵消桥')
            ->assertSee('合并净额');

        $params = [
            'root_org_id' => $f['group']->id,
            'date_from' => $this->orgPeriod()[0],
            'date_to' => $this->orgPeriod()[1],
        ];
        $this->actingAs($this->orgAdmin())
            ->get(route('consolidation.export', $params))
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_user_without_home_org_has_no_consolidation_root(): void
    {
        $staff = $this->orgStaff();
        $this->assertNull($staff->organization_id);

        $svc = app(ConsolidationService::class);
        $this->assertTrue($svc->rootCandidates($staff)->isEmpty(),
            '无归属组织的普通用户不得回退到 ROOT，否则等于看到全集团账簿');

        $rootId = (int) (Organization::where('code', 'ROOT')->value('id') ?? 0);
        $this->assertFalse($svc->userCanUseRoot($staff, $rootId));
    }

    public function test_user_with_home_org_can_only_root_in_own_subtree(): void
    {
        $f = $this->orgFixture();
        $staff = $this->orgStaff();
        $staff->forceFill(['organization_id' => $f['a']->id])->save();

        $svc = app(ConsolidationService::class);
        $ids = $svc->rootCandidates($staff)->pluck('id')->map('intval')->all();

        $this->assertContains((int) $f['a']->id, $ids);
        $this->assertNotContains((int) $f['b']->id, $ids, '不得窥探兄弟公司');
        $this->assertNotContains((int) $f['group']->id, $ids, '不得上移到整个集团');
        $this->assertTrue($svc->userCanUseRoot($staff, (int) $f['a']->id));
        $this->assertFalse($svc->userCanUseRoot($staff, (int) $f['b']->id));
    }

    public function test_missing_exchange_rate_fails_closed(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);

        // 伪造一条未配置汇率的外币流水：财务口径必须报错，不能静默按 1:1 相加
        DB::table('finance_flows')
            ->where('organization_id', $f['a']->id)
            ->limit(1)
            ->update(['currency' => 'USD']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('缺少币别 USD');

        $this->orgReport((int) $f['group']->id);
    }

    public function test_minority_interest_splits_subsidiary_profit(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);

        // 甲为母公司 80% 控股子公司：其净利润的 20% 属少数股东
        $f['a']->update(['ownership_pct' => 80]);

        $r = $this->orgReport((int) $f['group']->id);
        $t = $r['totals'];

        // 甲净利 = 1000 − 300 = 700；乙净利 = 500 − 200 = 300；集团本部 0
        $this->assertSame(1000.0, $t['net'], '合并净利润（含少数股东）仍按相加口径');
        $this->assertSame(140.0, $t['minority_interest'], '少数股东损益 = 20% × 700');
        $this->assertSame(860.0, $t['net_attributable'], '归母净利润 = 1000 − 140');

        $aRow = collect($r['rows'])->firstWhere('org.id', $f['a']->id);
        $this->assertNotNull($aRow);
        $this->assertSame(80.0, $aRow['group_ownership_pct']);
        $this->assertSame(140.0, $aRow['minority_net']);

        $this->assertCount(1, $r['minority']['items'], '仅甲有少数股东损益');
    }

    public function test_unrealized_internal_profit_is_eliminated(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);

        // 甲卖给乙存货：售价 1000、成本 700、毛利 300，期末 50% 未售出
        // → 未实现利润 = 300 × 50% = 150，须从合并净利润中抵减
        $this->orgInventory($f, 1000, 700, 0.5);

        $r = $this->orgReport((int) $f['group']->id);
        $t = $r['totals'];

        $this->assertSame(150.0, $t['unrealized_closing']);
        $this->assertSame(0.0, $t['unrealized_realized']);
        $this->assertSame(850.0, $t['net'], '合并净利润 = 基础净额 1000 − 未实现 150');
        $this->assertCount(1, $r['unrealized']['items']);
    }

    public function test_inter_period_realizes_prior_unrealized(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f); // 11 月流水

        $svc = app(ConsolidationService::class);

        // 一期（10 月）：甲→乙存货，100% 未售出 → 期末未实现 = (1000−700)×100% = 300
        [$octFrom, $octTo] = ['2026-10-01', '2026-10-31'];
        OrgIntercompanyInventory::create([
            'period_from' => $octFrom,
            'period_to' => $octTo,
            'seller_org_id' => (int) $f['a']->id,
            'buyer_org_id' => (int) $f['b']->id,
            'amount' => 1000, 'cost' => 700, 'profit' => 300,
            'unsold_ratio' => 1.0, 'unrealized_profit' => 300,
            'note' => '一期未实现',
        ]);
        // 固化一期快照（跨期抵消将读取其期末未实现利润）
        $svc->snapshot((int) $f['group']->id, $octFrom, $octTo, null);

        // 二期（11 月）：甲→乙存货全部售出（未售 0%）→ 期末未实现 = 0
        $this->orgInventory($f, 1000, 700, 0.0);

        $r = $this->orgReport((int) $f['group']->id); // 11 月
        $t = $r['totals'];

        $this->assertSame(300.0, $t['unrealized_opening'], '期初 = 一期期末未实现');
        $this->assertSame(0.0, $t['unrealized_closing']);
        $this->assertSame(300.0, $t['unrealized_realized'], '本期实现 = 期初 − 期末 = 300');
        $this->assertSame(1300.0, $t['net'], '合并净利润 = 1000 + 本期实现 300');
    }

    public function test_snapshot_stores_and_can_be_viewed(): void
    {
        $f = $this->orgFixture();
        $this->orgBaseFlows($f);

        $svc = app(ConsolidationService::class);
        $snap = $svc->snapshot((int) $f['group']->id, $this->orgPeriod()[0], $this->orgPeriod()[1], null);

        $this->assertNotNull($snap->id);
        // report_data 经 JSON 落库再反序列化，整型会丢失 float 形态（1000.0 → 100），
        // 数值等价即可，用宽松断言。
        $this->assertEquals(1000.0, $snap->report_data['totals']['net']);
        $this->assertSame(3, $snap->report_data['totals']['org_count']);
        $this->assertArrayHasKey('closing_unrealized', $snap->report_data);

        $this->actingAs($this->orgAdmin())
            ->get(route('consolidation.snapshots', ['root_org_id' => $f['group']->id]))
            ->assertStatus(200)
            ->assertSee($f['group']->name);

        $this->actingAs($this->orgAdmin())
            ->get(route('consolidation.snapshot.show', $snap))
            ->assertStatus(200)
            ->assertSee('合并快照');
    }
}
