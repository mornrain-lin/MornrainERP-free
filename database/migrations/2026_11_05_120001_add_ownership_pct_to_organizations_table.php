<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 组织被母公司持有的比例（%）。
     * 用于合并报表拆分「归属于母公司」与「少数股东」损益。
     * 根集团自身为 100（占位），子公司填写真实控股比例（如 80 表示母公司持股 80%）。
     */
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'ownership_pct')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->decimal('ownership_pct', 5, 2)
                    ->default(100.00)
                    ->after('status')
                    ->comment('集团内被母公司持有的比例（%）；根集团为 100');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'ownership_pct')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('ownership_pct');
            });
        }
    }
};
