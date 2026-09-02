@extends('layouts.adminlte')

@section('title', 'Manajemen Pengguna')
@section('page_title', 'Manajemen Pengguna & Akun')

@section('page_actions')
    <button type="button" class="btn btn-primary btn-sm shadow-sm font-weight-bold" data-toggle="modal" data-target="#createUserModal">
        <i class="fas fa-user-plus mr-1"></i> Tambah Pengguna Baru
    </button>
@endsection

@section('content')
<div class="row">
    <!-- Filter Card -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0">
            <div class="card-body py-2 px-3">
                <form action="{{ route('users.index') }}" method="GET" class="row align-items-center">
                    <div class="col-md-4 col-12 my-1">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" name="search" class="form-control" placeholder="Cari nama atau email..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-6 my-1">
                        <select name="role_id" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">-- Semua Role --</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" {{ request('role_id') == $r->id ? 'selected' : '' }}>
                                    {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-6 my-1">
                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">-- Semua Status --</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Non-Aktif</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-12 my-1 text-right">
                        <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="col-12">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Pengguna</th>
                            <th>Role & Wewenang</th>
                            <th style="width: 120px;" class="text-center">Status</th>
                            <th>Tanggal Terdaftar</th>
                            <th style="width: 140px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $index => $user)
                            <tr>
                                <td class="text-center font-weight-bold text-muted">{{ $users->firstItem() + $index }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-2 font-weight-bold shadow-xs" style="width: 36px; height: 36px; font-size: 14px;">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-weight-bold text-dark">{{ $user->name }}</div>
                                            <div class="text-muted" style="font-size: 12px;">
                                                <i class="fas fa-envelope mr-1"></i> {{ $user->email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($user->role)
                                        <span class="badge {{ $user->role->slug === 'super_admin' ? 'badge-dark' : 'badge-primary' }} px-2 py-1" style="font-size: 12px;">
                                            <i class="fas fa-shield-alt mr-1"></i> {{ $user->role->name }}
                                        </span>
                                    @else
                                        <span class="badge badge-warning text-dark px-2 py-1">Tanpa Role</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($user->status === 'active')
                                        <span class="badge badge-success px-2 py-1">
                                            <i class="fas fa-check-circle mr-1"></i> Aktif
                                        </span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">
                                            <i class="fas fa-ban mr-1"></i> Non-Aktif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-muted" style="font-size: 12.5px;">
                                        {{ $user->created_at ? $user->created_at->format('d M Y H:i') : '-' }}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="openEditUserModal('{{ $user->id }}', '{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}', '{{ $user->role_id }}', '{{ $user->status }}')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        @if($user->id !== auth()->id())
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteUser('{{ $user->id }}', '{{ addslashes($user->name) }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <form id="delete-user-{{ $user->id }}" action="{{ route('users.destroy', $user) }}" method="POST" style="display: none;">
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
                                    Tidak ada pengguna ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="card-footer bg-white py-2">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal Tambah User -->
<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-user-plus mr-1"></i> Tambah Pengguna Baru
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="font-weight-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Ahmad Fauzi" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-semibold">Alamat Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="contoh: ahmad@medina.com" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6 mb-3">
                            <label class="font-weight-semibold">Role Pengguna <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-control" required>
                                <option value="">-- Pilih Role --</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6 mb-3">
                            <label class="font-weight-semibold">Status Akun <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="active">Aktif</option>
                                <option value="inactive">Non-Aktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form id="editUserForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-user-edit mr-1"></i> Edit Data Pengguna
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="font-weight-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editUserName" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-semibold">Alamat Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="editUserEmail" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-semibold">Ubah Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password" minlength="6">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6 mb-3">
                            <label class="font-weight-semibold">Role Pengguna <span class="text-danger">*</span></label>
                            <select name="role_id" id="editUserRole" class="form-control" required>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6 mb-3">
                            <label class="font-weight-semibold">Status Akun <span class="text-danger">*</span></label>
                            <select name="status" id="editUserStatus" class="form-control" required>
                                <option value="active">Aktif</option>
                                <option value="inactive">Non-Aktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openEditUserModal(id, name, email, roleId, status) {
        document.getElementById('editUserForm').action = '/users/' + id;
        document.getElementById('editUserName').value = name;
        document.getElementById('editUserEmail').value = email;
        document.getElementById('editUserRole').value = roleId;
        document.getElementById('editUserStatus').value = status;
        $('#editUserModal').modal('show');
    }

    function confirmDeleteUser(id, name) {
        Swal.fire({
            title: 'Hapus Akun Pengguna?',
            text: 'Apakah Anda yakin ingin menghapus akun pengguna "' + name + '"?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-user-' + id).submit();
            }
        });
    }
</script>
@endpush
