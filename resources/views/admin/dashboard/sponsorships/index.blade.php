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
                                <label class="fs-6 fw-semibold mb-2">إسم المؤسسة الكافلة</label>
                                <select class="form-select form-select-solid" name="sponsor_id" id="sponsor_id">
                                    <option value="">اختر المؤسسة الكافلة</option>
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

                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-semibold mb-2">إسم المعيل</label>
                            <input type="text" class="form-control form-control-solid"
                                   placeholder="إسم المعيل" name="guardian_name"
                                   id="guardian_name" />
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

            // Reset form when modal is hidden
            $('#sponsorshipModal').on('hidden.bs.modal', function () {
                $('#sponsorshipForm')[0].reset();
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
                        $('#sponsor_id').val(response.sponsor_id);
                        $('#sponsoring_organization').val(response.sponsoring_organization);
                        $('#internal_file_number').val(response.internal_file_number);
                        $('#external_file_number').val(response.external_file_number);
                        $('#identity_number').val(response.identity_number);
                        $('#orphan_name').val(response.orphan_name);
                        $('#guardian_name').val(response.guardian_name);
                        $('#sponsorship_duration_months').val(response.sponsorship_duration_months);
                        $('#sponsorship_start_date').val(response.sponsorship_start_date);
                        $('#sponsorship_end_date').val(response.sponsorship_end_date);
                        $('#sponsorship_type_id').val(response.sponsorship_type_id);
                        $('#sponsorship_status_id').val(response.sponsorship_status_id);
                        $('#notes').val(response.notes);
                        
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
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-gray-600 fs-7 d-block mb-1">الكافل</span>
                                        <span class="fw-bold text-gray-800 fs-6">${data.sponsor?.sponsor_name || data.sponsoring_organization || '-'}</span>
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
