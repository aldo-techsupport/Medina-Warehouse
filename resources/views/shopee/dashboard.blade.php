@extends('layouts.adminlte')

@section('title', 'Dashboard Shopee Open API')
@section('page_title', 'Integrasi Shopee & Channel Sync')

@section('page_actions')
    @if($currentShopeeSetting->isSandbox())
    <div class="d-inline-flex align-items-center">
        <button type="button" class="btn btn-shopee btn-sm shadow-sm font-weight-bold" data-toggle="modal" data-target="#simulatorModal">
            <i class="fas fa-vial mr-1"></i> Simulasi Order
        </button>
    </div>
    @endif
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
                                <span class="mr-2">
                                    Potong Stok: <strong class="text-success"><i class="fas fa-check"></i> Aktif</strong>
                                </span>
                                @if($setting->isConnected())
                                    @if($setting->isTokenExpired())
                                        <span class="badge badge-warning px-2 py-1">
                                            <i class="fas fa-clock mr-1"></i> Token Kedaluwarsa
                                        </span>
                                    @else
                                        <span class="badge badge-success px-2 py-1">
                                            <i class="fas fa-check-circle mr-1"></i> Terhubung (Token Aktif)
                                        </span>
                                    @endif
                                @else
                                    <span class="badge badge-warning px-2 py-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Belum Terhubung
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center mt-2 mt-md-0">
                        @if(!empty($apiDashboardUrl))
                            <a href="{{ $apiDashboardUrl }}" target="_blank" class="btn btn-outline-primary btn-xs font-weight-bold mr-1 mb-1">
                                <i class="fas fa-server mr-1"></i> Dashboard OpenShopee API
                            </a>
                        @endif
                        @if($authUrl)
                            <a href="{{ $authUrl }}" target="_blank" class="btn btn-shopee btn-xs font-weight-bold mr-1 mb-1">
                                <i class="fas fa-lock mr-1"></i> Otorisasi (OAuth)
                            </a>
                        @endif
                        <a href="{{ route('shopee.settings') }}" class="btn btn-outline-dark btn-xs mb-1">
                            <i class="fas fa-cog mr-1"></i> Koneksi API
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
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0 shadow-xs" style="border-radius: 8px;">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Total Pesanan Sah</p>
                <h3 class="font-weight-bold text-dark mb-0" style="font-size: 1.4rem;">{{ number_format($totalOrders) }}</h3>
                <small class="text-muted" style="font-size: 11px;">
                    {{ $completedCount }} Selesai &bull; {{ $inShippingCount }} di Kurir
                </small>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-shopping-bag text-info fa-2x"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0 shadow-xs" style="border-left: 3px solid #f59e0b !important; border-radius: 8px;">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">
                    <i class="fas fa-clock text-warning mr-1"></i> Perlu Dipack (Ready to Ship)
                </p>
                <h3 class="font-weight-bold text-warning mb-0" style="font-size: 1.4rem;">{{ number_format($readyToShipCount) }}</h3>
                <div class="mt-1">
                    <a href="{{ route('shopee.orders') }}" class="font-weight-bold text-warning" style="font-size: 11px;">
                        Buka Pesanan Masuk &rarr;
                    </a>
                </div>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-truck-loading text-warning fa-2x"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0 shadow-xs" style="border-left: 3px solid #10b981 !important; border-radius: 8px;">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">
                    <i class="fas fa-coins text-success mr-1"></i> Total Penjualan Shopee
                </p>
                <h3 class="font-weight-bold text-success mb-0" style="font-size: 1.15rem; line-height: 1.2;">
                    Rp {{ number_format($totalSales, 0, ',', '.') }}
                </h3>
                <div class="mt-1 d-flex flex-column text-muted" style="font-size: 10.5px; line-height: 1.3;">
                    <span class="text-success"><i class="fas fa-check-circle mr-1"></i> Cair: Rp {{ number_format($releasedSales, 0, ',', '.') }}</span>
                    <span class="text-warning"><i class="fas fa-hourglass-half mr-1"></i> Akan Cair: Rp {{ number_format($escrowSales, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-wallet text-success fa-2x"></i>
            </div>
        </div>
    </div>
</div>

<!-- Workflow Quick Status Bar -->
<div class="card shadow-sm border-0 mb-3 bg-light">
    <div class="card-body p-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center flex-wrap my-1">
            <span class="font-weight-bold mr-3 text-dark" style="font-size: 12px;">Alur Pesanan & Pengiriman:</span>
            <a href="{{ route('shopee.orders') }}" class="btn btn-xs btn-outline-warning font-weight-bold mr-2 mb-1">
                <i class="fas fa-box-open mr-1"></i> 1. Pesanan Masuk ({{ $readyToShipCount }} Ready to Ship)
            </a>
            <a href="{{ route('courier.index', ['filter' => 'in_transit']) }}" class="btn btn-xs btn-outline-primary font-weight-bold mr-2 mb-1">
                <i class="fas fa-truck-fast mr-1"></i> 2. Dalam Pengiriman ({{ $inShippingCount }} di Kurir)
            </a>
            <a href="{{ route('courier.index', ['filter' => 'delivered']) }}" class="btn btn-xs btn-outline-success font-weight-bold mr-2 mb-1">
                <i class="fas fa-circle-check mr-1"></i> 3. Selesai ({{ $completedCount }} Terkirim)
            </a>
            @if($cancelledCount > 0)
                <span class="badge badge-secondary px-2 py-1 mb-1" style="font-size: 11px;">
                    Dibatalkan / Retur: {{ $cancelledCount }} (Stok dikembalikan)
                </span>
            @endif
        </div>
        <a href="{{ route('courier.index') }}" class="btn btn-xs btn-primary font-weight-bold my-1">
            <i class="fas fa-location-arrow mr-1"></i> Buka Menu Lacak Kurir &rarr;
        </a>
    </div>
</div>

<!-- Products Mapping -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark" style="font-size: 13px;">
            <i class="fas fa-boxes text-primary mr-1"></i> Produk Terhubung Shopee
        </span>
        <div class="d-inline-flex align-items-center">
            <button type="button" class="btn btn-xs btn-primary font-weight-bold shadow-sm mr-2" data-toggle="modal" data-target="#quickMutationModal">
                <i class="fas fa-plus mr-1"></i> Tambah / Mutasi Stok
            </button>
            <a href="{{ route('shopee.dashboard', ['refresh' => 1]) }}" class="btn btn-xs btn-outline-secondary">
                <i class="fas fa-sync-alt mr-1"></i> Segarkan
            </a>
        </div>
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
                    <th class="text-center" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($connectedProducts as $p)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                @if($p->image)
                                    <img src="{{ $p->image_url }}" alt="{{ $p->name }}" class="rounded mr-2 border" style="width: 38px; height: 38px; object-fit: cover;">
                                @endif
                                <div>
                                    <div class="font-weight-bold text-dark">{{ $p->name }}</div>
                                    <small class="text-muted">SKU: {{ $p->sku }}</small>
                                </div>
                            </div>
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
                            <span class="badge badge-success px-2 py-1 font-weight-normal">Terhubung</span>
                        </td>
                        <td class="text-center">
                            <button type="button" 
                                class="btn btn-xs btn-outline-primary font-weight-bold shadow-sm btn-open-stock-modal" 
                                data-toggle="modal" 
                                data-target="#shopeeStockModal" 
                                data-id="{{ $p->id }}"
                                data-sku="{{ $p->sku }}"
                                data-name="{{ $p->name }}"
                                data-shopee-item-id="{{ $p->shopee_item_id }}"
                                data-stock="{{ (int) $p->stock }}"
                                data-unit="{{ $p->unit }}"
                                title="Ubah atau Tambah Stok Produk Ini">
                                <i class="fas fa-edit mr-1"></i> Ubah Stok
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fas fa-box-open fa-2x mb-2 text-secondary d-block"></i>
                            Belum ada produk yang terhubung dari Shopee.<br>
                            <small class="text-muted">Produk akan tersinkronisasi secara otomatis dari toko Shopee Anda di latar belakang.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ubah Stok Produk Shopee (Single Reusable Dynamic Modal) -->
<div class="modal fade" id="shopeeStockModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('warehouse.mutations.store') }}" method="POST">
                @csrf
                <input type="hidden" name="product_id" id="modalProductId" value="">
                <input type="hidden" name="actor" value="{{ auth()->user()->name ?? 'Admin Gudang' }}">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title font-weight-bold">
                        <i class="fas fa-boxes mr-1"></i> Ubah / Tambah Stok: <span id="modalSkuTitle"></span>
                    </h6>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3">
                    <div class="bg-light p-2 rounded mb-3 border">
                        <div class="font-weight-bold text-dark" id="modalProductName"></div>
                        <div class="d-flex justify-content-between text-xs text-muted mt-1">
                            <span>Shopee Item ID: <strong id="modalShopeeItemId"></strong></span>
                            <span>Stok Saat Ini: <strong class="text-primary" id="modalCurrentStockText"></strong></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-semibold text-sm">Jenis Perubahan Stok <span class="text-danger">*</span></label>
                        <select name="type" class="form-control form-control-sm" required id="modalTypeSelect">
                            <option value="inbound" selected>📦 Tambah Stok / Barang Masuk (+ Tambah)</option>
                            <option value="adjustment">⚖️ Set Total Stok Baru (Stok Opname Langsung)</option>
                            <option value="outbound">🚚 Kurangi Stok / Barang Keluar (- Kurang)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-semibold text-sm" id="modalQtyLabel">Jumlah Unit Tambahan <span class="text-danger">*</span></label>
                        <input type="number" name="qty" id="modalQtyInput" class="form-control form-control-sm font-weight-bold" placeholder="" required min="1">
                        <small class="text-muted d-block mt-1">
                            Stok akhir setelah disimpan: <strong class="text-success" id="modalCalcResult">0</strong>
                        </small>
                    </div>

                    <div class="form-group mb-2">
                        <label class="font-weight-semibold text-sm">Keterangan / Catatan (Opsional)</label>
                        <input type="text" name="notes" id="modalNotesInput" class="form-control form-control-sm" placeholder="">
                    </div>

                    <div class="alert alert-info py-2 px-3 mb-0 text-xs rounded border-0">
                        <i class="fas fa-info-circle mr-1"></i> Perubahan stok otomatis tersimpan di gudang & langsung di-push live ke API Shopee.
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan & Update Shopee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var modalCurrentStock = 0;
    var modalUnit = 'unit';

    $(document).on('click', '.btn-open-stock-modal', function() {
        var btn = $(this);
        var id = btn.data('id');
        var sku = btn.data('sku');
        var name = btn.data('name');
        var shopeeItemId = btn.data('shopee-item-id') || '-';
        modalCurrentStock = parseInt(btn.data('stock')) || 0;
        modalUnit = btn.data('unit') || 'unit';

        $('#modalProductId').val(id);
        $('#modalSkuTitle').text(sku);
        $('#modalProductName').text(name);
        $('#modalShopeeItemId').text(shopeeItemId);
        $('#modalCurrentStockText').text(modalCurrentStock + ' ' + modalUnit);
        $('#modalTypeSelect').val('inbound');
        $('#modalQtyInput').val('');
        $('#modalNotesInput').val('');
        updateModalStockCalc();
    });

    $('#modalTypeSelect, #modalQtyInput').on('input change', function() {
        updateModalStockCalc();
    });

    function updateModalStockCalc() {
        var type = $('#modalTypeSelect').val() || 'inbound';
        var qty = parseInt($('#modalQtyInput').val()) || 0;
        var label = $('#modalQtyLabel');
        var newStock = modalCurrentStock;

        if (type === 'inbound') {
            label.html('Jumlah Unit Tambahan (Masuk) <span class="text-danger">*</span>');
            $('#modalQtyInput').attr('min', 1);
            newStock = modalCurrentStock + qty;
        } else if (type === 'outbound') {
            label.html('Jumlah Unit yang Dikurangi (Keluar) <span class="text-danger">*</span>');
            $('#modalQtyInput').attr('min', 1);
            newStock = Math.max(0, modalCurrentStock - qty);
        } else if (type === 'adjustment') {
            label.html('Total Stok Fisik Baru (Opname Langsung) <span class="text-danger">*</span>');
            $('#modalQtyInput').attr('min', 0);
            newStock = qty;
        }
        $('#modalCalcResult').text(newStock + ' ' + modalUnit);
    }
});
</script>
@endpush
