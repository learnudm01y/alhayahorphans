@extends('admin.dashboard.toolbars.index')
@section('content')
    <div class="card">
        <!--begin::Card header-->
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <h2>إضافة مستخدم جديد</h2>
            </div>
            <div class="card-toolbar">
                <div class="d-flex justify-content-end">
                    <a href="{{ route('users.index') }}" class="btn btn-light-primary me-3">
                        <i class="ki-duotone ki-arrow-left fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span class="ms-2">رجوع</span>
                    </a>
                </div>
            </div>
        </div>
        <!--end::Card header-->

        <!--begin::Card body-->
        <div class="card-body py-4">
            @if ($message = Session::get('success'))
                @push('scriptsCode')
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: 'تم بنجاح',
                            text: "{{ $message }}",
                            confirmButtonText: 'حسناً',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    </script>
                @endpush
            @endif

            @if (count($errors) > 0)
                @push('scriptsCode')
                    <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في الإدخال',
                            html: '<ul class="text-start">' +
                                '@foreach ($errors->all() as $error)' +
                                '<li>{{ $error }}</li>' +
                                '@endforeach' +
                                '</ul>',
                            confirmButtonText: 'حسناً',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    </script>
                @endpush
            @endif

            {!! Form::open(['route' => 'users.store', 'method' => 'POST', 'class' => 'form']) !!}
            <div class="row g-9 mb-8">
                <!--begin::Col-->
                <div class="col-md-6 fv-row">
                    <label class="fs-5 fw-semibold mb-2">الاسم</label>
                    {!! Form::text('name', null, [
                        'class' => 'form-control form-control-solid',
                        'placeholder' => 'أدخل اسم المستخدم',
                    ]) !!}
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 fv-row">
                    <label class="fs-5 fw-semibold mb-2">البريد الإلكتروني</label>
                    {!! Form::text('email', null, [
                        'class' => 'form-control form-control-solid',
                        'placeholder' => 'أدخل البريد الإلكتروني',
                    ]) !!}
                </div>
                <!--end::Col-->
            </div>

            <div class="row g-9 mb-8">
                <!--begin::Col-->
                <div class="col-md-6 fv-row">
                    <label class="fs-5 fw-semibold mb-2">كلمة المرور</label>
                    {!! Form::password('password', [
                        'class' => 'form-control form-control-solid',
                        'placeholder' => 'أدخل كلمة المرور',
                    ]) !!}
                </div>
                <!--end::Col-->

                <!--begin::Col-->
                <div class="col-md-6 fv-row">
                    <label class="fs-5 fw-semibold mb-2">تأكيد كلمة المرور</label>
                    {!! Form::password('confirm-password', [
                        'class' => 'form-control form-control-solid',
                        'placeholder' => 'تأكيد كلمة المرور',
                    ]) !!}
                </div>
                <!--end::Col-->
            </div>

            <div class="row g-9 mb-8">
                <!--begin::Col-->
                <div class="col-md-12 fv-row">
                    <label class="fs-5 fw-semibold mb-2">الرتبة</label>
                    {!! Form::select('role',
                        [
                            'admin' => 'إداري',
                            'user' => 'مستخدم عادي'
                        ],
                        null,
                        [
                            'class' => 'form-select form-select-solid',
                            'data-control' => 'select2',
                            'data-placeholder' => 'اختر الرتبة',
                            'data-hide-search' => 'true',
                        ]
                    ) !!}
                </div>
                <!--end::Col-->
            </div>

            <div class="row g-9 mb-8">
                <!--begin::Col-->
                <div class="col-md-12 fv-row">
                    <label class="fs-5 fw-semibold mb-2">الأدوار</label>
                    {!! Form::select(
                        'roles[]',
                        $roles,
                        [],
                        [
                            'class' => 'form-select form-select-solid select2-roles',
                            'data-control' => 'select2',
                            'data-placeholder' => 'اختر الأدوار المطلوبة',
                            'multiple' => 'multiple',
                            'data-allow-clear' => 'true',
                        ]
                    ) !!}
                </div>
                <!--end::Col-->
            </div>

            <!--begin::Actions-->
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 d-inline-flex align-items-center">
                    <span class="svg-icon svg-icon-2 me-0">
                        <i class="ki-duotone ki-cross-circle fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </span>
                    <span class="ms-2">إلغاء</span>
                </button>
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center">
                    <span class="svg-icon svg-icon-2 me-0">
                        <i class="ki-duotone ki-check-circle fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </span>
                    <span class="ms-2">حفظ</span>
                </button>
            </div>
            <!--end::Actions-->
            {!! Form::close() !!}
        </div>
        <!--end::Card body-->
    </div>
@endsection

