@extends('admin.dashboard.toolbars.index')
@section('content')
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h2 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-eye"></i> عرض تفاصيل الدور
            </h2>
            <a class="btn btn-primary" href="{{ route('roles.index') }}">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-4">
                    <div class="card border-right border-primary">
                        <div class="card-body">
                            <h5 class="card-title font-weight-bold">
                                <i class="fas fa-tag"></i> اسم الدور
                            </h5>
                            <p class="card-text h4 text-primary">{{ $role->name }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card border-right border-success">
                        <div class="card-body">
                            <h5 class="card-title font-weight-bold mb-3">
                                <i class="fas fa-lock"></i> الصلاحيات
                            </h5>
                            <div class="permissions-container">
                                @if (!empty($rolePermissions))
                                    @foreach ($rolePermissions as $v)
                                        <span class="badge badge-success m-1 p-2">
                                            <i class="fas fa-check-circle"></i>
                                            {{ $v->name }}
                                        </span>
                                    @endforeach
                                @else
                                    <p class="text-muted">لا توجد صلاحيات مضافة</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
