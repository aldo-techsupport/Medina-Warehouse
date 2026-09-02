<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'category',
        'unit',
        'purchase_price',
        'selling_price',
        'stock',
        'safety_stock',
        'shopee_item_id',
        'shopee_model_id',
        'shopee_stock',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
            'safety_stock' => 'integer',
            'shopee_item_id' => 'integer',
            'shopee_model_id' => 'integer',
            'shopee_stock' => 'integer',
        ];
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(StockMutation::class)->latest();
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->safety_stock;
    }

    public function isConnectedToShopee(): bool
    {
        return ! empty($this->shopee_item_id);
    }

    public function getImageUrlAttribute(): string
    {
        if (! empty($this->attributes['image'])) {
            return asset('storage/'.$this->attributes['image']);
        }

        $search = strtolower($this->sku.' '.$this->name.' '.$this->category);

        return match (true) {
            str_contains($search, 'gms') || str_contains($search, 'gamis') || str_contains($search, 'dress') => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=400&q=80',
            str_contains($search, 'koko') || str_contains($search, 'kurta') || str_contains($search, 'kemeja') => 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=400&q=80',
            str_contains($search, 'hjb') || str_contains($search, 'hijab') || str_contains($search, 'pashmina') || str_contains($search, 'kerudung') => 'https://images.unsplash.com/photo-1596704017254-9b121068fb31?auto=format&fit=crop&w=400&q=80',
            str_contains($search, 'srg') || str_contains($search, 'sarung') => 'https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=400&q=80',
            str_contains($search, 'mkn') || str_contains($search, 'mukena') => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=400&q=80',
            default => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80',
        };
    }
}
