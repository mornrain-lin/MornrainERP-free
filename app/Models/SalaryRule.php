<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 薪资规则（单行生效配置）
 *
 *  - 个税起征点 + 月度累进税率表
 *  - 社保（养老/医疗/失业）与公积金个人比例
 *  - 考勤联动扣款（缺勤日扣款倍数 × 日薪、迟到单次扣款）
 */
class SalaryRule extends Model
{
    use HasFactory;

    protected $table = 'hr_salary_rules';

    protected $fillable = [
        'name', 'tax_threshold', 'pension_rate', 'medical_rate', 'unemployment_rate',
        'housing_rate', 'late_deduct', 'deduct_per_day', 'tax_brackets', 'is_active', 'created_by',
    ];

    protected $casts = [
        'tax_threshold' => 'float',
        'pension_rate' => 'float',
        'medical_rate' => 'float',
        'unemployment_rate' => 'float',
        'housing_rate' => 'float',
        'late_deduct' => 'float',
        'deduct_per_day' => 'float',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTaxBracketsAttribute(?string $value): array
    {
        $decoded = json_decode($value ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setTaxBracketsAttribute(array $value): void
    {
        $this->attributes['tax_brackets'] = json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
    }

    /** 社保个人合计比例 */
    public function socialRate(): float
    {
        return $this->pension_rate + $this->medical_rate + $this->unemployment_rate;
    }
}
