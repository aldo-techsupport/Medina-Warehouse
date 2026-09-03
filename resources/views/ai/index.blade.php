@extends('layouts.adminlte')

@section('title', 'AI Seller & Analisis Penjualan')

@section('page_title')
    <div class="d-flex align-items-center">
        <div class="rounded-circle bg-purple text-white d-flex align-items-center justify-content-center mr-2 shadow-sm" style="width: 38px; height: 38px; font-size: 18px;">
            <i class="fas fa-robot"></i>
        </div>
        <div>
            <span class="d-block font-weight-bold" style="font-size: 1.25rem;">AI Seller & Analisis Penjualan</span>
            <span class="text-muted font-weight-normal" style="font-size: 12px;">Pakar strategi e-commerce, rekomendasi pemasaran, inventaris, & asisten chat interaktif</span>
        </div>
    </div>
@endsection

@section('page_actions')
    <div class="btn-group shadow-xs">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#aiConfigModal">
            <i class="fas fa-sliders-h text-purple mr-1"></i> <span class="d-none d-sm-inline">Status Router AI</span>
        </button>
        <button type="button" id="btnRefreshAnalysis" class="btn btn-sm btn-purple shadow-sm">
            <i class="fas fa-sync-alt mr-1" id="refreshSpinner"></i> <span>Analisis Ulang AI</span>
        </button>
    </div>
@endsection

