<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * 角色权限 + 系统参数初始化（幂等，可重复执行）
 *
 *  - 按 config/permissions.php 登记权限点目录
 *  - 内置角色：管理员（超级）/ 运营（默认无权限）/ 财务专员（财务全套 + 报表）
 *  - 写入系统参数默认值（后台「系统参数」页可改）
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ---- 权限点目录 ----
        foreach (config('permissions', []) as $group => $items) {
            foreach ($items as $key => $label) {
                Permission::updateOrCreate(
                    ['key' => $key],
                    ['group' => $group, 'label' => $label],
                );
            }
        }

        // ---- 内置角色 ----
        $admin = Role::updateOrCreate(
            ['name' => 'admin'],
            ['label' => '管理员', 'description' => '系统内置超级角色，不受权限限制', 'is_super' => true, 'is_system' => true],
        );
        $staff = Role::updateOrCreate(
            ['name' => 'staff'],
            ['label' => '运营', 'description' => '默认无权限，按需在角色管理授权', 'is_super' => false, 'is_system' => true],
        );
        $finance = Role::updateOrCreate(
            ['name' => 'finance'],
            ['label' => '财务专员', 'description' => '财务查看与账务处理（示例角色，可修改）', 'is_super' => false, 'is_system' => false],
        );

        // 管理员角色同步全部权限（超级角色本就放行，此处保持数据完整）
        $admin->permissions()->sync(Permission::query()->pluck('id'));
        $staff->permissions()->sync([]);
        $financeKeys = ['finance.view', 'finance.account', 'finance.flow', 'finance.prepay', 'finance.reconcile', 'reports.finance'];
        $finance->permissions()->sync(
            Permission::whereIn('key', $financeKeys)->pluck('id')
        );

        // ---- 系统参数默认值 ----
        $defaults = [
            'company_name' => ['晨雨ERP', 'basic', '公司名称', 'text'],
            'monthly_order_quota' => [(string) config('plan.monthly_order_quota', 400), 'quota', '月度订单额度（0 = 不限量）', 'number'],
            'default_safety_stock' => ['10', 'inventory', '默认安全库存（商品未设置时回退）', 'number'],
        ];
        foreach ($defaults as $key => [$value, $group, $label, $type]) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group, 'label' => $label, 'type' => $type],
            );
        }
    }
}
