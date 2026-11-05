<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

/**
 * 组织上下文（请求级单例状态的静态持有者）。
 *
 * 解析优先级：请求/session 指定的 active_org_id > 用户归属组织 > 根组织。
 * 集团管理员（admin 或持有 org.view_all）默认「查看全部」(isAll)，可在切换器中收窄。
 *
 * 设计要点：
 *  - id() 为 null 时不施加任何作用域（兼容多组织能力启用前、以及控制台命令）；
 *  - isAll() 为 true 时不施加作用域（跨组织查看）；
 *  - BelongsToOrganization 全局作用域据此过滤业务数据。
 */
class OrgContext
{
    /** @var int|null 当前生效组织 id */
    protected static ?int $id = null;

    /** @var bool 是否跨组织查看全部（不施加作用域） */
    protected static bool $all = false;

    public static function set(?int $id): void
    {
        self::$id = $id;
        self::$all = false;
    }

    public static function enableAll(): void
    {
        self::$all = true;
        self::$id = null;
    }

    public static function reset(): void
    {
        self::$id = null;
        self::$all = false;
    }

    public static function id(): ?int
    {
        return self::$id;
    }

    /**
     * 写入时应落到的组织 id。
     * isAll（跨组织查看）模式下 id() 为 null，此时回退到根组织，
     * 避免新建数据 organization_id 落空导致永久不可见。
     */
    public static function writeOrgId(): ?int
    {
        if (self::$id !== null) {
            return self::$id;
        }
        if (self::$all && Schema::hasTable('organizations')) {
            $root = Organization::where('code', 'ROOT')->value('id');

            return $root ? (int) $root : null;
        }

        return null;
    }

    public static function isAll(): bool
    {
        return self::$all;
    }

    public static function model(): ?Organization
    {
        return self::$id ? Organization::find(self::$id) : null;
    }

    /** 当前用户是否可跨组织查看全部数据 */
    public static function canViewAll(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('org.view_all');
    }

    /**
     * 依据用户与会话解析并设置当前组织上下文（由 OrgContextMiddleware 调用）。
     */
    public static function resolve(?User $user): void
    {
        self::reset();

        // 未执行组织迁移（旧安装升级前）→ 保持无作用域，业务行为完全不变
        if ($user === null || ! Schema::hasTable('organizations')) {
            return;
        }

        if (self::canViewAll($user)) {
            $picked = (int) (Session::get('active_org_id') ?? 0);
            if ($picked > 0 && self::userCanAccessOrg($user, $picked)) {
                self::set($picked);
            } else {
                self::enableAll();
            }

            return;
        }

        $picked = (int) (Session::get('active_org_id') ?? 0);
        if ($picked > 0 && self::userCanAccessOrg($user, $picked)) {
            self::set($picked);

            return;
        }

        if ($user->organization_id) {
            self::set((int) $user->organization_id);

            return;
        }

        $root = Organization::where('code', 'ROOT')->value('id');
        self::set($root ? (int) $root : null);
    }

    /** 用户是否有权切换到目标组织（自身/归属子树，或管理员任意） */
    public static function userCanAccessOrg(User $user, int $orgId): bool
    {
        if (self::canViewAll($user)) {
            return true;
        }
        if (! Schema::hasTable('organizations')) {
            return false;
        }

        $rootId = (int) (Organization::where('code', 'ROOT')->value('id') ?? 0);
        $home = $user->organization_id ? (int) $user->organization_id : null;

        if ($home === null) {
            return $orgId === $rootId;
        }
        if ($home === $orgId) {
            return true;
        }

        $homeOrg = Organization::find($home);
        $target = Organization::find($orgId);
        if (! $homeOrg || ! $target) {
            return false;
        }

        return $target->path === $homeOrg->path
            || str_starts_with($target->path, $homeOrg->path.'/');
    }

    /** 当前用户可切换的组织 id 列表（用于切换器选项） */
    public static function accessibleOrgIds(User $user): array
    {
        if (! Schema::hasTable('organizations')) {
            return [];
        }
        if (self::canViewAll($user)) {
            return Organization::pluck('id')->map('intval')->all();
        }

        $home = $user->organization_id ? (int) $user->organization_id : null;
        if ($home === null) {
            return Organization::where('code', 'ROOT')->pluck('id')->map('intval')->all();
        }

        $homeOrg = Organization::find($home);
        if (! $homeOrg) {
            return [];
        }

        return Organization::where('path', $homeOrg->path)
            ->orWhere('path', 'like', $homeOrg->path.'/%')
            ->pluck('id')
            ->map('intval')
            ->all();
    }
}
