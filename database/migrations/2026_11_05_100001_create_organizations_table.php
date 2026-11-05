<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 组织架构表（多组织核算）。
     * 三级：集团 / 公司 / 部门；物化路径 path 含自身 id。
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('type')->index()->comment('group|company|department');
            $table->string('name');
            $table->string('code')->nullable()->unique()->comment('组织编码');
            $table->unsignedTinyInteger('level')->default(0)->comment('0集团 1公司 2部门');
            $table->string('path')->nullable()->index()->comment('物化路径，含自身 id，如 1/3/7');
            $table->integer('sort')->default(0);
            $table->unsignedTinyInteger('status')->default(1)->comment('1启用 0停用');
            $table->timestamps();
        });

        // 播种默认根集团（多组织能力未启用前的兼容根节点）
        if (! DB::table('organizations')->where('code', 'ROOT')->exists()) {
            $id = DB::table('organizations')->insertGetId([
                'parent_id' => null,
                'type' => 'group',
                'name' => '默认组织',
                'code' => 'ROOT',
                'level' => 0,
                'path' => '',
                'sort' => 0,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('organizations')->where('id', $id)->update(['path' => (string) $id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
