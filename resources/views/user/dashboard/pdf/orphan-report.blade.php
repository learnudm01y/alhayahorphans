<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
@page {
    margin: 2cm;
}
/* تضمين خط Cairo للعربية */
@font-face {
    font-family: 'Cairo';
    src: url('file:///{{ str_replace("\\", "/", public_path("fonts/Cairo-Regular.ttf")) }}') format('truetype');
    font-weight: normal;
    font-style: normal;
}
@font-face {
    font-family: 'Cairo';
    src: url('file:///{{ str_replace("\\", "/", public_path("fonts/Cairo-Bold.ttf")) }}') format('truetype');
    font-weight: bold;
    font-style: normal;
}
/* استخدام خط Cairo للعربية */
body {
    font-family: 'Cairo', Arial, sans-serif;
    font-size: 14pt;
    direction: rtl;
    text-align: right;
    line-height: 1.6;
}
h1 {
    font-family: 'Cairo', Arial, sans-serif;
    text-align: center;
    color: #003366;
    font-size: 20pt;
    font-weight: bold;
    border-bottom: 2px solid #003366;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}
td, th {
    font-family: 'Cairo', Arial, sans-serif;
    border: 1px solid #cccccc;
    padding: 8px;
    text-align: center;
    vertical-align: top;
}
.header-blue {
    background-color: #0d47a1;
    color: #ffffff;
    font-weight: bold;
    font-size: 11pt;
    padding: 8px;
}
.header-pink {
    background-color: #E83E8C;
    color: #ffffff;
    font-weight: bold;
    font-size: 11pt;
    padding: 8px;
}
.header-black {
    background-color: #000000;
    color: #ffffff;
    font-weight: bold;
    font-size: 11pt;
    padding: 8px;
}
.header-gray {
    background-color: #6c757d;
    color: #ffffff;
    font-weight: bold;
    font-size: 11pt;
    padding: 8px;
}
.header-dark {
    background-color: #003366;
    color: #ffffff;
    font-weight: bold;
    font-size: 11pt;
    padding: 8px;
    width: 180px;
}
.content {
    background-color: #ffffff;
    font-size: 12pt;
    padding: 10px;
}
.content-right {
    background-color: #ffffff;
    font-size: 12pt;
    padding: 10px;
    text-align: right;
}
</style>
</head>
<body>

<h1>تقرير بيانات</h1>

@php
    $visibleFields = $visible_fields ?? [];

    $isFieldEnabled = function (string $fieldKey) use ($visibleFields) {
        return !array_key_exists($fieldKey, $visibleFields) || (bool) $visibleFields[$fieldKey];
    };

    $normalize = function ($value) {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $invalid = ['(/)', '/|\\', '/', '\\', '-', 'غير متوفر', 'null', 'NULL', 'N/A', ''];
            return in_array($trimmed, $invalid, true) ? null : $trimmed;
        }

        return $value;
    };

    $filterColumns = function (array $columns) use ($normalize) {
        $result = [];
        foreach ($columns as $column) {
            $value = $normalize($column['value'] ?? null);
            if ($value !== null) {
                $column['value'] = $value;
                $result[] = $column;
            }
        }
        return $result;
    };

    $normalizedSponsorshipImpact = $normalize($sponsorship_impact ?? null);
    $normalizedFamilyEvents = $normalize($family_events ?? null);
    $normalizedTimestamp = $normalize($timestamp ?? null);
@endphp

<!-- الصف الأول: معلومات أساسية مع صورة المكفول -->
@php
    $basicColumns = $filterColumns([
        ['label' => 'رقم الملف', 'value' => $isFieldEnabled('field_external_file_number') ? ($file_number ?? null) : null],
        ['label' => 'اسم المكفول', 'value' => $isFieldEnabled('field_sponsor_name') ? ($orphan_name ?? null) : null],
        ['label' => 'رقم الجوال', 'value' => $isFieldEnabled('field_phone') ? ($phone_number ?? null) : null],
        ['label' => 'حالة السكن السابق', 'value' => $isFieldEnabled('field_housing_status') ? ($housing_status ?? null) : null],
        ['label' => 'نوع السكن', 'value' => $isFieldEnabled('field_housing_type') ? ($housing_type ?? null) : null],
        ['label' => 'المدينة', 'value' => $isFieldEnabled('field_data_city') ? ($city ?? null) : null],
    ]);
