@extends('layouts.adminlte')

@section('title', 'Katalog & Stok Gudang Utama')
@section('page_title', 'Katalog Produk & Stok Gudang')

@section('page_actions')
    <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-toggle="modal" data-target="#addProductModal">
        <i class="fas fa-plus mr-1"></i> Tambah Produk
    </button>
@endsection

@section('content')

<!-- Search & Filter Card -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('warehouse.products') }}" method="GET">
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
                <h6>Belum ada produk yang ditemukan</h6>
                <p class="mb-3 text-sm">Silakan tambahkan produk baru atau ubah kata kunci pencarian.</p>
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
                        <th class="text-right d-none d-sm-table-cell">Harga Jual</th>
                        <th class="text-center">Stok Gudang</th>
                        <th class="text-center">Shopee</th>
                        <th class="text-center" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td>
                                <span class="font-weight-bold text-dark">{{ $product->sku }}</span>
                                <br><span class="badge badge-light border">{{ $product->category }}</span>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $product->name }}</div>
                                <small class="text-muted d-sm-none">Rp {{ number_format($product->selling_price, 0, ',', '.') }} &bull; </small>
                                <small class="text-muted">Min: {{ $product->safety_stock }} {{ $product->unit }}</small>
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
                                        <i class="fas fa-check mr-1"></i> ID: {{ $product->shopee_item_id }}
                                    </span>
                                @else
                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="modal" data-target="#mapShopeeModal{{ $product->id }}">
                                        <i class="fas fa-link mr-1"></i> Link
                                    </button>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-xs btn-outline-info" data-toggle="modal" data-target="#editProductModal{{ $product->id }}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @if($product->isConnectedToShopee())
                                        <form action="{{ route('shopee.sync.product', $product) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-warning" title="Push Stok">
                                                <i class="fas fa-sync"></i>
                                            </button>
                                        </form>
                                    @endif
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
                                                    <label>Safety Stock</label>
                                                    <input type="number" name="safety_stock" class="form-control form-control-sm" value="{{ $product->safety_stock }}" min="0">
                                                </div>
                                                <div class="form-group col-6">
                                                    <label>Shopee Item ID</label>
                                                    <input type="number" name="shopee_item_id" class="form-control form-control-sm" value="{{ $product->shopee_item_id }}">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select name="status" class="form-control form-control-sm">
                                                    <option value="active" {{ $product->status === 'active' ? 'selected' : '' }}>Aktif</option>
                                                    <option value="inactive" {{ $product->status === 'inactive' ? 'selected' : '' }}>Non-Aktif</option>
                                                </select>
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
