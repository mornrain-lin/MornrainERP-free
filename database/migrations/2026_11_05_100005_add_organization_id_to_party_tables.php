<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function rootOrgId(): int
    {
        if ($id = DB::table('organizations')->where('code', 'ROOT')->value('id')) {
            return (int) $id;
        }
        $id = DB::table('organizations')->insertGetId([
            'parent_id' => null, 'type' => 'group', 'name' => '默认组织', 'code' => 'ROOT',
            'level' => 0, 'path' => '', 'sort' => 0, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('organizations')->where('id', $id)->update(['path' => (string) $id]);

        return (int) $id;
    }

    /** 往来 / 商品 / 渠道表加 organization_id 并回填到默认根组织 */
    public function up(): void
    {
        $root = $this->rootOrgId();
        $tables = ['customers', 'suppliers', 'products', 'shops', 'platforms'];

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'organization_id')) {
                Schema::table($table, function ($t) {
                    $t->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
                });
            }
            DB::table($table)->whereNull('organization_id')->update(['organization_id' => $root]);
        }
    }

    public function down(): void
    {
        foreach (['customers', 'suppliers', 'products', 'shops', 'platforms'] as $table) {
            if (Schema::hasColumn($table, 'organization_id')) {
                Schema::table($table, fn ($t) => $t->dropColumn('organization_id'));
            }
        }
    }
};
