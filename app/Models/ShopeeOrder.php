<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopeeOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_sn',
        'tracking_number',
        'shipping_carrier',
        'shop_id',
        'order_status',
        'total_amount',
        'buyer_username',
        'items_data',
        'stock_deducted',
        'stock_deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'items_data' => 'array',
            'stock_deducted' => 'boolean',
            'stock_deducted_at' => 'datetime',
            'shop_id' => 'integer',
        ];
    }

    public function packingRecords(): HasMany
    {
        return $this->hasMany(PackingRecord::class);
    }

    public function isCancelled(): bool
    {
        return in_array($this->order_status, ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED']);
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
}
