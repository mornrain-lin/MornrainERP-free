<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 排班（多班次考勤）
 *
 * 员工 × 日期 × 班次 唯一，一天支持多班次（如早班 + 晚班）。
 */
class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'shift_id', 'date', 'note', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
