<?php

namespace Tests\Feature;

use App\Models\ReportTemplate;
use App\Models\User;
use Tests\TestCase;

class ReportDesignModuleTest extends TestCase
{
    private static ?User $staffUser = null;

    private function admin(): User
    {
        return User::find(1);
    }

    private function staff(): User
    {
        if (self::$staffUser === null) {
            self::$staffUser = User::firstOrCreate(
                ['email' => 'staff-test@gangwan.local'],
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

    private function templateData(): array
    {
        return [
            'name' => '本月订单明细',
            'code' => 'monthly_orders',
            'description' => '按订单号与买家筛选',
            'data_source' => 'orders',
            'col_fields' => [
                'order_no' => 'order_no|订单号',
                'buyer_name' => 'buyer_name|买家',
                'goods_amount' => 'goods_amount|商品金额',
                'status' => 'status|订单状态',
                'created_at' => 'created_at|创建时间',
            ],
            'filter_fields' => [
                'order_no' => 'contains',
                'status' => 'eq',
                'created_at' => 'between',
            ],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/report-design')->assertRedirect('/login');
    }

    public function test_admin_can_access_report_design_index(): void
    {
        $this->actingAs($this->admin())
            ->get('/report-design')
            ->assertStatus(200)
            ->assertSee('报表设计');
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $this->actingAs($this->staff())
            ->get('/report-design')
            ->assertStatus(403);
    }

    public function test_admin_can_create_run_and_export_report(): void
    {
        ReportTemplate::where('code', 'monthly_orders')->delete();

        $admin = $this->admin();

        // 创建设计页 200
        $this->actingAs($admin)->get('/report-design/create')->assertStatus(200)->assertSee('数据源');

        // 创建模板
        $this->actingAs($admin)
            ->post('/report-design', $this->templateData())
            ->assertRedirect();

        $template = ReportTemplate::where('code', 'monthly_orders')->latest('id')->first();
        $this->assertNotNull($template);
        $this->assertCount(5, $template->columns);
        $this->assertCount(3, $template->filters);
        $this->assertTrue($template->is_active);

        // 运行报表（带筛选）
        $this->actingAs($admin)
            ->get('/report-design/'.$template->id.'?status=pending')
            ->assertStatus(200)
            ->assertSee('结果明细');

        // 编辑页回填
        $this->actingAs($admin)->get('/report-design/'.$template->id.'/edit')->assertStatus(200)->assertSee('本月订单明细');

        // 更新模板
        $data = $this->templateData();
        $data['name'] = '本月订单明细V2';
        $this->actingAs($admin)
            ->put('/report-design/'.$template->id, $data)
            ->assertRedirect();
        $this->assertSame('本月订单明细V2', $template->fresh()->name);

        // 导出 CSV
        $this->actingAs($admin)
            ->get('/report-design/'.$template->id.'/export')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 停用后运行页 404
        $this->actingAs($admin)->post('/report-design/'.$template->id.'/toggle')->assertRedirect();
        $this->assertFalse($template->fresh()->is_active);
        $this->actingAs($admin)->get('/report-design/'.$template->id)->assertStatus(404);

        // 删除
        $this->actingAs($admin)->delete('/report-design/'.$template->id)->assertRedirect();
        $this->assertDatabaseMissing('report_templates', ['id' => $template->id]);
    }

    public function test_code_duplicate_is_rejected(): void
    {
        ReportTemplate::where('code', 'dup_code')->delete();

        ReportTemplate::create([
            'name' => '已有报表',
            'code' => 'dup_code',
            'description' => '',
            'data_source' => 'orders',
            'columns' => [['field' => 'order_no', 'label' => '订单号']],
            'filters' => [],
            'is_active' => true,
            'created_by' => $this->admin()->id,
        ]);

        $data = $this->templateData();
        $data['code'] = 'dup_code';
        $this->actingAs($this->admin())
            ->post('/report-design', $data)
            ->assertSessionHasErrors('code');
    }
}
