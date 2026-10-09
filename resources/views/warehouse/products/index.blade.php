@extends('layouts.adminlte')

@section('title', 'Katalog & Stok Gudang Utama')
@section('page_title', 'Katalog Produk & Stok Gudang')

@section('page_actions')
    <div class="d-inline-flex align-items-center">
        <a href="{{ route('warehouse.products', ['refresh' => 1]) }}" class="btn btn-outline-secondary btn-sm font-weight-bold shadow-sm mr-2">
            <i class="fas fa-sync-alt mr-1"></i> Segarkan
        </a>
        <button type="button" class="btn btn-primary btn-sm font-weight-bold shadow-sm" data-toggle="modal" data-target="#addProductModal">
            <i class="fas fa-plus mr-1"></i> Tambah Produk
        </button>
    </div>
@endsection

@section('content')

<!-- Search & Filter Card -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('warehouse.products') }}" method="GET">
            <input type="hidden" name="status" value="{{ $statusTab }}">
            <div class="row no-gutters">
                <div class="col-12 col-md-5 pr-md-2 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Cari nama produk, SKU, barcode..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-6 col-md-3 pr-1 pr-md-2 mb-2 mb-md-0">
                    <select name="category" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Kategori --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 pl-1 pr-md-2 mb-2 mb-md-0">
                    <select name="stock_status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Status Stok --</option>
                        <option value="low" {{ request('stock_status') == 'low' ? 'selected' : '' }}>⚠️ Menipis</option>
                        <option value="out" {{ request('stock_status') == 'out' ? 'selected' : '' }}>❌ Habis (0)</option>
                    </select>
                </div>
                <div class="col-12 col-md-1">
                    <button type="submit" class="btn btn-sm btn-secondary btn-block">Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sub-Tabs: Semua Produk / Aktif / Tidak Aktif (Nonaktif) -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between">
        <ul class="nav nav-pills custom-status-tabs mb-0">
            <li class="nav-item mr-2 mb-1 mb-md-0">
                <a class="nav-link font-weight-bold px-3 py-2 {{ $statusTab === 'all' ? 'active shadow-sm' : 'text-dark bg-light' }}" 
                   href="{{ route('warehouse.products', array_merge(request()->except(['page', 'status']), ['status' => 'all'])) }}">
                    <i class="fas fa-boxes mr-1 {{ $statusTab === 'all' ? '' : 'text-primary' }}"></i> Semua Produk 
                    <span class="badge {{ $statusTab === 'all' ? 'badge-light text-primary' : 'badge-secondary' }} ml-1 font-weight-bold">{{ $countAll }}</span>
                </a>
            </li>
            <li class="nav-item mr-2 mb-1 mb-md-0">
                <a class="nav-link font-weight-bold px-3 py-2 {{ $statusTab === 'active' ? 'active shadow-sm bg-success text-white' : 'text-dark bg-light' }}" 
                   href="{{ route('warehouse.products', array_merge(request()->except(['page', 'status']), ['status' => 'active'])) }}">
                    <i class="fas fa-check-circle mr-1 {{ $statusTab === 'active' ? 'text-white' : 'text-success' }}"></i> Produk Aktif 
                    <span class="badge {{ $statusTab === 'active' ? 'badge-light text-success' : 'badge-success' }} ml-1 font-weight-bold">{{ $countActive }}</span>
                </a>
            </li>
            <li class="nav-item mb-1 mb-md-0">
                <a class="nav-link font-weight-bold px-3 py-2 {{ $statusTab === 'inactive' ? 'active shadow-sm bg-secondary text-white' : 'text-dark bg-light' }}" 
                   href="{{ route('warehouse.products', array_merge(request()->except(['page', 'status']), ['status' => 'inactive'])) }}">
                    <i class="fas fa-eye-slash mr-1 {{ $statusTab === 'inactive' ? 'text-white' : 'text-danger' }}"></i> Tidak Aktif (Nonaktif) 
                    <span class="badge {{ $statusTab === 'inactive' ? 'badge-light text-dark' : 'badge-danger' }} ml-1 font-weight-bold">{{ $countInactive }}</span>
                </a>
            </li>
        </ul>
        <div class="text-muted text-xs font-weight-semibold mt-2 mt-md-0">
            @if($statusTab === 'inactive')
                <span class="badge badge-warning px-2 py-1"><i class="fas fa-info-circle mr-1"></i> Produk tidak aktif otomatis disembunyikan & diarsipkan di Shopee</span>
            @endif
        </div>
    </div>
</div>

