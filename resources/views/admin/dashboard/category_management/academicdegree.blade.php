@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">إدارة الدرجات العلمية</h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addAcademicDegreeModal">
                        <i class="fas fa-plus"></i> إضافة درجة علمية جديدة
                    </button>
                </div>
                <div class="card-body">
                    <div id="alert-messages">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive">
                        {!! $dataTable->table(['class' => 'table table-hover table-bordered table-striped text-center align-middle w-100'], true) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAcademicDegreeModal" tabindex="-1" aria-labelledby="addAcademicDegreeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addAcademicDegreeModalLabel">إضافة درجة علمية جديدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.store.category.management.academicdegree') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="add_name" class="form-label">اسم الدرجة العلمية</label>
                        <input type="text" name="description" id="add_name" class="form-control" value="{{ old('description') }}" required autofocus>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editAcademicDegreeModal" tabindex="-1" aria-labelledby="editAcademicDegreeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editAcademicDegreeModalLabel">تعديل الدرجة العلمية</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editAcademicDegreeForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="academic_degree_id" id="edit_academic_degree_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">اسم الدرجة العلمية</label>
                        <input type="text" name="description" id="edit_name" class="form-control" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">تحديث</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteAcademicDegreeModal" tabindex="-1" aria-labelledby="deleteAcademicDegreeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteAcademicDegreeModalLabel">حذف الدرجة العلمية</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد أنك تريد حذف هذه الدرجة العلمية؟</p>
                <strong id="delete_academic_degree_name"></strong>
                <form id="deleteAcademicDegreeForm" method="POST" action="" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">حذف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}
    <script>
        // Populate Edit Modal
        $('#editAcademicDegreeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var name = button.data('name');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#edit_academic_degree_id').val(id);
            modal.find('#edit_name').val(name);
            modal.find('#editAcademicDegreeForm').attr('action', actionUrl);
        });

        // Populate Delete Modal
        $('#deleteAcademicDegreeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var name = button.data('name');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#delete_academic_degree_name').text(name);
            modal.find('#deleteAcademicDegreeForm').attr('action', actionUrl);
        });

        // Auto-hide alerts
        $(document).ready(function(){
            $(".alert").delay(5000).slideUp(300, function() {
                $(this).alert('close');
            });
        });
    </script>
@endpush
