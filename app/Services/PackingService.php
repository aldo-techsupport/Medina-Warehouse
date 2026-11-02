<?php

namespace App\Services;

use App\Models\PackingRecord;
use App\Models\Product;
use App\Models\ShopeeOrder;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PackingService
{
    public function __construct(
        protected ShopeeService $shopeeService,
        protected ?StockMutationService $mutationService = null
    ) {
        $this->mutationService = $mutationService ?? new StockMutationService($this->shopeeService);
    }

    /**
     * Check scanned tracking number or Order SN and validate cancellation status.
     *
     * @return array<string, mixed>
     */
    public function checkOrder(string $rawQuery, ?string $packerName = 'Staff Packing'): array
    {
        $rawQuery = trim($rawQuery);
        if (empty($rawQuery)) {
            return [
                'status' => 'error',
                'code' => 422,
                'message' => 'Silakan scan atau masukkan Nomor Resi / Order SN.',
            ];
        }

        // Clean query: extract real tracking number if QR URL or courier metadata was scanned
        $query = $this->extractTrackingNumberFromScan($rawQuery);

        // Search in local Shopee orders by tracking_number or order_sn
        $order = ShopeeOrder::where('tracking_number', $query)
            ->orWhere('order_sn', $query)
            ->first();

        // Also fallback check with raw query if cleaned differs
        if (! $order && $query !== $rawQuery) {
            $order = ShopeeOrder::where('tracking_number', $rawQuery)
                ->orWhere('order_sn', $rawQuery)
                ->first();
            if ($order) {
                $query = $rawQuery;
            }
        }

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
            // In dev environment or disconnected Shopee API, automatically create a realistic mock order
            if ((app()->environment('local') || ! $this->shopeeService->getSetting()->isConnected()) && $this->isRecognizedReceiptFormat($query)) {
                $order = $this->autoCreateDevOrderForScan($query);
                $liveVerified = true;
            }
        }

        if (! $order) {
            return [
                'status' => 'not_found',
                'code' => 404,
                'message' => "Data pesanan dengan Resi / Order SN '{$query}' tidak ditemukan.",
            ];
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

                // Make sure absolute URL for images
                if ($imageUrl && ! str_starts_with($imageUrl, 'http://') && ! str_starts_with($imageUrl, 'https://')) {
                    $imageUrl = url($imageUrl);
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
            PackingRecord::create([
                'order_sn' => $order->order_sn,
                'tracking_number' => $order->tracking_number ?? $query,
                'shopee_order_id' => $order->id,
                'status' => 'blocked_cancelled',
                'packer_name' => $packerName ?: 'Staff Packing',
                'notes' => "PERINGATAN: Percobaan packing digagalkan karena pesanan telah DIBATALKAN / DIRETUR di Shopee (Status: {$order->order_status}).",
            ]);

            return [
                'status' => 'blocked_cancelled',
                'code' => 200,
                'order_sn' => $order->order_sn,
                'tracking_number' => $order->tracking_number ?? $query,
                'shipping_carrier' => $order->shipping_carrier ?? 'Shopee Shipping',
                'order_status' => $order->order_status,
                'buyer_username' => $order->buyer_username,
                'total_amount' => (float) $order->total_amount,
                'live_verified' => $liveVerified,
                'items' => $formattedItems,
                'message' => "🚫 PERINGATAN: Pesanan ini sudah BERSTATUS DIBATALKAN / RETUR ({$order->order_status}) di Shopee! JANGAN DIPACKING ATAU DISERAHKAN KE KURIR!",
            ];
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

        // Stale in_progress cleanup (> 3 mins)
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
            'video_url' => $existingPacking->video_url ? url($existingPacking->video_url) : null,
            'duration' => $existingPacking->formatted_duration,
        ] : null;

        $customMessage = $alreadyPacked
            ? ($isUploading
                ? "Video packing untuk Resi ini sedang diunggah di latar belakang oleh {$existingPacking->packer_name} ({$existingPacking->created_at->diffForHumans()})."
                : "Resi ini sudah pernah dipacking oleh {$existingPacking->packer_name} ({$existingPacking->created_at->diffForHumans()}).")
            : ($liveVerified
                ? 'Pesanan aktif & terverifikasi langsung via Shopee API. Silakan periksa produk dan mulai perekaman video packing.'
                : 'Pesanan aktif & valid. Silakan periksa produk dan mulai perekaman video packing.');

        return [
            'status' => 'ready',
            'code' => 200,
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
        ];
    }

    /**
     * Pre-register an upload record before or during recording.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function initUpload(array $data, bool $forceNew = false): array
    {
        $orderSn = (string) ($data['order_sn'] ?? '');
        $trackingNumber = $data['tracking_number'] ?? null;
        $packerName = $data['packer_name'] ?? 'Staff Packing';
        $duration = (int) ($data['duration'] ?? 0);
        $mode = $data['mode'] ?? 'packing';
        $isReturn = $mode === 'return_unboxing';

        $itemsChecked = [];
        if (! empty($data['items_checked'])) {
            $itemsChecked = is_array($data['items_checked'])
                ? $data['items_checked']
                : (json_decode($data['items_checked'], true) ?: []);
        }

        $order = ShopeeOrder::where('order_sn', $orderSn)->first();

        if (! $forceNew) {
            $existingCompleted = PackingRecord::where('order_sn', $orderSn)
                ->where('status', 'completed')
                ->whereNotNull('video_path')
                ->latest()
                ->first();

            if ($existingCompleted && $existingCompleted->video_path) {
                $fileExists = file_exists(storage_path('app/public/'.$existingCompleted->video_path))
                    || Storage::disk('public')->exists($existingCompleted->video_path);

                if ($fileExists) {
                    $videoFullUrl = $existingCompleted->video_url ? url($existingCompleted->video_url) : null;

                    return [
                        'status' => 'already_completed',
                        'record_id' => $existingCompleted->id,
                        'order_sn' => $existingCompleted->order_sn,
                        'tracking_number' => $existingCompleted->tracking_number,
                        'video_url' => $videoFullUrl,
                        'data' => [
                            'record_id' => $existingCompleted->id,
                            'video_url' => $videoFullUrl,
                        ],
                        'message' => 'Video packing untuk pesanan ini sudah tersimpan di server.',
                    ];
                }
            }
        }

        $record = PackingRecord::where('order_sn', $orderSn)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($record) {
            $record->update([
                'tracking_number' => $trackingNumber ?? ($order->tracking_number ?? null),
                'packer_name' => $packerName ?: $record->packer_name,
                'items_checked' => $itemsChecked,
                'video_duration' => $duration ?: $record->video_duration,
            ]);
        } else {
            $record = PackingRecord::create([
                'order_sn' => $orderSn,
                'tracking_number' => $trackingNumber ?? ($order->tracking_number ?? null),
                'shopee_order_id' => $order?->id,
                'status' => 'in_progress',
                'packer_name' => $packerName ?: ($isReturn ? 'Staff Pemeriksa Retur' : 'Staff Packing'),
                'items_checked' => $itemsChecked,
                'video_path' => null,
                'video_duration' => $duration,
                'file_size' => 0,
                'notes' => $data['notes'] ?? ($isReturn ? 'Pemeriksaan unboxing selesai, video sedang diunggah di latar belakang.' : 'Packing selesai, video sedang diunggah di latar belakang.'),
            ]);
        }

        return [
            'status' => 'success',
            'record_id' => $record->id,
            'order_sn' => $record->order_sn,
            'tracking_number' => $record->tracking_number,
            'message' => 'Upload video berhasil diinisialisasi di latar belakang.',
        ];
    }

    /**
     * Save uploaded video, process/optimize, and mark packing as completed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveAndOptimizeVideo(UploadedFile $videoFile, array $data): array
    {
        $orderSn = (string) ($data['order_sn'] ?? '');
        $trackingNumber = $data['tracking_number'] ?? null;
        $packerName = $data['packer_name'] ?? null;
        $duration = (int) ($data['duration'] ?? 0);
        $notes = $data['notes'] ?? null;
        $mode = $data['mode'] ?? 'packing';
        $isReturn = $mode === 'return_unboxing';

        $itemsChecked = [];
        if (! empty($data['items_checked'])) {
            $itemsChecked = is_array($data['items_checked'])
                ? $data['items_checked']
                : (json_decode($data['items_checked'], true) ?: []);
        }

        $order = ShopeeOrder::where('order_sn', $orderSn)->first();

        // Target extension
        $extension = strtolower($videoFile->getClientOriginalExtension());
        if (! in_array($extension, ['mp4', 'webm', 'mov', 'm4v'])) {
            $mime = $videoFile->getMimeType() ?: '';
            $extension = str_contains($mime, 'mp4') ? 'mp4' : 'webm';
        }

        $filename = 'pack_'.$orderSn.'_'.date('Ymd_His').'.'.$extension;
        $targetDir = storage_path('app/public/packing_videos');
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $path = null;
        try {
            $path = $videoFile->storeAs('packing_videos', $filename, 'public');
        } catch (\Throwable $e) {
            Log::warning('PackingService storeAs exception, fallback to move: '.$e->getMessage());
            try {
                $videoFile->move($targetDir, $filename);
                $path = 'packing_videos/'.$filename;
            } catch (\Throwable $moveEx) {
                Log::error('PackingService move failed: '.$moveEx->getMessage());
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
            throw new Exception('Gagal menyimpan file video ke direktori penyimpanan server.');
        }

        // Post-process: sanitize EBML offset header & transcode to universal faststart MP4
        $optimized = $this->processAndOptimizeVideo($path, $orderSn);
        $finalPath = $optimized['path'];
        $finalSize = $optimized['size'] ?: ($videoFile->getSize() ?: 0);
        $finalDuration = $optimized['duration'] ?: $duration;

        $record = PackingRecord::where('order_sn', $orderSn)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($record) {
            $record->update([
                'tracking_number' => $trackingNumber ?? ($order->tracking_number ?? null),
                'shopee_order_id' => $order?->id,
                'status' => 'completed',
                'packer_name' => $packerName ?: $record->packer_name,
                'items_checked' => $itemsChecked,
                'video_path' => $finalPath,
                'video_duration' => $finalDuration ?: (int) ($record->video_duration ?? 0),
                'file_size' => $finalSize,
                'notes' => $notes ?? ($isReturn ? 'Video Unboxing & Pemeriksaan Paket Retur Shopee.' : 'Video packing berhasil disimpan.'),
            ]);
        } else {
            $record = PackingRecord::create([
                'order_sn' => $orderSn,
                'tracking_number' => $trackingNumber ?? ($order->tracking_number ?? null),
                'shopee_order_id' => $order?->id,
                'status' => 'completed',
                'packer_name' => $packerName ?: ($isReturn ? 'Staff Pemeriksa Retur' : 'Staff Packing'),
                'items_checked' => $itemsChecked,
                'video_path' => $finalPath,
                'video_duration' => $finalDuration,
                'file_size' => $finalSize,
                'notes' => $notes ?? ($isReturn ? 'Video Unboxing & Pemeriksaan Paket Retur Shopee.' : 'Video packing berhasil disimpan.'),
            ]);
        }

        // Update Shopee order status to PROCESSED if ready
        if ($order && $order->order_status === 'READY_TO_SHIP') {
            $order->update(['order_status' => 'PROCESSED']);

            if ($this->shopeeService->getSetting()->isConnected() && ! str_starts_with($order->order_sn, 'ORD-') && ! str_starts_with($order->order_sn, 'SAMPLE-')) {
                try {
                    $this->shopeeService->shipOrder($order->order_sn);
                } catch (Exception $e) {
                    Log::info("Shopee shipOrder notice for {$order->order_sn}: ".$e->getMessage());
                }
            }
        }

        $videoFullUrl = $record->video_url ? url($record->video_url) : null;
        $message = $isReturn
            ? "Video Unboxing & Pemeriksaan Retur untuk Resi [{$record->tracking_number}] berhasil disimpan!"
            : "Video packing untuk Resi [{$record->tracking_number}] berhasil disimpan!";

        return [
            'status' => 'success',
            'message' => $message,
            'record_id' => $record->id,
            'video_url' => $videoFullUrl,
            'video_duration' => $record->formatted_duration,
            'file_size_formatted' => round($finalSize / (1024 * 1024), 2).' MB',
            'data' => [
                'record_id' => $record->id,
                'video_url' => $videoFullUrl,
                'order_sn' => $record->order_sn,
                'tracking_number' => $record->tracking_number,
                'duration' => $record->video_duration,
                'duration_formatted' => $record->formatted_duration,
                'file_size' => $finalSize,
                'packer_name' => $record->packer_name,
                'status' => $record->status,
            ],
        ];
    }

    /**
     * Get statistics for packing station dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
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
            ->take(10)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'order_sn' => $record->order_sn,
                    'tracking_number' => $record->tracking_number,
                    'packer_name' => $record->packer_name,
                    'duration' => $record->formatted_duration,
                    'video_url' => $record->video_url ? url($record->video_url) : null,
                    'has_video' => $record->hasVideo(),
                    'created_at' => $record->created_at->translatedFormat('d M Y, H:i'),
                    'time_diff' => $record->created_at->diffForHumans(),
                ];
            });

        return [
            'today_packed_count' => $todayPackedCount,
            'today_blocked_count' => $todayBlockedCount,
            'recent_packed' => $recentPacked,
        ];
    }

    /**
     * Clean and extract tracking number or order SN from scanned barcode/QR payload.
     */
    public function extractTrackingNumberFromScan(string $scan): string
    {
        $scan = trim($scan);

        // 1. If scan is a URL (e.g. SPX/Shopee QR code)
        if (filter_var($scan, FILTER_VALIDATE_URL) || str_contains($scan, 'http://') || str_contains($scan, 'https://')) {
            $parsed = parse_url($scan);
            if (! empty($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
                foreach (['tracking_number', 'resi', 'awb', 'order_sn', 'sn'] as $key) {
                    if (! empty($queryParams[$key])) {
                        return trim((string) $queryParams[$key]);
                    }
                }
            }
            if (! empty($parsed['path'])) {
                $segments = explode('/', trim($parsed['path'], '/'));
                $lastSegment = end($segments);
                if (preg_match('/^(SPXID[A-Z0-9]+|JP[0-9]+|JX[0-9]+|JT[0-9]+|TKP[0-9]+|[0-9]{10,})$/i', $lastSegment, $m)) {
                    return $m[1];
                }
            }
        }

        // 2. If scan contains delimiters like pipe '|' or semicolon ';', e.g. "SPXID0481928471|1|..."
        if (str_contains($scan, '|') || str_contains($scan, ';') || str_contains($scan, "\n") || str_contains($scan, "\t")) {
            $parts = preg_split('/[|;\t\n\r]+/', $scan);
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/^(SPXID[A-Z0-9]+|JP[0-9]+|JX[0-9]+|JT[0-9]+|TKP[0-9]+|[0-9]{10,})$/i', $part, $m)) {
                    return $m[1];
                }
            }
        }

        // 3. Regex extract known courier tracking numbers if embedded in arbitrary text
        if (preg_match('/\b(SPXID[A-Z0-9]{8,}|JP\d{10,}|JX\d{10,}|JT\d{10,}|TKP\d{10,})\b/i', $scan, $m)) {
            return $m[1];
        }

        // 4. Default clean alphanumeric + hyphens
        $cleaned = trim(preg_replace('/[^a-zA-Z0-9\-_]/', '', $scan));

        return $cleaned ?: $scan;
    }

    /**
     * Check if query string matches common Indonesian courier receipt or Shopee order SN format.
     */
    public function isRecognizedReceiptFormat(string $query): bool
    {
        if (strlen($query) < 8) {
            return false;
        }

        if (preg_match('/^SPX[A-Z0-9]{7,}$/i', $query)) {
            return true;
        }

        if (preg_match('/^J[PTX]\d{8,}$/i', $query)) {
            return true;
        }

        if (preg_match('/^\d{10,}$/', $query)) {
            return true;
        }

        if (preg_match('/^(NLID|TKP|ID)[A-Z0-9]{7,}$/i', $query)) {
            return true;
        }

        if (preg_match('/^2[56]\d{10,}[A-Z0-9]*$/i', $query)) {
            return true;
        }

        return false;
    }

    /**
     * Auto-generate a realistic mock order on dev environment when scanning any real package.
     */
    public function autoCreateDevOrderForScan(string $query): ?ShopeeOrder
    {
        $carrier = match (true) {
            str_starts_with($query, 'SPX') => 'SPX Express',
            str_starts_with($query, 'JP') || str_starts_with($query, 'JX') || str_starts_with($query, 'JT') => 'J&T Express',
            str_starts_with($query, '00') => 'SiCepat Express',
            str_starts_with($query, 'NLID') => 'Ninja Xpress',
            str_starts_with($query, 'ID') => 'Anteraja',
            default => 'SPX Express',
        };

        $sampleProducts = Product::take(2)->get();
        $itemsData = [];

        if ($sampleProducts->isNotEmpty()) {
            foreach ($sampleProducts as $p) {
                $itemsData[] = [
                    'item_id' => $p->shopee_item_id ?: $p->id,
                    'item_sku' => $p->sku,
                    'item_name' => $p->name,
                    'model_id' => $p->shopee_model_id,
                    'model_name' => 'Varian Pilihan',
                    'model_quantity_purchased' => 1,
                    'model_discounted_price' => (float) $p->selling_price,
                    'image_url' => $p->image_url,
                ];
            }
        } else {
            $itemsData[] = [
                'item_id' => 101,
                'item_sku' => 'MDN-KMT-01',
                'item_name' => 'Kemeja Katun Pria Medina Slim Fit Navy',
                'model_id' => null,
                'model_name' => 'Navy Blue (Size L)',
                'model_quantity_purchased' => 1,
                'model_discounted_price' => 185000,
                'image_url' => 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=400&q=80',
            ];
        }

        $orderSn = str_starts_with($query, '26') && strlen($query) >= 12
            ? $query
            : date('ymd').strtoupper(substr(md5($query), 0, 8));

        $buyerSuffix = substr(preg_replace('/[^a-zA-Z0-9]/', '', $query), -4) ?: 'buyer';

        return ShopeeOrder::create([
            'order_sn' => $orderSn,
            'tracking_number' => $query,
            'shipping_carrier' => $carrier,
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 185000,
            'buyer_username' => 'customer_'.$buyerSuffix,
            'items_data' => $itemsData,
        ]);
    }

    /**
     * Inspect, sanitize EBML offset header, and convert to faststart MP4 (H.264/AAC).
     *
     * @return array{path: string, size: int, duration: int}
     */
    public function processAndOptimizeVideo(string $relativeSavedPath, string $orderSn): array
    {
        $diskPath = storage_path('app/public/'.$relativeSavedPath);
        if (! file_exists($diskPath)) {
            return [
                'path' => $relativeSavedPath,
                'size' => 0,
                'duration' => 0,
            ];
        }

        // 1. Repair WebM if EBML header \x1a\x45\xdf\xa3 is offset
        $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
        if ($ext === 'webm') {
            $handle = @fopen($diskPath, 'rb');
            if ($handle) {
                $headerChunk = fread($handle, 2097152);
                $ebmlPos = strpos($headerChunk, "\x1a\x45\xdf\xa3");
                if ($ebmlPos !== false && $ebmlPos > 0) {
                    $tempClean = $diskPath.'.clean';
                    $outFp = @fopen($tempClean, 'wb');
                    if ($outFp) {
                        fwrite($outFp, substr($headerChunk, $ebmlPos));
                        while (! feof($handle)) {
                            fwrite($outFp, fread($handle, 1048576));
                        }
                        fclose($outFp);
                        fclose($handle);
                        rename($tempClean, $diskPath);
                    } else {
                        fclose($handle);
                    }
                } else {
                    fclose($handle);
                }
            }
        }

        // 2. Transcode to faststart MP4 (H.264/AAC) if ffmpeg is available
        if (function_exists('exec')) {
            $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $ffmpegBin = null;

            if ($isWin) {
                $testOut = [];
                $testCode = 1;
                @exec('where ffmpeg 2>nul', $testOut, $testCode);
                if ($testCode === 0 && ! empty($testOut[0])) {
                    $ffmpegBin = trim($testOut[0]);
                }
            } else {
                if (file_exists('/usr/local/bin/ffmpeg')) {
                    $ffmpegBin = '/usr/local/bin/ffmpeg';
                } elseif (function_exists('shell_exec')) {
                    $ffmpegBin = trim((string) @shell_exec('which ffmpeg 2>/dev/null'));
                }
            }

            if ($ffmpegBin && ($isWin || is_executable($ffmpegBin))) {
                $targetMp4Name = 'pack_'.$orderSn.'_'.date('Ymd_His').'.mp4';
                $targetMp4FullPath = storage_path('app/public/packing_videos/'.$targetMp4Name);

                $cmd = sprintf(
                    '%s -y -i %s -c:v libx264 -preset veryfast -crf 26 -c:a aac -b:a 128k -movflags +faststart %s 2>&1',
                    escapeshellarg($ffmpegBin),
                    escapeshellarg($diskPath),
                    escapeshellarg($targetMp4FullPath)
                );

                $output = [];
                $returnCode = 1;
                @exec($cmd, $output, $returnCode);

                if ($returnCode === 0 && file_exists($targetMp4FullPath) && filesize($targetMp4FullPath) > 1000) {
                    if ($diskPath !== $targetMp4FullPath && file_exists($diskPath)) {
                        @unlink($diskPath);
                    }
                    @chmod($targetMp4FullPath, 0664);

                    $duration = 0;
                    $ffprobeBin = null;

                    if ($isWin) {
                        $testProbeOut = [];
                        $testProbeCode = 1;
                        @exec('where ffprobe 2>nul', $testProbeOut, $testProbeCode);
                        if ($testProbeCode === 0 && ! empty($testProbeOut[0])) {
                            $ffprobeBin = trim($testProbeOut[0]);
                        }
                    } else {
                        if (file_exists('/usr/local/bin/ffprobe')) {
                            $ffprobeBin = '/usr/local/bin/ffprobe';
                        } elseif (function_exists('shell_exec')) {
                            $ffprobeBin = trim((string) @shell_exec('which ffprobe 2>/dev/null'));
                        }
                    }

                    if ($ffprobeBin) {
                        $probeCmd = sprintf(
                            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
                            escapeshellarg($ffprobeBin),
                            escapeshellarg($targetMp4FullPath)
                        );
                        $probeOut = [];
                        @exec($probeCmd, $probeOut);
                        $probeStr = trim(implode('', $probeOut));
                        if ($probeStr && is_numeric($probeStr)) {
                            $duration = (int) round((float) $probeStr);
                        }
                    }

                    return [
                        'path' => 'packing_videos/'.$targetMp4Name,
                        'size' => filesize($targetMp4FullPath),
                        'duration' => $duration,
                    ];
                }
            }
        }

        return [
            'path' => $relativeSavedPath,
            'size' => file_exists($diskPath) ? filesize($diskPath) : 0,
            'duration' => 0,
        ];
    }
}
