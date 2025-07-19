<div class="table-responsive">
    <table id="kt_file_manager_list" class="table align-middle table-row-dashed fs-6 gy-5 responsive-table" style="background: white;">
        <thead style="background-color: white !important; background-image: none !important; background: white !important;">
            <tr class="text-start fw-bold fs-6 border-bottom border-gray-200 bg-white text-dark" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important;">
                <th class="d-none d-md-table-cell w-10px pe-2 py-4 bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important;">
                    <div class="form-check form-check-sm form-check-custom form-check-solid me-3">
                        <input class="form-check-input" type="checkbox" data-kt-check="true" data-kt-check-target="#kt_file_manager_list .form-check-input" value="1" />
                    </div>
                </th>
                <th class="py-4 bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 200px;">اسم المجلد</th>
                <th class="d-none d-lg-table-cell py-4 bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 180px;">اسم الشخص</th>
                <th class="d-none d-sm-table-cell py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 80px;">عدد الملفات</th>
                <th class="d-none d-md-table-cell py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 90px;">الحجم</th>
                <th class="d-none d-lg-table-cell py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 100px;">آخر تعديل</th>
                <th class="py-4 text-center bg-white" style="background-color: white !important; background-image: none !important; background: white !important; color: #333 !important; min-width: 80px;">الإجراءات</th>
            </tr>
        </thead>
        <tbody class="fw-semibold text-gray-600">
            @forelse($folders as $folder)
            <tr class="responsive-row">
                <td class="d-none d-md-table-cell">
                    <div class="form-check form-check-sm form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" value="{{ $folder->folder_name }}" />
                    </div>
                </td>
                <td data-order="{{ $folder->folder_name }}">
                    <div class="d-flex align-items-center">
                        <div class="file-type-icon file-type-{{ $folder->has_images ? 'image' : ($folder->has_documents ? 'pdf' : 'default') }} me-3">
                            <i class="fas fa-folder fs-2x text-white"></i>
                        </div>
                        <div class="d-flex flex-column">
                            <a href="javascript:void(0)" class="text-gray-800 text-hover-primary folder-link fw-bold"
                               data-folder="{{ $folder->folder_name }}">{{ $folder->folder_name }}</a>

                            <!-- معلومات إضافية للجوال -->
                            <div class="d-block d-lg-none">
                                <div class="mobile-info-simple text-muted fs-7">
                                    <span class="d-block mb-1">
                                        <i class="fas fa-user me-1 text-primary"></i>
                                        {{ $folder->person_name ?? 'غير مسجل' }}
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-folder-open me-1 text-success"></i>
                                        {{ $folder->files_count }} ملف
                                    </span>
                                    <span class="d-block">
                                        <i class="fas fa-hdd me-1 text-info"></i>
                                        {{ $folder->formatted_size }}
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-clock me-1 text-warning"></i>
                                        {{ $folder->formatted_date }}
                                    </span>
                                </div>
                            </div>

                            <!-- تفاصيل نوع الملفات -->
                            <div class="mt-1">
                                @if(isset($folder->file_types_array) && is_array($folder->file_types_array))
                                    @foreach($folder->file_types_array as $type)
                                        @php
                                            $badgeColor = match($type) {
                                                'image', 'photo' => 'success',
                                                'document', 'pdf' => 'info',
                                                'excel', 'spreadsheet' => 'warning',
                                                default => 'secondary'
                                            };
                                            $icon = match($type) {
                                                'image', 'photo' => 'fas fa-image',
                                                'document', 'pdf' => 'fas fa-file-pdf',
                                                'excel', 'spreadsheet' => 'fas fa-file-excel',
                                                default => 'fas fa-paperclip'
                                            };
                                        @endphp
                                        <span class="badge badge-light-{{ $badgeColor }} badge-sm me-1">
                                            <i class="{{ $icon }} me-1"></i> {{ $type }}
                                        </span>
                                        </span>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </td>
                <td class="d-none d-lg-table-cell">
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column">
                            <span class="text-gray-800 fw-bold fs-6">
                                {{ $folder->person_name ?? 'غير مسجل' }}
                            </span>
                            <span class="text-muted fs-7">
                                <i class="fas fa-clipboard me-1"></i> رقم الملف: {{ $folder->folder_name }}
                            </span>
                        </div>
                    </div>
                </td>
                <td class="d-none d-sm-table-cell text-center">
                    <span class="badge badge-light-primary">{{ $folder->files_count }}</span>
                </td>
                <td class="d-none d-md-table-cell text-center">
                    <strong class="text-info">{{ $folder->formatted_size }}</strong>
                </td>
                <td class="d-none d-lg-table-cell text-center">
                    <span class="text-muted">{{ $folder->formatted_date }}</span>
                </td>
                <td class="text-center">
                    <div class="d-flex justify-content-center">
                        <!--begin::View folder-->
                        <button type="button" class="btn btn-sm btn-icon btn-light btn-active-light-primary folder-view-btn"
                                data-folder="{{ $folder->folder_name }}" title="عرض المحتويات">
                            <i class="fas fa-eye fs-5"></i>
                            <span class="d-md-none fw-bold ms-1">فتح</span>
                        </button>
                        <!--end::View folder-->
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-10">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-folder fs-4x text-muted mb-4"></i>
                        <h5 class="text-muted mb-0">لا توجد مجلدات</h5>
                        <p class="text-muted">لم يتم العثور على أي مجلدات في النظام</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<style>
/* CSS متجاوب للجداول */
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

    .file-type-icon {
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
        border-left: 3px solid #fd7e14;
    }

    .badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
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

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
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

/* تحسينات للشاشات المتوسطة */
@media (min-width: 768px) and (max-width: 991px) {
    .responsive-table thead th {
        font-size: 0.85rem;
    }
}

/* تحسينات لشاشات الحاسوب */
@media (min-width: 992px) {
    .responsive-table {
        font-size: 1rem;
    }
}
</style>

@if(isset($folders) && method_exists($folders, 'hasPages') && $folders->hasPages())
<div class="simple-pagination-wrapper">
    <div class="pagination-info text-center text-muted mb-3">
        عرض {{ $folders->firstItem() }} إلى {{ $folders->lastItem() }} من {{ $folders->total() }} نتيجة
    </div>
    {{ $folders->links('components.simple-pagination') }}
</div>
@endif
