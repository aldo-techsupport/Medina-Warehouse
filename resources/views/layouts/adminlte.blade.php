<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>@yield('title', 'Dashboard') - Medina Warehouse & Shopee Sync</title>

    <!-- Google Font: Inter & Source Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- AdminLTE v3.2.0 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --shopee-orange: #ee4d2d;
            --shopee-orange-hover: #d73211;
            --medina-primary: #1e3a8a;
            --medina-accent: #0284c7;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-tap-highlight-color: transparent;
            background-color: #f4f6f9;
        }
        .bg-shopee {
            background-color: var(--shopee-orange) !important;
            color: #ffffff !important;
        }
        .bg-purple {
            background-color: #8b5cf6 !important;
            color: #ffffff !important;
        }
        .text-purple {
            color: #8b5cf6 !important;
        }
        .btn-purple {
            background-color: #8b5cf6;
            color: #ffffff;
            border-color: #8b5cf6;
            font-weight: 600;
        }
        .btn-purple:hover, .btn-purple:focus {
            background-color: #7c3aed;
            color: #ffffff;
        }
        .btn-outline-purple {
            color: #8b5cf6;
            border-color: #8b5cf6;
            background-color: transparent;
        }
        .btn-outline-purple:hover, .btn-outline-purple:focus {
            background-color: #8b5cf6;
            color: #ffffff;
        }
        .btn-shopee {
            background-color: var(--shopee-orange);
            color: #ffffff;
            border-color: var(--shopee-orange);
            font-weight: 600;
        }
        .btn-shopee:hover, .btn-shopee:focus {
            background-color: var(--shopee-orange-hover);
            color: #ffffff;
        }
        .badge-shopee {
            background-color: rgba(238, 77, 45, 0.15);
            color: var(--shopee-orange);
            border: 1px solid rgba(238, 77, 45, 0.3);
            font-weight: 600;
        }
        .main-sidebar {
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .brand-link {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: #111827;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            border: 1px solid #e5e7eb;
            background: #ffffff;
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid #f3f4f6;
            font-weight: 600;
        }
        .small-box {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            position: relative;
        }
        .small-box .inner {
            padding: 12px 14px;
        }
        .small-box .inner h3 {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.2;
            word-break: break-word;
            margin-bottom: 2px;
        }
        @media (min-width: 768px) {
            .small-box .inner h3 {
                font-size: 1.6rem;
            }
        }
        .small-box .small-box-footer {
            background: transparent !important;
            color: #2563eb !important;
            padding: 4px 14px 10px 14px;
            font-weight: 600;
            font-size: 12px;
            text-align: right;
            display: block;
        }
        .table-responsive {
            border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }

        /* Navbar & Header Responsive Tweaks */
        .main-header.navbar {
            height: 56px;
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            padding: 0 0.75rem;
            background-color: #ffffff !important;
            border-bottom: 1px solid #e5e7eb;
        }
        .main-header .navbar-nav {
            display: flex;
            flex-direction: row;
            align-items: center;
            flex-wrap: nowrap !important;
        }

        /* Modal Modern Styling */
        .modal-content {
            border-radius: 14px;
            overflow: hidden;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding: 1rem 1.25rem;
        }
        .modal-body {
            padding: 1.25rem;
        }
        .modal-footer {
            padding: 0.85rem 1.25rem;
            border-top: 1px solid rgba(0,0,0,0.06);
        }

        /* Mobile Bottom Nav Bar with iPhone Safe Area Support */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(56px + env(safe-area-inset-bottom, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: #ffffff;
            box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
            z-index: 1045;
            justify-content: space-around;
            align-items: center;
            border-top: 1px solid #e5e7eb;
        }
        .mobile-bottom-nav-item {
            flex: 1;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
            text-decoration: none !important;
            padding: 4px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: color 0.15s ease;
        }
        .mobile-bottom-nav-item i {
            font-size: 17px;
            margin-bottom: 2px;
        }
        .mobile-bottom-nav-item.active {
            color: #2563eb;
            font-weight: 700;
        }
        .mobile-bottom-nav-item.shopee-nav.active {
            color: var(--shopee-orange);
        }
        .mobile-bottom-nav-fab {
            background: #dc2626;
            color: #ffffff !important;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: -18px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.45);
            border: 3px solid #ffffff;
        }
        .mobile-bottom-nav-fab i {
            font-size: 18px;
            margin-bottom: 0;
        }

        /* Responsive Mobile tweaks */
        @media (max-width: 767.98px) {
            .mobile-bottom-nav {
                display: flex !important;
            }
            .content-wrapper {
                padding-bottom: calc(75px + env(safe-area-inset-bottom, 10px)) !important;
            }
            .content-header h1 {
                font-size: 1.2rem !important;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
            .table-mobile-responsive th, .table-mobile-responsive td {
                padding: 0.5rem !important;
                font-size: 12px;
            }
            .btn-sm-mobile {
                padding: 0.25rem 0.5rem;
                font-size: 11px;
            }
        }
        @media (min-width: 768px) {
            .mobile-bottom-nav {
                display: none !important;
            }
            .content-wrapper {
                padding-bottom: 2rem !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed text-sm">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom shadow-xs">
        <!-- Left navbar links -->
        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <a class="nav-link px-2" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            @if(auth()->check() && auth()->user()->hasPermission('dashboard'))
                <li class="nav-item d-none d-lg-inline-block">
                    <a href="{{ route('dashboard') }}" class="nav-link font-weight-bold text-dark px-2">
                        <i class="fas fa-warehouse text-primary mr-1"></i> Gudang Utama
                    </a>
                </li>
            @endif
            @if(auth()->check() && auth()->user()->hasPermission('packing_station'))
                <li class="nav-item d-none d-lg-inline-block">
                    <a href="{{ route('packing.index') }}" class="nav-link font-weight-bold text-dark px-2">
                        <i class="fas fa-video text-danger mr-1"></i> Packing Video
                    </a>
                </li>
            @endif
            @if(auth()->check() && auth()->user()->hasPermission('shopee_dashboard'))
                <li class="nav-item d-none d-lg-inline-block">
                    <a href="{{ route('shopee.dashboard') }}" class="nav-link font-weight-bold px-2" style="color: var(--shopee-orange)">
                        <i class="fas fa-shopping-bag mr-1"></i> Shopee Sync
                    </a>
                </li>
            @endif
            @if(auth()->check() && auth()->user()->hasPermission('ai_advisor'))
                <li class="nav-item d-none d-lg-inline-block">
                    <a href="{{ route('ai.index') }}" class="nav-link font-weight-bold text-purple px-2">
                        <i class="fas fa-robot mr-1"></i> AI Advisor
                    </a>
                </li>
            @endif
            <!-- Mobile Brand Title in Header -->
            <li class="nav-item d-inline-block d-lg-none ml-1">
                <span class="font-weight-bold text-dark" style="font-size: 13.5px;">
                    <i class="fas fa-boxes-stacked text-primary mr-1"></i> Medina
                </span>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto align-items-center">
            <!-- Full Desktop Quick Actions (>= 1200px) -->
            @if(auth()->check() && auth()->user()->hasPermission('packing_station'))
                <li class="nav-item mr-2 d-none d-xl-inline-block">
                    <a href="{{ route('packing.index') }}" class="btn btn-sm btn-danger shadow-sm font-weight-bold">
                        <i class="fas fa-barcode mr-1"></i> <span>Scan Packing</span>
                    </a>
                </li>
            @endif

            @if(auth()->check() && auth()->user()->hasPermission('warehouse_mutations'))
                <li class="nav-item mr-2 d-none d-xl-inline-block">
                    <button type="button" class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#quickMutationModal">
                        <i class="fas fa-exchange-alt mr-1"></i> <span>Mutasi Cepat</span>
                    </button>
                </li>
            @endif

            @if(auth()->check() && auth()->user()->hasPermission('shopee_orders'))
                <li class="nav-item mr-2 d-none d-xl-inline-block">
                    <button type="button" class="btn btn-sm btn-shopee shadow-sm" data-toggle="modal" data-target="#simulatorModal">
                        <i class="fas fa-bolt mr-1"></i> <span>Simulasi Order</span>
                    </button>
                </li>
            @endif

            <!-- Collapsed Quick Action Dropdown (< 1200px) -->
            <li class="nav-item dropdown d-inline-block d-xl-none mr-2">
                <button type="button" class="btn btn-sm btn-outline-primary shadow-xs dropdown-toggle font-weight-bold px-2" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-bolt text-warning mr-1"></i> <span class="d-none d-sm-inline">Aksi Cepat</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 p-2" style="min-width: 220px; border-radius: 12px;">
                    <h6 class="dropdown-header font-weight-bold text-dark px-2 py-1">Pintasan Aksi</h6>
                    @if(auth()->check() && auth()->user()->hasPermission('packing_station'))
                        <a href="{{ route('packing.index') }}" class="dropdown-item py-2 text-danger font-weight-bold">
                            <i class="fas fa-video mr-2"></i> Stasiun Packing Video
                        </a>
                    @endif
                    @if(auth()->check() && auth()->user()->hasPermission('warehouse_mutations'))
                        <a href="#" class="dropdown-item py-2 text-primary font-weight-bold" data-toggle="modal" data-target="#quickMutationModal">
                            <i class="fas fa-exchange-alt mr-2"></i> Catat Mutasi Stok
                        </a>
                    @endif
                    @if(auth()->check() && auth()->user()->hasPermission('shopee_orders'))
                        <a href="#" class="dropdown-item py-2 font-weight-bold" style="color: var(--shopee-orange)" data-toggle="modal" data-target="#simulatorModal">
                            <i class="fas fa-bolt mr-2"></i> Simulasi Order Shopee
                        </a>
                    @endif
                </div>
            </li>

            <!-- User Profile Dropdown -->
            @auth
                <li class="nav-item dropdown">
                    <a class="nav-link d-flex align-items-center py-0 px-2" data-toggle="dropdown" href="#" aria-expanded="false">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center font-weight-bold mr-1 mr-md-2 shadow-xs" style="width: 32px; height: 32px; font-size: 13px;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="d-none d-md-block text-left mr-1">
                            <span class="d-block font-weight-bold text-dark text-truncate" style="max-width: 140px; font-size: 12.5px; line-height: 1.2;">{{ auth()->user()->name }}</span>
                            <span class="badge {{ auth()->user()->isSuperAdmin() ? 'badge-dark' : 'badge-primary' }}" style="font-size: 9.5px; font-weight: 600;">
                                {{ auth()->user()->role->name ?? 'Pengguna' }}
                            </span>
                        </div>
                        <i class="fas fa-chevron-down text-muted ml-1" style="font-size: 9px;"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 p-2" style="min-width: 230px; border-radius: 12px;">
                        <div class="px-3 py-2 border-bottom mb-2 bg-light rounded">
                            <div class="font-weight-bold text-dark">{{ auth()->user()->name }}</div>
                            <div class="text-muted text-truncate" style="font-size: 11.5px;">{{ auth()->user()->email }}</div>
                            <div class="mt-1">
                                <span class="badge badge-success" style="font-size: 10px;">
                                    <i class="fas fa-shield-alt mr-1"></i> Role: {{ auth()->user()->role->name ?? 'User' }}
                                </span>
                            </div>
                        </div>

                        @if(auth()->user()->hasPermission('role_management'))
                            <a href="{{ route('roles.index') }}" class="dropdown-item py-2">
                                <i class="fas fa-user-shield text-primary mr-2"></i> Manajemen Role & Akses
                            </a>
                        @endif

                        @if(auth()->user()->hasPermission('user_management'))
                            <a href="{{ route('users.index') }}" class="dropdown-item py-2">
                                <i class="fas fa-users-cog text-info mr-2"></i> Manajemen Pengguna
                            </a>
                        @endif

                        <div class="dropdown-divider"></div>

                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item py-2 text-danger font-weight-bold">
                                <i class="fas fa-sign-out-alt mr-2"></i> Keluar (Logout)
                            </button>
                        </form>
                    </div>
                </li>
            @endauth

            <!-- Fullscreen (Desktop Only) -->
            <li class="nav-item d-none d-lg-inline-block">
                <a class="nav-link px-2" href="#" id="fullscreenToggleBtn" role="button" onclick="event.preventDefault(); toggleFullscreen();" title="Layar Penuh (Fullscreen)">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="{{ route('dashboard') }}" class="brand-link d-flex align-items-center">
            <div class="brand-image img-circle elevation-2 d-flex align-items-center justify-content-center bg-primary text-white" style="width: 33px; height: 33px; font-size: 16px;">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <span class="brand-text font-weight-bold ml-2">Medina Warehouse</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar User Panel -->
            @auth
                <div class="user-panel mt-3 pb-3 mb-2 d-flex align-items-center border-bottom" style="border-color: rgba(255,255,255,0.1) !important;">
                    <div class="image">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center font-weight-bold shadow-xs" style="width: 34px; height: 34px; font-size: 14px;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    </div>
                    <div class="info ml-2">
                        <span class="d-block text-white font-weight-bold" style="font-size: 13px; line-height: 1.2;">{{ auth()->user()->name }}</span>
                        <span class="badge {{ auth()->user()->isSuperAdmin() ? 'badge-warning text-dark' : 'badge-info' }} mt-1" style="font-size: 10px;">
                            {{ auth()->user()->role->name ?? 'Pengguna' }}
                        </span>
                    </div>
                </div>
            @endauth

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                    
                    @if(auth()->user()->hasPermission('dashboard') || auth()->user()->hasPermission('warehouse_products') || auth()->user()->hasPermission('warehouse_mutations'))
                        <li class="nav-header text-uppercase font-weight-bold text-muted" style="font-size: 10px;">Gudang Utama</li>
                        
                        @if(auth()->user()->hasPermission('dashboard'))
                            <li class="nav-item">
                                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-chart-pie"></i>
                                    <p>Dashboard Gudang</p>
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasPermission('warehouse_products'))
                            <li class="nav-item">
                                <a href="{{ route('warehouse.products') }}" class="nav-link {{ request()->routeIs('warehouse.products*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-boxes"></i>
                                    <p>Katalog & Stok SKU</p>
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasPermission('warehouse_mutations'))
                            <li class="nav-item">
                                <a href="{{ route('warehouse.mutations') }}" class="nav-link {{ request()->routeIs('warehouse.mutations*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-history"></i>
                                    <p>Riwayat Mutasi Stok</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if(auth()->user()->hasPermission('packing_station') || auth()->user()->hasPermission('packing_history'))
                        <li class="nav-header text-uppercase font-weight-bold text-muted mt-2" style="font-size: 10px;">Packing & Pengiriman</li>

                        @if(auth()->user()->hasPermission('packing_station'))
                            <li class="nav-item">
                                <a href="{{ route('packing.index') }}" class="nav-link {{ request()->routeIs('packing.index') ? 'active bg-danger' : '' }}">
                                    <i class="nav-icon fas fa-video text-danger"></i>
                                    <p class="font-weight-bold">Stasiun Packing Video</p>
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasPermission('packing_history'))
                            <li class="nav-item">
                                <a href="{{ route('packing.history') }}" class="nav-link {{ request()->routeIs('packing.history') ? 'active bg-danger' : '' }}">
                                    <i class="nav-icon fas fa-photo-film text-danger"></i>
                                    <p>Galeri Video Packing</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if(auth()->user()->hasPermission('shopee_dashboard') || auth()->user()->hasPermission('shopee_orders') || auth()->user()->hasPermission('shopee_settings'))
                        <li class="nav-header text-uppercase font-weight-bold text-muted mt-2" style="font-size: 10px;">Integrasi Shopee</li>

                        @if(auth()->user()->hasPermission('shopee_dashboard'))
                            <li class="nav-item">
                                <a href="{{ route('shopee.dashboard') }}" class="nav-link {{ request()->routeIs('shopee.dashboard') ? 'active bg-orange' : '' }}">
                                    <i class="nav-icon fas fa-store" style="color: {{ request()->routeIs('shopee.dashboard') ? '#fff' : 'var(--shopee-orange)' }}"></i>
                                    <p>Dashboard Shopee</p>
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasPermission('shopee_orders'))
                            <li class="nav-item">
                                <a href="{{ route('shopee.orders') }}" class="nav-link {{ request()->routeIs('shopee.orders*') ? 'active bg-orange' : '' }}">
                                    <i class="nav-icon fas fa-shopping-cart" style="color: {{ request()->routeIs('shopee.orders*') ? '#fff' : 'var(--shopee-orange)' }}"></i>
                                    <p>Pesanan Shopee</p>
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasPermission('shopee_settings'))
                            <li class="nav-item">
                                <a href="{{ route('shopee.settings') }}" class="nav-link {{ request()->routeIs('shopee.settings*') ? 'active bg-orange' : '' }}">
                                    <i class="nav-icon fas fa-key" style="color: {{ request()->routeIs('shopee.settings*') ? '#fff' : 'var(--shopee-orange)' }}"></i>
                                    <p>API & Webhook Setting</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if(auth()->user()->hasPermission('ai_advisor'))
                        <li class="nav-header text-uppercase font-weight-bold text-muted mt-2" style="font-size: 10px;">AI & Pemasaran</li>
                        <li class="nav-item">
                            <a href="{{ route('ai.index') }}" class="nav-link {{ request()->routeIs('ai.*') ? 'active bg-purple' : '' }}">
                                <i class="nav-icon fas fa-robot" style="color: {{ request()->routeIs('ai.*') ? '#fff' : '#8b5cf6' }}"></i>
                                <p>
                                    AI Seller & Analisis
                                    <span class="badge badge-info right" style="font-size: 9px; font-weight: 700;">PRO AI</span>
                                </p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('role_management') || auth()->user()->hasPermission('user_management'))
                        <li class="nav-header text-uppercase font-weight-bold text-muted mt-2" style="font-size: 10px;">Pengaturan & Hak Akses</li>

                        @if(auth()->user()->hasPermission('role_management'))
                            <li class="nav-item">
                                <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-user-shield text-info"></i>
                                    <p>Role & Hak Akses Menu</p>
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasPermission('user_management'))
                            <li class="nav-item">
                                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-users-cog text-info"></i>
                                    <p>Manajemen Pengguna</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if(auth()->user()->hasPermission('warehouse_mutations') || auth()->user()->hasPermission('shopee_orders'))
                        <li class="nav-header text-uppercase font-weight-bold text-muted mt-2" style="font-size: 10px;">Aksi Cepat</li>
                        
                        @if(auth()->user()->hasPermission('warehouse_mutations'))
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-toggle="modal" data-target="#quickMutationModal">
                                    <i class="nav-icon fas fa-plus-circle text-success"></i>
                                    <p>Catat Mutasi Stok</p>
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasPermission('shopee_orders'))
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-toggle="modal" data-target="#simulatorModal">
                                    <i class="nav-icon fas fa-magic text-warning"></i>
                                    <p>Simulasi Beli di Shopee</p>
                                </a>
                            </li>
                        @endif
                    @endif

                    <li class="nav-item mt-3 pt-2 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
                        <form action="{{ route('logout') }}" method="POST" id="sidebarLogoutForm">
                            @csrf
                            <a href="#" class="nav-link text-danger font-weight-bold" onclick="event.preventDefault(); document.getElementById('sidebarLogoutForm').submit();">
                                <i class="nav-icon fas fa-sign-out-alt"></i>
                                <p>Keluar (Logout)</p>
                            </a>
                        </form>
                    </li>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper bg-light">
        <!-- Content Header (Page header) -->
        <div class="content-header pb-2 pt-2 pt-md-3">
            <div class="container-fluid">
                <div class="row align-items-center mb-1">
                    <div class="col-sm-6 col-12 mb-1 mb-sm-0">
                        <h1 class="m-0 text-dark font-weight-bold">@yield('page_title', 'Dashboard')</h1>
                    </div>
                    <div class="col-sm-6 col-12 text-sm-right">
                        @yield('page_actions')
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <div class="content pb-4">
            <div class="container-fluid">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm py-2" role="alert">
                        <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm py-2" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i> {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-warning alert-dismissible fade show shadow-sm py-2" role="alert">
                        <strong>Perhatian:</strong>
                        <ul class="mb-0 pl-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Quick Stock Mutation Modal -->
    <div class="modal fade" id="quickMutationModal" tabindex="-1" role="dialog" aria-labelledby="quickMutationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('warehouse.mutations.store') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title font-weight-bold" id="quickMutationModalLabel">
                            <i class="fas fa-exchange-alt mr-2"></i> Mutasi Stok Gudang Utama
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-semibold">Pilih Produk / SKU <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-control" required>
                                <option value="">-- Pilih Produk --</option>
                                @php
                                    $modalProducts = \App\Models\Product::where('status', 'active')->orderBy('name')->get();
                                @endphp
                                @foreach($modalProducts as $p)
                                    <option value="{{ $p->id }}">[{{ $p->sku }}] {{ $p->name }} (Stok: {{ $p->stock }} {{ $p->unit }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-semibold">Jenis Mutasi <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required id="mutationTypeSelect">
                                <option value="inbound">📦 Barang Masuk (+ Tambah Stok)</option>
                                <option value="outbound">🚚 Barang Keluar Manual (- Kurang Stok)</option>
                                <option value="adjustment">⚖️ Stok Opname / Koreksi Fisik (Set Jumlah Total)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-semibold" id="mutationQtyLabel">Jumlah Unit <span class="text-danger">*</span></label>
                            <input type="number" name="qty" class="form-control" placeholder="Contoh: 10" required min="1">
                            <small class="text-muted" id="mutationQtyHelp">Masukkan jumlah unit yang masuk / keluar.</small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-semibold">Catatan / Keterangan</label>
                            <input type="text" name="notes" class="form-control" placeholder="Contoh: Restock Supplier PT ABC / Sample Display">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-semibold">Petugas / Operator</label>
                            <input type="text" name="actor" class="form-control" value="Admin Gudang">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary font-weight-bold">
                            <i class="fas fa-save mr-1"></i> Simpan Mutasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Shopee Order Simulator Modal -->
    <div class="modal fade" id="simulatorModal" tabindex="-1" role="dialog" aria-labelledby="simulatorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('shopee.simulate.order') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-shopee text-white">
                        <h5 class="modal-title font-weight-bold" id="simulatorModalLabel">
                            <i class="fas fa-shopping-bag mr-2"></i> Simulasi Beli Produk di Shopee
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2" style="font-size: 13px;">
                            <i class="fas fa-info-circle mr-1"></i> Fitur simulasi ini menirukan alur pesanan nyata dari Shopee Open API. Saat pesanan masuk, <strong>stok di Gudang Utama akan otomatis terpotong</strong> dan tercatat di riwayat mutasi dengan nomor referensi Shopee Order SN.
                        </div>

                        <div class="form-group">
                            <label class="font-weight-semibold">Pilih Produk yang Dibeli <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-control" required>
                                <option value="">-- Pilih Produk --</option>
                                @foreach($modalProducts as $p)
                                    <option value="{{ $p->id }}" {{ $p->stock <= 0 ? 'disabled' : '' }}>
                                        [{{ $p->sku }}] {{ $p->name }} — Stok: {{ $p->stock }} {{ $p->unit }} (Rp {{ number_format($p->selling_price, 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-6">
                                <label class="font-weight-semibold">Jumlah Beli (Qty) <span class="text-danger">*</span></label>
                                <input type="number" name="qty" class="form-control" value="1" min="1" required>
                            </div>
                            <div class="form-group col-6">
                                <label class="font-weight-semibold">Status Pesanan</label>
                                <select name="order_status" class="form-control">
                                    <option value="READY_TO_SHIP">READY_TO_SHIP</option>
                                    <option value="PROCESSED">PROCESSED</option>
                                    <option value="COMPLETED">COMPLETED</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-semibold">Nama Akun Pembeli Shopee</label>
                            <input type="text" name="buyer_username" class="form-control" placeholder="Contoh: anita_shopee99">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-shopee font-weight-bold">
                            <i class="fas fa-cart-arrow-down mr-1"></i> Buat Pesanan Simulasi & Kurangi Stok
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav">
        @if(auth()->check() && auth()->user()->hasPermission('dashboard'))
            <a href="{{ route('dashboard') }}" class="mobile-bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-warehouse"></i>
                <span>Gudang</span>
            </a>
        @endif
        @if(auth()->check() && auth()->user()->hasPermission('warehouse_products'))
            <a href="{{ route('warehouse.products') }}" class="mobile-bottom-nav-item {{ request()->routeIs('warehouse.products*') ? 'active' : '' }}">
                <i class="fas fa-boxes"></i>
                <span>Produk</span>
            </a>
        @endif
        @if(auth()->check() && auth()->user()->hasPermission('packing_station'))
            <a href="{{ route('packing.index') }}" class="mobile-bottom-nav-item">
                <div class="mobile-bottom-nav-fab bg-danger">
                    <i class="fas fa-video"></i>
                </div>
                <span style="margin-top: 4px; color: #dc2626; font-weight: bold;">Packing</span>
            </a>
        @endif
        @if(auth()->check() && auth()->user()->hasPermission('warehouse_mutations'))
            <a href="{{ route('warehouse.mutations') }}" class="mobile-bottom-nav-item {{ request()->routeIs('warehouse.mutations*') ? 'active' : '' }}">
                <i class="fas fa-history"></i>
                <span>Riwayat</span>
            </a>
        @endif
        @if(auth()->check() && auth()->user()->hasPermission('shopee_dashboard'))
            <a href="{{ route('shopee.dashboard') }}" class="mobile-bottom-nav-item shopee-nav {{ request()->routeIs('shopee.*') ? 'active' : '' }}">
                <i class="fas fa-shopping-bag"></i>
                <span>Shopee</span>
            </a>
        @endif
    </nav>

    <!-- Main Footer (Desktop Only) -->
    <footer class="main-footer text-sm d-none d-md-block">
        <div class="float-right d-none d-sm-inline">
            <strong>Medina Warehouse</strong> v1.0.0
        </div>
        <span>&copy; {{ date('Y') }} Medina Warehouse & Shopee Open API Sync. All rights reserved.</span>
    </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
    function toggleFullscreen() {
        var elem = document.documentElement;
        if (!document.fullscreenElement && !document.mozFullScreenElement &&
            !document.webkitFullscreenElement && !document.msFullscreenElement) {
            if (elem.requestFullscreen) {
                elem.requestFullscreen().catch(err => console.log(err));
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            } else if (elem.mozRequestFullScreen) {
                elem.mozRequestFullScreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen().catch(err => console.log(err));
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            }
        }
    }

    function updateFullscreenIcon() {
        var isFull = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement);
        var icon = document.querySelector('#fullscreenToggleBtn i');
        if (icon) {
            if (isFull) {
                icon.className = 'fas fa-compress-arrows-alt';
            } else {
                icon.className = 'fas fa-expand-arrows-alt';
            }
        }
    }

    document.addEventListener('fullscreenchange', updateFullscreenIcon);
    document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
    document.addEventListener('mozfullscreenchange', updateFullscreenIcon);
    document.addEventListener('MSFullscreenChange', updateFullscreenIcon);

    $(document).ready(function() {
        $('#mutationTypeSelect').on('change', function() {
            var val = $(this).val();
            if (val === 'adjustment') {
                $('#mutationQtyLabel').html('Total Stok Fisik Baru (Stok Akhir) <span class="text-danger">*</span>');
                $('#mutationQtyHelp').text('Masukkan angka total stok aktual di gudang hasil opname.');
                $('input[name="qty"]').attr('min', '0');
            } else {
                $('#mutationQtyLabel').html('Jumlah Unit <span class="text-danger">*</span>');
                $('#mutationQtyHelp').text('Masukkan jumlah unit yang masuk / keluar.');
                $('input[name="qty"]').attr('min', '1');
            }
        });
    });
</script>

@stack('scripts')
</body>
</html>
