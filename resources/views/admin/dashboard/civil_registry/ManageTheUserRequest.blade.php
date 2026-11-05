@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="w-100 px-3 py-4">
        <div class="card shadow border-0 rounded-lg animate__animated animate__fadeIn">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between" style="border-top-right-radius: .5rem; border-top-left-radius: .5rem;">
                <h5 class="mb-0 text-white"><i class="fas fa-users-cog me-2 text-white"></i> إدارة طلبات المستخدمين</h5>
            </div>

            <!-- قسم الفلاتر -->
            <div class="card-body bg-white border-bottom p-3">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label for="status-filter" class="form-label fw-bold">
                            <i class="fas fa-filter text-primary"></i> فلترة حسب الحالة
                        </label>
                        <select id="status-filter" class="form-select form-select-lg">
                            <option value="">الكل</option>
                            @foreach(\App\Models\RequestStatus::all() as $status)
                                <option value="{{ $status->id }}">{{ $status->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" id="reset-filter" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> إعادة تعيين
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body bg-light p-4">
                {!! $dataTable->table([
                    'class' => 'table table-bordered table-hover table-striped align-middle mb-0 text-right',
                    'id' => 'managetheuserrequest-table',
                    'style' => 'width:100%',
                    'dir' => 'rtl',
                ]) !!}
            </div>
        </div>
    </div>
@endSection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        .card {
            border-radius: .75rem;
        }
        .card-header {
            border-top-right-radius: .75rem;
            border-top-left-radius: .75rem;
        }
        .card-body {
            border-bottom-right-radius: .75rem;
            border-bottom-left-radius: .75rem;
        }
        .table thead th {
            background: #f8fafc;
            color: #1a237e;
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
            text-align: right !important;
            direction: rtl;
        }
        .table {
            direction: rtl;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #f4f7fa;
        }
        .table-hover tbody tr:hover {
            background-color: #e3eafc;
        }
        .badge {
            font-size: 1em;
            padding: .5em 1em;
            border-radius: .5em;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: .5em;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: .5em;
            border: 1px solid #bdbdbd;
            padding: .25em .75em;
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: .5em;
            border: 1px solid #bdbdbd;
            padding: .25em .75em;
        }
    </style>
    <script>
        // ضبط اتجاه ظهور toastr إلى اليسار
        toastr.options = {
            "positionClass": "toast-top-left",
            "closeButton": true,
            "progressBar": true
        };

        // الفلترة حسب الحالة
        $(document).ready(function() {
            var table = $('#managetheuserrequest-table').DataTable();

            $('#status-filter').on('change', function() {
                var statusId = $(this).val();

                // إعادة تحميل الجدول مع معامل الفلتر
                table.ajax.url('{{ route('admin.manage.user.requests.index') }}?status_id=' + statusId).load();
            });

            $('#reset-filter').on('click', function() {
                $('#status-filter').val('').trigger('change');
            });
        });

        $(document).on('change', '.change-status', function() {
            var id = $(this).data('id');
            var status_id = $(this).val();
            var select = $(this);
            $.ajax({
                url: '{{ route('admin.manage.user.requests.changeStatus') }}',
                type: 'POST',
                data: {
                    id: id,
                    status_id: status_id,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if(response.success) {
                        toastr.success(response.message);
                        var table = $('#managetheuserrequest-table').DataTable();
                        var row = select.closest('tr');
                        // تحديث شارة الحالة في DataTable مباشرة
                        var badgeCellIndex = table.column('request_status_badge:name').index();
                        var rowIndex = table.row(row).index();
                        if(badgeCellIndex !== undefined && rowIndex !== undefined && response.status_html) {
                            table.cell(rowIndex, badgeCellIndex).data(response.status_html).draw(false);
                        }
                        // تحديث قائمة الحالة في action من السيرفر إذا أرسلت الخيارات الجديدة
                        if(response.status_options_html) {
                            select.html(response.status_options_html);
                        } else {
                            select.find('option').each(function() {
                                if($(this).text() === response.status) {
                                    $(this).prop('selected', true);
                                } else {
                                    $(this).prop('selected', false);
                                }
                            });
                        }
                        if(response.status === 'مقبول') {
                            row.fadeOut(400, function() {
                                $(this).remove();
                                // إعادة تحميل الجدول بالفلتر الحالي
                                var currentFilter = $('#status-filter').val();
                                if(currentFilter) {
                                    table.ajax.url('{{ route('admin.manage.user.requests.index') }}?status_id=' + currentFilter).load();
                                }
                            });
                        }
                    } else {
                        toastr.error('حدث خطأ أثناء تغيير الحالة');
                    }
                },
                error: function(xhr) {
                    // رفض الخادم (مثل وجود أفراد معلّقين): أعيد القائمة للحالة السابقة وبيّن السبب
                    var message = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'حدث خطأ أثناء تغيير الحالة';
                    toastr.error(message);
                    select.val(select.data('current-status'));
                }
            });
        });

        // ===== الفحص والقبول التلقائي =====
        function escapeHtml(value) {
            return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
        }

        function renderCheckReport(report) {
            var html = '<div class="text-right" dir="rtl">';

            (report.checks || []).forEach(function(check) {
                var icon = check.passed ? '✅' : '⛔';
                html += '<div class="mb-2">'
                    + '<div class="fw-bold">' + icon + ' ' + escapeHtml(check.label) + '</div>'
                    + '<div class="small text-muted">' + escapeHtml(check.detail) + '</div>'
                    + '</div>';
            });

            var missing = report.missing_documents || [];
            if (missing.length) {
                html += '<hr><div class="fw-bold mb-1">الوثائق الناقصة (' + missing.length + '):</div><ul class="mb-0 ps-3">';
                missing.forEach(function(item) {
                    html += '<li class="small">' + escapeHtml(item.person) + ' — ' + escapeHtml(item.document) + '</li>';
                });
                html += '</ul>';
            }

            return html + '</div>';
        }

        // تحديث شارة الحالة ثم إخراج السطر من الطابور، كما يفعل قائمة الحالة المنسدلة
        function markRowAccepted(row, statusHtml) {
            var table = $('#managetheuserrequest-table').DataTable();
            var badgeCellIndex = table.column('request_status_badge:name').index();
            var rowIndex = table.row(row).index();

            if (badgeCellIndex !== undefined && rowIndex !== undefined && statusHtml) {
                table.cell(rowIndex, badgeCellIndex).data(statusHtml).draw(false);
                row = table.row(rowIndex).node();
            }

            $(row).fadeOut(400, function() {
                table.row($(row)).remove().draw(false);
            });
        }

        function applyAutoAccept(id, button) {
            $.ajax({
                url: '{{ route('admin.manage.user.requests.autoAcceptApply') }}',
                type: 'POST',
                data: { id: id, _token: '{{ csrf_token() }}' },
                success: function(response) {
                    button.removeData('busy').prop('disabled', false);
                    toastr.success(response.message);
                    markRowAccepted(button.closest('tr'), response.status_html);
                },
                error: function(xhr) {
                    button.removeData('busy').prop('disabled', false);
                    var report = (xhr.responseJSON && xhr.responseJSON.report) || {};
                    var message = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'حدث خطأ أثناء اعتماد الطلب';
                    Swal.fire({
                        icon: 'error',
                        title: 'لم يُعتمد الطلب',
                        html: '<div class="mb-2">' + escapeHtml(message) + '</div>' + renderCheckReport(report),
                        confirmButtonText: 'حسنًا'
                    });
                }
            });
        }

        $(document).on('click', '.auto-accept-check', function() {
            var button = $(this);

            if (button.data('busy')) {
                return;
            }

            button.data('busy', true).prop('disabled', true);

            var id = button.data('id');
            var head = '<div class="small text-muted mb-2">الملف ' + escapeHtml(button.data('file') || '—')
                + (button.data('name') ? ' — ' + escapeHtml(button.data('name')) : '') + '</div>';
            var stop = function() {
                button.removeData('busy').prop('disabled', false);
            };

            $.ajax({
                url: '{{ route('admin.manage.user.requests.autoAcceptCheck') }}',
                type: 'POST',
                data: { id: id, _token: '{{ csrf_token() }}' },
                success: function(response) {
                    var report = response.report || {};

                    if (report.already_accepted) {
                        stop();
                        Swal.fire({ icon: 'info', title: 'الطلب معتمد بالفعل', html: head + renderCheckReport(report) });
                        return;
                    }

                    if (!report.can_accept) {
                        stop();
                        Swal.fire({
                            icon: 'error',
                            title: 'لا يمكن الاعتماد التلقائي',
                            html: head + renderCheckReport(report),
                            confirmButtonText: 'حسنًا'
                        });
                        return;
                    }

                    // الفحص نجح: لا كتابة قبل تأكيد الموظف صراحةً
                    Swal.fire({
                        icon: 'question',
                        title: 'كل الفحوصات ناجحة',
                        html: head + renderCheckReport(report)
                            + '<div class="mt-3 fw-bold">هل تُعيَّن الحالة إلى «مقبول» الآن؟</div>',
                        showCancelButton: true,
                        confirmButtonText: 'اعتماد الطلب',
                        cancelButtonText: 'إلغاء'
                    }).then(function(result) {
                        if (!result.isConfirmed) {
                            stop();
                            return;
                        }
                        applyAutoAccept(id, button);
                    });
                },
                error: function(xhr) {
                    stop();
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'تعذّر تنفيذ الفحص على الخادم');
                }
            });
        });
    </script>
@endpush
