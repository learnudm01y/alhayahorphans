@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid py-4" style="max-width:100vw;">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-gradient-primary text-dark fw-bold fs-4 text-center rounded-top p-4 d-flex align-items-center justify-content-between flex-wrap">
                        <span style="margin-top: 1.5rem; display: inline-block;">
                            <i class="fas fa-user-edit me-2"></i>
                            تعديل السجل
                        </span>
                        <div class="d-flex align-items-center" style="margin-top: 1.5rem;">
                            <label class="form-label mb-0 me-2 fs-5" style="color: #222;">رقم الملف:</label>
                            <input type="text"
                                class="form-control bg-secondary bg-opacity-25 border-0 text-center fs-3 fw-bold"
                                style="width: 180px; height: 55px; box-shadow: none;" value="{{ isset($data->file_id_number) ? str_pad($data->file_id_number, 6, '0', STR_PAD_LEFT) : '' }}"
                                readonly>
                        </div>
                    </div>
                    <div class="card-body bg-light">
                        @include('admin.dashboard.records_management.form_sections', ['edit' => true])
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('admin.dashboard.records_management.style')
@endsection

@push('scriptsCode')
    @include('admin.dashboard.records_management.javascript')
@endpush
