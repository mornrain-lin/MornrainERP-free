<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 合并快照：固化某一合并主体、某一期间的合并结果（含少数股东 /
 * 未实现利润 / 跨期等深化口径），用于审计留档与跨期对比。
 *
 * report_data 存序列化后的报表摘要（纯数组，不含 Eloquent 模型），
 * 便于只读回放，且天然跨数据库（SQLite / MySQL JSON 行为一致）。
 */
class ConsolidationSnapshot extends Model
{
    protected $fillable = [
        'root_org_id', 'period_from', 'period_to', 'generated_by', 'report_data',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'report_data' => 'array',
    ];

    public function root(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'root_org_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
