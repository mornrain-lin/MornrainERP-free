<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmTask extends Model
{
    use HasFactory;

    public const STATUS_TODO = 'todo';
    public const STATUS_DOING = 'doing';
    public const STATUS_DONE = 'done';
    public const STATUS_BLOCKED = 'blocked';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';

    protected $fillable = [
        'project_id', 'title', 'assignee_id', 'status', 'priority',
        'estimate_hours', 'spent_hours', 'due_date', 'remark', 'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'estimate_hours' => 'decimal:1',
        'spent_hours' => 'decimal:1',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(PmTimesheet::class, 'task_id');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_TODO => '待处理',
            self::STATUS_DOING => '进行中',
            self::STATUS_DONE => '已完成',
            self::STATUS_BLOCKED => '已阻塞',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusLabels()[$status] ?? $status;
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_TODO => 'muted',
            self::STATUS_DOING => 'warn',
            self::STATUS_DONE => 'success',
            self::STATUS_BLOCKED => 'danger',
            default => 'muted',
        };
    }

    public static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => '低',
            self::PRIORITY_HIGH => '高',
            default => '普通',
        };
    }

    /** 预估 vs 实际工时的偏差比例（无预估时为 null） */
    public function hoursRatio(): ?float
    {
        if (! $this->estimate_hours || (float) $this->estimate_hours <= 0) {
            return null;
        }

        return round(((float) $this->spent_hours / (float) $this->estimate_hours) * 100, 1);
    }
}
