@extends('layouts.adminlte')

@section('title', 'Dashboard Gudang Utama')
@section('page_title', 'Dashboard Gudang Utama')

@section('page_actions')
    <div class="btn-group d-none d-sm-inline-flex">
        <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-toggle="modal" data-target="#quickMutationModal">
            <i class="fas fa-plus mr-1"></i> Catat Mutasi
        </button>
        <button type="button" class="btn btn-shopee btn-sm ml-1" data-toggle="modal" data-target="#simulatorModal">
            <i class="fas fa-bolt mr-1"></i> Test Order Shopee
        </button>
    </div>
@endsection

@section('content')

<!-- Top Stats Grid -->
<div class="row">
    <!-- Total SKU -->
    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Total SKU</p>
                <h3 class="font-weight-bold text-dark mb-0" style="font-size: 1.4rem;">{{ number_format($totalProducts) }}</h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-boxes text-primary fa-2x"></i>
            </div>
            <a href="{{ route('warehouse.products') }}" class="small-box-footer mt-1 d-block font-weight-bold" style="font-size: 11px;">
                Lihat Katalog &rarr;
            </a>
        </div>
    </div>

    <!-- Total Fisik Stok Gudang -->
    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Stok Gudang</p>
                <h3 class="font-weight-bold text-primary mb-0" style="font-size: 1.4rem;">
                    {{ number_format($totalStock) }} <span style="font-size: 11px; font-weight: normal;" class="text-muted">Unit</span>
                </h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-cubes text-info fa-2x"></i>
            </div>
            <a href="{{ route('warehouse.mutations') }}" class="small-box-footer mt-1 d-block font-weight-bold" style="font-size: 11px;">
                Riwayat Mutasi &rarr;
            </a>
        </div>
    </div>

    <!-- Nilai Total Aset -->
    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Estimasi Aset</p>
                <h3 class="font-weight-bold text-success mb-0" style="font-size: 1.15rem; line-height: 1.2;">
                    Rp {{ number_format($totalAssetValue, 0, ',', '.') }}
                </h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-wallet text-success fa-2x"></i>
            </div>
            <div class="small-box-footer text-muted mt-1" style="font-size: 10px;">
                Harga HPP
            </div>
        </div>
    </div>

    <!-- Stok Kritis Alert -->
    <div class="col-6 col-lg-3 col-md-6 mb-2 mb-md-3">
        <div class="small-box bg-white p-2 p-md-3 border h-100 mb-0 {{ $lowStockCount > 0 ? 'border-danger' : '' }}">
            <div class="inner">
                <p class="text-muted text-uppercase mb-1 font-weight-bold" style="font-size: 10px;">Stok Kritis</p>
                <h3 class="font-weight-bold {{ $lowStockCount > 0 ? 'text-danger' : 'text-success' }} mb-0" style="font-size: 1.4rem;">
                    {{ $lowStockCount }} <span style="font-size: 11px; font-weight: normal;" class="text-muted">SKU</span>
                </h3>
            </div>
            <div class="icon d-none d-sm-block" style="top: 10px; right: 12px; opacity: 0.12; position: absolute;">
                <i class="fas fa-triangle-exclamation text-danger fa-2x"></i>
            </div>
            <a href="{{ route('warehouse.products', ['stock_status' => 'low']) }}" class="small-box-footer text-danger mt-1 d-block font-weight-bold" style="font-size: 11px;">
                Cek SKU &rarr;
            </a>
        </div>
    </div>
</div>

