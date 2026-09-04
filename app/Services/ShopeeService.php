<?php

namespace App\Services;

use App\Models\ShopeeSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeService
{
    protected ShopeeSetting $setting;

    public function __construct(?ShopeeSetting $setting = null)
    {
        $this->setting = $setting ?? ShopeeSetting::current();
    }

    /**
     * Get Base API URL based on environment.
     */
    public function getBaseUrl(): string
    {
        return $this->setting->environment === 'production'
            ? 'https://partner.shopeemobile.com'
            : 'https://partner.test-stable.shopeemobile.com';
    }

    /**
     * Generate HMAC-SHA256 signature for Shopee Public API (Auth / Token).
     */
    public function generatePublicSign(string $path, int $timestamp): string
    {
        $partnerId = (string) $this->setting->partner_id;
        $partnerKey = (string) $this->setting->partner_key;
        $baseString = sprintf('%s%s%s', $partnerId, $path, $timestamp);

        return hash_hmac('sha256', $baseString, $partnerKey);
    }

    /**
     * Generate HMAC-SHA256 signature for Shopee Shop API.
     */
    public function generateShopSign(string $path, int $timestamp, ?string $accessToken = null, ?int $shopId = null): string
    {
        $partnerId = (string) $this->setting->partner_id;
        $partnerKey = (string) $this->setting->partner_key;
        $token = $accessToken ?? (string) $this->setting->access_token;
        $shop = $shopId ?? (string) $this->setting->shop_id;

        $baseString = sprintf('%s%s%s%s%s', $partnerId, $path, $timestamp, $token, $shop);

        return hash_hmac('sha256', $baseString, $partnerKey);
    }

    /**
     * Generate Shopee Shop Partner Authorization URL.
     */
    public function getAuthPartnerUrl(string $redirectUrl): string
    {
        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $sign = $this->generatePublicSign($path, $timestamp);

        return sprintf(
            '%s%s?partner_id=%s&timestamp=%d&sign=%s&redirect=%s',
            $this->getBaseUrl(),
            $path,
            $this->setting->partner_id,
            $timestamp,
            $sign,
            urlencode($redirectUrl)
        );
    }

    /**
     * Exchange Authorization Code for Access Token & Refresh Token.
     */
    public function getAccessToken(string $code, int $shopId): array
    {
        $path = '/api/v2/auth/token/get';
        $timestamp = time();
        $sign = $this->generatePublicSign($path, $timestamp);

        $url = sprintf(
            '%s%s?partner_id=%s&timestamp=%d&sign=%s',
            $this->getBaseUrl(),
            $path,
            $this->setting->partner_id,
            $timestamp,
            $sign
        );

        $response = Http::timeout(15)->post($url, [
            'code' => $code,
            'shop_id' => $shopId,
            'partner_id' => (int) $this->setting->partner_id,
        ]);

        $data = $response->json();

        if ($response->successful() && empty($data['error'])) {
            $this->setting->update([
                'shop_id' => $shopId,
                'access_token' => $data['access_token'] ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'expire_in' => $data['expire_in'] ?? null,
                'token_expires_at' => now()->addSeconds(($data['expire_in'] ?? 14400) - 300),
            ]);

            return ['success' => true, 'data' => $data];
        }

        return [
            'success' => false,
            'error' => $data['message'] ?? $data['error'] ?? 'Gagal mendapatkan access token dari Shopee',
        ];
    }

    /**
     * Refresh Access Token.
     */
    public function refreshAccessToken(): array
    {
        if (empty($this->setting->refresh_token)) {
            return ['success' => false, 'error' => 'Refresh token tidak ditemukan'];
        }

        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $sign = $this->generatePublicSign($path, $timestamp);

        $url = sprintf(
            '%s%s?partner_id=%s&timestamp=%d&sign=%s',
            $this->getBaseUrl(),
            $path,
            $this->setting->partner_id,
            $timestamp,
            $sign
        );

        $response = Http::timeout(15)->post($url, [
            'refresh_token' => $this->setting->refresh_token,
            'shop_id' => (int) $this->setting->shop_id,
            'partner_id' => (int) $this->setting->partner_id,
        ]);

        $data = $response->json();

        if ($response->successful() && empty($data['error'])) {
            $this->setting->update([
                'access_token' => $data['access_token'] ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'expire_in' => $data['expire_in'] ?? null,
                'token_expires_at' => now()->addSeconds(($data['expire_in'] ?? 14400) - 300),
            ]);

            return ['success' => true, 'data' => $data];
        }

        return [
            'success' => false,
            'error' => $data['message'] ?? $data['error'] ?? 'Gagal memperbarui access token',
        ];
    }

    public function getSetting(): ShopeeSetting
    {
        return $this->setting;
    }

    /**
     * Ensure access token is valid before making authenticated requests.
     * Automatically refreshes the token if expired or about to expire within 10 minutes.
     */
    public function ensureValidToken(): bool
    {
        if (! $this->setting->isConnected()) {
            return false;
        }

        // If token expires in less than 10 minutes (or already expired), refresh it
        $needsRefresh = empty($this->setting->access_token) ||
            empty($this->setting->token_expires_at) ||
            $this->setting->token_expires_at->isPast() ||
            $this->setting->token_expires_at->diffInMinutes(now(), false) > -10;

        if ($needsRefresh && ! empty($this->setting->refresh_token)) {
            Log::info('Shopee Access Token is expiring or expired. Auto-refreshing...');
            $refreshResult = $this->refreshAccessToken();

            if ($refreshResult['success']) {
                $this->setting->refresh();
                Log::info('Shopee Access Token auto-refreshed successfully.');

                return true;
            }

            Log::error('Failed to auto-refresh Shopee token: '.($refreshResult['error'] ?? 'Unknown error'));

            return false;
        }

        return true;
    }

    /**
     * Build signed URL for Shop API endpoints (including query parameters).
     */
    public function buildShopUrl(string $path, array $queryParams = []): string
    {
        $timestamp = time();
        $sign = $this->generateShopSign($path, $timestamp);

        $params = array_merge([
            'partner_id' => $this->setting->partner_id,
            'timestamp' => $timestamp,
            'access_token' => $this->setting->access_token,
            'shop_id' => $this->setting->shop_id,
            'sign' => $sign,
        ], $queryParams);

        return $this->getBaseUrl().$path.'?'.http_build_query($params);
    }

    /**
     * Update product stock to Shopee Open API v2.
     */
    public function updateStock(int $itemId, int $stock, ?int $modelId = null): array
    {
        if (! $this->setting->isConnected()) {
            return [
                'success' => true,
                'simulated' => true,
                'message' => 'Simulasi: Stok berhasil diperbarui di Shopee (Sandbox/Simulation Mode).',
            ];
        }

        // Ensure token is fresh
        $this->ensureValidToken();

        $path = '/api/v2/product/update_stock';
        $url = $this->buildShopUrl($path);

        $stockList = [
            [
                'model_id' => $modelId ?? 0,
                'normal_stock' => $stock,
            ],
        ];

        try {
            $response = Http::timeout(15)->post($url, [
                'item_id' => $itemId,
                'stock_list' => $stockList,
            ]);

            $data = $response->json();

            // If error is auth expired, attempt 1 refresh and retry
            if (($data['error'] ?? '') === 'error_auth' && ! empty($this->setting->refresh_token)) {
                Log::warning('Shopee update_stock returned error_auth. Refreshing token and retrying...');
                $this->refreshAccessToken();
                $this->setting->refresh();
                $retryUrl = $this->buildShopUrl($path);
                $response = Http::timeout(15)->post($retryUrl, [
                    'item_id' => $itemId,
                    'stock_list' => $stockList,
                ]);
                $data = $response->json();
            }

            if ($response->successful() && empty($data['error'])) {
                return ['success' => true, 'data' => $data];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal update stok ke Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee update stock error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get order list from Shopee Open API v2.
     */
    public function getOrderList(int $timeFrom, int $timeTo, int $pageSize = 50, ?string $cursor = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/order/get_order_list';
        $queryParams = [
            'time_range_field' => 'create_time',
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'page_size' => min(100, max(1, $pageSize)),
        ];

        if ($cursor !== null && $cursor !== '') {
            $queryParams['cursor'] = $cursor;
        }

        $url = $this->buildShopUrl($path, $queryParams);

        try {
            $response = Http::timeout(15)->get($url);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return [
                    'success' => true,
                    'data' => $data['response'] ?? [],
                ];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengambil daftar order Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee getOrderList error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get detailed info for up to 50 orders from Shopee.
     */
    public function getOrderDetail(array $orderSns): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        if (empty($orderSns)) {
            return ['success' => true, 'data' => ['order_list' => []]];
        }

        $this->ensureValidToken();

        $path = '/api/v2/order/get_order_detail';
        $queryParams = [
            'order_sn_list' => implode(',', array_slice($orderSns, 0, 50)),
            'response_optional_fields' => 'item_list,buyer_username,total_amount,recipient_address,shipping_carrier,pay_time',
        ];

        $url = $this->buildShopUrl($path, $queryParams);

        try {
            $response = Http::timeout(20)->get($url);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return [
                    'success' => true,
                    'data' => $data['response'] ?? [],
                ];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengambil detail order Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee getOrderDetail error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get logistics tracking information for an order or tracking number.
     */
    public function getTrackingInfo(string $orderSn, ?string $packageNumber = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/logistics/get_tracking_info';
        $queryParams = ['order_sn' => $orderSn];
        if ($packageNumber) {
            $queryParams['package_number'] = $packageNumber;
        }

        $url = $this->buildShopUrl($path, $queryParams);

        try {
            $response = Http::timeout(15)->get($url);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return [
                    'success' => true,
                    'data' => $data['response'] ?? [],
                ];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengambil info tracking Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee getTrackingInfo error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get tracking number (nomor resi) from Shopee logistics after arranging shipment.
     */
    public function getTrackingNumber(string $orderSn, ?string $packageNumber = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/logistics/get_tracking_number';
        $queryParams = ['order_sn' => $orderSn];
        if ($packageNumber) {
            $queryParams['package_number'] = $packageNumber;
        }

        $url = $this->buildShopUrl($path, $queryParams);

        try {
            $response = Http::timeout(15)->get($url);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return [
                    'success' => true,
                    'data' => $data['response'] ?? [],
                ];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengambil nomor resi dari Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee getTrackingNumber error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get item list from Shopee Store.
     */
    public function getItemList(int $offset = 0, int $pageSize = 50, string $status = 'NORMAL'): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/product/get_item_list';
        $queryParams = [
            'offset' => $offset,
            'page_size' => min(100, max(1, $pageSize)),
            'item_status' => $status,
        ];

        $url = $this->buildShopUrl($path, $queryParams);

        try {
            $response = Http::timeout(15)->get($url);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return ['success' => true, 'data' => $data['response'] ?? []];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengambil daftar produk Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee getItemList error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get basic info for list of Item IDs.
     */
    public function getItemBaseInfo(array $itemIds): array
    {
        if (! $this->setting->isConnected() || empty($itemIds)) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung atau daftar Item ID kosong'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/product/get_item_base_info';
        $queryParams = [
            'item_id_list' => implode(',', array_slice($itemIds, 0, 50)),
        ];

        $url = $this->buildShopUrl($path, $queryParams);

        try {
            $response = Http::timeout(15)->get($url);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return ['success' => true, 'data' => $data['response'] ?? []];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengambil info produk Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee getItemBaseInfo error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pull recent orders from Shopee and synchronize with warehouse stock.
     */
    public function pullAndSyncOrders(int $days = 3, ?StockMutationService $mutationService = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $mutationService = $mutationService ?? new StockMutationService($this);
        $timeTo = time();
        $timeFrom = $timeTo - ($days * 86400);

        // Fetch Order List
        $listResult = $this->getOrderList($timeFrom, $timeTo, 50);
        if (! $listResult['success']) {
            return $listResult;
        }

        $orderEntries = $listResult['data']['order_list'] ?? [];
        if (empty($orderEntries)) {
            return [
                'success' => true,
                'synced_count' => 0,
                'message' => 'Tidak ada pesanan Shopee baru dalam rentang waktu yang dipilih.',
                'orders' => [],
            ];
        }

        $orderSns = array_column($orderEntries, 'order_sn');

        // Fetch Details for these orders
        $detailResult = $this->getOrderDetail($orderSns);
        if (! $detailResult['success']) {
            return $detailResult;
        }

        $detailedOrders = $detailResult['data']['order_list'] ?? [];
        $syncedOrders = [];

        foreach ($detailedOrders as $orderDetail) {
            $orderSn = $orderDetail['order_sn'] ?? null;
            if (! $orderSn) {
                continue;
            }

            // Map item list from Shopee format
            $rawItemList = $orderDetail['item_list'] ?? [];
            $items = [];
            foreach ($rawItemList as $rawItem) {
                $items[] = [
                    'item_id' => $rawItem['item_id'] ?? null,
                    'item_sku' => $rawItem['item_sku'] ?? null,
                    'item_name' => $rawItem['item_name'] ?? 'Shopee Item',
                    'model_id' => $rawItem['model_id'] ?? null,
                    'model_quantity_purchased' => $rawItem['model_quantity_purchased'] ?? 1,
                    'model_discounted_price' => $rawItem['model_discounted_price'] ?? 0,
                ];
            }

            $orderData = [
                'order_sn' => $orderSn,
                'shop_id' => (int) $this->setting->shop_id,
                'order_status' => $orderDetail['order_status'] ?? 'READY_TO_SHIP',
                'total_amount' => $orderDetail['total_amount'] ?? 0,
                'buyer_username' => $orderDetail['buyer_username'] ?? 'Shopee Customer',
                'tracking_number' => $orderDetail['tracking_number'] ?? ($orderDetail['package_list'][0]['tracking_number'] ?? null),
                'shipping_carrier' => $orderDetail['shipping_carrier'] ?? null,
                'items' => $items,
            ];

            // If tracking_number not in order_detail, try to fetch it if needed
            if (empty($orderData['tracking_number'])) {
                $trackingResult = $this->getTrackingNumber($orderSn);
                if ($trackingResult['success'] && ! empty($trackingResult['data']['tracking_number'])) {
                    $orderData['tracking_number'] = $trackingResult['data']['tracking_number'];
                }
            }

            $processed = $mutationService->processShopeeOrder($orderData);

            // Update tracking info on order if available
            if (! empty($orderData['tracking_number']) && empty($processed->tracking_number)) {
                $processed->update([
                    'tracking_number' => $orderData['tracking_number'],
                    'shipping_carrier' => $orderData['shipping_carrier'] ?? $processed->shipping_carrier,
                ]);
            }

            $syncedOrders[] = $processed;
        }

        return [
            'success' => true,
            'synced_count' => count($syncedOrders),
            'message' => 'Berhasil mensinkronkan '.count($syncedOrders).' pesanan dari Shopee.',
            'orders' => $syncedOrders,
        ];
    }

    /**
     * Verify Webhook Push Notification Signature from Shopee.
     */
    public function verifyWebhookSignature(string $fullUrl, string $rawBody, string $receivedSignature): bool
    {
        if (empty($this->setting->partner_key)) {
            return true; // Bypass in demo mode if no key configured
        }

        $calculatedSign = hash_hmac('sha256', $fullUrl.'|'.$rawBody, (string) $this->setting->partner_key);

        return hash_equals($calculatedSign, $receivedSignature);
    }
}
