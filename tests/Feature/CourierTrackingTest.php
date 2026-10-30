<?php

use App\Models\PackingRecord;
use App\Models\Role;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->superAdminRole = Role::firstOrCreate(
        ['slug' => 'super_admin'],
        ['name' => 'Super Admin', 'permissions' => array_keys(Role::SYSTEM_MENUS)]
    );

    $this->adminUser = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        [
            'name' => 'Admin Test',
            'username' => 'admintest',
            'password' => bcrypt('password'),
            'role_id' => $this->superAdminRole->id,
            'status' => 'active',
        ]
    );

    $this->setting = ShopeeSetting::current();
});

test('it displays courier tracking page with sidebar navigation for authorized users', function () {
    $response = $this->actingAs($this->adminUser)->get(route('courier.index'));

    $response->assertStatus(200);
    $response->assertSee('Lacak Kurir &amp; Pengiriman', false);
    $response->assertSee('Tertahan ≥ 5 Hari');
    $response->assertSee('Lacak Kurir');
});

test('it filters packages that are in transit for 5 or more days without delivery', function () {
    // 1. Delayed order (6 days ago, in transit)
    $delayedOrder = ShopeeOrder::create([
        'order_sn' => 'TEST_DELAY_6D',
        'tracking_number' => 'SPXID9900112233',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'PROCESSED',
        'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
        'total_amount' => 150000,
        'buyer_username' => 'buyer_delayed_test',
        'items_data' => [
            ['item_sku' => 'SKU-DELAY-1', 'item_name' => 'Produk Tertahan 6 Hari', 'model_quantity_purchased' => 1],
        ],
        'stock_deducted' => true,
        'created_at' => Carbon::now()->subDays(6),
        'shipped_at' => Carbon::now()->subDays(6),
    ]);

    // 2. Normal recent order (1 day ago, in transit)
    $freshOrder = ShopeeOrder::create([
        'order_sn' => 'TEST_FRESH_1D',
        'tracking_number' => 'SPXID1122334455',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'PROCESSED',
        'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
        'total_amount' => 200000,
        'buyer_username' => 'buyer_fresh_test',
        'items_data' => [
            ['item_sku' => 'SKU-FRESH-1', 'item_name' => 'Produk Baru 1 Hari', 'model_quantity_purchased' => 1],
        ],
        'stock_deducted' => true,
        'created_at' => Carbon::now()->subDays(1),
        'shipped_at' => Carbon::now()->subDays(1),
    ]);

    // 3. Completed order from 7 days ago (delivered, so should NOT be in delayed filter)
    $deliveredOrder = ShopeeOrder::create([
        'order_sn' => 'TEST_DELIVERED_7D',
        'tracking_number' => 'SPXID7788990011',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'COMPLETED',
        'logistics_status' => 'LOGISTICS_DELIVERED',
        'total_amount' => 250000,
        'buyer_username' => 'buyer_completed_test',
        'items_data' => [
            ['item_sku' => 'SKU-DELIVERED-1', 'item_name' => 'Produk Sudah Terkirim', 'model_quantity_purchased' => 1],
        ],
        'stock_deducted' => true,
        'created_at' => Carbon::now()->subDays(7),
        'shipped_at' => Carbon::now()->subDays(7),
        'delivered_at' => Carbon::now()->subDays(4),
    ]);

    // Request filter delayed_5_days
    $response = $this->actingAs($this->adminUser)->get(route('courier.index', ['filter' => 'delayed_5_days']));

    $response->assertStatus(200);
    $response->assertSee('TEST_DELAY_6D');
    $response->assertSee('SPXID9900112233');
    $response->assertSee('Tertahan (6 Hari)');
    $response->assertDontSee('TEST_FRESH_1D');
    $response->assertDontSee('TEST_DELIVERED_7D');
});

test('it filters packages by courier carrier and search keyword', function () {
    $jntOrder = ShopeeOrder::create([
        'order_sn' => 'TEST_JNT_SEARCH_99',
        'tracking_number' => 'JP123456789',
        'shipping_carrier' => 'J&T Express',
        'order_status' => 'PROCESSED',
        'total_amount' => 100000,
        'buyer_username' => 'jnt_customer',
        'items_data' => [
            ['item_sku' => 'JNT-SKU-1', 'item_name' => 'JNT Specific Product', 'model_quantity_purchased' => 1],
        ],
        'stock_deducted' => true,
        'created_at' => Carbon::now()->subDays(2),
    ]);

    $spxOrder = ShopeeOrder::create([
        'order_sn' => 'TEST_SPX_SEARCH_88',
        'tracking_number' => 'SPXID987654321',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'PROCESSED',
        'total_amount' => 120000,
        'buyer_username' => 'spx_customer',
        'items_data' => [
            ['item_sku' => 'SPX-SKU-1', 'item_name' => 'SPX Specific Product', 'model_quantity_purchased' => 1],
        ],
        'stock_deducted' => true,
        'created_at' => Carbon::now()->subDays(2),
    ]);

    // Test filter by carrier
    $resCarrier = $this->actingAs($this->adminUser)->get(route('courier.index', ['carrier' => 'J&T Express']));
    $resCarrier->assertStatus(200);
    $resCarrier->assertSee('TEST_JNT_SEARCH_99');
    $resCarrier->assertDontSee('TEST_SPX_SEARCH_88');

    // Test search by tracking number
    $resSearch = $this->actingAs($this->adminUser)->get(route('courier.index', ['search' => 'SPXID987654321']));
    $resSearch->assertStatus(200);
    $resSearch->assertSee('TEST_SPX_SEARCH_88');
    $resSearch->assertDontSee('TEST_JNT_SEARCH_99');
});

