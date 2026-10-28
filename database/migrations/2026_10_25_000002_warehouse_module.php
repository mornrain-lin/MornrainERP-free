<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 仓库
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('location', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 分仓库存（product_id + warehouse_id 维度）
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('stock')->default(0);
            $table->integer('safety_stock')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
        });

        // 仓库调拨单
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no', 32)->index();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->string('status', 16)->default('pending')->index(); // pending|in_transit|done|canceled
            $table->string('note', 255)->nullable();
            $table->json('items')->nullable(); // [{product_id, sku, name, qty}]
            $table->foreignId('created_by')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        // 组装 / 拆卸单
        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->index();
            $table->string('type', 16)->index(); // assemble|disassemble
            $table->foreignId('product_id')->constrained('products');
            $table->integer('qty')->default(0);
            $table->string('note', 255)->nullable();
            $table->json('items')->nullable(); // [{product_id, sku, name, qty}]
            $table->string('status', 16)->default('pending')->index(); // pending|done|canceled
            $table->foreignId('created_by')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        // 库存流水补充仓库维度
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assemblies');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('warehouses');
        if (Schema::hasColumn('stock_movements', 'warehouse_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
