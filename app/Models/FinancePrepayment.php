<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePrepayment extends Model
{
    use HasFactory;

    public const DIRECTION_OUT = 'out'; // 预付供应商款
    public const DIRECTION_IN = 'in';   // 预收客户款

    public const STATUS_PENDING = 'pending';   // 待核销
    public const STATUS_PARTIAL = 'partial';   // 部分核销
    public const STATUS_USED = 'used';         // 已核销
    public const STATUS_CANCELED = 'canceled'; // 已取消

    protected $fillable = [
        'prepay_no', 'direction', 'party_name', 'amount', 'used_amount',
        'status', 'account_id', 'occurred_on', 'remark', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'occurred_on' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 未核销余额 */
    public function getRemainingAttribute(): float
    {
        return round((float) $this->amount - (float) $this->used_amount, 2);
    }

    public static function directionLabel(string $direction): string
    {
        return $direction === self::DIRECTION_OUT ? '预付供应商' : '预收客户';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '待核销',
            self::STATUS_PARTIAL => '部分核销',
            self::STATUS_USED => '已核销',
            self::STATUS_CANCELED => '已取消',
            default => $status,
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warn',
            self::STATUS_PARTIAL => 'info',
            self::STATUS_USED => 'success',
            self::STATUS_CANCELED => 'muted',
            default => 'muted',
        };
    }
}
