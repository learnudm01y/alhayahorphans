<div class="d-flex flex-wrap gap-2">
@can('عرض تفاصيل الدور')
<a href="{{ route('users.show', $user->id) }}"
   class="btn btn-light-info btn-sm"
   data-bs-toggle="tooltip"
   data-bs-placement="top"
   title="عرض تفاصيل المستخدم">
    <i class="ki-duotone ki-user fs-2">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
    </i>
    <span class="ms-1">عرض</span>
</a>
@endcan
<button type="button"
        class="btn btn-light-primary btn-sm admin-activity-btn"
        data-admin-id="{{ $user->id }}"
        data-admin-name="{{ $user->name }}"
        data-bs-toggle="modal"
        data-bs-target="#adminActivityModal"
        title="سجل نشاط الموظف">
    <i class="bi bi-list-ul fs-2"></i>
    <span class="ms-1">سجل نشاط الموظف</span>
</button>
@can('تعديل مستخدم')
<a href="{{ route('users.edit', $user->id) }}"
   class="btn btn-light-warning btn-sm"
   data-bs-toggle="tooltip"
   data-bs-placement="top"
   title="تعديل بيانات المستخدم">
    <i class="ki-duotone ki-notepad-edit fs-2">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
    </i>
    <span class="ms-1">تعديل</span>
</a>
@endcan
@can('حذف مستخدم')
{!! Form::open(['method' => 'DELETE','route' => ['users.destroy', $user->id],'style'=>'display:inline', 'id' => 'delete-form-'.$user->id]) !!}
<button type="button"
        class="btn btn-light-danger btn-sm delete-btn"
        data-id="{{ $user->id }}"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        title="حذف المستخدم">
    <i class="ki-duotone ki-trash fs-2">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
    </i>
    <span class="ms-1">حذف</span>
</button>
{!! Form::close() !!}
@endcan
</div>
