<?php

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use App\Models\User;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('it verifies OpenShopee API bridge push authenticity via API key and HMAC-SHA256 signature', function () {
    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_live_secret_bridge_key_123',
        'shop_id' => 789012,
        'remote_connected' => true,
    ]);

    $service = new ShopeeService($setting);
    $rawBody = '{"event":"order.updated","data":{"order":{"order_sn":"SHP123"}}}';
    $validHmac = hash_hmac('sha256', $rawBody, 'osk_live_secret_bridge_key_123');

    expect($service->verifyBridgePush($rawBody, $validHmac, ''))->toBeTrue();
    expect($service->verifyBridgePush($rawBody, '', 'osk_live_secret_bridge_key_123'))->toBeTrue();
    expect($service->verifyBridgePush($rawBody, 'invalid_hmac', 'wrong_key'))->toBeFalse();
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
    $mutationService->processShopeeOrder($orderData);
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

test('it synchronizes gateway status and refreshes Shopee token via OpenShopee API', function () {
    $futureExpiry = now()->addHours(4)->toIso8601String();

    Http::fake([
        'http://openshopee.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_name' => 'Medina Official Store',
                'shop_id' => 789012,
                'environment' => 'sandbox',
                'is_connected' => true,
                'auto_sync_stock' => true,
                'token_expires_at' => $futureExpiry,
                'dashboard_url' => 'http://openshopee.test/dashboard',
            ],
            'counts' => ['products' => 5, 'orders' => 12, 'ready_to_ship' => 3],
        ], 200),
        'http://openshopee.test/api/v1/auth/refresh-token*' => Http::response([
            'success' => true,
            'status' => [
                'shop_name' => 'Medina Official Store',
                'shop_id' => 789012,
                'environment' => 'sandbox',
                'is_connected' => true,
                'token_expires_at' => $futureExpiry,
            ],
        ], 200),
    ]);

    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_test_refresh_key',
        'shop_id' => 789012,
        'remote_connected' => false,
        'token_expires_at' => now()->subMinutes(15),
    ]);

    Cache::forget('openshopee_gateway_status_ping');

    $service = new ShopeeService($setting);
    $valid = $service->ensureValidToken();

    expect($valid)->toBeTrue();
    expect($setting->fresh()->remote_connected)->toBeTrue();
    expect($setting->fresh()->token_expires_at->isFuture())->toBeTrue();

    $refreshRes = $service->refreshAccessToken();
    expect($refreshRes['success'])->toBeTrue();
});

