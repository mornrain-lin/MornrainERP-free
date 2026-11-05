<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrEmployee extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_LEFT = 'left';

    protected $fillable = [
        'employee_no', 'name', 'department', 'position', 'gender', 'phone', 'email',
        'hire_date', 'leave_date', 'status', 'salary', 'bank_account', 'remark', 'created_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'leave_date' => 'date',
        'salary' => 'float',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(HrAttendance::class, 'employee_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(HrLeave::class, 'employee_id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(HrSalary::class, 'employee_id');
    }

    public static function statusLabel(string $status): string
    {
        return $status === self::STATUS_LEFT ? '已离职' : '在职';
    }

    public static function genderLabel(string $gender): string
    {
        return $gender === 'female' ? '女' : '男';
    }

    public static function nextNo(): string
    {
        return 'EMP-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    }

    /** 在职时长（年，保留 1 位） */
    public function tenureYears(): ?float
    {
        if (! $this->hire_date) {
            return null;
        }

        $end = $this->leave_date ?? now();

        return round($this->hire_date->diffInDays($end) / 365, 1);
    }
}
