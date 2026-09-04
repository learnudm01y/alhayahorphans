@extends('admin.dashboard.toolbars.index')

@section('content')
<!--begin::Toolbar-->
{{-- <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                إدارة الجمعيات
            </h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">الرئيسية</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-500 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">إدارة الجمعيات</li>
            </ul>
        </div>
    </div>
</div> --}}
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">
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
                        <input type="text" id="kt_sponsor_search"
                               class="form-control form-control-solid w-250px ps-13"
                               placeholder="بحث في الجمعيات..." />
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end" data-kt-sponsor-table-toolbar="base">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sponsorModal">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            إضافة جمعية جديدة
                        </button>
                    </div>
                </div>
            </div>
            <!--end::Card header-->

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

<!-- Modal for Add/Edit Sponsor -->
<div class="modal fade" id="sponsorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modalTitle">إضافة جمعية جديدة</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <form id="sponsorForm">
                @csrf
                <input type="hidden" id="sponsor_id" name="sponsor_id">
                <input type="hidden" id="form_method" name="_method" value="POST">
                <input type="hidden" id="file_id" name="file_id" value="{{ $file_id ?? 0 }}">

                <div class="modal-body py-10 px-lg-17">
                    <div class="scroll-y me-n7 pe-7" style="max-height: 600px; overflow-y: auto;">

                        <!--begin::معلومات أساسية-->
                        <div class="mb-7">
                            <h3 class="fw-bold text-gray-900 mb-5">المعلومات الأساسية</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <!--begin::Input group-->
                            <div class="col-md-6 fv-row">
                                <label class="required fs-6 fw-semibold mb-2">اسم الجمعية</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="أدخل اسم الجمعية الكامل" name="sponsor_name"
                                       id="sponsor_name" required />
                            </div>
                            <!--end::Input group-->

                            <!--begin::Input group-->
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">الاسم المختصر</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="الاسم المختصر للجمعية" name="sponsor_short_name"
                                       id="sponsor_short_name" />
                            </div>
                            <!--end::Input group-->
                        </div>

                        <!--begin::معلومات الاتصال-->
                        <div class="mb-7 mt-10">
                            <h3 class="fw-bold text-gray-900 mb-5">معلومات الاتصال</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم الهاتف</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="+970 599 123 456" name="sponsor_phone_number"
                                       id="sponsor_phone_number" />
                            </div>
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">البريد الإلكتروني</label>
                                <input type="email" class="form-control form-control-solid"
                                       placeholder="example@example.com" name="sponsor_email"
                                       id="sponsor_email" />
                            </div>
                        </div>

                        <!--begin::Input group-->
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-semibold mb-2">العنوان</label>
                            <textarea class="form-control form-control-solid"
                                      rows="3" name="sponsor_address" id="sponsor_address"
                                      placeholder="عنوان الجمعية الكامل"></textarea>
                        </div>
                        <!--end::Input group-->

                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-semibold mb-2">الدولة</label>
                            <select name="country_code" id="country_code"
                                    aria-label="Select a Country"
                                    data-control="select2"
                                    data-placeholder="حدد الدولة..."
                                    class="form-select form-select-solid">
                                <option value="">حدد الدولة...</option>
                                @foreach ($countries as $country)
                                    @if(!empty($country->flag) && $country->code != '0')
                                    <option value="{{ $country->code }}"
                                        data-kt-flag="{{ asset('admin/assets/media/flags/' . $country->flag) }}">
                                        {{ $country->ci_birth_cd }} ({{ $country->code }})
                                    </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <!--begin::معلومات البنك-->
                        <div class="mb-7 mt-10">
                            <h3 class="fw-bold text-gray-900 mb-5">المعلومات البنكية</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">البنك</label>
                                <select class="form-select form-select-solid" name="sponsor_bank_name_id"
                                        id="sponsor_bank_name_id">
                                    <option value="">اختر البنك</option>
                                    @foreach(\App\Models\BankName::all() as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->description }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم الحساب البنكي</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="رقم الحساب" name="sponsor_account_bank_number"
                                       id="sponsor_account_bank_number" />
                            </div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">Swift Code</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="SWIFT CODE" name="sponsor_bank_swift_code"
                                       id="sponsor_bank_swift_code" />
                            </div>
                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">هاتف البنك</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="رقم هاتف البنك" name="sponsor_bank_related_phone_number"
                                       id="sponsor_bank_related_phone_number" />
                            </div>
                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">عملة الحساب</label>
                                <select class="form-select form-select-solid" name="sponsor_bank_account_currency"
                                        id="sponsor_bank_account_currency">
                                    <option value="">اختر العملة</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}">{{ $currency->description }}</option>
                                    @endforeach
                                </select>
                            </div>
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

