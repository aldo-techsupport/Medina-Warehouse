<?php

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use App\Models\User;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Illuminate\Support\Facades\Cache;

test('it correctly calculates Shopee public and shop HMAC-SHA256 signatures', function () {
    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key_sample',
        'shop_id' => 789012,
        'access_token' => 'test_access_token',
    ]);

    $service = new ShopeeService($setting);

    $path = '/api/v2/auth/token/get';
    $timestamp = 1700000000;
    $expectedPublicSign = hash_hmac('sha256', "123456{$path}{$timestamp}", 'secret_partner_key_sample');
    expect($service->generatePublicSign($path, $timestamp))->toBe($expectedPublicSign);

    $shopPath = '/api/v2/product/update_stock';
    $expectedShopSign = hash_hmac('sha256', "123456{$shopPath}{$timestamp}test_access_token789012", 'secret_partner_key_sample');
    expect($service->generateShopSign($shopPath, $timestamp))->toBe($expectedShopSign);
});

test('it automatically deducts warehouse stock when Shopee order arrives and logs mutation', function () {
    $product = Product::create([
        'sku' => 'MDN-TEST-DEDUCT',
        'name' => 'Product For Shopee Deduction',
        'unit' => 'Pcs',
        'stock' => 50,
        'purchase_price' => 100000,
        'selling_price' => 180000,
        'shopee_item_id' => 99887766,
    ]);

    $orderData = [
        'order_sn' => 'TEST_SHP_ORD_9918',
        'shop_id' => 123456,
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 360000,
        'buyer_username' => 'test_customer_shopee',
        'items' => [
            [
                'item_id' => 99887766,
                'item_sku' => 'MDN-TEST-DEDUCT',
                'item_name' => 'Product For Shopee Deduction',
                'model_quantity_purchased' => 3,
                'model_discounted_price' => 180000,
            ],
        ],
    ];

    $mutationService = new StockMutationService;
    $order = $mutationService->processShopeeOrder($orderData);

    expect($order->stock_deducted)->toBeTrue();
    expect($product->fresh()->stock)->toBe(47);

    $mutation = StockMutation::where('reference_no', 'SHP-TEST_SHP_ORD_9918')->first();
    expect($mutation)->not->toBeNull();
    expect($mutation->qty)->toBe(-3);
    expect($mutation->type)->toBe('shopee_sale');
    expect($mutation->stock_before)->toBe(50);
    expect($mutation->stock_after)->toBe(47);
});

test('it handles Shopee order cancellation by restocking warehouse items', function () {
    $product = Product::create([
        'sku' => 'MDN-TEST-CANCEL',
        'name' => 'Product For Shopee Cancel',
        'unit' => 'Pcs',
        'stock' => 20,
        'purchase_price' => 50000,
        'selling_price' => 100000,
        'shopee_item_id' => 55443322,
    ]);

    $orderData = [
        'order_sn' => 'TEST_SHP_ORD_CANCEL_1',
        'shop_id' => 123456,
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 200000,
        'buyer_username' => 'buyer_cancel',
        'items' => [
            [
                'item_id' => 55443322,
                'item_sku' => 'MDN-TEST-CANCEL',
                'item_name' => 'Product For Shopee Cancel',
                'model_quantity_purchased' => 2,
                'model_discounted_price' => 100000,
            ],
        ],
    ];

    $mutationService = new StockMutationService;
    $order = $mutationService->processShopeeOrder($orderData);
    expect($product->fresh()->stock)->toBe(18);

    // Cancel order
    $orderData['order_status'] = 'CANCELLED';
    $cancelledOrder = $mutationService->processShopeeOrder($orderData);

    expect($cancelledOrder->stock_deducted)->toBeFalse();
    expect($product->fresh()->stock)->toBe(20);

    $retMutation = StockMutation::where('reference_no', 'RET-TEST_SHP_ORD_CANCEL_1')->first();
    expect($retMutation)->not->toBeNull();
    expect($retMutation->qty)->toBe(2);
    expect($retMutation->type)->toBe('shopee_cancellation');
});

