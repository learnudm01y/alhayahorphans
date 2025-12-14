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

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
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
        width: 180px !important;
    }

    /* تحسين مظهر الجدول */
    #sponsors_fields_table.dataTable {
        border-collapse: separate !important;
        border-spacing: 0 !important;
    }

    .dataTables_wrapper .dataTables_scroll {
        overflow-x: hidden;
    }
</style>
@endpush

@push('scriptsCode')
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/sponsor-fields-management.js') }}"></script>

<script>
$(document).ready(function() {
    // الكود الآن في ملف sponsor-fields-management.js
    console.log('صفحة إدارة حقول الجمعيات جاهزة');
});
</script>
@endpush
