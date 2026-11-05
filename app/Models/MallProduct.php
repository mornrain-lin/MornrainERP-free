<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商城商品（B2C 商品上架）
 *
 * 关联 ERP 商品（products），库存口径统一走 products.stock；
 * 此处只承载商城售价、促销原价、封面、上下架与累计销量。
 */
class MallProduct extends Model
{
    protected $fillable = [
        'product_id', 'name', 'sku', 'price', 'original_price',
        'cover', 'description', 'is_online', 'sales_count', 'sort',
    ];

    protected $casts = [
        'price' => 'float',
        'original_price' => 'float',
        'is_online' => 'bool',
        'sales_count' => 'int',
        'sort' => 'int',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(MallCart::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(MallOrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MallReview::class);
    }

    /** 前台可见评价 */
    public function visibleReviews(): HasMany
    {
        return $this->reviews()->visible();
    }

    /** 评价平均分（0 = 暂无评价） */
    public function avgRating(): float
    {
        $avg = $this->visibleReviews()->avg('rating');

        return $avg ? round((float) $avg, 1) : 0;
    }

    public function reviewCount(): int
    {
        return (int) $this->visibleReviews()->count();
    }

    /** 是否处于促销（商城价低于原价） */
    public function isPromo(): bool
    {
        return $this->original_price > 0 && $this->price < $this->original_price;
    }

    /** 节省金额（促销时） */
    public function savedAmount(): float
    {
        return $this->isPromo() ? round($this->original_price - $this->price, 2) : 0;
    }

    /** 当前可售库存（取关联 ERP 商品） */
    public function availableStock(): int
    {
        return (int) ($this->product?->stock ?? 0);
    }
}
