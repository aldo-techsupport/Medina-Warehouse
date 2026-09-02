<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopeeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'partner_key',
        'shop_id',
        'shop_name',
        'access_token',
        'refresh_token',
        'expire_in',
        'token_expires_at',
        'is_active',
        'auto_sync_stock',
        'environment',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_sync_stock' => 'boolean',
            'token_expires_at' => 'datetime',
            'partner_id' => 'integer',
            'shop_id' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'partner_id' => null,
                'partner_key' => null,
                'shop_id' => null,
                'shop_name' => 'Medina Official Store',
                'is_active' => true,
                'auto_sync_stock' => true,
                'environment' => 'sandbox',
            ]
        );
    }

    public function isConnected(): bool
    {
        return ! empty($this->partner_id) && ! empty($this->partner_key) && ! empty($this->access_token);
    }
}
