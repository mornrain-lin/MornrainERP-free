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

    /** 用户表加 organization_id（归属组织 / 默认作用域）并回填到默认根组织 */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', function ($table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });
        }
        $root = $this->rootOrgId();
        DB::table('users')->whereNull('organization_id')->update(['organization_id' => $root]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', fn ($t) => $t->dropColumn('organization_id'));
        }
    }
};
