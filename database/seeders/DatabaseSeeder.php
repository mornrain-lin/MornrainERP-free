<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 晨雨ERP 演示数据
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            PlatformSeeder::class,
            DemoDataSeeder::class,
            SupplierSeeder::class,
            InventorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
