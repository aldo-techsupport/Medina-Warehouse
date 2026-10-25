@extends('layouts.adminlte')

@section('title', 'Pesanan Masuk Shopee')
@section('page_title', 'Pesanan Masuk Shopee')

@section('page_actions')
    <div class="d-inline-flex align-items-center flex-wrap">
        <a href="{{ route('courier.index') }}" class="btn btn-outline-primary btn-sm mr-2 mb-1 shadow-xs">
            <i class="fas fa-truck-fast mr-1"></i> Lacak Kurir
        </a>
        <a href="{{ route('shopee.orders', ['refresh' => 1]) }}" class="btn btn-outline-secondary btn-sm mr-2 mb-1">
            <i class="fas fa-sync-alt mr-1"></i> Segarkan
        </a>
        @if($currentShopeeSetting->isSandbox())
            <button type="button" class="btn btn-shopee btn-sm shadow-sm font-weight-bold mb-1" data-toggle="modal" data-target="#simulatorModal">
                <i class="fas fa-vial mr-1"></i> Simulasi Pesanan
            </button>
        @endif
    </div>
@endsection

@section('content')

<!-- Search Bar -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('shopee.orders') }}" method="GET">
            <div class="row align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Cari Order SN, pembeli, atau resi..." value="{{ request('search') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-sm btn-secondary">Cari</button>
                            @if(request('search'))
                                <a href="{{ route('shopee.orders') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark" style="font-size: 13px;">
            <i class="fas fa-shopping-cart text-warning mr-1"></i> Data Pesanan Shopee ({{ $orders->total() }} Pesanan)
        </span>
    </div>
    <div class="card-body p-0 table-responsive">
        @if($orders->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-2 text-secondary"></i>
                <h6 class="font-weight-bold text-dark">Belum ada pesanan masuk</h6>
                <p class="mb-3 text-sm">Pesanan baru dari Shopee akan otomatis tampil di sini dan memotong stok gudang.</p>
                @if($currentShopeeSetting->isSandbox())
                    <button type="button" class="btn btn-shopee btn-sm" data-toggle="modal" data-target="#simulatorModal">
                        <i class="fas fa-vial mr-1"></i> Buat Pesanan Simulasi
                    </button>
                @endif
            </div>
        @else
            <table class="table table-hover table-striped mb-0 text-sm align-middle table-mobile-responsive">
                <thead class="bg-light">
                    <tr>
                        <th>Order SN & Waktu</th>
                        <th>Pembeli & Ekspedisi</th>
                        <th>Item Pesanan</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center d-none d-sm-table-cell">Auto-Deduct</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td>
                                <span class="font-weight-bold text-dark">{{ $order->order_sn }}</span>
                                @if($order->tracking_number)
                                    <br><small class="text-muted"><i class="fas fa-barcode mr-1"></i> {{ $order->tracking_number }}</small>
                                @endif
                                <br><small class="text-muted"><i class="fas fa-clock mr-1"></i> {{ $order->created_at->format('d/m/y H:i') }}</small>
                            </td>
                            <td>
                                <div class="font-weight-semibold text-dark"><i class="fas fa-user-circle text-muted mr-1"></i> {{ $order->buyer_username }}</div>
                                <span class="badge badge-light border text-dark mt-1">
                                    <i class="fas fa-truck text-primary mr-1"></i> {{ $order->shipping_carrier ?? 'SPX Express' }}
                                </span>
                            </td>
                            <td>
                                @if(!empty($order->items_data))
                                    @foreach($order->items_data as $it)
                                        <div class="mb-1" style="font-size: 12px;">
                                            <span class="font-weight-bold text-dark">{{ $it['item_name'] ?? ($it['item_sku'] ?? 'Item') }}</span>
                                            @if(!empty($it['model_name']))
                                                <span class="badge badge-info ml-1">{{ $it['model_name'] }}</span>
                                            @endif
                                            <span class="badge badge-light border ml-1 font-weight-bold">x{{ $it['model_quantity_purchased'] ?? ($it['qty'] ?? 1) }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-right font-weight-bold text-dark" style="font-size: 13px;">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold">
                                    READY_TO_SHIP
                                </span>
                            </td>
                            <td class="text-center d-none d-sm-table-cell">
                                @if($order->stock_deducted)
                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Terpotong</span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1">Tidak</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('packing.index', ['search' => $order->order_sn]) }}" class="btn btn-xs btn-primary font-weight-bold shadow-xs">
                                    <i class="fas fa-box-open mr-1"></i> Pack
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if($orders->hasPages())
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Total: {{ $orders->total() }} Pesanan</small>
            {{ $orders->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>

@endsection
