<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailReturn extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = ['return_no', 'retail_sale_id', 'status', 'total', 'reason', 'handler_id'];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    public static function statusLabel(string $status): string
    {
        return $status === self::STATUS_CANCELED ? '已取消' : '已退款';
    }
}
