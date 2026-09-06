<!DOCTYPE html>
<html direction="rtl" dir="rtl" style="direction: rtl">
<head>
    <base href="../" />
    <title>إدارة التسجيلات</title>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{ asset('admin/assets/media/logos/favicon.ico') }}" />

    <!--begin::Global Stylesheets Bundle-->
    <link href="{{ asset('admin/assets/plugins/global/plugins.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('admin/assets/css/style.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('admin/assets/plugins/custom/cropper/cropper.bundle.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->

    <style>
        .select2-container--bootstrap-5 .select2-selection--single {
            height: 45px;
            display: flex;
            align-items: center;
            padding-right: 10px;
        }
        .select2-container--bootstrap-5 .select2-selection__rendered {
            padding-right: 0;
        }
        #cropperModal .modal-body {
            padding: 0;
            max-height: 60vh;
            overflow: hidden;
        }
        #cropperImage {
            display: block;
            width: 80%;
            height: auto;
        }
    </style>
    @stack('styles')
</head>

<body id="kt_app_body" data-kt-app-layout="dark-sidebar" data-kt-app-header-fixed="true"
    data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true"
    data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true"
    data-kt-app-sidebar-push-footer="true" class="app-default">

    <!--begin::Theme mode setup-->
    <script>
        var defaultThemeMode = "light";
        var themeMode = localStorage.getItem("data-bs-theme") || defaultThemeMode;
        if (themeMode === "system") {
            themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }
        document.documentElement.setAttribute("data-bs-theme", themeMode);
    </script>

    <!--begin::Loader-->
    <div id="loader" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color:rgba(255,255,255,0.8); z-index:9999; justify-content:center; align-items:center;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جاري التحميل...</span>
        </div>
    </div>

    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">

            <!--begin::Header-->
            <div id="kt_app_header" class="app-header" data-kt-sticky="true"
                data-kt-sticky-activate="{default: true, lg: true}" data-kt-sticky-name="app-header-minimize"
                data-kt-sticky-offset="{default: '200px', lg: '0'}" data-kt-sticky-animation="false">
                <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">

                    <!--begin::Sidebar mobile toggle-->
                    <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2" title="Show sidebar menu">
                        <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                            <i class="ki-duotone ki-abstract-14 fs-2 fs-md-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>

                    <!--begin::Mobile logo-->
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                        <a href="{{ route('generalRegistration.index') }}" class="d-lg-none">
                            <img alt="Logo" src="{{ asset('uploads/logo02small.png') }}" class="h-30px" />
                        </a>
                    </div>

                    <!--begin::Header wrapper-->
                    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1" id="kt_app_header_wrapper">

                        <!--begin::Page title-->
                        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3 mb-5 mb-lg-0">
                            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                                البيانات العامة
                            </h1>
                            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                                <li class="breadcrumb-item text-muted">
                                    <a href="{{ route('generalRegistration.index') }}" class="text-muted text-hover-primary">الرئيسية</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                                </li>
                                <li class="breadcrumb-item text-muted">البيانات</li>
                            </ul>
                        </div>

                        <!--begin::Navbar-->
                        <div class="app-navbar flex-shrink-0">
                            <!--begin::User menu-->
                            <div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
                                <div class="cursor-pointer symbol symbol-35px"
                                    data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                                    data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                                    <img alt="صورة المستخدم" src="{{ asset(Auth::user()->avatar) }}?v={{ time() }}" class="user-avatar rounded-circle" />
                                </div>

                                <!--begin::User account menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                                    <div class="menu-item px-3">
                                        <div class="menu-content d-flex align-items-center px-3">
                                            <div class="symbol symbol-50px me-5">
                                                <img alt="صورة المستخدم" src="{{ asset(Auth::user()->avatar) }}?v={{ time() }}" class="user-avatar rounded-circle" />
                                            </div>
                                            <div class="d-flex flex-column">
                                                <div class="fw-bold d-flex align-items-center fs-5">
                                                    {{ Auth::user()->name }}
                                                    <span class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">مستخدم</span>
                                                </div>
                                                <span class="fw-semibold text-muted fs-7">{{ Auth::user()->email }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-5">
                                       
                                    </div>
                                    <div class="separator my-2"></div>

                                    <!--begin::Theme switcher-->
                                    <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                                        data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                                        <a href="#" class="menu-link px-5">
                                            <span class="menu-title position-relative">الوضعية
                                                <span class="ms-5 position-absolute translate-middle-y top-50 end-0">
                                                    <i class="ki-duotone ki-night-day theme-light-show fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span><span class="path7"></span><span class="path8"></span><span class="path9"></span><span class="path10"></span></i>
                                                    <i class="ki-duotone ki-moon theme-dark-show fs-2"><span class="path1"></span><span class="path2"></span></i>
                                                </span>
                                            </span>
                                        </a>
                                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px" data-kt-menu="true" data-kt-element="theme-mode-menu">
                                            <div class="menu-item px-3 my-0">
                                                <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                                                    <span class="menu-icon" data-kt-element="icon"><i class="ki-duotone ki-night-day fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span><span class="path7"></span><span class="path8"></span><span class="path9"></span><span class="path10"></span></i></span>
                                                    <span class="menu-title">فاتح</span>
                                                </a>
                                            </div>
                                            <div class="menu-item px-3 my-0">
                                                <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                                                    <span class="menu-icon" data-kt-element="icon"><i class="ki-duotone ki-moon fs-2"><span class="path1"></span><span class="path2"></span></i></span>
                                                    <span class="menu-title">داكن</span>
                                                </a>
                                            </div>
                                            <div class="menu-item px-3 my-0">
                                                <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                                                    <span class="menu-icon" data-kt-element="icon"><i class="ki-duotone ki-screen fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i></span>
                                                    <span class="menu-title">تلقائي</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="menu-item px-5">
                                        <form method="POST" action="{{ route('user.logout.custom') }}">
                                            @csrf
                                            <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" class="menu-link px-5">تسجيل الخروج</a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Navbar-->
                    </div>
                </div>
            </div>
            <!--end::Header-->

            <!--begin::Wrapper-->
            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">

                <!--begin::Sidebar-->
                <div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true"
                    data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}"
                    data-kt-drawer-overlay="true" data-kt-drawer-width="225px" data-kt-drawer-direction="start"
                    data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

                    <!--begin::Logo-->
                    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
                        <a href="{{ route('generalRegistration.index') }}">
                            <img alt="Logo" src="{{ asset('uploads/logo01.png') }}" class="h-35px app-sidebar-logo-default" />
                            <img alt="Logo" src="{{ asset('uploads/logo02small.png') }}" class="h-20px app-sidebar-logo-minimize" />
                        </a>
                        <div id="kt_app_sidebar_toggle"
                            class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
                            data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
                            data-kt-toggle-name="app-sidebar-minimize">
                            <i class="ki-duotone ki-black-left-line fs-3 rotate-180"><span class="path1"></span><span class="path2"></span></i>
                        </div>
                    </div>

                    <!--begin::sidebar menu-->
                    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
                        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">
                            @include('user.dashboard.layout.sidebar')
                        </div>
                    </div>
                </div>
                <!--end::Sidebar-->

                <!--begin::Main-->
                <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <div class="d-flex flex-column flex-column-fluid">

                        <!--begin::Content-->
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            @yield('contentUser')
                        </div>
                        <!--end::Content-->
                    </div>

                    <!--begin::Footer-->
                    <div id="kt_app_footer" class="app-footer">
                        <div class="app-container container-fluid d-flex flex-column flex-md-row flex-center flex-md-stack py-3">
                            <div class="text-dark order-2 order-md-1">
                                <span class="text-muted fw-semibold me-1">{{ date('Y') }} &copy;</span>
                                <span class="text-gray-800">بوابة تسجيل الأيتام والمستفيدين</span>
                            </div>
                        </div>
                    </div>
                    <!--end::Footer-->
                </div>
                <!--end::Main-->
            </div>
            <!--end::Wrapper-->
        </div>
    </div>
    <!--end::App-->

    @include('admin.dashboard.component.cropper')

    <!--begin::Javascript-->
    <script>var hostUrl = "{{ asset('admin/assets/') }}";</script>

    <!--begin::Global Javascript Bundle-->
    <script src="{{ asset('admin/assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('admin/assets/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('admin/assets/plugins/custom/cropper/cropper.bundle.js') }}"></script>

    <script>
        // Set csrf at ajax header
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>

    @include('admin.dashboard.javascript.custom')
    @include('admin.dashboard.javascript.lodaer')
    @include('admin.dashboard.javascript.cropper')

    @yield('scripts')
    @stack('scriptsCode')
</body>
</html>