test('it pulls orders from OpenShopee API and automatically deducts warehouse stock', function () {
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
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_test_orders_pull',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/orders/pull*' => Http::response([
            'success' => true,
            'synced_count' => 1,
            'orders' => [
                [
                    'order_sn' => '260904PULLTEST99',
                    'order_status' => 'READY_TO_SHIP',
                    'total_amount' => 100000,
                    'buyer_username' => 'buyer_pull_api',
                    'tracking_number' => 'SPXID9988776655',
                    'shipping_carrier' => 'SPX Express',
                    'items' => [
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
        ], 200),
    ]);

    $service = new ShopeeService($setting);
    $result = $service->pullAndSyncOrders(days: 3);

    expect($result['success'])->toBeTrue();
    expect($result['synced_count'])->toBe(1);
    expect($product->fresh()->stock)->toBe(28);

    $mutation = StockMutation::where('reference_no', 'SHP-260904PULLTEST99')->first();
    expect($mutation)->not->toBeNull();
    expect($mutation->qty)->toBe(-2);
});

test('it gets tracking info and tracking number via OpenShopee API', function () {
    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_test_logistics',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/logistics/tracking-info*' => Http::response([
            'success' => true,
            'data' => [
                'tracking_info' => [
                    ['description' => 'Parcel is being prepared for pickup', 'logistics_status' => 'INITIAL'],
                ],
            ],
        ], 200),
        'http://openshopee.test/api/v1/logistics/tracking-number*' => Http::response([
            'success' => true,
            'data' => [
                'tracking_number' => 'SPXID1234567890',
            ],
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

test('shopee:sync command synchronizes products and orders via OpenShopee API and updates last_synced_at', function () {
    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_test_cli_sync',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
        'last_synced_at' => null,
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_id' => 789012,
                'shop_name' => 'Medina Official Store',
                'environment' => 'sandbox',
                'is_connected' => true,
                'token_expires_at' => now()->addHours(3)->toIso8601String(),
            ],
        ], 200),
        'http://openshopee.test/api/v1/products/pull*' => Http::response([
            'success' => true,
            'synced_count' => 1,
            'products' => [
                [
                    'item_id' => 777666,
                    'sku' => 'AUTO-777666',
                    'name' => 'Auto Synced Product',
                    'selling_price' => 50000,
                    'stock' => 12,
                    'status' => 'active',
                ],
            ],
        ], 200),
        'http://openshopee.test/api/v1/orders/pull*' => Http::response([
            'success' => true,
            'synced_count' => 0,
            'orders' => [],
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

test('it handles real-time OpenShopee API bridge webhook push for order.updated, products.synced, and ping', function () {
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
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_webhook_bridge_secret_99',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    // 1. Reject unauthenticated push
    $this->postJson('/api/shopee-bridge/webhook', ['event' => 'ping'])
        ->assertStatus(401);

    // 2. Accept signed ping
    $pingBody = json_encode(['event' => 'ping', 'data' => ['message' => 'test']]);
    $pingSign = hash_hmac('sha256', $pingBody, 'osk_webhook_bridge_secret_99');

    $this->call(
        'POST',
        '/api/shopee-bridge/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_OPENSHOPEE_EVENT' => 'ping',
            'HTTP_X_OPENSHOPEE_SIGNATURE' => $pingSign,
        ],
        $pingBody
    )->assertStatus(200)->assertJson(['code' => 0, 'message' => 'pong']);

    // 3. Real-time order.updated push deducts warehouse stock immediately
    $orderPayload = [
        'event' => 'order.updated',
        'shop_id' => 789012,
        'data' => [
            'order' => [
                'order_sn' => 'WEBHOOK_ORD_7711',
                'order_status' => 'READY_TO_SHIP',
                'buyer_username' => 'webhook_buyer',
                'total_amount' => 180000,
                'tracking_number' => 'JNT_REALTIME_01',
                'shipping_carrier' => 'J&T Express',
                'items' => [
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
    ];

    $rawOrderContent = json_encode($orderPayload);
    $orderSignature = hash_hmac('sha256', $rawOrderContent, 'osk_webhook_bridge_secret_99');

    $response = $this->call(
        'POST',
        '/api/shopee-bridge/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_OPENSHOPEE_EVENT' => 'order.updated',
            'HTTP_X_OPENSHOPEE_SIGNATURE' => $orderSignature,
        ],
        $rawOrderContent
    );

    $response->assertStatus(200);
    $response->assertJson(['code' => 0, 'message' => 'order synced']);

    $product->refresh();
    expect($product->stock)->toBe(18);

    $mutation = StockMutation::where('reference_no', 'SHP-WEBHOOK_ORD_7711')->first();
    expect($mutation)->not->toBeNull();
    expect($mutation->qty)->toBe(-2);
});

test('warehouse products page automatically triggers live sync from OpenShopee API without manual sync button', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_live_products_key',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_id' => 789012,
                'is_connected' => true,
                'token_expires_at' => now()->addHours(3)->toIso8601String(),
            ],
        ], 200),
        'http://openshopee.test/api/v1/products/pull*' => Http::response([
            'success' => true,
            'synced_count' => 1,
            'products' => [
                [
                    'item_id' => 999888,
                    'name' => 'Live Synced Shopee Product',
                    'sku' => 'LIVE-SHP-999888',
                    'selling_price' => 75000,
                    'stock' => 25,
                    'status' => 'active',
                ],
            ],
        ], 200),
    ]);

    Cache::forget('openshopee_gateway_status_ping');
    Cache::forget('shopee_live_products_sync_lock');

    $response = $this->actingAs($admin)->get(route('warehouse.products'));
    $response->assertStatus(200);

    $syncedProduct = Product::where('shopee_item_id', 999888)->first();
    expect($syncedProduct)->not->toBeNull();
    expect($syncedProduct->name)->toBe('Live Synced Shopee Product');
    expect($syncedProduct->stock)->toBe(25);
});

test('shopee orders page automatically triggers live sync from OpenShopee API without manual sync button', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

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
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_live_orders_key',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_id' => 789012,
                'is_connected' => true,
                'token_expires_at' => now()->addHours(3)->toIso8601String(),
            ],
        ], 200),
        'http://openshopee.test/api/v1/orders/pull*' => Http::response([
            'success' => true,
            'synced_count' => 1,
            'orders' => [
                [
                    'order_sn' => 'LIVE_ORDER_SN_123',
                    'order_status' => 'READY_TO_SHIP',
                    'total_amount' => 40000,
                    'buyer_username' => 'live_buyer',
                    'tracking_number' => 'LIVE_SPX_1234',
                    'shipping_carrier' => 'SPX Express',
                    'items' => [
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
        ], 200),
    ]);

    Cache::forget('openshopee_gateway_status_ping');
    Cache::forget('shopee_live_orders_sync_lock');

    $response = $this->actingAs($admin)->get(route('shopee.orders'));
    $response->assertStatus(200);

    $order = ShopeeOrder::where('order_sn', 'LIVE_ORDER_SN_123')->first();
    expect($order)->not->toBeNull();
    expect($order->buyer_username)->toBe('live_buyer');
    expect($product->fresh()->stock)->toBe(14);
});

