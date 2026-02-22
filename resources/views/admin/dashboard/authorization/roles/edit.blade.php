@extends('admin.dashboard.toolbars.index')
@section('content')
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h2 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-edit"></i> تعديل الدور
            </h2>
            <a class="btn btn-primary" href="{{ route('roles.index') }}">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>

        <div class="card-body">
            @if ($message = Session::get('success'))
            @push('scriptsCode')
              <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح',
                        text: "{{ $message }}",
                        confirmButtonText: 'حسناً',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                </script>
            @endpush
            @endif

            @if (count($errors) > 0)
            @push('scriptsCode')
              <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الإدخال',
                        html: '<ul class="text-start">' +
                              '@foreach ($errors->all() as $error)' +
                              '<li>{{ $error }}</li>' +
                              '@endforeach' +
                              '</ul>',
                        confirmButtonText: 'حسناً',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                </script>
            @endpush
            @endif

            {!! Form::model($role, ['method' => 'PATCH', 'route' => ['roles.update', $role->id]]) !!}
            @php
                $permissionsByName = $permission->keyBy('name');
                $permissionsMindMap = [
                    [
                        'title' => 'إدارة التسجيلات',
                        'section' => ['عرض قسم إدارة التسجيلات'],
                        'links' => ['عرض البيانات المدخلة', 'إدارة طلبات المستخدمين', 'إدخال بيانات التسجيلات'],
                        'actions' => ['إنشاء سجل جديد', 'تعديل سجل', 'حذف سجل', 'تصدير السجلات', 'رفع مرفقات السجل'],
                    ],
                    [
                        'title' => 'إدارة التصنيفات',
                        'section' => ['عرض قسم إدارة التصنيفات'],
                        'links' => [
                            'إدارة الدرجة العلمية', 'إدارة صلة القرابة', 'إدارة حالة المساعدة', 'إدارة أسماء البنوك',
                            'إدارة أسماء المدن', 'إدارة العملات', 'إدارة أسباب الوفاة', 'إدارة حالة النزوح',
                            'إدارة أنواع الوثائق', 'إدارة الحالة الوظيفية', 'إدارة الأقسام الرئيسية', 'إدارة الحالة الصحية',
                            'إدارة احتياجات المكفول', 'إدارة جوانب الإبداع', 'إدارة حالة المنزل', 'إدارة الحالة الاجتماعية',
                            'إدارة المحافظات', 'إدارة حالة الطلب', 'إدارة حالة الكفالة', 'إدارة نوع السكن', 'إدارة نوع الكفالة'
                        ],
                        'actions' => ['إضافة تصنيف', 'تعديل تصنيف', 'حذف تصنيف', 'تفعيل/تعطيل تصنيف'],
                    ],
                    [
                        'title' => 'إدارة الجمعيات',
                        'section' => ['عرض قسم إدارة الجمعيات'],
                        'links' => ['إدارة الجمعيات', 'تحديث بيانات الجمعيات'],
                        'actions' => ['إضافة جمعية', 'تعديل جمعية', 'حذف جمعية', 'إعداد حقول الجمعية', 'تصدير نماذج الجمعيات'],
                    ],
                    [
                        'title' => 'قسم الكفالات',
                        'section' => ['عرض قسم الكفالات'],
                        'links' => ['عرض المكفولين', 'عرض غير المكفولين'],
                        'actions' => ['إضافة كفالة', 'تعديل كفالة', 'حذف كفالة', 'استيراد الكفالات', 'تصدير الكفالات'],
                    ],
                    [
                        'title' => 'إدارة الصلاحيات',
                        'section' => ['عرض قسم إدارة الصلاحيات'],
                        'links' => ['المستخدمين', 'إنشاء مستخدم', 'صلاحيات المستخدمين', 'إنشاء صلاحيات المستخدمين'],
                        'actions' => ['تعديل مستخدم', 'حذف مستخدم', 'تعديل صلاحية مستخدم', 'حذف صلاحية مستخدم', 'عرض تفاصيل الدور'],
                    ],
                    [
                        'title' => 'إدارة السجل المدني',
                        'section' => ['عرض قسم إدارة السجل المدني'],
                        'links' => ['السجل المدني الجديد', 'إضافة مواطن', 'عرض السجل المدني القديم'],
                        'actions' => ['تعديل مواطن', 'حذف مواطن', 'بحث السجل المدني', 'عرض إحصائيات السجل المدني'],
                    ],
                    [
                        'title' => 'إدارة الملفات',
                        'section' => ['عرض قسم إدارة الملفات'],
                        'links' => ['الوصول لبوابة الملفات', 'إدارة المجلدات', 'رفع ملفات اكسل', 'إدارة الملفات المكررة'],
                        'actions' => ['تحميل ملفات', 'تنزيل ملفات', 'تنزيل ملف مضغوط', 'حذف ملف', 'حذف الملفات المكررة'],
                    ],
                    [
                        'title' => 'أدوات النظام',
                        'section' => ['عرض قسم أدوات النظام'],
                        'links' => ['اختبار سرعة الإنترنت', 'OpenSpeedTest'],
                        'actions' => ['تشغيل اختبار السرعة', 'عرض إحصائيات السرعة', 'تنزيل نتائج الاختبار'],
                    ],
                ];

                $coveredPermissionNames = [];
                foreach ($permissionsMindMap as $mapItem) {
                    $coveredPermissionNames = array_merge($coveredPermissionNames, $mapItem['section'], $mapItem['links'], $mapItem['actions']);
                }

                $coveredPermissionNames = array_unique($coveredPermissionNames);
                $otherPermissions = $permission->filter(function ($perm) use ($coveredPermissionNames) {
                    return !in_array($perm->name, $coveredPermissionNames);
                });
            @endphp
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="font-weight-bold">
                            <i class="fas fa-tag"></i> اسم الدور:
                        </label>
                        {!! Form::text('name', null, ['placeholder' => 'أدخل اسم الدور', 'class' => 'form-control']) !!}
                    </div>
                </div>

                <div class="col-md-12 mt-3">
                    <label class="font-weight-bold">
                        <i class="fas fa-lock"></i> الصلاحيات:
                    </label>
                    <div class="mb-3 d-flex justify-content-end">
                        <button type="button" id="toggleAllPermissionsBtnEdit" class="btn btn-dark btn-sm">
                            <i class="fas fa-check-double"></i> منح جميع الصلاحيات
                        </button>
                    </div>
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-light d-flex align-items-center" style="min-height: 64px;">
                            <h5 class="m-0 font-weight-bold text-dark" style="font-size: 1.2rem;">
                                <i class="fas fa-project-diagram"></i>إدارة الصلاحيات
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <style>
                                .mindmap-wrapper {
                                    overflow-x: auto;
                                    padding: 20px 20px;
                                    background: #f8f9fc;
                                    direction: rtl;
                                }
                                .mindmap {
                                    display: inline-flex;
                                }
                                .mindmap ul {
                                    display: flex;
                                    flex-direction: column;
                                    justify-content: center;
                                    list-style: none;
                                    padding: 0;
                                    margin: 0;
                                    padding-right: 30px;
                                    position: relative;
                                }
                                .mindmap > ul {
                                    padding-right: 0;
                                }
                                .mindmap li {
                                    display: flex;
                                    align-items: center;
                                    position: relative;
                                    padding: 5px 0;
                                }
                                /* Horizontal line from parent to this ul */
                                .mindmap ul::before {
                                    content: '';
                                    position: absolute;
                                    right: 0;
                                    top: 50%;
                                    width: 30px;
                                    border-top: 2px solid #b7b9cc;
                                }
                                .mindmap > ul::before {
                                    display: none;
                                }
                                /* Vertical line connecting children */
                                .mindmap li::before {
                                    content: '';
                                    position: absolute;
                                    right: -30px;
                                    top: 0;
                                    bottom: 0;
                                    border-right: 2px solid #b7b9cc;
                                }
                                .mindmap li:first-child::before {
                                    top: 50%;
                                }
                                .mindmap li:last-child::before {
                                    bottom: 50%;
                                }
                                .mindmap li:only-child::before {
                                    display: none;
                                }
                                /* Horizontal line from vertical line to child */
                                .mindmap li::after {
                                    content: '';
                                    position: absolute;
                                    right: -30px;
                                    top: 50%;
                                    width: 30px;
                                    border-top: 2px solid #b7b9cc;
                                }
                                .mindmap > ul > li::before,
                                .mindmap > ul > li::after {
                                    display: none;
                                }

                                .mindmap .node {
                                    padding: 7px 16px;
                                    border-radius: 30px;
                                    background: #fff;
                                    border: 2px solid #4e73df;
                                    color: #4e73df;
                                    font-weight: bold;
                                    white-space: nowrap;
                                    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                                    z-index: 2;
                                    position: relative;
                                }
                                .mindmap .node.root {
                                    background: #4e73df;
                                    color: #fff;
                                    font-size: 1.1rem;
                                    padding: 10px 22px;
                                }
                                .mindmap .node.root i {
                                    color: #fff;
                                }
                                .mindmap .node.section {
                                    background: #1cc88a;
                                    border-color: #1cc88a;
                                    color: #fff;
                                }
                                .mindmap .node.section i {
                                    color: #fff;
                                }
                                .mindmap .node.group {
                                    background: #f6c23e;
                                    border-color: #f6c23e;
                                    color: #fff;
                                }
                                .mindmap .node.group i {
                                    color: #fff;
                                }
                                .mindmap .node.leaf {
                                    background: #fff;
                                    border-color: #e3e6f0;
                                    color: #5a5c69;
                                    font-weight: normal;
                                    border-radius: 8px;
                                    padding: 5px 12px;
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                }
                                .mindmap .node.leaf:hover {
                                    border-color: #4e73df;
                                    background: #eaecf4;
                                }
                                .mindmap .custom-control {
                                    margin: 0;
                                    padding-right: 1.5rem;
                                }
                            </style>
                            <div class="mindmap-wrapper">
                                <div class="mindmap">
                                    <ul>
                                        <li>
                                            <div class="node root"><i class="fas fa-shield-alt"></i> الصلاحيات</div>
                                            <ul>
                                                @foreach ($permissionsMindMap as $sectionIndex => $section)
                                                    @php
                                                        $hasAnyValidPerms = false;
                                                        foreach (['section', 'links', 'actions'] as $groupKey) {
                                                            foreach ($section[$groupKey] as $permName) {
                                                                if ($permissionsByName->has($permName)) {
                                                                    $hasAnyValidPerms = true;
                                                                    break 2;
                                                                }
                                                            }
                                                        }
                                                    @endphp
                                                    @if($hasAnyValidPerms)
                                                    <li>
                                                        <div class="node section"><i class="fas fa-layer-group"></i> {{ $section['title'] }}</div>
                                                        <ul>
                                                            @foreach (['section' => 'عنوان القسم', 'links' => 'الروابط الفرعية', 'actions' => 'الأزرار والعمليات'] as $groupKey => $groupTitle)
                                                                @php
                                                                    $validPerms = [];
                                                                    foreach ($section[$groupKey] as $permName) {
                                                                        if ($permissionsByName->has($permName)) {
                                                                            $validPerms[] = $permissionsByName->get($permName);
                                                                        }
                                                                    }
                                                                @endphp
                                                                @if(count($validPerms) > 0)
                                                                <li>
                                                                    <div class="node group">{{ $groupTitle }}</div>
                                                                    <ul>
                                                                        @foreach ($validPerms as $perm)
                                                                            @php
                                                                                $checkboxId = 'permission_' . $sectionIndex . '_' . $groupKey . '_' . $perm->id;
                                                                            @endphp
                                                                            <li>
                                                                                <label class="node leaf mb-0" for="{{ $checkboxId }}">
                                                                                    <div class="custom-control custom-checkbox">
                                                                                        {{ Form::checkbox('permission[]', $perm->id, in_array($perm->id, $rolePermissions) ? true : false, ['class' => 'custom-control-input', 'id' => $checkboxId]) }}
                                                                                        <span class="custom-control-label"></span>
                                                                                    </div>
                                                                                    {{ $perm->name }}
                                                                                </label>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </li>
                                                                @endif
                                                            @endforeach
                                                        </ul>
                                                    </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-light d-flex align-items-center" style="min-height: 58px;">
                            <h5 class="m-0 font-weight-bold text-dark" style="font-size: 1.1rem;">
                                <i class="fas fa-list"></i> بقية الصلاحيات التفصيلية
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($otherPermissions as $value)
                                    <div class="col-md-3 mb-2">
                                        <div class="custom-control custom-checkbox">
                                            {{ Form::checkbox('permission[]', $value->id, in_array($value->id, $rolePermissions) ? true : false, [
                                                'class' => 'custom-control-input',
                                                'id' => 'permission'.$value->id
                                            ]) }}
                                            <label class="custom-control-label" for="permission{{$value->id}}">
                                                {{ $value->name }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 text-center mt-4">
                    <button type="submit" class="btn btn-success px-5">
                        <i class="fas fa-save"></i> حفظ التغييرات
                    </button>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleAllPermissionsBtn = document.getElementById('toggleAllPermissionsBtnEdit');
        if (!toggleAllPermissionsBtn) {
            return;
        }

        const permissionCheckboxes = () => Array.from(document.querySelectorAll('input[name="permission[]"]'));

        const refreshButtonState = () => {
            const allChecked = permissionCheckboxes().length > 0 && permissionCheckboxes().every(checkbox => checkbox.checked);
            toggleAllPermissionsBtn.classList.toggle('btn-dark', !allChecked);
            toggleAllPermissionsBtn.classList.toggle('btn-danger', allChecked);
            toggleAllPermissionsBtn.innerHTML = allChecked
                ? '<i class="fas fa-times"></i> إلغاء جميع الصلاحيات'
                : '<i class="fas fa-check-double"></i> منح جميع الصلاحيات';
        };

        toggleAllPermissionsBtn.addEventListener('click', function () {
            const allChecked = permissionCheckboxes().length > 0 && permissionCheckboxes().every(checkbox => checkbox.checked);
            permissionCheckboxes().forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            refreshButtonState();
        });

        permissionCheckboxes().forEach(checkbox => {
            checkbox.addEventListener('change', refreshButtonState);
        });

        refreshButtonState();
    });
</script>
@endpush
