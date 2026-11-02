<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PackingRecord;
use App\Services\PackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MobilePackingController extends Controller
{
    public function __construct(
        protected PackingService $packingService
    ) {}

    /**
     * Scan and validate barcode / QR code / Tracking Number for mobile packing app.
     */
    public function scan(Request $request): JsonResponse
    {
        $rawQuery = (string) ($request->input('query') ?: $request->input('tracking_number') ?: $request->input('barcode') ?: $request->input('order_sn') ?: '');
        $packerName = $request->input('packer_name') ?: ($request->user()?->name ?? 'Staff Packing');

        $result = $this->packingService->checkOrder($rawQuery, $packerName);
        $httpCode = $result['code'] ?? 200;

        return response()->json($result, $httpCode);
    }

    /**
     * Pre-register a packing record before / while recording video.
     */
    public function initUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_sn' => 'required|string',
            'tracking_number' => 'nullable|string',
            'duration' => 'nullable|integer',
            'packer_name' => 'nullable|string',
            'items_checked' => 'nullable',
            'notes' => 'nullable|string',
            'mode' => 'nullable|string|in:packing,return_unboxing',
            'force_new' => 'nullable|boolean',
        ]);

        if (empty($validated['packer_name']) && $request->user()) {
            $validated['packer_name'] = $request->user()->name;
        }

        $forceNew = $request->boolean('force_new', false);
        $result = $this->packingService->initUpload($validated, $forceNew);

        return response()->json($result);
    }

    /**
     * Upload recorded packing or unboxing video from mobile camera.
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        try {
            if (! $request->hasFile('video')) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => 'File video tidak ditemukan dalam request upload.',
                ], 422);
            }

            $videoFile = $request->file('video');
            if (! $videoFile->isValid()) {
                $errorMsg = match ($videoFile->getError()) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file video melebihi batas upload server (PHP upload_max_filesize).',
                    UPLOAD_ERR_PARTIAL => 'File video hanya terunggah sebagian karena koneksi terputus. Silakan coba lagi.',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file video yang dikirim.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file video ke disk server.',
                    default => 'Upload video gagal dengan kode error: '.$videoFile->getError(),
                };

                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => $errorMsg,
                ], 422);
            }

            $validated = $request->validate([
                'order_sn' => 'required|string',
                'tracking_number' => 'nullable|string',
                'video' => 'required|file|max:102400', // Max 100MB
                'duration' => 'nullable|integer',
                'packer_name' => 'nullable|string',
                'items_checked' => 'nullable',
                'notes' => 'nullable|string',
                'mode' => 'nullable|string|in:packing,return_unboxing',
            ]);

            if (empty($validated['packer_name']) && $request->user()) {
                $validated['packer_name'] = $request->user()->name;
            }

            $result = $this->packingService->saveAndOptimizeVideo($videoFile, $validated);

            return response()->json($result, 200);
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Validasi gagal: '.collect($ve->errors())->flatten()->implode(', '),
                'errors' => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('MobilePackingController uploadVideo error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'order_sn' => $request->input('order_sn'),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Terjadi kesalahan pada server saat menyimpan video: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for mobile packing dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->packingService->getStats();

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => $stats,
        ]);
    }

    /**
     * Get paginated packing records archive.
     */
    public function history(Request $request): JsonResponse
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

        if ($request->boolean('has_video') || $request->input('has_video') === '1') {
            $query->whereNotNull('video_path')->where('video_path', '!=', '');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $records = $query->latest()->paginate($perPage);

        $formatted = $records->getCollection()->map(function ($record) {
            return [
                'id' => $record->id,
                'order_sn' => $record->order_sn,
                'tracking_number' => $record->tracking_number,
                'shipping_carrier' => $record->shopeeOrder?->shipping_carrier,
                'status' => $record->status,
                'packer_name' => $record->packer_name,
                'duration' => $record->formatted_duration,
                'duration_seconds' => $record->video_duration,
                'file_size' => $record->file_size,
                'file_size_formatted' => $record->file_size ? round($record->file_size / (1024 * 1024), 2).' MB' : '0 MB',
                'video_url' => $record->video_url ? url($record->video_url) : null,
                'stream_url' => $record->hasVideo() ? url("/api/v1/packing/records/{$record->id}/stream") : null,
                'has_video' => $record->hasVideo(),
                'items_checked' => $record->items_checked,
                'notes' => $record->notes,
                'created_at' => $record->created_at->translatedFormat('d M Y, H:i'),
                'time_diff' => $record->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => $formatted,
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Get list of records that specifically have video attachments for mobile video browser.
     */
    public function videos(Request $request): JsonResponse
    {
        $request->merge(['has_video' => true]);

        return $this->history($request);
    }

    /**
     * Show detail of a single packing record.
     */
    public function show(int $id): JsonResponse
    {
        $record = PackingRecord::with('shopeeOrder')->find($id);

        if (! $record) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Data rekaman packing tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => [
                'id' => $record->id,
                'order_sn' => $record->order_sn,
                'tracking_number' => $record->tracking_number,
                'status' => $record->status,
                'packer_name' => $record->packer_name,
                'duration' => $record->formatted_duration,
                'duration_seconds' => $record->video_duration,
                'file_size' => $record->file_size,
                'file_size_formatted' => $record->file_size ? round($record->file_size / (1024 * 1024), 2).' MB' : '0 MB',
                'video_url' => $record->video_url ? url($record->video_url) : null,
                'stream_url' => $record->hasVideo() ? url("/api/v1/packing/records/{$record->id}/stream") : null,
                'has_video' => $record->hasVideo(),
                'items_checked' => $record->items_checked,
                'notes' => $record->notes,
                'created_at' => $record->created_at->translatedFormat('d M Y, H:i:s'),
                'shopee_order' => $record->shopeeOrder ? [
                    'order_sn' => $record->shopeeOrder->order_sn,
                    'shipping_carrier' => $record->shopeeOrder->shipping_carrier,
                    'order_status' => $record->shopeeOrder->order_status,
                    'buyer_username' => $record->shopeeOrder->buyer_username,
                    'total_amount' => (float) $record->shopeeOrder->total_amount,
                    'items_data' => $record->shopeeOrder->items_data,
                ] : null,
            ],
        ]);
    }

    /**
     * Stream recorded video with HTTP Byte-Range support (206 Partial Content)
     * for seamless mobile seeking / scrubbing on iOS & Android.
     */
    public function stream(int $id, Request $request)
    {
        $record = PackingRecord::find($id);

        if (! $record || ! $record->video_path) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Video rekaman tidak ditemukan untuk ID tersebut.',
            ], 404);
        }

        $diskPath = storage_path('app/public/'.ltrim($record->video_path, '/'));
        if (! file_exists($diskPath)) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'File fisik video tidak ditemukan pada server penyimpanan.',
            ], 404);
        }

        $extension = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => mime_content_type($diskPath) ?: 'video/mp4',
        };

        return response()->file($diskPath, [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

