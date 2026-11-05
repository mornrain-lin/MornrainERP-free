<?php

namespace App\Http\Controllers;

use App\Support\OrgContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 组织切换：将目标组织写入 session（供 OrgContext 解析），
 * 或切换为「全部」（跨组织查看，仅管理员/持 org.view_all 者可用）。
 */
class OrgSwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();
        $target = $request->input('org_id'); // 'all' | 组织 id | ''

        if ($target === 'all') {
            if (! OrgContext::canViewAll($user)) {
                abort(403, '无跨组织查看权限');
            }
            session(['active_org_id' => null]);
            OrgContext::enableAll();

            return back()->with('ok', '已切换：查看全部组织');
        }

        if ($target === '' || $target === null) {
            session()->forget('active_org_id');
            OrgContext::resolve($user);

            return back();
        }

        $orgId = (int) $target;
        if (! OrgContext::userCanAccessOrg($user, $orgId)) {
            abort(403, '无权切换到该组织');
        }

        session(['active_org_id' => $orgId]);
        OrgContext::set($orgId);

        $name = \App\Models\Organization::find($orgId)?->name ?? '组织';

        return back()->with('ok', '已切换到：'.$name);
    }
}
