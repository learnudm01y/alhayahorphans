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
                                <label class="fs-6 fw-semibold mb-2">رقم الملف الداخلي</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="رقم الملف الداخلي" name="internal_file_number"
                                       id="internal_file_number" />
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
                                <label class="fs-6 fw-semibold mb-2">رقم الهوية</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="رقم الهوية" name="identity_number"
                                       id="identity_number" />
                            </div>

                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">الإسم</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="إسم المكفول" name="orphan_name"
                                       id="orphan_name" />
                            </div>
                        </div>

                        <div class="row g-9 mb-7">
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">إسم المعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="إسم المعيل" name="guardian_name"
                                       id="guardian_name" />
                            </div>
                            <div class="col-md-6 fv-row">
                                <label class="fs-6 fw-semibold mb-2">رقم هوية المعيل</label>
                                <input type="text" class="form-control form-control-solid"
                                       placeholder="رقم هوية المعيل" name="guardian_identity_number"
                                       id="guardian_identity_number" />
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
            // تطبيق الفلاتر على الجدول
            // ============================================
            $('#apply_filters').on('click', function() {
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
                    table.ajax.url(baseUrl + queryString).load(function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم تطبيق الفلاتر',
                            text: 'تم تحديث النتائج بنجاح',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    });
                }
            });

            // ============================================
            // إعادة تعيين الفلاتر
            // ============================================
            $('#reset_filters').on('click', function() {
                $('#filter_sponsor, #filter_sponsorship_type, #filter_sponsorship_status').val('').trigger('change');

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

            function createSponsoredBankAccountForm(index, bankData = {}) {
                return `
                <div class="sponsored-bank-account-form border rounded p-4 mb-4 position-relative"
                     data-index="${index}"
                     style="border: 2px dashed #009ef7 !important; background-color: #d8d8d8;">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3 remove-sponsored-bank-btn"
                            title="حذف الحساب" style="z-index: 10;"></button>
                    <h6 class="mb-4 text-primary fw-bold">
                        <i class="fas fa-university me-2"></i>حساب بنكي رقم ${index + 1}
                    </h6>
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
            });

            // ============================================
            // وظيفة لجلب وعرض البيانات البنكية الموجودة
            // ============================================
            function loadExistingBankAccounts(identityNumber) {
                if (!identityNumber) {
                    $('#existingBankAccountsSection').addClass('d-none');
                    return;
                }

                $.ajax({
                    url: '/admin/records-management/get-bank-accounts',
                    type: 'GET',
                    data: { identity_number: identityNumber },
                    success: function(response) {
                        if (response.success && response.accounts && response.accounts.length > 0) {
                            let html = '';
                            response.accounts.forEach((account, index) => {
                                html += `
                                <div class="border rounded p-4 mb-3" style="background-color: #f8f9fa;">
                                    <h6 class="mb-3 text-dark fw-bold">
                                        <i class="fas fa-university text-primary me-2"></i>حساب بنكي ${index + 1}
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">اسم البنك</span>
                                            <span class="fw-bold text-gray-800 fs-6">${account.bank?.description || '-'}</span>
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
                            $('#existingBankAccountsList').html(html);
                            $('#existingBankAccountsSection').removeClass('d-none');
                        } else {
                            $('#existingBankAccountsSection').addClass('d-none');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading bank accounts:', xhr);
                        $('#existingBankAccountsSection').addClass('d-none');
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

                    searchInput.off('keyup input').on('keyup input', function() {
                        table.search(this.value).draw();
                    });

                    searchInitialized = true;
                    console.log('✓ Search initialized successfully!');
                } catch (error) {
                    console.error('Error initializing search:', error);
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

                        Swal.fire({
                            icon: 'success',
                            title: 'نجح!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: true,
                            confirmButtonText: 'حسناً'
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
                        $('#orphan_name').val(response.orphan_name);
                        $('#guardian_name').val(response.guardian_name);
                        $('#guardian_identity_number').val(response.guardian_identity_number);
                        $('#sponsorship_duration_months').val(response.sponsorship_duration_months);
                        $('#sponsorship_start_date').val(response.sponsorship_start_date);
                        $('#sponsorship_end_date').val(response.sponsorship_end_date);
                        $('#sponsorship_type_id').val(response.sponsorship_type_id);
                        $('#sponsorship_status_id').val(response.sponsorship_status_id);
                        $('#notes').val(response.notes);

                        // 🆕 تحميل البيانات البنكية الموجودة للشخص
                        if (response.identity_number) {
                            loadExistingBankAccounts(response.identity_number);
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
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsorship_status?.description || '-'}</span>
                                    </div>
                                    <div class="col-md-12">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">ملاحظات</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.notes || '-'}</span>
                                    </div>
                                </div>
                            </div>
                        `;

                        $('#viewSponsorshipContent').html(html);
                    },
                    error: function() {
                        $('#viewSponsorshipContent').html('<div class="alert alert-danger">حدث خطأ في تحميل البيانات</div>');
                    }
                });
            });
        });
    </script>
@endpush
