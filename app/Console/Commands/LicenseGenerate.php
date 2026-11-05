<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

/**
 * 生成激活码（供运营发放）。
 * 生成端必须与验证端使用同一 LICENSE_SECRET，否则激活码校验失败。
 */
class LicenseGenerate extends Command
{
    protected $signature = 'license:generate
        {--edition=pro : 版本 pro | enterprise}
        {--expires= : 截止日 YYYYMMDD，缺省一年}
        {--secret= : 覆盖校验密钥（默认读 config license.secret）}';

    protected $description = '生成晨雨ERP激活码（MRERP1.* 格式）';

    public function handle(): int
    {
        $edition = $this->option('edition');
        $plan = $edition === 'enterprise' ? 'enterprise' : 'pro';
        $expires = $this->option('expires');
        $secret = $this->option('secret');

        if ($secret !== null) {
            config(['license.secret' => $secret]);
        }

        $code = LicenseService::generateCode($plan, $expires ?: null, $secret);

        $this->info("激活码（{$edition} / {$plan}）：");
        $this->line($code);
        $this->newLine();
        $this->comment('将该码在「系统 → 激活码管理」页面粘贴激活，或执行：');
        $this->comment('php artisan license:activate '.$code);

        return self::SUCCESS;
    }
}
