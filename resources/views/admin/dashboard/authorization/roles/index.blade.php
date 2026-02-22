@extends('admin.dashboard.toolbars.index')
@section('content')
<div class="container-fluid">
    <style>
        .btn-metronic {
            padding: 0.65rem 1rem;
            border-radius: 0.475rem;
            font-weight: 500;
            margin: 0 3px;
            transition: all 0.2s ease;
        }
        .btn-metronic:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-metronic i {
            margin-right: 5px;
        }
        .roles-pagination .pagination {
            gap: 6px;
            margin-bottom: 0;
        }
        .roles-pagination .page-item .page-link {
            border-radius: 10px;
            border: 1px solid #d8dee9;
            color: #1f2937;
            min-width: 42px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            background: #fff;
            transition: all 0.2s ease;
        }
        .roles-pagination .page-item .page-link:hover {
            background: #eef2ff;
            border-color: #4f46e5;
            color: #312e81;
        }
        .roles-pagination .page-item.active .page-link {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
        }
        .roles-pagination .page-item.disabled .page-link {
            opacity: 0.45;
            cursor: not-allowed;
            background: #f8fafc;
        }
        .roles-pagination-list {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
            padding: 0;
            margin: 0;
            direction: rtl;
        }
        .roles-pagination-list .page-btn {
            border: 1px solid #d8dee9;
            border-radius: 10px;
            min-width: 42px;
            height: 40px;
            padding: 0 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
            background: #fff;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .roles-pagination-list .page-btn:hover {
            background: #eef2ff;
            border-color: #4f46e5;
            color: #312e81;
            text-decoration: none;
        }
        .roles-pagination-list .page-btn.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
        }
        .roles-pagination-list .page-btn.disabled {
            opacity: 0.45;
            pointer-events: none;
            background: #f8fafc;
        }
        .roles-pagination-meta {
            margin-top: 10px;
            color: #64748b;
            font-weight: 500;
        }
    </style>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h2 class="m-0 font-weight-bold text-primary">إدارة الأدوار</h2>
            @can('إنشاء صلاحيات المستخدمين')
            <a class="btn btn-success" href="{{ route('roles.create') }}">
                <i class="fas fa-plus-circle"></i> إضافة دور جديد
            </a>
            @endcan
        </div>
        <div class="card-body">
            @if ($message = Session::get('success'))
                @push('scriptsCode')
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'نجاح',
                        text: "{{ $message }}",
                        confirmButtonText: 'حسناً'
                    });
                </script>
                @endpush
            @endif

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>الرقم</th>
                            <th>الاسم</th>
                            <th width="280px">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $key => $role)
                            <tr>
                                <td>{{ ++$i }}</td>
                                <td>{{ $role->name }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        @can('عرض تفاصيل الدور')
                                        <a class="btn btn-metronic btn-light-primary" href="{{ route('roles.show', $role->id) }}">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                        @endcan
                                        @can('تعديل صلاحية مستخدم')
                                        <a class="btn btn-metronic btn-light-info" href="{{ route('roles.edit', $role->id) }}">
                                            <i class="fas fa-edit"></i> تعديل
                                        </a>
                                        @endcan
                                        @can('حذف صلاحية مستخدم')
                                        {!! Form::open(['method' => 'DELETE', 'route' => ['roles.destroy', $role->id], 'style' => 'display:inline', 'id' => 'delete-form-'.$role->id]) !!}
                                        <button type="button" class="btn btn-metronic btn-light-danger delete-btn" data-id="{{ $role->id }}">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                        {!! Form::close() !!}
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-column align-items-center roles-pagination mt-3">
                @if ($roles->hasPages())
                    <ul class="roles-pagination-list">
                        <li>
                            @if ($roles->onFirstPage())
                                <span class="page-btn disabled">السابق</span>
                            @else
                                <a class="page-btn" href="{{ $roles->previousPageUrl() }}">السابق</a>
                            @endif
                        </li>

                        @for ($page = 1; $page <= $roles->lastPage(); $page++)
                            <li>
                                @if ($page == $roles->currentPage())
                                    <span class="page-btn active">{{ $page }}</span>
                                @else
                                    <a class="page-btn" href="{{ $roles->url($page) }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endfor

                        <li>
                            @if ($roles->hasMorePages())
                                <a class="page-btn" href="{{ $roles->nextPageUrl() }}">التالي</a>
                            @else
                                <span class="page-btn disabled">التالي</span>
                            @endif
                        </li>
                    </ul>
                    <div class="roles-pagination-meta">
                        عرض {{ $roles->firstItem() ?? 0 }} إلى {{ $roles->lastItem() ?? 0 }} من {{ $roles->total() }} نتيجة
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scriptsCode')
<script>
    $(document).ready(function() {
        $('.delete-btn').on('click', function() {
            var roleId = $(this).data('id');
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: "لا يمكن التراجع عن عملية حذف الدور!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف!',
                cancelButtonText: 'إلغاء',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#delete-form-' + roleId).submit();
                }
            });
        });
    });
</script>
@endpush
@endsection
