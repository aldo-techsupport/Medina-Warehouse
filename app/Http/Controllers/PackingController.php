<?php

namespace App\Http\Controllers;

use App\Models\PackingRecord;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Services\ShopeeService;
use App\Services\StockMutationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();

        $todayPackedCount = PackingRecord::where('status', 'completed')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $todayBlockedCount = PackingRecord::where('status', 'blocked_cancelled')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $recentPacked = PackingRecord::with('shopeeOrder')
            ->where('status', 'completed')
            ->latest()
            ->take(5)
            ->get();

        $allOrders = ShopeeOrder::whereIn('order_status', ['READY_TO_SHIP', 'PROCESSED'])->latest()->take(5)->get();

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

        // Autocorrect common year typo (e.g. 251024 instead of 261024)
        if (! $order && str_starts_with($query, '251024')) {
            $corrected = '261024'.substr($query, 6);
            $order = ShopeeOrder::where('order_sn', $corrected)->orWhere('tracking_number', $corrected)->first();
            if ($order) {
                $query = $corrected;
            }
        }

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

                        $orderUpdated = false;
                        if ($liveStatus !== $order->order_status) {
                            $order->order_status = $liveStatus;
                            $orderUpdated = true;
                        }
                        if ($liveTracking && empty($order->tracking_number)) {
                            $order->tracking_number = $liveTracking;
                            $orderUpdated = true;
                        }

                        // Enrich items_data with Shopee's model_name and image_url if available
                        if (! empty($liveOrder['item_list'])) {
                            $enrichedItems = [];
                            foreach ($liveOrder['item_list'] as $rawItem) {
                                $enrichedItems[] = [
                                    'item_id' => $rawItem['item_id'] ?? null,
                                    'item_sku' => $rawItem['item_sku'] ?? null,
                                    'item_name' => $rawItem['item_name'] ?? 'Shopee Item',
                                    'model_id' => $rawItem['model_id'] ?? null,
                                    'model_name' => $rawItem['model_name'] ?? null,
                                    'model_quantity_purchased' => $rawItem['model_quantity_purchased'] ?? 1,
                                    'model_discounted_price' => $rawItem['model_discounted_price'] ?? 0,
                                    'image_url' => $rawItem['image_info']['image_url'] ?? null,
                                ];
                            }
                            $order->items_data = $enrichedItems;
                            $orderUpdated = true;
                        }

                        if ($orderUpdated) {
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
                    $lookupSn = $query;
                    if (str_starts_with($lookupSn, '251024')) {
                        $lookupSn = '261024'.substr($lookupSn, 6);
                    }

                    $detailResult = $this->shopeeService->getOrderDetail([$lookupSn]);
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
                                'model_name' => $rawItem['model_name'] ?? null,
                                'model_quantity_purchased' => $rawItem['model_quantity_purchased'] ?? 1,
                                'model_discounted_price' => $rawItem['model_discounted_price'] ?? 0,
                                'image_url' => $rawItem['image_info']['image_url'] ?? null,
                            ];
                        }

                        // Try to get tracking number from Shopee logistics API
                        $liveTracking = $liveOrder['tracking_number'] ?? ($liveOrder['package_list'][0]['tracking_number'] ?? null);
                        if (empty($liveTracking)) {
                            $trackRes = $this->shopeeService->getTrackingNumber($liveOrder['order_sn']);
                            if ($trackRes['success'] && ! empty($trackRes['data']['tracking_number'])) {
                                $liveTracking = $trackRes['data']['tracking_number'];
                            }
                        }

                        $orderData = [
                            'order_sn' => $liveOrder['order_sn'],
                            'shop_id' => (int) $this->shopeeService->getSetting()->shop_id,
                            'order_status' => $liveOrder['order_status'] ?? 'READY_TO_SHIP',
                            'total_amount' => $liveOrder['total_amount'] ?? 0,
                            'buyer_username' => $liveOrder['buyer_username'] ?? 'Shopee Customer',
                            'tracking_number' => $liveTracking ?? $query,
                            'shipping_carrier' => $liveOrder['shipping_carrier'] ?? ($liveOrder['package_list'][0]['shipping_carrier'] ?? 'Shopee Logistics'),
                            'items' => $items,
                        ];

                        $order = $this->mutationService->processShopeeOrder($orderData);
                        $order->update([
                            'tracking_number' => $orderData['tracking_number'],
                            'shipping_carrier' => $orderData['shipping_carrier'],
                        ]);
                        $liveVerified = true;
                    } elseif (empty($order)) {
                        // If query was a tracking number (e.g. SPXID...), pull latest orders from Shopee to see if it matches
                        $syncRes = $this->shopeeService->pullAndSyncOrders(1, $this->mutationService);
                        if (! empty($syncRes['success'])) {
                            $order = ShopeeOrder::where('tracking_number', $query)
                                ->orWhere('order_sn', $query)
                                ->first();
                            if ($order) {
                                $liveVerified = true;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning('Live Shopee order scan check warning: '.$e->getMessage());
            }
        }

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => "Data pesanan dengan Resi / Order SN '{$query}' tidak ditemukan.",
            ], 404);
        }

        // Format items list with product catalog information
        $formattedItems = [];
        if (! empty($order->items_data) && is_array($order->items_data)) {
            foreach ($order->items_data as $index => $item) {
                $sku = ! empty($item['item_sku']) ? $item['item_sku'] : null;
                $itemId = $item['item_id'] ?? null;
                $modelId = $item['model_id'] ?? null;

                $product = null;
                if ($sku) {
                    $product = Product::where('sku', $sku)->first();
                }
                if (! $product && $modelId) {
                    $product = Product::where('shopee_model_id', $modelId)->first();
                }
                if (! $product && $itemId) {
                    $product = Product::where('shopee_item_id', $itemId)->first();
                }

                // Determine image url:
                // 1. Direct variation image url from Shopee order
                // 2. Product image url from local database
                // 3. Fallback placeholder
                $imageUrl = ! empty($item['image_url']) ? $item['image_url'] : null;
                if (empty($imageUrl) && $product) {
                    $imageUrl = $product->image_url;
                }
                if (empty($imageUrl)) {
                    $search = strtolower(($item['item_name'] ?? '').' '.($item['model_name'] ?? ''));
                    $imageUrl = match (true) {
                        str_contains($search, 'gms') || str_contains($search, 'gamis') || str_contains($search, 'dress') => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=400&q=80',
                        str_contains($search, 'koko') || str_contains($search, 'kurta') || str_contains($search, 'kemeja') => 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=400&q=80',
                        str_contains($search, 'hjb') || str_contains($search, 'hijab') || str_contains($search, 'pashmina') || str_contains($search, 'kerudung') => 'https://images.unsplash.com/photo-1596704017254-9b121068fb31?auto=format&fit=crop&w=400&q=80',
                        str_contains($search, 'srg') || str_contains($search, 'sarung') => 'https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=400&q=80',
                        str_contains($search, 'mkn') || str_contains($search, 'mukena') => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=400&q=80',
                        default => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=400&q=80',
                    };
                }

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
                    'image_url' => $imageUrl,
                    'checked' => false,
                ];
            }
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
                'notes' => "PERINGATAN: Percobaan packing digagalkan karena pesanan telah DIBATALKAN / DIRETUR di Shopee (Status: {$order->order_status}).",
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
                'items' => $formattedItems,
                'message' => "🚫 PERINGATAN: Pesanan ini sudah BERSTATUS DIBATALKAN / RETUR ({$order->order_status}) di Shopee! JANGAN DIPACKING ATAU DISERAHKAN KE KURIR!",
            ]);
        }

        // Check if Order has already been packed previously or is currently uploading
        $existingPacking = PackingRecord::whereIn('status', ['completed', 'in_progress'])
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

        // If in_progress record was created > 3 minutes ago without video, it was abandoned (browser closed / connection lost)
        if ($existingPacking && $existingPacking->status === 'in_progress' && empty($existingPacking->video_path) && $existingPacking->created_at->isBefore(now()->subMinutes(3))) {
            $completedRecord = PackingRecord::where('status', 'completed')
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

            $existingPacking->delete();
            $existingPacking = $completedRecord;
        }

        $alreadyPacked = $existingPacking !== null;
        $isUploading = $existingPacking?->status === 'in_progress';
        $existingPackingData = $alreadyPacked ? [
            'id' => $existingPacking->id,
            'status' => $existingPacking->status,
            'is_uploading' => $isUploading,
            'packed_at' => $existingPacking->created_at->translatedFormat('d M Y, H:i'),
            'packed_time_diff' => $existingPacking->created_at->diffForHumans(),
            'packer_name' => $existingPacking->packer_name ?: 'Staff Packing',
            'video_url' => $existingPacking->video_url,
            'duration' => $existingPacking->formatted_duration,
        ] : null;

        $customMessage = $alreadyPacked
            ? ($isUploading
                ? "Video packing untuk Resi ini sedang diunggah di latar belakang oleh {$existingPacking->packer_name} ({$existingPacking->created_at->diffForHumans()})."
                : "Resi ini sudah pernah dipacking oleh {$existingPacking->packer_name} ({$existingPacking->created_at->diffForHumans()}).")
            : ($liveVerified
                ? 'Pesanan aktif & terverifikasi langsung via Shopee API. Silakan periksa produk dan mulai perekaman video packing.'
                : 'Pesanan aktif & valid. Silakan periksa produk dan mulai perekaman video packing.');

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
            'is_uploading' => $isUploading,
            'existing_packing' => $existingPackingData,
            'message' => $customMessage,
        ]);
    }

    /**
     * Pre-register a packing record with status 'in_progress' for background processing.
     */
    public function initUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_sn' => 'required|string',
            'tracking_number' => 'nullable|string',
            'duration' => 'nullable|integer',
            'packer_name' => 'nullable|string',
            'items_checked' => 'nullable|string',
            'notes' => 'nullable|string',
            'mode' => 'nullable|string',
        ]);

        $order = ShopeeOrder::where('order_sn', $validated['order_sn'])->first();
        $mode = $validated['mode'] ?? 'packing';
        $isReturn = $mode === 'return_unboxing';

        $itemsChecked = [];
        if (! empty($validated['items_checked'])) {
            $itemsChecked = json_decode($validated['items_checked'], true) ?: [];
        }

        $forceNew = $request->boolean('force_new', false);

        if (! $forceNew) {
            // Check if an existing completed record already exists with valid video on disk
            $existingCompleted = PackingRecord::where('order_sn', $validated['order_sn'])
                ->where('status', 'completed')
                ->whereNotNull('video_path')
                ->latest()
                ->first();

            if ($existingCompleted && $existingCompleted->video_path) {
                $fileExists = file_exists(storage_path('app/public/'.$existingCompleted->video_path))
                    || Storage::disk('public')->exists($existingCompleted->video_path);

                if ($fileExists) {
                    return response()->json([
                        'status' => 'already_completed',
                        'record_id' => $existingCompleted->id,
                        'order_sn' => $existingCompleted->order_sn,
                        'tracking_number' => $existingCompleted->tracking_number,
                        'video_url' => $existingCompleted->video_url,
                        'data' => [
                            'record_id' => $existingCompleted->id,
                            'video_url' => $existingCompleted->video_url,
                        ],
                        'message' => 'Video packing untuk pesanan ini sudah tersimpan di server.',
                    ]);
                }
            }
        }

        // Avoid duplicate in_progress records if one is already in flight
        $record = PackingRecord::where('order_sn', $validated['order_sn'])
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($record) {
            $record->update([
                'tracking_number' => $validated['tracking_number'] ?? ($order->tracking_number ?? null),
                'packer_name' => ($validated['packer_name'] ?? null) ?: $record->packer_name,
                'items_checked' => $itemsChecked,
                'video_duration' => (int) ($validated['duration'] ?? $record->video_duration ?? 0),
            ]);
        } else {
            $record = PackingRecord::create([
                'order_sn' => $validated['order_sn'],
                'tracking_number' => $validated['tracking_number'] ?? ($order->tracking_number ?? null),
                'shopee_order_id' => $order?->id,
                'status' => 'in_progress',
                'packer_name' => ($validated['packer_name'] ?? null) ?: ($isReturn ? 'Staff Pemeriksa Retur' : 'Staff Packing'),
                'items_checked' => $itemsChecked,
                'video_path' => null,
                'video_duration' => (int) ($validated['duration'] ?? 0),
                'file_size' => 0,
                'notes' => $validated['notes'] ?? ($isReturn ? 'Pemeriksaan unboxing selesai, video sedang diunggah di latar belakang.' : 'Packing selesai, video sedang diunggah di latar belakang.'),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'record_id' => $record->id,
            'order_sn' => $record->order_sn,
            'tracking_number' => $record->tracking_number,
            'message' => 'Upload video berhasil diinisialisasi di latar belakang.',
        ]);
    }

    /**
     * Upload & save recorded packing video.
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        try {
            if (! $request->hasFile('video')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File video tidak ditemukan dalam request upload.',
                ], 422);
            }

            $videoFile = $request->file('video');
            if (! $videoFile->isValid()) {
                $errorMsg = match ($videoFile->getError()) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file video melebihi batas upload server.',
                    UPLOAD_ERR_PARTIAL => 'File video hanya terunggah sebagian. Silakan coba lagi.',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file video yang dikirim.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file video ke disk server.',
                    default => 'Upload video gagal dengan kode error: '.$videoFile->getError(),
                };

                return response()->json([
                    'status' => 'error',
                    'message' => $errorMsg,
                ], 422);
            }

            $validated = $request->validate([
                'order_sn' => 'required|string',
                'tracking_number' => 'nullable|string',
                'video' => 'required|file|max:102400', // Max 100MB
                'duration' => 'nullable|integer',
                'packer_name' => 'nullable|string',
                'items_checked' => 'nullable|string',
                'notes' => 'nullable|string',
                'mode' => 'nullable|string',
            ]);

            $order = ShopeeOrder::where('order_sn', $validated['order_sn'])->first();

            // Store video to storage/app/public/packing_videos
            $extension = strtolower($videoFile->getClientOriginalExtension());
            if (! in_array($extension, ['mp4', 'webm', 'mov', 'm4v'])) {
                $mime = $videoFile->getMimeType() ?: '';
                $extension = str_contains($mime, 'mp4') ? 'mp4' : 'webm';
            }

            $filename = 'pack_'.$validated['order_sn'].'_'.date('Ymd_His').'.'.$extension;
            $targetDir = storage_path('app/public/packing_videos');
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $path = null;
            try {
                $path = $videoFile->storeAs('packing_videos', $filename, 'public');
            } catch (\Throwable $e) {
                Log::warning('PackingController storeAs exception, fallback to move: '.$e->getMessage());
                try {
                    $videoFile->move($targetDir, $filename);
                    $path = 'packing_videos/'.$filename;
                } catch (\Throwable $moveEx) {
                    Log::error('PackingController move failed: '.$moveEx->getMessage());
                }
            }

            if (! $path || (! file_exists(storage_path('app/public/'.$path)) && ! Storage::disk('public')->exists($path))) {
                if (file_exists($targetDir.'/'.$filename)) {
                    $path = 'packing_videos/'.$filename;
                } else {
                    $videoFile->move($targetDir, $filename);
                    $path = 'packing_videos/'.$filename;
                }
            }

            if (! $path) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal menyimpan file video ke direktori penyimpanan server.',
                ], 500);
            }

            $itemsChecked = [];
            if (! empty($validated['items_checked'])) {
                $itemsChecked = json_decode($validated['items_checked'], true) ?: [];
            }

            $mode = $validated['mode'] ?? 'packing';
            $isReturn = $mode === 'return_unboxing';

            // Check if there was an initial 'in_progress' record to transition to 'completed'
            $record = PackingRecord::where('order_sn', $validated['order_sn'])
                ->where('status', 'in_progress')
                ->latest()
                ->first();

            if ($record) {
                $record->update([
                    'tracking_number' => $validated['tracking_number'] ?? ($order->tracking_number ?? null),
                    'shopee_order_id' => $order?->id,
                    'status' => 'completed',
                    'packer_name' => ($validated['packer_name'] ?? null) ?: $record->packer_name,
                    'items_checked' => $itemsChecked,
                    'video_path' => $path,
                    'video_duration' => (int) ($validated['duration'] ?? $record->video_duration ?? 0),
                    'file_size' => $videoFile->getSize() ?: 0,
                    'notes' => $validated['notes'] ?? ($isReturn ? 'Video Unboxing & Pemeriksaan Paket Retur Shopee.' : 'Video packing berhasil disimpan.'),
                ]);
            } else {
                $record = PackingRecord::create([
                    'order_sn' => $validated['order_sn'],
                    'tracking_number' => $validated['tracking_number'] ?? ($order->tracking_number ?? null),
                    'shopee_order_id' => $order?->id,
                    'status' => 'completed',
                    'packer_name' => ($validated['packer_name'] ?? null) ?: ($isReturn ? 'Staff Pemeriksa Retur' : 'Staff Packing'),
                    'items_checked' => $itemsChecked,
                    'video_path' => $path,
                    'video_duration' => (int) ($validated['duration'] ?? 0),
                    'file_size' => $videoFile->getSize() ?: 0,
                    'notes' => $validated['notes'] ?? ($isReturn ? 'Video Unboxing & Pemeriksaan Paket Retur Shopee.' : 'Video packing berhasil disimpan.'),
                ]);
            }

            // Update Shopee order status to PROCESSED if ready
            if ($order && $order->order_status === 'READY_TO_SHIP') {
                $order->update(['order_status' => 'PROCESSED']);

                // Jika terhubung ke Shopee Open API dan bukan mock simulasi, kirim sinyal ship_order ke Shopee
                if ($this->shopeeService->getSetting()->isConnected() && ! str_starts_with($order->order_sn, 'ORD-') && ! str_starts_with($order->order_sn, 'SAMPLE-')) {
                    try {
                        $this->shopeeService->shipOrder($order->order_sn);
                    } catch (Exception $e) {
                        Log::info("Shopee shipOrder notice for {$order->order_sn}: ".$e->getMessage());
                    }
                }
            }

            $message = $isReturn
                ? "Video Unboxing & Pemeriksaan Retur untuk Resi [{$record->tracking_number}] berhasil disimpan!"
                : "Video packing untuk Resi [{$record->tracking_number}] berhasil disimpan!";

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'record_id' => $record->id,
                'video_url' => $record->video_url,
                'data' => [
                    'record_id' => $record->id,
                    'video_url' => $record->video_url,
                ],
            ]);
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal: '.collect($ve->errors())->flatten()->implode(', '),
                'errors' => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('PackingController uploadVideo error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'order_sn' => $request->input('order_sn'),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server saat menyimpan video: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Packing History & Video Archive.
     */
    public function history(Request $request)
    {
        // Auto-clean stale in_progress records that were abandoned (> 3 minutes old without video)
        PackingRecord::where('status', 'in_progress')
            ->whereNull('video_path')
            ->where('created_at', '<', now()->subMinutes(3))
            ->delete();

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
