<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استمارة مكفول</title>
    <style>
        /* Global Styles */
        body {
            font-family: 'Tajawal', 'Traditional Arabic', 'Simplified Arabic', 'Tahoma', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            direction: rtl;
            box-sizing: border-box;
        }

        .page-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background-color: white;
            @if(file_exists($backgroundPath))
            background-image: url('data:image/jpeg;base64,{{ base64_encode(file_get_contents($backgroundPath)) }}');
            @endif
            background-size: 210mm 297mm;
            background-position: center center;
            background-repeat: no-repeat;
            position: relative;
            padding: 30px 40px 40px 40px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            page-break-after: always;
        }

        :root {
            --primary-purple: #6c2b6d;
            --secondary-teal: #19a19a;
            --border-color: #a0a0a0;
            --text-color: #333;
        }

        .header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            position: relative;
        }

        .form-title {
            color: #0b5fa5;
            font-size: 24px;
            font-weight: 700;
            text-decoration: none;
            text-align: center;
            margin: 0 auto;
            border-bottom: 2px solid #0b5fa5;
            padding-bottom: 2px;
            display: inline-block;
        }
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .report-title {
            font-size: 32px;
            font-weight: 700;
            color: #6b2c91;
            text-decoration: none;
            margin-bottom: 25px;
            padding: 10px 0;
        }

        .section-header {
            background-color: #6b2c91;
            color: white;
            padding: 10px 15px;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            margin: 20px 0 15px 0;
            border-radius: 5px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.95);
        }

        .info-table th {
            background-color: #1db5a5;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #ddd;
        }

        .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 13px;
        }

        .info-table td:first-child {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .guardian-section {
            background-color: #6b2c91;
            color: white;
            padding: 10px 15px;
            font-size: 18px;
            font-weight: 700;
            text-align: center;
            margin: 20px 0 15px 0;
        }

        .member-card {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: inline-block;
            width: 48%;
            vertical-align: top;
            margin-left: 1%;
            margin-right: 1%;
        }

        .member-photo-container {
            text-align: center;
            margin-bottom: 15px;
        }

        .member-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #6b2c91;
            display: block;
            margin: 0 auto 10px auto;
        }

        .member-name {
            font-size: 16px;
            font-weight: 700;
            color: #6b2c91;
            text-align: center;
            margin-top: 8px;
        }

        .member-info-table {
            width: 100%;
            font-size: 12px;
        }

        .member-info-table td {
            padding: 5px;
            border-bottom: 1px solid #eee;
        }

        .member-info-table td:first-child {
            font-weight: 600;
            width: 40%;
            color: #555;
        }

        .sponsored-badge {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        .document-page {
            text-align: center;
        }

        .document-title {
            background-color: #6b2c91;
            color: white;
            padding: 15px;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 30px;
            border-radius: 5px;
        }

        .document-image {
            max-width: 90%;
            max-height: 700px;
            border: 3px solid #6b2c91;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            display: block;
            margin: 0 auto;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .page {
                margin: 0;
                border: none;
                page-break-after: always;
            }
            
            .page:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

{{-- الصفحة الأولى: بيانات المعيل والإخوة --}}
<div class="page">
    <div class="header" style="margin-bottom: 30px; margin-top: 50px;">
        <div class="report-title" style="font-size: 32px; font-weight: 700; color: #6b2c91; margin-bottom: 25px;">استمارة مكفول</div>
    </div>

    {{-- القسم العلوي: بيانات اليتيم والمعيل جنباً إلى جنب --}}
    <div style="display: table; width: 100%; margin-bottom: 20px;">
        {{-- بيانات اليتيم على اليمين --}}
        <div style="display: table-cell; width: 58%; vertical-align: top; padding-left: 10px;">
            <div class="section-header" style="margin-top: 0;">بيانات اليتيم</div>
            
            <table class="info-table" style="margin-bottom: 0;">
                <tr>
                    <th style="background-color: #1db5a5; color: white;">الاسم رباعي</th>
                    <td>{{ $selectedMember->first_name }} {{ $selectedMember->second_name }} {{ $selectedMember->third_name }} {{ $selectedMember->last_name }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">رقم الهوية</th>
                    <td>{{ $selectedMember->person_id ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">تاريخ الميلاد</th>
                    <td>{{ $selectedMember->person_birth_date ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">الجنس</th>
                    <td>@if($selectedMember->person_gender == 1) ذكر @elseif($selectedMember->person_gender == 2) أنثى @else - @endif</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">الحالة الصحية</th>
                    <td>{{ optional($selectedMember->healthStatus)->description ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">مستوى التعليم</th>
                    <td>{{ $selectedMember->acadimic_degree ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">حالة الكفالة</th>
                    <td>
                        @if($selectedMember->is_sponsored)
                            <span class="sponsored-badge">مكفول</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">المتوفى</th>
                    <td>الأب</td>
                </tr>
            </table>

            {{-- صورة اليتيم --}}
            <div style="text-align: center; margin-top: 15px;">
                @if($selectedMember->attachments && $selectedMember->attachments->count() > 0)
                    @php
                        $firstImage = $selectedMember->attachments->first(function($att) {
                            return \Str::endsWith(strtolower($att->stored_file_name), ['jpg', 'jpeg', 'png', 'gif']);
                        });
                    @endphp
                    @if($firstImage)
                        @php
                            $imagePath = storage_path('app/public/uploads/' . dirname($firstImage->stored_file_name) . '/' . basename($firstImage->stored_file_name));
                            if (!file_exists($imagePath)) {
                                $imagePath = public_path('storage/uploads/' . dirname($firstImage->stored_file_name) . '/' . basename($firstImage->stored_file_name));
                            }
                        @endphp
                        @if(file_exists($imagePath))
                            <img src="data:image/{{ pathinfo($imagePath, PATHINFO_EXTENSION) }};base64,{{ base64_encode(file_get_contents($imagePath)) }}" 
                                 alt="{{ $selectedMember->first_name }}" 
                                 style="width: 180px; height: 220px; object-fit: cover; border: 3px solid #6b2c91; border-radius: 10px;">
                        @endif
                    @endif
                @endif
            </div>
        </div>

        {{-- بيانات المعيل على اليسار --}}
        <div style="display: table-cell; width: 42%; vertical-align: top; padding-right: 10px;">
            <div class="guardian-section" style="margin-top: 0;">بيانات المعيل</div>
            
            <table class="info-table" style="margin-bottom: 0;">
                <tr>
                    <th style="background-color: #1db5a5; color: white;">الاسم رباعي</th>
                    <td>{{ $guardian->data_first_name }} {{ $guardian->data_father_name }} {{ $guardian->data_grand_father_name }} {{ $guardian->data_family_name }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">رقم الهوية</th>
                    <td>{{ $guardian->data_id_number }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">صلة القرابة</th>
                    <td>{{ optional($guardian->categoryOfRelation)->attribute ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">عدد من يعيلهم</th>
                    <td>{{ $familyMembers->count() }}</td>
                </tr>
                <tr>
                    <th style="background-color: #1db5a5; color: white;">العنوان الحالي</th>
                    <td>{{ optional($guardian->city)->city ?? '-' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- بيانات الإخوة في جدول --}}
    <div class="section-header">بيانات الإخوة</div>

    <table class="info-table" style="margin-bottom: 15px;">
        <thead>
            <tr>
                <th>الاسم رباعي</th>
                <th>رقم الهوية</th>
                <th>الجنس</th>
                <th>تاريخ الميلاد</th>
                <th>مستوى التعليم</th>
                <th>الحالة الصحية</th>
                <th>حالة الكفالة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($familyMembers as $member)
                <tr>
                    <td>{{ $member->first_name }} {{ $member->second_name }} {{ $member->third_name }} {{ $member->last_name }}</td>
                    <td>{{ $member->person_id ?? '-' }}</td>
                    <td>@if($member->person_gender == 1) ذكر @elseif($member->person_gender == 2) أنثى @else - @endif</td>
                    <td>{{ $member->person_birth_date ?? '-' }}</td>
                    <td>{{ $member->acadimic_degree ?? '-' }}</td>
                    <td>{{ optional($member->healthStatus)->description ?? '-' }}</td>
                    <td>
                        @if($member->is_sponsored)
                            مكفول
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- صور أفراد الأسرة --}}
    @if($familyMembers->count() > 0)
        <div style="text-align: center; margin-top: 15px;">
            @foreach($familyMembers as $member)
                @if($member->attachments && $member->attachments->count() > 0)
                    @php
                        $firstImage = $member->attachments->first(function($att) {
                            return \Str::endsWith(strtolower($att->stored_file_name), ['jpg', 'jpeg', 'png', 'gif']);
                        });
                    @endphp
                    @if($firstImage)
                        @php
                            $imagePath = storage_path('app/public/uploads/' . dirname($firstImage->stored_file_name) . '/' . basename($firstImage->stored_file_name));
                            if (!file_exists($imagePath)) {
                                $imagePath = public_path('storage/uploads/' . dirname($firstImage->stored_file_name) . '/' . basename($firstImage->stored_file_name));
                            }
                        @endphp
                        @if(file_exists($imagePath))
                            <div style="display: inline-block; margin: 5px; text-align: center;">
                                <img src="data:image/{{ pathinfo($imagePath, PATHINFO_EXTENSION) }};base64,{{ base64_encode(file_get_contents($imagePath)) }}" 
                                     alt="{{ $member->first_name }}" 
                                     style="width: 120px; height: 140px; object-fit: cover; border: 2px solid #6b2c91; border-radius: 8px;">
                                <div style="font-size: 11px; margin-top: 3px; font-weight: 600; color: #333;">
                                    {{ $member->first_name }} {{ $member->second_name }} {{ $member->third_name }} {{ $member->last_name }}
                                </div>
                            </div>
                        @endif
                    @endif
                @endif
            @endforeach
        </div>
    @endif
</div>

{{-- صفحات الوثائق --}}
@foreach($documentImages as $index => $document)
    <div class="page document-page">
        <div class="document-title">
            الوثيقة رقم {{ $index + 1 }}
        </div>
        
        @php
            $imagePath = storage_path('app/public/uploads/' . dirname($document->stored_file_name) . '/' . basename($document->stored_file_name));
            if (!file_exists($imagePath)) {
                $imagePath = public_path('storage/uploads/' . dirname($document->stored_file_name) . '/' . basename($document->stored_file_name));
            }
        @endphp
        
        @if(file_exists($imagePath))
            <img src="data:image/{{ pathinfo($imagePath, PATHINFO_EXTENSION) }};base64,{{ base64_encode(file_get_contents($imagePath)) }}" 
                 alt="وثيقة {{ $index + 1 }}" 
                 class="document-image">
        @endif
    </div>
@endforeach

</body>
</html>
