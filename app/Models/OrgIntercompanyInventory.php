<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 内部存货交易（未实现利润的来源）。
 *
 * 组织 A 将存货卖给组织 B：
 *   amount = 售价，cost = 成本，profit = 毛利（= amount − cost）；
 *   unsold_ratio = 期末仍在 B 手里未售出的比例（0~1）；
 *   unrealized_profit = profit × unsold_ratio = 合并时需抵减的未实现利润。
 *
 * 刻意不挂 BelongsToOrganization：它天然跨组织，施加单组织作用域会让
 * 合并引擎读不到完整两端。
 */
class OrgIntercompanyInventory extends Model
{
    protected $fillable = [
        'period_from', 'period_to', 'seller_org_id', 'buyer_org_id',
        'amount', 'cost', 'profit', 'unsold_ratio', 'unrealized_profit', 'note', 'created_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'amount' => 'float',
        'cost' => 'float',
        'profit' => 'float',
        'unsold_ratio' => 'float',
        'unrealized_profit' => 'float',
    ];

    /** 与给定期间有重叠的未实现利润记录 */
    public function scopeInPeriod(Builder $q, string $from, string $to): Builder
    {
        return $q->where('period_from', '<=', $to)
            ->where('period_to', '>=', $from);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'seller_org_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'buyer_org_id');
    }
}
