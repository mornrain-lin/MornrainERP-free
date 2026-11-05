<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\OrgIntercompanyTransaction;
use App\Services\Consolidation\IntercompanyService;
use App\Services\Finance\FinanceService;
use App\Support\OrgContext;
use Tests\Concerns\OrgFixture;
use Tests\TestCase;

/**
 * 组织间内部往来登记 / 红冲回归测试。
 *
 * 验证镜像流水的组织归属、账户余额联动、作废红冲、非法登记拒绝，
 * 以及多组织下单号生成不撞唯一索引（本迭代修复的既有缺陷）。
 */
class IntercompanyTest extends TestCase
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

    public function test_record_creates_mirrored_flows_and_updates_balances(): void
    {
        $f = $this->orgFixture();
        $txn = $this->orgIntercompany($f, 400);

        $this->assertTrue($txn->isConfirmed());
        $this->assertNotEmpty($txn->from_flow_id);
        $this->assertNotEmpty($txn->to_flow_id);

        $flows = FinanceFlow::withoutGlobalScope('organization')
            ->where('intercompany_id', $txn->id)->get();
        $this->assertCount(2, $flows, '应生成一对镜像流水');

        $out = $flows->firstWhere('direction', FinanceFlow::DIRECTION_EXPENSE);
        $in = $flows->firstWhere('direction', FinanceFlow::DIRECTION_INCOME);

        $this->assertSame((int) $f['a']->id, (int) $out->organization_id);
        $this->assertSame((int) $f['b']->id, (int) $in->organization_id);
        $this->assertSame(FinanceFlow::CAT_INTERCOMPANY_OUT, $out->category);
        $this->assertSame(FinanceFlow::CAT_INTERCOMPANY_IN, $in->category);
        $this->assertSame(400.0, (float) $out->amount);
        $this->assertSame(400.0, (float) $in->amount);
        $this->assertSame('CNY', $out->currency);

        $balanceA = (float) FinanceAccount::withoutGlobalScope('organization')->find($f['accA']->id)->balance;
        $balanceB = (float) FinanceAccount::withoutGlobalScope('organization')->find($f['accB']->id)->balance;
        $this->assertSame(-400.0, $balanceA);
        $this->assertSame(400.0, $balanceB);
        $this->assertSame(0.0, $balanceA + $balanceB, '往来不产生集团层面的资金增减');
    }

    public function test_record_rejects_same_organization(): void
    {
        $f = $this->orgFixture();
        $payload = $this->payload($f, ['to_organization_id' => $f['a']->id]);

        $this->actingAs($this->orgAdmin())
            ->post(route('intercompany.store'), $payload)
            ->assertSessionHasErrors('to_organization_id');

        $this->assertSame(0, OrgIntercompanyTransaction::where('from_organization_id', $f['a']->id)->count());
    }

    public function test_record_rejects_account_belonging_to_another_organization(): void
    {
        $f = $this->orgFixture();
        $payload = $this->payload($f, [
            'to_account_id' => $f['accA']->id, // 账户属甲，却要记到乙名下
        ]);

        $this->actingAs($this->orgAdmin())
            ->post(route('intercompany.store'), $payload)
            ->assertSessionHasErrors('to_account_id');

        $this->assertSame(0, OrgIntercompanyTransaction::where('from_organization_id', $f['a']->id)->count());
    }

    public function test_cancel_red_ink_deletes_flows_and_reverses_balances(): void
    {
        $f = $this->orgFixture();
        $txn = $this->orgIntercompany($f, 400);

        app(IntercompanyService::class)->cancel($txn);

        $this->assertFalse($txn->fresh()->isConfirmed());
        $this->assertNull($txn->fresh()->from_flow_id);
        $this->assertNull($txn->fresh()->to_flow_id);
        $this->assertSame(0, FinanceFlow::withoutGlobalScope('organization')
            ->where('intercompany_id', $txn->id)->count());

        $balanceA = (float) FinanceAccount::withoutGlobalScope('organization')->find($f['accA']->id)->balance;
        $balanceB = (float) FinanceAccount::withoutGlobalScope('organization')->find($f['accB']->id)->balance;
        $this->assertSame(0.0, $balanceA);
        $this->assertSame(0.0, $balanceB);
        // 登记记录保留作审计
        $this->assertSame(1, OrgIntercompanyTransaction::where('id', $txn->id)->count());
    }

    public function test_mirrored_flow_cannot_be_deleted_manually(): void
    {
        $f = $this->orgFixture();
        $txn = $this->orgIntercompany($f, 400);
        $flow = FinanceFlow::withoutGlobalScope('organization')->where('intercompany_id', $txn->id)->first();

        app(FinanceService::class)->remove($flow);

        $this->assertNotNull(
            FinanceFlow::withoutGlobalScope('organization')->find($flow->id),
            '镜像流水必须走作废流程，禁止单独删除以免破坏抵消配对'
        );
    }

    public function test_flow_numbers_stay_unique_across_organizations(): void
    {
        $f = $this->orgFixture();

        OrgContext::set((int) $f['a']->id);
        $flowA = $this->orgFlow($f['accA'], (int) $f['a']->id, FinanceFlow::DIRECTION_INCOME, 100);

        OrgContext::set((int) $f['b']->id);
        $flowB = $this->orgFlow($f['accB'], (int) $f['b']->id, FinanceFlow::DIRECTION_INCOME, 100);

        $this->assertNotSame($flowA->flow_no, $flowB->flow_no, '单号生成必须跨组织计数，否则撞唯一索引');
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->orgAdmin())
            ->get(route('intercompany.index'))
            ->assertStatus(200)
            ->assertSee('登记内部往来');
    }

    public function test_account_can_be_created_for_a_specific_organization(): void
    {
        $f = $this->orgFixture();

        $this->actingAs($this->orgAdmin())
            ->get(route('finance.accounts'))
            ->assertStatus(200)
            ->assertSee('归属组织');

        $this->actingAs($this->orgAdmin())
            ->post(route('finance.accounts.store'), [
                'name' => 'CONS指定组织账户',
                'type' => FinanceAccount::TYPE_BANK,
                'organization_id' => $f['a']->id,
            ])
            ->assertRedirect(route('finance.accounts'));

        $account = FinanceAccount::withoutGlobalScope('organization')
            ->where('name', 'CONS指定组织账户')->firstOrFail();
        $this->assertSame((int) $f['a']->id, (int) $account->organization_id);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $f, array $overrides = []): array
    {
        return array_merge([
            'from_organization_id' => $f['a']->id,
            'to_organization_id' => $f['b']->id,
            'from_account_id' => $f['accA']->id,
            'to_account_id' => $f['accB']->id,
            'amount' => 400,
            'occurred_on' => $this->orgDay(),
            'category' => OrgIntercompanyTransaction::CAT_TRANSFER,
            'remark' => '合并核算测试',
        ], $overrides);
    }
}
