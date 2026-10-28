<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';   // 待入库
    public const STATUS_STOCKED = 'stocked';   // 已入库（货已回仓）
    public const STATUS_REFUNDED = 'refunded'; // 已退款（闭环）
    public const STATUS_CANCELED = 'canceled'; // 已取消

    protected $fillable = [
        'return_no', 'sale_order_id', 'customer_id', 'status', 'return_date',
        'total_amount', 'refund_amount', 'reason', 'remark',
        'stocked_at', 'refunded_at', 'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'stocked_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'sale_return_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '待入库',
            self::STATUS_STOCKED => '已入库',
            self::STATUS_REFUNDED => '已退款',
            self::STATUS_CANCELED => '已取消',
            default => $status,
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warn',
            self::STATUS_STOCKED => 'info',
            self::STATUS_REFUNDED => 'success',
            self::STATUS_CANCELED => 'muted',
            default => 'muted',
        };
    }
}
