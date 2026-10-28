<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUST = 'adjust';
    public const TYPE_PO_IN = 'po_in'; // 采购入库
    public const TYPE_TRANSFER_OUT = 'transfer_out'; // 调拨出库
    public const TYPE_TRANSFER_IN = 'transfer_in';   // 调拨入库
    public const TYPE_ASSEMBLE_IN = 'assemble_in';   // 组装产出
    public const TYPE_ASSEMBLE_OUT = 'assemble_out'; // 组装耗用
    public const TYPE_MES_ISSUE = 'mes_issue';       // 生产领料出库
    public const TYPE_MES_RETURN = 'mes_return';     // 生产退料入库
    public const TYPE_MES_FINISH = 'mes_finish';     // 生产完工入库
    public const TYPE_MALL_OUT = 'mall_out';         // 商城销售出库
    public const TYPE_OTHER = 'other';               // 其他出入库

    protected $fillable = [
        'product_id', 'type', 'quantity', 'balance_after', 'order_id', 'user_id', 'remark', 'warehouse_id',
    ];

    protected $casts = [
        'quantity' => 'int',
        'balance_after' => 'int',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_IN => '入库',
            self::TYPE_OUT => '出库',
            self::TYPE_PO_IN => '采购入库',
            self::TYPE_TRANSFER_OUT => '调拨出库',
            self::TYPE_TRANSFER_IN => '调拨入库',
            self::TYPE_ASSEMBLE_IN => '组装产出',
            self::TYPE_ASSEMBLE_OUT => '组装耗用',
            self::TYPE_MES_ISSUE => '生产领料',
            self::TYPE_MES_RETURN => '生产退料',
            self::TYPE_MES_FINISH => '生产完工',
            self::TYPE_MALL_OUT => '商城销售',
            self::TYPE_OTHER => '其他',
            default => '盘点',
        };
    }
}
