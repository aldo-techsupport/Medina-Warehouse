<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Role;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
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
                'permissions' => ['dashboard', 'warehouse_products', 'warehouse_mutations', 'packing_history'],
            ]
        );

        $packerRole = Role::updateOrCreate(
            ['slug' => 'operator_packing'],
            [
                'name' => 'Operator Packing',
                'description' => 'Petugas meja packing, scan resi, rekam video pengiriman, dan cek riwayat packing.',
                'permissions' => ['packing_station', 'packing_history'],
            ]
        );

        $shopeeRole = Role::updateOrCreate(
            ['slug' => 'staff_shopee'],
            [
                'name' => 'Staff Shopee',
                'description' => 'Spesialis operasional marketplace, sinkronisasi stok shopee, dan pemantauan order.',
                'permissions' => ['shopee_dashboard', 'shopee_orders', 'shopee_settings', 'ai_advisor'],
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

        // 1. Seed Shopee Settings
        $shopeeSetting = ShopeeSetting::updateOrCreate(
            ['id' => 1],
            [
                'partner_id' => 1008542,
                'partner_key' => 'shopee_partner_key_sample_secret_hash_2026',
                'shop_id' => 28491823,
                'shop_name' => 'Medina Official Store',
                'access_token' => 'sample_shopee_access_token_active',
                'refresh_token' => 'sample_shopee_refresh_token_active',
                'expire_in' => 14400,
                'token_expires_at' => now()->addHours(3),
                'is_active' => true,
                'auto_sync_stock' => true,
                'environment' => 'sandbox',
            ]
        );

        // 2. Seed Warehouse Products
        $productsData = [
            [
                'sku' => 'MDN-GMS-001',
                'barcode' => '899202600101',
                'name' => 'Gamis Medina Silk Premium Emerald (Size M)',
                'category' => 'Gamis & Dress',
                'unit' => 'Pcs',
                'purchase_price' => 140000,
                'selling_price' => 245000,
                'stock' => 45,
                'safety_stock' => 10,
                'shopee_item_id' => 184910291,
                'shopee_model_id' => 101,
                'shopee_stock' => 45,
            ],
            [
                'sku' => 'MDN-GMS-002',
                'barcode' => '899202600102',
                'name' => 'Gamis Medina Silk Premium Maroon (Size L)',
                'category' => 'Gamis & Dress',
                'unit' => 'Pcs',
                'purchase_price' => 140000,
                'selling_price' => 245000,
                'stock' => 4, // Low stock for demonstration
                'safety_stock' => 10,
                'shopee_item_id' => 184910292,
                'shopee_model_id' => 102,
                'shopee_stock' => 4,
            ],
            [
                'sku' => 'MDN-KOKO-001',
                'barcode' => '899202600201',
                'name' => 'Kemeja Koko Modern Kurta White (Size XL)',
                'category' => 'Kemeja Koko',
                'unit' => 'Pcs',
                'purchase_price' => 95000,
                'selling_price' => 165000,
                'stock' => 30,
                'safety_stock' => 8,
                'shopee_item_id' => 184910293,
                'shopee_model_id' => 201,
                'shopee_stock' => 30,
            ],
            [
                'sku' => 'MDN-HJB-001',
                'barcode' => '899202600301',
                'name' => 'Hijab Pashmina Plisket Ceruty Mocca',
                'category' => 'Hijab & Kerudung',
                'unit' => 'Pcs',
                'purchase_price' => 22000,
                'selling_price' => 45000,
                'stock' => 120,
                'safety_stock' => 20,
                'shopee_item_id' => 184910294,
                'shopee_model_id' => 301,
                'shopee_stock' => 120,
            ],
            [
                'sku' => 'MDN-SRG-001',
                'barcode' => '899202600401',
                'name' => 'Sarung Tenun Jacquard Medina Black Gold',
                'category' => 'Sarung & Perlengkapan',
                'unit' => 'Pcs',
                'purchase_price' => 85000,
                'selling_price' => 149000,
                'stock' => 25,
                'safety_stock' => 5,
                'shopee_item_id' => 184910295,
                'shopee_model_id' => 401,
                'shopee_stock' => 25,
            ],
            [
                'sku' => 'MDN-MKN-001',
                'barcode' => '899202600501',
                'name' => 'Mukena Traveling Parasut Premium Sage Green',
                'category' => 'Mukena',
                'unit' => 'Pcs',
                'purchase_price' => 70000,
                'selling_price' => 125000,
                'stock' => 18,
                'safety_stock' => 5,
                'shopee_item_id' => null,
                'shopee_model_id' => null,
                'shopee_stock' => 0,
            ],
        ];

        foreach ($productsData as $pData) {
            $product = Product::updateOrCreate(
                ['sku' => $pData['sku']],
                $pData
            );

            // Initial Inbound Stock Mutation
            StockMutation::firstOrCreate(
                ['reference_no' => 'INB-'.$product->sku.'-INIT'],
                [
                    'product_id' => $product->id,
                    'type' => 'inbound',
                    'qty' => $product->stock + 5,
                    'stock_before' => 0,
                    'stock_after' => $product->stock + 5,
                    'notes' => 'Penerimaan Stok Awal Gudang Utama',
                    'actor' => 'Admin Gudang',
                    'created_at' => now()->subDays(3),
                ]
            );
        }

        // 3. Seed Sample Shopee Orders
        $p1 = Product::where('sku', 'MDN-GMS-001')->first();
        $p2 = Product::where('sku', 'MDN-HJB-001')->first();

        // Sample 1: Ready to ship order with tracking number
        $orderSn1 = '26'.date('md').'SHP90812';
        $order1 = ShopeeOrder::updateOrCreate(
            ['order_sn' => $orderSn1],
            [
                'tracking_number' => 'SPXID029481928',
                'shipping_carrier' => 'SPX Express',
                'shop_id' => 28491823,
                'order_status' => 'READY_TO_SHIP',
                'total_amount' => 490000,
                'buyer_username' => 'ratna_sari_jakarta',
                'items_data' => [
                    [
                        'item_id' => $p1->shopee_item_id,
                        'item_sku' => $p1->sku,
                        'item_name' => $p1->name,
                        'model_quantity_purchased' => 2,
                        'model_discounted_price' => $p1->selling_price,
                    ],
                ],
                'stock_deducted' => true,
                'stock_deducted_at' => now()->subHours(2),
                'created_at' => now()->subHours(2),
            ]
        );

        // Sample 2: Multi-item Order with tracking number
        $orderSn2 = '26'.date('md').'SHP77215';
        $order2 = ShopeeOrder::updateOrCreate(
            ['order_sn' => $orderSn2],
            [
                'tracking_number' => 'JT8829103912',
                'shipping_carrier' => 'J&T Express',
                'shop_id' => 28491823,
                'order_status' => 'READY_TO_SHIP',
                'total_amount' => 290000,
                'buyer_username' => 'dewi_anggraeni99',
                'items_data' => [
                    [
                        'item_id' => $p1->shopee_item_id,
                        'item_sku' => $p1->sku,
                        'item_name' => $p1->name,
                        'model_quantity_purchased' => 1,
                        'model_discounted_price' => $p1->selling_price,
                    ],
                    [
                        'item_id' => $p2->shopee_item_id,
                        'item_sku' => $p2->sku,
                        'item_name' => $p2->name,
                        'model_quantity_purchased' => 1,
                        'model_discounted_price' => $p2->selling_price,
                    ],
                ],
                'stock_deducted' => true,
                'stock_deducted_at' => now()->subHours(1),
                'created_at' => now()->subHours(1),
            ]
        );

        // Sample 3: CANCELLED Order for testing blocker
        $orderSnCancel = '26'.date('md').'CANCEL99';
        ShopeeOrder::updateOrCreate(
            ['order_sn' => $orderSnCancel],
            [
                'tracking_number' => 'CANCEL_DEMO_SPX99182',
                'shipping_carrier' => 'SPX Express',
                'shop_id' => 28491823,
                'order_status' => 'CANCELLED',
                'total_amount' => 165000,
                'buyer_username' => 'budi_santoso_cancel',
                'items_data' => [
                    [
                        'item_id' => 184910293,
                        'item_sku' => 'MDN-KOKO-001',
                        'item_name' => 'Kemeja Koko Modern Kurta White',
                        'model_quantity_purchased' => 1,
                        'model_discounted_price' => 165000,
                    ],
                ],
                'stock_deducted' => false,
                'created_at' => now()->subHours(3),
            ]
        );
    }
}
