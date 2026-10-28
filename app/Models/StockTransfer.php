<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELED = 'canceled';

    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_TRANSFER_IN = 'transfer_in';

    protected $fillable = [
        'transfer_no', 'from_warehouse_id', 'to_warehouse_id', 'status',
        'note', 'items', 'created_by', 'done_at',
    ];

    protected $casts = ['items' => 'array', 'done_at' => 'datetime'];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '待发出',
            self::STATUS_IN_TRANSIT => '在途',
            self::STATUS_DONE => '已完成',
            self::STATUS_CANCELED => '已取消',
            default => $status,
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warn',
            self::STATUS_IN_TRANSIT => 'info',
            self::STATUS_DONE => 'success',
            self::STATUS_CANCELED => 'muted',
            default => 'muted',
        };
    }
}
