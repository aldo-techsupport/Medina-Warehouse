@extends('layouts.adminlte')

@section('title', 'Dashboard Shopee Open API')
@section('page_title', 'Integrasi Shopee & Channel Sync')

@section('page_actions')
    <div class="btn-group d-none d-sm-inline-flex">
        <form action="{{ route('shopee.sync.all') }}" method="POST" class="d-inline mr-1">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sync-alt mr-1"></i> Sync Semua Stok
            </button>
        </form>
        <button type="button" class="btn btn-shopee btn-sm" data-toggle="modal" data-target="#simulatorModal">
            <i class="fas fa-bolt mr-1"></i> Simulasi Order
        </button>
    </div>
@endsection

@section('content')

<!-- Shopee API Connection Banner -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 mb-3" style="border-top: 4px solid var(--shopee-orange) !important;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <div class="rounded p-2 d-flex align-items-center justify-content-center mr-2 mr-md-3 bg-shopee text-white shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="fas fa-store fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="font-weight-bold mb-1 text-dark">{{ $setting->shop_name }}</h5>
                            <div class="d-flex flex-wrap align-items-center text-muted" style="font-size: 11px;">
                                <span class="mr-2">
                                    <i class="fas fa-server mr-1"></i> Mode: <strong>{{ strtoupper($setting->environment) }}</strong>
                                </span>
                                <span class="mr-2">
                                    <i class="fas fa-id-badge mr-1"></i> Shop ID: <strong>{{ $setting->shop_id ?: '-' }}</strong>
                                </span>
                                <span>
                                    Auto-Deduct: <strong class="text-success"><i class="fas fa-check"></i> AKTIF</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center mt-2 mt-md-0">
                        @if($authUrl)
                            <a href="{{ $authUrl }}" target="_blank" class="btn btn-shopee btn-xs font-weight-bold mr-1 mb-1">
                                <i class="fas fa-lock mr-1"></i> Otorisasi (OAuth)
                            </a>
                        @endif
                        <a href="{{ route('shopee.settings') }}" class="btn btn-outline-dark btn-xs mb-1">
                            <i class="fas fa-cog mr-1"></i> Pengaturan API
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="row">
    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">SKU Terhubung</p>
                <h3 class="font-weight-bold mb-0" style="color: var(--shopee-orange); font-size: 1.4rem;">
                    {{ $connectedProducts->count() }} <span style="font-size: 11px; font-weight: normal;" class="text-muted">SKU</span>
                </h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-link fa-2x" style="color: var(--shopee-orange);"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Total Pesanan</p>
                <h3 class="font-weight-bold text-dark mb-0" style="font-size: 1.4rem;">{{ number_format($totalOrders) }}</h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-shopping-bag text-info fa-2x"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Perlu Dipack</p>
                <h3 class="font-weight-bold text-warning mb-0" style="font-size: 1.4rem;">{{ $readyToShipCount }}</h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-truck-loading text-warning fa-2x"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Total Penjualan</p>
                <h3 class="font-weight-bold text-success mb-0" style="font-size: 1.15rem; line-height: 1.2;">
                    Rp {{ number_format($totalSales, 0, ',', '.') }}
                </h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-coins text-success fa-2x"></i>
            </div>
        </div>
    </div>
</div>

<!-- Products Mapping & Live Sync -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark" style="font-size: 13px;">
            <i class="fas fa-boxes text-primary mr-1"></i> Sinkronisasi Stok Gudang &rarr; Shopee
        </span>
        <form action="{{ route('shopee.sync.all') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-xs btn-outline-secondary">
                <i class="fas fa-sync mr-1"></i> Push Semua
            </button>
        </form>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover table-striped mb-0 text-sm align-middle table-mobile-responsive">
            <thead class="bg-light">
                <tr>
                    <th>Produk & SKU</th>
                    <th class="text-center">Shopee Item ID</th>
                    <th class="text-center">Stok Gudang</th>
                    <th class="text-center d-none d-sm-table-cell">Stok Shopee</th>
                    <th class="text-center">Status Sync</th>
                    <th class="text-center" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($connectedProducts as $p)
                    <tr>
                        <td>
                            <div class="font-weight-bold text-dark">{{ $p->name }}</div>
                            <small class="text-muted">SKU: {{ $p->sku }}</small>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light border">{{ $p->shopee_item_id }}</span>
                        </td>
                        <td class="text-center font-weight-bold text-primary">
                            {{ $p->stock }} {{ $p->unit }}
                        </td>
                        <td class="text-center font-weight-bold d-none d-sm-table-cell" style="color: var(--shopee-orange);">
                            {{ $p->shopee_stock ?? $p->stock }}
                        </td>
                        <td class="text-center">
                            @if($p->stock === $p->shopee_stock)
                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Sinkron</span>
                            @else
                                <span class="badge badge-warning px-2 py-1 text-dark"><i class="fas fa-exclamation-triangle mr-1"></i> Perlu Push</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <form action="{{ route('shopee.sync.product', $p) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-shopee">
                                    <i class="fas fa-sync-alt mr-1"></i> Push
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