test('warehouse product update automatically pushes live stock, price, and item name to OpenShopee API', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

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
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_push_product_key',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/products/update-stock*' => Http::response(['success' => true], 200),
        'http://openshopee.test/api/v1/products/update-price*' => Http::response(['success' => true], 200),
        'http://openshopee.test/api/v1/products/update-item*' => Http::response(['success' => true], 200),
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
        return str_contains($request->url(), '/api/v1/products/update-stock')
            && $request->hasHeader('X-API-Key', 'osk_push_product_key');
    });
});

test('order simulation is disabled and guarded when environment is production', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

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
    expect($product->fresh()->stock)->toBe(10);
});

test('it updates physical stock, records adjustment mutation, and pushes to OpenShopee API on product edit', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

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
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_stock_edit_key',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/products/update-stock*' => Http::response(['success' => true], 200),
        'http://openshopee.test/api/v1/products/update-price*' => Http::response(['success' => true], 200),
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
        return str_contains($request->url(), '/api/v1/products/update-stock')
            && $request['stock'] === 25;
    });
});

test('it pushes stock to OpenShopee API when recording inbound mutation', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

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
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_inbound_key',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/products/update-stock*' => Http::response(['success' => true], 200),
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
        return str_contains($request->url(), '/api/v1/products/update-stock')
            && $request['stock'] === 15;
    });
});

test('warehouse product catalog sub-tabs filter by status and calculate counts', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    Cache::put('shopee_live_products_sync_lock', time(), now()->addHours(1));

    $uniq = uniqid();
    Product::create([
        'sku' => 'ACT-'.$uniq,
        'name' => 'Active Item '.$uniq,
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 10000,
        'selling_price' => 20000,
        'status' => 'active',
    ]);

    Product::create([
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

test('warehouse product toggle-status unlists and relists via OpenShopee API', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

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
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_toggle_key',
        'shop_id' => 789012,
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(3),
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/products/unlist*' => Http::response(['success' => true], 200),
    ]);

    // 1. Toggle from active to inactive (unlist on OpenShopee API)
    $response = $this->actingAs($admin)->patch(route('warehouse.products.toggle-status', $product));
    $response->assertRedirect();
    expect($product->fresh()->status)->toBe('inactive');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/products/unlist')
            && $request['item_id'] === 999888
            && $request['unlist'] === true;
    });

    // 2. Toggle from inactive back to active (relist on OpenShopee API)
    $response2 = $this->actingAs($admin)->patch(route('warehouse.products.toggle-status', $product));
    $response2->assertRedirect();
    expect($product->fresh()->status)->toBe('active');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/products/unlist')
            && $request['item_id'] === 999888
            && $request['unlist'] === false;
    });
});

