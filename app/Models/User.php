<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

/**
 * 后台账号
 *
 * 基础两级角色：admin（管理员）/ staff（运营）
 * 细粒度权限：role_user → roles → permission_role → permissions；
 * admin 与超级角色（roles.is_super）自动放行全部权限校验。
 */
class User extends Authenticatable
{
    use HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'bool',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /** 是否拥有某权限点（admin / 超级角色恒 true） */
    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->roles()->where('is_super', true)->exists()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('key', $key))
            ->exists();
    }

    public function roleLabel(): string
    {
        return $this->isAdmin() ? '管理员' : '运营';
    }

    /** 密码统一走 bcrypt，禁止在业务代码里手写 Hash::make */
    public static function hashPassword(string $plain): string
    {
        return Hash::make($plain);
    }

    /** 基础角色下拉选项（value => label） */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_ADMIN => '管理员',
            self::ROLE_STAFF => '运营',
        ];
    }
}