@if($latestAiAnalysis && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('ai_advisor')))
<!-- AI Executive Intelligence & Daily Action Steps Banner -->
<div class="card mb-3 border-0 shadow-sm overflow-hidden" style="border-radius: 12px; background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%); border-left: 5px solid #8b5cf6 !important; border-top: 1px solid #e9d5ff; border-right: 1px solid #e9d5ff; border-bottom: 1px solid #e9d5ff;">
    <div class="card-body p-3 p-md-4">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 pb-2 border-bottom" style="border-color: rgba(139, 92, 246, 0.15) !important;">
            <div class="d-flex align-items-center mb-2 mb-md-0">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white mr-2 mr-md-3 shadow-xs flex-shrink-0" style="width: 42px; height: 42px; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); font-size: 18px;">
                    <i class="fas fa-brain"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center flex-wrap">
                        <h5 class="font-weight-bold mb-0 mr-2 text-dark" style="font-size: 15px;">
                            AI Executive Summary & Rekomendasi Hari Ini
                        </h5>
                        <span class="badge badge-purple text-white px-2 py-1" style="font-size: 10px;">
                            <i class="fas fa-robot mr-1"></i> Asisten Penjualan Medina
                        </span>
                    </div>
                    <div class="text-muted" style="font-size: 11.5px;">
                        <i class="far fa-clock mr-1"></i> Analisis: <strong>{{ $latestAiAnalysis->created_at->diffForHumans() }}</strong> ({{ $latestAiAnalysis->created_at->translatedFormat('d M Y, H:i') }})
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex align-items-center flex-wrap">
                <button type="button" id="btnQuickRegenerateAi" class="btn btn-xs btn-outline-purple mr-2 font-weight-bold shadow-xs">
                    <i class="fas fa-sync-alt mr-1" id="dashAiSpinner"></i> Analisis Ulang AI
                </button>
                <a href="{{ route('ai.index') }}" class="btn btn-xs btn-purple font-weight-bold shadow-sm">
                    <i class="fas fa-comments mr-1"></i> Konsultasi / Chat AI &rarr;
                </a>
            </div>
        </div>

        <!-- Body: Summary & Action Checklist -->
        <div class="row">
            <!-- Left: Executive Summary -->
            <div class="col-lg-7 col-12 mb-3 mb-lg-0">
                <div class="p-3 bg-white rounded border shadow-xs h-100" style="border-color: #f1f5f9 !important;">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge badge-light border text-purple font-weight-bold mr-2" style="font-size: 11px;">
                            <i class="fas fa-chart-line mr-1"></i> Kinerja & Tren Toko
                        </span>
                        @if(!empty($latestAiAnalysis->raw_metrics['sales']['top_selling_skus']))
                            <div class="text-truncate text-muted" style="font-size: 11px;">
                                Best Seller: <strong>{{ array_key_first($latestAiAnalysis->raw_metrics['sales']['top_selling_skus']) }}</strong>
                            </div>
                        @endif
                    </div>
                    <p class="text-dark mb-2" style="font-size: 13px; line-height: 1.6;">
                        {{ Str::limit($latestAiAnalysis->summary, 320) }}
                    </p>

                    @if(!empty($latestAiAnalysis->marketing_advice[0]))
                        <div class="p-2 rounded mt-2" style="background-color: #fdf4ff; border: 1px dashed #d946ef;">
                            <div class="d-flex align-items-center text-dark font-weight-bold" style="font-size: 12px;">
                                <i class="fas fa-bullhorn text-warning mr-1"></i> Saran Pemasaran: {{ $latestAiAnalysis->marketing_advice[0]['title'] }}
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 11px; line-height: 1.3;">
                                {{ $latestAiAnalysis->marketing_advice[0]['description'] }}
                            </small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right: Actionable Steps Checklist -->
            <div class="col-lg-5 col-12">
                <div class="p-3 bg-white rounded border shadow-xs h-100" style="border-color: #f1f5f9 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="font-weight-bold text-dark" style="font-size: 12.5px;">
                            <i class="fas fa-tasks text-success mr-1"></i> Langkah Prioritas Hari Ini
                        </span>
                        <span class="badge badge-success" style="font-size: 9.5px;">Checklist Aksi</span>
                    </div>

                    <div class="daily-action-steps" style="display: flex; flex-direction: column; gap: 6px;">
                        @if(!empty($latestAiAnalysis->actionable_steps))
                            @foreach(array_slice($latestAiAnalysis->actionable_steps, 0, 3) as $step)
                                <div class="d-flex align-items-start p-2 rounded" style="background-color: #f8fafc; border-left: 3px solid #10b981;">
                                    <span class="badge badge-success rounded-circle mr-2 mt-1" style="width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px; flex-shrink: 0;">
                                        {{ $step['step'] ?? $loop->iteration }}
                                    </span>
                                    <div class="flex-grow-1" style="font-size: 12px; line-height: 1.35;">
                                        <div class="font-weight-bold text-dark">{{ $step['task'] ?? '' }}</div>
                                        <div class="text-muted mt-1" style="font-size: 10.5px;">
                                            <span class="badge badge-light border">{{ $step['category'] ?? 'Umum' }}</span>
                                            @if(!empty($step['target_sku']))
                                                &bull; Target: <strong class="text-primary">{{ $step['target_sku'] }}</strong>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted text-center py-2" style="font-size: 12px;">
                                Tidak ada tindakan mendesak hari ini.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Shopee Connection & Sync Status Banner -->
