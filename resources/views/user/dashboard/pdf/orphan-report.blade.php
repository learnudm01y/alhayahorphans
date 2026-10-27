@php
    $reportGroups = $form_field_groups ?? [];
    $sections = [
        'basic' => [],
        'father' => [],
        'mother' => [],
        'guardian' => [],
        'housing' => [],
        'education' => [],
        'health' => [],
        'religion' => [],
        'needs' => [],
        'impact' => [],
        'additional' => [],
    ];
    $sectionColors = [
        'basic' => 'header-blue',
        'father' => 'header-pink',
        'mother' => 'header-pink',
        'guardian' => 'header-black',
        'housing' => 'header-blue',
        'education' => 'header-blue',
        'health' => 'header-blue',
        'religion' => 'header-blue',
        'needs' => 'header-blue',
        'impact' => 'header-dark',
        'additional' => 'header-dark',
    ];
    $sectionColorAssigned = [];
    $renderedFieldValues = [];

    foreach ($reportGroups as $group) {
        $categoryId = (string) ($group['category_id'] ?? '');
        $headerClass = $group['header_class'] ?? 'header-dark';
        if ($categoryId === 'name_parts') {
            continue;
        }

        foreach (($group['fields'] ?? []) as $field) {
            $field['header_class'] = $headerClass;
            $label = (string) ($field['label'] ?? '');
            $fieldValue = $field['value'] ?? null;
            if (is_array($fieldValue)) {
                $comparisonValue = implode('، ', array_map(function ($item) {
                    return is_scalar($item) ? trim((string) $item) : '';
                }, $fieldValue));
            } else {
                $comparisonValue = is_scalar($fieldValue) ? trim((string) $fieldValue) : '';
            }
            $comparisonLabel = preg_replace('/\s+/u', ' ', trim($label));

            if ($comparisonLabel !== '' && $comparisonValue !== '') {
                $fieldFingerprint = $comparisonLabel . "\0" . $comparisonValue;
                if (isset($renderedFieldValues[$fieldFingerprint])) {
                    continue;
                }
                $renderedFieldValues[$fieldFingerprint] = true;
            }

            $target = 'additional';

            if (str_contains($label, 'طموح')) {
                $target = 'needs';
            } elseif ($categoryId === '1') {
                $target = 'basic';
            } elseif (in_array($categoryId, ['8', '13', '16'], true)) {
                $target = 'guardian';
            } elseif ($categoryId === '13.5') {
                $target = 'mother';
            } elseif ($categoryId === '14') {
                if (str_contains($label, 'الأب')) {
                    $target = 'father';
                } elseif (str_contains($label, 'الأم')) {
                    $target = 'mother';
                }
            } elseif ($categoryId === '2') {
                $target = 'housing';
            } elseif ($categoryId === '3') {
                $target = 'education';
            } elseif (in_array($categoryId, ['4', '6'], true)) {
                $target = 'health';
            } elseif ($categoryId === '5') {
                $target = 'religion';
            } elseif ($categoryId === '7') {
                $target = 'needs';
            } elseif ($categoryId === '10') {
                $target = 'impact';
            }

            $sections[$target][] = $field;
            if (!isset($sectionColorAssigned[$target])) {
                $sectionColors[$target] = $headerClass;
                $sectionColorAssigned[$target] = true;
            }
        }
    }

    $sortFields = function (array $fields, bool $housing = false): array {
        $decorated = [];
        foreach ($fields as $index => $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($housing) {
                if (str_contains($label, 'المدينة')) {
                    $priority = 0;
                } elseif (str_contains($label, 'عنوان')) {
                    $priority = 1;
                } else {
                    $priority = 2;
                }
            } elseif (preg_match('/^(?:الاسم|اسم|إسم)/u', $label) === 1) {
                $priority = 0;
            } elseif (str_contains($label, 'هوية')) {
                $priority = 1;
            } elseif (str_contains($label, 'تاريخ')) {
                $priority = 2;
            } else {
                $priority = 3;
            }

            $decorated[] = [
                'field' => $field,
                'priority' => $priority,
                'index' => $index,
            ];
        }

        usort($decorated, function ($left, $right) {
            return ($left['priority'] <=> $right['priority'])
                ?: ($left['index'] <=> $right['index']);
        });

        return array_column($decorated, 'field');
    };
    foreach ($sections as $sectionKey => $fields) {
        $sections[$sectionKey] = $sortFields($fields, $sectionKey === 'housing');
    }
    $ambitionFields = [];
    $sections['needs'] = array_values(array_filter($sections['needs'], function ($field) use (&$ambitionFields) {
        if (str_contains((string) ($field['label'] ?? ''), 'طموح')) {
            $ambitionFields[] = $field;
            return false;
        }

        return true;
    }));
    $sections['needs'] = array_merge($sections['needs'], $ambitionFields);

    $valueToText = function ($value) {
        if (is_array($value)) {
            return implode('، ', array_map(function ($item) {
                return is_scalar($item) ? (string) $item : '';
            }, $value));
        }

        return is_scalar($value) ? (string) $value : '';
    };
    $makeRows = function (array $fields, int $columns) use ($valueToText) {
        $rows = [];
        $shortFields = [];

        foreach ($fields as $field) {
            $text = $valueToText($field['value'] ?? null);
            if (\Illuminate\Support\Str::length($text) > 60 || str_contains((string) ($field['label'] ?? ''), 'طموح')) {
                if ($shortFields) {
                    $rows[] = ['fields' => $shortFields, 'long' => false];
                    $shortFields = [];
                }
                $rows[] = ['fields' => [$field], 'long' => true];
                continue;
            }

            $shortFields[] = $field;
            if (count($shortFields) === $columns) {
                $rows[] = ['fields' => $shortFields, 'long' => false];
                $shortFields = [];
            }
        }

        if ($shortFields) {
            $rows[] = ['fields' => $shortFields, 'long' => false];
        }

        foreach ($rows as &$row) {
            $spanTotal = $columns === 6 ? 12 : 2;
            $fieldCount = count($row['fields']);
            $baseSpan = intdiv($spanTotal, $fieldCount);
            $extraSpans = $spanTotal % $fieldCount;
            $row['spans'] = [];
            for ($index = 0; $index < $fieldCount; $index++) {
                $row['spans'][] = $row['long'] ? $spanTotal : $baseSpan + ($index < $extraSpans ? 1 : 0);
            }
        }
        unset($row);

        return $rows;
    };
    $isLtrLabel = function ($label) {
        return preg_match('/(هوية|الهويه|الهاتف|الجوال|تاريخ|رقم|iban)/iu', (string) $label) === 1;
    };

    $familyMembersVisible = $visible_fields['field_family_members_section'] ?? true;
    $hasOrphanPhoto = !empty($orphan_photo)
        && !empty($orphan_photo['file_exists'])
        && !empty($orphan_photo['is_image']);

    $familyBlocks = [
        ['title' => 'الأب المتوفى', 'key' => 'father'],
    ];
    if (count($sections['mother'])) {
        $familyBlocks[] = ['title' => 'الأم', 'key' => 'mother'];
    }
    $familyBlocks[] = ['title' => 'المعيل', 'key' => 'guardian'];
    $familyBlockWidth = 100 / count($familyBlocks);

    $contentWeight = 0;
    foreach ($sections as $sectionKey => $fields) {
        if (in_array($sectionKey, ['father', 'mother', 'guardian'], true)) {
            continue;
        }

        $contentWeight += count($makeRows($fields, 6));
        foreach ($fields as $field) {
            $textLength = \Illuminate\Support\Str::length($valueToText($field['value'] ?? null));
            if ($textLength > 60) {
                $contentWeight += max(0, ceil($textLength / 90) - 1);
            }
        }
    }
    foreach ($familyBlocks as $block) {
        $blockFields = $sections[$block['key']];
        $contentWeight += count($makeRows($blockFields, 2));
        foreach ($blockFields as $field) {
            $textLength = \Illuminate\Support\Str::length($valueToText($field['value'] ?? null));
            if ($textLength > 60) {
                $contentWeight += max(0, ceil($textLength / 35) - 1);
            }
        }
    }
    if ($familyMembersVisible) {
        $contentWeight += count($family_members ?? []);
    }
    $reportSize = $contentWeight <= 34 ? 'lg' : ($contentWeight <= 58 ? 'md' : 'sm');
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'Cairo';
            src: url('file:///{{ str_replace("\\", "/", public_path("fonts/Cairo-Regular.ttf")) }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'Cairo';
            src: url('file:///{{ str_replace("\\", "/", public_path("fonts/Cairo-Bold.ttf")) }}') format('truetype');
            font-weight: bold;
        }
        * {
            box-sizing: border-box;
        }
        body {
            direction: rtl;
            font-family: 'Cairo', sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            color: #222;
            margin: 0;
            padding: 0;
        }
        body.size-lg {
            font-size: 10.5pt;
            line-height: 1.4;
        }
        body.size-md {
            font-size: 9.75pt;
            line-height: 1.37;
        }
        body.size-sm {
            font-size: 9pt;
            line-height: 1.35;
        }
        table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            page-break-inside: auto;
        }
        tr, td, th, .report-section, .family-block, .field-box {
            page-break-inside: avoid;
        }
        .report-header {
            position: relative;
            overflow: hidden;
            margin-bottom: 2px;
        }
        .report-header.with-photo {
            height: 30mm;
        }
        .report-header.no-photo {
            height: 10mm;
        }
        .report-title {
            position: absolute;
            top: 3mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 15pt;
            line-height: 1.2;
            margin: 0;
            font-weight: bold;
        }
        body.size-lg .report-title { font-size: 18pt; }
        body.size-md .report-title { font-size: 16pt; }
        .header-photo {
            position: absolute;
            top: 0;
            left: 0;
            width: 23mm;
            height: 29mm;
            overflow: hidden;
            border: 1px solid #cbd2d8;
            border-radius: 3px;
            text-align: center;
            line-height: 29mm;
        }
        .header-photo img {
            max-width: 23mm;
            max-height: 29mm;
            vertical-align: middle;
        }
        .report-section {
            margin: 0 0 4px;
        }
        .section-title {
            font-size: 11pt;
            line-height: 1.2;
            padding: 2px 5px;
            margin: 0;
            color: #fff;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
        }
        body.size-lg .section-title { font-size: 13pt; padding: 4px 6px; }
        body.size-md .section-title { font-size: 12pt; padding: 3px 5px; }
        .header-blue { background-color: #2878a5; }
        .header-pink { background-color: #bd6578; }
        .header-black { background-color: #333; }
        .header-gray { background-color: #777; }
        .header-dark { background-color: #4b5963; }
        .fields-table {
            margin: 0;
        }
        .fields-table td {
            border: 1px solid #d5d9dc;
            padding: 2px 3px;
            vertical-align: top;
            overflow-wrap: break-word;
        }
        body.size-lg .fields-table td { padding: 4px 5px; }
        body.size-md .fields-table td { padding: 3px 4px; }
        .field-label {
            display: block;
            color: #555;
            font-size: 9pt;
            line-height: 1.2;
        }
        body.size-lg .field-label { font-size: 9.5pt; }
        body.size-md .field-label { font-size: 9.25pt; }
        .field-value {
            display: block;
            font-size: 9.5pt;
            line-height: 1.3;
            white-space: pre-wrap;
            overflow-wrap: break-word;
        }
        body.size-lg .field-value { font-size: 11.25pt; line-height: 1.35; }
        body.size-md .field-value { font-size: 10.25pt; line-height: 1.33; }
        .muted {
            color: #999;
        }
        .family-table {
            margin: 0;
            table-layout: fixed;
        }
        .family-table > tbody > tr > td {
            padding: 0 2px;
            vertical-align: top;
        }
        .family-block {
            border: 1px solid #d5d9dc;
        }
        .family-block-title {
            color: #fff;
            font-size: 9pt;
            padding: 2px 4px;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
        }
        body.size-lg .family-block-title { font-size: 10.5pt; padding: 4px 5px; }
        body.size-md .family-block-title { font-size: 9.75pt; padding: 3px 4px; }
        .family-fields td {
            border: 1px solid #e0e3e5;
            padding: 2px;
            vertical-align: top;
            overflow-wrap: break-word;
        }
        body.size-lg .family-fields td { padding: 3px; }
        body.size-md .family-fields td { padding: 2px 3px; }
        .family-fields .field-label {
            font-size: 8.5pt;
        }
        body.size-lg .family-fields .field-label { font-size: 9.5pt; }
        body.size-md .family-fields .field-label { font-size: 9pt; }
        .family-fields .field-value {
            font-size: 8.5pt;
        }
        body.size-lg .family-fields .field-value { font-size: 11.25pt; line-height: 1.3; }
        body.size-md .family-fields .field-value { font-size: 10.25pt; line-height: 1.3; }
        .members-table {
            width: 100%;
            table-layout: fixed;
            font-size: 6.5pt;
            line-height: 1.2;
        }
        .members-table th, .members-table td {
            border: 1px solid #bfc5c9;
            padding: 1px 2px;
            vertical-align: top;
            overflow-wrap: break-word;
        }
        .members-table th {
            background: #e9edf0;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
        }
        body.size-lg .members-table { font-size: 7.5pt; }
        body.size-md .members-table { font-size: 7pt; }
        body.size-lg .members-table th, body.size-lg .members-table td { padding: 2px 3px; }
        body.size-md .members-table th, body.size-md .members-table td { padding: 1.5px 2px; }
        .member-identity, .member-date {
            white-space: nowrap;
        }
        .member-name {
            white-space: normal;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .empty-members {
            border: 1px solid #d5d9dc;
            padding: 3px 5px;
            color: #888;
            font-size: 8.5pt;
        }
        .ltr {
            direction: ltr;
            unicode-bidi: embed;
            display: inline-block;
        }
    </style>
</head>
<body class="size-{{ $reportSize }}">
    <header class="report-header {{ $hasOrphanPhoto ? 'with-photo' : 'no-photo' }}">
        <h1 class="report-title">تقرير بيانات</h1>
        @if($hasOrphanPhoto)
            <div class="header-photo">
                <img src="file:///{{ str_replace('\\', '/', $orphan_photo['file_path']) }}" alt="">
            </div>
        @endif
    </header>

    @if(count($sections['basic']))
        <section class="report-section">
            <h2 class="section-title {{ $sectionColors['basic'] }}">بيانات المكفول</h2>
            <table class="fields-table"><tbody>
                @foreach($makeRows($sections['basic'], 6) as $row)
                    <tr>
                        @foreach($row['fields'] as $fieldIndex => $field)
                            @php $text = $valueToText($field['value'] ?? null); @endphp
                            <td colspan="{{ $row['spans'][$fieldIndex] }}">
                                <span class="field-label">{{ $field['label'] ?? '' }}</span>
                                <span class="field-value {{ trim($text) === '' || trim($text) === 'لا يوجد' ? 'muted' : '' }} {{ $isLtrLabel($field['label'] ?? '') ? 'ltr' : '' }}">{{ $text }}</span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody></table>
        </section>
    @endif

    @if(count($sections['father']) || count($sections['mother']) || count($sections['guardian']))
        <section class="report-section">
            <h2 class="section-title {{ $sectionColors['guardian'] }}">الأسرة</h2>
            <table class="family-table">
                <colgroup>
                    @foreach($familyBlocks as $block)
                        <col style="width:{{ $familyBlockWidth }}%;">
                    @endforeach
                </colgroup>
                <tbody><tr>
                @foreach($familyBlocks as $block)
                    <td>
                        <div class="family-block">
                            <div class="family-block-title {{ $sectionColors[$block['key']] }}">{{ $block['title'] }}</div>
                            @if(count($sections[$block['key']]))
                                <table class="family-fields"><tbody>
                                    @foreach($makeRows($sections[$block['key']], 2) as $row)
                                        <tr>
                                            @foreach($row['fields'] as $fieldIndex => $field)
                                                @php $text = $valueToText($field['value'] ?? null); @endphp
                                                <td colspan="{{ $row['spans'][$fieldIndex] }}">
                                                    <span class="field-label">{{ $field['label'] ?? '' }}</span>
                                                    <span class="field-value {{ trim($text) === '' || trim($text) === 'لا يوجد' ? 'muted' : '' }} {{ $isLtrLabel($field['label'] ?? '') ? 'ltr' : '' }}">{{ $text }}</span>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody></table>
                            @else
                                <div class="empty-members">لا توجد بيانات مسجلة</div>
                            @endif
                        </div>
                    </td>
                @endforeach
            </tr></tbody></table>
        </section>
    @endif

    @foreach([
        ['key' => 'housing', 'title' => 'السكن'],
        ['key' => 'education', 'title' => 'التعليم'],
        ['key' => 'health', 'title' => 'الصحة والحالة النفسية والسلوكية'],
        ['key' => 'religion', 'title' => 'الجانب الديني'],
        ['key' => 'needs', 'title' => 'الاحتياجات والإبداع والطموح'],
        ['key' => 'impact', 'title' => 'أثر الكفالة والمتابعة'],
        ['key' => 'additional', 'title' => 'بيانات إضافية'],
    ] as $section)
        @if(count($sections[$section['key']]))
            <section class="report-section">
                <h2 class="section-title {{ $sectionColors[$section['key']] }}">{{ $section['title'] }}</h2>
                <table class="fields-table"><tbody>
                    @foreach($makeRows($sections[$section['key']], 6) as $row)
                        <tr>
                            @foreach($row['fields'] as $fieldIndex => $field)
                                @php $text = $valueToText($field['value'] ?? null); @endphp
                                <td colspan="{{ $row['spans'][$fieldIndex] }}">
                                    <span class="field-label">{{ $field['label'] ?? '' }}</span>
                                    <span class="field-value {{ trim($text) === '' || trim($text) === 'لا يوجد' ? 'muted' : '' }} {{ $isLtrLabel($field['label'] ?? '') ? 'ltr' : '' }}">{{ $text }}</span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody></table>
            </section>
        @endif
    @endforeach

    @if($familyMembersVisible)
    <section class="report-section">
        <h2 class="section-title {{ $sectionColors['basic'] }}">أفراد الأسرة</h2>
        @if(!empty($family_members))
            <table class="members-table">
                <colgroup>
                    <col style="width:4%;">
                    <col style="width:28%;">
                    <col style="width:14%;">
                    <col style="width:13%;">
                    <col style="width:20%;">
                    <col style="width:21%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>م</th>
                        <th>الاسم</th>
                        <th>رقم الهوية</th>
                        <th>تاريخ الميلاد</th>
                        <th>مستوى التعليم</th>
                        <th>الحالة الصحية</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($family_members as $member)
                        <tr>
                            <td>{{ $member['index'] ?? '' }}</td>
                            <td class="member-name">{{ $member['full_name'] ?? '' }}</td>
                            <td class="member-identity"><span class="ltr">{{ $member['person_id'] ?? '' }}</span></td>
                            <td class="member-date"><span class="ltr">{{ $member['birth_date'] ?? '' }}</span></td>
                            <td>{{ $member['academic_degree'] ?? '' }}</td>
                            <td>{{ $member['health_status'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-members">لا يوجد أفراد أسرة مسجلين</div>
        @endif
    </section>
    @endif
</body>
</html>
