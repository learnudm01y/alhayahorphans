@extends('admin.dashboard.toolbars.index')

@push('styles')
<style>
    /* إزالة جميع الهوامش والحشو بشكل قاسي */
    #kt_app_content {
        padding: 0 !important;
        margin: 0 !important;
    }

    #kt_app_content_container {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .card {
        margin: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .card-header {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
    }

    .card-body {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
    }

    /* جعل الجدول يملأ العرض بالكامل */
    #sponsorships-table_wrapper {
        width: 100% !important;
    }

    .dataTables_wrapper {
        width: 100% !important;
    }

    /* عرض أسماء المؤسسات بشكل عمودي */
    #sponsorships-table td:first-child > div,
    #sponsorships-table td:nth-child(2) > div {
        display: flex !important;
        flex-direction: column !important;
        gap: 5px !important;
        width: 100% !important;
    }

    #sponsorships-table td:first-child > div > div,
    #sponsorships-table td:nth-child(2) > div > div {
        display: block !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        margin-bottom: 3px !important;
        padding: 2px 5px !important;
        background-color: #f8f9fa !important;
        border-radius: 3px !important;
    }

    /* إصلاح Select2 للعربية - RTL */
    .select2-container--bootstrap5 .select2-selection--multiple:not(.form-select-sm):not(.form-select-lg) .select2-selection__choice .select2-selection__choice__display {
        margin-left: 0.1rem;
        font-size: 1.1rem;
        padding-right: 16px;
    }
    .select2-container--bootstrap5 .select2-dropdown .select2-results__option.select2-results__option--selected {
        background-color: var(--bs-component-hover-bg);
        color: var(--bs-component-hover-color);
        transition: color 0.2s ease;
        position: relative;
        padding-right: 32px;
    }
    .select2-container--bootstrap5 .select2-selection--single.form-select-solid .select2-selection__rendered {
        color: var(--bs-gray-700);
        padding-right: 43px;
    }

    /* تنسيق النقطة الخضراء لحالة "محدث" */
    .status-indicator-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        background-color: #50cd89;
        border-radius: 50%;
        margin-left: 6px;
        box-shadow: 0 0 6px rgba(80, 205, 137, 0.6);
        animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
        0%, 100% {
            box-shadow: 0 0 6px rgba(80, 205, 137, 0.6);
        }
        50% {
            box-shadow: 0 0 12px rgba(80, 205, 137, 0.9);
        }
    }

    /* تنسيق علامة الصح السوداء لحالة "ذهب للصرف" */
    .status-check-mark {
        display: inline-block;
        font-weight: bold;
        color: #000000;
        margin-left: 6px;
        font-size: 14px;
        text-shadow: 0 0 2px rgba(0, 0, 0, 0.3);
    }

    /* تنسيق زر الاعتماد - أخضر غامق */
    .btn-dark-green {
        background-color: #1a5f3b !important;
        border-color: #1a5f3b !important;
        color: #ffffff !important;
        font-weight: 600;
    }

    .btn-dark-green:disabled {
        background-color: #1a5f3b !important;
        border-color: #1a5f3b !important;
        opacity: 0.8;
        cursor: not-allowed;
    }

    .btn-outline-success:hover {
        background-color: #198754;
        border-color: #198754;
        color: #ffffff;
    }

    /* تنسيق النقطة في القائمة المنسدلة */
    .change-sponsorship-status option {
        padding: 5px 10px;
    }
</style>
@endpush

