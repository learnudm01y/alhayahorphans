{{-- هذا الملف الجزئي يحتوي على جميع الحقول والأقسام والعمليات الموجودة في create.blade.php ويستخدم في كل من create و edit --}}
<ul class="nav nav-tabs nav-fill mb-4 mobile-bottom-tabs" id="formTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active py-3" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic"
            type="button" role="tab" aria-controls="basic" aria-selected="true">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-user tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label">البيانات الأساسية</span>
            </div>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-3" id="family-members-tab" data-bs-toggle="tab"
            data-bs-target="#family-members" type="button" role="tab"
            aria-controls="family-members" aria-selected="false">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-users tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label">أفراد الأسرة</span>
            </div>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-3" id="deceased-tab" data-bs-toggle="tab" data-bs-target="#deceased"
            type="button" role="tab" aria-controls="deceased" aria-selected="false">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-user-times tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label">الأفراد المتوفين</span>
            </div>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-3" id="attachments-tab" data-bs-toggle="tab"
            data-bs-target="#attachments" type="button" role="tab" aria-controls="attachments"
            aria-selected="false">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-paperclip tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label">المرفقات</span>
            </div>
        </button>
    </li>
</ul>
<style>
.tab-icon {
    font-size: 2rem;
    color: #0d6efd;
    background: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    margin-bottom: 0.2rem;
    border: none !important;
    transition: none !important;
    box-shadow: none !important;
}
.nav-tabs .nav-link.active .tab-icon,
.nav-tabs .nav-link:focus .tab-icon,
.nav-tabs .nav-link:hover .tab-icon {
    background: none !important;
    color: #0d6efd !important;
    border: none !important;
    transform: none !important;
    box-shadow: none !important;
}

