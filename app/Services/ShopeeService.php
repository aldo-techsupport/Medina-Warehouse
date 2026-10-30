<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShopeeService
{
    protected ShopeeSetting $setting;

    protected bool $isResettingMirror = false;

    public function __construct(?ShopeeSetting $setting = null)
    {
        $this->setting = ($setting !== null && ($setting->exists || ! empty($setting->getAttributes())))
            ? $setting
            : ShopeeSetting::current();
    }

    public function getSetting(): ShopeeSetting
    {
        return $this->setting;
    }

    public function reloadSetting(): self
    {
        $this->setting = ShopeeSetting::current()->fresh() ?? ShopeeSetting::current();

        return $this;
    }

    public function getApiUrl(): string
    {
        return $this->setting->getApiUrl();
    }

    /**
     * Build pre-authenticated HTTP client targeting the standalone OpenShopee API server.
     */
    protected function client(?int $timeout = null): PendingRequest
    {
        $maxTimeout = $timeout ?? (int) config('services.openshopee.timeout', 20);

        return Http::withOptions(['force_ip_resolve' => 'v4'])
            ->baseUrl($this->setting->getApiUrl())
            ->connectTimeout(4)
            ->timeout(max(5, $maxTimeout))
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $this->setting->getApiKey(),
            ]);
    }

    /**
     * Synchronize live connection status & store metadata from OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function syncGatewayStatus(bool $force = false): array
    {
        if (! $this->setting->hasApiBridge()) {
            return [
                'success' => false,
                'error' => 'URL atau API Key OpenShopee API belum dikonfigurasi.',
            ];
        }

        $cacheKey = 'openshopee_gateway_status_ping';
        if (! $force && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->client(8)->get('/api/v1/status');
            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['success'])) {
                Cache::forget('openshopee_vps_maintenance_breaker');
                $status = $data['status'] ?? [];
                $this->applyRemoteStatusSnapshot($status);
                $this->flushPendingGatewayOutbox();

                $result = [
                    'success' => true,
                    'reachable' => true,
                    'status' => $status,
                    'counts' => $data['counts'] ?? [],
                ];

                Cache::put($cacheKey, $result, now()->addSeconds(12));

                return $result;
            }

            $result = [
                'success' => false,
                'reachable' => false,
                'maintenance' => $response->serverError(),
                'error' => $data['error'] ?? ('OpenShopee API merespons HTTP '.$response->status()),
            ];

            if ($response->serverError()) {
                Cache::put('openshopee_vps_maintenance_breaker', true, now()->addSeconds(15));
                Cache::put($cacheKey, $result, now()->addSeconds(15));
            }

            return $result;
        } catch (Throwable $e) {
            $result = [
                'success' => false,
                'reachable' => false,
                'maintenance' => true,
                'error' => 'Tidak dapat terhubung ke server OpenShopee API: '.$e->getMessage(),
            ];

            Cache::put('openshopee_vps_maintenance_breaker', true, now()->addSeconds(15));
            Cache::put($cacheKey, $result, now()->addSeconds(15));

            return $result;
        }
    }

    /**
     * Mirror status snapshot from OpenShopee API into local ShopeeSetting metadata.
     *
     * @param  array<string, mixed>  $status
     */
    public function applyRemoteStatusSnapshot(array $status): void
    {
        $incomingEnv = ! empty($status['environment']) ? (string) $status['environment'] : null;
        $previousEnv = $this->setting->environment ?: 'sandbox';
        $incomingShopId = ! empty($status['shop_id']) ? (int) $status['shop_id'] : null;
        $previousShopId = ! empty($this->setting->shop_id) ? (int) $this->setting->shop_id : null;

        $remoteContextSwitched = ! $this->isResettingMirror
            && (
                ($incomingEnv !== null && $incomingEnv !== $previousEnv)
                || ($incomingShopId !== null && $previousShopId !== null && $incomingShopId !== $previousShopId)
            )
            && ($this->setting->last_synced_at !== null || Product::query()->exists() || ShopeeOrder::query()->exists());

        $update = [
            'shop_name' => $status['shop_name'] ?? $this->setting->shop_name,
            'shop_id' => $status['shop_id'] ?? $this->setting->shop_id,
            'environment' => $status['environment'] ?? $this->setting->environment,
            'remote_connected' => (bool) ($status['is_connected'] ?? false),
            'auto_sync_stock' => array_key_exists('auto_sync_stock', $status)
                ? (bool) $status['auto_sync_stock']
                : $this->setting->auto_sync_stock,
            'remote_auth_url' => $status['auth_url'] ?? $this->setting->remote_auth_url,
            'remote_dashboard_url' => $status['dashboard_url'] ?? $this->setting->remote_dashboard_url,
            'token_expires_at' => ! empty($status['token_expires_at'])
                ? Carbon::parse($status['token_expires_at'])
                : null,
            'last_synced_at' => ! empty($status['last_synced_at'])
                ? Carbon::parse($status['last_synced_at'])
                : $this->setting->last_synced_at,
        ];

        $this->setting->update($update);

        if ($remoteContextSwitched) {
            $this->resetAndResyncFromApi();
        }
    }

    /**
     * Purge all locally mirrored products, stock mutations, Shopee orders, AI cached summaries,
     * and delta sync cursors so zero stale data remains when API Key / URL / Environment changes.
     */
    public function purgeLocalSyncedData(): void
    {
        DB::transaction(function () {
            StockMutation::query()->delete();
            Product::query()->delete();
            ShopeeOrder::query()->delete();
            AiAnalysis::query()->delete();

            $this->setting->update([
                'shop_id' => null,
                'remote_connected' => false,
                'token_expires_at' => null,
                'remote_auth_url' => null,
                'last_synced_at' => null,
            ]);
        });

        foreach ([
            'openshopee_delta_cursor_iso',
            'openshopee_gateway_status_ping',
            'openshopee_vps_maintenance_breaker',
            'openshopee_pending_stock_outbox',
            'shopee_live_products_sync_lock',
            'shopee_live_orders_sync_lock',
            'shopee_web_bg_sync_cooldown',
            'shopee_web_bg_active_sweep_lock',
            'shopee_product_sync_webhook_lock',
            'delayed_sidebar_count_badge',
            'courier_unique_carriers_list',
        ] as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Perform a clean full-state bootstrap from OpenShopee API (orders first so historical orders
     * do not double-deduct stock, followed by authoritative product catalog & live stock).
     *
     * @return array<string, mixed>
     */
    public function syncFullStateFromGateway(?StockMutationService $mutationService = null): array
    {
        if (! $this->setting->hasApiBridge()) {
            return [
                'success' => false,
                'error' => 'URL atau API Key OpenShopee API belum dikonfigurasi.',
            ];
        }

        $ping = $this->syncGatewayStatus(true);
        if (empty($ping['success'])) {
            return [
                'success' => false,
                'reachable' => false,
                'error' => $ping['error'] ?? 'OpenShopee API tidak dapat dijangkau.',
            ];
        }

        $this->reloadSetting();

        $pulledProductsFallback = null;
        $pulledOrdersFallback = null;

        if ($this->setting->isConnected()) {
            try {
                $pullProdRes = $this->client(25)->post('/api/v1/products/pull')->json() ?? [];
                if (! empty($pullProdRes['success']) && is_array($pullProdRes['products'] ?? null)) {
                    $pulledProductsFallback = $pullProdRes['products'];
                }
            } catch (Throwable $e) {
                Log::info('Initial live pullProducts skipped (using API buffer): '.$e->getMessage());
            }

            try {
                $pullOrdRes = $this->client(25)->post('/api/v1/orders/pull', ['days' => 7])->json() ?? [];
                if (! empty($pullOrdRes['success']) && is_array($pullOrdRes['orders'] ?? null)) {
                    $pulledOrdersFallback = $pullOrdRes['orders'];
                }
            } catch (Throwable $e) {
                Log::info('Initial live pullOrders skipped (using API buffer): '.$e->getMessage());
            }
        }

        $ordersPayload = [];
        $serverTime = now()->subSeconds(5)->toIso8601String();
        try {
            $ordersRes = $this->client(15)->get('/api/v1/orders', ['limit' => 500])->json() ?? [];
            if (! empty($ordersRes['success']) && is_array($ordersRes['orders'] ?? null)) {
                $ordersPayload = $ordersRes['orders'];
                $serverTime = (string) ($ordersRes['server_time'] ?? $serverTime);
            } elseif (is_array($pulledOrdersFallback)) {
                $ordersPayload = $pulledOrdersFallback;
            }
        } catch (Throwable $e) {
            if (is_array($pulledOrdersFallback)) {
                $ordersPayload = $pulledOrdersFallback;
            }
        }

        $productsPayload = [];
        try {
            $productsRes = $this->client(15)->get('/api/v1/products', ['limit' => 500])->json() ?? [];
            if (! empty($productsRes['success']) && is_array($productsRes['products'] ?? null)) {
                $productsPayload = $productsRes['products'];
            } elseif (is_array($pulledProductsFallback)) {
                $productsPayload = $pulledProductsFallback;
            }
        } catch (Throwable $e) {
            if (is_array($pulledProductsFallback)) {
                $productsPayload = $pulledProductsFallback;
            }
        }

        $syncedOrders = ! empty($ordersPayload)
            ? $this->applySyncedOrders($ordersPayload, $mutationService)
            : [];

        $syncedProducts = $this->applySyncedProducts($productsPayload, true);

        Cache::put('openshopee_delta_cursor_iso', $serverTime, now()->addHours(24));
        Cache::put('shopee_live_products_sync_lock', time(), now()->addSeconds(15));
        Cache::put('shopee_live_orders_sync_lock', time(), now()->addSeconds(15));

        $this->setting->update(['last_synced_at' => now()]);

        return [
            'success' => true,
            'reachable' => true,
            'products_synced' => count($syncedProducts),
            'orders_synced' => count($syncedOrders),
            'status' => $ping['status'] ?? [],
        ];
    }

    /**
     * Wipe all stale local warehouse data and rebuild cleanly from the active OpenShopee API state.
     *
     * @return array<string, mixed>
     */
    public function resetAndResyncFromApi(?StockMutationService $mutationService = null): array
    {
        $this->isResettingMirror = true;

        try {
            $this->purgeLocalSyncedData();
            $this->reloadSetting();

            if (! $this->setting->hasApiBridge()) {
                return [
                    'success' => false,
                    'purged' => true,
                    'products_synced' => 0,
                    'orders_synced' => 0,
                    'error' => 'Data lokal lama telah dibersihkan. Silakan masukkan API Key yang valid.',
                ];
            }

            $res = $this->syncFullStateFromGateway($mutationService);

            return array_merge($res, ['purged' => true]);
        } finally {
            $this->isResettingMirror = false;
        }
    }

    /**
     * Push optional non-secret preferences (shop_name, environment, auto_sync_stock) to OpenShopee API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateRemoteSettings(array $payload): array
    {
        if (! $this->setting->hasApiBridge()) {
            return ['success' => false, 'error' => 'Koneksi OpenShopee API belum diatur.'];
        }

        try {
            $response = $this->client(10)->put('/api/v1/settings', $payload);
            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['success'])) {
                if (! empty($data['status']) && is_array($data['status'])) {
                    $this->applyRemoteStatusSnapshot($data['status']);
                }

                return ['success' => true, 'data' => $data];
            }

            return ['success' => false, 'error' => $data['error'] ?? 'Gagal memperbarui pengaturan di OpenShopee API'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Shopee OAuth URL managed by OpenShopee API.
     */
    public function getAuthPartnerUrl(?string $redirectUrl = null): string
    {
        if (! empty($this->setting->remote_auth_url)) {
            return (string) $this->setting->remote_auth_url;
        }

        if (! $this->setting->hasApiBridge()) {
            return '';
        }

        try {
            $params = $redirectUrl ? ['redirect' => $redirectUrl] : [];
            $res = $this->client(8)->get('/api/v1/auth/url', $params)->json() ?? [];

            return (string) ($res['auth_url'] ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Request token refresh on OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function refreshAccessToken(): array
    {
        if (! $this->setting->hasApiBridge()) {
            return ['success' => false, 'error' => 'Koneksi OpenShopee API belum diatur.'];
        }

        try {
            $response = $this->client(15)->post('/api/v1/auth/refresh-token');
            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['success'])) {
                if (! empty($data['status'])) {
                    $this->applyRemoteStatusSnapshot($data['status']);
                }

                return ['success' => true, 'data' => $data];
            }

            return ['success' => false, 'error' => $data['error'] ?? 'Gagal refresh token di OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Disconnect Shopee OAuth session on OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function disconnectRemote(): array
    {
        if (! $this->setting->hasApiBridge()) {
            return ['success' => false, 'error' => 'Koneksi OpenShopee API belum diatur.'];
        }

        try {
            $response = $this->client(10)->post('/api/v1/auth/disconnect');
            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['success'])) {
                if (! empty($data['status'])) {
                    $this->applyRemoteStatusSnapshot($data['status']);
                } else {
                    $this->setting->update(['remote_connected' => false, 'token_expires_at' => null]);
                }

                return ['success' => true];
            }

            return ['success' => false, 'error' => $data['error'] ?? 'Gagal memutuskan koneksi di OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify connection health with OpenShopee API (token management runs on OpenShopee API).
     */
    public function ensureValidToken(): bool
    {
        if (! $this->setting->hasApiBridge()) {
            return false;
        }

        $status = $this->syncGatewayStatus();

        return ! empty($status['success']) && $this->setting->isConnected();
    }

    /**
     * Queue local stock/price update into persistent outbox when API VPS is undergoing maintenance,
     * and flush automatically once the API VPS is back online.
     */
    protected function queuePendingOutboxItem(int $itemId, ?int $stock = null, ?float $price = null, ?int $modelId = null): void
    {
        $outboxKey = 'openshopee_pending_stock_outbox';
        $outbox = Cache::get($outboxKey, []);
        if (! is_array($outbox)) {
            $outbox = [];
        }

        $entryKey = $itemId.':'.($modelId ?? 0);
        $existing = $outbox[$entryKey] ?? [
            'item_id' => $itemId,
            'model_id' => $modelId,
        ];

        if ($stock !== null) {
            $existing['stock'] = $stock;
        }
        if ($price !== null) {
            $existing['price'] = $price;
        }
        $existing['queued_at'] = now()->toIso8601String();

        $outbox[$entryKey] = $existing;
        Cache::put($outboxKey, $outbox, now()->addDays(7));
    }

    /**
     * Flush any queued stock/price mutations recorded while the API VPS was offline/in maintenance.
     */
    public function flushPendingGatewayOutbox(): int
    {
        $outboxKey = 'openshopee_pending_stock_outbox';
        $outbox = Cache::get($outboxKey, []);
        if (empty($outbox) || ! is_array($outbox) || ! $this->setting->isConnected()) {
            return 0;
        }

        $batchPayload = [];
        foreach ($outbox as $entry) {
            if (! isset($entry['item_id'])) {
                continue;
            }
            $localProduct = Product::query()->where('shopee_item_id', (int) $entry['item_id'])->first();
            $resolvedStock = $entry['stock'] ?? ($localProduct?->stock ?? null);
            if ($resolvedStock === null) {
                continue;
            }
            $item = [
                'item_id' => (int) $entry['item_id'],
                'stock' => (int) $resolvedStock,
            ];
            if (isset($entry['price'])) {
                $item['price'] = (float) $entry['price'];
            }
            if (! empty($entry['model_id'])) {
                $item['model_id'] = (int) $entry['model_id'];
            }
            $batchPayload[] = $item;
        }

        if (empty($batchPayload)) {
            Cache::forget($outboxKey);

            return 0;
        }

        try {
            $res = $this->client(20)->post('/api/v1/products/batch-sync', [
                'products' => $batchPayload,
            ]);

            if ($res->successful() && ! empty($res->json('success'))) {
                Cache::forget($outboxKey);

                return (int) ($res->json('synced_count') ?? count($batchPayload));
            }
        } catch (Throwable $e) {
            Log::warning('Deferred outbox flush to OpenShopee API postponed: '.$e->getMessage());
        }

        return 0;
    }

    /**
     * Push live stock update to OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function updateStock(int $itemId, int $stock, ?int $modelId = null): array
    {
        if (! $this->setting->isConnected()) {
            return [
                'success' => true,
                'simulated' => true,
                'message' => 'Simulasi: Stok siap disinkronkan saat OpenShopee API terhubung.',
            ];
        }

        try {
            $response = $this->client(15)->post('/api/v1/products/update-stock', [
                'item_id' => $itemId,
                'stock' => $stock,
                'model_id' => $modelId,
            ]);

            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['success'])) {
                Cache::put('shopee_live_products_sync_lock', time(), now()->addSeconds(30));

                return $data;
            }

            if ($response->serverError()) {
                $this->queuePendingOutboxItem($itemId, $stock, null, $modelId);
                Cache::put('openshopee_vps_maintenance_breaker', true, now()->addSeconds(15));
            }

            return ['success' => false, 'queued' => $response->serverError(), 'error' => $data['error'] ?? 'Gagal update stok melalui OpenShopee API'];
        } catch (Throwable $e) {
            Log::warning('OpenShopee API updateStock error (queued to outbox): '.$e->getMessage());
            $this->queuePendingOutboxItem($itemId, $stock, null, $modelId);
            Cache::put('openshopee_vps_maintenance_breaker', true, now()->addSeconds(15));

            return ['success' => false, 'queued' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * Push live price update to OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function updatePrice(int $itemId, float $price, ?int $modelId = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        try {
            $response = $this->client(15)->post('/api/v1/products/update-price', [
                'item_id' => $itemId,
                'price' => $price,
                'model_id' => $modelId,
            ]);

            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['success'])) {
                return $data;
            }

            if ($response->serverError()) {
                $this->queuePendingOutboxItem($itemId, null, $price, $modelId);
                Cache::put('openshopee_vps_maintenance_breaker', true, now()->addSeconds(15));
            }

            return ['success' => false, 'queued' => $response->serverError(), 'error' => $data['error'] ?? 'Gagal update harga melalui OpenShopee API'];
        } catch (Throwable $e) {
            Log::warning('OpenShopee API updatePrice error (queued to outbox): '.$e->getMessage());
            $this->queuePendingOutboxItem($itemId, null, $price, $modelId);
            Cache::put('openshopee_vps_maintenance_breaker', true, now()->addSeconds(15));

            return ['success' => false, 'queued' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * Push item basic info update to OpenShopee API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateItem(int $itemId, array $data): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        try {
            $response = $this->client(15)->post('/api/v1/products/update-item', [
                'item_id' => $itemId,
                'data' => $data,
            ]);

            $resData = $response->json() ?? [];

            return $response->successful() && ! empty($resData['success'])
                ? $resData
                : ['success' => false, 'error' => $resData['error'] ?? 'Gagal update info produk melalui OpenShopee API'];
        } catch (Exception $e) {
            Log::warning('OpenShopee API updateItem error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Unlist or Re-list product via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function unlistProduct(int $itemId, bool $unlist = true): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        try {
            $response = $this->client(15)->post('/api/v1/products/unlist', [
                'item_id' => $itemId,
                'unlist' => $unlist,
            ]);

            $resData = $response->json() ?? [];

            return $response->successful() && ! empty($resData['success'])
                ? $resData
                : ['success' => false, 'error' => $resData['error'] ?? 'Gagal mengubah status produk melalui OpenShopee API'];
        } catch (Exception $e) {
            Log::warning('OpenShopee API unlistProduct error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete product via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function deleteProduct(int $itemId): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        try {
            $response = $this->client(15)->post('/api/v1/products/delete', [
                'item_id' => $itemId,
            ]);

            $resData = $response->json() ?? [];

            return $response->successful() && ! empty($resData['success'])
                ? $resData
                : ['success' => false, 'error' => $resData['error'] ?? 'Gagal menghapus produk melalui OpenShopee API'];
        } catch (Exception $e) {
            Log::warning('OpenShopee API deleteProduct error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch live shop products via OpenShopee API for interactive mapping UI.
     *
     * @return array<string, mixed>
     */
    public function fetchLiveShopProducts(int $offset = 0, int $pageSize = 20): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        try {
            $response = $this->client(15)->get('/api/v1/products', [
                'live' => 1,
                'offset' => $offset,
                'page_size' => $pageSize,
            ]);

            $data = $response->json() ?? [];

            return $response->successful() && ! empty($data['success'])
                ? $data
                : ['success' => false, 'error' => $data['error'] ?? 'Gagal mengambil produk dari OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Apply normalized Shopee product payloads to local Warehouse catalog & stock mutations.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<Product>
     */
    public function applySyncedProducts(array $items, bool $reconcileMissing = false): array
    {
        $syncedProducts = [];
        $seenItemIds = [];

        foreach ($items as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $seenItemIds[] = $itemId;

            $name = (string) ($item['name'] ?? $item['item_name'] ?? 'Produk Shopee');
            $sku = ! empty($item['sku']) ? trim((string) $item['sku']) : 'SHP-'.$itemId;
            $sellingPrice = (float) ($item['selling_price'] ?? 0);
            $purchasePrice = round($sellingPrice * 0.7);
            $stock = (int) ($item['stock'] ?? 0);
            $modelId = ! empty($item['model_id']) ? (int) $item['model_id'] : null;
            $image = $item['image'] ?? null;
            $status = ($item['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

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
                    'status' => $status,
                ]);

                if ($oldStock !== $stock) {
                    $diff = $stock - $oldStock;
                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_no' => 'SYNC-SHP-'.$itemId.'-'.time().'-'.random_int(10, 99),
                        'type' => $diff > 0 ? 'inbound' : 'outbound',
                        'qty' => $diff,
                        'stock_before' => $oldStock,
                        'stock_after' => $stock,
                        'notes' => 'Sinkronisasi real-time stok dari OpenShopee API',
                        'actor' => 'OpenShopee API Sync',
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
                    'status' => $status,
                ]);

                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_no' => 'INB-SHP-'.$product->sku,
                    'type' => 'inbound',
                    'qty' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'notes' => "Import awal produk & stok dari OpenShopee API (Item #{$itemId})",
                    'actor' => 'OpenShopee API Sync',
                ]);
            }

            $syncedProducts[] = $product;
        }

        if ($reconcileMissing) {
            $cleanupQuery = Product::query()->whereNotNull('shopee_item_id');
            if (! empty($seenItemIds)) {
                $cleanupQuery->whereNotIn('shopee_item_id', $seenItemIds);
            }
            $cleanupQuery->delete();
        }

        return $syncedProducts;
    }

    /**
     * Apply normalized Shopee order payloads to local Warehouse database & stock mutations.
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @return list<ShopeeOrder>
     */
    public function applySyncedOrders(array $orders, ?StockMutationService $mutationService = null): array
    {
        $mutationService = $mutationService ?? new StockMutationService($this);
        $syncedOrders = [];

        foreach ($orders as $orderPayload) {
            $orderSn = $orderPayload['order_sn'] ?? null;
            if (! $orderSn) {
                continue;
            }

            $items = $orderPayload['items'] ?? ($orderPayload['items_data'] ?? []);
            $orderData = [
                'order_sn' => $orderSn,
                'shop_id' => $orderPayload['shop_id'] ?? $this->setting->shop_id,
                'order_status' => $orderPayload['order_status'] ?? 'READY_TO_SHIP',
                'total_amount' => $orderPayload['total_amount'] ?? 0,
                'buyer_username' => $orderPayload['buyer_username'] ?? 'Shopee Customer',
                'tracking_number' => $orderPayload['tracking_number'] ?? null,
                'shipping_carrier' => $orderPayload['shipping_carrier'] ?? null,
                'recipient_address' => $orderPayload['recipient_address'] ?? null,
                'items' => $items,
            ];

            if (! empty($orderData['recipient_address'])) {
                Cache::put("courier_order_address_{$orderSn}", $orderData['recipient_address'], now()->addDays(7));
            }

            $processed = $mutationService->processShopeeOrder($orderData);

            $extraUpdates = [];
            if (! empty($orderData['tracking_number']) && $processed->tracking_number !== $orderData['tracking_number']) {
                $extraUpdates['tracking_number'] = $orderData['tracking_number'];
            }
            if (! empty($orderData['shipping_carrier']) && $processed->shipping_carrier !== $orderData['shipping_carrier']) {
                $extraUpdates['shipping_carrier'] = $orderData['shipping_carrier'];
            }
            if (! empty($orderPayload['logistics_status'])) {
                $extraUpdates['logistics_status'] = $orderPayload['logistics_status'];
            }
            if (! empty($orderPayload['tracking_data'])) {
                $extraUpdates['tracking_data'] = $orderPayload['tracking_data'];
            }
            if (! empty($orderPayload['shipped_at']) && empty($processed->shipped_at)) {
                $extraUpdates['shipped_at'] = Carbon::parse($orderPayload['shipped_at']);
            }
            if (! empty($orderPayload['delivered_at']) && empty($processed->delivered_at)) {
                $extraUpdates['delivered_at'] = Carbon::parse($orderPayload['delivered_at']);
            }

            if (! empty($extraUpdates)) {
                $processed->update($extraUpdates);
            }

            $syncedOrders[] = $processed;
        }

        return $syncedOrders;
    }

    /**
     * Pull products and stock from OpenShopee API and synchronize with Warehouse catalog.
     *
     * @return array<string, mixed>
     */
    public function pullAndSyncProducts(?StockMutationService $mutationService = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee.'];
        }

        try {
            $response = $this->client(25)->post('/api/v1/products/pull');
            $data = $response->json() ?? [];

            if (! $response->successful() || empty($data['success'])) {
                return [
                    'success' => false,
                    'error' => $data['error'] ?? 'Gagal menarik produk dari OpenShopee API',
                ];
            }

            $reconciled = (bool) ($data['reconciled'] ?? $data['reconcile'] ?? false);
            $syncedProducts = $this->applySyncedProducts($data['products'] ?? [], $reconciled);
            $this->setting->update(['last_synced_at' => now()]);

            return [
                'success' => true,
                'synced_count' => count($syncedProducts),
                'message' => 'Berhasil mensinkronkan '.count($syncedProducts).' produk dari OpenShopee API.',
                'products' => $syncedProducts,
            ];
        } catch (Exception $e) {
            Log::warning('OpenShopee API pullAndSyncProducts error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pull recent orders from OpenShopee API and synchronize with Warehouse stock.
     *
     * @return array<string, mixed>
     */
    public function pullAndSyncOrders(int $days = 3, ?StockMutationService $mutationService = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee.'];
        }

        try {
            $response = $this->client(25)->post('/api/v1/orders/pull', ['days' => $days]);
            $data = $response->json() ?? [];

            if (! $response->successful() || empty($data['success'])) {
                return [
                    'success' => false,
                    'error' => $data['error'] ?? 'Gagal menarik pesanan dari OpenShopee API',
                ];
            }

            $syncedOrders = $this->applySyncedOrders($data['orders'] ?? [], $mutationService);
            $this->setting->update(['last_synced_at' => now()]);

            return [
                'success' => true,
                'synced_count' => count($syncedOrders),
                'message' => 'Berhasil mensinkronkan '.count($syncedOrders).' pesanan dari OpenShopee API.',
                'orders' => $syncedOrders,
            ];
        } catch (Exception $e) {
            Log::warning('OpenShopee API pullAndSyncOrders error: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fast delta synchronization from OpenShopee API buffer (for real-time UI polling).
     *
     * @return array<string, mixed>
     */
    public function syncDeltaFromGateway(?StockMutationService $mutationService = null): array
    {
        if (! $this->setting->hasApiBridge()) {
            return ['success' => false, 'error' => 'Koneksi OpenShopee API belum dikonfigurasi.'];
        }

        $cursorKey = 'openshopee_delta_cursor_iso';
        $updatedSince = Cache::get($cursorKey);

        try {
            $this->syncGatewayStatus();

            $params = $updatedSince ? ['updated_since' => $updatedSince, 'limit' => 100] : ['limit' => 100];
            $ordersRes = $this->client(10)->get('/api/v1/orders', $params)->json() ?? [];
            $productsRes = $this->client(10)->get('/api/v1/products', $params)->json() ?? [];

            $syncedOrders = [];
            if (! empty($ordersRes['success']) && ! empty($ordersRes['orders'])) {
                $syncedOrders = $this->applySyncedOrders($ordersRes['orders'], $mutationService);
            }

            $syncedProducts = [];
            if (! empty($productsRes['success']) && ! empty($productsRes['products'])) {
                $syncedProducts = $this->applySyncedProducts($productsRes['products']);
            }

            $nextCursor = $ordersRes['server_time'] ?? now()->subSeconds(5)->toIso8601String();
            Cache::put($cursorKey, $nextCursor, now()->addHours(24));

            if (! empty($syncedOrders) || ! empty($syncedProducts)) {
                $this->setting->update(['last_synced_at' => now()]);
            }

            return [
                'success' => true,
                'orders_synced' => count($syncedOrders),
                'products_synced' => count($syncedProducts),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Incremental active orders sync via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function syncActiveOrdersIncremental(int $batchLimit = 50, ?StockMutationService $mutationService = null, int $daysWindow = 3): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        try {
            $response = $this->client(25)->post('/api/v1/orders/sync-active', [
                'batch' => $batchLimit,
                'days' => $daysWindow,
            ]);

            $data = $response->json() ?? [];

            if (! $response->successful() || empty($data['success'])) {
                return [
                    'success' => false,
                    'error' => $data['error'] ?? 'Gagal sinkronisasi pesanan aktif dari OpenShopee API',
                ];
            }

            $syncedOrders = $this->applySyncedOrders($data['orders'] ?? [], $mutationService);
            $this->setting->update(['last_synced_at' => now()]);

            return [
                'success' => true,
                'total_active' => $data['total_active'] ?? count($syncedOrders),
                'synced_this_batch' => count($syncedOrders),
                'missing_remaining' => $data['missing_remaining'] ?? 0,
                'message' => $data['message'] ?? ('Berhasil mensinkronkan '.count($syncedOrders).' pesanan aktif.'),
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get detailed info for orders via OpenShopee API.
     *
     * @param  array<int, string>  $orderSns
     * @return array<string, mixed>
     */
    public function getOrderDetail(array $orderSns): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung'];
        }

        if (empty($orderSns)) {
            return ['success' => true, 'data' => ['order_list' => []]];
        }

        try {
            $response = $this->client(20)->post('/api/v1/orders/detail', [
                'order_sn_list' => array_values(array_slice($orderSns, 0, 50)),
            ]);

            $data = $response->json() ?? [];

            return $response->successful() && ! empty($data['success'])
                ? $data
                : ['success' => false, 'error' => $data['error'] ?? 'Gagal mengambil detail order dari OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get logistics tracking info via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function getTrackingInfo(string $orderSn, ?string $packageNumber = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung'];
        }

        try {
            $params = ['order_sn' => $orderSn];
            if ($packageNumber) {
                $params['package_number'] = $packageNumber;
            }

            $response = $this->client(15)->get('/api/v1/logistics/tracking-info', $params);
            $data = $response->json() ?? [];

            return $response->successful() && ! empty($data['success'])
                ? $data
                : ['success' => false, 'error' => $data['error'] ?? 'Gagal mengambil tracking info dari OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get tracking number via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function getTrackingNumber(string $orderSn, ?string $packageNumber = null): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung'];
        }

        try {
            $params = ['order_sn' => $orderSn];
            if ($packageNumber) {
                $params['package_number'] = $packageNumber;
            }

            $response = $this->client(15)->get('/api/v1/logistics/tracking-number', $params);
            $data = $response->json() ?? [];

            return $response->successful() && ! empty($data['success'])
                ? $data
                : ['success' => false, 'error' => $data['error'] ?? 'Gagal mengambil nomor resi dari OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolve recipient address via OpenShopee API.
     *
     * @return array<string, mixed>|null
     */
    public function resolveOrderRecipientAddress(ShopeeOrder $order): ?array
    {
        $recipientAddress = $order->recipient_address ?: Cache::get("courier_order_address_{$order->order_sn}");
        if (! is_array($recipientAddress)) {
            $recipientAddress = null;
        }

        if (! $this->setting->isConnected()) {
            return $recipientAddress;
        }

        try {
            $response = $this->client(15)->post('/api/v1/logistics/resolve-address', [
                'order_sn' => $order->order_sn,
                'current_address' => $recipientAddress,
            ]);

            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['recipient_address']) && is_array($data['recipient_address'])) {
                $resolved = $data['recipient_address'];
                Cache::put("courier_order_address_{$order->order_sn}", $resolved, now()->addDays(7));
                if (ShopeeOrder::hasRecipientAddressColumn()) {
                    $order->update(['recipient_address' => $resolved]);
                }

                return $resolved;
            }
        } catch (Exception $e) {
            Log::warning("OpenShopee API resolveAddress warning for {$order->order_sn}: ".$e->getMessage());
        }

        return $recipientAddress;
    }

    /**
     * Sync logistics tracking info via OpenShopee API into local ShopeeOrder model.
     *
     * @return array<string, mixed>
     */
    public function syncOrderTrackingInfo(ShopeeOrder $order): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        try {
            $response = $this->client(20)->post('/api/v1/logistics/sync-order-tracking', [
                'order_sn' => $order->order_sn,
                'tracking_number' => $order->tracking_number,
            ]);

            $res = $response->json() ?? [];

            if (! $response->successful() || empty($res['success'])) {
                return [
                    'success' => false,
                    'error' => $res['error'] ?? 'Gagal mengambil tracking dari OpenShopee API',
                ];
            }

            $payload = $res['data'] ?? [];
            $trackingData = $payload['tracking_info'] ?? [];
            $logisticsStatus = $payload['logistics_status'] ?? null;

            $updateData = [
                'tracking_data' => $trackingData,
                'last_tracked_at' => now(),
            ];

            if (! empty($payload['tracking_number'])) {
                $updateData['tracking_number'] = $payload['tracking_number'];
            }

            if (! empty($payload['recipient_address']) && ShopeeOrder::hasRecipientAddressColumn()) {
                $updateData['recipient_address'] = $payload['recipient_address'];
                Cache::put("courier_order_address_{$order->order_sn}", $payload['recipient_address'], now()->addDays(7));
            }

            if ($logisticsStatus) {
                $updateData['logistics_status'] = $logisticsStatus;
                if ($logisticsStatus === 'LOGISTICS_DELIVERED') {
                    $updateData['delivered_at'] = $order->delivered_at ?? now();
                    $updateData['order_status'] = 'COMPLETED';
                } elseif (in_array($logisticsStatus, ['LOGISTICS_PICKUP_DONE', 'LOGISTICS_DELIVERY_IN_PROCESS'], true) && empty($order->shipped_at)) {
                    $updateData['shipped_at'] = now();
                }
            }

            $order->update($updateData);

            return [
                'success' => true,
                'data' => [
                    'order_sn' => $order->order_sn,
                    'tracking_number' => $order->tracking_number,
                    'logistics_status' => $order->logistics_status,
                    'tracking_info' => $order->tracking_data,
                ],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Arrange shipment (ship_order) via OpenShopee API.
     *
     * @param  array<string, mixed>  $shippingParams
     * @return array<string, mixed>
     */
    public function shipOrder(string $orderSn, array $shippingParams = []): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung'];
        }

        try {
            $response = $this->client(15)->post('/api/v1/logistics/ship-order', [
                'order_sn' => $orderSn,
                'shipping_params' => $shippingParams,
            ]);

            $data = $response->json() ?? [];

            return $response->successful() && ! empty($data['success'])
                ? $data
                : ['success' => false, 'error' => $data['error'] ?? 'Gagal mengatur pengiriman melalui OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Trigger order simulation on OpenShopee API (Sandbox).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function simulateOrderOnGateway(array $payload): array
    {
        if (! $this->setting->hasApiBridge()) {
            return ['success' => false, 'error' => 'Koneksi OpenShopee API belum diatur'];
        }

        try {
            $response = $this->client(10)->post('/api/v1/orders/simulate', $payload);
            $data = $response->json() ?? [];

            return $response->successful() && ! empty($data['success'])
                ? $data
                : ['success' => false, 'error' => $data['error'] ?? 'Gagal simulasi di OpenShopee API'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify authenticity of real-time push event sent from OpenShopee API to Frontend.
     */
    public function verifyBridgePush(string $rawBody, string $signature, string $headerApiKey): bool
    {
        $configuredKey = $this->setting->getApiKey();
        if ($configuredKey === '') {
            return false;
        }

        if ($headerApiKey !== '' && hash_equals($configuredKey, $headerApiKey)) {
            return true;
        }

        if ($signature !== '') {
            $expected = hash_hmac('sha256', $rawBody, $configuredKey);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Live on-the-fly sync for products via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function syncLiveProductsIfStale(int $seconds = 15, ?StockMutationService $mutationService = null, bool $force = false): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        if (! $force && Cache::has('openshopee_vps_maintenance_breaker')) {
            return ['success' => false, 'offline_buffered' => true, 'error' => 'Server OpenShopee API sedang pemeliharaan; menampilkan data gudang lokal terakhir.'];
        }

        $cacheKey = 'shopee_live_products_sync_lock';
        if (! $force && Cache::has($cacheKey)) {
            return ['success' => true, 'cached' => true, 'message' => 'Data produk sudah sinkron dengan OpenShopee API terbaru.'];
        }

        Cache::put($cacheKey, time(), now()->addSeconds(max(1, $seconds)));

        return $this->pullAndSyncProducts($mutationService);
    }

    /**
     * Live on-the-fly sync for orders via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function syncLiveOrdersIfStale(int $seconds = 15, int $days = 3, ?StockMutationService $mutationService = null, bool $force = false): array
    {
        if (! $this->setting->isConnected()) {
            return ['success' => false, 'error' => 'OpenShopee API belum terhubung ke toko Shopee'];
        }

        if (! $force && Cache::has('openshopee_vps_maintenance_breaker')) {
            return ['success' => false, 'offline_buffered' => true, 'error' => 'Server OpenShopee API sedang pemeliharaan; menampilkan data pesanan lokal terakhir.'];
        }

        $cacheKey = 'shopee_live_orders_sync_lock';
        if (! $force && Cache::has($cacheKey)) {
            return ['success' => true, 'cached' => true, 'message' => 'Data pesanan sudah sinkron dengan OpenShopee API terbaru.'];
        }

        Cache::put($cacheKey, time(), now()->addSeconds(max(1, $seconds)));

        return $this->pullAndSyncOrders($days, $mutationService);
    }

    /**
     * Synchronize both products & orders live via OpenShopee API.
     *
     * @return array<string, mixed>
     */
    public function syncAllLiveIfStale(int $seconds = 15, ?StockMutationService $mutationService = null, bool $force = false): array
    {
        $productsResult = $this->syncLiveProductsIfStale($seconds, $mutationService, $force);
        $ordersResult = $this->syncLiveOrdersIfStale($seconds, 3, $mutationService, $force);

        return [
            'success' => ($productsResult['success'] ?? false) || ($ordersResult['success'] ?? false),
            'products' => $productsResult,
            'orders' => $ordersResult,
        ];
    }
}
