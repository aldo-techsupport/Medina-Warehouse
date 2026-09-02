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

        $path = '/api/v2/product/update_stock';
        $timestamp = time();
        $sign = $this->generateShopSign($path, $timestamp);

        $url = sprintf(
            '%s%s?partner_id=%s&timestamp=%d&access_token=%s&shop_id=%s&sign=%s',
            $this->getBaseUrl(),
            $path,
            $this->setting->partner_id,
            $timestamp,
            $this->setting->access_token,
            $this->setting->shop_id,
            $sign
        );

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
