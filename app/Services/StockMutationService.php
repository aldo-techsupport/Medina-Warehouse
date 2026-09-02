<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StockMutationService
{
    protected ShopeeService $shopeeService;

    public function __construct(?ShopeeService $shopeeService = null)
    {
        $this->shopeeService = $shopeeService ?? new ShopeeService;
    }

    /**
     * Record Inbound Mutation (Barang Masuk / Pembelian / Penerimaan).
     */
    public function recordInbound(Product $product, int $qty, string $notes = '', string $actor = 'Admin'): StockMutation
    {
        if ($qty <= 0) {
            throw new Exception('Jumlah barang masuk harus lebih besar dari 0');
        }

        return DB::transaction(function () use ($product, $qty, $notes, $actor) {
            $product = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();
            $stockBefore = $product->stock;
            $stockAfter = $stockBefore + $qty;

            $product->update([
                'stock' => $stockAfter,
                'shopee_stock' => $product->isConnectedToShopee() ? $stockAfter : $product->shopee_stock,
            ]);

            $mutation = StockMutation::create([
                'reference_no' => 'INB-'.date('Ymd').'-'.strtoupper(Str::random(5)),
                'product_id' => $product->id,
                'type' => 'inbound',
                'qty' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'notes' => $notes ?: 'Penerimaan barang masuk ke gudang utama',
                'actor' => $actor,
            ]);

            $this->syncStockToShopeeIfEnabled($product, $stockAfter);

            return $mutation;
        });
    }

    /**
     * Record Outbound Mutation (Barang Keluar Manual / Distribusi).
     */
    public function recordOutbound(Product $product, int $qty, string $notes = '', string $actor = 'Admin'): StockMutation
    {
        if ($qty <= 0) {
            throw new Exception('Jumlah barang keluar harus lebih besar dari 0');
        }

        return DB::transaction(function () use ($product, $qty, $notes, $actor) {
            $product = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();
            if ($product->stock < $qty) {
                throw new Exception("Stok tidak mencukupi! Stok saat ini: {$product->stock}, permintaan: {$qty}");
            }

            $stockBefore = $product->stock;
            $stockAfter = $stockBefore - $qty;

            $product->update([
                'stock' => $stockAfter,
                'shopee_stock' => $product->isConnectedToShopee() ? $stockAfter : $product->shopee_stock,
            ]);

            $mutation = StockMutation::create([
                'reference_no' => 'OUT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
                'product_id' => $product->id,
                'type' => 'outbound',
                'qty' => -$qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'notes' => $notes ?: 'Pengeluaran barang manual dari gudang utama',
                'actor' => $actor,
            ]);

            $this->syncStockToShopeeIfEnabled($product, $stockAfter);

            return $mutation;
        });
    }

    /**
     * Record Adjustment (Stok Opname / Penyesuaian Fisik).
     */
    public function recordAdjustment(Product $product, int $newStock, string $notes = '', string $actor = 'Admin'): StockMutation
    {
        if ($newStock < 0) {
            throw new Exception('Stok hasil penyesuaian tidak boleh negatif');
        }

        return DB::transaction(function () use ($product, $newStock, $notes, $actor) {
            $product = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();
            $stockBefore = $product->stock;
            $diff = $newStock - $stockBefore;

            $product->update([
                'stock' => $newStock,
                'shopee_stock' => $product->isConnectedToShopee() ? $newStock : $product->shopee_stock,
            ]);

            $mutation = StockMutation::create([
                'reference_no' => 'ADJ-'.date('Ymd').'-'.strtoupper(Str::random(5)),
                'product_id' => $product->id,
                'type' => 'adjustment',
                'qty' => $diff,
                'stock_before' => $stockBefore,
                'stock_after' => $newStock,
                'notes' => $notes ?: 'Penyesuaian stok opname gudang utama',
                'actor' => $actor,
            ]);

            $this->syncStockToShopeeIfEnabled($product, $newStock);

            return $mutation;
        });
    }

    /**
     * Process Shopee Order: Automatically deduct warehouse stock and record mutation.
     */
    public function processShopeeOrder(array $orderData): ShopeeOrder
    {
        return DB::transaction(function () use ($orderData) {
            $orderSn = $orderData['order_sn'];
            $items = $orderData['items'] ?? [];
            $shopId = $orderData['shop_id'] ?? ShopeeSetting::current()->shop_id;
            $buyer = $orderData['buyer_username'] ?? 'Shopee Customer';
            $totalAmount = $orderData['total_amount'] ?? 0;
            $status = $orderData['order_status'] ?? 'READY_TO_SHIP';

            $order = ShopeeOrder::firstOrNew(['order_sn' => $orderSn]);
            $isAlreadyDeducted = $order->exists && $order->stock_deducted;

            $order->fill([
                'shop_id' => $shopId,
                'order_status' => $status,
                'total_amount' => $totalAmount,
                'buyer_username' => $buyer,
                'items_data' => $items,
            ]);

            // Deduct stock if not already deducted and status is valid
            if (! $isAlreadyDeducted && in_array($status, ['READY_TO_SHIP', 'PROCESSED', 'COMPLETED'])) {
                foreach ($items as $item) {
                    $itemSku = $item['item_sku'] ?? null;
                    $itemId = $item['item_id'] ?? null;
                    $modelId = $item['model_id'] ?? null;
                    $qty = (int) ($item['model_quantity_purchased'] ?? $item['qty'] ?? 1);

                    // Find warehouse product by SKU or Shopee Item ID
                    $product = null;
                    if ($itemSku) {
                        $product = Product::where('sku', $itemSku)->lockForUpdate()->first();
                    }
                    if (! $product && $itemId) {
                        $product = Product::where('shopee_item_id', $itemId)->lockForUpdate()->first();
                    }

                    if ($product) {
                        $stockBefore = $product->stock;
                        $stockAfter = max(0, $stockBefore - $qty);

                        $product->update([
                            'stock' => $stockAfter,
                            'shopee_stock' => $stockAfter,
                        ]);

                        StockMutation::create([
                            'reference_no' => 'SHP-'.$orderSn,
                            'product_id' => $product->id,
                            'type' => 'shopee_sale',
                            'qty' => -$qty,
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                            'notes' => "Pesanan Shopee #{$orderSn} (".($item['item_name'] ?? $product->name)." x{$qty})",
                            'actor' => 'Shopee Webhook / Sync',
                        ]);
                    }
                }

                $order->stock_deducted = true;
                $order->stock_deducted_at = now();
            }

            // Handle cancellation (restock if previously deducted)
            if ($isAlreadyDeducted && in_array($status, ['CANCELLED', 'IN_CANCEL'])) {
                foreach ($items as $item) {
                    $itemSku = $item['item_sku'] ?? null;
                    $itemId = $item['item_id'] ?? null;
                    $qty = (int) ($item['model_quantity_purchased'] ?? $item['qty'] ?? 1);

                    $product = null;
                    if ($itemSku) {
                        $product = Product::where('sku', $itemSku)->lockForUpdate()->first();
                    }
                    if (! $product && $itemId) {
                        $product = Product::where('shopee_item_id', $itemId)->lockForUpdate()->first();
                    }

                    if ($product) {
                        $stockBefore = $product->stock;
                        $stockAfter = $stockBefore + $qty;

                        $product->update([
                            'stock' => $stockAfter,
                            'shopee_stock' => $stockAfter,
                        ]);

                        StockMutation::create([
                            'reference_no' => 'RET-'.$orderSn,
                            'product_id' => $product->id,
                            'type' => 'shopee_cancellation',
                            'qty' => $qty,
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                            'notes' => "Restock pembatalan pesanan Shopee #{$orderSn}",
                            'actor' => 'Shopee Cancellation Sync',
                        ]);
                    }
                }

                $order->stock_deducted = false;
                $order->stock_deducted_at = null;
            }

            $order->save();

            return $order;
        });
    }

    /**
     * Push updated stock to Shopee if product is connected and auto_sync is enabled.
     */
    protected function syncStockToShopeeIfEnabled(Product $product, int $newStock): void
    {
        $setting = ShopeeSetting::current();
        if ($setting->auto_sync_stock && $product->isConnectedToShopee()) {
            try {
                $this->shopeeService->updateStock(
                    (int) $product->shopee_item_id,
                    $newStock,
                    $product->shopee_model_id ? (int) $product->shopee_model_id : null
                );
            } catch (Exception $e) {
                Log::warning("Failed to push stock to Shopee for SKU {$product->sku}: ".$e->getMessage());
            }
        }
    }
}
