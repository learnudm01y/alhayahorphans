<!DOCTYPE html>
<html direction="rtl" dir="rtl" style="direction: rtl">
<!--begin::Head-->

<head>
    <base href="../" />
    <title> بوابة الإدخال للمستخدمين   </title>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link href="{{ asset('admin/assets/plugins/global/plugins.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('admin/assets/css/style.bundle.rtl.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
    <link href="{{ asset('admin/assets/plugins/custom/cropper/cropper.bundle.css') }}" rel="stylesheet"
        type="text/css" />
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

    </style>
    @stack('UserStylesGeneralRegistration')

    <style>
        /* تنسيق الشعار */
        .logo-container {
            position: fixed;
            top: 35px;
            left: 30%;
            transform: translateX(-50%);
            z-index: 9999;
            background: white;
            padding: 4px 8px;
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            max-width: 90%;
        }

        .logo-container img {
            height: 28px;
            width: auto;
            display: block;
            max-width: 100%;
        }

        @media (min-width: 481px) and (max-width: 768px) {
            .logo-container {
                top: 8px;
                padding: 5px 10px;
            }

            .logo-container img {
                height: 35px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .logo-container {
                top: 12px;
                padding: 6px 12px;
            }

            .logo-container img {
                height: 40px;
            }
        }

        @media (min-width: 1025px) {
            .logo-container {
                top: 20px;
                right: 20px;
                left: auto;
                transform: none;
                padding: 10px 15px;
                max-width: none;
            }

            .logo-container img {
                height: 50px;
            }
        }
    </style>

</head>
<body id="kt_app_body" data-kt-app-layout="dark-sidebar" data-kt-app-header-fixed="true"
    data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true"
    data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true"
    data-kt-app-sidebar-push-footer="true" data-kt-app-toolbar-enabled="true" data-kt-app-toolbar-fixed="true"
    class="app-default">

    <!--begin::Logo-->
    <div class="logo-container">
        <img src="{{ asset('uploads/logo01.png') }}" alt="الحياة لتنمية الأسرة" title="Al Hayah For Family Development">
    </div>
    <!--end::Logo-->


        @yield('contentGeneralRegistration')


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
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/offer-a-deal/type.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/offer-a-deal/details.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/offer-a-deal/finance.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/offer-a-deal/complete.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/offer-a-deal/main.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/two-factor-authentication.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom/utilities/modals/users-search.js') }}"></script>
    <!--end::Custom Javascript-->
    <script src="{{ asset('admin/assets/plugins/custom/cropper/cropper.bundle.js') }}"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
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



    <!--end::Theme Mode Script-->
    {{-- @yield('scripts') --}}
    @stack('scriptsCodeUserRegistration')

    <!--end::Custom Javascript-->
    <!--end::Javascript-->

</body>
<!--end::Body-->

</html>

