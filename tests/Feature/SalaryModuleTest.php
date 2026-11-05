<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrSalary;
use App\Models\SalaryRule;
use App\Models\User;
use Tests\TestCase;

class SalaryModuleTest extends TestCase
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
                ['email' => 'staff-test@mornrain.local'],
                [
                    'name' => '测试运营',
                    'password' => User::hashPassword('staff1234'),
                    'role' => User::ROLE_STAFF,
                    'is_active' => true,
                ],
            );
        }

        return self::$staffUser;
    }

    private function createEmployee(string $name = '薪资测试员', float $salary = 8000): HrEmployee
    {
        return HrEmployee::create([
            'employee_no' => HrEmployee::nextNo(),
            'name' => $name,
            'department' => '测试部',
            'position' => '职员',
            'gender' => 'male',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => HrEmployee::STATUS_ACTIVE,
            'salary' => $salary,
            'created_by' => $this->admin()->id,
        ]);
    }

    /** 重置为默认薪资规则（避免与其他测试的规则更新互相污染） */
    private function resetDefaultRule(): void
    {
        SalaryRule::query()->delete();
        (new \App\Services\Hr\SalaryService())->activeRule();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/hr/salary')->assertRedirect('/login');
        $this->get('/hr/salary/rules')->assertRedirect('/login');
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $this->actingAs($this->staff())
            ->get('/hr/salary')
            ->assertStatus(403);
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin())
            ->get('/hr/salary')
            ->assertOk()
            ->assertSee('薪资管理');

        $this->actingAs($this->admin())
            ->get('/hr/salary/create')
            ->assertOk();

        $this->actingAs($this->admin())
            ->get('/hr/salary/rules')
            ->assertOk()
            ->assertSee('个税起征点');
    }

    public function test_admin_can_generate_payslip_with_tax_and_insurance(): void
    {
        $this->resetDefaultRule();
        $emp = $this->createEmployee();
        $period = now()->format('Y-m');

        $this->actingAs($this->admin())
            ->post('/hr/salary', [
                'employee_id' => $emp->id,
                'period' => $period,
                'base_salary' => 8000,
                'bonus' => 500,
                'deductions' => 0,
            ])
            ->assertRedirect();

        $salary = HrSalary::where('employee_id', $emp->id)->where('period', $period)->firstOrFail();

        // 应发 = 8000 + 500 = 8500
        $this->assertEquals(8500, (float) $salary->gross_pay);
        // 社保 = 8000 × (0.08 + 0.02 + 0.005) = 840
        $this->assertEquals(840, (float) $salary->social_insurance);
        // 公积金 = 8000 × 0.07 = 560
        $this->assertEquals(560, (float) $salary->housing_fund);
        // 应纳税所得 = 8500 - 840 - 560 - 5000 = 2100 → 税 = 2100 × 3% = 63
        $this->assertEquals(63, (float) $salary->income_tax);
        // 实发 = 8500 - 840 - 560 - 63 - 0 = 7037
        $this->assertEquals(7037, (float) $salary->net_pay);
        $this->assertEquals(HrSalary::STATUS_DRAFT, $salary->status);

        // 详情页 200
        $this->actingAs($this->admin())
            ->get("/hr/salary/{$salary->id}")
            ->assertOk()
            ->assertSee('实发');
    }

    public function test_attendance_penalties_are_deducted(): void
    {
        $this->resetDefaultRule();
        $emp = $this->createEmployee();
        $period = now()->format('Y-m');

        // 1 次迟到 + 1 天缺勤
        HrAttendance::create([
            'employee_id' => $emp->id,
            'date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'type' => HrAttendance::TYPE_LATE,
            'check_in' => '09:05',
        ]);
        HrAttendance::create([
            'employee_id' => $emp->id,
            'date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'type' => HrAttendance::TYPE_ABSENT,
        ]);

        $this->actingAs($this->admin())
            ->post('/hr/salary', [
                'employee_id' => $emp->id,
                'period' => $period,
                'base_salary' => 8000,
                'bonus' => 0,
                'deductions' => 0,
            ])
            ->assertRedirect();

        $salary = HrSalary::where('employee_id', $emp->id)->where('period', $period)->firstOrFail();

        // 考勤扣款 = 迟到 50 + 缺勤 8000/21.75 ≈ 367.82
        $expected = round(50 + 8000 / 21.75, 2);
        $this->assertEqualsWithDelta($expected, (float) $salary->attendance_deduction, 0.02);

        $detail = $salary->payroll_detail;
        $this->assertSame(1, $detail['attendance']['late_count']);
        $this->assertSame(1, (int) $detail['attendance']['absent_days']);
    }

    public function test_batch_generate_and_pay(): void
    {
        $emp1 = $this->createEmployee('批量甲');
        $emp2 = $this->createEmployee('批量乙', 12000);
        $period = now()->format('Y-m');

        $this->actingAs($this->admin())
            ->post('/hr/salary/generate-month', ['period' => $period])
            ->assertRedirect()
            ->assertSessionHas('ok');

        $this->assertDatabaseHas('hr_salaries', ['employee_id' => $emp1->id, 'period' => $period]);
        $this->assertDatabaseHas('hr_salaries', ['employee_id' => $emp2->id, 'period' => $period]);

        // 重复批量自动跳过
        $this->actingAs($this->admin())
            ->post('/hr/salary/generate-month', ['period' => $period])
            ->assertRedirect();

        $salary = HrSalary::where('employee_id', $emp1->id)->where('period', $period)->firstOrFail();

        // 发放
        $this->actingAs($this->admin())
            ->post("/hr/salary/{$salary->id}/pay")
            ->assertRedirect()
            ->assertSessionHas('ok');

        $salary->refresh();
        $this->assertEquals(HrSalary::STATUS_PAID, $salary->status);
        $this->assertNotNull($salary->paid_at);

        // 重复发放被拦截
        $this->actingAs($this->admin())
            ->post("/hr/salary/{$salary->id}/pay")
            ->assertSessionHas('err');
    }

    public function test_update_rules(): void
    {
        $this->actingAs($this->admin())
            ->put('/hr/salary/rules', [
                'name' => '测试规则',
                'tax_threshold' => 6000,
                'pension_rate' => 0.1,
                'medical_rate' => 0.02,
                'unemployment_rate' => 0.005,
                'housing_rate' => 0.07,
                'late_deduct' => 100,
                'deduct_per_day' => 1.5,
                'cap1' => 3000, 'rate1' => 0.03, 'quick1' => 0,
                'cap2' => 12000, 'rate2' => 0.1, 'quick2' => 210,
                'cap3' => 25000, 'rate3' => 0.2, 'quick3' => 1410,
                'cap4' => 35000, 'rate4' => 0.25, 'quick4' => 2660,
                'cap5' => 55000, 'rate5' => 0.3, 'quick5' => 4410,
                'cap6' => '', 'rate6' => 0.45, 'quick6' => 15160,
            ])
            ->assertRedirect()
            ->assertSessionHas('ok');

        $rule = SalaryRule::where('is_active', true)->latest('id')->firstOrFail();
        $this->assertEquals(6000, (float) $rule->tax_threshold);
        $this->assertEquals(0.1, (float) $rule->pension_rate);
        $this->assertEquals(100, (float) $rule->late_deduct);
        $this->assertCount(6, $rule->tax_brackets);
        $this->assertNull($rule->tax_brackets[5]['cap']);
    }
}
