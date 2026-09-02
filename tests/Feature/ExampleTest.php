<?php

use App\Models\User;

test('unauthenticated users are redirected to login', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated user can view dashboard', function () {
    $user = User::where('email', 'admin@medina.com')->first();
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200);
});
