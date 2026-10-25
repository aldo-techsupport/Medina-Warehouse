<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ShopeeOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_sn',
        'tracking_number',
        'shipping_carrier',
        'logistics_status',
        'tracking_data',
        'shipped_at',
        'delivered_at',
        'last_tracked_at',
        'shop_id',
        'order_status',
        'total_amount',
        'buyer_username',
        'items_data',
        'recipient_address',
        'stock_deducted',
        'stock_deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'items_data' => 'array',
            'recipient_address' => 'array',
            'tracking_data' => 'array',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_tracked_at' => 'datetime',
            'stock_deducted' => 'boolean',
            'stock_deducted_at' => 'datetime',
            'shop_id' => 'integer',
        ];
    }

    /**
     * Safely check whether the recipient_address column exists on shopee_orders table.
     */
    public static function hasRecipientAddressColumn(): bool
    {
        try {
            return Schema::hasColumn('shopee_orders', 'recipient_address');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check whether a Shopee field value is empty or masked with asterisks ("****") by Shopee PII policy.
     */
    public static function isMaskedValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return true;
        }

        $trimmed = trim($value);

        return $trimmed === '' || preg_match('/^\*+$/', $trimmed) === 1;
    }

    /**
     * Extract the latest known transit/destination city or hub from logistics tracking milestones.
     */
    public function extractLocationFromTracking(): ?string
    {
        if (empty($this->tracking_data) || ! is_array($this->tracking_data)) {
            return null;
        }

        $sorted = $this->tracking_data;
        usort($sorted, fn ($a, $b) => (($b['update_time'] ?? $b['ctime'] ?? 0) <=> ($a['update_time'] ?? $a['ctime'] ?? 0)));

        foreach ($sorted as $step) {
            $desc = $step['description'] ?? '';
            if (! is_string($desc) || $desc === '') {
                continue;
            }

            if (preg_match('/\bke\s+((?:Kota|Kab\.?|Kabupaten)\s+[^,.]+)/iu', $desc, $m)) {
                return trim(preg_replace('/\s+via\s+.*$/iu', '', $m[1]));
            }

            if (preg_match('/\bdi\s+(?:lokasi\s+(?:transit|sortir)\s+)?((?:Kota|Kab\.?|Kabupaten)\s+[^,.]+)/iu', $desc, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Get clean formatted string for full destination address based on Shopee v2.order.get_order_detail.
     *
     * @param  array<string, mixed>|null  $addressOverride
     */
    public function getFormattedRecipientAddress(?array $addressOverride = null): string
    {
        $addr = $addressOverride ?? ($this->recipient_address ?? null);
        if (empty($addr) || ! is_array($addr)) {
            return '-';
        }

        if (! self::isMaskedValue($addr['full_address'] ?? null)) {
            return trim((string) $addr['full_address']);
        }

        $parts = array_filter([
            $addr['sort_code'] ?? null,
            $addr['sub_district'] ?? null,
            $addr['town'] ?? null,
            $addr['district'] ?? null,
            $addr['city'] ?? null,
            $addr['state'] ?? null,
            $addr['zipcode'] ?? null,
        ], fn ($val) => ! self::isMaskedValue($val));

        return ! empty($parts) ? implode(', ', $parts) : '-';
    }

    /**
     * Get concise destination location (District/City, Province) from Shopee recipient_address.
     *
     * @param  array<string, mixed>|null  $addressOverride
     */
    public function getDestinationLocation(?array $addressOverride = null): string
    {
        $addr = $addressOverride ?? ($this->recipient_address ?? null);
        if (is_array($addr)) {
            $parts = array_filter([
                $addr['city'] ?? null,
                $addr['state'] ?? null,
            ], fn ($val) => ! self::isMaskedValue($val));

            if (! empty($parts)) {
                return implode(', ', $parts);
            }

            foreach (['district', 'town', 'sub_district', 'deliver_area'] as $key) {
                if (! self::isMaskedValue($addr[$key] ?? null)) {
                    return trim((string) $addr[$key]);
                }
            }
        }

        return $this->extractLocationFromTracking() ?? '-';
    }

    public function getFormattedRecipientAddressAttribute(): string
    {
        return $this->getFormattedRecipientAddress();
    }

    public function getDestinationLocationAttribute(): string
    {
        return $this->getDestinationLocation();
    }

    public function packingRecords(): HasMany
    {
        return $this->hasMany(PackingRecord::class);
    }

    public function isCancelled(): bool
    {
        return in_array($this->order_status, ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED']);
    }

    public function isDelivered(): bool
    {
        return $this->order_status === 'COMPLETED' || $this->logistics_status === 'LOGISTICS_DELIVERED';
    }

    /**
     * Calculate days since package was created or shipped.
     */
    public function getDaysInTransitAttribute(): int
    {
        $startDate = $this->shipped_at ?? $this->created_at;
        if (! $startDate) {
            return 0;
        }

        $endDate = $this->delivered_at ?? now();

        return (int) floor($startDate->diffInDays($endDate));
    }

    /**
     * Check if package is delayed / stuck (undelivered and in-transit >= X days).
     */
    public function isDelayed(int $thresholdDays = 5): bool
    {
        if ($this->isCancelled() || $this->isDelivered()) {
            return false;
        }

        return $this->days_in_transit >= $thresholdDays;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->order_status) {
            'UNPAID' => 'secondary',
            'READY_TO_SHIP' => 'warning',
            'PROCESSED' => 'info',
            'COMPLETED' => 'success',
            'CANCELLED', 'IN_CANCEL' => 'danger',
            default => 'primary',
        };
    }

    /**
     * Map logistics status code according to Shopee Open API Guide 4.
     */
    public function getLogisticsInfoAttribute(): array
    {
        if ($this->isCancelled()) {
            return [
                'code' => $this->logistics_status ?? 'CANCELLED',
                'label' => 'Dibatalkan',
                'badge' => 'danger',
                'icon' => 'fa-circle-xmark',
            ];
        }

        if ($this->isDelayed(5)) {
            return [
                'code' => $this->logistics_status ?? 'DELAYED',
                'label' => "Tertahan ({$this->days_in_transit} Hari)",
                'badge' => 'danger',
                'icon' => 'fa-triangle-exclamation',
                'is_delayed' => true,
            ];
        }

        return match ($this->logistics_status) {
            'LOGISTICS_NOT_START' => [
                'code' => 'LOGISTICS_NOT_START',
                'label' => 'Menunggu Pickup',
                'badge' => 'secondary',
                'icon' => 'fa-box',
            ],
            'LOGISTICS_REQUEST_CREATED' => [
                'code' => 'LOGISTICS_REQUEST_CREATED',
                'label' => 'Resi Dibuat (Siap Kirim)',
                'badge' => 'info',
                'icon' => 'fa-barcode',
            ],
            'LOGISTICS_PICKUP_DONE' => [
                'code' => 'LOGISTICS_PICKUP_DONE',
                'label' => 'Diserahkan ke Kurir',
                'badge' => 'primary',
                'icon' => 'fa-truck-ramp-box',
            ],
            'LOGISTICS_DELIVERY_IN_PROCESS' => [
                'code' => 'LOGISTICS_DELIVERY_IN_PROCESS',
                'label' => 'Dalam Perjalanan (On Delivery)',
                'badge' => 'warning',
                'icon' => 'fa-truck-fast',
            ],
            'LOGISTICS_DELIVERED' => [
                'code' => 'LOGISTICS_DELIVERED',
                'label' => 'Terkirim (Delivered)',
                'badge' => 'success',
                'icon' => 'fa-circle-check',
            ],
            'LOGISTICS_DELIVERY_FAILED' => [
                'code' => 'LOGISTICS_DELIVERY_FAILED',
                'label' => 'Gagal Kirim (Antar Ulang)',
                'badge' => 'danger',
                'icon' => 'fa-triangle-exclamation',
            ],
            'LOGISTICS_REQUEST_CANCELED', 'LOGISTICS_CANCELLED' => [
                'code' => 'LOGISTICS_CANCELLED',
                'label' => 'Pengiriman Dibatalkan',
                'badge' => 'danger',
                'icon' => 'fa-ban',
            ],
            'LOGISTICS_COD_REJECTED' => [
                'code' => 'LOGISTICS_COD_REJECTED',
                'label' => 'COD Ditolak Pembeli',
                'badge' => 'danger',
                'icon' => 'fa-hand',
            ],
            default => match ($this->order_status) {
                'READY_TO_SHIP' => [
                    'code' => 'READY_TO_SHIP',
                    'label' => 'Perlu Dikirim',
                    'badge' => 'warning',
                    'icon' => 'fa-clock',
                ],
                'PROCESSED' => [
                    'code' => 'PROCESSED',
                    'label' => 'Sedang Diproses/Dikirim',
                    'badge' => 'info',
                    'icon' => 'fa-boxes-packing',
                ],
                'COMPLETED' => [
                    'code' => 'COMPLETED',
                    'label' => 'Paket Diterima (Selesai)',
                    'badge' => 'success',
                    'icon' => 'fa-circle-check',
                ],
                default => [
                    'code' => 'IN_TRANSIT',
                    'label' => 'Dalam Pengiriman',
                    'badge' => 'primary',
                    'icon' => 'fa-truck',
                ],
            },
        };
    }

    /**
     * Styling badge color for different courier carriers.
     */
    public function getCarrierStyleAttribute(): array
    {
        $carrier = strtoupper($this->shipping_carrier ?? 'SPX EXPRESS');

        if (str_contains($carrier, 'SPX') || str_contains($carrier, 'SHOPEE')) {
            return ['bg' => '#ff5722', 'color' => '#ffffff', 'name' => 'SPX Express'];
        }
        if (str_contains($carrier, 'J&T') || str_contains($carrier, 'JNT')) {
            return ['bg' => '#e53935', 'color' => '#ffffff', 'name' => 'J&T Express'];
        }
        if (str_contains($carrier, 'SICEPAT')) {
            return ['bg' => '#b71c1c', 'color' => '#ffffff', 'name' => 'SiCepat Ekspres'];
        }
        if (str_contains($carrier, 'ANTERAJA')) {
            return ['bg' => '#9c27b0', 'color' => '#ffffff', 'name' => 'Anteraja'];
        }
        if (str_contains($carrier, 'JNE')) {
            return ['bg' => '#0d47a1', 'color' => '#ffffff', 'name' => 'JNE Express'];
        }
        if (str_contains($carrier, 'NINJA')) {
            return ['bg' => '#c2185b', 'color' => '#ffffff', 'name' => 'Ninja Xpress'];
        }
        if (str_contains($carrier, 'ID EXPRESS') || str_contains($carrier, 'IDE')) {
            return ['bg' => '#d81b60', 'color' => '#ffffff', 'name' => 'ID Express'];
        }

        return ['bg' => '#475569', 'color' => '#ffffff', 'name' => $this->shipping_carrier ?? 'Jasa Kirim'];
    }
}
