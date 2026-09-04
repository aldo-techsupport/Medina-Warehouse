@extends('layouts.adminlte')

@section('title', 'Packing Video Station')
@section('page_title', 'Packing Video & Validasi Resi')

@section('page_actions')
    <a href="{{ route('packing.history') }}" class="btn btn-outline-primary btn-sm font-weight-bold">
        <i class="fas fa-video mr-1"></i> Galeri Video Packing
    </a>
@endsection

@push('styles')
<style>
    .video-preview-wrapper {
        position: relative;
        width: 100%;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
        min-height: 260px;
        height: 280px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    @media (min-width: 992px) {
        .video-preview-wrapper {
            height: 330px;
        }
    }
    #reader {
        width: 100% !important;
        height: 100% !important;
        border: none !important;
    }
    #reader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 8px;
    }
    #cameraPreview {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .recording-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(220, 38, 38, 0.95);
        color: white;
        padding: 5px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 12px;
        display: none;
        align-items: center;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.5);
        animation: pulseRecording 1.5s infinite;
        z-index: 10;
    }
    @keyframes pulseRecording {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.75; transform: scale(1.04); }
        100% { opacity: 1; transform: scale(1); }
    }
    .item-check-card {
        border-radius: 8px;
        border: 2px solid #e5e7eb;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .item-check-card.checked {
        border-color: #10b981;
        background-color: #ecfdf5;
    }
    .cancelled-alert-card {
        border: 3px solid #dc2626 !important;
        background: #fef2f2 !important;
        animation: shake 0.5s ease-in-out;
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-8px); }
        40%, 80% { transform: translateX(8px); }
    }
    .item-thumb-img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }
    .modal-item-card {
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        overflow: hidden;
    }
    .modal-item-card img {
        width: 90px;
        height: 90px;
        object-fit: cover;
    }

    /* ========================================================
       📱 MOBILE FULLSCREEN CAMERA STATION STYLING
       ======================================================== */
    @media (max-width: 767.98px) {
        /* Sembunyikan elemen UI luar AdminLTE pada HP */
        .main-header.navbar,
        .content-header,
        .mobile-bottom-nav,
        .desktop-only-packing,
        .main-footer {
            display: none !important;
        }

        html, body {
            overflow: hidden !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #000 !important;
        }

        .content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            background: #000 !important;
            height: 100vh !important;
            height: 100dvh !important;
            overflow: hidden !important;
        }

        .content, .container-fluid {
            padding: 0 !important;
            margin: 0 !important;
            height: 100% !important;
            width: 100% !important;
        }

        .row {
            margin: 0 !important;
        }

        .col-12, .col-lg-5 {
            padding: 0 !important;
            margin: 0 !important;
        }

        .card {
            border-radius: 0 !important;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            margin: 0 !important;
        }

        .card-body {
            padding: 0 !important;
        }

        .desktop-cam-card-header,
        .desktop-cam-card-footer {
            display: none !important;
        }

        /* Kamera preview fixed 100% satu layar penuh HP */
        .video-preview-wrapper {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important;
            min-height: 100vh !important;
            border-radius: 0 !important;
            z-index: 1035 !important;
            background: #000 !important;
        }

        #cameraPreview {
            width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important;
            object-fit: cover !important;
            border-radius: 0 !important;
        }

        .mobile-only-cam-ui {
            display: block !important;
        }

        /* Floating glassmorphism buttons */
        .btn-cam-glass {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            text-decoration: none !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
            transition: all 0.2s ease;
        }

        .btn-cam-glass:active, .btn-cam-glass.active {
            background: rgba(37, 99, 235, 0.85);
            border-color: #60a5fa;
            transform: scale(0.94);
        }

        /* Top Header Floating */
        .mobile-cam-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 30;
            padding: calc(12px + env(safe-area-inset-top, 0px)) 16px 12px 16px;
            background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 75%, rgba(0,0,0,0) 100%);
            pointer-events: auto;
        }

        .mobile-cam-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .mobile-cam-pill.pill-rec {
            background: rgba(220, 38, 38, 0.9);
            border-color: #ef4444;
            box-shadow: 0 0 16px rgba(239, 68, 68, 0.7);
        }

        /* Center Reticle / Viewfinder Targeting Box */
        .mobile-cam-reticle-box {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 250px;
            height: 180px;
            pointer-events: none;
            z-index: 25;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reticle-corner {
            position: absolute;
            width: 26px;
            height: 26px;
            border-color: #10b981;
            border-style: solid;
        }

        .corner-tl { top: 0; left: 0; border-width: 3.5px 0 0 3.5px; border-top-left-radius: 12px; }
        .corner-tr { top: 0; right: 0; border-width: 3.5px 3.5px 0 0; border-top-right-radius: 12px; }
        .corner-bl { bottom: 0; left: 0; border-width: 0 0 3.5px 3.5px; border-bottom-left-radius: 12px; }
        .corner-br { bottom: 0; right: 0; border-width: 0 3.5px 3.5px 0; border-bottom-right-radius: 12px; }



        /* Bottom Controls Floating */
        .mobile-cam-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 30;
            padding: 16px 16px calc(14px + env(safe-area-inset-bottom, 0px)) 16px;
            background: linear-gradient(0deg, rgba(0,0,0,0.88) 0%, rgba(0,0,0,0.4) 65%, rgba(0,0,0,0) 100%);
            pointer-events: auto;
        }

        .mobile-standby-pill {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 25px;
            padding: 9px 18px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
        }

        .mobile-order-sheet {
            background: rgba(15, 23, 42, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1.5px solid rgba(255, 255, 255, 0.22);
            border-radius: 20px;
            padding: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            animation: slideUp 0.25s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(25px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .mobile-record-btn {
            height: 52px;
            font-size: 15px;
            letter-spacing: 0.5px;
            transition: all 0.2s ease;
        }

        .mobile-record-btn:active {
            transform: scale(0.97);
        }
    }

    @media (min-width: 768px) {
        .mobile-only-cam-ui {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')

<!-- Compact Top Bar: Scanner & Live Info -->
<div class="card shadow-sm border-0 mb-3 bg-white desktop-only-packing">
    <div class="card-body p-2 p-md-3">
        <div class="row align-items-center">
            <!-- Scan Input -->
            <div class="col-lg-7 col-12 mb-2 mb-lg-0">
                <form id="scanForm" onsubmit="handleScanSubmit(event)" class="m-0">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-primary text-white font-weight-bold px-3">
                                <i class="fas fa-barcode mr-1"></i> SCAN RESI
                            </span>
                        </div>
                        <input type="text" id="barcodeInput" class="form-control form-control-lg font-weight-bold" placeholder="Scan barcode / QR resi atau ketik No. Resi..." autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary px-3" id="btnToggleKeyboard" onclick="toggleVirtualKeyboard()" title="Buka/Tutup Keyboard Virtual HP">
                                <i class="fas fa-keyboard" id="keyboardIcon"></i>
                            </button>
                            <button type="submit" class="btn btn-primary px-3 font-weight-bold" id="btnCheckResi">
                                <i class="fas fa-search mr-1"></i> Cek
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Staf Name & Quick Counters -->
            <div class="col-lg-5 col-12">
                <div class="d-flex align-items-center justify-content-between justify-content-lg-end">
                    <div class="mr-3 text-center text-sm-left">
                        <span class="badge badge-success px-2 py-1" style="font-size: 12px;">
                            <i class="fas fa-check mr-1"></i> <strong id="todayPackedCounter">{{ $todayPackedCount }}</strong> Di-Pack
                        </span>
                        <span class="badge badge-danger px-2 py-1 ml-1" style="font-size: 12px;">
                            <i class="fas fa-ban mr-1"></i> <strong id="todayBlockedCounter">{{ $todayBlockedCount }}</strong> Dibatalkan
                        </span>
                    </div>

                    <div class="d-flex align-items-center">
                        <input type="text" id="packerNameInput" class="form-control form-control-sm font-weight-bold mr-2" value="Staff Packing 1" style="width: 130px;" title="Nama Staf">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleCameraDevice()" id="switchCamBtn" title="Ganti Kamera Depan/Belakang">
                            <i class="fas fa-camera-rotate mr-1"></i> Ganti Kamera
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Demo Buttons -->
        <div class="d-flex flex-wrap align-items-center mt-2 pt-2 border-top" style="font-size: 12px;">
            <span class="text-muted mr-2 font-weight-bold"><i class="fas fa-magic mr-1 text-primary"></i> Contoh Resi:</span>
            @if($allOrders->isNotEmpty())
                @foreach($allOrders->take(3) as $ord)
                    <button type="button" class="btn btn-xs btn-outline-dark mr-1 mb-1" onclick="quickTestOrder('{{ $ord->tracking_number ?: $ord->order_sn }}')">
                        {{ $ord->tracking_number ?: $ord->order_sn }} ({{ $ord->order_status }})
                    </button>
                @endforeach
            @endif
            <button type="button" class="btn btn-xs btn-outline-danger mb-1" onclick="quickTestCancelOrder()">
                <i class="fas fa-ban mr-1"></i> Resi Cancelled
            </button>
        </div>
    </div>
</div>

<!-- Main Packing Workspace: Camera (Left) & Checklist (Right) -->
<div class="row">
    
    <!-- LEFT: Camera Video Stream, Auto QR Scanner & Record Controls -->
    <div class="col-lg-5 col-12 mb-3">
        <div class="card shadow-sm border-0 mb-0">
            <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center desktop-cam-card-header">
                <span class="font-weight-bold" style="font-size: 13px;">
                    <i class="fas fa-camera mr-1 text-danger"></i> Kamera Auto-Scan & Video Rekam
                </span>
                <div class="d-flex align-items-center">
                    <span class="badge badge-info mr-1" id="cameraModeBadge"><i class="fas fa-qrcode mr-1"></i> Auto-Scan Aktif</span>
                    <span class="badge badge-success" id="cameraStatusBadge"><i class="fas fa-circle mr-1" style="font-size: 7px;"></i> Siap</span>
                </div>
            </div>
            <div class="card-body p-2 bg-black">
                <div class="video-preview-wrapper" id="cameraWrapper">

                    <!-- Direct Video for Recording -->
                    <video id="cameraPreview" autoplay playsinline muted></video>

                    <!-- Recording Badge with Live Timer -->
                    <div class="recording-badge" id="recordingBadge">
                        <i class="fas fa-circle mr-1 text-white" style="font-size: 9px;"></i>
                        <span id="recordingTimerText">REC 00:00</span>
                    </div>

                    <div id="cameraPlaceholder" class="text-center text-white p-3" style="display: none; position: absolute;">
                        <i class="fas fa-video-slash fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-2" style="font-size: 12px;">Kamera belum diizinkan atau tidak aktif.</p>
                        <button class="btn btn-primary btn-xs" onclick="startCamera()">Aktifkan Kamera</button>
                    </div>

                    <!-- 📱 MOBILE FULLSCREEN CAMERA OVERLAYS -->
                    <div class="mobile-cam-header mobile-only-cam-ui">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <a href="{{ route('dashboard') }}" class="btn-cam-glass" title="Kembali ke Dashboard">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            
                            <div class="text-center">
                                <span class="mobile-cam-pill" id="mobileStatusPill">
                                    <i class="fas fa-qrcode text-success mr-1"></i> Auto-Scan Resi
                                </span>
                                <span class="mobile-cam-pill pill-rec" id="mobileStatusPillRec" style="display: none;">
                                    <i class="fas fa-circle text-danger mr-1 animate-pulse"></i> <span id="mobileRecTimerText">REC 00:00</span>
                                </span>
                            </div>

                            <div class="d-flex align-items-center">
                                <button type="button" class="btn-cam-glass mr-2" id="mobileTorchBtn" onclick="toggleTorch()" title="Lampu Flash">
                                    <i class="fas fa-bolt" id="torchIcon"></i>
                                </button>
                                <button type="button" class="btn-cam-glass mr-2" onclick="toggleCameraDevice()" title="Ganti Kamera">
                                    <i class="fas fa-camera-rotate"></i>
                                </button>
                                <button type="button" class="btn-cam-glass" onclick="openManualInputModal()" title="Ketik Resi Manual / Demo">
                                    <i class="fas fa-keyboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Center Viewfinder Reticle -->
                    <div class="mobile-cam-reticle-box mobile-only-cam-ui" id="mobileCamReticle">
                        <div class="reticle-corner corner-tl"></div>
                        <div class="reticle-corner corner-tr"></div>
                        <div class="reticle-corner corner-bl"></div>
                        <div class="reticle-corner corner-br"></div>
                    </div>

                    <!-- Bottom Floating Controls -->
                    <div class="mobile-cam-bottom mobile-only-cam-ui">
                        <!-- Standby Info (Shown when no order active) -->
                        <div id="mobileStandbyInfo">
                            <div class="mobile-standby-pill d-flex align-items-center justify-content-between">
                                <span class="text-white text-xs font-weight-bold">
                                    <i class="fas fa-check-circle text-success mr-1"></i> <span id="mobilePackedCounter">{{ $todayPackedCount }}</span> Di-Pack
                                </span>
                                <span class="text-white-50 text-xs" id="mobilePackerNameBadge">
                                    <i class="fas fa-user mr-1"></i> Staff 1
                                </span>
                                <span class="text-white text-xs font-weight-bold">
                                    <i class="fas fa-ban text-danger mr-1"></i> <span id="mobileBlockedCounter">{{ $todayBlockedCount }}</span> Batal
                                </span>
                            </div>
                            <div class="text-center text-white-50 mt-2" style="font-size: 11px;">
                                <i class="fas fa-circle-notch fa-spin mr-1 text-primary"></i> Kamera siap otomatis mendeteksi resi
                            </div>
                        </div>

                        <!-- Active Order Card (Shown when order scanned & valid) -->
                        <div id="mobileActiveOrderCard" style="display: none;">
                            <div class="mobile-order-sheet">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> Valid</span>
                                        <strong class="text-white ml-1 font-weight-bold" id="mobileOrderCarrier">SPX</strong>
                                        <span class="text-white-50 ml-1 text-xs" id="mobileOrderTracking">SPXID...</span>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-info font-weight-bold" onclick="showProductPhotoModal()">
                                        <i class="fas fa-images mr-1"></i> Foto Barang
                                    </button>
                                </div>

                                <div class="text-white-50 text-xs mb-3 d-flex justify-content-between">
                                    <span id="mobileOrderBuyer">Pembeli: -</span>
                                    <span class="text-warning font-weight-bold" id="mobileOrderItemsCount">0 Barang</span>
                                </div>

                                <!-- Big Shutter Record Button -->
                                <button type="button" class="btn btn-danger btn-block btn-lg font-weight-bold shadow-lg py-3 rounded-pill mobile-record-btn" id="mobileBtnStartRecord" onclick="startPackingRecording()">
                                    <i class="fas fa-circle mr-2"></i> MULAI REKAM PACKING
                                </button>

                                <button type="button" class="btn btn-success btn-block btn-lg font-weight-bold shadow-lg py-3 rounded-pill mobile-record-btn" id="mobileBtnStopRecord" onclick="stopPackingRecording()" style="display: none;">
                                    <i class="fas fa-check-circle mr-2"></i> SELESAI & SIMPAN (<span id="mobileRecBtnTimer">00:00</span>)
                                </button>

                                <div class="text-center mt-2">
                                    <button type="button" class="btn btn-link text-white-50 text-xs p-0" onclick="resetPackingState()">
                                        <i class="fas fa-arrow-rotate-left mr-1"></i> Batal / Scan Paket Lain
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white p-2 desktop-cam-card-footer">
                <div class="row no-gutters">
                    <div class="col-6 pr-1">
                        <button type="button" class="btn btn-danger btn-block font-weight-bold py-2 shadow-sm" id="btnStartRecord" onclick="startPackingRecording()" disabled>
                            <i class="fas fa-circle mr-1"></i> Mulai Rekam
                        </button>
                    </div>
                    <div class="col-6 pl-1">
                        <button type="button" class="btn btn-success btn-block font-weight-bold py-2 shadow-sm" id="btnStopRecord" onclick="stopPackingRecording()" disabled>
                            <i class="fas fa-check mr-1"></i> Selesai & Simpan
                        </button>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted" style="font-size: 11px;">
                        <i class="fas fa-info-circle text-primary mr-1"></i> Arahkan barcode / QR resi kertas ke kamera HP/laptop untuk scan otomatis.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Order Details & Product Checklist -->
    <div class="col-lg-7 col-12 mb-3 desktop-only-packing">
        
        <!-- INITIAL EMPTY STATE -->
        <div id="emptyOrderState" class="card shadow-sm border-0 d-flex align-items-center justify-content-center text-center p-4" style="min-height: 380px;">
            <div class="py-4">
                <div class="rounded-circle bg-light p-3 d-inline-flex align-items-center justify-content-center mb-2" style="width: 70px; height: 70px;">
                    <i class="fas fa-barcode fa-2x text-primary"></i>
                </div>
                <h5 class="font-weight-bold text-dark mb-1">Siap Menerima Scan Resi</h5>
                <p class="text-muted mb-0" style="max-width: 320px; font-size: 13px;">
                    Arahkan barcode/QR resi kertas ke kamera di sebelah kiri atau ketik pada kotak scan di atas.
                </p>
            </div>
        </div>



        <!-- ✅ ACTIVE ORDER PACKING CHECKLIST (Shown when order is Valid) -->
        <div id="activeOrderSection" class="card shadow-sm border-0 mb-0" style="display: none;">
            <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge badge-success px-2 py-1 mr-1"><i class="fas fa-check-circle mr-1"></i> Valid</span>
                    <strong class="text-dark" id="orderCarrierText">SPX Express</strong>
                    <span class="text-muted ml-1" id="orderTrackingText">SPXID123456789</span>
                </div>
                <div>
                    <button type="button" class="btn btn-xs btn-outline-info mr-1" onclick="showProductPhotoModal()">
                        <i class="fas fa-images mr-1"></i> Lihat Foto Barang
                    </button>
                    <span class="badge badge-warning font-weight-bold px-2 py-1" id="orderStatusBadge">READY_TO_SHIP</span>
                </div>
            </div>
            
            <div class="card-body p-3">
                <!-- Repack Warning Alert -->
                <div id="repackWarningBanner" class="alert alert-warning py-2 px-3 text-xs mb-3 font-weight-bold" style="display: none;">
                    <i class="fas fa-exclamation-triangle mr-1 text-warning"></i>
                    <strong>Peringatan:</strong> Resi ini sudah pernah dipacking oleh <span id="repackPackerText">-</span> (<span id="repackTimeText">-</span>). Anda sedang melakukan packing ulang.
                </div>

                <!-- Order Details Header -->
                <div class="bg-light p-2 rounded mb-3">
                    <div class="row">
                        <div class="col-sm-6 col-12">
                            <small class="text-muted">Order SN:</small>
                            <span class="font-weight-bold text-dark ml-1" id="activeOrderSnText">-</span>
                        </div>
                        <div class="col-sm-6 col-12">
                            <small class="text-muted">Pembeli:</small>
                            <span class="font-weight-bold text-dark ml-1" id="activeBuyerText">-</span>
                        </div>
                    </div>
                </div>

                <!-- Product Checklist Instruction -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold text-dark m-0" style="font-size: 14px;">
                        <i class="fas fa-clipboard-check text-success mr-1"></i> Daftar Barang yang Wajib Dimasukkan:
                    </h6>
                    <span class="badge badge-primary" id="checklistCounterBadge">0 / 0 Selesai</span>
                </div>

                <!-- Products Checklist Container -->
                <div id="productsChecklistContainer" class="mb-3">
                    <!-- Dynamic Product Cards with Photos will be inserted here -->
                </div>

                <!-- Action instruction -->
                <div class="alert alert-info py-2 text-xs d-flex align-items-center mb-0">
                    <i class="fas fa-info-circle mr-2 fa-lg"></i>
                    <div>
                        Centang barang di atas &rarr; Tekan <strong>"Mulai Rekam"</strong> di kiri &rarr; Masukkan barang & lakban &rarr; Tekan <strong>"Selesai & Simpan"</strong>.
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- 🚫 MODAL POPUP: PERINGATAN PESANAN DIBATALKAN DI SHOPEE -->
<div class="modal fade" id="cancelledOrderModal" tabindex="-1" role="dialog" aria-labelledby="cancelledModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border: 3px solid #dc2626 !important; border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="modal-title font-weight-bold" id="cancelledModalLabel">
                    <i class="fas fa-ban mr-2"></i> PESANAN DIBATALKAN DI SHOPEE!
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="resetPackingState()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-center bg-white">
                <div class="rounded-circle bg-danger text-white p-3 d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 76px; height: 76px; animation: pulseRecording 1.5s infinite;">
                    <i class="fas fa-ban fa-3x"></i>
                </div>
                <h4 class="font-weight-bold text-danger mb-1">JANGAN DIPACKING ATAU DISERAHKAN KE KURIR!</h4>
                <p class="text-muted mb-3" style="font-size: 13px;">Pesanan ini telah dibatalkan oleh pembeli di aplikasi Shopee.</p>

                <div class="bg-light p-3 rounded text-left border mb-3">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block font-weight-semibold">Nomor Resi / AWB:</small>
                            <span class="font-weight-bold text-dark" id="cancelledTrackingText">-</span>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block font-weight-semibold">Shopee Order SN:</small>
                            <span class="font-weight-bold text-dark" id="cancelledOrderSnText">-</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block font-weight-semibold">Status Shopee:</small>
                            <span class="badge badge-danger px-2 py-1 font-weight-bold" id="cancelledStatusBadge">CANCELLED</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block font-weight-semibold">Pembeli:</small>
                            <span class="font-weight-bold text-dark" id="cancelledBuyerText">-</span>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning text-left py-2 mb-0 text-xs">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Kembalikan barang ke rak Gudang Utama untuk mencegah kerugian toko.
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-block font-weight-bold py-2 shadow-sm" data-dismiss="modal" onclick="resetPackingState()">
                    <i class="fas fa-arrow-left mr-1"></i> Mengerti, Scan Paket Berikutnya
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 🖼️ MODAL POPUP: FOTO PRODUK PESANAN SHOPEE (Auto Pops Up upon Valid Scan) -->
<div class="modal fade" id="productPhotoPopupModal" tabindex="-1" role="dialog" aria-labelledby="productPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <div>
                    <h5 class="modal-title font-weight-bold" id="productPhotoModalLabel">
                        <i class="fas fa-box-open mr-2"></i> Foto Barang Pesanan: <span id="modalResiNumber"></span>
                    </h5>
                    <small class="text-white-50" id="modalCarrierAndBuyer"></small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3 bg-light">
                <p class="text-muted font-weight-bold mb-2 text-sm">
                    <i class="fas fa-info-circle text-primary mr-1"></i> Silakan ambil barang fisik sesuai foto, SKU, dan kuantitas berikut:
                </p>
                
                <!-- Dynamic Items Container -->
                <div id="modalProductsListContainer" class="row">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer bg-white d-flex justify-content-between py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    Tutup & Cek di Layar
                </button>
                <button type="button" class="btn btn-danger font-weight-bold shadow-sm px-4" onclick="confirmFromModalAndStartRecord()">
                    <i class="fas fa-circle mr-1"></i> Barang Sesuai, Mulai Rekam Packing!
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ⚠️ MODAL POPUP: RESI SUDAH PERNAH DI-PACKING SEBELUMNYA -->
<div class="modal fade" id="alreadyPackedModal" tabindex="-1" role="dialog" aria-labelledby="alreadyPackedModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border: 3px solid #f59e0b !important; border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-warning text-dark py-3 d-flex justify-content-between align-items-center">
                <h5 class="modal-title font-weight-bold" id="alreadyPackedModalLabel">
                    <i class="fas fa-exclamation-triangle mr-2"></i> RESI SUDAH PERNAH DI-PACK!
                </h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close" onclick="resetPackingState()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-center bg-white">
                <div class="rounded-circle bg-warning text-white p-3 d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 72px; height: 72px;">
                    <i class="fas fa-box-check fa-3x text-dark"></i>
                </div>
                <h5 class="font-weight-bold text-dark mb-1">Paket Ini Sudah Pernah Dikemas!</h5>
                <p class="text-muted mb-3 text-sm">Resi ini tercatat sudah selesai dipacking dan tersimpan di sistem.</p>

                <div class="bg-light p-3 rounded text-left border mb-3">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block font-weight-semibold">Nomor Resi / AWB:</small>
                            <span class="font-weight-bold text-dark" id="alreadyPackedTrackingText">-</span>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block font-weight-semibold">Order SN:</small>
                            <span class="font-weight-bold text-dark" id="alreadyPackedOrderSnText">-</span>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block font-weight-semibold">Waktu Packing:</small>
                            <span class="font-weight-bold text-dark" id="alreadyPackedTimeText">-</span>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted d-block font-weight-semibold">Petugas Packing:</small>
                            <span class="badge badge-info px-2 py-1 font-weight-bold" id="alreadyPackedPackerText">-</span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block font-weight-semibold">Durasi Video:</small>
                            <span class="font-weight-bold text-success" id="alreadyPackedDurationText">-</span>
                        </div>
                    </div>
                </div>

                <!-- 🎬 INLINE VIDEO PLAYER PREVIEW -->
                <div id="alreadyPackedVideoContainer" class="mb-3 text-left" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="font-weight-bold text-dark"><i class="fas fa-play-circle text-danger mr-1"></i> Rekaman Packing Sebelumnya:</small>
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="closeInlineVideo()"><i class="fas fa-times"></i> Tutup</button>
                    </div>
                    <div class="bg-black rounded overflow-hidden shadow-sm" style="max-height: 260px; background: #000;">
                        <video id="alreadyPackedInlineVideo" controls playsinline preload="metadata" style="max-height: 250px; width: 100%; object-fit: contain; background: #000;"></video>
                    </div>
                </div>

                <div class="alert alert-warning text-left py-2 mb-0 text-xs">
                    <i class="fas fa-info-circle mr-1"></i> Anda dapat memutar video sebelumnya untuk memeriksa paket, atau melanjutkan <strong>Packing Ulang</strong> jika kemasan perlu diperbaiki.
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetPackingState()">
                    <i class="fas fa-times mr-1"></i> Batal / Scan Paket Lain
                </button>
                <div class="d-flex">
                    <button type="button" class="btn btn-info btn-sm mr-2 font-weight-bold" id="btnPreviewPackedVideo" onclick="viewExistingPackingVideo()">
                        <i class="fas fa-play mr-1"></i> Putar Video
                    </button>
                    <button type="button" class="btn btn-warning btn-sm font-weight-bold" onclick="proceedToRepack()">
                        <i class="fas fa-redo mr-1"></i> Tetap Packing Ulang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 🎬 VIDEO PREVIEW MODAL -->
<div class="modal fade" id="stationVideoPlayerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content bg-dark text-white border-0 shadow">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title font-weight-bold" id="stationVideoModalTitle">Video Rekaman Packing Sebelumnya</h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="stopStationModalVideo()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0 bg-black text-center">
                <video id="stationModalVideoElement" controls autoplay style="max-height: 440px; width: 100%; object-fit: contain; background: #000;"></video>
            </div>
            <div class="modal-footer border-secondary py-2 d-flex justify-content-between">
                <small class="text-muted" id="stationVideoModalMeta"></small>
                <div>
                    <button type="button" class="btn btn-warning btn-xs font-weight-bold mr-2" onclick="closeVideoAndProceedToRepack()">
                        <i class="fas fa-redo mr-1"></i> Lanjut Packing Ulang
                    </button>
                    <button type="button" class="btn btn-secondary btn-xs" data-dismiss="modal" onclick="stopStationModalVideo()">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 📱 MODAL INPUT MANUAL & PENGATURAN HP -->
<div class="modal fade" id="mobileManualModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title font-weight-bold"><i class="fas fa-keyboard mr-2"></i> Input Resi Manual & Opsi</h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <form onsubmit="handleMobileManualSubmit(event)">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark text-xs mb-1">Nomor Resi / AWB:</label>
                        <div class="input-group">
                            <input type="text" id="mobileManualResiInput" class="form-control font-weight-bold" placeholder="Ketik atau tempel No. Resi...">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary font-weight-bold">
                                    <i class="fas fa-search mr-1"></i> Cek
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="form-group mb-3">
                    <label class="font-weight-bold text-dark text-xs mb-1">Nama Petugas Packing:</label>
                    <input type="text" id="mobilePackerInput" class="form-control form-control-sm font-weight-bold" value="Staff Packing 1" onchange="syncPackerName(this.value)">
                </div>

                <div class="border-top pt-2 mt-3">
                    <label class="text-muted font-weight-bold text-xs mb-1">Contoh Cepat Resi (Demo):</label>
                    <div class="d-flex flex-wrap">
                        @if($allOrders->isNotEmpty())
                            @foreach($allOrders->take(3) as $ord)
                                <button type="button" class="btn btn-xs btn-outline-dark mr-1 mb-1" onclick="quickTestOrderMobile('{{ $ord->tracking_number ?: $ord->order_sn }}')">
                                    {{ $ord->tracking_number ?: $ord->order_sn }}
                                </button>
                            @endforeach
                        @endif
                        <button type="button" class="btn btn-xs btn-outline-danger mb-1" onclick="quickTestCancelOrderMobile()">
                            <i class="fas fa-ban mr-1"></i> Resi Cancelled
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <a href="{{ route('packing.history') }}" class="btn btn-outline-secondary btn-xs font-weight-bold">
                    <i class="fas fa-history mr-1"></i> Galeri Video Packing
                </a>
                <button type="button" class="btn btn-secondary btn-xs" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<!-- Html5-QRCode for 1D Barcode and 2D QR Code Camera Scanning -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    let currentOrderData = null;
    let mediaStream = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let recordStartTime = null;
    let timerInterval = null;
    let recordDuration = 0;
    let currentFacingMode = 'environment'; // 'user' or 'environment'
    let html5QrCode = null;
    let isScanningActive = true;
    let lastScannedCode = null;
    let lastScanTime = 0;
    let currentTrack = null;
    let isTorchOn = false;

    // Audio Synthesizer Beeps
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

    function playBeep(freq = 800, type = 'sine', duration = 0.15) {
        try {
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = type;
            osc.frequency.value = freq;
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + duration);
            osc.stop(audioCtx.currentTime + duration);
        } catch(e) {
            console.warn('Audio feedback failed', e);
        }
    }

    function playSuccessSound() {
        playBeep(880, 'sine', 0.1);
        setTimeout(() => playBeep(1320, 'sine', 0.15), 100);
    }

    function playAlarmSound() {
        playBeep(300, 'sawtooth', 0.25);
        setTimeout(() => playBeep(250, 'sawtooth', 0.25), 150);
        setTimeout(() => playBeep(300, 'sawtooth', 0.3), 300);
    }

    function playWarningSound() {
        playBeep(650, 'sine', 0.15);
        setTimeout(() => playBeep(650, 'sine', 0.2), 180);
    }

    // Camera Init & QR/Barcode Reader Integration
    async function startCamera() {
        try {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
            }
            const constraints = {
                video: {
                    facingMode: currentFacingMode,
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };
            mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            const videoEl = document.getElementById('cameraPreview');
            videoEl.srcObject = mediaStream;
            document.getElementById('cameraPlaceholder').style.display = 'none';
            document.getElementById('cameraStatusBadge').innerHTML = '<i class="fas fa-circle mr-1" style="font-size: 7px;"></i> Siap';
            document.getElementById('cameraStatusBadge').className = 'badge badge-success';

            // Check Torch Capability
            currentTrack = mediaStream.getVideoTracks()[0];
            const capabilities = currentTrack && currentTrack.getCapabilities ? currentTrack.getCapabilities() : {};
            const torchBtn = document.getElementById('mobileTorchBtn');
            if (torchBtn) {
                torchBtn.style.display = capabilities.torch ? 'inline-flex' : 'none';
            }
            isTorchOn = false;
            const torchIcon = document.getElementById('torchIcon');
            if (torchIcon) torchIcon.className = 'fas fa-bolt text-white';
            if (torchBtn) torchBtn.classList.remove('active');

            // Start Barcode / 2D QR code detection loop from video stream
            initCameraBarcodeDetector(videoEl);
        } catch (err) {
            console.warn('Camera access error:', err);
            document.getElementById('cameraPlaceholder').style.display = 'block';
            document.getElementById('cameraStatusBadge').innerHTML = '<i class="fas fa-times mr-1"></i> Kamera Nonaktif';
            document.getElementById('cameraStatusBadge').className = 'badge badge-danger';
        }
    }

    function toggleCameraDevice() {
        isTorchOn = false;
        currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
        startCamera();
    }

    async function toggleTorch() {
        if (!mediaStream) return;
        const tracks = mediaStream.getVideoTracks();
        if (!tracks || tracks.length === 0) return;
        const track = tracks[0];

        try {
            const capabilities = track.getCapabilities ? track.getCapabilities() : {};
            if (!capabilities.torch) {
                Swal.fire({
                    icon: 'info',
                    title: 'Flash Tidak Tersedia',
                    text: 'Kamera yang aktif saat ini tidak mendukung lampu flash/torch.',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }

            isTorchOn = !isTorchOn;
            await track.applyConstraints({
                advanced: [{ torch: isTorchOn }]
            });

            const icon = document.getElementById('torchIcon');
            const btn = document.getElementById('mobileTorchBtn');
            if (isTorchOn) {
                if (icon) icon.className = 'fas fa-bolt text-warning';
                if (btn) btn.classList.add('active');
            } else {
                if (icon) icon.className = 'fas fa-bolt text-white';
                if (btn) btn.classList.remove('active');
            }
        } catch (err) {
            console.warn('Torch error:', err);
        }
    }

    // Direct Real-time Barcode / 2D QR Code Detector using Native BarcodeDetector or Canvas scanning
    async function initCameraBarcodeDetector(videoElement) {
        if ('BarcodeDetector' in window) {
            try {
                const detector = new BarcodeDetector({
                    formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'data_matrix', 'pdf417']
                });

                const scanLoop = async () => {
                    if (isScanningActive && videoElement.readyState === videoElement.HAVE_ENOUGH_DATA) {
                        try {
                            const barcodes = await detector.detect(videoElement);
                            if (barcodes.length > 0) {
                                const detected = barcodes[0].rawValue.trim();
                                onDetectedBarcode(detected);
                            }
                        } catch (e) {}
                    }
                    if (isScanningActive) {
                        requestAnimationFrame(scanLoop);
                    }
                };
                requestAnimationFrame(scanLoop);
            } catch (e) {
                console.log('Native BarcodeDetector not active, fallback to input scanner.');
            }
        }
    }

    const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || ('ontouchstart' in window);
    let isKeyboardEnabled = false;

    function toggleVirtualKeyboard() {
        const input = document.getElementById('barcodeInput');
        const icon = document.getElementById('keyboardIcon');
        const btn = document.getElementById('btnToggleKeyboard');

        isKeyboardEnabled = !isKeyboardEnabled;

        if (isKeyboardEnabled) {
            input.setAttribute('inputmode', 'text');
            btn.className = 'btn btn-warning px-3';
            btn.title = 'Keyboard HP Aktif (Klik untuk sembunyikan)';
            input.focus();
        } else {
            input.setAttribute('inputmode', 'none');
            btn.className = 'btn btn-outline-secondary px-3';
            btn.title = 'Keyboard HP Nonaktif (Klik untuk mengetik)';
            input.blur();
        }
    }

    function onDetectedBarcode(rawCode) {
        const now = Date.now();
        // Prevent duplicate scan within 3 seconds
        if (rawCode === lastScannedCode && (now - lastScanTime) < 3000) {
            return;
        }

        lastScannedCode = rawCode;
        lastScanTime = now;

        const input = document.getElementById('barcodeInput');
        if (input) {
            input.value = rawCode;
            input.blur();
        }
        if (document.activeElement && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }

        checkResiOrder(rawCode);
    }

    // Scan Submission Handler
    async function handleScanSubmit(e) {
        e.preventDefault();
        const input = document.getElementById('barcodeInput');
        const query = input.value.trim();
        if (!query) return;

        input.blur();
        if (document.activeElement && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }

        checkResiOrder(query);
    }

    async function checkResiOrder(query) {
        // Dismiss mobile keyboard if open
        const input = document.getElementById('barcodeInput');
        if (input) {
            input.blur();
        }
        if (document.activeElement && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }
        if (isMobileDevice && isKeyboardEnabled) {
            toggleVirtualKeyboard();
        }

        const btnCheck = document.getElementById('btnCheckResi');
        if (btnCheck) {
            btnCheck.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cek...';
            btnCheck.disabled = true;
        }

        try {
            const response = await fetch("{{ route('packing.check') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    query: query,
                    packer_name: document.getElementById('packerNameInput').value
                })
            });

            const data = await response.json();
            if (btnCheck) {
                btnCheck.innerHTML = '<i class="fas fa-search mr-1"></i> Cek';
                btnCheck.disabled = false;
            }

            if (data.status === 'blocked_cancelled') {
                // 🚫 BLOCKED: ORDER IS CANCELLED IN SHOPEE
                $('#productPhotoPopupModal').modal('hide');
                $('#alreadyPackedModal').modal('hide');
                playAlarmSound();
                showCancelledOrder(data);
            } else if (data.status === 'ready') {
                currentOrderData = data;

                if (data.already_packed) {
                    // ⚠️ WARNING: ALREADY PACKED PREVIOUSLY
                    $('#productPhotoPopupModal').modal('hide');
                    playWarningSound();
                    showAlreadyPackedModal(data);
                } else {
                    // ✅ FRESH PACKING
                    playSuccessSound();
                    showActiveOrder(data);
                    showProductPhotoModal();
                }
            } else {
                $('#productPhotoPopupModal').modal('hide');
                $('#alreadyPackedModal').modal('hide');
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: data.message || 'Pesanan tidak ditemukan.'
                });
            }
        } catch (err) {
            if (btnCheck) {
                btnCheck.innerHTML = '<i class="fas fa-search mr-1"></i> Cek';
                btnCheck.disabled = false;
            }
            $('#productPhotoPopupModal').modal('hide');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat memeriksa data pesanan.'
            });
        }
    }

    function showCancelledOrder(data) {
        $('#productPhotoPopupModal').modal('hide');
        document.getElementById('emptyOrderState').style.display = 'flex';
        document.getElementById('activeOrderSection').style.display = 'none';

        document.getElementById('cancelledTrackingText').innerText = data.tracking_number || '-';
        document.getElementById('cancelledOrderSnText').innerText = data.order_sn || '-';
        document.getElementById('cancelledStatusBadge').innerText = data.order_status || 'CANCELLED';
        document.getElementById('cancelledBuyerText').innerText = data.buyer_username || '-';

        // Update blocked counters
        const blockedEl = document.getElementById('todayBlockedCounter');
        if (blockedEl) {
            const nextBlocked = parseInt(blockedEl.innerText || 0) + 1;
            blockedEl.innerText = nextBlocked;
            const mobileBlockedEl = document.getElementById('mobileBlockedCounter');
            if (mobileBlockedEl) mobileBlockedEl.innerText = nextBlocked;
        }

        // Disable recording
        document.getElementById('btnStartRecord').disabled = true;
        document.getElementById('btnStopRecord').disabled = true;

        // Reset mobile active card
        const mActiveCard = document.getElementById('mobileActiveOrderCard');
        const mStandby = document.getElementById('mobileStandbyInfo');
        const mReticle = document.getElementById('mobileCamReticle');
        if (mActiveCard) mActiveCard.style.display = 'none';
        if (mStandby) mStandby.style.display = 'block';
        if (mReticle) mReticle.style.display = 'flex';

        // Pop up the Cancelled Alert Modal
        $('#cancelledOrderModal').modal('show');
    }

    function showActiveOrder(data) {
        currentOrderData = data;
        $('#cancelledOrderModal').modal('hide');
        document.getElementById('emptyOrderState').style.display = 'none';
        document.getElementById('activeOrderSection').style.display = 'block';

        document.getElementById('orderCarrierText').innerText = data.shipping_carrier || 'Shopee Shipping';
        document.getElementById('orderTrackingText').innerText = data.tracking_number || data.order_sn;
        document.getElementById('orderStatusBadge').innerText = data.order_status + (data.live_verified ? ' • Live Shopee' : '');
        document.getElementById('orderStatusBadge').className = data.live_verified ? 'badge badge-success font-weight-bold px-2 py-1' : 'badge badge-warning font-weight-bold px-2 py-1';
        document.getElementById('activeOrderSnText').innerText = data.order_sn;
        document.getElementById('activeBuyerText').innerText = data.buyer_username;

        // Render Product Checklist Items in Right Column with Photos
        const container = document.getElementById('productsChecklistContainer');
        container.innerHTML = '';

        data.items.forEach((item, idx) => {
            const card = document.createElement('div');
            card.className = 'item-check-card p-2 p-md-3 mb-2 bg-white shadow-sm d-flex align-items-center justify-content-between';
            card.id = `itemCard_${idx}`;
            card.onclick = () => toggleCheckItem(idx);

            card.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="mr-2">
                        <input type="checkbox" id="checkItem_${idx}" class="form-check-input ml-0" style="width: 18px; height: 18px; cursor: pointer;" onchange="event.stopPropagation(); toggleCheckItem(${idx});">
                    </div>
                    <img src="${item.image_url}" alt="${item.name}" class="item-thumb-img mr-3 ml-4">
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0" style="font-size: 14px;">${item.name}</h6>
                        <div class="text-muted mt-1" style="font-size: 12px;">
                            <span class="badge badge-light border mr-1">SKU: ${item.sku}</span>
                            ${item.model_name ? `<span class="badge badge-info mr-1">Variasi: ${item.model_name}</span>` : ''}
                            <span class="font-weight-bold text-primary" style="font-size: 13px;">Jumlah: ${item.qty} ${item.unit}</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="badge badge-pill badge-secondary" id="badgeItem_${idx}" style="font-size: 11px;">Belum Dicek</span>
                </div>
            `;
            container.appendChild(card);
        });

        updateChecklistCounter();
        document.getElementById('btnStartRecord').disabled = false;
        const bInput = document.getElementById('barcodeInput');
        if (bInput) bInput.value = '';

        // 📱 UPDATE MOBILE FULLSCREEN OVERLAY
        const mCarrier = document.getElementById('mobileOrderCarrier');
        const mTracking = document.getElementById('mobileOrderTracking');
        const mBuyer = document.getElementById('mobileOrderBuyer');
        const mItems = document.getElementById('mobileOrderItemsCount');
        const mActiveCard = document.getElementById('mobileActiveOrderCard');
        const mStandby = document.getElementById('mobileStandbyInfo');
        const mReticle = document.getElementById('mobileCamReticle');

        if (mCarrier) mCarrier.innerText = data.shipping_carrier || 'Shopee';
        if (mTracking) mTracking.innerText = data.tracking_number || data.order_sn;
        if (mBuyer) mBuyer.innerText = `Pembeli: ${data.buyer_username || '-'}`;
        if (mItems) mItems.innerText = `${data.items ? data.items.length : 0} Barang`;
        if (mActiveCard) mActiveCard.style.display = 'block';
        if (mStandby) mStandby.style.display = 'none';
        if (mReticle) mReticle.style.display = 'none';

        const mBtnStart = document.getElementById('mobileBtnStartRecord');
        const mBtnStop = document.getElementById('mobileBtnStopRecord');
        if (mBtnStart) { mBtnStart.style.display = 'block'; mBtnStart.disabled = false; }
        if (mBtnStop) { mBtnStop.style.display = 'none'; }
    }

    // 🖼️ POPUP MODAL FOTO BARANG
    function showProductPhotoModal() {
        if (!currentOrderData) return;

        document.getElementById('modalResiNumber').innerText = currentOrderData.tracking_number || currentOrderData.order_sn;
        document.getElementById('modalCarrierAndBuyer').innerText = `${currentOrderData.shipping_carrier || 'Shopee'} | Pembeli: ${currentOrderData.buyer_username} (${currentOrderData.order_sn})`;

        const container = document.getElementById('modalProductsListContainer');
        container.innerHTML = '';

        currentOrderData.items.forEach((item, idx) => {
            const col = document.createElement('div');
            col.className = 'col-12 col-md-6 mb-3';
            col.innerHTML = `
                <div class="modal-item-card p-2 d-flex align-items-center bg-white shadow-sm h-100">
                    <img src="${item.image_url}" alt="${item.name}" class="rounded mr-3 border">
                    <div style="flex: 1;">
                        <h6 class="font-weight-bold text-dark mb-1" style="font-size: 14px;">${item.name}</h6>
                        <div class="mb-2">
                            <span class="badge badge-light border">SKU: ${item.sku}</span>
                            ${item.model_name ? `<span class="badge badge-info">${item.model_name}</span>` : ''}
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 13px;">
                                WAJIB DI-PACK: ${item.qty} ${item.unit}
                            </span>
                            <small class="text-muted">Stok: ${item.current_stock}</small>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(col);
        });

        $('#productPhotoPopupModal').modal('show');
    }

    function confirmFromModalAndStartRecord() {
        $('#productPhotoPopupModal').modal('hide');
        // Check all items
        if (currentOrderData) {
            currentOrderData.items.forEach((item, idx) => {
                item.checked = true;
                const checkbox = document.getElementById(`checkItem_${idx}`);
                const card = document.getElementById(`itemCard_${idx}`);
                const badge = document.getElementById(`badgeItem_${idx}`);
                if (checkbox) checkbox.checked = true;
                if (card) card.classList.add('checked');
                if (badge) {
                    badge.className = 'badge badge-pill badge-success';
                    badge.innerText = '✓ Selesai Dicek';
                }
            });
            updateChecklistCounter();
        }

        // Auto start recording
        startPackingRecording();
    }

    function toggleCheckItem(idx) {
        if (!currentOrderData || !currentOrderData.items[idx]) return;
        const item = currentOrderData.items[idx];
        item.checked = !item.checked;

        const checkbox = document.getElementById(`checkItem_${idx}`);
        const card = document.getElementById(`itemCard_${idx}`);
        const badge = document.getElementById(`badgeItem_${idx}`);

        checkbox.checked = item.checked;
        if (item.checked) {
            card.classList.add('checked');
            badge.className = 'badge badge-pill badge-success';
            badge.innerText = '✓ Selesai Dicek';
            playBeep(950, 'sine', 0.08);
        } else {
            card.classList.remove('checked');
            badge.className = 'badge badge-pill badge-secondary';
            badge.innerText = 'Belum Dicek';
        }

        updateChecklistCounter();
    }

    function updateChecklistCounter() {
        if (!currentOrderData) return;
        const total = currentOrderData.items.length;
        const checked = currentOrderData.items.filter(i => i.checked).length;

        document.getElementById('checklistCounterBadge').innerText = `${checked} / ${total} Selesai`;
        if (checked === total && total > 0) {
            document.getElementById('checklistCounterBadge').className = 'badge badge-success';
        } else {
            document.getElementById('checklistCounterBadge').className = 'badge badge-primary';
        }
    }

    function resetPackingState() {
        currentOrderData = null;
        $('#cancelledOrderModal').modal('hide');
        $('#alreadyPackedModal').modal('hide');
        $('#productPhotoPopupModal').modal('hide');
        $('#stationVideoPlayerModal').modal('hide');
        stopStationModalVideo();
        closeInlineVideo();
        const repackBanner = document.getElementById('repackWarningBanner');
        if (repackBanner) repackBanner.style.display = 'none';
        document.getElementById('emptyOrderState').style.display = 'flex';
        document.getElementById('activeOrderSection').style.display = 'none';
        document.getElementById('btnStartRecord').disabled = true;
        document.getElementById('btnStopRecord').disabled = true;
        const bInput = document.getElementById('barcodeInput');
        if (bInput) bInput.value = '';

        if (!isMobileDevice && bInput) {
            bInput.focus();
        } else if (bInput) {
            bInput.blur();
        }

        isScanningActive = true;
        document.getElementById('cameraModeBadge').innerHTML = '<i class="fas fa-qrcode mr-1"></i> Auto-Scan Aktif';

        // 📱 RESET MOBILE FULLSCREEN OVERLAY
        const mActiveCard = document.getElementById('mobileActiveOrderCard');
        const mStandby = document.getElementById('mobileStandbyInfo');
        const mPillStandby = document.getElementById('mobileStatusPill');
        const mPillRec = document.getElementById('mobileStatusPillRec');
        const mReticle = document.getElementById('mobileCamReticle');
        const mBtnStart = document.getElementById('mobileBtnStartRecord');
        const mBtnStop = document.getElementById('mobileBtnStopRecord');

        if (mActiveCard) mActiveCard.style.display = 'none';
        if (mStandby) mStandby.style.display = 'block';
        if (mPillStandby) mPillStandby.style.display = 'inline-flex';
        if (mPillRec) mPillRec.style.display = 'none';
        if (mReticle) mReticle.style.display = 'flex';
        if (mBtnStart) { mBtnStart.style.display = 'block'; mBtnStart.disabled = false; }
        if (mBtnStop) {
            mBtnStop.style.display = 'none';
            mBtnStop.disabled = false;
            mBtnStop.innerHTML = '<i class="fas fa-check-circle mr-2"></i> SELESAI & SIMPAN (<span id="mobileRecBtnTimer">00:00</span>)';
        }
    }

    function showAlreadyPackedModal(data) {
        closeInlineVideo();
        document.getElementById('alreadyPackedTrackingText').innerText = data.tracking_number || data.order_sn;
        document.getElementById('alreadyPackedOrderSnText').innerText = data.order_sn;
        document.getElementById('alreadyPackedTimeText').innerText = data.existing_packing ? `${data.existing_packing.packed_at} (${data.existing_packing.packed_time_diff})` : '-';
        document.getElementById('alreadyPackedPackerText').innerText = data.existing_packing ? data.existing_packing.packer_name : 'Staff';
        document.getElementById('alreadyPackedDurationText').innerText = data.existing_packing ? `${data.existing_packing.duration}` : '-';

        const btnPlay = document.getElementById('btnPreviewPackedVideo');
        if (data.existing_packing && data.existing_packing.video_url) {
            btnPlay.style.display = 'inline-block';
            btnPlay.innerHTML = '<i class="fas fa-play mr-1"></i> Putar Video';
            btnPlay.className = 'btn btn-info btn-sm mr-2 font-weight-bold';
        } else {
            btnPlay.style.display = 'none';
        }

        $('#alreadyPackedModal').modal('show');
    }

    function viewExistingPackingVideo() {
        if (!currentOrderData || !currentOrderData.existing_packing || !currentOrderData.existing_packing.video_url) {
            Swal.fire('Video Tidak Tersedia', 'File rekaman video sebelumnya tidak ditemukan di server.', 'info');
            return;
        }

        const exp = currentOrderData.existing_packing;
        const container = document.getElementById('alreadyPackedVideoContainer');
        const videoEl = document.getElementById('alreadyPackedInlineVideo');
        const btn = document.getElementById('btnPreviewPackedVideo');

        if (container.style.display === 'none' || !container.style.display) {
            // Show inline video player inside the modal
            videoEl.src = exp.video_url;
            container.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-stop mr-1"></i> Tutup Video';
            btn.className = 'btn btn-secondary btn-sm mr-2 font-weight-bold';

            videoEl.load();
            const playPromise = videoEl.play();
            if (playPromise !== undefined) {
                playPromise.catch(err => {
                    console.log('Autoplay blocked or prevented:', err);
                });
            }
        } else {
            closeInlineVideo();
        }
    }

    function closeInlineVideo() {
        const videoEl = document.getElementById('alreadyPackedInlineVideo');
        if (videoEl) {
            videoEl.pause();
            videoEl.src = '';
        }
        const container = document.getElementById('alreadyPackedVideoContainer');
        if (container) {
            container.style.display = 'none';
        }
        const btn = document.getElementById('btnPreviewPackedVideo');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-play mr-1"></i> Putar Video';
            btn.className = 'btn btn-info btn-sm mr-2 font-weight-bold';
        }
    }

    function stopStationModalVideo() {
        const videoEl = document.getElementById('stationModalVideoElement');
        if (videoEl) {
            videoEl.pause();
            videoEl.src = '';
        }
    }

    function closeVideoAndProceedToRepack() {
        stopStationModalVideo();
        $('#stationVideoPlayerModal').modal('hide');
        proceedToRepack();
    }

    function proceedToRepack() {
        closeInlineVideo();
        stopStationModalVideo();
        $('#alreadyPackedModal').modal('hide');
        if (!currentOrderData) return;

        showActiveOrder(currentOrderData);

        const repackBanner = document.getElementById('repackWarningBanner');
        if (repackBanner && currentOrderData.already_packed && currentOrderData.existing_packing) {
            document.getElementById('repackPackerText').innerText = currentOrderData.existing_packing.packer_name;
            document.getElementById('repackTimeText').innerText = currentOrderData.existing_packing.packed_at;
            repackBanner.style.display = 'block';
        }

        showProductPhotoModal();
    }

    // Quick Test Helper for Demo
    function quickTestOrder(val) {
        const bInput = document.getElementById('barcodeInput');
        if (bInput) bInput.value = val;
        checkResiOrder(val);
    }

    function quickTestCancelOrder() {
        checkResiOrder('CANCEL_DEMO_SPX99182');
    }

    // Video Recording Implementation
    function startPackingRecording() {
        if (!mediaStream) {
            alert('Kamera tidak aktif! Silakan izinkan akses kamera.');
            return;
        }

        // Pause barcode auto-scan during video recording
        isScanningActive = false;
        document.getElementById('cameraModeBadge').innerHTML = '<i class="fas fa-video mr-1"></i> Mode Rekam';

        recordedChunks = [];
        const options = { mimeType: 'video/webm;codecs=vp8,opus' };
        try {
            mediaRecorder = new MediaRecorder(mediaStream, MediaRecorder.isTypeSupported(options.mimeType) ? options : undefined);
        } catch (e) {
            mediaRecorder = new MediaRecorder(mediaStream);
        }

        mediaRecorder.ondataavailable = function(e) {
            if (e.data && e.data.size > 0) {
                recordedChunks.push(e.data);
            }
        };

        mediaRecorder.onstop = function() {
            uploadRecordedVideo();
        };

        mediaRecorder.start(1000);
        recordStartTime = Date.now();
        document.getElementById('recordingBadge').style.display = 'flex';
        document.getElementById('btnStartRecord').disabled = true;
        document.getElementById('btnStopRecord').disabled = false;

        // 📱 MOBILE RECORDING STATE
        const mBtnStart = document.getElementById('mobileBtnStartRecord');
        const mBtnStop = document.getElementById('mobileBtnStopRecord');
        const mPillStandby = document.getElementById('mobileStatusPill');
        const mPillRec = document.getElementById('mobileStatusPillRec');
        const mReticle = document.getElementById('mobileCamReticle');

        if (mBtnStart) mBtnStart.style.display = 'none';
        if (mBtnStop) { mBtnStop.style.display = 'block'; mBtnStop.disabled = false; }
        if (mPillStandby) mPillStandby.style.display = 'none';
        if (mPillRec) mPillRec.style.display = 'inline-flex';
        if (mReticle) mReticle.style.display = 'none';

        timerInterval = setInterval(() => {
            const elapsedSeconds = Math.floor((Date.now() - recordStartTime) / 1000);
            recordDuration = elapsedSeconds;
            const mins = String(Math.floor(elapsedSeconds / 60)).padStart(2, '0');
            const secs = String(elapsedSeconds % 60).padStart(2, '0');
            document.getElementById('recordingTimerText').innerText = `REC ${mins}:${secs}`;

            // Mobile timer display
            const mTimerText = document.getElementById('mobileRecTimerText');
            const mBtnTimerText = document.getElementById('mobileRecBtnTimer');
            if (mTimerText) mTimerText.innerText = `REC ${mins}:${secs}`;
            if (mBtnTimerText) mBtnTimerText.innerText = `${mins}:${secs}`;
        }, 500);

        playBeep(600, 'sine', 0.2);
    }

    function stopPackingRecording() {
        if (!mediaRecorder || mediaRecorder.state === 'inactive') return;

        clearInterval(timerInterval);
        document.getElementById('recordingBadge').style.display = 'none';
        document.getElementById('btnStopRecord').disabled = true;
        document.getElementById('btnStopRecord').innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...';

        const mBtnStop = document.getElementById('mobileBtnStopRecord');
        if (mBtnStop) {
            mBtnStop.disabled = true;
            mBtnStop.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Menyimpan...';
        }

        mediaRecorder.stop();
        playBeep(750, 'sine', 0.2);
    }

    async function uploadRecordedVideo() {
        if (recordedChunks.length === 0 || !currentOrderData) {
            document.getElementById('btnStopRecord').innerHTML = '<i class="fas fa-check mr-1"></i> Selesai & Simpan';
            return;
        }

        const videoBlob = new Blob(recordedChunks, { type: 'video/webm' });
        const formData = new FormData();
        formData.append('video', videoBlob, `pack_${currentOrderData.order_sn}.webm`);
        formData.append('order_sn', currentOrderData.order_sn);
        formData.append('tracking_number', currentOrderData.tracking_number || '');
        formData.append('duration', recordDuration);
        formData.append('packer_name', document.getElementById('packerNameInput').value);
        formData.append('items_checked', JSON.stringify(currentOrderData.items));

        try {
            const response = await fetch("{{ route('packing.upload') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            });

            const result = await response.json();
            document.getElementById('btnStopRecord').innerHTML = '<i class="fas fa-check mr-1"></i> Selesai & Simpan';

            if (result.status === 'success') {
                playSuccessSound();
                Swal.fire({
                    icon: 'success',
                    title: 'Packing Selesai!',
                    html: `Video packing untuk <strong>[${currentOrderData.tracking_number || currentOrderData.order_sn}]</strong> berhasil disimpan.<br><small class="text-muted">Durasi: ${recordDuration} detik</small>`,
                    timer: 3000,
                    showConfirmButton: true
                });

                // Increment counters
                const packedCounter = document.getElementById('todayPackedCounter');
                if (packedCounter) {
                    const nextCount = parseInt(packedCounter.innerText || 0) + 1;
                    packedCounter.innerText = nextCount;
                    const mPacked = document.getElementById('mobilePackedCounter');
                    if (mPacked) mPacked.innerText = nextCount;
                }

                resetPackingState();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menyimpan Video',
                    text: result.message || 'Terjadi kesalahan saat upload.'
                });
            }
        } catch (err) {
            document.getElementById('btnStopRecord').innerHTML = '<i class="fas fa-check mr-1"></i> Selesai & Simpan';
            Swal.fire({
                icon: 'error',
                title: 'Upload Error',
                text: 'Gagal mengunggah video packing ke server.'
            });
        }
    }

    // 📱 MOBILE HELPER FUNCTIONS
    function openManualInputModal() {
        const currentPacker = document.getElementById('packerNameInput').value;
        const mobilePacker = document.getElementById('mobilePackerInput');
        if (mobilePacker) mobilePacker.value = currentPacker;
        $('#mobileManualModal').modal('show');
        setTimeout(() => {
            const inp = document.getElementById('mobileManualResiInput');
            if (inp) inp.focus();
        }, 400);
    }

    function handleMobileManualSubmit(e) {
        e.preventDefault();
        const input = document.getElementById('mobileManualResiInput');
        const val = input.value.trim();
        if (!val) return;
        $('#mobileManualModal').modal('hide');
        input.value = '';
        checkResiOrder(val);
    }

    function quickTestOrderMobile(val) {
        $('#mobileManualModal').modal('hide');
        checkResiOrder(val);
    }

    function quickTestCancelOrderMobile() {
        $('#mobileManualModal').modal('hide');
        quickTestCancelOrder();
    }

    function syncPackerName(val) {
        document.getElementById('packerNameInput').value = val;
        const badge = document.getElementById('mobilePackerNameBadge');
        if (badge) badge.innerHTML = `<i class="fas fa-user mr-1"></i> ${val}`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        startCamera();
        if (isMobileDevice) {
            const bInput = document.getElementById('barcodeInput');
            if (bInput) bInput.setAttribute('inputmode', 'none');
        } else {
            const bInput = document.getElementById('barcodeInput');
            if (bInput) bInput.focus();
        }
    });
</script>
@endpush
