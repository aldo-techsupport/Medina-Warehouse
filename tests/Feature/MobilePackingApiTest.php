<?php

use App\Models\PackingRecord;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::where('username', 'packer')->first() ?: User::first();
});

test('mobile api ping and system status returns 200 with server info', function () {
    $pingResponse = $this->getJson('/api/v1/ping');
    $pingResponse->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'code' => 200,
        ]);

    $statusResponse = $this->getJson('/api/v1/status');
    $statusResponse->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'api_version',
            'local_server_urls',
            'video_config' => [
                'max_file_size_bytes',
                'recommended_resolution',
                'supported_formats',
            ],
        ]);
});

test('mobile api auth login succeeds with correct credentials and returns bearer token', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'packer',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'token',
            'token_type',
            'user' => ['id', 'name', 'username', 'role'],
        ]);

    $token = $response->json('token');
    expect($token)->not->toBeEmpty();

    // Verify /api/v1/auth/me works with this Bearer token
    $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me');

    $meResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'user' => [
                'username' => 'packer',
            ],
        ]);

    // Verify logout revokes token
    $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $logoutResponse->assertStatus(200);

    // Verify subsequent call with revoked token is rejected
    $recheck = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me');

    $recheck->assertStatus(401);
});

test('mobile api login rejects invalid password', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'packer',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'status' => 'error',
            'code' => 401,
        ]);
});

test('mobile api packers list returns active operators', function () {
    $response = $this->getJson('/api/v1/auth/packers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'name', 'username', 'role_name'],
            ],
        ]);
});

test('mobile api scan blocks cancelled orders and creates blocked packing record', function () {
    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-M-CANCEL-001',
        'tracking_number' => 'SPXID-M-CANCEL-001',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'CANCELLED',
        'total_amount' => 125000,
        'buyer_username' => 'cancel_buyer',
        'items_data' => [
            ['item_name' => 'Baju Koko Modern', 'item_sku' => 'KOKO-001', 'model_quantity_purchased' => 1],
        ],
    ]);

    $response = $this->postJson('/api/v1/scanner/scan', [
        'query' => 'SPXID-M-CANCEL-001',
        'packer_name' => 'Mobile Operator 1',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'blocked_cancelled',
            'order_sn' => 'ORD-M-CANCEL-001',
            'tracking_number' => 'SPXID-M-CANCEL-001',
        ]);

    $record = PackingRecord::where('order_sn', 'ORD-M-CANCEL-001')->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('blocked_cancelled');
    expect($record->packer_name)->toBe('Mobile Operator 1');
});

test('mobile api scan returns order details and items checklist for ready order', function () {
    $product = Product::create([
        'sku' => 'SKU-MOB-001',
        'name' => 'Gamis Syari Medina',
        'unit' => 'Pcs',
        'stock' => 15,
        'purchase_price' => 80000,
        'selling_price' => 175000,
    ]);

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-M-VALID-002',
        'tracking_number' => 'SPXID-M-VALID-002',
        'shipping_carrier' => 'SPX Express',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 175000,
        'buyer_username' => 'mobile_buyer_2',
        'items_data' => [
            [
                'item_name' => 'Gamis Syari Medina',
                'item_sku' => 'SKU-MOB-001',
                'model_quantity_purchased' => 1,
                'model_discounted_price' => 175000,
            ],
        ],
    ]);

    $response = $this->postJson('/api/v1/scanner/scan', [
        'query' => 'SPXID-M-VALID-002',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ready',
            'order_sn' => 'ORD-M-VALID-002',
            'tracking_number' => 'SPXID-M-VALID-002',
        ]);

    $items = $response->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0]['name'])->toBe('Gamis Syari Medina');
    expect($items[0]['sku'])->toBe('SKU-MOB-001');
});

