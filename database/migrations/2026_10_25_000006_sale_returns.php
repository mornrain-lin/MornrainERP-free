<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 销售退货（B 端，人民币）
 *  - sale_returns      退货单：待入库 → 已入库 → 已退款（或已取消）
 *  - sale_return_items 退货明细（售价快照，行来自原销售订单明细）
 *  - 入库回补库存走 StockService（库存唯一入口）
 *  - 退款走 FinanceService::record（资金唯一入口，分类 CAT_REFUND_OUT）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 64)->unique();
            $table->foreignId('sale_order_id')->constrained('sale_orders')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status', 20)->default('pending')->comment('pending/stocked/refunded/canceled');
            $table->date('return_date')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0)->comment('退货金额(CNY)');
            $table->decimal('refund_amount', 14, 2)->default(0)->comment('已退金额(CNY)');
            $table->string('reason', 120)->nullable()->comment('退货原因');
            $table->text('remark')->nullable();
            $table->timestamp('stocked_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('sale_order_id');
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_order_item_id')->nullable()->constrained('sale_order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64);
            $table->string('product_name', 160)->comment('退货时商品名快照');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0)->comment('售价快照(CNY)');
            $table->decimal('line_total', 14, 2)->default(0)->comment('小计(CNY)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