<!-- Modal for View Sponsor Details -->
<div class="modal fade" id="viewSponsorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h2 class="fw-bold text-gray-800">
                    <i class="ki-duotone ki-information-5 fs-1 text-info me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    تفاصيل الجمعية
                </h2>
                <input type="hidden" id="view_sponsor_id">
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <div class="modal-body py-10 px-lg-17">
                <div class="scroll-y me-n7 pe-7" style="max-height: 600px; overflow-y: auto;">

                    <!-- معلومات أساسية -->
                    <div class="mb-10">
                        <h3 class="fw-bold text-gray-900 mb-5">
                            <i class="ki-duotone ki-abstract-13 fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            المعلومات الأساسية
                        </h3>
                        <div class="separator separator-dashed mb-7"></div>

                        <div class="row g-5">
                            <div class="col-md-4">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">رقم الملف</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_file_id">-</span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">اسم الجمعية</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_sponsor_name">-</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">الاسم المختصر</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_sponsor_short_name">-</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">رقم الهاتف</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_sponsor_phone_number">-</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">البريد الإلكتروني</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_sponsor_email">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">العنوان</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_sponsor_address">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">الدولة</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_country_name">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- معلومات البنك -->
                    <div class="mb-10">
                        <h3 class="fw-bold text-gray-900 mb-5">
                            <i class="ki-duotone ki-bank fs-2 text-success me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            المعلومات البنكية
                        </h3>
                        <div class="separator separator-dashed mb-7"></div>

                        <div class="row g-5">
                            <div class="col-md-6">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">اسم البنك</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_bank_name">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">رقم الحساب البنكي</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_account_number">-</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">رمز SWIFT</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_swift_code">-</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">رقم هاتف البنك</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_bank_phone">-</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-600 fs-7 mb-1">عملة الحساب</span>
                                    <span class="fw-bold text-gray-800 fs-6" id="view_currency">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!--begin::مندوبي الجمعية-->
                    <div class="mb-10">
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <h3 class="fw-bold text-gray-900">مندوبي الجمعية</h3>
                            <button type="button" class="btn btn-sm btn-primary" id="addEmployeeBtn">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                إضافة مندوب
                            </button>
                        </div>
                        <div class="separator mb-5"></div>

                        <div id="employeesList">
                            <div class="text-center text-muted py-5">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                جاري التحميل...
                            </div>
                        </div>
                    </div>
                    <!--end::مندوبي الجمعية-->

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-warning" id="designReportBtn">
                    <i class="bi bi-palette-fill"></i>
                    تصميم التقرير
                </button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="ki-duotone ki-pencil fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    تعديل
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEmployeeModalLabel">إضافة مندوب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addEmployeeForm">
                @csrf
                <input type="hidden" id="employee_sponsor_id" name="sponsor_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="employee_name" class="form-label required">اسم المندوب</label>
                        <input type="text" class="form-control" id="employee_name" name="employee_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="saveEmployeeBtn">
                        <span class="indicator-label">حفظ</span>
                        <span class="indicator-progress" style="display: none;">
                            جاري الحفظ... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Edit Sponsor -->
