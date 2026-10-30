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

test('it returns 404 and does not create orders for invalid or arbitrary tracking numbers', function () {
    $initialOrderCount = ShopeeOrder::count();

    $response = $this->actingAs($this->user)->postJson(route('packing.check'), [
        'query' => 'q2wed1q241',
    ]);

    $response->assertStatus(404);
    $response->assertJson([
        'status' => 'error',
    ]);

    expect(ShopeeOrder::count())->toBe($initialOrderCount);
});

test('it returns items in checkOrder for return unboxing inspection on cancelled orders', function () {
    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-RETURN-TEST-004',
        'tracking_number' => 'RESI-RETUR-111',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'CANCELLED',
        'total_amount' => 99000,
        'buyer_username' => 'buyer_retur_test',
        'items_data' => [
            ['item_name' => 'Baju Koko Modern', 'item_sku' => 'SKU-RETUR-1', 'model_quantity_purchased' => 1],
        ],
    ]);

    $response = $this->actingAs($this->user)->postJson(route('packing.check'), [
        'query' => 'RESI-RETUR-111',
    ]);

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['status'])->toBe('blocked_cancelled');
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['name'])->toBe('Baju Koko Modern');
});

test('it can upload and store return unboxing inspection video', function () {
    Storage::fake('public');

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-RETURN-VIDEO-005',
        'tracking_number' => 'RESI-RETUR-VIDEO-222',
        'order_status' => 'CANCELLED',
        'total_amount' => 175000,
        'buyer_username' => 'buyer_unboxing_test',
    ]);

    $dummyVideo = UploadedFile::fake()->create('unboxing_video.webm', 1024, 'video/webm');

    $response = $this->actingAs($this->user)->postJson(route('packing.upload'), [
        'order_sn' => 'ORD-RETURN-VIDEO-005',
        'tracking_number' => 'RESI-RETUR-VIDEO-222',
        'video' => $dummyVideo,
        'duration' => 25,
        'mode' => 'return_unboxing',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'success',
    ]);

    $record = PackingRecord::where('order_sn', 'ORD-RETURN-VIDEO-005')->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('completed');
    expect($record->notes)->toContain('Unboxing');
});

test('it returns 422 json error when video file is missing on upload', function () {
    $response = $this->actingAs($this->user)->postJson(route('packing.upload'), [
        'order_sn' => 'ORD-NO-VIDEO',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'status' => 'error',
    ]);
});

test('it can initialize background upload and update to completed on video upload', function () {
    Storage::fake('public');

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-BG-UPLOAD-007',
        'tracking_number' => 'RESI-BG-777',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 190000,
        'buyer_username' => 'buyer_bg_test',
    ]);

    // 1. Init background upload
    $initResponse = $this->actingAs($this->user)->postJson(route('packing.initUpload'), [
        'order_sn' => 'ORD-BG-UPLOAD-007',
        'tracking_number' => 'RESI-BG-777',
        'duration' => 20,
        'packer_name' => 'Siti Background',
    ]);

    $initResponse->assertStatus(200);
    $initResponse->assertJson(['status' => 'success']);

    $record = PackingRecord::where('order_sn', 'ORD-BG-UPLOAD-007')->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('in_progress');

    // 2. Scan package again while uploading: should detect uploading state
    $checkResponse = $this->actingAs($this->user)->postJson(route('packing.check'), [
        'query' => 'RESI-BG-777',
    ]);

    $checkResponse->assertStatus(200);
    $checkResponse->assertJson([
        'status' => 'ready',
        'already_packed' => true,
        'is_uploading' => true,
    ]);

    // 3. Complete the upload
    $dummyVideo = UploadedFile::fake()->create('bg_video.mp4', 2048, 'video/mp4');
    $uploadResponse = $this->actingAs($this->user)->postJson(route('packing.upload'), [
        'order_sn' => 'ORD-BG-UPLOAD-007',
        'tracking_number' => 'RESI-BG-777',
        'video' => $dummyVideo,
        'duration' => 20,
    ]);

    $uploadResponse->assertStatus(200);
    $uploadResponse->assertJson(['status' => 'success']);

    expect($record->fresh()->status)->toBe('completed');
    expect($record->fresh()->hasVideo())->toBeTrue();
});

