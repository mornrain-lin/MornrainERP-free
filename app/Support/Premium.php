<?php

namespace App\Support;

/**
 * 收费私有包（mornrain/erp-premium）可用性探针。
 *
 * 公开仓库不含任何收费逻辑；收费模块以独立 Composer 私有包形式交付。
 * 本类仅用于在免费代码中判断「私有包是否已安装」，从而决定菜单是否展示为
 * 「锁定 / 升级入口」。它不加载任何收费业务逻辑，也不依赖收费代码存在。
 */
class Premium
{
    /**
     * 私有包是否已安装并可见于自动加载。
     * class_exists 在未安装时仅返回 false，不会抛错。
     */
    public static function installed(): bool
    {
        return class_exists(\Mornrain\ErpPremium\PremiumServiceProvider::class)
            || class_exists(\Mornrain\ErpPremium\Http\Controllers\CrmController::class);
    }

    /**
     * 收费模块清单（route/hub key -> 展示名），用于锁定页与文档。
     */
    public static function modules(): array
    {
        return [
            'crm' => 'CRM 客户关系',
            'pm' => 'PM 项目管理',
            'oa' => 'OA 办公',
            'adm' => 'ADM 行政',
            'mes' => 'MES 制造执行',
            'hr' => 'EHR 人力',
            'hr.salary' => '薪资管理',
            'hr.shifts' => '多班次考勤',
            'recruit' => '招聘管理',
            'aftersale' => '云售后',
            'forum' => '论坛',
            'survey' => '问卷',
            'ai' => 'AI 经营助手',
            'mall' => '商城',
        ];
    }
}
