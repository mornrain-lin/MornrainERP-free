<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 角色权限（细粒度 RBAC）
 *  - roles            角色（is_super = 超级管理员，不受权限限制）
 *  - permissions       权限点目录（key 唯一，如 finance.flow）
 *  - permission_role   角色 ↔ 权限 多对多
 *  - role_user         用户 ↔ 角色 多对多
 *
 * 兼容策略：users.role = admin 的存量管理员等价超级角色，自动放行一切权限校验。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique()->comment('角色标识（slug）');
            $table->string('label', 60)->comment('显示名');
            $table->string('description', 120)->nullable();
            $table->boolean('is_super')->default(false)->comment('超级角色：绕过全部权限校验');
            $table->boolean('is_system')->default(false)->comment('系统内置角色：不可删除');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique()->comment('权限点 key，如 finance.flow');
            $table->string('group', 40)->comment('分组（与 config/permissions.php 一致）');
            $table->string('label', 80)->comment('权限说明');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
