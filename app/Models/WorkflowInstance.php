<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'workflow_key', 'category', 'title', 'applicant_id', 'data',
        'status', 'current_node', 'node_count', 'approved_at',
    ];

    protected $casts = [
        'data' => 'array',
        'approved_at' => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowInstanceLog::class, 'instance_id');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '审批中',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已驳回',
            self::STATUS_CANCELED => '已撤回',
            default => $status,
        };
    }

    /** 状态对应的徽标色调（沿用订单状态机配色） */
    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warn',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELED => 'muted',
            default => 'muted',
        };
    }
}
