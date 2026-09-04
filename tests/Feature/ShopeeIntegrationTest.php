<?php

use App\Models\Product;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use App\Services\ShopeeService;
use App\Services\StockMutationService;

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