<!-- Products Table Card -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark" style="font-size: 13px;">
            <i class="fas fa-boxes text-primary mr-1"></i> Daftar SKU & Stok ({{ $products->total() }} Produk)
        </span>
    </div>
    <div class="card-body p-0 table-responsive">
        @if($products->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-2 text-secondary"></i>
                @if($statusTab === 'inactive')
                    <h6>Tidak ada produk yang Tidak Aktif</h6>
                    <p class="mb-3 text-sm">Semua produk saat ini berstatus aktif.</p>
                @elseif($statusTab === 'active')
                    <h6>Tidak ada produk aktif</h6>
                    <p class="mb-3 text-sm">Silakan tambahkan produk baru atau aktifkan produk dari tab Tidak Aktif.</p>
                @else
                    <h6>Belum ada produk yang ditemukan</h6>
                    <p class="mb-3 text-sm">Silakan tambahkan produk baru atau ubah kata kunci pencarian.</p>
                @endif
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addProductModal">
                    <i class="fas fa-plus mr-1"></i> Tambah Produk
                </button>
            </div>
        @else
            <table class="table table-hover table-striped mb-0 text-sm align-middle table-mobile-responsive">
                <thead class="bg-light">
                    <tr>
                        <th>SKU & Info</th>
                        <th>Nama Produk</th>
                        <th class="text-center">Status</th>
                        <th class="text-right d-none d-sm-table-cell">Harga Jual</th>
                        <th class="text-center">Stok Gudang</th>
                        <th class="text-center">Shopee</th>
                        <th class="text-center" style="width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr class="{{ $product->status === 'inactive' ? 'table-secondary opacity-75' : '' }}">
                            <td>
                                <span class="font-weight-bold text-dark">{{ $product->sku }}</span>
                                <br><span class="badge badge-light border">{{ $product->category }}</span>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark {{ $product->status === 'inactive' ? 'text-muted' : '' }}">
                                    {{ $product->name }}
                                </div>
                                <small class="text-muted d-sm-none">Rp {{ number_format($product->selling_price, 0, ',', '.') }} &bull; </small>
                                <small class="text-muted">Min: {{ $product->safety_stock }} {{ $product->unit }}</small>
                            </td>
                            <td class="text-center">
                                @if($product->status === 'inactive')
                                    <span class="badge badge-secondary px-2 py-1 font-weight-bold shadow-sm" title="Produk Nonaktif & Diarsipkan di Shopee">
                                        <i class="fas fa-eye-slash mr-1 text-warning"></i> Tidak Aktif
                                    </span>
                                @else
                                    <span class="badge badge-success px-2 py-1 font-weight-bold shadow-sm" title="Produk Aktif & Tampil">
                                        <i class="fas fa-check-circle mr-1"></i> Aktif
                                    </span>
                                @endif
                            </td>
                            <td class="text-right d-none d-sm-table-cell">
                                <span class="font-weight-semibold">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</span>
                                <br><small class="text-muted">Beli: Rp {{ number_format($product->purchase_price, 0, ',', '.') }}</small>
                            </td>
                            <td class="text-center">
                                @if($product->stock <= 0)
                                    <span class="badge badge-danger px-2 py-1 font-weight-bold">0 {{ $product->unit }} (HABIS)</span>
                                @elseif($product->isLowStock())
                                    <span class="badge badge-warning px-2 py-1 font-weight-bold text-dark">{{ $product->stock }} {{ $product->unit }}</span>
                                @else
                                    <span class="badge badge-success px-2 py-1 font-weight-bold">{{ $product->stock }} {{ $product->unit }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($product->isConnectedToShopee())
                                    <span class="badge badge-shopee px-2 py-1" style="font-size: 11px;">
                                        ID: {{ $product->shopee_item_id }}
                                    </span>
                                    @if($product->status === 'inactive')
                                        <br><small class="badge badge-light border text-danger" style="font-size: 10px;">Diarsipkan Shopee</small>
                                    @endif
                                @else
                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="modal" data-target="#mapShopeeModal{{ $product->id }}">
                                        <i class="fas fa-link mr-1"></i> Hubungkan
                                    </button>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <!-- Quick Toggle Active / Inactive Button -->
                                    <form action="{{ route('warehouse.products.toggle-status', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $product->status === 'active' ? 'Nonaktifkan dan sembunyikan produk ini dari Shopee?' : 'Aktifkan kembali dan tampilkan produk ini di Shopee?' }}')">
                                        @csrf
                                        @method('PATCH')
                                        @if($product->status === 'inactive')
                                            <button type="submit" class="btn btn-xs btn-outline-success mr-1" title="Aktifkan Kembali Produk (Tampilkan di Shopee)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-xs btn-outline-secondary mr-1" title="Nonaktifkan Produk (Arsipkan di Shopee)">
                                                <i class="fas fa-eye-slash"></i>
                                            </button>
                                        @endif
                                    </form>

                                    <button type="button" class="btn btn-xs btn-outline-info mr-1" data-toggle="modal" data-target="#editProductModal{{ $product->id }}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="{{ route('warehouse.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus produk {{ $product->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Product Modal -->
                        <div class="modal fade" id="editProductModal{{ $product->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content border-0 shadow">
                                    <form action="{{ route('warehouse.products.update', $product) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header bg-dark text-white py-2">
                                            <h6 class="modal-title font-weight-bold">Edit Produk: {{ $product->sku }}</h6>
                                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body p-3">
                                            <div class="form-group">
                                                <label>Nama Produk <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control form-control-sm" value="{{ $product->name }}" required>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-6">
                                                    <label>SKU <span class="text-danger">*</span></label>
                                                    <input type="text" name="sku" class="form-control form-control-sm" value="{{ $product->sku }}" required>
                                                </div>
                                                <div class="form-group col-6">
                                                    <label>Barcode</label>
                                                    <input type="text" name="barcode" class="form-control form-control-sm" value="{{ $product->barcode }}">
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-6">
                                                    <label>Kategori</label>
                                                    <input type="text" name="category" class="form-control form-control-sm" value="{{ $product->category }}">
                                                </div>
                                                <div class="form-group col-6">
                                                    <label>Satuan (Unit)</label>
                                                    <input type="text" name="unit" class="form-control form-control-sm" value="{{ $product->unit }}" required>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-6">
                                                    <label>Harga Beli (Rp)</label>
                                                    <input type="number" name="purchase_price" class="form-control form-control-sm" value="{{ (int)$product->purchase_price }}" required>
                                                </div>
                                                <div class="form-group col-6">
                                                    <label>Harga Jual (Rp)</label>
                                                    <input type="number" name="selling_price" class="form-control form-control-sm" value="{{ (int)$product->selling_price }}" required>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-6">
                                                    <label class="font-weight-bold text-primary">Stok Fisik Gudang <span class="text-danger">*</span></label>
                                                    <input type="number" name="stock" class="form-control form-control-sm font-weight-bold" value="{{ $product->stock }}" min="0" required>
                                                    <small class="text-muted" style="font-size: 11px;">Otomatis update ke Shopee API</small>
                                                </div>
                                                <div class="form-group col-6">
                                                    <label>Safety Stock (Batas Minimum)</label>
                                                    <input type="number" name="safety_stock" class="form-control form-control-sm" value="{{ $product->safety_stock }}" min="0">
                                                    <small class="text-muted" style="font-size: 11px;">Peringatan jika stok menipis</small>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-6">
                                                    <label>Shopee Item ID</label>
                                                    <input type="number" name="shopee_item_id" class="form-control form-control-sm" value="{{ $product->shopee_item_id }}">
                                                </div>
                                                <div class="form-group col-6">
                                                    <label>Status</label>
                                                    <select name="status" class="form-control form-control-sm">
                                                        <option value="active" {{ $product->status === 'active' ? 'selected' : '' }}>Aktif</option>
                                                        <option value="inactive" {{ $product->status === 'inactive' ? 'selected' : '' }}>Non-Aktif</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light py-2">
                                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Map Shopee Modal -->
                        <div class="modal fade" id="mapShopeeModal{{ $product->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content border-0 shadow">
                                    <form action="{{ route('shopee.map', $product) }}" method="POST">
                                        @csrf
                                        <div class="modal-header bg-shopee text-white py-2">
                                            <h6 class="modal-title font-weight-bold">
                                                <i class="fas fa-link mr-1"></i> Hubungkan ke Shopee
                                            </h6>
                                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body p-3">
                                            <p class="text-muted text-xs">Hubungkan <strong>[{{ $product->sku }}] {{ $product->name }}</strong> dengan Item ID Shopee.</p>
                                            <div class="form-group">
                                                <label class="text-sm">Shopee Item ID <span class="text-danger">*</span></label>
                                                <input type="number" name="shopee_item_id" class="form-control form-control-sm" placeholder="Contoh: 23145678901" required>
                                            </div>
                                            <div class="form-group">
                                                <label class="text-sm">Shopee Model ID (Opsional jika variasi)</label>
                                                <input type="number" name="shopee_model_id" class="form-control form-control-sm" placeholder="Kosongkan jika bukan variasi">
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light py-2">
                                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-shopee btn-sm font-weight-bold">Hubungkan & Sync</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if($products->hasPages())
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Total: {{ $products->total() }} SKU</small>
            {{ $products->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('warehouse.products.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title font-weight-bold" id="addProductModalLabel">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Produk Baru
                    </h6>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3">
                    <div class="form-group">
                        <label class="font-weight-semibold text-sm">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Contoh: Gamis Medina Silk Premium" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">SKU <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control form-control-sm" placeholder="MDN-GMS-001" required>
                        </div>
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">Barcode</label>
                            <input type="text" name="barcode" class="form-control form-control-sm" placeholder="899123456789">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">Kategori</label>
                            <input type="text" name="category" class="form-control form-control-sm" placeholder="Fashion Muslim">
                        </div>
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control form-control-sm" value="Pcs" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">Harga Beli (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="purchase_price" class="form-control form-control-sm" placeholder="100000" required min="0">
                        </div>
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">Harga Jual (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="selling_price" class="form-control form-control-sm" placeholder="175000" required min="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">Stok Awal Fisik</label>
                            <input type="number" name="initial_stock" class="form-control form-control-sm" value="0" min="0">
                        </div>
                        <div class="form-group col-6">
                            <label class="font-weight-semibold text-sm">Safety Stock</label>
                            <input type="number" name="safety_stock" class="form-control form-control-sm" value="5" min="0">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-semibold text-sm">Shopee Item ID (Opsional)</label>
                        <input type="number" name="shopee_item_id" class="form-control form-control-sm" placeholder="Contoh: 25412984712">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
