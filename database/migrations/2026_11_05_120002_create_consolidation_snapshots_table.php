<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 合并快照：固化某一合并主体、某一期间的合并结果（含少数股东 /
     * 未实现利润 / 跨期等深化口径），用于审计留档与跨期对比。
     * report_data 存序列化后的报表摘要（纯数组，不含 Eloquent 模型）。
     */
    public function up(): void
    {
        if (! Schema::hasTable('consolidation_snapshots')) {
            Schema::create('consolidation_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('root_org_id');
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->json('report_data');
                $table->timestamps();

                $table->index(['root_org_id', 'period_to']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidation_snapshots');
    }
};
