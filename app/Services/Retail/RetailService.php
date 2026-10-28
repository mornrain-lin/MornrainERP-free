<?php

namespace App\Services\Retail;

use App\Models\Product;
use App\Models\RetailMember;
use App\Models\RetailReturn;
use App\Models\RetailSale;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;

/**
 * 零售服务（线下门店 POS，人民币）
 *
 *  - 开单即扣库存（StockService 唯一入口）
 *  - 会员折扣 + 手动优惠叠加；储值支付扣会员余额；消费按 ¥1=1 积分累计
 *  - 退货为整单退：回补库存、按原支付方式退款（储值支付退回余额）、扣回积分
 */
class RetailService
{
    public function __construct(private StockService $stock) {}

    /** 门店开单 */
    public function createSale(
        ?int $memberId,
        array $items,
        float $manualDiscount = 0.0,
        string $paymentMethod = RetailSale::PAY_CASH,
        ?string $remark = null,
        ?int $cashierId = null,
    ): RetailSale {
        return DB::transaction(function () use ($memberId, $items, $manualDiscount, $paymentMethod, $remark, $cashierId) {
            $member = $memberId ? RetailMember::find($memberId) : null;
            $rows = $this->normalizeItems($items);

            $subtotal = round(array_sum(array_map(fn ($r) => $r['qty'] * $r['unit_price'], $rows)), 2);
            $memberDiscount = $member && (float) $member->discount < 1.0
                ? round($subtotal * (1 - (float) $member->discount), 2)
                : 0.0;
            $totalDiscount = round($memberDiscount + max(0, $manualDiscount), 2);
            $total = round(max(0, $subtotal - $totalDiscount), 2);

            // 储值支付：扣会员余额
            if ($paymentMethod === RetailSale::PAY_BALANCE) {
                if (! $member) {
                    abort(422, '储值支付需要选择会员');
                }
                if ((float) $member->balance < $total) {
                    abort(422, '会员储值余额不足（当前 ¥' . number_format((float) $member->balance, 2) . '）');
                }
            }

            $sale = RetailSale::create([
                'sale_no' => $this->nextSaleNo(),
                'member_id' => $member?->id,
                'status' => RetailSale::STATUS_COMPLETED,
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'points_earned' => (int) floor($total),
                'cashier_id' => $cashierId,
                'remark' => $remark,
                'sold_at' => now(),
            ]);

            foreach ($rows as $row) {
                $sale->items()->create([
                    'product_id' => $row['product_id'],
                    'sku' => $row['sku'],
                    'product_name' => $row['name'],
                    'quantity' => $row['qty'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => round($row['qty'] * $row['unit_price'], 2),
                ]);

                $product = $row['product_id'] ? Product::find($row['product_id']) : null;
                if ($product) {
                    $this->stock->adjust($product, -1 * $row['qty'], StockMovement::TYPE_OUT, "零售单 {$sale->sale_no} 销售出库", null, $cashierId);
                }
            }

            if ($member) {
                if ($paymentMethod === RetailSale::PAY_BALANCE) {
                    $member->decrement('balance', $total);
                }
                $member->increment('points', (int) floor($total));
            }

            return $sale->load('items', 'member');
        });
    }

    /** 整单退货：回补库存 + 退款（储值支付退回余额）+ 扣回积分 */
    public function createReturn(RetailSale $sale, ?string $reason = null, ?int $handlerId = null): RetailReturn
    {
        if ($sale->status !== RetailSale::STATUS_COMPLETED) {
            abort(422, '仅已完成的零售单可退货');
        }

        return DB::transaction(function () use ($sale, $reason, $handlerId) {
            foreach ($sale->items as $item) {
                $product = $item->product_id ? Product::find($item->product_id) : null;
                if ($product) {
                    $this->stock->adjust($product, (int) $item->quantity, StockMovement::TYPE_IN, "零售退货回补", null, $handlerId);
                }
            }

            $member = $sale->member;
            if ($member) {
                // 扣回积分
                if ($sale->points_earned > 0) {
                    $member->decrement('points', min($sale->points_earned, (int) $member->points));
                }
                // 储值支付退回余额
                if ($sale->payment_method === RetailSale::PAY_BALANCE) {
                    $member->increment('balance', (float) $sale->total);
                }
            }

            $return = RetailReturn::create([
                'return_no' => $this->nextReturnNo(),
                'retail_sale_id' => $sale->id,
                'status' => RetailReturn::STATUS_COMPLETED,
                'total' => $sale->total,
                'reason' => $reason,
                'handler_id' => $handlerId,
            ]);

            $sale->update(['status' => RetailSale::STATUS_REFUNDED]);

            return $return;
        });
    }

    /** 会员充值 */
    public function topUp(RetailMember $member, float $amount): RetailMember
    {
        if ($amount <= 0) {
            return $member;
        }
        $member->increment('balance', $amount);

        return $member;
    }

    /** 归一化明细（明细行列表） */
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
            $out[] = ['product_id' => $pid, 'sku' => $p->sku, 'name' => $p->name, 'qty' => $qty, 'unit_price' => $price];
        }

        return $out;
    }

    private function nextSaleNo(): string
    {
        return 'RS' . date('Ymd') . str_pad(RetailSale::whereDate('created_at', now())->count() + 1, 3, '0', STR_PAD_LEFT);
    }

    private function nextReturnNo(): string
    {
        return 'RT' . date('Ymd') . str_pad(RetailReturn::whereDate('created_at', now())->count() + 1, 3, '0', STR_PAD_LEFT);
    }
}
