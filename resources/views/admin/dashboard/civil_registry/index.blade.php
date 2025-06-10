@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header p-4" style="background: #0d6efd; border-radius: .5rem .5rem 0 0;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="color: #fff; font-weight: bold; font-size: 1.5rem; letter-spacing: 1px;">
                                <i class="bi bi-people-fill me-2" style="color: #fff;"></i>
                                إدارة المواطنين
                            </span>
                        </div>
                        <div class="d-flex justify-content-start">
                            <a href="{{ route('admin.persons.create') }}" class="btn btn-light btn-sm d-flex align-items-center justify-content-center">
                                <i class="bi bi-plus-circle me-1"></i>
                                إضافة مواطن
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <div class="row g-2">
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-id-num" class="form-control" placeholder="رقم الهوية">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-first-arb" class="form-control" placeholder="الاسم الأول">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-father-arb" class="form-control" placeholder="اسم الأب">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-grand-father-arb" class="form-control" placeholder="اسم الجد">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-family-arb" class="form-control" placeholder="اسم العائلة">
                                </div>
                            </div>
                        </div>
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
        // لا تغييرات هنا لأن التسريع يجب أن يتم في الاستعلامات وقاعدة البيانات وليس في الواجهة
        document.addEventListener('DOMContentLoaded', function () {
            $(document).on('preInit.dt', '#persons-table', function () {
                var table = $('#persons-table').DataTable();
                $('#search-ci-id-num').on('keyup change', function () {
                    table.column(0).search(this.value).draw();
                });
                $('#search-ci-first-arb').on('keyup change', function () {
                    table.column(1).search(this.value).draw();
                });
                $('#search-ci-father-arb').on('keyup change', function () {
                    table.column(2).search(this.value).draw();
                });
                $('#search-ci-grand-father-arb').on('keyup change', function () {
                    table.column(3).search(this.value).draw();
                });
                $('#search-ci-family-arb').on('keyup change', function () {
                    table.column(4).search(this.value).draw();
                });
            });
            $('#persons-table').on('click', '.delete-btn', function (event) {
                event.preventDefault();
                var form = $(this).closest('form');
                var deleteUrl = form.attr('action');

                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'لن تتمكن من التراجع عن هذا الإجراء!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، احذف!',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.off('submit').submit();
                    }
                });
            });
        });
    </script>
@endpush
