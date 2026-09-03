<?php

use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guest cannot access AI advisor and is redirected to login', function () {
    $response = $this->get(route('ai.index'));
    $response->assertRedirect(route('login'));
});

test('user without ai_advisor permission receives 403 forbidden', function () {
    $packer = User::where('email', 'packer@medina.com')->first();

    $response = $this->actingAs($packer)->get(route('ai.index'));
    $response->assertStatus(403);
});

test('super admin can access AI advisor dashboard', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $response = $this->actingAs($admin)->get(route('ai.index'));
    $response->assertStatus(200);
    $response->assertSee('AI Seller & Analisis Penjualan');
    $response->assertSee('Chat Asisten Seller AI');
});

test('user can trigger AI sales analysis with mocked router response', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    config([
        'ai.api_key' => 'test-fake-key',
        'ai.base_url' => 'https://openrouter.ai/api/v1',
        'ai.model' => 'google/gemini-2.5-flash',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'model' => 'google/gemini-2.5-flash',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'summary' => 'Penjualan toko stabil dengan potensi pertumbuhan pada kategori gamis.',
                            'marketing_advice' => [
                                [
                                    'title' => 'Promo Diskon Gamis & Pashmina',
                                    'badge' => 'Bundling Shopee',
                                    'description' => 'Tingkatkan omzet dengan bundling.',
                                    'action' => 'Buat kombo hemat',
                                    'impact' => 'Naik 25%',
                                ],
                            ],
                            'inventory_advice' => [
                                [
                                    'title' => 'Restock Gamis Maroon',
                                    'priority' => 'Tinggi',
                                    'description' => 'Sisa stok menipis.',
                                    'recommendation' => 'Pesan 50 pcs',
                                ],
                            ],
                            'actionable_steps' => [
                                [
                                    'step' => 1,
                                    'task' => 'Hubungi penjahit gamis',
                                    'category' => 'Stok',
                                    'target_sku' => 'MDN-GMS-002',
                                ],
                            ],
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'total_tokens' => 350,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->postJson(route('ai.analyze'));
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('ai_analyses', [
        'user_id' => $admin->id,
        'model_used' => 'google/gemini-2.5-flash',
    ]);
});

test('ai chat endpoint processes seller question and persists history', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    config([
        'ai.api_key' => 'test-fake-key',
        'ai.base_url' => 'https://openrouter.ai/api/v1',
        'ai.model' => 'google/gemini-2.5-flash',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'model' => 'google/gemini-2.5-flash',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Rekomendasi terbaik untuk bundling adalah Gamis Silk dengan Hijab Pashmina Plisket.',
                    ],
                ],
            ],
            'usage' => [
                'total_tokens' => 150,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->postJson(route('ai.chat'), [
        'message' => 'Produk apa yang cocok untuk dibundling?',
        'session_id' => 'test-session-123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('reply', 'Rekomendasi terbaik untuk bundling adalah Gamis Silk dengan Hijab Pashmina Plisket.');

    // Assert user and assistant messages stored
    $this->assertDatabaseHas('ai_chat_messages', [
        'user_id' => $admin->id,
        'session_id' => 'test-session-123',
        'role' => 'user',
        'content' => 'Produk apa yang cocok untuk dibundling?',
    ]);

    $this->assertDatabaseHas('ai_chat_messages', [
        'user_id' => $admin->id,
        'session_id' => 'test-session-123',
        'role' => 'assistant',
        'content' => 'Rekomendasi terbaik untuk bundling adalah Gamis Silk dengan Hijab Pashmina Plisket.',
    ]);
});

test('ai chat can be cleared for the current user', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    AiChatMessage::create([
        'user_id' => $admin->id,
        'session_id' => 'to-clear-session',
        'role' => 'user',
        'content' => 'Pesan sementara',
    ]);

    $response = $this->actingAs($admin)->postJson(route('ai.chat.clear'), [
        'session_id' => 'to-clear-session',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $this->assertDatabaseMissing('ai_chat_messages', [
        'session_id' => 'to-clear-session',
    ]);
});

test('ai test connection endpoint returns connectivity status', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    config([
        'ai.api_key' => 'test-fake-key',
        'ai.base_url' => 'https://openrouter.ai/api/v1',
        'ai.model' => 'google/gemini-2.5-flash',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'model' => 'google/gemini-2.5-flash',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'ONLINE google/gemini-2.5-flash',
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->postJson(route('ai.test'));
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('model', 'google/gemini-2.5-flash');
});

test('ai chat works with smart local fallback when api key is not configured', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    config([
        'ai.api_key' => '',
    ]);

    $response = $this->actingAs($admin)->postJson(route('ai.chat'), [
        'message' => 'Produk mana yang perlu di restock?',
        'session_id' => 'local-fallback-session',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $this->assertStringContainsString('Daftar Produk yang Mendesak untuk Di-restock', $response->json('reply'));
});

test('super admin sees AI executive summary and actionable steps on main dashboard', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertStatus(200);
    $response->assertSee('AI Executive Summary', false);
    $response->assertSee('Langkah Prioritas Hari Ini');
    $response->assertSee('Checklist Aksi');
});
