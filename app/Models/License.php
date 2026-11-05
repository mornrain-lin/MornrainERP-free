<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 激活记录（单条生效）。
 * LicenseService 通过 active() 作用域读取最新一条，据此判定当前套餐。
 */
class License extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** 当前生效的激活记录（始终取最新一条） */
    public function scopeActive(Builder $query): Builder
    {
        return $query->orderByDesc('id')->limit(1);
    }
}
