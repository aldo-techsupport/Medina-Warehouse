<?php

namespace App\Console\Commands;

use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShopeeSyncActiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:sync-active 
                            {--daemon : Jalankan terus-menerus di background dengan interval delay}
                            {--delay=10 : Jeda istirahat antar penarikan dalam detik (default: 10 detik)}
                            {--batch=1000 : Jumlah maksimal pesanan per batch perputaran (default: 1000)}
                            {--days=3 : Rentang hari pesanan aktif yang dipindai (default: 3 hari)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi real-time delta & pesanan aktif dari OpenShopee API di background (cocok untuk PC Lokal / LAN)';

    /**
     * Execute the console command.
     */
    public function handle(ShopeeService $shopeeService, StockMutationService $mutationService): int
    {
        $isDaemon = (bool) $this->option('daemon');
        $delay = max(5, (int) $this->option('delay'));
        $batch = min(2000, max(50, (int) $this->option('batch')));
        $days = min(15, max(1, (int) $this->option('days')));

        $setting = ShopeeSetting::current();
        if (! $isDaemon) {
            if ($setting->hasApiBridge()) {
                $shopeeService->syncGatewayStatus(true);
                $setting->refresh();
            }

            if (! $setting->isConnected()) {
                $this->warn('Shopee Auto-Sync: OpenShopee API / Toko Shopee belum terhubung.');

                return self::SUCCESS;
            }
        }

        $this->info('OpenShopee Local Bridge Sync started (Daemon: '.($isDaemon ? "YES, delay: {$delay}s" : 'NO').", Batch: {$batch}, Window: {$days}d)...");

        $cycle = 0;
        do {
            $cycle++;
            cache()->put('shopee_daemon_active', now()->toDateTimeString(), max(60, $delay * 3));

            try {
                $shopeeService->reloadSetting();
                $setting = $shopeeService->getSetting();

                if (! $setting->hasApiBridge()) {
                    $this->warn('['.now()->format('H:i:s').'] Menunggu konfigurasi URL & API Key OpenShopee API...');
                } else {
                    // 1. Fast real-time delta pull from OpenShopee API buffer (catches all Shopee webhooks received by VPS)
                    $delta = $shopeeService->syncDeltaFromGateway($mutationService);
                    $deltaOrders = (int) ($delta['orders_synced'] ?? 0);
                    $deltaProducts = (int) ($delta['products_synced'] ?? 0);

                    if ($deltaOrders > 0 || $deltaProducts > 0) {
                        $this->line('['.now()->format('Y-m-d H:i:s')."] [DELTA] {$deltaOrders} pesanan & {$deltaProducts} produk tersinkron real-time dari buffer API.");
                    }

                    // 2. Direct active orders sweep on first cycle or every ~60s
                    $sweepEvery = max(1, (int) ceil(60 / $delay));
                    if ($setting->fresh()->isConnected() && ($cycle === 1 || $cycle % $sweepEvery === 0)) {
                        $currentDays = ($cycle % ($sweepEvery * 30) === 0) ? 15 : $days;
                        $result = $shopeeService->syncActiveOrdersIncremental($batch, $mutationService, $currentDays);

                        $now = now()->format('Y-m-d H:i:s');
                        if (! empty($result['success'])) {
                            $synced = $result['synced_this_batch'] ?? 0;
                            $remaining = $result['missing_remaining'] ?? 0;
                            $total = $result['total_active'] ?? 0;

                            $msg = "[{$now}] [OK] Synced {$synced} orders. Remaining to pull: {$remaining} / {$total} active orders.";
                            $this->line($msg);

                            if ($synced > 0) {
                                Log::debug("Shopee Background Sync: {$msg}");
                            }
                        } else {
                            $err = $result['error'] ?? 'Unknown error';
                            $this->error("[{$now}] [FAIL] Sync error: {$err}");
                            Log::warning("Shopee Background Sync warning: {$err}");
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->error('Error during Shopee sync: '.$e->getMessage());
                Log::error('Shopee Background Sync exception: '.$e->getMessage());
            }

            if ($isDaemon) {
                sleep($delay);
            }
        } while ($isDaemon);

        $this->info('Sinkronisasi pesanan aktif selesai.');

        return self::SUCCESS;
    }
}
