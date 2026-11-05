<?php

namespace Tests\Feature;

use App\Models\HrEmployee;
use App\Models\HrSalary;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class AiModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! \App\Support\Premium::installed()) {
            $this->markTestSkipped(
                '收费私有包 mornrain/erp-premium 未安装：该模块已随「模型一」移出公开仓库，'
                .'本测试须在已安装私有包的环境运行。'
            );
        }
    }

    private static ?User $staffUser = null;

    private function admin(): User
    {
        return User::find(1);
    }

    private function staff(): User
    {
        if (self::$staffUser === null) {
            self::$staffUser = User::firstOrCreate(
                ['email' => 'staff-ai-test@mornrain.local'],
                [
                    'name' => 'AI测试运营',
                    'password' => User::hashPassword('staff1234'),
                    'role' => User::ROLE_STAFF,
                    'is_active' => true,
                ],
            );
        }

        return self::$staffUser;
    }

    private function createEmployee(string $name = 'AI测试员工', float $salary = 8000): HrEmployee
    {
        return HrEmployee::create([
            'employee_no' => 'AI' . substr(uniqid(), -5),
            'name' => $name,
            'department' => '运营部',
            'position' => '专员',
            'gender' => 'female',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => HrEmployee::STATUS_ACTIVE,
            'salary' => $salary,
            'created_by' => $this->admin()->id,
        ]);
    }

    public function test_guest_redirects_to_login(): void
    {
        $this->get('/ai')->assertRedirect('/login');
        $this->post('/ai/ask', ['question' => '毛利'])->assertRedirect('/login');
    }

    public function test_staff_forbidden(): void
    {
        $this->actingAs($this->staff())->get('/ai')->assertForbidden();
        $this->actingAs($this->staff())->post('/ai/ask', ['question' => '毛利'])->assertForbidden();
    }

    public function test_admin_dashboard_renders_enhanced_sections(): void
    {
        $this->actingAs($this->admin())->get('/ai')
            ->assertOk()
            ->assertSee('智能问答')
            ->assertSee('销售趋势')
            ->assertSee('滚动预测')
            ->assertSee('收入结构')
            ->assertSee('人力成本')
            ->assertSee('经营建议');
    }

    public function test_ask_returns_rule_based_answer(): void
    {
        $this->actingAs($this->admin())
            ->from('/ai?days=30')
            ->post('/ai/ask', ['question' => '本月毛利如何？'])
            ->assertRedirect('/ai?days=30')
            ->assertSessionHas('ask');

        $ask = session('ask');
        $this->assertStringContainsString('毛利', $ask['answer']);
        $this->assertSame('本月毛利如何？', $ask['question']);
    }

    public function test_ask_low_stock_hits_inventory_keyword(): void
    {
        $this->actingAs($this->admin())
            ->from('/ai')
            ->post('/ai/ask', ['question' => '哪些商品低于安全库存？'])
            ->assertRedirect('/ai');

        $ask = session('ask');
        $this->assertStringContainsString('安全库存', $ask['answer']);
    }

    public function test_labor_cost_reads_salary_period(): void
    {
        $period = now()->format('Y-m');
        $before = app(\App\Services\Ai\AiService::class)->laborCost($period);

        $emp = $this->createEmployee();
        HrSalary::create([
            'employee_id' => $emp->id,
            'period' => $period,
            'base_salary' => 8000,
            'gross_pay' => 8000,
            'deductions' => 0,
            'net_pay' => 7200,
            'status' => HrSalary::STATUS_DRAFT,
        ]);

        $lc = app(\App\Services\Ai\AiService::class)->laborCost($period);
        $this->assertEquals(7200.0, (float) round($lc['total'] - $before['total'], 2));
        $this->assertSame($period, $lc['period']);
    }
}
