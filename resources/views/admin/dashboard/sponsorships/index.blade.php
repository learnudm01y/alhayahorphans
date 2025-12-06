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
                    <div class="d-flex justify-content-end" data-kt-sponsorship-table-toolbar="base">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sponsorshipModal">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            إضافة كفالة جديدة
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

                        <!-- عرض البيانات البنكية الموجودة -->
                        <div id="existingIndexBankAccountsSection" class="d-none mb-5">
                            <div class="alert alert-success d-flex align-items-center py-3">
                                <i class="fas fa-check-circle fs-2 me-3"></i>
                                <div>
                                    <strong>البيانات البنكية الموجودة</strong>
                                    <p class="mb-0 small">هذه هي الحسابات البنكية المسجلة مسبقاً لهذا الشخص</p>
                                </div>
                            </div>
                            <div id="existingIndexBankAccountsList" class="row g-3">
                                <!-- سيتم عرض البيانات البنكية الموجودة هنا -->
                            </div>
                        </div>

                        <div class="alert alert-info d-flex align-items-center py-3 mb-5">
                            <i class="fas fa-info-circle fs-2 me-3"></i>
                            <span>يمكنك إضافة حتى 10 حسابات بنكية للمكفول. جميع الحقول اختيارية.</span>
                            <button type="button" class="btn btn-sm btn-primary ms-auto" id="addSponsorshipBankAccount">
                                <i class="fas fa-plus me-1"></i>إضافة حساب بنكي
                            </button>
                        </div>

                        <div id="sponsorshipBankAccountsContainer" class="d-none">
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
            // Search functionality
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

            // Initialize Select2 for multiple sponsors
            $('#sponsor_ids').select2({
                placeholder: 'اختر المؤسسات الكافلة',
                allowClear: true,
                dir: 'rtl',
                dropdownParent: $('#sponsorshipModal')
            });

            // معالج تحميل البيانات البنكية عند إدخال رقم الهوية
            $('#guardian_identity_number, #identity_number').on('blur', function() {
                const guardianIdentity = $('#guardian_identity_number').val();
                const identityNumber = $('#identity_number').val();

                // استخدام رقم هوية المعيل أولاً، ثم رقم الهوية
                const identityToUse = guardianIdentity || identityNumber;

                if (identityToUse && identityToUse.length >= 9) {
                    loadExistingIndexBankAccounts(identityToUse);
                }
            });

            // Reset form when modal is hidden
            $('#sponsorshipModal').on('hidden.bs.modal', function () {
                $('#sponsorshipForm')[0].reset();
                $('#sponsor_ids').val(null).trigger('change');
                $('#sponsorship_id').val('');
                $('#form_method').val('POST');
                $('#modalTitle').text('إضافة كفالة جديدة');
                // مسح الحسابات البنكية
                $('#sponsorshipBankAccountsContainer').addClass('d-none').html('');
                $('#existingIndexBankAccountsSection').addClass('d-none');
                $('#existingIndexBankAccountsList').html('');
                sponsorshipBankAccountCount = 0;
                $('#addSponsorshipBankAccount').prop('disabled', false);
            });

            // ============================================
            // إدارة الحسابات البنكية في المودال
            // ============================================
            let sponsorshipBankAccountCount = 0;
            const maxSponsorshipBankAccounts = 10;

            // ============================================
            // تحميل البيانات البنكية الموجودة
            // ============================================
            function loadExistingIndexBankAccounts(guardianIdentity) {
                if (!guardianIdentity) {
                    $('#existingIndexBankAccountsSection').addClass('d-none');
                    return;
                }

                $.ajax({
                    url: '/admin/records-management/get-bank-accounts',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        guardian_identity: guardianIdentity
                    },
                    success: function(response) {
                        if (response.success && response.accounts && response.accounts.length > 0) {
                            let html = '';
                            response.accounts.forEach(function(account, index) {
                                const bankName = account.bank ? account.bank.description : 'غير محدد';
                                const guardianName = account.re_guardian_name || 'غير محدد';
                                const ownerIdentity = account.person_owner_identity_number || 'غير محدد';
                                const phoneNumber = account.re_phone_number || 'غير محدد';
                                const ibanUsd = account.iban_usd || 'غير محدد';
                                const ibanShekel = account.iban_shekel || 'غير محدد';

                                html += `
                                    <div class="col-md-6 mb-3">
                                        <div class="card border border-success">
                                            <div class="card-header bg-light-success py-2">
                                                <h6 class="mb-0 text-success">
                                                    <i class="fas fa-university me-2"></i>حساب بنكي ${index + 1}
                                                </h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">البنك:</small>
                                                        <strong>${bankName}</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">صاحب الحساب:</small>
                                                        <strong>${guardianName}</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">رقم الهوية:</small>
                                                        <strong>${ownerIdentity}</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">الهاتف:</small>
                                                        <strong>${phoneNumber}</strong>
                                                    </div>
                                                    <div class="col-12">
                                                        <small class="text-muted d-block">IBAN بالدولار:</small>
                                                        <strong class="small">${ibanUsd}</strong>
                                                    </div>
                                                    <div class="col-12">
                                                        <small class="text-muted d-block">IBAN بالشيكل:</small>
                                                        <strong class="small">${ibanShekel}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });

                            $('#existingIndexBankAccountsList').html(html);
                            $('#existingIndexBankAccountsSection').removeClass('d-none');
                        } else {
                            $('#existingIndexBankAccountsSection').addClass('d-none');
                            $('#existingIndexBankAccountsList').html('');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading bank accounts:', xhr);
                        $('#existingIndexBankAccountsSection').addClass('d-none');
                    }
                });
            }

            function createSponsorshipBankAccountForm(index, bankData = {}) {
                return `
                <div class="sponsorship-bank-account-form border rounded p-4 mb-4 position-relative"
                     data-index="${index}"
                     style="border: 2px dashed #009ef7 !important; background-color: #d8d8d8;">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3 remove-sponsorship-bank-btn"
                            title="حذف الحساب" style="z-index: 10;"></button>
                    <h6 class="mb-4 text-primary fw-bold">
                        <i class="fas fa-university me-2"></i>حساب بنكي رقم ${index + 1}
                    </h6>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">اسم البنك</label>
                            <select name="bank_accounts[${index}][bank_name]" class="form-select form-select-solid">
                                <option value="">اختر البنك</option>
                                @foreach($bankNames ?? [] as $bank)
                                    <option value="{{ $bank->id }}" ${bankData.bank_name == '{{ $bank->id }}' ? 'selected' : ''}>{{ $bank->description }}</option>
                                @endforeach
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

            function updateRemoveSponsorshipBankButtons() {
                $('.remove-sponsorship-bank-btn').off('click').on('click', function(e) {
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
                            $(this).closest('.sponsorship-bank-account-form').remove();
                            sponsorshipBankAccountCount--;

                            // إعادة ترقيم
                            $('.sponsorship-bank-account-form').each(function(idx) {
                                $(this).attr('data-index', idx);
                                $(this).find('h6').html(`<i class="fas fa-university me-2"></i>حساب بنكي رقم ${idx + 1}`);
                            });

                            if (sponsorshipBankAccountCount === 0) {
                                $('#sponsorshipBankAccountsContainer').addClass('d-none');
                            }

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

            $('#addSponsorshipBankAccount').on('click', function() {
                if (sponsorshipBankAccountCount < maxSponsorshipBankAccounts) {
                    $('#sponsorshipBankAccountsContainer').removeClass('d-none');
                    $('#sponsorshipBankAccountsContainer').append(createSponsorshipBankAccountForm(sponsorshipBankAccountCount));
                    sponsorshipBankAccountCount++;
                    updateRemoveSponsorshipBankButtons();

                    if (sponsorshipBankAccountCount >= maxSponsorshipBankAccounts) {
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

                        // 🏦 تحميل البيانات البنكية الموجودة
                        const guardianIdentity = response.guardian_identity_number || response.identity_number;
                        if (guardianIdentity) {
                            loadExistingIndexBankAccounts(guardianIdentity);
                        }

                        // 🏦 تحميل الحسابات البنكية
                        $('#sponsorshipBankAccountsContainer').html('').addClass('d-none');
                        sponsorshipBankAccountCount = 0;

                        if (response.bank_accounts && response.bank_accounts.length > 0) {
                            $('#sponsorshipBankAccountsContainer').removeClass('d-none');
                            response.bank_accounts.forEach(function(account, index) {
                                $('#sponsorshipBankAccountsContainer').append(createSponsorshipBankAccountForm(index, account));
                                sponsorshipBankAccountCount++;
                            });
                            updateRemoveSponsorshipBankButtons();

                            if (sponsorshipBankAccountCount >= maxSponsorshipBankAccounts) {
                                $('#addSponsorshipBankAccount').prop('disabled', true);
                            } else {
                                $('#addSponsorshipBankAccount').prop('disabled', false);
                            }
                        } else {
                            $('#addSponsorshipBankAccount').prop('disabled', false);
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
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">الملؤسسات الكافلة</span>
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
