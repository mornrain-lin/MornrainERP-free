<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SaleQuote;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Services\Finance\FinanceService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;

/**
 * 销售服务（B 端，人民币）
 *
 *  - 销售订单与跨境电商 orders 分立；金额一律 CNY
 *  - 发货经 StockService::adjust() 扣减库存（库存唯一入口），流水注明销售单号
 *  - 回款只累计 paid_amount，不自动产生财务流水（资金流水在财务管理登记，保持模块解耦）
 *  - 报价单可一键转销售订单
 *  - 销售退货：入库回补库存（StockService）+ 退款记账（FinanceService，CAT_REFUND_OUT）
 */
class SalesService
{
    public function __construct(
        private StockService $stock,
        private FinanceService $finance,
    ) {}

    /** 创建销售订单（含明细，返回带明细与总额的订单） */
    public function createOrder(
        ?int $customerId,
        array $items,
        ?string $orderDate = null,
        ?string $expectedAt = null,
        ?string $remark = null,
        ?int $userId = null,
    ): SaleOrder {
        return DB::transaction(function () use ($customerId, $items, $orderDate, $expectedAt, $remark, $userId) {
            $order = SaleOrder::create([
                'so_no' => $this->nextSoNo(),
                'customer_id' => $customerId,
                'status' => SaleOrder::STATUS_DRAFT,
                'order_date' => $orderDate ?: now()->toDateString(),
                'expected_at' => $expectedAt,
                'total_amount' => 0,
                'paid_amount' => 0,
                'remark' => $remark,
                'created_by' => $userId,
            ]);

            $total = $this->syncItems($order, $items);

            $order->update(['total_amount' => $total]);

            return $order->load('items', 'customer');
        });
    }

    public function confirmOrder(SaleOrder $order): SaleOrder
    {
        if ($order->status === SaleOrder::STATUS_DRAFT) {
            $order->update(['status' => SaleOrder::STATUS_CONFIRMED]);
        }

        return $order;
    }

    /** 发货：扣减库存（库存唯一入口），状态 → 已发货 */
    public function shipOrder(SaleOrder $order, ?int $userId = null): int
    {
        if ($order->status !== SaleOrder::STATUS_CONFIRMED) {
            return 0;
        }

        return DB::transaction(function () use ($order, $userId) {
            $moved = 0;
            foreach ($order->items as $item) {
                $product = $item->product_id ? Product::find($item->product_id) : null;
                if (! $product) {
                    continue;
                }
                $this->stock->adjust(
                    $product, -1 * (int) $item->quantity, StockMovement::TYPE_OUT,
                    "销售订单 {$order->so_no} 发货出库", null, $userId,
                );
                $moved++;
            }
            $order->update(['status' => SaleOrder::STATUS_SHIPPED, 'shipped_at' => $order->shipped_at ?? now()]);

            return $moved;
        });
    }

    public function completeOrder(SaleOrder $order): SaleOrder
    {
        if ($order->status === SaleOrder::STATUS_SHIPPED) {
            $order->update(['status' => SaleOrder::STATUS_COMPLETED]);
        }

        return $order;
    }

    public function cancelOrder(SaleOrder $order): SaleOrder
    {
        if (in_array($order->status, [SaleOrder::STATUS_DRAFT, SaleOrder::STATUS_CONFIRMED], true)) {
            $order->update(['status' => SaleOrder::STATUS_CANCELED]);
        }

        return $order;
    }

    /** 登记回款（可多次，累计到 paid_amount） */
    public function registerPayment(SaleOrder $order, float $amount): SaleOrder
    {
        if ($amount <= 0) {
            return $order;
        }
        $paid = min(round((float) $order->paid_amount + $amount, 2), (float) $order->total_amount);
        $order->update(['paid_amount' => $paid]);

        return $order;
    }

