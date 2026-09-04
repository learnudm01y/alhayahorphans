<div class="d-flex justify-content-end gap-2">
    <button class="btn btn-icon btn-light-info btn-sm view-sponsorship"
            data-id="{{ $row->id }}"
            title="عرض التفاصيل">
        <i class="ki-duotone ki-eye fs-4">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
        </i>
    </button>

    <button class="btn btn-icon btn-light-primary btn-sm edit-sponsorship"
            data-id="{{ $row->id }}"
            title="تعديل">
        <i class="ki-duotone ki-pencil fs-4">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
    </button>

    <button class="btn btn-icon btn-light-warning btn-sm regenerate-file-number"
            data-id="{{ $row->id }}"
            data-current="{{ $row->internal_file_number ?: '-' }}"
            title="إعادة توليد رقم الملف الداخلي">
        <i class="ki-duotone ki-refresh fs-4">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
    </button>

    <button class="btn btn-icon btn-light-danger btn-sm delete-sponsorship"
            data-id="{{ $row->id }}"
            title="حذف">
        <i class="ki-duotone ki-trash fs-4">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
            <span class="path4"></span>
            <span class="path5"></span>
        </i>
    </button>
</div>
