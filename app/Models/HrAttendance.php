<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendance extends Model
{
    use HasFactory;

    public const TYPE_PRESENT = 'present';
    public const TYPE_LATE = 'late';
    public const TYPE_ABSENT = 'absent';
    public const TYPE_LEAVE = 'leave';
    public const TYPE_HALF = 'half';

    protected $fillable = [
        'employee_id', 'date', 'type', 'check_in', 'check_out', 'remark', 'shift_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    /** 班次（多班次考勤） */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PRESENT => '正常',
            self::TYPE_LATE => '迟到',
            self::TYPE_ABSENT => '缺勤',
            self::TYPE_LEAVE => '请假',
            self::TYPE_HALF => '半天',
        ];
    }

    public static function typeTone(string $type): string
    {
        return match ($type) {
            self::TYPE_PRESENT => 'success',
            self::TYPE_LATE => 'warn',
            self::TYPE_ABSENT => 'danger',
            self::TYPE_LEAVE => 'info',
            self::TYPE_HALF => 'muted',
            default => 'muted',
        };
    }
}
