@extends('admin.dashboard.toolbars.index')

@section('content')
<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h2 class="fw-bold text-gray-900">
                        <i class="fas fa-cogs fs-2 me-2"></i>
                        إدارة حقول الجمعيات
                    </h2>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" id="kt_sponsors_fields_search"
                               class="form-control form-control-solid w-250px ps-13"
                               placeholder="بحث في الجمعيات..." />
                    </div>
                </div>
            </div>
            <!--end::Card header-->

            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="sponsors_fields_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">الرقم</th>
                            <th class="min-w-150px">رقم الملف</th>
                            <th class="min-w-200px">اسم الجمعية</th>
                            <th class="min-w-150px">البلد</th>
                            <th class="min-w-150px">رقم الهاتف</th>
                            <th class="min-w-150px text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <!-- سيتم ملء البيانات هنا من خلال DataTable -->
                    </tbody>
                </table>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
    </div>
</div>
<!--end::Content-->

<!--begin::Modal لإدارة الحقول-->
<div class="modal fade" id="fieldsManagementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h2 class="fw-bold text-white" id="modalTitle">
                    <i class="fas fa-cogs me-2"></i>
                    إدارة حقول الجمعية: <span id="sponsor_name_display"></span>
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-light" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1 text-white">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <div class="modal-body py-10 px-lg-17">
                <!--begin::Tabs Navigation-->
                <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#fields_tab">
                            <i class="fas fa-list-check me-2"></i>
                            الحقول
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#documents_tab">
                            <i class="fas fa-file-alt me-2"></i>
                            الوثائق المطلوبة
                        </a>
                    </li>
                </ul>
                <!--end::Tabs Navigation-->

                <!--begin::Tab Content-->
                <div class="tab-content" id="managementTabContent">
                    <!--begin::Fields Tab-->
                    <div class="tab-pane fade show active" id="fields_tab" role="tabpanel">
                        <div class="row">
                            <!--begin::Sidebar - قائمة الحقول-->
                            <div class="col-md-4">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h3 class="card-title">الحقول المتاحة</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-5">
                                            <input type="text" class="form-control form-control-sm"
                                                   id="searchFields"
                                                   placeholder="بحث في الحقول...">
                                        </div>
                                        <div class="scroll-y" style="max-height: 600px;">
                                            <div id="fieldsListContainer">
                                                <!-- سيتم ملء قائمة الحقول هنا -->
                                                <div class="text-center py-10">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">جاري التحميل...</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Sidebar-->

                            <!--begin::Main Content - معاينة الحقول المختارة-->
                            <div class="col-md-8">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h3 class="card-title">الحقول المفعلة للجمعية</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info d-flex align-items-center mb-5">
                                            <i class="fas fa-info-circle fs-2x me-4"></i>
                                            <div>
                                                <strong>ملاحظة:</strong> قم بتفعيل أو إلغاء تفعيل الحقول التي تريد إظهارها أو إخفائها للجمعية.
                                                الحقول المفعلة ستظهر في نماذج التسجيل والتقارير الخاصة بهذه الجمعية.
                                            </div>
                                        </div>

                                        <div class="scroll-y" style="max-height: 600px;">
                                            <div id="activeFieldsContainer">
                                                <!-- سيتم ملء الحقول المفعلة هنا -->
                                                <div class="text-center py-10">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">جاري التحميل...</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Main Content-->
                        </div>
                    </div>
                    <!--end::Fields Tab-->

                    <!--begin::Documents Tab - النظام الجديد-->
                    <div class="tab-pane fade" id="documents_tab" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h3 class="card-title fw-bold">
                                    <i class="fas fa-file-check text-primary me-2"></i>
                                    إدارة الوثائق المطلوبة
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-6">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>تعليمات:</strong> حدد الوثائق التي سيتم عرضها في صفحة تحديث البيانات. فقط الوثائق المحددة ستظهر للمستخدمين.
                                </div>

                                <div id="documents_loading" class="text-center py-10" style="display: none;">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">جاري التحميل...</span>
                                    </div>
                                    <p class="mt-3 text-muted">جاري تحميل الوثائق...</p>
                                </div>

                                <div id="documents_list" class="table-responsive">
                                    <table class="table table-row-bordered table-hover gs-7">
                                        <thead>
                                            <tr class="fw-bold fs-6 text-gray-800 border-bottom-2 border-gray-200">
                                                <th class="w-50px text-center">
                                                    <input type="checkbox" class="form-check-input" id="select_all_docs">
                                                </th>
                                                <th class="w-80px">المعرف</th>
                                                <th>اسم الوثيقة</th>
                                                <th class="w-120px">البادئة</th>
                                                <th class="w-120px text-center">الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody id="documents_tbody">
                                            <!-- سيتم ملؤها بواسطة JavaScript -->
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-5 d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span id="enabled_count">0</span> وثيقة مفعلة
                                    </div>
                                    <button type="button" class="btn btn-success btn-lg" id="save_documents_btn">
                                        <i class="fas fa-save me-2"></i>
                                        حفظ التغييرات
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Documents Tab-->
                </div>
                <!--end::Tab Content-->
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-success" id="saveDocumentsBtnFooter" style="display: none;">
                    <i class="fas fa-file-check me-2"></i>
                    حفظ إعدادات الوثائق
                </button>
                <button type="button" class="btn btn-primary" id="saveFieldsBtnFooter">
                    <i class="fas fa-save me-2"></i>
                    حفظ التغييرات
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