test('it returns notification data from api notifications endpoint', function () {
    $response = $this->actingAs($this->user)->getJson(route('api.notifications'));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'status',
        'total_badge',
        'pending_count',
        'pending_orders',
        'cancellations_count',
        'cancellations',
        'low_stock_count',
    ]);
});

test('it auto cleans abandoned in_progress upload records older than 3 minutes', function () {
    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-STALE-TEST-008',
        'tracking_number' => 'RESI-STALE-888',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 100000,
        'buyer_username' => 'buyer_stale_test',
    ]);

    $staleRecord = PackingRecord::create([
        'order_sn' => 'ORD-STALE-TEST-008',
        'tracking_number' => 'RESI-STALE-888',
        'status' => 'in_progress',
        'packer_name' => 'Staff Inactive',
        'video_path' => null,
    ]);
    $staleRecord->timestamps = false;
    $staleRecord->created_at = now()->subMinutes(5);
    $staleRecord->save();

    // Check packing: should delete the 5-minute-old abandoned draft
    $response = $this->actingAs($this->user)->postJson(route('packing.check'), [
        'query' => 'RESI-STALE-888',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'ready',
        'already_packed' => false,
        'is_uploading' => false,
    ]);

    expect(PackingRecord::find($staleRecord->id))->toBeNull();
});

test('it returns already_completed in initUpload if video record already exists on disk', function () {
    Storage::fake('public');

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-EXISTING-DONE-009',
        'tracking_number' => 'RESI-EXISTING-999',
        'order_status' => 'PROCESSED',
        'total_amount' => 150000,
        'buyer_username' => 'buyer_existing_done',
    ]);

    // Create fake video file on disk
    $fakePath = 'packing_videos/pack_ORD-EXISTING-DONE-009_test.webm';
    Storage::disk('public')->put($fakePath, 'fake video content');

    $completedRecord = PackingRecord::create([
        'order_sn' => 'ORD-EXISTING-DONE-009',
        'tracking_number' => 'RESI-EXISTING-999',
        'shopee_order_id' => $order->id,
        'status' => 'completed',
        'video_path' => $fakePath,
        'video_duration' => 18,
        'packer_name' => 'Doni Packer',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('packing.initUpload'), [
        'order_sn' => 'ORD-EXISTING-DONE-009',
        'tracking_number' => 'RESI-EXISTING-999',
        'packer_name' => 'Doni Packer',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'already_completed',
        'order_sn' => 'ORD-EXISTING-DONE-009',
        'tracking_number' => 'RESI-EXISTING-999',
        'video_url' => $completedRecord->video_url,
    ]);
});

test('it returns both video_url and data array on successful upload', function () {
    Storage::fake('public');

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-PAYLOAD-TEST-010',
        'tracking_number' => 'RESI-PAYLOAD-010',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 85000,
        'buyer_username' => 'buyer_payload_test',
    ]);

    $dummyVideo = UploadedFile::fake()->create('packing_sample.webm', 512, 'video/webm');

    $response = $this->actingAs($this->user)->postJson(route('packing.upload'), [
        'order_sn' => 'ORD-PAYLOAD-TEST-010',
        'tracking_number' => 'RESI-PAYLOAD-010',
        'video' => $dummyVideo,
        'duration' => 10,
    ]);

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['status'])->toBe('success');
    expect($data)->toHaveKey('video_url');
    expect($data)->toHaveKey('data');
    expect($data['data']['video_url'])->toBe($data['video_url']);
});

