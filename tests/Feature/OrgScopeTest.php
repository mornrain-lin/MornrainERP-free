<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Warehouse;
use App\Support\OrgContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 多组织数据隔离（BelongsToOrganization 全局作用域）回归测试。
 *
 * 验证：
 *  - 设置 OrgContext 后，业务查询仅返回当前组织数据；
 *  - creating 事件自动把新建数据打上当前组织 id；
 *  - OrgContext::isAll()（跨组织查看）时作用域被禁用，可见全部；
 *  - withoutGlobalScope 可绕过（合并报表等场景预留）。
 *
 * 未使用 RefreshDatabase，沿用项目「已存在数据库」的依赖方式；
 * 每个用例结束清理自建的组织与仓库，避免污染。
 */
class OrgScopeTest extends TestCase
{
    protected function makeOrg(string $code): Organization
    {
        $org = Organization::create([
            'name' => '测试组织'.$code,
            'type' => Organization::TYPE_GROUP,
            'code' => $code,
        ]);
        $org->computePath();
        $org->saveQuietly();

        return $org;
    }

    protected function tearDown(): void
    {
        OrgContext::reset();
        DB::table('warehouses')->where('name', 'like', '隔离测试%')->delete();
        Organization::where('code', 'like', 'SCOPE_%')->delete();
        parent::tearDown();
    }

    public function test_global_scope_isolates_by_org(): void
    {
        $orgA = $this->makeOrg('SCOPE_A');
        $orgB = $this->makeOrg('SCOPE_B');

        OrgContext::set($orgA->id);
        Warehouse::create(['name' => '隔离测试仓A']);

        // 只统计本用例自建的数据，避免库里既有仓库干扰断言
        $probe = fn () => Warehouse::where('name', '隔离测试仓A');

        // 切到 B：看不到 A 的数据
        OrgContext::set($orgB->id);
        $this->assertSame(0, $probe()->count(), '作用域应仅返回当前组织数据');
        $this->assertSame(1, Warehouse::withoutGlobalScope('organization')
            ->where('name', '隔离测试仓A')->count());
    }

    public function test_creating_event_tags_current_org(): void
    {
        $orgA = $this->makeOrg('SCOPE_A');
        OrgContext::set($orgA->id);

        $w = Warehouse::create(['name' => '隔离测试仓A']);
        $this->assertEquals($orgA->id, $w->organization_id);
    }

    public function test_is_all_disables_scope(): void
    {
        $orgA = $this->makeOrg('SCOPE_A');
        OrgContext::set($orgA->id);
        Warehouse::create(['name' => '隔离测试仓A']);

        OrgContext::enableAll();
        $this->assertSame(1, Warehouse::where('name', '隔离测试仓A')->count(),
            '跨组织查看模式下应可见全部');
    }

    public function test_unset_context_does_not_scope(): void
    {
        $orgA = $this->makeOrg('SCOPE_A');
        OrgContext::set($orgA->id);
        Warehouse::create(['name' => '隔离测试仓A']);

        // OrgContext 未设定（如控制台命令）：不施加作用域，可见全部
        OrgContext::reset();
        $this->assertSame(1, Warehouse::where('name', '隔离测试仓A')->count());
    }
}