test('pesanan shopee page strictly displays only READY_TO_SHIP orders', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    ShopeeOrder::create([
        'order_sn' => 'SN_ONLY_READY_001',
        'buyer_username' => 'buyer_ready',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 150000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_ONLY_PROC_002',
        'buyer_username' => 'buyer_proc',
        'order_status' => 'PROCESSED',
        'total_amount' => 200000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_ONLY_DONE_003',
        'buyer_username' => 'buyer_done',
        'order_status' => 'COMPLETED',
        'total_amount' => 300000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_ONLY_CANC_004',
        'buyer_username' => 'buyer_canc',
        'order_status' => 'CANCELLED',
        'total_amount' => 500000,
    ]);

    $response = $this->actingAs($admin)->get(route('shopee.orders'));

    $response->assertStatus(200);
    $response->assertSee('SN_ONLY_READY_001');
    $response->assertDontSee('SN_ONLY_PROC_002');
    $response->assertDontSee('SN_ONLY_DONE_003');
    $response->assertDontSee('SN_ONLY_CANC_004');
    $response->assertSee('Lacak Kurir');
});

test('shopee dashboard calculates revenue correctly excluding cancelled orders and separating released vs escrow', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    ShopeeOrder::query()->delete();

    ShopeeOrder::create([
        'order_sn' => 'SN_REV_COMPLETED',
        'order_status' => 'COMPLETED',
        'total_amount' => 350000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_REV_READY',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 150000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_REV_INTRANSIT',
        'order_status' => 'PROCESSED',
        'total_amount' => 200000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_REV_CANCELLED',
        'order_status' => 'CANCELLED',
        'total_amount' => 999000,
    ]);

    $response = $this->actingAs($admin)->get(route('shopee.dashboard'));

    $response->assertStatus(200);
    $response->assertViewHas('totalSales', 700000.0);
    $response->assertViewHas('releasedSales', 350000.0);
    $response->assertViewHas('escrowSales', 350000.0);
    $response->assertViewHas('readyToShipCount', 1);
    $response->assertViewHas('inShippingCount', 1);
});

