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
    </style>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h2 class="m-0 font-weight-bold text-primary">إدارة الأدوار</h2>
            <a class="btn btn-success" href="{{ route('roles.create') }}">
                <i class="fas fa-plus-circle"></i> إضافة دور جديد
            </a>
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
                                        <a class="btn btn-metronic btn-light-primary" href="{{ route('roles.show', $role->id) }}">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                        <a class="btn btn-metronic btn-light-info" href="{{ route('roles.edit', $role->id) }}">
                                            <i class="fas fa-edit"></i> تعديل
                                        </a>
                                        {!! Form::open(['method' => 'DELETE', 'route' => ['roles.destroy', $role->id], 'style' => 'display:inline', 'id' => 'delete-form-'.$role->id]) !!}
                                        <button type="button" class="btn btn-metronic btn-light-danger delete-btn" data-id="{{ $role->id }}">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                        {!! Form::close() !!}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {!! $roles->render() !!}
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
