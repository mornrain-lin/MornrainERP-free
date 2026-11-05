<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 套餐门禁中间件：plan:pro / plan:enterprise
 *
 *  - 未登录 → 跳转登录页（与 auth 中间件保持一致的行为）
 *  - 当前套餐等级 < 要求等级 → 403（核心拦截点）
 *  - 与 permission 中间件相互独立，可叠加使用：
 *      ->middleware(['permission:crm.view','plan:pro'])
 *
 * 任何 LicenseService 异常都会因 fail-closed 回落 free，从而触发 403，不会误放行。
 */
class EnsurePlan
{
    public function handle(Request $request, Closure $next, string $required): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $current = LicenseService::plan();
        if (LicenseService::rank($current) < LicenseService::rank($required)) {
            abort(403, "当前套餐（{$current}）不包含该功能，请激活 ".strtoupper($required)." 版本后使用");
        }

        return $next($request);
    }
}
