<?php

namespace App\Console\Commands;

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
            $this->line("✓ Produk: {$productResult['message']}");
        } else {
            $this->error("✗ Produk: {$productResult['error']}");
        }

        // 3. Sinkronkan Pesanan Terbaru dari Shopee
        $days = (int) $this->option('orders-days');
        $this->info("Menarik pesanan Shopee {$days} hari terakhir...");
        $orderResult = $shopeeService->pullAndSyncOrders($days, $mutationService);
        if ($orderResult['success']) {
            $this->line("✓ Pesanan: {$orderResult['message']}");
        } else {
            $this->error("✗ Pesanan: {$orderResult['error']}");
        }

        // 4. Catat waktu sinkronisasi terakhir
        $setting->update([
            'last_synced_at' => now(),
        ]);

        Log::info('Shopee Auto-Sync Scheduler completed successfully', [
            'products_synced' => $productResult['synced_count'] ?? 0,
            'orders_synced' => $orderResult['synced_count'] ?? 0,
        ]);

        $this->info('Sinkronisasi otomatis Shopee selesai!');

        return self::SUCCESS;
    }
}
