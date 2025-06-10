@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12"> <!-- تم تكبير العرض من col-lg-10 إلى col-lg-12 -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient-primary text-black fw-bold fs-4 text-end rounded-top d-flex align-items-center justify-content-between flex-row-reverse">
                    <span><i class="fas fa-user-edit me-2"></i></span>
                    <span>تعديل بيانات المواطن</span>
                </div>
                <div class="card-body bg-light">
                    <form method="POST" action="{{ route('admin.persons.update', $person->ID) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-4">
                            {{-- رقم الهوية --}}
                            <div class="col-md-6">
                                <label class="form-label">رقم الهوية</label>
                                <input type="text" name="CI_ID_NUM" class="form-control" value="{{ $person->CI_ID_NUM }}">
                            </div>
                            {{-- الاسم الأول، اسم الأب، اسم الجد، اسم العائلة، اسم الأم --}}
                            <div class="col-12">
                                <div class="row g-4">
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">الاسم الأول</label>
                                        <input type="text" name="CI_FIRST_ARB" class="form-control" value="{{ $person->CI_FIRST_ARB }}">
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم الأب</label>
                                        <input type="text" name="CI_FATHER_ARB" class="form-control" value="{{ $person->CI_FATHER_ARB }}">
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم الجد</label>
                                        <input type="text" name="CI_GRAND_FATHER_ARB" class="form-control" value="{{ $person->CI_GRAND_FATHER_ARB }}">
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم العائلة</label>
                                        <input type="text" name="CI_FAMILY_ARB" class="form-control" value="{{ $person->CI_FAMILY_ARB }}">
                                    </div>
                                    <div class="col-md-2 flex-grow-1">
                                        <label class="form-label">اسم الأم</label>
                                        <input type="text" name="MOTHER_NAME1" class="form-control" value="{{ $person->MOTHER_NAME1 }}">
                                    </div>
                                </div>
                            </div>
                            {{-- بيانات الميلاد --}}
                            <div class="col-md-4">
                                <label class="form-label">تاريخ الميلاد</label>
                                <input type="date" name="CI_BIRTH_DT" class="form-control" value="{{ \Carbon\Carbon::parse($person->CI_BIRTH_DT)->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"> دولة الميلاد</label>
                                <select name="CI_BIRTH_CD" class="form-control">
                                    @foreach($CI_BIRTH_CD as $CI_BIRTH_CD_1)
                                        <option value="{{ $CI_BIRTH_CD_1->id }}"
                                            @if($person->CI_BIRTH_CD == $CI_BIRTH_CD_1->id) selected @endif>
                                            {{ $CI_BIRTH_CD_1->ci_birth_cd ?? $CI_BIRTH_CD_1->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"> منطقة الميلاد</label>
                                <select name="CI_BIRTH_TB_CD" class="form-control">
                                    @foreach($CI_BIRTH_TB_CD as $CI_BIRTH_TB_CD_1)
                                        <option value="{{ $CI_BIRTH_TB_CD_1->id }}"
                                            @if($person->CI_BIRTH_TB_CD == $CI_BIRTH_TB_CD_1->id) selected @endif>
                                            {{ $CI_BIRTH_TB_CD_1->CI_BIRTH_TB_CD ?? $CI_BIRTH_TB_CD_1->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- بيانات إضافية --}}
                            <div class="col-md-4">
                                <label class="form-label">الجنس</label>
                                <select name="CI_SEX_CD" class="form-control">
                                    <option value="1" @if($person->CI_SEX_CD == 1) selected @endif>ذكر</option>
                                    <option value="2" @if($person->CI_SEX_CD == 2) selected @endif>أنثى</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الحالة الإجتماعية</label>
                                <select name="CI_PERSONAL_CD" class="form-control">
                                    @foreach($socialStatuses as $status)
                                        <option value="{{ $status->id }}"
                                            @if($person->CI_PERSONAL_CD == $status->id) selected @endif>
                                            {{ $status->CI_PERSONAL_CD ?? $status->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">حالة الوفاة</label>
                                <select name="CI_DEAD_DT" class="form-control">
                                    <option value="0" @if(empty($person->CI_DEAD_DT)) selected @endif>غير متوفي</option>
                                    <option value="1" @if(!empty($person->CI_DEAD_DT)) selected @endif>متوفي</option>
                                </select>
                            </div>
                            {{-- العنوان --}}
                            <div class="col-md-4">
                                <label class="form-label">المدينة</label>
                                <select name="CITY" class="form-control">
                                    @foreach($city as $city_1)
                                        <option value="{{ $city_1->id }}"
                                            @if($person->CITY == $city_1->id) selected @endif>
                                            {{ $city_1->city ?? $city_1->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الشارع</label>
                                <input type="text" name="STREET" class="form-control" value="{{ $person->STREET ?? '' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">رقم المنزل</label>
                                <input type="text" name="HOUSE_NO" class="form-control" value="{{ $person->HOUSE_NO ?? '' }}">
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success px-4">حفظ التعديلات</button>
                            <a href="{{ route('admin.persons.index') }}" class="btn btn-secondary px-4">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
@if(session('success'))
<script>
    Swal.fire({
        icon: 'success',
        title: 'تم بنجاح',
        text: '{{ session("success") }}',
        confirmButtonText: 'حسنًا',
        timer: 3000
    });
</script>
@endif
@endpush
