@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">إدارة المدن</h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addCityModal">
                        <i class="fas fa-plus"></i> إضافة مدينة جديدة
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
                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ session('error') }}
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

<div class="modal fade" id="addCityModal" tabindex="-1" aria-labelledby="addCityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addCityModalLabel">إضافة مدينة جديدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.city_name.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="add_name" class="form-label">اسم المدينة <span class="text-danger">*</span></label>
                        <input type="text" name="city" id="add_name" class="form-control" value="{{ old('city') }}" required autofocus placeholder="أدخل اسم المدينة">
                    </div>
                    <div class="mb-3">
                        <label for="add_province_id" class="form-label">المحافظة</label>
                        <select name="province_id" id="add_province_id" class="form-select">
                            <option value="">اختر المحافظة (اختياري)</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->id }}" {{ old('province_id') == $province->id ? 'selected' : '' }}>
                                    {{ $province->description }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editCityModal" tabindex="-1" aria-labelledby="editCityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editCityModalLabel">تعديل بيانات المدينة</h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editCityForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">اسم المدينة <span class="text-danger">*</span></label>
                        <input type="text" name="city" id="edit_name" class="form-control" required placeholder="أدخل اسم المدينة">
                    </div>
                    <div class="mb-3">
                        <label for="edit_province_id" class="form-label">المحافظة</label>
                        <select name="province_id" id="edit_province_id" class="form-select">
                            <option value="">اختر المحافظة (اختياري)</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->id }}">
                                    {{ $province->description }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">تحديث</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteCityModal" tabindex="-1" aria-labelledby="deleteCityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCityModalLabel">حذف المدينة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد أنك تريد حذف هذه المدينة؟</p>
                <div class="alert alert-secondary text-center">
                    <strong id="delete_City_name" class="fs-5 text-dark"></strong>
                </div>
                <form id="deleteCityForm" method="POST" action="" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
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
        $(document).on('change', '.city-province-ui', function () {
            const cityId = $(this).data('city-id');
            const provinceId = $(this).val();
            const selectElem = $(this);

            if (cityId) {
                selectElem.prop('disabled', true);
                $.ajax({
                    url: '/admin/city_name/' + cityId + '/update-province',
                    type: 'PATCH',
                    data: {
                        _token: '{{ csrf_token() }}',
                        province_id: provinceId
                    },
                    success: function (response) {
                        selectElem.prop('disabled', false);
                    },
                    error: function(xhr) {
                        selectElem.prop('disabled', false);
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'حدث خطأ أثناء تحديث المحافظة';
                        alert(msg);
                    }
                });
            }
        });

        $(document).ready(function () {
            $(".alert").delay(5000).slideUp(300, function() {
                $(this).alert('close');
            });
        });

        $('#editCityModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var name = button.data('name');
            var provinceId = button.data('province-id');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#edit_name').val(name);
            modal.find('#edit_province_id').val(provinceId || '');
            modal.find('#editCityForm').attr('action', actionUrl);
        });

        $('#deleteCityModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var name = button.data('name');
            var actionUrl = button.data('action');

            var modal = $(this);
            modal.find('#delete_City_name').text(name);
            modal.find('#deleteCityForm').attr('action', actionUrl);
        });
    </script>
@endpush