test('it automatically refreshes Shopee token when token is expiring or expired', function () {
    Http::fake([
        '*/api/v2/auth/access_token/get*' => Http::response([
            'access_token' => 'new_refreshed_access_token_xyz',
            'refresh_token' => 'new_refreshed_refresh_token_abc',
            'expire_in' => 14400,
            'error' => '',
            'message' => 'success',
        ], 200),
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'expired_old_token',
        'refresh_token' => 'valid_refresh_token',
        'token_expires_at' => now()->subMinutes(15), // expired 15 mins ago
    ]);

    $service = new ShopeeService($setting);
    $refreshed = $service->ensureValidToken();

    expect($refreshed)->toBeTrue();
    expect($setting->fresh()->access_token)->toBe('new_refreshed_access_token_xyz');
    expect($setting->fresh()->refresh_token)->toBe('new_refreshed_refresh_token_abc');
    expect($setting->fresh()->token_expires_at->isFuture())->toBeTrue();
});

test('it pulls orders from Shopee API and automatically deducts warehouse stock', function () {
    $product = Product::create([
        'sku' => 'MDN-SHP-PULL-1',
        'name' => 'Produk Pull Shopee',
        'unit' => 'Pcs',
        'stock' => 30,
        'purchase_price' => 25000,
        'selling_price' => 50000,
        'shopee_item_id' => 77112233,
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/order/get_order_list*' => Http::response([
            'response' => [
                'order_list' => [
                    ['order_sn' => '260904PULLTEST99'],
                ],
            ],
            'error' => '',
        ], 200),
        '*/api/v2/order/get_order_detail*' => Http::response([
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => '260904PULLTEST99',
                        'order_status' => 'READY_TO_SHIP',
                        'total_amount' => 100000,
                        'buyer_username' => 'buyer_pull_api',
                        'tracking_number' => 'SPXID9988776655',
                        'shipping_carrier' => 'SPX Express',
                        'item_list' => [
                            [
                                'item_id' => 77112233,
                                'item_sku' => 'MDN-SHP-PULL-1',
                                'item_name' => 'Produk Pull Shopee',
                                'model_quantity_purchased' => 2,
                                'model_discounted_price' => 50000,
                            ],
                        ],
                    ],
                ],
            ],
            'error' => '',
        ], 200),
    ]);

    $service = new ShopeeService($setting);
    $result = $service->pullAndSyncOrders(days: 3);

    expect($result['success'])->toBeTrue();
    expect($result['synced_count'])->toBe(1);
    expect($product->fresh()->stock)->toBe(28); // 30 - 2 = 28

    $mutation = StockMutation::where('reference_no', 'SHP-260904PULLTEST99')->first();
    expect($mutation)->not->toBeNull();
    expect($mutation->qty)->toBe(-2);
});

test('it gets tracking info and tracking number from Shopee Logistics API', function () {
    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/logistics/get_tracking_info*' => Http::response([
            'response' => [
                'tracking_info' => [
                    ['description' => 'Parcel is being prepared for pickup', 'logistics_status' => 'INITIAL'],
                ],
            ],
            'error' => '',
        ], 200),
        '*/api/v2/logistics/get_tracking_number*' => Http::response([
            'response' => [
                'tracking_number' => 'SPXID1234567890',
            ],
            'error' => '',
        ], 200),
    ]);

    $service = new ShopeeService($setting);
    $trackingInfo = $service->getTrackingInfo('260904ORDER123');
    expect($trackingInfo['success'])->toBeTrue();
    expect($trackingInfo['data']['tracking_info'][0]['logistics_status'])->toBe('INITIAL');

    $trackingNum = $service->getTrackingNumber('260904ORDER123');
    expect($trackingNum['success'])->toBeTrue();
    expect($trackingNum['data']['tracking_number'])->toBe('SPXID1234567890');
});

test('shopee:sync command synchronizes products and orders and updates last_synced_at', function () {
    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
        'last_synced_at' => null,
    ]);

    Http::fake([
        '*/api/v2/product/get_item_list*' => Http::response([
            'response' => [
                'item' => [
                    ['item_id' => 777666, 'item_status' => 'NORMAL', 'update_time' => time()],
                ],
                'has_next_page' => false,
                'total_count' => 1,
            ],
            'error' => '',
        ], 200),
        '*/api/v2/product/get_item_base_info*' => Http::response([
            'response' => [
                'item_list' => [
                    [
                        'item_id' => 777666,
                        'item_name' => 'Auto Synced Product',
                        'item_sku' => 'AUTO-777666',
                        'price_info' => [
                            ['currency' => 'IDR', 'original_price' => 50000, 'current_price' => 50000],
                        ],
                        'stock_info_v2' => [
                            'summary_info' => [
                                'total_available_stock' => 12,
                            ],
                        ],
                    ],
                ],
            ],
            'error' => '',
        ], 200),
        '*/api/v2/order/get_order_list*' => Http::response([
            'response' => [
                'order_list' => [],
                'more' => false,
            ],
            'error' => '',
        ], 200),
    ]);

    $this->artisan('shopee:sync')
        ->assertExitCode(0);

    $setting->refresh();
    expect($setting->last_synced_at)->not->toBeNull();

    $product = Product::where('shopee_item_id', 777666)->first();
    expect($product)->not->toBeNull();
    expect($product->stock)->toBe(12);
});

