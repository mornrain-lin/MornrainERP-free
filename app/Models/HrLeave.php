<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeave extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_ANNUAL = 'annual';
    public const TYPE_SICK = 'sick';
    public const TYPE_PERSONAL = 'personal';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'employee_id', 'type', 'start_date', 'end_date', 'days', 'reason', 'status',
        'workflow_instance_id', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'float',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => '待审批',
            self::STATUS_APPROVED => '已批准',
            self::STATUS_REJECTED => '已驳回',
            self::STATUS_CANCELLED => '已撤回',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusLabels()[$status] ?? $status;
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'muted',
            default => 'warn',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_ANNUAL => '年假',
            self::TYPE_SICK => '病假',
            self::TYPE_PERSONAL => '事假',
            default => '其他',
        };
    }
}