@push('styles')
    <style>
        .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__rendered {
            padding: 5px;
        }

        /* تنسيقات توسيط الأيقونات */
        .svg-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }

        .btn .ki-duotone {
            line-height: 1;
            vertical-align: middle;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // تهيئة Select2 مع الترجمة العربية
        $(document).ready(function() {
            $('.form-select').select2({
                language: {
                    noResults: () => "لا توجد نتائج متاحة",
                    searching: () => "جاري البحث ...",
                    loadingMore: () => "جاري تحميل نتائج إضافية...",
                    errorLoading: () => "لا يمكن تحميل النتائج"
                }
            });
        });
    </script>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // تهيئة Select2 للرتبة
            $('select[name="role"]').select2({
                language: {
                    noResults: () => "لا توجد رتب متاحة",
                }
            });

            // تهيئة مخصصة للأدوار
            $('.select2-roles').select2({
                language: {
                    noResults: () => "لا توجد أدوار متاحة",
                    searching: () => "جاري البحث...",
                    errorLoading: () => "لا يمكن تحميل النتائج",
                    removeAllItems: () => "إزالة كل الأدوار",
                    removeItem: () => "إزالة هذا الدور",
                    search: () => "ابحث عن دور",
                    inputTooShort: () => "الرجاء إدخال حرفين على الأقل للبحث"
                },
                minimumInputLength: 1,
                maximumSelectionLength: 5,
                selectOnClose: true,
                width: '100%',
                allowClear: true,
                escapeMarkup: function(markup) {
                    return markup;
                },
                templateResult: function(data) {
                    if (data.loading) return data.text;
                    var markup = "<div class='select2-result-role'>" +
                        "<i class='ki-duotone ki-shield-tick me-2 fs-6'></i>" +
                        data.text + "</div>";
                    return markup;
                },
                templateSelection: function(data) {
                    if (!data.id) return data.text;
                    return $('<span class="select2-selection__choice__display">' +
                        '<i class="ki-duotone ki-shield-tick me-2 fs-6"></i>' +
                        data.text + '</span>');
                }
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        /* تحسينات إضافية لـ Select2 الأدوار */
        .select2-roles + .select2-container .select2-selection--multiple {
            min-height: 45px;
            border: 1px solid #e4e6ef;
        }

        .select2-roles + .select2-container .select2-selection__choice {
            background-color: #f3f6f9 !important;
            border: 1px solid #e4e6ef !important;
            color: #3f4254 !important;
            padding: 5px 35px 5px 12px !important;
            margin: 4px !important;
        }

        .select2-roles + .select2-container .select2-selection__choice__remove {
            position: absolute !important;
            right: 6px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 24px !important;
            height: 24px !important;
            padding: 0 !important;
            font-family: "ki" !important;
            font-style: normal !important;
            border: none !important;
            border-radius: 0 !important;
            background: none !important;
        }

        .select2-roles + .select2-container .select2-selection__choice__remove:before {
            content: "\e834" !important; /* استخدام أيقونة ki-duotone ki-cross-circle */
            font-family: 'ki' !important;
            font-size: 1.3rem !important;
            color: #a1a5b7 !important;
            display: inline-block !important;
            line-height: 1 !important;
        }

        .select2-roles + .select2-container .select2-selection__choice__remove:hover:before {
            color: #009ef7 !important;
        }
    </style>
@endpush

@push('styles')
    <style>
        /* ==========================================================================
       تنسيقات أساسية لـ Metronic + Select2 (Bootstrap5، RTL صفحة)
       ========================================================================== */

        /* 1. الحاوية الرئيسية للـ tags */
        .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__rendered {
            padding: 5px;
        }

        /* 2. كل tag يصبح موضعه نسبي (relative) مع بافر داخلي كبير على اليمين */
        .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__choice {
            position: relative !important;
            /* لضبط أيقونة الإغلاق مطلقاً داخله */
            padding: 5px 32px 5px 12px !important;
            /* 32px بافر على يمين النص لإفساح المجال للأيقونة */
            margin: 4px !important;
            background-color: #f3f6f9;
            border-radius: 4px !important;
            direction: ltr !important;
            /* يكسر تأثير RTL داخل الـ tag */
        }

        /* 3. نص الـ tag بدون تعديل موضعي */
        .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__choice__display {
            padding: 0 !important;
            margin: 0 !important;
            color: #3f4254;
            font-size: 0.875rem;
        }

        /* 4. أيقونة الإغلاق تصبح مطلقة داخل الـ tag */
        .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__choice__remove {
            position: absolute !important;
            top: 50% !important;
            right: 8px !important;
            /* تحدد البُعد الثابت من حافة الـ tag */
            transform: translateY(-50%) !important;
            width: 16px !important;
            height: 16px !important;
            background-color: rgba(0, 0, 0, 0.05) !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            margin: 0 !important;
        }

        /* 5. رمز × داخل الأيقونة */
        .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__choice__remove::before {
            content: '×' !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #5e6278 !important;
            line-height: 1 !important;
        }

        /* 6. (اختياري) عند التمرير فوق الأيقونة للتفاعل */
        .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__choice__remove:hover {
            background-color: #e4e6ef !important;
            transform: translateY(-50%) scale(1.1) !important;
        }
    </style>
@endpush
