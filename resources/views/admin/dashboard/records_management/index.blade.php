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
