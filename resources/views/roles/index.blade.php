@extends('layouts.adminlte')

@section('title', 'Manajemen Role & Hak Akses')
@section('page_title', 'Manajemen Role & Hak Akses Menu')

@section('page_actions')
    <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm shadow-sm font-weight-bold px-3">
        <i class="fas fa-plus-circle mr-1"></i> Tambah Role Baru
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                    <div>
                        <h5 class="font-weight-bold text-dark mb-1 d-flex align-items-center">
                            <i class="fas fa-user-shield text-primary mr-2"></i> Daftar Role & Hak Akses Menu
                        </h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">Kelola hak akses modul dan navigasi untuk masing-masing tingkatan pengguna.</p>
                    </div>
                    <div class="mt-2 mt-sm-0">
                        <span class="badge badge-primary px-3 py-2" style="font-size: 12px; font-weight: 600;">
                            Total: {{ $roles->count() }} Role
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th style="min-width: 180px;">Role & Kode</th>
                            <th style="min-width: 200px;">Deskripsi / Tanggung Jawab</th>
                            <th style="width: 120px;" class="text-center">Pengguna</th>
                            <th style="min-width: 300px;">Menu yang Diizinkan</th>
                            <th style="width: 150px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $index => $role)
                            <tr>
                                <td class="text-center font-weight-bold text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="font-weight-bold text-dark" style="font-size: 14.5px;">
                                        @if($role->slug === 'super_admin')
                                            <i class="fas fa-crown text-warning mr-1"></i>
                                        @else
                                            <i class="fas fa-shield-alt text-primary mr-1"></i>
                                        @endif
                                        {{ $role->name }}
                                    </div>
                                    <span class="badge badge-secondary font-monospace mt-1" style="font-size: 10.5px;">
                                        {{ $role->slug }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted" style="font-size: 13px;">{{ $role->description ?? '-' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-pill badge-primary px-3 py-1" style="font-size: 11.5px;">
                                        <i class="fas fa-users mr-1"></i> {{ $role->users_count }} User
                                    </span>
                                </td>
                                <td>
                                    @if($role->slug === 'super_admin')
                                        <span class="badge badge-success px-2 py-1" style="font-size: 11.5px;">
                                            <i class="fas fa-check-double mr-1"></i> Akses Penuh ke Semua Menu (Super Admin)
                                        </span>
                                    @else
                                        <div class="d-flex flex-wrap" style="gap: 5px;">
                                            @php
                                                $rolePerms = $role->permissions ?? [];
                                            @endphp
                                            @if(empty($rolePerms))
                                                <span class="badge badge-warning text-dark">Belum ada menu yang diizinkan</span>
                                            @else
                                                @foreach($systemMenus as $category => $menus)
                                                    @foreach($menus as $key => $menuData)
                                                        @if(in_array($key, $rolePerms, true))
                                                            <span class="badge badge-light border text-dark px-2 py-1" title="{{ $category }}: {{ $menuData['name'] }}" style="font-size: 11px;">
                                                                <i class="{{ $menuData['icon'] }} mr-1 text-primary"></i> {{ $menuData['name'] }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('roles.edit', $role) }}" class="btn btn-outline-primary" title="Atur Hak Akses Menu">
                                            <i class="fas fa-edit mr-1"></i> Atur Menu
                                        </a>
                                        @if($role->slug !== 'super_admin')
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteRole('{{ $role->id }}', '{{ addslashes($role->name) }}', {{ $role->users_count }})" title="Hapus Role">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <form id="delete-role-{{ $role->id }}" action="{{ route('roles.destroy', $role) }}" method="POST" style="display: none;">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Belum ada data role.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function confirmDeleteRole(id, name, userCount) {
        if (userCount > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Tidak Dapat Menghapus',
                text: 'Role "' + name + '" masih digunakan oleh ' + userCount + ' user aktif. Silakan pindahkan role user terkait terlebih dahulu.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        Swal.fire({
            title: 'Hapus Role?',
            text: 'Apakah Anda yakin ingin menghapus role "' + name + '"?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-role-' + id).submit();
            }
        });
    }
</script>
@endpush
