<?php

namespace App\Http\Controllers;

use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CourierTrackingController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        public ShopeeService $shopeeService
    ) {}

    /**
     * Display a listing of courier packages with advanced filters.
     * Supports SLA monitoring for packages undelivered after 5+ days.
     */
    public function index(Request $request): View
    {
        $setting = ShopeeSetting::current();

        $query = ShopeeOrder::with('packingRecords');

        // Apply Status / Category Filter
        $filter = $request->input('filter', 'all');

        if ($filter === 'delayed_5_days') {
            $query->whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
                ->where(function ($q) {
                    $q->whereNull('logistics_status')
                        ->orWhere('logistics_status', '!=', 'LOGISTICS_DELIVERED');
                })
                ->where(function ($q) {
                    $q->where('created_at', '<=', now()->subDays(5))
                        ->orWhere('shipped_at', '<=', now()->subDays(5));
                });
        } elseif ($filter === 'in_transit') {
            $query->whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
                ->where(function ($q) {
                    $q->whereNull('logistics_status')
                        ->orWhere('logistics_status', '!=', 'LOGISTICS_DELIVERED');
                });
        } elseif ($filter === 'delivered') {
            $query->where(function ($q) {
                $q->where('order_status', 'COMPLETED')
                    ->orWhere('logistics_status', 'LOGISTICS_DELIVERED');
            });
        } elseif ($filter === 'failed') {
            $query->whereIn('logistics_status', ['LOGISTICS_DELIVERY_FAILED', 'LOGISTICS_COD_REJECTED']);
        } elseif ($filter === 'ready_to_ship') {
            $query->where('order_status', 'READY_TO_SHIP')
                ->where(function ($q) {
                    $q->whereNull('logistics_status')
                        ->orWhere('logistics_status', 'LOGISTICS_NOT_START');
                });
        } elseif ($filter === 'cancelled') {
            $query->where(function ($q) {
                $q->whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])
                    ->orWhereIn('logistics_status', ['LOGISTICS_CANCEL', 'LOGISTICS_RETURN']);
            });
        }

        // Filter by Courier / Carrier
        if ($request->filled('carrier')) {
            $query->where('shipping_carrier', $request->input('carrier'));
        }

        // Custom Days Filter (e.g. >= 5 days, >= 7 days)
        if ($request->filled('min_days')) {
            $minDays = (int) $request->input('min_days');
            if ($minDays > 0) {
                $query->whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
                    ->where(function ($q) use ($minDays) {
                        $q->where('created_at', '<=', now()->subDays($minDays))
                            ->orWhere('shipped_at', '<=', now()->subDays($minDays));
                    });
            }
        }

        // Search by Resi, Order SN, Pembeli, or SKU
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_sn', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('buyer_username', 'like', "%{$search}%")
                    ->orWhere('shipping_carrier', 'like', "%{$search}%");
            });
        }

        // Paginate results
        $packages = $query->latest()->paginate(15)->withQueryString();

        // Calculate KPI Metrics for dashboard badges
        $totalCount = ShopeeOrder::count();

        $delayed5DaysCount = ShopeeOrder::whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
            ->where(function ($q) {
                $q->whereNull('logistics_status')
                    ->orWhere('logistics_status', '!=', 'LOGISTICS_DELIVERED');
            })
            ->where(function ($q) {
                $q->where('created_at', '<=', now()->subDays(5))
                    ->orWhere('shipped_at', '<=', now()->subDays(5));
            })
            ->count();

        $inTransitCount = ShopeeOrder::whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
            ->where(function ($q) {
                $q->whereNull('logistics_status')
                    ->orWhere('logistics_status', '!=', 'LOGISTICS_DELIVERED');
            })
            ->count();

        $deliveredCount = ShopeeOrder::where(function ($q) {
            $q->where('order_status', 'COMPLETED')
                ->orWhere('logistics_status', 'LOGISTICS_DELIVERED');
        })->count();

        $cancelledCount = ShopeeOrder::where(function ($q) {
            $q->whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN', 'RETURNED'])
                ->orWhereIn('logistics_status', ['LOGISTICS_CANCEL', 'LOGISTICS_RETURN']);
        })->count();

        $readyToShipCount = ShopeeOrder::where('order_status', 'READY_TO_SHIP')
            ->where(function ($q) {
                $q->whereNull('logistics_status')
                    ->orWhere('logistics_status', 'LOGISTICS_NOT_START');
            })->count();

        $failedCount = ShopeeOrder::whereIn('logistics_status', ['LOGISTICS_DELIVERY_FAILED', 'LOGISTICS_COD_REJECTED'])->count();

        $cachedCarriers = Cache::remember('courier_unique_carriers_list', 1800, function () {
            return ShopeeOrder::whereNotNull('shipping_carrier')
                ->where('shipping_carrier', '!=', '')
                ->distinct()
                ->orderBy('shipping_carrier')
                ->pluck('shipping_carrier')
                ->map(fn ($item) => is_string($item) ? trim($item) : '')
                ->filter(fn ($item) => $item !== '')
                ->values()
                ->all();
        });

        $carriersList = [];
        if (is_array($cachedCarriers)) {
            $carriersList = $cachedCarriers;
        } elseif ($cachedCarriers instanceof Collection) {
            $carriersList = $cachedCarriers->all();
        } elseif (is_object($cachedCarriers) && isset($cachedCarriers->items) && is_array($cachedCarriers->items)) {
            $carriersList = $cachedCarriers->items;
        }

        $carriersList = array_values(array_unique(array_filter(array_map(function ($c) {
            return is_string($c) ? trim($c) : '';
        }, (array) $carriersList), fn ($c) => $c !== '')));

        return view('courier.index', compact(
            'packages',
            'setting',
            'filter',
            'totalCount',
            'delayed5DaysCount',
            'inTransitCount',
            'deliveredCount',
            'cancelledCount',
            'readyToShipCount',
            'failedCount',
            'carriersList'
        ));
    }

    /**
     * Display details of a single package, including items, recipient address & live tracking timeline.
     */
    public function show(Request $request, ShopeeOrder $order): JsonResponse|View
    {
        $order->load('packingRecords');

        // If tracking info is missing or older than 1 hour and connected to Shopee, auto-sync
        if ($this->shopeeService->getSetting()->isConnected()) {
            $needsTracking = empty($order->tracking_data) || ! $order->last_tracked_at || $order->last_tracked_at->diffInMinutes(now()) > 60;

            if ($needsTracking) {
                $this->shopeeService->syncOrderTrackingInfo($order);
                $order->refresh();
            }
        }

        $recipientAddress = $this->shopeeService->resolveOrderRecipientAddress($order);
        $rawName = $recipientAddress['name'] ?? null;
        $rawPhone = $recipientAddress['phone'] ?? null;
        $recipientName = ! ShopeeOrder::isMaskedValue($rawName) ? trim((string) $rawName) : $order->buyer_username;
        $recipientPhone = ! ShopeeOrder::isMaskedValue($rawPhone) ? trim((string) $rawPhone) : null;
        $formattedAddress = $order->getFormattedRecipientAddress($recipientAddress);
        $destinationCity = $order->getDestinationLocation($recipientAddress);
        $isAddressMasked = ShopeeOrder::isMaskedValue($recipientAddress['full_address'] ?? null);

        // If AJAX request, return rich JSON structure for quick modal inspection
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'order_sn' => $order->order_sn,
                    'tracking_number' => $order->tracking_number ?? 'Belum ada resi',
                    'shipping_carrier' => $order->shipping_carrier ?? 'SPX Express',
                    'carrier_style' => $order->carrier_style,
                    'order_status' => $order->order_status,
                    'logistics_info' => $order->logistics_info,
                    'buyer_username' => $order->buyer_username,
                    'recipient_address' => $recipientAddress,
                    'recipient_name' => $recipientName,
                    'recipient_phone' => $recipientPhone,
                    'destination_city' => $destinationCity,
                    'formatted_address' => $formattedAddress,
                    'is_address_masked' => $isAddressMasked,
                    'address_image' => $recipientAddress['address_image'] ?? null,
                    'name_image' => $recipientAddress['name_image'] ?? null,
                    'phone_image' => $recipientAddress['phone_image'] ?? null,
                    'total_amount' => $order->total_amount,
                    'formatted_amount' => 'Rp '.number_format((float) $order->total_amount, 0, ',', '.'),
                    'items_data' => $order->items_data ?? [],
                    'days_in_transit' => $order->days_in_transit,
                    'is_delayed_5_days' => $order->isDelayed(5),
                    'created_at' => $order->created_at->format('d M Y, H:i'),
                    'shipped_at' => $order->shipped_at ? $order->shipped_at->format('d M Y, H:i') : null,
                    'delivered_at' => $order->delivered_at ? $order->delivered_at->format('d M Y, H:i') : null,
                    'last_tracked_at' => $order->last_tracked_at ? $order->last_tracked_at->format('d M Y, H:i') : null,
                    'has_video' => $order->hasPackingVideo(),
                    'packing_record' => $order->latest_packing_record ? [
                        'id' => $order->latest_packing_record->id,
                        'video_url' => $order->latest_packing_record->video_url,
                        'packer_name' => $order->latest_packing_record->packer_name,
                        'duration_formatted' => $order->latest_packing_record->duration_formatted,
                        'created_at' => $order->latest_packing_record->created_at->format('d M Y, H:i'),
                    ] : null,
                ],
            ]);
        }

        return view('courier.show', compact('order'));
    }

    /**
     * Synchronize live tracking updates for a single package from Shopee Logistics API.
     */
    public function syncTracking(ShopeeOrder $order): JsonResponse|RedirectResponse
    {
        $setting = $this->shopeeService->getSetting();

        if (! $setting->isConnected()) {
            // Provide simulated update if sandbox
            if ($setting->isSandbox()) {
                $this->applySandboxMockTracking($order);

                return request()->expectsJson()
                    ? response()->json(['success' => true, 'message' => 'Status tracking simulasi Shopee berhasil diperbarui.'])
                    : redirect()->back()->with('success', "Status tracking paket #{$order->order_sn} berhasil disimulasikan.");
            }

            $msg = 'Toko Shopee belum terhubung. Silakan hubungkan toko di menu Pengaturan Shopee.';

            return request()->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : redirect()->back()->with('error', $msg);
        }

        $result = $this->shopeeService->syncOrderTrackingInfo($order);

        if ($result['success']) {
            $msg = "Tracking paket #{$order->order_sn} ({$order->tracking_number}) berhasil disinkronkan dengan Shopee Open API!";

            return request()->expectsJson()
                ? response()->json(['success' => true, 'message' => $msg, 'data' => $result['data']])
                : redirect()->back()->with('success', $msg);
        }

        $errorMsg = $result['error'] ?? 'Gagal mengambil tracking dari Shopee';

        return request()->expectsJson()
            ? response()->json(['success' => false, 'message' => $errorMsg], 400)
            : redirect()->back()->with('error', $errorMsg);
    }

    /**
     * Batch refresh tracking updates for all active / in-transit packages.
     */
    public function syncAllTracking(): RedirectResponse
    {
        $setting = $this->shopeeService->getSetting();

        if (! $setting->isConnected()) {
            return redirect()->back()->with('error', 'Toko Shopee belum terhubung ke Open API.');
        }

        $activeOrders = ShopeeOrder::whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'IN_CANCEL', 'RETURNED'])
            ->latest()
            ->take(20)
            ->get();

        $synced = 0;
        foreach ($activeOrders as $order) {
            $res = $this->shopeeService->syncOrderTrackingInfo($order);
            if ($res['success']) {
                $synced++;
            }
        }

        return redirect()->back()->with('success', "Berhasil mensinkronkan status kurir untuk {$synced} paket dari Shopee Open API v2!");
    }

    /**
     * Create demo packages in Sandbox mode for quick UI & SLA testing.
     * Generates packages with realistic Shopee Guide 4 tracking milestones,
     * including packages that are > 5 days overdue so the user can immediately test the filter.
     */
    public function generateDemoPackages(): RedirectResponse
    {
        try {
            // 1. Normal In-Transit package (2 days ago)
            $orderSn1 = '26'.date('md').'DEMO1';
            ShopeeOrder::updateOrCreate(
                ['order_sn' => $orderSn1],
                [
                    'tracking_number' => 'SPXID0481928471',
                    'shipping_carrier' => 'SPX Express',
                    'order_status' => 'PROCESSED',
                    'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
                    'total_amount' => 185000,
                    'buyer_username' => 'andi_saputra92',
                    'items_data' => [
                        [
                            'item_sku' => 'MDN-KMT-01',
                            'item_name' => 'Kemeja Katun Pria Medina Slim Fit Navy',
                            'model_quantity_purchased' => 1,
                            'model_discounted_price' => 185000,
                        ],
                    ],
                    'stock_deducted' => true,
                    'created_at' => Carbon::now()->subDays(2),
                    'shipped_at' => Carbon::now()->subDays(1),
                    'last_tracked_at' => Carbon::now()->subHours(2),
                    'tracking_data' => [
                        ['ctime' => Carbon::now()->subDays(2)->timestamp, 'description' => 'Pesanan telah dibuat dan dibayar pembeli.', 'logistics_status' => 'LOGISTICS_NOT_START'],
                        ['ctime' => Carbon::now()->subDays(1)->timestamp, 'description' => 'Paket telah diserahkan ke SPX Express Drop Point Gudang.', 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
                        ['ctime' => Carbon::now()->subHours(8)->timestamp, 'description' => 'Paket telah tiba di Sorting Center Jakarta Barat.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
                        ['ctime' => Carbon::now()->subHours(2)->timestamp, 'description' => 'Paket sedang diberangkatkan ke Hub transit kota tujuan.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
                    ],
                ]
            );

            // 2. DELAYED Package (6 days in transit without delivery - TRIGGERS 5+ DAYS FILTER)
            $orderSn2 = '26'.date('md').'DELAY6D';
            ShopeeOrder::updateOrCreate(
                ['order_sn' => $orderSn2],
                [
                    'tracking_number' => 'JP98231048291',
                    'shipping_carrier' => 'J&T Express',
                    'order_status' => 'PROCESSED',
                    'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
                    'total_amount' => 240000,
                    'buyer_username' => 'ratna_dewi_bdg',
                    'items_data' => [
                        [
                            'item_sku' => 'MDN-GMS-02',
                            'item_name' => 'Gamis Syari Medina Premium Olive AllSize',
                            'model_quantity_purchased' => 1,
                            'model_discounted_price' => 240000,
                        ],
                    ],
                    'stock_deducted' => true,
                    'created_at' => Carbon::now()->subDays(6),
                    'shipped_at' => Carbon::now()->subDays(6),
                    'last_tracked_at' => Carbon::now()->subHours(4),
                    'tracking_data' => [
                        ['ctime' => Carbon::now()->subDays(6)->timestamp, 'description' => 'Nomor resi dibuat di sistem Shopee.', 'logistics_status' => 'LOGISTICS_REQUEST_CREATED'],
                        ['ctime' => Carbon::now()->subDays(6)->addHours(4)->timestamp, 'description' => 'Paket telah di-pickup oleh kurir J&T Express.', 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
                        ['ctime' => Carbon::now()->subDays(5)->timestamp, 'description' => 'Paket masuk di Gateway Transit Bandung Selatan.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
                        ['ctime' => Carbon::now()->subDays(3)->timestamp, 'description' => 'Kendala operasional hub: Penjadwalan ulang pengantaran paket.', 'logistics_status' => 'LOGISTICS_DELIVERY_FAILED'],
                        ['ctime' => Carbon::now()->subHours(4)->timestamp, 'description' => 'Paket tertahan di Sorting Hub: Menunggu alokasi kurir pengantar.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
                    ],
                ]
            );

            // 3. ANOTHER DELAYED Package (8 days in transit - SPX Express - TRIGGERS 5+ DAYS FILTER)
            $orderSn3 = '26'.date('md').'DELAY8D';
            ShopeeOrder::updateOrCreate(
                ['order_sn' => $orderSn3],
                [
                    'tracking_number' => 'SPXID0981273911',
                    'shipping_carrier' => 'SPX Express',
                    'order_status' => 'PROCESSED',
                    'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
                    'total_amount' => 450000,
                    'buyer_username' => 'hendra_surabaya',
                    'items_data' => [
                        [
                            'item_sku' => 'MDN-JKT-05',
                            'item_name' => 'Jaket Bomber Medina Water-Repellent L',
                            'model_quantity_purchased' => 2,
                            'model_discounted_price' => 225000,
                        ],
                    ],
                    'stock_deducted' => true,
                    'created_at' => Carbon::now()->subDays(8),
                    'shipped_at' => Carbon::now()->subDays(8),
                    'last_tracked_at' => Carbon::now()->subHours(12),
                    'tracking_data' => [
                        ['ctime' => Carbon::now()->subDays(8)->timestamp, 'description' => 'Resi diterbitkan via Shopee Logistics API.', 'logistics_status' => 'LOGISTICS_REQUEST_CREATED'],
                        ['ctime' => Carbon::now()->subDays(8)->addHours(3)->timestamp, 'description' => 'Paket diterima oleh agen SPX Drop Point.', 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
                        ['ctime' => Carbon::now()->subDays(6)->timestamp, 'description' => 'Paket dalam perjalanan darat antar pulau Surabaya DC.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
                        ['ctime' => Carbon::now()->subDays(4)->timestamp, 'description' => 'Paket tertahan: Alamat penerima belum dapat dijangkau oleh kurir.', 'logistics_status' => 'LOGISTICS_DELIVERY_FAILED'],
                    ],
                ]
            );

            // 4. Completed / Delivered package (5 days ago, delivered 2 days ago)
            $orderSn4 = '26'.date('md').'DLVD4';
            ShopeeOrder::updateOrCreate(
                ['order_sn' => $orderSn4],
                [
                    'tracking_number' => '004128941029',
                    'shipping_carrier' => 'SiCepat Ekspres',
                    'order_status' => 'COMPLETED',
                    'logistics_status' => 'LOGISTICS_DELIVERED',
                    'total_amount' => 125000,
                    'buyer_username' => 'maya_safitri',
                    'items_data' => [
                        [
                            'item_sku' => 'MDN-TSH-03',
                            'item_name' => 'Kaos Polos Medina Cotton Combed 24s Hitam M',
                            'model_quantity_purchased' => 2,
                            'model_discounted_price' => 62500,
                        ],
                    ],
                    'stock_deducted' => true,
                    'created_at' => Carbon::now()->subDays(5),
                    'shipped_at' => Carbon::now()->subDays(5),
                    'delivered_at' => Carbon::now()->subDays(2),
                    'last_tracked_at' => Carbon::now()->subDays(2),
                    'tracking_data' => [
                        ['ctime' => Carbon::now()->subDays(5)->timestamp, 'description' => 'Paket telah di-pickup oleh SiCepat.', 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
                        ['ctime' => Carbon::now()->subDays(3)->timestamp, 'description' => 'Paket dibawa oleh kurir SiCepat menuju alamat penerima.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
                        ['ctime' => Carbon::now()->subDays(2)->timestamp, 'description' => 'Paket telah diterima oleh [Yang Bersangkutan: Maya]. Terima kasih telah berbelanja!', 'logistics_status' => 'LOGISTICS_DELIVERED'],
                    ],
                ]
            );

            // 5. Ready to Ship package (Ordered today, needs packing)
            $orderSn5 = '26'.date('md').'NEWORD';
            ShopeeOrder::updateOrCreate(
                ['order_sn' => $orderSn5],
                [
                    'tracking_number' => 'SPXID'.rand(1000000000, 9999999999),
                    'shipping_carrier' => 'SPX Express',
                    'order_status' => 'READY_TO_SHIP',
                    'logistics_status' => 'LOGISTICS_REQUEST_CREATED',
                    'total_amount' => 210000,
                    'buyer_username' => 'bambang_wijaya',
                    'items_data' => [
                        [
                            'item_sku' => 'MDN-CLY-04',
                            'item_name' => 'Celana Chino Pria Medina Slimfit Khaki 32',
                            'model_quantity_purchased' => 1,
                            'model_discounted_price' => 210000,
                        ],
                    ],
                    'stock_deducted' => true,
                    'created_at' => Carbon::now()->subHours(3),
                    'shipped_at' => null,
                    'last_tracked_at' => Carbon::now()->subMinutes(30),
                    'tracking_data' => [
                        ['ctime' => Carbon::now()->subHours(3)->timestamp, 'description' => 'Pesanan baru diverifikasi oleh Shopee. Siap di-packing.', 'logistics_status' => 'LOGISTICS_REQUEST_CREATED'],
                    ],
                ]
            );

            return redirect()->route('courier.index')->with('success', 'Berhasil membuat 5 data paket demo realistis sesuai standar Shopee Open Platform Guide 4 (Termasuk paket tertahan >= 5 hari)!');
        } catch (Exception $e) {
            return redirect()->route('courier.index')->with('error', 'Gagal membuat data demo: '.$e->getMessage());
        }
    }

    /**
     * Apply realistic sandbox mock tracking timeline to an order.
     */
    protected function applySandboxMockTracking(ShopeeOrder $order): void
    {
        $carrier = $order->shipping_carrier ?? 'SPX Express';
        $now = now();

        $timeline = [
            ['ctime' => $order->created_at->timestamp, 'description' => 'Pesanan diverifikasi & resi diterbitkan.', 'logistics_status' => 'LOGISTICS_REQUEST_CREATED'],
            ['ctime' => $order->created_at->addHours(4)->timestamp, 'description' => "Paket telah diserahkan ke agen {$carrier}.", 'logistics_status' => 'LOGISTICS_PICKUP_DONE'],
            ['ctime' => $order->created_at->addDay()->timestamp, 'description' => 'Paket sedang transit di Hub Sortir Utama.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'],
        ];

        if ($order->isDelayed(5)) {
            $timeline[] = ['ctime' => $now->subDays(2)->timestamp, 'description' => 'Paket mengalami penundaan antaran di hub transit tujuan.', 'logistics_status' => 'LOGISTICS_DELIVERY_FAILED'];
            $timeline[] = ['ctime' => $now->timestamp, 'description' => 'Menunggu jadwal pengantaran ulang kurir.', 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'];
            $order->update([
                'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
                'tracking_data' => $timeline,
                'last_tracked_at' => $now,
            ]);
        } else {
            $timeline[] = ['ctime' => $now->timestamp, 'description' => "Kurir {$carrier} sedang membawa paket ke alamat tujuan.", 'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS'];
            $order->update([
                'logistics_status' => 'LOGISTICS_DELIVERY_IN_PROCESS',
                'tracking_data' => $timeline,
                'last_tracked_at' => $now,
            ]);
        }
    }
}
