<?php

namespace App\Models\Traits;

use App\Models\Organization;
use App\Support\OrgContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 业务数据「归属组织」能力。
 *
 *  - 自动注册全局作用域：当前请求通过 OrgContext 限定到单一组织，实现数据隔离；
 *    OrgContext::isAll() 或 id() 为 null 时不施加过滤（兼容未启用多组织 / 控制台）。
 *  - creating 事件：若未显式指定，自动写入当前组织 id，保证新建数据落在该组织。
 *
 * 使用：在业务模型 `use BelongsToOrganization;` 即可（Organization 模型自身除外）。
 */
trait BelongsToOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            if (OrgContext::isAll()) {
                return;
            }
            $orgId = OrgContext::id();
            if ($orgId !== null) {
                $query->where($query->qualifyColumn('organization_id'), $orgId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->organization_id) && OrgContext::writeOrgId() !== null) {
                $model->organization_id = OrgContext::writeOrgId();
            }
        });
    }

    /** 显式按组织查询（绕过当前上下文时使用） */
    public function scopeInOrganization(Builder $query, ?int $orgId): Builder
    {
        if ($orgId !== null) {
            return $query->where($query->qualifyColumn('organization_id'), $orgId);
        }

        return $query;
    }
}