@section('content')
<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-fluid">
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" id="kt_sponsorship_search"
                               class="form-control form-control-solid w-250px ps-13"
                               placeholder="بحث في الكفالات..." />
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end gap-2" data-kt-sponsorship-table-toolbar="base">
                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                            <i class="ki-duotone ki-file-up fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            رفع ملف Excel
                        </button>

                        <!-- أزرار التصدير -->
                        <button type="button" class="btn btn-success btn-sm" id="export_excel_full_btn">
                            <i class="ki-duotone ki-file-down fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            تصدير كامل
                        </button>

                        <button type="button" class="btn btn-info btn-sm" id="export_excel_login_btn">
                            <i class="ki-duotone ki-profile-user fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            تصدير تسجيل دخول
                        </button>

                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sponsorshipModal">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            إضافة كفالة جديدة
                        </button>
                    </div>
                </div>
            </div>
            <!--end::Card header-->

            <!--begin::Filters-->
            <div class="card-body border-top pt-6">
                <div class="row g-3 mb-5">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">المؤسسات الكافلة</label>
                        <select class="form-select form-select-solid" id="filter_sponsor" data-control="select2" data-placeholder="جميع المؤسسات" data-allow-clear="true">
                            <option value="">جميع المؤسسات</option>
                            @foreach($sponsors as $sponsor)
                                <option value="{{ $sponsor->id }}">{{ $sponsor->sponsor_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">نوع الكفالة</label>
                        <select class="form-select form-select-solid" id="filter_sponsorship_type" data-control="select2" data-placeholder="جميع الأنواع" data-allow-clear="true">
                            <option value="">جميع الأنواع</option>
                            @foreach($sponsorshipTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">حالة الكفالة</label>
                        <select class="form-select form-select-solid" id="filter_sponsorship_status" data-control="select2" data-placeholder="جميع الحالات" data-allow-clear="true">
                            <option value="">جميع الحالات</option>
                            @foreach($sponsorshipStatuses as $status)
                                <option value="{{ $status->id }}">{{ $status->description }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-light btn-sm me-2" id="reset_filters">
                        <i class="ki-duotone ki-arrows-circle fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        إعادة تعيين
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="apply_filters">
                        <i class="ki-duotone ki-filter fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        تطبيق الفلاتر
                    </button>
                </div>
            </div>
            <!--end::Filters-->

            <!--begin::Card body-->
            <div class="card-body pt-0">
                {{ $dataTable->table(['class' => 'table align-middle table-row-dashed fs-6 gy-5']) }}
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
    </div>
</div>
<!--end::Content-->

<!-- Modal for Import Excel -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">رفع ملف Excel</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <form id="importExcelForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body py-10 px-lg-17">

                    <!--begin::تعليمات-->
                    <div class="alert alert-info d-flex align-items-center mb-7">
                        <i class="ki-duotone ki-information-5 fs-2x text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">تعليمات رفع الملف</h5>
                                    <span>يجب أن يحتوي ملف Excel على الأعمدة التالية بالترتيب:</span>
                                    <small class="text-muted mt-2 d-block">
                                        <strong>اسم الكافل*</strong> (مطلوب - سيتم إدخاله في عمود اسم الكافل)، <strong>هوية المعيل*</strong> (مطلوب)، هوية اليتيم، اسم اليتيم، اسم المعيل، رقم الملف الداخلي،
                                        رقم الملف الخارجي، المؤسسة الراعية، تاريخ بدء الكفالة، تاريخ انتهاء الكفالة،
                                        مدة الكفالة، المبلغ الشهري، ملاحظات، <strong>المحفظة</strong> (اسم البنك)، IBAN دولار، IBAN شيكل،
                                        رقم الهاتف، رقم الحساب
                                    </small>
                                    <div class="alert alert-warning mt-3 mb-0 py-2">
                                        <small>
                                            <strong>⚠️ مهم:</strong>
                                            <strong>"هوية المعيل"</strong> = رقم هوية ولي الأمر (للتحقق من وجوده) |
                                            <strong>"المحفظة"</strong> = اسم البنك
                                        </small>
                                    </div>
                                </div>
                                <a href="{{ asset('templates/sponsorships_import_template.xlsx') }}"
                                   class="btn btn-sm btn-light-info" download>
                                    <i class="ki-duotone ki-file-down fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    تحميل القالب
                                </a>
                            </div>
                        </div>
                    </div>
                    <!--end::تعليمات-->

                    <!--begin::الفلاتر-->
                    <div class="mb-7">
                        <h3 class="fw-bold text-gray-900 mb-5">إعدادات الاستيراد</h3>
                        <div class="separator mb-5"></div>
                    </div>

                    <div class="row g-9 mb-7">
                        <div class="col-md-4 fv-row">
                            <label class="fs-6 fw-semibold mb-2 required">المؤسسة الكافلة</label>
                            <select class="form-select form-select-solid" id="import_sponsor_id" name="sponsor_id" required>
                                <option value="">اختر المؤسسة</option>
                                @foreach($sponsors as $sponsor)
                                    <option value="{{ $sponsor->id }}">{{ $sponsor->sponsor_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 fv-row">
                            <label class="fs-6 fw-semibold mb-2 required">نوع الكفالة</label>
                            <select class="form-select form-select-solid" id="import_sponsorship_type_id" name="sponsorship_type_id" required>
                                <option value="">اختر النوع</option>
                                @foreach($sponsorshipTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->description }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 fv-row">
                            <label class="fs-6 fw-semibold mb-2 required">حالة الكفالة</label>
                            <select class="form-select form-select-solid" id="import_sponsorship_status_id" name="sponsorship_status_id" required>
                                <option value="">اختر الحالة</option>
                                @foreach($sponsorshipStatuses as $status)
                                    <option value="{{ $status->id }}">{{ $status->description }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <!--end::الفلاتر-->

                    <!--begin::رفع الملف-->
                    <div class="row g-9 mb-7">
                        <div class="col-md-12 fv-row">
                            <label class="fs-6 fw-semibold mb-2 required">ملف Excel</label>
                            <input type="file" class="form-control form-control-solid"
                                   id="excel_file" name="excel_file"
                                   accept=".xlsx,.xls" required>
                            <div class="form-text">يدعم ملفات Excel فقط (.xlsx, .xls)</div>
                        </div>
                    </div>
                    <!--end::رفع الملف-->

                    <!--begin::Progress Bar-->
                    <div id="import_progress_container" style="display: none;">
                        <div class="progress" style="height: 30px;">
                            <div id="import_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%">
                                <span id="import_progress_text">0%</span>
                            </div>
                        </div>
                        <div id="import_status_text" class="text-center mt-3 fw-bold"></div>
                    </div>
                    <!--end::Progress Bar-->

                    <!--begin::نتائج الفحص-->
                    <div id="validation_results_container" style="display: none;">
                        <div class="separator my-5"></div>
                        <h3 class="fw-bold text-gray-900 mb-5">نتائج الفحص</h3>

                        <div id="validation_results"></div>

                        <div class="alert alert-success d-flex align-items-center mt-5" id="validation_success" style="display: none !important;">
                            <i class="ki-duotone ki-shield-tick fs-2x text-success me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>
                                <h5 class="mb-1">✅ الملف جاهز للاستيراد</h5>
                                <span>جميع البيانات صحيحة ويمكنك الآن بدء عملية الاستيراد</span>
                            </div>
                        </div>
                    </div>
                    <!--end::نتائج الفحص-->

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning" id="check_file_btn">
                        <i class="ki-duotone ki-shield-search fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        فحص الملف
                    </button>
                    <button type="button" class="btn btn-primary" id="import_submit_btn" style="display: none;">
                        <i class="ki-duotone ki-file-up fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        تنفيذ الاستيراد
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit Sponsorship -->
<div class="modal fade" id="sponsorshipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modalTitle">إضافة كفالة جديدة</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <form id="sponsorshipForm">
                @csrf
                <input type="hidden" id="sponsorship_id" name="sponsorship_id">
                <input type="hidden" id="form_method" name="_method" value="POST">

                <div class="modal-body py-10 px-lg-17">
                    <div class="scroll-y me-n7 pe-7" style="max-height: 600px; overflow-y: auto;">

                        <!--begin::معلومات الكافل-->
                        <div class="mb-7">
                            <h3 class="fw-bold text-gray-900 mb-5">معلومات الكافل</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">المؤسسات الكافلة (يمكن اختيار أكثر من مؤسسة)</label>
                                <select class="form-select form-select-solid" name="sponsor_ids[]" id="sponsor_ids" multiple>
                                    @foreach($sponsors as $sponsor)
                                        <option value="{{ $sponsor->id }}">{{ $sponsor->sponsor_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">الكافل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="اسم الكافل" name="sponsoring_organization"
                                       id="sponsoring_organization" />
                            </div>
                        </div>

                        <!--begin::معلومات المكفول-->
                        <div class="mb-7 mt-10">
                            <h3 class="fw-bold text-gray-900 mb-5">معلومات المكفول</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">
                                    <i class="bi bi-file-earmark-text text-primary me-1"></i>
                                    رقم الملف الداخلي
                                    <span class="badge badge-light-success ms-2">يُولّد تلقائياً</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light-primary">
                                        <i class="bi bi-hash text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-solid bg-light-primary fw-bold"
                                           placeholder="سيتم توليده تلقائياً" name="internal_file_number"
                                           id="internal_file_number" readonly />
                                </div>
                                <div class="form-text text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    رقم الملف يُولّد تلقائياً عند فتح النموذج
                                </div>
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم الملف الخارجي</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="رقم الملف الخارجي" name="external_file_number"
                                       id="external_file_number" />
                            </div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم هوية المكفول</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-solid"
                                           placeholder="ابحث برقم هوية المكفول" name="identity_number"
                                           id="identity_number" autocomplete="off" />
                                    <div id="sponsored_search_results" class="position-absolute w-100 bg-white border rounded shadow-sm" style="display: none; z-index: 1050; max-height: 200px; overflow-y: auto;"></div>
                                </div>
                                <div class="form-text">أدخل رقم الهوية للبحث في قاعدة البيانات المركزية</div>
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">نوع الشخص</label>
                                <select class="form-select form-select-solid" name="person_type" id="person_type">
                                    <option value="">اختر نوع الشخص</option>
                                    <option value="breadwinner">معيل</option>
                                    <option value="family_member">فرد عائلة</option>
                                    <option value="deceased_father">أب متوفي</option>
                                    <option value="deceased_mother">أم متوفية</option>
                                </select>
                            </div>
                        </div>

                        <!--begin::الاسم الرباعي-->
                        <div class="row g-9 mb-7">
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">الاسم الأول</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="الاسم الأول" name="orphan_first_name"
                                       id="orphan_first_name" />
                            </div>
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">اسم الأب</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="اسم الأب" name="orphan_father_name"
                                       id="orphan_father_name" />
                            </div>
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">اسم الجد</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="اسم الجد" name="orphan_grandfather_name"
                                       id="orphan_grandfather_name" />
                            </div>
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">اسم العائلة</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="اسم العائلة" name="orphan_family_name"
                                       id="orphan_family_name" />
                            </div>
                            <input type="hidden" name="orphan_name" id="orphan_name" />
                        </div>
                        <!--end::الاسم الرباعي-->

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">تاريخ ميلاد المكفول</label>
                                <input type="date" class="form-control form-control-solid"
                                       name="sponsored_birth_date"
                                       id="sponsored_birth_date" />
                            </div>
                        </div>

                        <!--begin::بيانات المعيل-->
                        <div class="mb-7 mt-10">
                            <h3 class="fw-bold text-gray-900 mb-5">
                                <i class="bi bi-person-badge me-2"></i>بيانات المعيل
                            </h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم هوية المعيل</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-solid"
                                           placeholder="ابحث برقم هوية المعيل" name="guardian_identity_number"
                                           id="guardian_identity_number" autocomplete="off" />
                                    <div id="guardian_search_results" class="position-absolute w-100 bg-white border rounded shadow-sm" style="display: none; z-index: 1050; max-height: 200px; overflow-y: auto;"></div>
                                </div>
                                <div class="form-text">أدخل رقم الهوية للبحث في قاعدة البيانات المركزية</div>
                            </div>
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">تاريخ ميلاد المعيل</label>
                                <input type="date" class="form-control form-control-solid"
                                       name="guardian_birth_date"
                                       id="guardian_birth_date" />
                            </div>
                        </div>

                        <!--begin::الاسم الرباعي للمعيل-->
                        <div class="row g-9 mb-7">
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">الاسم الأول للمعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="الاسم الأول" name="guardian_first_name"
                                       id="guardian_first_name" />
                            </div>
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">اسم أب المعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="اسم الأب" name="guardian_father_name"
                                       id="guardian_father_name" />
                            </div>
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">اسم جد المعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="اسم الجد" name="guardian_grandfather_name"
                                       id="guardian_grandfather_name" />
                            </div>
                            <div class="col-md-3 fv-row">
                                <label class="fs-6 fw-semibold mb-2">اسم عائلة المعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="اسم العائلة" name="guardian_family_name"
                                       id="guardian_family_name" />
                            </div>
                            <input type="hidden" name="guardian_name" id="guardian_name" />
                        </div>
                        <!--end::الاسم الرباعي للمعيل-->

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم هاتف المعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="رقم هاتف المعيل" name="guardian_phone"
                                       id="guardian_phone" />
                            </div>
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">جوال بديل للمعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="جوال بديل للمعيل" name="guardian_alt_phone"
                                       id="guardian_alt_phone" />
                            </div>
                        </div>

                        <!--begin::معلومات الكفالة-->
                        <div class="mb-7 mt-10">
                            <h3 class="fw-bold text-gray-900 mb-5">تفاصيل الكفالة</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">مدة الكفالة (بالأشهر)</label>
                                <input type="number" class="form-control form-control-solid"
                                       placeholder="عدد الأشهر" name="sponsorship_duration_months"
                                       id="sponsorship_duration_months" min="1" />
                            </div>

                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">تاريخ البداية</label>
                                <input type="date" class="form-control form-control-solid"
                                       name="sponsorship_start_date" id="sponsorship_start_date" />
                            </div>

                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">تاريخ النهاية</label>
                                <input type="date" class="form-control form-control-solid"
                                       name="sponsorship_end_date" id="sponsorship_end_date" />
                            </div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">نوع الكفالة</label>
                                <select class="form-select form-select-solid" name="sponsorship_type_id" id="sponsorship_type_id">
                                    <option value="">اختر نوع الكفالة</option>
                                    @foreach($sponsorshipTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->description }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">حالة الكفالة</label>
                                <select class="form-select form-select-solid" name="sponsorship_status_id" id="sponsorship_status_id">
                                    <option value="">اختر حالة الكفالة</option>
                                    @foreach($sponsorshipStatuses as $status)
                                        <option value="{{ $status->id }}">{{ $status->description }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-semibold mb-2">ملاحظات</label>
                            <textarea class="form-control form-control-solid" rows="3"
                                      name="notes" id="notes" placeholder="أي ملاحظات إضافية"></textarea>
                        </div>

                        <!--begin::المعلومات البنكية-->
                        <div class="mb-7 mt-10">
                            <h3 class="fw-bold text-gray-900 mb-5">
                                <i class="fas fa-university text-primary me-2"></i>المعلومات البنكية
                            </h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <!--begin::عرض البيانات البنكية الموجودة-->
                        <div id="existingBankAccountsSection" class="d-none mb-7">
                            <div class="alert alert-success d-flex align-items-center py-3 mb-4">
                                <i class="fas fa-check-circle fs-2 me-3"></i>
                                <span class="fw-semibold">البيانات البنكية المسجلة للشخص</span>
                            </div>
                            <div id="existingBankAccountsList">
                                <!-- سيتم عرض البيانات البنكية الموجودة هنا -->
                            </div>
                        </div>
                        <!--end::عرض البيانات البنكية الموجودة-->

                        <div class="alert alert-info d-flex align-items-center py-3 mb-5">
                            <i class="fas fa-info-circle fs-2 me-3"></i>
                            <span>يمكنك إضافة حتى 10 حسابات بنكية للمكفول. جميع الحقول اختيارية.</span>
                            <button type="button" class="btn btn-sm btn-primary ms-auto" id="addSponsoredBankAccount">
                                <i class="fas fa-plus me-1"></i>إضافة حساب بنكي
                            </button>
                        </div>

                        <div id="sponsoredBankAccountsContainer" class="d-none">
                            <!-- سيتم إضافة الحسابات البنكية هنا ديناميكياً -->
                        </div>
                    </div>
                </div>

                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span class="indicator-label">حفظ</span>
                        <span class="indicator-progress">جاري الحفظ...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for View Sponsorship Details -->
<div class="modal fade" id="viewSponsorshipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h2 class="fw-bold text-gray-800">
                    <i class="ki-duotone ki-information-5 fs-1 text-info me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    تفاصيل الكفالة
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <div class="modal-body py-10 px-lg-17">
                <div class="scroll-y me-n7 pe-7" style="max-height: 600px; overflow-y: auto;">
                    <div id="viewSponsorshipContent">
                        <div class="text-center py-10">
                            <span class="spinner-border spinner-border-lg text-primary"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scriptsCode')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script type="text/javascript">
        $(function() {
            // ============================================
            // إدارة الحسابات البنكية في مودال المكفولين
            // ============================================
            let sponsoredBankAccountCount = 0;
            const maxSponsoredBankAccounts = 10;
            const bankNames = @json($bankNames ?? []);

            // ============================================
            // تفعيل Select2 للفلاتر
            // ============================================
            $('#filter_sponsor, #filter_sponsorship_type, #filter_sponsorship_status').select2({
                dir: 'rtl',
                width: '100%'
            });

            // ============================================
            // تطبيق الفلاتر بشكل تلقائي عند التغيير (كل فلتر مستقل)
            // ============================================
            function applyFilters() {
                if ($.fn.DataTable.isDataTable('#sponsorships-table')) {
                    const table = $('#sponsorships-table').DataTable();

                    const sponsorFilter = $('#filter_sponsor').val();
                    const typeFilter = $('#filter_sponsorship_type').val();
                    const statusFilter = $('#filter_sponsorship_status').val();

                    // بناء الـ URL مع المعاملات
                    let baseUrl = window.location.pathname;
                    const params = [];

                    if (sponsorFilter) params.push('sponsor_id=' + sponsorFilter);
                    if (typeFilter) params.push('sponsorship_type_id=' + typeFilter);
                    if (statusFilter) params.push('sponsorship_status_id=' + statusFilter);

                    const queryString = params.length > 0 ? '?' + params.join('&') : '';

                    // تحديث الـ AJAX URL وإعادة تحميل البيانات
                    table.ajax.url(baseUrl + queryString).load();
                }
            }

            // تفعيل الفلاتر بشكل تلقائي عند التغيير
            $('#filter_sponsor').on('change', function() {
                console.log('🔴 Sponsor filter changed:', $(this).val());
                applyFilters();
            });

            $('#filter_sponsorship_type').on('change', function() {
                console.log('🔵 Type filter changed:', $(this).val());
                applyFilters();
            });

            $('#filter_sponsorship_status').on('change', function() {
                console.log('🟢 Status filter changed:', $(this).val());
                applyFilters();
            });

            // زر تطبيق الفلاتر (احتياطي للتطبيق اليدوي)
            $('#apply_filters').on('click', function() {
                applyFilters();
                Swal.fire({
                    icon: 'success',
                    title: 'تم تطبيق الفلاتر',
                    text: 'تم تحديث النتائج بنجاح',
                    timer: 1500,
                    showConfirmButton: false
                });
            });

            // ============================================
            // إعادة تعيين الفلاتر بشكل تلقائي
            // ============================================
            $('#reset_filters').on('click', function() {
                console.log('♻️ Resetting all filters...');

                // إزالة القيم مؤقتاً بدون تفعيل change event
                $('#filter_sponsor').val(null).trigger('change.select2');
                $('#filter_sponsorship_type').val(null).trigger('change.select2');
                $('#filter_sponsorship_status').val(null).trigger('change.select2');

                if ($.fn.DataTable.isDataTable('#sponsorships-table')) {
                    const table = $('#sponsorships-table').DataTable();
                    table.ajax.url(window.location.pathname).load(function() {
                        Swal.fire({
                            icon: 'info',
                            title: 'تم إعادة تعيين الفلاتر',
                            text: 'تم عرض جميع السجلات',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    });
                }
            });

            // ============================================
            // 🍪 نظام حفظ إعدادات عرض الأعمدة (Column Visibility)
            // ============================================

            const COLUMN_COOKIE_NAME = 'sponsorships_table_columns';
            const COOKIE_EXPIRY_DAYS = 365; // سنة واحدة

            // دالة لحفظ Cookie
            function setCookie(name, value, days) {
                const expires = new Date();
                expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
                document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/';
            }

            // دالة لقراءة Cookie
            function getCookie(name) {
                const nameEQ = name + "=";
                const ca = document.cookie.split(';');
                for(let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
                }
                return null;
            }

            // دالة لحذف Cookie
            function deleteCookie(name) {
                document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/';
            }

            // دالة لحفظ حالة الأعمدة
            function saveColumnVisibility() {
                if (!$.fn.DataTable.isDataTable('#sponsorships-table')) return;

                const table = $('#sponsorships-table').DataTable();
                const columnStates = [];

                // جلب حالة كل عمود
                table.columns().every(function() {
                    const column = this;
                    columnStates.push({
                        index: column.index(),
                        visible: column.visible()
                    });
                });

                // حفظ الحالة في Cookie
                setCookie(COLUMN_COOKIE_NAME, JSON.stringify(columnStates), COOKIE_EXPIRY_DAYS);
                console.log('🍪 تم حفظ إعدادات الأعمدة:', columnStates);
            }

            // دالة لاستعادة حالة الأعمدة
            function restoreColumnVisibility() {
                const savedState = getCookie(COLUMN_COOKIE_NAME);

                if (!savedState) {
                    console.log('🍪 لا توجد إعدادات محفوظة للأعمدة');
                    return;
                }

                if (!$.fn.DataTable.isDataTable('#sponsorships-table')) return;

                try {
                    const columnStates = JSON.parse(savedState);
                    const table = $('#sponsorships-table').DataTable();

                    console.log('🍪 استعادة إعدادات الأعمدة:', columnStates);

                    // تطبيق الحالة المحفوظة
                    columnStates.forEach(function(state) {
                        table.column(state.index).visible(state.visible, false);
                    });

                    // إعادة رسم الجدول مرة واحدة فقط
                    table.columns.adjust().draw(false);

                    console.log('✅ تم استعادة إعدادات الأعمدة بنجاح');
                } catch (e) {
                    console.error('❌ خطأ في استعادة إعدادات الأعمدة:', e);
                    deleteCookie(COLUMN_COOKIE_NAME);
                }
            }

            // استعادة الإعدادات عند تحميل الصفحة
            setTimeout(function() {
                if ($.fn.DataTable.isDataTable('#sponsorships-table')) {
                    restoreColumnVisibility();

                    // مراقبة تغييرات رؤية الأعمدة
                    const table = $('#sponsorships-table').DataTable();

                    // عند الضغط على زر التحكم بالأعمدة
                    table.on('column-visibility.dt', function(e, settings, column, state) {
                        console.log('🔄 تغيير رؤية العمود:', column, '→', state ? 'مرئي' : 'مخفي');
                        saveColumnVisibility();
                    });

                    console.log('✅ تم تفعيل نظام حفظ إعدادات الأعمدة');
                }
            }, 1000); // انتظار ثانية لضمان تحميل DataTable

            function createSponsoredBankAccountForm(index, bankData = {}) {
                const isApproved = bankData.check_account == 1;
                const accountId = bankData.id || '';
                const guardianFileId = bankData.guardian_file_id || '';

                return `
                <div class="sponsored-bank-account-form border rounded p-4 mb-4 position-relative bank-account-card"
                     data-index="${index}"
                     data-account-id="${accountId}"
                     style="border: 2px dashed #009ef7 !important; background-color: ${isApproved ? '#f1faff' : '#d8d8d8'};">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="mb-0 text-primary fw-bold">
                            <i class="fas fa-university me-2"></i>حساب بنكي رقم ${index + 1}
                        </h6>
                        <div class="d-flex gap-2">
                            ${accountId ? `
                                <button type="button"
                                        class="btn btn-sm approve-bank-account-btn ${isApproved ? 'btn-dark-green' : 'btn-outline-success'}"
                                        data-account-id="${accountId}"
                                        data-guardian-file-id="${guardianFileId}"
                                        ${isApproved ? 'disabled' : ''}>
                                    <i class="fas ${isApproved ? 'fa-check-circle' : 'fa-check'} me-1"></i>
                                    ${isApproved ? 'حساب معتمد' : 'اعتماد الحساب'}
                                </button>
                            ` : ''}
                            <button type="button"
                                    class="btn btn-sm btn-danger remove-sponsored-bank-btn"
                                    title="حذف الحساب">
                                <i class="fas fa-trash-alt me-1"></i>حذف
                            </button>
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">اسم البنك</label>
                            <select name="bank_accounts[${index}][bank_name]" class="form-select form-select-solid">
                                <option value="">اختر البنك</option>
                                ${bankNames.map(bank => `
                                    <option value="${bank.id}" ${bankData.bank_name == bank.id ? 'selected' : ''}>
                                        ${bank.description}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">اسم صاحب الحساب</label>
                            <input type="text" name="bank_accounts[${index}][re_guardian_name]"
                                   class="form-control form-control-solid" maxlength="100"
                                   placeholder="أدخل اسم صاحب الحساب"
                                   value="${bankData.re_guardian_name || ''}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">رقم هوية صاحب الحساب</label>
                            <input type="text" name="bank_accounts[${index}][person_owner_identity_number]"
                                   class="form-control form-control-solid" maxlength="20"
                                   placeholder="أدخل رقم الهوية"
                                   value="${bankData.person_owner_identity_number || ''}"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">رقم هاتف صاحب الحساب</label>
                            <input type="text" name="bank_accounts[${index}][re_phone_number]"
                                   class="form-control form-control-solid" maxlength="20"
                                   placeholder="أدخل رقم الهاتف"
                                   value="${bankData.re_phone_number || ''}"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">رقم IBAN بالدولار</label>
                            <input type="text" name="bank_accounts[${index}][iban_usd]"
                                   class="form-control form-control-solid" maxlength="34"
                                   placeholder="مثال: PS00XXXX0000000000000000000"
                                   value="${bankData.iban_usd || ''}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">رقم IBAN بالشيكل</label>
                            <input type="text" name="bank_accounts[${index}][iban_shekel]"
                                   class="form-control form-control-solid" maxlength="34"
                                   placeholder="مثال: PS00XXXX0000000000000000000"
                                   value="${bankData.iban_shekel || ''}">
                        </div>
                        <input type="hidden" name="bank_accounts[${index}][id]" value="${bankData.id || ''}">
                    </div>
                </div>
                `;
            }

            function updateRemoveSponsoredBankButtons() {
                $('.remove-sponsored-bank-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'تأكيد الحذف',
                        text: 'هل أنت متأكد من حذف هذا الحساب البنكي؟',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'نعم، احذف',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $(this).closest('.sponsored-bank-account-form').remove();
                            sponsoredBankAccountCount--;

                            // إعادة ترقيم
                            $('.sponsored-bank-account-form').each(function(idx) {
                                $(this).attr('data-index', idx);
                                $(this).find('h6').html(`<i class="fas fa-university me-2"></i>حساب بنكي رقم ${idx + 1}`);
                            });

                            if (sponsoredBankAccountCount === 0) {
                                $('#sponsoredBankAccountsContainer').addClass('d-none');
                            }

                            $('#addSponsoredBankAccount').prop('disabled', false);

                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف',
                                text: 'تم حذف الحساب البنكي بنجاح',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    });
                });
            }

            $('#addSponsoredBankAccount').on('click', function() {
                if (sponsoredBankAccountCount < maxSponsoredBankAccounts) {
                    $('#sponsoredBankAccountsContainer').removeClass('d-none');
                    $('#sponsoredBankAccountsContainer').append(createSponsoredBankAccountForm(sponsoredBankAccountCount));
                    sponsoredBankAccountCount++;
                    updateRemoveSponsoredBankButtons();

                    if (sponsoredBankAccountCount >= maxSponsoredBankAccounts) {
                        $(this).prop('disabled', true);
                    }
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'لا يمكن إضافة أكثر من 10 حسابات بنكية'
                    });
                }
            });

            // مسح الحسابات البنكية عند إغلاق المودال
            $('#sponsorshipModal').on('hidden.bs.modal', function() {
                $('#sponsoredBankAccountsContainer').html('').addClass('d-none');
                $('#existingBankAccountsSection').addClass('d-none');
                $('#existingBankAccountsList').html('');
                sponsoredBankAccountCount = 0;
                $('#addSponsoredBankAccount').prop('disabled', false);
                // مسح نتائج البحث في السجل المدني
                $('#guardian_search_results').hide().html('');
                $('#sponsored_search_results').hide().html('');
                // مسح حقول الاسم الرباعي للمكفول
                $('#orphan_first_name').val('');
                $('#orphan_father_name').val('');
                $('#orphan_grandfather_name').val('');
                $('#orphan_family_name').val('');
                $('#orphan_name').val('');
                // مسح حقول الاسم الرباعي للمعيل
                $('#guardian_first_name').val('');
                $('#guardian_father_name').val('');
                $('#guardian_grandfather_name').val('');
                $('#guardian_family_name').val('');
                $('#guardian_name').val('');
                $('#guardian_birth_date').val('');
                // مسح حقل نوع الشخص
                $('#person_type').val('');
            });

            // ============================================
            // البحث في السجل المدني لرقم هوية المكفول
            // ============================================
            let sponsoredSearchTimeout = null;

            $('#identity_number').on('input', function() {
                const query = $(this).val().trim();
                const resultsDiv = $('#sponsored_search_results');

                // إلغاء البحث السابق
                if (sponsoredSearchTimeout) {
                    clearTimeout(sponsoredSearchTimeout);
                }

                // إخفاء النتائج إذا كان الحقل فارغاً
                if (query.length < 3) {
                    resultsDiv.hide().html('');
                    return;
                }

                // تأخير البحث لتجنب الطلبات المتكررة
                sponsoredSearchTimeout = setTimeout(function() {
                    resultsDiv.html('<div class="p-2 text-center text-muted"><i class="bi bi-hourglass-split me-1"></i>جاري البحث...</div>').show();

                    $.ajax({
                        url: '/admin/api/search-civil-registry',
                        type: 'GET',
                        data: { identity_number: query },
                        success: function(response) {
                            if (response.success && response.data) {
                                const person = response.data;
                                const fullName = `${person.first_name} ${person.second_name} ${person.third_name} ${person.last_name}`.trim();
                                resultsDiv.html(`
                                    <div class="sponsored-result p-2 border-bottom cursor-pointer" style="cursor: pointer;"
                                         data-identity="${person.identity_number}"
                                         data-name="${fullName}"
                                         data-first="${person.first_name}"
                                         data-second="${person.second_name}"
                                         data-third="${person.third_name}"
                                         data-last="${person.last_name}"
                                         data-birth="${person.birth_date || ''}">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="text-primary">${person.identity_number}</strong>
                                                <span class="text-muted mx-2">-</span>
                                                <span>${fullName}</span>
                                            </div>
                                            <span class="badge bg-info">السجل المدني</span>
                                        </div>
                                    </div>
                                `).show();
                            } else {
                                resultsDiv.html('<div class="p-2 text-center text-muted"><i class="bi bi-x-circle me-1"></i>لم يتم العثور على نتائج</div>').show();
                            }
                        },
                        error: function() {
                            resultsDiv.html('<div class="p-2 text-center text-danger"><i class="bi bi-exclamation-triangle me-1"></i>حدث خطأ أثناء البحث</div>').show();
                        }
                    });
                }, 300);
            });

            // اختيار نتيجة من بحث المكفول
            $(document).on('click', '.sponsored-result', function() {
                const identity = $(this).data('identity');
                const firstName = $(this).data('first');
                const secondName = $(this).data('second');
                const thirdName = $(this).data('third');
                const lastName = $(this).data('last');
                const birthDate = $(this).data('birth');

                // ملء حقول الاسم الرباعي
                $('#identity_number').val(identity);
                $('#orphan_first_name').val(firstName || '');
                $('#orphan_father_name').val(secondName || '');
                $('#orphan_grandfather_name').val(thirdName || '');
                $('#orphan_family_name').val(lastName || '');
                // تحديث الحقل المخفي بالاسم الكامل
                const fullName = [firstName, secondName, thirdName, lastName].filter(n => n).join(' ');
                $('#orphan_name').val(fullName);

                if (birthDate) {
                    $('#sponsored_birth_date').val(birthDate);
                }
                $('#sponsored_search_results').hide().html('');
            });

            // إخفاء نتائج بحث المكفول عند النقر خارجها
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#identity_number, #sponsored_search_results').length) {
                    $('#sponsored_search_results').hide();
                }
            });

            // ============================================
            // البحث في السجل المدني لرقم هوية المعيل
            // ============================================
            let guardianSearchTimeout = null;

            $('#guardian_identity_number').on('input', function() {
                const query = $(this).val().trim();
                const resultsDiv = $('#guardian_search_results');

                // إلغاء البحث السابق
                if (guardianSearchTimeout) {
                    clearTimeout(guardianSearchTimeout);
                }

                // إخفاء النتائج إذا كان الحقل فارغاً
                if (query.length < 3) {
                    resultsDiv.hide().html('');
                    return;
                }

                // تأخير البحث لتجنب الطلبات المتكررة
                guardianSearchTimeout = setTimeout(function() {
                    resultsDiv.html('<div class="p-2 text-center text-muted"><i class="bi bi-hourglass-split me-1"></i>جاري البحث...</div>').show();

                    $.ajax({
                        url: '/admin/api/search-civil-registry',
                        type: 'GET',
                        data: { identity_number: query },
                        success: function(response) {
                            if (response.success && response.data) {
                                const person = response.data;
                                const fullName = `${person.first_name} ${person.second_name} ${person.third_name} ${person.last_name}`.trim();
                                resultsDiv.html(`
                                    <div class="guardian-result p-2 border-bottom cursor-pointer" style="cursor: pointer;"
                                         data-identity="${person.identity_number}"
                                         data-name="${fullName}"
                                         data-first="${person.first_name}"
                                         data-second="${person.second_name}"
                                         data-third="${person.third_name}"
                                         data-last="${person.last_name}"
                                         data-birth="${person.birth_date || ''}">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="text-primary">${person.identity_number}</strong>
                                                <span class="text-muted mx-2">-</span>
                                                <span>${fullName}</span>
                                            </div>
                                            <span class="badge bg-success">السجل المدني</span>
                                        </div>
                                    </div>
                                `).show();
                            } else {
                                resultsDiv.html('<div class="p-2 text-center text-muted"><i class="bi bi-x-circle me-1"></i>لم يتم العثور على نتائج</div>').show();
                            }
                        },
                        error: function() {
                            resultsDiv.html('<div class="p-2 text-center text-danger"><i class="bi bi-exclamation-triangle me-1"></i>حدث خطأ أثناء البحث</div>').show();
                        }
                    });
                }, 300);
            });

            // اختيار نتيجة من البحث
            $(document).on('click', '.guardian-result', function() {
                const identity = $(this).data('identity');
                const firstName = $(this).data('first');
                const secondName = $(this).data('second');
                const thirdName = $(this).data('third');
                const lastName = $(this).data('last');
                const birthDate = $(this).data('birth');

                // ملء رقم الهوية
                $('#guardian_identity_number').val(identity);

                // ملء حقول الاسم الرباعي للمعيل
                $('#guardian_first_name').val(firstName || '');
                $('#guardian_father_name').val(secondName || '');
                $('#guardian_grandfather_name').val(thirdName || '');
                $('#guardian_family_name').val(lastName || '');

                // تحديث الحقل المخفي بالاسم الكامل
                const fullName = [firstName, secondName, thirdName, lastName].filter(n => n).join(' ');
                $('#guardian_name').val(fullName);

                // ملء تاريخ ميلاد المعيل
                if (birthDate) {
                    $('#guardian_birth_date').val(birthDate);
                }

                $('#guardian_search_results').hide().html('');
            });

            // إخفاء النتائج عند النقر خارجها
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#guardian_identity_number, #guardian_search_results').length) {
                    $('#guardian_search_results').hide();
                }
            });

            // ============================================
            // وظيفة لجلب وعرض البيانات البنكية الموجودة باستخدام رقم الملف مباشرة
            // ============================================
            function loadExistingBankAccountsByFileId(fileId) {
                if (!fileId) {
                    $('#existingBankAccountsSection').addClass('d-none');
                    $('#sponsoredBankAccountsContainer').html('').addClass('d-none');
                    return;
                }

                $.ajax({
                    url: '/admin/records-management/get-bank-accounts-by-file-id',
                    type: 'GET',
                    data: { file_id: fileId },
                    success: function(response) {
                        if (response.success && response.accounts && response.accounts.length > 0) {
                            // إخفاء قسم العرض للقراءة فقط
                            $('#existingBankAccountsSection').addClass('d-none');

                            // عرض الحسابات بشكل قابل للتعديل
                            sponsoredBankAccountCount = 0;
                            $('#sponsoredBankAccountsContainer').html('').removeClass('d-none');

                            response.accounts.forEach((account, index) => {
                                const bankData = {
                                    id: account.id,
                                    bank_name: account.bank_name,
                                    re_guardian_name: account.re_guardian_name,
                                    person_owner_identity_number: account.person_owner_identity_number,
                                    re_phone_number: account.re_phone_number,
                                    iban_usd: account.iban_usd,
                                    iban_shekel: account.iban_shekel,
                                    check_account: account.check_account,
                                    guardian_file_id: response.guardian_file_id
                                };

                                $('#sponsoredBankAccountsContainer').append(
                                    createSponsoredBankAccountForm(sponsoredBankAccountCount, bankData)
                                );
                                sponsoredBankAccountCount++;
                            });

                            updateRemoveSponsoredBankButtons();

                            // تحديث حالة زر الإضافة
                            if (sponsoredBankAccountCount >= maxSponsoredBankAccounts) {
                                $('#addSponsoredBankAccount').prop('disabled', true);
                            } else {
                                $('#addSponsoredBankAccount').prop('disabled', false);
                            }
                        } else {
                            $('#existingBankAccountsSection').addClass('d-none');
                            $('#sponsoredBankAccountsContainer').html('').addClass('d-none');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading bank accounts by file ID:', xhr);
                        $('#existingBankAccountsSection').addClass('d-none');
                        $('#sponsoredBankAccountsContainer').html('').addClass('d-none');
                    }
                });
            }

            // ============================================
            // وظيفة لجلب وعرض البيانات البنكية الموجودة باستخدام رقم الهوية
            // ============================================
            function loadExistingBankAccounts(identityNumber) {
                if (!identityNumber) {
                    $('#existingBankAccountsSection').addClass('d-none');
                    $('#sponsoredBankAccountsContainer').html('').addClass('d-none');
                    return;
                }

                $.ajax({
                    url: '/admin/records-management/get-bank-accounts',
                    type: 'GET',
                    data: { guardian_identity: identityNumber },
                    success: function(response) {
                        if (response.success && response.accounts && response.accounts.length > 0) {
                            // إخفاء قسم العرض للقراءة فقط
                            $('#existingBankAccountsSection').addClass('d-none');

                            // عرض الحسابات بشكل قابل للتعديل
                            sponsoredBankAccountCount = 0;
                            $('#sponsoredBankAccountsContainer').html('').removeClass('d-none');

                            response.accounts.forEach((account, index) => {
                                const bankData = {
                                    id: account.id,
                                    bank_name: account.bank_name,
                                    re_guardian_name: account.re_guardian_name,
                                    person_owner_identity_number: account.person_owner_identity_number,
                                    re_phone_number: account.re_phone_number,
                                    iban_usd: account.iban_usd,
                                    iban_shekel: account.iban_shekel,
                                    check_account: account.check_account,
                                    guardian_file_id: response.guardian_file_id
                                };

                                $('#sponsoredBankAccountsContainer').append(
                                    createSponsoredBankAccountForm(sponsoredBankAccountCount, bankData)
                                );
                                sponsoredBankAccountCount++;
                            });

                            updateRemoveSponsoredBankButtons();

                            // تحديث حالة زر الإضافة
                            if (sponsoredBankAccountCount >= maxSponsoredBankAccounts) {
                                $('#addSponsoredBankAccount').prop('disabled', true);
                            } else {
                                $('#addSponsoredBankAccount').prop('disabled', false);
                            }
                        } else {
                            $('#existingBankAccountsSection').addClass('d-none');
                            $('#sponsoredBankAccountsContainer').html('').addClass('d-none');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading bank accounts:', xhr);
                        $('#existingBankAccountsSection').addClass('d-none');
                        $('#sponsoredBankAccountsContainer').html('').addClass('d-none');
                    }
                });
            }

            // ============================================
            // Search functionality
            // ============================================
            let searchInitialized = false;

            $(document).on('init.dt', function(e, settings) {
                if (settings.sTableId === 'sponsorships-table' && !searchInitialized) {
                    initSearch();
                }
            });

            setTimeout(function() {
                if (!searchInitialized && $.fn.DataTable.isDataTable('#sponsorships-table')) {
                    initSearch();
                }
            }, 1000);

            function initSearch() {
                try {
                    const table = $('#sponsorships-table').DataTable();
                    const searchInput = $('#kt_sponsorship_search');

                    // إزالة أي event handlers سابقة
                    searchInput.off('keyup input change');

                    // تفعيل البحث الفوري
                    searchInput.on('keyup input change', function() {
                        const searchValue = this.value;
                        console.log('🔍 Searching for:', searchValue);
                        table.search(searchValue).draw();
                    });

                    // مسح البحث عند الضغط على Escape
                    searchInput.on('keydown', function(e) {
                        if (e.key === 'Escape') {
                            $(this).val('');
                            table.search('').draw();
                        }
                    });

                    searchInitialized = true;
                    console.log('✅ Search engine activated successfully!');
                    console.log('🔍 You can now search for: names, IDs, files, etc.');
                } catch (error) {
                    console.error('❌ Error initializing search:', error);
                }
            }

            // ============================================
            // تغيير حالة الكفالة من الجدول مباشرة
            // ============================================
            $(document).on('change', '.change-sponsorship-status', function() {
                const select = $(this);
                const sponsorshipId = select.data('sponsorship-id');
                const newStatusId = select.val();
                const oldValue = select.data('old-value') || select.val();

                Swal.fire({
                    title: 'تأكيد التغيير',
                    text: 'هل أنت متأكد من تغيير حالة الكفالة؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، غير الحالة',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // حفظ القيمة القديمة
                        select.data('old-value', newStatusId);

                        $.ajax({
                            url: `/admin/sponsorships/${sponsorshipId}/update-status`,
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                sponsorship_status_id: newStatusId
                            },
                            success: function(response) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم التحديث',
                                    text: response.message || 'تم تحديث حالة الكفالة بنجاح',
                                    timer: 2000,
                                    showConfirmButton: false
                                });

                                // إعادة تحميل الجدول
                                if ($.fn.DataTable.isDataTable('#sponsorships-table')) {
                                    $('#sponsorships-table').DataTable().ajax.reload(null, false);
                                }
                            },
                            error: function(xhr) {
                                // إرجاع القيمة القديمة
                                select.val(oldValue);

                                let errorMessage = 'حدث خطأ أثناء تحديث حالة الكفالة';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMessage = xhr.responseJSON.message;
                                }

                                Swal.fire({
                                    icon: 'error',
                                    title: 'خطأ!',
                                    text: errorMessage,
                                    confirmButtonText: 'حسناً'
                                });
                            }
                        });
                    } else {
                        // إرجاع القيمة القديمة إذا ألغى المستخدم
                        select.val(oldValue);
                    }
                });
            });

            // Initialize Select2 for multiple sponsors
            $('#sponsor_ids').select2({
                placeholder: 'اختر المؤسسات الكافلة',
                allowClear: true,
                dir: 'rtl',
                dropdownParent: $('#sponsorshipModal')
            });

            // ============================================
            // 🆕 توليد رقم الملف الداخلي عند فتح المودال
            // ============================================
            $('#sponsorshipModal').on('show.bs.modal', function (e) {
                // التحقق من أنه ليس تعديل (في حالة التعديل، الرقم موجود مسبقاً)
                const isEdit = $('#sponsorship_id').val() !== '';

                if (!isEdit) {
                    // توليد رقم ملف جديد
                    generateNewFileNumber();
                }
            });

            // دالة توليد رقم الملف
            function generateNewFileNumber() {
                $('#internal_file_number').val('جاري التوليد...').prop('readonly', true);

                $.ajax({
                    url: '/api/files/generate-record-number',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        table: 'sponsorships',
                        column: 'internal_file_number'
                    },
                    success: function(response) {
                        if (response.success && response.record_number) {
                            $('#internal_file_number').val(response.record_number);
                            console.log('✅ تم توليد رقم الملف:', response.record_number);
                        } else {
                            // محاولة بديلة باستخدام timestamp
                            const fallbackNumber = 'SP' + Date.now().toString().slice(-8);
                            $('#internal_file_number').val(fallbackNumber);
                            console.warn('⚠️ استخدام رقم بديل:', fallbackNumber);
                        }
                    },
                    error: function(xhr) {
                        console.error('❌ خطأ في توليد رقم الملف:', xhr);
                        // رقم بديل في حالة الخطأ
                        const fallbackNumber = 'SP' + Date.now().toString().slice(-8);
                        $('#internal_file_number').val(fallbackNumber);
                    },
                    complete: function() {
                        $('#internal_file_number').prop('readonly', true);
                    }
                });
            }

            // Reset form when modal is hidden
            $('#sponsorshipModal').on('hidden.bs.modal', function () {
                $('#sponsorshipForm')[0].reset();
                $('#sponsor_ids').val(null).trigger('change');
                $('#sponsorship_id').val('');
                $('#form_method').val('POST');
                $('#modalTitle').text('إضافة كفالة جديدة');
            });

            // Submit Form
            $('#sponsorshipForm').on('submit', function(e) {
                e.preventDefault();

                const sponsorshipId = $('#sponsorship_id').val();
                const method = $('#form_method').val();
                let url = '/admin/sponsorships';

                if (method === 'PUT') {
                    url = `/admin/sponsorships/${sponsorshipId}`;
                }

                const formData = $(this).serialize();
                const submitBtn = $('#submitBtn');

                submitBtn.attr('data-kt-indicator', 'on');
                submitBtn.prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        $('#sponsorshipModal').modal('hide');

                        // تنظيف modal backdrop بعد إغلاق المودال
                        cleanupModalBackdrop();

                        // 🔍 التحقق من وجود تحذيرات حسابات بنكية مكررة
                        let message = response.message;
                        let icon = 'success';

                        if (response.info && response.info.bank_duplicates && response.info.bank_duplicates.length > 0) {
                            icon = 'warning';
                            message += '\n\n⚠️ تم تجاهل الحسابات البنكية المكررة التالية:\n\n';
                            response.info.bank_duplicates.forEach(function(dup) {
                                message += `${dup.index}. ${dup.message}\n\n`;
                            });
                        }

                        Swal.fire({
                            icon: icon,
                            title: icon === 'success' ? 'نجح!' : 'نجح مع تحذيرات',
                            html: message.replace(/\n/g, '<br>'),
                            timer: icon === 'success' ? 2000 : null,
                            showConfirmButton: true,
                            confirmButtonText: 'حسناً'
                        }).then(function() {
                            // تنظيف modal backdrop مرة أخرى بعد إغلاق SweetAlert
                            cleanupModalBackdrop();
                        });

                        if ($.fn.DataTable.isDataTable('#sponsorships-table')) {
                            $('#sponsorships-table').DataTable().ajax.reload();
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'حدث خطأ أثناء العملية';
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            errorMessage = Object.values(xhr.responseJSON.errors).join('<br>');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            html: errorMessage,
                            confirmButtonText: 'حسناً'
                        });
                    },
                    complete: function() {
                        submitBtn.removeAttr('data-kt-indicator');
                        submitBtn.prop('disabled', false);
                    }
                });
            });

            // Edit Sponsorship
            $(document).on('click', '.edit-sponsorship', function() {
                const sponsorshipId = $(this).data('id');

                $.ajax({
                    url: `/admin/sponsorships/${sponsorshipId}/edit`,
                    type: 'GET',
                    success: function(response) {
                        $('#modalTitle').text('تعديل الكفالة');
                        $('#sponsorship_id').val(response.id);
                        $('#form_method').val('PUT');

                        // تعيين المؤسسات الكافلة المتعددة
                        if (response.sponsor_ids && response.sponsor_ids.length > 0) {
                            $('#sponsor_ids').val(response.sponsor_ids).trigger('change');
                        } else {
                            $('#sponsor_ids').val(null).trigger('change');
                        }

                        $('#sponsoring_organization').val(response.sponsoring_organization);
                        $('#internal_file_number').val(response.internal_file_number);
                        $('#external_file_number').val(response.external_file_number);
                        $('#identity_number').val(response.identity_number);

                        // ملء حقول الاسم الرباعي من الاسم الكامل
                        const fullName = response.orphan_name || '';
                        const nameParts = fullName.split(' ').filter(n => n);
                        $('#orphan_first_name').val(nameParts[0] || '');
                        $('#orphan_father_name').val(nameParts[1] || '');
                        $('#orphan_grandfather_name').val(nameParts[2] || '');
                        $('#orphan_family_name').val(nameParts.slice(3).join(' ') || '');
                        $('#orphan_name').val(fullName);

                        // ملء نوع الشخص
                        $('#person_type').val(response.person_type || '');

                        $('#sponsored_birth_date').val(response.sponsored_birth_date || '');

                        // ملء حقول الاسم الرباعي للمعيل
                        const guardianFullName = response.guardian_name || '';
                        const guardianNameParts = guardianFullName.split(' ').filter(n => n);
                        $('#guardian_first_name').val(guardianNameParts[0] || '');
                        $('#guardian_father_name').val(guardianNameParts[1] || '');
                        $('#guardian_grandfather_name').val(guardianNameParts[2] || '');
                        $('#guardian_family_name').val(guardianNameParts.slice(3).join(' ') || '');
                        $('#guardian_name').val(guardianFullName);

                        $('#guardian_identity_number').val(response.guardian_identity_number);

                        // ملء تاريخ ميلاد المعيل
                        $('#guardian_birth_date').val(response.guardian_birth_date || '');

                        // 📞 تحميل أرقام هواتف المعيل
                        $('#guardian_phone').val(response.guardian_phone || '-');
                        $('#guardian_alt_phone').val(response.guardian_alt_phone || '-');

                        $('#sponsorship_duration_months').val(response.sponsorship_duration_months);

                        // 📅 تحميل التواريخ بصيغة صحيحة لحقول input[type="date"]
                        console.log('📅 تواريخ الكفالة:', {
                            start: response.sponsorship_start_date,
                            end: response.sponsorship_end_date
                        });
                        $('#sponsorship_start_date').val(response.sponsorship_start_date || '');
                        $('#sponsorship_end_date').val(response.sponsorship_end_date || '');

                        $('#sponsorship_type_id').val(response.sponsorship_type_id);
                        $('#sponsorship_status_id').val(response.sponsorship_status_id);
                        $('#notes').val(response.notes);

                        // 🆕 تحميل البيانات البنكية الموجودة للمعيل
                        // استخدام guardian_file_id (رقم الملف) الذي يتم جلبه من الخادم
                        const fileIdToLoad = response.guardian_file_id || response.relation_id_number || response.internal_file_number;
                        if (fileIdToLoad) {
                            console.log('🏦 تحميل الحسابات البنكية لرقم الملف:', fileIdToLoad);
                            loadExistingBankAccountsByFileId(fileIdToLoad);
                        } else {
                            // fallback: استخدام الطريقة القديمة
                            const identityToLoad = response.guardian_identity_number || response.identity_number;
                            if (identityToLoad) {
                                console.log('🏦 تحميل الحسابات البنكية للهوية:', identityToLoad);
                                loadExistingBankAccounts(identityToLoad);
                            }
                        }

                        $('#sponsorshipModal').modal('show');
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: 'حدث خطأ أثناء تحميل البيانات',
                            confirmButtonText: 'حسناً'
                        });
                    }
                });
            });

            // Delete Sponsorship
            $(document).on('click', '.delete-sponsorship', function() {
                const sponsorshipId = $(this).data('id');

                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: "لن تتمكن من التراجع عن هذا!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، احذف!',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: `/admin/sponsorships/${sponsorshipId}`,
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                Swal.fire('تم الحذف!', response.message, 'success');
                                $('#sponsorships-table').DataTable().ajax.reload();
                            },
                            error: function() {
                                Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف', 'error');
                            }
                        });
                    }
                });
            });

            // View Sponsorship
            $(document).on('click', '.view-sponsorship', function() {
                const sponsorshipId = $(this).data('id');

                $('#viewSponsorshipModal').modal('show');
                $('#viewSponsorshipContent').html('<div class="text-center py-10"><span class="spinner-border spinner-border-lg text-primary"></span></div>');

                $.ajax({
                    url: `/admin/sponsorships/${sponsorshipId}/edit`,
                    type: 'GET',
                    success: function(data) {
                        let html = `
                            <div class="mb-10">
                                <h3 class="fw-bold text-gray-900 mb-5">معلومات الكافل</h3>
                                <div class="separator separator-dashed mb-7"></div>
                                <div class="row g-5">
                                    <div class="col-md-12">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">المؤسسات الكافلة</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsors && data.sponsors.length > 0 ? data.sponsors.map(s => s.sponsor_name).join(', ') : (data.sponsor?.sponsor_name || data.sponsoring_organization || '-')}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">إسم المؤسسة</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsoring_organization || '-'}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-10">
                                <h3 class="fw-bold text-gray-900 mb-5">معلومات المكفول</h3>
                                <div class="separator separator-dashed mb-7"></div>
                                <div class="row g-5">
                                    <div class="col-md-4">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم الملف الداخلي</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.internal_file_number || '-'}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم الملف الخارجي</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.external_file_number || '-'}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم الهوية</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.identity_number || '-'}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">الإسم</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.orphan_name || '-'}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">إسم المعيل</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.guardian_name || '-'}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم هوية المعيل</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.guardian_identity_number || '-'}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم هاتف المعيل</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.guardian_phone || '-'}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">جوال بديل للمعيل</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.guardian_alt_phone || '-'}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-10">
                                <h3 class="fw-bold text-gray-900 mb-5">تفاصيل الكفالة</h3>
                                <div class="separator separator-dashed mb-7"></div>
                                <div class="row g-5">
                                    <div class="col-md-4">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">مدة الكفالة</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsorship_duration_months ? data.sponsorship_duration_months + ' شهر' : '-'}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">تاريخ البداية</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsorship_start_date || '-'}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">تاريخ النهاية</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsorship_end_date || '-'}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">نوع الكفالة</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsorship_type?.description || '-'}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">حالة الكفالة</span>
                                        <span class="fw-bold text-gray-800 fs-6">
                                            ${data.sponsorship_status_id == 3 || (data.sponsorship_status?.description || '').includes('محدث')
                                                ? '<span class="status-indicator-dot"></span>'
                                                : ''}
                                            ${data.sponsorship_status_id == 5 || (data.sponsorship_status?.description || '').includes('ذهب') || (data.sponsorship_status?.description || '').includes('صرف')
                                                ? '<span class="status-check-mark">✓</span>'
                                                : ''}
                                            ${data.sponsorship_status?.description || '-'}
                                        </span>
                                    </div>
                                    <div class="col-md-12">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">ملاحظات</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.notes || '-'}</span>
                                    </div>
                                </div>
                            </div>
                        `;

                        $('#viewSponsorshipContent').html(html);

                        // 🏦 عرض البيانات البنكية مباشرة من الاستجابة (تم جلبها مع بيانات الكفالة)
                        let bankHtml = `
                            <div class="mb-10">
                                <h3 class="fw-bold text-gray-900 mb-5">
                                    <i class="fas fa-university text-primary me-2"></i>المعلومات البنكية
                                </h3>
                                <div class="separator separator-dashed mb-7"></div>
                        `;

                        if (data.bank_accounts && data.bank_accounts.length > 0) {
                            data.bank_accounts.forEach((account, index) => {
                                const isApproved = account.check_account == 1;
                                const bankName = account.bank ? account.bank.description : '-';

                                bankHtml += `
                                    <div class="border rounded p-4 mb-4 bank-account-card" data-account-id="${account.id}" style="background-color: #f1faff; border: 2px solid #009ef7 !important;">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0 text-primary fw-bold">
                                                <i class="fas fa-university me-2"></i>حساب بنكي رقم ${index + 1}
                                            </h6>
                                            <button type="button"
                                                    class="btn btn-sm approve-bank-account-btn ${isApproved ? 'btn-dark-green' : 'btn-outline-success'}"
                                                    data-account-id="${account.id}"
                                                    data-guardian-file-id="${data.guardian_file_id || ''}"
                                                    ${isApproved ? 'disabled' : ''}>
                                                <i class="fas ${isApproved ? 'fa-check-circle' : 'fa-check'} me-1"></i>
                                                ${isApproved ? 'حساب معتمد' : 'اعتماد الحساب'}
                                            </button>
                                        </div>
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">اسم البنك</span>
                                                <span class="fw-bold text-gray-800 fs-6">${bankName}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">اسم صاحب الحساب</span>
                                                <span class="fw-bold text-gray-800 fs-6">${account.re_guardian_name || '-'}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم هوية صاحب الحساب</span>
                                                <span class="fw-bold text-gray-800 fs-6">${account.person_owner_identity_number || '-'}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم هاتف صاحب الحساب</span>
                                                <span class="fw-bold text-gray-800 fs-6">${account.re_phone_number || '-'}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم IBAN بالدولار</span>
                                                <span class="fw-bold text-gray-800 fs-6">${account.iban_usd || '-'}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">رقم IBAN بالشيكل</span>
                                                <span class="fw-bold text-gray-800 fs-6">${account.iban_shekel || '-'}</span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        } else {
                            bankHtml += `
                                <div class="alert alert-warning d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle fs-2 me-3"></i>
                                    <span class="fw-semibold">لا توجد بيانات بنكية مسجلة لهذا الشخص</span>
                                </div>
                            `;
                        }

                        bankHtml += `</div>`;
                        $('#viewSponsorshipContent').append(bankHtml);
                    },
                    error: function() {
                        $('#viewSponsorshipContent').html('<div class="alert alert-danger">حدث خطأ في تحميل البيانات</div>');
                    }
                });
            });

            // ============================================
            // تصدير Excel مع الفلاتر
            // ============================================

            // تصدير كامل البيانات
            // ربط زر تصدير كامل مع وظيفة DataTable Excel
            $('#export_excel_full_btn').click(function() {
                // تفعيل تصدير Excel من DataTable
                var table = $('#sponsorships-table').DataTable();
                table.button('.buttons-excel').trigger();
                return;
            });

            // الكود القديم للتصدير (احتياطي)
            $('#export_excel_full_btn_old').click(function() {
                exportExcel('full');
            });

            // تصدير بيانات تسجيل الدخول
            $('#export_excel_login_btn').click(function() {
                exportExcel('login');
            });

            // دالة التصدير الموحدة
            function exportExcel(type) {
                console.log('📊 بدء التصدير - النوع:', type);

                // جمع الفلاتر الحالية
                const sponsorFilter = $('#filter_sponsor').val();
                const typeFilter = $('#filter_sponsorship_type').val();
                const statusFilter = $('#filter_sponsorship_status').val();

                // بناء الـ URL
                let exportUrl = '{{ route("admin.sponsorships.export") }}';
                const params = [];

                // إضافة نوع التصدير
                params.push('export_type=' + type);

                if (sponsorFilter) params.push('sponsor_id=' + sponsorFilter);
                if (typeFilter) params.push('sponsorship_type_id=' + typeFilter);
                if (statusFilter) params.push('sponsorship_status_id=' + statusFilter);

                // إضافة البحث من DataTable
                const searchValue = $('#sponsorships-table_filter input').val();
                if (searchValue) params.push('search=' + encodeURIComponent(searchValue));

                const queryString = params.length > 0 ? '?' + params.join('&') : '';

                // عرض رسالة تحميل
                const title = type === 'login' ? 'جاري تصدير بيانات تسجيل الدخول...' : 'جاري التصدير...';
                Swal.fire({
                    title: title,
                    html: 'يرجى الانتظار حتى يتم تجهيز الملف',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // فتح الرابط للتحميل
                window.location.href = exportUrl + queryString;

                // إغلاق رسالة التحميل بعد ثانيتين
                setTimeout(function() {
                    Swal.close();
                    Swal.fire({
                        icon: 'success',
                        title: 'تم التصدير بنجاح',
                        text: 'تم تحميل الملف إلى جهازك',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }, 2000);
            }

            // ============================================
            // رفع ملف Excel واستيراد البيانات
            // ============================================

            // دالة مساعدة لتنظيف modal backdrop
            function cleanupModalBackdrop() {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
                $('body').css('overflow', '');
            }

            // فحص الملف قبل الاستيراد
            // ============================================
            $('#import_sponsor_id, #import_sponsorship_type_id, #import_sponsorship_status_id').select2({
                dir: 'rtl',
                width: '100%',
                dropdownParent: $('#importExcelModal')
            });

            $('#importExcelForm').on('submit', function(e) {
                e.preventDefault();

                // التحقق من الحقول المطلوبة
                if (!$('#import_sponsor_id').val() || !$('#import_sponsorship_type_id').val() ||
                    !$('#import_sponsorship_status_id').val() || !$('#excel_file').val()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'يرجى ملء جميع الحقول المطلوبة'
                    });
                    return;
                }

                const formData = new FormData(this);
                formData.append('check_only', '1'); // فحص فقط

                // إظهار شريط التقدم
                $('#import_progress_container').show();
                $('#validation_results_container').hide();
                $('#import_progress_bar').css('width', '0%');
                $('#import_progress_text').text('0%');
                $('#import_status_text').text('جاري فحص الملف...');
                $('#check_file_btn').prop('disabled', true);
                $('#import_submit_btn').hide();

                $.ajax({
                    url: '{{ route("admin.sponsorships.import") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                const percentComplete = Math.round((e.loaded / e.total) * 100);
                                $('#import_progress_bar').css('width', percentComplete + '%');
                                $('#import_progress_text').text(percentComplete + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        console.log('✅ Response received:', response);
                        $('#import_progress_bar').css('width', '100%');
                        $('#import_progress_text').text('100%');
                        $('#import_status_text').text('اكتمل الفحص');
                        $('#check_file_btn').prop('disabled', false);

                        setTimeout(() => {
                            $('#import_progress_container').hide();
                        }, 1000);

                        if (response.success) {
                            console.log('✅ Displaying validation results...');
                            displayValidationResults(response);
                        } else {
                            console.error('❌ Response success is false:', response);
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: response.message || 'حدث خطأ أثناء فحص الملف'
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('❌ AJAX Error:', xhr);
                        console.error('Status:', xhr.status);
                        console.error('Response:', xhr.responseText);

                        $('#check_file_btn').prop('disabled', false);
                        $('#import_progress_container').hide();

                        let errorMessage = 'حدث خطأ أثناء فحص الملف';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            errorMessage = 'خطأ في الخادم: ' + xhr.responseText.substring(0, 200);
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            html: errorMessage
                        }).then(() => {
                            cleanupModalBackdrop();
                        });
                    }
                });
            });

            // دوال مساعدة لعرض نوع الشخص
            function getPersonTypeLabel(type) {
                const types = {
                    'معيل': 'معيل',
                    'معيل اسره': 'معيل أسرة',
                    'معيل اسرة': 'معيل أسرة',
                    'معيل أسرة': 'معيل أسرة',
                    'معيل عائله': 'معيل عائلة',
                    'معيل عائلة': 'معيل عائلة',
                    'فرد عايله': 'فرد عائلة',
                    'فرد عائله': 'فرد عائلة',
                    'فرد عائلة': 'فرد عائلة',
                    'فرد اسره': 'فرد أسرة',
                    'فرد اسرة': 'فرد أسرة',
                    'فرد أسرة': 'فرد أسرة',
                    'فرد الع ائله': 'فرد عائلة',
                    'أب متوفي': 'أب متوفي',
                    'اب متوفي': 'أب متوفي',
                    'الاب المتوفي': 'أب متوفي',
                    'أم متوفيه': 'أم متوفية',
                    'ام متوفيه': 'أم متوفية',
                    'الام المتوفيه': 'أم متوفية',
                    'أم متوفية': 'أم متوفية',
                    'ام متوفية': 'أم متوفية'
                };
                return types[type] || type;
            }

            function getPersonTypeBadge(type) {
                if (type.includes('معيل')) {
                    return 'badge-primary';
                } else if (type.includes('فرد')) {
                    return 'badge-info';
                } else if (type.includes('متوفي') || type.includes('متوفيه') || type.includes('متوفية')) {
                    return 'badge-secondary';
                }
                return 'badge-light';
            }

            function getTableNameInArabic(tableName) {
                const tables = {
                    'data': 'بيانات المعيلين',
                    're_people': 'أفراد الأسرة',
                    'dead_people (father)': 'المتوفين (أب)',
                    'dead_people (mother)': 'المتوفين (أم)'
                };
                return tables[tableName] || tableName;
            }

            // عرض نتائج الفحص
            function displayValidationResults(response) {
                console.log('🔍 Displaying Validation Results...');
                console.log('📊 Full Response:', response);
                console.log('✅ Response.validation exists:', !!response.validation);

                if (!response.validation) {
                    console.error('❌ No validation data in response!');
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: 'لم يتم استلام بيانات التحقق من الخادم'
                    });
                    return;
                }

                console.log('📋 Missing Persons:', response.validation.missing_persons);
                console.log('🏦 Missing Banks:', response.validation.missing_banks);

                const container = $('#validation_results');
                container.empty();

                let hasErrors = false;

                // المعلومات الأساسية
                let summaryHtml = `
                    <div class="alert alert-primary d-flex align-items-center mb-5">
                        <i class="ki-duotone ki-information-5 fs-2x text-primary me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>
                            <h5 class="mb-1">معلومات الملف</h5>
                            <p class="mb-0">إجمالي السجلات: <strong>${response.validation.total_rows}</strong></p>
                        </div>
                    </div>
                `;
                container.append(summaryHtml);

                // الأشخاص غير الموجودين (معيلين، أفراد عائلة، متوفين) - تحذير فقط، لا يمنع الاستيراد
                if (response.validation.missing_persons && response.validation.missing_persons.length > 0) {
                    // 🆕 لا يعتبر خطأ - سيتم الإدخال بدون relation_id_number
                    // hasErrors = false; // لا نغير hasErrors

                    let personsTableHtml = `
                        <div class="alert alert-warning mb-5">
                            <div class="d-flex align-items-start mb-3">
                                <i class="ki-duotone ki-information-5 fs-2x text-warning me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">⚠️ أشخاص غير موجودين في قاعدة البيانات (${response.validation.missing_persons.length})</h5>
                                    <p class="mb-1"><strong>ملاحظة:</strong> سيتم إدخال هؤلاء الأشخاص في جدول الكفالات <strong>بدون ربطهم</strong> برقم ملف (relation_id_number).</p>
                                    <p class="mb-3">يمكنك إنشاء سجلاتهم أولاً ثم إعادة الفحص، أو المتابعة بالاستيراد مباشرة:</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="text-center" style="width: 60px;">#</th>
                                            <th>نوع الشخص</th>
                                            <th>رقم الهوية</th>
                                            <th>الاسم</th>
                                            <th>الهاتف</th>
                                            <th>جوال بديل</th>
                                            <th>الجدول المستهدف</th>
                                            <th class="text-center" style="width: 80px;">الصف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;

                    response.validation.missing_persons.forEach((person, index) => {
                        const typeLabel = getPersonTypeLabel(person.type);
                        const typeBadge = getPersonTypeBadge(person.type);

                        // عرض أرقام الهاتف إذا كان الشخص معيل
                        let phoneDisplay = '-';
                        let altPhoneDisplay = '-';
                        let phoneStatusBadge = '';

                        if (person.type === 'معيل') {
                            phoneDisplay = person.phone || '-';
                            altPhoneDisplay = person.alt_phone || '-';

                            // عرض حالة التحديث إذا كانت موجودة
                            if (person.phone_status) {
                                phoneStatusBadge = `<br><small class="text-muted">${person.phone_status}</small>`;
                            }
                        }

                        personsTableHtml += `
                            <tr>
                                <td class="text-center fw-bold">${index + 1}</td>
                                <td><span class="badge ${typeBadge}">${typeLabel}</span></td>
                                <td class="font-monospace">${person.identity || '-'}</td>
                                <td>${person.name || '-'}</td>
                                <td class="font-monospace">${phoneDisplay}${phoneStatusBadge}</td>
                                <td class="font-monospace">${altPhoneDisplay}</td>
                                <td><code class="text-primary">${getTableNameInArabic(person.target_table)}</code></td>
                                <td class="text-center"><span class="badge badge-light-primary">${person.row}</span></td>
                            </tr>
                        `;
                    });

                    personsTableHtml += `
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-success mt-4 mb-0">
                                <i class="ki-duotone ki-information-5 fs-2x text-success me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <strong>ملاحظة:</strong> هؤلاء الأشخاص غير مسجلين في النظام. سيتم إدخال بيانات الكفالة الخاصة بهم في جدول الكفالات فقط بدون ربطهم بالملف الذي يحمل المعلومات المسبقة عنهم.
                                <br><small class="text-muted">لإضافة بياناتهم الكاملة، يرجى استخدام بوابة إدارة  التسجيلات.</small>
                            </div>
                        </div>
                    `;
                    container.append(personsTableHtml);
                }

                // عرض المعيلين الذين تم تحديث أرقام هواتفهم
                if (response.validation.updated_phones && response.validation.updated_phones.length > 0) {
                    let updatedPhonesHtml = `
                        <div class="alert alert-info mb-5">
                            <div class="d-flex align-items-start mb-3">
                                <i class="ki-duotone ki-phone fs-2x text-info me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">📞 تم تحديث أرقام الهاتف (${response.validation.updated_phones.length})</h5>
                                    <p class="mb-3">تم تحديث أرقام الهاتف التالية في قاعدة البيانات:</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm align-middle">
                                    <thead class="table-info">
                                        <tr>
                                            <th class="text-center" style="width: 60px;">#</th>
                                            <th>رقم الهوية</th>
                                            <th>الاسم</th>
                                            <th>رقم الهاتف القديم</th>
                                            <th>رقم الهاتف الجديد</th>
                                            <th>جوال بديل قديم</th>
                                            <th>جوال بديل جديد</th>
                                            <th class="text-center" style="width: 80px;">الصف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;

                    response.validation.updated_phones.forEach((person, index) => {
                        const phoneOld = person.changes.phone ? person.changes.phone.old : '-';
                        const phoneNew = person.changes.phone ? person.changes.phone.new : '-';
                        const altPhoneOld = person.changes.alt_phone ? person.changes.alt_phone.old : '-';
                        const altPhoneNew = person.changes.alt_phone ? person.changes.alt_phone.new : '-';

                        updatedPhonesHtml += `
                            <tr>
                                <td class="text-center fw-bold">${index + 1}</td>
                                <td class="font-monospace">${person.identity}</td>
                                <td>${person.name}</td>
                                <td class="font-monospace ${person.changes.phone ? 'text-decoration-line-through text-muted' : ''}">${phoneOld}</td>
                                <td class="font-monospace ${person.changes.phone ? 'fw-bold text-success' : ''}">${phoneNew}</td>
                                <td class="font-monospace ${person.changes.alt_phone ? 'text-decoration-line-through text-muted' : ''}">${altPhoneOld}</td>
                                <td class="font-monospace ${person.changes.alt_phone ? 'fw-bold text-success' : ''}">${altPhoneNew}</td>
                                <td class="text-center"><span class="badge badge-light-primary">${person.row}</span></td>
                            </tr>
                        `;
                    });

                    updatedPhonesHtml += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    container.append(updatedPhonesHtml);
                }

                // البنوك المفقودة - هذه فقط تمنع الاستيراد
                if (response.validation.missing_banks && response.validation.missing_banks.length > 0) {
                    hasErrors = true; // البنوك فقط تمنع الاستيراد
                    let banksHtml = `
                        <div class="alert alert-danger d-flex align-items-start mb-5">
                            <i class="ki-duotone ki-cross-circle fs-2x text-danger me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="flex-grow-1">
                                <h5 class="mb-2">❌ بنوك غير موجودة (${response.validation.missing_banks.length})</h5>
                                <p class="mb-2">يجب إضافة هذه البنوك من قسم إدارة البنوك قبل الاستيراد:</p>
                                <div class="bg-light-danger p-3 rounded">
                                    ${response.validation.missing_banks.map(bank => `<span class="badge badge-danger me-2 mb-2">${bank}</span>`).join('')}
                                </div>
                            </div>
                        </div>
                    `;
                    container.append(banksHtml);
                }

                $('#validation_results_container').show();

                if (!hasErrors) {
                    $('#validation_success').show();
                    $('#import_submit_btn').show();
                } else {
                    $('#validation_success').hide();
                    $('#import_submit_btn').hide();
                }
            }

            // 🚫 تم إزالة كود إنشاء السجلات المفقودة
            // لأنه يُسبب إدخال بيانات خاطئة في الجداول (data, re_people, dead_people)
            // يجب إدخال هذه البيانات من خلال البوابات المخصصة فقط

            // تنفيذ الاستيراد الفعلي
            $('#import_submit_btn').on('click', function() {
                const formData = new FormData($('#importExcelForm')[0]);
                // إزالة check_only لتنفيذ الاستيراد

                $('#import_progress_container').show();
                $('#import_progress_bar').css('width', '0%');
                $('#import_progress_text').text('0%');
                $('#import_status_text').text('جاري الاستيراد...');
                $('#import_submit_btn').prop('disabled', true);

                $.ajax({
                    url: '{{ route("admin.sponsorships.import") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#import_progress_bar').css('width', '100%');
                        $('#import_progress_text').text('100%');

                        if (response.success) {
                            $('#importExcelModal').modal('hide');

                            // 🆕 إضافة معلومات الربط في الرسالة
                            let linkedInfo = '';
                            if (response.summary.linked !== undefined && response.summary.unlinked !== undefined) {
                                linkedInfo = `
                                    <hr>
                                    <p><strong>📊 تفاصيل الربط:</strong></p>
                                    <p><i class="ki-duotone ki-check-circle text-success"></i> <strong>مرتبطين بملفات موجودة:</strong> <span class="text-success">${response.summary.linked}</span></p>
                                    <p><i class="ki-duotone ki-information text-warning"></i> <strong>بدون ربط (سجلات جديدة):</strong> <span class="text-warning">${response.summary.unlinked}</span></p>
                                `;
                            }

                            Swal.fire({
                                icon: 'success',
                                title: 'اكتملت عملية الاستيراد',
                                html: `
                                    <p><strong>إجمالي السجلات:</strong> ${response.summary.total}</p>
                                    <p><strong>تم الإدخال بنجاح:</strong> <span class="text-success">${response.summary.success}</span></p>
                                    <p><strong>أخطاء:</strong> <span class="text-danger">${response.summary.errors}</span></p>
                                    ${linkedInfo}
                                    <div class="alert alert-info mt-3 text-start" style="font-size: 0.9rem;">
                                        <strong>ℹ️ ملاحظة:</strong> السجلات غير المرتبطة تم إدخالها في جدول الكفالات فقط.
                                        يمكنك لاحقاً ربطها يدوياً عند إضافة بيانات الأشخاص.
                                    </div>
                                `
                            }).then(() => {
                                // تنظيف modal backdrop بعد إغلاق SweetAlert
                                cleanupModalBackdrop();

                                // إعادة تحميل الجدول
                                if ($.fn.DataTable.isDataTable('#sponsorships-table')) {
                                    $('#sponsorships-table').DataTable().ajax.reload();
                                }
                            });
                        }
                    },
                    error: function(xhr) {
                        $('#import_submit_btn').prop('disabled', false);
                        $('#import_progress_container').hide();

                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'حدث خطأ أثناء الاستيراد'
                        }).then(() => {
                            cleanupModalBackdrop();
                        });
                    }
                });
            });

            // إعادة تعيين النموذج عند إغلاق المودال
            $('#importExcelModal').on('hidden.bs.modal', function() {
                $('#importExcelForm')[0].reset();
                $('#import_progress_container').hide();
                $('#validation_results_container').hide();
                $('#check_file_btn').prop('disabled', false).show();
                $('#import_submit_btn').prop('disabled', false).hide();
                $('#import_sponsor_id, #import_sponsorship_type_id, #import_sponsorship_status_id').val('').trigger('change');

                // تنظيف modal backdrop للتأكد
                cleanupModalBackdrop();
            });

            // ====================================================================
            // معالج زر اعتماد الحساب البنكي
            // ====================================================================
            $(document).on('click', '.approve-bank-account-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const accountId = $btn.data('account-id');
                const guardianFileId = $btn.data('guardian-file-id');

                // تعطيل الزر مؤقتاً
                $btn.prop('disabled', true);
                const originalHtml = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin me-1"></i>جاري الاعتماد...');

                $.ajax({
                    url: '{{ route("admin.records.management.approveBankAccount") }}',
                    type: 'POST',
                    data: {
                        account_id: accountId,
                        guardian_file_id: guardianFileId,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            // تحديث جميع الأزرار: إعادتها للحالة الافتراضية (غير معتمد)
                            $('.bank-account-card').each(function() {
                                const $otherBtn = $(this).find('.approve-bank-account-btn');
                                $otherBtn.removeClass('btn-dark-green').addClass('btn-outline-success');
                                $otherBtn.html('<i class="fas fa-check me-1"></i>اعتماد الحساب');
                                $otherBtn.prop('disabled', false);
                            });

                            // تحديث الزر الحالي فقط (أخضر غامق ومعطل)
                            $btn.removeClass('btn-outline-success').addClass('btn-dark-green');
                            $btn.html('<i class="fas fa-check-circle me-1"></i>حساب معتمد');
                            $btn.prop('disabled', true);

                            // عرض رسالة نجاح
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الاعتماد',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            $btn.html(originalHtml);
                            $btn.prop('disabled', false);

                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: response.message || 'حدث خطأ أثناء اعتماد الحساب'
                            });
                        }
                    },
                    error: function(xhr) {
                        $btn.html(originalHtml);
                        $btn.prop('disabled', false);

                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: 'حدث خطأ أثناء اعتماد الحساب البنكي'
                        });
                    }
                });
            });
        });
    </script>
@endpush
