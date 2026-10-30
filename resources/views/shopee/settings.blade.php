@extends('layouts.adminlte')

@section('title', 'Pengaturan Koneksi OpenShopee API')
@section('page_title', 'Koneksi OpenShopee API & Real-Time Sync')

@section('content')

<div class="row">
    <!-- Left Column: OpenShopee API Bridge Credentials -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-network-wired text-primary mr-1"></i> Konfigurasi Server OpenShopee API (Terpisah)
                </h6>
                @if($setting->hasApiBridge())
                    <span class="badge badge-info px-2 py-1">Bridge Dikonfigurasi</span>
                @else
                    <span class="badge badge-secondary px-2 py-1">Belum Dikonfigurasi</span>
                @endif
            </div>
            <div class="card-body">
                <div class="alert alert-light border text-sm mb-3">
                    <i class="fas fa-info-circle text-primary mr-1"></i>
                    Aplikasi ini berjalan murni sebagai <strong>Frontend Gudang</strong>. Seluruh kredensial rahasia Shopee (Partner ID, Partner Key, OAuth Token) dikelola secara terpusat di <strong>Dashboard AdminLTE OpenShopee API</strong> (<code>/www/wwwroot/OpenShopee_Api</code>).
                </div>

                <form action="{{ route('shopee.settings.update') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label class="font-weight-bold">URL Server OpenShopee API <span class="text-danger">*</span></label>
                        <input type="url" name="api_url" class="form-control font-monospace" value="{{ old('api_url', $setting->getApiUrl()) }}" placeholder="" required>
                        <small class="text-muted">Alamat URL utama tempat aplikasi backend <code>OpenShopee_API</code> dijalankan (tanpa akhiran <code>/api/v1</code>).</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Client API Key (<code>X-API-Key</code>) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="api_key" id="openShopeeApiKeyInput" class="form-control font-monospace" value="{{ old('api_key', $setting->getApiKey()) }}" placeholder="" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="const el = document.getElementById('openShopeeApiKeyInput'); el.type = el.type === 'password' ? 'text' : 'password';">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted d-block">Buat Client API Key melalui menu <strong>Client API Keys &amp; Push</strong> pada Dashboard OpenShopee API, lalu tempel di sini.</small>
                        <small class="text-info d-block mt-1"><i class="fas fa-sync-alt mr-1"></i>Mengganti <strong>API Key</strong>, <strong>URL Server API</strong>, atau <strong>Mode Lingkungan</strong> akan otomatis membersihkan data lokal lama dan me-refresh seluruh katalog &amp; pesanan mengikuti data API yang aktif.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Nama Tampilan Toko</label>
                            <input type="text" name="shop_name" class="form-control" value="{{ old('shop_name', $setting->shop_name) }}" placeholder="">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Mode Lingkungan</label>
                            <select name="environment" class="form-control">
                                <option value="sandbox" {{ $setting->environment === 'sandbox' ? 'selected' : '' }}>Sandbox (Development / Uji Coba)</option>
                                <option value="production" {{ $setting->environment === 'production' ? 'selected' : '' }}>Production (Toko Live Shopee)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="autoSyncStockSwitch" name="auto_sync_stock" value="1" {{ $setting->auto_sync_stock ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold text-dark" for="autoSyncStockSwitch">
                                Sinkronkan Perubahan Stok Gudang ke OpenShopee API Secara Real-Time
                            </label>
                        </div>
                        <small class="text-muted">Setiap barang masuk, barang keluar, atau opname stok di gudang akan langsung dikirim ke OpenShopee API untuk mengupdate stok live Shopee.</small>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex flex-wrap align-items-center">
                        <button type="submit" class="btn btn-primary font-weight-bold px-4 mr-2 mb-2">
                            <i class="fas fa-save mr-1"></i> Simpan &amp; Hubungkan API
                        </button>
                    </div>
                </form>

                @if($setting->hasApiBridge())
                    <form action="{{ route('shopee.settings.test') }}" method="POST" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-sm font-weight-bold">
                            <i class="fas fa-plug mr-1"></i> Test Koneksi &amp; Tarik Delta Real-Time Sekarang
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Live Gateway Status & Real-Time Push Webhook -->
    <div class="col-lg-5">
        <!-- Status & Remote OAuth Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-satellite-dish text-success mr-1"></i> Status Server API &amp; Toko Shopee
                </h6>
                @if(!empty($apiDashboardUrl))
                    <a href="{{ $apiDashboardUrl }}" target="_blank" class="btn btn-xs btn-outline-primary font-weight-bold">
                        <i class="fas fa-external-link-alt mr-1"></i> Dashboard API
                    </a>
                @endif
            </div>
            <div class="card-body">
                @php
                    $apiReachable = !empty($connectionCheck['success']);
                    $counts = $connectionCheck['counts'] ?? [];
                @endphp

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom text-sm">
                        <span class="text-muted">Koneksi Server OpenShopee API:</span>
                        @if($apiReachable)
                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Online &amp; Terautentikasi</span>
                        @elseif($setting->hasApiBridge())
                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Tidak Terjangkau</span>
                        @else
                            <span class="badge badge-secondary px-2 py-1">Belum Diatur</span>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom text-sm">
                        <span class="text-muted">Status OAuth Toko Shopee:</span>
                        @if($setting->isConnected())
                            <span class="badge badge-{{ $setting->isTokenExpired() ? 'warning' : 'success' }} px-2 py-1">
                                <i class="fas fa-{{ $setting->isTokenExpired() ? 'clock' : 'store' }} mr-1"></i>
                                {{ $setting->isTokenExpired() ? 'Token Kedaluwarsa' : 'Terhubung (Shop #'.($setting->shop_id ?: '-').')' }}
                            </span>
                        @else
                            <span class="badge badge-warning px-2 py-1">Belum Terhubung ke Shopee</span>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom text-sm">
                        <span class="text-muted">Sinkronisasi Terakhir:</span>
                        <strong class="text-dark">
                            {{ $setting->last_synced_at ? $setting->last_synced_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') : 'Belum pernah' }}
                        </strong>
                    </div>

                    @if($apiReachable)
                        <div class="d-flex justify-content-between align-items-center pt-2 text-sm">
                            <span class="text-muted">Buffer di Server API:</span>
                            <span class="font-weight-bold text-dark">
                                {{ $counts['products'] ?? 0 }} SKU &bull; {{ $counts['orders'] ?? 0 }} Order ({{ $counts['ready_to_ship'] ?? 0 }} Siap Kirim)
                            </span>
                        </div>
                    @endif
                </div>

                @if(!empty($connectionCheck['error']) && $setting->hasApiBridge())
                    <div class="alert alert-danger py-2 px-3 text-xs mb-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i> {{ $connectionCheck['error'] }}
                    </div>
                @endif

                @if($setting->isConnected())
                    <div class="d-flex mb-2">
                        <form action="{{ route('shopee.settings.refresh') }}" method="POST" class="mr-2 flex-fill">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-dark btn-block font-weight-bold">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh Token via API
                            </button>
                        </form>
                        <form action="{{ route('shopee.settings.disconnect') }}" method="POST" class="flex-fill" onsubmit="return confirm('Putuskan sesi OAuth Shopee pada server OpenShopee API?')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-block font-weight-bold">
                                <i class="fas fa-unlink mr-1"></i> Putus Koneksi
                            </button>
                        </form>
                    </div>
                @endif

                @if(!empty($authUrl))
                    <a href="{{ $authUrl }}" target="_blank" class="btn btn-shopee btn-block font-weight-bold mb-2">
                        <i class="fas fa-external-link-alt mr-1"></i> Buka Otorisasi Shopee (OAuth)
                    </a>
                @endif

                @if(!empty($apiDashboardUrl))
                    <a href="{{ $apiDashboardUrl }}" target="_blank" class="btn btn-outline-primary btn-sm btn-block font-weight-bold">
                        <i class="fas fa-sliders-h mr-1"></i> Kelola Partner ID &amp; Key di Dashboard OpenShopee API
                    </a>
                @endif
            </div>
        </div>

        <!-- Real-Time Push Bridge Webhook Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-bolt text-danger mr-1"></i> Endpoint Real-Time Push ke Frontend Ini
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted text-sm mb-3">
                    Daftarkan URL di bawah ini pada kolom <strong>Frontend Push Webhook URL</strong> di Dashboard <strong>OpenShopee API</strong> agar setiap pesanan masuk, pembatalan, dan update stok langsung terdorong seketika (<em>real-time push</em>) ke aplikasi gudang ini:
                </p>

                <div class="form-group">
                    <label class="text-xs text-uppercase font-weight-bold text-muted">Frontend Bridge Webhook URL:</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm bg-light font-monospace" value="{{ $bridgeWebhookUrl }}" readonly id="bridgeWebhookUrlInput">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('bridgeWebhookUrlInput').value); alert('URL Real-Time Bridge Webhook berhasil disalin!')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border py-2 mb-0 text-xs">
                    <i class="fas fa-shield-alt text-success mr-1"></i> <strong>Catatan PC Lokal / LAN:</strong> Jika web gudang ini berjalan di jaringan lokal tanpa IP/domain publik, <strong>URL ini tidak perlu diisi</strong> di Dashboard API — sinkronisasi real-time tetap berjalan otomatis via <em>auto delta-polling</em> tiap 15 detik &amp; daemon lokal.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
