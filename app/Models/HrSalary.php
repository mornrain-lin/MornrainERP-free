<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSalary extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'employee_id', 'period', 'base_salary', 'bonus', 'deductions',
        'gross_pay', 'social_insurance', 'housing_fund', 'income_tax', 'attendance_deduction',
        'net_pay', 'status', 'paid_at', 'payroll_detail', 'remark',
    ];

    protected $casts = [
        'base_salary' => 'float',
        'bonus' => 'float',
        'deductions' => 'float',
        'gross_pay' => 'float',
        'social_insurance' => 'float',
        'housing_fund' => 'float',
        'income_tax' => 'float',
        'attendance_deduction' => 'float',
        'net_pay' => 'float',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function getPayrollDetailAttribute(?string $value): array
    {
        $decoded = json_decode($value ?? '{}', true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setPayrollDetailAttribute(array $value): void
    {
        $this->attributes['payroll_detail'] = json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public static function statusLabel(string $status): string
    {
        return $status === self::STATUS_PAID ? '已发放' : '待发';
    }

    public static function statusTone(string $status): string
    {
        return $status === self::STATUS_PAID ? 'success' : 'warn';
    }
}
