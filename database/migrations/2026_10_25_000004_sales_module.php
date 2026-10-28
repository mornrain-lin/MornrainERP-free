<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 销售管理模块（B 端，人民币，与跨境电商 orders 分立）
 *  - customers        客户档案（CRM 雏形）
 *  - sale_orders      销售订单 + 明细，发货经 StockService 扣减库存
 *  - sale_order_items 销售订单明细（售价快照）
 *  - sale_quotes      报价单（items 存 JSON，可转销售订单）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96);
            $table->string('contact', 60)->nullable()->comment('联系人');
            $table->string('phone', 40)->nullable();
            $table->string('email', 96)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('level', 20)->default('normal')->comment('normal/vip/gold');
            $table->string('remark')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->string('so_no', 64)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status', 20)->default('draft')->comment('draft/confirmed/shipped/completed/canceled');
            $table->date('order_date')->nullable();
            $table->date('expected_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0)->comment('销售总额(CNY)');
            $table->decimal('paid_amount', 14, 2)->default(0)->comment('已回款(CNY)');
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_id');
        });

        Schema::create('sale_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_order_id')->constrained('sale_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64);
            $table->string('product_name', 160)->comment('下单时商品名快照');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0)->comment('售价(CNY)');
            $table->decimal('line_total', 14, 2)->default(0)->comment('售价小计(CNY)');
            $table->timestamps();

            $table->index('sku');
        });

        Schema::create('sale_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_no', 64)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status', 20)->default('draft')->comment('draft/sent/accepted/rejected/converted/expired');
            $table->date('valid_until')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->json('items')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_quotes');
        Schema::dropIfExists('sale_order_items');
        Schema::dropIfExists('sale_orders');
        Schema::dropIfExists('customers');
    }
};
