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
                            {--delay=60 : Jeda istirahat antar penarikan dalam detik (default: 60 detik)}
                            {--batch=1000 : Jumlah maksimal pesanan per batch perputaran (default: 1000)}
                            {--days=3 : Rentang hari pesanan aktif yang dipindai (default: 3 hari)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi pesanan aktif Shopee (READY_TO_SHIP & PROCESSED) secara otomatis di background';

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

        $isDaemon = (bool) $this->option('daemon');
        $delay = max(15, (int) $this->option('delay'));
        $batch = min(2000, max(50, (int) $this->option('batch')));
        $days = min(15, max(1, (int) $this->option('days')));

        $this->info('Shopee Active Orders Sync started (Daemon: '.($isDaemon ? "YES, delay: {$delay}s" : 'NO').", Batch: {$batch}, Window: {$days}d)...");

        $cycle = 0;
        do {
            $cycle++;
            // Update daemon heartbeat in cache so other parts know background runner is alive
            cache()->put('shopee_daemon_active', now()->toDateTimeString(), 180);

            // Once every 30 cycles (~30-60 mins), do a deep 15-day sweep. Otherwise use fast recent $days.
            $currentDays = ($cycle % 30 === 0) ? 15 : $days;

            try {
                $result = $shopeeService->syncActiveOrdersIncremental($batch, $mutationService, $currentDays);

                $now = now()->format('Y-m-d H:i:s');
                if ($result['success']) {
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
