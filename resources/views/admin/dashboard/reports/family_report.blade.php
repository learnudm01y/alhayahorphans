<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استمارة أيتام</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @php
            // تحديد الألوان الديناميكية
            $primaryColor = $customDesign['theme_colors']['primary'] ?? '#6c2b6d';
            $secondaryColor = $customDesign['theme_colors']['secondary'] ?? '#19a19a';
            $accentColor = $customDesign['theme_colors']['accent'] ?? '#7d7d7d';

            // تحديد الخلفية المستخدمة
            $backgroundType = $customDesign['background_type'] ?? 'single';
            $singleBg = $customDesign['single_image'] ?? ('data:image/jpeg;base64,' . $backgroundBase64);
        @endphp

        /* Global Styles */
        @page {
            margin: 0;
            size: auto;
        }

        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background-color: #fff;
            direction: rtl;
            box-sizing: border-box;
            font-size: 13px;
            margin: 0;
            position: relative;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page-container {
            width: 100%;
            min-height: 100vh;
            height: auto;
            background-color: transparent;
            position: relative;
            padding: 22px 30px 36px 30px;
            box-sizing: border-box;
            margin: 0;
            overflow: visible;
        }

        .page-container > * {
            position: relative;
            z-index: 1;
        }

        .fixed-bg-layer {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 100% 100%;
            z-index: 0;
        }

        .fixed-bg-part {
            position: fixed;
            right: 0;
            left: 0;
            width: 100%;
            z-index: 0;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 100% 100%;
        }

        .fixed-bg-header {
            top: 0;
            height: 15%;
        }

        .fixed-bg-main {
            top: 15%;
            height: 70%;
        }

        .fixed-bg-footer {
            bottom: 0;
            height: 15%;
        }

        .keep-together {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .document-page {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex-direction: column;
            padding: 30px 30px 30px 30px;
            page-break-before: always;
        }

        .document-title {
            text-align: center;
            margin-bottom: 12px;
            font-size: 26px;
            font-weight: bold;
        }

        .document-name {
            text-align: center;
            margin-bottom: 16px;
            font-size: 22px;
        }

        .document-heading-line {
            width: 100%;
            text-align: center;
            margin-top: 6px;
            margin-bottom: 18px;
            font-weight: bold;
            direction: rtl;
            unicode-bidi: plaintext;
        }

        .document-heading-line .document-title {
            display: inline;
            margin: 0;
        }

        .document-heading-line .document-separator {
            display: inline;
            margin: 0 10px;
            font-size: 24px;
            color: #666;
            font-weight: 700;
        }

        .document-heading-line .document-name {
            display: inline;
            margin: 0;
        }

        .document-image-wrap {
            width: 100%;
            height: 220mm;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-align: center;
        }

        .document-image {
            max-width: 95%;
            max-height: 215mm;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border: none;
            box-shadow: none;
            border-radius: 0;
        }

        /* Colors - Using dynamic colors */
        /* Primary: {{ $primaryColor }} */
        /* Secondary: {{ $secondaryColor }} */
        /* Accent: {{ $accentColor }} */
        /* Border: #ccc */

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 2px;
        }

/* Logo Section */
.logo-container {
    margin-bottom: 6px;
}

.logo-text h1 {
    font-size: 22px; /* Reduced */
    color: var(--primary-purple);
    margin: 0;
    font-weight: 700;
}

.logo-text h2 {
    font-size: 18px; /* Reduced */
    color: var(--secondary-teal);
    margin: 0;
    font-weight: 500;
}

.logo-icon svg {
    height: 60px; /* Reduced */
    width: 60px; /* Reduced */
}

/* Form Title */
.form-title {
    color: {{ $accentColor }};
    font-size: 22px;
    font-weight: 700;
    text-align: center;
    border-bottom: 2px solid {{ $accentColor }};
    padding-bottom: 2px;
    display: inline-block;
    margin: 0 auto;
}

/* Re-centering title logic - Removed duplicated styles */

        /* Tables Layout */
        .top-tables-container {
            width: 100%;
            margin-bottom: 5px;
        }

        .top-tables-container table.layout-table {
            width: 100%;
            border: none;
            border-collapse: collapse; /* Changed to collapse to avoid unexpected spacing issues */
        }

        .top-tables-container table.layout-table td {
            width: 50%;
            vertical-align: top;
            padding: 5px; /* Added small padding to separate the two tables slightly */
            border: none;
        }

.table-block {
    width: 100%;
}

/* Specific flex overrides removed */

