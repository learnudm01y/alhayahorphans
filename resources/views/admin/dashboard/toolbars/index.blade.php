<!DOCTYPE html>
<html direction="rtl" dir="rtl" style="direction: rtl">
<!--begin::Head-->

<head>
    <base href="../" />
    <title> لوحة الإدارة المركزية </title>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="description"
        content="The most advanced Bootstrap 5 Admin Theme with 40 unique prebuilt layouts on Themeforest trusted by 100,000 beginners and professionals. Multi-demo, Dark Mode, RTL support and complete React, Angular, Vue, Asp.Net Core, Rails, Spring, Blazor, Django, Express.js, Node.js, Flask, Symfony & Laravel versions. Grab your copy now and get life-time updates for free." />
    <meta name="keywords"
        content="metronic, bootstrap, bootstrap 5, angular, VueJs, React, Asp.Net Core, Rails, Spring, Blazor, Django, Express.js, Node.js, Flask, Symfony & Laravel starter kits, admin themes, web design, figma, web development, free templates, free admin themes, bootstrap theme, bootstrap template, bootstrap dashboard, bootstrap dak mode, bootstrap button, bootstrap datepicker, bootstrap timepicker, fullcalendar, datatables, flaticon" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:title"
        content="Metronic - Bootstrap Admin Template, HTML, VueJS, React, Angular. Laravel, Asp.Net Core, Ruby on Rails, Spring Boot, Blazor, Django, Express.js, Node.js, Flask Admin Dashboard Theme & Template" />
    <meta property="og:url" content="https://keenthemes.com/metronic" />
    <meta property="og:site_name" content="Keenthemes | Metronic" />
    <link rel="canonical" href="https://preview.keenthemes.com/metronic8" />
    <link rel="shortcut icon" href="{{ asset('admin/assets/media/logos/favicon.ico') }}" />
    <!--begin::Fonts(mandatory for all pages)-->
    {{-- <link rel="stylesheet" href="{{ asset('admin/assets/fonts/iranyekan/fontface.css') }}"> --}}
    <!--end::Fonts-->
    <!--begin::Vendor Stylesheets(used for this page only)-->
    <link href="{{ asset('admin/assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}" rel="stylesheet"
        type="text/css" />
    <link href="{{ asset('admin/assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet"
        type="text/css" />
    <!--end::Vendor Stylesheets-->
     <!-- Google Fonts Arabic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link href="{{ asset('admin/assets/plugins/global/plugins.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('admin/assets/css/style.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
    <link href="{{ asset('admin/assets/plugins/custom/cropper/cropper.bundle.css') }}" rel="stylesheet"
        type="text/css" />

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Profile Search Suggestions CSS -->
    <link href="{{ asset('css/profile-search-suggestions.css') }}" rel="stylesheet" type="text/css" />

    <script>
        // Frame-busting to prevent site from being loaded within a frame without permission (click-jacking)
        if (window.top != window.self) {
            window.top.location.replace(window.self.location.href);
        }
    </script>
    <style>
        .img-flag {
            width: 16px;
            height: 12px;
            object-fit: cover;
            margin-right: 8px;
        }

        .select2-container--default .select2-results__option .img-flag {
            width: 16px;
            height: 12px;
        }

        .select2-container--default .select2-selection__rendered .img-flag {
            width: 16px;
            height: 12px;
        }

        .select2-container--bootstrap-5 .select2-selection--single {
            height: 45px;
            display: flex;
            align-items: center;
            padding-right: 10px;
        }

        .select2-results__option img,
        .select2-selection__rendered img {
            height: 20px;
            margin-left: 10px;
            vertical-align: middle;
        }

        .select2-container--bootstrap-5 .select2-selection__rendered {
            padding-right: 0;
        }

        /* نزيل البادينغ حتى تمتلئ الصورة مساحة body بالكامل */
        /* نجعل المودال لا يتعدّى 80% من ارتفاع الشاشة */
        #cropperModal .modal-body {
            padding: 0;
            max-height: 60vh;
            overflow: hidden;
        }

        /* الصورة تملأ العرض بالكامل وتحافظ على ratio */
        #cropperImage {
            display: block;
            width: 80%;
            height: auto;
        }
    </style>
    @stack('styles')

</head>

<!--end::Head-->
<!--begin::Body-->