test('it handles Shopee webhook and fetches order detail when items are not in payload', function () {
    $product = Product::create([
        'sku' => 'WEBHOOK-SKU-99',
        'name' => 'Webhook Product Test',
        'unit' => 'Pcs',
        'stock' => 20,
        'purchase_price' => 50000,
        'selling_price' => 90000,
        'shopee_item_id' => 991122,
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/order/get_order_detail*' => Http::response([
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'WEBHOOK_ORD_7711',
                        'order_status' => 'READY_TO_SHIP',
                        'buyer_username' => 'webhook_buyer',
                        'total_amount' => 180000,
                        'shipping_carrier' => 'J&T Express',
                        'item_list' => [
                            [
                                'item_id' => 991122,
                                'item_sku' => 'WEBHOOK-SKU-99',
                                'item_name' => 'Webhook Product Test',
                                'model_quantity_purchased' => 2,
                                'model_discounted_price' => 90000,
                            ],
                        ],
                    ],
                ],
            ],
            'error' => '',
        ], 200),
    ]);

    $payload = [
        'code' => 3,
        'data' => [
            'order_sn' => 'WEBHOOK_ORD_7711',
            'status' => 'READY_TO_SHIP',
        ],
    ];

    $rawContent = json_encode($payload);
    $url = url('/shopee/webhook');
    $signature = hash_hmac('sha256', $url.'|'.$rawContent, 'secret_partner_key');

    $response = $this->call(
        'POST',
        '/shopee/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => $signature,
        ],
        $rawContent
    );

    $response->assertStatus(200);
    $response->assertJson(['code' => 0, 'message' => 'success']);

    $product->refresh();
    expect($product->stock)->toBe(18); // 20 - 2

    $mutation = StockMutation::where('reference_no', 'SHP-WEBHOOK_ORD_7711')->first();
    expect($mutation)->not->toBeNull();
    expect($mutation->qty)->toBe(-2);
});

test('warehouse products page automatically triggers live sync from Shopee API without manual sync button', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/product/get_item_list*' => Http::response([
            'response' => [
                'item' => [
                    ['item_id' => 999888, 'item_status' => 'NORMAL', 'update_time' => time()],
                ],
                'has_next_page' => false,
                'total_count' => 1,
            ],
            'error' => '',
        ], 200),
        '*/api/v2/product/get_item_base_info*' => Http::response([
            'response' => [
                'item_list' => [
                    [
                        'item_id' => 999888,
                        'item_name' => 'Live Synced Shopee Product',
                        'item_sku' => 'LIVE-SHP-999888',
                        'price_info' => [
                            ['currency' => 'IDR', 'original_price' => 75000, 'current_price' => 75000],
                        ],
                        'stock_info_v2' => [
                            'summary_info' => [
                                'total_available_stock' => 25,
                            ],
                        ],
                    ],
                ],
            ],
            'error' => '',
        ], 200),
    ]);

    Cache::forget('shopee_live_products_sync_lock');

    $response = $this->actingAs($admin)->get(route('warehouse.products'));
    $response->assertStatus(200);

    $syncedProduct = Product::where('shopee_item_id', 999888)->first();
    expect($syncedProduct)->not->toBeNull();
    expect($syncedProduct->name)->toBe('Live Synced Shopee Product');
    expect($syncedProduct->stock)->toBe(25);
});

