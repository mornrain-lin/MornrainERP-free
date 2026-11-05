<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 系统参数（key-value 全局配置，管理员可在后台维护）
 *  - company_name            公司名称（登录页 / 侧边栏品牌）
 *  - monthly_order_quota     月度订单额度（覆盖 config/plan.php，0 = 不限量）
 *  - default_safety_stock    默认安全库存（商品未单独设置时的回退值）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->string('group', 40)->default('basic')->comment('分组：basic / quota / inventory');
            $table->string('label', 80)->comment('参数说明');
            $table->string('type', 20)->default('text')->comment('text/number/switch');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
