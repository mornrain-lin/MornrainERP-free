<?php

/*
|--------------------------------------------------------------------------
| 晨雨ERP · 套餐与额度配置
|--------------------------------------------------------------------------
| 免费版默认每月 400 单；超过后禁止新建 / 导入 / 拉单（已存在订单可继续维护）。
| 设为 0 表示不限量（付费版）。
|
| plans        套餐等级（数值越大等级越高），LicenseService::rank 据此判定。
| paid_modules 付费模块 → 所需最低套餐，是「路由门禁」与「菜单隐藏」的单一真相。
*/
return [
    // free / pro / enterprise
    'name' => env('ERP_PLAN', 'free'),

    // 每月订单额度；0 = 不限量
    'monthly_order_quota' => (int) env('ERP_FREE_QUOTA', 400),

    // 套餐等级（数值越大等级越高）
    'plans' => [
        'free' => 0,
        'pro' => 1,
        'enterprise' => 2,
    ],

    // 付费模块 → 所需最低套餐
    'paid_modules' => [
        'crm' => 'pro',
        'pm' => 'pro',
        'mes' => 'pro',
        'adm' => 'pro',
        'oa' => 'pro',
        'hr' => 'pro',
        'hr.shifts' => 'pro',
        'hr.salary' => 'pro',
        'recruit' => 'pro',
        'aftersale' => 'pro',
        'forum' => 'pro',
        'survey' => 'pro',
        'ai' => 'enterprise',
        'mall' => 'enterprise',
    ],
];
