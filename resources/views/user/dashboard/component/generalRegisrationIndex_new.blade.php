@extends('user.dashboard.toolbars.index')

@section('contentUser')
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700&display=swap" rel="stylesheet">
<style>
    .profile-img { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 2px solid #eee; background: #fafafa; }
    .card-custom { border-radius: 18px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 1.5rem; background: #fff; }
    .info-label { color: #888; font-size: 1rem; margin-bottom: 2px; }
    .info-value { font-size: 1.1rem; font-weight: bold; color: #222; margin-bottom: 8px; }
    .field-group-title { font-size: 1.3rem; font-weight: bold; color: #1e40af; margin: 1.5rem 0 1rem 0; padding-bottom: 0.5rem; border-bottom: 2px solid #3b82f6; }
    .form-control, .form-select { border-radius: 8px; border: 1px solid #ddd; }
    .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25); }
</style>

<div class="container">
    <h2 class="mb-4 text-center" style="font-family: 'Cairo', Arial, Tahoma, sans-serif;">تحديث بيانات الكفالة</h2>

    @if(isset($sponsorship) && $sponsorship)
        <form method="POST" action="{{ route('user.update-sponsorship-data') }}" id="sponsorshipUpdateForm">
            @csrf
            <input type="hidden" name="sponsorship_id" value="{{ $sponsorship->id }}">

            {{-- معلومات أساسية عن الكفالة --}}
            <div class="card card-custom p-3 mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="background: rgba(59,130,246,0.1); border-radius: 16px; padding: 14px 24px; font-weight: bold; font-size: 1.3rem; font-family: 'Cairo', Arial, Tahoma, sans-serif;">
                    <div class="d-flex align-items-center flex-wrap" style="gap: 16px;">
                        <span>رقم الملف: {{ $sponsorship->internal_file_number }}</span>
                        <span style="border-right: 2px solid #bbb; height: 22px; margin: 0 12px;"></span>
                        <span>الجمعية: {{ optional($sponsorship->sponsor)->sponsor_name }}</span>
                    </div>
                </div>
            </div>

            {{-- معلومات المكفول الأساسية - يتم إخفاؤها عندما يكون الشخص معيل --}}
            @if(isset($enabledFields) && count(array_filter($enabledFields, fn($f) => $f['category_id'] == 1)) > 0 && $sponsorship->person_type !== 'breadwinner')
            <div class="card card-custom p-4 mb-4">
                <h4 class="field-group-title">معلومات المكفول الأساسية</h4>
                <div class="row">
                    @foreach($enabledFields as $fieldKey => $fieldInfo)
                        @if($fieldInfo['category_id'] == 1)
                            <div class="col-md-6 mb-3">
                                <label class="form-label info-label">
                                    {{ $fieldInfo['display_name'] }}
                                    @if($fieldInfo['required']) <span class="text-danger">*</span> @endif
                                </label>

                                @php
                                    $fieldValue = '';
                                    switch($fieldKey) {
                                        case 'field_sponsor_name':
                                            $fieldValue = $sponsorship->orphan_name;
                                            break;
                                        case 'field_phone':
                                            $fieldValue = $sponsorship->relationData->data_phone_number ?? '';
                                            break;
                                        case 'field_mother_name':
                                            $fieldValue = $sponsorship->relationData->deadPepole->mother_first_name ?? '';
                                            $fieldValue .= ' ' . ($sponsorship->relationData->deadPepole->mother_last_name ?? '');
                                            break;
                                        case 'field_father_death_date':
                                            // يحتاج إلى تاريخ وفاة الأب من جدول آخر
                                            $fieldValue = '';
                                            break;
                                    }
                                @endphp

                                <input type="text"
                                       class="form-control"
                                       name="{{ $fieldKey }}"
                                       value="{{ old($fieldKey, $fieldValue) }}"
                                       {{ $fieldInfo['required'] ? 'required' : '' }}>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- معلومات السكن --}}
            @if(isset($enabledFields) && count(array_filter($enabledFields, fn($f) => $f['category_id'] == 2)) > 0)
            <div class="card card-custom p-4 mb-4">
                <h4 class="field-group-title">معلومات السكن</h4>
                {{-- ملاحظة: معلومات السكن تظهر للجميع --}}
                <div class="row">
                    @foreach($enabledFields as $fieldKey => $fieldInfo)
                        @if($fieldInfo['category_id'] == 2)
                            <div class="col-md-6 mb-3">
                                <label class="form-label info-label">
                                    {{ $fieldInfo['display_name'] }}
                                    @if($fieldInfo['required']) <span class="text-danger">*</span> @endif
                                </label>

                                @php
                                    $fieldValue = '';
                                    switch($fieldKey) {
                                        case 'field_housing_status':
                                            $fieldValue = optional($sponsorship->relationData->housingStatus)->description ?? '';
                                            break;
                                        case 'field_housing_type':
                                            $fieldValue = optional($sponsorship->relationData->currentHousingType)->description ?? '';
                                            break;
                                        case 'field_housing_address':
                                            $fieldValue = optional($sponsorship->relationData->city)->city ?? '';
                                            break;
                                        case 'field_housing_address_detail':
                                            $fieldValue = $sponsorship->relationData->data_description_needs ?? '';
                                            break;
                                    }
                                @endphp

                                @if(in_array($fieldKey, ['field_housing_status', 'field_housing_type']))
                                    <input type="text"
                                           class="form-control"
                                           name="{{ $fieldKey }}"
                                           value="{{ old($fieldKey, $fieldValue) }}"
                                           {{ $fieldInfo['required'] ? 'required' : '' }}>
                                @else
                                    <textarea class="form-control"
                                              name="{{ $fieldKey }}"
                                              rows="2"
                                              {{ $fieldInfo['required'] ? 'required' : '' }}>{{ old($fieldKey, $fieldValue) }}</textarea>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- معلومات المعيل --}}
            @if(isset($enabledFields) && count(array_filter($enabledFields, fn($f) => $f['category_id'] == 8)) > 0)
            <div class="card card-custom p-4 mb-4">
                <h4 class="field-group-title">معلومات المعيل</h4>
                <div class="row">
                    @foreach($enabledFields as $fieldKey => $fieldInfo)
                        @if($fieldInfo['category_id'] == 8)
                            <div class="col-md-6 mb-3">
                                <label class="form-label info-label">
                                    {{ $fieldInfo['display_name'] }}
                                    @if($fieldInfo['required']) <span class="text-danger">*</span> @endif
                                </label>

                                @php
                                    $fieldValue = '';
                                    switch($fieldKey) {
                                        case 'field_current_guardian':
                                            $fieldValue = $sponsorship->guardian_name;
                                            break;
                                        case 'field_guardian_relationship':
                                            $fieldValue = optional($sponsorship->relationData->categoryOfRelation)->attribute ?? '';
                                            break;
                                        case 'field_guardian_health':
                                            $fieldValue = optional($sponsorship->relationData->healthStatus)->description ?? '';
                                            break;
                                        case 'field_guardian_job':
                                            $fieldValue = optional($sponsorship->relationData->employmentStatusBreadwinner)->description ?? '';
                                            break;
                                    }
                                @endphp

                                <input type="text"
                                       class="form-control"
                                       name="{{ $fieldKey }}"
                                       value="{{ old($fieldKey, $fieldValue) }}"
                                       {{ $fieldInfo['required'] ? 'required' : '' }}>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- أفراد الأسرة --}}
            @if(isset($sponsorship->relationData->rePeople) && count($sponsorship->relationData->rePeople) > 0)
            <div class="card card-custom p-4 mb-4">
                <h4 class="field-group-title">أفراد الأسرة</h4>
                <div class="row">
                    @foreach($sponsorship->relationData->rePeople as $member)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100 shadow-sm border">
                                <div class="card-body">
                                    <div><strong>الاسم:</strong> {{ $member->person_name }}</div>
                                    <div><strong>صلة القرابة:</strong> {{ $member->person_relationship }}</div>
                                    <div><strong>تاريخ الميلاد:</strong> {{ $member->person_birth_date }}</div>
                                    <div><strong>الحالة الصحية:</strong> {{ $member->person_health_status }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- الحساب البنكي المعتمد --}}
            @if(isset($approvedBankAccount) && $approvedBankAccount)
            <div class="card card-custom p-4 mb-4">
                <h4 class="field-group-title">المعلومات البنكية المعتمدة</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label info-label">اسم البنك</label>
                        <input type="text" class="form-control" readonly
                               value="@php
                                   $bankName = $approvedBankAccount->bank_name;
                                   if(is_numeric($bankName)) {
                                       $bankModel = \App\Models\BankName::find($bankName);
                                       echo $bankModel ? $bankModel->description : $approvedBankAccount->bank_name;
                                   } else {
                                       echo $bankName;
                                   }
                               @endphp">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label info-label">اسم صاحب الحساب</label>
                        <input type="text" class="form-control" readonly
                               value="{{ $approvedBankAccount->re_guardian_name }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label info-label">رقم الآيبان (شيكل)</label>
                        <input type="text" class="form-control" readonly
                               value="{{ $approvedBankAccount->iban_shekel }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label info-label">رقم الآيبان (دولار)</label>
                        <input type="text" class="form-control" readonly
                               value="{{ $approvedBankAccount->iban_usd }}">
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    المعلومات البنكية معتمدة ولا يمكن تعديلها. للتغيير يرجى التواصل مع الإدارة.
                </div>
            </div>
            @endif

            {{-- أزرار الحفظ --}}
            <div class="card card-custom p-3 mb-4">
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        <i class="fas fa-arrow-right me-2"></i>
                        رجوع
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>
                        حفظ التعديلات
                    </button>
                </div>
            </div>
        </form>

    @elseif(isset($data) && $data)
        {{-- عرض البيانات القديمة في حالة عدم وجود كفالة --}}
        @include('user.dashboard.component.partials.old-data-view')
    @else
        <div class="alert alert-warning text-center">لا توجد بيانات لعرضها.</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.getElementById('sponsorshipUpdateForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم حفظ التعديلات على بياناتك',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، احفظ',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});
</script>
@endpush
