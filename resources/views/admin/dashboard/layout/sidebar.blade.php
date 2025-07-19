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
                    <i class="fas fa-database fs-2"></i>
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
                    <a class="menu-link" href="{{ route('admin.records.management') }}">
                        <span class="menu-bullet">
                            <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title"> البيانات المدخلة  </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--begin:Menu item-->
                <!--end:Menu item-->
                <!--begin:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.manage.user.requests.index') }}">
                        <span class="menu-bullet">
                            <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title"> إدارة طلبات المستخدمين  </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--begin:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.records.management.create') }}">
                        <span class="menu-bullet">
                            <span class="bullet bullet-dot"></span>
                        </span>
                        <span class="menu-title"> إدخال بيانات   </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
            </div>
            <!--end:Menu sub-->
        </div>
        <!--end:Menu item-->
        <!--begin:Menu item-->
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <!--begin:Menu link-->
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-tags fs-2"></i>
                </span>
                <span class="menu-title"> إدارة التصنيفات </span>
                <span class="menu-arrow"></span>
            </span>
            <!--end:Menu link-->
            <!--begin:Menu sub-->
            <div class="menu-sub menu-sub-accordion">
                <!--begin:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.category.management.academicdegree') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-graduation-cap"></i>
                        </span>
                        <span class="menu-title"> الدرجة العلمية </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--begin:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.CategoryOfRelation_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-graduation-cap"></i>
                        </span>
                        <span class="menu-title"> صلة القرابة  </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--begin:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.aid_status.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-hands-helping"></i>
                        </span>
                        <span class="menu-title"> حالة المساعدة </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.bank_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-university"></i>
                        </span>
                        <span class="menu-title"> اسماء البنوك </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.city_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-city"></i>
                        </span>
                        <span class="menu-title"> اسماء المدن </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.CurrencyType_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-money-bill-wave"></i>
                        </span>
                        <span class="menu-title"> العملات  </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.DeathReason_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-book-dead"></i>
                        </span>
                        <span class="menu-title"> أسباب الوفاة  </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.DisplacementStatus_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-walking"></i>
                        </span>
                        <span class="menu-title"> حالة النزوح   </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.DocumentType_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-file-alt"></i>
                        </span>
                        <span class="menu-title"> انواع الوثائق  </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.Employment_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-briefcase"></i>
                        </span>
                        <span class="menu-title"> الحالة وظيفية   </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.GeneralCategory_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-th-list"></i>
                        </span>
                        <span class="menu-title"> الاقسام الرئيسية للموقع   </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.HealthStatus_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-heartbeat"></i>
                        </span>
                        <span class="menu-title"> الحالة الصحية   </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.HousingStatus_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-home"></i>
                        </span>
                        <span class="menu-title"> حالة المنزل    </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.MaritalStatus_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-ring"></i>
                        </span>
                        <span class="menu-title"> الحالة الإجتماعية </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.Province_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-map-marked-alt"></i>
                        </span>
                        <span class="menu-title"> المحافظات</span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.RequestStatus_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-clipboard-check"></i>
                        </span>
                        <span class="menu-title"> حالة الطلب</span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.SponsorshipStatus_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-hand-holding-heart"></i>
                        </span>
                        <span class="menu-title"> حالة الكفالة</span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.TypeOfAccommodation_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-building"></i>
                        </span>
                        <span class="menu-title"> نوع السكن </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                <!--end:Menu item-->
                <!--end:Menu item-->
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.TypeOfGuarantee_name.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                        <span class="menu-title"> نوع الكفالة </span>
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
        <!--begin:Menu item-->
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-folder-open"></i>
                </span>
                <span class="menu-title">إدارة الملفات</span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.file.manager') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        <span class="menu-title"> البوابة الرئيسية </span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.manage.folders.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        <span class="menu-title"> ادارة المجلدات  </span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.file.excel.gateway.sidebar') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-file-excel"></i>
                        </span>
                        <span class="menu-title"> رفع ملفات اكسل  </span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.duplicate.files.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-copy"></i>
                        </span>
                        <span class="menu-title"> إدارة الملفات المكررة </span>
                    </a>
                </div>
            </div>
        </div>
        <!--end:Menu item-->
        <!--begin:Menu item-->
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-tools"></i>
                </span>
                <span class="menu-title">أدوات النظام</span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.speedtest.standalone') }}">
                        <span class="menu-bullet">
                             <i class="fas fa-tachometer-alt"></i>
                        </span>
                           <span class="menu-title"> اختبار سرعة الإنترنت </span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.openspeedtest.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-rocket"></i>
                        </span>
                        <span class="menu-title"> OpenSpeedTest </span>
                    </a>
                </div>
            </div>
        </div>
        <!--end:Menu item-->
    </div>
    <!--end::Menu-->
</div>
