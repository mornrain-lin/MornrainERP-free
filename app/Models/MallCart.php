<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 购物车（按用户聚合，下单时转商城订单）
 */
class MallCart extends Model
{
    protected $fillable = [
        'user_id', 'mall_product_id', 'qty',
    ];

    protected $casts = [
        'qty' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mallProduct(): BelongsTo
    {
        return $this->belongsTo(MallProduct::class);
    }

    /** 小计（商城价 × 数量） */
    public function subtotal(): float
    {
        return round((float) ($this->mallProduct?->price ?? 0) * $this->qty, 2);
    }
}
