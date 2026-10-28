<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RetailController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportDesignController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 港湾ERP 路由
|--------------------------------------------------------------------------
| 轻量级跨境电商 ERP
|  · /login 与 /logout 之外，全部路由需要登录
|  · 基础资料（店铺/商品/平台对接）与账号管理额外要求管理员角色
*/

// ---- 登录 / 登出 ----
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---- 后台（需登录） ----
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ---- 产品路线图 / 模块总览（全员可见）----
    Route::get('/roadmap', [RoadmapController::class, 'index'])->name('roadmap');

    // ---- 综合查询（全员可用；资金流水组仅管理员命中）----
    Route::get('/query', [QueryController::class, 'index'])->name('query.index');
    Route::get('/query/export', [QueryController::class, 'export'])->name('query.export');

    // ---- 订单管理 ----
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/create', [OrderController::class, 'create'])->name('create');
        Route::post('/', [OrderController::class, 'store'])->name('store');
        Route::get('/import', [OrderController::class, 'importForm'])->name('import.form');
        Route::post('/import', [OrderController::class, 'import'])->name('import');
        Route::get('/export', [OrderController::class, 'export'])->name('export');
        Route::post('/batch-ship', [OrderController::class, 'batchShip'])->name('batch-ship');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('edit');
        Route::put('/{order}', [OrderController::class, 'update'])->name('update');
        Route::delete('/{order}', [OrderController::class, 'destroy'])->name('destroy');
        Route::post('/{order}/status', [OrderController::class, 'updateStatus'])->name('status');
        Route::post('/{order}/ship', [OrderController::class, 'ship'])->name('ship');
    });

    // ---- 库存与采购 ----
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('/{product}/adjust', [InventoryController::class, 'adjust'])->name('adjust');
    });

    // ---- 采购管理（purchases.view） ----
    Route::prefix('purchases')->name('purchases.')->middleware('permission:purchases.view')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseController::class, 'create'])->name('create');
        Route::post('/', [PurchaseController::class, 'store'])->name('store');
        Route::post('/from-suggestions', [PurchaseController::class, 'fromSuggestions'])->name('from-suggestions');
        Route::get('/{purchaseOrder}', [PurchaseController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [PurchaseController::class, 'edit'])->name('edit');
        Route::put('/{purchaseOrder}', [PurchaseController::class, 'update'])->name('update');
        Route::delete('/{purchaseOrder}', [PurchaseController::class, 'destroy'])->name('destroy');
        Route::post('/{purchaseOrder}/receive', [PurchaseController::class, 'receive'])->name('receive');
        Route::post('/{purchaseOrder}/place', [PurchaseController::class, 'place'])->name('place');
        Route::post('/{purchaseOrder}/cancel', [PurchaseController::class, 'cancel'])->name('cancel');
    });

    // ---- 请购申请（purchases.view） ----
    Route::prefix('requisitions')->name('requisitions.')->middleware('permission:purchases.view')->group(function () {
        Route::get('/', [RequisitionController::class, 'index'])->name('index');
        Route::get('/create', [RequisitionController::class, 'create'])->name('create');
        Route::post('/', [RequisitionController::class, 'store'])->name('store');
        Route::get('/{requisition}', [RequisitionController::class, 'show'])->name('show');
        Route::get('/{requisition}/edit', [RequisitionController::class, 'edit'])->name('edit');
        Route::put('/{requisition}', [RequisitionController::class, 'update'])->name('update');
        Route::delete('/{requisition}', [RequisitionController::class, 'destroy'])->name('destroy');
        Route::post('/{requisition}/submit', [RequisitionController::class, 'submit'])->name('submit');
        Route::post('/{requisition}/cancel', [RequisitionController::class, 'cancel'])->name('cancel');
        Route::post('/{requisition}/convert', [RequisitionController::class, 'convert'])->name('convert');
    });

    // ---- 物流轨迹回写 ----
    Route::prefix('shipments')->name('shipments.')->group(function () {
        Route::post('/track-all', [ShipmentController::class, 'trackAll'])->name('track-all');
        Route::post('/{shipment}/track', [ShipmentController::class, 'track'])->name('track');
    });

    // ---- 平台对接（sync.manage） ----
    Route::prefix('sync')->name('sync.')->middleware('permission:sync.manage')->group(function () {
        Route::get('/', [SyncController::class, 'index'])->name('index');
        Route::put('/{shop}/config', [SyncController::class, 'saveConfig'])->name('config');
        Route::post('/{shop}/run', [SyncController::class, 'run'])->name('run');
        Route::post('/run-all', [SyncController::class, 'runAll'])->name('run-all');
    });

    // ---- 基础资料（管理员） ----
    Route::resource('shops', ShopController::class)->except(['show'])->middleware('admin');
    Route::resource('products', ProductController::class)->except(['show'])->middleware('admin');
    Route::resource('suppliers', SupplierController::class)->except(['show'])->middleware('admin');
    Route::post('suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])
        ->middleware('admin')
        ->name('suppliers.toggle');

    // ---- 报表 ----
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('profit', [ReportController::class, 'profit'])->name('profit');
        Route::get('export', [ReportController::class, 'export'])->name('export');
        // 财务报表与经营报表含资金 / 经营敏感数据，仅授权用户可用
        Route::middleware('permission:reports.finance')->group(function () {
            Route::get('finance', [ReportController::class, 'finance'])->name('finance');
            Route::get('finance/export', [ReportController::class, 'financeExport'])->name('finance.export');
            Route::get('business', [ReportController::class, 'business'])->name('business');
        });
    });

    // ---- 系统状态（管理员） ----
    Route::prefix('system')->name('system.')->middleware('admin')->group(function () {
        Route::get('status', [SystemController::class, 'status'])->name('status');
        Route::post('clear-cache', [SystemController::class, 'clearCache'])->name('clear-cache');
    });

    // ---- 账号与密码 ----
    Route::get('/password', [AuthController::class, 'passwordForm'])->name('password.form');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::prefix('users')->name('users.')->middleware('permission:users.manage')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::post('/{user}/toggle', [UserController::class, 'toggle'])->name('toggle');
        Route::put('/{user}/password', [UserController::class, 'resetPassword'])->name('password');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    // ---- 操作审计（audit.view） ----
    Route::prefix('audit')->name('audit.')->middleware('permission:audit.view')->group(function () {
        Route::get('/', [AuditController::class, 'index'])->name('index');
    });

    // ---- 角色权限 / 系统参数（仅管理员） ----
    Route::prefix('roles')->name('roles.')->middleware('admin')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
        Route::post('/{role}/permissions', [RoleController::class, 'permissions'])->name('permissions');
        Route::post('/assign', [RoleController::class, 'assignUser'])->name('assign');
    });

    Route::prefix('settings')->name('settings.')->middleware('admin')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/', [SettingsController::class, 'update'])->name('update');
    });

    // ---- 菜单管理（仅管理员；config/modules.php 之上的覆盖式配置） ----
    Route::prefix('menus')->name('menus.')->middleware('admin')->group(function () {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::post('/', [MenuController::class, 'store'])->name('store');
        Route::post('/custom', [MenuController::class, 'customStore'])->name('custom.store');
        Route::post('/custom/{index}/remove', [MenuController::class, 'customRemove'])->name('custom.remove');
        Route::post('/reset', [MenuController::class, 'reset'])->name('reset');
    });

    // ---- 模块落地页（规划中模块说明页，避免侧边栏 404） ----
    Route::get('module/{key}', [ModuleController::class, 'hub'])->name('module.hub');

    // ---- 电子审批流（50+ 电子流程） ----
    Route::prefix('workflows')->name('workflows.')->group(function () {
        Route::get('/', [WorkflowController::class, 'index'])->name('index');
        Route::get('/todo', [WorkflowController::class, 'todo'])->name('todo');
        Route::get('/my', [WorkflowController::class, 'my'])->name('my');
        Route::get('/create/{key}', [WorkflowController::class, 'create'])->name('create');
        Route::post('/', [WorkflowController::class, 'store'])->name('store');
        Route::get('/{instance}', [WorkflowController::class, 'show'])->name('show');
        Route::post('/{instance}/approve', [WorkflowController::class, 'approve'])->name('approve');
        Route::post('/{instance}/reject', [WorkflowController::class, 'reject'])->name('reject');
    });

    // ---- 仓库管理（inventory.view） ----
    Route::prefix('warehouses')->name('warehouses.')->middleware('permission:inventory.view')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::post('/', [WarehouseController::class, 'storeWarehouse'])->name('store');
        Route::post('/{warehouse}/default', [WarehouseController::class, 'setDefault'])->name('default');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroyWarehouse'])->name('destroy');
        Route::get('/transfers', [WarehouseController::class, 'transfers'])->name('transfers.index');
        Route::get('/transfers/create', [WarehouseController::class, 'transferCreate'])->name('transfers.create');
        Route::post('/transfers', [WarehouseController::class, 'transferStore'])->name('transfers.store');
        Route::get('/transfers/{transfer}', [WarehouseController::class, 'transferShow'])->name('transfers.show');
        Route::post('/transfers/{transfer}/complete', [WarehouseController::class, 'transferComplete'])->name('transfers.complete');
        Route::post('/transfers/{transfer}/cancel', [WarehouseController::class, 'transferCancel'])->name('transfers.cancel');
        Route::get('/assemblies', [WarehouseController::class, 'assemblies'])->name('assemblies.index');
        Route::get('/assemblies/create', [WarehouseController::class, 'assemblyCreate'])->name('assemblies.create');
        Route::post('/assemblies', [WarehouseController::class, 'assemblyStore'])->name('assemblies.store');
        Route::get('/assemblies/{assembly}', [WarehouseController::class, 'assemblyShow'])->name('assemblies.show');
        Route::post('/assemblies/{assembly}/complete', [WarehouseController::class, 'assemblyComplete'])->name('assemblies.complete');
        Route::post('/assemblies/{assembly}/cancel', [WarehouseController::class, 'assemblyCancel'])->name('assemblies.cancel');
        Route::get('/other', [WarehouseController::class, 'otherForm'])->name('other.form');
        Route::post('/other', [WarehouseController::class, 'otherStore'])->name('other.store');
        Route::get('/stocktake', [WarehouseController::class, 'stocktakeForm'])->name('stocktake.form');
        Route::post('/stocktake', [WarehouseController::class, 'stocktakeStore'])->name('stocktake.store');
    });

    // ---- 财务管理（finance.view，写操作按细粒度权限）----
    Route::prefix('finance')->name('finance.')->middleware('permission:finance.view')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        // 资金账户
        Route::get('/accounts', [FinanceController::class, 'accounts'])->name('accounts');
        Route::middleware('permission:finance.account')->group(function () {
            Route::post('/accounts', [FinanceController::class, 'accountStore'])->name('accounts.store');
            Route::post('/accounts/{account}/toggle', [FinanceController::class, 'accountToggle'])->name('accounts.toggle');
            Route::delete('/accounts/{account}', [FinanceController::class, 'accountDestroy'])->name('accounts.destroy');
        });
        // 收支流水
        Route::get('/flows', [FinanceController::class, 'flows'])->name('flows');
        Route::middleware('permission:finance.flow')->group(function () {
            Route::post('/flows', [FinanceController::class, 'flowStore'])->name('flows.store');
            Route::delete('/flows/{flow}', [FinanceController::class, 'flowDestroy'])->name('flows.destroy');
        });
        // 账务台账
        Route::get('/ledger', [FinanceController::class, 'ledger'])->name('ledger');
        Route::get('/ledger/export', [FinanceController::class, 'ledgerExport'])->name('ledger.export');
        // 预付款 / 预收款
        Route::get('/prepayments', [FinanceController::class, 'prepayments'])->name('prepayments');
        Route::middleware('permission:finance.prepay')->group(function () {
            Route::post('/prepayments', [FinanceController::class, 'prepayStore'])->name('prepayments.store');
            Route::post('/prepayments/{prepay}/apply', [FinanceController::class, 'prepayApply'])->name('prepayments.apply');
            Route::post('/prepayments/{prepay}/cancel', [FinanceController::class, 'prepayCancel'])->name('prepayments.cancel');
        });
        // 对账
        Route::get('/reconciliations', [FinanceController::class, 'reconciliations'])->name('reconciliations');
        Route::middleware('permission:finance.reconcile')->group(function () {
            Route::post('/reconciliations', [FinanceController::class, 'reconcileStore'])->name('reconciliations.store');
        });
    });

    // ---- 销售管理（sales.view）----
    Route::prefix('sales')->name('sales.')->middleware('permission:sales.view')->group(function () {
        // 客户
        Route::get('/customers', [SalesController::class, 'customers'])->name('customers');
        Route::post('/customers', [SalesController::class, 'customerStore'])->name('customers.store');
        Route::post('/customers/{customer}/toggle', [SalesController::class, 'customerToggle'])->name('customers.toggle');
        Route::delete('/customers/{customer}', [SalesController::class, 'customerDestroy'])->name('customers.destroy');
        // 销售订单
        Route::get('/orders', [SalesController::class, 'orders'])->name('orders');
        Route::get('/orders/create', [SalesController::class, 'orderCreate'])->name('orders.create');
        Route::post('/orders', [SalesController::class, 'orderStore'])->name('orders.store');
        Route::get('/orders/{order}', [SalesController::class, 'orderShow'])->name('orders.show');
        Route::post('/orders/{order}/confirm', [SalesController::class, 'orderConfirm'])->name('orders.confirm');
        Route::post('/orders/{order}/ship', [SalesController::class, 'orderShip'])->name('orders.ship');
        Route::post('/orders/{order}/complete', [SalesController::class, 'orderComplete'])->name('orders.complete');
        Route::post('/orders/{order}/cancel', [SalesController::class, 'orderCancel'])->name('orders.cancel');
        Route::post('/orders/{order}/pay', [SalesController::class, 'orderPay'])->name('orders.pay');
        // 报价单
        Route::get('/quotes', [SalesController::class, 'quotes'])->name('quotes');
        Route::get('/quotes/create', [SalesController::class, 'quoteCreate'])->name('quotes.create');
        Route::post('/quotes', [SalesController::class, 'quoteStore'])->name('quotes.store');
        Route::get('/quotes/{quote}', [SalesController::class, 'quoteShow'])->name('quotes.show');
        Route::post('/quotes/{quote}/status', [SalesController::class, 'quoteStatus'])->name('quotes.status');
        Route::post('/quotes/{quote}/convert', [SalesController::class, 'quoteConvert'])->name('quotes.convert');
        // 销售退货
        Route::get('/returns', [SalesController::class, 'returns'])->name('returns');
        Route::get('/returns/select', [SalesController::class, 'returnSelect'])->name('returns.select');
        Route::get('/returns/create/{order}', [SalesController::class, 'returnCreate'])->name('returns.create');
        Route::post('/returns/{order}', [SalesController::class, 'returnStore'])->name('returns.store');
        Route::get('/returns/{return}', [SalesController::class, 'returnShow'])->name('returns.show');
        Route::post('/returns/{return}/stock', [SalesController::class, 'returnStock'])->name('returns.stock');
        Route::post('/returns/{return}/refund', [SalesController::class, 'returnRefund'])->name('returns.refund');
        Route::post('/returns/{return}/cancel', [SalesController::class, 'returnCancel'])->name('returns.cancel');
    });

    // ---- 零售管理（retail.view）----
    Route::prefix('retail')->name('retail.')->middleware('permission:retail.view')->group(function () {
        // 会员
        Route::get('/members', [RetailController::class, 'members'])->name('members');
        Route::post('/members', [RetailController::class, 'memberStore'])->name('members.store');
        Route::post('/members/{member}/topup', [RetailController::class, 'memberTopUp'])->name('members.topup');
        Route::post('/members/{member}/toggle', [RetailController::class, 'memberToggle'])->name('members.toggle');
        // 门店开单
        Route::get('/pos', [RetailController::class, 'pos'])->name('pos');
        Route::post('/pos', [RetailController::class, 'posStore'])->name('pos.store');
        // 零售单 / 退货
        Route::get('/sales', [RetailController::class, 'sales'])->name('sales');
        Route::get('/sales/{sale}', [RetailController::class, 'saleShow'])->name('sales.show');
        Route::post('/sales/{sale}/refund', [RetailController::class, 'saleRefund'])->name('sales.refund');
        Route::get('/returns', [RetailController::class, 'returns'])->name('returns');
    });

    // 填写入口：登录用户即可参与（企业内全员问卷）
    // ---- 报表设计（report.design） ----
    Route::prefix('report-design')->name('report-design.')->middleware('permission:report.design')->group(function () {
        Route::get('/', [ReportDesignController::class, 'index'])->name('index');
        Route::get('/create', [ReportDesignController::class, 'create'])->name('create');
        Route::post('/', [ReportDesignController::class, 'store'])->name('store');
        Route::get('/{template}', [ReportDesignController::class, 'show'])->name('show');
        Route::get('/{template}/edit', [ReportDesignController::class, 'edit'])->name('edit');
        Route::put('/{template}', [ReportDesignController::class, 'update'])->name('update');
        Route::get('/{template}/export', [ReportDesignController::class, 'export'])->name('export');
        Route::post('/{template}/toggle', [ReportDesignController::class, 'toggle'])->name('toggle');
        Route::delete('/{template}', [ReportDesignController::class, 'destroy'])->name('destroy');
    });

});
