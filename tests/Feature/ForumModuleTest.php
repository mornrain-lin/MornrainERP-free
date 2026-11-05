<?php

namespace Tests\Feature;

use App\Models\ForumBoard;
use App\Models\ForumThread;
use App\Models\User;
use Tests\TestCase;

class ForumModuleTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/forum')->assertRedirect('/login');
    }

    public function test_admin_can_access_forum_index(): void
    {
        $this->actingAs($this->admin())
            ->get('/forum')
            ->assertStatus(200)
            ->assertSee('论坛');
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $this->actingAs($this->staff())
            ->get('/forum')
            ->assertStatus(403);
    }

    public function test_admin_can_create_board_thread_and_reply(): void
    {
        $admin = $this->admin();

        $board = ForumBoard::firstOrCreate(
            ['slug' => 'product'],
            ['name' => '产品交流', 'description' => '产品讨论', 'icon' => '🧩', 'sort' => 1, 'created_by' => $admin->id],
        );

        $this->actingAs($admin)
            ->post('/forum', ['board_id' => $board->id, 'title' => '测试帖', 'content' => '内容'])
            ->assertRedirect();

        $thread = ForumThread::where('title', '测试帖')->latest('id')->first();
        $this->assertNotNull($thread);
        $this->assertSame(0, $thread->reply_count);

        $this->actingAs($admin)
            ->get("/forum/thread/{$thread->id}")
            ->assertStatus(200)
            ->assertSee('测试帖');

        $this->actingAs($admin)
            ->post("/forum/thread/{$thread->id}/reply", ['content' => '回复内容'])
            ->assertSessionHas('success');
        $this->assertSame(1, $thread->fresh()->reply_count);
    }

    public function test_admin_can_pin_lock_and_prevent_reply_on_locked(): void
    {
        $admin = $this->admin();
        $board = ForumBoard::firstOrCreate(
            ['slug' => 'product'],
            ['name' => '产品交流', 'description' => '产品讨论', 'icon' => '🧩', 'sort' => 1, 'created_by' => $admin->id],
        );

        $thread = ForumThread::create([
            'board_id' => $board->id,
            'user_id' => $admin->id,
            'title' => '置顶测试帖',
            'content' => '内容',
        ]);

        $this->actingAs($admin)
            ->post("/forum/thread/{$thread->id}/pin")
            ->assertSessionHas('success');
        $this->assertTrue($thread->fresh()->isPinned());

        $this->actingAs($admin)
            ->post("/forum/thread/{$thread->id}/lock")
            ->assertSessionHas('success');
        $this->assertTrue($thread->fresh()->isLocked());

        $this->actingAs($admin)
            ->post("/forum/thread/{$thread->id}/reply", ['content' => '锁定后回复'])
            ->assertSessionHas('error');
        $this->assertSame(0, $thread->fresh()->reply_count);
    }
}
