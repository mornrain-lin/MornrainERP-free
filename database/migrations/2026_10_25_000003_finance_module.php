<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 财务管理模块
 *
 * 口径：finance_* 全部以人民币（CNY）记账，真实资金流水；
 *      平台币与利润核算在 orders / ProfitCalculator，二者不混。
 *  - finance_accounts      资金账户（现金/银行/支付宝/微信/其他）
 *  - finance_flows         收支流水（收入/支出 + 分类 + 关联账户）
 *  - finance_prepayments   预付款（付供应商）/ 预收款（收客户），支持核销
 *  - finance_reconciliations 对账单（系统余额 vs 实盘余额）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('type', 20)->default('cash')->comment('cash/bank/alipay/wechat/other');
            $table->decimal('balance', 14, 2)->default(0)->comment('当前余额(CNY)');
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_flows', function (Blueprint $table) {
            $table->id();
            $table->string('flow_no', 64)->unique();
            $table->string('direction', 10)->comment('income/expense');
            $table->string('category', 30)->comment('收支分类');
            $table->decimal('amount', 14, 2)->default(0);
            $table->foreignId('account_id')->constrained('finance_accounts')->cascadeOnDelete();
            $table->date('occurred_on')->nullable();
            $table->string('related_type', 30)->nullable()->comment('order/purchase/manual');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'occurred_on']);
            $table->index('account_id');
        });

        Schema::create('finance_prepayments', function (Blueprint $table) {
            $table->id();
            $table->string('prepay_no', 64)->unique();
            $table->string('direction', 10)->comment('out=预付供应商 / in=预收客户');
            $table->string('party_name', 96)->comment('供应商 / 客户名称');
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('used_amount', 14, 2)->default(0)->comment('已核销金额');
            $table->string('status', 20)->default('pending')->comment('pending/partial/used/canceled');
            $table->foreignId('account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->date('occurred_on')->nullable();
            $table->string('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('finance_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('finance_accounts')->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('system_balance', 14, 2)->default(0)->comment('系统账面余额');
            $table->decimal('actual_balance', 14, 2)->default(0)->comment('实盘/对账余额');
            $table->decimal('difference', 14, 2)->default(0)->comment('差异 = 实盘 - 账面');
            $table->string('status', 20)->default('balanced')->comment('balanced/diff');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_reconciliations');
        Schema::dropIfExists('finance_prepayments');
        Schema::dropIfExists('finance_flows');
        Schema::dropIfExists('finance_accounts');
    }
};
