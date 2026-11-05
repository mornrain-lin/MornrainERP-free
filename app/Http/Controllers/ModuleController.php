<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * 模块落地页：按 hub key 从 config/modules.php 读取元数据渲染说明页。
     * 用于尚未独立上线的模块（status=planned），避免侧边栏 404。
     */
    public function hub(Request $request, string $key)
    {
        $item = null;
        foreach (config('modules', []) as $group) {
            foreach ($group['items'] ?? [] as $it) {
                if (($it['hub'] ?? null) === $key) {
                    $item = $it;
                    break 2;
                }
            }
        }

        abort_if(is_null($item), 404, '未找到该模块');

        $meta = $item['meta'] ?? [];

        return view('module.hub', [
            'key'      => $key,
            'name'     => $item['name'],
            'icon'     => $item['icon'],
            'desc'     => $meta['desc'] ?? '',
            'features' => $meta['features'] ?? [],
            'status'   => $item['status'] ?? 'planned',
        ]);
    }
}
