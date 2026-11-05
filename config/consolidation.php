<?php

/*
|--------------------------------------------------------------------------
| 晨雨ERP · 跨组织合并核算口径
|--------------------------------------------------------------------------
| 本配置只定义「合并报表的计算口径」，不改变各组织独立账簿的记账方式。
| 各组织账簿仍以 organization_id 隔离，合并是只读的报表视角。
*/

return [

    /*
    | 记账本位币。当前系统 finance_* 一律 CNY（见 finance_module 迁移），
    | 因此本迭代不做汇率折算，所有金额直接相加。
    | 预留 rates：若将来启用多币别，按 'USD' => 7.20（1 外币 = N CNY）
    | 配置汇率，ConsolidationService 会按交易的 currency 折算后再合并。
    */
    'base_currency' => 'CNY',
    'rates' => [
        // 'USD' => 7.20,
    ],

    // 内部往来镜像流水使用的收支分类（写入 finance_flows.category）
    'intercompany' => [
        'income_category' => 'intercompany_in',
        'expense_category' => 'intercompany_out',
    ],

    // 内部往来业务类型（仅用于登记时分类，不参与抵消判定）
    'categories' => [
        'transfer' => '资金划拨',
        'loan' => '内部借款',
        'management_fee' => '管理费分摊',
        'cost_sharing' => '成本分摊',
        'other' => '其他内部往来',
    ],

    // 单次报表允许的最大期间跨度（天），防止误查全历史拖垮数据库
    'max_period_days' => 366,

];
