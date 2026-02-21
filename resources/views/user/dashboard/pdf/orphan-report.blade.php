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

<!-- الصف الأول: معلومات أساسية مع صورة المكفول -->
<table>
<tr>
<td class="header-blue">رقم الملف</td>
<td class="header-blue">اسم المكفول</td>
<td class="header-blue">رقم الجوال</td>
<td class="header-blue">حالة السكن السابق</td>
<td class="header-blue">نوع السكن</td>
<td class="header-blue">المدينة</td>
@if(!empty($orphan_photo) && $orphan_photo['file_exists'])
<td class="header-blue" rowspan="2" style="width: 120px; text-align: center;">الصورة الشخصية</td>
@endif
</tr>
<tr>
<td class="content">{{ $file_number ?? 'غير متوفر' }}</td>
<td class="content">{{ $orphan_name ?? 'غير متوفر' }}</td>
<td class="content">{{ $phone_number ?? 'غير متوفر' }}</td>
<td class="content">{{ $housing_status ?? 'غير متوفر' }}</td>
<td class="content">{{ $housing_type ?? 'غير متوفر' }}</td>
<td class="content">{{ $city ?? 'غير متوفر' }}</td>
@if(!empty($orphan_photo) && $orphan_photo['file_exists'])
<td class="content" style="text-align: center; padding: 5px; vertical-align: middle;">
    <img src="file://{{ str_replace('\\', '/', $orphan_photo['file_path']) }}"
         style="max-width: 100px; max-height: 120px; border: 2px solid #0d6efd; border-radius: 5px;" />
</td>
@endif
</tr>
</table>

<!-- الصف الثاني: معلومات المدرسة -->
<table>
<tr>
<td class="header-blue" style="width:30%;">عنوان المدرسة</td>
<td class="header-blue">المرحلة الدراسية</td>
<td class="header-blue">مستوى الطالب</td>
<td class="header-blue">سبب الضعف</td>
</tr>
<tr>
<td class="content">{{ $school_address ?? '/|\\' }}</td>
<td class="content">{{ $academic_stage ?? '/|\\' }}</td>
<td class="content">{{ $student_level ?? '/|\\' }}</td>
<td class="content">{{ $weakness_reason ?? '/|\\' }}</td>
</tr>
</table>

<!-- الصف الثالث: الحالة النفسية والسلوكية -->
<table>
<tr>
<td class="header-blue">الحالة النفسية</td>
<td class="header-blue">الحالة السلوكية</td>
<td class="header-blue">الالتزام الديني</td>
<td class="header-blue">مقدار حفظه للقرآن</td>
</tr>
<tr>
<td class="content">{{ $psychological_status ?? '/|\\' }}</td>
<td class="content">{{ $behavioral_status ?? '/|\\' }}</td>
<td class="content">{{ $religious_commitment ?? '/|\\' }}</td>
<td class="content">{{ $quran_memorization ?? '/|\\' }}</td>
</tr>
</table>

<!-- الصف الرابع: الحالة الصحية والاحتياجات -->
<table>
<tr>
<td class="header-blue">الحالة الصحية لليتيم</td>
<td class="header-blue">احتياجات اليتيم</td>
<td class="header-blue">جوانب الإبداع</td>
</tr>
<tr>
<td class="content">{{ $orphan_health_status ?? 'غير متوفر' }}</td>
<td class="content">{{ $orphan_needs ?? 'غير متوفر' }}</td>
<td class="content">{{ $creativity_aspects ?? '/|\\' }}</td>
</tr>
</table>

<!-- قسم الأم - يظهر فقط إذا كانت الأم ليست المعيل -->
@if(!empty($mother_name))
<table>
<tr>
<td class="header-pink" style="width:33.33%;">اسم الأم</td>
<td class="header-pink" style="width:33.33%;">رقم الهوية</td>
<td class="header-pink" style="width:33.33%;">هل الأم على قيد الحياة</td>
</tr>
<tr>
<td class="content" style="width:33.33%;">{{ $mother_name ?? 'غير متوفر' }}</td>
<td class="content" style="width:33.33%;">{{ $mother_id ?? 'غير متوفر' }}</td>
<td class="content" style="width:33.33%;">{{ $mother_alive ?? 'غير متوفر' }}</td>
</tr>
</table>
@endif

<!-- قسم المعيل مع الصورة الشخصية -->
<table>
<tr>
<td class="header-black" style="width:35%;">اسم المعيل رباعي</td>
<td class="header-black">صلة القرابة</td>
<td class="header-black">الحالة الصحية</td>
<td class="header-black">الوظيفة</td>
<td class="header-black">عدد من يعيلهم</td>
@if(!empty($guardian_photo) && $guardian_photo['file_exists'])
<td class="header-black" rowspan="2" style="width: 120px; text-align: center;">الصورة الشخصية</td>
@endif
</tr>
<tr>
<td class="content">{{ $guardian_full_name ?? 'غير متوفر' }}</td>
<td class="content">{{ $guardian_relation ?? 'غير متوفر' }}</td>
<td class="content">{{ $guardian_health ?? '/|\\' }}</td>
<td class="content">{{ $guardian_job ?? $employment_status }}</td>
<td class="content">{{ $dependents_count ?? $number_of_individuals }}</td>
@if(!empty($guardian_photo) && $guardian_photo['file_exists'])
<td class="content" style="text-align: center; padding: 5px; vertical-align: middle;">
    <img src="file://{{ str_replace('\\', '/', $guardian_photo['file_path']) }}"
         style="max-width: 100px; max-height: 120px; border: 2px solid #000; border-radius: 5px;" />
</td>
@endif
</tr>
</table>

<!-- جدول أفراد الأسرة -->
<table>
<tr>
<td class="header-gray" style="width:8%;">م</td>
<td class="header-gray" style="width:35%;">الاسم رباعي</td>
<td class="header-gray" style="width:19%;">تاريخ الميلاد</td>
<td class="header-gray" style="width:19%;">مستوى التعليم</td>
<td class="header-gray" style="width:19%;">الحالة الصحية</td>
</tr>
@forelse($family_members as $member)
<tr>
<td class="content">{{ $member['index'] }}</td>
<td class="content">{{ $member['full_name'] }}</td>
<td class="content">{{ $member['birth_date'] }}</td>
<td class="content">{{ $member['academic_degree'] }}</td>
<td class="content">{{ $member['health_status'] ?? 'غير متوفر' }}</td>
</tr>
@empty
<tr>
<td class="content" colspan="5">لا يوجد أفراد مسجلين</td>
</tr>
@endforelse
</table>

<!-- تأثير الكفالة على الأيتام -->
<table>
<tr>
<td class="header-dark">تأثير الكفالة على الأيتام</td>
<td class="content-right">{{ $sponsorship_impact ?? '/|\\' }}</td>
</tr>
</table>

<!-- أهم الأحداث التي مرت بها الأسرة -->
<table>
<tr>
<td class="header-dark">اهم الأحداث التي مرت بها الأسرة</td>
<td class="content-right">{{ $family_events ?? '/|\\' }}</td>
</tr>
</table>

<!-- طابع زمني -->
<table>
<tr>
<td class="header-dark">طابع زمني</td>
<td class="content-right">{{ $timestamp ?? date('Y-m-d H:i:s') }}</td>
</tr>
</table>

</body>
</html>