test('main warehouse dashboard displays accurate shopee income and workflow breakdown', function () {
    Http::fake([
        '*vendorarouter*' => Http::response([
            'choices' => [['message' => ['content' => 'Ringkasan analisis AI.']]],
        ], 200),
    ]);

    $admin = User::where('email', 'admin@medina.com')->first();

    ShopeeOrder::query()->delete();

    ShopeeOrder::create([
        'order_sn' => 'SN_MAIN_DONE',
        'order_status' => 'COMPLETED',
        'total_amount' => 500000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_MAIN_READY',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 250000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_MAIN_INTRANSIT',
        'order_status' => 'PROCESSED',
        'total_amount' => 150000,
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SN_MAIN_CANCEL',
        'order_status' => 'CANCELLED',
        'total_amount' => 400000,
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertViewHas('totalShopeeSales', 900000.0);
    $response->assertViewHas('releasedShopeeSales', 500000.0);
    $response->assertViewHas('escrowShopeeSales', 400000.0);
    $response->assertViewHas('readyToShipOrders', 1);
    $response->assertViewHas('inTransitOrders', 1);
    $response->assertSee('Pesanan Siap Dikirim');
    $response->assertSee('Dalam Pengiriman');
});

test('settings page saves OpenShopee API bridge credentials, verifies connection, and runs real-time delta sync', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    Http::fake([
        'http://openshopee.test/api/v1/settings*' => Http::response([
            'success' => true,
            'status' => [
                'shop_name' => 'Medina Official Store',
                'shop_id' => 556677,
                'environment' => 'sandbox',
                'is_connected' => true,
                'token_expires_at' => now()->addHours(4)->toIso8601String(),
                'dashboard_url' => 'http://openshopee.test/dashboard',
            ],
        ], 200),
        'http://openshopee.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_name' => 'Medina Official Store',
                'shop_id' => 556677,
                'environment' => 'sandbox',
                'is_connected' => true,
                'token_expires_at' => now()->addHours(4)->toIso8601String(),
                'dashboard_url' => 'http://openshopee.test/dashboard',
            ],
            'counts' => ['products' => 1, 'orders' => 1, 'ready_to_ship' => 1],
        ], 200),
        'http://openshopee.test/api/v1/orders*' => Http::response([
            'success' => true,
            'orders' => [
                [
                    'order_sn' => 'DELTA_ORD_001',
                    'order_status' => 'READY_TO_SHIP',
                    'buyer_username' => 'delta_buyer',
                    'total_amount' => 125000,
                    'items' => [],
                ],
            ],
            'server_time' => now()->toIso8601String(),
        ], 200),
        'http://openshopee.test/api/v1/products*' => Http::response([
            'success' => true,
            'products' => [
                [
                    'item_id' => 443322,
                    'sku' => 'DELTA-SKU-01',
                    'name' => 'Delta Synced Product',
                    'selling_price' => 125000,
                    'stock' => 40,
                    'status' => 'active',
                ],
            ],
        ], 200),
    ]);

    $res = $this->actingAs($admin)->post(route('shopee.settings.update'), [
        'api_url' => 'http://openshopee.test/',
        'api_key' => 'osk_live_bridge_token_xyz',
        'shop_name' => 'Medina Official Store',
        'environment' => 'sandbox',
        'auto_sync_stock' => '1',
    ]);

    $res->assertRedirect(route('shopee.settings'));
    $res->assertSessionHas('success');

    $setting = ShopeeSetting::current()->fresh();
    expect($setting->api_url)->toBe('http://openshopee.test');
    expect($setting->api_key)->toBe('osk_live_bridge_token_xyz');
    expect($setting->remote_connected)->toBeTrue();
    expect($setting->shop_id)->toBe(556677);

    expect(Product::where('shopee_item_id', 443322)->exists())->toBeTrue();
    expect(ShopeeOrder::where('order_sn', 'DELTA_ORD_001')->exists())->toBeTrue();
});

