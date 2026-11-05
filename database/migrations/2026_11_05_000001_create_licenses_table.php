<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 激活记录表：存储当前生效的激活码（单条）。
     * 仅保存脱敏主体（edition.expires.nonce），校验位不落地，降低泄露风险。
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('edition', 8);                 // PRO / ENT
            $table->string('plan', 20);                   // free / pro / enterprise
            $table->string('code', 120)->nullable();      // 脱敏存储（不含校验位的主体）
            $table->timestamp('expires_at')->nullable();  // 有效期截止
            $table->timestamps();

            $table->index(['plan', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
