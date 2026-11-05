<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 权限校验中间件：permission:finance.flow
 *
 *  - users.role = admin 的存量管理员等价超级角色，直接放行
 *  - 超级角色（is_super）直接放行
 *  - 其余按 role_user → permission_role 链校验权限点
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasPermission($permission)) {
            abort(403, "没有所需权限：{$permission}");
        }

        return $next($request);
    }
}