test('changing api_key on warehouse settings wipes all old products, mutations, and orders and refreshes cleanly from new API', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://openshopee-old.test',
        'api_key' => 'osk_old_stale_api_key_111',
        'shop_name' => 'Toko Lawas',
        'shop_id' => 111000,
        'environment' => 'sandbox',
        'remote_connected' => true,
        'last_synced_at' => now()->subMinutes(10),
    ]);

    $staleProduct = Product::create([
        'sku' => 'OLD-STALE-SKU-99',
        'name' => 'Produk Lama Harus Hilang',
        'unit' => 'Pcs',
        'stock' => 99,
        'purchase_price' => 40000,
        'selling_price' => 80000,
        'shopee_item_id' => 111222,
        'status' => 'active',
    ]);

    StockMutation::create([
        'product_id' => $staleProduct->id,
        'reference_no' => 'OLD-MUT-999',
        'type' => 'inbound',
        'qty' => 99,
        'stock_before' => 0,
        'stock_after' => 99,
        'notes' => 'Data lama',
        'actor' => 'Old Sync',
    ]);

    ShopeeOrder::create([
        'order_sn' => 'OLD_STALE_ORD_999',
        'shop_id' => 111000,
        'order_status' => 'READY_TO_SHIP',
        'buyer_username' => 'old_buyer',
        'total_amount' => 160000,
    ]);

    Http::fake([
        'http://openshopee-new.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_name' => 'Medina Production Store',
                'shop_id' => 888999,
                'environment' => 'production',
                'is_connected' => true,
                'token_expires_at' => now()->addHours(4)->toIso8601String(),
                'dashboard_url' => 'http://openshopee-new.test/dashboard',
            ],
            'counts' => ['products' => 1, 'orders' => 1, 'ready_to_ship' => 1],
        ], 200),
        'http://openshopee-new.test/api/v1/products/pull*' => Http::response([
            'success' => true,
            'reconciled' => true,
            'products' => [[
                'item_id' => 333444,
                'sku' => 'NEW-API-SKU-01',
                'name' => 'Produk Baru Dari API',
                'selling_price' => 150000,
                'stock' => 42,
                'status' => 'active',
            ]],
        ], 200),
        'http://openshopee-new.test/api/v1/orders/pull*' => Http::response([
            'success' => true,
            'orders' => [[
                'order_sn' => 'NEW_API_ORD_001',
                'order_status' => 'READY_TO_SHIP',
                'buyer_username' => 'new_real_buyer',
                'total_amount' => 450000,
                'items' => [[
                    'item_id' => 333444,
                    'item_sku' => 'NEW-API-SKU-01',
                    'item_name' => 'Produk Baru Dari API',
                    'model_quantity_purchased' => 3,
                    'model_discounted_price' => 150000,
                ]],
            ]],
        ], 200),
        'http://openshopee-new.test/api/v1/products*' => Http::response([
            'success' => true,
            'environment' => 'production',
            'products' => [[
                'item_id' => 333444,
                'sku' => 'NEW-API-SKU-01',
                'name' => 'Produk Baru Dari API',
                'selling_price' => 150000,
                'stock' => 42,
                'status' => 'active',
            ]],
        ], 200),
        'http://openshopee-new.test/api/v1/orders*' => Http::response([
            'success' => true,
            'environment' => 'production',
            'orders' => [[
                'order_sn' => 'NEW_API_ORD_001',
                'order_status' => 'READY_TO_SHIP',
                'buyer_username' => 'new_real_buyer',
                'total_amount' => 450000,
                'items' => [[
                    'item_id' => 333444,
                    'item_sku' => 'NEW-API-SKU-01',
                    'item_name' => 'Produk Baru Dari API',
                    'model_quantity_purchased' => 3,
                    'model_discounted_price' => 150000,
                ]],
            ]],
            'server_time' => now()->toIso8601String(),
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('shopee.settings.update'), [
        'api_url' => 'http://openshopee-new.test',
        'api_key' => 'osk_new_fresh_api_key_222',
        'environment' => 'sandbox',
        'auto_sync_stock' => '1',
    ]);

    $response->assertRedirect(route('shopee.settings'));
    $response->assertSessionHas('success');

    // Old stale data must be 100% gone
    expect(Product::where('sku', 'OLD-STALE-SKU-99')->exists())->toBeFalse();
    expect(ShopeeOrder::where('order_sn', 'OLD_STALE_ORD_999')->exists())->toBeFalse();
    expect(StockMutation::where('reference_no', 'OLD-MUT-999')->exists())->toBeFalse();

    // New API data & authoritative stock (42, without double-deducting historical order) must be active
    $newProduct = Product::where('sku', 'NEW-API-SKU-01')->first();
    expect($newProduct)->not->toBeNull();
    expect($newProduct->stock)->toBe(42);
    expect(ShopeeOrder::where('order_sn', 'NEW_API_ORD_001')->first()?->stock_deducted)->toBeTrue();
    expect($setting->fresh()->environment)->toBe('production');
    expect($setting->fresh()->shop_id)->toBe(888999);

    // Changing API key again to an empty / newly provisioned OpenShopee API leaves zero stale products or orders
    Http::fake([
        'http://openshopee-empty.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_name' => 'Toko Kosong Baru',
                'shop_id' => null,
                'environment' => 'sandbox',
                'is_connected' => false,
                'token_expires_at' => null,
            ],
            'counts' => ['products' => 0, 'orders' => 0, 'ready_to_ship' => 0],
        ], 200),
        'http://openshopee-empty.test/api/v1/products*' => Http::response([
            'success' => true,
            'environment' => 'sandbox',
            'products' => [],
        ], 200),
        'http://openshopee-empty.test/api/v1/orders*' => Http::response([
            'success' => true,
            'environment' => 'sandbox',
            'orders' => [],
            'server_time' => now()->toIso8601String(),
        ], 200),
    ]);

    $this->actingAs($admin)->post(route('shopee.settings.update'), [
        'api_url' => 'http://openshopee-empty.test',
        'api_key' => 'osk_brand_new_empty_key_333',
        'environment' => 'sandbox',
        'auto_sync_stock' => '1',
    ])->assertRedirect(route('shopee.settings'));

    expect(Product::count())->toBe(0);
    expect(ShopeeOrder::count())->toBe(0);
    expect(StockMutation::count())->toBe(0);
    expect($setting->fresh()->remote_connected)->toBeFalse();
});

