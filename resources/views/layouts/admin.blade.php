@extends('admin.dashboard.toolbars.index')
@section('content')

    <div class="d-flex">
        <!-- Main Content -->
        <div class="flex-fill content-wrapper">
            <!-- Breadcrumb -->
            @if(!isset($hideBreadcrumb) || !$hideBreadcrumb)
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-home"></i>
                            الرئيسية
                        </a>
                    </li>
                    @stack('breadcrumb')
                    @if(isset($breadcrumb))
                        {!! $breadcrumb !!}
                    @endif
                </ol>
            </nav>
            @endif

            <!-- Page Header -->
            @if(View::hasSection('page-header'))
                <div class="page-header">
                    @yield('page-header')
                </div>
            @endif

            <!-- Alerts -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    {{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        </div>
    </div>

@push('styles')
    <style>
        body {
            font-family: 'Cairo', Arial, sans-serif;
            background-color: #f8f9fa;
        }

        .admin-navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }

        .admin-sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 4px rgba(0,0,0,.1);
        }

        .sidebar-link {
            color: #ecf0f1;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
            border-right: 3px solid transparent;
        }

        .sidebar-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
            border-right-color: #3498db;
        }

        .sidebar-link.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: #fff;
            border-right-color: #3498db;
        }

        .content-wrapper {
            min-height: calc(100vh - 56px);
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,.1);
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            border: none;
            transition: transform 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,.15);
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }

        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #495057;
            font-weight: 500;
        }

        .btn-custom {
            border-radius: 20px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }

        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 500;
        }

        .modal-header {
            border-radius: 10px 10px 0 0;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        /* تحسين pagination */
        .pagination-btn {
            border: none;
            background: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #e9ecef;
        }

        .pagination-btn:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* تحسينات responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                position: fixed;
                top: 56px;
                right: -250px;
                width: 250px;
                height: calc(100vh - 56px);
                z-index: 1050;
                transition: right 0.3s ease;
            }

            .admin-sidebar.show {
                right: 0;
            }

            .content-wrapper {
                margin-right: 0;
            }
        }
    </style>
@endpush

@include('file-management.dublicateJavascript')
@endsection




