<?php

use App\Models\PackingRecord;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::where('email', 'admin@medina.com')->first();
});

test('it blocks packing for cancelled Shopee orders and creates blocked packing log', function () {
    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-CANCEL-TEST-001',
        'tracking_number' => 'RESI-CANCEL-999',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'CANCELLED',
        'total_amount' => 150000,
        'buyer_username' => 'buyer_cancel_test',
        'items_data' => [
            ['item_name' => 'Item Test Cancel', 'item_sku' => 'SKU-001', 'model_quantity_purchased' => 1],
        ],
    ]);

    $response = $this->actingAs($this->user)->postJson(route('packing.check'), [
        'query' => 'RESI-CANCEL-999',
        'packer_name' => 'Budi Packer',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'blocked_cancelled',
        'order_sn' => 'ORD-CANCEL-TEST-001',
        'tracking_number' => 'RESI-CANCEL-999',
    ]);

    // Verify blocked record is logged
    $record = PackingRecord::where('order_sn', 'ORD-CANCEL-TEST-001')->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('blocked_cancelled');
    expect($record->packer_name)->toBe('Budi Packer');
});

test('it allows valid ready_to_ship orders and returns items checklist', function () {
    $product = Product::create([
        'sku' => 'SKU-PACK-001',
        'name' => 'Gamis Cantik Silk',
        'unit' => 'Pcs',
        'stock' => 20,
        'purchase_price' => 100000,
        'selling_price' => 200000,
    ]);

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-VALID-TEST-002',
        'tracking_number' => 'RESI-VALID-888',
        'shipping_carrier' => 'J&T Express',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 200000,
        'buyer_username' => 'buyer_valid_test',
        'items_data' => [
            [
                'item_name' => 'Gamis Cantik Silk',
                'item_sku' => 'SKU-PACK-001',
                'model_quantity_purchased' => 2,
                'model_discounted_price' => 200000,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)->postJson(route('packing.check'), [
        'query' => 'RESI-VALID-888',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'ready',
        'order_sn' => 'ORD-VALID-TEST-002',
        'tracking_number' => 'RESI-VALID-888',
        'shipping_carrier' => 'J&T Express',
    ]);

    $data = $response->json();
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['name'])->toBe('Gamis Cantik Silk');
    expect($data['items'][0]['qty'])->toBe(2);
});

test('it can upload and store packing video and create completed record', function () {
    Storage::fake('public');

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-UPLOAD-TEST-003',
        'tracking_number' => 'RESI-UPLOAD-777',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 120000,
        'buyer_username' => 'buyer_upload_test',
    ]);

    $dummyVideo = UploadedFile::fake()->create('packing_video.webm', 1024, 'video/webm');

    $response = $this->actingAs($this->user)->postJson(route('packing.upload'), [
        'order_sn' => 'ORD-UPLOAD-TEST-003',
        'tracking_number' => 'RESI-UPLOAD-777',
        'video' => $dummyVideo,
        'duration' => 15,
        'packer_name' => 'Ahmad Packer',
        'items_checked' => json_encode([['name' => 'Item 1', 'qty' => 1, 'checked' => true]]),
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'success',
    ]);

    $record = PackingRecord::where('order_sn', 'ORD-UPLOAD-TEST-003')->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('completed');
    expect($record->video_duration)->toBe(15);
    expect($record->packer_name)->toBe('Ahmad Packer');

    // Check file exists in fake storage
    Storage::disk('public')->assertExists($record->video_path);

    // Order status should update to PROCESSED
    expect($order->fresh()->order_status)->toBe('PROCESSED');
});

test('it renders packing index and history views', function () {
    $this->actingAs($this->user)->get(route('packing.index'))->assertStatus(200);
    $this->actingAs($this->user)->get(route('packing.history'))->assertStatus(200);
});
