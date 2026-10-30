<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('unauthenticated users are redirected to login', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated user can view dashboard', function () {
    Http::fake([
        '*vendorarouter*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Ringkasan analisis stok gudang stabil.']],
            ],
        ], 200),
    ]);

    $user = User::where('email', 'admin@medina.com')->first();
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200);
});
