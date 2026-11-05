<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * 后台菜单合并层
 *
 * 约定（对应项目硬性约定第 16 条）：
 *  - config/modules.php 是菜单的唯一声明式基底，绝不在布局里硬编码菜单；
 *  - 本类在基底之上叠加数据库覆盖（settings.menu_config）：显隐 / 分组排序 / 自定义链接；
 *  - 「产品路线图」页仍直读 config，保持产品真相与运营菜单解耦。
 *
 * 覆盖结构：
 * [
 *   'hidden_groups' => ['企业套件', ...],          // 隐藏整个分组
 *   'hidden_items'  => ['orders.import.form', ...], // 隐藏单个菜单（route 或 hub key）
 *   'group_order'   => ['经营', '采购管理', ...],    // 分组排序（未列出的按原序追加）
 *   'custom'        => [['name','icon','url','group'], ...], // 自定义链接
 * ]
 */
class MenuConfig
{
    public const KEY = 'menu_config';

    /** 合并后的分组菜单（供侧边栏渲染） */
    public static function groups(): array
    {
        $override = self::override();

        $groups = [];
        foreach (config('modules', []) as $group) {
            $title = (string) ($group['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $items = collect($group['items'] ?? [])
                ->reject(fn ($it) => in_array(self::itemKey($it), $override['hidden_items'], true))
                ->values()
                ->all();

            if (in_array($title, $override['hidden_groups'], true) || $items === []) {
                continue;
            }

            // 收费模块：未安装私有包时标记为「锁定」，菜单展示为升级入口而非隐藏
            if (! Premium::installed()) {
                $items = collect($items)->map(function ($it) {
                    if (! empty($it['plan'])) {
                        $it['locked'] = true;
                        $it['url'] = route('premium.locked', ['module' => self::itemKey($it)]);
                        unset($it['route'], $it['hub']);
                    }

                    return $it;
                })->all();
            }

            $groups[$title] = ['title' => $title, 'items' => $items];
        }

        // 自定义链接：挂到指定分组，缺省落入「自定义」
        foreach ($override['custom'] as $link) {
            $target = ($link['group'] ?? '') !== '' ? $link['group'] : '自定义';
            if (! isset($groups[$target])) {
                $groups[$target] = ['title' => $target, 'items' => []];
            }
            $groups[$target]['items'][] = [
                'name' => $link['name'] ?? '未命名链接',
                'icon' => $link['icon'] ?: '🔗',
                'url' => $link['url'] ?? '#',
                'external' => Str::startsWith($link['url'] ?? '', ['http://', 'https://']),
                'status' => 'ready',
            ];
        }

        // 分组排序：按 group_order 重排，未列出的分组按原序补后
        if ($override['group_order'] !== []) {
            $ordered = [];
            foreach ($override['group_order'] as $t) {
                if (isset($groups[$t])) {
                    $ordered[$t] = $groups[$t];
                }
            }
            foreach ($groups as $t => $g) {
                $ordered[$t] ??= $g;
            }
            $groups = $ordered;
        }

        return array_values($groups);
    }

    /** 读取覆盖配置（缺省为空结构） */
    public static function override(): array
    {
        $raw = Setting::get(self::KEY);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];

        return [
            'hidden_groups' => array_values(array_filter((array) ($data['hidden_groups'] ?? []), 'is_string')),
            'hidden_items' => array_values(array_filter((array) ($data['hidden_items'] ?? []), 'is_string')),
            'group_order' => array_values(array_filter((array) ($data['group_order'] ?? []), 'is_string')),
            'custom' => array_values(array_filter((array) ($data['custom'] ?? []), 'is_array')),
        ];
    }

    /** 保存覆盖配置 */
    public static function save(array $override): void
    {
        Setting::put(self::KEY, json_encode([
            'hidden_groups' => array_values($override['hidden_groups'] ?? []),
            'hidden_items' => array_values($override['hidden_items'] ?? []),
            'group_order' => array_values($override['group_order'] ?? []),
            'custom' => array_values($override['custom'] ?? []),
        ], JSON_UNESCAPED_UNICODE), [
            'group' => 'menu',
            'label' => '后台菜单配置',
            'type' => 'json',
        ]);
    }

    /** 恢复默认（清空覆盖） */
    public static function reset(): void
    {
        self::save(['hidden_groups' => [], 'hidden_items' => [], 'group_order' => [], 'custom' => []]);
    }

    /** 菜单项唯一键：路由名优先，落地页 key 次之，最后用显示名 */
    private static function itemKey(array $it): string
    {
        return (string) ($it['route'] ?? ($it['hub'] ?? ($it['name'] ?? '')));
    }
}
