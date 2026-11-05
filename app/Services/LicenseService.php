<?php

namespace App\Services;

use App\Models\License;
use Carbon\Carbon;

/**
 * 激活码 / 套餐门禁核心服务（fail-closed 设计）。
 *
 * 激活码格式：MRERP1.{EDITION}.{EXPIRES}.{NONCE}.{CHECK}
 *   EDITION  PRO=专业版  ENT=企业版
 *   EXPIRES  YYYYMMDD（有效期截止日）
 *   NONCE    8 位随机串
 *   CHECK    前 8 位 sha256(EDITION|EXPIRES|NONCE|SECRET) 大写
 *
 * 设计原则：任何异常一律回落 free，保证 fail-closed——
 * 即使授权服务异常，也绝不误放行付费功能。
 */
class LicenseService
{
    public const PREFIX = 'MRERP1';

    /** 套餐等级（数值越大等级越高）；与 config/plan.php 的 plans 保持一致 */
    public const PLANS = [
        'free' => 0,
        'pro' => 1,
        'enterprise' => 2,
    ];

    public static function rank(string $plan): int
    {
        $plans = config('plan.plans', self::PLANS);

        return $plans[$plan] ?? 0;
    }

    /** 当前生效套餐（fail-closed：异常 / 无记录 / 已过期 → free） */
    public static function plan(): string
    {
        try {
            $lic = License::active()->first();
            if (! $lic) {
                return 'free';
            }
            if ($lic->expires_at && $lic->expires_at->isPast()) {
                return 'free';
            }

            return $lic->plan;
        } catch (\Throwable $e) {
            return 'free';
        }
    }

    /** 解码后的激活信息（无效返回 null） */
    public static function info(): ?array
    {
        try {
            $lic = License::active()->first();
            if (! $lic) {
                return null;
            }

            return [
                'edition' => $lic->edition,
                'plan' => $lic->plan,
                'expires_at' => $lic->expires_at?->toDateTimeString(),
                'activated_at' => $lic->created_at?->toDateTimeString(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** 校验并激活激活码；成功写入 licenses 表并返回 true */
    public static function activate(string $code): bool
    {
        $parsed = self::parse($code);
        if ($parsed === null) {
            return false;
        }

        try {
            $lic = License::active()->first() ?? new License();
            $lic->edition = $parsed['edition'];
            $lic->plan = $parsed['plan'];
            $lic->code = $parsed['edition'].'.'.$parsed['expires'].'.'.$parsed['nonce'];
            $lic->expires_at = $parsed['expires_at'];
            $lic->save();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function deactivate(): void
    {
        try {
            License::query()->delete();
        } catch (\Throwable $e) {
            // 忽略：测试 / 异常场景下清空失败不应中断流程
        }
    }

    public static function parse(string $code): ?array
    {
        if (! str_starts_with($code, self::PREFIX.'.')) {
            return null;
        }
        $parts = explode('.', $code);
        if (count($parts) !== 5) {
            return null;
        }
        [, $edition, $expires, $nonce, $check] = $parts;

        $plan = match ($edition) {
            'PRO' => 'pro',
            'ENT' => 'enterprise',
            default => null,
        };
        if ($plan === null) {
            return null;
        }
        if (! ctype_digit($expires) || strlen($expires) !== 8) {
            return null;
        }
        if (! hash_equals(self::checksum($edition, $expires, $nonce), strtoupper($check))) {
            return null;
        }
        try {
            $expiresAt = Carbon::createFromFormat('Ymd', $expires)->endOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        return [
            'edition' => $edition,
            'plan' => $plan,
            'expires' => $expires,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
        ];
    }

    public static function checksum(string $edition, string $expires, string $nonce, ?string $secret = null): string
    {
        return strtoupper(substr(hash('sha256', $edition.'|'.$expires.'|'.$nonce.'|'.self::secret($secret)), 0, 8));
    }

    public static function generateCode(string $plan = 'pro', ?string $expires = null, ?string $secret = null): string
    {
        $edition = $plan === 'enterprise' ? 'ENT' : 'PRO';
        $expires = $expires ?? Carbon::now()->addYear()->format('Ymd');
        $nonce = substr(str_replace('.', '', uniqid('', true)), 0, 8);
        if (strlen($nonce) < 8) {
            $nonce = str_pad($nonce, 8, '0');
        }
        $check = self::checksum($edition, $expires, $nonce, $secret);

        return self::PREFIX.".{$edition}.{$expires}.{$nonce}.{$check}";
    }

    protected static function secret(?string $override = null): string
    {
        $secret = $override ?? config('license.secret') ?? config('app.key');

        return (string) ($secret ?: 'mornrain-erp-license-secret');
    }
}
