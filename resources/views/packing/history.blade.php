@extends('layouts.adminlte')

@section('title', 'Galeri & Riwayat Video Packing')
@section('page_title', 'Riwayat Video Packing')

@section('page_actions')
    <a href="{{ route('packing.index') }}" class="btn btn-primary btn-sm font-weight-bold">
        <i class="fas fa-barcode mr-1"></i> Buka Stasiun Packing
    </a>
@endsection

@section('content')

<!-- Search & Filter Bar -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('packing.history') }}" method="GET">
            <div class="row no-gutters">
                <div class="col-12 col-md-4 pr-md-2 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Cari Resi, Order SN, Staf..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-6 col-md-3 pr-1 pr-md-2 mb-2 mb-md-0">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Status --</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>✅ Selesai (Ada Video)</option>
                        <option value="blocked_cancelled" {{ request('status') === 'blocked_cancelled' ? 'selected' : '' }}>🚫 Dicegah (Shopee Cancel)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 pl-1 pr-md-2 mb-2 mb-md-0">
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date') }}" onchange="this.form.submit()">
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-sm btn-secondary btn-block">Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark" style="font-size: 13px;">
            <i class="fas fa-video text-primary mr-1"></i> Arsip Video Packing ({{ $records->total() }} Log)
        </span>
    </div>
    <div class="card-body p-0 table-responsive">
        @if($records->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="fas fa-film fa-3x mb-2 text-secondary"></i>
                <h6>Belum ada rekaman video packing</h6>
                <p class="mb-3 text-sm">Rekam proses packing paket Shopee di Stasiun Packing untuk menyimpan bukti video otomatis di sini.</p>
                <a href="{{ route('packing.index') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-barcode mr-1"></i> Mulai Packing Sekarang
                </a>
            </div>
        @else
            <table class="table table-hover table-striped mb-0 text-sm align-middle table-mobile-responsive">
                <thead class="bg-light">
                    <tr>
                        <th>Waktu & Resi</th>
                        <th>Order SN & Pembeli</th>
                        <th class="d-none d-sm-table-cell">Staf</th>
                        <th class="text-center">Status</th>
                        <th class="text-center d-none d-md-table-cell">Durasi</th>
                        <th class="text-center" style="width: 100px;">Video</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $rec)
                        <tr>
                            <td>
                                <span class="badge badge-light border text-dark font-weight-bold" style="font-size: 12px;">
                                    {{ $rec->tracking_number ?: '-' }}
                                </span>
                                <br><small class="text-muted">{{ $rec->created_at->format('d/m/y H:i') }}</small>
                            </td>
                            <td>
                                <span class="font-weight-bold text-dark">{{ $rec->order_sn }}</span>
                                @if($rec->shopeeOrder)
                                    <br><small class="text-muted"><i class="fas fa-user-circle mr-1"></i> {{ $rec->shopeeOrder->buyer_username }}</small>
                                @endif
                            </td>
                            <td class="d-none d-sm-table-cell">
                                <span class="badge badge-secondary">{{ $rec->packer_name }}</span>
                            </td>
                            <td class="text-center">
                                @if($rec->status === 'completed')
                                    <span class="badge badge-success px-2 py-1">
                                        <i class="fas fa-check mr-1"></i> Selesai
                                    </span>
                                @else
                                    <span class="badge badge-danger px-2 py-1 font-weight-bold">
                                        <i class="fas fa-ban mr-1"></i> Dicegah (Cancel)
                                    </span>
                                @endif
                            </td>
                            <td class="text-center d-none d-md-table-cell font-weight-bold text-dark">
                                {{ $rec->duration_formatted }}
                            </td>
                            <td class="text-center">
                                @if($rec->hasVideo())
                                    <button type="button" class="btn btn-xs btn-danger font-weight-bold shadow-sm" onclick="openVideoPlayerModal('{{ $rec->video_url }}', '{{ $rec->tracking_number ?: $rec->order_sn }}', '{{ $rec->packer_name }}', '{{ $rec->duration_formatted }}', '{{ $rec->created_at->format('d/m/Y H:i') }}')">
                                        <i class="fas fa-play mr-1"></i> Putar
                                    </button>
                                @else
                                    <span class="text-muted text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if($records->hasPages())
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Total: {{ $records->total() }} Log</small>
            {{ $records->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>

<!-- Video Player Modal -->
<div class="modal fade" id="videoPlayerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content bg-dark text-white border-0 shadow">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title font-weight-bold" id="videoModalTitle">Bukti Video Packing</h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="stopModalVideo()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0 bg-black text-center">
                <video id="modalVideoElement" controls autoplay style="max-height: 480px; width: 100%; object-fit: contain; background: #000;"></video>
            </div>
            <div class="modal-footer border-secondary py-2 d-flex justify-content-between">
                <small class="text-muted" id="videoModalMeta"></small>
                <div>
                    <a id="videoDownloadBtn" href="#" download class="btn btn-outline-light btn-xs">
                        <i class="fas fa-download mr-1"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary btn-xs" data-dismiss="modal" onclick="stopModalVideo()">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openVideoPlayerModal(videoUrl, resi, packer, duration, time) {
        document.getElementById('videoModalTitle').innerHTML = `<i class="fas fa-video text-danger mr-1"></i> Video Packing: <strong>${resi}</strong>`;
        document.getElementById('videoModalMeta').innerHTML = `Staf: <strong>${packer}</strong> &bull; Durasi: <strong>${duration}</strong> &bull; ${time}`;
        
        const videoEl = document.getElementById('modalVideoElement');
        videoEl.src = videoUrl;
        
        document.getElementById('videoDownloadBtn').href = videoUrl;
        
        $('#videoPlayerModal').modal('show');
    }

    function stopModalVideo() {
        const videoEl = document.getElementById('modalVideoElement');
        videoEl.pause();
        videoEl.src = '';
    }

    $('#videoPlayerModal').on('hidden.bs.modal', function () {
        stopModalVideo();
    });
</script>
@endpush
