<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 招聘职位
 *
 * 企业发布的在招岗位：维护部门 / 编制 / 薪资区间与招聘状态。
 * 候选人数（candidates_count）由候选人表实时统计。
 */
class RecruitJob extends Model
{
    protected $table = 'hr_recruit_jobs';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'job_title', 'department', 'position', 'headcount',
        'salary_range', 'location', 'description', 'status', 'created_by',
    ];

    protected $casts = [
        'headcount' => 'int',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(RecruitCandidate::class, 'job_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasManyThrough(RecruitInterview::class, RecruitCandidate::class, 'job_id', 'candidate_id');
    }

    public static function statusLabel(string $status): string
    {
        return $status === self::STATUS_OPEN ? '招聘中' : '已关闭';
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
