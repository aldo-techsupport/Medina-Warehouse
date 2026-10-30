<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ShopeeController extends Controller
{
    public function __construct(
        protected ShopeeService $shopeeService,
        protected StockMutationService $mutationService
    ) {}

    /**
     * Shopee Dashboard (synchronized with OpenShopee API).
     */
    public function dashboard(Request $request): View
    {
        $setting = ShopeeSetting::current();
        if ($setting->hasApiBridge()) {
            $this->shopeeService->syncGatewayStatus($request->boolean('refresh'));
            $setting->refresh();
        }

        if ($setting->isConnected()) {
            $this->shopeeService->syncAllLiveIfStale(
                15,
                $this->mutationService,
                $request->boolean('refresh')
            );
            $setting->refresh();
        }

        $connectedProducts = Product::whereNotNull('shopee_item_id')->orderBy('name')->get();
        $unconnectedProducts = Product::whereNull('shopee_item_id')->orderBy('name')->get();

        // Shopee Revenue & Order Accounting:
        // Exclude cancelled & returned orders so income matches actual Shopee Seller Centre
        $validOrdersQuery = ShopeeOrder::whereNotIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED']);

        $totalOrders = (clone $validOrdersQuery)->count();
        $totalSales = (float) (clone $validOrdersQuery)->sum('total_amount');

        $releasedSales = (float) ShopeeOrder::where('order_status', 'COMPLETED')->sum('total_amount');
        $completedCount = ShopeeOrder::where('order_status', 'COMPLETED')->count();

        $escrowSales = (float) ShopeeOrder::whereIn('order_status', ['READY_TO_SHIP', 'PROCESSED'])->sum('total_amount');

        $readyToShipCount = ShopeeOrder::where('order_status', 'READY_TO_SHIP')->count();
        $inShippingCount = ShopeeOrder::where('order_status', 'PROCESSED')->count();
        $cancelledCount = ShopeeOrder::whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])->count();

        $recentOrders = (clone $validOrdersQuery)->latest()->take(6)->get();

        $authUrl = $setting->hasApiBridge() ? $this->shopeeService->getAuthPartnerUrl() : '';
        $apiDashboardUrl = $setting->getDashboardUrl();

        return view('shopee.dashboard', compact(
            'setting',
            'connectedProducts',
            'unconnectedProducts',
            'totalOrders',
            'totalSales',
            'releasedSales',
            'escrowSales',
            'readyToShipCount',
            'inShippingCount',
            'completedCount',
            'cancelledCount',
            'recentOrders',
            'authUrl',
            'apiDashboardUrl'
        ));
    }

    /**
     * Shopee Orders List (Khusus Pesanan Masuk: READY_TO_SHIP).
     */
    public function orders(Request $request): View
    {
        $setting = ShopeeSetting::current();
        if ($setting->hasApiBridge()) {
            $this->shopeeService->syncGatewayStatus($request->boolean('refresh'));
            $setting->refresh();
        }

        if ($setting->isConnected()) {
            $this->shopeeService->syncLiveOrdersIfStale(
                15,
                3,
                $this->mutationService,
                $request->boolean('refresh')
            );
            $setting->refresh();
        }

        $query = ShopeeOrder::where('order_status', 'READY_TO_SHIP');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_sn', 'like', "%{$search}%")
                    ->orWhere('buyer_username', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        $readyToShipCount = ShopeeOrder::where('order_status', 'READY_TO_SHIP')->count();
        $inCourierCount = ShopeeOrder::whereNotIn('order_status', ['READY_TO_SHIP', 'CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])->count();

        return view('shopee.orders', compact('orders', 'setting', 'readyToShipCount', 'inCourierCount'));
    }

    /**
     * OpenShopee API Connection & Bridge Settings Page.
     */
    public function settings(): View
    {
        $setting = ShopeeSetting::current();
        $connectionCheck = null;

        if ($setting->hasApiBridge()) {
            $connectionCheck = $this->shopeeService->syncGatewayStatus(true);
            $setting->refresh();
        }

        $bridgeWebhookUrl = route('shopee.bridge.webhook');
        $webhookUrl = $bridgeWebhookUrl;
        $authUrl = $setting->hasApiBridge() ? $this->shopeeService->getAuthPartnerUrl() : '';
        $apiDashboardUrl = $setting->getDashboardUrl();

        return view('shopee.settings', compact(
            'setting',
            'connectionCheck',
            'bridgeWebhookUrl',
            'webhookUrl',
            'authUrl',
            'apiDashboardUrl'
        ));
    }

    /**
     * Update OpenShopee API Bridge Settings on Frontend.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_url' => 'required|url|max:500',
            'api_key' => 'required|string|max:500',
            'shop_name' => 'nullable|string|max:255',
            'environment' => 'nullable|in:sandbox,production',
            'auto_sync_stock' => 'nullable|boolean',
        ]);

        $setting = ShopeeSetting::current();

        $oldApiKey = $setting->getApiKey();
        $oldApiUrl = rtrim($setting->getApiUrl(), '/');
        $oldEnv = $setting->environment ?: 'sandbox';

        $apiUrl = rtrim(trim((string) $validated['api_url']), '/');
        $apiKey = trim((string) $validated['api_key']);
        $autoSyncStock = $request->boolean('auto_sync_stock');
        $requestedEnv = $validated['environment'] ?? $oldEnv;

        $apiKeyChanged = $oldApiKey !== $apiKey;
        $apiUrlChanged = $oldApiUrl !== $apiUrl;
        $envChanged = $oldEnv !== $requestedEnv;

        $setting->update([
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'shop_name' => ! empty($validated['shop_name']) ? trim((string) $validated['shop_name']) : $setting->shop_name,
            'environment' => $requestedEnv,
            'auto_sync_stock' => $autoSyncStock,
        ]);

        Cache::forget('openshopee_gateway_status_ping');
        $this->shopeeService->reloadSetting();

        if ($apiKeyChanged || $apiUrlChanged) {
            $resync = $this->shopeeService->resetAndResyncFromApi($this->mutationService);

            if (! empty($resync['success'])) {
                $pCount = (int) ($resync['products_synced'] ?? 0);
                $oCount = (int) ($resync['orders_synced'] ?? 0);
                $storeState = $setting->fresh()->isConnected()
                    ? 'Toko Shopee terhubung aktif.'
                    : 'Server API terhubung (silakan otorisasi toko Shopee pada Dashboard OpenShopee API).';

                return redirect()->route('shopee.settings')->with(
                    'success',
                    "Konfigurasi OpenShopee API diperbarui! Data lama telah dibersihkan otomatis & disinkronkan ulang mengikuti API ({$pCount} produk, {$oCount} pesanan). {$storeState}"
                );
            }

            return redirect()->route('shopee.settings')->with(
                'error',
                'Konfigurasi disimpan & data lama telah dibersihkan, namun server OpenShopee API belum merespons: '.($resync['error'] ?? 'Unknown error')
            );
        }

        if (! empty($validated['shop_name']) || ! empty($validated['environment'])) {
            $this->shopeeService->updateRemoteSettings([
                'shop_name' => $validated['shop_name'] ?? $setting->shop_name,
                'environment' => $requestedEnv,
                'auto_sync_stock' => $autoSyncStock,
            ]);
        }

        if ($envChanged) {
            $resync = $this->shopeeService->reloadSetting()->resetAndResyncFromApi($this->mutationService);
            if (! empty($resync['success'])) {
                $pCount = (int) ($resync['products_synced'] ?? 0);
                $oCount = (int) ($resync['orders_synced'] ?? 0);

                return redirect()->route('shopee.settings')->with(
                    'success',
                    'Mode berhasil dialihkan ke '.strtoupper($requestedEnv)."! Tampilan lokal disegarkan mengikuti partisi API ({$pCount} produk, {$oCount} pesanan)."
                );
            }
        }

        $ping = $this->shopeeService->syncGatewayStatus(true);

        if (! empty($ping['success'])) {
            $this->shopeeService->syncDeltaFromGateway($this->mutationService);
            $storeState = $setting->fresh()->isConnected()
                ? 'Toko Shopee terhubung aktif.'
                : 'Server API terhubung (silakan otorisasi toko Shopee pada Dashboard OpenShopee API).';

            return redirect()->route('shopee.settings')->with(
                'success',
                "Konfigurasi OpenShopee API berhasil disimpan & terverifikasi! {$storeState}"
            );
        }

        return redirect()->route('shopee.settings')->with(
            'error',
            'Konfigurasi disimpan, namun server OpenShopee API belum merespons: '.($ping['error'] ?? 'Unknown error')
        );
    }

    /**
     * Test live connectivity to OpenShopee API and run immediate delta sync.
     */
    public function testApiConnection(): RedirectResponse
    {
        Cache::forget('openshopee_gateway_status_ping');
        $ping = $this->shopeeService->reloadSetting()->syncGatewayStatus(true);

        if (empty($ping['success'])) {
            return redirect()->route('shopee.settings')->with(
                'error',
                'Gagal terhubung ke OpenShopee API: '.($ping['error'] ?? 'Periksa URL & API Key.')
            );
        }

        $delta = $this->shopeeService->syncDeltaFromGateway($this->mutationService);
        $ordersSynced = (int) ($delta['orders_synced'] ?? 0);
        $productsSynced = (int) ($delta['products_synced'] ?? 0);

        return redirect()->route('shopee.settings')->with(
            'success',
            "Koneksi OpenShopee API aktif & real-time! Sinkronisasi delta berhasil ({$productsSynced} produk, {$ordersSynced} pesanan diperbarui)."
        );
    }

    /**
     * Handle legacy/redirect callback by refreshing status from OpenShopee API.
     */
    public function handleCallback(Request $request): RedirectResponse
    {
        Cache::forget('openshopee_gateway_status_ping');
        $status = $this->shopeeService->reloadSetting()->syncGatewayStatus(true);

        if (! empty($status['success']) && ShopeeSetting::current()->isConnected()) {
            return redirect()->route('shopee.dashboard')->with('success', 'Toko Shopee berhasil terhubung melalui OpenShopee API!');
        }

        return redirect()->route('shopee.settings')->with(
            'error',
            'Otorisasi OAuth Shopee kini dikelola langsung melalui Dashboard OpenShopee API.'
        );
    }

    /**
     * Request Token Refresh via OpenShopee API.
     */
    public function refreshToken(): RedirectResponse
    {
        $result = $this->shopeeService->refreshAccessToken();

        if (! empty($result['success'])) {
            return redirect()->back()->with('success', 'Access token Shopee di OpenShopee API berhasil diperbarui!');
        }

        return redirect()->back()->with('error', 'Gagal memperbarui token: '.($result['error'] ?? 'Unknown error'));
    }

    /**
     * Disconnect Shopee Account via OpenShopee API.
     */
    public function disconnect(): RedirectResponse
    {
        $result = $this->shopeeService->disconnectRemote();

        if (! empty($result['success'])) {
            return redirect()->back()->with('success', 'Koneksi akun Shopee pada OpenShopee API berhasil diputuskan.');
        }

        ShopeeSetting::current()->update([
            'remote_connected' => false,
            'token_expires_at' => null,
        ]);

        return redirect()->back()->with('success', 'Status koneksi akun Shopee pada frontend telah direset.');
    }

    /**
     * Map Warehouse Product to Shopee Item ID.
     */
    public function mapProduct(Request $request, Product $product): RedirectResponse
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

        $this->shopeeService->updateStock(
            (int) $product->shopee_item_id,
            $product->stock,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );
        $this->shopeeService->updatePrice(
            (int) $product->shopee_item_id,
            (float) $product->selling_price,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );

        return redirect()->back()->with(
            'success',
            "Produk [{$product->sku}] {$product->name} berhasil dipetakan ke Shopee Item ID #{$product->shopee_item_id} (Stok & Harga disinkronkan)!"
        );
    }

    /**
     * Unlink product from Shopee.
     */
    public function unmapProduct(Product $product): RedirectResponse
    {
        $product->update([
            'shopee_item_id' => null,
            'shopee_model_id' => null,
            'shopee_stock' => 0,
        ]);

        return redirect()->back()->with('success', "Produk {$product->name} telah dilepas dari Shopee.");
    }

    /**
     * Sync single product stock and price to Shopee via OpenShopee API.
     */
    public function syncProductStock(Product $product): RedirectResponse
    {
        if (! $product->isConnectedToShopee()) {
            return redirect()->back()->with('error', "Produk {$product->name} belum terhubung ke Shopee.");
        }

        $this->shopeeService->updateStock(
            (int) $product->shopee_item_id,
            $product->stock,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );

        $this->shopeeService->updatePrice(
            (int) $product->shopee_item_id,
            (float) $product->selling_price,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );

        $product->update(['shopee_stock' => $product->stock]);

        return redirect()->back()->with(
            'success',
            "Stok ({$product->stock} unit) & harga (Rp ".number_format($product->selling_price, 0, ',', '.').") produk {$product->name} berhasil disinkronkan ke Shopee!"
        );
    }

    /**
     * Sync all connected products stock and price to Shopee via OpenShopee API.
     */
    public function syncAllStock(): RedirectResponse
    {
        $products = Product::whereNotNull('shopee_item_id')->get();
        $synced = 0;

        foreach ($products as $product) {
            $this->shopeeService->updateStock(
                (int) $product->shopee_item_id,
                $product->stock,
                $product->shopee_model_id ? (int) $product->shopee_model_id : null
            );
            $this->shopeeService->updatePrice(
                (int) $product->shopee_item_id,
                (float) $product->selling_price,
                $product->shopee_model_id ? (int) $product->shopee_model_id : null
            );
            $product->update(['shopee_stock' => $product->stock]);
            $synced++;
        }

        return redirect()->back()->with('success', "Berhasil mensinkronkan stok & harga {$synced} produk ke Shopee!");
    }

    /**
     * Real-Time Bridge Push Webhook Receiver from OpenShopee API.
     * Instantly synchronizes orders, products, stock mutations, and connection status.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $rawContent = $request->getContent();
        $signature = (string) $request->header('X-OpenShopee-Signature', $request->header('Authorization', ''));
        $headerApiKey = (string) $request->header('X-API-Key', '');

        $data = $request->json()->all();
        $setting = $this->shopeeService->getSetting();

        $isValidBridge = $this->shopeeService->verifyBridgePush($rawContent, $signature, $headerApiKey);

        if (! $isValidBridge) {
            Log::warning('OpenShopee Bridge Webhook Rejected: invalid API key or HMAC signature');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = (string) ($request->header('X-OpenShopee-Event') ?: ($data['event'] ?? ''));
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];

        if ($event === 'ping') {
            return response()->json([
                'code' => 0,
                'message' => 'pong',
                'frontend' => config('app.name'),
            ]);
        }

        if ($event === 'order.updated' && ! empty($payload['order']) && is_array($payload['order'])) {
            $this->shopeeService->applySyncedOrders([$payload['order']], $this->mutationService);
            $setting->update(['last_synced_at' => now()]);

            return response()->json(['code' => 0, 'message' => 'order synced']);
        }

        if ($event === 'orders.synced' && ! empty($payload['orders']) && is_array($payload['orders'])) {
            $synced = $this->shopeeService->applySyncedOrders($payload['orders'], $this->mutationService);
            $setting->update(['last_synced_at' => now()]);

            return response()->json(['code' => 0, 'message' => 'orders synced', 'count' => count($synced)]);
        }

        if ($event === 'products.synced' && is_array($payload['products'] ?? null)) {
            $reconciled = (bool) ($payload['reconciled'] ?? $payload['reconcile'] ?? false);
            $synced = $this->shopeeService->applySyncedProducts($payload['products'], $reconciled);
            $setting->update(['last_synced_at' => now()]);

            return response()->json(['code' => 0, 'message' => 'products synced', 'count' => count($synced)]);
        }

        if ($event === 'settings.updated') {
            $statusSnapshot = is_array($payload['status'] ?? null) ? $payload['status'] : $payload;
            if (! empty($statusSnapshot)) {
                $this->shopeeService->applyRemoteStatusSnapshot($statusSnapshot);
            }

            return response()->json(['code' => 0, 'message' => 'settings synced']);
        }

        // Fallback compatibility for direct compact order/item event format
        $code = isset($data['code']) ? (int) $data['code'] : null;
        $orderSn = $payload['order_sn'] ?? ($payload['ordersn'] ?? null);

        if ($orderSn) {
            $existingOrder = ShopeeOrder::where('order_sn', $orderSn)->first();
            $incomingStatus = ($code === 3 || $code === null)
                ? ($payload['order_status'] ?? $payload['status'] ?? null)
                : ($payload['order_status'] ?? null);
            $status = $incomingStatus ?: ($existingOrder ? $existingOrder->order_status : 'READY_TO_SHIP');
            $items = $payload['items'] ?? ($existingOrder?->items_data ?? []);
            $buyerUsername = $payload['buyer_username'] ?? ($existingOrder ? $existingOrder->buyer_username : 'Shopee Customer');
            $totalAmount = $payload['total_amount'] ?? ($existingOrder ? $existingOrder->total_amount : 0);
            $trackingNumber = $payload['tracking_no'] ?? ($payload['tracking_number'] ?? $existingOrder?->tracking_number);
            $shippingCarrier = $payload['shipping_carrier'] ?? $existingOrder?->shipping_carrier;

            if (empty($items) && $setting->isConnected()) {
                $detailResult = $this->shopeeService->getOrderDetail([$orderSn]);
                if (! empty($detailResult['success']) && ! empty($detailResult['data']['order_list'][0])) {
                    $orderDetail = $detailResult['data']['order_list'][0];
                    $status = $orderDetail['order_status'] ?? $status;
                    $buyerUsername = $orderDetail['buyer_username'] ?? $buyerUsername;
                    $totalAmount = $orderDetail['total_amount'] ?? $totalAmount;
                    $shippingCarrier = $shippingCarrier ?? ($orderDetail['shipping_carrier'] ?? null);
                    $trackingNumber = $trackingNumber ?? ($orderDetail['tracking_number'] ?? ($orderDetail['package_list'][0]['tracking_number'] ?? null));

                    foreach ($orderDetail['item_list'] ?? [] as $rawItem) {
                        $items[] = [
                            'item_id' => $rawItem['item_id'] ?? null,
                            'item_sku' => $rawItem['item_sku'] ?? null,
                            'item_name' => $rawItem['item_name'] ?? 'Shopee Item',
                            'model_id' => $rawItem['model_id'] ?? null,
                            'model_name' => $rawItem['model_name'] ?? null,
                            'model_quantity_purchased' => $rawItem['model_quantity_purchased'] ?? 1,
                            'model_discounted_price' => $rawItem['model_discounted_price'] ?? 0,
                            'image_url' => $rawItem['image_info']['image_url'] ?? null,
                        ];
                    }
                }
            }

            $this->shopeeService->applySyncedOrders([[
                'order_sn' => $orderSn,
                'order_status' => $status,
                'total_amount' => $totalAmount,
                'buyer_username' => $buyerUsername,
                'tracking_number' => $trackingNumber,
                'shipping_carrier' => $shippingCarrier,
                'items' => $items,
            ]], $this->mutationService);
        }

        if (in_array($code, [6, 7, 8, 9, 16, 22, 27], true)) {
            $productSyncLock = 'shopee_product_sync_webhook_lock';
            if (! Cache::has($productSyncLock)) {
                Cache::put($productSyncLock, true, 60);
                $this->shopeeService->pullAndSyncProducts($this->mutationService);
            }
        }

        if (! $setting->last_synced_at || $setting->last_synced_at->diffInSeconds(now()) >= 30) {
            $setting->update(['last_synced_at' => now()]);
        }

        return response()->json(['code' => 0, 'message' => 'success']);
    }

    /**
     * Order Simulation Tool (Sandbox Only — syncs with OpenShopee API when bridge is configured).
     */
    public function simulateOrder(Request $request): RedirectResponse
    {
        $setting = ShopeeSetting::current();
        if ($setting->isProduction()) {
            return redirect()->back()->with('error', 'Fitur simulasi order dinonaktifkan pada mode Production.');
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'buyer_username' => 'nullable|string|max:100',
            'order_status' => 'nullable|in:READY_TO_SHIP,PROCESSED,COMPLETED',
        ]);

        $orderStatus = $validated['order_status'] ?? 'READY_TO_SHIP';
        $product = Product::findOrFail($validated['product_id']);
        $qty = (int) $validated['qty'];
        $buyer = $validated['buyer_username'] ?: 'pembeli_shopee_'.random_int(100, 999);
        $orderSn = '26'.date('md').strtoupper(Str::random(8));

        $orderData = [
            'order_sn' => $orderSn,
            'shop_id' => $setting->shop_id ?? 12345678,
            'order_status' => $orderStatus,
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
            $this->mutationService->processShopeeOrder($orderData);

            if ($product->isConnectedToShopee()) {
                $product->refresh();
                $this->shopeeService->updateStock((int) $product->shopee_item_id, $product->stock);
            }

            if ($setting->hasApiBridge()) {
                $this->shopeeService->simulateOrderOnGateway($orderData);
            }

            return redirect()->back()->with(
                'success',
                "Simulasi Berhasil! Pesanan Shopee #{$orderSn} diterima. Stok [{$product->sku}] {$product->name} berkurang {$qty} {$product->unit} di Gudang Utama dan disinkronkan menjadi {$product->stock} unit."
            );
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Gagal memproses simulasi: '.$e->getMessage());
        }
    }

    /**
     * Pull recent orders via OpenShopee API.
     */
    public function pullOrders(Request $request): RedirectResponse
    {
        $days = (int) $request->input('days', 3);
        $days = min(15, max(1, $days));

        $result = $this->shopeeService->pullAndSyncOrders($days, $this->mutationService);

        if (! empty($result['success'])) {
            $count = $result['synced_count'] ?? 0;
            $msg = $count > 0
                ? "Berhasil menarik & mensinkronkan {$count} pesanan terbaru melalui OpenShopee API!"
                : 'Pengecekan selesai: Tidak ada pesanan Shopee baru dalam rentang waktu tersebut.';

            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', 'Gagal menarik pesanan: '.($result['error'] ?? 'Terjadi kesalahan sistem'));
    }

    /**
     * Fetch live shop products via OpenShopee API for interactive product mapping.
     */
    public function fetchShopProducts(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->input('offset', 0));
        $pageSize = min(50, max(1, (int) $request->input('page_size', 20)));

        $result = $this->shopeeService->fetchLiveShopProducts($offset, $pageSize);

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Gagal mengambil produk dari OpenShopee API',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'items' => $result['items'] ?? [],
            'has_next_page' => $result['has_next_page'] ?? false,
            'total_count' => $result['total_count'] ?? count($result['items'] ?? []),
        ]);
    }

    /**
     * Pull all products and stock via OpenShopee API.
     */
    public function pullProducts(): RedirectResponse
    {
        $result = $this->shopeeService->pullAndSyncProducts($this->mutationService);

        if (! empty($result['success'])) {
            $count = $result['synced_count'] ?? 0;
            $msg = $count > 0
                ? "Berhasil menarik & mensinkronkan {$count} produk beserta stok melalui OpenShopee API!"
                : 'Pengecekan selesai: Belum ada produk aktif yang ditemukan di toko Shopee Anda.';

            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', 'Gagal menarik produk: '.($result['error'] ?? 'Terjadi kesalahan sistem'));
    }

    /**
     * Real-time web background delta sync with 12-second cooldown lock.
     */
    public function backgroundSync(): JsonResponse
    {
        if (Cache::has('shopee_daemon_active')) {
            return response()->json([
                'status' => 'daemon_running',
                'message' => 'Background daemon aktif berjalan di background.',
            ]);
        }

        $lockKey = 'shopee_web_bg_sync_cooldown';
        if (Cache::has($lockKey)) {
            return response()->json([
                'status' => 'cooldown',
                'message' => 'Sync dalam masa jeda aman (cooldown 12 detik)',
            ]);
        }

        Cache::put($lockKey, time(), 12);

        try {
            $deltaResult = $this->shopeeService->syncDeltaFromGateway($this->mutationService);
            $activeResult = null;
            $trackedCount = 0;

            $activeSweepLock = 'shopee_web_bg_active_sweep_lock';
            if ($this->shopeeService->getSetting()->isConnected() && ! Cache::has($activeSweepLock)) {
                Cache::put($activeSweepLock, time(), 60);
                $activeResult = $this->shopeeService->syncActiveOrdersIncremental(20, $this->mutationService, 2);

                $activePackages = ShopeeOrder::whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
                    ->whereNotNull('tracking_number')
                    ->where(function ($q) {
                        $q->whereNull('last_tracked_at')
                            ->orWhere('last_tracked_at', '<=', now()->subMinutes(20));
                    })
                    ->take(5)
                    ->get();

                foreach ($activePackages as $pkg) {
                    $trackRes = $this->shopeeService->syncOrderTrackingInfo($pkg);
                    if (! empty($trackRes['success'])) {
                        $trackedCount++;
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'delta' => $deltaResult,
                'data' => $activeResult ?? $deltaResult,
                'orders_synced' => (int) ($deltaResult['orders_synced'] ?? 0) + (int) ($activeResult['synced_this_batch'] ?? 0),
                'products_synced' => (int) ($deltaResult['products_synced'] ?? 0),
                'packages_tracked' => $trackedCount,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