/* Table Styles */
table.styled-table {
    width: 100%;
    border-collapse: collapse;
    border: 4px solid {{ $primaryColor }};
    background: white;
}

table.styled-table td {
    border: 1px solid #000;
}

table.styled-table th {
    border: 1px solid #000;
}

.main-header {
    background-color: {{ $primaryColor }};
    color: white;
    padding: 4px 6px;
    text-align: center;
    font-size: 15px;
    font-weight: bold;
    border: 1px solid #000 !important;
    line-height: 1.2;
}

.sub-header th {
    background-color: {{ $accentColor }};
    color: white;
    padding: 3px 4px;
    font-size: 12px;
    font-weight: bold;
    border: 1px solid #000 !important;
    white-space: nowrap;
}

.siblings-table .sub-header th {
    background-color: {{ $accentColor }};
    border: 1px solid #000 !important;
}

td {
    padding: 1px 3px;
    border: 1px solid #000 !important;
    font-size: 12px;
    vertical-align: middle;
    white-space: nowrap;
    line-height: 1.1;
}

.label {
    font-weight: bold;
    color: white;
    width: 35%;
    background-color: {{ $secondaryColor }};
    text-align: center;
    border: 1px solid #000 !important;
    padding: 1px 3px;
    font-size: 12px;
    white-space: nowrap;
    line-height: 1.1;
}

.styled-table .value {
    background-color: white;
    color: #333;
    text-align: center;
    font-weight: bold;
    border: 1px solid #000 !important;
    padding: 1px 3px;
    font-size: 12px;
    white-space: nowrap;
    line-height: 1.1;
}

table.compact-deceased-table td,
table.compact-deceased-table th {
    font-size: 11px;
    padding: 4px 5px;
    text-align: center;
    white-space: nowrap;
}

table.compact-deceased-table th {
    background-color: {{ $secondaryColor }};
    color: #fff;
    font-weight: 700;
}

table.compact-deceased-table .value {
    background-color: #fff;
    color: #333;
    font-weight: 700;
}

table.compact-deceased-table .marker-value {
    font-size: 11px;
    width: 12%;
}

