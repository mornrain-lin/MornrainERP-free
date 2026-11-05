<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 内部存货交易（未实现利润来源）。
     * 组织 A 将存货卖给组织 B：amount = 售价，cost = 成本，profit = 毛利；
     * unsold_ratio = 期末仍在 B 手里未售出的比例；
     * unrealized_profit = profit × unsold_ratio = 合并时需抵减的未实现利润。
     * 刻意不挂 BelongsToOrganization：它天然跨组织。
     * 表名用复数 org_intercompany_inventories，与 sibling 表
     * org_intercompany_transactions 保持命名一致，也契合 Eloquent 默认复数推断。
     */
    public function up(): void
    {
        if (! Schema::hasTable('org_intercompany_inventories')) {
            Schema::create('org_intercompany_inventories', function (Blueprint $table) {
                $table->id();
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedBigInteger('seller_org_id');
                $table->unsignedBigInteger('buyer_org_id');
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('cost', 14, 2)->default(0);
                $table->decimal('profit', 14, 2)->default(0);
                $table->decimal('unsold_ratio', 5, 4)->default(0);
                $table->decimal('unrealized_profit', 14, 2)->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['seller_org_id', 'buyer_org_id']);
                $table->index(['period_from', 'period_to']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_intercompany_inventories');
    }
};
