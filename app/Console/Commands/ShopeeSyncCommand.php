<?php

namespace App\Console\Commands;

use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShopeeSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:sync {--orders-days=7 : Rentang hari untuk mengambil pesanan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi otomatis produk, stok, dan pesanan terbaru dari Shopee Open API v2';

    /**
     * Execute the console command.
     */
    public function handle(ShopeeService $shopeeService, StockMutationService $mutationService): int
    {
        $setting = ShopeeSetting::current();

        if (! $setting->isConnected()) {
            $this->warn('Shopee Auto-Sync: Toko Shopee belum terhubung atau token belum aktif.');

            return self::SUCCESS;
        }

        $this->info('Memulai sinkronisasi otomatis dengan Shopee...');

        // 1. Pastikan Token Masih Valid (Auto Refresh jika expired)
        $shopeeService->ensureValidToken();

        // 2. Sinkronkan Produk & Stok dari Shopee ke Gudang
        $this->info('Menarik dan menyelaraskan produk & stok Shopee...');
        $productResult = $shopeeService->pullAndSyncProducts($mutationService);
        if ($productResult['success']) {
            $this->line("[OK] Produk: {$productResult['message']}");
        } else {
            $this->error("[FAIL] Produk: {$productResult['error']}");
        }

        // 3. Sinkronkan Pesanan Terbaru dari Shopee
        $days = (int) $this->option('orders-days');
        $this->info("Menarik pesanan Shopee {$days} hari terakhir...");
        $orderResult = $shopeeService->pullAndSyncOrders($days, $mutationService);
        if ($orderResult['success']) {
            $this->line("[OK] Pesanan: {$orderResult['message']}");
        } else {
            $this->error("[FAIL] Pesanan: {$orderResult['error']}");
        }

        // 4. Sinkronkan Status Lacak Kurir & Milestone Pengiriman untuk paket yang aktif
        $this->info('Menyelaraskan status pelacakan kurir (Shopee Logistics API)...');
        $activePackages = ShopeeOrder::whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
            ->whereNotNull('tracking_number')
            ->where(function ($q) {
                $q->whereNull('last_tracked_at')
                    ->orWhere('last_tracked_at', '<=', now()->subMinutes(20));
            })
            ->take(15)
            ->get();

        $trackedCount = 0;
        foreach ($activePackages as $pkg) {
            $trackRes = $shopeeService->syncOrderTrackingInfo($pkg);
            if (! empty($trackRes['success'])) {
                $trackedCount++;
            }
        }
        $this->line("[OK] Lacak Kurir: {$trackedCount} paket diperbarui.");

        // 5. Catat waktu sinkronisasi terakhir
        $setting->update([
            'last_synced_at' => now(),
        ]);

        Log::debug('Shopee Auto-Sync Scheduler completed successfully', [
            'products_synced' => $productResult['synced_count'] ?? 0,
            'orders_synced' => $orderResult['synced_count'] ?? 0,
            'packages_tracked' => $trackedCount,
        ]);

        $this->info('Sinkronisasi otomatis Shopee selesai!');

        return self::SUCCESS;
    }
}
