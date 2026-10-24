<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use Exception;
use Illuminate\Support\Facades\Cache;
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
            : 'https://openplatform.sandbox.test-stable.shopee.sg';
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
    public function getAccessToken(string $code, ?int $shopId = null, ?int $mainAccountId = null): array
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

        $payload = [
            'code' => $code,
            'partner_id' => (int) $this->setting->partner_id,
        ];

        if (! empty($shopId)) {
            $payload['shop_id'] = $shopId;
        }

        if (! empty($mainAccountId)) {
            $payload['main_account_id'] = $mainAccountId;
        }

        $response = Http::timeout(15)->post($url, $payload);

        $data = $response->json();

        if ($response->successful() && empty($data['error'])) {
            $updateData = [
                'access_token' => $data['access_token'] ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'expire_in' => $data['expire_in'] ?? null,
                'token_expires_at' => now()->addSeconds(($data['expire_in'] ?? 14400) - 300),
            ];

            if (! empty($shopId)) {
                $updateData['shop_id'] = $shopId;
            }

            $this->setting->update($updateData);

            return ['success' => true, 'data' => $data];
        }

        Log::error('Shopee getAccessToken failed', [
            'status' => $response->status(),
            'response' => $data,
            'partner_id' => $this->setting->partner_id,
            'environment' => $this->setting->environment,
            'shop_id' => $shopId,
            'main_account_id' => $mainAccountId,
        ]);

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

        // Build stock list with seller_stock (required for Indonesian IDZ shops)
        $stockList = [
            [
                'model_id' => $modelId ?? 0,
                'seller_stock' => [
                    [
                        'location_id' => 'IDZ',
                        'stock' => $stock,
                    ],
                ],
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

            // If seller_stock is rejected because shop uses normal_stock, fallback to normal_stock
            $hasFailure = ! empty($data['error']) || ! empty($data['response']['failure_list']);
            if ($hasFailure && ! str_contains(strtolower($data['error'] ?? ''), 'auth')) {
                $normalStockList = [
                    [
                        'model_id' => $modelId ?? 0,
                        'normal_stock' => $stock,
                    ],
                ];
                $fallbackUrl = $this->buildShopUrl($path);
                $fallbackResponse = Http::timeout(15)->post($fallbackUrl, [
                    'item_id' => $itemId,
                    'stock_list' => $normalStockList,
                ]);
                $fallbackData = $fallbackResponse->json();
                if ($fallbackResponse->successful() && empty($fallbackData['error']) && empty($fallbackData['response']['failure_list'])) {
                    Cache::put('shopee_live_products_sync_lock', time(), now()->addSeconds(60));

                    return ['success' => true, 'data' => $fallbackData];
                }
            }

            if ($response->successful() && empty($data['error']) && empty($data['response']['failure_list'])) {
                Cache::put('shopee_live_products_sync_lock', time(), now()->addSeconds(60));

                return ['success' => true, 'data' => $data];
            }

            $errorMessage = $data['message'] ?? $data['error'] ?? '';
            if (! empty($data['response']['failure_list'][0]['failed_reason'])) {
                $errorMessage .= ' ('.$data['response']['failure_list'][0]['failed_reason'].')';
            }

            return ['success' => false, 'error' => $errorMessage ?: 'Gagal update stok ke Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee update stock error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update product price in Shopee Open API v2.
     */
    public function updatePrice(int $itemId, float $price, ?int $modelId = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/product/update_price';
        $url = $this->buildShopUrl($path);

        $priceList = [
            [
                'model_id' => $modelId ?? 0,
                'original_price' => (float) $price,
            ],
        ];

        try {
            $response = Http::timeout(15)->post($url, [
                'item_id' => $itemId,
                'price_list' => $priceList,
            ]);

            $data = $response->json();

            // Auto retry on auth error
            if (($data['error'] ?? '') === 'error_auth' && ! empty($this->setting->refresh_token)) {
                Log::warning('Shopee update_price returned error_auth. Refreshing token and retrying...');
                $this->refreshAccessToken();
                $this->setting->refresh();
                $retryUrl = $this->buildShopUrl($path);
                $response = Http::timeout(15)->post($retryUrl, [
                    'item_id' => $itemId,
                    'price_list' => $priceList,
                ]);
                $data = $response->json();
            }

            if ($response->successful() && empty($data['error']) && empty($data['response']['failure_list'])) {
                return ['success' => true, 'data' => $data];
            }

            $errorMessage = $data['message'] ?? $data['error'] ?? '';
            if (! empty($data['response']['failure_list'][0]['failed_reason'])) {
                $errorMessage .= ' ('.$data['response']['failure_list'][0]['failed_reason'].')';
            }

            return ['success' => false, 'error' => $errorMessage ?: 'Gagal update harga ke Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee update price error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update product basic info (item_name, description, weight, dimensions) in Shopee Open API v2.
     */
    public function updateItem(int $itemId, array $data): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/product/update_item';
        $url = $this->buildShopUrl($path);

        $payload = array_merge(['item_id' => $itemId], $data);

        try {
            $response = Http::timeout(15)->post($url, $payload);
            $resData = $response->json();

            if (($resData['error'] ?? '') === 'error_auth' && ! empty($this->setting->refresh_token)) {
                $this->refreshAccessToken();
                $this->setting->refresh();
                $retryUrl = $this->buildShopUrl($path);
                $response = Http::timeout(15)->post($retryUrl, $payload);
                $resData = $response->json();
            }

            if ($response->successful() && empty($resData['error'])) {
                return ['success' => true, 'data' => $resData];
            }

            return ['success' => false, 'error' => $resData['message'] ?? $resData['error'] ?? 'Gagal update info produk ke Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee update item error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Unlist (hide) or Re-list product in Shopee Open API v2.
     */
    public function unlistProduct(int $itemId, bool $unlist = true): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/product/unlist_item';
        $url = $this->buildShopUrl($path);

        $payload = [
            'item_list' => [
                [
                    'item_id' => $itemId,
                    'unlist' => $unlist,
                ],
            ],
        ];

        try {
            $response = Http::timeout(15)->post($url, $payload);
            $resData = $response->json();

            if ($response->successful() && empty($resData['error']) && empty($resData['response']['failure_list'])) {
                return ['success' => true, 'data' => $resData];
            }

            return ['success' => false, 'error' => $resData['message'] ?? $resData['error'] ?? 'Gagal mengubah status tampil produk di Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee unlist product error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete product in Shopee Open API v2.
     */
    public function deleteProduct(int $itemId): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/product/delete_item';
        $url = $this->buildShopUrl($path);

        try {
            $response = Http::timeout(15)->post($url, ['item_id' => $itemId]);
            $resData = $response->json();

            if ($response->successful() && empty($resData['error'])) {
                return ['success' => true, 'data' => $resData];
            }

            return ['success' => false, 'error' => $resData['message'] ?? $resData['error'] ?? 'Gagal menghapus produk di Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee delete product error: '.$e->getMessage());

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
            'response_optional_fields' => 'item_list,buyer_username,total_amount,recipient_address,shipping_carrier,pay_time,package_list',
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
     * Arrange shipment (ship order) in Shopee Open API v2.
     */
    public function shipOrder(string $orderSn, array $shippingParams = []): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/logistics/ship_order';
        $url = $this->buildShopUrl($path);

        $payload = array_merge([
            'order_sn' => $orderSn,
            'dropoff' => (object) [],
        ], $shippingParams);

        try {
            $response = Http::timeout(15)->post($url, $payload);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return ['success' => true, 'data' => $data];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengatur pengiriman di Shopee'];
        } catch (Exception $e) {
            Log::error("Shopee shipOrder error for {$orderSn}: ".$e->getMessage());

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
     * Get model / variation list for an item.
     */
    public function getModelList(int $itemId): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();

        $path = '/api/v2/product/get_model_list';
        $queryParams = ['item_id' => $itemId];
        $url = $this->buildShopUrl($path, $queryParams);

        try {
            $response = Http::timeout(15)->get($url);
            $data = $response->json();

            if ($response->successful() && empty($data['error'])) {
                return ['success' => true, 'data' => $data['response'] ?? []];
            }

            return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Gagal mengambil daftar model Shopee'];
        } catch (Exception $e) {
            Log::error('Shopee getModelList error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pull all products and stock from Shopee and synchronize with warehouse product catalog.
     */
    public function pullAndSyncProducts(?StockMutationService $mutationService = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung. Silakan lakukan otorisasi OAuth terlebih dahulu.'];
        }

        $this->ensureValidToken();

        // 1. Fetch item list
        $offset = 0;
        $allItemIds = [];

        do {
            $listResult = $this->getItemList($offset, 50, 'NORMAL');
            if (! $listResult['success']) {
                return $listResult;
            }

            $items = $listResult['data']['item'] ?? [];
            foreach ($items as $it) {
                if (isset($it['item_id'])) {
                    $allItemIds[] = (int) $it['item_id'];
                }
            }

            $hasNext = $listResult['data']['has_next_page'] ?? false;
            $offset += 50;
        } while ($hasNext && count($allItemIds) < 200);

        if (empty($allItemIds)) {
            return [
                'success' => true,
                'synced_count' => 0,
                'message' => 'Tidak ditemukan produk aktif di toko Shopee Anda.',
                'products' => [],
            ];
        }

        // 2. Fetch base info for all items in chunks of 50
        $chunks = array_chunk($allItemIds, 50);
        $syncedProducts = [];

        foreach ($chunks as $chunk) {
            $baseResult = $this->getItemBaseInfo($chunk);
            if (! $baseResult['success']) {
                continue;
            }

            $itemList = $baseResult['data']['item_list'] ?? [];
            foreach ($itemList as $item) {
                $itemId = (int) $item['item_id'];
                $name = $item['item_name'] ?? 'Produk Shopee';
                $sku = ! empty($item['item_sku']) ? trim($item['item_sku']) : 'SHP-'.$itemId;
                $sellingPrice = (float) ($item['price_info'][0]['current_price'] ?? 0);
                $purchasePrice = round($sellingPrice * 0.7);

                // Stock resolution from stock_info_v2 or model_list
                $stock = null;
                $modelId = null;

                if (! empty($item['has_model'])) {
                    $modelRes = $this->getModelList($itemId);
                    if ($modelRes['success'] && ! empty($modelRes['data']['model'])) {
                        $firstModel = $modelRes['data']['model'][0];
                        $modelId = (int) $firstModel['model_id'];
                        if (isset($firstModel['stock_info_v2']['seller_stock'][0]['stock'])) {
                            $stock = (int) $firstModel['stock_info_v2']['seller_stock'][0]['stock'];
                        } elseif (isset($firstModel['stock_info_v2']['summary_info']['total_available_stock'])) {
                            $stock = (int) $firstModel['stock_info_v2']['summary_info']['total_available_stock'];
                        }
                    }
                }

                if ($stock === null) {
                    if (isset($item['stock_info_v2']['summary_info']['total_available_stock'])) {
                        $stock = (int) $item['stock_info_v2']['summary_info']['total_available_stock'];
                    } elseif (isset($item['stock_info_v2']['seller_stock'][0]['stock'])) {
                        $stock = (int) $item['stock_info_v2']['seller_stock'][0]['stock'];
                    } else {
                        $stock = 0;
                    }
                }

                $image = $item['image']['image_url_list'][0] ?? null;

                // Match existing product by shopee_item_id or sku
                $product = Product::where('shopee_item_id', $itemId)
                    ->orWhere('sku', $sku)
                    ->first();

                if ($product) {
                    $oldStock = $product->stock;
                    $product->update([
                        'name' => $name,
                        'image' => $image ?? $product->image,
                        'shopee_item_id' => $itemId,
                        'shopee_model_id' => $modelId ?? $product->shopee_model_id,
                        'shopee_stock' => $stock,
                        'selling_price' => $sellingPrice > 0 ? $sellingPrice : $product->selling_price,
                        'stock' => $stock,
                    ]);

                    if ($oldStock !== $stock) {
                        $diff = $stock - $oldStock;
                        StockMutation::create([
                            'product_id' => $product->id,
                            'reference_no' => 'SYNC-SHP-'.$itemId.'-'.time(),
                            'type' => $diff > 0 ? 'inbound' : 'outbound',
                            'qty' => $diff,
                            'stock_before' => $oldStock,
                            'stock_after' => $stock,
                            'notes' => 'Sinkronisasi update stok dari Shopee Open API',
                            'actor' => 'Shopee Sync',
                        ]);
                    }
                } else {
                    $product = Product::create([
                        'sku' => $sku,
                        'barcode' => '899'.str_pad((string) ($itemId % 100000000), 9, '0', STR_PAD_LEFT),
                        'name' => $name,
                        'image' => $image,
                        'category' => 'Shopee Marketplace',
                        'unit' => 'Pcs',
                        'purchase_price' => $purchasePrice,
                        'selling_price' => $sellingPrice,
                        'stock' => $stock,
                        'safety_stock' => 5,
                        'shopee_item_id' => $itemId,
                        'shopee_model_id' => $modelId,
                        'shopee_stock' => $stock,
                        'status' => 'active',
                    ]);

                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_no' => 'INB-SHP-'.$product->sku,
                        'type' => 'inbound',
                        'qty' => $stock,
                        'stock_before' => 0,
                        'stock_after' => $stock,
                        'notes' => "Import awal produk & stok dari Shopee Open API (Item #{$itemId})",
                        'actor' => 'Shopee Sync',
                    ]);
                }

                $syncedProducts[] = $product;
            }
        }

        return [
            'success' => true,
            'synced_count' => count($syncedProducts),
            'message' => 'Berhasil mensinkronkan '.count($syncedProducts).' produk dari Shopee.',
            'products' => $syncedProducts,
        ];
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

        // Fetch Order List with cursor pagination to cover all order pages
        $cursor = null;
        $orderEntries = [];
        do {
            $listResult = $this->getOrderList($timeFrom, $timeTo, 100, $cursor);
            if (! $listResult['success']) {
                if (empty($orderEntries)) {
                    return $listResult;
                }
                break;
            }

            $entries = $listResult['data']['order_list'] ?? [];
            if (empty($entries)) {
                break;
            }

            foreach ($entries as $e) {
                $orderEntries[] = $e;
            }

            $hasMore = ! empty($listResult['data']['more']);
            $cursor = $listResult['data']['next_cursor'] ?? null;
        } while ($hasMore && count($orderEntries) < 1000);

        if (empty($orderEntries)) {
            return [
                'success' => true,
                'synced_count' => 0,
                'message' => 'Tidak ada pesanan Shopee baru dalam rentang waktu yang dipilih.',
                'orders' => [],
            ];
        }

        $orderSns = array_values(array_unique(array_column($orderEntries, 'order_sn')));

        // Fetch Details for these orders in chunks of 50 (Shopee API limit)
        $detailedOrders = [];
        foreach (array_chunk($orderSns, 50) as $chunkSns) {
            $detailResult = $this->getOrderDetail($chunkSns);
            if ($detailResult['success'] && ! empty($detailResult['data']['order_list'])) {
                foreach ($detailResult['data']['order_list'] as $det) {
                    $detailedOrders[] = $det;
                }
            }
        }
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
                    'model_name' => $rawItem['model_name'] ?? null,
                    'model_quantity_purchased' => $rawItem['model_quantity_purchased'] ?? 1,
                    'model_discounted_price' => $rawItem['model_discounted_price'] ?? 0,
                    'image_url' => $rawItem['image_info']['image_url'] ?? null,
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

            // If tracking_number not in order_detail, check local database first before hitting Shopee API
            if (empty($orderData['tracking_number'])) {
                $existingOrder = ShopeeOrder::where('order_sn', $orderSn)->first(['id', 'tracking_number', 'shipping_carrier']);
                if ($existingOrder && ! empty($existingOrder->tracking_number)) {
                    $orderData['tracking_number'] = $existingOrder->tracking_number;
                    if (empty($orderData['shipping_carrier']) && ! empty($existingOrder->shipping_carrier)) {
                        $orderData['shipping_carrier'] = $existingOrder->shipping_carrier;
                    }
                } elseif (in_array($orderData['order_status'], ['PROCESSED', 'COMPLETED']) || (! empty($orderDetail['package_list'][0]['logistics_status']) && in_array($orderDetail['package_list'][0]['logistics_status'], ['LOGISTICS_REQUEST_CREATED', 'LOGISTICS_PICKUP_DONE', 'LOGISTICS_DELIVERY_DONE']))) {
                    // Only query Shopee Logistics API if tracking number is missing locally and shipment was actually booked/processed
                    $trackingResult = $this->getTrackingNumber($orderSn);
                    if ($trackingResult['success'] && ! empty($trackingResult['data']['tracking_number'])) {
                        $orderData['tracking_number'] = $trackingResult['data']['tracking_number'];
                    }
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
     * Pull missing active orders (READY_TO_SHIP & PROCESSED) incrementally in batches.
     * Designed for safe background execution with cooldown to avoid Shopee API rate limits.
     */
    public function syncActiveOrdersIncremental(int $batchLimit = 50, ?StockMutationService $mutationService = null, int $daysWindow = 3): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $this->ensureValidToken();
        $mutationService = $mutationService ?? new StockMutationService($this);

        $timeTo = time();
        $timeFrom = $timeTo - (max(1, $daysWindow) * 86400);

        $allEntries = [];

        // 1. Capture recent updates across ALL statuses (SHIPPED, COMPLETED, CANCELLED, etc.) in last 2 hours
        $recentUpdateFrom = $timeTo - 7200;
        $updateCursor = null;
        do {
            $params = [
                'time_range_field' => 'update_time',
                'time_from' => $recentUpdateFrom,
                'time_to' => $timeTo,
                'page_size' => 100,
            ];
            if ($updateCursor) {
                $params['cursor'] = $updateCursor;
            }

            $url = $this->buildShopUrl('/api/v2/order/get_order_list', $params);
            $res = Http::timeout(15)->get($url)->json();

            $entries = $res['response']['order_list'] ?? [];
            foreach ($entries as $e) {
                if (! empty($e['order_sn'])) {
                    $allEntries[$e['order_sn']] = $e;
                }
            }
            $updateCursor = $res['response']['next_cursor'] ?? null;
            $more = ! empty($res['response']['more']);
        } while ($more);

        // 2. Capture active orders (READY_TO_SHIP & PROCESSED) across $daysWindow days
        foreach (['READY_TO_SHIP', 'PROCESSED'] as $status) {
            $cursor = null;
            do {
                $params = [
                    'time_range_field' => 'create_time',
                    'time_from' => $timeFrom,
                    'time_to' => $timeTo,
                    'page_size' => 100,
                    'order_status' => $status,
                ];
                if ($cursor) {
                    $params['cursor'] = $cursor;
                }

                $url = $this->buildShopUrl('/api/v2/order/get_order_list', $params);
                $res = Http::timeout(15)->get($url)->json();

                $entries = $res['response']['order_list'] ?? [];
                foreach ($entries as $e) {
                    if (! empty($e['order_sn'])) {
                        $allEntries[$e['order_sn']] = $e;
                    }
                }
                $cursor = $res['response']['next_cursor'] ?? null;
                $more = ! empty($res['response']['more']);
            } while ($more);
        }

        $allSns = array_keys($allEntries);

        if (empty($allSns)) {
            return [
                'success' => true,
                'total_active' => 0,
                'synced_this_batch' => 0,
                'missing_remaining' => 0,
                'message' => 'Tidak ada pesanan aktif Shopee yang ditemukan.',
            ];
        }

        // Find which orders already exist in local DB with tracking numbers and items (chunked to keep SQL small)
        $completeSns = [];
        foreach (array_chunk($allSns, 500) as $snChunk) {
            $chunkComplete = ShopeeOrder::whereIn('order_sn', $snChunk)
                ->whereNotNull('items_data')
                ->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNotNull('tracking_number')->where('tracking_number', '!=', '');
                    })->orWhere('updated_at', '>=', now()->subMinutes(15));
                })
                ->pluck('order_sn')
                ->toArray();
            $completeSns = array_merge($completeSns, $chunkComplete);
        }

        $missingSns = array_values(array_diff($allSns, $completeSns));

        if (empty($missingSns)) {
            return [
                'success' => true,
                'total_active' => count($allSns),
                'synced_this_batch' => 0,
                'missing_remaining' => 0,
                'message' => 'Semua pesanan aktif Shopee sudah 100% tersinkronisasi di database.',
            ];
        }

        // Process missing orders in chunks of 50 up to $batchLimit (default: 1000 orders)
        $targetLimit = max(50, $batchLimit);
        $ordersToSync = array_slice($missingSns, 0, $targetLimit);
        $chunks = array_chunk($ordersToSync, 50);

        $syncedCount = 0;
        foreach ($chunks as $chunk) {
            $detailResult = $this->getOrderDetail($chunk);

            if (! $detailResult['success'] || empty($detailResult['data']['order_list'])) {
                continue;
            }

            foreach ($detailResult['data']['order_list'] as $orderDetail) {
                $orderSn = $orderDetail['order_sn'] ?? null;
                if (! $orderSn) {
                    continue;
                }

                $rawItemList = $orderDetail['item_list'] ?? [];
                $items = [];
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

                $tracking = $orderDetail['tracking_number'] ?? ($orderDetail['package_list'][0]['tracking_number'] ?? null);

                $orderData = [
                    'order_sn' => $orderSn,
                    'shop_id' => (int) $this->setting->shop_id,
                    'order_status' => $orderDetail['order_status'] ?? 'READY_TO_SHIP',
                    'total_amount' => $orderDetail['total_amount'] ?? 0,
                    'buyer_username' => $orderDetail['buyer_username'] ?? 'Shopee Customer',
                    'tracking_number' => $tracking,
                    'shipping_carrier' => $orderDetail['shipping_carrier'] ?? ($orderDetail['package_list'][0]['shipping_carrier'] ?? null),
                    'items' => $items,
                ];

                if (empty($orderData['tracking_number'])) {
                    $existing = ShopeeOrder::where('order_sn', $orderSn)->first(['tracking_number', 'shipping_carrier']);
                    if ($existing && ! empty($existing->tracking_number)) {
                        $orderData['tracking_number'] = $existing->tracking_number;
                        $orderData['shipping_carrier'] = $existing->shipping_carrier ?? $orderData['shipping_carrier'];
                    } elseif (in_array($orderData['order_status'], ['PROCESSED', 'COMPLETED'])) {
                        $trackRes = $this->getTrackingNumber($orderSn);
                        if ($trackRes['success'] && ! empty($trackRes['data']['tracking_number'])) {
                            $orderData['tracking_number'] = $trackRes['data']['tracking_number'];
                        }
                    }
                }

                try {
                    $processed = $mutationService->processShopeeOrder($orderData);
                    if (! empty($orderData['tracking_number']) && empty($processed->tracking_number)) {
                        $processed->update([
                            'tracking_number' => $orderData['tracking_number'],
                            'shipping_carrier' => $orderData['shipping_carrier'] ?? $processed->shipping_carrier,
                        ]);
                    }
                    $syncedCount++;
                } catch (\Throwable $e) {
                    $existing = ShopeeOrder::where('order_sn', $orderSn)->first();
                    if ($existing) {
                        $existing->update([
                            'order_status' => $orderData['order_status'] ?? $existing->order_status,
                            'tracking_number' => $orderData['tracking_number'] ?? $existing->tracking_number,
                            'shipping_carrier' => $orderData['shipping_carrier'] ?? $existing->shipping_carrier,
                        ]);
                        $syncedCount++;
                    } else {
                        Log::warning("Could not sync order {$orderSn}: ".$e->getMessage());
                    }
                }
            }
        }

        $remaining = count($missingSns) - $syncedCount;

        return [
            'success' => true,
            'total_active' => count($allSns),
            'synced_this_batch' => $syncedCount,
            'missing_remaining' => max(0, $remaining),
            'message' => "Berhasil mensinkronkan {$syncedCount} pesanan aktif. Sisa belum ditarik: {$remaining} pesanan.",
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

        $partnerKey = (string) $this->setting->partner_key;
        $calculatedSign = hash_hmac('sha256', $fullUrl.'|'.$rawBody, $partnerKey);

        if (hash_equals($calculatedSign, $receivedSignature)) {
            return true;
        }

        // Check if scheme mismatch (http vs https behind reverse proxy/tunnel)
        if (str_starts_with($fullUrl, 'http://')) {
            $httpsUrl = 'https://'.substr($fullUrl, 7);
            if (hash_equals(hash_hmac('sha256', $httpsUrl.'|'.$rawBody, $partnerKey), $receivedSignature)) {
                return true;
            }
        } elseif (str_starts_with($fullUrl, 'https://')) {
            $httpUrl = 'http://'.substr($fullUrl, 8);
            if (hash_equals(hash_hmac('sha256', $httpUrl.'|'.$rawBody, $partnerKey), $receivedSignature)) {
                return true;
            }
        }

        // Also check against configured route webhook URL
        try {
            $configuredWebhookUrl = route('shopee.webhook');
            if ($configuredWebhookUrl !== $fullUrl) {
                if (hash_equals(hash_hmac('sha256', $configuredWebhookUrl.'|'.$rawBody, $partnerKey), $receivedSignature)) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }

    /**
     * Live on-the-fly sync for products from Shopee Open API v2.
     */
    public function syncLiveProductsIfStale(int $seconds = 20, ?StockMutationService $mutationService = null, bool $force = false): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $cacheKey = 'shopee_live_products_sync_lock';
        if (! $force && Cache::has($cacheKey)) {
            return ['success' => true, 'cached' => true, 'message' => 'Data produk sudah sinkron dengan Shopee API terbaru.'];
        }

        try {
            Cache::put($cacheKey, time(), now()->addSeconds($seconds));
            $result = $this->pullAndSyncProducts($mutationService);
            $this->setting->update(['last_synced_at' => now()]);

            return $result;
        } catch (Exception $e) {
            Log::warning('Shopee auto live sync products error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Live on-the-fly sync for orders from Shopee Open API v2.
     */
    public function syncLiveOrdersIfStale(int $seconds = 20, int $days = 15, ?StockMutationService $mutationService = null, bool $force = false): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'Toko Shopee belum terhubung'];
        }

        $cacheKey = 'shopee_live_orders_sync_lock';
        if (! $force && Cache::has($cacheKey)) {
            return ['success' => true, 'cached' => true, 'message' => 'Data pesanan sudah sinkron dengan Shopee API terbaru.'];
        }

        try {
            Cache::put($cacheKey, time(), now()->addSeconds($seconds));
            $result = $this->pullAndSyncOrders($days, $mutationService);
            $this->setting->update(['last_synced_at' => now()]);

            return $result;
        } catch (Exception $e) {
            Log::warning('Shopee auto live sync orders error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Synchronize both products & orders live on-the-fly from Shopee Open API v2.
     */
    public function syncAllLiveIfStale(int $seconds = 20, ?StockMutationService $mutationService = null, bool $force = false): array
    {
        $productsResult = $this->syncLiveProductsIfStale($seconds, $mutationService, $force);
        $ordersResult = $this->syncLiveOrdersIfStale($seconds, 15, $mutationService, $force);

        return [
            'success' => ($productsResult['success'] ?? false) || ($ordersResult['success'] ?? false),
            'products' => $productsResult,
            'orders' => $ordersResult,
        ];
    }
}
