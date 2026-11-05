<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 商城优惠券模板
 *
 *  - type: fixed=立减（value=金额） / percent=折扣（value=百分比，如 85 表示 85 折）
 *  - 状态 active/disabled；到期自动失效；claimed_count 记录领取量
 */
class MallCoupon extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $table = 'mall_coupons';

    protected $fillable = [
        'code', 'name', 'type', 'value', 'min_amount', 'total',
        'claimed_count', 'per_user_limit', 'starts_at', 'ends_at', 'status',
    ];

    protected $casts = [
        'value' => 'float',
        'min_amount' => 'float',
        'total' => 'int',
        'claimed_count' => 'int',
        'per_user_limit' => 'int',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    /** 是否过期（按 ends_at 判定，未设置视为长期有效） */
    public function isExpired(): bool
    {
        if (! $this->ends_at) {
            return false;
        }

        return $this->ends_at->lt(now()->toDateString());
    }

    /** 是否可领取：启用 + 未过期 + 未领完 */
    public function isClaimable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || $this->isExpired()) {
            return false;
        }
        if ($this->total > 0 && $this->claimed_count >= $this->total) {
            return false;
        }

        return true;
    }

    /** 券面额文案 */
    public function label(): string
    {
        if ($this->type === self::TYPE_PERCENT) {
            return $this->value . ' 折';
        }

        return '¥' . number_format($this->value, 0);
    }

    /** 对指定金额的优惠额 */
    public function discountFor(float $amount): float
    {
        $amount = max(0, $amount);
        if ($amount < $this->min_amount) {
            return 0;
        }
        if ($this->type === self::TYPE_PERCENT) {
            return round($amount * (100 - $this->value) / 100, 2);
        }

        return round(min($this->value, $amount), 2);
    }

    /** 使用规则文案 */
    public function usageText(): string
    {
        $min = $this->min_amount > 0 ? "满 ¥{$this->min_amount} 可用" : '无门槛';
        $expire = $this->ends_at ? "，{$this->ends_at->format('Y-m-d')} 前有效" : '';

        return $min . $expire;
    }
}