test('mobile api upload video stores packing video and updates status', function () {
    Storage::fake('public');

    $order = ShopeeOrder::create([
        'order_sn' => 'ORD-M-UPLOAD-003',
        'tracking_number' => 'SPXID-M-UPLOAD-003',
        'order_status' => 'READY_TO_SHIP',
        'total_amount' => 99000,
        'buyer_username' => 'buyer_upload_3',
    ]);

    $fakeVideo = UploadedFile::fake()->create('packing_video.mp4', 1024, 'video/mp4');

    $response = $this->postJson('/api/v1/packing/upload-video', [
        'order_sn' => 'ORD-M-UPLOAD-003',
        'tracking_number' => 'SPXID-M-UPLOAD-003',
        'video' => $fakeVideo,
        'duration' => 12,
        'packer_name' => 'Siti Mobile Packer',
        'items_checked' => json_encode([0]),
        'mode' => 'packing',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    expect($response->json('video_url'))->not->toBeNull();

    $record = PackingRecord::where('order_sn', 'ORD-M-UPLOAD-003')->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('completed');
    expect($record->packer_name)->toBe('Siti Mobile Packer');
    expect($record->video_duration)->toBe(12);

    // Verify order was transitioned to PROCESSED
    $order->refresh();
    expect($order->order_status)->toBe('PROCESSED');
});

test('mobile api packing history and stats return formatted data', function () {
    $statsResponse = $this->getJson('/api/v1/packing/stats');
    $statsResponse->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                'today_packed_count',
                'today_blocked_count',
                'recent_packed',
            ],
        ]);

    $historyResponse = $this->getJson('/api/v1/packing/history');
    $historyResponse->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data',
            'pagination' => ['current_page', 'last_page', 'total'],
        ]);

    $videosResponse = $this->getJson('/api/v1/packing/videos');
    $videosResponse->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data',
            'pagination' => ['current_page', 'last_page', 'total'],
        ]);

    $videosAliasResponse = $this->getJson('/api/v1/videos');
    $videosAliasResponse->assertStatus(200);
});

test('mobile api video streaming endpoint serves file with 200 or 206 partial content', function () {
    // Create a dummy video file
    $dummyFilename = 'pack_STREAM_TEST_'.time().'.mp4';
    $targetDir = storage_path('app/public/packing_videos');
    if (! is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir.'/'.$dummyFilename;
    file_put_contents($targetPath, str_repeat('A', 1024));

    $record = PackingRecord::create([
        'order_sn' => 'ORD-STREAM-001',
        'tracking_number' => 'SPX-STREAM-001',
        'status' => 'completed',
        'packer_name' => 'Tester Stream',
        'video_path' => 'packing_videos/'.$dummyFilename,
        'video_duration' => 10,
        'file_size' => 1024,
    ]);

    // Test full stream
    $streamResponse = $this->get('/api/v1/packing/records/'.$record->id.'/stream');
    $streamResponse->assertStatus(200);
    $streamResponse->assertHeader('Content-Type', 'video/mp4');
    $streamResponse->assertHeader('Accept-Ranges', 'bytes');

    // Test alias /api/v1/videos/{id}/stream
    $aliasStreamResponse = $this->get('/api/v1/videos/'.$record->id.'/stream');
    $aliasStreamResponse->assertStatus(200);

    // Test Range request
    $rangeResponse = $this->withHeader('Range', 'bytes=0-499')
        ->get('/api/v1/packing/records/'.$record->id.'/stream');
    $rangeResponse->assertStatus(206);
    $rangeResponse->assertHeader('Content-Range', 'bytes 0-499/1024');

    // Clean up dummy file
    @unlink($targetPath);
});

test('mobile api start-record and upload aliases work properly', function () {
    $startResponse = $this->postJson('/api/v1/packing/start-record', [
        'order_sn' => 'ORD-ALIAS-TEST-01',
        'tracking_number' => 'SPX-ALIAS-01',
        'packer_name' => 'Alias Packer',
    ]);
    $startResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'order_sn' => 'ORD-ALIAS-TEST-01',
        ]);
});

test('web mobile api documentation page is accessible to authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/mobile-api');

    $response->assertStatus(200);
    $response->assertSee('API &amp; Integrasi Mobile App', false);
    $response->assertSee('/api/v1/scanner/scan');
    $response->assertSee('/api/v1/packing/upload-video');
});

test('web mobile api can generate token for user', function () {
    $response = $this->actingAs($this->user)->postJson('/mobile-api/token', [
        'user_id' => $this->user->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    expect($response->json('token'))->not->toBeEmpty();
});

test('web mobile api postman collection downloads json file', function () {
    $response = $this->actingAs($this->user)->get('/mobile-api/postman');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/json');
});
