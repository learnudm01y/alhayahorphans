@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"> المحافظات   </h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addProvinceModal">
                        <i class="fas fa-plus"></i> إضافة محافظة جديدة
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

<div class="modal fade" id="addProvinceModal" tabindex="-1" aria-labelledby="addProvinceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addProvinceModalLabel">إضافة محافظة جديدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.Province_name.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="add_name" class="form-label"> محافظة</label>
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

<div class="modal fade" id="editProvinceModal" tabindex="-1" aria-labelledby="editProvinceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editProvinceModalLabel">تعديل محافظة</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editProvinceForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="Province_id" id="edit_Province_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label"> محافظة</label>
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

<div class="modal fade" id="deleteProvinceModal" tabindex="-1" aria-labelledby="deleteProvinceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteProvinceModalLabel">حذف محافظة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد أنك تريد حذف محافظة؟</p>
                <strong id="delete_Province_name"></strong>
                <form id="deleteProvinceForm" method="POST" action="" class="mt-3">
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
        $('#editProvinceModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var name = button.data('name');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#edit_Province_id').val(id);
            modal.find('#edit_name').val(name);
            modal.find('#editProvinceForm').attr('action', actionUrl);
        });

        // Populate Delete Modal
        $('#deleteProvinceModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var name = button.data('name');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#delete_Province_name').text(name);
            modal.find('#deleteProvinceForm').attr('action', actionUrl);
        });

        // Auto-hide alerts
        $(document).ready(function(){
            $(".alert").delay(5000).slideUp(300, function() {
                $(this).alert('close');
            });
        });
    </script>
@endpush
