<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 角色权限管理（仅管理员）
 *
 *  - 角色 CRUD（系统内置角色不可删除）
 *  - 权限勾选（目录来自 config/permissions.php）
 *  - 用户 ↔ 角色分配（admin 账号等价超级角色，无需分配）
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('is_super', 'desc')->orderBy('id')->get();
        $catalog = config('permissions', []);
        $users = User::orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->with('roles')
            ->get();

        return view('roles.index', compact('roles', 'catalog', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:40|regex:/^[a-z][a-z0-9_]*$/|unique:roles,name',
            'label' => 'required|string|max:60',
            'description' => 'nullable|string|max:120',
        ], [
            'name.regex' => '角色标识只能用小写字母、数字、下划线，且以字母开头',
        ]);

        Role::create($data + ['is_super' => false, 'is_system' => false]);
        app(AuditService::class)->log('role.created', "新建角色 {$data['label']}（{$data['name']}）");

        return back()->with('ok', "角色 {$data['label']} 已创建");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:60',
            'description' => 'nullable|string|max:120',
        ]);
        $role->update($data);
        app(AuditService::class)->log('role.updated', "修改角色 {$role->label}");

        return back()->with('ok', '角色已更新');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('err', '系统内置角色不可删除');
        }
        if ($role->users()->exists()) {
            return back()->with('err', '该角色已分配给用户，请先解除分配');
        }
        $label = $role->label;
        $role->delete();
        app(AuditService::class)->log('role.deleted', "删除角色 {$label}");

        return back()->with('ok', "角色 {$label} 已删除");
    }

    /** 保存角色权限勾选 */
    public function permissions(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string',
        ]);
        if ($role->is_super) {
            return back()->with('err', '超级角色拥有全部权限，无需配置');
        }
        $ids = Permission::whereIn('key', $data['permissions'] ?? [])->pluck('id');
        $role->permissions()->sync($ids);
        app(AuditService::class)->log('role.permissions', "更新角色 {$role->label} 的权限（{$ids->count()} 项）", $role);

        return back()->with('ok', "角色 {$role->label} 权限已保存（{$ids->count()} 项）");
    }

    /** 保存用户角色分配 */
    public function assignUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_ids' => 'array',
            'role_ids.*' => 'exists:roles,id',
        ]);
        $user = User::findOrFail($data['user_id']);
        if ($user->isAdmin()) {
            return back()->with('err', '管理员账号等价超级角色，无需分配');
        }
        $user->roles()->sync($data['role_ids'] ?? []);
        app(AuditService::class)->log('role.assigned', "调整账号 {$user->name} 的角色", $user);

        return back()->with('ok', "账号 {$user->name} 的角色已更新");
    }
}
