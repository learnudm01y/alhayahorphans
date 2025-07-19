{{-- جدول مجلدات Excel --}}
<div class="table-responsive">
    <table id="kt_excel_manager_list" class="table align-middle table-row-dashed fs-6 gy-5 responsive-table" style="background: white;">
        <thead style="background-color: white !important; background-image: none !important; background: white !important;">
            <tr class="text-start fw-bold fs-6 border-bottom border-gray-200 bg-white text-dark" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important;">
                <th class="d-none d-md-table-cell w-10px pe-2 py-4 bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important;">
                    <div class="form-check form-check-sm form-check-custom form-check-solid me-3">
                        <input class="form-check-input" type="checkbox" data-kt-check="true" data-kt-check-target="#kt_excel_manager_list .form-check-input" value="1" />
                    </div>
                </th>
                <th class="py-4 bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 200px;">رقم السجل / المجلد</th>
                <th class="d-none d-sm-table-cell py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 80px;">ملفات Excel</th>
                <th class="d-none d-md-table-cell py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 90px;">الحجم</th>
                <th class="d-none d-lg-table-cell py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 100px;">آخر تحديث</th>
                <th class="py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 80px;">الإجراءات</th>
            </tr>
        </thead>
        <tbody class="fw-semibold text-gray-600">
            @forelse($folders as $folder)
            <tr data-folder="{{ $folder->folder_name }}" class="excel-folder-row responsive-row">
                <td class="d-none d-md-table-cell">
                    <div class="form-check form-check-sm form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" value="{{ $folder->folder_name }}" />
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="icon-wrapper me-3">
                            <i class="fas fa-folder fs-2x text-success"></i>
                        </span>
                        <div class="d-flex flex-column">
                            <a href="javascript:void(0)" class="text-gray-800 text-hover-primary fw-bold folder-link"
                               data-folder="{{ $folder->folder_name }}"
                               onclick="loadExcelFolderContents('{{ $folder->folder_name }}')">
                                <i class="fas fa-folder me-1 text-success"></i> رقم السجل: {{ $folder->folder_name }}
                            </a>

                            <!-- معلومات إضافية للجوال -->
                            <div class="d-block d-sm-none mt-2">
                                <div class="mobile-info-simple text-muted fs-7">
                                    <span class="d-block mb-1">
                                        <i class="fas fa-folder-open me-1 text-success"></i>
                                        {{ $folder->files_count }} ملف Excel
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-hdd me-1 text-info"></i>
                                        {{ $folder->formatted_size }}
                                    </span>
                                    <span class="d-block">
                                        <i class="fas fa-clock me-1 text-warning"></i>
                                        {{ $folder->formatted_date }}
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="badge badge-light-success rounded-pill">
                                    <i class="fas fa-file-excel fs-7 me-1"></i>
                                    Excel Files
                                </span>
                                @if(!empty($folder->file_types_array))
                                    @foreach(array_slice($folder->file_types_array, 0, 2) as $type)
                                        <span class="badge badge-light-info rounded-pill fs-8">{{ strtoupper($type) }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </td>
                <td class="d-none d-sm-table-cell text-center">
                    <span class="badge badge-success px-3 py-2">
                        <i class="fas fa-files-o fs-5 me-1"></i>
                        {{ number_format($folder->files_count) }}
                    </span>
                </td>
                <td class="d-none d-md-table-cell text-center">
                    <span class="text-success fw-bold">{{ $folder->formatted_size }}</span>
                </td>
                <td class="d-none d-lg-table-cell text-center">
                    <span class="text-muted">{{ $folder->formatted_date }}</span>
                </td>
                <td class="text-center">
                    <div class="d-flex justify-content-center gap-1">
                        {{-- زر عرض محتويات المجلد --}}
                        <button type="button"
                                class="btn btn-sm btn-outline-primary folder-view-btn shadow-sm px-2 py-1 d-flex align-items-center gap-1"
                                data-folder="{{ $folder->folder_name }}"
                                onclick="loadExcelFolderContents('{{ $folder->folder_name }}')"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="معاينة ملفات Excel في هذا المجلد">
                            <i class="fas fa-eye fs-5"></i>
                            <span class="d-none d-md-inline fw-bold">معاينة</span>
                            <span class="d-md-none fw-bold">فتح</span>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center py-10">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-file-excel fs-4x text-muted mb-4"></i>
                        <h5 class="text-muted mb-2">لا توجد ملفات Excel</h5>
                        <p class="text-muted fs-6">لم يتم العثور على أي ملفات Excel في النظام</p>
                        @if(isset($error))
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                {{ $error }}
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<style>
/* CSS متجاوب مخصص لجدول Excel */
@media (max-width: 767px) {
    .responsive-table {
        font-size: 0.9rem;
    }

    .responsive-table thead th {
        font-size: 0.8rem;
        padding: 0.5rem 0.25rem !important;
    }

    .responsive-table tbody td {
        padding: 0.75rem 0.5rem;
    }

    .icon-wrapper {
        display: none;
    }

    .folder-link {
        font-size: 0.9rem;
        text-decoration: none;
    }

    .folder-link:hover {
        color: #0d6efd !important;
    }

    .mobile-info-simple {
        margin-top: 0.5rem;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 3px solid #28a745;
    }

    .badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }

    .btn-sm {
        padding: 0.25rem 0.4rem;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    .folder-view-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .responsive-table {
        font-size: 0.8rem;
    }

    .responsive-table thead th {
        font-size: 0.75rem;
        padding: 0.4rem 0.2rem !important;
    }

    .responsive-table tbody td {
        padding: 0.6rem 0.3rem;
    }

    .folder-link {
        font-size: 0.85rem;
    }

    .mobile-info-simple {
        margin-top: 0.75rem;
        padding: 0.4rem;
    }
}

/* تحسينات للشاشات المتوسطة */
@media (min-width: 768px) and (max-width: 991px) {
    .responsive-table thead th {
        font-size: 0.85rem;
    }
}
</style>

{{-- Pagination للمجلدات --}}
@if(isset($folders) && method_exists($folders, 'hasPages') && $folders->hasPages())
    <div class="simple-pagination-wrapper">
        <div class="pagination-info text-center text-muted mb-3">
            عرض {{ $folders->firstItem() }} إلى {{ $folders->lastItem() }} من {{ $folders->total() }} مجلد
        </div>
        {{ $folders->appends(request()->query())->links('components.simple-pagination') }}
    </div>
@endif
