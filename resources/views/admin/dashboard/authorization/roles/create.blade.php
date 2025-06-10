@extends('admin.dashboard.toolbars.index')
@section('content')
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h2 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-user-shield"></i> إضافة دور جديد
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

            {!! Form::open(['route' => 'roles.store', 'method' => 'POST']) !!}
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
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                @foreach ($permission as $value)
                                    <div class="col-md-3 mb-2">
                                        <div class="custom-control custom-checkbox">
                                            {{ Form::checkbox('permission[]', $value->id, false, ['class' => 'custom-control-input', 'id' => 'permission'.$value->id]) }}
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
                        <i class="fas fa-save"></i> حفظ
                    </button>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
@endsection