    /** 创建报价单 */
    public function createQuote(
        ?int $customerId,
        array $items,
        ?string $validUntil = null,
        ?string $remark = null,
        ?int $userId = null,
    ): SaleQuote {
        $rows = $this->normalizeItems($items);
        $total = round(array_sum(array_map(fn ($r) => $r['qty'] * $r['unit_price'], $rows)), 2);

        return SaleQuote::create([
            'quote_no' => $this->nextQuoteNo(),
            'customer_id' => $customerId,
            'status' => SaleQuote::STATUS_DRAFT,
            'valid_until' => $validUntil,
            'total_amount' => $total,
            'items' => $rows,
            'remark' => $remark,
            'created_by' => $userId,
        ]);
    }

    public function quoteStatus(SaleQuote $quote, string $status): SaleQuote
    {
        $allowed = [SaleQuote::STATUS_DRAFT, SaleQuote::STATUS_SENT, SaleQuote::STATUS_ACCEPTED, SaleQuote::STATUS_REJECTED, SaleQuote::STATUS_EXPIRED];
        if (in_array($status, $allowed, true) && ! in_array($quote->status, [SaleQuote::STATUS_CONVERTED], true)) {
            $quote->update(['status' => $status]);
        }

        return $quote;
    }

    /** 报价单转销售订单（草稿状态） */
    public function convertQuote(SaleQuote $quote, ?int $userId = null): ?SaleOrder
    {
        if ($quote->status === SaleQuote::STATUS_CONVERTED || empty($quote->items)) {
            return null;
        }
        $items = array_map(fn ($r) => [
            'product_id' => $r['product_id'] ?? null,
            'qty' => $r['qty'] ?? 1,
            'unit_price' => $r['unit_price'] ?? 0,
        ], $quote->items);

        $order = $this->createOrder($quote->customer_id, $items, null, null, "由报价单 {$quote->quote_no} 转入", $userId);
        $quote->update(['status' => SaleQuote::STATUS_CONVERTED]);

        return $order;
    }

    /** 写入 / 覆盖订单明细，返回总额 */
    private function syncItems(SaleOrder $order, array $items): float
    {
        $order->items()->delete();
        $total = 0.0;
        foreach ($this->normalizeItems($items) as $row) {
            $lineTotal = round($row['qty'] * $row['unit_price'], 2);
            $total += $lineTotal;
            $order->items()->create([
                'product_id' => $row['product_id'],
                'sku' => $row['sku'],
                'product_name' => $row['name'],
                'quantity' => $row['qty'],
                'unit_price' => $row['unit_price'],
                'line_total' => $lineTotal,
            ]);
        }

        return round($total, 2);
    }

