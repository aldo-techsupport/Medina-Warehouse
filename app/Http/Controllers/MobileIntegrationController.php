<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileIntegrationController extends Controller
{
    public function __construct(
        protected PackingService $packingService
    ) {}

    /**
     * Mobile API Documentation, Local Network Discovery, & Tester Page.
     */
    public function index(Request $request)
    {
        $serverIps = $this->detectLocalIps($request);
        $port = $request->getPort();
        $scheme = $request->getScheme();
        $portSuffix = ($port && ! in_array($port, [80, 443])) ? ':'.$port : '';

        $localBaseUrls = array_map(function ($ip) use ($scheme, $portSuffix) {
            return "{$scheme}://{$ip}{$portSuffix}/api/v1";
        }, $serverIps);

        $primaryApiUrl = ! empty($localBaseUrls) ? $localBaseUrls[0] : url('/api/v1');

        $users = User::with('role')->where('status', 'active')->get();
        $currentUser = auth()->user();

        $stats = $this->packingService->getStats();

        return view('mobile.index', compact(
            'serverIps',
            'localBaseUrls',
            'primaryApiUrl',
            'users',
            'currentUser',
            'stats'
        ));
    }

    /**
     * Generate or rotate API token for an operator or current user.
     */
    public function generateToken(Request $request): JsonResponse
    {
        $userId = $request->input('user_id') ?: auth()->id();
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        // Only super admin or user themselves can generate token
        if (! auth()->user()->isSuperAdmin() && auth()->id() !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengatur token pengguna ini.',
            ], 403);
        }

        $plainToken = $user->generateApiToken();

        return response()->json([
            'status' => 'success',
            'message' => 'API Token baru berhasil dibuat untuk '.$user->name.'.',
            'token' => $plainToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ],
        ]);
    }

    /**
     * Export Postman Collection v2.1.0 JSON.
     */
    public function postmanCollection(Request $request): StreamedResponse
    {
        $host = $request->getSchemeAndHttpHost();
        $baseUrl = $host.'/api/v1';

        $collection = [
            'info' => [
                'name' => 'Warehouse Storage Mobile & Camera API (v1)',
                '_postman_id' => 'warehouse-storage-api-v1',
                'description' => 'REST APIs for warehouse mobile app: camera barcode scanner, video recording & upload, order verification, and packing archives across local networks without SSL hurdles.',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                [
                    'key' => 'baseUrl',
                    'value' => $baseUrl,
                    'type' => 'string',
                ],
                [
                    'key' => 'authToken',
                    'value' => '',
                    'type' => 'string',
                ],
            ],
            'item' => [
                [
                    'name' => '1. System & Discovery',
                    'item' => [
                        [
                            'name' => 'Ping Health Check',
                            'request' => [
                                'method' => 'GET',
                                'header' => [],
                                'url' => [
                                    'raw' => '{{baseUrl}}/ping',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['ping'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'System & Video Config Status',
                            'request' => [
                                'method' => 'GET',
                                'header' => [],
                                'url' => [
                                    'raw' => '{{baseUrl}}/status',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['status'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => '2. Authentication & Operators',
                    'item' => [
                        [
                            'name' => 'Login (Obtain Bearer Token)',
                            'request' => [
                                'method' => 'POST',
                                'header' => [
                                    ['key' => 'Content-Type', 'value' => 'application/json'],
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                ],
                                'body' => [
                                    'mode' => 'raw',
                                    'raw' => json_encode([
                                        'username' => 'packer',
                                        'password' => 'password',
                                    ], JSON_PRETTY_PRINT),
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/auth/login',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['auth', 'login'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Get Active Packers (Operator List)',
                            'request' => [
                                'method' => 'GET',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/auth/packers',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['auth', 'packers'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Current User Profile (Me)',
                            'request' => [
                                'method' => 'GET',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/auth/me',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['auth', 'me'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Logout',
                            'request' => [
                                'method' => 'POST',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/auth/logout',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['auth', 'logout'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => '3. Camera Scanner & Packing',
                    'item' => [
                        [
                            'name' => 'Scan Barcode / Resi / Order SN',
                            'request' => [
                                'method' => 'POST',
                                'header' => [
                                    ['key' => 'Content-Type', 'value' => 'application/json'],
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'body' => [
                                    'mode' => 'raw',
                                    'raw' => json_encode([
                                        'query' => 'SPXID0481928471',
                                        'packer_name' => 'Siti Packer',
                                    ], JSON_PRETTY_PRINT),
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/scanner/scan',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['scanner', 'scan'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Initialize Background Upload',
                            'request' => [
                                'method' => 'POST',
                                'header' => [
                                    ['key' => 'Content-Type', 'value' => 'application/json'],
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'body' => [
                                    'mode' => 'raw',
                                    'raw' => json_encode([
                                        'order_sn' => '241024ABCDEF1234',
                                        'tracking_number' => 'SPXID0481928471',
                                        'duration' => 25,
                                        'packer_name' => 'Siti Packer',
                                        'items_checked' => [0, 1],
                                        'mode' => 'packing',
                                    ], JSON_PRETTY_PRINT),
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/packing/init-upload',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['packing', 'init-upload'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Upload Recorded Video (Multipart)',
                            'request' => [
                                'method' => 'POST',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'body' => [
                                    'mode' => 'formdata',
                                    'formdata' => [
                                        ['key' => 'video', 'type' => 'file', 'src' => ''],
                                        ['key' => 'order_sn', 'value' => '241024ABCDEF1234', 'type' => 'text'],
                                        ['key' => 'tracking_number', 'value' => 'SPXID0481928471', 'type' => 'text'],
                                        ['key' => 'duration', 'value' => '25', 'type' => 'text'],
                                        ['key' => 'packer_name', 'value' => 'Siti Packer', 'type' => 'text'],
                                        ['key' => 'items_checked', 'value' => json_encode([0, 1]), 'type' => 'text'],
                                        ['key' => 'mode', 'value' => 'packing', 'type' => 'text'],
                                    ],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/packing/upload-video',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['packing', 'upload-video'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => '4. Packing Dashboard & Records',
                    'item' => [
                        [
                            'name' => 'Today Packing Stats',
                            'request' => [
                                'method' => 'GET',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/packing/stats',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['packing', 'stats'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Packing Records History',
                            'request' => [
                                'method' => 'GET',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/packing/history?per_page=15',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['packing', 'history'],
                                    'query' => [
                                        ['key' => 'per_page', 'value' => '15'],
                                        ['key' => 'status', 'value' => 'completed', 'disabled' => true],
                                        ['key' => 'search', 'value' => '', 'disabled' => true],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'name' => 'List Videos Archive (Only with Video)',
                            'request' => [
                                'method' => 'GET',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/packing/videos?per_page=15',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['packing', 'videos'],
                                    'query' => [
                                        ['key' => 'per_page', 'value' => '15'],
                                        ['key' => 'search', 'value' => '', 'disabled' => true],
                                        ['key' => 'date', 'value' => '', 'disabled' => true],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Single Record Detail',
                            'request' => [
                                'method' => 'GET',
                                'header' => [
                                    ['key' => 'Accept', 'value' => 'application/json'],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/packing/records/1',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['packing', 'records', '1'],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Stream Video (HTTP 206 Byte-Range)',
                            'request' => [
                                'method' => 'GET',
                                'header' => [
                                    ['key' => 'Range', 'value' => 'bytes=0-1048576', 'disabled' => true],
                                    ['key' => 'Authorization', 'value' => 'Bearer {{authToken}}'],
                                ],
                                'url' => [
                                    'raw' => '{{baseUrl}}/packing/records/1/stream',
                                    'host' => ['{{baseUrl}}'],
                                    'path' => ['packing', 'records', '1', 'stream'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return response()->streamDownload(function () use ($collection) {
            echo json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, 'warehouse_mobile_api_collection.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Detect local IP addresses of the server for mobile configuration.
     *
     * @return array<int, string>
     */
    protected function detectLocalIps(Request $request): array
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

