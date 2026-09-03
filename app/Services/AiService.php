<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\AiChatMessage;
use App\Models\Product;
use App\Models\ShopeeOrder;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiService
{
    /**
     * Determine if AI API key is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('ai.api_key'));
    }

    /**
     * Get the normalized chat completions endpoint URL.
     */
    public function getChatCompletionsUrl(): string
    {
        $baseUrl = rtrim(config('ai.base_url', 'https://openrouter.ai/api/v1'), '/');

        if (Str::endsWith($baseUrl, '/chat/completions')) {
            return $baseUrl;
        }

        return $baseUrl.'/chat/completions';
    }

    /**
     * Gather comprehensive live warehouse & sales metrics from the database.
     */
    public function gatherWarehouseMetrics(): array
    {
        // 1. Product & Inventory Metrics
        $products = Product::where('status', 'active')->get();
        $totalProducts = $products->count();
        $totalStockUnits = (int) $products->sum('stock');
        $totalAssetValue = (float) $products->sum(fn ($p) => $p->stock * (float) $p->purchase_price);

        $lowStockProducts = $products->filter(fn ($p) => $p->stock <= $p->safety_stock)->map(fn ($p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category,
            'stock' => $p->stock,
            'safety_stock' => $p->safety_stock,
            'selling_price' => (float) $p->selling_price,
            'purchase_price' => (float) $p->purchase_price,
            'is_shopee' => ! empty($p->shopee_item_id),
        ])->values()->all();

        $healthyProducts = $products->filter(fn ($p) => $p->stock > $p->safety_stock)->map(fn ($p) => [
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category,
            'stock' => $p->stock,
            'selling_price' => (float) $p->selling_price,
        ])->values()->all();

        // 2. Shopee Sales & Orders Metrics
        $shopeeOrders = ShopeeOrder::latest()->get();
        $totalOrdersCount = $shopeeOrders->count();
        $totalShopeeRevenue = (float) $shopeeOrders->whereNotIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN'])->sum('total_amount');
        $cancelledOrdersCount = $shopeeOrders->whereIn('order_status', ['CANCELLED', 'IN_CANCEL', 'TO_RETURN'])->count();
        $completedOrdersCount = $shopeeOrders->where('order_status', 'COMPLETED')->count();
        $readyOrdersCount = $shopeeOrders->where('order_status', 'READY_TO_SHIP')->count();

        $todayOrders = $shopeeOrders->filter(fn ($o) => $o->created_at && $o->created_at->isToday());
        $todayRevenue = (float) $todayOrders->whereNotIn('order_status', ['CANCELLED', 'IN_CANCEL'])->sum('total_amount');

        // Extract sold items frequency
        $itemSalesCount = [];
        foreach ($shopeeOrders as $order) {
            if ($order->isCancelled() || empty($order->items_data)) {
                continue;
            }
            foreach ($order->items_data as $item) {
                $sku = $item['item_sku'] ?? ($item['item_name'] ?? 'UNKNOWN');
                $qty = (int) ($item['model_quantity_purchased'] ?? 1);
                $itemSalesCount[$sku] = ($itemSalesCount[$sku] ?? 0) + $qty;
            }
        }
        arsort($itemSalesCount);

        // 3. Stock Mutation Trends (Last 30 Days)
        $thirtyDaysAgo = now()->subDays(30);
        $recentMutations = StockMutation::where('created_at', '>=', $thirtyDaysAgo)->get();
        $inboundTotal = (int) $recentMutations->where('type', 'inbound')->sum('qty');
        $outboundTotal = abs((int) $recentMutations->where('type', 'outbound')->sum('qty'));
        $shopeeSaleTotal = abs((int) $recentMutations->where('type', 'shopee_sale')->sum('qty'));

        // Identify Slow Moving Items (active items with 0 sales in itemSalesCount)
        $slowMovingProducts = $products->filter(function ($p) use ($itemSalesCount) {
            return ! isset($itemSalesCount[$p->sku]) && $p->stock > 5;
        })->map(fn ($p) => [
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category,
            'stock' => $p->stock,
            'selling_price' => (float) $p->selling_price,
            'capital_tied_up' => (float) ($p->stock * $p->purchase_price),
        ])->values()->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'inventory' => [
                'total_products' => $totalProducts,
                'total_stock_units' => $totalStockUnits,
                'total_asset_value' => $totalAssetValue,
                'low_stock_count' => count($lowStockProducts),
                'low_stock_items' => $lowStockProducts,
                'slow_moving_count' => count($slowMovingProducts),
                'slow_moving_items' => $slowMovingProducts,
                'healthy_items' => array_slice($healthyProducts, 0, 10),
            ],
            'sales' => [
                'total_orders' => $totalOrdersCount,
                'total_revenue' => $totalShopeeRevenue,
                'average_order_value' => $totalOrdersCount > 0 ? round($totalShopeeRevenue / $totalOrdersCount, 2) : 0,
                'ready_to_ship_orders' => $readyOrdersCount,
                'completed_orders' => $completedOrdersCount,
                'cancelled_orders' => $cancelledOrdersCount,
                'today_orders_count' => $todayOrders->count(),
                'today_revenue' => $todayRevenue,
                'top_selling_skus' => array_slice($itemSalesCount, 0, 5, true),
            ],
            'mutations_30d' => [
                'inbound_qty' => $inboundTotal,
                'outbound_qty' => $outboundTotal,
                'shopee_sale_qty' => $shopeeSaleTotal,
            ],
        ];
    }

    /**
     * Generate an AI sales & marketing analysis.
     */
    public function generateSalesAnalysis(?int $userId = null): AiAnalysis
    {
        $metrics = $this->gatherWarehouseMetrics();

        if (! $this->isConfigured()) {
            return $this->generateFallbackAnalysis($metrics, $userId, 'Smart Rule-Based Engine (API Key belum diisi di .env)');
        }

        try {
            $prompt = $this->buildAnalysisPrompt($metrics);
            $response = $this->sendChatCompletion([
                ['role' => 'system', 'content' => 'Anda adalah konsultan senior e-commerce dan pakar strategi pemasaran ritel marketplace Shopee untuk brand pakaian muslim "Medina Warehouse". Berikan analisis mendalam, tajam, profesional, dan dapat langsung dieksekusi. Berikan output HANYA dalam format JSON valid tanpa teks lain.'],
                ['role' => 'user', 'content' => $prompt],
            ], jsonMode: true);

            $parsed = $this->parseJsonPayload($response['content'] ?? '');

            if ($parsed && isset($parsed['summary'])) {
                return AiAnalysis::create([
                    'user_id' => $userId,
                    'model_used' => config('ai.model'),
                    'summary' => $parsed['summary'],
                    'marketing_advice' => $parsed['marketing_advice'] ?? [],
                    'inventory_advice' => $parsed['inventory_advice'] ?? [],
                    'actionable_steps' => $parsed['actionable_steps'] ?? [],
                    'raw_metrics' => $metrics,
                ]);
            }

            Log::warning('AI Analysis response was not valid JSON, using fallback parser', ['response' => $response]);

            return $this->generateFallbackAnalysis($metrics, $userId, config('ai.model'));
        } catch (\Throwable $e) {
            Log::error('AI Analysis failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return $this->generateFallbackAnalysis($metrics, $userId, 'Fallback Engine (Koneksi Router Error: '.$e->getMessage().')');
        }
    }

    /**
     * Handle multi-turn interactive chat for sellers.
     */
    public function chat(User $user, string $userMessage, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: (string) Str::uuid();

        // 1. Record user message
        AiChatMessage::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $userMessage,
            'model_used' => config('ai.model'),
        ]);

        // 2. Fetch recent conversation history
        $recentMessages = AiChatMessage::where('session_id', $sessionId)
            ->where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->reverse();

        $metrics = $this->gatherWarehouseMetrics();

        // 3. Check if router is configured
        if (! $this->isConfigured()) {
            $replyContent = $this->generateSmartChatFallback($userMessage, $metrics);

            AiChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $replyContent,
                'model_used' => 'Local Warehouse Assistant (API Key belum diisi)',
            ]);

            return [
                'success' => true,
                'reply' => $replyContent,
                'session_id' => $sessionId,
                'model' => 'Smart Local Assistant',
                'created_at' => now()->format('H:i'),
            ];
        }

        try {
            $systemInstruction = $this->buildChatSystemPrompt($metrics, $user);

            $payloadMessages = [
                ['role' => 'system', 'content' => $systemInstruction],
            ];

            $messageCount = $recentMessages->count();
            $index = 0;
            foreach ($recentMessages as $msg) {
                $index++;
                $content = $msg->content;
                if ($index === $messageCount && $msg->role === 'user') {
                    $contextSnippet = $this->buildUserContextSnippet($metrics);
                    $content = $contextSnippet."\n\n[PERTANYAAN SELLER]:\n".$content;
                }

                $payloadMessages[] = [
                    'role' => $msg->role,
                    'content' => $content,
                ];
            }

            $response = $this->sendChatCompletion($payloadMessages);
            $replyContent = $response['content'] ?? 'Maaf, asisten AI tidak dapat memproses jawaban saat ini.';
            $modelUsed = $response['model'] ?? config('ai.model');
            $tokens = $response['tokens'] ?? null;

            AiChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $replyContent,
                'model_used' => $modelUsed,
                'tokens' => $tokens,
            ]);

            return [
                'success' => true,
                'reply' => $replyContent,
                'session_id' => $sessionId,
                'model' => $modelUsed,
                'created_at' => now()->format('H:i'),
            ];
        } catch (\Throwable $e) {
            Log::error('AI Chat error: '.$e->getMessage());

            $fallbackReply = "⚠️ **Kendala Koneksi Router AI:** {$e->getMessage()}\n\n"
                .$this->generateSmartChatFallback($userMessage, $metrics);

            AiChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $fallbackReply,
                'model_used' => 'Emergency Fallback Assistant',
            ]);

            return [
                'success' => true,
                'reply' => $fallbackReply,
                'session_id' => $sessionId,
                'model' => 'Emergency Fallback',
                'created_at' => now()->format('H:i'),
            ];
        }
    }

    /**
     * Test connection to the configured AI router endpoint.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'AI_API_KEY belum diset di file .env. Silakan isi AI_API_KEY terlebih dahulu.',
                'base_url' => config('ai.base_url'),
                'model' => config('ai.model'),
            ];
        }

        try {
            $startTime = microtime(true);
            $response = $this->sendChatCompletion([
                ['role' => 'user', 'content' => 'Halo! Balas dengan satu kata "ONLINE" dan nama model Anda.'],
            ]);
            $latency = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'message' => 'Koneksi ke AI Router berhasil! Respon: '.$response['content'],
                'latency_ms' => $latency,
                'base_url' => config('ai.base_url'),
                'model' => config('ai.model'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal terhubung ke AI Router: '.$e->getMessage(),
                'base_url' => config('ai.base_url'),
                'model' => config('ai.model'),
            ];
        }
    }

    /**
     * Send chat completion request to OpenAI-compatible router API.
     */
    protected function sendChatCompletion(array $messages, bool $jsonMode = false): array
    {
        $url = $this->getChatCompletionsUrl();
        $apiKey = config('ai.api_key');
        $model = config('ai.model');
        $timeout = config('ai.timeout', 60);

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('ai.site_url', 'http://localhost:8000'),
            'X-Title' => config('ai.site_name', 'Medina Warehouse'),
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => config('ai.temperature', 0.7),
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $http = Http::withHeaders($headers)
            ->timeout($timeout);

        $response = $http->post($url, $payload);

        if ($response->failed()) {
            $errorMsg = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("Router API [{$response->status()}]: {$errorMsg}");
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? '';
        $tokens = $json['usage']['total_tokens'] ?? null;
        $actualModel = $json['model'] ?? $model;

        return [
            'content' => trim($content),
            'tokens' => $tokens,
            'model' => $actualModel,
        ];
    }

    /**
     * Build prompt for sales data analysis.
     */
    protected function buildAnalysisPrompt(array $metrics): string
    {
        $metricsJson = json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Analisis data penjualan dan inventaris toko pakaian muslim "Medina Warehouse" berikut ini:

DATA TOKO:
{$metricsJson}

Berikan output HANYA format JSON valid dengan struktur persis berikut:
{
  "summary": "Tinjauan komprehensif kinerja penjualan, kesehatan stok, dan potensi pertumbuhan dalam 2-3 paragraf.",
  "marketing_advice": [
    {
      "title": "Judul Saran Marketing (contoh: Strategi Bundling Best Seller & Slow Moving)",
      "badge": "Promo Shopee / Bundling / Flash Sale / Iklan",
      "description": "Uraian detail strategi pemasaran yang harus dilakukan seller.",
      "action": "Langkah praktis yang disarankan (misal: Buat voucher diskon 10% minimal beli 2)",
      "impact": "Estimasi dampak positif terhadap omzet atau perputaran barang"
    }
  ],
  "inventory_advice": [
    {
      "title": "Judul Saran Inventaris (contoh: Restock Darurat SKU Menipis)",
      "priority": "Tinggi / Sedang / Rendah",
      "description": "Uraian status stok produk dan proyeksi kehabisan barang.",
      "recommendation": "Jumlah restock yang disarankan dan timeline eksekusi"
    }
  ],
  "actionable_steps": [
    {
      "step": 1,
      "task": "Tugas prioritas 1",
      "category": "Pemasaran / Operasional / Stok",
      "target_sku": "SKU atau Umum"
    },
    {
      "step": 2,
      "task": "Tugas prioritas 2",
      "category": "Pemasaran / Operasional / Stok",
      "target_sku": "SKU atau Umum"
    },
    {
      "step": 3,
      "task": "Tugas prioritas 3",
      "category": "Pemasaran / Operasional / Stok",
      "target_sku": "SKU atau Umum"
    }
  ]
}
PROMPT;
    }

    /**
     * Build system prompt for interactive chat with warehouse awareness.
     */
    protected function buildChatSystemPrompt(array $metrics, User $user): string
    {
        $lowStockList = collect($metrics['inventory']['low_stock_items'])->map(fn ($i) => "{$i['name']} ({$i['sku']}) - Sisa Stok: {$i['stock']} pcs (Safety: {$i['safety_stock']})")->implode("\n- ");
        $topList = collect($metrics['sales']['top_selling_skus'])->map(fn ($qty, $sku) => "{$sku}: terjual {$qty} pcs")->implode("\n- ");
        $slowList = collect($metrics['inventory']['slow_moving_items'])->map(fn ($i) => "{$i['name']} ({$i['sku']}) - Stok Mengendap: {$i['stock']} pcs")->implode("\n- ");

        $totalRevenueRp = 'Rp '.number_format($metrics['sales']['total_revenue'], 0, ',', '.');
        $todayRevenueRp = 'Rp '.number_format($metrics['sales']['today_revenue'], 0, ',', '.');
        $todayOrders = $metrics['sales']['today_orders_count'];
        $totalStock = $metrics['inventory']['total_stock_units'];
        $roleName = $user->role->name ?? 'Seller';

        return <<<SYSTEM
PERATURAN UTAMA & IDENTITAS PERAN:
1. Anda adalah "Asisten Analisis Penjualan & Pemasaran Medina Warehouse".
2. DILARANG KERAS mengaku atau menyebut diri sebagai asisten koding, pemrograman, developer perangkat lunak, Qoder, atau menyebut nama model teknis AI. Anda adalah asisten dan penasihat bisnis operasional toko pakaian muslim Medina Warehouse.
3. Anda MEMILIKI data riil penjualan dan stok inventaris toko hari ini di bawah. JANGAN PERNAH menyatakan bahwa Anda tidak memiliki akses ke data penjualan!
4. Berikan jawaban dan analisis berbasis data riil berikut ini untuk membantu seller menentukan langkah bisnis harian, promosi, dan restock barang.

DATA TOKO & PENJUALAN HARI INI:
- Total Stok Gudang: {$totalStock} unit
- Total Omzet Shopee: {$totalRevenueRp} ({$metrics['sales']['total_orders']} pesanan)
- Penjualan Shopee Hari Ini: {$todayRevenueRp} ({$todayOrders} pesanan)
- Produk Stok Kritis (Mendesak Restock):
- {$lowStockList}
- Produk Terlaris (Best Seller):
- {$topList}
- Produk Lambat Terjual (Slow Moving):
- {$slowList}

PANDUAN KOMUNIKASI:
1. Bersikap ramah, suportif, proaktif, dan gunakan Bahasa Indonesia yang profesional dan mudah dipahami.
2. Manfaatkan angka dan data riil toko di atas saat menjawab pertanyaan {$user->name} ({$roleName}).
3. Berikan saran strategi konkrit (bundling promo, flash sale, voucher diskon, atau instruksi restock ke vendor).
4. Gunakan format Markdown (bold, bullet points) agar rapi dan enak dibaca.
SYSTEM;
    }

    /**
     * Build context snippet injected into the user query.
     */
    protected function buildUserContextSnippet(array $metrics): string
    {
        $lowStockList = collect($metrics['inventory']['low_stock_items'])->map(fn ($i) => "- {$i['name']} ({$i['sku']}): sisa {$i['stock']} pcs (batas aman: {$i['safety_stock']} pcs)")->implode("\n");
        $topList = collect($metrics['sales']['top_selling_skus'])->map(fn ($qty, $sku) => "- {$sku}: terjual {$qty} pcs")->implode("\n");
        $slowList = collect($metrics['inventory']['slow_moving_items'])->map(fn ($i) => "- {$i['name']} ({$i['sku']}): mengendap {$i['stock']} pcs")->implode("\n");
        $totalRevenue = number_format($metrics['sales']['total_revenue'], 0, ',', '.');
        $todayRevenue = number_format($metrics['sales']['today_revenue'], 0, ',', '.');
        $todayOrders = $metrics['sales']['today_orders_count'];

        return <<<CTX
[PERAN & PANDUAN PENTING]:
Anda adalah Asisten Analisis Penjualan & Pemasaran Medina Warehouse (BUKAN asisten pemrograman/koding, JANGAN sebut diri Anda Qoder atau asisten koding).
Anda MEMILIKI data toko Medina Warehouse hari ini:
- Penjualan Hari Ini: Rp {$todayRevenue} ({$todayOrders} pesanan)
- Total Akumulasi Omzet Shopee: Rp {$totalRevenue} ({$metrics['sales']['total_orders']} pesanan)
- Total Stok Gudang: {$metrics['inventory']['total_stock_units']} unit
- Produk Stok Menipis / Kritis:
{$lowStockList}
- Produk Best Seller:
{$topList}
- Produk Slow-Moving:
{$slowList}

Tolong jawab pertanyaan seller berikut ini secara langsung sebagai Asisten Penjualan Medina dengan memberikan langkah konkret dan solusi:
CTX;
    }

    /**
     * Rule-based fallback analysis using real warehouse metrics.
     */
    protected function generateFallbackAnalysis(array $metrics, ?int $userId, string $modelName): AiAnalysis
    {
        $lowStockItems = $metrics['inventory']['low_stock_items'];
        $slowMovingItems = $metrics['inventory']['slow_moving_items'];
        $topSkus = $metrics['sales']['top_selling_skus'];
        $totalRevenueRp = 'Rp '.number_format($metrics['sales']['total_revenue'], 0, ',', '.');
        $totalOrders = $metrics['sales']['total_orders'];

        $summary = "Berdasarkan evaluasi sistem pada data toko Medina Warehouse, tercatat total {$totalOrders} pesanan Shopee dengan akumulasi omzet {$totalRevenueRp}. "
            .'Saat ini terdapat '.count($lowStockItems).' produk dengan tingkat persediaan di bawah safety stock yang memerlukan tindakan restock segera. '
            .'Selain itu, teridentifikasi '.count($slowMovingItems).' produk dengan perputaran lambat (slow-moving) yang membutuhkan stimulus promosi atau penawaran bundling untuk mencairkan modal yang mengendap.';

        $marketingAdvice = [
            [
                'title' => 'Strategi Paket Bundling (Best Seller + Slow Moving)',
                'badge' => 'Bundling Shopee',
                'description' => 'Gabungkan produk favorit dengan produk berstok tinggi. Contoh: Bundling Gamis Silk Premium dengan Hijab Pashmina Plisket untuk menaikkan Average Order Value (AOV).',
                'action' => 'Buat Paket Kombo Hemat di Shopee Seller Centre dengan diskon 10-15% khusus pembelian paket.',
                'impact' => 'Mempercepat perputaran produk hijab sekaligus mendongkrak margin transaksi gamis.',
            ],
            [
                'title' => 'Flash Sale Terjadwal untuk Produk Menumpuk',
                'badge' => 'Flash Sale Toko',
                'description' => 'Adakan Flash Sale Toko Saya pada jam prime time (12:00 - 13:00 dan 19:00 - 21:00) untuk produk perlengkapan ibadah seperti Mukena dan Sarung.',
                'action' => 'Alokasikan kuota diskon khusus 20 pcs per item dengan batasan waktu 3 jam.',
                'impact' => 'Mengosongkan ruang rak gudang dan menarik traffic pembeli baru.',
            ],
            [
                'title' => 'Broadcast Chat & Voucher Pembeli Setia',
                'badge' => 'Retensi Pelanggan',
                'description' => 'Kirimkan pesan broadcast chat kepada pelanggan yang pernah berbelanja di Medina Warehouse dengan menawarkan voucher eksklusif potongan belanja.',
                'action' => 'Buat voucher diskon Rp 15.000 minimal belanja Rp 200.000 yang berlaku selama 48 jam.',
                'impact' => 'Meningkatkan tingkat repeat order pembeli hingga 20-30%.',
            ],
        ];

        $inventoryAdvice = [];
        if (! empty($lowStockItems)) {
            foreach (array_slice($lowStockItems, 0, 3) as $item) {
                $inventoryAdvice[] = [
                    'title' => "Restock Prioritas: {$item['name']}",
                    'priority' => 'Tinggi',
                    'description' => "Stok SKU {$item['sku']} tersisa {$item['stock']} pcs, berada di bawah batas aman ({$item['safety_stock']} pcs). Resiko lost-sales tinggi di Shopee.",
                    'recommendation' => 'Terbitkan Purchase Order minimal 30-50 pcs kepada vendor pekan ini.',
                ];
            }
        } else {
            $inventoryAdvice[] = [
                'title' => 'Tingkat Persediaan dalam Status Sehat',
                'priority' => 'Rendah',
                'description' => 'Seluruh produk saat ini berada di atas batas safety stock minimum.',
                'recommendation' => 'Pertahankan pemantauan berkala dan siapkan buffer stok menjelang event tanggal kembar.',
            ];
        }

        $actionableSteps = [
            [
                'step' => 1,
                'task' => 'Hubungi vendor konveksi untuk order ulang produk berkategori Gamis yang stoknya tersisa di bawah 10 pcs.',
                'category' => 'Stok & Gudang',
                'target_sku' => ! empty($lowStockItems) ? $lowStockItems[0]['sku'] : 'Umum',
            ],
            [
                'step' => 2,
                'task' => 'Aktifkan fitur Kombo Hemat / Paket Diskon di Shopee Seller Centre untuk produk Pashmina dan Mukena.',
                'category' => 'Pemasaran',
                'target_sku' => 'MDN-HJB-001 & MDN-MKN-001',
            ],
            [
                'step' => 3,
                'task' => 'Konfigurasikan API Router AI di file .env (AI_API_KEY) untuk mengaktifkan analisis generatif LLM yang lebih personal.',
                'category' => 'Sistem AI',
                'target_sku' => 'Konfigurasi .env',
            ],
        ];

        return AiAnalysis::create([
            'user_id' => $userId,
            'model_used' => $modelName,
            'summary' => $summary,
            'marketing_advice' => $marketingAdvice,
            'inventory_advice' => $inventoryAdvice,
            'actionable_steps' => $actionableSteps,
            'raw_metrics' => $metrics,
        ]);
    }

    /**
     * Smart local assistant response for chat when API key is not yet set.
     */
    protected function generateSmartChatFallback(string $message, array $metrics): string
    {
        $msgLower = strtolower($message);
        $lowStock = $metrics['inventory']['low_stock_items'];
        $top = $metrics['sales']['top_selling_skus'];

        if (str_contains($msgLower, 'restock') || str_contains($msgLower, 'stok') || str_contains($msgLower, 'habis')) {
            if (empty($lowStock)) {
                return '✅ **Status Stok Aman:** Saat ini tidak ada produk yang berada di bawah batas safety stock. Seluruh produk memiliki persediaan yang mencukupi.';
            }

            $itemsText = collect($lowStock)->map(fn ($i) => "- **{$i['name']}** (`{$i['sku']}`): Sisa **{$i['stock']} pcs** (Batas aman: {$i['safety_stock']} pcs)")->implode("\n");

            return "🚨 **Daftar Produk yang Mendesak untuk Di-restock:**\n\n{$itemsText}\n\n💡 *Saran Seller:* Segera terbitkan Surat Pesanan (PO) ke penjahit/konveksi minimal 30-50 pcs untuk mencegah status 'Stok Habis' di Shopee yang dapat menurunkan peringkat pencarian toko.";
        }

        if (str_contains($msgLower, 'bundling') || str_contains($msgLower, 'paket') || str_contains($msgLower, 'promo')) {
            return "🎁 **Rekomendasi Paket Bundling & Promo Shopee:**\n\n"
                ."1. **Paket OOTD Syar'i Medina:**\n"
                ."   - *Kombinasi:* Gamis Silk Premium (`MDN-GMS-001`) + Hijab Pashmina Plisket (`MDN-HJB-001`)\n"
                ."   - *Penawaran:* Diskon potongan Rp 25.000 atau bundling Kombo Hemat di Shopee.\n"
                ."   - *Tujuan:* Menaikkan nilai keranjang belanja rata-rata pembeli.\n\n"
                ."2. **Paket Hadiah / Hampers Ibadah:**\n"
                ."   - *Kombinasi:* Kemeja Koko Kurta (`MDN-KOKO-001`) + Sarung Tenun Jacquard (`MDN-SRG-001`)\n"
                ."   - *Penawaran:* Hadiah tas pouch gratis untuk pembelian berpasangan.\n\n"
                .'*Tips:* Anda dapat mengaktifkan fitur ini melalui menu **Shopee Seller Centre > Promosi Saya > Kombo Hemat**.';
        }

        if (str_contains($msgLower, 'broadcast') || str_contains($msgLower, 'copy') || str_contains($msgLower, 'pesan')) {
            return "📢 **Draft Pesan Broadcast Chat Shopee:**\n\n"
                ."*Subjek:* Promo Spesial Khusus Pelanggan Setia Medina Warehouse! ✨\n\n"
                ."*Halo Kak!* Terima kasih sudah menjadi bagian dari keluarga Medina. Ada kabar gembira khusus hari ini! 🎉\n\n"
                ."Dapatkan **Voucher Diskon Tambahan 10%** tanpa minimum belanja untuk koleksi Gamis Silk & Hijab Premium kami. Gunakan kode voucher: **MEDINASPECIAL** saat checkout.\n\n"
                ."Stok sangat terbatas lho Kak, jangan sampai kehabisan warna favoritmu ya! 🛍️✨\n"
                .'*Klik link produk sekarang sebelum promo berakhir!*';
        }

        return "Halo! Saya adalah **Medina AI Seller Advisor**. Saya dapat membantu menganalisis data penjualan toko, memberikan ide promosi Shopee, saran bundling produk, serta peringatan stok yang perlu di-restock.\n\n"
            .'📌 *Tips:* Untuk mendapatkan jawaban percakapan generatif lengkap dengan router LLM eksternal, Anda dapat mengisi `AI_API_KEY` pada file `.env`. Saat ini saya beroperasi menggunakan mesin analitik berbasis data internal toko Anda.';
    }

    /**
     * Parse JSON from AI response, tolerating markdown wrappers.
     */
    protected function parseJsonPayload(string $content): ?array
    {
        $clean = trim($content);

        if (str_starts_with($clean, '```json')) {
            $clean = substr($clean, 7);
        } elseif (str_starts_with($clean, '```')) {
            $clean = substr($clean, 3);
        }

        if (str_ends_with($clean, '```')) {
            $clean = substr($clean, 0, -3);
        }

        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }
}
