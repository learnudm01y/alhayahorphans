<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استمارة أيتام</title>
    <style>
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
            font-size: 13px; /* Reduced from 15px */
        }

        .page-container {
            width: 100%;
            min-height: 340mm !important; /* Force high height as requested "very high" */
            height: auto;
            background-color: white;
            background-image: url('data:image/jpeg;base64,{{ $backgroundBase64 }}');

            /* FORCE STRETCH: 100% width and 100% of the new tall height */
            background-size: 100% 100% !important;
            background-position: center top;
            background-repeat: no-repeat;

            position: relative;
            padding: 30px 30px 40px 30px;
            box-sizing: border-box;
        }

        /* Colors - Direct values for wkhtmltopdf compatibility */
        /* Purple: #6c2b6d */
        /* Teal: #19a19a */
        /* Grey: #7d7d7d */
        /* Border: #ccc */

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 5px;
        }

/* Logo Section */
.logo-container {
    margin-bottom: 30px;
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
    color: #0b5fa5;
    font-size: 28px; /* Reduced */
    font-weight: 700;
    text-align: center;
    border-bottom: 3px solid #0b5fa5;
    padding-bottom: 4px;
    display: inline-block;
    margin: 0 auto;
}

/* Re-centering title logic - Removed duplicated styles */

        /* Tables Layout */
        .top-tables-container {
            width: 100%;
            margin-bottom: 20px;
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
    border: 4px solid #6c2b6d;
    background: white;
}

table.styled-table td {
    border: 1px solid #000;
}

table.styled-table th {
    border: 1px solid #000;
}

.main-header {
    background-color: #6c2b6d;
    color: white;
    padding: 8px; /* Reduced */
    text-align: center;
    font-size: 18px; /* Reduced */
    font-weight: bold;
    border: 1px solid #000 !important;
}

.sub-header th {
    background-color: #7d7d7d;
    color: white;
    padding: 6px 4px; /* Reduced */
    font-size: 14px; /* Reduced */
    font-weight: bold;
    border: 1px solid #000 !important;
    white-space: nowrap;
}

.siblings-table .sub-header th {
    background-color: #7d7d7d;
    border: 1px solid #000 !important;
}

td {
    padding: 6px 8px; /* Reduced */
    border: 1px solid #000 !important;
    font-size: 14px; /* Reduced */
    vertical-align: middle;
    white-space: nowrap;
}

.label {
    font-weight: bold;
    color: white;
    width: 35%;
    background-color: #19a19a;
    text-align: center;
    border: 1px solid #000 !important;
    padding: 6px 6px; /* Reduced */
    font-size: 14px; /* Reduced */
    white-space: nowrap;
}

/* Override for specific styling */
.styled-table .label {
    background-color: #19a19a;
    color: white;
    width: 35%;
}

.styled-table .value {
    background-color: white;
    color: #333;
    text-align: center;
    font-weight: bold;
    border: 1px solid #000 !important;
    padding: 6px 6px; /* Reduced */
    font-size: 14px; /* Reduced */
    white-space: nowrap;
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

/* Specific Height Balancing - Removed to maintain uniform padding */

/* Siblings Table Styling */
        .siblings-block {
            margin-bottom: 5px; /* Reduced from 25px to minimize gap */
            overflow: visible; /* Prevent cutting off */
        }

.siblings-block .main-header {
    background-color: #6c2b6d;
}

        .siblings-block .sub-header th {
            background-color: #7d7d7d;
            color: white;
            border: 2px solid white;
            padding: 6px 4px; /* Increased from 4px 3px */
            font-size: 13px; /* Increased from 12px */
        }

        .siblings-block td {
            text-align: center;
            font-weight: 600;
            border: 1px solid #000 !important;
            font-size: 13px; /* Increased from 12px */
            padding: 6px 6px; /* Increased from 4px */
            white-space: nowrap;
            line-height: 1.4;
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
            border: 1px solid #000;
            background: white;
            padding: 4px; /* Reduced padding */
            text-align: center;
            vertical-align: top;
        }

        .photo-item {
            width: 100%;
            display: block;
            text-align: center;
        }

        .photo-item img {
            width: 70%; /* Constrain width to make portrait */
            max-width: 90px; /* Maximum width */
            height: 130px; /* Taller than wide - portrait orientation */
            object-fit: cover;
            display: block;
            margin: 0 auto 3px auto;
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
    border: 2px solid #6c2b6d;
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
                                            <td class="photo-cell" rowspan="6">
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
                                                    <div style="width:120px; height:160px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:10px; color:#999;">لا توجد صورة</div>
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
                                                    $deadPeople = \App\Models\DeadPepole::where('re_file_id', $guardian->file_id_number)->first();
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
            <div class="page-container" style="page-break-before: always; display: flex; align-items: center; justify-content: center; flex-direction: column; padding: 30px;">
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

                <!-- عنوان الوثيقة واسم الشخص فوق الصورة -->
                <h2 style="text-align: center; color: #6c2b6d; margin-bottom: 15px; font-size: 26px; font-weight: bold;">
                    {{ $docTypeName }}
                </h2>
                <h3 style="text-align: center; color: #0b5fa5; margin-bottom: 25px; font-size: 22px;">
                    {{ $personName }}
                </h3>

                @if($base64Image)
                    <div style="text-align: center; width: 100%; flex-grow: 1; display: flex; align-items: center; justify-content: center;">
                        <img src="{{ $base64Image }}"
                             style="max-width: 95%; max-height: 80vh; width: auto; height: auto; object-fit: contain; border: 4px solid #6c2b6d; box-shadow: 0 6px 25px rgba(0,0,0,0.2); border-radius: 4px;"
                             alt="{{ $docTypeName }}">
                    </div>
                @elseif($fileExists && !$isImage)
                    <div style="text-align: center; padding: 100px; background: #f5f5f5; border: 2px dashed #ccc; border-radius: 10px;">
                        <div style="font-size: 80px; color: #6c2b6d; margin-bottom: 20px;">📄</div>
                        <p style="font-size: 18px; color: #333; margin-bottom: 10px;">ملف {{ strtoupper(pathinfo($document->stored_file_name, PATHINFO_EXTENSION)) }}</p>
                    </div>
                @else
                    <div style="text-align: center; padding: 100px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 10px;">
                        <div style="font-size: 80px; color: #dc3545; margin-bottom: 20px;">⚠️</div>
                        <p style="font-size: 18px; color: #856404;">الملف غير موجود</p>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</body>

</html>
