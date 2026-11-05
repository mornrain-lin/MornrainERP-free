<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'label', 'description', 'is_super', 'is_system'];

    protected $casts = [
        'is_super' => 'bool',
        'is_system' => 'bool',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /** 是否拥有某权限点（超级角色恒 true） */
    public function hasPermission(string $key): bool
    {
        if ($this->is_super) {
            return true;
        }

        return $this->permissions()->where('key', $key)->exists();
    }
}
