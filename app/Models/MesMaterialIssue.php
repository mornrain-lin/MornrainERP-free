<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MesMaterialIssue extends Model
{
    use HasFactory;

    public const TYPE_ISSUE = 'issue';   // 领料（出库）
    public const TYPE_RETURN = 'return'; // 退料（入库）

    protected $fillable = [
        'work_order_id', 'product_id', 'type', 'quantity', 'operator_id', 'remark',
    ];

    protected $casts = [
        'quantity' => 'int',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MesWorkOrder::class, 'work_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public static function typeLabel(string $type): string
    {
        return $type === self::TYPE_RETURN ? '退料' : '领料';
    }
}