<div class="modal fade" id="editSponsorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">تعديل بيانات الجمعية</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <form id="editSponsorForm" autocomplete="off">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_sponsor_id" name="sponsor_id">
                <input type="hidden" id="edit_file_id" name="file_id">

                <div class="modal-body py-10 px-lg-17">
                    <div class="scroll-y me-n7 pe-7" style="max-height: 600px; overflow-y: auto; pointer-events: auto;">

                        <!--begin::معلومات أساسية-->
                        <div class="mb-7">
                            <h3 class="fw-bold text-gray-900 mb-5">المعلومات الأساسية</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="required fs-6 fw-semibold mb-2">اسم الجمعية</label>
                                <input type="text" class="form-control" autocomplete="off"
                                       placeholder="أدخل اسم الجمعية الكامل" name="sponsor_name"
                                       id="edit_sponsor_name" required
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">الاسم المختصر</label>
                                <input type="text" class="form-control" autocomplete="off"
                                       placeholder="أدخل الاسم المختصر" name="sponsor_short_name"
                                       id="edit_sponsor_short_name"
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم الهاتف</label>
                                <input type="text" class="form-control" autocomplete="off"
                                       placeholder="مثال: +1234567890" name="sponsor_phone_number"
                                       id="edit_sponsor_phone_number"
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">البريد الإلكتروني</label>
                                <input type="email" class="form-control" autocomplete="off"
                                       placeholder="example@domain.com" name="sponsor_email"
                                       id="edit_sponsor_email"
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">العنوان</label>
                                <input type="text" class="form-control" autocomplete="off"
                                       placeholder="أدخل العنوان" name="sponsor_address"
                                       id="edit_sponsor_address"
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">الدولة</label>
                                <select class="form-select" name="country_code"
                                        id="edit_country_code"
                                        data-control="select2"
                                        data-dropdown-parent="#editSponsorModal"
                                        data-placeholder="اختر الدولة"
                                        data-allow-clear="true">
                                    <option value="">اختر الدولة</option>
                                    @foreach($countries as $country)
                                        @if(!empty($country->flag) && $country->code != '0')
                                        <option value="{{ $country->code }}"
                                            data-kt-flag="{{ asset('admin/assets/media/flags/' . $country->flag) }}">
                                            {{ $country->ci_birth_cd }} ({{ $country->code }})
                                        </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!--begin::معلومات بنكية-->
                        <div class="mb-7">
                            <h3 class="fw-bold text-gray-900 mb-5">المعلومات البنكية</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">اسم البنك</label>
                                <select class="form-select" name="sponsor_bank_name_id"
                                        id="edit_sponsor_bank_name_id"
                                        style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;">
                                    <option value="0">اختر البنك</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->description }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم الحساب البنكي</label>
                                <input type="text" class="form-control" autocomplete="off"
                                       placeholder="أدخل رقم الحساب" name="sponsor_account_bank_number"
                                       id="edit_sponsor_account_bank_number"
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رمز SWIFT</label>
                                <input type="text" class="form-control" autocomplete="off"
                                       placeholder="مثال: ABCDUS33XXX" name="sponsor_bank_swift_code"
                                       id="edit_sponsor_bank_swift_code"
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم هاتف البنك</label>
                                <input type="text" class="form-control" autocomplete="off"
                                       placeholder="رقم التواصل مع البنك" name="sponsor_bank_related_phone_number"
                                       id="edit_sponsor_bank_related_phone_number"
                                       style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;" />
                            </div>

                            <div class="col-md-4 fv-row">
                                <label class="fs-6 fw-semibold mb-2">عملة الحساب</label>
                                <select class="form-select" name="sponsor_bank_account_currency"
                                        id="edit_sponsor_bank_account_currency"
                                        style="position: relative !important; z-index: 9999 !important; pointer-events: auto !important;">
                                    <option value="">اختر العملة</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}">{{ $currency->description }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="editSubmitBtn">
                        <span class="indicator-label">حفظ التعديلات</span>
                        <span class="indicator-progress">جاري الحفظ...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Design Modal -->
