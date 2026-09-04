<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>Login - Medina Warehouse & Shopee Sync</title>

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
    <!-- AdminLTE v3.2.0 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <style>
        :root {
            --shopee-orange: #ee4d2d;
            --medina-blue: #1e3a8a;
            --medina-accent: #0284c7;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card-box {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-header-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            padding: 2.2rem 2rem 1.8rem;
            text-align: center;
            color: #ffffff;
            position: relative;
        }
        .login-header-bg::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #ee4d2d, #10b981);
        }
        .brand-icon-circle {
            width: 58px;
            height: 58px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #ffffff;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .input-wrapper {
            position: relative;
        }
        .form-control-custom {
            height: 46px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding-left: 42px;
            padding-right: 42px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .form-control-custom:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            z-index: 5;
            pointer-events: none;
        }
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            z-index: 5;
            padding: 0 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 30px;
            width: 30px;
        }
        .password-toggle-btn:hover {
            color: #475569;
        }
        .btn-login-submit {
            height: 46px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            border: none;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
            transition: all 0.2s;
        }
        .btn-login-submit:hover {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.45);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="login-card-box">
    <!-- Header -->
    <div class="login-header-bg">
        <div class="brand-icon-circle p-1 bg-white shadow" style="overflow: hidden;">
            <img src="{{ asset('images/logo.png') }}" alt="Medina Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
        </div>
        <h4 class="font-weight-bold mb-1" style="letter-spacing: -0.5px;">Medina Warehouse</h4>
        <p class="mb-0 text-white-50" style="font-size: 13px;">Sistem Gudang & Sinkronisasi Shopee OpenAPI</p>
    </div>

    <!-- Body -->
    <div class="p-4 p-md-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert" style="font-size: 13px; border-radius: 8px;">
                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert" style="font-size: 13px; border-radius: 8px;">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf
            
            <div class="form-group mb-3">
                <label class="font-weight-semibold text-dark mb-1" style="font-size: 13px;">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" id="inputUsername" class="form-control form-control-custom" placeholder="Masukkan username" value="{{ old('username') }}" required autofocus>
                </div>
            </div>

            <div class="form-group mb-3">
                <label class="font-weight-semibold text-dark mb-1" style="font-size: 13px;">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="inputPassword" class="form-control form-control-custom" placeholder="Masukkan password" required>
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility()" aria-label="Lihat Password">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="rememberMe" name="remember">
                    <label class="custom-control-label text-muted" for="rememberMe" style="font-size: 13px; cursor: pointer;">Ingat Saya</label>
                </div>
                <span class="badge badge-light text-muted border" style="font-size: 11px;">v1.0.0</span>
            </div>

            <button type="submit" class="btn btn-login-submit btn-block">
                <i class="fas fa-sign-in-alt mr-2"></i> Masuk ke Sistem
            </button>
        </form>
    </div>
</div>

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function togglePasswordVisibility() {
        const input = document.getElementById('inputPassword');
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>
</body>
</html>
