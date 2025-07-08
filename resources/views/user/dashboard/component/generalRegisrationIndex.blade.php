
@extends('user.dashboard.toolbars.index')

@section('contentUser')
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700&display=swap" rel="stylesheet">
<style>
    .profile-img { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 2px solid #eee; background: #fafafa; }
    .card-custom { border-radius: 18px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 1.5rem; background: #fff; }
    .info-label { color: #888; font-size: 1rem; margin-bottom: 2px; }
    .info-value { font-size: 1.1rem; font-weight: bold; color: #222; margin-bottom: 8px; }
    .main-header-row .d-flex span, .main-header-row .d-flex { font-size: 1.3rem; }
</style>
<div class="container">
    <h2 class="mb-4 text-center" style="font-family: 'Cairo', Arial, Tahoma, sans-serif;">عرض تفاصيل السجل</h2>
    @if($data)
    <div class="card card-custom p-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 main-header-row"
             style="background: rgba(180,180,180,0.18); border-radius: 16px 16px 0 0; padding: 14px 24px; font-weight: bold; font-size: 1.3rem; font-family: 'Cairo', Arial, Tahoma, sans-serif; width: 100%;">
            <div class="d-flex align-items-center flex-wrap" style="gap: 16px;">
                <span>رقم الملف: {{ $data->file_id_number }}</span>
                <span style="border-right: 2px solid #bbb; height: 22px; margin: 0 12px;"></span>
                <span>القسم: {{ optional($data->section)->description }}</span>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 mb-3">
                <div class="d-flex flex-column align-items-start" style="direction: rtl;">
                    <div>
                        <span class="info-label" style="font-size:1.1rem;"> رقم الهوية :</span>
                        <span class="info-value" style="display:inline-block; font-size:1.25rem;">{{ $data->data_id_number }}</span>
                    </div>
                    <div>
                        <span class="info-label" style="font-size:1.1rem;">الاسم الكامل :</span>
                        <span class="info-value" style="display:inline-block; font-size:1.25rem;">{{ $data->data_first_name }} {{ $data->data_father_name }} {{ $data->data_grand_father_name }} {{ $data->data_family_name }}</span>
                    </div>
                    <hr style="border-top: 3px solid #ffc107; width: 220px; margin: 8px 0 0 0; border-radius: 2px;">
                </div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">تاريخ الميلاد</div>
                <div class="info-value">{{ $data->data_birth_date }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">حالة الطلب</div>
                <div class="info-value">{{ optional($data->requestStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">صلة القرابة</div>
                <div class="info-value">{{ optional($data->categoryOfRelation)->attribute }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">الحالة الصحية</div>
                <div class="info-value">{{ optional($data->healthStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">رقم الجوال</div>
                <div class="info-value">{{ $data->data_phone_number }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">رقم جوال إضافي</div>
                <div class="info-value">{{ $data->data_alt_phone_number }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">الحالة الاجتماعية</div>
                <div class="info-value">{{ optional($data->maritalStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">المؤهل العلمي</div>
                <div class="info-value">{{ optional($data->academicQualification)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">المدينة</div>
                <div class="info-value">{{ optional($data->city)->city }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">المحافظة</div>
                <div class="info-value">{{ optional($data->province)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">حالة عمل العائل</div>
                <div class="info-value">{{ optional($data->employmentStatusBreadwinner)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">الحالة السكنية</div>
                <div class="info-value">{{ optional($data->housingStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">نوع السكن الحالي</div>
                <div class="info-value">{{ optional($data->currentHousingType)->description }}</div>
            </div>
            <div class="col-12 mb-2">
                <div class="info-label">وصف الاحتياج</div>
                <div class="info-value">{{ $data->data_description_needs }}</div>
            </div>
        </div>
        <div class="mt-3 w-100">
            <div class="info-label mb-1">المعلومات البنكية:</div>
            @if($data->guardianBankAccount)
                <div class="row mb-2">
                    <div class="col-md-6">
                        <strong>اسم البنك:</strong> {{ optional($data->guardianBankAccount->bank)->name ?? $data->guardianBankAccount->bank_name }}
                    </div>
                    <div class="col-md-6">
                        <strong>رقم الآيبان (شيكل):</strong> {{ $data->guardianBankAccount->iban_shekel }}
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <strong>رقم الآيبان (دولار):</strong> {{ $data->guardianBankAccount->iban_usd }}
                    </div>
                    <div class="col-md-6">
                        <strong>اسم صاحب الحساب:</strong> {{ $data->guardianBankAccount->re_guardian_name }}
                    </div>
                </div>
            @else
                <span class="text-muted">لا توجد بيانات بنكية مسجلة.</span>
            @endif
        </div>
        <div class="mt-3 w-100">
            <div class="info-label mb-1">المرفقات:</div>
            @if($data->attachments && count($data->attachments))
                <ul>
                    @foreach($data->attachments as $att)
                        <li>
                            <a href="{{ asset($att->file_path) }}" target="_blank">{{ $att->original_file_name }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <span class="text-muted">لا يوجد مرفقات.</span>
            @endif
        </div>
    </div>

    <div class="card card-custom p-3 mb-4">
        <h5 class="mb-3">أفراد الأسرة</h5>
        @if($data->rePeople && count($data->rePeople))
            <div class="row family-scroll-row">
                @foreach($data->rePeople as $member)
                    <div class="col-md-6 col-lg-4 col-12 mb-4 family-member-card">
                        <div class="card h-100 shadow-sm border-0">
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
        @else
            <p class="text-muted">لا يوجد أفراد أسرة.</p>
        @endif
    </div>

    <div class="card card-custom p-3 mb-4">
        <h5 class="mb-3">بيانات المتوفين</h5>
        @if($data->deadPepole)
            <div class="row">
                @if($data->deadPepole->father_first_name || $data->deadPepole->father_last_name || $data->deadPepole->father_id)
                    <div class="col-md-6 col-12 mb-3">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body">
                                <div><strong>اسم الأب:</strong> {{ $data->deadPepole->father_first_name }} {{ $data->deadPepole->father_last_name }}</div>
                                <div><strong>رقم هوية الأب:</strong> {{ $data->deadPepole->father_id }}</div>
                            </div>
                        </div>
                    </div>
                @endif
                @if($data->deadPepole->mother_first_name || $data->deadPepole->mother_last_name || $data->deadPepole->mother_id)
                    <div class="col-md-6 col-12 mb-3">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body">
                                <div><strong>اسم الأم:</strong> {{ $data->deadPepole->mother_first_name }} {{ $data->deadPepole->mother_last_name }}</div>
                                <div><strong>رقم هوية الأم:</strong> {{ $data->deadPepole->mother_id }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <p class="text-muted">لا توجد بيانات متوفين.</p>
        @endif
    </div>
    @else
        <div class="alert alert-warning text-center">لا توجد بيانات لعرضها.</div>
    @endif
</div>
@endsection
