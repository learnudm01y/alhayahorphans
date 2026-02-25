@extends('admin.dashboard.toolbars.index')
@section('content')
    @push('styles')
        <style>
            /* اتجاه الجدول من اليمين لليسار */
            table.dataTable {
                direction: rtl !important;
                color: #000 !important;
                /* النص باللون الأسود */
                background: #fff;
                border-radius: 0.75rem;
                overflow: hidden;
                font-family: 'Cairo', 'Tajawal', Arial, sans-serif;
                font-size: 1rem;
            }

            /* محاذاة رؤوس الأعمدة ومحتوى الخلايا لليمين */
            table.dataTable thead th {
                background: #f5f6fa;
                color: #2D85FF;
                font-weight: bold;
                border-bottom: 2px solid #e9ecef;
                text-align: right !important;
                vertical-align: middle;
                font-size: 1.08rem;
                padding-top: 1rem;
                padding-bottom: 1rem;
            }

            table.dataTable tbody td {
                background: #fff;
                color: #222;
                text-align: right !important;
                vertical-align: middle;
                font-size: 1rem;
                padding-top: 0.7rem;
                padding-bottom: 0.7rem;
                border-bottom: 1px solid #f0f0f0;
            }

            table.dataTable tbody tr:last-child td {
                border-bottom: none;
            }

            /* إزالة أي تأثير hover/active/focus/selected على الصفوف والخلايا بدون إخفاء النص */
            table.dataTable tbody tr:hover,
            table.dataTable tbody tr:focus,
            table.dataTable tbody tr:active,
            table.dataTable tbody tr.selected,
            table.dataTable tbody tr:focus-visible,
            table.dataTable tbody td:hover,
            table.dataTable tbody td:focus,
            table.dataTable tbody td:active,
            table.dataTable tbody td.selected,
            table.dataTable tbody tr.selected td {
                background-color: transparent !important;
                outline: none !important;
                box-shadow: none !important;
                color: inherit !important;
                /* إبقاء النص ظاهر */
            }

            /* تعطيل تمييز الخلية عند التحديد (مثلاً عند الضغط على خلية) بدون إخفاء النص */
            table.dataTable tbody td:active,
            table.dataTable tbody td:focus,
            table.dataTable tbody td.selected {
                background-color: transparent !important;
                outline: none !important;
                box-shadow: none !important;
                color: inherit !important;
                /* إبقاء النص ظاهر */
            }

            /* تعطيل تمييز الصف بالكامل عند التحديد بدون إخفاء النص */
            table.dataTable tbody tr.selected,
            table.dataTable tbody tr.selected td {
                background-color: transparent !important;
                color: inherit !important;
                /* إبقاء النص ظاهر */
            }

            /* تعطيل أي تأثير hover على الصفوف والخلايا نهائياً */
            table.dataTable tbody tr:hover,
            table.dataTable tbody td:hover {
                background-color: inherit !important;
                color: inherit !important;
                outline: none !important;
                box-shadow: none !important;
                transition: none !important;
                filter: none !important;
                cursor: default !important;
            }

            /* تنسيق زر الإضافة */
            .btn.btn-primary {
                background-color: #2D85FF;
                border: none;
                font-weight: bold;
                padding: 0.6rem 1.2rem;
                font-size: 1rem;
                border-radius: 0.45rem;
            }

            /* تنسيق أزرار العمليات */
            .btn-action {
                font-weight: 500;
                font-size: 0.95rem;
                padding: 0.4rem 0.9rem;
                border-radius: 0.45rem;
                margin-left: 0.3rem;
            }
        </style>
    @endpush

    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pt-6 bg-white">
            <div class="card-title">
                <h2 class="fw-bold fs-3 text-primary">إدارة المستخدمين</h2>
            </div>
            <div class="card-toolbar">
                @can('إنشاء مستخدم')
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    إضافة مستخدم جديد
                </a>
                @endcan
            </div>
        </div>

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

        <div class="card-body py-4">


            <!-- شريط التبويب -->
            <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#kt_tab_admins">الإدارة</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.role.management102') }}">المستخدمين</a>
                </li>
            </ul>
            <!-- حقول البحث -->
            <div class="row mb-4">
                <div class="col-md-4 mb-2">
                    <input type="text" id="search-name" class="form-control" placeholder="بحث بالاسم">
                </div>
                <div class="col-md-4 mb-2">
                    <input type="text" id="search-mobile" class="form-control" placeholder="بحث برقم الجوال">
                </div>
            </div>
            <div class="tab-content" id="myTabContent">
                <!-- تبويب الإدارة -->
                <div class="tab-pane fade show active" id="kt_tab_admins" role="tabpanel">
                    {!! $dataTable->table(
                        [
                            'class' => 'table table-bordered align-middle w-100 text-end',
                            'style' => 'direction: rtl;',
                        ],
                        true,
                    ) !!}
                </div>

                <!-- تبويب المستخدمين -->
                <div class="tab-pane fade" id="kt_tab_users" role="tabpanel">
                    {{-- جدول المستخدمين العاديين مستقبلاً --}}
                </div>
            </div>
        </div>
    </div>

    <!-- مودال سجل نشاط الموظف -->
    <div class="modal fade" id="adminActivityModal" tabindex="-1" aria-labelledby="adminActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminActivityModalLabel">سجل نشاط الموظف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div id="admin-activity-table-area" class="w-100 text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}
    <script>
        // ربط البحث المخصص مع الجدول
        $(document).ready(function() {
            var table = $('.dataTable').DataTable();

            $('#search-name').on('keyup change', function() {
                // البحث في عمود الاسم (غير رقم العمود حسب ترتيب جدولك)
                table.column(1).search(this.value).draw();
            });

            $('#search-mobile').on('keyup change', function() {
                // البحث في عمود الجوال (غير رقم العمود حسب ترتيب جدولك)
                table.column(3).search(this.value).draw();
            });

            $('#admins_table').DataTable({
                select: false, // ✅ تأكد من تعطيله
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json',
                    search: 'بحث:'
                },
                order: [
                    [0, 'asc']
                ],
                pagingType: 'full_numbers',
                responsive: true,
                autoWidth: false,
                columnDefs: [{
                        targets: 0,
                        width: '80px',
                        className: 'text-end fw-bold fs-6'
                    },
                    {
                        targets: [1, 2, 3],
                        width: '200px',
                        className: 'text-end pe-3 fs-6'
                    },
                    {
                        targets: -1,
                        width: '180px',
                        className: 'text-center',
                        orderable: false
                    }
                ]
            });

            // استخدم delegation ليعمل مع العناصر الديناميكية
            $(document).on('click', '.delete-btn', function() {
                var userId = $(this).data('id');
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'لا يمكن التراجع عن عملية الحذف!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف!',
                    cancelButtonText: 'إلغاء',
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#delete-form-' + userId).submit();
                    }
                });
            });

            // سجل نشاط الموظف - جلب السجلات عند الضغط على الزر
            $(document).on('click', '.admin-activity-btn', function() {
                var adminId = $(this).data('admin-id');
                var adminName = $(this).data('admin-name');
                $('#adminActivityModalLabel').text('سجل نشاط الموظف: ' + adminName);
                var area = document.getElementById('admin-activity-table-area');
                area.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">جاري التحميل...</span></div>';

                fetch('/admin/ajax/admin-records/' + adminId)
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            area.innerHTML = data.html;
                            bindActivityPagination(adminId);
                        } else {
                            area.innerHTML = '<div class="alert alert-warning">' + (data.message || 'لا توجد سجلات.') + '</div>';
                        }
                    })
                    .catch(function() {
                        area.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء جلب البيانات.</div>';
                    });
            });

            function bindActivityPagination(adminId) {
                var area = document.getElementById('admin-activity-table-area');
                var pagers = area.querySelectorAll('.admin-records-pagination a.page-link[data-page]');
                pagers.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        var page = link.getAttribute('data-page');
                        area.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">جاري التحميل...</span></div>';
                        fetch('/admin/ajax/admin-records/' + adminId + '?page=' + page)
                            .then(function(response) { return response.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    area.innerHTML = data.html;
                                    bindActivityPagination(adminId);
                                } else {
                                    area.innerHTML = '<div class="alert alert-warning">' + (data.message || 'لا توجد سجلات.') + '</div>';
                                }
                            })
                            .catch(function() {
                                area.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء جلب البيانات.</div>';
                            });
                    });
                });
            }
        });
    </script>
@endpush
