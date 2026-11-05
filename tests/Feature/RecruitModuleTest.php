<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class RecruitModuleTest extends TestCase
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
        $this->get('/recruit')->assertRedirect('/login');
    }

    public function test_admin_can_access_recruit_index(): void
    {
        $user = User::find(1);
        $this->actingAs($user)
            ->get('/recruit')
            ->assertStatus(200)
            ->assertSee('招聘管理');
    }

    public function test_admin_can_access_key_pages(): void
    {
        $user = User::find(1);
        foreach (['/recruit/jobs', '/recruit/jobs/create', '/recruit/candidates', '/recruit/candidates/create'] as $uri) {
            $this->actingAs($user)->get($uri)->assertStatus(200);
        }
        $this->assertTrue(true);
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $user = User::find(2);
        $this->actingAs($user)
            ->get('/recruit')
            ->assertStatus(403);
    }
}
