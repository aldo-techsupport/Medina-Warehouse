<?php

namespace App\Http\Controllers;

use App\Models\PackingRecord;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PackingController extends Controller
{
    protected ShopeeService $shopeeService;

    protected StockMutationService $mutationService;

    public function __construct(ShopeeService $shopeeService, ?StockMutationService $mutationService = null)
    {
        $this->shopeeService = $shopeeService;
        $this->mutationService = $mutationService ?? new StockMutationService($shopeeService);
    }

    /**
     * Packing Station UI.
     */
    public function index()
    {
        $todayPackedCount = PackingRecord::where('status', 'completed')
            ->whereDate('created_at', today())
            ->count();

        $todayBlockedCount = PackingRecord::where('status', 'blocked_cancelled')
            ->whereDate('created_at', today())
            ->count();

        $recentPacked = PackingRecord::with('shopeeOrder')
            ->where('status', 'completed')
            ->latest()
            ->take(5)
            ->get();

        $allOrders = ShopeeOrder::where('order_status', 'READY_TO_SHIP')->latest()->take(5)->get();

        return view('packing.index', compact(
            'todayPackedCount',
            'todayBlockedCount',
            'recentPacked',
            'allOrders'
        ));
    }

    /**
     * Check scanned tracking number or Order SN and validate cancellation status.
     * Supports live query to Shopee Open API v2 to guarantee up-to-the-second status.
     */
    public function checkOrder(Request $request): JsonResponse
    {
        $query = trim($request->input('query', ''));

        if (empty($query)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan scan atau masukkan Nomor Resi / Order SN.',
            ], 422);
        }

        // Search in local Shopee orders by tracking_number or order_sn
        $order = ShopeeOrder::where('tracking_number', $query)
            ->orWhere('order_sn', $query)
            ->first();

        $liveVerified = false;
        $liveTrackingInfo = null;

        // If connected to Shopee Open API, verify live order status and tracking info
        if ($this->shopeeService->getSetting()->isConnected()) {
            try {
                if ($order) {
                    $detailResult = $this->shopeeService->getOrderDetail([$order->order_sn]);
                    if ($detailResult['success'] && ! empty($detailResult['data']['order_list'][0])) {
                        $liveOrder = $detailResult['data']['order_list'][0];
                        $liveStatus = $liveOrder['order_status'] ?? $order->order_status;
                        $liveTracking = $liveOrder['tracking_number'] ?? ($liveOrder['package_list'][0]['tracking_number'] ?? null);

                        if ($liveStatus !== $order->order_status || ($liveTracking && empty($order->tracking_number))) {
                            $order->order_status = $liveStatus;
                            if ($liveTracking) {
                                $order->tracking_number = $liveTracking;
                            }
                            $order->save();
                        }
                        $liveVerified = true;
                    }

                    $trackResult = $this->shopeeService->getTrackingInfo($order->order_sn);
                    if ($trackResult['success'] && ! empty($trackResult['data']['tracking_info'])) {
                        $liveTrackingInfo = $trackResult['data']['tracking_info'];
                    }
                } else {
                    // Order not yet in local DB: attempt to fetch directly from Shopee API
                    $detailResult = $this->shopeeService->getOrderDetail([$query]);
                    if ($detailResult['success'] && ! empty($detailResult['data']['order_list'][0])) {
                        $liveOrder = $detailResult['data']['order_list'][0];
                        $rawItemList = $liveOrder['item_list'] ?? [];
                        $items = [];
                        foreach ($rawItemList as $rawItem) {
                            $items[] = [
                                'item_id' => $rawItem['item_id'] ?? null,
                                'item_sku' => $rawItem['item_sku'] ?? null,
                                'item_name' => $rawItem['item_name'] ?? 'Shopee Item',
                                'model_id' => $rawItem['model_id'] ?? null,
                                'model_quantity_purchased' => $rawItem['model_quantity_purchased'] ?? 1,
                                'model_discounted_price' => $rawItem['model_discounted_price'] ?? 0,
                            ];
                        }

                        $orderData = [
                            'order_sn' => $liveOrder['order_sn'],
                            'shop_id' => (int) $this->shopeeService->getSetting()->shop_id,
                            'order_status' => $liveOrder['order_status'] ?? 'READY_TO_SHIP',
                            'total_amount' => $liveOrder['total_amount'] ?? 0,
                            'buyer_username' => $liveOrder['buyer_username'] ?? 'Shopee Customer',
                            'tracking_number' => $liveOrder['tracking_number'] ?? $query,
                            'shipping_carrier' => $liveOrder['shipping_carrier'] ?? 'Shopee Logistics',
                            'items' => $items,
                        ];

                        $order = $this->mutationService->processShopeeOrder($orderData);
                        $order->update([
                            'tracking_number' => $orderData['tracking_number'],
                            'shipping_carrier' => $orderData['shipping_carrier'],
                        ]);
                        $liveVerified = true;
                    }
                }
            } catch (Exception $e) {
                Log::warning('Live Shopee order scan check warning: '.$e->getMessage());
            }
        }

        // Fallback mock order for instant local testing if no Shopee API connected
        if (! $order) {
            $isCancelledQuery = str_contains(strtoupper($query), 'CANCEL');
            $firstProduct = Product::where('status', 'active')->first();
            if ($firstProduct) {
                $order = ShopeeOrder::create([
                    'order_sn' => '26'.date('md').strtoupper(Str::random(6)),
                    'tracking_number' => $query,
                    'shipping_carrier' => 'SPX Express',
                    'shop_id' => ShopeeSetting::current()->shop_id ?? 28491823,
                    'order_status' => $isCancelledQuery ? 'CANCELLED' : 'READY_TO_SHIP',
                    'total_amount' => $firstProduct->selling_price,
                    'buyer_username' => 'shopee_user_'.rand(100, 999),
                    'items_data' => [
                        [
                            'item_id' => $firstProduct->shopee_item_id ?? 184910291,
                            'item_sku' => $firstProduct->sku,
                            'item_name' => $firstProduct->name,
                            'model_quantity_purchased' => 1,
                            'model_discounted_price' => $firstProduct->selling_price,
                        ],
                    ],
                    'stock_deducted' => ! $isCancelledQuery,
                    'stock_deducted_at' => $isCancelledQuery ? null : now(),
                ]);
            }
        }

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => "Data pesanan dengan Resi / Order SN '{$query}' tidak ditemukan.",
            ], 404);
        }

        // Check if Order is CANCELLED / IN_CANCEL / RETURNED
        if ($order->isCancelled()) {
            // Record blocked packing attempt
            PackingRecord::create([
                'order_sn' => $order->order_sn,
                'tracking_number' => $order->tracking_number ?? $query,
                'shopee_order_id' => $order->id,
                'status' => 'blocked_cancelled',
                'packer_name' => $request->input('packer_name', 'Staff Packing'),
                'notes' => "PERINGATAN: Percobaan packing digagalkan karena pesanan telah DIBATALKAN di Shopee (Status: {$order->order_status}).",
            ]);

            return response()->json([
                'status' => 'blocked_cancelled',
                'order_sn' => $order->order_sn,
                'tracking_number' => $order->tracking_number ?? $query,
                'shipping_carrier' => $order->shipping_carrier ?? 'Shopee Shipping',
                'order_status' => $order->order_status,
                'buyer_username' => $order->buyer_username,
                'total_amount' => $order->total_amount,
                'live_verified' => $liveVerified,
                'message' => "🚫 PERINGATAN: Pesanan ini sudah BERSTATUS DIBATALKAN ({$order->order_status}) di Shopee! JANGAN DIPACKING ATAU DISERAHKAN KE KURIR!",
            ]);
        }

        // Format items list with product catalog information
        $formattedItems = [];
        if (! empty($order->items_data) && is_array($order->items_data)) {
            foreach ($order->items_data as $index => $item) {
                $sku = $item['item_sku'] ?? null;
                $product = $sku ? Product::where('sku', $sku)->first() : null;

                $formattedItems[] = [
                    'index' => $index,
                    'name' => $item['item_name'] ?? ($product->name ?? 'Produk Shopee'),
                    'sku' => $sku ?? ($product->sku ?? '-'),
                    'model_name' => $item['model_name'] ?? null,
                    'qty' => (int) ($item['model_quantity_purchased'] ?? ($item['qty'] ?? 1)),
                    'price' => (float) ($item['model_discounted_price'] ?? ($product->selling_price ?? 0)),
                    'unit' => $product->unit ?? 'Pcs',
                    'barcode' => $product->barcode ?? null,
                    'current_stock' => $product->stock ?? 0,
                    'image_url' => $product ? $product->image_url : 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80',
                    'checked' => false,
                ];
            }
        }

        // Check if Order has already been packed previously
        $existingPacking = PackingRecord::where('status', 'completed')
            ->where(function ($q) use ($order, $query) {
                $q->where('order_sn', $order->order_sn);
                if (! empty($order->tracking_number)) {
                    $q->orWhere('tracking_number', $order->tracking_number);
                }
                if (! empty($query)) {
                    $q->orWhere('tracking_number', $query);
                }
            })
            ->latest()
            ->first();

        $alreadyPacked = $existingPacking !== null;
        $existingPackingData = $alreadyPacked ? [
            'id' => $existingPacking->id,
            'packed_at' => $existingPacking->created_at->translatedFormat('d M Y, H:i'),
            'packed_time_diff' => $existingPacking->created_at->diffForHumans(),
            'packer_name' => $existingPacking->packer_name ?: 'Staff Packing',
            'video_url' => $existingPacking->video_url,
            'duration' => $existingPacking->formatted_duration,
        ] : null;

        return response()->json([
            'status' => 'ready',
            'order_id' => $order->id,
            'order_sn' => $order->order_sn,
            'tracking_number' => $order->tracking_number ?? $query,
            'shipping_carrier' => $order->shipping_carrier ?? 'SPX Express',
            'order_status' => $order->order_status,
            'buyer_username' => $order->buyer_username,
            'total_amount' => (float) $order->total_amount,
            'live_verified' => $liveVerified,
            'live_tracking_info' => $liveTrackingInfo,
            'items' => $formattedItems,
            'already_packed' => $alreadyPacked,
            'existing_packing' => $existingPackingData,
            'message' => $alreadyPacked
                ? "Resi ini sudah pernah dipacking oleh {$existingPacking->packer_name} ({$existingPacking->created_at->diffForHumans()})."
                : ($liveVerified
                    ? 'Pesanan aktif & terverifikasi langsung via Shopee API. Silakan periksa produk dan mulai perekaman video packing.'
                    : 'Pesanan aktif & valid. Silakan periksa produk dan mulai perekaman video packing.'),
        ]);
    }

    /**
     * Upload & save recorded packing video.
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_sn' => 'required|string',
            'tracking_number' => 'nullable|string',
            'video' => 'required|file|max:102400', // Max 100MB
            'duration' => 'nullable|integer',
            'packer_name' => 'nullable|string',
            'items_checked' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $order = ShopeeOrder::where('order_sn', $validated['order_sn'])->first();

        // Store video to storage/app/public/packing_videos
        $videoFile = $request->file('video');
        $extension = $videoFile->getClientOriginalExtension() ?: 'webm';
        $filename = 'pack_'.$validated['order_sn'].'_'.date('Ymd_His').'.'.$extension;
        $path = $videoFile->storeAs('packing_videos', $filename, 'public');

        $itemsChecked = [];
        if (! empty($validated['items_checked'])) {
            $itemsChecked = json_decode($validated['items_checked'], true) ?: [];
        }

        $record = PackingRecord::create([
            'order_sn' => $validated['order_sn'],
            'tracking_number' => $validated['tracking_number'] ?? ($order->tracking_number ?? null),
            'shopee_order_id' => $order?->id,
            'status' => 'completed',
            'packer_name' => $validated['packer_name'] ?: 'Staff Packing',
            'items_checked' => $itemsChecked,
            'video_path' => $path,
            'video_duration' => (int) ($validated['duration'] ?? 0),
            'file_size' => $videoFile->getSize(),
            'notes' => $validated['notes'] ?? 'Video packing berhasil disimpan.',
        ]);

        // Update Shopee order status to PROCESSED if ready
        if ($order && $order->order_status === 'READY_TO_SHIP') {
            $order->update(['order_status' => 'PROCESSED']);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Video packing untuk Resi [{$record->tracking_number}] berhasil disimpan!",
            'record_id' => $record->id,
            'video_url' => $record->video_url,
        ]);
    }

    /**
     * Packing History & Video Archive.
     */
    public function history(Request $request)
    {
        $query = PackingRecord::with('shopeeOrder');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('order_sn', 'like', "%{$search}%")
                    ->orWhere('packer_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $records = $query->latest()->paginate(15)->withQueryString();

        return view('packing.history', compact('records'));
    }
}
