@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="w-100 px-3 py-4">
        <div class="card shadow border-0 rounded-lg animate__animated animate__fadeIn">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between" style="border-top-right-radius: .5rem; border-top-left-radius: .5rem;">
                <h5 class="mb-0 text-white"><i class="fas fa-users-cog me-2 text-white"></i> إدارة طلبات المستخدمين</h5>
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
                            row.fadeOut(400, function() { $(this).remove(); });
                        }
                    } else {
                        toastr.error('حدث خطأ أثناء تغيير الحالة');
                    }
                },
                error: function(xhr) {
                    toastr.error('حدث خطأ أثناء تغيير الحالة');
                }
            });
        });
    </script>
@endpush
