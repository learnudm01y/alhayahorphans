@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"> انواع الوثائق</h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addDocumentTypeModal">
                        <i class="fas fa-plus"></i> إضافة نوع وثيقة جديدة
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

<div class="modal fade" id="addDocumentTypeModal" tabindex="-1" aria-labelledby="addDocumentTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addDocumentTypeModalLabel">إضافة نوع وثيقة جديدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.DocumentType_name.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="add_description" class="form-label">نوع وثيقة</label>
                        <input type="text" name="description" id="add_description" class="form-control" value="{{ old('description') }}" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="add_pref" class="form-label">المختصر</label>
                        <input type="text" name="pref" id="add_pref" class="form-control" value="{{ old('pref') }}" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editDocumentTypeModal" tabindex="-1" aria-labelledby="editDocumentTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editDocumentTypeModalLabel">تعديل نوع وثيقة</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editDocumentTypeForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="DocumentType_id" id="edit_DocumentType_id">
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">نوع وثيقة</label>
                        <input type="text" name="description" id="edit_description" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_pref" class="form-label">المختصر</label>
                        <input type="text" name="pref" id="edit_pref" class="form-control" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">تحديث</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteDocumentTypeModal" tabindex="-1" aria-labelledby="deleteDocumentTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteDocumentTypeModalLabel">حذف نوع وثيقة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد أنك تريد حذف نوع وثيقة؟</p>
                <strong id="delete_DocumentType_name"></strong>
                <form id="deleteDocumentTypeForm" method="POST" action="" class="mt-3">
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
    <!-- toastr CSS & JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Populate Edit Modal
        $('#editDocumentTypeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var description = button.data('description');
            var pref = button.data('pref');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#edit_DocumentType_id').val(id);
            modal.find('#edit_description').val(description);
            modal.find('#edit_pref').val(pref);
            modal.find('#editDocumentTypeForm').attr('action', actionUrl);
        });

        // Populate Delete Modal
        $('#deleteDocumentTypeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var name = button.data('name');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#delete_DocumentType_name').text(name);
            modal.find('#deleteDocumentTypeForm').attr('action', actionUrl);
        });

        // Auto-hide alerts
        $(document).ready(function(){
            $(".alert").delay(5000).slideUp(300, function() {
                $(this).alert('close');
            });
        });

        // متغير لضمان عدم تكرار رسالة toastr عند التحديث الجماعي
        let documentTypeToastrShown = false;

        function bindDocumentTypeSwitches() {
            document.querySelectorAll('.document-type-switch').forEach(function(switchEl) {
                switchEl.onchange = null;
                switchEl.addEventListener('change', function() {
                    const id = this.dataset.id;
                    const portal = this.dataset.portal;
                    const enabled = this.checked ? 1 : 0;
                    fetch("{{ route('admin.DocumentType_name.update', 0) }}".replace('/0', '/' + id), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            portal: portal,
                            enabled: enabled,
                            _method: 'PATCH'
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // عرض toastr مرة واحدة فقط مهما كان عدد التحديثات المتزامنة
                            if (!documentTypeToastrShown) {
                                toastr.success('تم تحديث حالة الوثيقة بنجاح');
                                documentTypeToastrShown = true;
                                setTimeout(() => { documentTypeToastrShown = false; }, 1500);
                            }
                            if (window.LaravelDataTables && window.LaravelDataTables['documenttype-table']) {
                                window.LaravelDataTables['documenttype-table'].ajax.reload(function() {
                                    bindDocumentTypeSwitches();
                                }, false);
                            }
                        } else {
                            toastr.error('فشل التحديث: ' + (data.error || 'خطأ غير معروف'));
                        }
                    })
                    .catch((err) => {
                        toastr.error('حدث خطأ أثناء الاتصال بالخادم');
                        console.error(err);
                    });
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            bindDocumentTypeSwitches();
        });
        $(document).on('draw.dt', function() {
            bindDocumentTypeSwitches();
        });
    </script>
@endpush

@push('scriptsCode')
<script>
function bindDocumentTypeRequiredRadios() {
    document.querySelectorAll('.document-type-required-radio').forEach(function(radioEl) {
        if (!radioEl.dataset.bound) {
            radioEl.addEventListener('change', function() {
                const id = this.dataset.id;
                const is_required = this.value;
                fetch("{{ route('admin.DocumentType_name.update', 0) }}".replace('/0', '/' + id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        is_required: is_required,
                        _method: 'PATCH'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success('تم تحديث حالة الإلزامية بنجاح');
                        if (window.LaravelDataTables && window.LaravelDataTables['documenttype-table']) {
                            window.LaravelDataTables['documenttype-table'].ajax.reload(null, false);
                        }
                    } else {
                        toastr.error('فشل التحديث: ' + (data.error || 'خطأ غير معروف'));
                    }
                })
                .catch((err) => {
                    toastr.error('حدث خطأ أثناء الاتصال بالخادم');
                    console.error(err);
                });
            });
            radioEl.dataset.bound = "1";
        }
    });
}

$(document).ready(function() {
    bindDocumentTypeRequiredRadios();
    if (window.LaravelDataTables && window.LaravelDataTables['documenttype-table']) {
        window.LaravelDataTables['documenttype-table'].on('draw', function() {
            bindDocumentTypeRequiredRadios();
        });
    }
});
</script>
@endpush