@endphp
@if(count($basicColumns) > 0)
<table>
<tr>
@foreach($basicColumns as $column)
<td class="header-blue">{{ $column['label'] }}</td>
@endforeach
@if(!empty($orphan_photo) && $orphan_photo['file_exists'])
<td class="header-blue" rowspan="2" style="width: 120px; text-align: center;">الصورة الشخصية</td>
@endif
</tr>
<tr>
@foreach($basicColumns as $column)
<td class="content">{{ $column['value'] }}</td>
@endforeach
@if(!empty($orphan_photo) && $orphan_photo['file_exists'])
<td class="content" style="text-align: center; padding: 5px; vertical-align: middle;">
    <img src="file://{{ str_replace('\\', '/', $orphan_photo['file_path']) }}"
         style="max-width: 100px; max-height: 120px; border: 2px solid #0d6efd; border-radius: 5px;" />
</td>
@endif
</tr>
</table>
@endif

<!-- الصف الثاني: معلومات المدرسة -->
@php
    $schoolColumns = $filterColumns([
        ['label' => 'عنوان المدرسة', 'value' => $isFieldEnabled('field_school_address') ? ($school_address ?? null) : null],
        ['label' => 'المرحلة الدراسية', 'value' => $isFieldEnabled('field_grade') ? ($academic_stage ?? null) : null],
        ['label' => 'مستوى الطالب', 'value' => $isFieldEnabled('field_student_level') ? ($student_level ?? null) : null],
        ['label' => 'سبب الضعف', 'value' => $isFieldEnabled('field_weakness_reason') ? ($weakness_reason ?? null) : null],
    ]);
@endphp
@if(count($schoolColumns) > 0)
<table>
<tr>
@foreach($schoolColumns as $column)
<td class="header-blue" style="width:{{ 100 / count($schoolColumns) }}%;">{{ $column['label'] }}</td>
@endforeach
</tr>
<tr>
@foreach($schoolColumns as $column)
<td class="content">{{ $column['value'] }}</td>
@endforeach
</tr>
</table>
@endif

<!-- الصف الثالث: الحالة النفسية والسلوكية -->
@php
    $psyColumns = $filterColumns([
        ['label' => 'الحالة النفسية', 'value' => $isFieldEnabled('field_psychological_state') ? ($psychological_status ?? null) : null],
        ['label' => 'الحالة السلوكية', 'value' => $isFieldEnabled('field_behavioral_state') ? ($behavioral_status ?? null) : null],
        ['label' => 'الالتزام الديني', 'value' => $isFieldEnabled('field_religious_commitment') ? ($religious_commitment ?? null) : null],
        ['label' => 'مقدار حفظه للقرآن', 'value' => $isFieldEnabled('field_quran_memorization') ? ($quran_memorization ?? null) : null],
    ]);
@endphp
@if(count($psyColumns) > 0)
<table>
<tr>
@foreach($psyColumns as $column)
<td class="header-blue">{{ $column['label'] }}</td>
@endforeach
</tr>
<tr>
@foreach($psyColumns as $column)
<td class="content">{{ $column['value'] }}</td>
@endforeach
</tr>
</table>
@endif

<!-- الصف الرابع: الحالة الصحية والاحتياجات -->
@php
    $healthColumns = $filterColumns([
        ['label' => 'الحالة الصحية لليتيم', 'value' => $isFieldEnabled('field_health_status') ? ($orphan_health_status ?? null) : null],
        ['label' => 'احتياجات اليتيم', 'value' => $isFieldEnabled('field_orphan_needs') ? ($orphan_needs ?? null) : null],
        ['label' => 'جوانب الإبداع', 'value' => $isFieldEnabled('field_creativity_aspects') ? ($creativity_aspects ?? null) : null],
    ]);
@endphp
@if(count($healthColumns) > 0)
<table>
<tr>
@foreach($healthColumns as $column)
<td class="header-blue">{{ $column['label'] }}</td>
@endforeach
</tr>
<tr>
@foreach($healthColumns as $column)
<td class="content">{{ $column['value'] }}</td>
@endforeach
</tr>
</table>
@endif

<!-- قسم الأم - يظهر فقط إذا كانت الأم ليست المعيل -->
@php
    $isMotherNameEnabled = $isFieldEnabled('field_mother_first_name') || $isFieldEnabled('field_living_mother_first_name');
    $isMotherIdEnabled = $isFieldEnabled('field_mother_id') || $isFieldEnabled('field_living_mother_id');
    $isMotherStatusEnabled = $isFieldEnabled('field_mother_status');

    $motherColumns = $filterColumns([
        ['label' => 'اسم الأم', 'value' => $isMotherNameEnabled ? ($mother_name ?? null) : null],
        ['label' => 'رقم الهوية', 'value' => $isMotherIdEnabled ? ($mother_id ?? null) : null],
        ['label' => 'هل الأم على قيد الحياة', 'value' => $isMotherStatusEnabled ? ($mother_alive ?? null) : null],
    ]);
