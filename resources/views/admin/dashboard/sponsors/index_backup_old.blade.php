@extends('admin.dashboard.toolbars.index')

@section('content')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
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
</div>
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
                    <div class="scroll-y me-n7 pe-7" data-kt-scroll="true"
                         data-kt-scroll-activate="{default: false, lg: true}"
                         data-kt-scroll-max-height="auto"
                         data-kt-scroll-offset="300px">

                        <!--begin::معلومات أساسية-->
                        <div class="mb-7">
                            <h3 class="fw-bold text-gray-900 mb-5">المعلومات الأساسية</h3>
                            <div class="separator mb-5"></div>
                        </div>

                        <!-- عرض رقم الملف (مخفي - للإرسال فقط) -->
                        <input type="hidden" id="file_id" name="file_id" value="{{ $file_id ?? 0 }}">
                        
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
                                    <option value="USD">دولار أمريكي (USD)</option>
                                    <option value="ILS">شيكل (ILS)</option>
                                    <option value="JOD">دينار أردني (JOD)</option>
                                    <option value="EUR">يورو (EUR)</option>
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
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script type="text/javascript">
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

            // Reset form when modal is hidden
            $('#sponsorModal').on('hidden.bs.modal', function () {
                $('#sponsorForm')[0].reset();
                $('#sponsor_id').val('');
                $('#form_method').val('POST');
                $('#modalTitle').text('إضافة جمعية جديدة');

                // إعادة توليد file_id جديد عند فتح modal للإضافة
                $.ajax({
                    url: '{{ route("admin.sponsors.index") }}',
                    type: 'GET',
                    success: function(response) {
                        // استخراج file_id من الـ response
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(response, 'text/html');
                        const fileIdInput = doc.querySelector('#file_id');
                        if (fileIdInput) {
                            const newFileId = fileIdInput.value;
                            $('#file_id').val(newFileId);
                            $('#file_id_display').text(String(newFileId).padStart(6, '0'));
                        }
                    }
                });
            });

            // Edit Sponsor
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
                        Swal.fire({
                            icon: 'success',
                            title: 'نجح!',
                            text: response.message,
                            confirmButtonText: 'حسناً'
                        });
                        $('#sponsorModal').modal('hide');
                        $('#sponsors-table').DataTable().ajax.reload();
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
        });
    </script>
@endpush
