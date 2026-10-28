<?php

/*
|--------------------------------------------------------------------------
| 港湾ERP · 模块注册表
|--------------------------------------------------------------------------
| 驱动侧边栏导航与「模块落地页」（module.hub）。
| 每个分组（group）包含若干模块（item）：
|   name     显示名
|   icon     图标（unicode / emoji）
|   route    已上线的命名路由（存在则直链）；与 hub 二选一
|   hub      尚未独立上线的模块 key，指向 module.hub 落地页
|   admin    仅管理员可见（true）或全员可见（false/缺省）
|   status   ready（已上线）| planned（规划中，落地页说明）
|   meta     落地页说明：desc 简介 + features 功能清单
|
| 升级节奏：新模块先在 status=planned 下挂 hub 落地页，
| 待对应阶段实现后改 status=ready 并把 hub 换成 route。
*/

return [
    // ---------------- 经营 ----------------
    [
        'title' => '经营',
        'items' => [
            ['name' => '概览', 'icon' => '◪', 'route' => 'dashboard', 'status' => 'ready'],
            ['name' => '产品路线图', 'icon' => '🗺', 'route' => 'roadmap', 'status' => 'ready'],
            ['name' => '订单管理', 'icon' => '▤', 'route' => 'orders.index', 'status' => 'ready'],
            ['name' => '订单导入', 'icon' => '⇪', 'route' => 'orders.import.form', 'status' => 'ready'],
        ],
    ],

    // ---------------- 零售管理 ----------------
    [
        'title' => '零售管理',
        'items' => [
            ['name' => '门店开单', 'icon' => '🛒', 'route' => 'retail.pos', 'admin' => true, 'permission' => 'retail.view', 'status' => 'ready'],
            ['name' => '零售单', 'icon' => '🧾', 'route' => 'retail.sales', 'admin' => true, 'permission' => 'retail.view', 'status' => 'ready'],
            ['name' => '零售退货', 'icon' => '↩', 'route' => 'retail.returns', 'admin' => true, 'permission' => 'retail.view', 'status' => 'ready'],
            ['name' => '会员管理', 'icon' => '👥', 'route' => 'retail.members', 'admin' => true, 'permission' => 'retail.view', 'status' => 'ready'],
        ],
    ],

    // ---------------- 销售管理 ----------------
    [
        'title' => '销售管理',
        'items' => [
            ['name' => '销售订单', 'icon' => '📝', 'route' => 'sales.orders', 'admin' => true, 'permission' => 'sales.view', 'status' => 'ready'],
            ['name' => '客户管理', 'icon' => '🤝', 'route' => 'sales.customers', 'admin' => true, 'permission' => 'sales.view', 'status' => 'ready'],
            ['name' => '报价单', 'icon' => '📄', 'route' => 'sales.quotes', 'admin' => true, 'permission' => 'sales.view', 'status' => 'ready'],
            ['name' => '销售退货', 'icon' => '↩', 'route' => 'sales.returns', 'admin' => true, 'permission' => 'sales.view', 'status' => 'ready'],
        ],
    ],

    // ---------------- 采购管理 ----------------
    [
        'title' => '采购管理',
        'items' => [
            ['name' => '采购管理', 'icon' => '📦', 'route' => 'purchases.index', 'admin' => true, 'permission' => 'purchases.view', 'status' => 'ready'],
            ['name' => '请购申请', 'icon' => '📨', 'route' => 'requisitions.index', 'admin' => true, 'permission' => 'purchases.view', 'status' => 'ready'],
        ],
    ],

    // ---------------- 仓库管理 ----------------
    [
        'title' => '仓库管理',
        'items' => [
            ['name' => '库存总览', 'icon' => '▦', 'route' => 'inventory.index', 'status' => 'ready'],
            ['name' => '仓库与分仓', 'icon' => '🏬', 'route' => 'warehouses.index', 'admin' => true, 'permission' => 'inventory.view', 'status' => 'ready'],
            ['name' => '仓库调拨', 'icon' => '⇄', 'route' => 'warehouses.transfers.index', 'admin' => true, 'permission' => 'inventory.view', 'status' => 'ready'],
            ['name' => '组装拆卸', 'icon' => '🧩', 'route' => 'warehouses.assemblies.index', 'admin' => true, 'permission' => 'inventory.view', 'status' => 'ready'],
            ['name' => '库存盘点', 'icon' => '🔎', 'route' => 'warehouses.stocktake.form', 'admin' => true, 'permission' => 'inventory.view', 'status' => 'ready'],
            ['name' => '其他出入库', 'icon' => '➕', 'route' => 'warehouses.other.form', 'admin' => true, 'permission' => 'inventory.view', 'status' => 'ready'],
        ],
    ],

    // ---------------- 财务管理 ----------------
    [
        'title' => '财务管理',
        'items' => [
            ['name' => '财务总览', 'icon' => '◪', 'route' => 'finance.index', 'admin' => true, 'permission' => 'finance.view', 'status' => 'ready'],
            ['name' => '资金账户', 'icon' => '🏦', 'route' => 'finance.accounts', 'admin' => true, 'permission' => 'finance.view', 'status' => 'ready'],
            ['name' => '收支流水', 'icon' => '📊', 'route' => 'finance.flows', 'admin' => true, 'permission' => 'finance.view', 'status' => 'ready'],
            ['name' => '预付款管理', 'icon' => '💰', 'route' => 'finance.prepayments', 'admin' => true, 'permission' => 'finance.view', 'status' => 'ready'],
            ['name' => '对账中心', 'icon' => '⚖', 'route' => 'finance.reconciliations', 'admin' => true, 'permission' => 'finance.view', 'status' => 'ready'],
            ['name' => '账务台账', 'icon' => '📒', 'route' => 'finance.ledger', 'admin' => true, 'permission' => 'finance.view', 'status' => 'ready'],
        ],
    ],

    // ---------------- 报表查询 ----------------
    [
        'title' => '报表查询',
        'items' => [
            ['name' => '利润报表', 'icon' => '◈', 'route' => 'reports.profit', 'status' => 'ready'],
            ['name' => '财务报表', 'icon' => '🧾', 'route' => 'reports.finance', 'admin' => true, 'permission' => 'reports.finance', 'status' => 'ready'],
            ['name' => '经营报表', 'icon' => '📈', 'route' => 'reports.business', 'admin' => true, 'permission' => 'reports.finance', 'status' => 'ready'],
            ['name' => '综合查询', 'icon' => '🔍', 'route' => 'query.index', 'status' => 'ready'],
            ['name' => '报表设计', 'icon' => '📐', 'route' => 'report-design.index', 'admin' => true, 'permission' => 'report.design', 'status' => 'ready'],
        ],
    ],

    // ---------------- 系统管理 ----------------
    [
        'title' => '系统管理',
        'items' => [
            ['name' => '平台对接', 'icon' => '⇄', 'route' => 'sync.index', 'admin' => true, 'permission' => 'sync.manage', 'status' => 'ready'],
            ['name' => '系统状态', 'icon' => '🩺', 'route' => 'system.status', 'admin' => true, 'permission' => 'system.status', 'status' => 'ready'],
            ['name' => '账号管理', 'icon' => '☺', 'route' => 'users.index', 'admin' => true, 'permission' => 'users.manage', 'status' => 'ready'],
            ['name' => '操作审计', 'icon' => '🔍', 'route' => 'audit.index', 'admin' => true, 'permission' => 'audit.view', 'status' => 'ready'],
            ['name' => '角色权限', 'icon' => '🔐', 'route' => 'roles.index', 'admin' => true, 'status' => 'ready'],
            ['name' => '系统参数', 'icon' => '⚙', 'route' => 'settings.index', 'admin' => true, 'status' => 'ready'],
            ['name' => '菜单管理', 'icon' => '📑', 'route' => 'menus.index', 'admin' => true, 'status' => 'ready'],
        ],
    ],

    // ---------------- 流程中心 ----------------
    [
        'title' => '流程中心',
        'items' => [
            ['name' => '流程目录', 'icon' => '📑', 'route' => 'workflows.index', 'status' => 'ready'],
            ['name' => '我的待办', 'icon' => '⏳', 'route' => 'workflows.todo', 'status' => 'ready'],
            ['name' => '我发起的', 'icon' => '📨', 'route' => 'workflows.my', 'status' => 'ready'],
        ],
    ],

    // ---------------- 企业套件 ----------------
    [
        ],
];
