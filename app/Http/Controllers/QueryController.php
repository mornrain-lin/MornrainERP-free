<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinanceFlow;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RetailMember;
use App\Models\RetailSale;
use App\Models\SaleOrder;
use App\Models\SaleQuote;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WorkflowInstance;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 综合查询：跨模块统一检索
 *
 *  - 一个关键词打通：跨境订单 / 销售 / 采购 / 零售 / 资金 / 流程 / 基础资料
 *  - 每组限量返回（默认 8 条），点击直达对应单据详情
 *  - 结果可整体导出 CSV
 */
class QueryController extends Controller
{
    private const PER_GROUP = 8;

    /** 分组定义：key => [标题, 图标, 所属 scope] */
    private const GROUPS = [
        'orders'      => ['跨境订单', '▤', 'trade'],
        'sale_orders' => ['销售订单', '📝', 'trade'],
        'quotes'      => ['报价单', '📄', 'trade'],
        'returns'     => ['销售退货', '↩', 'trade'],
        'purchases'   => ['采购单', '📦', 'trade'],
        'retail'      => ['零售单', '🧾', 'retail'],
        'members'     => ['零售会员', '👥', 'retail'],
        'flows'       => ['资金流水', '📊', 'finance'],
        'workflows'   => ['审批流程', '📑', 'workflow'],
        'products'    => ['商品', '▦', 'base'],
        'customers'   => ['客户', '🤝', 'base'],
        'suppliers'   => ['供应商', '🚚', 'base'],
        'warehouses'  => ['仓库', '🏬', 'base'],
    ];

    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));
        $scope = (string) $request->query('scope', 'all');
        $isAdmin = $request->user()?->role === \App\Models\User::ROLE_ADMIN;
        $groups = [];
        $total = 0;

        if ($keyword !== '') {
            $like = '%' . $this->escapeLike($keyword) . '%';

            $add = function (string $key, \Illuminate\Database\Eloquent\Builder $query, array $columns, callable $mapper) use (&$groups, &$total, $like) {
                $rows = $query->where(function ($q) use ($columns, $like) {
                    foreach ($columns as $col) {
                        $q->orWhere($col, 'like', $like);
                    }
                })->latest('id')->limit(self::PER_GROUP)->get();

                if ($rows->isNotEmpty()) {
                    $groups[$key] = [
                        'title' => self::GROUPS[$key][0],
                        'icon' => self::GROUPS[$key][1],
                        'rows' => $rows->map($mapper),
                        'shown' => $rows->count(),
                    ];
                    $total += $rows->count();
                }
            };

            if (in_array($scope, ['all', 'trade'], true)) {
                $add('orders', Order::query(), ['order_no', 'buyer_name'], fn ($o) => [
                    'label' => $o->order_no,
                    'desc' => '买家 ' . ($o->buyer_name ?: '—'),
                    'badge' => $o->status instanceof \App\Enums\OrderStatus ? $o->status->label() : (string) $o->status,
                    'url' => route('orders.show', $o),
                ]);
                $add('sale_orders', SaleOrder::query(), ['so_no'], fn ($o) => [
                    'label' => $o->so_no,
                    'desc' => '¥' . number_format((float) $o->total_amount, 2) . ' · ' . ($o->customer?->name ?? '散客'),
                    'badge' => SaleOrder::statusLabel($o->status),
                    'url' => route('sales.orders.show', $o),
                ]);
                $add('quotes', SaleQuote::query(), ['quote_no'], fn ($o) => [
                    'label' => $o->quote_no,
                    'desc' => '¥' . number_format((float) $o->total_amount, 2) . ' · ' . ($o->customer?->name ?? '散客'),
                    'badge' => SaleQuote::statusLabel($o->status),
                    'url' => route('sales.quotes.show', $o),
                ]);
                $add('returns', SaleReturn::query(), ['return_no', 'reason'], fn ($o) => [
                    'label' => $o->return_no,
                    'desc' => '¥' . number_format((float) $o->total_amount, 2) . ' · ' . ($o->reason ?: '—'),
                    'badge' => SaleReturn::statusLabel($o->status),
                    'url' => route('sales.returns.show', $o),
                ]);
                $add('purchases', PurchaseOrder::query(), ['po_no', 'remark'], fn ($o) => [
                    'label' => $o->po_no,
                    'desc' => '¥' . number_format((float) $o->total_amount, 2) . ' · ' . ($o->supplier?->name ?? '—'),
                    'badge' => $o->status instanceof \App\Enums\POStatus ? $o->status->label() : (string) $o->status,
                    'url' => route('purchases.show', $o),
                ]);
            }
            if (in_array($scope, ['all', 'retail'], true)) {
                $add('retail', RetailSale::query(), ['sale_no', 'remark'], fn ($o) => [
                    'label' => $o->sale_no,
                    'desc' => '¥' . number_format((float) $o->total, 2) . ' · ' . ($o->member?->name ?? '散客'),
                    'badge' => '零售单',
                    'url' => route('retail.sales.show', $o),
                ]);
                $add('members', RetailMember::query(), ['card_no', 'name', 'phone'], fn ($o) => [
                    'label' => $o->card_no . ' · ' . $o->name,
                    'desc' => '储值 ¥' . number_format((float) $o->balance, 2) . ' · ' . $o->points . ' 积分',
                    'badge' => $o->is_active ? '在用' : '停用',
                    'url' => route('retail.members'),
                ]);
            }
            // 资金流水含敏感余额，仅管理员可检索
            if ($isAdmin && in_array($scope, ['all', 'finance'], true)) {
                $add('flows', FinanceFlow::query(), ['flow_no', 'remark'], fn ($o) => [
                    'label' => $o->flow_no,
                    'desc' => ($o->direction === 'income' ? '+' : '−') . '¥' . number_format((float) $o->amount, 2)
                        . ' · ' . ($o->account?->name ?? '—') . ' · ' . ($o->remark ?: '—'),
                    'badge' => FinanceFlow::categoryLabel($o->category),
                    'url' => route('finance.flows'),
                ]);
            }
            if (in_array($scope, ['all', 'workflow'], true)) {
                $add('workflows', WorkflowInstance::query(), ['title', 'workflow_key'], fn ($o) => [
                    'label' => $o->title,
                    'desc' => $o->workflow_key . ' · 申请人 ' . ($o->applicant?->name ?? '—'),
                    'badge' => (string) $o->status,
                    'url' => route('workflows.show', $o),
                ]);
            }
            if (in_array($scope, ['all', 'base'], true)) {
                $add('products', Product::query(), ['sku', 'name'], fn ($o) => [
                    'label' => $o->sku . ' · ' . $o->name,
                    'desc' => '库存 ' . (int) $o->stock . ' · 成本 ¥' . number_format((float) $o->cost_price, 2),
                    'badge' => $o->is_active ? '在售' : '停售',
                    'url' => route('products.index'),
                ]);
                $add('customers', Customer::query(), ['name', 'contact', 'phone'], fn ($o) => [
                    'label' => $o->name,
                    'desc' => trim(($o->contact ?: '') . ' ' . ($o->phone ?: '')),
                    'badge' => $o->is_active ? '在用' : '停用',
                    'url' => route('sales.customers'),
                ]);
                $add('suppliers', Supplier::query(), ['name', 'contact', 'phone'], fn ($o) => [
                    'label' => $o->name,
                    'desc' => trim(($o->contact ?: '') . ' ' . ($o->phone ?: '')),
                    'badge' => $o->is_active ? '在用' : '停用',
                    'url' => route('suppliers.index'),
                ]);
                $add('warehouses', Warehouse::query(), ['name', 'location'], fn ($o) => [
                    'label' => $o->name . ($o->is_default ? '（默认）' : ''),
                    'desc' => $o->location ?: '—',
                    'badge' => '仓库',
                    'url' => route('warehouses.index'),
                ]);
            }
        }

        return view('query.index', compact('keyword', 'scope', 'groups', 'total'));
    }

    /** 导出全部命中结果 CSV（带 BOM） */
    public function export(Request $request): StreamedResponse
    {
        $request->merge(['q' => (string) $request->query('q', '')]);
        // 复用 index 的分组逻辑，但取全量（每组上限提高到 200）
        $keyword = trim((string) $request->query('q', ''));
        $scope = (string) $request->query('scope', 'all');
        $isAdmin = $request->user()?->role === \App\Models\User::ROLE_ADMIN;
        $rows = [];

        if ($keyword !== '') {
            $like = '%' . $this->escapeLike($keyword) . '%';
            $collect = function (string $title, \Illuminate\Database\Eloquent\Builder $query, array $columns, callable $mapper) use (&$rows, $like) {
                $query->where(function ($q) use ($columns, $like) {
                    foreach ($columns as $col) {
                        $q->orWhere($col, 'like', $like);
                    }
                })->latest('id')->limit(200)->get()->each(function ($m) use (&$rows, $title, $mapper) {
                    $r = $mapper($m);
                    $rows[] = [$title, $r['label'], $r['desc'], $r['badge']];
                });
            };

            if (in_array($scope, ['all', 'trade'], true)) {
                $collect('跨境订单', Order::query(), ['order_no', 'buyer_name'], fn ($o) => ['label' => $o->order_no, 'desc' => '买家 ' . ($o->buyer_name ?: '—'), 'badge' => (string) $o->status]);
                $collect('销售订单', SaleOrder::query(), ['so_no'], fn ($o) => ['label' => $o->so_no, 'desc' => '¥' . number_format((float) $o->total_amount, 2), 'badge' => SaleOrder::statusLabel($o->status)]);
                $collect('报价单', SaleQuote::query(), ['quote_no'], fn ($o) => ['label' => $o->quote_no, 'desc' => '¥' . number_format((float) $o->total_amount, 2), 'badge' => SaleQuote::statusLabel($o->status)]);
                $collect('销售退货', SaleReturn::query(), ['return_no', 'reason'], fn ($o) => ['label' => $o->return_no, 'desc' => '¥' . number_format((float) $o->total_amount, 2), 'badge' => SaleReturn::statusLabel($o->status)]);
                $collect('采购单', PurchaseOrder::query(), ['po_no', 'remark'], fn ($o) => ['label' => $o->po_no, 'desc' => '¥' . number_format((float) $o->total_amount, 2), 'badge' => (string) $o->status]);
            }
            if (in_array($scope, ['all', 'retail'], true)) {
                $collect('零售单', RetailSale::query(), ['sale_no', 'remark'], fn ($o) => ['label' => $o->sale_no, 'desc' => '¥' . number_format((float) $o->total, 2), 'badge' => '零售单']);
                $collect('零售会员', RetailMember::query(), ['card_no', 'name', 'phone'], fn ($o) => ['label' => $o->card_no . ' · ' . $o->name, 'desc' => '储值 ¥' . number_format((float) $o->balance, 2), 'badge' => $o->is_active ? '在用' : '停用']);
            }
            if ($isAdmin && in_array($scope, ['all', 'finance'], true)) {
                $collect('资金流水', FinanceFlow::query(), ['flow_no', 'remark'], fn ($o) => ['label' => $o->flow_no, 'desc' => '¥' . number_format((float) $o->amount, 2), 'badge' => FinanceFlow::categoryLabel($o->category)]);
            }
            if (in_array($scope, ['all', 'workflow'], true)) {
                $collect('审批流程', WorkflowInstance::query(), ['title', 'workflow_key'], fn ($o) => ['label' => $o->title, 'desc' => $o->workflow_key, 'badge' => (string) $o->status]);
            }
            if (in_array($scope, ['all', 'base'], true)) {
                $collect('商品', Product::query(), ['sku', 'name'], fn ($o) => ['label' => $o->sku, 'desc' => $o->name . ' · 库存 ' . (int) $o->stock, 'badge' => $o->is_active ? '在售' : '停售']);
                $collect('客户', Customer::query(), ['name', 'contact', 'phone'], fn ($o) => ['label' => $o->name, 'desc' => trim(($o->contact ?: '') . ' ' . ($o->phone ?: '')), 'badge' => $o->is_active ? '在用' : '停用']);
                $collect('供应商', Supplier::query(), ['name', 'contact', 'phone'], fn ($o) => ['label' => $o->name, 'desc' => trim(($o->contact ?: '') . ' ' . ($o->phone ?: '')), 'badge' => $o->is_active ? '在用' : '停用']);
                $collect('仓库', Warehouse::query(), ['name', 'location'], fn ($o) => ['label' => $o->name, 'desc' => $o->location ?: '—', 'badge' => '仓库']);
            }
        }

        $fileName = 'query_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $keyword) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ["综合查询结果（关键词：{$keyword}）"]);
            fputcsv($out, ['模块', '单号 / 名称', '说明', '状态']);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** 转义 LIKE 通配符（轻量：只处理 % 与 _） */
    private function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
