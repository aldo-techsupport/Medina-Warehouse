<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    public function dashboard(Request $request)
    {
        $setting = ShopeeSetting::current();
        if ($setting->isConnected() && $request->boolean('refresh')) {
            $this->shopeeService->syncAllLiveIfStale(0, $this->mutationService, true);
            $setting->refresh();
        }

        $connectedProducts = Product::whereNotNull('shopee_item_id')->orderBy('name')->get();
        $unconnectedProducts = Product::whereNull('shopee_item_id')->orderBy('name')->get();

        // Shopee Revenue & Order Accounting:
        // Exclude cancelled & returned orders so income matches actual Shopee Seller Centre
        $validOrdersQuery = ShopeeOrder::whereNotIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED']);

        // 1. Total order & total omzet (pesanan sah)
        $totalOrders = (clone $validOrdersQuery)->count();
        $totalSales = (float) (clone $validOrdersQuery)->sum('total_amount');

        // 2. Penghasilan Selesai / Cair ke Saldo Penjual (COMPLETED)
        $releasedSales = (float) ShopeeOrder::where('order_status', 'COMPLETED')->sum('total_amount');
        $completedCount = ShopeeOrder::where('order_status', 'COMPLETED')->count();

        // 3. Penghasilan Akan Cair / Sedang Ditahan di Shopee Escrow (READY_TO_SHIP & PROCESSED)
        $escrowSales = (float) ShopeeOrder::whereIn('order_status', ['READY_TO_SHIP', 'PROCESSED'])->sum('total_amount');

        // 4. Status Breakdown:
        // Khusus READY_TO_SHIP (pesanan masuk gudang siap dipacking)
        $readyToShipCount = ShopeeOrder::where('order_status', 'READY_TO_SHIP')->count();
        // Sedang diproses / dalam perjalanan kurir (dipantau di Lacak Kurir)
        $inShippingCount = ShopeeOrder::where('order_status', 'PROCESSED')->count();
        $cancelledCount = ShopeeOrder::whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])->count();

        $recentOrders = (clone $validOrdersQuery)->latest()->take(6)->get();

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
            'releasedSales',
            'escrowSales',
            'readyToShipCount',
            'inShippingCount',
            'completedCount',
            'cancelledCount',
            'recentOrders',
            'authUrl'
        ));
    }

    /**
     * Shopee Orders List (Khusus Pesanan Masuk: READY_TO_SHIP).
     */
    public function orders(Request $request)
    {
        $setting = ShopeeSetting::current();
        if ($setting->isConnected() && $request->boolean('refresh')) {
            $this->shopeeService->syncLiveOrdersIfStale(0, 3, $this->mutationService, true);
            $setting->refresh();
        }

        // Pesanan Shopee khusus hanya menampilkan pesanan yang READY_TO_SHIP (Perlu Dikirim)
        // Pesanan yang sudah diproses atau diserahkan ke ekspedisi dilacak di menu Lacak Kurir
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

        // Metrik status untuk kemudahan staf gudang
        $readyToShipCount = ShopeeOrder::where('order_status', 'READY_TO_SHIP')->count();
        $inCourierCount = ShopeeOrder::whereNotIn('order_status', ['READY_TO_SHIP', 'CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])->count();

        return view('shopee.orders', compact('orders', 'setting', 'readyToShipCount', 'inCourierCount'));
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

        $partnerId = ! empty($validated['partner_id']) ? (int) trim((string) $validated['partner_id']) : null;
        $partnerKey = ! empty($validated['partner_key']) ? trim((string) $validated['partner_key']) : null;

        $partnerIdChanged = (int) $setting->partner_id !== (int) $partnerId;
        $partnerKeyChanged = $setting->partner_key !== $partnerKey;
        $envChanged = $setting->environment !== $validated['environment'];

        $updateData = [
            'partner_id' => $partnerId,
            'partner_key' => $partnerKey,
            'shop_id' => $validated['shop_id'] ?? null,
            'shop_name' => $validated['shop_name'] ?: 'Medina Official Store',
            'environment' => $validated['environment'],
            'auto_sync_stock' => $request->boolean('auto_sync_stock'),
        ];

        if ($partnerIdChanged || $partnerKeyChanged || $envChanged) {
            $updateData['access_token'] = null;
            $updateData['refresh_token'] = null;
            $updateData['expire_in'] = null;
            $updateData['token_expires_at'] = null;
        }

        $setting->update($updateData);

        return redirect()->route('shopee.settings')->with('success', 'Konfigurasi Shopee Open API berhasil disimpan! Silakan klik tombol "Buka Otorisasi Shopee" untuk menghubungkan toko.');
    }

    /**
     * OAuth Callback from Shopee.
     */
    public function handleCallback(Request $request)
    {
        $code = $request->input('code');
        $rawShopId = $request->input('shop_id');
        $shopId = ! empty($rawShopId) ? (int) $rawShopId : (! empty(ShopeeSetting::current()->shop_id) ? (int) ShopeeSetting::current()->shop_id : null);
        $mainAccountId = $request->filled('main_account_id') ? (int) $request->input('main_account_id') : null;

        if (! $code) {
            return redirect()->route('shopee.settings')->with('error', 'Otorisasi Shopee gagal: Kode otorisasi tidak diterima.');
        }

        $result = $this->shopeeService->getAccessToken($code, $shopId, $mainAccountId);

        if ($result['success']) {
            return redirect()->route('shopee.dashboard')->with('success', 'Selamat! Toko Shopee berhasil terhubung.');
        }

        return redirect()->route('shopee.settings')->with('error', 'Gagal menukar token: '.($result['error'] ?? 'Unknown error'));
    }

    /**
     * Manually Refresh Access Token.
     */
    public function refreshToken()
    {
        $result = $this->shopeeService->refreshAccessToken();

        if ($result['success']) {
            return redirect()->back()->with('success', 'Access token Shopee berhasil diperbarui!');
        }

        return redirect()->back()->with('error', 'Gagal memperbarui token: '.($result['error'] ?? 'Unknown error'));
    }

    /**
     * Disconnect Shopee Account.
     */
    public function disconnect()
    {
        $setting = ShopeeSetting::current();
        $setting->update([
            'access_token' => null,
            'refresh_token' => null,
            'expire_in' => null,
            'token_expires_at' => null,
        ]);

        return redirect()->back()->with('success', 'Koneksi akun Shopee berhasil diputuskan.');
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

        // Push current stock and price to Shopee
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

        return redirect()->back()->with('success', "Produk [{$product->sku}] {$product->name} berhasil dipetakan ke Shopee Item ID #{$product->shopee_item_id} (Stok & Harga disinkronkan)!");
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
     * Sync single product stock and price to Shopee.
     */
    public function syncProductStock(Product $product)
    {
        if (! $product->isConnectedToShopee()) {
            return redirect()->back()->with('error', "Produk {$product->name} belum terhubung ke Shopee.");
        }

        $resultStock = $this->shopeeService->updateStock(
            (int) $product->shopee_item_id,
            $product->stock,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );

        $resultPrice = $this->shopeeService->updatePrice(
            (int) $product->shopee_item_id,
            (float) $product->selling_price,
            $product->shopee_model_id ? (int) $product->shopee_model_id : null
        );

        $product->update(['shopee_stock' => $product->stock]);

        return redirect()->back()->with('success', "Stok ({$product->stock} unit) & harga (Rp ".number_format($product->selling_price, 0, ',', '.').") produk {$product->name} berhasil disinkronkan ke Shopee!");
    }

    /**
     * Sync all connected products stock and price to Shopee.
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
     * Webhook Endpoint for Shopee Push Notifications.
     * Decrements warehouse stock automatically when order is created/paid on Shopee.
     */
    public function handleWebhook(Request $request)
    {
        $signature = $request->header('Authorization', '');
        $rawContent = $request->getContent();
        $fullUrl = $request->fullUrl();

        $data = $request->json()->all();
        $shopId = (int) ($data['shop_id'] ?? 0);
        $setting = $this->shopeeService->getSetting();

        // Verify HMAC signature, or authorize if payload belongs to our authenticated shop
        $isValidSignature = $this->shopeeService->verifyWebhookSignature($fullUrl, $rawContent, $signature);
        $isAuthorizedShop = ($setting->isConnected() && $shopId === (int) $setting->shop_id);

        if (! $isValidSignature && ! $isAuthorizedShop) {
            Log::warning('Shopee Webhook Rejected: unauthorized shop or invalid signature');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $code = $data['code'] ?? null; // e.g. 3 = order update, 4 = order created, 10/11 = item update
        $dataContent = $data['data'] ?? [];
        $orderSn = $dataContent['order_sn'] ?? ($dataContent['ordersn'] ?? null);
        Log::debug("Shopee Webhook Received: code {$code}, order {$orderSn}");

        if ($orderSn) {
            $existingOrder = ShopeeOrder::where('order_sn', $orderSn)->first();
            $status = $dataContent['status'] ?? ($existingOrder ? $existingOrder->order_status : 'READY_TO_SHIP');
            $items = $dataContent['items'] ?? [];
            $buyerUsername = $dataContent['buyer_username'] ?? ($existingOrder ? $existingOrder->buyer_username : 'Shopee Customer');
            $totalAmount = $dataContent['total_amount'] ?? ($existingOrder ? $existingOrder->total_amount : 0);
            $trackingNumber = $dataContent['tracking_no'] ?? ($dataContent['tracking_number'] ?? ($existingOrder ? $existingOrder->tracking_number : null));
            $shippingCarrier = $dataContent['shipping_carrier'] ?? ($existingOrder ? $existingOrder->shipping_carrier : null);

            // Jika order sudah tersimpan di database lokal dan items_data lengkap:
            // CUKUP proses mutasi / status / tracking secara lokal (1-2 ms) tanpa request HTTP eksternal ke Shopee API!
            if ($existingOrder && ! empty($existingOrder->items_data)) {
                $items = $existingOrder->items_data;
                $order = $this->mutationService->processShopeeOrder([
                    'order_sn' => $orderSn,
                    'order_status' => $status,
                    'total_amount' => $totalAmount,
                    'buyer_username' => $buyerUsername,
                    'items' => $items,
                ]);

                if ($trackingNumber && ($order->tracking_number !== $trackingNumber || empty($order->tracking_number))) {
                    $order->update([
                        'tracking_number' => $trackingNumber,
                        'shipping_carrier' => $shippingCarrier ?? $order->shipping_carrier,
                    ]);
                }
            } else {
                // Hanya jika order baru di DB / belum memiliki rincian item, ambil langsung dari Shopee API
                if (empty($items)) {
                    $detailResult = $this->shopeeService->getOrderDetail([$orderSn]);
                    if ($detailResult['success'] && ! empty($detailResult['data']['order_list'])) {
                        $orderDetail = $detailResult['data']['order_list'][0];
                        $status = $orderDetail['order_status'] ?? $status;
                        $buyerUsername = $orderDetail['buyer_username'] ?? $buyerUsername;
                        $totalAmount = $orderDetail['total_amount'] ?? $totalAmount;
                        $shippingCarrier = $shippingCarrier ?? ($orderDetail['shipping_carrier'] ?? null);
                        $trackingNumber = $trackingNumber ?? ($orderDetail['tracking_number'] ?? ($orderDetail['package_list'][0]['tracking_number'] ?? null));

                        $rawItemList = $orderDetail['item_list'] ?? [];
                        foreach ($rawItemList as $rawItem) {
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

                $order = $this->mutationService->processShopeeOrder([
                    'order_sn' => $orderSn,
                    'order_status' => $status,
                    'total_amount' => $totalAmount,
                    'buyer_username' => $buyerUsername,
                    'items' => $items,
                ]);

                if ($trackingNumber && empty($order->tracking_number)) {
                    $order->update([
                        'tracking_number' => $trackingNumber,
                        'shipping_carrier' => $shippingCarrier ?? $order->shipping_carrier,
                    ]);
                }
            }
        }

        // Jika ada perubahan item/stok di Shopee, trigger sync produk otomatis (dibatasi debounce cache 2 menit)
        if (in_array((int) $code, [10, 11])) {
            $productSyncLock = 'shopee_product_sync_webhook_lock';
            if (! Cache::has($productSyncLock)) {
                Cache::put($productSyncLock, true, 120);
                $this->shopeeService->pullAndSyncProducts($this->mutationService);
            }
        }

        // Update timestamp sinkronisasi (dibatasi maksimal 1x per 60 detik agar tidak membebani DB)
        $setting = ShopeeSetting::current();
        if (! $setting->last_synced_at || $setting->last_synced_at->diffInSeconds(now()) >= 60) {
            $setting->update(['last_synced_at' => now()]);
        }

        return response()->json(['code' => 0, 'message' => 'success']);
    }

    /**
     * Order Simulation Tool (For instant testing without real Shopee orders).
     */
    public function simulateOrder(Request $request)
    {
        if (ShopeeSetting::current()->isProduction()) {
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
        $buyer = $validated['buyer_username'] ?: 'pembeli_shopee_'.rand(100, 999);
        $orderSn = '26'.date('md').strtoupper(Str::random(8));

        $orderData = [
            'order_sn' => $orderSn,
            'shop_id' => ShopeeSetting::current()->shop_id ?? 12345678,
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
            $order = $this->mutationService->processShopeeOrder($orderData);

            if ($product->isConnectedToShopee()) {
                $product->refresh();
                $this->shopeeService->updateStock((int) $product->shopee_item_id, $product->stock);
            }

            return redirect()->back()->with('success', "Simulasi Berhasil! Pesanan Shopee #{$orderSn} diterima. Stok [{$product->sku}] {$product->name} berkurang {$qty} {$product->unit} di Gudang Utama dan stok live Shopee disinkronkan menjadi {$product->stock} unit.");
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

    /**
     * Pull all products and stock directly from Shopee Open API v2.
     */
    public function pullProducts(Request $request)
    {
        $result = $this->shopeeService->pullAndSyncProducts($this->mutationService);

        if ($result['success']) {
            $count = $result['synced_count'] ?? 0;
            $msg = $count > 0
                ? "Berhasil menarik & mensinkronkan {$count} produk beserta stok dari Shopee!"
                : 'Pengecekan selesai: Belum ada produk aktif yang ditemukan di toko Shopee Anda.';

            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', 'Gagal menarik produk dari Shopee: '.($result['error'] ?? 'Terjadi kesalahan sistem'));
    }

    /**
     * Web-triggered background sync with 60-second cooldown lock.
     */
    public function backgroundSync(): JsonResponse
    {
        // Jika background runner Windows daemon sedang aktif, tidak perlu menjalankan sync di thread web
        if (Cache::has('shopee_daemon_active')) {
            return response()->json([
                'status' => 'daemon_running',
                'message' => 'Background runner Windows sedang aktif berjalan di background.',
            ]);
        }

        $lockKey = 'shopee_web_bg_sync_cooldown';
        if (Cache::has($lockKey)) {
            return response()->json([
                'status' => 'cooldown',
                'message' => 'Sync dalam masa jeda aman (cooldown 60 detik)',
            ]);
        }

        // Put 60 seconds lock to prevent overlapping calls
        Cache::put($lockKey, time(), 60);

        try {
            $result = $this->shopeeService->syncActiveOrdersIncremental(20, $this->mutationService, 2);

            // Also synchronize tracking milestones for active packages
            $activePackages = ShopeeOrder::whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
                ->whereNotNull('tracking_number')
                ->where(function ($q) {
                    $q->whereNull('last_tracked_at')
                        ->orWhere('last_tracked_at', '<=', now()->subMinutes(20));
                })
                ->take(5)
                ->get();

            $trackedCount = 0;
            foreach ($activePackages as $pkg) {
                $trackRes = $this->shopeeService->syncOrderTrackingInfo($pkg);
                if (! empty($trackRes['success'])) {
                    $trackedCount++;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $result,
                'packages_tracked' => $trackedCount,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
