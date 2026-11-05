<?php

namespace Tests\Feature;

use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use Tests\TestCase;

class ShiftModuleTest extends TestCase
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

    private function shiftData(array $overrides = []): array
    {
        return array_merge([
            'name' => '早班',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_cross_day' => '0',
            'grace_minutes' => 5,
            'color' => 'primary',
            'remark' => '测试班次',
        ], $overrides);
    }

    private function createEmployee(): HrEmployee
    {
        return HrEmployee::create([
            'employee_no' => HrEmployee::nextNo(),
            'name' => '班次测试员',
            'department' => '测试部',
            'position' => '职员',
            'gender' => 'male',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => HrEmployee::STATUS_ACTIVE,
            'salary' => 8000,
            'created_by' => $this->admin()->id,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/hr/shifts')->assertRedirect('/login');
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $this->actingAs($this->staff())
            ->get('/hr/shifts')
            ->assertStatus(403);
    }

    public function test_admin_can_access_shift_pages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/hr/shifts')->assertStatus(200)->assertSee('班次管理');
        $this->actingAs($admin)->get('/hr/shifts/create')->assertStatus(200)->assertSee('新建班次');
        $this->actingAs($admin)->get('/hr/shifts/schedules')->assertStatus(200)->assertSee('排班管理');
        $this->actingAs($admin)->get('/hr/shifts/clock')->assertStatus(200)->assertSee('班次打卡');
        $this->actingAs($admin)->get('/hr/shifts/stats')->assertStatus(200)->assertSee('考勤统计');
    }

    public function test_admin_can_create_and_toggle_shift(): void
    {
        $admin = $this->admin();

        // 创建班次
        $this->actingAs($admin)
            ->post('/hr/shifts', $this->shiftData())
            ->assertRedirect(route('hr.shifts.index'));

        $shift = WorkShift::where('name', '早班')->latest('id')->first();
        $this->assertNotNull($shift);
        $this->assertEquals('08:00', $shift->start_time);
        $this->assertEquals(9, (float) $shift->work_hours);
        $this->assertTrue($shift->is_active);

        // 编辑页 200
        $this->actingAs($admin)->get('/hr/shifts/'.$shift->id.'/edit')->assertStatus(200);

        // 停用 / 启用
        $this->actingAs($admin)->post('/hr/shifts/'.$shift->id.'/toggle')->assertRedirect();
        $this->assertFalse($shift->fresh()->is_active);
        $this->actingAs($admin)->post('/hr/shifts/'.$shift->id.'/toggle')->assertRedirect();
        $this->assertTrue($shift->fresh()->is_active);
    }

    public function test_admin_can_schedule_and_clock_with_late_judgement(): void
    {
        $admin = $this->admin();
        $emp = $this->createEmployee();

        $shift = WorkShift::create([
            'name' => '早班', 'start_time' => '08:00', 'end_time' => '17:00',
            'is_cross_day' => false, 'grace_minutes' => 5, 'work_hours' => 9,
            'color' => 'primary', 'is_active' => true, 'created_by' => $admin->id,
        ]);

        // 批量排班（今天 1 天）
        $this->actingAs($admin)
            ->post('/hr/shifts/schedules/batch', [
                'employee_ids' => [$emp->id],
                'shift_id' => $shift->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $sched = WorkSchedule::where('employee_id', $emp->id)
            ->where('shift_id', $shift->id)
            ->whereDate('date', now()->toDateString())
            ->first();
        $this->assertNotNull($sched);
        $this->assertEquals(now()->toDateString(), $sched->date->toDateString());

        // 迟到打卡（08:10 > 08:00+5）
        $this->actingAs($admin)
            ->post('/hr/shifts/clock', [
                'employee_id' => $emp->id,
                'shift_id' => $shift->id,
                'date' => now()->toDateString(),
                'action' => 'in',
                'time' => '08:10',
            ])
            ->assertRedirect();

        $att = HrAttendance::where('employee_id', $emp->id)
            ->where('shift_id', $shift->id)
            ->whereDate('date', now()->toDateString())
            ->first();
        $this->assertNotNull($att);
        $this->assertEquals(HrAttendance::TYPE_LATE, $att->type);
        $this->assertEquals('08:10', $att->check_in);

        // 签退
        $this->actingAs($admin)
            ->post('/hr/shifts/clock', [
                'employee_id' => $emp->id,
                'shift_id' => $shift->id,
                'date' => now()->toDateString(),
                'action' => 'out',
                'time' => '17:05',
            ])
            ->assertRedirect();

        $this->assertEquals('17:05', $att->fresh()->check_out);

        // 打卡页与统计页包含员工
        $this->actingAs($admin)->get('/hr/shifts/clock')->assertStatus(200)->assertSee('班次测试员');
        $this->actingAs($admin)->get('/hr/shifts/stats')->assertStatus(200)->assertSee('班次测试员');
    }

    public function test_cross_day_shift_hours(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/hr/shifts', $this->shiftData([
                'name' => '夜班',
                'start_time' => '22:00',
                'end_time' => '06:00',
                'is_cross_day' => '1',
            ]))
            ->assertRedirect();

        $shift = WorkShift::where('name', '夜班')->latest('id')->first();
        $this->assertNotNull($shift);
        $this->assertTrue($shift->is_cross_day);
        $this->assertEquals(8, (float) $shift->work_hours);
    }
}