@push('styles')
<style>
    /* Chat Modern Styling */
    .chat-container {
        display: flex;
        flex-direction: column;
        height: 520px;
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        overflow: hidden;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        gap: 12px;
        scroll-behavior: smooth;
    }
    .chat-bubble {
        max-width: 85%;
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 13.5px;
        line-height: 1.5;
        position: relative;
        word-break: break-word;
    }
    .chat-bubble-user {
        align-self: flex-end;
        background: #1e3a8a;
        color: #ffffff;
        border-bottom-right-radius: 3px;
        box-shadow: 0 2px 6px rgba(30, 58, 138, 0.2);
    }
    .chat-bubble-assistant {
        align-self: flex-start;
        background: #ffffff;
        color: #1f2937;
        border-bottom-left-radius: 3px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }
    .chat-meta {
        font-size: 10px;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .chat-bubble-user .chat-meta {
        color: #cbd5e1;
        justify-content: flex-end;
    }
    .chat-bubble-assistant .chat-meta {
        color: #94a3b8;
        justify-content: flex-start;
    }
    .chat-suggestion-chip {
        display: inline-block;
        font-size: 11.5px;
        font-weight: 500;
        padding: 5px 10px;
        border-radius: 20px;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        transition: all 0.15s ease;
        margin: 2px;
        white-space: nowrap;
    }
    .chat-suggestion-chip:hover {
        background: #8b5cf6;
        color: #ffffff;
        border-color: #8b5cf6;
    }
    .chat-input-wrapper {
        padding: 10px 12px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
    }
    .typing-indicator {
        display: none;
        align-self: flex-start;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 14px;
        font-size: 12px;
        color: #64748b;
    }
    .typing-indicator span {
        display: inline-block;
        width: 6px;
        height: 6px;
        background-color: #8b5cf6;
        border-radius: 50%;
        margin: 0 1px;
        animation: typing 1.4s infinite ease-in-out both;
    }
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes typing {
        0%, 80%, 100% { transform: scale(0); opacity: 0.3; }
        40% { transform: scale(1); opacity: 1; }
    }
    /* Strategy Cards */
    .strategy-card {
        border-left: 4px solid #8b5cf6;
        transition: transform 0.15s ease;
    }
    .strategy-card:hover {
        transform: translateY(-2px);
    }
    .inventory-card {
        border-left: 4px solid #ef4444;
    }
    .checklist-item {
        border-left: 3px solid #10b981;
        background: #f8fafc;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 8px;
    }
</style>
@endpush

@section('content')

    <!-- Status Alert if API Key is not set -->
    @if(!$aiConfig['is_configured'])
        <div class="alert alert-warning alert-dismissible fade show shadow-xs border-0 py-2 mb-3" style="background-color: #fffbeb; border-left: 4px solid #f59e0b !important; color: #92400e;" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fa-lg mr-2 text-warning"></i>
                <div class="flex-grow-1" style="font-size: 13px;">
                    <strong>Mode Analitik Internal Aktif:</strong> Kunci <code>AI_API_KEY</code> belum diisi di file <code>.env</code>. Sistem saat ini beroperasi menggunakan <em>Smart Rule-Based Analytics</em> dengan data toko riil Anda. Masukkan API Key router (OpenRouter/OpenAI/Groq) untuk mengaktifkan AI Generatif penuh.
                </div>
                <button type="button" class="btn btn-xs btn-warning font-weight-bold ml-2 shadow-xs" data-toggle="modal" data-target="#aiConfigModal">
                    Lihat Konfigurasi
                </button>
            </div>
        </div>
    @endif

    <!-- Top Key Metrics Cards -->
    <div class="row">
        <!-- Shopee Revenue -->
        <div class="col-6 col-md-3">
            <div class="small-box">
                <div class="inner">
                    <div class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Omzet Shopee</div>
                    <h3 style="color: var(--shopee-orange);">Rp {{ number_format($metrics['sales']['total_revenue'], 0, ',', '.') }}</h3>
                    <div class="text-muted" style="font-size: 11.5px;">
                        <i class="fas fa-shopping-bag mr-1"></i> {{ $metrics['sales']['total_orders'] }} pesanan tercatat
                    </div>
                </div>
                <div class="small-box-footer text-muted" style="font-size: 11px;">
                    Hari Ini: <strong>Rp {{ number_format($metrics['sales']['today_revenue'], 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>

        <!-- Inventory Valuation -->
        <div class="col-6 col-md-3">
            <div class="small-box">
                <div class="inner">
                    <div class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Nilai Aset Stok</div>
                    <h3 class="text-primary">Rp {{ number_format($metrics['inventory']['total_asset_value'], 0, ',', '.') }}</h3>
                    <div class="text-muted" style="font-size: 11.5px;">
                        <i class="fas fa-boxes mr-1"></i> {{ number_format($metrics['inventory']['total_stock_units']) }} unit ({{ $metrics['inventory']['total_products'] }} SKU)
                    </div>
                </div>
                <div class="small-box-footer text-muted" style="font-size: 11px;">
                    Perputaran Sehat
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-6 col-md-3">
            <div class="small-box">
                <div class="inner">
                    <div class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Stok Kritis (Restock)</div>
                    <h3 class="{{ $metrics['inventory']['low_stock_count'] > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $metrics['inventory']['low_stock_count'] }} <span style="font-size: 13px; font-weight: normal;">SKU</span>
                    </h3>
                    <div class="text-muted" style="font-size: 11.5px;">
                        @if($metrics['inventory']['low_stock_count'] > 0)
                            <i class="fas fa-exclamation-triangle text-danger mr-1"></i> Perlu order ke vendor
                        @else
                            <i class="fas fa-check-circle text-success mr-1"></i> Stok di atas batas aman
                        @endif
                    </div>
                </div>
                <div class="small-box-footer text-muted" style="font-size: 11px;">
                    Safety Stock Threshold
                </div>
            </div>
        </div>

        <!-- AI Engine Status -->
        <div class="col-6 col-md-3">
            <div class="small-box">
                <div class="inner">
                    <div class="text-muted text-uppercase font-weight-bold" style="font-size: 11px;">Asisten Cerdas Toko</div>
                    <h3 class="text-purple text-truncate" style="font-size: 1.15rem;">
                        Asisten Penjualan
                    </h3>
                    <div class="text-muted text-truncate" style="font-size: 11.5px;">
                        @if($aiConfig['is_configured'])
                            <span class="badge badge-success" style="font-size: 10px;"><i class="fas fa-check-circle mr-1"></i> Terhubung Router</span>
                        @else
                            <span class="badge badge-warning text-dark" style="font-size: 10px;"><i class="fas fa-brain mr-1"></i> Local Rule Engine</span>
                        @endif
                    </div>
                </div>
                <div class="small-box-footer text-purple" style="font-size: 11px; cursor: pointer;" data-toggle="modal" data-target="#aiConfigModal">
                    Uji Koneksi <i class="fas fa-arrow-right ml-1"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Analysis on Left, Chat on Right -->
    <div class="row">
        
        <!-- Left Column: Sales Analysis & Strategy -->
        <div class="col-lg-7 col-12 mb-4">

            <!-- Executive Summary Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center bg-white py-2">
                    <div class="font-weight-bold text-dark" style="font-size: 14px;">
                        <i class="fas fa-chart-line text-purple mr-1"></i> Diagnosis Kinerja & Ringkasan Eksekutif
                    </div>
                    <div>
                        <span class="badge badge-light border text-muted" style="font-size: 11px;">
                            <i class="far fa-clock mr-1"></i> {{ $latestAnalysis ? $latestAnalysis->created_at->translatedFormat('d M Y, H:i') : '-' }}
                        </span>
                        <span class="badge badge-purple text-white ml-1" style="font-size: 10px;">
                            Asisten Penjualan Medina
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-dark" style="font-size: 13.5px; line-height: 1.6;">
                        {!! nl2br(e($latestAnalysis->summary ?? 'Belum ada data analisis. Klik tombol Analisis Ulang AI di atas.')) !!}
                    </div>

                    @if(!empty($latestAnalysis->raw_metrics['sales']['top_selling_skus']))
                        <div class="mt-3 pt-2 border-top">
                            <span class="text-muted font-weight-bold mr-2" style="font-size: 11.5px;">PRODUK PALING LARIS:</span>
                            @foreach($latestAnalysis->raw_metrics['sales']['top_selling_skus'] as $sku => $qty)
                                <span class="badge badge-light border text-dark font-weight-bold mr-1" style="font-size: 11px;">
                                    <i class="fas fa-fire text-danger mr-1"></i> {{ $sku }} ({{ $qty }} pcs)
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Marketing & Promo Strategy -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <div class="font-weight-bold text-dark" style="font-size: 14px;">
                        <i class="fas fa-bullhorn text-warning mr-1"></i> Saran Pemasaran & Strategi Promosi Seller
                    </div>
                </div>
                <div class="card-body p-3">
                    @if(!empty($latestAnalysis->marketing_advice))
                        <div class="row">
                            @foreach($latestAnalysis->marketing_advice as $advice)
                                <div class="col-12 mb-2">
                                    <div class="card card-body strategy-card p-3 bg-light border-0 shadow-xs mb-0">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 13.5px;">{{ $advice['title'] ?? 'Strategi Promosi' }}</h6>
                                            <span class="badge badge-purple text-white" style="font-size: 10px;">{{ $advice['badge'] ?? 'Promo Shopee' }}</span>
                                        </div>
                                        <p class="text-muted mb-2" style="font-size: 12.5px; line-height: 1.4;">{{ $advice['description'] ?? '' }}</p>
                                        
                                        @if(!empty($advice['action']))
                                            <div class="d-flex align-items-center text-primary font-weight-bold" style="font-size: 12px;">
                                                <i class="fas fa-arrow-circle-right mr-1"></i> Langkah: {{ $advice['action'] }}
                                            </div>
                                        @endif
                                        @if(!empty($advice['impact']))
                                            <div class="text-success mt-1" style="font-size: 11.5px;">
                                                <i class="fas fa-chart-pie mr-1"></i> <em>Potensi: {{ $advice['impact'] }}</em>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted text-center py-3">Belum ada saran pemasaran terdaftar.</div>
                    @endif
                </div>
            </div>

            <!-- Inventory Restock & Stock Health -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <div class="font-weight-bold text-dark" style="font-size: 14px;">
                        <i class="fas fa-dolly-flatbed text-danger mr-1"></i> Rekomendasi Restock & Pengendalian Persediaan
                    </div>
                </div>
                <div class="card-body p-3">
                    @if(!empty($latestAnalysis->inventory_advice))
                        <div class="row">
                            @foreach($latestAnalysis->inventory_advice as $inv)
                                <div class="col-12 mb-2">
                                    <div class="card card-body inventory-card p-3 bg-light border-0 shadow-xs mb-0">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 13.5px;">{{ $inv['title'] ?? 'Restock Barang' }}</h6>
                                            <span class="badge {{ ($inv['priority'] ?? '') === 'Tinggi' ? 'badge-danger' : 'badge-warning text-dark' }}" style="font-size: 10px;">
                                                Prioritas: {{ $inv['priority'] ?? 'Sedang' }}
                                            </span>
                                        </div>
                                        <p class="text-muted mb-2" style="font-size: 12.5px; line-height: 1.4;">{{ $inv['description'] ?? '' }}</p>
                                        @if(!empty($inv['recommendation']))
                                            <div class="text-danger font-weight-bold" style="font-size: 12px;">
                                                <i class="fas fa-exclamation-circle mr-1"></i> Tindakan: {{ $inv['recommendation'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted text-center py-3">Persediaan dalam batas aman.</div>
                    @endif
                </div>
            </div>

            <!-- Actionable Steps Checklist -->
            @if(!empty($latestAnalysis->actionable_steps))
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-2">
                        <div class="font-weight-bold text-dark" style="font-size: 14px;">
                            <i class="fas fa-tasks text-success mr-1"></i> Checklist Aksi Hari Ini untuk Seller
                        </div>
                    </div>
                    <div class="card-body p-3">
                        @foreach($latestAnalysis->actionable_steps as $step)
                            <div class="checklist-item d-flex align-items-start">
                                <span class="badge badge-success rounded-circle mr-2 mt-1" style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 11px;">
                                    {{ $step['step'] ?? $loop->iteration }}
                                </span>
                                <div class="flex-grow-1">
                                    <div class="font-weight-bold text-dark" style="font-size: 13px;">{{ $step['task'] ?? '' }}</div>
                                    <div class="text-muted" style="font-size: 11px;">
                                        Kategori: <span class="badge badge-light border">{{ $step['category'] ?? 'Umum' }}</span>
                                        @if(!empty($step['target_sku']))
                                            | Target: <span class="font-weight-bold text-primary">{{ $step['target_sku'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        <!-- Right Column: Interactive AI Seller Chat -->
        <div class="col-lg-5 col-12">
            <div class="card shadow-sm" style="position: sticky; top: 70px;">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-purple text-white d-flex align-items-center justify-content-center mr-2" style="width: 28px; height: 28px; font-size: 13px;">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold text-dark" style="font-size: 13.5px;">Chat Asisten Seller AI</div>
                            <div class="text-muted" style="font-size: 10.5px;">Konsultasi strategi, promo, & stok secara interaktif</div>
                        </div>
                    </div>
                    <button type="button" id="btnClearChat" class="btn btn-xs btn-outline-danger shadow-xs" title="Reset Percakapan">
                        <i class="fas fa-trash-alt mr-1"></i> Baru
                    </button>
                </div>

                <!-- Chat Box Content -->
                <div class="chat-container">
                    
                    <!-- Chat Messages Log -->
                    <div class="chat-messages" id="chatMessages">
                        <!-- AI Greeting Welcome Message -->
                        <div class="chat-bubble chat-bubble-assistant">
                            <div class="font-weight-bold text-purple mb-1" style="font-size: 12px;">
                                <i class="fas fa-robot mr-1"></i> Medina AI Advisor
                            </div>
                            <div>
                                Halo <strong>{{ auth()->user()->name }}</strong>! Saya adalah asisten pintar toko Medina Warehouse. Anda bisa menanyakan apa saja mengenai:
                                <ul class="mb-1 pl-3 mt-1" style="font-size: 12.5px;">
                                    <li>Strategi bundling produk terlaris</li>
                                    <li>Saran restock SKU yang menipis</li>
                                    <li>Draft kata-kata promo & broadcast Shopee</li>
                                    <li>Cara menaikkan penjualan dan perputaran stok</li>
                                </ul>
                                Silakan tanyakan di bawah atau klik salah satu rekomendasi pertanyaan cepat!
                            </div>
                            <div class="chat-meta">
                                <span>{{ now()->format('H:i') }}</span>
                            </div>
                        </div>

                        <!-- Render Previous Saved Messages -->
                        @foreach($chatMessages as $msg)
                            @if($msg->role === 'user')
                                <div class="chat-bubble chat-bubble-user">
                                    <div>{!! nl2br(e($msg->content)) !!}</div>
                                    <div class="chat-meta">
                                        <span>{{ $msg->created_at->format('H:i') }}</span>
                                    </div>
                                </div>
                            @elseif($msg->role === 'assistant')
                                <div class="chat-bubble chat-bubble-assistant">
                                    <div class="font-weight-bold text-purple mb-1" style="font-size: 11px;">
                                        <i class="fas fa-robot mr-1"></i> Asisten Penjualan Medina
                                    </div>
                                    <div class="chat-formatted-text">{!! nl2br(e($msg->content)) !!}</div>
                                    <div class="chat-meta">
                                        <span>{{ $msg->created_at->format('H:i') }}</span>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        <!-- Realtime Typing Indicator Bubble -->
                        <div class="typing-indicator" id="typingIndicator">
                            <i class="fas fa-robot text-purple mr-1"></i> AI sedang menyusun strategi...
                            <span></span><span></span><span></span>
                        </div>
                    </div>

                    <!-- Quick Suggestions Prompt Chips -->
                    <div class="px-2 pt-2 pb-1 bg-white border-top overflow-auto text-nowrap" style="scrollbar-width: thin;">
                        <span class="chat-suggestion-chip" data-prompt="Produk mana yang paling mendesak untuk di-restock sekarang dan berapa estimasinya?">
                            🚨 Produk Mendesak Restock?
                        </span>
                        <span class="chat-suggestion-chip" data-prompt="Berikan rekomendasi paket bundling produk terlaris dan slow-moving untuk Shopee.">
                            🎁 Ide Bundling Produk
                        </span>
                        <span class="chat-suggestion-chip" data-prompt="Buatkan draft kata-kata promo broadcast chat Shopee untuk cuci gudang akhir bulan ini.">
                            📢 Draft Broadcast Promo
                        </span>
                        <span class="chat-suggestion-chip" data-prompt="Bagaimana strategi memaksimalkan omzet saat kampanye tanggal kembar di Shopee?">
                            ⚡ Strategi Tanggal Kembar
                        </span>
                    </div>

                    <!-- Input Form -->
                    <div class="chat-input-wrapper">
                        <form id="chatForm" class="d-flex align-items-center">
                            @csrf
                            <input type="hidden" name="session_id" id="chatSessionId" value="{{ $sessionId }}">
                            <div class="input-group">
                                <input type="text" id="chatInput" name="message" class="form-control form-control-sm border-right-0" placeholder="Tanya saran pemasaran atau stok ke AI..." autocomplete="off" required style="border-radius: 20px 0 0 20px; font-size: 13px; height: 38px; padding-left: 14px;">
                                <div class="input-group-append">
                                    <button type="submit" id="btnSendChat" class="btn btn-purple btn-sm px-3" style="border-radius: 0 20px 20px 0; height: 38px;">
                                        <i class="fas fa-paper-plane" id="sendIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- Modal Konfigurasi Router AI -->
    <div class="modal fade" id="aiConfigModal" tabindex="-1" role="dialog" aria-labelledby="aiConfigModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold" id="aiConfigModalLabel" style="font-size: 15px;">
                        <i class="fas fa-sliders-h text-purple mr-2"></i> Konfigurasi Router AI
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <div class="text-muted mb-3">
                        Sistem ini menggunakan router standar <strong>OpenAI-compatible</strong> yang mendukung OpenRouter, OpenCode, OpenAI, Groq, Ollama, dan provider LLM lainnya.
                    </div>

                    <table class="table table-sm table-bordered bg-light mb-3">
                        <tbody>
                            <tr>
                                <th style="width: 35%;">Status Kunci API</th>
                                <td>
                                    @if($aiConfig['is_configured'])
                                        <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> API Key Terkonfigurasi</span>
                                    @else
                                        <span class="badge badge-warning text-dark"><i class="fas fa-exclamation-triangle mr-1"></i> Belum Diisi</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Base URL Endpoint</th>
                                <td><code class="text-purple">{{ $aiConfig['base_url'] }}</code></td>
                            </tr>
                            <tr>
                                <th>Model Default</th>
                                <td><code class="text-dark font-weight-bold">{{ $aiConfig['model'] }}</code></td>
                            </tr>
                            <tr>
                                <th>Timeout Koneksi</th>
                                <td>{{ $aiConfig['timeout'] }} detik</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-2 bg-dark text-white rounded font-monospace mb-3" style="font-size: 11px;">
                        <span class="text-muted"># Pengaturan pada file .env:</span><br>
                        AI_BASE_URL="https://openrouter.ai/api/v1"<br>
                        AI_API_KEY="sk-or-v1-xxxxxxxxxxxx"<br>
                        AI_MODEL="google/gemini-2.5-flash"<br>
                    </div>

                    <div class="text-center pt-2">
                        <button type="button" id="btnTestConnection" class="btn btn-sm btn-outline-primary shadow-xs">
                            <i class="fas fa-network-wired mr-1"></i> Uji Koneksi Router Sekarang
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    const chatMessagesEl = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const btnSendChat = document.getElementById('btnSendChat');
    const sendIcon = document.getElementById('sendIcon');
    const typingIndicator = document.getElementById('typingIndicator');
    const btnRefreshAnalysis = document.getElementById('btnRefreshAnalysis');
    const refreshSpinner = document.getElementById('refreshSpinner');
    const btnClearChat = document.getElementById('btnClearChat');
    const btnTestConnection = document.getElementById('btnTestConnection');

    // Auto-scroll chat to bottom
    function scrollToBottom() {
        chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
    }
    scrollToBottom();

    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Format simple markdown into HTML
    function formatMarkdown(text) {
        let html = escapeHtml(text);
        // Bold: **text**
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic: *text*
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Inline code: `code`
        html = html.replace(/`(.*?)`/g, '<code class="bg-light px-1 rounded text-danger">$1</code>');
        // Convert line breaks
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    // Append a user bubble
    function appendUserMessage(text, timeStr) {
        const time = timeStr || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const bubble = `
            <div class="chat-bubble chat-bubble-user">
                <div>${escapeHtml(text).replace(/\n/g, '<br>')}</div>
                <div class="chat-meta">
                    <span>${time}</span>
                </div>
            </div>
        `;
        // Insert before typing indicator
        typingIndicator.insertAdjacentHTML('beforebegin', bubble);
        scrollToBottom();
    }

    // Append an assistant bubble
    function appendAssistantMessage(text, modelName, timeStr) {
        const time = timeStr || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const model = modelName || 'AI Advisor';
        const formatted = formatMarkdown(text);

        const bubble = `
            <div class="chat-bubble chat-bubble-assistant">
                <div class="font-weight-bold text-purple mb-1" style="font-size: 11px;">
                    <i class="fas fa-robot mr-1"></i> Asisten Penjualan Medina
                </div>
                <div class="chat-formatted-text">${formatted}</div>
                <div class="chat-meta">
                    <span>${time}</span>
                </div>
            </div>
        `;
        typingIndicator.insertAdjacentHTML('beforebegin', bubble);
        scrollToBottom();
    }

    // Submit Chat Message
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = chatInput.value.trim();
        if (!msg) return;

        const sessionId = document.getElementById('chatSessionId').value;

        // Display user message immediately
        appendUserMessage(msg);
        chatInput.value = '';
        chatInput.disabled = true;
        btnSendChat.disabled = true;
        sendIcon.className = 'fas fa-spinner fa-spin';

        // Show typing indicator
        typingIndicator.style.display = 'block';
        scrollToBottom();

        $.ajax({
            url: "{{ route('ai.chat') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                message: msg,
                session_id: sessionId
            },
            dataType: 'json',
            success: function(res) {
                typingIndicator.style.display = 'none';
                if (res.success) {
                    appendAssistantMessage(res.reply, res.model, res.created_at);
                } else {
                    appendAssistantMessage('⚠️ Maaf, terjadi kendala saat menghubungi AI: ' + (res.message || 'Error tidak diketahui'), 'System');
                }
            },
            error: function(xhr) {
                typingIndicator.style.display = 'none';
                const errMsg = xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText;
                appendAssistantMessage('⚠️ Gagal mengirim pesan: ' + errMsg, 'Error Handler');
            },
            complete: function() {
                chatInput.disabled = false;
                btnSendChat.disabled = false;
                sendIcon.className = 'fas fa-paper-plane';
                chatInput.focus();
                scrollToBottom();
            }
        });
    });

    // Quick Prompt Suggestion click handler
    $('.chat-suggestion-chip').on('click', function() {
        const prompt = $(this).data('prompt');
        chatInput.value = prompt;
        chatForm.dispatchEvent(new Event('submit'));
    });

    // Clear Chat History
    btnClearChat.addEventListener('click', function() {
        Swal.fire({
            title: 'Mulai Percakapan Baru?',
            text: 'Riwayat obrolan dengan asisten AI akan dibersihkan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#8b5cf6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Bersihkan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const sessionId = document.getElementById('chatSessionId').value;
                $.ajax({
                    url: "{{ route('ai.chat.clear') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        session_id: sessionId
                    },
                    success: function(res) {
                        if (res.new_session_id) {
                            document.getElementById('chatSessionId').value = res.new_session_id;
                        }
                        // Remove all bubbles except first greeting and typing indicator
                        const bubbles = chatMessagesEl.querySelectorAll('.chat-bubble:not(:first-child)');
                        bubbles.forEach(b => b.remove());
                        scrollToBottom();

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Riwayat chat telah dibersihkan',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    },
                    error: function() {
                        Swal.fire('Error', 'Gagal membersihkan riwayat chat.', 'error');
                    }
                });
            }
        });
    });

    // Refresh Sales Analysis
    btnRefreshAnalysis.addEventListener('click', function() {
        refreshSpinner.classList.add('fa-spin');
        btnRefreshAnalysis.disabled = true;

        Swal.fire({
            title: 'Menganalisis Data Toko...',
            text: 'AI sedang mengevaluasi transaksi penjualan, perputaran stok, dan menyusun strategi promosi.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: "{{ route('ai.analyze') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}"
            },
            dataType: 'json',
            success: function(res) {
                Swal.fire({
                    icon: 'success',
                    title: 'Analisis Selesai!',
                    text: res.message || 'Analisis data penjualan dan strategi pemasaran berhasil diperbarui.',
                    confirmButtonColor: '#8b5cf6'
                }).then(() => {
                    location.reload();
                });
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText;
                Swal.fire('Gagal', 'Terjadi kendala saat menganalisis data: ' + msg, 'error');
            },
            complete: function() {
                refreshSpinner.classList.remove('fa-spin');
                btnRefreshAnalysis.disabled = false;
            }
        });
    });

    // Test Router Connection
    btnTestConnection.addEventListener('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menguji koneksi...');

        $.ajax({
            url: "{{ route('ai.test') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}"
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Koneksi Berhasil!',
                        html: `<div>${escapeHtml(res.message)}</div>
                               <div class="mt-2 text-muted" style="font-size: 12px;">Latensi: <strong>${res.latency_ms} ms</strong> | Model: <strong>${escapeHtml(res.model)}</strong></div>`,
                        confirmButtonColor: '#8b5cf6'
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Koneksi Gagal / Belum Siap',
                        text: res.message,
                        confirmButtonColor: '#8b5cf6'
                    });
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText;
                Swal.fire('Error', 'Gagal melakukan tes koneksi: ' + msg, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-network-wired mr-1"></i> Uji Koneksi Router Sekarang');
            }
        });
    });
});
</script>
@endpush