<div class="card mb-3 border-0 bg-white shadow-sm overflow-hidden" style="border-left: 4px solid var(--shopee-orange) !important;">
    <div class="card-body p-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center mb-2 mb-md-0">
                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center mr-2 mr-md-3 flex-shrink-0" style="background-color: rgba(238, 77, 45, 0.1); width: 40px; height: 40px;">
                    <i class="fas fa-store" style="color: var(--shopee-orange)"></i>
                </div>
                <div>
                    <div class="d-flex flex-wrap align-items-center mb-1">
                        <strong class="text-dark mr-2" style="font-size: 13px;">Shopee Open API:</strong>
                        @if($shopeeSetting->isConnected())
                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Terhubung ({{ ucfirst($shopeeSetting->environment) }})</span>
                        @else
                            <span class="badge badge-info px-2 py-1"><i class="fas fa-vial mr-1"></i> Sandbox / Simulasi</span>
                        @endif
                    </div>
                    <div class="text-muted" style="font-size: 11px;">
                        Toko: <strong>{{ $shopeeSetting->shop_name }}</strong> &bull;
                        Auto-Deduct: <strong class="text-success">Aktif</strong> &bull;
                        Terhubung: <strong>{{ $shopeeConnectedCount }} SKU</strong>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center mt-2 mt-md-0">
                <form action="{{ route('shopee.sync.all') }}" method="POST" class="mr-2 flex-fill flex-md-grow-0">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-outline-secondary btn-block">
                        <i class="fas fa-sync-alt mr-1"></i> Push Stok
                    </button>
                </form>
                <a href="{{ route('shopee.dashboard') }}" class="btn btn-xs btn-shopee flex-fill flex-md-grow-0">
                    <i class="fas fa-external-link-alt mr-1"></i> Channel Shopee
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Fast Actions -->
<div class="row">
    <!-- Chart: Tren Keluar Masuk & Penjualan Shopee 7 Hari -->
    <div class="col-lg-8 col-12 mb-3">
        <div class="card shadow-sm border-0 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="font-weight-bold text-dark" style="font-size: 13px;">
                    <i class="fas fa-chart-line text-primary mr-1"></i> Arus Stok & Shopee (7 Hari)
                </span>
                <span class="badge badge-light border d-none d-sm-inline">Real-time</span>
            </div>
            <div class="card-body p-2 p-md-3">
                <div style="position: relative; height: 230px; width: 100%;">
                    <canvas id="mutationTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Mutation Panel & Shortcuts -->
    <div class="col-lg-4 col-12 mb-3">
        <div class="card shadow-sm border-0 mb-0">
            <div class="card-header py-2">
                <span class="font-weight-bold text-dark" style="font-size: 13px;">
                    <i class="fas fa-bolt text-warning mr-1"></i> Aksi Cepat
                </span>
            </div>
            <div class="card-body p-2">
                <div class="list-group list-group-flush">
                    <a href="{{ route('packing.index') }}" class="list-group-item list-group-item-action d-flex align-items-center border-0 px-2 py-2 rounded mb-1 bg-light">
                        <div class="rounded-circle bg-danger text-white p-2 mr-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="fas fa-video"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold text-dark" style="font-size: 13px;">Stasiun Packing Video</div>
                            <small class="text-muted">Scan resi & rekam video packing</small>
                        </div>
                    </a>

                    <a href="#" class="list-group-item list-group-item-action d-flex align-items-center border-0 px-2 py-2 rounded mb-1 bg-light" data-toggle="modal" data-target="#quickMutationModal">
                        <div class="rounded-circle bg-success text-white p-2 mr-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold text-dark" style="font-size: 13px;">Barang Masuk (Inbound)</div>
                            <small class="text-muted">Tambah stok penerimaan barang</small>
                        </div>
                    </a>

                    <a href="#" class="list-group-item list-group-item-action d-flex align-items-center border-0 px-2 py-2 rounded bg-light" data-toggle="modal" data-target="#simulatorModal">
                        <div class="rounded-circle bg-shopee text-white p-2 mr-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold text-dark" style="font-size: 13px;">Simulasi Order Shopee</div>
                            <small class="text-muted">Tes otomatis potong stok gudang</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Section: Low Stock Warning & Recent Mutations -->