test('it allows re-packing when force_new is true even if completed record exists', function () {
    Storage::fake('public');

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-FORCE-NEW-011',
        'tracking_number' => 'RESI-FORCE-NEW-011',
        'order_status' => 'PROCESSED',
        'total_amount' => 150000,
        'buyer_username' => 'buyer_force_new',
    ]);

    $fakePath = 'packing_videos/pack_ORD-FORCE-NEW-011_test.webm';
    Storage::disk('public')->put($fakePath, 'fake video content');

    PackingRecord::create([
        'order_sn' => 'ORD-FORCE-NEW-011',
        'tracking_number' => 'RESI-FORCE-NEW-011',
        'shopee_order_id' => $order->id,
        'status' => 'completed',
        'video_path' => $fakePath,
        'video_duration' => 18,
        'packer_name' => 'Doni Packer',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('packing.initUpload'), [
        'order_sn' => 'ORD-FORCE-NEW-011',
        'tracking_number' => 'RESI-FORCE-NEW-011',
        'packer_name' => 'Doni Packer',
        'force_new' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'success',
        'order_sn' => 'ORD-FORCE-NEW-011',
    ]);
});

test('it handles video upload on real disk without finfo crashing', function () {
    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-REAL-DISK-012',
        'tracking_number' => 'RESI-REAL-DISK-012',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 99000,
        'buyer_username' => 'buyer_real_disk',
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'pest_vid');
    file_put_contents($tmp, 'real disk video test content');
    $realVideo = new UploadedFile($tmp, 'real_video.webm', 'video/webm', null, true);

    $response = $this->actingAs($this->user)->postJson(route('packing.upload'), [
        'order_sn' => 'ORD-REAL-DISK-012',
        'tracking_number' => 'RESI-REAL-DISK-012',
        'video' => $realVideo,
        'duration' => 12,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);

    $record = PackingRecord::where('order_sn', 'ORD-REAL-DISK-012')->first();
    expect($record)->not->toBeNull();
    expect(file_exists(storage_path('app/public/'.$record->video_path)))->toBeTrue();

    // Clean up
    if ($record->video_path && file_exists(storage_path('app/public/'.$record->video_path))) {
        unlink(storage_path('app/public/'.$record->video_path));
    }
});

test('it auto repairs webm video with offset ebml header on upload', function () {
    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-EBML-OFFSET-013',
        'tracking_number' => 'RESI-EBML-OFFSET-013',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 120000,
        'buyer_username' => 'buyer_ebml_test',
    ]);

    // Simulate offset EBML header: 100 bytes of garbage prefix followed by EBML header
    $garbage = str_repeat("\x00\xFF", 50);
    $ebmlHeader = "\x1a\x45\xdf\xa3\x9f\x42\x86\x81\x01\x42\xf7\x81\x01\x42\xf2\x81\x04\x42\xf3\x81\x08\x42\x82\x84webm";
    $payload = $garbage.$ebmlHeader.str_repeat("\x1f\x43\xb6\x75", 10);

    $tmp = tempnam(sys_get_temp_dir(), 'pest_ebml');
    file_put_contents($tmp, $payload);
    $corruptVideo = new UploadedFile($tmp, 'corrupt_video.webm', 'video/webm', null, true);

    $response = $this->actingAs($this->user)->postJson(route('packing.upload'), [
        'order_sn' => 'ORD-EBML-OFFSET-013',
        'tracking_number' => 'RESI-EBML-OFFSET-013',
        'video' => $corruptVideo,
        'duration' => 15,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'success']);

    $record = PackingRecord::where('order_sn', 'ORD-EBML-OFFSET-013')->first();
    expect($record)->not->toBeNull();
    $fullPath = storage_path('app/public/'.$record->video_path);
    expect(file_exists($fullPath))->toBeTrue();

    // Verify the saved file has the sanitized header or is converted
    $content = file_get_contents($fullPath);
    if (str_ends_with($record->video_path, '.webm')) {
        expect(str_starts_with($content, "\x1a\x45\xdf\xa3"))->toBeTrue();
    } else {
        expect(str_ends_with($record->video_path, '.mp4'))->toBeTrue();
    }

    // Clean up
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
});