    /** 归一化明细：接受「明细行列表」[{product_id,qty,unit_price}, ...]，附带 sku/name 快照 */
    private function normalizeItems(array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $p = Product::find($pid);
            if (! $p) {
                continue;
            }
            $out[] = [
                'product_id' => $pid,
                'sku' => $p->sku,
                'name' => $p->name,
                'qty' => $qty,
                'unit_price' => $price,
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------- 销售退货

    /**
     * 创建退货单（按原销售订单明细行退货）
     *
     * @param  array  $lines  [{sale_order_item_id, qty}, ...]
     */
    public function createReturn(
        SaleOrder $order,
        array $lines,
        ?string $reason = null,
        ?string $returnDate = null,
        ?string $remark = null,
        ?int $userId = null,
    ): SaleReturn {
        if (! in_array($order->status, [SaleOrder::STATUS_SHIPPED, SaleOrder::STATUS_COMPLETED], true)) {
            abort(422, '仅已发货 / 已完成的销售订单可发起退货');
        }

        return DB::transaction(function () use ($order, $lines, $reason, $returnDate, $remark, $userId) {
            $sourceItems = $order->items()->get()->keyBy('id');
            // 该订单历史非取消退货的已退数量（按明细行汇总）
            $returned = SaleReturn::where('sale_order_id', $order->id)
                ->where('status', '!=', SaleReturn::STATUS_CANCELED)
                ->with('items')
                ->get()
                ->flatMap(fn ($r) => $r->items)
                ->groupBy('sale_order_item_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            $return = SaleReturn::create([
                'return_no' => $this->nextReturnNo(),
                'sale_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => SaleReturn::STATUS_PENDING,
                'return_date' => $returnDate ?: now()->toDateString(),
                'total_amount' => 0,
                'refund_amount' => 0,
                'reason' => $reason,
                'remark' => $remark,
                'created_by' => $userId,
            ]);

            $total = 0.0;
            foreach ($lines as $line) {
                $itemId = (int) ($line['sale_order_item_id'] ?? 0);
                $qty = (int) ($line['qty'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }
                /** @var SaleOrderItem|null $src */
                $src = $sourceItems->get($itemId);
                if (! $src) {
                    continue;
                }
                $already = (int) ($returned[$itemId] ?? 0);
                if ($already + $qty > (int) $src->quantity) {
                    abort(422, "SKU {$src->sku} 退货数量超出可退数量（原 {$src->quantity} / 已退 {$already}）");
                }
                $lineTotal = round($qty * (float) $src->unit_price, 2);
                $total += $lineTotal;
                $return->items()->create([
                    'sale_order_item_id' => $src->id,
                    'product_id' => $src->product_id,
                    'sku' => $src->sku,
                    'product_name' => $src->product_name,
                    'quantity' => $qty,
                    'unit_price' => $src->unit_price,
                    'line_total' => $lineTotal,
                ]);
            }

            if ($total <= 0) {
                abort(422, '请至少填写一行有效的退货数量');
            }

            $return->update(['total_amount' => round($total, 2)]);

            return $return->load('items', 'order', 'customer');
        });
    }

    /** 退货入库：货回仓库，回补库存（库存唯一入口），状态 → 已入库 */
    public function stockReturn(SaleReturn $return, ?int $userId = null): SaleReturn
    {
        if ($return->status !== SaleReturn::STATUS_PENDING) {
            return $return;
        }

        return DB::transaction(function () use ($return, $userId) {
            foreach ($return->items as $item) {
                $product = $item->product_id ? Product::find($item->product_id) : null;
                if (! $product) {
                    continue;
                }
                $this->stock->adjust(
                    $product, (int) $item->quantity, StockMovement::TYPE_IN,
                    "销售退货 {$return->return_no} 入库回补", null, $userId,
                );
            }
            $return->update(['status' => SaleReturn::STATUS_STOCKED, 'stocked_at' => now()]);

            return $return;
        });
    }

    /** 退货退款：资金唯一入口记支出（CAT_REFUND_OUT），状态 → 已退款 */
    public function refundReturn(SaleReturn $return, FinanceAccount $account, float $amount, ?int $userId = null): SaleReturn
    {
        if ($return->status !== SaleReturn::STATUS_STOCKED) {
            return $return;
        }
        $amount = round(abs($amount), 2);
        if ($amount <= 0 || $amount > (float) $return->total_amount) {
            abort(422, '退款金额必须大于 0 且不超过退货金额');
        }

        return DB::transaction(function () use ($return, $account, $amount, $userId) {
            $this->finance->record(
                $account,
                FinanceFlow::DIRECTION_EXPENSE,
                FinanceFlow::CAT_REFUND_OUT,
                $amount,
                null,
                "销售退货 {$return->return_no} 退款",
                'sale_return',
                $return->id,
                $userId,
            );
            $return->update([
                'status' => SaleReturn::STATUS_REFUNDED,
                'refund_amount' => $amount,
                'refunded_at' => now(),
            ]);

            return $return;
        });
    }

    /** 取消退货单（仅待入库状态可取消；已入库的货已在仓，需另做出库） */
    public function cancelReturn(SaleReturn $return): SaleReturn
    {
        if ($return->status === SaleReturn::STATUS_PENDING) {
            $return->update(['status' => SaleReturn::STATUS_CANCELED]);
        }

        return $return;
    }

    private function nextReturnNo(): string
    {
        return 'SR' . date('Ymd') . str_pad(SaleReturn::whereDate('created_at', now())->count() + 1, 3, '0', STR_PAD_LEFT);
    }

    private function nextSoNo(): string
    {
        return 'SO' . date('Ymd') . str_pad(SaleOrder::whereDate('created_at', now())->count() + 1, 3, '0', STR_PAD_LEFT);
    }

    private function nextQuoteNo(): string
    {
        return 'QT' . date('Ymd') . str_pad(SaleQuote::whereDate('created_at', now())->count() + 1, 3, '0', STR_PAD_LEFT);
    }
}