test('it returns package details and tracking timeline as JSON for modal', function () {
    $order = ShopeeOrder::create([
        'order_sn' => 'JSON_MODAL_TEST_01',
        'tracking_number' => 'SPXID_JSON_001',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'PROCESSED',
        'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
        'total_amount' => 300000,
        'buyer_username' => 'json_buyer',
        'recipient_address' => [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'town' => 'Senayan',
            'district' => 'Kebayoran Baru',
            'city' => 'Kota Jakarta Selatan',
            'state' => 'DKI Jakarta',
            'region' => 'ID',
            'zipcode' => '12190',
            'full_address' => 'Jl. Senopati No. 10, Senayan, Kebayoran Baru, Kota Jakarta Selatan, DKI Jakarta 12190',
        ],
        'items_data' => [
            ['item_sku' => 'JSON-SKU-1', 'item_name' => 'Modal Test Product', 'model_quantity_purchased' => 2, 'model_discounted_price' => 150000],
        ],
        'tracking_data' => [
            ['ctime' => time() - 86400, 'description' => 'Paket telah di-pickup kurir', 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
            ['ctime' => time(), 'description' => 'Paket sedang diantar ke penerima', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
        ],
        'stock_deducted' => true,
        'created_at' => Carbon::now()->subDays(1),
        'shipped_at' => Carbon::now()->subDays(1),
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('courier.show', $order));

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'data' => [
            'order_sn' => 'JSON_MODAL_TEST_01',
            'tracking_number' => 'SPXID_JSON_001',
            'shipping_carrier' => 'SPX Express',
            'buyer_username' => 'json_buyer',
            'recipient_name' => 'Budi Santoso',
            'recipient_phone' => '081234567890',
            'destination_city' => 'Kota Jakarta Selatan, DKI Jakarta',
            'formatted_address' => 'Jl. Senopati No. 10, Senayan, Kebayoran Baru, Kota Jakarta Selatan, DKI Jakarta 12190',
            'is_delayed_5_days' => false,
        ],
    ]);
});

test('it syncs tracking info using Shopee Logistics API according to Guide 4', function () {
    $this->setting->update([
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_test_courier_bridge_key',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/logistics/sync-order-tracking*' => Http::response([
            'success' => true,
            'data' => [
                'order_sn' => 'SYNC_LOGISTICS_API_TEST',
                'tracking_number' => 'SPXID_API_123456',
                'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
                'recipient_address' => [
                    'name' => 'Siti Aminah',
                    'phone' => '081122334455',
                    'town' => 'Dago',
                    'district' => 'Coblong',
                    'city' => 'Kota Bandung',
                    'state' => 'Jawa Barat',
                    'region' => 'ID',
                    'zipcode' => '40135',
                    'full_address' => 'Jl. Ir. H. Juanda No. 88, Dago, Coblong, Kota Bandung, Jawa Barat 40135',
                ],
                'tracking_info' => [
                    ['ctime' => time() - 3600, 'description' => 'Parcel handed to SPX Express Hub', 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
                    ['ctime' => time(), 'description' => 'Courier is delivering the parcel to buyer', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
                ],
            ],
        ], 200),
    ]);

    $order = ShopeeOrder::create([
        'order_sn' => 'SYNC_LOGISTICS_API_TEST',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 120000,
        'buyer_username' => 'api_sync_buyer',
        'stock_deducted' => true,
        'created_at' => Carbon::now()->subDays(2),
    ]);

    $response = $this->actingAs($this->adminUser)->post(route('courier.sync', $order));

    $response->assertRedirect();
    $order->refresh();

    expect($order->tracking_number)->toBe('SPXID_API_123456');
    expect($order->logistics_status)->toBe('LOGISTICS_DELIVERY_IN_PROCESS');
    expect($order->tracking_data)->toHaveCount(2);
    expect($order->last_tracked_at)->not->toBeNull();
});

test('it handles carrier list dropdown safely with various cache payloads', function () {
    // Put a collection directly in cache
    Cache::put('courier_unique_carriers_list', collect(['SPX Express', 'J&T Express']), 60);

    $response = $this->actingAs($this->adminUser)->get(route('courier.index'));
    $response->assertStatus(200);
    $response->assertSee('SPX Express');
    $response->assertSee('J&T Express');
});

test('it filters packages by cancelled status correctly', function () {
    ShopeeOrder::create([
        'order_sn' => 'TEST_ORD_CANCEL_01',
        'tracking_number' => 'SPXID_CANCEL_01',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'CANCELLED',
        'total_amount' => 110000,
        'buyer_username' => 'cancel_buyer_01',
    ]);

    ShopeeOrder::create([
        'order_sn' => 'TEST_ORD_ACTIVE_02',
        'tracking_number' => 'SPXID_ACTIVE_02',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'PROCESSED',
        'total_amount' => 150000,
        'buyer_username' => 'active_buyer_02',
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('courier.index', ['filter' => 'cancelled']));

    $response->assertStatus(200);
    $response->assertSee('TEST_ORD_CANCEL_01');
    $response->assertSee('SPXID_CANCEL_01');
    $response->assertDontSee('TEST_ORD_ACTIVE_02');
    $response->assertSee('Dibatalkan');
});

test('it displays direct packing video action and modal in courier tracking with correct states', function () {
    $orderWithVideo = ShopeeOrder::create([
        'order_sn' => 'ORD_WITH_VID_01',
        'tracking_number' => 'RESI_VID_01',
        'shipping_carrier' => 'J&T Express',
        'order_status' => 'PROCESSED',
        'total_amount' => 125000,
        'buyer_username' => 'buyer_video',
    ]);

    PackingRecord::create([
        'order_sn' => $orderWithVideo->order_sn,
        'tracking_number' => $orderWithVideo->tracking_number,
        'shopee_order_id' => $orderWithVideo->id,
        'status' => 'completed',
        'packer_name' => 'Budi Packer',
        'video_path' => 'packing_videos/test_vid.webm',
        'video_duration' => 15,
        'file_size' => 102400,
    ]);

    $orderWithoutVideo = ShopeeOrder::create([
        'order_sn' => 'ORD_NO_VID_02',
        'tracking_number' => 'RESI_NO_VID_02',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 90000,
        'buyer_username' => 'buyer_novideo',
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('courier.index'));

    $response->assertStatus(200);
    $response->assertSee('Video Ada');
    $response->assertSee('Tidak ada video');
    $response->assertSee('packingVideoModal');

    // Verify modals are not nested inside each other
    $content = $response->getContent();
    $detailPos = strpos($content, 'id="packageDetailModal"');
    $videoPos = strpos($content, 'id="packingVideoModal"');
    expect($detailPos)->toBeLessThan($videoPos);
    $detailModalSub = substr($content, $detailPos, $videoPos - $detailPos);
    expect(substr_count($detailModalSub, '<div'))->toEqual(substr_count($detailModalSub, '</div>'));

    // Test JSON endpoint for order with video
    $jsonWithVid = $this->actingAs($this->adminUser)->getJson(route('courier.show', $orderWithVideo));
    $jsonWithVid->assertStatus(200);
    $jsonWithVid->assertJsonPath('data.has_video', true);
    $jsonWithVid->assertJsonPath('data.packing_record.packer_name', 'Budi Packer');

    // Test JSON endpoint for order without video
    $jsonNoVid = $this->actingAs($this->adminUser)->getJson(route('courier.show', $orderWithoutVideo));
    $jsonNoVid->assertStatus(200);
    $jsonNoVid->assertJsonPath('data.has_video', false);
    $jsonNoVid->assertJsonPath('data.packing_record', null);
    $jsonNoVid->assertJsonPath('data.tracking_timeline', []);
});

test('it returns tracking_timeline in show json endpoint when tracking data exists', function () {
    $timeline = [
        ['ctime' => 1730000000, 'description' => 'Paket telah di-pickup SPX Express', 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
        ['ctime' => 1730010000, 'description' => 'Paket sedang menuju Hub Sortir', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
    ];

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD_TIMELINE_TEST_01',
        'tracking_number' => 'SPXID_TIMELINE_01',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'PROCESSED',
        'total_amount' => 95000,
        'buyer_username' => 'buyer_timeline',
        'tracking_data' => $timeline,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('courier.show', $order));

    $response->assertStatus(200);
    $response->assertJsonPath('data.tracking_number', 'SPXID_TIMELINE_01');
    $response->assertJsonPath('data.tracking_timeline', $timeline);
    $response->assertJsonCount(2, 'data.tracking_timeline');
});
