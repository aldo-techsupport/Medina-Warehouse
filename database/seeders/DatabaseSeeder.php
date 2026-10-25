<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Seed Roles
        $allMenuKeys = [];
        foreach (Role::SYSTEM_MENUS as $cat => $menus) {
            foreach ($menus as $k => $v) {
                $allMenuKeys[] = $k;
            }
        }

        $superAdminRole = Role::updateOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Akses penuh ke seluruh modul, pengaturan hak akses, API, dan mutasi data.',
                'permissions' => $allMenuKeys,
            ]
        );

        $gudangRole = Role::updateOrCreate(
            ['slug' => 'admin_gudang'],
            [
                'name' => 'Admin Gudang',
                'description' => 'Bertanggung jawab atas stok fisik, mutasi masuk/keluar, dan monitoring dashboard gudang.',
                'permissions' => ['dashboard', 'warehouse_products', 'warehouse_mutations', 'packing_history', 'courier_tracking'],
            ]
        );

        $packerRole = Role::updateOrCreate(
            ['slug' => 'operator_packing'],
            [
                'name' => 'Operator Packing',
                'description' => 'Petugas meja packing, scan resi, rekam video pengiriman, dan cek riwayat packing.',
                'permissions' => ['packing_station', 'packing_history', 'courier_tracking'],
            ]
        );

        $shopeeRole = Role::updateOrCreate(
            ['slug' => 'staff_shopee'],
            [
                'name' => 'Staff Shopee',
                'description' => 'Spesialis operasional marketplace, sinkronisasi stok shopee, dan pemantauan order.',
                'permissions' => ['shopee_dashboard', 'shopee_orders', 'shopee_settings', 'ai_advisor', 'courier_tracking'],
            ]
        );

        // 0.1 Seed Default Users
        User::updateOrCreate(
            ['email' => 'admin@medina.com'],
            [
                'username' => 'admin',
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'gudang@medina.com'],
            [
                'username' => 'gudang',
                'name' => 'Budi Gudang',
                'password' => Hash::make('password'),
                'role_id' => $gudangRole->id,
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'packer@medina.com'],
            [
                'username' => 'packer',
                'name' => 'Siti Packer',
                'password' => Hash::make('password'),
                'role_id' => $packerRole->id,
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'shopee@medina.com'],
            [
                'username' => 'shopee',
                'name' => 'Rian Marketplace',
                'password' => Hash::make('password'),
                'role_id' => $shopeeRole->id,
                'status' => 'active',
            ]
        );
    }
}
