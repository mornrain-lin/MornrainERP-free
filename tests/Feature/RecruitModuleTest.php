<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class RecruitModuleTest extends TestCase
{
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
