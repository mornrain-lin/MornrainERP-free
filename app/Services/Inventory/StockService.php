<?php

namespace App\Services\Inventory;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Assembly;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 库存服务
 *
 * 约定：
 *  - 库存余额以 products.stock 为准，流水只解释「为什么变」
 *  - 发货即出库；退款完成自动回补
 *  - 允许负库存（超卖要被看见，而不是悄悄抹平），列表页会标红
 */
class StockService
{
    /**
     * 库存变动唯一入口
     *
     * @param  int  $quantity  正数入库，负数出库
     */
    public function adjust(
        Product $product,
        int $quantity,
        string $type,
        ?string $remark = null,
        ?int $orderId = null,
        ?int $userId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $type, $remark, $orderId, $userId) {
            $fresh = Product::whereKey($product->id)->first() ?? $product;
            $balance = (int) $fresh->stock + $quantity;

            $fresh->forceFill(['stock' => $balance])->save();

            return StockMovement::create([
                'product_id' => $fresh->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'order_id' => $orderId,
                'user_id' => $userId,
                'remark' => $remark,
            ]);
        });
    }

    /** 发货出库：按订单明细逐行扣减，未匹配到商品的 SKU 会被跳过 */
    public function deductForOrder(Order $order): int
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $skus = $items->pluck('sku')->filter()->unique()->all();
        $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

        $moved = 0;
        foreach ($items as $item) {
            $product = $products->get($item->sku);
            if (! $product) {
                continue;
            }
            $this->adjust(
                $product,
                -1 * (int) $item->quantity,
                StockMovement::TYPE_OUT,
                "订单 {$order->order_no} 发货出库",
                $order->id,
                auth()->id(),
            );
            $moved++;
        }

        return $moved;
    }

    /** 退款完成回补库存 */
    public function returnForOrder(Order $order): int
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        $skus = $items->pluck('sku')->filter()->unique()->all();
        $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

        $moved = 0;
        foreach ($items as $item) {
            $product = $products->get($item->sku);
            if (! $product) {
                continue;
            }
            $this->adjust(
                $product,
                (int) $item->quantity,
                StockMovement::TYPE_IN,
                "订单 {$order->order_no} 退款回补",
                $order->id,
                auth()->id(),
            );
            $moved++;
        }

        return $moved;
    }

    /**
     * 低于安全库存的商品
     *
     * 口径：商品未单独设置 safety_stock（为 0 / 空）时回退到系统参数
     * default_safety_stock（后台「系统参数」可改）。
     */
    public function lowStockProducts(): Collection
    {
        $default = max(0, (int) Setting::get('default_safety_stock', 0));
        $threshold = 'COALESCE(NULLIF(safety_stock, 0), ' . $default . ')';

        return Product::where('is_active', true)
            ->whereRaw($threshold . ' > 0')
            ->whereColumn('stock', '<=', DB::raw($threshold))
            ->orderBy('stock')
            ->get();
    }

    /**
     * 分仓库存变动（多仓库）
     *
     * 同时维护 products.stock（总量）与 warehouse_stocks（分仓），
     * 流水写入 warehouse_id，便于按仓追溯。
     *
     * @param  int  $quantity  正数入库，负数出库
     */
    public function adjustWarehouse(
        Product $product,
        int $quantity,
        string $type,
        int $warehouseId,
        ?string $remark = null,
        ?int $userId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $type, $warehouseId, $remark, $userId) {
            $fresh = Product::whereKey($product->id)->first() ?? $product;
            $balance = (int) $fresh->stock + $quantity;
            $fresh->forceFill(['stock' => $balance])->save();

            $wh = WarehouseStock::firstOrNew([
                'warehouse_id' => $warehouseId,
                'product_id' => $fresh->id,
            ]);
            $wh->stock = (int) $wh->stock + $quantity;
            $wh->save();

            return StockMovement::create([
                'product_id' => $fresh->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'user_id' => $userId,
                'remark' => $remark,
            ]);
        });
    }

    /** 执行仓库调拨：调出仓减、调入仓加（总量不变） */
    public function transfer(StockTransfer $transfer): void
    {
        if ($transfer->status === StockTransfer::STATUS_DONE) {
            return;
        }

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items ?? [] as $row) {
                $product = Product::find($row['product_id'] ?? null);
                if (! $product) {
                    continue;
                }
                $qty = (int) ($row['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $this->adjustWarehouse(
                    $product, -$qty, StockMovement::TYPE_TRANSFER_OUT,
                    $transfer->from_warehouse_id, "调拨 {$transfer->transfer_no} 出库", auth()->id(),
                );
                $this->adjustWarehouse(
                    $product, $qty, StockMovement::TYPE_TRANSFER_IN,
                    $transfer->to_warehouse_id, "调拨 {$transfer->transfer_no} 入库", auth()->id(),
                );
            }
            $transfer->status = StockTransfer::STATUS_DONE;
            $transfer->done_at = now();
            $transfer->save();
        });
    }

    /** 执行组装 / 拆卸：耗用一侧、产出另一侧 */
    public function assemble(Assembly $assembly): void
    {
        if ($assembly->status === Assembly::STATUS_DONE) {
            return;
        }

        DB::transaction(function () use ($assembly) {
            $main = Product::find($assembly->product_id);
            $qty = (int) $assembly->qty;
            if ($main && $qty > 0) {
                if ($assembly->type === Assembly::TYPE_ASSEMBLE) {
                    // 产出成品，耗用子件
                    $this->adjustWarehouse($main, $qty, StockMovement::TYPE_ASSEMBLE_IN, $this->defaultWarehouse(), "组装 {$assembly->doc_no} 产出", auth()->id());
                    foreach ($assembly->items ?? [] as $row) {
                        $comp = Product::find($row['product_id'] ?? null);
                        if (! $comp) {
                            continue;
                        }
                        $this->adjustWarehouse($comp, -(int) ($row['qty'] ?? 0) * $qty, StockMovement::TYPE_ASSEMBLE_OUT, $this->defaultWarehouse(), "组装 {$assembly->doc_no} 耗用", auth()->id());
                    }
                } else {
                    // 拆卸：耗用成品，产出子件
                    $this->adjustWarehouse($main, -$qty, StockMovement::TYPE_ASSEMBLE_OUT, $this->defaultWarehouse(), "拆卸 {$assembly->doc_no} 耗用", auth()->id());
                    foreach ($assembly->items ?? [] as $row) {
                        $comp = Product::find($row['product_id'] ?? null);
                        if (! $comp) {
                            continue;
                        }
                        $this->adjustWarehouse($comp, (int) ($row['qty'] ?? 0) * $qty, StockMovement::TYPE_ASSEMBLE_IN, $this->defaultWarehouse(), "拆卸 {$assembly->doc_no} 产出", auth()->id());
                    }
                }
            }
            $assembly->status = Assembly::STATUS_DONE;
            $assembly->done_at = now();
            $assembly->save();
        });
    }

    /** 组装拆卸无指定仓库时回退到默认仓 */
    protected function defaultWarehouse(): int
    {
        $wh = Warehouse::default()->first();

        return $wh?->id ?? Warehouse::query()->orderBy('id')->value('id') ?? 1;
    }


    /**
     * 采购建议：按近 N 天销量 + 安全库存测算补货量
     *
     * @return Collection<int, array{product: Product, sold: int, suggest: int, amount: float}>
     */
    public function purchaseSuggestions(int $days = 30): Collection
    {
        $validStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Shipped->value,
            OrderStatus::Completed->value,
        ];

        $sold = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays($days))
            ->whereIn('orders.status', $validStatuses)
            ->groupBy('order_items.sku')
            ->selectRaw('order_items.sku as sku, SUM(order_items.quantity) as qty')
            ->pluck('qty', 'sku');

        return Product::where('is_active', true)
            ->orderBy('sku')
            ->get()
            ->map(function (Product $p) use ($sold) {
                $sold30 = (int) ($sold[$p->sku] ?? 0);
                // 未单独设置安全库存时回退系统参数默认值
                $safety = (int) $p->safety_stock > 0
                    ? (int) $p->safety_stock
                    : max(0, (int) Setting::get('default_safety_stock', 0));
                $suggest = max(0, $safety + $sold30 - (int) $p->stock);

                return [
                    'product' => $p,
                    'sold' => $sold30,
                    'suggest' => $suggest,
                    'amount' => round($suggest * (float) $p->cost_price, 2),
                ];
            })
            ->filter(fn (array $row) => $row['suggest'] > 0)
            ->sortByDesc('suggest')
            ->values();
    }
}
