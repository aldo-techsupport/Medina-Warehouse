<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopeeSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSystemController extends Controller
{
    /**
     * Get system and hardware status for mobile client configuration.
     */
    public function status(Request $request): JsonResponse
    {
        $shopeeConnected = false;
        try {
            $setting = ShopeeSetting::current();
            $shopeeConnected = $setting->isConnected();
        } catch (\Throwable) {
            $shopeeConnected = false;
        }

        $serverIps = $this->detectServerIps($request);

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'app_name' => config('app.name', 'Warehouse Storage'),
            'api_version' => 'v1.0.0',
            'server_time' => now()->toIso8601String(),
            'server_timezone' => config('app.timezone', 'Asia/Jakarta'),
            'local_server_urls' => collect($serverIps)->map(function ($ip) use ($request) {
                $port = $request->getPort();
                $scheme = $request->getScheme();
                $portSuffix = ($port && ! in_array($port, [80, 443])) ? ':'.$port : '';

                return "{$scheme}://{$ip}{$portSuffix}/api/v1";
            })->values(),
            'current_base_url' => url('/api/v1'),
            'video_config' => [
                'max_file_size_bytes' => 104857600, // 100MB
                'max_file_size_formatted' => '100 MB',
                'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                'php_post_max_size' => ini_get('post_max_size'),
                'recommended_resolution' => '1280x720 (720p)',
                'recommended_fps' => 30,
                'recommended_bitrate' => '1.5 Mbps - 2.5 Mbps',
                'supported_formats' => ['mp4', 'webm', 'mov', 'm4v'],
            ],
            'shopee_connected' => $shopeeConnected,
            'warehouse_station_auth' => [
                'auth_supported' => true,
                'optional_for_local_lan' => true,
            ],
        ]);
    }

    /**
     * Quick health-check ping.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'code' => 200,
            'message' => 'Warehouse Mobile API is online & reachable.',
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Detect local IP addresses of the server for mobile configuration.
     *
     * @return array<int, string>
     */
    protected function detectServerIps(Request $request): array
    {
        $ips = [];

        // 1. Host from incoming request
        $host = $request->getHost();
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        }

        // 2. Server address from PHP
        if (! empty($_SERVER['SERVER_ADDR'])) {
            $ips[] = $_SERVER['SERVER_ADDR'];
        }

        // 3. Linux hostname -I
        if (function_exists('shell_exec')) {
            $raw = @shell_exec('hostname -I 2>/dev/null');
            if ($raw) {
                $parts = preg_split('/\s+/', trim($raw));
                foreach ($parts as $p) {
                    if (filter_var($p, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $ips[] = $p;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ips)));
    }
}

