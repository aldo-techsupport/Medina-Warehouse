<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('login page is accessible to guests', function () {
    $this->get(route('login'))->assertStatus(200);
});

test('users can authenticate with valid credentials', function () {
    $response = $this->post(route('login.post'), [
        'username' => 'admin',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('users cannot authenticate with invalid password', function () {
    $this->post(route('login.post'), [
        'username' => 'admin',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('inactive users are blocked from logging in', function () {
    $inactiveRole = Role::where('slug', 'admin_gudang')->first();
    User::create([
        'name' => 'Inactive User',
        'username' => 'inactive_user',
        'email' => 'inactive@medina.com',
        'password' => Hash::make('password'),
        'role_id' => $inactiveRole->id,
        'status' => 'inactive',
    ]);

    $this->post(route('login.post'), [
        'username' => 'inactive_user',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('packer role cannot access warehouse products or mutations and receives 403', function () {
    $packer = User::where('email', 'packer@medina.com')->first();

    $response = $this->actingAs($packer)->get(route('warehouse.products'));
    $response->assertStatus(403);

    $response = $this->actingAs($packer)->get(route('warehouse.mutations'));
    $response->assertStatus(403);
});

test('packer role can access packing station and history', function () {
    $packer = User::where('email', 'packer@medina.com')->first();

    $this->actingAs($packer)->get(route('packing.index'))->assertStatus(200);
    $this->actingAs($packer)->get(route('packing.history'))->assertStatus(200);
});

test('super admin can access all menus including role and user management', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200);
    $this->actingAs($admin)->get(route('roles.index'))->assertStatus(200);
    $this->actingAs($admin)->get(route('users.index'))->assertStatus(200);
    $this->actingAs($admin)->get(route('warehouse.products'))->assertStatus(200);
    $this->actingAs($admin)->get(route('shopee.dashboard'))->assertStatus(200);
});

test('super admin can create and update roles with custom menu permissions', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $response = $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'Supervisor Stock',
        'slug' => 'supervisor_stock',
        'description' => 'Khusus mengurus stok gudang',
        'permissions' => ['dashboard', 'warehouse_products'],
    ]);

    $response->assertRedirect(route('roles.index'));
    $role = Role::where('slug', 'supervisor_stock')->first();
    expect($role)->not->toBeNull();
    expect($role->hasPermission('warehouse_products'))->toBeTrue();
    expect($role->hasPermission('packing_station'))->toBeFalse();
});

test('user can log out successfully', function () {
    $admin = User::where('email', 'admin@medina.com')->first();

    $response = $this->actingAs($admin)->post(route('logout'));
    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