test('switching active environment on OpenShopee API automatically refreshes warehouse data to match the active partition', function () {
    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://openshopee.test',
        'api_key' => 'osk_mode_switch_secret_777',
        'shop_id' => 100200,
        'environment' => 'sandbox',
        'remote_connected' => true,
        'last_synced_at' => now()->subMinute(),
    ]);

    Product::create([
        'sku' => 'SBX-ONLY-SKU',
        'name' => 'Sandbox Product',
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 20000,
        'selling_price' => 40000,
        'shopee_item_id' => 70001,
        'status' => 'active',
    ]);

    ShopeeOrder::create([
        'order_sn' => 'SBX_ONLY_ORD_01',
        'shop_id' => 100200,
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 40000,
    ]);

    Http::fake([
        'http://openshopee.test/api/v1/status*' => Http::response([
            'success' => true,
            'status' => [
                'shop_name' => 'Medina Live Production',
                'shop_id' => 999888,
                'environment' => 'production',
                'is_connected' => true,
                'token_expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ], 200),
        'http://openshopee.test/api/v1/products/pull*' => Http::response([
            'success' => false,
            'buffered_preserved' => true,
            'error' => 'Shopee upstream maintenance (using preserved production buffer)',
        ], 400),
        'http://openshopee.test/api/v1/orders/pull*' => Http::response([
            'success' => false,
            'error' => 'Shopee upstream maintenance',
        ], 400),
        'http://openshopee.test/api/v1/products*' => Http::response([
            'success' => true,
            'environment' => 'production',
            'products' => [[
                'item_id' => 80001,
                'sku' => 'PROD-BUFFERED-SKU',
                'name' => 'Production Buffered Product',
                'selling_price' => 195000,
                'stock' => 75,
                'status' => 'active',
            ]],
        ], 200),
        'http://openshopee.test/api/v1/orders*' => Http::response([
            'success' => true,
            'environment' => 'production',
            'orders' => [[
                'order_sn' => 'PROD_BUFFERED_ORD_01',
                'order_status' => 'READY_TO_SHIP',
                'buyer_username' => 'prod_customer',
                'total_amount' => 195000,
                'items' => [],
            ]],
            'server_time' => now()->toIso8601String(),
        ], 200),
    ]);

    $pushBody = json_encode([
        'event' => 'settings.updated',
        'environment' => 'production',
        'data' => [
            'shop_name' => 'Medina Live Production',
            'shop_id' => 999888,
            'environment' => 'production',
            'is_connected' => true,
        ],
    ]);
    $signature = hash_hmac('sha256', $pushBody, 'osk_mode_switch_secret_777');

    $this->call(
        'POST',
        '/api/shopee-bridge/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_OPENSHOPEE_EVENT' => 'settings.updated',
            'HTTP_X_OPENSHOPEE_SIGNATURE' => $signature,
        ],
        $pushBody
    )->assertOk();

    expect(Product::where('sku', 'SBX-ONLY-SKU')->exists())->toBeFalse();
    expect(ShopeeOrder::where('order_sn', 'SBX_ONLY_ORD_01')->exists())->toBeFalse();

    expect(Product::where('sku', 'PROD-BUFFERED-SKU')->first()?->stock)->toBe(75);
    expect(ShopeeOrder::where('order_sn', 'PROD_BUFFERED_ORD_01')->exists())->toBeTrue();
    expect($setting->fresh()->environment)->toBe('production');
});

