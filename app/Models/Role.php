<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * All system configurable menus grouped by category
     */
    public const SYSTEM_MENUS = [
        'Gudang Utama' => [
            'dashboard' => [
                'name' => 'Dashboard Gudang',
                'description' => 'Ringkasan performa gudang, grafik omzet, & status stok',
                'icon' => 'fas fa-chart-pie',
                'route' => 'dashboard',
            ],
            'warehouse_products' => [
                'name' => 'Katalog & Stok SKU',
                'description' => 'Manajemen master produk SKU, barcode, harga, & stok',
                'icon' => 'fas fa-boxes',
                'route' => 'warehouse.products',
            ],
            'warehouse_mutations' => [
                'name' => 'Riwayat Mutasi Stok',
                'description' => 'Pencatatan barang masuk (inbound), keluar, & opname fisik',
                'icon' => 'fas fa-history',
                'route' => 'warehouse.mutations',
            ],
        ],
        'Packing & Pengiriman' => [
            'packing_station' => [
                'name' => 'Stasiun Packing Video',
                'description' => 'Scan barcode resi, rekam video packing, & validasi pesanan',
                'icon' => 'fas fa-video',
                'route' => 'packing.index',
            ],
            'packing_history' => [
                'name' => 'Galeri Video Packing',
                'description' => 'Pencarian arsip rekaman video & bukti packing pengiriman',
                'icon' => 'fas fa-photo-film',
                'route' => 'packing.history',
            ],
        ],
        'Integrasi Shopee' => [
            'shopee_dashboard' => [
                'name' => 'Dashboard Shopee',
                'description' => 'Monitoring sinkronisasi stok realtime & log webhook API',
                'icon' => 'fas fa-store',
                'route' => 'shopee.dashboard',
            ],
            'shopee_orders' => [
                'name' => 'Pesanan Shopee',
                'description' => 'Daftar transaksi pesanan Shopee & status pengurangan stok',
                'icon' => 'fas fa-shopping-cart',
                'route' => 'shopee.orders',
            ],
            'shopee_settings' => [
                'name' => 'API & Webhook Setting',
                'description' => 'Konfigurasi Partner ID, Partner Key, & Token Shopee OpenAPI',
                'icon' => 'fas fa-key',
                'route' => 'shopee.settings',
            ],
        ],
        'Pengaturan Sistem' => [
            'role_management' => [
                'name' => 'Manajemen Role & Akses',
                'description' => 'Kelola hak akses menu untuk setiap role pengguna',
                'icon' => 'fas fa-user-shield',
                'route' => 'roles.index',
            ],
            'user_management' => [
                'name' => 'Manajemen Pengguna',
                'description' => 'Kelola akun login karyawan, penetapan role, & status aktif',
                'icon' => 'fas fa-users-cog',
                'route' => 'users.index',
            ],
        ],
    ];

    /**
     * Relationship with users
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if role has access to specific menu key
     */
    public function hasPermission(string $menuKey): bool
    {
        if ($this->slug === 'super_admin') {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return in_array($menuKey, $permissions, true);
    }
}
