@extends('layouts.adminlte')

@section('title', 'Riwayat Mutasi Stok')
@section('page_title', 'Riwayat Mutasi Stok Gudang')

@section('page_actions')
    <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-toggle="modal" data-target="#quickMutationModal">
        <i class="fas fa-plus mr-1"></i> Catat Mutasi
    </button>
@endsection

@section('content')

<!-- Filter Bar -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('warehouse.mutations') }}" method="GET">
            <div class="row no-gutters">
                <div class="col-12 col-md-4 pr-md-2 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Cari No. Ref, SKU, catatan..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-6 col-md-3 pr-1 pr-md-2 mb-2 mb-md-0">
                    <select name="type" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Jenis --</option>
                        <option value="inbound" {{ request('type') == 'inbound' ? 'selected' : '' }}>📦 Barang Masuk</option>
                        <option value="outbound" {{ request('type') == 'outbound' ? 'selected' : '' }}>🚚 Barang Keluar</option>
                        <option value="adjustment" {{ request('type') == 'adjustment' ? 'selected' : '' }}>⚖️ Stok Opname</option>
                        <option value="shopee_sale" {{ request('type') == 'shopee_sale' ? 'selected' : '' }}>🛍️ Shopee Sale</option>
                        <option value="shopee_cancellation" {{ request('type') == 'shopee_cancellation' ? 'selected' : '' }}>🔄 Shopee Cancel</option>
                    </select>
                </div>
                <div class="col-6 col-md-4 pl-1 pr-md-2 mb-2 mb-md-0">
                    <select name="product_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Produk --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>[{{ $p->sku }}] {{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-1">
                    <button type="submit" class="btn btn-sm btn-secondary btn-block">Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Mutations Table Card -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark" style="font-size: 13px;">
            <i class="fas fa-history text-primary mr-1"></i> Log Mutasi ({{ $mutations->total() }} Log)
        </span>
    </div>
    <div class="card-body p-0 table-responsive">
        @if($mutations->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="fas fa-receipt fa-3x mb-2 text-secondary"></i>
                <h6>Belum ada riwayat mutasi</h6>
                <p class="mb-0 text-sm">Semua pergerakan stok masuk, keluar, dan pembelian Shopee akan tercatat di sini.</p>
            </div>
        @else
            <table class="table table-hover table-striped mb-0 text-sm align-middle table-mobile-responsive">
                <thead class="bg-light">
                    <tr>
                        <th>Waktu & Ref</th>
                        <th>Produk / SKU</th>
                        <th>Jenis</th>
                        <th class="text-center">Perubahan</th>
                        <th class="text-center">Sisa</th>
                        <th class="d-none d-md-table-cell">Keterangan</th>
                        <th class="d-none d-lg-table-cell">Operator</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mutations as $m)
                        <tr>
                            <td>
                                <span class="font-weight-semibold text-dark">{{ $m->reference_no }}</span>
                                <br><small class="text-muted">{{ $m->created_at->format('d/m/y H:i') }}</small>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $m->product->name ?? 'Produk Dihapus' }}</div>
                                <small class="text-muted">SKU: {{ $m->product->sku ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="badge badge-{{ $m->badge_class }} px-2 py-1">
                                    @if($m->type === 'shopee_sale')
                                        <i class="fas fa-shopping-bag mr-1"></i>
                                    @endif
                                    {{ $m->type_label }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($m->qty > 0)
                                    <span class="badge badge-success px-2 py-1 font-weight-bold">+{{ $m->qty }}</span>
                                @else
                                    <span class="badge badge-danger px-2 py-1 font-weight-bold">{{ $m->qty }}</span>
                                @endif
                            </td>
                            <td class="text-center font-weight-bold text-dark">
                                {{ $m->stock_before }} &rarr; <span class="text-primary">{{ $m->stock_after }}</span>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <small class="text-dark">{{ $m->notes ?: '-' }}</small>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <small class="text-muted">{{ $m->actor }}</small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if($mutations->hasPages())
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Total: {{ $mutations->total() }} Log</small>
            {{ $mutations->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>

@endsection
