@extends('layouts.adminlte')

@section('title', $role->exists ? 'Edit Role & Akses Menu: ' . $role->name : 'Tambah Role Baru')
@section('page_title', $role->exists ? 'Pengaturan Role & Hak Akses Menu' : 'Tambah Role Baru')

@section('page_actions')
    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Role
    </a>
@endsection

@push('styles')
<style>
    .perm-card {
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        background: #ffffff;
        padding: 14px 16px;
        transition: all 0.2s ease-in-out;
        cursor: pointer;
        user-select: none;
        position: relative;
        display: flex;
        align-items: flex-start;
    }
    .perm-card:hover {
        border-color: #93c5fd;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
        transform: translateY(-1px);
    }
    .perm-card.is-active {
        border-color: #2563eb;
        background-color: #f0f7ff;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.12);
    }
    .perm-card .custom-control-input {
        margin-top: 3px;
        cursor: pointer;
    }
    .perm-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
        margin-right: 12px;
        background: #f1f5f9;
        color: #475569;
        transition: all 0.2s ease;
    }
    .perm-card.is-active .perm-icon-box {
        background: #2563eb;
        color: #ffffff;
    }
    .category-section {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
</style>
@endpush

@section('content')
<form action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" method="POST">
    @csrf
    @if($role->exists)
        @method('PUT')
    @endif

    <div class="row">
        <!-- Role Details Column (Left on Desktop, Top on Mobile) -->
        <div class="col-lg-4 col-12 mb-4">
            <div class="card card-outline card-primary shadow-sm sticky-top" style="top: 75px; z-index: 10;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="font-weight-bold text-dark mb-0 d-flex align-items-center">
                        <i class="fas fa-shield-alt text-primary mr-2"></i>
                        {{ $role->exists ? 'Edit Data Role' : 'Informasi Role Baru' }}
                    </h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark mb-1" style="font-size: 13px;">
                            Nama Role <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $role->name) }}" placeholder="Contoh: Supervisor Gudang" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark mb-1" style="font-size: 13px;">
                            Kode Slug Role
                        </label>
                        <input type="text" name="slug" class="form-control font-monospace" value="{{ old('slug', $role->slug) }}" placeholder="Contoh: supervisor_gudang" {{ $role->slug === 'super_admin' ? 'readonly' : '' }}>
                        <small class="text-muted d-block mt-1" style="font-size: 11.5px;">Otomatis dibuat dari nama jika dikosongkan.</small>
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-weight-bold text-dark mb-1" style="font-size: 13px;">
                            Deskripsi Wewenang & Tanggung Jawab
                        </label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Deskripsikan cakupan tugas role ini...">{{ old('description', $role->description) }}</textarea>
                    </div>

                    @if($role->slug === 'super_admin')
                        <div class="alert alert-warning py-2 px-3 mb-4 rounded" style="font-size: 12.5px;">
                            <i class="fas fa-crown mr-1"></i> <strong>Super Admin</strong> memiliki akses penuh ke semua modul sistem secara default.
                        </div>
                    @endif

                    <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                        <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary font-weight-semibold">Batal</a>
                        <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm">
                            <i class="fas fa-save mr-1"></i> {{ $role->exists ? 'Simpan Perubahan' : 'Simpan Role' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Permissions Matrix Column (Right on Desktop) -->
        <div class="col-lg-8 col-12">
            <div class="card card-outline card-success shadow-sm mb-4">
                <!-- Clean Header -->
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                        <div class="mb-2 mb-sm-0">
                            <h5 class="font-weight-bold text-dark mb-1 d-flex align-items-center" style="font-size: 16px;">
                                <i class="fas fa-list-check text-success mr-2"></i> Pengaturan Hak Akses Menu
                            </h5>
                            <p class="text-muted mb-0" style="font-size: 12.5px;">
                                Centang menu-menu yang diizinkan untuk dibuka oleh pengguna dengan role ini.
                            </p>
                        </div>
                        @if($role->slug !== 'super_admin')
                            <div class="btn-group btn-group-sm flex-shrink-0">
                                <button type="button" class="btn btn-primary font-weight-bold px-3 shadow-xs" onclick="checkAll(true)">
                                    <i class="fas fa-check-square mr-1"></i> Pilih Semua
                                </button>
                                <button type="button" class="btn btn-outline-secondary px-3" onclick="checkAll(false)">
                                    <i class="fas fa-square mr-1"></i> Batal Semua
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Matrix Body -->
                <div class="card-body p-3 p-md-4" style="background-color: #f8fafc;">
                    @php
                        $currentPermissions = old('permissions', $role->permissions ?? []);
                        $isSuperAdmin = $role->slug === 'super_admin';
                    @endphp

                    @foreach($systemMenus as $categoryName => $menus)
                        @php
                            $catSlug = Str::slug($categoryName);
                        @endphp
                        <div class="category-section mb-4 p-3 p-md-4">
                            <!-- Category Header -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-3 border-bottom">
                                <div class="d-flex align-items-center">
                                    <span class="font-weight-bold text-dark" style="font-size: 14.5px;">
                                        <i class="fas fa-folder-open text-primary mr-2"></i> {{ $categoryName }}
                                    </span>
                                    <span class="badge badge-light border ml-2 text-muted" style="font-size: 11px;">
                                        {{ count($menus) }} Menu
                                    </span>
                                </div>
                                @if(! $isSuperAdmin)
                                    <div class="mt-1 mt-sm-0">
                                        <button type="button" class="btn btn-xs btn-outline-primary mr-1" onclick="toggleCategory('{{ $catSlug }}', true)">
                                            Pilih Kategori
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleCategory('{{ $catSlug }}', false)">
                                            Batal
                                        </button>
                                    </div>
                                @endif
                            </div>

                            <!-- Menu Items Cards Grid -->
                            <div class="row">
                                @foreach($menus as $menuKey => $menuData)
                                    @php
                                        $isChecked = $isSuperAdmin || in_array($menuKey, $currentPermissions, true);
                                    @endphp
                                    <div class="col-md-6 col-12 mb-3">
                                        <div class="perm-card {{ $isChecked ? 'is-active' : '' }}" onclick="cardClick(this, event)">
                                            <div class="perm-icon-box">
                                                <i class="{{ $menuData['icon'] }}"></i>
                                            </div>
                                            <div class="flex-grow-1 pr-2">
                                                <div class="font-weight-bold text-dark" style="font-size: 13.5px; line-height: 1.3;">
                                                    {{ $menuData['name'] }}
                                                </div>
                                                <div class="text-muted mt-1" style="font-size: 11.5px; line-height: 1.35;">
                                                    {{ $menuData['description'] }}
                                                </div>
                                            </div>
                                            <div class="custom-control custom-checkbox mt-1">
                                                <input type="checkbox" 
                                                       class="custom-control-input perm-checkbox group-{{ $catSlug }}" 
                                                       id="perm_{{ $menuKey }}" 
                                                       name="permissions[]" 
                                                       value="{{ $menuKey }}" 
                                                       {{ $isChecked ? 'checked' : '' }}
                                                       {{ $isSuperAdmin ? 'disabled' : '' }}>
                                                <label class="custom-control-label" for="perm_{{ $menuKey }}"></label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function updateCardState(checkbox) {
        const card = checkbox.closest('.perm-card');
        if (card) {
            if (checkbox.checked) {
                card.classList.add('is-active');
            } else {
                card.classList.remove('is-active');
            }
        }
    }

    function cardClick(card, event) {
        // If clicked directly on the checkbox or label, let default handler work
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'LABEL') {
            return;
        }
        const checkbox = card.querySelector('.perm-checkbox');
        if (checkbox && !checkbox.disabled) {
            checkbox.checked = !checkbox.checked;
            updateCardState(checkbox);
        }
    }

    function checkAll(state) {
        document.querySelectorAll('.perm-checkbox:not(:disabled)').forEach(cb => {
            cb.checked = state;
            updateCardState(cb);
        });
    }

    function toggleCategory(catSlug, state) {
        document.querySelectorAll('.group-' + catSlug + ':not(:disabled)').forEach(cb => {
            cb.checked = state;
            updateCardState(cb);
        });
    }

    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            updateCardState(this);
        });
    });
</script>
@endpush