test('shopee orders page automatically triggers live sync from Shopee API without manual sync button', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    $product = Product::create([
        'sku' => 'LIVE-ORD-SKU-1',
        'name' => 'Product For Live Order',
        'unit' => 'Pcs',
        'stock' => 15,
        'purchase_price' => 20000,
        'selling_price' => 40000,
        'shopee_item_id' => 888777,
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/order/get_order_list*' => Http::response([
            'response' => [
                'order_list' => [
                    ['order_sn' => 'LIVE_ORDER_SN_123'],
                ],
            ],
            'error' => '',
        ], 200),
        '*/api/v2/order/get_order_detail*' => Http::response([
            'response' => [
                'order_list' => [
                    [
                        'order_sn' => 'LIVE_ORDER_SN_123',
                        'order_status' => 'READY_TO_SHIP',
                        'total_amount' => 40000,
                        'buyer_username' => 'live_buyer',
                        'tracking_number' => 'LIVE_SPX_1234',
                        'shipping_carrier' => 'SPX Express',
                        'item_list' => [
                            [
                                'item_id' => 888777,
                                'item_sku' => 'LIVE-ORD-SKU-1',
                                'item_name' => 'Product For Live Order',
                                'model_quantity_purchased' => 1,
                                'model_discounted_price' => 40000,
                            ],
                        ],
                    ],
                ],
            ],
            'error' => '',
        ], 200),
    ]);

    Cache::forget('shopee_live_orders_sync_lock');

    $response = $this->actingAs($admin)->get(route('shopee.orders'));
    $response->assertStatus(200);

    $order = ShopeeOrder::where('order_sn', 'LIVE_ORDER_SN_123')->first();
    expect($order)->not->toBeNull();
    expect($order->buyer_username)->toBe('live_buyer');
    expect($product->fresh()->stock)->toBe(14); // 15 - 1
});

test('warehouse product update automatically pushes live stock to Shopee API', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    $product = Product::create([
        'sku' => 'PUSH-TEST-SKU',
        'name' => 'Push Test Product',
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 30000,
        'selling_price' => 60000,
        'shopee_item_id' => 555444,
        'status' => 'active',
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_partner_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/product/update_stock*' => Http::response([
            'error' => '',
            'message' => 'success',
            'response' => ['success_list' => []],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->put(route('warehouse.products.update', $product), [
        'sku' => 'PUSH-TEST-SKU',
        'name' => 'Push Test Product Updated',
        'unit' => 'Pcs',
        'purchase_price' => 30000,
        'selling_price' => 65000,
        'shopee_item_id' => 555444,
        'status' => 'active',
    ]);

    $response->assertRedirect();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/product/update_stock');
    });
});

test('order simulation is disabled and guarded when environment is production', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    $product = Product::create([
        'sku' => 'PROD-MODE-SKU',
        'name' => 'Production Mode Product',
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 30000,
        'selling_price' => 60000,
        'status' => 'active',
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'environment' => 'production',
    ]);

    expect($setting->isProduction())->toBeTrue();
    expect($setting->isSandbox())->toBeFalse();

    $response = $this->actingAs($admin)->post(route('shopee.simulate.order'), [
        'product_id' => $product->id,
        'qty' => 1,
        'order_status' => 'READY_TO_SHIP',
    ]);

    $response->assertSessionHas('error');
    expect($product->fresh()->stock)->toBe(10); // Not deducted
});

test('it updates physical stock, records adjustment mutation, and pushes to Shopee on product edit', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    $product = Product::create([
        'sku' => 'STOCK-EDIT-SKU',
        'name' => 'Stock Edit Product',
        'unit' => 'Pcs',
        'stock' => 15,
        'purchase_price' => 50000,
        'selling_price' => 90000,
        'shopee_item_id' => 888777,
        'status' => 'active',
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/product/update_stock*' => Http::response([
            'error' => '',
            'message' => 'success',
            'response' => ['success_list' => [['model_id' => 0, 'location_id' => 'IDZ', 'stock' => 25]]],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->put(route('warehouse.products.update', $product), [
        'sku' => 'STOCK-EDIT-SKU',
        'name' => 'Stock Edit Product',
        'unit' => 'Pcs',
        'purchase_price' => 50000,
        'selling_price' => 90000,
        'stock' => 25,
        'safety_stock' => 5,
        'shopee_item_id' => 888777,
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $fresh = $product->fresh();
    expect($fresh->stock)->toBe(25);
    expect($fresh->shopee_stock)->toBe(25);

    $mutation = StockMutation::where('product_id', $product->id)->latest()->first();
    expect($mutation)->not->toBeNull();
    expect($mutation->type)->toBe('adjustment');
    expect($mutation->stock_before)->toBe(15);
    expect($mutation->stock_after)->toBe(25);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/product/update_stock');
    });
});

test('it pushes stock to Shopee when recording inbound mutation', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    $product = Product::create([
        'sku' => 'MUT-INB-SKU',
        'name' => 'Inbound Mutation Product',
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 50000,
        'selling_price' => 90000,
        'shopee_item_id' => 999111,
        'status' => 'active',
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/product/update_stock*' => Http::response([
            'error' => '',
            'message' => 'success',
            'response' => ['success_list' => [['model_id' => 0, 'location_id' => 'IDZ', 'stock' => 15]]],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('warehouse.mutations.store'), [
        'product_id' => $product->id,
        'type' => 'inbound',
        'qty' => 5,
        'notes' => 'Restock baru masuk',
        'actor' => 'Admin Gudang',
    ]);

    $response->assertRedirect();
    $fresh = $product->fresh();
    expect($fresh->stock)->toBe(15);
    expect($fresh->shopee_stock)->toBe(15);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/product/update_stock');
    });
});

