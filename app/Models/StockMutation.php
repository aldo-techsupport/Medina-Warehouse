<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'product_id',
        'type',
        'qty',
        'stock_before',
        'stock_after',
        'notes',
        'actor',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'inbound' => 'Barang Masuk',
            'outbound' => 'Barang Keluar',
            'adjustment' => 'Penyesuaian (Opname)',
            'shopee_sale' => 'Penjualan Shopee',
            'shopee_cancellation' => 'Pembatalan Shopee (Restock)',
            default => ucfirst($this->type),
        };
    }

    public function getBadgeClassAttribute(): string
    {
        return match ($this->type) {
            'inbound' => 'success',
            'outbound' => 'warning',
            'adjustment' => 'info',
            'shopee_sale' => 'danger',
            'shopee_cancellation' => 'secondary',
            default => 'primary',
        };
    }
}
