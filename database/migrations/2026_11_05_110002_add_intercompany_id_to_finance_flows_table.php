<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 收支流水关联内部往来登记。
 *
 * intercompany_id 非空 = 该流水是内部往来的镜像流水之一；
 * 合并报表的抵消引擎按此字段成对剔除。历史流水为 NULL，行为完全不变。
 * 不加外键：内部往来作废时镜像流水会被红冲删除，避免级联约束干扰。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_flows', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_flows', 'intercompany_id')) {
                $table->unsignedBigInteger('intercompany_id')->nullable()->index()
                    ->after('related_id')
                    ->comment('关联 org_intercompany_transactions.id；非空表示内部往来镜像流水');
            }
            // 记账币别：历史数据一律 CNY（见 finance_module 迁移口径），
            // 合并时按 config/consolidation.php 的 rates 折算为 base_currency。
            if (! Schema::hasColumn('finance_flows', 'currency')) {
                $table->string('currency', 3)->default('CNY')->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_flows', function (Blueprint $table) {
            if (Schema::hasColumn('finance_flows', 'intercompany_id')) {
                $table->dropColumn('intercompany_id');
            }
            if (Schema::hasColumn('finance_flows', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
