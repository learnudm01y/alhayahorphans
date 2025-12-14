
@extends('user.dashboard.toolbars.index')

@section('contentUser')
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700&display=swap" rel="stylesheet">
<style>
    .card-custom {
        border-radius: 18px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
        background: #fff;
        border: none;
    }
    .info-label {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 5px;
        font-weight: 500;
    }
    .info-value {
        font-size: 1.05rem;
        font-weight: 600;
        color: #2c3e50;
    }
    .category-title {
        font-size: 1.3rem;
        font-weight: bold;
        color: #2563eb;
        margin: 2rem 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 3px solid #3b82f6;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-control, .form-select {
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        font-size: 0.95rem;
        padding: 0.65rem 1rem;
        transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.15);
        outline: none;
    }
    .header-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 18px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }
    .header-card h2 {
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
        font-weight: 700;
    }
    .header-card p {
        margin-bottom: 0.3rem;
        font-size: 1rem;
        opacity: 0.95;
    }
    .btn-save {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 12px 35px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 12px;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }
    .btn-back {
        background: #6c757d;
        border: none;
        padding: 12px 35px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 12px;
        color: white;
    }
    .field-icon {
        font-size: 1.3rem;
        color: #3b82f6;
    }
    .no-fields-alert {
        text-align: center;
        padding: 3rem;
        background: #fff3cd;
        border-radius: 15px;
        border: 2px solid #ffc107;
    }
    .family-member-card {
        background: #f8f9fa;
        border: 2px solid #e9ecef !important;
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    .family-member-card:hover {
        border-color: #667eea !important;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
</style>

<div class="container py-4">
    @if(isset($sponsorship) && $sponsorship)
        {{-- Form --}}
        <form method="POST" action="{{ route('user.update-sponsorship-data') }}" id="sponsorshipForm">
            @csrf
            <input type="hidden" name="sponsorship_id" value="{{ $sponsorship->id }}">

            @if(isset($groupedFields) && count($groupedFields) > 0)
                {{-- Display Fields by Category --}}
                @foreach($groupedFields as $categoryId => $categoryData)
                    <div class="card card-custom p-4 mb-4">
                        <h3 class="category-title">
                            <i class="bi bi-folder2-open field-icon"></i>
                            {{ $categoryData['name'] }}
                        </h3>

                        <div class="row g-3">
                            @foreach($categoryData['fields'] as $field)
                                @php
                                    $value = $fieldValues[$field['db_column']] ?? '';
                                    $fieldKey = str_replace('field_', '', $field['db_column']);
                                @endphp

                                <div class="col-md-6 col-12">
                                    <label class="info-label">
                                        <i class="bi bi-dot me-1"></i>
                                        {{ $field['display_name'] }}
                                        @if($field['required'] ?? false)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>

                                    @if($field['db_column'] == 'field_health_status')
                                        {{-- Health Status Dropdown --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر الحالة الصحية</option>
                                            @foreach($healthStatuses as $status)
                                                <option value="{{ $status->description }}"
                                                    {{ $value == $status->description ? 'selected' : '' }}>
                                                    {{ $status->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_housing_status')
                                        {{-- Housing Status Dropdown --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر حالة السكن</option>
                                            @foreach($housingStatuses as $status)
                                                <option value="{{ $status->description }}"
                                                    {{ $value == $status->description ? 'selected' : '' }}>
                                                    {{ $status->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_housing_type')
                                        {{-- Housing Type Dropdown --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر نوع السكن</option>
                                            @foreach($housingTypes as $type)
                                                <option value="{{ $type->description }}"
                                                    {{ $value == $type->description ? 'selected' : '' }}>
                                                    {{ $type->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_guardian_bank_name')
                                        {{-- Bank Name Dropdown --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر اسم البنك</option>
                                            @foreach($bankNames as $bank)
                                                <option value="{{ $bank->id }}"
                                                    {{ $value == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_father_death_reason' || $field['db_column'] == 'field_mother_death_reason')
                                        {{-- Death Reason Dropdown --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر سبب الوفاة</option>
                                            @foreach($deathReasons as $reason)
                                                <option value="{{ $reason->description }}"
                                                    {{ $value == $reason->description ? 'selected' : '' }}>
                                                    {{ $reason->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_data_province')
                                        {{-- Province Dropdown --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر المحافظة</option>
                                            @foreach($provinces as $province)
                                                <option value="{{ $province->description }}"
                                                    {{ $value == $province->description ? 'selected' : '' }}>
                                                    {{ $province->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_data_city')
                                        {{-- City Dropdown --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر المدينة</option>
                                            @foreach($cities as $city)
                                                <option value="{{ $city->city }}"
                                                    {{ $value == $city->city ? 'selected' : '' }}>
                                                    {{ $city->city }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif(Str::contains($fieldKey, ['notes', 'note']) && !Str::contains($fieldKey, ['reason']))
                                        {{-- Text Area --}}
                                        <textarea
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            rows="3"
                                            placeholder="أدخل {{ $field['display_name'] }}"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >{{ old('fields.' . $field['db_column'], $value) }}</textarea>
                                    @elseif(Str::contains($fieldKey, ['_date', '_birth_date']) || (Str::contains($fieldKey, ['death']) && Str::contains($fieldKey, ['date'])))
                                        {{-- Date Input - فقط للحقول التي تحتوي على _date أو _birth_date أو death_date --}}
                                        <input
                                            type="date"
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            value="{{ old('fields.' . $field['db_column'], $value) }}"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                    @elseif(Str::contains($fieldKey, ['phone', 'mobile', 'tel']))
                                        {{-- Phone Input --}}
                                        <input
                                            type="tel"
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            value="{{ old('fields.' . $field['db_column'], $value) }}"
                                            placeholder="مثال: 0599123456"
                                            pattern="[0-9]*"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                    @elseif(Str::contains($fieldKey, ['email', 'mail']))
                                        {{-- Email Input --}}
                                        <input
                                            type="email"
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            value="{{ old('fields.' . $field['db_column'], $value) }}"
                                            placeholder="example@email.com"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                    @elseif(Str::contains($fieldKey, ['iban', 'id_owner', 'account_owner']))
                                        {{-- IBAN and Account Fields - Text Input --}}
                                        <input
                                            type="text"
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            value="{{ old('fields.' . $field['db_column'], $value) }}"
                                            placeholder="أدخل {{ $field['display_name'] }}"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                    @elseif(Str::contains($fieldKey, ['age', 'count', 'duration', 'months']) && !Str::contains($fieldKey, ['account', 'phone', 'iban', 'id']))
                                        {{-- Number Input --}}
                                        <input
                                            type="number"
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            value="{{ old('fields.' . $field['db_column'], $value) }}"
                                            min="0"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                    @else
                                        {{-- Text Input (Default) --}}
                                        <input
                                            type="text"
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            value="{{ old('fields.' . $field['db_column'], $value) }}"
                                            placeholder="أدخل {{ $field['display_name'] }}"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                    @endif

                                    @error('fields.' . $field['db_column'])
                                        <div class="text-danger small mt-1">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- أفراد الأسرة - عرض ديناميكي من re_people --}}
                <div class="card card-custom p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="category-title mb-0">
                            <i class="bi bi-people-fill field-icon"></i>
                            أفراد الأسرة
                        </h3>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addFamilyMember()">
                            <i class="bi bi-plus-circle me-1"></i>
                            إضافة فرد جديد
                        </button>
                    </div>

                    <div id="family-members-container">
                        @if(isset($familyMembers) && $familyMembers->count() > 0)
                            @foreach($familyMembers as $index => $member)
                                <div class="family-member-card card mb-3 p-3" data-member-id="{{ $member->id }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0">فرد رقم {{ $index + 1 }}</h5>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="removeFamilyMember(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <input type="hidden" name="family_members[{{ $index }}][id]" value="{{ $member->id }}">
                                    <input type="hidden" name="family_members[{{ $index }}][person_id]" value="{{ $member->person_id }}">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">اسماء اخوة المكفول</label>
                                            <input type="text" name="family_members[{{ $index }}][name]"
                                                   class="form-control"
                                                   value="{{ trim("{$member->first_name} {$member->second_name} {$member->third_name} {$member->last_name}") }}"
                                                   placeholder="أدخل اسماء اخوة المكفول">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">تاريخ الميلاد (للأخ/الأخت)</label>
                                            <input type="date" name="family_members[{{ $index }}][birthdate]"
                                                   class="form-control"
                                                   value="{{ $member->person_birth_date }}">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">الصف (للأخ/الأخت)</label>
                                            <input type="text" name="family_members[{{ $index }}][grade]"
                                                   class="form-control"
                                                   value="{{ $member->person_gender == 1 ? 'ذكر' : ($member->person_gender == 2 ? 'أنثى' : '') }}"
                                                   placeholder="أدخل الصف (للأخ/الأخت)">
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">ملاحظات (الحالة الصحية والإجتماعية)</label>
                                            <textarea name="family_members[{{ $index }}][notes]"
                                                      class="form-control"
                                                      rows="3"
                                                      placeholder="أدخل ملاحظات (الحالة الصحية والإجتماعية)">{{ $member->person_note ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                لا يوجد أفراد أسرة مسجلين. يمكنك إضافة أفراد جدد بالضغط على زر "إضافة فرد جديد".
                            </div>
                        @endif
                    </div>
                </div>

                {{-- تم إزالة القسم المنفصل للحساب البنكي - الحقول الآن ضمن النموذج القابل للتعديل --}}

                {{-- Action Buttons --}}
                <div class="card card-custom p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('user.dashboard') }}" class="btn btn-back">
                            <i class="bi bi-arrow-right me-2"></i>
                            رجوع
                        </a>
                        <button type="submit" class="btn btn-save">
                            <i class="bi bi-save me-2"></i>
                            حفظ التغييرات
                        </button>
                    </div>
                </div>
            @else
                {{-- No Fields Enabled --}}
                <div class="no-fields-alert">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ffc107;"></i>
                    <h3 class="mt-3 mb-2">لا توجد حقول مفعلة</h3>
                    <p class="text-muted">
                        لم يتم تفعيل أي حقول للعرض من قبل الجمعية.
                        <br>
                        يرجى التواصل مع الإدارة لتفعيل الحقول المطلوبة.
                    </p>
                    <a href="{{ route('user.dashboard') }}" class="btn btn-back mt-3">
                        <i class="bi bi-arrow-right me-2"></i>
                        العودة للرئيسية
                    </a>
                </div>
            @endif
        </form>

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('sponsorshipForm');

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    Swal.fire({
                        title: 'تأكيد الحفظ',
                        text: 'هل أنت متأكد من حفظ التغييرات على بياناتك؟',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'نعم، احفظ التغييرات',
                        cancelButtonText: 'إلغاء',
                        confirmButtonColor: '#667eea',
                        cancelButtonColor: '#6c757d',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'جاري الحفظ...',
                                text: 'يرجى الانتظار',
                                icon: 'info',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            form.submit();
                        }
                    });
                });
            }
        });

        // Family Members Management
        let familyMemberIndex = {{ isset($familyMembers) ? $familyMembers->count() : 0 }};

        function addFamilyMember() {
            const container = document.getElementById('family-members-container');
            const alertInfo = container.querySelector('.alert-info');
            if (alertInfo) {
                alertInfo.remove();
            }

            const newMemberHtml = `
                <div class="family-member-card card mb-3 p-3" data-member-id="new-${familyMemberIndex}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">فرد رقم ${familyMemberIndex + 1}</h5>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeFamilyMember(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>

                    <input type="hidden" name="family_members[${familyMemberIndex}][is_new]" value="1">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسماء اخوة المكفول</label>
                            <input type="text" name="family_members[${familyMemberIndex}][name]"
                                   class="form-control"
                                   placeholder="أدخل اسماء اخوة المكفول">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ الميلاد (للأخ/الأخت)</label>
                            <input type="date" name="family_members[${familyMemberIndex}][birthdate]"
                                   class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">الصف (للأخ/الأخت)</label>
                            <input type="text" name="family_members[${familyMemberIndex}][grade]"
                                   class="form-control"
                                   placeholder="أدخل الصف (للأخ/الأخت)">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">ملاحظات (الحالة الصحية والإجتماعية)</label>
                            <textarea name="family_members[${familyMemberIndex}][notes]"
                                      class="form-control"
                                      rows="3"
                                      placeholder="أدخل ملاحظات (الحالة الصحية والإجتماعية)"></textarea>
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', newMemberHtml);
            familyMemberIndex++;
        }

        function removeFamilyMember(button) {
            Swal.fire({
                title: 'تأكيد الحذف',
                text: 'هل أنت متأكد من حذف هذا الفرد؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
            }).then((result) => {
                if (result.isConfirmed) {
                    const card = button.closest('.family-member-card');
                    card.remove();

                    // إعادة ترقيم الأفراد
                    const allCards = document.querySelectorAll('.family-member-card');
                    allCards.forEach((card, index) => {
                        card.querySelector('h5').textContent = `فرد رقم ${index + 1}`;
                    });

                    // إذا لم يتبق أي فرد، أظهر رسالة
                    if (allCards.length === 0) {
                        const container = document.getElementById('family-members-container');
                        container.innerHTML = `
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                لا يوجد أفراد أسرة مسجلين. يمكنك إضافة أفراد جدد بالضغط على زر "إضافة فرد جديد".
                            </div>
                        `;
                    }
                }
            });
        }
        </script>
    @else
        {{-- No Sponsorship Found --}}
        <div class="card card-custom p-5 text-center">
            <i class="bi bi-exclamation-circle" style="font-size: 4rem; color: #dc3545;"></i>
            <h2 class="mt-4 mb-3">لم يتم العثور على بيانات الكفالة</h2>
            <p class="text-muted mb-4">
                عذراً، لم نتمكن من إيجاد بيانات الكفالة الخاصة بك.
                <br>
                يرجى التواصل مع الإدارة للحصول على المساعدة.
            </p>
            <a href="{{ route('user.dashboard') }}" class="btn btn-back">
                <i class="bi bi-arrow-right me-2"></i>
                العودة للرئيسية
            </a>
        </div>
    @endif
</div>

@if(session('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'نجح!',
                text: '{{ session("success") }}',
                icon: 'success',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#667eea'
            });
        });
    </script>
@endif

@if(session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'خطأ!',
                text: '{{ session("error") }}',
                icon: 'error',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#dc3545'
            });
        });
    </script>
@endif

@endsection


