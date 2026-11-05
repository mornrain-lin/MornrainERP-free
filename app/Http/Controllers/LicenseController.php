<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 激活码 / 套餐管理（仅管理员可访问，路由层已加 admin + throttle 保护）。
 */
class LicenseController extends Controller
{
    /** 激活码管理页：展示当前套餐 + 激活表单 */
    public function index(): View
    {
        return view('license.index', [
            'plan' => LicenseService::plan(),
            'info' => LicenseService::info(),
        ]);
    }

    /** 当前套餐信息（JSON，供前端 / API 轮询） */
    public function status(Request $request)
    {
        $data = [
            'plan' => LicenseService::plan(),
            'info' => LicenseService::info(),
        ];

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return back()->with('license_status', $data);
    }

    /** 通过激活码激活（路由层已加 throttle:10,1 防爆破） */
    public function activate(Request $request): RedirectResponse
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            return back()->withErrors(['code' => '请输入激活码']);
        }

        if (LicenseService::activate($code)) {
            return back()->with('success', '激活成功，已解锁 '.strtoupper(LicenseService::plan()).' 版本功能');
        }

        return back()->withErrors(['code' => '激活码无效或已过期']);
    }

    /** 解除激活（回落免费版） */
    public function destroy(Request $request): RedirectResponse
    {
        LicenseService::deactivate();

        return back()->with('success', '已解除激活，回落到免费版');
    }
}
