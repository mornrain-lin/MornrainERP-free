<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OaSchedule extends Model
{
    use HasFactory;

    public const TYPE_MEETING = 'meeting';
    public const TYPE_VISIT = 'visit';
    public const TYPE_DEADLINE = 'deadline';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'title', 'type', 'start_at', 'end_at', 'location', 'remark', 'creator_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'oa_schedule_user', 'schedule_id', 'user_id');
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_MEETING => '会议',
            self::TYPE_VISIT => '拜访',
            self::TYPE_DEADLINE => '截止',
            self::TYPE_OTHER => '其他',
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::typeLabels()[$type] ?? $type;
    }

    public static function typeTone(string $type): string
    {
        return match ($type) {
            self::TYPE_MEETING => 'info',
            self::TYPE_VISIT => 'warn',
            self::TYPE_DEADLINE => 'danger',
            default => 'muted',
        };
    }

    /** 时长（小时，保留 1 位；无结束时间返回 null） */
    public function durationHours(): ?float
    {
        if (! $this->end_at) {
            return null;
        }

        return round($this->start_at->diffInMinutes($this->end_at) / 60, 1);
    }
}
