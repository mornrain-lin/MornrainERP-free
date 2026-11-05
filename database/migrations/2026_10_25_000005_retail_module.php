<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 零售管理模块（线下门店 POS，人民币）
 *  - retail_members    会员档案（等级/折扣/积分/储值余额）
 *  - retail_sales      零售单（开单即扣库存，支持会员折扣与多种支付）
 *  - retail_sale_items 零售明细（售价快照）
 *  - retail_returns    零售退货（整单退，回补库存并按原支付方式退款）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_members', function (Blueprint $table) {
            $table->id();
            $table->string('card_no', 32)->unique()->comment('会员卡号');
            $table->string('name', 60);
            $table->string('phone', 40)->nullable()->unique();
            $table->string('level', 20)->default('normal')->comment('normal/silver/gold');
            $table->decimal('discount', 4, 2)->default(1.00)->comment('会员折扣率，如 0.95');
            $table->unsignedInteger('points')->default(0)->comment('积分');
            $table->decimal('balance', 12, 2)->default(0)->comment('储值余额(CNY)');
            $table->string('remark')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('retail_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no', 64)->unique();
            $table->foreignId('member_id')->nullable()->constrained('retail_members')->nullOnDelete();
            $table->string('status', 20)->default('completed')->comment('completed/refunded/canceled');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0)->comment('优惠合计(会员折扣+手动)');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('payment_method', 20)->default('cash')->comment('cash/wechat/alipay/card/balance');
            $table->unsignedInteger('points_earned')->default(0);
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remark')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('member_id');
        });

        Schema::create('retail_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained('retail_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64);
            $table->string('product_name', 160);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();

            $table->index('sku');
        });

        Schema::create('retail_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 64)->unique();
            $table->foreignId('retail_sale_id')->constrained('retail_sales')->cascadeOnDelete();
            $table->string('status', 20)->default('completed')->comment('completed/canceled');
            $table->decimal('total', 14, 2)->default(0)->comment('退款金额');
            $table->string('reason')->nullable();
            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_returns');
        Schema::dropIfExists('retail_sale_items');
        Schema::dropIfExists('retail_sales');
        Schema::dropIfExists('retail_members');
    }
};
