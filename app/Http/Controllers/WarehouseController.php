<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMutation;
use App\Services\StockMutationService;
use Exception;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    protected StockMutationService $mutationService;

    public function __construct(StockMutationService $mutationService)
    {
        $this->mutationService = $mutationService;
    }

    /**
     * Products List & Inventory Catalog.
     */
    public function products(Request $request)
    {
        $query = Product::query();

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

        return view('warehouse.products.index', compact('products', 'categories'));
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
            'safety_stock' => 'nullable|integer|min:0',
            'shopee_item_id' => 'nullable|integer',
            'status' => 'required|string|in:active,inactive',
        ]);

        $product->update($validated);

        return redirect()->back()->with('success', "Produk {$product->name} berhasil diperbarui!");
    }

    /**
     * Delete product.
     */
    public function destroyProduct(Product $product)
    {
        $name = $product->name;
        $product->delete();

        return redirect()->back()->with('success', "Produk {$name} berhasil dihapus!");
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
        $actor = $validated['actor'] ?: 'Staff Gudang';
        $notes = $validated['notes'] ?: '';

        try {
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

            return redirect()->back()->with('success', $msg);
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
