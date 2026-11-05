<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 组织间内部往来登记（合并抵消的唯一依据）。
 *
 * 会计口径：登记一笔内部往来，会在付款方组织生成一笔支出、收款方组织生成
 * 一笔收入（镜像流水，金额相等、方向相反），两笔各自进入独立账簿、各自
 * 影响所属资金账户余额。合并报表时，仅当「付款方与收款方都在合并范围内」
 * 才把这一对流水成对剔除，从而消除集团内部虚增的收支。
 *
 * 未登记为内部往来的手工流水不参与抵消——抵消必须以显式登记为准，
 * 避免误把真实外部交易抵掉。
 *
 * 不使用数据库外键：组织可能被删除/停用，历史往来需保留作审计凭据；
 * 完整性由 IntercompanyService 在写入时校验。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_intercompany_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txn_no', 64)->unique()->comment('内部往来单号');
            $table->unsignedBigInteger('from_organization_id')->index()->comment('付款方组织');
            $table->unsignedBigInteger('to_organization_id')->index()->comment('收款方组织');
            $table->decimal('amount', 14, 2)->default(0)->comment('金额(CNY)');
            $table->string('currency', 3)->default('CNY');
            $table->date('occurred_on')->index();
            $table->string('category', 30)->default('transfer')->comment('transfer/loan/management_fee/cost_sharing/other');
            $table->string('status', 20)->default('confirmed')->index()->comment('confirmed/canceled');
            $table->unsignedBigInteger('from_account_id')->nullable()->comment('付款方资金账户');
            $table->unsignedBigInteger('to_account_id')->nullable()->comment('收款方资金账户');
            $table->unsignedBigInteger('from_flow_id')->nullable()->comment('付款方支出流水');
            $table->unsignedBigInteger('to_flow_id')->nullable()->comment('收款方收入流水');
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['occurred_on', 'status'], 'ic_occurred_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_intercompany_transactions');
    }
};
