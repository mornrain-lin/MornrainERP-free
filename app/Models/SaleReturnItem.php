<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Traits\BelongsToOrganization;
class SaleReturnItem extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'sale_return_id', 'sale_order_item_id', 'product_id',
        'sku', 'product_name', 'quantity', 'unit_price', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
