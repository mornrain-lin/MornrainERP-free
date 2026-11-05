<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Traits\BelongsToOrganization;
class RetailSale extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELED = 'canceled';

    public const PAY_CASH = 'cash';
    public const PAY_WECHAT = 'wechat';
    public const PAY_ALIPAY = 'alipay';
    public const PAY_CARD = 'card';
    public const PAY_BALANCE = 'balance';

    protected $fillable = [
        'sale_no', 'member_id', 'status', 'subtotal', 'discount', 'total',
        'payment_method', 'points_earned', 'cashier_id', 'remark', 'sold_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'points_earned' => 'int',
        'sold_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RetailSaleItem::class, 'retail_sale_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(RetailMember::class, 'member_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(RetailReturn::class, 'retail_sale_id');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_REFUNDED => '已退货',
            self::STATUS_CANCELED => '已取消',
            default => '已完成',
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_REFUNDED => 'danger',
            self::STATUS_CANCELED => 'muted',
            default => 'success',
        };
    }

    public static function paymentLabel(string $method): string
    {
        return match ($method) {
            self::PAY_WECHAT => '微信',
            self::PAY_ALIPAY => '支付宝',
            self::PAY_CARD => '银行卡',
            self::PAY_BALANCE => '储值余额',
            default => '现金',
        };
    }
}
