@extends('layouts.adminlte')

@section('title', 'Lacak Kurir & Pengiriman')
@section('page_title', 'Lacak Kurir & Pengiriman')

@section('page_actions')
    <div class="d-inline-flex align-items-center">
        <a href="{{ route('courier.index') }}" class="btn btn-outline-secondary btn-sm mr-2">
            <i class="fas fa-sync-alt mr-1"></i> Segarkan
        </a>
        @if($setting->isConnected())
            <form action="{{ route('courier.sync.all') }}" method="POST" class="d-inline" onsubmit="var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<i class=&quot;fas fa-spinner fa-spin mr-1&quot;></i> Memperbarui...';">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm shadow-sm font-weight-bold">
                    <i class="fas fa-rotate mr-1"></i> Sinkronkan Kurir
                </button>
            </form>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .kpi-card {
        border-radius: 10px;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .kpi-card.delayed-alert {
        background: #fff5f5;
        border: 1.5px solid #f87171;
    }
    .kpi-card.delayed-alert .kpi-num {
        color: #dc2626;
    }
    .filter-pill {
        border-radius: 20px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        transition: all 0.2s ease;
        margin-right: 6px;
        margin-bottom: 6px;
        border: 1.5px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
    }
    .filter-pill:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #1e293b;
    }
    .filter-pill.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
    }
    .filter-pill.active .badge-light-custom {
        background: rgba(255,255,255,0.25);
        color: #ffffff;
    }
    .filter-pill.delayed-pill {
        border-color: #fca5a5;
        background: #fff;
        color: #dc2626;
    }
    .filter-pill.delayed-pill:hover {
        background: #fef2f2;
    }
    .filter-pill.delayed-pill.active {
        background: #dc2626;
        border-color: #dc2626;
        color: #ffffff !important;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.35);
    }
    .filter-pill.cancel-pill {
        border-color: #fecdd3;
        background: #fff;
        color: #e11d48;
    }
    .filter-pill.cancel-pill:hover {
        background: #fff1f2;
        border-color: #fda4af;
        color: #be123c;
    }
    .filter-pill.cancel-pill.active {
        background: #e11d48;
        border-color: #e11d48;
        color: #ffffff !important;
        box-shadow: 0 2px 8px rgba(225, 29, 72, 0.35);
    }
    .badge-light-custom {
        background: #e2e8f0;
        color: #334155;
        font-size: 11px;
        padding: 2px 7px;
        border-radius: 12px;
        margin-left: 6px;
    }
    .timeline-wrapper-shopee {
        max-height: 420px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 6px 10px 6px 14px;
    }
    .timeline-item-shopee {
        position: relative;
        padding-left: 22px;
        padding-bottom: 16px;
        border-left: 2px solid #e2e8f0;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .timeline-item-shopee:last-child {
        border-left: 2px solid transparent;
        padding-bottom: 0;
    }
    .timeline-dot {
        position: absolute;
        left: -7px;
        top: 3px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #cbd5e1;
    }
    .timeline-dot.active {
        background: #10b981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
    }
    .timeline-dot.danger {
        background: #ef4444;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
    }
    @media (min-width: 768px) {
        .modal-col-divider {
            border-right: 1px solid #e2e8f0;
        }
    }
    @media (max-width: 767.98px) {
        .modal-col-divider {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        .timeline-wrapper-shopee {
            max-height: 320px;
        }
    }
</style>
@endpush

@section('content')

<!-- SLA Alert Banner if there are delayed packages >= 5 days -->
@if($delayed5DaysCount > 0)
<div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-3" style="border-radius: 10px; background: #fff5f5; border-left: 4px solid #dc2626 !important;">
    <div class="mr-3">
        <i class="fas fa-triangle-exclamation fa-2x text-danger"></i>
    </div>
    <div class="flex-grow-1">
        <h6 class="font-weight-bold text-danger mb-1">
            Peringatan SLA: Terdapat {{ $delayed5DaysCount }} Paket Pengiriman Tertahan (≥ 5 Hari)
        </h6>
        <p class="mb-0 text-sm text-dark">
            Paket belum sampai ke alamat penerima dan telah melampaui batas waktu 5 hari. Silakan periksa rincian resi dan lakukan konfirmasi ke pihak ekspedisi terkait.
        </p>
    </div>
    <div class="ml-3 d-none d-md-block">
        <a href="{{ route('courier.index', ['filter' => 'delayed_5_days']) }}" class="btn btn-danger btn-sm font-weight-bold shadow-sm">
            Lihat Paket Tertahan
        </a>
    </div>
</div>
@endif

<!-- KPI Metrics Cards -->
<div class="row mb-3">
    <!-- Card 1: Total Semua Paket -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
        <div class="card shadow-sm border-0 kpi-card h-100 bg-white">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 48px; height: 48px; background: #eff6ff; color: #2563eb; font-size: 20px;">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
                <div>
                    <span class="text-xs font-weight-bold text-uppercase text-muted">Total Paket</span>
                    <h3 class="font-weight-bold mb-0 text-dark">{{ number_format($totalCount) }}</h3>
                    <small class="text-muted text-xs">Semua paket tercatat</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Paket Tertahan >= 5 Hari -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
        <div class="card shadow-sm border-0 kpi-card h-100 {{ $delayed5DaysCount > 0 ? 'delayed-alert' : 'bg-white' }}">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 48px; height: 48px; background: {{ $delayed5DaysCount > 0 ? '#fecaca' : '#f1f5f9' }}; color: {{ $delayed5DaysCount > 0 ? '#dc2626' : '#64748b' }}; font-size: 20px;">
                    <i class="fas fa-clock-rotate-left"></i>
                </div>
                <div>
                    <span class="text-xs font-weight-bold text-uppercase {{ $delayed5DaysCount > 0 ? 'text-danger' : 'text-muted' }}">
                        Tertahan ≥ 5 Hari
                    </span>
                    <h3 class="font-weight-bold mb-0 kpi-num">{{ number_format($delayed5DaysCount) }}</h3>
                    <small class="{{ $delayed5DaysCount > 0 ? 'text-danger font-weight-bold' : 'text-muted' }} text-xs">
                        {{ $delayed5DaysCount > 0 ? 'Perlu tindak lanjut ekspedisi' : 'Pengiriman dalam batas SLA' }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Dalam Pengiriman -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
        <div class="card shadow-sm border-0 kpi-card h-100 bg-white">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 48px; height: 48px; background: #fffbeb; color: #d97706; font-size: 20px;">
                    <i class="fas fa-truck-fast"></i>
                </div>
                <div>
                    <span class="text-xs font-weight-bold text-uppercase text-muted">Dalam Pengiriman</span>
                    <h3 class="font-weight-bold mb-0 text-dark">{{ number_format($inTransitCount) }}</h3>
                    <small class="text-muted text-xs">Sedang dalam proses antar</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Terkirim -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-12 mb-2">
        <div class="card shadow-sm border-0 kpi-card h-100 bg-white">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 48px; height: 48px; background: #ecfdf5; color: #059669; font-size: 20px;">
                    <i class="fas fa-circle-check"></i>
                </div>
                <div>
                    <span class="text-xs font-weight-bold text-uppercase text-muted">Terkirim</span>
                    <h3 class="font-weight-bold mb-0 text-dark">{{ number_format($deliveredCount) }}</h3>
                    <small class="text-muted text-xs">Paket selesai diterima</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Navigasi Status -->
<div class="mb-3 d-flex align-items-center flex-wrap">
    <a href="{{ route('courier.index', array_merge(request()->except('filter', 'page'), ['filter' => 'all'])) }}" 
       class="filter-pill {{ $filter === 'all' ? 'active' : '' }}">
        Semua Paket 
        <span class="badge-light-custom">{{ $totalCount }}</span>
    </a>

    <a href="{{ route('courier.index', array_merge(request()->except('filter', 'page'), ['filter' => 'delayed_5_days'])) }}" 
       class="filter-pill delayed-pill {{ $filter === 'delayed_5_days' ? 'active' : '' }}">
        <i class="fas fa-triangle-exclamation mr-1"></i> Tertahan ≥ 5 Hari 
        <span class="badge badge-danger ml-1 font-weight-bold">{{ $delayed5DaysCount }}</span>
    </a>

    <a href="{{ route('courier.index', array_merge(request()->except('filter', 'page'), ['filter' => 'in_transit'])) }}" 
       class="filter-pill {{ $filter === 'in_transit' ? 'active' : '' }}">
        Dalam Pengiriman 
        <span class="badge-light-custom">{{ $inTransitCount }}</span>
    </a>

    <a href="{{ route('courier.index', array_merge(request()->except('filter', 'page'), ['filter' => 'ready_to_ship'])) }}" 
       class="filter-pill {{ $filter === 'ready_to_ship' ? 'active' : '' }}">
        Perlu Dikirim 
        @if(isset($readyToShipCount) && $readyToShipCount > 0)
            <span class="badge-light-custom">{{ $readyToShipCount }}</span>
        @endif
    </a>

    <a href="{{ route('courier.index', array_merge(request()->except('filter', 'page'), ['filter' => 'delivered'])) }}" 
       class="filter-pill {{ $filter === 'delivered' ? 'active' : '' }}">
        Terkirim 
        <span class="badge-light-custom">{{ $deliveredCount }}</span>
    </a>

    <a href="{{ route('courier.index', array_merge(request()->except('filter', 'page'), ['filter' => 'failed'])) }}" 
       class="filter-pill {{ $filter === 'failed' ? 'active' : '' }}">
        Kendala Antar
        @if(isset($failedCount) && $failedCount > 0)
            <span class="badge-light-custom">{{ $failedCount }}</span>
        @endif
    </a>

    <a href="{{ route('courier.index', array_merge(request()->except('filter', 'page'), ['filter' => 'cancelled'])) }}" 
       class="filter-pill cancel-pill {{ $filter === 'cancelled' ? 'active' : '' }}">
        <i class="fas fa-ban mr-1"></i> Dibatalkan
        <span class="badge-light-custom">{{ $cancelledCount }}</span>
    </a>
</div>

<!-- Form Pencarian & Filter Ekspedisi -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-3">
        <form action="{{ route('courier.index') }}" method="GET">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <div class="row align-items-center">
                <!-- Kolom Pencarian -->
                <div class="col-12 col-md-5 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" 
                               placeholder="Cari No. Resi, Order SN, atau Pembeli..." 
                               value="{{ request('search') }}">
                    </div>
                </div>

                <!-- Pilihan Ekspedisi -->
                <div class="col-6 col-md-3 mb-2 mb-md-0">
                    <select name="carrier" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Ekspedisi --</option>
                        @foreach($carriersList as $c)
                            @if(is_string($c) && trim($c) !== '')
                                @php $cName = trim($c); @endphp
                                <option value="{{ $cName }}" {{ request('carrier') === $cName ? 'selected' : '' }}>{{ $cName }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <!-- Filter Hari Perjalanan -->
                <div class="col-6 col-md-2 mb-2 mb-md-0">
                    <select name="min_days" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Durasi Kirim --</option>
                        <option value="3" {{ request('min_days') == '3' ? 'selected' : '' }}>≥ 3 Hari</option>
                        <option value="5" {{ request('min_days') == '5' ? 'selected' : '' }}>≥ 5 Hari</option>
                        <option value="7" {{ request('min_days') == '7' ? 'selected' : '' }}>≥ 7 Hari</option>
                    </select>
                </div>

                <!-- Tombol -->
                <div class="col-12 col-md-2 d-flex">
                    <button type="submit" class="btn btn-sm btn-primary btn-block mr-1">
                        Cari
                    </button>
                    @if(request()->hasAny(['search', 'carrier', 'min_days']) || $filter !== 'all')
                        <a href="{{ route('courier.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                            <i class="fas fa-undo"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar Paket -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold text-dark mb-0">
            Daftar Paket Pengiriman 
            <span class="badge badge-light ml-2 font-weight-normal border text-muted">
                {{ $packages->total() }} Data
            </span>
        </h6>
        @if($filter === 'delayed_5_days')
            <span class="badge badge-danger p-2 font-weight-bold">
                Filter: Paket Tertahan (≥ 5 Hari)
            </span>
        @elseif($filter === 'cancelled')
            <span class="badge badge-danger p-2 font-weight-bold">
                Filter: Paket Dibatalkan / Retur
            </span>
        @endif
    </div>

    <div class="card-body p-0 table-responsive">
        @if($packages->isEmpty())
            <div class="text-center py-5 text-muted">
                <div class="mb-3">
                    <i class="fas fa-box-open fa-3x text-secondary"></i>
                </div>
                <h6 class="font-weight-bold text-dark">Tidak Ada Data Paket</h6>
                <p class="text-sm mb-3">
                    @if($filter === 'delayed_5_days')
                        Saat ini tidak ada paket yang tertahan ≥ 5 hari.
                    @elseif($filter === 'cancelled')
                        Saat ini tidak ada paket yang dibatalkan atau retur.
                    @else
                        Tidak ada data paket yang sesuai dengan kriteria pencarian.
                    @endif
                </p>
                @if(request()->hasAny(['search', 'carrier', 'min_days']) || $filter !== 'all')
                    <a href="{{ route('courier.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-undo mr-1"></i> Reset Filter
                    </a>
                @endif
            </div>
        @else
            <table class="table table-hover table-striped mb-0 text-sm align-middle">
                <thead class="bg-light text-muted">
                    <tr>
                        <th style="width: 240px;">No. Resi & Order SN</th>
                        <th style="width: 160px;">Ekspedisi</th>
                        <th>Rincian Produk</th>
                        <th style="width: 140px;">Pembeli</th>
                        <th style="width: 130px;" class="text-center">Durasi Kirim</th>
                        <th style="width: 180px;" class="text-center">Status Pengiriman</th>
                        <th style="width: 110px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packages as $pkg)
                        @php
                            $isDelayed = $pkg->isDelayed(5);
                            $logistics = $pkg->logistics_info;
                            $carrierStyle = $pkg->carrier_style;
                            $days = $pkg->days_in_transit;
                            $items = $pkg->items_data ?? [];
                            $totalQty = collect($items)->sum('model_quantity_purchased');
                            $hasVideo = $pkg->packingRecords->whereNotNull('video_path')->isNotEmpty();
                        @endphp
                        <tr class="{{ $isDelayed ? 'table-danger' : '' }}" style="{{ $isDelayed ? 'border-left: 4px solid #dc2626;' : '' }}">
                            <!-- No. Resi & Order SN -->
                            <td>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="font-weight-bold text-dark" style="font-family: monospace; font-size: 13px;">
                                        {{ $pkg->tracking_number ?? 'Belum ada resi' }}
                                    </span>
                                    @if($pkg->tracking_number)
                                        <button type="button" class="btn btn-link btn-xs text-muted p-0 ml-2" 
                                                onclick="navigator.clipboard.writeText('{{ $pkg->tracking_number }}'); alert('Nomor resi disalin: {{ $pkg->tracking_number }}');" 
                                                title="Salin Resi">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    @endif
                                </div>
                                <div class="text-xs text-muted mb-1">
                                    {{ $pkg->order_sn }}
                                </div>
                                <div class="text-xs text-muted">
                                    {{ $pkg->created_at->format('d/m/Y H:i') }}
                                </div>
                                @if($hasVideo)
                                    <div class="mt-1">
                                        <span class="badge badge-success text-xs font-weight-normal">
                                            <i class="fas fa-video mr-1"></i> Video Ada
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <!-- Ekspedisi -->
                            <td>
                                <span class="badge py-1 px-2 font-weight-bold text-xs mb-1 d-inline-block" 
                                      style="background-color: {{ $carrierStyle['bg'] }}; color: {{ $carrierStyle['color'] }}; border-radius: 4px;">
                                    {{ $carrierStyle['name'] }}
                                </span>
                                @if($pkg->last_tracked_at)
                                    <div class="text-muted" style="font-size: 10.5px;">
                                        {{ $pkg->last_tracked_at->diffForHumans() }}
                                    </div>
                                @endif
                            </td>

                            <!-- Rincian Produk -->
                            <td>
                                <div class="font-weight-semibold text-dark mb-1">
                                    {{ count($items) }} Produk ({{ $totalQty > 0 ? $totalQty : 1 }} Pcs)
                                </div>
                                <div class="text-muted text-xs">
                                    @if(!empty($items))
                                        @foreach(array_slice($items, 0, 2) as $it)
                                            <div class="text-truncate" style="max-width: 280px;">
                                                - {{ $it['item_name'] ?? ($it['item_sku'] ?? 'Item') }} 
                                                <span class="font-weight-bold">x{{ $it['model_quantity_purchased'] ?? 1 }}</span>
                                            </div>
                                        @endforeach
                                        @if(count($items) > 2)
                                            <span class="text-primary text-xs">+{{ count($items) - 2 }} produk lainnya</span>
                                        @endif
                                    @else
                                        <span>-</span>
                                    @endif
                                </div>
                                <div class="text-dark font-weight-bold text-xs mt-1">
                                    Rp {{ number_format((float) $pkg->total_amount, 0, ',', '.') }}
                                </div>
                            </td>

                            <!-- Pembeli -->
                            <td>
                                <div class="font-weight-semibold text-dark">
                                    {{ $pkg->buyer_username ?? '-' }}
                                </div>
                                <span class="badge badge-light border text-muted text-xs mt-1">
                                    {{ $pkg->order_status }}
                                </span>
                            </td>

                            <!-- Durasi Kirim -->
                            <td class="text-center">
                                @if($pkg->isDelivered())
                                    <span class="badge badge-success px-2 py-1">
                                        {{ $days }} Hari (Selesai)
                                    </span>
                                @elseif($isDelayed)
                                    <div class="badge badge-danger p-2 font-weight-bold d-inline-block">
                                        {{ $days }} Hari
                                    </div>
                                    <div class="text-danger font-weight-bold text-xs mt-1">
                                        Tertahan ≥ 5 Hari
                                    </div>
                                @else
                                    <div class="badge badge-info px-2 py-1">
                                        {{ $days }} Hari
                                    </div>
                                @endif
                            </td>

                            <!-- Status Pengiriman -->
                            <td class="text-center">
                                <span class="badge badge-{{ $logistics['badge'] }} px-2 py-1 font-weight-bold text-xs d-inline-block mb-1">
                                    <i class="fas {{ $logistics['icon'] }} mr-1"></i> {{ $logistics['label'] }}
                                </span>
                                @php
                                    $latestMilestone = !empty($pkg->tracking_data)
                                        ? collect($pkg->tracking_data)->sortByDesc(fn ($m) => $m['update_time'] ?? $m['ctime'] ?? 0)->first()
                                        : null;
                                @endphp
                                @if($latestMilestone)
                                    <div class="text-muted text-xs mx-auto" style="max-width: 220px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; white-space: normal; line-height: 1.35;" title="{{ $latestMilestone['description'] ?? '' }}">
                                        {{ $latestMilestone['description'] ?? '' }}
                                    </div>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="openPackageDetailModal({{ $pkg->id }})" 
                                            title="Lihat Rincian Paket">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <form action="{{ route('courier.sync', $pkg) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm" title="Sinkronkan Tracking">
                                            <i class="fas fa-rotate"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($packages->hasPages())
        <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="text-muted text-xs">
                Menampilkan {{ $packages->firstItem() }} - {{ $packages->lastItem() }} dari {{ $packages->total() }} paket
            </span>
            <div>
                {{ $packages->links('pagination::bootstrap-4') }}
            </div>
        </div>
    @endif
</div>

<!-- Modal Rincian Pengiriman Paket -->
<div class="modal fade" id="packageDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow" style="border-radius: 8px;">
            <div class="modal-header bg-light py-3 border-bottom">
                <div class="pr-2" style="min-width: 0;">
                    <h6 class="modal-title font-weight-bold text-dark mb-0 d-flex flex-wrap align-items-center" id="modalOrderSn" style="gap: 6px;">Rincian Paket</h6>
                    <small class="text-muted d-block text-break" id="modalTrackingNumber">-</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3 p-md-4" id="modalContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted text-sm mt-2">Memuat rincian paket...</div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 border-top d-flex justify-content-between">
                <div id="modalSlaNotice"></div>
                <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openPackageDetailModal(orderId) {
        $('#packageDetailModal').modal('show');
        $('#modalOrderSn').text('Memuat...');
        $('#modalTrackingNumber').text('-');
        $('#modalContent').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-muted text-sm mt-2">Memuat data pengiriman...</div>
            </div>
        `);
        $('#modalSlaNotice').html('');

        $.ajax({
            url: `/courier-tracking/${orderId}`,
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(res) {
                if (!res.success) {
                    $('#modalContent').html(`<div class="alert alert-danger">Gagal memuat rincian paket.</div>`);
                    return;
                }
                const d = res.data;
                $('#modalOrderSn').html(`Pesanan #${d.order_sn} <span class="badge badge-${d.logistics_info.badge} ml-2">${d.logistics_info.label}</span>`);
                $('#modalTrackingNumber').html(`No. Resi: <strong class="text-dark">${d.tracking_number}</strong> (${d.shipping_carrier})`);

                if (d.is_delayed_5_days) {
                    $('#modalSlaNotice').html(`
                        <span class="badge badge-danger p-2 text-xs font-weight-bold">
                            <i class="fas fa-triangle-exclamation mr-1"></i> Paket Tertahan ${d.days_in_transit} Hari
                        </span>
                    `);
                } else {
                    $('#modalSlaNotice').html(`
                        <span class="text-muted text-xs">
                            Durasi kirim: ${d.days_in_transit} hari
                        </span>
                    `);
                }

                let itemsHtml = '';
                if (d.items_data && d.items_data.length > 0) {
                    d.items_data.forEach(item => {
                        itemsHtml += `
                            <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light rounded border">
                                <div>
                                    <div class="font-weight-bold text-dark text-sm">${item.item_name || item.item_sku || 'Produk'}</div>
                                    <div class="text-muted text-xs">SKU: ${item.item_sku || '-'} | Qty: <strong>${item.model_quantity_purchased || 1} Pcs</strong></div>
                                </div>
                                <div class="font-weight-bold text-dark text-sm">
                                    Rp ${Number(item.model_discounted_price || 0).toLocaleString('id-ID')}
                                </div>
                            </div>
                        `;
                    });
                } else {
                    itemsHtml = '<div class="text-muted text-xs">Tidak ada data rincian item.</div>';
                }

                let timelineHtml = '';
                if (d.tracking_timeline && d.tracking_timeline.length > 0) {
                    const sorted = [...d.tracking_timeline].sort((a, b) => ((b.update_time || b.ctime || 0) - (a.update_time || a.ctime || 0)));
                    sorted.forEach((step, idx) => {
                        const isLatest = idx === 0;
                        const ts = step.update_time || step.ctime;
                        const dateStr = ts ? new Date(ts * 1000).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
                        timelineHtml += `
                            <div class="timeline-item-shopee">
                                <div class="timeline-dot ${isLatest ? (d.is_delayed_5_days ? 'danger' : 'active') : ''}"></div>
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-1" style="gap: 4px;">
                                    <span class="text-xs font-weight-bold text-muted">${dateStr}</span>
                                    <span class="badge badge-light border text-xs text-wrap text-left" style="max-width: 100%;">${step.logistics_status || 'UPDATE'}</span>
                                </div>
                                <div class="${isLatest ? 'font-weight-bold text-dark' : 'text-secondary'} text-sm" style="line-height: 1.45; word-break: break-word;">
                                    ${step.description || '-'}
                                </div>
                            </div>
                        `;
                    });
                } else {
                    timelineHtml = `
                        <div class="text-muted text-xs p-3 bg-light rounded border text-center">
                            Belum ada riwayat pelacakan kurir yang tercatat.
                        </div>
                    `;
                }

                let videoSection = '';
                if (d.has_video && d.packing_record) {
                    videoSection = `
                        <div class="alert alert-success border-0 p-2 mb-3 d-flex align-items-center justify-content-between text-xs">
                            <div>
                                <i class="fas fa-video mr-1"></i> Rekaman video packing tersedia (${d.packing_record.packer_name || 'Operator'})
                            </div>
                            <a href="${d.packing_record.video_path ? '/storage/' + d.packing_record.video_path.replace(/^\//, '') : '#'}" target="_blank" class="btn btn-xs btn-success font-weight-bold">
                                Putar Video
                            </a>
                        </div>
                    `;
                }

                const isMasked = val => !val || /^\*+$/.test(String(val).trim());
                let addressSection = '';
                const addr = d.recipient_address || {};
                const recipientName = (!isMasked(d.recipient_name) ? d.recipient_name : null) || (!isMasked(addr.name) ? addr.name : null) || d.buyer_username || '-';
                const rawPhone = (!isMasked(d.recipient_phone) ? d.recipient_phone : null) || (!isMasked(addr.phone) ? addr.phone : null);
                const recipientPhone = rawPhone ? `<span class="text-muted ml-1 font-weight-normal">(${rawPhone})</span>` : '';
                const destinationCity = (!isMasked(d.destination_city) && d.destination_city !== '-') ? d.destination_city : '';
                const cleanFullAddress = (!isMasked(d.formatted_address) && d.formatted_address !== '-') ? d.formatted_address : (!isMasked(addr.full_address) ? addr.full_address : '');
                const regionParts = [addr.sub_district, addr.town, addr.district, addr.city, addr.state, addr.zipcode].filter(v => !isMasked(v)).join(', ');
                const zipcodeBadge = !isMasked(addr.zipcode) ? `<span class="badge badge-light border text-muted ml-1">${addr.zipcode}</span>` : '';

                let addressBodyHtml = '';
                if (d.address_image) {
                    addressBodyHtml = `
                        ${d.name_image ? `<div class="mb-1"><img src="${d.name_image}" alt="Nama Penerima" style="max-height: 22px;" class="mr-2">${d.phone_image ? `<img src="${d.phone_image}" alt="No. HP" style="max-height: 20px;">` : ''}</div>` : `<div class="font-weight-bold text-dark text-sm mb-1">${recipientName} ${recipientPhone}</div>`}
                        <div class="bg-white p-2 rounded border mb-1">
                            <img src="${d.address_image}" alt="Alamat Lengkap Penerima" class="img-fluid" style="max-height: 110px;">
                        </div>
                        ${cleanFullAddress ? `<div class="text-secondary text-xs">Kode Sortir / Area: <strong>${cleanFullAddress}</strong></div>` : ''}
                    `;
                } else if (cleanFullAddress) {
                    addressBodyHtml = `
                        <div class="font-weight-bold text-dark text-sm mb-1">
                            ${recipientName} ${recipientPhone}
                        </div>
                        <div class="text-secondary text-xs" style="line-height: 1.45;">
                            ${cleanFullAddress} ${zipcodeBadge}
                        </div>
                        ${(regionParts && cleanFullAddress !== regionParts) ? `<div class="text-muted text-xs mt-1"><i class="fas fa-city mr-1"></i>${regionParts}</div>` : ''}
                    `;
                } else {
                    addressBodyHtml = `
                        <div class="font-weight-bold text-dark text-sm mb-1">
                            ${recipientName} ${recipientPhone}
                        </div>
                        <div class="text-secondary text-xs">
                            ${destinationCity || '-'}
                        </div>
                    `;
                }

                addressSection = `
                    <div class="card border-0 mb-3" style="background-color: #f1f5f9; border-left: 3.5px solid #2563eb !important; border-radius: 6px;">
                        <div class="card-body p-2 p-md-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="font-weight-bold text-dark text-xs text-uppercase" style="letter-spacing: 0.5px;">
                                    <i class="fas fa-map-marker-alt text-danger mr-1"></i> Alamat Pengiriman
                                </span>
                                ${destinationCity ? `<span class="badge badge-primary px-2 py-1 font-weight-normal text-xs">${destinationCity}</span>` : ''}
                            </div>
                            ${addressBodyHtml}
                        </div>
                    </div>
                `;

                $('#modalContent').html(`
                    <div class="row">
                        <div class="col-12 col-md-6 modal-col-divider">
                            <h6 class="font-weight-bold text-dark mb-2">Rincian Produk</h6>
                            <div class="mb-3">
                                ${itemsHtml}
                            </div>

                            ${addressSection}
                            
                            <div class="bg-light p-3 rounded border mb-3 text-xs">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Total Pembayaran:</span>
                                    <strong class="text-dark">${d.formatted_amount}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Pembeli:</span>
                                    <strong class="text-dark">${d.buyer_username || '-'}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Ekspedisi:</span>
                                    <strong class="text-dark">${d.shipping_carrier}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Tanggal Pesanan:</span>
                                    <span class="text-dark">${d.created_at}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Durasi Pengiriman:</span>
                                    <strong class="${d.is_delayed_5_days ? 'text-danger' : 'text-dark'}">${d.days_in_transit} Hari</strong>
                                </div>
                            </div>

                            ${videoSection}
                        </div>

                        <div class="col-12 col-md-6 pl-md-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="font-weight-bold text-dark mb-0">Riwayat Pengiriman</h6>
                                <form action="/courier-tracking/sync/${d.id}" method="POST" class="d-inline">
                                    <input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
                                    <button type="submit" class="btn btn-xs btn-outline-primary font-weight-bold">
                                        Perbarui
                                    </button>
                                </form>
                            </div>
                            <div class="timeline-wrapper-shopee">
                                ${timelineHtml}
                            </div>
                        </div>
                    </div>
                `);
            },
            error: function(err) {
                $('#modalContent').html(`
                    <div class="alert alert-danger">
                        Gagal memuat rincian paket.
                    </div>
                `);
            }
        });
    }
</script>
@endpush
