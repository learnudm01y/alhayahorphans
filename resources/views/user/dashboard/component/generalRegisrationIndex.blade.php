
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

    /* Skeleton Loading Animation */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: skeleton-loading 1.5s infinite;
        border-radius: 8px;
        min-height: 38px;
    }
    .skeleton-text {
        height: 20px;
        margin-bottom: 8px;
    }
    .skeleton-input {
        height: 42px;
        width: 100%;
    }
    @keyframes skeleton-loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Guardian Search Styles */
    .search-success {
        border-color: #28a745 !important;
        background-color: rgba(40, 167, 69, 0.05);
    }
    .search-error {
        border-color: #dc3545 !important;
        background-color: rgba(220, 53, 69, 0.05);
    }
    .field-updated {
        animation: field-highlight 1s ease;
    }
    @keyframes field-highlight {
        0% { background-color: rgba(40, 167, 69, 0.2); }
        100% { background-color: transparent; }
    }
</style>

<div class="container py-4">
    @if(isset($sponsorship) && $sponsorship)
        {{-- Form --}}
        <form method="POST" action="{{ route('user.update-sponsorship-data') }}" id="sponsorshipForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="sponsorship_id" value="{{ $sponsorship->id }}">

            {{-- 🆕 عرض نوع الشخص (person_type) --}}
            @php
                $personTypeLabels = [
                    'breadwinner' => ['label' => 'معيل', 'icon' => 'bi-person-badge', 'color' => 'primary', 'table' => 'جدول البيانات الأساسية (data)'],
                    'family_member' => ['label' => 'فرد عائلة', 'icon' => 'bi-people-fill', 'color' => 'success', 'table' => 'جدول أفراد الأسرة (re_people)'],
                    'deceased_father' => ['label' => 'أب متوفي', 'icon' => 'bi-person-x', 'color' => 'secondary', 'table' => 'جدول المتوفين (dead_people)'],
                    'deceased_mother' => ['label' => 'أم متوفية', 'icon' => 'bi-person-x', 'color' => 'secondary', 'table' => 'جدول المتوفين (dead_people)'],
                ];
                $currentPersonType = $fieldValues['_stored_person_type'] ?? null;
                $personTypeInfo = $personTypeLabels[$currentPersonType] ?? null;
            @endphp

            @if($personTypeInfo)
                <div class="alert alert-{{ $personTypeInfo['color'] }} d-flex align-items-center mb-4" role="alert">
                    <i class="bi {{ $personTypeInfo['icon'] }} fs-4 me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-1">
                            نوع الشخص: <strong>{{ $personTypeInfo['label'] }}</strong>
                        </h5>
                        <small class="opacity-75">
                            <i class="bi bi-database me-1"></i>
                            يتم تخزين البيانات في: {{ $personTypeInfo['table'] }}
                        </small>
                    </div>
                </div>
            @else
                <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-1">نوع الشخص غير محدد</h5>
                        <small class="opacity-75">
                            لم يتم تحديد نوع الشخص لهذه الكفالة. سيتم استخدام الطريقة الافتراضية للتخزين.
                        </small>
                    </div>
                </div>
            @endif

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

                                    // === متغيرات التحكم في مصدر البيانات ===
                                    $sponsoredDataSource = $fieldValues['_sponsored_data_source'] ?? 'none';
                                    $guardianDataSource = $fieldValues['_guardian_data_source'] ?? 'none';
                                    $sponsoredShow4Fields = $fieldValues['_sponsored_show_4_fields'] ?? false;
                                    $guardianShow4Fields = $fieldValues['_guardian_show_4_fields'] ?? true;
                                    $guardianNeedsCivilSearch = $fieldValues['_guardian_needs_civil_search'] ?? false;

                                    // 🆕 متغير جديد: هل نحتاج لإنشاء بيانات مركزية جديدة؟
                                    $needsCentralDataEntry = $fieldValues['_needs_central_data_entry'] ?? false;
                                    $sponsorshipOrphanName = $fieldValues['_sponsorship_orphan_name'] ?? '';
                                    $sponsorshipGuardianName = $fieldValues['_sponsorship_guardian_name'] ?? '';

                                    // تحديد الحقول التي تتطلب عرض 4 حقول منفصلة للاسم
                                    $nameFieldsMapping = [
                                        'field_sponsor_name' => [
                                            'prefix' => 'sponsored',
                                            'label' => 'المكفول',
                                            'type' => 'sponsored', // لتحديد نوع الشخص
                                            'fields' => ['first_name' => 'الاسم الأول', 'second_name' => 'اسم الأب', 'third_name' => 'اسم الجد', 'last_name' => 'اسم العائلة']
                                        ],
                                        'field_father_first_name' => [
                                            'prefix' => 'father',
                                            'label' => 'الأب المتوفى',
                                            'type' => 'parent',
                                            'fields' => ['first_name' => 'الاسم الأول', 'second_name' => 'اسم الأب', 'third_name' => 'اسم الجد', 'last_name' => 'اسم العائلة']
                                        ],
                                        'field_mother_first_name' => [
                                            'prefix' => 'mother',
                                            'label' => 'الأم المتوفية',
                                            'type' => 'parent',
                                            'fields' => ['first_name' => 'الاسم الأول', 'second_name' => 'اسم الأب', 'third_name' => 'اسم الجد', 'last_name' => 'اسم العائلة']
                                        ],
                                        'field_first_name' => [
                                            'prefix' => 'orphan',
                                            'label' => 'اليتيم',
                                            'type' => 'sponsored',
                                            'fields' => ['first_name' => 'الاسم الأول', 'second_name' => 'اسم الأب', 'third_name' => 'اسم الجد', 'last_name' => 'اسم العائلة']
                                        ],
                                        'field_guardian_name' => [
                                            'prefix' => 'guardian',
                                            'label' => 'المعيل',
                                            'type' => 'guardian',
                                            'fields' => ['first_name' => 'الاسم الأول', 'second_name' => 'اسم الأب', 'third_name' => 'اسم الجد', 'last_name' => 'اسم العائلة']
                                        ],
                                    ];

                                    $isNameField = array_key_exists($field['db_column'], $nameFieldsMapping);

                                    // تحديد هل هذا حقل اسم للمكفول ويجب عرضه كحقل واحد
                                    $isSponsoredNameField = ($field['db_column'] === 'field_sponsor_name');
                                    $showSponsoredAs1Field = $isSponsoredNameField && !$sponsoredShow4Fields && !$needsCentralDataEntry;

                                    // تحديد حقول الأم (الاسم، رقم الهوية، تاريخ الوفاة، سبب الوفاة)
                                    $motherFields = ['field_mother_first_name', 'field_mother_second_name', 'field_mother_third_name', 'field_mother_last_name', 'field_mother_id', 'field_mother_death_date', 'field_mother_death_reason'];
                                    $isMotherField = in_array($field['db_column'], $motherFields);

                                    // الحصول على قيمة عرض حقول الأم من fieldValues
                                    $showMotherDeathFields = $fieldValues['show_mother_death_fields'] ?? true;

                                    // 🆕 تحديد حقول رقم هوية الأب والأم (المكررة - تُخفى عند عرضها في البحث التلقائي)
                                    $deceasedIdFields = ['field_father_id', 'field_mother_id'];
                                    $isDeceasedIdField = in_array($field['db_column'], $deceasedIdFields);
                                @endphp

                                {{-- إخفاء حقول الأم إذا كانت صلة القرابة "أم" --}}
                                @if($isMotherField && !$showMotherDeathFields)
                                    @continue
                                @endif

                                {{-- 🆕 إخفاء حقول رقم هوية الأب والأم العادية - أصبحت مدمجة في قسم الأسماء --}}
                                @if($isDeceasedIdField)
                                    @continue
                                @endif

                                @if($isNameField && $showSponsoredAs1Field)
                                    {{-- عرض حقل واحد فقط للاسم (البيانات من sponsorship فقط) --}}
                                    <div class="col-12">
                                        <div class="border rounded p-3 bg-light mb-2">
                                            <label class="info-label fw-bold mb-2">
                                                <i class="bi bi-person-badge me-1"></i>
                                                {{ $field['display_name'] }}
                                                @if($field['required'] ?? false)
                                                    <span class="text-danger">*</span>
                                                @endif
                                                <span class="badge bg-secondary ms-2">حقل موحد</span>
                                            </label>
                                            <input
                                                type="text"
                                                name="fields[{{ $field['db_column'] }}]"
                                                class="form-control"
                                                value="{{ old('fields.' . $field['db_column'], $value) }}"
                                                placeholder="أدخل {{ $field['display_name'] }}"
                                                {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            >
                                            <small class="text-muted d-block mt-1">
                                                <i class="bi bi-info-circle me-1"></i>
                                                قاعدة البيانات الموحدة
                                            </small>
                                        </div>
                                    </div>
                                @elseif($isNameField && $needsCentralDataEntry)
                                    {{-- 🆕 عرض الاسم من sponsorships + تنبيه + 4 حقول للإدخال --}}
                                    @php
                                        $nameConfig = $nameFieldsMapping[$field['db_column']];
                                        $prefix = $nameConfig['prefix'];

                                        // تحديد إذا كان حقل متوفي (أب أو أم) - لا نعرض التنبيه لهم
                                        $isDeceasedField = in_array($prefix, ['father', 'mother']);

                                        // تحديد الاسم الحالي من sponsorships
                                        $currentFullName = '';
                                        if ($prefix == 'sponsored') {
                                            $currentFullName = $sponsorshipOrphanName;
                                        } elseif ($prefix == 'guardian') {
                                            $currentFullName = $sponsorshipGuardianName;
                                        }
                                    @endphp

                                    @if($isDeceasedField)
                                        {{-- حقول المتوفين - عرض عادي مع إمكانية البحث في قاعدة البيانات المركزية --}}
                                        <div class="col-12">
                                            <div class="border rounded p-3 bg-light mb-2">
                                                <label class="info-label fw-bold mb-2">
                                                    <i class="bi bi-person-badge me-1"></i>
                                                    {{ $field['display_name'] }}
                                                </label>

                                                {{-- حقل رقم الهوية مع البحث التلقائي --}}
                                                <div class="mb-3">
                                                    <label class="small text-muted">رقم هوية {{ $prefix == 'father' ? 'الأب' : 'الأم' }} المتوفى/ة</label>
                                                    <div class="input-group">
                                                        <input
                                                            type="text"
                                                            id="deceased_{{ $prefix }}_id"
                                                            name="fields[field_{{ $prefix }}_id]"
                                                            class="form-control"
                                                            value="{{ $fieldValues['field_' . $prefix . '_id'] ?? '' }}"
                                                            placeholder="أدخل رقم الهوية (9 أرقام) - البحث تلقائي"
                                                            pattern="[0-9]{9,10}"
                                                            maxlength="10"
                                                            oninput="this.value = this.value.replace(/[^0-9]/g, ''); autoSearchDeceased('{{ $prefix }}', this.value);"
                                                        >
                                                        <span class="input-group-text bg-light" id="deceased_{{ $prefix }}_spinner" style="display: none;">
                                                            <i class="bi bi-hourglass-split text-primary"></i>
                                                        </span>
                                                    </div>
                                                    <div id="deceased_{{ $prefix }}_search_status" class="mt-1" style="display: none;">
                                                        <small class="text-info">
                                                            <i class="bi bi-hourglass-split me-1"></i>
                                                            <span id="deceased_{{ $prefix }}_status_text">جاري البحث...</span>
                                                        </small>
                                                    </div>
                                                </div>

                                                {{-- 4 حقول للاسم --}}
                                                <div class="row g-2">
                                                    @foreach($nameConfig['fields'] as $namePart => $nameLabel)
                                                        @php
                                                            $nameFieldKey = "field_{$prefix}_{$namePart}";
                                                            $nameValue = $fieldValues[$nameFieldKey] ?? '';
                                                        @endphp
                                                        <div class="col-md-3 col-6">
                                                            <label class="small text-muted">{{ $nameLabel }}</label>
                                                            <input
                                                                type="text"
                                                                id="deceased_{{ $prefix }}_{{ $namePart }}"
                                                                name="names[{{ $prefix }}][{{ $namePart }}]"
                                                                class="form-control"
                                                                value="{{ old("names.{$prefix}.{$namePart}", $nameValue) }}"
                                                                placeholder="{{ $nameLabel }}"
                                                            >
                                                        </div>
                                                    @endforeach
                                                </div>

                                                {{-- حقل تاريخ الميلاد (يُجلب من قاعدة البيانات المركزية) --}}
                                                <div class="row g-2 mt-2">
                                                    <div class="col-md-4">
                                                        <label class="small text-muted">
                                                            <i class="bi bi-calendar-date me-1"></i>
                                                            تاريخ ميلاد {{ $prefix == 'father' ? 'الأب' : 'الأم' }}
                                                        </label>
                                                        <input
                                                            type="text"
                                                            id="deceased_{{ $prefix }}_birth_date"
                                                            class="form-control bg-light"
                                                            placeholder="يُجلب تلقائياً"
                                                            readonly
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                    {{-- حقول المكفول والمعيل - عرض التنبيه مع 4 حقول --}}
                                    <div class="col-12">
                                        <div class="border rounded p-3 mb-2" style="background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%); border-color: #ffc107 !important;">
                                            <label class="info-label fw-bold mb-2">
                                                <i class="bi bi-person-badge me-1"></i>
                                                {{ $field['display_name'] }}
                                                @if($field['required'] ?? false)
                                                    <span class="text-danger">*</span>
                                                @endif
                                                <span class="badge bg-warning text-dark ms-2">يتطلب تأكيد البيانات</span>
                                            </label>

                                            @if($currentFullName)
                                                {{-- عرض الاسم الحالي من sponsorships (غير قابل للتعديل) --}}
                                                <div class="alert alert-info py-2 mb-3">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    <strong>الاسم المسجل حالياً:</strong>
                                                    <span class="fw-bold">{{ $currentFullName }}</span>
                                                </div>
                                            @endif

                                            {{-- تنبيه للمستخدم --}}
                                            <div class="alert alert-warning py-2 mb-3">
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                <strong>تنبيه مهم:</strong>
                                                يرجى إعادة إدخال الاسم بشكل صحيح في الحقول أدناه للتأكد من دقة البيانات.
                                            </div>

                                            {{-- 4 حقول منفصلة للإدخال --}}
                                            <div class="row g-2">
                                                @foreach($nameConfig['fields'] as $namePart => $nameLabel)
                                                    @php
                                                        $nameFieldKey = "field_{$prefix}_{$namePart}";
                                                        $nameValue = $fieldValues[$nameFieldKey] ?? '';
                                                    @endphp
                                                    <div class="col-md-3 col-6">
                                                        <label class="small text-dark fw-bold">{{ $nameLabel }} <span class="text-danger">*</span></label>
                                                        <input
                                                            type="text"
                                                            name="names[{{ $prefix }}][{{ $namePart }}]"
                                                            class="form-control border-warning"
                                                            value="{{ old("names.{$prefix}.{$namePart}", $nameValue) }}"
                                                            placeholder="{{ $nameLabel }}"
                                                            required
                                                        >
                                                    </div>
                                                @endforeach
                                            </div>

                                            <small class="text-muted d-block mt-2">
                                                <i class="bi bi-database me-1"></i>
                                                سيتم إنشاء سجل جديد في النظام المركزي
                                            </small>
                                        </div>
                                    </div>
                                    @endif
                                    </div>
                                @elseif($isNameField)
                                    {{-- عرض 4 حقول منفصلة للأسماء --}}
                                    @php
                                        $nameConfig = $nameFieldsMapping[$field['db_column']];
                                        $prefix = $nameConfig['prefix'];
                                        // تحديد إذا كان حقل للوالدين المتوفين
                                        $isDeceasedParentField = in_array($prefix, ['father', 'mother']);
                                    @endphp
                                    <div class="col-12">
                                        <div class="border rounded p-3 bg-light mb-2">
                                            <label class="info-label fw-bold mb-2">
                                                <i class="bi bi-person-badge me-1"></i>
                                                {{ $field['display_name'] }}
                                                @if($field['required'] ?? false)
                                                    <span class="text-danger">*</span>
                                                @endif
                                            </label>

                                            {{-- 🆕 حقل رقم الهوية للوالدين المتوفين - يظهر أولاً --}}
                                            @if($isDeceasedParentField)
                                                <div class="mb-3">
                                                    <label class="small text-muted">
                                                        <i class="bi bi-card-text me-1"></i>
                                                        رقم هوية {{ $prefix == 'father' ? 'الأب' : 'الأم' }}
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="fields[field_{{ $prefix }}_id]"
                                                        class="form-control"
                                                        value="{{ old('fields.field_' . $prefix . '_id', $fieldValues['field_' . $prefix . '_id'] ?? '') }}"
                                                        placeholder="أدخل رقم هوية {{ $prefix == 'father' ? 'الأب' : 'الأم' }}"
                                                        pattern="[0-9]{9,10}"
                                                        maxlength="10"
                                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                                    >
                                                </div>
                                            @endif

                                            <div class="row g-2">
                                                @foreach($nameConfig['fields'] as $namePart => $nameLabel)
                                                    @php
                                                        $nameFieldKey = "field_{$prefix}_{$namePart}";
                                                        $nameValue = $fieldValues[$nameFieldKey] ?? '';
                                                    @endphp
                                                    <div class="col-md-3 col-6">
                                                        <label class="small text-muted">{{ $nameLabel }}</label>
                                                        <input
                                                            type="text"
                                                            name="names[{{ $prefix }}][{{ $namePart }}]"
                                                            class="form-control"
                                                            value="{{ old("names.{$prefix}.{$namePart}", $nameValue) }}"
                                                            placeholder="{{ $nameLabel }}"
                                                            {{ ($field['required'] ?? false) && $namePart == 'first_name' ? 'required' : '' }}
                                                        >
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @else
                                {{-- تحديد الحقول المشروطة التي تحتاج إخفاء/إظهار --}}
                                @php
                                    $isConditionalField = in_array($field['db_column'], ['field_treatment_cost', 'field_family_disease_cost', 'field_orphan_health']);
                                    $conditionalWrapperStyle = '';
                                    $conditionalWrapperId = '';

                                    if ($field['db_column'] == 'field_treatment_cost') {
                                        $conditionalWrapperId = 'treatment_cost_field_wrapper';
                                        $conditionalWrapperStyle = ($fieldValues['field_receives_treatment'] ?? '') == 'نعم' ? '' : 'display: none;';
                                    } elseif ($field['db_column'] == 'field_orphan_health') {
                                        $conditionalWrapperId = 'orphan_health_field_wrapper';
                                        $conditionalWrapperStyle = ($fieldValues['field_receives_treatment'] ?? '') == 'نعم' ? '' : 'display: none;';
                                    } elseif ($field['db_column'] == 'field_family_disease_cost') {
                                        $conditionalWrapperId = 'family_disease_cost_field_wrapper';
                                        $conditionalWrapperStyle = ($fieldValues['field_family_sick_member'] ?? '') == 'نعم' ? '' : 'display: none;';
                                    }
                                @endphp
                                <div class="col-md-6 col-12" @if($isConditionalField) id="{{ $conditionalWrapperId }}" style="{{ $conditionalWrapperStyle }}" @endif>
                                    <label class="info-label">
                                        <i class="bi bi-dot me-1"></i>
                                        {{ $field['display_name'] }}
                                        @if($field['required'] ?? false)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>

                                    @if($field['db_column'] == 'field_data_id_number')
                                        {{-- حقل رقم هوية المعيل مع البحث في قاعدة البيانات المركزية --}}
                                        <div class="input-group">
                                            <input
                                                type="text"
                                                name="fields[{{ $field['db_column'] }}]"
                                                id="guardian_identity_search"
                                                class="form-control"
                                                value="{{ old('fields.' . $field['db_column'], $value) }}"
                                                placeholder="أدخل رقم الهوية (9 أرقام)"
                                                pattern="[0-9]{9,10}"
                                                maxlength="10"
                                                {{ ($field['required'] ?? false) ? 'required' : '' }}
                                                @if($guardianNeedsCivilSearch)
                                                    oninput="handleGuardianIdentityInput(this)"
                                                @endif
                                            >
                                            @if($guardianNeedsCivilSearch)
                                                <button type="button" class="btn btn-outline-primary" onclick="searchGuardianInCivilRegistry()" id="searchGuardianBtn">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            @endif
                                        </div>
                                        @if($guardianNeedsCivilSearch)
                                            <div id="guardianSearchStatus" class="mt-1" style="display: none;">
                                                <small class="text-info">
                                                    <i class="bi bi-hourglass-split me-1"></i>
                                                    <span id="searchStatusText">جاري البحث عن بيانات المعيل...</span>
                                                </small>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                <i class="bi bi-info-circle me-1"></i>
                                               أدخل رقم الهوية بشكل دقيق
                                            </small>
                                        @endif
                                    @elseif($field['db_column'] == 'field_health_status')
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
                                    @elseif($field['db_column'] == 'field_house_demolition')
                                        {{-- House Demolition Dropdown - هل المنزل هدم كلي او جزئي --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر حالة الهدم</option>
                                            <option value="غير مهدوم" {{ $value == 'غير مهدوم' ? 'selected' : '' }}>غير مهدوم</option>
                                            <option value="هدم جزئي" {{ $value == 'هدم جزئي' ? 'selected' : '' }}>هدم جزئي</option>
                                            <option value="هدم كلي" {{ $value == 'هدم كلي' ? 'selected' : '' }}>هدم كلي</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_house_repair_need')
                                        {{-- House Repair Need Dropdown - هل المنزل بحاجة الى ترميم او بناء --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر حالة الترميم</option>
                                            <option value="لا يحتاج" {{ $value == 'لا يحتاج' ? 'selected' : '' }}>لا يحتاج</option>
                                            <option value="ترميم" {{ $value == 'ترميم' ? 'selected' : '' }}>ترميم</option>
                                            <option value="بناء جزئي" {{ $value == 'بناء جزئي' ? 'selected' : '' }}>بناء جزئي</option>
                                            <option value="بناء كامل" {{ $value == 'بناء كامل' ? 'selected' : '' }}>بناء كامل</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_grade')
                                        {{-- Academic Grade Dropdown - الصف --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر الصف</option>
                                            @foreach($academicDegrees as $degree)
                                                <option value="{{ $degree->description }}"
                                                    {{ $value == $degree->description ? 'selected' : '' }}>
                                                    {{ $degree->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_student_level')
                                        {{-- Student Level Dropdown - مستوى الطالب --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر مستوى الطالب</option>
                                            <option value="ممتاز" {{ $value == 'ممتاز' ? 'selected' : '' }}>ممتاز</option>
                                            <option value="جيد جدًا" {{ $value == 'جيد جدًا' ? 'selected' : '' }}>جيد جدًا</option>
                                            <option value="جيد" {{ $value == 'جيد' ? 'selected' : '' }}>جيد</option>
                                            <option value="متوسط" {{ $value == 'متوسط' ? 'selected' : '' }}>متوسط</option>
                                            <option value="ضعيف" {{ $value == 'ضعيف' ? 'selected' : '' }}>ضعيف</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_weakness_reason')
                                        {{-- Weakness Reason Dropdown - سبب الضعف --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر سبب الضعف</option>
                                            <option value="ظروف نفسية" {{ $value == 'ظروف نفسية' ? 'selected' : '' }}>ظروف نفسية</option>
                                            <option value="ظروف مادية" {{ $value == 'ظروف مادية' ? 'selected' : '' }}>ظروف مادية</option>
                                            <option value="انقطاع عن الدراسة" {{ $value == 'انقطاع عن الدراسة' ? 'selected' : '' }}>انقطاع عن الدراسة</option>
                                            <option value="صعوبات تعلم" {{ $value == 'صعوبات تعلم' ? 'selected' : '' }}>صعوبات تعلم</option>
                                            <option value="مرض" {{ $value == 'مرض' ? 'selected' : '' }}>مرض</option>
                                            <option value="لا يوجد" {{ $value == 'لا يوجد' ? 'selected' : '' }}>لا يوجد</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_tent_school')
                                        {{-- Tent School Dropdown - هل يكمل تعليمه في خيمة مدرسية --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر</option>
                                            <option value="نعم" {{ $value == 'نعم' ? 'selected' : '' }}>نعم</option>
                                            <option value="لا" {{ $value == 'لا' ? 'selected' : '' }}>لا</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_psychological_state')
                                        {{-- Psychological State Dropdown - الحالة النفسية --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر الحالة النفسية</option>
                                            <option value="مستقرة" {{ $value == 'مستقرة' ? 'selected' : '' }}>مستقرة</option>
                                            <option value="قلق" {{ $value == 'قلق' ? 'selected' : '' }}>قلق</option>
                                            <option value="اكتئاب" {{ $value == 'اكتئاب' ? 'selected' : '' }}>اكتئاب</option>
                                            <option value="صدمة نفسية" {{ $value == 'صدمة نفسية' ? 'selected' : '' }}>صدمة نفسية</option>
                                            <option value="بحاجة متابعة" {{ $value == 'بحاجة متابعة' ? 'selected' : '' }}>بحاجة متابعة</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_behavioral_state')
                                        {{-- Behavioral State Dropdown - الحالة السلوكية --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر الحالة السلوكية</option>
                                            <option value="جيدة" {{ $value == 'جيدة' ? 'selected' : '' }}>جيدة</option>
                                            <option value="عدوانية" {{ $value == 'عدوانية' ? 'selected' : '' }}>عدوانية</option>
                                            <option value="انطوائية" {{ $value == 'انطوائية' ? 'selected' : '' }}>انطوائية</option>
                                            <option value="فرط حركة" {{ $value == 'فرط حركة' ? 'selected' : '' }}>فرط حركة</option>
                                            <option value="طبيعية" {{ $value == 'طبيعية' ? 'selected' : '' }}>طبيعية</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_orphan_behavior')
                                        {{-- Orphan Behavior Dropdown - سلوك اليتيم --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر سلوك اليتيم</option>
                                            <option value="ملتزم" {{ $value == 'ملتزم' ? 'selected' : '' }}>ملتزم</option>
                                            <option value="متوسط" {{ $value == 'متوسط' ? 'selected' : '' }}>متوسط</option>
                                            <option value="غير منضبط" {{ $value == 'غير منضبط' ? 'selected' : '' }}>غير منضبط</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_religious_commitment')
                                        {{-- Religious Commitment Dropdown - الإلتزام الديني --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر الإلتزام الديني</option>
                                            <option value="ملتزم" {{ $value == 'ملتزم' ? 'selected' : '' }}>ملتزم</option>
                                            <option value="متوسط" {{ $value == 'متوسط' ? 'selected' : '' }}>متوسط</option>
                                            <option value="ضعيف" {{ $value == 'ضعيف' ? 'selected' : '' }}>ضعيف</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_quran_memorization')
                                        {{-- Quran Memorization Dropdown - مقدار حفظه للقرآن --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر مقدار الحفظ</option>
                                            <option value="لا يحفظ" {{ $value == 'لا يحفظ' ? 'selected' : '' }}>لا يحفظ</option>
                                            <option value="جزء" {{ $value == 'جزء' ? 'selected' : '' }}>جزء</option>
                                            <option value="2–5 أجزاء" {{ $value == '2–5 أجزاء' ? 'selected' : '' }}>2–5 أجزاء</option>
                                            <option value="أكثر من 5 أجزاء" {{ $value == 'أكثر من 5 أجزاء' ? 'selected' : '' }}>أكثر من 5 أجزاء</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_prayer_commitment')
                                        {{-- Prayer Commitment Dropdown - التزام اليتيم بالصلاة --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر مستوى الالتزام</option>
                                            <option value="دائمًا" {{ $value == 'دائمًا' ? 'selected' : '' }}>دائمًا</option>
                                            <option value="أحيانًا" {{ $value == 'أحيانًا' ? 'selected' : '' }}>أحيانًا</option>
                                            <option value="نادرًا" {{ $value == 'نادرًا' ? 'selected' : '' }}>نادرًا</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_receives_treatment')
                                        {{-- Receives Treatment Dropdown - هل يتلقى العلاج --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            id="field_receives_treatment"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            onchange="toggleTreatmentCost(this.value)"
                                        >
                                            <option value="">اختر</option>
                                            <option value="لا" {{ $value == 'لا' ? 'selected' : '' }}>لا</option>
                                            <option value="نعم" {{ $value == 'نعم' ? 'selected' : '' }}>نعم</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_treatment_cost')
                                        {{-- Treatment Cost Dropdown - تكاليف العلاج (يظهر فقط عند اختيار نعم في هل يتلقى العلاج) --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر تكاليف العلاج</option>
                                            <option value="لا يوجد" {{ $value == 'لا يوجد' ? 'selected' : '' }}>لا يوجد</option>
                                            <option value="بسيطة" {{ $value == 'بسيطة' ? 'selected' : '' }}>بسيطة</option>
                                            <option value="متوسطة" {{ $value == 'متوسطة' ? 'selected' : '' }}>متوسطة</option>
                                            <option value="مرتفعة" {{ $value == 'مرتفعة' ? 'selected' : '' }}>مرتفعة</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_family_sick_member')
                                        {{-- Family Sick Member Dropdown - هل يوجد أحد من افراد الأسرة مريض --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            id="field_family_sick_member"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            onchange="toggleFamilyDiseaseCost(this.value)"
                                        >
                                            <option value="">اختر</option>
                                            <option value="لا" {{ $value == 'لا' ? 'selected' : '' }}>لا</option>
                                            <option value="نعم" {{ $value == 'نعم' ? 'selected' : '' }}>نعم</option>
                                        </select>
                                    @elseif($field['db_column'] == 'field_guardian_health')
                                        {{-- Guardian Health Status Dropdown - حالته الصحية --}}
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
                                    @elseif($field['db_column'] == 'field_guardian_job')
                                        {{-- Guardian Job Dropdown - وظيفته --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر الحالة</option>
                                            @foreach($employmentStatuses as $employment)
                                                <option value="{{ $employment->description }}"
                                                    {{ $value == $employment->description ? 'selected' : '' }}>
                                                    {{ $employment->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_orphan_needs')
                                        {{-- Orphan Needs Dropdown - احتياجات المكفول --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر احتياج المكفول</option>
                                            @foreach($orphanNeeds as $need)
                                                <option value="{{ $need->description }}"
                                                    {{ $value == $need->description ? 'selected' : '' }}>
                                                    {{ $need->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_creativity_aspects')
                                        {{-- Creativity Aspects Dropdown - جوانب الإبداع --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر جانب الإبداع</option>
                                            @foreach($creativityAspects as $aspect)
                                                <option value="{{ $aspect->description }}"
                                                    {{ $value == $aspect->description ? 'selected' : '' }}>
                                                    {{ $aspect->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_dependents_female' || $field['db_column'] == 'field_dependents_male')
                                        {{-- Dependents Number Fields - حقول عددية --}}
                                        <input
                                            type="number"
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-control"
                                            value="{{ old('fields.' . $field['db_column'], $value) }}"
                                            min="0"
                                            placeholder="أدخل {{ $field['display_name'] }}"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                    @elseif($field['db_column'] == 'field_sponsorship_impact')
                                        {{-- Sponsorship Impact Dropdown - تأثير الكفالة --}}
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        >
                                            <option value="">اختر تأثير الكفالة</option>
                                            <option value="إيجابي جدًا" {{ $value == 'إيجابي جدًا' ? 'selected' : '' }}>إيجابي جدًا</option>
                                            <option value="إيجابي" {{ $value == 'إيجابي' ? 'selected' : '' }}>إيجابي</option>
                                            <option value="متوسط" {{ $value == 'متوسط' ? 'selected' : '' }}>متوسط</option>
                                            <option value="ضعيف" {{ $value == 'ضعيف' ? 'selected' : '' }}>ضعيف</option>
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
                                    @elseif($field['db_column'] == 'field_guardian_relationship')
                                        {{-- Guardian Relationship Dropdown - صلة القرابة --}}
                                        @php
                                            $currentRelationship = $fieldValues['field_guardian_relationship'] ?? '';
                                        @endphp
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            id="guardian_relationship"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            onchange="handleRelationshipChange(this.value)"
                                        >
                                            <option value="">اختر صلة القرابة</option>
                                            @foreach($categoryOfRelations as $relation)
                                                <option value="{{ $relation->id }}"
                                                    {{ $currentRelationship == $relation->id ? 'selected' : '' }}>
                                                    {{ $relation->attribute }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['db_column'] == 'field_data_relationship')
                                        {{-- Guardian Data Relationship Dropdown - صلة قرابة المعيل --}}
                                        @php
                                            $currentDataRelationship = $fieldValues['field_data_relationship'] ?? $fieldValues['field_guardian_relationship'] ?? '';
                                        @endphp
                                        <select
                                            name="fields[{{ $field['db_column'] }}]"
                                            class="form-select"
                                            id="data_relationship"
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            onchange="handleRelationshipChange(this.value)"
                                        >
                                            <option value="">اختر صلة القرابة</option>
                                            @foreach($categoryOfRelations as $relation)
                                                <option value="{{ $relation->id }}"
                                                    {{ $currentDataRelationship == $relation->id ? 'selected' : '' }}>
                                                    {{ $relation->attribute }}
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
                                    @elseif($field['db_column'] == 'field_guardian_id_owner')
                                        {{-- حقل رقم هوية صاحب الحساب مع البحث في قاعدة البيانات المركزية --}}
                                        <div class="input-group">
                                            <input
                                                type="text"
                                                name="fields[{{ $field['db_column'] }}]"
                                                id="bank_owner_identity"
                                                class="form-control"
                                                value="{{ old('fields.' . $field['db_column'], $value) }}"
                                                placeholder="أدخل رقم الهوية (9 أرقام) للبحث في البيانات المركزية"
                                                pattern="[0-9]{9,10}"
                                                maxlength="10"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, ''); searchBankOwnerInCivil(this.value);"
                                                {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            >
                                            <span class="input-group-text bg-light" id="bank_owner_spinner" style="display: none;">
                                                <i class="bi bi-hourglass-split text-primary"></i>
                                            </span>
                                        </div>
                                        <div id="bank_owner_search_status" class="mt-1" style="display: none;">
                                            <small class="text-info">
                                                <i class="bi bi-hourglass-split me-1"></i>
                                                <span id="bank_owner_status_text">جاري البحث...</span>
                                            </small>
                                        </div>
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
                                @endif
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
                                        {{-- حقل رقم الهوية مع البحث في قاعدة البيانات المركزية --}}
                                        <div class="col-12 mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="bi bi-card-text me-1"></i>
                                                رقم هوية فرد الأسرة
                                            </label>
                                            <div class="input-group">
                                                <input type="text"
                                                       name="family_members[{{ $index }}][identity_number]"
                                                       id="family_member_identity_{{ $index }}"
                                                       class="form-control"
                                                       value="{{ $member->person_id ?? '' }}"
                                                       placeholder="أدخل رقم الهوية (9 أرقام) "
                                                       pattern="[0-9]{9,10}"
                                                       maxlength="10"
                                                       oninput="this.value = this.value.replace(/[^0-9]/g, ''); searchFamilyMemberInCivil({{ $index }}, this.value);">
                                                <span class="input-group-text bg-light" id="family_member_spinner_{{ $index }}" style="display: none;">
                                                    <i class="bi bi-hourglass-split text-primary"></i>
                                                </span>
                                            </div>
                                            <div id="family_member_search_status_{{ $index }}" class="mt-1" style="display: none;">
                                                <small class="text-info">
                                                    <i class="bi bi-hourglass-split me-1"></i>
                                                    <span id="family_member_status_text_{{ $index }}">جاري البحث...</span>
                                                </small>
                                            </div>
                                        </div>

                                        {{-- 4 حقول منفصلة للاسم --}}
                                        <div class="col-12 mb-3">
                                            <label class="form-label fw-bold">اسم فرد الأسرة</label>
                                            <div class="row g-2">
                                                <div class="col-md-3 col-6">
                                                    <label class="small text-muted">الاسم الأول</label>
                                                    <input type="text" name="family_members[{{ $index }}][first_name]"
                                                           id="family_member_first_name_{{ $index }}"
                                                           class="form-control"
                                                           value="{{ $member->first_name ?? '' }}"
                                                           placeholder="الاسم الأول">
                                                </div>
                                                <div class="col-md-3 col-6">
                                                    <label class="small text-muted">اسم الأب</label>
                                                    <input type="text" name="family_members[{{ $index }}][second_name]"
                                                           id="family_member_second_name_{{ $index }}"
                                                           class="form-control"
                                                           value="{{ $member->second_name ?? '' }}"
                                                           placeholder="اسم الأب">
                                                </div>
                                                <div class="col-md-3 col-6">
                                                    <label class="small text-muted">اسم الجد</label>
                                                    <input type="text" name="family_members[{{ $index }}][third_name]"
                                                           id="family_member_third_name_{{ $index }}"
                                                           class="form-control"
                                                           value="{{ $member->third_name ?? '' }}"
                                                           placeholder="اسم الجد">
                                                </div>
                                                <div class="col-md-3 col-6">
                                                    <label class="small text-muted">اسم العائلة</label>
                                                    <input type="text" name="family_members[{{ $index }}][last_name]"
                                                           id="family_member_last_name_{{ $index }}"
                                                           class="form-control"
                                                           value="{{ $member->last_name ?? '' }}"
                                                           placeholder="اسم العائلة">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">تاريخ الميلاد (للأخ/الأخت)</label>
                                            <input type="date" name="family_members[{{ $index }}][birthdate]"
                                                   id="family_member_birthdate_{{ $index }}"
                                                   class="form-control"
                                                   value="{{ $member->person_birth_date }}">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">الجنس</label>
                                            <select name="family_members[{{ $index }}][gender]"
                                                    id="family_member_gender_{{ $index }}"
                                                    class="form-select">
                                                <option value="">اختر الجنس</option>
                                                <option value="1" {{ $member->person_gender == 1 ? 'selected' : '' }}>ذكر</option>
                                                <option value="2" {{ $member->person_gender == 2 ? 'selected' : '' }}>أنثى</option>
                                            </select>
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

                {{-- قسم المرفقات --}}
                <div class="card card-custom mb-4">
                    <div class="card-body">
                        <div class="category-title">
                            <i class="bi bi-paperclip field-icon"></i>
                            المرفقات والوثائق
                        </div>

                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            يرجى رفع المستندات المطلوبة (صور أو فيديوهات). يمكنك استخدام الكاميرا مباشرة أو اختيار ملف من جهازك.
                        </div>

                        <div id="attachments-container">
                            @if(isset($documentTypes) && $documentTypes->count() > 0)
                                @foreach($documentTypes as $index => $docType)
                                    <div class="attachment-card card mb-3 p-3 border-start border-4 border-primary" data-doc-type-id="{{ $docType->id }}">
                                        <div class="mb-3">
                                            <h5 class="text-primary mb-2">
                                                <i class="bi bi-file-earmark-text me-2"></i>
                                                {{ $docType->description }}
                                                @if($docType->pref)
                                                    <span class="badge bg-secondary">{{ $docType->pref }}</span>
                                                @endif
                                            </h5>
                                        </div>

                                        <div class="row">
                                            {{-- عرض المرفقات الموجودة كصور نائبة فقط --}}
                                            @if(isset($existingAttachments[$docType->id]) && count($existingAttachments[$docType->id]) > 0)
                                                <div class="col-12 mb-3">
                                                    <div class="existing-files border rounded p-3 bg-light">
                                                        <p class="mb-2 fw-bold"><i class="bi bi-cloud-check me-2"></i>الملفات المرفوعة على Google Drive:</p>
                                                        <div class="row">
                                                            @foreach($existingAttachments[$docType->id] as $attachment)
                                                                @php
                                                                    $fileExtension = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                                                                    if (empty($fileExtension)) {
                                                                        $fileExtension = strtolower(pathinfo($attachment->stored_file_name ?? '', PATHINFO_EXTENSION));
                                                                    }
                                                                    $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                                    $isVideo = in_array($fileExtension, ['mp4', 'avi', 'mov', 'wmv', 'webm']);
                                                                @endphp
                                                                <div class="col-md-3 col-6 mb-2">
                                                                    <div class="file-placeholder text-center p-3 border rounded" style="background: linear-gradient(135deg, #e8f4f8 0%, #d4edda 100%); min-height: 120px;">
                                                                        @if($isImage)
                                                                            <i class="bi bi-image text-success" style="font-size: 3rem;"></i>
                                                                            <p class="mb-0 mt-2 small text-success fw-bold">
                                                                                <i class="bi bi-check-circle me-1"></i>صورة مرفوعة
                                                                            </p>
                                                                        @elseif($isVideo)
                                                                            <i class="bi bi-camera-video text-primary" style="font-size: 3rem;"></i>
                                                                            <p class="mb-0 mt-2 small text-primary fw-bold">
                                                                                <i class="bi bi-check-circle me-1"></i>فيديو مرفوع
                                                                            </p>
                                                                        @else
                                                                            <i class="bi bi-file-earmark text-secondary" style="font-size: 3rem;"></i>
                                                                            <p class="mb-0 mt-2 small text-secondary fw-bold">
                                                                                <i class="bi bi-check-circle me-1"></i>ملف مرفوع
                                                                            </p>
                                                                        @endif
                                                                        <small class="text-muted d-block mt-1">{{ $attachment->stored_file_name ?? 'ملف' }}</small>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- حقول رفع الملفات الجديدة --}}
                                            <div class="col-md-12 mb-3">
                                                <div class="upload-buttons-group d-flex gap-2 mb-3">
                                                    {{-- زر الكاميرا للصور --}}
                                                    <button type="button"
                                                            class="btn btn-outline-primary flex-fill"
                                                            onclick="openCameraForPhoto({{ $docType->id }})">
                                                        <i class="bi bi-camera-fill me-2"></i>
                                                        التقاط صورة
                                                    </button>

                                                    {{-- زر الكاميرا للفيديو --}}
                                                    <button type="button"
                                                            class="btn btn-outline-danger flex-fill"
                                                            onclick="openCameraForVideo({{ $docType->id }})">
                                                        <i class="bi bi-camera-video-fill me-2"></i>
                                                        تسجيل فيديو
                                                    </button>

                                                    {{-- زر اختيار ملف --}}
                                                    <button type="button"
                                                            class="btn btn-outline-success flex-fill"
                                                            onclick="document.getElementById('file-{{ $docType->id }}').click()">
                                                        <i class="bi bi-folder2-open me-2"></i>
                                                        اختيار من الملفات
                                                    </button>
                                                </div>

                                                {{-- حقل الإدخال المخفي --}}
                                                <input type="file"
                                                       id="file-{{ $docType->id }}"
                                                       name="attachments[{{ $docType->id }}][]"
                                                       class="d-none"
                                                       accept="image/*,video/*"
                                                       multiple
                                                       onchange="previewFiles(this, {{ $docType->id }})">

                                                {{-- منطقة المعاينة --}}
                                                <div id="preview-{{ $docType->id }}" class="preview-container mt-3 d-none">
                                                    <div class="border rounded p-3 bg-light">
                                                        <p class="mb-2 fw-bold"><i class="bi bi-eye me-2"></i>معاينة الملفات الجديدة:</p>
                                                        <div id="preview-grid-{{ $docType->id }}" class="row"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    لا توجد أنواع مرفقات مفعلة حالياً.
                                </div>
                            @endif
                        </div>
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
        // التحكم في إظهار/إخفاء حقول العلاج (تكاليف العلاج + الوضع الصحي للمكفول)
        function toggleTreatmentCost(value) {
            const treatmentCostWrapper = document.getElementById('treatment_cost_field_wrapper');
            const orphanHealthWrapper = document.getElementById('orphan_health_field_wrapper');

            if (value === 'نعم') {
                if (treatmentCostWrapper) treatmentCostWrapper.style.display = '';
                if (orphanHealthWrapper) orphanHealthWrapper.style.display = '';
            } else {
                if (treatmentCostWrapper) treatmentCostWrapper.style.display = 'none';
                if (orphanHealthWrapper) orphanHealthWrapper.style.display = 'none';
            }
        }

        // التحكم في إظهار/إخفاء حقل نوع المرض والتكاليف
        function toggleFamilyDiseaseCost(value) {
            const wrapper = document.getElementById('family_disease_cost_field_wrapper');
            if (wrapper) {
                if (value === 'نعم') {
                    wrapper.style.display = '';
                } else {
                    wrapper.style.display = 'none';
                }
            }
        }

        // تهيئة حالة الحقول المشروطة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // تهيئة حقل تكاليف العلاج والوضع الصحي
            const receivesTreatmentSelect = document.getElementById('field_receives_treatment');
            if (receivesTreatmentSelect) {
                toggleTreatmentCost(receivesTreatmentSelect.value);
            }

            // تهيئة حقل نوع المرض والتكاليف
            const familySickMemberSelect = document.getElementById('field_family_sick_member');
            if (familySickMemberSelect) {
                toggleFamilyDiseaseCost(familySickMemberSelect.value);
            }
        });

        // التعامل مع تغيير صلة القرابة
        function handleRelationshipChange(relationshipId) {
            // id = 2 يمثل "أم"
            const isMotherRelationship = (relationshipId == '2');

            // البحث عن جميع حقول الأم وإخفائها/عرضها
            const motherFieldNames = [
                'field_mother_first_name', 'field_mother_second_name',
                'field_mother_third_name', 'field_mother_last_name',
                'field_mother_id', 'field_mother_death_date', 'field_mother_death_reason'
            ];

            motherFieldNames.forEach(function(fieldName) {
                // البحث عن الحقل بالاسم
                const inputs = document.querySelectorAll('[name*="' + fieldName + '"]');
                inputs.forEach(function(input) {
                    // الصعود للعنصر الأب (div.col-md-6 أو div.col-12)
                    let container = input.closest('.col-md-6, .col-12');
                    if (container) {
                        container.style.display = isMotherRelationship ? 'none' : 'block';
                    }
                });

                // البحث عن حقول الأسماء المنفصلة (names[mother][...])
                const nameInputs = document.querySelectorAll('[name^="names[mother]"]');
                nameInputs.forEach(function(input) {
                    let container = input.closest('.col-12');
                    if (container) {
                        container.style.display = isMotherRelationship ? 'none' : 'block';
                    }
                });
            });

            // إظهار رسالة توضيحية
            if (isMotherRelationship) {
                console.log('صلة القرابة: أم - تم إخفاء حقول الأم المتوفية');
            }
        }

        // البحث في قاعدة البيانات المركزية عند إدخال رقم الهوية
        function searchCivilRegistry(identityNumber, personType) {
            if (!identityNumber || identityNumber.length < 9) {
                return;
            }

            fetch('{{ route("user.search-civil-registry") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    identity_number: identityNumber,
                    person_type: personType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fillNameFieldsFromCivilRegistry(data.data, personType);
                }
            })
            .catch(error => {
                console.error('خطأ في البحث في قاعدة البيانات المركزية:', error);
            });
        }

        // ملء حقول الاسم من قاعدة البيانات المركزية
        function fillNameFieldsFromCivilRegistry(data, personType) {
            if (personType === 'father') {
                setInputValue('names[father][first_name]', data.first_name);
                setInputValue('names[father][second_name]', data.second_name);
                setInputValue('names[father][third_name]', data.third_name);
                setInputValue('names[father][last_name]', data.last_name);
            } else if (personType === 'mother') {
                setInputValue('names[mother][first_name]', data.first_name);
                setInputValue('names[mother][second_name]', data.second_name);
                setInputValue('names[mother][third_name]', data.third_name);
                setInputValue('names[mother][last_name]', data.last_name);
            }

            Swal.fire({
                icon: 'success',
                title: 'تم جلب البيانات',
                text: 'تم جلب بيانات الاسم من قاعدة البيانات المركزية',
                timer: 2000,
                showConfirmButton: false
            });
        }

        // دالة مساعدة لتعيين قيمة حقل
        function setInputValue(name, value) {
            const input = document.querySelector('[name="' + name + '"]');
            if (input && value) {
                input.value = value;
            }
        }

        // إضافة مستمعي أحداث لحقول رقم هوية الأب والأم
        document.addEventListener('DOMContentLoaded', function() {
            // حقل رقم هوية الأب
            const fatherIdInput = document.querySelector('[name="fields[field_father_id]"]');
            if (fatherIdInput) {
                fatherIdInput.addEventListener('blur', function() {
                    searchCivilRegistry(this.value, 'father');
                });
            }

            // حقل رقم هوية الأم
            const motherIdInput = document.querySelector('[name="fields[field_mother_id]"]');
            if (motherIdInput) {
                motherIdInput.addEventListener('blur', function() {
                    searchCivilRegistry(this.value, 'mother');
                });
            }

            // تطبيق حالة صلة القرابة عند التحميل
            const relationshipSelect = document.getElementById('guardian_relationship');
            if (relationshipSelect && relationshipSelect.value) {
                handleRelationshipChange(relationshipSelect.value);
            }

            // تطبيق حالة صلة القرابة للمعيل التفصيلي
            const dataRelationshipSelect = document.getElementById('data_relationship');
            if (dataRelationshipSelect && dataRelationshipSelect.value) {
                handleRelationshipChange(dataRelationshipSelect.value);
            }
        });

        // === دوال البحث عن المعيل في قاعدة البيانات المركزية ===
        let guardianSearchTimeout = null;

        // التعامل مع إدخال رقم هوية المعيل
        function handleGuardianIdentityInput(input) {
            const value = input.value.replace(/\D/g, ''); // إزالة غير الأرقام
            input.value = value;

            // إظهار حالة البحث عند إدخال 9 أرقام
            if (value.length >= 9) {
                clearTimeout(guardianSearchTimeout);
                guardianSearchTimeout = setTimeout(function() {
                    searchGuardianInCivilRegistry();
                }, 500); // انتظار 500ms قبل البحث
            }
        }

        // البحث عن المعيل في قاعدة البيانات المركزية
        function searchGuardianInCivilRegistry() {
            const identityInput = document.getElementById('guardian_identity_search');
            const statusDiv = document.getElementById('guardianSearchStatus');
            const statusText = document.getElementById('searchStatusText');
            const searchBtn = document.getElementById('searchGuardianBtn');

            if (!identityInput) return;

            const identityNumber = identityInput.value.trim();

            if (identityNumber.length < 9) {
                Swal.fire({
                    icon: 'warning',
                    title: 'رقم هوية غير صالح',
                    text: 'يجب إدخال 9 أرقام على الأقل',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }

            // إظهار حالة البحث
            statusDiv.style.display = 'block';
            statusText.textContent = 'جاري البحث عن بيانات المعيل في قاعدة البيانات المركزية...';
            identityInput.classList.remove('search-success', 'search-error');
            if (searchBtn) searchBtn.disabled = true;

            fetch('{{ route("user.search-civil-registry") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    identity_number: identityNumber,
                    person_type: 'guardian'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (searchBtn) searchBtn.disabled = false;

                if (data.success) {
                    identityInput.classList.add('search-success');
                    statusText.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>تم العثور على البيانات وتعبئة الحقول</span>';

                    // ملء حقول المعيل
                    fillGuardianFieldsFromCivilRegistry(data.data);

                    setTimeout(function() {
                        statusDiv.style.display = 'none';
                    }, 3000);
                } else {
                    identityInput.classList.add('search-error');
                    statusText.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (data.message || 'لم يتم العثور على بيانات') + '</span>';
                }
            })
            .catch(error => {
                if (searchBtn) searchBtn.disabled = false;
                identityInput.classList.add('search-error');
                statusText.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>حدث خطأ أثناء البحث</span>';
                console.error('خطأ في البحث:', error);
            });
        }

        // ملء حقول المعيل من قاعدة البيانات المركزية
        function fillGuardianFieldsFromCivilRegistry(data) {
            // حقول الاسم
            setInputValueWithHighlight('fields[field_data_first_name]', data.first_name);
            setInputValueWithHighlight('fields[field_data_father_name]', data.second_name);
            setInputValueWithHighlight('fields[field_data_grand_father_name]', data.third_name);
            setInputValueWithHighlight('fields[field_data_family_name]', data.last_name);

            // تاريخ الميلاد
            if (data.birth_date) {
                setInputValueWithHighlight('fields[field_data_birth_date]', data.birth_date);
            }

            // المدينة
            if (data.city) {
                setSelectValueWithHighlight('fields[field_data_city]', data.city);
            }

            Swal.fire({
                icon: 'success',
                title: 'تم جلب البيانات',
                text: 'تم جلب بيانات الشخص',
                timer: 2500,
                showConfirmButton: false
            });
        }

        // متغير لتتبع عملية البحث التلقائي للمتوفين
        let deceasedSearchTimeout = {};
        let deceasedSearchInProgress = {};
        let deceasedLastSearchedId = {}; // لتتبع آخر رقم هوية تم البحث عنه بنجاح

        // دالة البحث التلقائي عند إدخال رقم الهوية
        function autoSearchDeceased(personType, value) {
            // إلغاء أي بحث سابق
            if (deceasedSearchTimeout[personType]) {
                clearTimeout(deceasedSearchTimeout[personType]);
            }

            // مسح الحقول إذا كان الرقم غير مكتمل
            if (value.length < 9) {
                const spinner = document.getElementById('deceased_' + personType + '_spinner');
                if (spinner) spinner.style.display = 'none';
                return;
            }

            // إذا كان البحث جاري بالفعل أو تم البحث عن نفس الرقم سابقاً، لا تبدأ بحث جديد
            if (deceasedSearchInProgress[personType] || deceasedLastSearchedId[personType] === value) {
                return;
            }

            // تأخير قصير للتأكد من اكتمال الإدخال
            deceasedSearchTimeout[personType] = setTimeout(function() {
                searchDeceasedInCivilRegistry(personType);
            }, 300);
        }

        // دالة البحث عن المتوفين (أب/أم) في قاعدة البيانات المركزية
        function searchDeceasedInCivilRegistry(personType) {
            const identityInput = document.getElementById('deceased_' + personType + '_id');
            const spinner = document.getElementById('deceased_' + personType + '_spinner');

            if (!identityInput) return;

            const identityNumber = identityInput.value.trim();

            if (identityNumber.length < 9) {
                return;
            }

            // تفعيل حالة البحث
            deceasedSearchInProgress[personType] = true;
            if (spinner) spinner.style.display = 'flex';
            identityInput.classList.remove('search-success', 'search-error');

            fetch('{{ route("user.search-civil-registry") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    identity_number: identityNumber,
                    person_type: personType
                })
            })
            .then(response => response.json())
            .then(data => {
                deceasedSearchInProgress[personType] = false;
                const spinner = document.getElementById('deceased_' + personType + '_spinner');
                if (spinner) spinner.style.display = 'none';

                const identityInput = document.getElementById('deceased_' + personType + '_id');

                if (data.success) {
                    // حفظ رقم الهوية الذي تم البحث عنه بنجاح
                    deceasedLastSearchedId[personType] = identityNumber;

                    if (identityInput) {
                        identityInput.classList.remove('search-error');
                        identityInput.classList.add('search-success');
                    }

                    // ملء حقول المتوفي
                    fillDeceasedFieldsFromCivilRegistry(personType, data.data);
                } else {
                    if (identityInput) {
                        identityInput.classList.remove('search-success');
                        identityInput.classList.add('search-error');
                    }
                }
            })
            .catch(error => {
                deceasedSearchInProgress[personType] = false;
                const spinner = document.getElementById('deceased_' + personType + '_spinner');
                if (spinner) spinner.style.display = 'none';

                const identityInput = document.getElementById('deceased_' + personType + '_id');
                if (identityInput) {
                    identityInput.classList.remove('search-success');
                    identityInput.classList.add('search-error');
                }
                console.error('خطأ في البحث:', error);
            });
        }

        // ملء حقول المتوفي من قاعدة البيانات المركزية
        function fillDeceasedFieldsFromCivilRegistry(personType, data) {
            // حقول الاسم
            setDeceasedInputValueWithHighlight('deceased_' + personType + '_first_name', data.first_name);
            setDeceasedInputValueWithHighlight('deceased_' + personType + '_second_name', data.second_name);
            setDeceasedInputValueWithHighlight('deceased_' + personType + '_third_name', data.third_name);
            setDeceasedInputValueWithHighlight('deceased_' + personType + '_last_name', data.last_name);

            // تاريخ الميلاد
            if (data.birth_date) {
                setDeceasedInputValueWithHighlight('deceased_' + personType + '_birth_date', data.birth_date);
            }
        }

        // دالة مساعدة لتعيين قيمة حقل متوفي مع تأثير التمييز
        function setDeceasedInputValueWithHighlight(fieldId, value) {
            const input = document.getElementById(fieldId);
            if (input && value) {
                input.value = value;
                input.classList.add('field-updated');
                setTimeout(function() {
                    input.classList.remove('field-updated');
                }, 1000);
            }
        }

        // دالة مساعدة لتعيين قيمة حقل مع تأثير التمييز
        function setInputValueWithHighlight(name, value) {
            const input = document.querySelector('[name="' + name + '"]');
            if (input && value) {
                input.value = value;
                input.classList.add('field-updated');
                setTimeout(function() {
                    input.classList.remove('field-updated');
                }, 1000);
            }
        }

        // دالة مساعدة لتعيين قيمة select مع تأثير التمييز
        function setSelectValueWithHighlight(name, value) {
            const select = document.querySelector('[name="' + name + '"]');
            if (select && value) {
                // البحث عن الخيار المطابق
                for (let option of select.options) {
                    if (option.value === value || option.text === value) {
                        select.value = option.value;
                        select.classList.add('field-updated');
                        setTimeout(function() {
                            select.classList.remove('field-updated');
                        }, 1000);
                        break;
                    }
                }
            }
        }

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

        // Attachments Management
        let cameraStream = null;
        let currentDocTypeId = null;

        // فتح الكاميرا للصور
        async function openCameraForPhoto(docTypeId) {
            currentDocTypeId = docTypeId;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false
                });

                showCameraModal(stream, 'photo', docTypeId);
            } catch (error) {
                console.error('خطأ في فتح الكاميرا:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: 'تعذر الوصول إلى الكاميرا. يرجى التأكد من منح الأذونات اللازمة.',
                    confirmButtonColor: '#667eea'
                });
            }
        }

        // فتح الكاميرا للفيديو
        async function openCameraForVideo(docTypeId) {
            currentDocTypeId = docTypeId;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: true
                });

                showCameraModal(stream, 'video', docTypeId);
            } catch (error) {
                console.error('خطأ في فتح الكاميرا:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: 'تعذر الوصول إلى الكاميرا والميكروفون. يرجى التأكد من منح الأذونات اللازمة.',
                    confirmButtonColor: '#667eea'
                });
            }
        }

        // عرض نافذة الكاميرا
        function showCameraModal(stream, type, docTypeId) {
            cameraStream = stream;
            const isVideo = type === 'video';

            const modalHtml = `
                <div id="camera-modal" class="modal fade show" style="display: block; background: rgba(0,0,0,0.8);">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-camera${isVideo ? '-video' : ''}-fill me-2"></i>
                                    ${isVideo ? 'تسجيل فيديو' : 'التقاط صورة'}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" onclick="closeCameraModal()"></button>
                            </div>
                            <div class="modal-body text-center">
                                <video id="camera-preview" autoplay playsinline style="width: 100%; max-height: 500px; border-radius: 10px;"></video>
                                ${isVideo ? '<div id="recording-indicator" class="d-none mt-2 text-danger fw-bold"><i class="bi bi-record-circle-fill me-2"></i>جاري التسجيل...</div>' : ''}
                            </div>
                            <div class="modal-footer justify-content-center">
                                ${isVideo ?
                                    '<button type="button" class="btn btn-danger btn-lg" id="record-btn" onclick="toggleRecording()"><i class="bi bi-record-circle me-2"></i>بدء التسجيل</button>' :
                                    '<button type="button" class="btn btn-primary btn-lg" onclick="capturePhoto()"><i class="bi bi-camera me-2"></i>التقاط الصورة</button>'
                                }
                                <button type="button" class="btn btn-secondary btn-lg" onclick="closeCameraModal()">إلغاء</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            document.getElementById('camera-preview').srcObject = stream;
        }

        // التقاط صورة
        function capturePhoto() {
            const video = document.getElementById('camera-preview');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            canvas.toBlob((blob) => {
                const file = new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
                addFileToInput(file, currentDocTypeId);
                closeCameraModal();

                Swal.fire({
                    icon: 'success',
                    title: 'تم!',
                    text: 'تم التقاط الصورة بنجاح',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 'image/jpeg', 0.95);
        }

        // تسجيل الفيديو
        let mediaRecorder = null;
        let recordedChunks = [];

        function toggleRecording() {
            const recordBtn = document.getElementById('record-btn');
            const indicator = document.getElementById('recording-indicator');

            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                // بدء التسجيل
                recordedChunks = [];
                mediaRecorder = new MediaRecorder(cameraStream, { mimeType: 'video/webm' });

                mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        recordedChunks.push(e.data);
                    }
                };

                mediaRecorder.onstop = () => {
                    const blob = new Blob(recordedChunks, { type: 'video/webm' });
                    const file = new File([blob], `video-${Date.now()}.webm`, { type: 'video/webm' });
                    addFileToInput(file, currentDocTypeId);
                    closeCameraModal();

                    Swal.fire({
                        icon: 'success',
                        title: 'تم!',
                        text: 'تم تسجيل الفيديو بنجاح',
                        timer: 2000,
                        showConfirmButton: false
                    });
                };

                mediaRecorder.start();
                recordBtn.innerHTML = '<i class="bi bi-stop-circle me-2"></i>إيقاف التسجيل';
                recordBtn.classList.remove('btn-danger');
                recordBtn.classList.add('btn-warning');
                indicator.classList.remove('d-none');
            } else {
                // إيقاف التسجيل
                mediaRecorder.stop();
            }
        }

        // إغلاق نافذة الكاميرا
        function closeCameraModal() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            document.getElementById('camera-modal')?.remove();
        }

        // إضافة ملف إلى الإدخال
        function addFileToInput(file, docTypeId) {
            const input = document.getElementById(`file-${docTypeId}`);
            const dataTransfer = new DataTransfer();

            // إضافة الملفات الموجودة
            for (let i = 0; i < input.files.length; i++) {
                dataTransfer.items.add(input.files[i]);
            }

            // إضافة الملف الجديد
            dataTransfer.items.add(file);
            input.files = dataTransfer.files;

            // تحديث المعاينة
            previewFiles(input, docTypeId);
        }

        // معاينة الملفات
        function previewFiles(input, docTypeId) {
            const previewContainer = document.getElementById(`preview-${docTypeId}`);
            const previewGrid = document.getElementById(`preview-grid-${docTypeId}`);

            if (input.files.length > 0) {
                previewContainer.classList.remove('d-none');
                previewGrid.innerHTML = '';

                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const col = document.createElement('div');
                        col.className = 'col-md-3 mb-2';

                        let content = '';
                        if (file.type.startsWith('image/')) {
                            content = `<img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;">`;
                        } else if (file.type.startsWith('video/')) {
                            content = `<video class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;" controls><source src="${e.target.result}" type="${file.type}"></video>`;
                        }

                        col.innerHTML = `
                            <div class="position-relative">
                                ${content}
                                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removePreviewFile(${docTypeId}, ${index})">
                                    <i class="bi bi-x"></i>
                                </button>
                                <small class="d-block text-center mt-1 text-muted">${file.name}</small>
                            </div>
                        `;
                        previewGrid.appendChild(col);
                    };
                    reader.readAsDataURL(file);
                });
            } else {
                previewContainer.classList.add('d-none');
            }
        }

        // حذف ملف من المعاينة
        function removePreviewFile(docTypeId, fileIndex) {
            const input = document.getElementById(`file-${docTypeId}`);
            const dataTransfer = new DataTransfer();

            Array.from(input.files).forEach((file, index) => {
                if (index !== fileIndex) {
                    dataTransfer.items.add(file);
                }
            });

            input.files = dataTransfer.files;
            previewFiles(input, docTypeId);
        }

        // حذف مرفق موجود
        function deleteAttachment(attachmentId, button) {
            Swal.fire({
                title: 'تأكيد الحذف',
                text: 'هل أنت متأكد من حذف هذا المرفق؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
            }).then((result) => {
                if (result.isConfirmed) {
                    // يمكن إضافة AJAX هنا لحذف المرفق من السيرفر
                    button.closest('.col-md-3').remove();

                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف',
                        text: 'تم حذف المرفق بنجاح',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }

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
                        <!-- حقل رقم الهوية مع البحث في قاعدة البيانات المركزية -->
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-card-text me-1"></i>
                                رقم هوية فرد الأسرة
                            </label>
                            <div class="input-group">
                                <input type="text"
                                       name="family_members[${familyMemberIndex}][identity_number]"
                                       id="family_member_identity_${familyMemberIndex}"
                                       class="form-control"
                                       placeholder="أدخل رقم الهوية (9 أرقام) "
                                       pattern="[0-9]{9,10}"
                                       maxlength="10"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, ''); searchFamilyMemberInCivil(${familyMemberIndex}, this.value);">
                                <span class="input-group-text bg-light" id="family_member_spinner_${familyMemberIndex}" style="display: none;">
                                    <i class="bi bi-hourglass-split text-primary"></i>
                                </span>
                            </div>
                            <div id="family_member_search_status_${familyMemberIndex}" class="mt-1" style="display: none;">
                                <small class="text-info">
                                    <i class="bi bi-hourglass-split me-1"></i>
                                    <span id="family_member_status_text_${familyMemberIndex}">جاري البحث...</span>
                                </small>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">اسم فرد الأسرة</label>
                            <div class="row g-2">
                                <div class="col-md-3 col-6">
                                    <label class="small text-muted">الاسم الأول</label>
                                    <input type="text" name="family_members[${familyMemberIndex}][first_name]"
                                           id="family_member_first_name_${familyMemberIndex}"
                                           class="form-control"
                                           placeholder="الاسم الأول">
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="small text-muted">اسم الأب</label>
                                    <input type="text" name="family_members[${familyMemberIndex}][second_name]"
                                           id="family_member_second_name_${familyMemberIndex}"
                                           class="form-control"
                                           placeholder="اسم الأب">
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="small text-muted">اسم الجد</label>
                                    <input type="text" name="family_members[${familyMemberIndex}][third_name]"
                                           id="family_member_third_name_${familyMemberIndex}"
                                           class="form-control"
                                           placeholder="اسم الجد">
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="small text-muted">اسم العائلة</label>
                                    <input type="text" name="family_members[${familyMemberIndex}][last_name]"
                                           id="family_member_last_name_${familyMemberIndex}"
                                           class="form-control"
                                           placeholder="اسم العائلة">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ الميلاد (للأخ/الأخت)</label>
                            <input type="date" name="family_members[${familyMemberIndex}][birthdate]"
                                   id="family_member_birthdate_${familyMemberIndex}"
                                   class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">الجنس</label>
                            <select name="family_members[${familyMemberIndex}][gender]"
                                    id="family_member_gender_${familyMemberIndex}"
                                    class="form-select">
                                <option value="">اختر الجنس</option>
                                <option value="1">ذكر</option>
                                <option value="2">أنثى</option>
                            </select>
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

        // 🆕 دالة البحث في قاعدة البيانات المركزية لأفراد العائلة
        let familyMemberSearchTimeout = {};

        function searchFamilyMemberInCivil(index, identityNumber) {
            // تنظيف الـ timeout السابق
            if (familyMemberSearchTimeout[index]) {
                clearTimeout(familyMemberSearchTimeout[index]);
            }

            // إخفاء حالة البحث إذا كان الرقم أقل من 9 أرقام
            if (identityNumber.length < 9) {
                document.getElementById(`family_member_search_status_${index}`).style.display = 'none';
                document.getElementById(`family_member_spinner_${index}`).style.display = 'none';
                return;
            }

            // عرض حالة البحث
            document.getElementById(`family_member_search_status_${index}`).style.display = 'block';
            document.getElementById(`family_member_spinner_${index}`).style.display = 'flex';
            document.getElementById(`family_member_status_text_${index}`).textContent = 'جاري البحث في قاعدة البيانات المركزية...';
            document.getElementById(`family_member_status_text_${index}`).className = 'text-info';

            // تأخير البحث 500ms لتجنب الطلبات المتكررة
            familyMemberSearchTimeout[index] = setTimeout(() => {
                fetch(`{{ route('user.generalRegistration.searchCivilRegistry') }}?identity_number=${identityNumber}`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById(`family_member_spinner_${index}`).style.display = 'none';

                    if (data.found) {
                        // تم العثور على البيانات - تعبئة الحقول
                        document.getElementById(`family_member_status_text_${index}`).innerHTML = '<i class="bi bi-check-circle me-1"></i> تم العثور على البيانات';
                        document.getElementById(`family_member_status_text_${index}`).className = 'text-success';

                        // تعبئة الأسماء
                        const firstNameField = document.getElementById(`family_member_first_name_${index}`) ||
                                              document.querySelector(`[name="family_members[${index}][first_name]"]`);
                        const secondNameField = document.getElementById(`family_member_second_name_${index}`) ||
                                               document.querySelector(`[name="family_members[${index}][second_name]"]`);
                        const thirdNameField = document.getElementById(`family_member_third_name_${index}`) ||
                                              document.querySelector(`[name="family_members[${index}][third_name]"]`);
                        const lastNameField = document.getElementById(`family_member_last_name_${index}`) ||
                                             document.querySelector(`[name="family_members[${index}][last_name]"]`);
                        const birthdateField = document.getElementById(`family_member_birthdate_${index}`) ||
                                              document.querySelector(`[name="family_members[${index}][birthdate]"]`);
                        const genderField = document.getElementById(`family_member_gender_${index}`) ||
                                           document.querySelector(`[name="family_members[${index}][gender]"]`);

                        if (firstNameField && data.first_name) firstNameField.value = data.first_name;
                        if (secondNameField && data.second_name) secondNameField.value = data.second_name;
                        if (thirdNameField && data.third_name) thirdNameField.value = data.third_name;
                        if (lastNameField && data.last_name) lastNameField.value = data.last_name;
                        if (birthdateField && data.birth_date) birthdateField.value = data.birth_date;
                        if (genderField && data.gender) genderField.value = data.gender;

                    } else {
                        // لم يتم العثور على البيانات
                        document.getElementById(`family_member_status_text_${index}`).innerHTML = '<i class="bi bi-x-circle me-1"></i> لم يتم العثور على البيانات - يمكنك إدخالها يدوياً';
                        document.getElementById(`family_member_status_text_${index}`).className = 'text-warning';
                    }
                })
                .catch(error => {
                    console.error('Error searching civil registry:', error);
                    document.getElementById(`family_member_spinner_${index}`).style.display = 'none';
                    document.getElementById(`family_member_status_text_${index}`).innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> حدث خطأ في البحث';
                    document.getElementById(`family_member_status_text_${index}`).className = 'text-danger';
                });
            }, 500);
        }

        // 🆕 دالة البحث في قاعدة البيانات المركزية لصاحب الحساب البنكي
        let bankOwnerSearchTimeout = null;

        function searchBankOwnerInCivil(identityNumber) {
            // تنظيف الـ timeout السابق
            if (bankOwnerSearchTimeout) {
                clearTimeout(bankOwnerSearchTimeout);
            }

            // إخفاء حالة البحث إذا كان الرقم أقل من 9 أرقام
            if (identityNumber.length < 9) {
                document.getElementById('bank_owner_search_status').style.display = 'none';
                document.getElementById('bank_owner_spinner').style.display = 'none';
                return;
            }

            // عرض حالة البحث
            document.getElementById('bank_owner_search_status').style.display = 'block';
            document.getElementById('bank_owner_spinner').style.display = 'flex';
            document.getElementById('bank_owner_status_text').textContent = 'جاري البحث في قاعدة البيانات المركزية ...';
            document.getElementById('bank_owner_status_text').className = 'text-info';

            // تأخير البحث 500ms لتجنب الطلبات المتكررة
            bankOwnerSearchTimeout = setTimeout(() => {
                fetch(`{{ route('user.generalRegistration.searchCivilRegistry') }}?identity_number=${identityNumber}`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('bank_owner_spinner').style.display = 'none';

                    if (data.found) {
                        // تم العثور على البيانات - تعبئة اسم صاحب الحساب
                        document.getElementById('bank_owner_status_text').innerHTML = '<i class="bi bi-check-circle me-1"></i> تم العثور على البيانات وتعبئة الاسم';
                        document.getElementById('bank_owner_status_text').className = 'text-success';

                        // بناء الاسم الكامل
                        const fullName = [
                            data.first_name || '',
                            data.second_name || '',
                            data.third_name || '',
                            data.last_name || ''
                        ].filter(n => n.trim() !== '').join(' ');

                        // تعبئة حقل اسم صاحب الحساب
                        const ownerNameField = document.querySelector('[name="fields[field_guardian_account_owner_name]"]');
                        if (ownerNameField && fullName) {
                            ownerNameField.value = fullName;
                        }

                    } else {
                        // لم يتم العثور على البيانات
                        document.getElementById('bank_owner_status_text').innerHTML = '<i class="bi bi-x-circle me-1"></i> لم يتم العثور على البيانات - أدخل الاسم يدوياً';
                        document.getElementById('bank_owner_status_text').className = 'text-warning';
                    }
                })
                .catch(error => {
                    console.error('Error searching civil registry for bank owner:', error);
                    document.getElementById('bank_owner_spinner').style.display = 'none';
                    document.getElementById('bank_owner_status_text').innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> حدث خطأ في البحث';
                    document.getElementById('bank_owner_status_text').className = 'text-danger';
                });
            }, 500);
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


