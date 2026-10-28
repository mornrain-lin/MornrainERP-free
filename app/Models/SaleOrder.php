<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'so_no', 'customer_id', 'status', 'order_date', 'expected_at', 'shipped_at',
        'total_amount', 'paid_amount', 'remark', 'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_at' => 'date',
        'shipped_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 未回款余额 */
    public function getRemainingAttribute(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function isSettled(): bool
    {
        return $this->remaining <= 0;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => '草稿',
            self::STATUS_CONFIRMED => '已确认',
            self::STATUS_SHIPPED => '已发货',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELED => '已取消',
            default => $status,
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'muted',
            self::STATUS_CONFIRMED => 'info',
            self::STATUS_SHIPPED => 'warn',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELED => 'muted',
            default => 'muted',
        };
    }
}