<body id="kt_app_body" data-kt-app-layout="dark-sidebar" data-kt-app-header-fixed="true"
    data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true"
    data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true"
    data-kt-app-sidebar-push-footer="true" data-kt-app-toolbar-enabled="true" data-kt-app-toolbar-fixed="true"
    class="app-default">
    <!--begin::Theme mode setup on page load-->
    <script>
        var defaultThemeMode = "light";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
    </script>
    <div id="loader"
        style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color:rgba(255,255,255,0.8); z-index:9999; justify-content:center; align-items:center;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جاري التحميل...</span>
        </div>
    </div>
    <!--end::Theme mode setup on page load-->
    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <!--begin::Page-->
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
            <!--begin::Header-->
            <div id="kt_app_header" class="app-header" data-kt-sticky="true"
                data-kt-sticky-activate="{default: true, lg: true}" data-kt-sticky-name="app-header-minimize"
                data-kt-sticky-offset="{default: '200px', lg: '0'}" data-kt-sticky-animation="false">
                <!--begin::Header container-->
                <div class="app-container container-fluid d-flex align-items-stretch justify-content-between"
                    id="kt_app_header_container">
                    <!--begin::Sidebar mobile toggle-->
                    <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2" title="Show sidebar menu">
                        <div class="btn btn-icon btn-active-color-primary w-35px h-35px"
                            id="kt_app_sidebar_mobile_toggle">
                            <i class="ki-duotone ki-abstract-14 fs-2 fs-md-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <!--end::Sidebar mobile toggle-->
                    <!--begin::Mobile logo-->
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                        <a href="{{ route('admin.dashboard') }}" class="d-lg-none">
                            <img alt="Logo" src="{{ asset('uploads/small-logo.png') }}" class="h-30px" />
                        </a>
                    </div>
                    <!--end::Mobile logo-->
                    <!--begin::Header wrapper-->
                    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1"
                        id="kt_app_header_wrapper">
                        <!--begin::Page title-->
                        <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
                            data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
                            class="page-title d-flex flex-column justify-content-center flex-wrap me-3 mb-5 mb-lg-0">
                            <!--begin::Title-->
                            <h1
                                class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                                Extended</h1>
                            <!--end::Title-->
                            <!--begin::Breadcrumb-->
                            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                                <!--begin::Item-->
                                <li class="breadcrumb-item text-muted">
                                    <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Home</a>
                                </li>
                                <!--end::Item-->
                                <!--begin::Item-->
                                <li class="breadcrumb-item">
                                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                                </li>
                                <!--end::Item-->
                                <!--begin::Item-->
                                <li class="breadcrumb-item text-muted">Toolbars</li>
                                <!--end::Item-->
                            </ul>
                            <!--end::Breadcrumb-->
                        </div>
                        <!--end::Page title-->
                        <!--begin::Navbar-->
                        <div class="app-navbar flex-shrink-0">
                            <!--begin::Search-->
                            <div class="app-navbar-item align-items-stretch ms-1 ms-md-4">
                                <!--begin::Search-->
                                <div id="kt_header_search" class="header-search d-flex align-items-stretch"
                                    data-kt-search-keypress="true" data-kt-search-min-length="2"
                                    data-kt-search-enter="enter" data-kt-search-layout="menu"
                                    data-kt-menu-trigger="auto" data-kt-menu-overflow="false"
                                    data-kt-menu-permanent="true" data-kt-menu-placement="bottom-end">

                                    <!--end::Search toggle-->
                                    <!--begin::Menu-->

                                    <!--end::Menu-->
                                </div>
                                <!--end::Search-->
                            </div>
                            <!--end::Search-->
                            <!--begin::Activities-->
                            <div class="app-navbar-item ms-1 ms-md-4">
                                <!--begin::Drawer toggle-->

                                <!--end::Drawer toggle-->
                            </div>
                            <!--end::Activities-->
                            <!--begin::Notifications-->
                            <div class="app-navbar-item ms-1 ms-md-4">
                                <!--begin::Menu- wrapper-->

                                <!--end::Menu wrapper-->
                            </div>
                            <!--end::Notifications-->
                            <!--begin::Chat-->
                            <div class="app-navbar-item ms-1 ms-md-4">
                                <!--begin::Menu wrapper-->

                                <!--end::Menu wrapper-->
                            </div>
                            <!--end::Chat-->
                            <!--begin::My apps links-->
                            <div class="app-navbar-item ms-1 ms-md-4">
                                <!--begin::Menu wrapper-->

                                <!--begin::My apps-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column w-100 w-sm-350px"
                                    data-kt-menu="true">
                                    <!--begin::Card-->

                                    <!--end::Card-->
                                </div>
                                <!--end::My apps-->
                                <!--end::Menu wrapper-->
                            </div>
                            <!--end::My apps links-->
                            <!--begin::User menu-->
                            <div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
                                <!--begin::Menu wrapper-->
                                <div class="cursor-pointer symbol symbol-35px"
                                    data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                                    data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                                    {{-- <img src="{{ Auth::user()->avatar }}" class="rounded-3" alt="user-avatar" /> --}}
                                    <img alt="Logo" src="{{ Auth::user()->avatar }}?v={{ time() }}"
                                        class="user-avatar" />
                                </div>
                                <!--begin::User account menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
                                    data-kt-menu="true">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <div class="menu-content d-flex align-items-center px-3">
                                            <!--begin::Avatar-->
                                            <div class="symbol symbol-50px me-5">
                                                {{-- <img alt="Logo" src="{{ Auth::user()->avatar }}" class="user-avatar" /> --}}
                                                <img alt="Logo"
                                                    src="{{ Auth::user()->avatar }}?v={{ time() }}"
                                                    class="user-avatar" />
                                            </div>
                                            <!--end::Avatar-->
                                            <!--begin::Username-->
                                            <div class="d-flex flex-column">
                                                <div class="fw-bold d-flex align-items-center fs-5">
                                                    {{ Auth::user()->name }}
                                                    <span
                                                        class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">الإدارة</span>
                                                </div>
                                                <a href="#"
                                                    class="fw-semibold text-muted text-hover-primary fs-7">{{ Auth::user()->email }}</a>
                                            </div>
                                            <!--end::Username-->
                                        </div>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu separator-->
                                    <div class="separator my-2"></div>
                                    <!--end::Menu separator-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-5">
                                        <a href="{{ route('admin.index.profile') }}" class="menu-link px-5"> الملف
                                            الشخصي </a>
                                    </div>
                                    <!--end::Menu item-->



                                    <!--begin::Menu separator-->
                                    <div class="separator my-2"></div>
                                    <!--end::Menu separator-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                                        data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                                        <a href="#" class="menu-link px-5">
                                            <span class="menu-title position-relative"> الوضعية
                                                <span class="ms-5 position-absolute translate-middle-y top-50 end-0">
                                                    <i class="ki-duotone ki-night-day theme-light-show fs-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                        <span class="path4"></span>
                                                        <span class="path5"></span>
                                                        <span class="path6"></span>
                                                        <span class="path7"></span>
                                                        <span class="path8"></span>
                                                        <span class="path9"></span>
                                                        <span class="path10"></span>
                                                    </i>
                                                    <i class="ki-duotone ki-moon theme-dark-show fs-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </span></span>
                                        </a>
                                        <!--begin::Menu-->
                                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px"
                                            data-kt-menu="true" data-kt-element="theme-mode-menu">
                                            <!--begin::Menu item-->
                                            <div class="menu-item px-3 my-0">
                                                <a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
                                                    data-kt-value="light">
                                                    <span class="menu-icon" data-kt-element="icon">
                                                        <i class="ki-duotone ki-night-day fs-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                            <span class="path3"></span>
                                                            <span class="path4"></span>
                                                            <span class="path5"></span>
                                                            <span class="path6"></span>
                                                            <span class="path7"></span>
                                                            <span class="path8"></span>
                                                            <span class="path9"></span>
                                                            <span class="path10"></span>
                                                        </i>
                                                    </span>
                                                    <span class="menu-title"> ابيض </span>
                                                </a>
                                            </div>
                                            <!--end::Menu item-->
                                            <!--begin::Menu item-->
                                            <div class="menu-item px-3 my-0">
                                                <a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
                                                    data-kt-value="dark">
                                                    <span class="menu-icon" data-kt-element="icon">
                                                        <i class="ki-duotone ki-moon fs-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                    </span>
                                                    <span class="menu-title"> اسود </span>
                                                </a>
                                            </div>
                                            <!--end::Menu item-->
                                            <!--begin::Menu item-->
                                            <div class="menu-item px-3 my-0">
                                                <a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
                                                    data-kt-value="system">
                                                    <span class="menu-icon" data-kt-element="icon">
                                                        <i class="ki-duotone ki-screen fs-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                            <span class="path3"></span>
                                                            <span class="path4"></span>
                                                        </i>
                                                    </span>
                                                    <span class="menu-title"> مثل النظام </span>
                                                </a>
                                            </div>
                                            <!--end::Menu item-->
                                        </div>
                                        <!--end::Menu-->
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-5 my-1">
                                        {{-- <a href="{{ route('admin.settings') }}" class="menu-link px-5"> إعدادات الحساب </a> --}}
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-5">
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <a href="route('logout')"
                                                onclick="event.preventDefault();
                                                            this.closest('form').submit();"
                                                class="menu-link px-5"> تسجيل الخروج </a>
                                        </form>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::User account menu-->
                                <!--end::Menu wrapper-->
                            </div>
                            <!--end::User menu-->
                            <!--begin::Header menu toggle-->
                            <!--end::Header menu toggle-->
                            <!--begin::Aside toggle-->
                            <!--end::Header menu toggle-->
                        </div>
                        <!--end::Navbar-->
                    </div>
                    <!--end::Header wrapper-->
                </div>
                <!--end::Header container-->
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
                        <!--begin::Logo image-->
                        <a href="{{ route('admin.dashboard') }}">
                            <img alt="Logo" src="{{ asset('uploads/logo.png') }}"
                                class="h-35px app-sidebar-logo-default" />
                            <img alt="Logo" src="{{ asset('uploads/small-logo.png') }}"
                                class="h-20px app-sidebar-logo-minimize" />
                        </a>
                        <!--end::Logo image-->
                        <!--begin::Sidebar toggle-->
                        <!--begin::Minimized sidebar setup:
                            if (isset($_COOKIE["sidebar_minimize_state"]) && $_COOKIE["sidebar_minimize_state"] === "on") {
                                1. "src/js/layout/sidebar.js" adds "sidebar_minimize_state" cookie value to save the sidebar minimize state.
                                2. Set data-kt-app-sidebar-minimize="on" attribute for body tag.
                                3. Set data-kt-toggle-state="active" attribute to the toggle element with "kt_app_sidebar_toggle" id.
                                4. Add "active" class to to sidebar toggle element with "kt_app_sidebar_toggle" id.
                            }
                        -->
                        <div id="kt_app_sidebar_toggle"
                            class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
                            data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
                            data-kt-toggle-name="app-sidebar-minimize">
                            <i class="ki-duotone ki-black-left-line fs-3 rotate-180">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                        <!--end::Sidebar toggle-->
                    </div>
                    <!--end::Logo-->
                    <!--begin::sidebar menu-->
                    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
                        <!--begin::Menu wrapper-->
                        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">
                            <!--begin::Scroll wrapper-->
                            @include('admin.dashboard.layout.sidebar')
                            <!--end::Scroll wrapper-->
                        </div>
                        <!--end::Menu wrapper-->
                    </div>
                    <!--end::sidebar menu-->
                    <!--begin::Footer-->
                    <div class="app-sidebar-footer flex-column-auto pt-2 pb-6 px-6" id="kt_app_sidebar_footer">
                        <a href="https://preview.keenthemes.com/html/metronic/docs"
                            class="btn btn-flex flex-center btn-custom btn-primary overflow-hidden text-nowrap px-0 h-40px w-100"
                            data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-dismiss-="click"
                            title="200+ in-house components and 3rd-party plugins">
                            <span class="btn-label">Docs & Components</span>
                            <i class="ki-duotone ki-document btn-icon fs-2 m-0">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </a>
                    </div>
                    <!--end::Footer-->
                </div>
                <!--end::Sidebar-->
                <!--begin::Main-->
                <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <!--begin::Content wrapper-->
                    <div class="d-flex flex-column flex-column-fluid">
                        <!--begin::Toolbar-->
                        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
                            <!--begin::Toolbar container-->
                            <div id="kt_app_toolbar_container"
                                class="app-container container-fluid d-flex flex-stack flex-wrap flex-md-nowrap">
                                <!--begin::Toolbar start-->
                                <div class="d-flex align-items-center flex-shrink-0 me-5">
                                    <!--begin::Label-->
                                    <span class="fs-7 fw-bold text-gray-700 pe-4 d-none d-md-block">Team:</span>
                                    <!--end::Label-->
                                    <!--begin::Users-->
                                    <div class="symbol-group symbol-hover flex-shrink-0 me-2">
                                        <!--begin::User-->
                                        <div class="symbol symbol-circle symbol-35px">
                                            <div class="symbol-label fw-bold bg-warning text-inverse-warning">A</div>
                                        </div>
                                        <!--end::User-->
                                        <!--begin::User-->
                                        <div class="symbol symbol-circle symbol-35px">
                                            <img src="assets/media/avatars/300-1.jpg" alt="" />
                                        </div>
                                        <!--end::User-->
                                        <!--begin::User-->
                                        <div class="symbol symbol-circle symbol-35px">
                                            <img src="assets/media/avatars/300-2.jpg" alt="" />
                                        </div>
                                        <!--end::User-->
                                        <!--begin::User-->
                                        <div class="symbol symbol-circle symbol-35px">
                                            <div class="symbol-label fw-bold bg-primary text-inverse-primary">S</div>
                                        </div>
                                        <!--end::User-->
                                        <!--begin::User-->
                                        <div class="symbol symbol-circle symbol-35px">
                                            <img src="assets/media/avatars/300-5.jpg" alt="" />
                                        </div>
                                        <!--end::User-->
                                        <!--begin::User-->
                                        <div class="symbol symbol-circle symbol-35px">
                                            <div class="symbol-label fw-bold bg-danger text-inverse-danger">P</div>
                                        </div>
                                        <!--end::User-->
                                        <!--begin::User-->
                                        <div class="symbol symbol-circle symbol-35px">
                                            <img src="assets/media/avatars/300-20.jpg" alt="" />
                                        </div>
                                        <!--end::User-->
                                    </div>
                                    <!--end::Users-->
                                    <!--begin::Button-->
                                    <div data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover"
                                        title="Invite a team member">
                                        <a href="#" class="btn btn-sm btn-icon" data-bs-toggle="modal"
                                            data-bs-target="#kt_modal_invite_friends">
                                            <i class="ki-duotone ki-plus-square fs-2hx text-success">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </a>
                                    </div>
                                    <!--end::Button-->
                                </div>
                                <!--end::Toolbar start-->
                                <!--begin::Toolbar end-->
                                <div class="d-flex align-items-center overflow-visible">
                                    <!--begin::Search-->
                                    <div class="position-relative my-1 profile-search-container" style="min-width: 300px; z-index: 1050;">
                                        <i
                                            class="ki-duotone ki-magnifier fs-2 text-gray-500 position-absolute top-50 translate-middle ps-10">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <input type="text"
                                            class="form-control form-control-lg form-control-solid ps-10"
                                            name="search_profiles" value="" placeholder="البحث عن الملفات..."
                                            autocomplete="off" style="width: 300px; font-size: 14px;" />
                                    </div>
                                    <!--end::Search-->
                                    <!--begin::Separartor-->
                                    <div class="bullet bg-secondary h-35px w-1px mx-6"></div>
                                    <!--end::Separartor-->
                                    <!--begin::Label-->
                                    <span class="fs-7 fw-bold text-gray-700 flex-shrink-0 pe-4 d-none d-md-block">Sort
                                        By:</span>
                                    <!--end::Label-->
                                    <!--begin::Select-->
                                    <select class="form-select form-select-sm w-125px form-select-solid me-6"
                                        data-control="select2" data-placeholder="Latest" data-hide-search="true">
                                        <option value=""></option>
                                        <option value="1" selected="selected">Latest</option>
                                        <option value="2">In Progress</option>
                                        <option value="3">Done</option>
                                    </select>
                                    <!--end::Select-->
                                    <!--begin::Actions-->
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-sm btn-icon btn-light"
                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Enable row view">
                                            <i class="ki-duotone ki-row-horizontal fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                    </div>
                                    <!--end::Actions-->
                                </div>
                                <!--end::Toolbar end-->
                            </div>
                            <!--end::Toolbar container-->
                        </div>
                        <!--end::Toolbar-->
                        <!--begin::Content-->
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <!--begin::Content container-->

                            @yield('content')
                            <!--end::Content container-->
                        </div>
                        <!--end::Content-->
                    </div>
                    <!--end::Content wrapper-->
                    <!--begin::Footer-->
                    <div id="kt_app_footer" class="app-footer">
                        <!--begin::Footer container-->
                        <div
                            class="app-container container-fluid d-flex flex-column flex-md-row flex-center flex-md-stack py-3">
                            <!--begin::Copyright-->
                            <div class="text-dark order-2 order-md-1">
                                <span class="text-muted fw-semibold me-1">2023&copy;</span>
                                <a href="https://keenthemes.com" target="_blank"
                                    class="text-gray-800 text-hover-primary">Keenthemes</a>
                            </div>
                            <!--end::Copyright-->
                            <!--begin::Menu-->
                            <ul class="menu menu-gray-600 menu-hover-primary fw-semibold order-1">
                                <li class="menu-item">
                                    <a href="https://keenthemes.com" target="_blank" class="menu-link px-2">About</a>
                                </li>
                                <li class="menu-item">
                                    <a href="https://devs.keenthemes.com" target="_blank"
                                        class="menu-link px-2">Support</a>
                                </li>
                                <li class="menu-item">
                                    <a href="https://1.envato.market/EA4JP" target="_blank"
                                        class="menu-link px-2">Purchase</a>
                                </li>
                            </ul>
                            <!--end::Menu-->
                        </div>
                        <!--end::Footer container-->
                    </div>
                    <!--end::Footer-->
                </div>
                <!--end:::Main-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Page-->
    </div>
    <!--end::App-->
    {{-- our modal --}}

    @include('admin.dashboard.component.cropper')
    <!--end::Modal - Users Search-->
    <!--end::Modals-->
    <!--begin::Javascript-->
    <script>
        var hostUrl = "{{ asset('admin/assets/') }}";
    </script>


    <!--begin::Global Javascript Bundle (mandatory for all pages)-->
    <script src="{{ asset('admin/assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('admin/assets/js/scripts.bundle.js') }}"></script>
    <!--end::Global Javascript Bundle-->

    <!--begin::Vendors Javascript (used for this page only)-->
    <script src="{{ asset('admin/assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    <script src="{{ asset('admin/assets/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>

    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/radar.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/map.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/continentsLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/usaLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZonesLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZoneAreasLow.js"></script>
    <!--end::Vendors Javascript-->

    <!--begin::Custom Javascript (used for this page only)-->
    <script src="{{ asset('admin/assets/js/custom/account/settings/signin-methods.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/account/settings/profile-details.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/account/settings/deactivate-account.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/pages/user-profile/general.js') }}"></script>

    <script src="{{ asset('admin/assets/js/widgets.bundle.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/widgets.js') }}"></script>


    <script src="{{ asset('admin/assets/js/custom/utilities/modals/upgrade-plan.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/create-app.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/new-target.js') }}"></script>

    {{-- Offer-a-deal scripts with error handling --}}
    <script>
        // Function to safely load scripts
        function loadScriptSafely(src, callback) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = function() {
                console.log('Successfully loaded: ' + src);
                if (callback) callback();
            };
            script.onerror = function() {
                console.warn('Failed to load script: ' + src + ' - continuing without it');
                if (callback) callback();
            };
            document.head.appendChild(script);
        }

        // Load offer-a-deal scripts with error handling
        var offerDealScripts = [
            '{{ asset("admin/assets/js/custom/utilities/modals/offer-a-deal/type.js") }}',
            '{{ asset("admin/assets/js/custom/utilities/modals/offer-a-deal/details.js") }}',
            '{{ asset("admin/assets/js/custom/utilities/modals/offer-a-deal/finance.js") }}',
            '{{ asset("admin/assets/js/custom/utilities/modals/offer-a-deal/complete.js") }}',
            '{{ asset("admin/assets/js/custom/utilities/modals/offer-a-deal/main.js") }}'
        ];

        // Load scripts sequentially with error handling
        var loadNext = function(index) {
            if (index < offerDealScripts.length) {
                loadScriptSafely(offerDealScripts[index], function() {
                    loadNext(index + 1);
                });
            }
        };

        // Start loading after a short delay
        setTimeout(function() {
            loadNext(0);
        }, 100);
    </script>

    <script src="{{ asset('admin/assets/js/custom/utilities/modals/two-factor-authentication.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/users-search.js') }}"></script>
    <!--end::Custom Javascript-->
    <script src="{{ asset('admin/assets/plugins/custom/cropper/cropper.bundle.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <!--begin::Theme Mode Script-->

    <script>
        var defaultThemeMode = "light";
        var themeMode;

        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }

            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }

            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
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

    <!-- Profile Search Suggestions JS -->
    <script src="{{ asset('js/profile-search-suggestions.js') }}"></script>


    <!--end::Theme Mode Script-->
    @yield('scripts')
    @stack('scriptsCode')

    <!--end::Custom Javascript-->
    <!--end::Javascript-->

</body>
<!--end::Body-->

</html>
