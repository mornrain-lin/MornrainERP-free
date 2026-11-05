<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 组织架构管理（OrganizationController）回归测试。
 *
 * 覆盖：
 *  - 列表渲染、创建三级树（集团→公司→部门）并校验 level/path；
 *  - 编辑改父级后路径级联重建；
 *  - 删除叶子节点；根组织与含子节点者被拒；
 *  - 三级层级校验（集团无上级、公司上级须集团、部门上级须公司、不可成环）。
 */
class OrganizationControllerTest extends TestCase
{
    protected function admin(): User
    {
        return User::where('role', User::ROLE_ADMIN)->first()
            ?? User::create([
                'name' => '组织测试管理员',
                'email' => 'admin-org-test@mornrain.local',
                'password' => User::hashPassword('admin1234'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);
    }

    protected function tearDown(): void
    {
        Organization::where('code', 'like', 'OC_%')->delete();
        parent::tearDown();
    }

    public function test_index_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(route('organizations.index'))
            ->assertStatus(200)
            ->assertSee('组织架构');
    }

    public function test_store_builds_three_level_tree(): void
    {
        $this->actingAs($this->admin())
            ->post(route('organizations.store'), [
                'name' => '集团X', 'type' => Organization::TYPE_GROUP, 'parent_id' => '', 'code' => 'OC_G', 'status' => 1,
            ])
            ->assertRedirect(route('organizations.index'));

        $group = Organization::where('code', 'OC_G')->firstOrFail();
        $this->assertSame(0, $group->level);

        $this->actingAs($this->admin())
            ->post(route('organizations.store'), [
                'name' => '公司Y', 'type' => Organization::TYPE_COMPANY, 'parent_id' => $group->id, 'code' => 'OC_C', 'status' => 1,
            ])
            ->assertRedirect(route('organizations.index'));

        $company = Organization::where('code', 'OC_C')->firstOrFail();
        $this->assertSame(1, $company->level);
        $this->assertSame($group->id, $company->parent_id);

        $this->actingAs($this->admin())
            ->post(route('organizations.store'), [
                'name' => '部门Z', 'type' => Organization::TYPE_DEPARTMENT, 'parent_id' => $company->id, 'code' => 'OC_D', 'status' => 1,
            ])
            ->assertRedirect(route('organizations.index'));

        $dept = Organization::where('code', 'OC_D')->firstOrFail();
        $this->assertSame(2, $dept->level);
        // 物化路径：group/id/company/id/dept/id
        $this->assertSame("{$group->id}/{$company->id}/{$dept->id}", $dept->path);
    }

    public function test_hierarchy_validation_rejects_wrong_parent(): void
    {
        $group = Organization::create(['name' => 'G', 'type' => Organization::TYPE_GROUP, 'code' => 'OC_G2']);
        $group->computePath();
        $group->saveQuietly();

        // 部门直接挂在集团下 → 拒绝
        $this->actingAs($this->admin())
            ->post(route('organizations.store'), [
                'name' => '部门', 'type' => Organization::TYPE_DEPARTMENT, 'parent_id' => $group->id, 'code' => 'OC_D2',
            ])
            ->assertSessionHasErrors('parent_id');

        // 集团挂上级 → 拒绝
        $this->actingAs($this->admin())
            ->post(route('organizations.store'), [
                'name' => '集团', 'type' => Organization::TYPE_GROUP, 'parent_id' => $group->id, 'code' => 'OC_G3',
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_update_reparents_and_rebuilds(): void
    {
        $g1 = Organization::create(['name' => 'G1', 'type' => Organization::TYPE_GROUP, 'code' => 'OC_G1']);
        $g1->computePath();
        $g1->saveQuietly();
        $g2 = Organization::create(['name' => 'G2', 'type' => Organization::TYPE_GROUP, 'code' => 'OC_G2']);
        $g2->computePath();
        $g2->saveQuietly();
        $c = Organization::create(['name' => 'C', 'type' => Organization::TYPE_COMPANY, 'parent_id' => $g1->id, 'code' => 'OC_C1']);
        $c->computePath();
        $c->saveQuietly();
        $d = Organization::create(['name' => 'D', 'type' => Organization::TYPE_DEPARTMENT, 'parent_id' => $c->id, 'code' => 'OC_D1']);
        $d->computePath();
        $d->saveQuietly();

        // 把公司 C 从 G1 改挂到 G2
        $this->actingAs($this->admin())
            ->put(route('organizations.update', $c), [
                'name' => 'C', 'type' => Organization::TYPE_COMPANY, 'parent_id' => $g2->id, 'code' => 'OC_C1', 'status' => 1,
            ])
            ->assertRedirect(route('organizations.index'));

        $c->refresh();
        $d->refresh();
        $this->assertSame($g2->id, $c->parent_id, '公司父级应更新为 G2');
        $this->assertStringStartsWith($g2->path.'/', $c->path);
        // 子部门路径应随父级级联重建
        $this->assertStringStartsWith($c->path.'/', $d->path);
    }

    public function test_destroy_blocks_node_with_children_and_root(): void
    {
        $root = Organization::where('code', 'ROOT')->firstOrFail();
        // 根组织不可删
        $this->actingAs($this->admin())
            ->delete(route('organizations.destroy', $root))
            ->assertSessionHasErrors('name');

        $group = Organization::create(['name' => 'G', 'type' => Organization::TYPE_GROUP, 'code' => 'OC_G4']);
        $group->computePath();
        $group->saveQuietly();
        $company = Organization::create(['name' => 'C', 'type' => Organization::TYPE_COMPANY, 'parent_id' => $group->id, 'code' => 'OC_C2']);
        $company->computePath();
        $company->saveQuietly();

        // 含子节点不可删
        $this->actingAs($this->admin())
            ->delete(route('organizations.destroy', $group))
            ->assertSessionHasErrors('name');

        // 叶子节点可删
        $this->actingAs($this->admin())
            ->delete(route('organizations.destroy', $company))
            ->assertRedirect(route('organizations.index'));
        $this->assertNull(Organization::find($company->id));
    }
}
