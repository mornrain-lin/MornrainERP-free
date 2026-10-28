<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assembly extends Model
{
    public const TYPE_ASSEMBLE = 'assemble';     // 组装：耗用子件 -> 产出成品
    public const TYPE_DISASSEMBLE = 'disassemble'; // 拆卸：耗用成品 -> 产出子件

    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'doc_no', 'type', 'product_id', 'qty', 'note', 'items', 'status', 'created_by', 'done_at',
    ];

    protected $casts = ['items' => 'array', 'done_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function typeLabel(string $type): string
    {
        return $type === self::TYPE_DISASSEMBLE ? '拆卸' : '组装';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '待执行',
            self::STATUS_DONE => '已完成',
            self::STATUS_CANCELED => '已取消',
            default => $status,
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warn',
            self::STATUS_DONE => 'success',
            self::STATUS_CANCELED => 'muted',
            default => 'muted',
        };
    }
}
