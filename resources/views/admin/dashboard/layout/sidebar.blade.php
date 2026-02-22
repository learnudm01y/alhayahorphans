<div id="kt_app_sidebar_menu_scroll" class="scroll-y my-5 mx-3" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer" data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">
    @php
        $recordsSectionPermissions = [
            'عرض قسم إدارة التسجيلات',
            'عرض البيانات المدخلة',
            'إدارة طلبات المستخدمين',
            'إدخال بيانات التسجيلات',
        ];

        $categoriesSectionPermissions = [
            'عرض قسم إدارة التصنيفات',
            'إدارة الدرجة العلمية',
            'إدارة صلة القرابة',
            'إدارة حالة المساعدة',
            'إدارة أسماء البنوك',
            'إدارة أسماء المدن',
            'إدارة العملات',
            'إدارة أسباب الوفاة',
            'إدارة حالة النزوح',
            'إدارة أنواع الوثائق',
            'إدارة الحالة الوظيفية',
            'إدارة الأقسام الرئيسية',
            'إدارة الحالة الصحية',
            'إدارة احتياجات المكفول',
            'إدارة جوانب الإبداع',
            'إدارة حالة المنزل',
            'إدارة الحالة الاجتماعية',
            'إدارة المحافظات',
            'إدارة حالة الطلب',
            'إدارة حالة الكفالة',
            'إدارة نوع السكن',
            'إدارة نوع الكفالة',
        ];

        $sponsorsSectionPermissions = [
            'عرض قسم إدارة الجمعيات',
            'إدارة الجمعيات',
            'تحديث بيانات الجمعيات',
        ];

        $sponsorshipSectionPermissions = [
            'عرض قسم الكفالات',
            'عرض المكفولين',
            'عرض غير المكفولين',
        ];

        $rolesSectionPermissions = [
            'عرض قسم إدارة الصلاحيات',
            'المستخدمين',
            'إنشاء مستخدم',
            'صلاحيات المستخدمين',
            'إنشاء صلاحيات المستخدمين',
        ];

        $civilRegistrySectionPermissions = [
            'عرض قسم إدارة السجل المدني',
            'السجل المدني الجديد',
            'إضافة مواطن',
            'عرض السجل المدني القديم',
        ];

        $filesSectionPermissions = [
            'عرض قسم إدارة الملفات',
            'الوصول لبوابة الملفات',
            'إدارة المجلدات',
            'رفع ملفات اكسل',
            'إدارة الملفات المكررة',
        ];

        $toolsSectionPermissions = [
            'عرض قسم أدوات النظام',
            'اختبار سرعة الإنترنت',
            'OpenSpeedTest',
        ];
    @endphp
    <!--begin::Menu-->
    <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6" id="#kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false">
        @canany(['الوصول للصفحة الرئيسية', 'عرض قسم إدارة التسجيلات', 'عرض قسم إدارة التصنيفات', 'عرض قسم إدارة الجمعيات', 'عرض قسم الكفالات', 'عرض قسم إدارة الصلاحيات', 'عرض قسم إدارة السجل المدني', 'عرض قسم إدارة الملفات', 'عرض قسم أدوات النظام'])
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
        @endcanany
        <!--begin:Menu item-->
        @canany($recordsSectionPermissions)
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
                @canany(['عرض قسم إدارة التسجيلات', 'عرض البيانات المدخلة'])
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
                @endcanany
                <!--begin:Menu item-->
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة التسجيلات', 'إدارة طلبات المستخدمين'])
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
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة التسجيلات', 'إدخال بيانات التسجيلات'])
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
                @endcanany
                <!--end:Menu item-->
            </div>
            <!--end:Menu sub-->
        </div>
        @endcanany
        <!--end:Menu item-->
        <!--begin:Menu item-->
        @canany($categoriesSectionPermissions)
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
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة الدرجة العلمية'])
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
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة صلة القرابة'])
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
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة حالة المساعدة'])
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
                @endcanany
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة أسماء البنوك'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة أسماء المدن'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة العملات'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة أسباب الوفاة'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة حالة النزوح'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة أنواع الوثائق'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة الحالة الوظيفية'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة الأقسام الرئيسية'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة الحالة الصحية'])
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
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة احتياجات المكفول'])
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.orphan_needs.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-hand-holding-heart"></i>
                        </span>
                        <span class="menu-title"> احتياجات المكفول </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة جوانب الإبداع'])
                <div class="menu-item">
                    <!--begin:Menu link-->
                    <a class="menu-link" href="{{ route('admin.creativity_aspects.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-lightbulb"></i>
                        </span>
                        <span class="menu-title"> جوانب الإبداع </span>
                    </a>
                    <!--end:Menu link-->
                </div>
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة حالة المنزل'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة الحالة الاجتماعية'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة المحافظات'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة حالة الطلب'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة حالة الكفالة'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة نوع السكن'])
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
                @endcanany
                <!--end:Menu item-->
                <!--end:Menu item-->
                @canany(['عرض قسم إدارة التصنيفات', 'إدارة نوع الكفالة'])
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
                @endcanany
                <!--end:Menu item-->
            </div>
        </div>
        @endcanany
        <!--end:Menu item-->
        <!--begin:Menu item-->
        @canany($sponsorsSectionPermissions)
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-hands-helping fs-2"></i>
                </span>
                <span class="menu-title">إدارة الجمعيات</span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                @canany(['عرض قسم إدارة الجمعيات', 'إدارة الجمعيات'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.sponsors.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-list"></i>
                        </span>
                        <span class="menu-title"> إدارة الجمعيات </span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم إدارة الجمعيات', 'تحديث بيانات الجمعيات'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.sponsors.fields-management') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-cogs"></i>
                        </span>
                        <span class="menu-title"> تحديث البيانات </span>
                    </a>
                </div>
                @endcanany
            </div>
        </div>
        @endcanany
        <!--end:Menu item-->
        <!--begin:Menu item-->
        @canany($sponsorshipSectionPermissions)
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-hand-holding-heart fs-2"></i>
                </span>
                <span class="menu-title">قسم الكفالات</span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                @canany(['عرض قسم الكفالات', 'عرض المكفولين'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('sponsorships.sponsored') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-users"></i>
                        </span>
                        <span class="menu-title">الأشخاص المكفولين</span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم الكفالات', 'عرض غير المكفولين'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('sponsorships.unsponsored') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-user-times"></i>
                        </span>
                        <span class="menu-title">الأشخاص غير المكفولين</span>
                    </a>
                </div>
                @endcanany
            </div>
        </div>
        @endcanany
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
        @canany($rolesSectionPermissions)
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
                @canany(['عرض قسم إدارة الصلاحيات', 'المستخدمين'])
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
                @endcanany
                @canany(['عرض قسم إدارة الصلاحيات', 'المستخدمين'])
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
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة الصلاحيات', 'إنشاء مستخدم'])
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
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة الصلاحيات', 'صلاحيات المستخدمين'])
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
                @endcanany
                <!--end:Menu item-->
                <!--begin:Menu item-->
                @canany(['عرض قسم إدارة الصلاحيات', 'إنشاء صلاحيات المستخدمين'])
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
                @endcanany
                <!--end:Menu item-->
            </div>
            <!--end:Menu sub-->
        </div>
        @endcanany
        <!--end:Menu item-->
        <!--begin:Menu item-->
        @canany($civilRegistrySectionPermissions)
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-id-card-alt fs-2"></i>
                </span>
                <span class="menu-title"> إدارة السجل المدني </span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                @canany(['عرض قسم إدارة السجل المدني', 'السجل المدني الجديد'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('civil-registry.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-user-friends"></i>
                        </span>
                        <span class="menu-title"> السجل المدني الجديد </span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم إدارة السجل المدني', 'إضافة مواطن'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('civil-registry.create') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-user-plus"></i>
                        </span>
                        <span class="menu-title"> إضافة مواطن </span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم إدارة السجل المدني', 'عرض السجل المدني القديم'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.index.civilian') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-database"></i>
                        </span>
                        <span class="menu-title"> النظام القديم </span>
                    </a>
                </div>
                @endcanany
            </div>
        </div>
        @endcanany
        <!--end:Menu item-->
        <!--begin:Menu item-->
        @canany($filesSectionPermissions)
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-folder-open"></i>
                </span>
                <span class="menu-title">إدارة الملفات</span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                @canany(['عرض قسم إدارة الملفات', 'الوصول لبوابة الملفات'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.file.manager') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        <span class="menu-title"> البوابة الرئيسية </span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم إدارة الملفات', 'إدارة المجلدات'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.manage.folders.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        <span class="menu-title"> ادارة المجلدات  </span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم إدارة الملفات', 'رفع ملفات اكسل'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.file.excel.gateway.sidebar') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-file-excel"></i>
                        </span>
                        <span class="menu-title"> رفع ملفات اكسل  </span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم إدارة الملفات', 'إدارة الملفات المكررة'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.duplicate.files.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-copy"></i>
                        </span>
                        <span class="menu-title"> إدارة الملفات المكررة </span>
                    </a>
                </div>
                @endcanany
            </div>
        </div>
        @endcanany
        <!--end:Menu item-->
        <!--begin:Menu item-->
        @canany($toolsSectionPermissions)
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
            <span class="menu-link">
                <span class="menu-icon">
                    <i class="fas fa-tools"></i>
                </span>
                <span class="menu-title">أدوات النظام</span>
                <span class="menu-arrow"></span>
            </span>
            <div class="menu-sub menu-sub-accordion">
                @canany(['عرض قسم أدوات النظام', 'اختبار سرعة الإنترنت'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.speedtest.standalone') }}">
                        <span class="menu-bullet">
                             <i class="fas fa-tachometer-alt"></i>
                        </span>
                           <span class="menu-title"> اختبار سرعة الإنترنت </span>
                    </a>
                </div>
                @endcanany
                @canany(['عرض قسم أدوات النظام', 'OpenSpeedTest'])
                <div class="menu-item">
                    <a class="menu-link" href="{{ route('admin.openspeedtest.index') }}">
                        <span class="menu-bullet">
                            <i class="fas fa-rocket"></i>
                        </span>
                        <span class="menu-title"> OpenSpeedTest </span>
                    </a>
                </div>
                @endcanany
            </div>
        </div>
        @endcanany
        <!--end:Menu item-->
    </div>
    <!--end::Menu-->
</div>
