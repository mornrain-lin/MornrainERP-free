<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditService;
use App\Support\MenuConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 菜单管理（仅管理员）
 *
 * 在 config/modules.php 声明式基底之上做数据库覆盖：
 *  - 分组 / 菜单显隐（隐藏仅影响侧边栏，路由权限仍由 permission 中间件把关）
 *  - 分组排序（序号越小越靠前）
 *  - 自定义链接（可挂到已有分组或「自定义」分组）
 *
 * 「产品路线图」页始终直读 config，不受本页配置影响。
 */
class MenuController extends Controller
{
    public function index()
    {
        $override = MenuConfig::override();
        $groups = config('modules', []);

        // 当前生效的分组序号（未配置时按 config 原序）
        $orderMap = array_flip($override['group_order']);
        $positions = [];
        $i = 1;
        foreach ($groups as $g) {
            $title = (string) ($g['title'] ?? '');
            $positions[$title] = $orderMap[$title] ?? $i;
            $i++;
        }
        // 配置了顺序但标题对不上的（理论上不会），追加到末尾
        foreach ($override['group_order'] as $title) {
            if (! isset($positions[$title])) {
                $positions[$title] = $i++;
            }
        }

        $customGroups = collect($groups)->pluck('title')->push('自定义')->unique()->values();

        return view('menus.index', compact('groups', 'override', 'positions', 'customGroups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hidden_groups' => ['nullable', 'array'],
            'hidden_groups.*' => ['string', 'max:60'],
            'hidden_items' => ['nullable', 'array'],
            'hidden_items.*' => ['string', 'max:80'],
            'order' => ['nullable', 'array'],
            'order.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // 序号 → 排序后的分组标题列表
        $groupOrder = [];
        if (! empty($data['order'])) {
            $pairs = [];
            foreach ($data['order'] as $title => $pos) {
                $pairs[$title] = (int) $pos;
            }
            asort($pairs);
            $groupOrder = array_keys($pairs);
        }

        MenuConfig::save([
            'hidden_groups' => $data['hidden_groups'] ?? [],
            'hidden_items' => $data['hidden_items'] ?? [],
            'group_order' => $groupOrder,
            'custom' => MenuConfig::override()['custom'],
        ]);

        app(AuditService::class)->log('menu.updated', '更新后台菜单配置（显隐 / 排序）');

        return redirect()->route('menus.index')->with('ok', '菜单配置已保存');
    }

    /** 新增自定义链接 */
    public function customStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'icon' => ['nullable', 'string', 'max:8'],
            'url' => ['required', 'string', 'max:255', 'url'],
            'group' => ['nullable', 'string', 'max:60'],
        ]);

        $override = MenuConfig::override();
        $override['custom'][] = [
            'name' => $data['name'],
            'icon' => $data['icon'] ?: '🔗',
            'url' => $data['url'],
            'group' => $data['group'] ?? '',
        ];
        MenuConfig::save($override);

        app(AuditService::class)->log('menu.custom_added', "新增自定义菜单「{$data['name']}」");

        return redirect()->route('menus.index')->with('ok', "自定义菜单「{$data['name']}」已添加");
    }

    /** 删除自定义链接（按下标） */
    public function customRemove(int $index): RedirectResponse
    {
        $override = MenuConfig::override();
        $name = $override['custom'][$index]['name'] ?? " #{$index}";
        unset($override['custom'][$index]);
        MenuConfig::save($override);

        app(AuditService::class)->log('menu.custom_removed', "删除自定义菜单「{$name}」");

        return redirect()->route('menus.index')->with('ok', "自定义菜单「{$name}」已删除");
    }

    /** 恢复默认（清空全部覆盖） */
    public function reset(): RedirectResponse
    {
        MenuConfig::reset();

        app(AuditService::class)->log('menu.reset', '恢复默认菜单配置');

        return redirect()->route('menus.index')->with('ok', '已恢复默认菜单');
    }
}