table.compact-deceased-table .name-value {
    width: 44%;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

table.compact-deceased-table thead th:first-child {
    width: 12%;
}

table.compact-deceased-table thead th:nth-child(2) {
    width: 44%;
}

table.compact-deceased-table thead th:nth-child(3),
table.compact-deceased-table thead th:nth-child(4),
table.compact-deceased-table thead th:nth-child(5) {
    width: 14%;
}

table.compact-live-mother-table {
    table-layout: fixed;
}

table.compact-live-mother-table td,
table.compact-live-mother-table th {
    font-size: 11px;
    padding: 4px 5px;
    text-align: center;
    white-space: nowrap;
    font-family: 'Cairo', Tahoma, Arial, sans-serif !important;
}

table.compact-live-mother-table th {
    background-color: {{ $secondaryColor }};
    color: #fff;
    font-weight: 700;
}

table.compact-live-mother-table .value {
    background-color: #fff;
    color: #333;
    font-weight: 700;
}

table.compact-live-mother-table .marker-value {
    width: 12%;
}

table.compact-live-mother-table .name-value {
    width: 34%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

table.compact-live-mother-table thead th:first-child {
    width: 12%;
}

table.compact-live-mother-table thead th:nth-child(2) {
    width: 34%;
}

table.compact-live-mother-table thead th:nth-child(3),
table.compact-live-mother-table thead th:nth-child(4),
table.compact-live-mother-table thead th:nth-child(5),
table.compact-live-mother-table thead th:nth-child(6) {
    width: 13.5%;
}

        /* Photo Cell */
        .photo-cell {
            width: 140px;
            padding: 5px;
            border: 1px solid #000 !important;
            background: #fff;
            vertical-align: middle;
            text-align: center;
        }

        .photo-cell img {
            width: 120px; /* Reduced width slightly */
            height: 160px; /* Portrait */
            object-fit: contain; /* Ensure aspect ratio */
            display: block;
            margin: 0 auto;
        }

        .orphan-photo-cell {
            width: 140px !important;
            min-width: 140px !important;
            max-width: 140px !important;
            padding: 0 !important;
        }

        .orphan-photo-cell img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            display: block;
            margin: 0;
            border: none;
            image-rendering: -webkit-optimize-contrast;
        }

        .orphan-photo-placeholder {
            width: 100%;
            height: 100%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #999;
            border: none;
            box-sizing: border-box;
        }

/* Specific Height Balancing - Removed to maintain uniform padding */

/* Siblings Table Styling */
        .siblings-block {
            margin-bottom: 5px; /* Reduced from 25px to minimize gap */
            overflow: visible; /* Prevent cutting off */
        }

.siblings-block .main-header {
    background-color: {{ $primaryColor }};
}

        .siblings-block .sub-header th {
            background-color: {{ $accentColor }};
            color: white;
            border: 2px solid white;
            padding: 3px 4px;
            font-size: 12px;
        }

        .siblings-block td {
            text-align: center;
            font-weight: 600;
            border: 1px solid #000 !important;
            font-size: 12px;
            padding: 3px 4px;
            white-space: nowrap;
            line-height: 1.2;
        }

        /* Photos Grid */
        .photos-grid {
            width: 100%;
            margin-bottom: 40px; /* Increased to prevent touching page bottom */
            max-width: 100%; /* Prevent overflow */
        }

        .photos-grid table {
            width: 100%;
            border: none;
            border-collapse: separate;
            border-spacing: 6px; /* Reduced spacing */
            table-layout: fixed; /* Ensures equal width columns */
        }

        .photos-grid table td {
            width: 16.66%;
            border: none;
            background: white;
            padding: 3px;
            text-align: center;
            vertical-align: top;
        }

        .photo-item {
            width: 100%;
            display: block;
            text-align: center;
        }

        .photo-item img {
            width: 100%;
            max-width: none;
            height: 145px;
            object-fit: contain;
            object-position: center center;
            display: block;
            margin: 0 auto 4px auto;
            background: #fff;
            border: none;
        }

        .photo-item .caption {
            font-size: 11px;
            font-weight: bold;
            line-height: 1.2;
        }

/* Footer - Removed (not used) */

/* Specific tweaks for "Guardian Table" labels -> White bg?
   Image 1: Guardian table (Left side). Columns: Name, ID, Relation...
   Label column is Teal? No.
   Let's look at crop 2 (Left side).
   "بيانات المعيل" (Purple Top)
   "الاسم رباعي" -> Teal Background? Yes.
   "اسماء ..." -> White.
   Okay, consistent style for both tables.
*/
/* Fix border colors and thickness */
.styled-table {
    border: 2px solid {{ $primaryColor }};
}

.styled-table thead tr:first-child th {
    border-bottom: 2px solid white;
    /* Separator between title and content */
}

/* Adjustments for inputs */
.value-cell.number {
    font-family: sans-serif;
    /* For numbers to look right */
}
    </style>
        <?php
            // Prefer backgroundnew103.jpg if present, otherwise fallback to background102.jpg
            $bgBase = '';
            $newBg = public_path('backgroundnew103.jpg');
            $oldBg = public_path('background102.jpg');
            if (file_exists($newBg)) {
                try {
                    $bgBase = base64_encode(file_get_contents($newBg));
                } catch (\Exception $e) {
                    $bgBase = '';
                }
            } elseif (file_exists($oldBg)) {
                try {
                    $bgBase = base64_encode(file_get_contents($oldBg));
                } catch (\Exception $e) {
                    $bgBase = '';
                }
            }
            // If controller already passed $backgroundBase64, prefer it unless we loaded one above
            if (empty($bgBase)) {
                $backgroundBase64 = $backgroundBase64 ?? '';
            } else {
                $backgroundBase64 = $bgBase;
            }
        ?>
</head>

<body>
    @if($backgroundType === 'single' && !empty($singleBg))
    <div class="fixed-bg-layer" style="background-image: url('{{ $singleBg }}');"></div>
    @endif

    @if($backgroundType === 'triple')
        @if(!empty($customDesign['header_image']))
        <div class="fixed-bg-part fixed-bg-header" style="background-image: url('{{ $customDesign['header_image'] }}');"></div>
        @endif
        @if(!empty($customDesign['main_image']))
        <div class="fixed-bg-part fixed-bg-main" style="background-image: url('{{ $customDesign['main_image'] }}');"></div>
        @endif
        @if(!empty($customDesign['footer_image']))
        <div class="fixed-bg-part fixed-bg-footer" style="background-image: url('{{ $customDesign['footer_image'] }}');"></div>
        @endif
    @endif

    <div class="page-container">
        <!-- Header -->
        <header class="header">
            <div class="logo-container">
                <!-- Logo text and graphic removed as requested -->
            </div>
            <h1 class="form-title">استمارة أيتام</h1>
        </header>
        <div class="content-wrapper">
            <!-- Top Section: Guardian and Orphan Tables -->
            <div class="top-tables-container">
                <table class="layout-table">
                    <tr>
                        <!-- Orphan Table (Right Side in RTL) -->
                        <td>
                            <div class="table-block orphan-block">
                                <table class="styled-table">
                                    <thead>
                                        <tr>
                                            <th colspan="3" class="main-header">بيانات اليتيم</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="label">الاسم رباعي</td>
                                            <td class="value" colspan="2">{{ $selectedMember->first_name }} {{ $selectedMember->second_name }} {{ $selectedMember->third_name }} {{ $selectedMember->last_name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">رقم الهوية</td>
                                            <td class="value">{{ $selectedMember->person_id }}</td>
                                            <td class="photo-cell orphan-photo-cell" rowspan="6">
                                                @php
                                                    // البحث عن الصورة الشخصية للمكفول المحدد
                                                    $orphanPhoto = null;

                                                    // استخدام personalPhotos إذا كانت متوفرة
                                                    if (isset($personalPhotos)) {
                                                        $orphanPhoto = $personalPhotos->where('person_identity_number', $selectedMember->person_id)->first();
                                                    }

                                                    // البحث في attachments إذا لم تجد
                                                    if (!$orphanPhoto && $selectedMember->attachments) {
                                                        // النوع 12 فقط = صور شخصية
                                                        $orphanPhoto = $selectedMember->attachments->where('file_type', '12')->first();

                                                        if (!$orphanPhoto) {
                                                            $orphanPhoto = $selectedMember->attachments->filter(function($a) {
                                                                $name = strtolower($a->stored_file_name ?? '');
                                                                return str_starts_with($name, '12_') ||
                                                                       str_contains($name, 'personal_photo');
                                                            })->first();
                                                        }
                                                    }

                                                    if ($orphanPhoto) {
                                                        $orphanPhotoPath = $orphanPhoto->file_path;

                                                        // معالجة المسار
                                                        if (str_starts_with($orphanPhotoPath, 'storage/uploads/')) {
                                                            $fullPath = base_path('storage/app/public/uploads/' . substr($orphanPhotoPath, 16));
                                                        } elseif (str_starts_with($orphanPhotoPath, 'storage/attachments/')) {
                                                            $fullPath = base_path('storage/app/public/attachments/' . substr($orphanPhotoPath, 20));
                                                        } elseif (str_starts_with($orphanPhotoPath, 'storage/')) {
                                                            $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $orphanPhotoPath));
                                                        } else {
                                                            $fullPath = public_path($orphanPhotoPath);
                                                        }

                                                        // تحويل الصورة إلى base64 للعرض في PDF
                                                        if (file_exists($fullPath)) {
                                                            try {
                                                                $imageData = base64_encode(file_get_contents($fullPath));
                                                                $mimeType = mime_content_type($fullPath);
                                                                $orphanPhotoPath = "data:{$mimeType};base64,{$imageData}";
                                                            } catch (\Exception $e) {
                                                                $orphanPhotoPath = '';
                                                            }
                                                        } else {
                                                            $orphanPhotoPath = '';
                                                        }
                                                    } else {
                                                        $orphanPhotoPath = '';
                                                    }
                                                @endphp
                                                @if($orphanPhotoPath)
                                                    <img src="{{ $orphanPhotoPath }}" alt="صورة اليتيم">
                                                @else
                                                    <div class="orphan-photo-placeholder">لا توجد صورة</div>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label">تاريخ الميلاد</td>
                                            <td class="value">{{ $selectedMember->person_birth_date ? \Carbon\Carbon::parse($selectedMember->person_birth_date)->format('d/m/Y') : 'غير متوفر' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">الجنس</td>
                                            <td class="value">{{ $selectedMember->sex_id == 1 ? 'ذكر' : ('انثى' ?? 'غير محدد') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">الحالة الصحية</td>
                                            <td class="value">{{ $selectedMember->healthStatus->description ?? 'غير متوفر' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">مستوى التعليم</td>
                                            <td class="value">{{ $selectedMember->acadimic_degree ?? 'غير متوفر' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">حالة الكفالة</td>
                                            <td class="value">{{ $selectedMember->is_sponsored ? 'مكفول' : 'غير مكفول' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">المتوفى</td>
                                            <td class="value" colspan="2">
                                                @php
                                                    $deceased = 'غير متوفر';
                                                    if ($deadPeople) {
                                                        if ($deadPeople->father_death_date) $deceased = 'الأب';
                                                        elseif ($deadPeople->mother_death_date) $deceased = 'الأم';
                                                    }
                                                @endphp
                                                {{ $deceased }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                        <!-- Guardian Table (Left Side in RTL) -->
                        <td>
                            <div class="table-block guardian-block">
                                <table class="styled-table">
                                    <thead>
                                        <tr>
                                            <th colspan="3" class="main-header">بيانات المعيل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="label">الاسم رباعي</td>
                                            <td class="value" colspan="2">{{ $guardian->data_first_name }} {{ $guardian->data_father_name }} {{ $guardian->data_grand_father_name }} {{ $guardian->data_family_name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">رقم الهوية</td>
                                            <td class="value number">{{ $guardian->data_id_number }}</td>
                                            <td class="photo-cell" rowspan="4">
                                                @php
                                                    // البحث عن الصورة الشخصية للمعيل
                                                    $guardianPhoto = null;

                                                    // استخدام personalPhotos إذا كانت متوفرة
                                                    if (isset($personalPhotos)) {
                                                        $guardianPhoto = $personalPhotos->where('person_identity_number', $guardian->data_id_number)->first();
                                                    }

                                                    // البحث في attachments إذا لم تجد
                                                    if (!$guardianPhoto && isset($guardian->attachments)) {
                                                        // النوع 12 فقط = صور شخصية
                                                        $guardianPhoto = $guardian->attachments->where('file_type', '12')->first();

                                                        if (!$guardianPhoto) {
                                                            $guardianPhoto = $guardian->attachments->filter(function($a) {
                                                                $name = strtolower($a->stored_file_name ?? '');
                                                                return str_starts_with($name, '12_') ||
                                                                       str_contains($name, 'personal_photo');
                                                            })->first();
                                                        }
                                                    }

                                                    if ($guardianPhoto) {
                                                        $guardianPhotoPath = $guardianPhoto->file_path;

                                                        // معالجة المسار
                                                        if (str_starts_with($guardianPhotoPath, 'storage/uploads/')) {
                                                            $fullPath = base_path('storage/app/public/uploads/' . substr($guardianPhotoPath, 16));
                                                        } elseif (str_starts_with($guardianPhotoPath, 'storage/attachments/')) {
                                                            $fullPath = base_path('storage/app/public/attachments/' . substr($guardianPhotoPath, 20));
                                                        } elseif (str_starts_with($guardianPhotoPath, 'storage/')) {
                                                            $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $guardianPhotoPath));
                                                        } else {
                                                            $fullPath = public_path($guardianPhotoPath);
                                                        }

                                                        // تحويل الصورة إلى base64 للعرض في PDF
                                                        if (file_exists($fullPath)) {
                                                            try {
                                                                $imageData = base64_encode(file_get_contents($fullPath));
                                                                $mimeType = mime_content_type($fullPath);
                                                                $guardianPhotoPath = "data:{$mimeType};base64,{$imageData}";
                                                            } catch (\Exception $e) {
                                                                $guardianPhotoPath = '';
                                                            }
                                                        } else {
                                                            $guardianPhotoPath = '';
                                                        }
                                                    } else {
                                                        $guardianPhotoPath = '';
                                                    }
                                                @endphp
                                                @if($guardianPhotoPath)
                                                    <img src="{{ $guardianPhotoPath }}" alt="صورة المعيل">
                                                @else
                                                    <div style="width:120px; height:160px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:10px; color:#999;">لا توجد صورة</div>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label">صلة القرابة</td>
                                            <td class="value">{{ $guardian->categoryOfRelation->attribute ?? 'غير متوفر' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">عدد من يعيلهم</td>
                                            <td class="value number">
                                                @php
                                                    // استخدم القيمة من قاعدة البيانات إن وجدت، وإلا استخدم العدد المحتسب من أفراد التقرير
                                                    $dependents = $guardian->data_number_of_individuals ?? ($computedDependents ?? null) ?? ($guardian->data_number_female + $guardian->data_number_mail) ?? 0;
                                                @endphp
                                                {{ $dependents }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="label">العنوان الحالي</td>
                                            <td class="value">{{ $guardian->city->city ?? 'غير متوفر' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Siblings Table -->
            <div class="siblings-block">
                <table class="styled-table full-width">
                    <thead>
                        <tr>
                            <th colspan="7" class="main-header">بيانات الإخوة الأيتام</th>
                        </tr>
                        <tr class="sub-header">
                            <th>الاسم رباعي</th>
                            <th>رقم الهوية</th>
                            <th>الجنس</th>
                            <th>تاريخ الميلاد</th>
                            <th>مستوى التعليم</th>
                            <th>الحالة الصحية</th>
                            <th class="last-col">حالة الكفالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($familyMembers as $index => $member)
                            @if($member->id != $selectedMember->id)
                            <tr>
                                <td>{{ $member->first_name }} {{ $member->second_name }} {{ $member->third_name }} {{ $member->last_name }}</td>
                                <td>{{ $member->person_id }}</td>
                                <td>{{ $member->sex_id == 1 ? 'ذكر' : 'انثى' }}</td>
                                <td>{{ $member->person_birth_date ? \Carbon\Carbon::parse($member->person_birth_date)->format('d/m/Y') : 'غير متوفر' }}</td>
                                <td>{{ $member->acadimic_degree ?? 'غير متوفر' }}</td>
                                <td>{{ $member->healthStatus->description ?? 'غير متوفر' }}</td>
                                <td>{{ $member->is_sponsored ? 'مكفول' : 'غير مكفول' }}</td>
                            </tr>
                            @endif
                        @endforeach
                        @if($familyMembers->count() <= 1)
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">لا يوجد إخوة آخرون</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            @php
                $fatherFullName = trim(implode(' ', array_filter([
                    optional($deadPeople)->father_first_name,
                    optional($deadPeople)->father_second_name,
                    optional($deadPeople)->father_third_name,
                    optional($deadPeople)->father_last_name,
                ])));

                $motherFullName = trim(implode(' ', array_filter([
                    optional($deadPeople)->mother_first_name,
                    optional($deadPeople)->mother_second_name,
                    optional($deadPeople)->mother_third_name,
                    optional($deadPeople)->mother_last_name,
                ])));

                $hasFatherDeceasedData = !empty($fatherFullName)
                    || (!empty(optional($deadPeople)->father_id) && (int)(optional($deadPeople)->father_id) > 0);

                $hasMotherDeceasedData = !empty($motherFullName)
                    || (!empty(optional($deadPeople)->mother_id) && (int)(optional($deadPeople)->mother_id) > 0);
            @endphp

            @if($hasFatherDeceasedData || $hasMotherDeceasedData || (!empty($additionalDeceased) && $additionalDeceased->count() > 0))
            <div class="siblings-block">
                <table class="styled-table full-width compact-deceased-table">
                    <thead>
                        <tr>
                            <th>البيان</th>
                            <th>الاسم</th>
                            <th>رقم الهوية</th>
                            <th>تاريخ الوفاة</th>
                            <th>سبب الوفاة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($hasFatherDeceasedData)
                        <tr>
                            <td class="value marker-value">(الأب المتوفي)</td>
                            <td class="value name-value">
                                {{ $fatherFullName ?: 'غير متوفر' }}
                            </td>
                            <td class="value">{{ optional($deadPeople)->father_id ?? 'غير متوفر' }}</td>
                            <td class="value">
                                {{ !empty($deadPeople?->father_death_date) ? \Carbon\Carbon::parse($deadPeople->father_death_date)->format('d/m/Y') : 'غير متوفر' }}
                            </td>
                            <td class="value">{{ $deadPeople?->fatherDeathReason?->description ?? 'غير متوفر' }}</td>
                        </tr>
                        @endif

                        @if($hasMotherDeceasedData)
                        <tr>
                            <td class="value marker-value">(الأم المتوفية)</td>
                            <td class="value name-value">
                                {{ $motherFullName ?: 'غير متوفر' }}
                            </td>
                            <td class="value">{{ optional($deadPeople)->mother_id ?? 'غير متوفر' }}</td>
                            <td class="value">
                                {{ !empty($deadPeople?->mother_death_date) ? \Carbon\Carbon::parse($deadPeople->mother_death_date)->format('d/m/Y') : 'غير متوفر' }}
                            </td>
                            <td class="value">{{ $deadPeople?->motherDeathReason?->description ?? 'غير متوفر' }}</td>
                        </tr>
                        @endif

                        @if(!empty($additionalDeceased) && $additionalDeceased->count() > 0)
                            @foreach($additionalDeceased as $ad)
                            <tr>
                                <td class="value marker-value">({{ $ad->relationship_text ?? $ad->relationship ?? 'متوفي إضافي' }})</td>
                                <td class="value name-value">
                                    {{ trim(($ad->first_name ?? '') . ' ' . ($ad->second_name ?? '') . ' ' . ($ad->third_name ?? '') . ' ' . ($ad->last_name ?? '')) ?: 'غير متوفر' }}
                                </td>
                                <td class="value">{{ $ad->person_id ?? '-' }}</td>
                                <td class="value">
                                    {{ !empty($ad->death_date) ? \Carbon\Carbon::parse($ad->death_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="value">{{ $ad->death_reason ?? '-' }}</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            @endif

            @if(!empty($liveMother) && (!empty($liveMother->person_id) || !empty($liveMother->first_name) || !empty($liveMother->second_name) || !empty($liveMother->third_name) || !empty($liveMother->last_name)))
            <div class="siblings-block">
                <table class="styled-table full-width compact-live-mother-table">
                    <thead>
                        <tr>
                            <th>البيان</th>
                            <th>الاسم</th>
                            <th>رقم الهوية</th>
                            <th>تاريخ الميلاد</th>
                            <th>الجوال</th>
                            <th>الحالة الصحية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="value marker-value">(الأم الحية)</td>
                            <td class="value name-value">
                                @php
                                    $liveMotherFullName = trim(implode(' ', array_filter([
                                        $liveMother->first_name ?? null,
                                        $liveMother->second_name ?? null,
                                        $liveMother->third_name ?? null,
                                        $liveMother->last_name ?? null,
                                    ])));
                                @endphp
                                {{ $liveMotherFullName ?: 'غير متوفر' }}
                            </td>
                            <td class="value">{{ $liveMother->person_id ?? 'غير متوفر' }}</td>
                            <td class="value">
                                {{ !empty($liveMother->person_birth_date) ? \Carbon\Carbon::parse($liveMother->person_birth_date)->format('d/m/Y') : 'غير متوفر' }}
                            </td>
                            <td class="value">{{ $liveMother->phone ?? 'غير متوفر' }}</td>
                            <td class="value">{{ $liveMother->health_status ?? 'غير متوفر' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif

            {{-- المتوفون الإضافيون تم دمجهم في الجدول أعلاه --}}

            <!-- Photos Grid -->
            <div class="photos-grid">
                <table>
                    @php
                        $photoMembers = $familyMembers->filter(function($m) use ($selectedMember) {
                            return $m->id != $selectedMember->id;
                        });
                        $chunks = $photoMembers->chunk(6);
                    @endphp
                    @foreach($chunks as $chunk)
                    <tr>
                        @foreach($chunk as $member)
                        <td>
                            <div class="photo-item">
                                @php
                                    // البحث عن الصورة الشخصية (النوع 12 فقط)
                                    $memberPhoto = null;

                                    if (isset($personalPhotos)) {
                                        $memberPhoto = $personalPhotos->where('person_identity_number', $member->person_id)->first();
                                    }

                                    if (!$memberPhoto && $member->attachments) {
                                        $memberPhoto = $member->attachments->where('file_type', '12')->first();

                                        if (!$memberPhoto) {
                                            $memberPhoto = $member->attachments->filter(function($a) {
                                                $name = strtolower($a->stored_file_name ?? '');
                                                return str_starts_with($name, '12_');
                                            })->first();
                                        }
                                    }

                                    $memberPhotoPath = '';
                                    if ($memberPhoto) {
                                        $filePath = $memberPhoto->file_path;
                                        if (str_starts_with($filePath, 'storage/uploads/')) {
                                            $fullPath = base_path('storage/app/public/uploads/' . substr($filePath, 16));
                                        } elseif (str_starts_with($filePath, 'storage/attachments/')) {
                                            $fullPath = base_path('storage/app/public/attachments/' . substr($filePath, 20));
                                        } elseif (str_starts_with($filePath, 'storage/')) {
                                            $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $filePath));
                                        } else {
                                            $fullPath = public_path($filePath);
                                        }

                                        if (file_exists($fullPath)) {
                                            try {
                                                $imageData = base64_encode(file_get_contents($fullPath));
                                                $mimeType = mime_content_type($fullPath);
                                                $memberPhotoPath = "data:{$mimeType};base64,{$imageData}";
                                            } catch (\Exception $e) {
                                                $memberPhotoPath = '';
                                            }
                                        }
                                    }
                                @endphp
                                @if($memberPhotoPath)
                                    <img src="{{ $memberPhotoPath }}" alt="{{ $member->first_name }}">
                                @else
                                    <div style="width:100%; height:100%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:9px; color:#999;">لا توجد صورة</div>
                                @endif
                                <div class="caption">{{ $member->first_name }} {{ $member->second_name }}</div>
                            </div>
                        </td>
                        @endforeach
                        @if($chunk->count() < 6)
                            @for($i = $chunk->count(); $i < 6; $i++)
                                <td style="visibility: hidden;"></td>
                            @endfor
                        @endif
                    </tr>
                    @endforeach
                    @if($photoMembers->count() == 0)
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px;">لا توجد صور إضافية</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
        <!-- Footer -->
        <!-- Footer Removed -->
    </div>

    <!-- صفحات منفصلة للوثائق الأخرى (غير الصور الشخصية) -->
    @if(isset($otherDocuments) && $otherDocuments->count() > 0)
        @foreach($otherDocuments as $document)
                @php
                    // الحصول على نوع الوثيقة
                    $docTypeName = 'وثيقة';
                    $docTypeObj = \DB::table('document_types')->where('pref', $document->file_type)->first();
                    if ($docTypeObj) {
                        $docTypeName = $docTypeObj->description;
                    }

                    // الحصول على اسم الشخص
                    $personName = 'غير معروف';
                    $personIdentity = $document->person_identity_number;

                    // البحث في المعيل
                    if (isset($guardian) && $guardian->data_id_number == $personIdentity) {
                        $personName = $guardian->data_first_name . ' ' . $guardian->data_family_name;
                    } else {
                        // البحث في الأيتام
                        if (isset($familyMembers)) {
                            $foundMember = $familyMembers->firstWhere('person_id', $personIdentity);
                            if ($foundMember) {
                                $personName = $foundMember->first_name . ' ' . $foundMember->family_name;
                            }
                        }

                        // البحث في بيانات الأب/الأم المتوفيين
                        if ($personName === 'غير معروف' && isset($deadPeople)) {
                            if (!empty($deadPeople->father_id) && (string)$deadPeople->father_id === (string)$personIdentity) {
                                $personName = trim(implode(' ', array_filter([
                                    $deadPeople->father_first_name,
                                    $deadPeople->father_second_name,
                                    $deadPeople->father_third_name,
                                    $deadPeople->father_last_name,
                                ]))) ?: 'الأب المتوفي';
                            } elseif (!empty($deadPeople->mother_id) && (string)$deadPeople->mother_id === (string)$personIdentity) {
                                $personName = trim(implode(' ', array_filter([
                                    $deadPeople->mother_first_name,
                                    $deadPeople->mother_second_name,
                                    $deadPeople->mother_third_name,
                                    $deadPeople->mother_last_name,
                                ]))) ?: 'الأم المتوفية';
                            }
                        }
                    }

                    $docPath = $document->file_path;
                    $fullPath = '';

                    // معالجة المسار بشكل صحيح
                    if (str_starts_with($docPath, 'storage/uploads/')) {
                        $fullPath = base_path('storage/app/public/uploads/' . substr($docPath, 16));
                    } elseif (str_starts_with($docPath, 'storage/attachments/')) {
                        $fullPath = base_path('storage/app/public/attachments/' . substr($docPath, 20));
                    } elseif (str_starts_with($docPath, 'storage/')) {
                        $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $docPath));
                    } else {
                        $fullPath = public_path($docPath);
                    }

                    $isImage = in_array(strtolower(pathinfo($document->stored_file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);
                    $fileExists = file_exists($fullPath);

                    $base64Image = '';
                    if ($fileExists && $isImage) {
                        try {
                            $imageData = base64_encode(file_get_contents($fullPath));
                            $mimeType = mime_content_type($fullPath);
                            $base64Image = "data:{$mimeType};base64,{$imageData}";
                        } catch (\Exception $e) {
                            $base64Image = '';
                        }
                    }
                @endphp

                @if(empty($base64Image))
                    @continue
                @endif

              <div class="page-container document-page">

                <!-- عنوان الوثيقة واسم الشخص فوق الصورة -->
                <div class="document-heading-line">
                    <span class="document-title" style="color: {{ $primaryColor }};">{{ $docTypeName }}</span>
                    <span class="document-separator">/</span>
                    <span class="document-name" style="color: {{ $accentColor }};">{{ $personName }}</span>
                </div>

                <div class="document-image-wrap keep-together">
                    <img src="{{ $base64Image }}"
                         class="document-image keep-together"
                         style="border-color: {{ $primaryColor }};"
                         alt="{{ $docTypeName }}">
                </div>
            </div>
        @endforeach
    @endif
</body>

</html>
