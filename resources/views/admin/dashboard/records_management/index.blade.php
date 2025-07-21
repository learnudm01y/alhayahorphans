@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">سجل الإدارة</h3>
                    </div>
                    <div class="card-body">
                       {!! $dataTable->table(['class' => 'table table-bordered table-striped text-center align-middle w-100'], true) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* تحسين عرض الـ dropdown */
        .dropdown-menu {
            z-index: 9999 !important;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            color: #212529 !important;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .table td {
            position: relative;
        }

        /* التأكد من أن الأزرار تظهر بشكل صحيح */
        .btn-secondary.dropdown-toggle {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary.dropdown-toggle:hover {
            background-color: #5c636a;
            border-color: #565e64;
        }
    </style>
@endsection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}
    <script>
        $(document).ready(function() {
            // التأكد من أن dropdowns تعمل بشكل صحيح
            $(document).on('click', '.dropdown-toggle', function(e) {
                e.preventDefault();
                $(this).dropdown('toggle');
            });

            // إعادة تهيئة Bootstrap dropdowns بعد تحديث DataTable
            $('#recordsmanagemente-table').on('draw.dt', function() {
                // إعادة تهيئة dropdowns
                var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
                var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                    return new bootstrap.Dropdown(dropdownToggleEl);
                });
            });
        });
    </script>
@endpush
