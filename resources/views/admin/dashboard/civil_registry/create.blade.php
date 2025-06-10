@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient-primary text-dark fw-bold fs-4 text-center rounded-top p-4">
                    <span>
                        <i class="bi bi-plus-circle me-2"></i>
                        إضافة مواطن جديد
                    </span>
                </div>
                <div class="card-body bg-light">
                    <form method="POST" action="{{ route('admin.persons.store') }}">
                        @csrf
                        <div class="row g-4">
                            {{-- رقم الهوية --}}
                            <div class="col-md-6">
                                <label class="form-label">رقم الهوية</label>
                                <input type="text" name="CI_ID_NUM" class="form-control" required>
                            </div>

                            {{-- الاسم الأول، اسم الأب، اسم الجد، اسم العائلة، اسم الأم --}}
                            <div class="col-12">
                                <div class="row g-4">
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">الاسم الأول</label>
                                        <input type="text" name="CI_FIRST_ARB" class="form-control" required>
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم الأب</label>
                                        <input type="text" name="CI_FATHER_ARB" class="form-control" required>
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم الجد</label>
                                        <input type="text" name="CI_GRAND_FATHER_ARB" class="form-control" required>
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم العائلة</label>
                                        <input type="text" name="CI_FAMILY_ARB" class="form-control" required>
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم الأم</label>
                                        <input type="text" name="MOTHER_NAME1" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">تاريخ الميلاد</label>
                                <input type="date" name="CI_BIRTH_DT" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">دولة الميلاد</label>
                                <select name="CI_BIRTH_CD" class="form-control" required>
                                    @foreach($CI_BIRTH_CD as $birth)
                                        <option value="{{ $birth->id }}">{{ $birth->ci_birth_cd }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">منطقة الميلاد</label>
                                <select name="CI_BIRTH_TB_CD" class="form-control" required>
                                    @foreach($CI_BIRTH_TB_CD as $birth_tb)
                                        <option value="{{ $birth_tb->id }}">{{ $birth_tb->CI_BIRTH_TB_CD }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الجنس</label>
                                <select name="CI_SEX_CD" class="form-control" required>
                                    <option value="1">ذكر</option>
                                    <option value="2">أنثى</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الحالة الاجتماعية</label>
                                <select name="CI_PERSONAL_CD" class="form-control" required>
                                    @foreach($socialStatuses as $status)
                                        <option value="{{ $status->id }}">{{ $status->CI_PERSONAL_CD }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">حالة الوفاة</label>
                                <select name="CI_DEAD_DT" class="form-control">
                                    <option value="0">غير متوفي</option>
                                    <option value="1">متوفي</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">المدينة</label>
                                <select name="CITY" class="form-control" required>
                                    @foreach($city as $c)
                                        <option value="{{ $c->id }}">{{ $c->city }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الشارع</label>
                                <input type="text" name="STREET" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">رقم المنزل</label>
                                <input type="text" name="HOUSE_NO" class="form-control">
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success px-4">إضافة</button>
                            <a href="{{ route('admin.persons.index') }}" class="btn btn-secondary px-4">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
