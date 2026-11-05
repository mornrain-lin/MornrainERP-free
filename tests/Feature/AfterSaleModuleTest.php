<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AfterSaleTicket;

class AfterSaleModuleTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/aftersale')->assertRedirect('/login');
    }

    public function test_admin_can_access_aftersale_index(): void
    {
        $user = User::find(1);
        $this->actingAs($user)
            ->get('/aftersale')
            ->assertStatus(200)
            ->assertSee('云售后');
    }

    public function test_admin_can_access_key_pages(): void
    {
        $user = User::find(1);
        foreach (['/aftersale', '/aftersale/create'] as $uri) {
            $this->actingAs($user)->get($uri)->assertStatus(200);
        }
        $this->assertTrue(true);
    }

    public function test_admin_can_create_and_view_ticket(): void
    {
        $user = User::find(1);
        $this->actingAs($user)
            ->post('/aftersale', [
                'type' => 'repair',
                'priority' => 'high',
                'subject' => '打印机异响故障',
                'description' => '设备运行时有异响，需要维修',
                'customer_name' => '测试客户',
            ])
            ->assertRedirect();

        $ticket = AfterSaleTicket::where('subject', '打印机异响故障')->first();
        $this->assertNotNull($ticket);
        $this->assertSame(AfterSaleTicket::STATUS_PENDING, $ticket->status);
        $this->assertNotEmpty($ticket->ticket_no);

        $this->actingAs($user)
            ->get("/aftersale/{$ticket->id}")
            ->assertStatus(200)
            ->assertSee($ticket->ticket_no);
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $staff = User::where('email', 'staff-test@mornrain.local')->first()
            ?? User::create([
                'name' => '测试运营',
                'email' => 'staff-test@mornrain.local',
                'password' => User::hashPassword('staff1234'),
                'role' => User::ROLE_STAFF,
                'is_active' => true,
            ]);

        $this->actingAs($staff)
            ->get('/aftersale')
            ->assertStatus(403);
    }
}
