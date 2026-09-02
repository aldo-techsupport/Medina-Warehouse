@extends('layouts.adminlte')

@section('title', 'Daftar Pesanan Shopee')
@section('page_title', 'Pesanan Masuk dari Shopee')

@section('page_actions')
    <button type="button" class="btn btn-shopee btn-sm d-none d-sm-inline-block" data-toggle="modal" data-target="#simulatorModal">
        <i class="fas fa-bolt mr-1"></i> Simulasi Pesanan
    </button>
@endsection

@section('content')

<!-- Filter Bar -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('shopee.orders') }}" method="GET">
            <div class="row no-gutters">
                <div class="col-12 col-md-5 pr-md-2 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Cari Order SN atau pembeli..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-8 col-md-5 pr-1 pr-md-2 mb-2 mb-md-0">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Status --</option>
                        <option value="READY_TO_SHIP" {{ request('status') == 'READY_TO_SHIP' ? 'selected' : '' }}>🟡 READY_TO_SHIP (Perlu Dikirim)</option>
                        <option value="PROCESSED" {{ request('status') == 'PROCESSED' ? 'selected' : '' }}>🔵 PROCESSED (Diproses)</option>
                        <option value="COMPLETED" {{ request('status') == 'COMPLETED' ? 'selected' : '' }}>🟢 COMPLETED (Selesai)</option>
                        <option value="CANCELLED" {{ request('status') == 'CANCELLED' ? 'selected' : '' }}>🔴 CANCELLED (Dibatalkan)</option>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <button type="submit" class="btn btn-sm btn-secondary btn-block">Filter</button>
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
                <h6>Belum ada data pesanan</h6>
                <p class="mb-3 text-sm">Pesanan dari Shopee Open API / Webhook akan otomatis tersimpan di sini dan memotong stok gudang.</p>
                <button type="button" class="btn btn-shopee btn-sm" data-toggle="modal" data-target="#simulatorModal">
                    <i class="fas fa-bolt mr-1"></i> Buat Pesanan Simulasi
                </button>
            </div>
        @else
            <table class="table table-hover table-striped mb-0 text-sm align-middle table-mobile-responsive">
                <thead class="bg-light">
                    <tr>
                        <th>Order SN & Resi</th>
                        <th>Pembeli</th>
                        <th>Item Pesanan</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center d-none d-sm-table-cell">Auto-Deduct</th>
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
                                <br><small class="text-muted">{{ $order->created_at->format('d/m/y H:i') }}</small>
                            </td>
                            <td>
                                <div class="font-weight-semibold text-dark"><i class="fas fa-user-circle text-muted mr-1"></i> {{ $order->buyer_username }}</div>
                                <small class="text-muted">{{ $order->shipping_carrier ?? 'SPX Express' }}</small>
                            </td>
                            <td>
                                @if(!empty($order->items_data))
                                    @foreach($order->items_data as $it)
                                        <div class="mb-1" style="font-size: 12px;">
                                            <span class="font-weight-bold text-dark">{{ $it['item_name'] ?? ($it['item_sku'] ?? 'Item') }}</span>
                                            <span class="badge badge-light border ml-1 font-weight-bold">x{{ $it['model_quantity_purchased'] ?? ($it['qty'] ?? 1) }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-right font-weight-bold" style="font-size: 13px;">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                @if($order->order_status === 'COMPLETED')
                                    <span class="badge badge-success px-2 py-1">COMPLETED</span>
                                @elseif($order->order_status === 'READY_TO_SHIP')
                                    <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold">READY_TO_SHIP</span>
                                @elseif($order->order_status === 'CANCELLED' || $order->isCancelled())
                                    <span class="badge badge-danger px-2 py-1 font-weight-bold">CANCELLED</span>
                                @else
                                    <span class="badge badge-info px-2 py-1">{{ $order->order_status }}</span>
                                @endif
                            </td>
                            <td class="text-center d-none d-sm-table-cell">
                                @if($order->stock_deducted)
                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Terpotong</span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1">Tidak</span>
                                @endif
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
