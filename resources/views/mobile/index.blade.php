@extends('layouts.adminlte')

@section('title', 'API & Integrasi Mobile App')
@section('page_title', 'API & Integrasi Mobile App (Scanner & Video)')

@section('page_actions')
    <a href="{{ route('mobile.api.postman') }}" class="btn btn-warning btn-sm font-weight-bold shadow-xs">
        <i class="fas fa-file-arrow-down mr-1"></i> Unduh Postman Collection
    </a>
    <a href="{{ route('packing.index') }}" class="btn btn-outline-danger btn-sm font-weight-bold shadow-xs ml-1">
        <i class="fas fa-video mr-1"></i> Buka Stasiun Packing Web
    </a>
@endsection

@push('styles')
<style>
    .endpoint-badge {
        font-family: monospace;
        font-weight: 700;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
        min-width: 60px;
        text-align: center;
    }
    .endpoint-post { background-color: #28a745; color: #fff; }
    .endpoint-get { background-color: #007bff; color: #fff; }
    .code-block {
        background-color: #1e1e2d;
        color: #dcdcdc;
        padding: 12px 14px;
        border-radius: 6px;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 12px;
        overflow-x: auto;
        position: relative;
    }
    .copy-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        font-size: 11px;
        padding: 2px 8px;
    }
    .nav-tabs-custom .nav-link {
        font-weight: 600;
        font-size: 13px;
        color: #495057;
    }
    .nav-tabs-custom .nav-link.active {
        color: #007bff;
        border-bottom: 2px solid #007bff;
    }
    .qr-card-box {
        background: #f8f9fa;
        border: 2px dashed #ced4da;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
    }
</style>
@endpush

@section('content')
<div class="row">
    <!-- SSL Problem Solved Alert Banner -->
    <div class="col-12 mb-3">
        <div class="card bg-gradient-info text-white shadow-sm border-0 mb-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <i class="fas fa-shield-halved fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="font-weight-bold mb-1">
                            <i class="fas fa-check-circle mr-1 text-warning"></i> Solusi Bebas Kendala Izin SSL di Jaringan Lokal (Local Network / LAN)
                        </h6>
                        <p class="mb-0 text-xs" style="line-height: 1.5;">
                            Browser modern (Google Chrome di Android/iOS) mewajibkan sertifikat <strong>HTTPS (SSL)</strong> untuk mengizinkan akses kamera (<code>getUserMedia</code>).
                            Pada IP lokal (seperti <code>192.168.x.x</code>), menerapkan SSL di setiap HP operator gudang sangat rumit. Dengan <strong>Aplikasi Mobile Native (Flutter / React Native / Android)</strong> yang terhubung ke REST API ini, kamera scanner barcode dan perekaman video packing dapat berjalan 100% lancar tanpa hambatan perizinan browser!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Left Column: Server Base URL & QR Code & Token Generator -->
    <div class="col-lg-4 col-md-5">
        <!-- Server Discovery & Base URL -->
        <div class="card card-outline card-primary shadow-sm mb-3">
            <div class="card-header py-2">
                <h3 class="card-title font-weight-bold text-sm">
                    <i class="fas fa-server mr-1 text-primary"></i> Server Base URL Mobile
                </h3>
            </div>
            <div class="card-body p-3">
                <label class="text-xs text-muted mb-1">Primary Base URL (IP Lokal Gudang):</label>
                <div class="input-group mb-2">
                    <input type="text" class="form-control form-control-sm font-weight-bold text-primary" id="baseUrlInput" value="{{ $primaryApiUrl }}" readonly>
                    <div class="input-group-append">
                        <button class="btn btn-sm btn-outline-primary" type="button" onclick="copyToClipboard('#baseUrlInput', 'Base URL berhasil disalin!')" title="Salin URL">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                @if(count($localBaseUrls) > 1)
                    <div class="text-xs text-muted mb-1 font-weight-bold">Pilihan IP Jaringan Lainnya:</div>
                    <ul class="list-unstyled mb-3 text-xs">
                        @foreach($localBaseUrls as $altUrl)
                            <li class="mb-1">
                                <a href="javascript:void(0)" onclick="$('#baseUrlInput').val('{{ $altUrl }}'); refreshQrCode('{{ $altUrl }}');" class="text-secondary">
                                    <i class="fas fa-network-wired mr-1 text-muted"></i> {{ $altUrl }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="qr-card-box my-2">
                    <img id="serverQrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($primaryApiUrl) }}" alt="QR Code Base URL" class="img-fluid rounded mb-2" style="max-width: 140px;">
                    <div class="text-xs font-weight-bold text-dark">Scan di Aplikasi Mobile</div>
                    <div class="text-muted text-xs">Otomatis konfigurasi IP Server tanpa ketik manual.</div>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-xs btn-outline-success font-weight-bold" onclick="testPing()">
                        <i class="fas fa-heartbeat mr-1"></i> Test Ping API
                    </button>
                    <span id="pingStatus" class="badge badge-light border text-xs align-self-center">Belum dicek</span>
                </div>
            </div>
        </div>

        <!-- Token Generator -->
        <div class="card card-outline card-secondary shadow-sm mb-3">
            <div class="card-header py-2">
                <h3 class="card-title font-weight-bold text-sm">
                    <i class="fas fa-key mr-1 text-warning"></i> Kelola Token API Mobile
                </h3>
            </div>
            <div class="card-body p-3">
                <p class="text-xs text-muted mb-2">
                    Aplikasi mobile dapat menggunakan <strong>Login otomatis</strong> via endpoint <code>/auth/login</code> atau menggunakan <strong>Static Bearer Token</strong> di bawah ini:
                </p>
                <div class="form-group mb-2">
                    <label class="text-xs font-weight-semibold">Pilih Operator / Akun:</label>
                    <select class="form-control form-control-sm" id="tokenUserSelect">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $u->username }}) - {{ $u->role?->name ?? 'Staff' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-primary btn-block font-weight-bold" onclick="generateNewToken()">
                    <i class="fas fa-rotate mr-1"></i> Generate / Buat Token Baru
                </button>

                <div id="tokenResultBox" class="mt-3" style="display: none;">
                    <label class="text-xs font-weight-bold text-success mb-1">
                        <i class="fas fa-check-circle mr-1"></i> Token Berhasil Dibuat:
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm font-weight-bold text-dark font-monospace" id="generatedTokenInput" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="copyToClipboard('#generatedTokenInput', 'Token berhasil disalin!')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-danger d-block mt-1 text-xs">Simpan token ini. Token digunakan pada header: <code>Authorization: Bearer &lt;TOKEN&gt;</code></small>
                </div>
            </div>
        </div>

        <!-- Mobile App Quick Specs -->
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 bg-light">
                <h3 class="card-title font-weight-bold text-sm">
                    <i class="fas fa-video mr-1 text-danger"></i> Rekomendasi Format Video
                </h3>
            </div>
            <div class="card-body p-2 text-xs">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Resolusi:</td>
                        <td class="font-weight-bold text-right">720p (1280x720) 30 FPS</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Codec Video:</td>
                        <td class="font-weight-bold text-right">H.264 / AVC (MP4)</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Codec Audio:</td>
                        <td class="font-weight-bold text-right">AAC (128 kbps)</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Maksimal File:</td>
                        <td class="font-weight-bold text-right">100 MB per rekaman</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Server Auto-Fix:</td>
                        <td class="font-weight-bold text-success text-right"><i class="fas fa-check"></i> MP4 Faststart Transcode</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: API Documentation Tabs & Live Tester -->
    <div class="col-lg-8 col-md-7">
        <div class="card card-primary card-outline card-tabs shadow-sm mb-3">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs nav-tabs-custom" id="apiDocTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-scanner-link" data-toggle="pill" href="#tab-scanner" role="tab">
                            <i class="fas fa-barcode mr-1 text-danger"></i> 1. Scanner & Cek Resi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-upload-link" data-toggle="pill" href="#tab-upload" role="tab">
                            <i class="fas fa-cloud-arrow-up mr-1 text-primary"></i> 2. Upload Video
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-auth-link" data-toggle="pill" href="#tab-auth" role="tab">
                            <i class="fas fa-user-lock mr-1 text-warning"></i> 3. Login & Auth
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-history-link" data-toggle="pill" href="#tab-history" role="tab">
                            <i class="fas fa-boxes-packing mr-1 text-success"></i> 4. Riwayat & Stats
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-info" id="tab-tester-link" data-toggle="pill" href="#tab-tester" role="tab">
                            <i class="fas fa-vial mr-1"></i> Live API Tester
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-3">
                <div class="tab-content" id="apiDocTabsContent">
                    
                    <!-- TAB 1: SCANNER & CEK RESI -->
                    <div class="tab-pane fade show active" id="tab-scanner" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <span class="endpoint-badge endpoint-post">POST</span>
                                <strong class="text-sm font-monospace ml-1">/api/v1/scanner/scan</strong>
                            </div>
                            <span class="badge badge-light border text-xs">Publik / Auth Opsional</span>
                        </div>
                        <p class="text-xs text-muted mb-3">
                            Endpoint utama yang dipanggil saat kamera HP membaca barcode resi (SPX, J&T, SiCepat, dll) atau Order SN. Sistem secara cerdas mendeteksi status pesanan Shopee, mengecek apakah pesanan <strong>DIBATALKAN / DIRETUR</strong> (untuk mencegah kerugian kirim barang gratis), dan mengembalikan daftar produk lengkap dengan foto.
                        </p>

                        <div class="font-weight-bold text-xs mb-1">Request Body (JSON):</div>
                        <div class="code-block mb-3">
                            <button class="btn btn-xs btn-outline-light copy-btn" onclick="copyCode(this)">Copy</button>
{
  "query": "SPXID0481928471",          // Wajib: Hasil scan barcode resi atau Order SN
  "packer_name": "Siti Packer"         // Opsional: Nama operator yang sedang packing
}
                        </div>

                        <div class="font-weight-bold text-xs mb-1">Response JSON (Status Pesanan Siap Packing - HTTP 200):</div>
                        <div class="code-block mb-3">
                            <button class="btn btn-xs btn-outline-light copy-btn" onclick="copyCode(this)">Copy</button>
{
  "status": "ready",
  "order_id": 12,
  "order_sn": "261024ABCDEF888",
  "tracking_number": "SPXID0481928471",
  "shipping_carrier": "SPX Express",
  "order_status": "READY_TO_SHIP",
  "buyer_username": "customer_shopee",
  "total_amount": 185000,
  "live_verified": true,
  "already_packed": false,
  "is_uploading": false,
  "message": "Pesanan aktif & valid. Silakan periksa produk dan mulai perekaman video packing.",
  "items": [
    {
      "index": 0,
      "name": "Kemeja Katun Pria Medina Slim Fit",
      "sku": "MDN-KMT-01",
      "model_name": "Navy Blue (Size L)",
      "qty": 1,
      "price": 185000,
      "unit": "Pcs",
      "image_url": "http://192.168.1.50:8000/images/sample.jpg",
      "checked": false
    }
  ]
}
                        </div>

                        <div class="font-weight-bold text-xs mb-1 text-danger">
                            <i class="fas fa-triangle-exclamation mr-1"></i> Response JSON Jika Pesanan Dibatalkan di Shopee:
                        </div>
                        <div class="code-block border border-danger">
                            <button class="btn btn-xs btn-outline-light copy-btn" onclick="copyCode(this)">Copy</button>
{
  "status": "blocked_cancelled",
  "order_sn": "261024ABCDEF888",
  "tracking_number": "SPXID0481928471",
  "order_status": "CANCELLED",
  "message": "🚫 PERINGATAN: Pesanan ini sudah BERSTATUS DIBATALKAN / RETUR (CANCELLED) di Shopee! JANGAN DIPACKING ATAU DISERAHKAN KE KURIR!"
}
                        </div>
                    </div>

                    <!-- TAB 2: UPLOAD VIDEO -->
                    <div class="tab-pane fade" id="tab-upload" role="tabpanel">
                        <!-- Init Upload -->
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="endpoint-badge endpoint-post">POST</span>
                                    <strong class="text-sm font-monospace ml-1">/api/v1/packing/init-upload</strong>
                                </div>
                                <span class="badge badge-secondary text-xs">Langkah 1 (Opsional)</span>
                            </div>
                            <p class="text-xs text-muted mb-2">
                                Daftarkan sesi packing sebelum/saat kamera mulai merekam. Menghindari duplikasi rekaman jika ada operator lain di stasiun berbeda.
                            </p>
                            <div class="code-block">
                                <button class="btn btn-xs btn-outline-light copy-btn" onclick="copyCode(this)">Copy</button>
{
  "order_sn": "261024ABCDEF888",
  "tracking_number": "SPXID0481928471",
  "packer_name": "Siti Packer",
  "duration": 25,
  "items_checked": [0, 1],
  "mode": "packing"                     // atau "return_unboxing" untuk retur
}
                            </div>
                        </div>

                        <!-- Upload Video Multipart -->
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="endpoint-badge endpoint-post">POST</span>
                                    <strong class="text-sm font-monospace ml-1">/api/v1/packing/upload-video</strong>
                                </div>
                                <span class="badge badge-primary text-xs">Langkah 2 (Upload File)</span>
                            </div>
                            <p class="text-xs text-muted mb-2">
                                Unggah file video hasil rekaman kamera HP menggunakan <code>multipart/form-data</code>. Server akan otomatis melakukan validasi, perbaikan header EBML WebM (jika WebM), transcode ke H.264 Faststart MP4, dan mengubah status pesanan ke <code>PROCESSED</code>.
                            </p>

                            <div class="font-weight-bold text-xs mb-1">Form-Data Parameters:</div>
                            <table class="table table-sm table-bordered text-xs mb-3">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Field</th>
                                        <th>Tipe</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>video</code></td>
                                        <td><span class="badge badge-danger">File</span></td>
                                        <td>File rekaman video kamera (MP4 / WebM / MOV / M4V, maks. 100MB).</td>
                                    </tr>
                                    <tr>
                                        <td><code>order_sn</code></td>
                                        <td>String</td>
                                        <td>Nomor Order SN Shopee dari hasil scan.</td>
                                    </tr>
                                    <tr>
                                        <td><code>tracking_number</code></td>
                                        <td>String</td>
                                        <td>Nomor Resi paket (opsional, disarankan).</td>
                                    </tr>
                                    <tr>
                                        <td><code>duration</code></td>
                                        <td>Integer</td>
                                        <td>Durasi video dalam detik (contoh: <code>18</code>).</td>
                                    </tr>
                                    <tr>
                                        <td><code>packer_name</code></td>
                                        <td>String</td>
                                        <td>Nama operator gudang yang merekam (contoh: <code>Siti Packer</code>).</td>
                                    </tr>
                                    <tr>
                                        <td><code>items_checked</code></td>
                                        <td>JSON String</td>
                                        <td>Array index produk yang dicentang, misal <code>[0, 1]</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>mode</code></td>
                                        <td>String</td>
                                        <td><code>packing</code> (default) atau <code>return_unboxing</code>.</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="font-weight-bold text-xs mb-1">Response JSON (Upload Berhasil - HTTP 200):</div>
                            <div class="code-block">
                                <button class="btn btn-xs btn-outline-light copy-btn" onclick="copyCode(this)">Copy</button>
{
  "status": "success",
  "message": "Video packing untuk Resi [SPXID0481928471] berhasil disimpan!",
  "record_id": 45,
  "video_url": "http://192.168.1.50:8000/storage/packing_videos/pack_261024ABCDEF888_20261030_142010.mp4",
  "video_duration": "00:25",
  "file_size_formatted": "4.82 MB",
  "data": {
    "record_id": 45,
    "video_url": "http://192.168.1.50:8000/storage/packing_videos/pack_261024ABCDEF888_20261030_142010.mp4",
    "order_sn": "261024ABCDEF888",
    "tracking_number": "SPXID0481928471",
    "packer_name": "Siti Packer",
    "status": "completed"
  }
}
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: LOGIN & AUTH -->
                    <div class="tab-pane fade" id="tab-auth" role="tabpanel">
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="endpoint-badge endpoint-post">POST</span>
                                    <strong class="text-sm font-monospace ml-1">/api/v1/auth/login</strong>
                                </div>
                            </div>
                            <p class="text-xs text-muted mb-2">
                                Login operator gudang dari aplikasi HP. Mengembalikan Bearer Token untuk otentikasi request berikutnya.
                            </p>
                            <div class="code-block mb-2">
                                <button class="btn btn-xs btn-outline-light copy-btn" onclick="copyCode(this)">Copy</button>
// Request:
{
  "username": "packer",
  "password": "password"
}

// Response:
{
  "status": "success",
  "token": "7a8b9c0d1e2f3g4h5i6j7k8l9m0n1o2p3q4r5s6t7u8v9w0x1y2z3a4b5c6d7e8f",
  "token_type": "Bearer",
  "user": {
    "id": 3,
    "name": "Siti Packer",
    "username": "packer",
    "role": "operator_packing",
    "role_name": "Operator Packing"
  }
}
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="endpoint-badge endpoint-get">GET</span>
                                    <strong class="text-sm font-monospace ml-1">/api/v1/auth/packers</strong>
                                </div>
                            </div>
                            <p class="text-xs text-muted mb-2">
                                Mengambil daftar nama operator gudang yang aktif. Sangat berguna jika aplikasi mobile dioperasikan dalam mode tablet bersama (Shared Device) sehingga operator cukup memilih namanya dari menu dropdown tanpa ketik password berulang kali.
                            </p>
                        </div>
                    </div>

                    <!-- TAB 4: RIWAYAT & STATS -->
                    <div class="tab-pane fade" id="tab-history" role="tabpanel">
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="endpoint-badge endpoint-get">GET</span>
                                    <strong class="text-sm font-monospace ml-1">/api/v1/packing/stats</strong>
                                </div>
                            </div>
                            <p class="text-xs text-muted mb-2">
                                Mengambil statistik harian: jumlah paket yang berhasil dipacking hari ini, jumlah paket dibatalkan yang berhasil dicegah, dan 10 rekaman terakhir.
                            </p>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="endpoint-badge endpoint-get">GET</span>
                                    <strong class="text-sm font-monospace ml-1">/api/v1/packing/history</strong>
                                </div>
                            </div>
                            <p class="text-xs text-muted mb-2">
                                Mengambil riwayat arsip rekaman video secara berhalaman (pagination), mendukung filter <code>status</code>, pencarian <code>search</code> (nomor resi/order SN/nama packer), dan tanggal <code>date</code>.
                            </p>
                        </div>
                    </div>

                    <!-- TAB 5: LIVE API TESTER -->
                    <div class="tab-pane fade" id="tab-tester" role="tabpanel">
                        <div class="alert alert-light border p-2 mb-3 text-xs">
                            <i class="fas fa-info-circle text-primary mr-1"></i>
                            Gunakan form di bawah ini untuk menguji secara langsung endpoint Scanner dan Upload Video ke server backend.
                        </div>

                        <!-- Test Scanner -->
                        <div class="card card-outline card-danger mb-3">
                            <div class="card-header py-1 bg-light">
                                <span class="font-weight-bold text-xs"><i class="fas fa-barcode mr-1 text-danger"></i> Uji Coba Scanner (Scan Resi / Order SN)</span>
                            </div>
                            <div class="card-body p-2">
                                <div class="form-row">
                                    <div class="col-md-7 mb-2">
                                        <input type="text" class="form-control form-control-sm" id="testQueryInput" placeholder="Contoh: SPXID0481928471 atau JP1234567890">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <input type="text" class="form-control form-control-sm" id="testPackerInput" placeholder="Nama Packer" value="{{ auth()->user()->name }}">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-danger btn-block font-weight-bold" onclick="runScannerTest()">
                                            <i class="fas fa-search mr-1"></i> Uji Scan
                                        </button>
                                    </div>
                                </div>
                                <div id="scannerTestResult" style="display: none;" class="mt-2">
                                    <div class="code-block" id="scannerTestResultJson"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Test Ping -->
                        <div class="card card-outline card-secondary mb-0">
                            <div class="card-header py-1 bg-light">
                                <span class="font-weight-bold text-xs"><i class="fas fa-network-wired mr-1 text-info"></i> Uji Coba Ping & Status API</span>
                            </div>
                            <div class="card-body p-2">
                                <button type="button" class="btn btn-xs btn-outline-info font-weight-bold mr-2" onclick="runStatusTest()">
                                    <i class="fas fa-server mr-1"></i> Fetch /api/v1/status
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-success font-weight-bold" onclick="runPackersTest()">
                                    <i class="fas fa-users mr-1"></i> Fetch /api/v1/auth/packers
                                </button>
                                <div id="genericTestResult" style="display: none;" class="mt-2">
                                    <div class="code-block" id="genericTestResultJson"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function copyToClipboard(selector, msg) {
        const val = $(selector).val();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(val).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: msg || 'Tersalin ke clipboard!',
                    showConfirmButton: false,
                    timer: 2000
                });
            });
        } else {
            const input = document.querySelector(selector);
            input.select();
            document.execCommand('copy');
            alert(msg || 'Tersalin ke clipboard!');
        }
    }

    function copyCode(btn) {
        const pre = btn.parentElement;
        const text = pre.innerText.replace('Copy', '').trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                const orig = btn.innerText;
                btn.innerText = 'Copied!';
                setTimeout(() => btn.innerText = orig, 1500);
            });
        }
    }

    function refreshQrCode(url) {
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(url);
        $('#serverQrCode').attr('src', qrUrl);
    }

    function testPing() {
        const baseUrl = $('#baseUrlInput').val().trim();
        $('#pingStatus').html('<i class="fas fa-spinner fa-spin text-primary"></i> Pinging...');
        $.ajax({
            url: `${baseUrl}/ping`,
            method: 'GET',
            timeout: 5000,
            success: function(res) {
                $('#pingStatus').removeClass('badge-danger badge-light').addClass('badge-success').html('<i class="fas fa-check-circle mr-1"></i> Online (OK)');
            },
            error: function(xhr) {
                $('#pingStatus').removeClass('badge-success badge-light').addClass('badge-danger').html('<i class="fas fa-times-circle mr-1"></i> Gagal terhubung');
            }
        });
    }

    function generateNewToken() {
        const userId = $('#tokenUserSelect').val();
        $.ajax({
            url: "{{ route('mobile.api.token') }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                user_id: userId
            },
            success: function(res) {
                if (res.status === 'success') {
                    $('#tokenResultBox').slideDown();
                    $('#generatedTokenInput').val(res.token);
                    Swal.fire({
                        icon: 'success',
                        title: 'Token Dibuat!',
                        text: res.message,
                        timer: 2500,
                        showConfirmButton: false
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan sistem.'
                });
            }
        });
    }

    function runScannerTest() {
        const query = $('#testQueryInput').val().trim();
        const packerName = $('#testPackerInput').val().trim();
        const baseUrl = $('#baseUrlInput').val().trim();

        if (!query) {
            alert('Masukkan nomor resi atau order SN terlebih dahulu!');
            return;
        }

        $('#scannerTestResult').show();
        $('#scannerTestResultJson').html('Mengirim request...');

        $.ajax({
            url: `${baseUrl}/scanner/scan`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                query: query,
                packer_name: packerName
            }),
            success: function(data, status, xhr) {
                $('#scannerTestResultJson').html(JSON.stringify(data, null, 2));
            },
            error: function(xhr) {
                const errData = xhr.responseJSON || { error: xhr.statusText };
                $('#scannerTestResultJson').html(JSON.stringify(errData, null, 2));
            }
        });
    }

    function runStatusTest() {
        const baseUrl = $('#baseUrlInput').val().trim();
        $('#genericTestResult').show();
        $('#genericTestResultJson').html('Mengambil data status...');
        $.ajax({
            url: `${baseUrl}/status`,
            method: 'GET',
            success: function(data) {
                $('#genericTestResultJson').html(JSON.stringify(data, null, 2));
            },
            error: function(xhr) {
                $('#genericTestResultJson').html(JSON.stringify(xhr.responseJSON || { error: xhr.statusText }, null, 2));
            }
        });
    }

    function runPackersTest() {
        const baseUrl = $('#baseUrlInput').val().trim();
        $('#genericTestResult').show();
        $('#genericTestResultJson').html('Mengambil daftar packers...');
        $.ajax({
            url: `${baseUrl}/auth/packers`,
            method: 'GET',
            success: function(data) {
                $('#genericTestResultJson').html(JSON.stringify(data, null, 2));
            },
            error: function(xhr) {
                $('#genericTestResultJson').html(JSON.stringify(xhr.responseJSON || { error: xhr.statusText }, null, 2));
            }
        });
    }
</script>
@endpush