<div class="row">
    <!-- Low Stock Alert Box -->
    <div class="col-lg-5 col-12 mb-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-danger" style="font-size: 13px;">
                    <i class="fas fa-exclamation-circle mr-1"></i> Stok Menipis / Kritis
                </span>
                <a href="{{ route('warehouse.products', ['stock_status' => 'low']) }}" class="btn btn-xs btn-outline-danger">Semua</a>
            </div>
            <div class="card-body p-0 table-responsive">
                @if($lowStockProducts->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-shield-alt text-success fa-2x mb-1"></i>
                        <p class="mb-0" style="font-size: 12px;">Semua stok produk aman.</p>
                    </div>
                @else
                    <table class="table table-hover table-striped mb-0 text-sm table-mobile-responsive">
                        <thead class="bg-light">
                            <tr>
                                <th>Produk</th>
                                <th class="text-center">Stok</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowStockProducts as $lp)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold text-dark">{{ $lp->name }}</div>
                                        <small class="text-muted">{{ $lp->sku }} (Min: {{ $lp->safety_stock }})</small>
                                    </td>
                                    <td class="text-center font-weight-bold text-danger">
                                        {{ $lp->stock }} {{ $lp->unit }}
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-xs btn-success font-weight-bold" data-toggle="modal" data-target="#quickMutationModal">
                                            + Restock
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Stock Mutations Table -->
    <div class="col-lg-7 col-12 mb-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-dark" style="font-size: 13px;">
                    <i class="fas fa-history text-primary mr-1"></i> Mutasi Stok Terakhir
                </span>
                <a href="{{ route('warehouse.mutations') }}" class="btn btn-xs btn-outline-primary">Semua</a>
            </div>
            <div class="card-body p-0 table-responsive">
                @if($recentMutations->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-2x mb-1"></i>
                        <p class="mb-0" style="font-size: 12px;">Belum ada catatan mutasi stok.</p>
                    </div>
                @else
                    <table class="table table-hover mb-0 text-sm table-mobile-responsive">
                        <thead class="bg-light">
                            <tr>
                                <th>Waktu & Ref</th>
                                <th>Produk</th>
                                <th>Jenis</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentMutations as $mut)
                                <tr>
                                    <td>
                                        <span class="font-weight-semibold text-dark">{{ $mut->reference_no }}</span>
                                        <br><small class="text-muted">{{ $mut->created_at->translatedFormat('d M H:i') }}</small>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ $mut->product->name ?? 'Produk Dihapus' }}</span>
                                        <br><small class="text-muted">{{ $mut->product->sku ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $mut->badge_class }}">
                                            @if($mut->type === 'shopee_sale')
                                                <i class="fas fa-shopping-bag mr-1"></i>
                                            @endif
                                            {{ $mut->type_label }}
                                        </span>
                                    </td>
                                    <td class="text-center font-weight-bold {{ $mut->qty > 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $mut->qty > 0 ? '+' . $mut->qty : $mut->qty }}
                                    </td>
                                    <td class="text-right font-weight-bold text-dark">
                                        {{ $mut->stock_after }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('mutationTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($chartLabels),
                datasets: [
                    {
                        label: 'Masuk',
                        data: @json($chartInbound),
                        backgroundColor: 'rgba(40, 167, 69, 0.75)',
                        borderColor: '#28a745',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Keluar',
                        data: @json($chartOutbound),
                        backgroundColor: 'rgba(255, 193, 7, 0.75)',
                        borderColor: '#ffc107',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Shopee',
                        data: @json($chartShopee),
                        backgroundColor: 'rgba(238, 77, 45, 0.85)',
                        borderColor: '#ee4d2d',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 10,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // Quick AI Regenerate from Dashboard
        $('#btnQuickRegenerateAi').on('click', function() {
            const btn = $(this);
            const spinner = $('#dashAiSpinner');
            btn.prop('disabled', true);
            spinner.addClass('fa-spin');

            Swal.fire({
                title: 'Menganalisis Data Toko...',
                text: 'AI sedang mengevaluasi penjualan hari ini dan menyusun checklist aksi prioritas.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: "{{ route('ai.analyze') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                dataType: 'json',
                success: function(res) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Analisis Selesai!',
                        text: res.message || 'Analisis data hari ini berhasil diperbarui.',
                        confirmButtonColor: '#8b5cf6'
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText;
                    Swal.fire('Gagal', 'Terjadi kendala saat menganalisis: ' + msg, 'error');
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.removeClass('fa-spin');
                }
            });
        });
    });
</script>
@endpush
