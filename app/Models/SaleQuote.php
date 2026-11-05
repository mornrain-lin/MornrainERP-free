<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Traits\BelongsToOrganization;
class SaleQuote extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'quote_no', 'customer_id', 'status', 'valid_until', 'total_amount', 'items', 'remark', 'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'valid_until' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => '草稿',
            self::STATUS_SENT => '已发送',
            self::STATUS_ACCEPTED => '已接受',
            self::STATUS_REJECTED => '已拒绝',
            self::STATUS_CONVERTED => '已转订单',
            self::STATUS_EXPIRED => '已过期',
            default => $status,
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'muted',
            self::STATUS_SENT => 'info',
            self::STATUS_ACCEPTED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CONVERTED => 'success',
            self::STATUS_EXPIRED => 'muted',
            default => 'muted',
        };
    }
}
