<?php

namespace App\Http\Controllers;

use App\Support\Premium;
use Illuminate\Http\Request;

/**
 * 收费模块锁定桩（免费核心的一部分，永不接触收费逻辑）。
 *
 * 当收费私有包未安装时，菜单中的收费项会被 MenuConfig 改写为指向
 * premium.locked 的「升级入口」；本控制器仅渲染一个升级引导页。
 */
class PremiumStubController extends Controller
{
    public function show(Request $request, string $module)
    {
        abort_unless(auth()->check(), 401);

        $name = Premium::modules()[$module] ?? '该收费模块';

        return view('locked.upgrade', compact('module', 'name'));
    }
}