test('local warehouse survives sudden API VPS maintenance without data loss and auto-flushes queued stock changes once VPS recovers', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $setting = ShopeeSetting::current();
    $setting->update([
        'api_url' => 'http://vps-openshopee.test',
        'api_key' => 'osk_vps_prod_key_888',
        'shop_id' => 999888,
        'environment' => 'production',
        'remote_connected' => true,
        'token_expires_at' => now()->addHours(4),
        'last_synced_at' => now()->subMinute(),
    ]);

    $product = Product::create([
        'sku' => 'VPS-SAFE-SKU-01',
        'name' => 'Produk Gudang Aman Saat VPS Maintenance',
        'unit' => 'Pcs',
        'stock' => 20,
        'purchase_price' => 50000,
        'selling_price' => 100000,
        'shopee_item_id' => 91001,
        'status' => 'active',
    ]);

    ShopeeOrder::create([
        'order_sn' => 'VPS_SAFE_ORD_01',
        'shop_id' => 999888,
        'order_status' => 'READY_TO_SHIP',
        'buyer_username' => 'customer_safe',
        'total_amount' => 100000,
    ]);

    Cache::forget('openshopee_gateway_status_ping');
    Cache::forget('openshopee_vps_maintenance_breaker');
    Cache::forget('openshopee_pending_stock_outbox');

    $vpsInMaintenance = true;
    Http::fake(function ($request) use (&$vpsInMaintenance) {
        if ($vpsInMaintenance) {
            return Http::response('503 Service Temporarily Unavailable (VPS Maintenance)', 503);
        }

        if (str_contains($request->url(), '/api/v1/status')) {
            return Http::response([
                'success' => true,
                'status' => [
                    'shop_name' => 'Medina Official Production',
                    'shop_id' => 999888,
                    'environment' => 'production',
                    'is_connected' => true,
                    'token_expires_at' => now()->addHours(4)->toIso8601String(),
                ],
                'counts' => ['products' => 1, 'orders' => 1, 'ready_to_ship' => 1],
            ], 200);
        }

        if (str_contains($request->url(), '/api/v1/products/batch-sync')) {
            return Http::response([
                'success' => true,
                'synced_count' => 1,
            ], 200);
        }

        return Http::response(['success' => true, 'products' => [], 'orders' => []], 200);
    });

    // 1. VPS suddenly enters 503 maintenance: warehouse pages still work & preserve 100% local data
    $this->actingAs($admin)->get(route('warehouse.products'))->assertOk()->assertSee('VPS-SAFE-SKU-01');
    $this->actingAs($admin)->get(route('shopee.orders'))->assertOk()->assertSee('VPS_SAFE_ORD_01');

    // 2. Staff records +10 inbound stock locally while VPS is STILL down
    $this->actingAs($admin)->post(route('warehouse.mutations.store'), [
        'product_id' => $product->id,
        'type' => 'inbound',
        'qty' => 10,
        'notes' => 'Barang masuk saat VPS sedang maintenance',
        'actor' => 'Admin Gudang',
    ])->assertRedirect();

    expect($product->fresh()->stock)->toBe(30);
    expect(Cache::get('openshopee_pending_stock_outbox'))->not->toBeEmpty();

    // 3. VPS finishes maintenance and comes back online -> auto-flushes queued stock (30) to Shopee!
    $vpsInMaintenance = false;
    Cache::forget('openshopee_gateway_status_ping');

    $ping = app(ShopeeService::class)->syncGatewayStatus(true);
    expect($ping['success'])->toBeTrue();
    expect(Cache::get('openshopee_pending_stock_outbox'))->toBeNull();

    Http::assertSent(fn ($req) => str_contains($req->url(), '/api/v1/products/batch-sync')
        && ($req['products'][0]['item_id'] ?? null) === 91001
        && ($req['products'][0]['stock'] ?? null) === 30
    );
});
