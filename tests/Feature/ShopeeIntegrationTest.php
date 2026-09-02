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