@endphp
@if(count($motherColumns) > 0)
<table>
<tr>
@foreach($motherColumns as $column)
<td class="header-pink" style="width:{{ 100 / count($motherColumns) }}%;">{{ $column['label'] }}</td>
@endforeach
</tr>
<tr>
@foreach($motherColumns as $column)
<td class="content" style="width:{{ 100 / count($motherColumns) }}%;">{{ $column['value'] }}</td>
@endforeach
</tr>
</table>
@endif

<!-- قسم المعيل مع الصورة الشخصية -->
@php
    $isGuardianNameEnabled =
        $isFieldEnabled('field_data_first_name') ||
        $isFieldEnabled('field_data_father_name') ||
        $isFieldEnabled('field_data_grand_father_name') ||
        $isFieldEnabled('field_data_family_name') ||
        $isFieldEnabled('field_re_guardian_name');

    $isGuardianRelationEnabled =
        $isFieldEnabled('field_data_relationship') ||
        $isFieldEnabled('field_relationship');

    $isDependentsCountEnabled =
        $isFieldEnabled('field_dependents_female') ||
        $isFieldEnabled('field_dependents_male') ||
        $isFieldEnabled('field_family_members_count');

    $guardianColumns = $filterColumns([
        ['label' => 'اسم المعيل رباعي', 'value' => $isGuardianNameEnabled ? ($guardian_full_name ?? null) : null],
        ['label' => 'صلة القرابة', 'value' => $isGuardianRelationEnabled ? ($guardian_relation ?? null) : null],
        ['label' => 'الحالة الصحية', 'value' => $isFieldEnabled('field_guardian_health') ? ($guardian_health ?? null) : null],
        ['label' => 'الوظيفة', 'value' => $isFieldEnabled('field_guardian_job') ? ($guardian_job ?? null) : null],
        ['label' => 'عدد من يعيلهم', 'value' => $isDependentsCountEnabled ? ($dependents_count ?? null) : null],
    ]);
@endphp
@if(count($guardianColumns) > 0)
<table>
<tr>
@foreach($guardianColumns as $column)
<td class="header-black" style="width:{{ $loop->first ? '35' : ((65 / max(count($guardianColumns)-1,1))) }}%;">{{ $column['label'] }}</td>
@endforeach
@if(!empty($guardian_photo) && $guardian_photo['file_exists'])
<td class="header-black" rowspan="2" style="width: 120px; text-align: center;">الصورة الشخصية</td>
@endif
</tr>
<tr>
@foreach($guardianColumns as $column)
<td class="content">{{ $column['value'] }}</td>
@endforeach
@if(!empty($guardian_photo) && $guardian_photo['file_exists'])
<td class="content" style="text-align: center; padding: 5px; vertical-align: middle;">
    <img src="file://{{ str_replace('\\', '/', $guardian_photo['file_path']) }}"
         style="max-width: 100px; max-height: 120px; border: 2px solid #000; border-radius: 5px;" />
</td>
@endif
</tr>
</table>
@endif

<!-- جدول أفراد الأسرة -->
@if(($visible_fields['field_family_members_section'] ?? true) && !empty($family_members) && count($family_members) > 0)
<table>
<tr>
<td class="header-gray" style="width:8%;">م</td>
<td class="header-gray" style="width:35%;">الاسم رباعي</td>
<td class="header-gray" style="width:19%;">تاريخ الميلاد</td>
<td class="header-gray" style="width:19%;">مستوى التعليم</td>
<td class="header-gray" style="width:19%;">الحالة الصحية</td>
</tr>
@foreach($family_members as $member)
<tr>
<td class="content">{{ $member['index'] }}</td>
<td class="content">{{ $member['full_name'] }}</td>
<td class="content">{{ $member['birth_date'] }}</td>
<td class="content">{{ $member['academic_degree'] }}</td>
<td class="content">{{ $member['health_status'] }}</td>
</tr>
@endforeach
</table>
@endif

<!-- تأثير الكفالة على الأيتام -->
@if(($visible_fields['field_sponsorship_impact'] ?? true) && !empty($normalizedSponsorshipImpact))
<table>
<tr>
<td class="header-dark">تأثير الكفالة على الأيتام</td>
<td class="content-right">{{ $normalizedSponsorshipImpact }}</td>
</tr>
</table>
@endif

<!-- أهم الأحداث التي مرت بها الأسرة -->
@if(($visible_fields['field_important_events'] ?? true) && !empty($normalizedFamilyEvents))
<table>
<tr>
<td class="header-dark">اهم الأحداث التي مرت بها الأسرة</td>
<td class="content-right">{{ $normalizedFamilyEvents }}</td>
</tr>
</table>
@endif

<!-- طابع زمني -->
@if(($visible_fields['field_data_update_date'] ?? true) && !empty($normalizedTimestamp))
<table>
<tr>
<td class="header-dark">طابع زمني</td>
<td class="content-right">{{ $normalizedTimestamp }}</td>
</tr>
</table>
@endif

</body>
</html>