<div class="modal fade" id="reportDesignModal" tabindex="-1" aria-labelledby="reportDesignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="reportDesignModalLabel">
                    <i class="bi bi-palette-fill"></i> تصميم تقرير الجمعية
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reportDesignForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="design_sponsor_id" name="sponsor_id">

                <div class="modal-body">
                    <!-- نوع الخلفية -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">نوع الخلفية</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="background_type" id="bg_single" value="single" checked>
                                <label class="form-check-label" for="bg_single">
                                    <i class="bi bi-file-earmark"></i> صورة واحدة كاملة (A4)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="background_type" id="bg_triple" value="triple">
                                <label class="form-check-label" for="bg_triple">
                                    <i class="bi bi-layout-three-columns"></i> ثلاث صور (رأس - محتوى - تذييل)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- صورة واحدة -->
                    <div id="single_image_section" class="mb-4">
                        <label for="single_image" class="form-label fw-bold">
                            <i class="bi bi-image"></i> صورة الخلفية الكاملة
                        </label>
                         <input type="file" class="form-control" id="single_image" name="single_image" accept="image/*,.heic,.heif">
                        <small class="text-muted">الحجم الموصى به: A4 (595 × 842 بكسل)</small>
                        <div id="single_image_preview" class="mt-2"></div>
                    </div>

                    <!-- الصور الثلاثية -->
                    <div id="triple_images_section" class="mb-4" style="display: none;">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="header_image" class="form-label fw-bold">
                                    <i class="bi bi-arrow-up-square"></i> صورة الرأس
                                </label>
                                 <input type="file" class="form-control" id="header_image" name="header_image" accept="image/*,.heic,.heif">
                                <div id="header_image_preview" class="mt-2"></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="main_image" class="form-label fw-bold">
                                    <i class="bi bi-square"></i> الصورة الرئيسية
                                </label>
                                 <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*,.heic,.heif">
                                <div id="main_image_preview" class="mt-2"></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="footer_image" class="form-label fw-bold">
                                    <i class="bi bi-arrow-down-square"></i> صورة التذييل
                                </label>
                                 <input type="file" class="form-control" id="footer_image" name="footer_image" accept="image/*,.heic,.heif">
                                <div id="footer_image_preview" class="mt-2"></div>
                            </div>
                        </div>
                        <small class="text-muted d-block">يمكنك تحميل 1 أو 2 أو 3 صور حسب الحاجة</small>
                    </div>

                    <!-- ألوان التصميم -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-palette"></i> ألوان التصميم (اختياري)
                        </label>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label for="primary_color" class="form-label">اللون الأساسي</label>
                                <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" value="#1a1a1a">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="secondary_color" class="form-label">اللون الثانوي</label>
                                <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color" value="#4a4a4a">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="accent_color" class="form-label">لون التمييز</label>
                                <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color" value="#007bff">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning" id="saveDesignBtn">
                        <i class="bi bi-save"></i> حفظ التصميم
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script type="text/javascript">
        // Format country option with flag
        function formatCountryOption(country) {
            if (!country.id) {
                return country.text;
            }

            var $country = $(
                '<span><img src="' + $(country.element).data('kt-flag') + '" class="rounded me-2" style="width: 20px;" /> ' + country.text + '</span>'
            );

            return $country;
        }

        $(function() {
            console.log('Document ready - Starting search initialization...');

            // Multiple approaches to ensure search works
            let searchInitialized = false;

            // Approach 1: Listen to DataTable init event
            $(document).on('init.dt', function(e, settings) {
                console.log('DataTable init event fired for table:', settings.sTableId);
                if (settings.sTableId === 'sponsors-table' && !searchInitialized) {
                    console.log('Initializing search via init.dt event');
                    initSearch();
                }
            });

            // Approach 2: Direct initialization after delay
            setTimeout(function() {
                console.log('Timeout check - searchInitialized:', searchInitialized);
                console.log('DataTable exists:', $.fn.DataTable.isDataTable('#sponsors-table'));

                if (!searchInitialized && $.fn.DataTable.isDataTable('#sponsors-table')) {
                    console.log('Initializing search via timeout');
                    initSearch();
                }
            }, 1000);

            // Approach 3: Immediate check for table
            if ($('#sponsors-table').length) {
                console.log('Table element found in DOM');
                $(document).on('draw.dt', '#sponsors-table', function() {
                    if (!searchInitialized) {
                        console.log('Initializing search via draw.dt event');
                        initSearch();
                    }
                });
            }

            // Search initialization function
            function initSearch() {
                try {
                    const table = $('#sponsors-table').DataTable();
                    console.log('DataTable object:', table);

                    const searchInput = $('#kt_sponsor_search');
                    console.log('Search input found:', searchInput.length > 0);

                    searchInput.off('keyup input').on('keyup input', function() {
                        const searchValue = this.value;
                        console.log('Searching for:', searchValue);
                        table.search(searchValue).draw();
                    });

                    searchInitialized = true;
                    console.log('✓ Search initialized successfully!');
                } catch (error) {
                    console.error('Error initializing search:', error);
                }
            }

            // Reset form when modal is hidden (with flag to prevent multiple executions)
            let isCleaningUp = false;
            $('#sponsorModal').on('hidden.bs.modal', function () {
                if (isCleaningUp) {
                    console.log('Cleanup already in progress, skipping...');
                    return;
                }
                isCleaningUp = true;
                console.log('Modal cleanup started');

                // تنظيف شامل
                setTimeout(function() {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').removeAttr('style');
                    $('body').css({'overflow': '', 'padding-right': ''});

                    // إعادة تعيين النموذج
                    $('#sponsorForm')[0].reset();
                    $('#sponsor_id').val('');
                    $('#form_method').val('POST');
                    $('#modalTitle').text('إضافة جمعية جديدة');

                    console.log('Modal cleanup completed');
                    isCleaningUp = false;
                }, 150);
            });

            // View Sponsor Details
            $(document).on('click', '.view-sponsor', function() {
                const sponsorId = $(this).data('id');

                $.ajax({
                    url: `/admin/sponsors/${sponsorId}/edit`,
                    type: 'GET',
                    success: function(response) {
                        // ملء البيانات في مودال العرض
                        $('#view_file_id').text(response.file_id || '-');
                        $('#view_sponsor_name').text(response.sponsor_name || '-');
                        $('#view_sponsor_short_name').text(response.sponsor_short_name || '-');
                        $('#view_sponsor_phone_number').text(response.sponsor_phone_number || '-');
                        $('#view_sponsor_email').text(response.sponsor_email || '-');
                        $('#view_sponsor_address').text(response.sponsor_address || '-');

                        // عرض اسم الدولة بدلاً من الكود
                        if (response.country_code) {
                            const countryOption = $(`#edit_country_code option[value="${response.country_code}"]`).text();
                            $('#view_country_name').text(countryOption || response.country_code);
                        } else {
                            $('#view_country_name').text('-');
                        }

                        // المعلومات البنكية
                        if (response.sponsor_bank_name_id && response.sponsor_bank_name_id !== '0') {
                            const bankOption = $(`#edit_sponsor_bank_name_id option[value="${response.sponsor_bank_name_id}"]`).text();
                            $('#view_bank_name').text(bankOption || '-');
                        } else {
                            $('#view_bank_name').text('-');
                        }

                        $('#view_account_number').text(response.sponsor_account_bank_number || '-');
                        $('#view_swift_code').text(response.sponsor_bank_swift_code || '-');
                        $('#view_bank_phone').text(response.sponsor_bank_related_phone_number || '-');

                        // عرض اسم العملة بدلاً من ID
                        if (response.sponsor_bank_account_currency) {
                            const currencyOption = $(`#edit_sponsor_bank_account_currency option[value="${response.sponsor_bank_account_currency}"]`).text();
                            $('#view_currency').text(currencyOption || response.sponsor_bank_account_currency);
                        } else {
                            $('#view_currency').text('-');
                        }

                        // حفظ ID للاستخدام في زر التعديل من مودال العرض
                        $('#view_sponsor_id').val(sponsorId);
                        $('#editFromViewBtn').data('sponsor-id', sponsorId);

                        // عرض المودال
                        $('#viewSponsorModal').modal('show');
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

            // فتح مودال التعديل من مودال العرض
            $('#editFromViewBtn').on('click', function() {
                const sponsorId = $(this).data('sponsor-id');
                $('#viewSponsorModal').modal('hide');

                // انتظار إغلاق المودال الأول قبل فتح الثاني
                setTimeout(function() {
                    $('.view-sponsor[data-id="' + sponsorId + '"]').closest('tr').find('.edit-sponsor').trigger('click');
                }, 500);
            });

            // Edit Sponsor - فتح مودال التعديل المنفصل
            $(document).on('click', '.edit-sponsor', function() {
                const sponsorId = $(this).data('id');

                $.ajax({
                    url: `/admin/sponsors/${sponsorId}/edit`,
                    type: 'GET',
                    success: function(response) {
                        console.log('Edit data loaded:', response);

                        // ملء البيانات في مودال التعديل
                        $('#edit_sponsor_id').val(response.id);
                        $('#edit_file_id').val(response.file_id || 0);
                        $('#edit_sponsor_name').val(response.sponsor_name).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_short_name').val(response.sponsor_short_name).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_phone_number').val(response.sponsor_phone_number).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_email').val(response.sponsor_email).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_address').val(response.sponsor_address).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_bank_name_id').val(response.sponsor_bank_name_id).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_account_bank_number').val(response.sponsor_account_bank_number).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_bank_swift_code').val(response.sponsor_bank_swift_code).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_bank_related_phone_number').val(response.sponsor_bank_related_phone_number).prop('disabled', false).prop('readonly', false);
                        $('#edit_sponsor_bank_account_currency').val(response.sponsor_bank_account_currency).prop('disabled', false).prop('readonly', false);
                        $('#edit_country_code').val(response.country_code).prop('disabled', false).prop('readonly', false);

                        console.log('Form fields populated');

                        // Initialize Select2 for country dropdown in edit modal
                        if ($('#edit_country_code').data('select2')) {
                            $('#edit_country_code').select2('destroy');
                        }

                        $('#edit_country_code').select2({
                            dropdownParent: $('#editSponsorModal'),
                            placeholder: 'اختر الدولة',
                            allowClear: true,
                            language: {
                                noResults: function() {
                                    return "لا توجد نتائج";
                                },
                                searching: function() {
                                    return "جاري البحث...";
                                }
                            },
                            templateResult: formatCountryOption,
                            templateSelection: formatCountryOption
                        });

                        // Set the value after initializing select2
                        if (response.country_code) {
                            $('#edit_country_code').val(response.country_code).trigger('change');
                        }

                        $('#editSponsorModal').modal('show');
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

            // Submit Edit Form
            $('#editSponsorForm').on('submit', function(e) {
                e.preventDefault();

                const sponsorId = $('#edit_sponsor_id').val();
                const url = `/admin/sponsors/${sponsorId}`;
                const formData = $(this).serialize();

                const submitBtn = $('#editSubmitBtn');
                submitBtn.attr('data-kt-indicator', 'on');
                submitBtn.prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        // إغلاق المودال
                        $('#editSponsorModal').modal('hide');

                        // عرض رسالة النجاح
                        Swal.fire({
                            icon: 'success',
                            title: 'نجح!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: true,
                            confirmButtonText: 'حسناً'
                        });

                        // إعادة تحميل الجدول
                        if ($.fn.DataTable.isDataTable('#sponsors-table')) {
                            $('#sponsors-table').DataTable().ajax.reload();
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

            // Reset edit form when modal is closed
            $('#editSponsorModal').on('hidden.bs.modal', function () {
                $('#editSponsorForm')[0].reset();
                $('#edit_sponsor_id').val('');
                $('#edit_file_id').val('');

                // Destroy Select2 instance
                if ($('#edit_country_code').data('select2')) {
                    $('#edit_country_code').select2('destroy');
                }
            });

            // Edit Sponsor (OLD - Keep for compatibility if needed)
            /*
            $(document).on('click', '.edit-sponsor', function() {
                const sponsorId = $(this).data('id');

                $.ajax({
                    url: `/admin/sponsors/${sponsorId}/edit`,
                    type: 'GET',
                    success: function(response) {
                        $('#modalTitle').text('تعديل بيانات الجمعية');
                        $('#sponsor_id').val(response.id);
                        $('#form_method').val('PUT');
                        $('#file_id').val(response.file_id || 0);
                        $('#sponsor_name').val(response.sponsor_name);
                        $('#sponsor_short_name').val(response.sponsor_short_name);
                        $('#sponsor_phone_number').val(response.sponsor_phone_number);
                        $('#sponsor_email').val(response.sponsor_email);
                        $('#sponsor_address').val(response.sponsor_address);
                        $('#sponsor_bank_name_id').val(response.sponsor_bank_name_id);
                        $('#sponsor_account_bank_number').val(response.sponsor_account_bank_number);
                        $('#sponsor_bank_swift_code').val(response.sponsor_bank_swift_code);
                        $('#sponsor_bank_related_phone_number').val(response.sponsor_bank_related_phone_number);
                        $('#sponsor_bank_account_currency').val(response.sponsor_bank_account_currency);
                        $('#country_code').val(response.country_code);
                        $('#sponsorModal').modal('show');
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
            */

            // Submit Form
            $('#sponsorForm').on('submit', function(e) {
                e.preventDefault();

                console.log('=== Form Submit Started ===');

                const sponsorId = $('#sponsor_id').val();
                const method = $('#form_method').val();
                let url = '/admin/sponsors';

                if (method === 'PUT') {
                    url = `/admin/sponsors/${sponsorId}`;
                }

                const formData = $(this).serialize();
                console.log('Form Data:', formData);
                console.log('URL:', url);
                console.log('Method:', method);

                const submitBtn = $('#submitBtn');
                submitBtn.attr('data-kt-indicator', 'on');
                submitBtn.prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        console.log('Success Response:', response);

                        // توليد file_id جديد قبل إغلاق الـ modal
                        $.ajax({
                            url: '/admin/sponsors/generate-file-id',
                            method: 'GET',
                            async: false,
                            success: function(idResponse) {
                                if (idResponse.file_id) {
                                    $('#file_id').val(idResponse.file_id);
                                    console.log('New file_id generated:', idResponse.file_id);
                                }
                            },
                            error: function() {
                                console.error('Failed to generate new file_id');
                            }
                        });

                        // إغلاق الـ modal
                        $('#sponsorModal').modal('hide');

                        // عرض رسالة النجاح
                        Swal.fire({
                            icon: 'success',
                            title: 'نجح!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: true,
                            confirmButtonText: 'حسناً'
                        });

                        // إعادة تحميل الجدول
                        if ($.fn.DataTable.isDataTable('#sponsors-table')) {
                            $('#sponsors-table').DataTable().ajax.reload();
                        }
                    },
                    error: function(xhr) {
                        console.error('Error Response:', xhr);
                        console.error('Status:', xhr.status);
                        console.error('Response Text:', xhr.responseText);

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

            // Delete Sponsor
            $(document).on('click', '.delete-sponsor', function() {
                const sponsorId = $(this).data('id');

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
                            url: `/admin/sponsors/${sponsorId}`,
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                Swal.fire('تم الحذف!', response.message, 'success');
                                $('#sponsors-table').DataTable().ajax.reload();
                            },
                            error: function() {
                                Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف', 'error');
                            }
                        });
                    }
                });
            });

            // ===================== Employee Management =====================
            let currentSponsorId = null;

            // Load employees when view modal opens
            $('#viewSponsorModal').on('shown.bs.modal', function() {
                const sponsorId = $('#view_sponsor_id').val();
                console.log('View modal opened, sponsor ID:', sponsorId);
                if (sponsorId) {
                    currentSponsorId = sponsorId;
                    loadEmployees(sponsorId);
                } else {
                    console.error('No sponsor ID found');
                    $('#employeesList').html('<div class="alert alert-warning">لم يتم العثور على معرف الجمعية</div>');
                }
            });

            // Function to load employees
            function loadEmployees(sponsorId) {
                console.log('Loading employees for sponsor:', sponsorId);

                $('#employeesList').html(`
                    <div class="d-flex justify-content-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                `);

                $.ajax({
                    url: `/admin/sponsors/${sponsorId}/employees`,
                    type: 'GET',
                    success: function(response) {
                        console.log('Employees loaded:', response);
                        renderEmployees(response.employees);
                    },
                    error: function(xhr) {
                        console.error('Error loading employees:', xhr);
                        $('#employeesList').html('<div class="alert alert-danger">حدث خطأ أثناء تحميل المندوبين</div>');
                    }
                });
            }

            // Function to render employees list
            function renderEmployees(employees) {
                if (!employees || employees.length === 0) {
                    $('#employeesList').html(`
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="ki-duotone ki-information fs-2hx text-info me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <span>لا يوجد مندوبين حالياً</span>
                            </div>
                        </div>
                    `);
                    return;
                }

                let html = '<div class="table-responsive">';
                html += '<table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">';
                html += '<thead><tr class="fw-bold text-muted"><th class="min-w-120px">اسم المندوب</th><th class="min-w-100px text-end">الإجراءات</th></tr></thead>';
                html += '<tbody>';

                employees.forEach(function(employee) {
                    html += `
                        <tr>
                            <td><div class="text-gray-800 fw-bold">${employee.employee_name}</div></td>
                            <td class="text-end">
                                <button class="btn btn-icon btn-light-danger btn-sm delete-employee" data-id="${employee.id}" title="حذف">
                                    <i class="ki-duotone ki-trash fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += '</tbody></table></div>';
                $('#employeesList').html(html);
            }

            // Open add employee modal
            $(document).on('click', '#addEmployeeBtn', function() {
                console.log('Add employee button clicked, sponsor ID:', currentSponsorId);

                if (currentSponsorId) {
                    $('#employee_sponsor_id').val(currentSponsorId);
                    $('#employee_name').val('');
                    $('#addEmployeeModal').modal('show');
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'لم يتم تحديد الجمعية',
                        confirmButtonText: 'حسناً'
                    });
                }
            });

            // Submit add employee form
            $('#addEmployeeForm').on('submit', function(e) {
                e.preventDefault();

                const submitBtn = $('#saveEmployeeBtn');
                const sponsorId = $('#employee_sponsor_id').val();

                submitBtn.attr('data-kt-indicator', 'on');
                submitBtn.prop('disabled', true);

                $.ajax({
                    url: `/admin/sponsors/${sponsorId}/employees`,
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        $('#addEmployeeModal').modal('hide');

                        Swal.fire({
                            icon: 'success',
                            title: 'نجح!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: true,
                            confirmButtonText: 'حسناً'
                        });

                        // Reload employees list
                        loadEmployees(currentSponsorId);
                    },
                    error: function(xhr) {
                        let errorMessage = 'حدث خطأ أثناء إضافة المندوب';
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

            // Delete employee
            $(document).on('click', '.delete-employee', function() {
                const employeeId = $(this).data('id');

                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: "سيتم حذف المندوب نهائياً",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'نعم، احذف!',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: `/admin/sponsors/employees/${employeeId}`,
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الحذف!',
                                    text: response.message,
                                    timer: 2000
                                });

                                // Reload employees list
                                loadEmployees(currentSponsorId);
                            },
                            error: function(xhr) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'خطأ!',
                                    text: 'حدث خطأ أثناء حذف المندوب',
                                    confirmButtonText: 'حسناً'
                                });
                            }
                        });
                    }
                });
            });
        });

        // معالج زر تصميم التقرير
        let currentDesignSponsorId = null;

        $('#designReportBtn').on('click', function() {
            currentDesignSponsorId = $('#view_sponsor_id').val();
            $('#design_sponsor_id').val(currentDesignSponsorId);

            // إخفاء modal العرض وإظهار modal التصميم
            $('#viewSponsorModal').modal('hide');

            // تحميل التصميم الحالي إن وجد
            loadCurrentDesign(currentDesignSponsorId);

            setTimeout(() => {
                $('#reportDesignModal').modal('show');
            }, 300);
        });

        // تبديل بين أقسام الصور
        $('input[name="background_type"]').on('change', function() {
            if ($(this).val() === 'single') {
                $('#single_image_section').show();
                $('#triple_images_section').hide();
            } else {
                $('#single_image_section').hide();
                $('#triple_images_section').show();
            }
        });

        // معاينة الصور
        function setupImagePreview(inputId, previewId) {
            $(`#${inputId}`).on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $(`#${previewId}`).html(`
                            <img src="${e.target.result}" class="img-thumbnail" style="max-height: 150px;">
                            <button type="button" class="btn btn-sm btn-danger mt-1 remove-image" data-input="${inputId}">
                                <i class="bi bi-trash"></i> إزالة
                            </button>
                        `);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        setupImagePreview('single_image', 'single_image_preview');
        setupImagePreview('header_image', 'header_image_preview');
        setupImagePreview('main_image', 'main_image_preview');
        setupImagePreview('footer_image', 'footer_image_preview');

        // إزالة الصورة
        $(document).on('click', '.remove-image', function() {
            const inputId = $(this).data('input');
            $(`#${inputId}`).val('');
            $(this).parent().html('');
        });

        // حفظ التصميم
        $('#reportDesignForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = $('#saveDesignBtn');

            submitBtn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> جاري الحفظ...');

            $.ajax({
                url: '{{ route("admin.sponsors.save-report-design") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحفظ!',
                        text: response.message,
                        timer: 2000
                    });

                    $('#reportDesignModal').modal('hide');
                    $('#reportDesignForm')[0].reset();

                    // مسح المعاينات
                    $('#single_image_preview, #header_image_preview, #main_image_preview, #footer_image_preview').html('');
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: xhr.responseJSON?.message || 'حدث خطأ أثناء حفظ التصميم',
                        confirmButtonText: 'حسناً'
                    });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('<i class="bi bi-save"></i> حفظ التصميم');
                }
            });
        });

        // تحميل التصميم الحالي
        function loadCurrentDesign(sponsorId) {
            if (!sponsorId) {
                console.log('No sponsor ID provided');
                return;
            }

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/report-design`,
                method: 'GET',
                success: function(response) {
                    if (response.design) {
                        const design = response.design;

                        // تحديد نوع الخلفية
                        $(`input[name="background_type"][value="${design.background_type}"]`).prop('checked', true).trigger('change');

                        // الألوان
                        if (design.theme_colors) {
                            $('#primary_color').val(design.theme_colors.primary || '#1a1a1a');
                            $('#secondary_color').val(design.theme_colors.secondary || '#4a4a4a');
                            $('#accent_color').val(design.theme_colors.accent || '#007bff');
                        }

                        // عرض الصور الموجودة مع معالجة الأخطاء
                        if (design.single_image) {
                            $('#single_image_preview').html(`
                                <img src="/storage/${design.single_image}"
                                     class="img-thumbnail"
                                     style="max-height: 150px;"
                                     onerror="this.style.display='none'; this.nextElementSibling.innerHTML='<i class=&quot;bi bi-exclamation-triangle text-warning&quot;></i> الصورة غير موجودة'">
                                <p class="text-muted mt-1">الصورة الحالية</p>
                            `);
                        }

                        if (design.header_image) {
                            $('#header_image_preview').html(`
                                <img src="/storage/${design.header_image}"
                                     class="img-thumbnail"
                                     style="max-height: 150px;"
                                     onerror="this.style.display='none'; this.nextElementSibling.innerHTML='<i class=&quot;bi bi-exclamation-triangle text-warning&quot;></i> الصورة غير موجودة'">
                                <p class="text-muted mt-1">الصورة الحالية</p>
                            `);
                        }

                        if (design.main_image) {
                            $('#main_image_preview').html(`
                                <img src="/storage/${design.main_image}"
                                     class="img-thumbnail"
                                     style="max-height: 150px;"
                                     onerror="this.style.display='none'; this.nextElementSibling.innerHTML='<i class=&quot;bi bi-exclamation-triangle text-warning&quot;></i> الصورة غير موجودة'">
                                <p class="text-muted mt-1">الصورة الحالية</p>
                            `);
                        }

                        if (design.footer_image) {
                            $('#footer_image_preview').html(`
                                <img src="/storage/${design.footer_image}"
                                     class="img-thumbnail"
                                     style="max-height: 150px;"
                                     onerror="this.style.display='none'; this.nextElementSibling.innerHTML='<i class=&quot;bi bi-exclamation-triangle text-warning&quot;></i> الصورة غير موجودة'">
                                <p class="text-muted mt-1">الصورة الحالية</p>
                            `);
                        }
                    }
                },
                error: function(xhr) {
                    console.log('No existing design found');
                }
            });
        }
    </script>
@endpush
