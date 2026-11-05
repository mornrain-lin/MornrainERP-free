<?php

namespace App\Http\Middleware;

use App\Support\OrgContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 在每个已认证请求上解析并设置组织上下文（OrgContext）。
 * 必须在 auth 之后执行，以便在 $request->user() 可用时解析默认组织。
 */
class OrgContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        OrgContext::resolve($request->user());

        return $next($request);
    }
}
