<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Akses Ditolak | Medina Warehouse</title>

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .error-card {
            max-width: 520px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            text-align: center;
            padding: 2.5rem 2rem;
        }
        .error-icon-box {
            width: 80px;
            height: 80px;
            background: #fef2f2;
            color: #ef4444;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 1.5rem;
            border: 4px solid #fee2e2;
        }
    </style>
</head>
<body>

<div class="error-card">
    <div class="error-icon-box">
        <i class="fas fa-lock"></i>
    </div>
    
    <h2 class="font-weight-bold text-dark mb-2">403 - Akses Ditolak</h2>
    <p class="text-muted mb-4" style="font-size: 14.5px;">
        Maaf, akun Anda dengan role <strong>{{ auth()->user()->role->name ?? 'Pengguna' }}</strong> tidak memiliki hak akses untuk membuka halaman atau fitur ini.
    </p>

    <div class="p-3 bg-light rounded mb-4 text-left border" style="font-size: 13px;">
        <div class="d-flex align-items-center mb-1">
            <i class="fas fa-user-circle text-primary mr-2"></i>
            <span>Akun: <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }})</span>
        </div>
        <div class="d-flex align-items-center">
            <i class="fas fa-shield-alt text-secondary mr-2"></i>
            <span>Role Saat Ini: <span class="badge badge-secondary">{{ auth()->user()->role->name ?? 'Tanpa Role' }}</span></span>
        </div>
    </div>

    <div class="d-flex justify-content-center" style="gap: 10px;">
        <a href="{{ route('dashboard') }}" class="btn btn-primary font-weight-bold px-4">
            <i class="fas fa-home mr-1"></i> Kembali ke Dashboard
        </a>
        <form action="{{ route('logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-danger font-weight-bold px-3">
                <i class="fas fa-sign-out-alt mr-1"></i> Logout
            </button>
        </form>
    </div>
</div>

</body>
</html>
