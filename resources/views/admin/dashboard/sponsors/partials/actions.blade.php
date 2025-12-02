<div class="d-flex justify-content-center gap-1">
    <button class="btn btn-light-info btn-sm view-sponsor"
            data-id="{{ $row->id }}"
            data-bs-toggle="tooltip"
            title="عرض التفاصيل">
        <i class="ki-duotone ki-eye fs-2">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
        </i>
        <span class="d-none d-md-inline ms-1">عرض</span>
    </button>

    <button class="btn btn-light-warning btn-sm edit-sponsor"
            data-id="{{ $row->id }}"
            data-bs-toggle="tooltip"
            title="تعديل">
        <i class="ki-duotone ki-notepad-edit fs-2">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
        <span class="d-none d-md-inline ms-1">تعديل</span>
    </button>

    <button class="btn btn-light-danger btn-sm delete-sponsor"
            data-id="{{ $row->id }}"
            data-bs-toggle="tooltip"
            title="حذف">
        <i class="ki-duotone ki-trash fs-2">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
        </i>
        <span class="d-none d-md-inline ms-1">حذف</span>
    </button>
</div>
