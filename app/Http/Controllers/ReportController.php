<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\POStatus;
use App\Models\Customer;
use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\FinancePrepayment;
use App\Models\Order;
use App\Models\Platform;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RetailMember;
use App\Models\RetailReturn;
use App\Models\RetailSale;
use App\Models\SaleOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * 利润报表：按平台 / 店铺 / 日期三个维度聚合营收、成本、毛利与毛利率
     */
    public function profit(Request $request)
    {
        $from = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', Carbon::today()->toDateString());

        $orders = Order::query()
            ->with(['platform', 'shop'])
            ->withAggregates()
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->get();

        $valid = $orders->filter(fn (Order $o) => $o->status->countsAsRevenue());

        // 汇总
        $totalRevenue = 0.0;
        $totalCost = 0.0;
        $totalProfit = 0.0;
        $costBreakdown = ['商品采购' => 0.0, '物流' => 0.0, '广告' => 0.0, '其他' => 0.0];
        foreach ($valid as $o) {
            $p = $o->profit();
            $totalRevenue += $p['revenue'];
            $totalProfit += $p['profit'];
            $costBreakdown['商品采购'] += $p['product_cost'];
            $costBreakdown['物流'] += (float) $o->shipping_cost;
            $costBreakdown['广告'] += (float) $o->ad_cost;
            $costBreakdown['其他'] += (float) $o->other_cost;
        }
        $totalCost = array_sum($costBreakdown);
        $margin = $totalRevenue > 0 ? round($totalProfit / $totalRevenue * 100, 2) : 0;

        // 广告费汇总：广告费以人民币计，直接累加
        $totalAd = round($valid->sum(fn (Order $o) => (float) $o->ad_cost), 2);
        $adShare = $totalRevenue > 0 ? round($totalAd / $totalRevenue * 100, 2) : 0;
        // ROAS = 营收 / 广告费（广告费为零时返回 null，页面显示 —）
        $roas = $totalAd > 0 ? round($totalRevenue / $totalAd, 2) : null;

        // 按平台
        $byPlatform = [];
        foreach (Platform::orderBy('id')->get() as $platform) {
            $rows = $valid->where('platform_id', $platform->id);
            $rev = $pro = 0.0;
            foreach ($rows as $o) {
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $byPlatform[] = [
                'name' => $platform->name,
                'color' => $platform->color,
                'orders' => $rows->count(),
                'revenue' => round($rev, 2),
                'profit' => round($pro, 2),
                'margin' => $rev > 0 ? round($pro / $rev * 100, 1) : 0,
            ];
        }

        // 按店铺
        $byShop = [];
        foreach (Shop::with('platform')->orderBy('id')->get() as $shop) {
            $rows = $valid->where('shop_id', $shop->id);
            if ($rows->isEmpty()) {
                continue;
            }
            $rev = $pro = 0.0;
            foreach ($rows as $o) {
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $byShop[] = [
                'name' => $shop->display_name,
                'orders' => $rows->count(),
                'revenue' => round($rev, 2),
                'profit' => round($pro, 2),
                'margin' => $rev > 0 ? round($pro / $rev * 100, 1) : 0,
            ];
        }

        // 按日
        $byDay = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        while ($cursor->lte($end)) {
            $rows = $valid->filter(fn (Order $o) => $o->created_at->isSameDay($cursor));
            $rev = $pro = 0.0;
            foreach ($rows as $o) {
                $p = $o->profit();
                $rev += $p['revenue'];
                $pro += $p['profit'];
            }
            $byDay[] = ['date' => $cursor->format('m-d'), 'revenue' => round($rev, 2), 'profit' => round($pro, 2)];
            $cursor->addDay();
        }

        // 亏损订单 Top
        $lossOrders = $valid
            ->map(fn (Order $o) => ['order' => $o, 'profit' => $o->profit()])
            ->filter(fn ($x) => $x['profit']['profit'] < 0)
            ->sortBy('profit.profit')
            ->take(8)
            ->values();

        // SKU 毛利贡献 Top
        $skuStats = [];
        foreach ($valid as $o) {
            foreach ($o->items as $it) {
                $key = $it->sku;
                $skuStats[$key] ??= ['sku' => $key, 'name' => $it->product_name, 'qty' => 0, 'revenue' => 0.0, 'cost' => 0.0];
                $skuStats[$key]['qty'] += $it->quantity;
                $skuStats[$key]['revenue'] += round($it->line_total * (float) $o->exchange_rate, 2);
                $skuStats[$key]['cost'] += $it->line_cost;
            }
        }
        $skuTop = collect($skuStats)
            ->map(function ($s) {
                $s['profit'] = round($s['revenue'] - $s['cost'], 2);
                $s['margin'] = $s['revenue'] > 0 ? round($s['profit'] / $s['revenue'] * 100, 1) : 0;

                return $s;
            })
            ->sortByDesc('profit')
            ->take(10)
            ->values();

        return view('reports.profit', compact(
            'from', 'to', 'totalRevenue', 'totalCost', 'totalProfit', 'margin',
            'totalAd', 'adShare', 'roas',
            'costBreakdown', 'byPlatform', 'byShop', 'byDay', 'lossOrders', 'skuTop'
        ) + ['orderCount' => $valid->count()]);
    }

    /**
     * 财务报表：收支流水按月份 / 分类聚合，账户余额与预付款概览
     *
     * 口径说明：财务模块一律人民币（CNY）；
     * 收支流水与账户余额一一对应（写流水即改余额），期内净额 = 收入 − 支出。
     */
    public function finance(Request $request)
    {
        $from = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', Carbon::today()->toDateString());

        $flows = FinanceFlow::query()
            ->with('account')
            ->whereBetween('occurred_on', [$from, $to])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $incomeFlows = $flows->where('direction', FinanceFlow::DIRECTION_INCOME);
        $expenseFlows = $flows->where('direction', FinanceFlow::DIRECTION_EXPENSE);
        $totalIncome = round($incomeFlows->sum('amount'), 2);
        $totalExpense = round($expenseFlows->sum('amount'), 2);
        $net = round($totalIncome - $totalExpense, 2);

        // ---- 按月聚合 ----
        $byMonth = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $endMonth = Carbon::parse($to)->startOfMonth();
        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m');
            $rows = $flows->filter(fn (FinanceFlow $f) => $f->occurred_on?->format('Y-m') === $key);
            $byMonth[] = [
                'month' => $cursor->format('y年m月'),
                'income' => round($rows->where('direction', FinanceFlow::DIRECTION_INCOME)->sum('amount'), 2),
                'expense' => round($rows->where('direction', FinanceFlow::DIRECTION_EXPENSE)->sum('amount'), 2),
            ];
            $cursor->addMonth();
        }

        // ---- 分类汇总（收入 / 支出两套分类） ----
        $incomeCats = $this->categoryBreakdown($incomeFlows, FinanceFlow::categories(FinanceFlow::DIRECTION_INCOME), $totalIncome);
        $expenseCats = $this->categoryBreakdown($expenseFlows, FinanceFlow::categories(FinanceFlow::DIRECTION_EXPENSE), $totalExpense);

        // ---- 账户余额概览 ----
        $accounts = FinanceAccount::orderByDesc('is_active')->orderBy('id')->get();
        $activeBalance = round($accounts->where('is_active', true)->sum('balance'), 2);

        // ---- 预付款 / 预收款未核销 ----
        $prepayPending = FinancePrepayment::query()
            ->with('account')
            ->whereIn('status', [FinancePrepayment::STATUS_PENDING, FinancePrepayment::STATUS_PARTIAL])
            ->orderBy('occurred_on')
            ->get();
        $prepayOut = round($prepayPending->where('direction', FinancePrepayment::DIRECTION_OUT)->sum(fn ($p) => $p->remaining), 2);
        $prepayIn = round($prepayPending->where('direction', FinancePrepayment::DIRECTION_IN)->sum(fn ($p) => $p->remaining), 2);

        // ---- 往来余额：销售应收 + 采购应付 ----
        $receivable = round((float) SaleOrder::query()
            ->where('status', '!=', SaleOrder::STATUS_CANCELED)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as v')
            ->value('v'), 2);

        return view('reports.finance', compact(
            'from', 'to', 'totalIncome', 'totalExpense', 'net',
            'byMonth', 'incomeCats', 'expenseCats',
            'accounts', 'activeBalance', 'prepayPending', 'prepayOut', 'prepayIn',
            'receivable', 'flows'
        ));
    }

    /**
     * 分类汇总：把流水集合按分类键聚合成 [{label, amount, share}]
     *
     * @param  \Illuminate\Support\Collection<int, FinanceFlow>  $flows
     * @param  array<string, string>  $categories
     * @return list<array{label: string, amount: float, share: float}>
     */
    private function categoryBreakdown($flows, array $categories, float $total): array
    {
        $rows = [];
        foreach ($categories as $key => $label) {
            $amount = round((float) $flows->where('category', $key)->sum('amount'), 2);
            $rows[] = [
                'label' => $label,
                'amount' => $amount,
                'share' => $total > 0 ? round($amount / $total * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * 经营报表：销售 / 采购 / 库存 / 零售多维分析
     *
     * 口径：人民币（CNY）；销售订单与采购单均排除已取消。
     */
    public function business(Request $request)
    {
        $from = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', Carbon::today()->toDateString());

        // ---- 销售分析 ----
        $saleOrders = SaleOrder::query()
            ->with('customer')
            ->whereBetween('order_date', [$from, $to])
            ->where('status', '!=', SaleOrder::STATUS_CANCELED)
            ->get();
        $saleTotal = round($saleOrders->sum('total_amount'), 2);
        $salePaid = round($saleOrders->sum('paid_amount'), 2);
        $saleReceivable = round($saleTotal - $salePaid, 2);

        $byCustomer = $saleOrders
            ->groupBy('customer_id')
            ->map(function ($rows) {
                return [
                    'name' => $rows->first()->customer?->name ?? '（未关联客户）',
                    'orders' => $rows->count(),
                    'total' => round($rows->sum('total_amount'), 2),
                    'paid' => round($rows->sum('paid_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        // ---- 采购分析 ----
        $purchaseOrders = PurchaseOrder::query()
            ->with('supplier')
            ->whereBetween('order_date', [$from, $to])
            ->where('status', '!=', POStatus::Cancelled->value)
            ->get();
        $purchaseTotal = round($purchaseOrders->sum('total_amount'), 2);

        $bySupplier = $purchaseOrders
            ->groupBy('supplier_id')
            ->map(function ($rows) {
                return [
                    'name' => $rows->first()->supplier?->name ?? '（未关联供应商）',
                    'orders' => $rows->count(),
                    'total' => round($rows->sum('total_amount'), 2),
                    'received' => $rows->where('status', POStatus::Received)->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        // ---- 按月销售 / 采购趋势 ----
        $saleByMonth = $this->monthlyTrend($saleOrders, 'order_date', 'total_amount', $from, $to);
        $purchaseByMonth = $this->monthlyTrend($purchaseOrders, 'order_date', 'total_amount', $from, $to);

        // ---- 库存分析 ----
        // 口径：products.stock 是全局总库存（StockService 唯一入口维护），
        // warehouse_stocks 是分仓明细；总价值按主表算，避免未分仓数据被漏计。
        $warehouseStocks = WarehouseStock::with(['warehouse', 'product'])->get();
        $stockQty = (int) Product::sum('stock');
        $stockValue = round((float) Product::query()
            ->selectRaw('COALESCE(SUM(stock * cost_price), 0) as v')
            ->value('v'), 2);
        $defaultSafety = max(0, (int) \App\Models\Setting::get('default_safety_stock', 0));
        $threshold = 'COALESCE(NULLIF(safety_stock, 0), ' . $defaultSafety . ')';
        $lowStockCount = Product::query()
            ->whereRaw($threshold . ' > 0')
            ->whereColumn('stock', '<=', \Illuminate\Support\Facades\DB::raw($threshold))
            ->count();
        $byWarehouse = $warehouseStocks
            ->groupBy('warehouse_id')
            ->map(function ($rows) {
                $value = $rows->sum(fn ($ws) => (float) $ws->stock * (float) $ws->product?->cost_price);

                return [
                    'name' => $rows->first()->warehouse?->name ?? '未分仓',
                    'skus' => $rows->count(),
                    'qty' => (int) $rows->sum('stock'),
                    'value' => round($value, 2),
                ];
            })
            ->sortByDesc('value')
            ->values();

        // ---- 零售概览 ----
        $retailSales = RetailSale::query()
            ->whereBetween('sold_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->where('status', RetailSale::STATUS_COMPLETED)
            ->get();
        $retailAmount = round($retailSales->sum('total'), 2);
        $retailReturns = RetailReturn::query()
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->get();
        $memberCount = RetailMember::count();

        // ---- 基础资料规模 ----
        $counts = [
            'customers' => Customer::count(),
            'suppliers' => Supplier::count(),
            'products' => Product::count(),
            'users' => User::count(),
        ];

        return view('reports.business', compact(
            'from', 'to',
            'saleOrders', 'saleTotal', 'salePaid', 'saleReceivable', 'byCustomer',
            'purchaseOrders', 'purchaseTotal', 'bySupplier',
            'saleByMonth', 'purchaseByMonth',
            'stockQty', 'stockValue', 'lowStockCount', 'byWarehouse',
            'retailSales', 'retailAmount', 'retailReturns', 'memberCount', 'counts'
        ));
    }

    /**
     * 按月聚合任意模型的金额趋势
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return list<array{month: string, amount: float}>
     */
    private function monthlyTrend($rows, string $dateField, string $amountField, string $from, string $to): array
    {
        $trend = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $endMonth = Carbon::parse($to)->startOfMonth();
        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m');
            $amount = $rows
                ->filter(fn ($row) => $row->{$dateField}?->format('Y-m') === $key)
                ->sum(fn ($row) => (float) $row->{$amountField});
            $trend[] = ['month' => $cursor->format('y年m月'), 'amount' => round($amount, 2)];
            $cursor->addMonth();
        }

        return $trend;
    }

    /**
     * 导出收支流水 CSV（带 BOM，Excel 打开不乱码）
     */
    public function financeExport(Request $request): StreamedResponse
    {
        $from = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', Carbon::today()->toDateString());

        $flows = FinanceFlow::query()
            ->with('account')
            ->whereBetween('occurred_on', [$from, $to])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $filename = "港湾ERP-finance-{$from}-{$to}.csv";

        return response()->streamDownload(function () use ($flows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($handle, ['流水号', '发生日期', '方向', '分类', '账户', '金额(CNY)', '备注']);

            foreach ($flows as $flow) {
                fputcsv($handle, [
                    $flow->flow_no,
                    $flow->occurred_on?->format('Y-m-d'),
                    FinanceFlow::directionLabel($flow->direction),
                    FinanceFlow::categoryLabel($flow->category),
                    $flow->account?->name,
                    $flow->amount,
                    $flow->remark,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 导出订单利润明细 CSV（带 BOM，Excel 打开不乱码）
     */
    public function export(Request $request): StreamedResponse
    {
        $from = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', Carbon::today()->toDateString());

        $orders = Order::query()
            ->with(['platform', 'shop'])
            ->withAggregates()
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->orderBy('created_at')
            ->get();

        $filename = "港湾ERP-profit-{$from}-{$to}.csv";

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($handle, [
                '订单号', '下单时间', '平台', '店铺', '状态',
                '营收(CNY)', '商品成本(CNY)', '物流(CNY)', '广告(CNY)', '其他(CNY)', '毛利(CNY)', '毛利率(%)',
            ]);

            foreach ($orders as $order) {
                $p = $order->profit();
                fputcsv($handle, [
                    $order->order_no,
                    $order->created_at?->format('Y-m-d H:i'),
                    $order->platform?->name,
                    $order->shop?->name,
                    $order->status->label(),
                    $p['revenue'],
                    $p['product_cost'],
                    (float) $order->shipping_cost,
                    (float) $order->ad_cost,
                    (float) $order->other_cost,
                    $p['profit'],
                    $p['margin'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
