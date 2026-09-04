<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopeeController extends Controller
{
    protected ShopeeService $shopeeService;

    protected StockMutationService $mutationService;

    public function __construct(ShopeeService $shopeeService, StockMutationService $mutationService)
    {
        $this->shopeeService = $shopeeService;
        $this->mutationService = $mutationService;
    }

    /**
     * Shopee Dashboard.
     */
    public function dashboard()
    {
        $setting = ShopeeSetting::current();
        $connectedProducts = Product::whereNotNull('shopee_item_id')->orderBy('name')->get();
        $unconnectedProducts = Product::whereNull('shopee_item_id')->orderBy('name')->get();

        $totalOrders = ShopeeOrder::count();
        $totalSales = ShopeeOrder::sum('total_amount');
        $readyToShipCount = ShopeeOrder::where('order_status', 'READY_TO_SHIP')->count();
        $recentOrders = ShopeeOrder::latest()->take(6)->get();

        $authUrl = '';
        if (! empty($setting->partner_id) && ! empty($setting->partner_key)) {
            $redirectUrl = route('shopee.callback');
            $authUrl = $this->shopeeService->getAuthPartnerUrl($redirectUrl);
        }

        return view('shopee.dashboard', compact(
            'setting',
            'connectedProducts',
            'unconnectedProducts',
            'totalOrders',
            'totalSales',
            'readyToShipCount',
            'recentOrders',
            'authUrl'
        ));
    }

    /**
     * Shopee Orders List.
     */
    public function orders(Request $request)
    {
        $query = ShopeeOrder::query();

        if ($request->filled('status')) {
            $query->where('order_status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_sn', 'like', "%{$search}%")
                    ->orWhere('buyer_username', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $setting = ShopeeSetting::current();

        return view('shopee.orders', compact('orders', 'setting'));
    }

    /**
     * Shopee API Settings Page.
     */
    public function settings()
    {
        $setting = ShopeeSetting::current();
        $callbackUrl = route('shopee.callback');
        $webhookUrl = route('shopee.webhook');

        $authUrl = '';
        if (! empty($setting->partner_id) && ! empty($setting->partner_key)) {
            $authUrl = $this->shopeeService->getAuthPartnerUrl($callbackUrl);
        }

        return view('shopee.settings', compact('setting', 'callbackUrl', 'webhookUrl', 'authUrl'));
    }

    /**
     * Update Shopee Settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'nullable|numeric',
            'partner_key' => 'nullable|string|max:255',
            'shop_id' => 'nullable|numeric',
            'shop_name' => 'nullable|string|max:255',
            'environment' => 'required|in:sandbox,production',
            'auto_sync_stock' => 'nullable|boolean',
        ]);

        $setting = ShopeeSetting::current();
        $setting->update([
            'partner_id' => $validated['partner_id'] ?? null,
            'partner_key' => $validated['partner_key'] ?? null,
            'shop_id' => $validated['shop_id'] ?? null,
            'shop_name' => $validated['shop_name'] ?: 'Medina Official Store',
            'environment' => $validated['environment'],
            'auto_sync_stock' => $request->boolean('auto_sync_stock'),
        ]);

        return redirect()->route('shopee.settings')->with('success', 'Konfigurasi Shopee Open API berhasil disimpan!');
    }

    /**
     * OAuth Callback from Shopee.
     */
    public function handleCallback(Request $request)
    {
        $code = $request->input('code');
        $shopId = (int) ($request->input('shop_id') ?? ShopeeSetting::current()->shop_id);

        if (! $code) {
            return redirect()->route('shopee.settings')->with('error', 'Otorisasi Shopee gagal: Kode otorisasi tidak diterima.');
        }

        $result = $this->shopeeService->getAccessToken($code, $shopId);

        if ($result['success']) {
            return redirect()->route('shopee.dashboard')->with('success', 'Selamat! Toko Shopee berhasil terhubung.');
        }

        return redirect()->route('shopee.settings')->with('error', 'Gagal menukar token: '.($result['error'] ?? 'Unknown error'));
    }

    /**
     * Map Warehouse Product to Shopee Item ID.
     */
    public function mapProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'shopee_item_id' => 'required|numeric',
            'shopee_model_id' => 'nullable|numeric',
        ]);

        $product->update([
            'shopee_item_id' => $validated['shopee_item_id'],
            'shopee_model_id' => $validated['shopee_model_id'] ?? null,
            'shopee_stock' => $product->stock,
        ]);

        // Push current stock to Shopee
        $this->shopeeService->updateStock(
            (int) $product->shopee_item_id,
            $product->stock,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );

        return redirect()->back()->with('success', "Produk [{$product->sku}] {$product->name} berhasil dipetakan ke Shopee Item ID #{$product->shopee_item_id}!");
    }

    /**
     * Unlink product from Shopee.
     */
    public function unmapProduct(Product $product)
    {
        $product->update([
            'shopee_item_id' => null,
            'shopee_model_id' => null,
            'shopee_stock' => 0,
        ]);

        return redirect()->back()->with('success', "Produk {$product->name} telah dilepas dari Shopee.");
    }

    /**
     * Sync single product stock to Shopee.
     */
    public function syncProductStock(Product $product)
    {
        if (! $product->isConnectedToShopee()) {
            return redirect()->back()->with('error', "Produk {$product->name} belum terhubung ke Shopee.");
        }

        $result = $this->shopeeService->updateStock(
            (int) $product->shopee_item_id,
            $product->stock,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );

        $product->update(['shopee_stock' => $product->stock]);

        return redirect()->back()->with('success', "Stok produk {$product->name} ({$product->stock} unit) berhasil disinkronkan ke Shopee!");
    }

    /**
     * Sync all connected products stock to Shopee.
     */
    public function syncAllStock()
    {
        $products = Product::whereNotNull('shopee_item_id')->get();
        $synced = 0;

        foreach ($products as $product) {
            $this->shopeeService->updateStock(
                (int) $product->shopee_item_id,
                $product->stock,
                $product->shopee_model_id ? (int) $product->shopee_model_id : null
            );
            $product->update(['shopee_stock' => $product->stock]);
            $synced++;
        }

        return redirect()->back()->with('success', "Berhasil mensinkronkan {$synced} produk ke Shopee!");
    }

    /**
     * Webhook Endpoint for Shopee Push Notifications.
     * Decrements warehouse stock automatically when order is created/paid on Shopee.
     */
    public function handleWebhook(Request $request)
    {
        $signature = $request->header('Authorization', '');
        $rawContent = $request->getContent();
        $fullUrl = $request->fullUrl();

        // Verify HMAC signature
        if (! $this->shopeeService->verifyWebhookSignature($fullUrl, $rawContent, $signature)) {
            Log::warning('Shopee Webhook Signature Verification Failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();
        Log::info('Shopee Webhook Received', ['data' => $data]);

        $code = $data['code'] ?? null; // e.g. 3 = order status update
        $dataContent = $data['data'] ?? [];

        if (isset($dataContent['order_sn'])) {
            $orderSn = $dataContent['order_sn'];
            $status = $dataContent['status'] ?? 'READY_TO_SHIP';

            // Items can be passed in webhook or fetched
            $items = $dataContent['items'] ?? [];

            $this->mutationService->processShopeeOrder([
                'order_sn' => $orderSn,
                'order_status' => $status,
                'total_amount' => $dataContent['total_amount'] ?? 0,
                'buyer_username' => $dataContent['buyer_username'] ?? 'Shopee Customer',
                'items' => $items,
            ]);
        }

        return response()->json(['code' => 0, 'message' => 'success']);
    }

    /**
     * Order Simulation Tool (For instant testing without real Shopee orders).
     */
    public function simulateOrder(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'buyer_username' => 'nullable|string|max:100',
            'order_status' => 'required|in:READY_TO_SHIP,PROCESSED,COMPLETED',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $qty = (int) $validated['qty'];
        $buyer = $validated['buyer_username'] ?: 'pembeli_shopee_'.rand(100, 999);
        $orderSn = '26'.date('md').strtoupper(Str::random(8));

        $orderData = [
            'order_sn' => $orderSn,
            'shop_id' => ShopeeSetting::current()->shop_id ?? 12345678,
            'order_status' => $validated['order_status'],
            'total_amount' => $product->selling_price * $qty,
            'buyer_username' => $buyer,
            'items' => [
                [
                    'item_id' => $product->shopee_item_id ?? 99999999,
                    'item_sku' => $product->sku,
                    'item_name' => $product->name,
                    'model_quantity_purchased' => $qty,
                    'model_discounted_price' => $product->selling_price,
                ],
            ],
        ];

        try {
            $order = $this->mutationService->processShopeeOrder($orderData);

            return redirect()->back()->with('success', "Simulasi Berhasil! Pesanan Shopee #{$orderSn} diterima. Stok [{$product->sku}] {$product->name} otomatis berkurang {$qty} {$product->unit} di Gudang Utama.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Gagal memproses simulasi: '.$e->getMessage());
        }
    }

    /**
     * Pull recent orders directly from Shopee Open API v2.
     */
    public function pullOrders(Request $request)
    {
        $days = (int) $request->input('days', 3);
        $days = min(15, max(1, $days));

        $result = $this->shopeeService->pullAndSyncOrders($days, $this->mutationService);

        if ($result['success']) {
            $count = $result['synced_count'] ?? 0;
            $msg = $count > 0
                ? "Berhasil menarik & mensinkronkan {$count} pesanan terbaru dari Shopee!"
                : 'Pengecekan selesai: Tidak ada pesanan Shopee baru dalam rentang waktu tersebut.';

            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', 'Gagal menarik pesanan dari Shopee: '.($result['error'] ?? 'Terjadi kesalahan sistem'));
    }

    /**
     * Fetch products from Shopee API for interactive product mapping.
     */
    public function fetchShopProducts(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $pageSize = min(50, (int) $request->input('page_size', 20));

        $result = $this->shopeeService->getItemList($offset, $pageSize);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Gagal mengambil produk dari Shopee',
            ], 400);
        }

        $items = $result['data']['item'] ?? [];
        $itemIds = array_column($items, 'item_id');

        $baseInfoResult = ! empty($itemIds) ? $this->shopeeService->getItemBaseInfo($itemIds) : ['success' => true, 'data' => ['item_list' => []]];

        return response()->json([
            'success' => true,
            'items' => $baseInfoResult['data']['item_list'] ?? [],
            'has_next_page' => $result['data']['has_next_page'] ?? false,
            'total_count' => $result['data']['total_count'] ?? count($items),
        ]);
    }
}
