<div id="kt_app_sidebar_menu_scroll" class="scroll-y my-5 mx-3" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer" data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">
    <!--begin::Menu-->
    <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6" id="#kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false">
        <div class="menu-item">
            <!--begin:Menu link-->
            <a class="menu-link" href="{{ route('admin.dashboard') }}">
                <span class="menu-icon">
                    <i class="fas fa-chart-pie"></i>
                </span>
                <span class="menu-title"> الصفحة الرئيسية </span>
            </a>
            <!--end:Menu link-->
        </div>
        <!--begin:Menu item-->
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <!--begin:Menu link-->
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="ki-duotone ki-element-11 fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                </span>
                <span class="menu-title"> إدارة التسجيلات </span>
                <span class="menu-arrow"></span>
            </span>
            <!--end:Menu link-->
            <!--begin:Menu sub-->
            <div class="menu-sub menu-sub-accordion">
                <!--begin:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="../../demo1/dist/index.html">
                        <span class="menu-bullet">
                            <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title"> نماذج الادخال </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
            </div>
            <!--end:Menu sub-->
        </div>
        <!--end:Menu item-->
        <!--begin:Menu item-->
        <div class="menu-item pt-5">
            <!--begin:Menu content-->
            <div class="menu-content">
                <span class="menu-heading fw-bold text-uppercase fs-7">Pages</span>
            </div>
            <!--end:Menu content-->
        </div>
        <!--end:Menu item-->
        <!--begin:Menu item-->
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <!--begin:Menu link-->
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-user-shield fs-2"></i>
                </span>
                <span class="menu-title"> إدارة الصلاحيات </span>
                <span class="menu-arrow"></span>
            </span>
            <!--end:Menu link-->
            <!--begin:Menu sub-->
            <div class="menu-sub menu-sub-accordion">
                <!--begin:Menu item-->
                @can('المستخدمين')
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.role.management101') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-users"></i>
                        </span>
                        <span class="menu-title"> الإداريين </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                @endcan
                @can('المستخدمين')
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.role.management102') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-users"></i>
                        </span>
                        <span class="menu-title"> مستخدمين عاديين </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                @endcan
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @can('إنشاء مستخدم')
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('users.create') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-user-plus"></i>
                        </span>
                        <span class="menu-title"> إنشاء مستخدم </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                @endcan
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @can('صلاحيات المستخدمين')
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('roles.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-user-lock"></i>
                        </span>
                        <span class="menu-title"> صلاحيات المستخدمين </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                @endcan
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @can('إنشاء صلاحيات المستخدمين')
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('roles.create') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-plus-circle"></i>
                        </span>
                        <span class="menu-title"> إنشاء صلاحيات المستخدمين </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                @endcan
                <!--end:Menu item-->
            </div>
            <!--end:Menu sub-->
        </div>
        <!--end:Menu item-->
        <!--begin:Menu item-->
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-id-card-alt fs-2"></i>
                </span>
                <span class="menu-title"> إدارة السجل المدني </span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.index.civilian') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-user-friends"></i>
                        </span>
                        <span class="menu-title"> المواطنين </span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.persons.create') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-user-plus"></i>
                        </span>
                        <span class="menu-title"> إضافة مواطن </span>
                    </a>
                </div>
            </div>
        </div>
        <!--end:Menu item-->
    </div>
    <!--end::Menu-->
</div>