test('warehouse product catalog sub-tabs filter by status and calculate counts', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    Cache::put('shopee_live_products_sync_lock', time(), now()->addHours(1));

    $uniq = uniqid();
    $pActive = Product::create([
        'sku' => 'ACT-'.$uniq,
        'name' => 'Active Item '.$uniq,
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 10000,
        'selling_price' => 20000,
        'status' => 'active',
    ]);

    $pInactive = Product::create([
        'sku' => 'INA-'.$uniq,
        'name' => 'Inactive Item '.$uniq,
        'unit' => 'Pcs',
        'stock' => 5,
        'purchase_price' => 15000,
        'selling_price' => 25000,
        'status' => 'inactive',
    ]);

    // Test 'all' tab
    $resAll = $this->actingAs($admin)->get(route('warehouse.products', ['status' => 'all', 'search' => $uniq]));
    $resAll->assertStatus(200);
    $resAll->assertSee('Semua Produk');
    $resAll->assertSee('Produk Aktif');
    $resAll->assertSee('Tidak Aktif (Nonaktif)');
    expect($resAll->viewData('products')->pluck('name'))->toContain('Active Item '.$uniq);
    expect($resAll->viewData('products')->pluck('name'))->toContain('Inactive Item '.$uniq);
    expect($resAll->viewData('countActive'))->toBeGreaterThanOrEqual(1);
    expect($resAll->viewData('countInactive'))->toBeGreaterThanOrEqual(1);

    // Test 'active' tab
    $resAct = $this->actingAs($admin)->get(route('warehouse.products', ['status' => 'active', 'search' => $uniq]));
    $resAct->assertStatus(200);
    expect($resAct->viewData('products')->pluck('name'))->toContain('Active Item '.$uniq);
    expect($resAct->viewData('products')->pluck('name'))->not->toContain('Inactive Item '.$uniq);

    // Test 'inactive' tab
    $resIna = $this->actingAs($admin)->get(route('warehouse.products', ['status' => 'inactive', 'search' => $uniq]));
    $resIna->assertStatus(200);
    expect($resIna->viewData('products')->pluck('name'))->not->toContain('Active Item '.$uniq);
    expect($resIna->viewData('products')->pluck('name'))->toContain('Inactive Item '.$uniq);
    $resIna->assertSee('Produk tidak aktif otomatis disembunyikan');
});

test('warehouse product toggle-status unlists and relists on Shopee API', function () {
    $admin = User::firstOrCreate(
        ['email' => 'admin@medina.com'],
        ['name' => 'Admin Test', 'username' => 'admintest', 'password' => bcrypt('password'), 'role' => 'superadmin', 'is_active' => true]
    );

    $product = Product::create([
        'sku' => 'TOGGLE-SKU-1',
        'name' => 'Toggle Status Product',
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 20000,
        'selling_price' => 40000,
        'shopee_item_id' => 999888,
        'status' => 'active',
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'partner_id' => 123456,
        'partner_key' => 'secret_key',
        'shop_id' => 789012,
        'access_token' => 'valid_token',
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        '*/api/v2/product/unlist_item*' => Http::response([
            'error' => '',
            'message' => 'success',
            'response' => ['failure_list' => [], 'success_list' => [999888]],
        ], 200),
    ]);

    // 1. Toggle from active to inactive (unlist on Shopee)
    $response = $this->actingAs($admin)->patch(route('warehouse.products.toggle-status', $product));
    $response->assertRedirect();
    expect($product->fresh()->status)->toBe('inactive');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/product/unlist_item') &&
               $request['item_list'][0]['unlist'] === true;
    });

    // 2. Toggle from inactive back to active (relist on Shopee)
    $response2 = $this->actingAs($admin)->patch(route('warehouse.products.toggle-status', $product));
    $response2->assertRedirect();
    expect($product->fresh()->status)->toBe('active');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/product/unlist_item') &&
               $request['item_list'][0]['unlist'] === false;
    });
});
