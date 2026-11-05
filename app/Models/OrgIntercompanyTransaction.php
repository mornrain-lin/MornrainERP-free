<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 组织间内部往来登记（合并抵消依据）。
 *
 * 注意：本模型刻意不挂 BelongsToOrganization trait——内部往来天然跨组织，
 * 若施加单组织全局作用域，合并引擎将无法读取完整的往来两端。
 */
class OrgIntercompanyTransaction extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELED = 'canceled';

    public const CAT_TRANSFER = 'transfer';
    public const CAT_LOAN = 'loan';
    public const CAT_MANAGEMENT_FEE = 'management_fee';
    public const CAT_COST_SHARING = 'cost_sharing';
    public const CAT_OTHER = 'other';

    protected $fillable = [
        'txn_no', 'from_organization_id', 'to_organization_id', 'amount', 'currency',
        'occurred_on', 'category', 'status', 'from_account_id', 'to_account_id',
        'from_flow_id', 'to_flow_id', 'remark', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
        'from_organization_id' => 'integer',
        'to_organization_id' => 'integer',
    ];

    public function fromOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'from_organization_id');
    }

    public function toOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'to_organization_id');
    }

    /**
     * 账户关联：调用方若处于某个组织的上下文中，跨组织账户会被全局作用域过滤，
     * 需要时请显式用 FinanceAccount::withoutGlobalScope('organization') 查询。
     */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'to_account_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /** 两端是否都落在给定合并范围内（决定能否抵消） */
    public function isFullyInside(array $orgIds): bool
    {
        return in_array((int) $this->from_organization_id, $orgIds, true)
            && in_array((int) $this->to_organization_id, $orgIds, true);
    }

    /** 是否恰好只有一端落在合并范围内（对范围外的内部往来，不抵消） */
    public function isPartiallyInside(array $orgIds): bool
    {
        $from = in_array((int) $this->from_organization_id, $orgIds, true);
        $to = in_array((int) $this->to_organization_id, $orgIds, true);

        return $from !== $to;
    }

    public function scopeConfirmed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeBetween(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('occurred_on', [$from, $to]);
    }

    public static function categoryLabel(string $key): string
    {
        return config('consolidation.categories.'.$key, $key);
    }
}
