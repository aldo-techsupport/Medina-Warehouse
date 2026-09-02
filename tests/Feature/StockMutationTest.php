<?php

use App\Models\Product;
use App\Services\StockMutationService;

beforeEach(function () {
    $this->mutationService = new StockMutationService;
});

test('it can record inbound stock mutation and increase product stock', function () {
    $product = Product::create([
        'sku' => 'TEST-SKU-INB',
        'name' => 'Test Product Inbound',
        'unit' => 'Pcs',
        'stock' => 10,
        'purchase_price' => 50000,
        'selling_price' => 75000,
    ]);

    $mutation = $this->mutationService->recordInbound($product, 15, 'Supplier Restock', 'Admin');

    expect($product->fresh()->stock)->toBe(25);
    expect($mutation->qty)->toBe(15);
    expect($mutation->stock_before)->toBe(10);
    expect($mutation->stock_after)->toBe(25);
    expect($mutation->type)->toBe('inbound');
});

test('it can record outbound stock mutation and decrease product stock', function () {
    $product = Product::create([
        'sku' => 'TEST-SKU-OUT',
        'name' => 'Test Product Outbound',
        'unit' => 'Pcs',
        'stock' => 20,
        'purchase_price' => 50000,
        'selling_price' => 75000,
    ]);

    $mutation = $this->mutationService->recordOutbound($product, 8, 'Manual Distribution', 'Staff');

    expect($product->fresh()->stock)->toBe(12);
    expect($mutation->qty)->toBe(-8);
    expect($mutation->stock_before)->toBe(20);
    expect($mutation->stock_after)->toBe(12);
    expect($mutation->type)->toBe('outbound');
});

test('it throws exception when outbound qty exceeds current stock', function () {
    $product = Product::create([
        'sku' => 'TEST-SKU-EXCEED',
        'name' => 'Test Product Exceed',
        'unit' => 'Pcs',
        'stock' => 5,
        'purchase_price' => 50000,
        'selling_price' => 75000,
    ]);

    $this->mutationService->recordOutbound($product, 10, 'Manual Distribution', 'Staff');
})->throws(Exception::class);

test('it can record adjustment stock opname', function () {
    $product = Product::create([
        'sku' => 'TEST-SKU-ADJ',
        'name' => 'Test Product Adjustment',
        'unit' => 'Pcs',
        'stock' => 15,
        'purchase_price' => 50000,
        'selling_price' => 75000,
    ]);

    $mutation = $this->mutationService->recordAdjustment($product, 22, 'Physical Audit Correction', 'Auditor');

    expect($product->fresh()->stock)->toBe(22);
    expect($mutation->stock_before)->toBe(15);
    expect($mutation->stock_after)->toBe(22);
    expect($mutation->qty)->toBe(7);
});
