<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 系统参数（key-value）
 *
 *  - 读取走 Setting::get(key, default)，带请求内静态缓存，避免每页反复查库
 *  - 写入走 Setting::set(key, value) 并失效缓存
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'label', 'type'];

    /** @var array<string, mixed> 请求内缓存 */
    protected static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }
        $row = static::query()->where('key', $key)->first();
        $value = $row?->value ?? $default;
        static::$cache[$key] = $value;

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $row = static::query()->where('key', $key)->first();
        if ($row) {
            $row->update(['value' => (string) $value]);
        }
        static::$cache[$key] = $value;
    }

    /** 写入（不存在则创建，如菜单配置等新增参数） */
    public static function put(string $key, mixed $value, array $extra = []): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value] + $extra);
        static::$cache[$key] = $value;
    }

    /** 批量写入（不存在则跳过；目录登记见 migrations / 后台参数页） */
    public static function putMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            static::set($key, $value);
        }
    }
}
