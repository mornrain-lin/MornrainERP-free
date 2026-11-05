<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AfterSaleTicket extends Model
{
    use HasFactory;

    // 状态
    public const STATUS_PENDING = 'pending';     // 待处理
    public const STATUS_PROCESSING = 'processing'; // 处理中
    public const STATUS_WAITING = 'waiting';     // 待客户确认
    public const STATUS_RESOLVED = 'resolved';   // 已解决
    public const STATUS_CLOSED = 'closed';       // 已关闭

    // 类型
    public const TYPE_RETURN = 'return';         // 退货
    public const TYPE_EXCHANGE = 'exchange';     // 换货
    public const TYPE_REPAIR = 'repair';         // 维修
    public const TYPE_REFUND = 'refund';         // 退款
    public const TYPE_COMPLAINT = 'complaint';   // 投诉
    public const TYPE_CONSULT = 'consult';       // 咨询

    // 优先级
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'ticket_no', 'order_id', 'order_no', 'customer_id', 'customer_name', 'contact',
        'type', 'priority', 'subject', 'description', 'status', 'handler_id',
        'resolution', 'resolved_at', 'closed_at', 'created_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AfterSaleLog::class, 'ticket_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'order_id');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_WAITING => '待客户确认',
            self::STATUS_RESOLVED => '已解决',
            self::STATUS_CLOSED => '已关闭',
            default => $status,
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warn',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_WAITING => 'warn',
            self::STATUS_RESOLVED => 'success',
            self::STATUS_CLOSED => 'muted',
            default => 'muted',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_RETURN => '退货',
            self::TYPE_EXCHANGE => '换货',
            self::TYPE_REPAIR => '维修',
            self::TYPE_REFUND => '退款',
            self::TYPE_COMPLAINT => '投诉',
            self::TYPE_CONSULT => '咨询',
            default => $type,
        };
    }

    public static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => '低',
            self::PRIORITY_MEDIUM => '中',
            self::PRIORITY_HIGH => '高',
            self::PRIORITY_URGENT => '紧急',
            default => $priority,
        };
    }

    public static function priorityTone(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => 'muted',
            self::PRIORITY_MEDIUM => 'info',
            self::PRIORITY_HIGH => 'warn',
            self::PRIORITY_URGENT => 'danger',
            default => 'muted',
        };
    }
}
