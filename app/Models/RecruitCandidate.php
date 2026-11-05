<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 招聘候选人
 *
 * 简历登记 + 应聘状态流转（new → interviewing → offered → hired / rejected）。
 * hired 时通过 RecruitService::hire 联动创建员工档案（hr_employees），
 * employee_id 保存对应关系；多轮面试见 interviews 关联。
 */
class RecruitCandidate extends Model
{
    protected $table = 'hr_recruit_candidates';

    public const STATUS_NEW = 'new';
    public const STATUS_INTERVIEWING = 'interviewing';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_HIRED = 'hired';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'job_id', 'name', 'phone', 'email', 'gender', 'source',
        'resume_text', 'salary_expect', 'status', 'employee_id', 'created_by',
    ];

    protected $casts = [
        'salary_expect' => 'float',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(RecruitJob::class, 'job_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(RecruitInterview::class, 'candidate_id')->orderByDesc('round');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_INTERVIEWING => '面试中',
            self::STATUS_OFFERED => '已发 Offer',
            self::STATUS_HIRED => '已入职',
            self::STATUS_REJECTED => '已淘汰',
            default => '新登记',
        };
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            'referral' => '内部推荐',
            'website' => '招聘网站',
            'other' => '其他',
            default => '内推',
        };
    }

    /** 最近一次面试结果是否通过（面试轮次最大的一条） */
    public function latestInterviewPassed(): bool
    {
        $latest = $this->interviews()->orderByDesc('round')->first();

        return $latest !== null && $latest->result === RecruitInterview::RESULT_PASS;
    }
}