/* معالجة القوائم المنسدلة وخيارات select لتناسب جميع الأجهزة المحمولة واللوحية بشكل دقيق */
@media (max-width: 1400px) {
    select,
    select:focus {
        font-size: 1.1rem !important;
        max-width: 100vw !important;
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
    select option {
        font-size: 1.1rem !important;
        max-width: 98vw !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        padding: 0.5rem 1rem !important;
        line-height: 1.6 !important;
    }
    .dropdown-menu,
    .bootstrap-select .dropdown-menu,
    .dropdown-menu.show {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        min-width: 0 !important;
        max-width: 98vw !important;
        width: 98vw !important;
        margin: 0 auto !important;
        z-index: 1100 !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 24px rgba(0,0,0,0.13) !important;
        overflow-x: hidden !important;
        max-height: 60vh !important;
        overflow-y: auto !important;
        font-size: 1.1rem !important;
    }
    .dropdown-menu .dropdown-item,
    .bootstrap-select .dropdown-menu .dropdown-item {
        font-size: 1.1rem !important;
        white-space: normal !important;
        word-break: break-word !important;
        padding: 0.5rem 1rem !important;
        line-height: 1.6 !important;
    }
    .card-body .dropdown-menu,
    .bg-light .dropdown-menu {
        left: 1vw !important;
        right: 1vw !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }
}
@media (max-width: 576px) {
    .mobile-bottom-tabs {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1050;
        background: rgba(245,245,245,0.95);
        box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
        margin-bottom: 0 !important;
        border-top: 1.5px solid #e5e7eb;
        border-radius: 22px 22px 0 0;
        padding: 0.2rem 0.5rem 0.3rem 0.5rem;
        display: flex !important;
        justify-content: space-between;
        gap: 0 !important;
    }
    .mobile-bottom-tabs .nav-item {
        flex: 1 1 0;
        display: flex;
        justify-content: center;
        align-items: stretch;
        position: relative;
    }
    .mobile-bottom-tabs .nav-link {
        padding: 0.4rem 0 !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        border-radius: 18px !important;
        position: relative;
        height: 100%;
        min-width: 0;
    }
    .mobile-bottom-tabs .tab-label {
        display: none !important;
    }
    .tab-icon {
        font-size: 2.1rem !important;
        color: #232323 !important;
        background: rgba(200,200,200,0.18) !important;
        border-radius: 16px !important;
        padding: 0.55rem !important;
        margin-bottom: 0 !important;
        border: none !important;
        box-shadow: 0 1px 6px rgba(0,0,0,0.04) !important;
        transition: background 0.2s, color 0.2s, box-shadow 0.2s !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .nav-tabs .nav-link.active .tab-icon,
    .nav-tabs .nav-link:focus .tab-icon,
    .nav-tabs .nav-link:hover .tab-icon {
        color: #232323 !important;
        background: rgba(44,44,44,0.13) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.10) !important;
    }
    .mobile-bottom-tabs .nav-item:not(:last-child)::after {
        content: "";
        position: absolute;
        top: 18%;
        right: 0;
        width: 1.5px;
        height: 64%;
        background: #e5e7eb;
        border-radius: 2px;
        opacity: 0.85;
        z-index: 2;
    }
    body {
        padding-bottom: 80px !important;
    }
}
</style>
<div class="card-body bg-light">
    <form action="{{ isset($edit) && $edit ? route('admin.records.management.update', $data->id) : route('admin.records.management.store') }}" method="POST"
        enctype="multipart/form-data" autocomplete="off" id="main_form">
        @csrf
        @if(isset($edit) && $edit)
            @method('PUT')
        @endif
        <input type="hidden" name="file_id_number" value="{{ $data->file_id_number ?? $file_id_number ?? '' }}">
        <input type="hidden" id="person_identity_number_hidden" name="person_identity_number" value="">
        <input type="hidden" id="file_type_hidden" name="file_type" value="">
        <div class="tab-content" id="formTabsContent">
            <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                @include('admin.dashboard.records_management.sections.basic', ['edit' => isset($edit) ? $edit : false])
            </div>
            <div class="tab-pane fade" id="family-members" role="tabpanel" aria-labelledby="family-members-tab">
                @include('admin.dashboard.records_management.sections.family_members', ['edit' => isset($edit) ? $edit : false])
            </div>
            <div class="tab-pane fade" id="deceased" role="tabpanel" aria-labelledby="deceased-tab">
                @include('admin.dashboard.records_management.sections.deceased', ['edit' => isset($edit) ? $edit : false])
            </div>
            <div class="tab-pane fade" id="attachments" role="tabpanel" aria-labelledby="attachments-tab">
                @include('admin.dashboard.records_management.sections.attachments', ['edit' => isset($edit) ? $edit : false])
            </div>
        </div>

        {{-- منطقة عرض رسائل الـ Validation --}}
        <div id="validation-errors-container" class="alert alert-danger d-none mt-4" role="alert">
            <h5 class="alert-heading mb-3"><i class="fas fa-exclamation-triangle me-2"></i>يرجى تصحيح الأخطاء التالية:</h5>
            <ul id="validation-errors-list" class="mb-0"></ul>
        </div>

        <div class="mt-4 text-center">
            <button type="submit" class="btn btn-success px-5 py-2">{{ isset($edit) && $edit ? 'حفظ التعديلات' : 'حفظ السجل' }}</button>
            <a href="{{ route('admin.records.management') }}" class="btn btn-secondary px-4 py-2">إلغاء</a>
        </div>
    </form>
</div>
{{-- تمرير بيانات الأشخاص للـ JS --}}
<script>
window.personsList = {
    main: {
        id: "{{ $data->data_id_number ?? $data_id_number ?? '' }}",
        name: "{{ trim(($data->data_first_name ?? '') . ' ' . ($data->data_father_name ?? '') . ' ' . ($data->data_grand_father_name ?? '') . ' ' . ($data->data_family_name ?? '')) }}"
    },
    family: [
        @if(isset($data) && $data->rePeople)
            @foreach($data->rePeople as $index => $member)
                {
                    id: "{{ $member->person_id ?? '' }}",
                    name: "{{ trim($member->first_name . ' ' . $member->second_name . ' ' . $member->third_name . ' ' . $member->last_name) }}"
                }@if(!$loop->last),@endif
            @endforeach
        @endif
    ],
    deceased: {
        father: {
            id: "{{ $data->deadPepole->father_id ?? '' }}",
            name: "{{ isset($data->deadPepole) ? trim($data->deadPepole->father_first_name . ' ' . $data->deadPepole->father_second_name . ' ' . $data->deadPepole->father_third_name . ' ' . $data->deadPepole->father_last_name) : '' }}"
        },
        mother: {
            id: "{{ $data->deadPepole->mother_id ?? '' }}",
            name: "{{ isset($data->deadPepole) ? trim($data->deadPepole->mother_first_name . ' ' . $data->deadPepole->mother_second_name . ' ' . $data->deadPepole->mother_third_name . ' ' . $data->deadPepole->mother_last_name) : '' }}"
        }
    }
};
</script>
    </form>
</div>
{{-- تمرير بيانات الأشخاص للـ JS --}}
<script>
window.personsList = {
    main: {
        id: "{{ $data->data_id_number ?? $data_id_number ?? '' }}",
        name: "{{ trim(($data->data_first_name ?? '') . ' ' . ($data->data_father_name ?? '') . ' ' . ($data->data_grand_father_name ?? '') . ' ' . ($data->data_family_name ?? '')) }}"
    },
    family: [
        @if(isset($data) && $data->rePeople)
            @foreach($data->rePeople as $index => $member)
                {
                    id: "{{ $member->person_id ?? '' }}",
                    name: "{{ trim($member->first_name . ' ' . $member->second_name . ' ' . $member->third_name . ' ' . $member->last_name) }}"
                }@if(!$loop->last),@endif
            @endforeach
        @endif
    ],
    deceased: {
        father: {
            id: "{{ $data->deadPepole->father_id ?? '' }}",
            name: "{{ isset($data->deadPepole) ? trim($data->deadPepole->father_first_name . ' ' . $data->deadPepole->father_second_name . ' ' . $data->deadPepole->father_third_name . ' ' . $data->deadPepole->father_last_name) : '' }}"
        },
        mother: {
            id: "{{ $data->deadPepole->mother_id ?? '' }}",
            name: "{{ isset($data->deadPepole) ? trim($data->deadPepole->mother_first_name . ' ' . $data->deadPepole->mother_second_name . ' ' . $data->deadPepole->mother_third_name . ' ' . $data->deadPepole->mother_last_name) : '' }}"
        }
    }
};
</script>