@endsection

@push('styles')
<style>
    .field-item {
        border: 1px solid #e4e6ef;
        border-radius: 0.475rem;
        padding: 1rem;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .field-item:hover {
        background-color: #f5f8fa;
        border-color: #009ef7;
    }

    .field-item.active {
        background-color: #e8f4fd;
        border-color: #009ef7;
    }

    .field-switch {
        transform: scale(1.2);
    }

    .field-category-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    /* إصلاح محاذاة DataTable */
    #sponsors_fields_table {
        width: 100% !important;
        table-layout: fixed;
    }

    #sponsors_fields_table thead th {
        text-align: right !important;
        padding: 0.75rem 1rem !important;
        vertical-align: middle !important;
        white-space: nowrap;
    }

    #sponsors_fields_table tbody td {
        text-align: right !important;
        padding: 0.75rem 1rem !important;
        vertical-align: middle !important;
    }

    #sponsors_fields_table thead th:first-child,
    #sponsors_fields_table tbody td:first-child {
        text-align: center !important;
    }

    #sponsors_fields_table thead th:last-child,
    #sponsors_fields_table tbody td:last-child {
        text-align: center !important;
    }

    /* إصلاح عرض الأعمدة */
    #sponsors_fields_table th:nth-child(1),
    #sponsors_fields_table td:nth-child(1) {
        width: 80px !important;
    }

    #sponsors_fields_table th:nth-child(2),
    #sponsors_fields_table td:nth-child(2) {
        width: 120px !important;
    }

    #sponsors_fields_table th:nth-child(3),
    #sponsors_fields_table td:nth-child(3) {
        width: auto !important;
        min-width: 200px;
    }

    #sponsors_fields_table th:nth-child(4),
    #sponsors_fields_table td:nth-child(4) {
        width: 150px !important;
    }

    #sponsors_fields_table th:nth-child(5),
    #sponsors_fields_table td:nth-child(5) {
        width: 150px !important;
    }

    #sponsors_fields_table th:nth-child(6),
    #sponsors_fields_table td:nth-child(6) {
        width: 220px !important; /* عرض أكبر لعمود الإجراءات */
    }

    /* تحسين مظهر الجدول */
    #sponsors_fields_table.dataTable {
        border-collapse: separate !important;
        border-spacing: 0 !important;
    }

    .dataTables_wrapper .dataTables_scroll {
        overflow-x: hidden;
    }

    /* تنسيق أزرار Google Drive المحدثة */
    .toggle-google-drive-btn {
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        border: 2px solid transparent;
    }

    .toggle-google-drive-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .toggle-google-drive-btn.btn-success {
        background: linear-gradient(135deg, #50cd89 0%, #40b76f 100%);
        border-color: #50cd89;
    }

    .toggle-google-drive-btn.btn-success:hover {
        background: linear-gradient(135deg, #40b76f 0%, #38a665 100%);
    }

    .toggle-google-drive-btn.btn-light-danger {
        background: #f1f1f4;
        color: #7e8299;
        border-color: #e4e6ef;
    }

    .toggle-google-drive-btn.btn-light-danger:hover {
        background: #f8f9fa;
        border-color: #f1416c;
        color: #f1416c;
    }

    /* تنسيق بطاقات الوثائق */
    .document-card {
        transition: all 0.3s ease;
    }

    .document-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }

    .document-card.opacity-50 {
        opacity: 0.5;
    }

    /* تنسيق نصوص الحالة */
    .enabled-text {
        display: none;
    }

    .disabled-text {
        display: inline;
    }

    .document-enabled-toggle:checked ~ label .enabled-text {
        display: inline;
    }

    .document-enabled-toggle:checked ~ label .disabled-text {
        display: none;
    }

    /* تنسيق التبويبات */
    .nav-tabs .nav-link {
        font-weight: 600;
        color: #7e8299;
        padding: 1rem 1.5rem;
    }

    .nav-tabs .nav-link.active {
        color: #009ef7;
        border-bottom: 3px solid #009ef7;
    }

    .nav-tabs .nav-link:hover {
        color: #009ef7;
    }
</style>
@endpush

@push('scriptsCode')
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/documents-management-new.js') }}"></script>
<script src="{{ asset('js/sponsor-fields-management.js') }}"></script>

<script>
$(document).ready(function() {
    console.log('✅ صفحة إدارة حقول الجمعيات جاهزة');
});
</script>
@endpush
