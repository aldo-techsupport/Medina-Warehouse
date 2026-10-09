<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    protected StockMutationService $mutationService;

    protected ShopeeService $shopeeService;

    public function __construct(StockMutationService $mutationService, ShopeeService $shopeeService)
    {
        $this->mutationService = $mutationService;
        $this->shopeeService = $shopeeService;
    }

    /**
     * Products List & Inventory Catalog.
     */
    public function products(Request $request)
    {
        $setting = ShopeeSetting::current();
        if ($setting->isConnected()) {
            try {
                $this->shopeeService->syncLiveProductsIfStale(20, $this->mutationService, $request->boolean('refresh'));
            } catch (Exception $e) {
                Log::warning('Auto live sync products on load: '.$e->getMessage());
            }
        }

        $statusTab = $request->input('status', 'all');

        $countAll = Product::count();
        $countActive = Product::where('status', 'active')->count();
        $countInactive = Product::where('status', 'inactive')->count();

        $query = Product::query();

        if ($statusTab === 'active') {
            $query->where('status', 'active');
        } elseif ($statusTab === 'inactive') {
            $query->where('status', 'inactive');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('stock_status')) {
            if ($request->stock_status === 'low') {
                $query->whereColumn('stock', '<=', 'safety_stock');
            } elseif ($request->stock_status === 'out') {
                $query->where('stock', '<=', 0);
            }
        }

        $products = $query->orderBy('name')->paginate(15)->withQueryString();
        $categories = Product::select('category')->distinct()->pluck('category');

        return view('warehouse.products.index', compact(
            'products',
            'categories',
            'statusTab',
            'countAll',
            'countActive',
            'countInactive'
        ));
    }

    /**
     * Store new product.
     */
    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:products,sku',
            'barcode' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'initial_stock' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'shopee_item_id' => 'nullable|integer',
        ]);

        $initialStock = (int) ($validated['initial_stock'] ?? 0);
        unset($validated['initial_stock']);

        $product = Product::create([
            ...$validated,
            'stock' => 0,
            'safety_stock' => $validated['safety_stock'] ?? 5,
            'category' => $validated['category'] ?: 'Umum',
        ]);

        if ($initialStock > 0) {
            $this->mutationService->recordInbound(
                $product,
                $initialStock,
                'Stok Awal Pembuatan Produk',
                'Admin'
            );
        }

        // Live push to Shopee API if connected
        if ($product->isConnectedToShopee()) {
            try {
                $this->shopeeService->updateStock((int) $product->shopee_item_id, $product->stock);
                $this->shopeeService->updatePrice((int) $product->shopee_item_id, (float) $product->selling_price);
            } catch (Exception $e) {
                Log::warning('Live push to Shopee on product create failed: '.$e->getMessage());
            }
        }

        return redirect()->back()->with('success', "Produk [{$product->sku}] {$product->name} berhasil ditambahkan!");
    }

    /**
     * Update product.
     */
    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => "required|string|max:50|unique:products,sku,{$product->id}",
            'barcode' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'shopee_item_id' => 'nullable|integer',
            'status' => 'required|string|in:active,inactive',
        ]);

        $newStock = isset($validated['stock']) ? (int) $validated['stock'] : null;
        unset($validated['stock']);

        $oldStock = $product->stock;
        $oldPrice = (float) $product->selling_price;
        $oldName = $product->name;
        $oldStatus = $product->status;

        $product->update($validated);

        $shopeeSyncNotes = [];

        if ($product->isConnectedToShopee()) {
            // 1. Live Sync Stok jika diedit
            if ($newStock !== null && $newStock !== $oldStock) {
                $this->mutationService->recordAdjustment(
                    $product,
                    $newStock,
                    'Penyesuaian stok dari Edit Produk',
                    auth()->user()?->name ?? 'Admin'
                );
                $product->refresh();
                $shopeeSyncNotes[] = "Stok: {$product->stock} unit";
            } else {
                $resStock = $this->shopeeService->updateStock(
                    (int) $product->shopee_item_id,
                    $product->stock,
                    $product->shopee_model_id ? (int) $product->shopee_model_id : null
                );
                if ($resStock['success']) {
                    $product->update(['shopee_stock' => $product->stock]);
                    $shopeeSyncNotes[] = "Stok: {$product->stock} unit";
                }
            }

            // 2. Live Sync Harga ke Shopee API
            $newPrice = (float) $validated['selling_price'];
            if ($newPrice !== $oldPrice || true) {
                $resPrice = $this->shopeeService->updatePrice(
                    (int) $product->shopee_item_id,
                    $newPrice,
                    $product->shopee_model_id ? (int) $product->shopee_model_id : null
                );
                if ($resPrice['success']) {
                    $shopeeSyncNotes[] = 'Harga Shopee: Rp '.number_format($newPrice, 0, ',', '.');
                } else {
                    Log::warning("Shopee live update price warning for {$product->sku}: ".($resPrice['error'] ?? ''));
                }
            }

            // 3. Live Sync Nama Produk ke Shopee API jika diedit
            if ($validated['name'] !== $oldName) {
                $resName = $this->shopeeService->updateItem((int) $product->shopee_item_id, [
                    'item_name' => $validated['name'],
                ]);
                if ($resName['success']) {
                    $shopeeSyncNotes[] = 'Nama di Shopee diperbarui';
                }
            }

            // 4. Live Sync Status Tampil (Active / Inactive -> Unlist di Shopee)
            if ($validated['status'] !== $oldStatus) {
                $isInactive = $validated['status'] === 'inactive';
                $resUnlist = $this->shopeeService->unlistProduct((int) $product->shopee_item_id, $isInactive);
                if ($resUnlist['success']) {
                    $shopeeSyncNotes[] = $isInactive ? 'Status: Diarsipkan di Shopee' : 'Status: Aktif di Shopee';
                }
            }
        }

        $shopeeMsg = ! empty($shopeeSyncNotes)
            ? ' (Live Shopee: '.implode(', ', $shopeeSyncNotes).')'
            : '';

        return redirect()->back()->with('success', "Produk {$product->name} berhasil diperbarui{$shopeeMsg}!");
    }

    /**
     * Delete product.
     */
    public function destroyProduct(Product $product)
    {
        $name = $product->name;

        // Jika terhubung ke Shopee, hapus produk di Shopee
        if ($product->isConnectedToShopee()) {
            try {
                $this->shopeeService->deleteProduct((int) $product->shopee_item_id);
            } catch (Exception $e) {
                Log::warning("Shopee live delete product warning for {$product->sku}: ".$e->getMessage());
            }
        }

        $product->delete();

        return redirect()->back()->with('success', "Produk {$name} berhasil dihapus dari gudang & Shopee!");
    }

    /**
     * Toggle product status (active <-> inactive) and sync to Shopee.
     */
    public function toggleProductStatus(Product $product)
    {
        $newStatus = $product->status === 'active' ? 'inactive' : 'active';
        $product->update(['status' => $newStatus]);

        $shopeeMsg = '';
        if ($product->isConnectedToShopee()) {
            $isInactive = $newStatus === 'inactive';
            $res = $this->shopeeService->unlistProduct((int) $product->shopee_item_id, $isInactive);
            if ($res['success']) {
                $shopeeMsg = $isInactive ? ' (Diarsipkan di Shopee)' : ' (Diaktifkan kembali di Shopee)';
            }
        }

        $label = $newStatus === 'active' ? 'diaktifkan kembali' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Produk [{$product->sku}] {$product->name} berhasil {$label}{$shopeeMsg}!");
    }

    /**
     * Process Stock Mutation (Inbound / Outbound / Adjustment).
     */
    public function storeMutation(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:inbound,outbound,adjustment',
            'qty' => 'required|integer',
            'notes' => 'nullable|string|max:255',
            'actor' => 'nullable|string|max:100',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $actor = ! empty($validated['actor']) ? $validated['actor'] : (auth()->user()?->name ?? 'Staff Gudang');
        $notes = $validated['notes'] ?? '';

        try {
            $shopeeMsg = '';
            if ($validated['type'] === 'inbound') {
                $this->mutationService->recordInbound($product, (int) $validated['qty'], $notes, $actor);
                $msg = "Berhasil mencatat Barang Masuk (+{$validated['qty']} {$product->unit}) untuk {$product->name}";
            } elseif ($validated['type'] === 'outbound') {
                $this->mutationService->recordOutbound($product, (int) $validated['qty'], $notes, $actor);
                $msg = "Berhasil mencatat Barang Keluar (-{$validated['qty']} {$product->unit}) untuk {$product->name}";
            } elseif ($validated['type'] === 'adjustment') {
                $this->mutationService->recordAdjustment($product, (int) $validated['qty'], $notes, $actor);
                $msg = "Berhasil menyesuaikan Stok Opname ({$product->name} kini: {$validated['qty']} {$product->unit})";
            }

            $product->refresh();
            if ($product->isConnectedToShopee()) {
                $shopeeMsg = " & stok di Shopee API terupdate: {$product->stock} unit";
            }

            return redirect()->back()->with('success', $msg.$shopeeMsg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Stock Mutations History Page.
     */
    public function mutations(Request $request)
    {
        $query = StockMutation::with('product');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $mutations = $query->latest()->paginate(20)->withQueryString();
        $products = Product::orderBy('name')->get();

        return view('warehouse.mutations.index', compact('mutations', 'products'));
    }
}
