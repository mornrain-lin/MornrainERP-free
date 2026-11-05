<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

/**
 * 通过激活码激活专业版 / 企业版（CLI 方式，便于无界面环境部署）。
 */
class LicenseActivate extends Command
{
    protected $signature = 'license:activate {code : 激活码 MRERP1.*}';

    protected $description = '通过激活码激活专业版 / 企业版';

    public function handle(): int
    {
        $code = (string) $this->argument('code');

        if (LicenseService::activate($code)) {
            $this->info('激活成功，当前套餐：'.LicenseService::plan());

            return self::SUCCESS;
        }

        $this->error('激活失败：激活码无效或已过期');

        return self::FAILURE;
    }
}
