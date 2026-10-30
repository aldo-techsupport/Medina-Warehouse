<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopeeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_url',
        'api_key',
        'shop_id',
        'shop_name',
        'token_expires_at',
        'is_active',
        'remote_connected',
        'auto_sync_stock',
        'environment',
        'remote_auth_url',
        'remote_dashboard_url',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'remote_connected' => 'boolean',
            'auto_sync_stock' => 'boolean',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'shop_id' => 'integer',
        ];
    }

    public static function current(): self
    {
        $setting = static::first();
        if ($setting) {
            return $setting;
        }

        return static::firstOrCreate(
            ['id' => 1],
            [
                'api_url' => null,
                'api_key' => null,
                'shop_id' => null,
                'shop_name' => null,
                'is_active' => true,
                'remote_connected' => false,
                'auto_sync_stock' => true,
                'environment' => 'sandbox',
            ]
        );
    }

    public function getApiUrl(): string
    {
        $url = trim((string) ($this->api_url ?: config('services.openshopee.url', '')));

        return rtrim($url, '/');
    }

    public function getApiKey(): string
    {
        return trim((string) ($this->api_key ?: config('services.openshopee.api_key', '')));
    }

    public function hasApiBridge(): bool
    {
        return $this->getApiUrl() !== '' && $this->getApiKey() !== '';
    }

    public function getDashboardUrl(): string
    {
        if (! empty($this->remote_dashboard_url)) {
            return (string) $this->remote_dashboard_url;
        }

        $base = $this->getApiUrl();

        return $base !== '' ? $base.'/dashboard' : '';
    }

    public function isConnected(): bool
    {
        return $this->is_active && $this->remote_connected && $this->hasApiBridge();
    }

    public function isTokenExpired(): bool
    {
        return empty($this->token_expires_at) || $this->token_expires_at->isPast();
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function isSandbox(): bool
    {
        return $this->environment !== 'production';
    }
}
