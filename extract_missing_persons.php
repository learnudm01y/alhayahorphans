<?php

/**
 * سكربت لاستخراج قائمة الأشخاص المفقودين من ملف laravel.log
 * وحفظها في ملف JSON لاستخدامها لاحقاً
 *
 * الاستخدام:
 * php extract_missing_persons.php
 */

$logFile = __DIR__ . '/storage/logs/laravel.log';
$outputFile = __DIR__ . '/missing_persons.json';

echo "=== استخراج الأشخاص المفقودين من السجل ===\n\n";

if (!file_exists($logFile)) {
    die("❌ ملف السجل غير موجود: {$logFile}\n");
}

// قراءة محتوى الملف
$content = file_get_contents($logFile);

// البحث عن سطر "missing_details"
$pattern = '/"missing_details":\s*(\[[\s\S]*?\])\s*[,}]/';

if (preg_match($pattern, $content, $matches)) {
    // محاولة فك ترميز JSON
    $missingDetails = $matches[1];

    // تنظيف الـ JSON
    $missingDetails = preg_replace('/\s+/', ' ', $missingDetails);

    $missingPersons = json_decode($missingDetails, true);

    if ($missingPersons === null) {
        echo "⚠️ لم يتم العثور على بيانات JSON صالحة\n";
        echo "محاولة البحث بطريقة بديلة...\n\n";

        // طريقة بديلة: البحث عن الأشخاص المفقودين باستخدام regex
        $missingPersons = extractMissingPersonsAlternative($content);
    }

    if (empty($missingPersons)) {
        echo "لم يتم العثور على أشخاص مفقودين\n";
        exit;
    }

    echo "✅ تم العثور على " . count($missingPersons) . " شخص مفقود\n\n";

    // تصنيف الأشخاص حسب النوع
    $familyMembers = [];
    $guardians = [];

    foreach ($missingPersons as $person) {
        $type = normalizePersonType($person['type'] ?? '');

        if (in_array($type, ['فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة'])) {
            $familyMembers[] = $person;
        } elseif (in_array($type, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل عائله', 'معيل عائلة'])) {
            $guardians[] = $person;
        }
    }

    echo "📊 التوزيع:\n";
    echo "   - أفراد عائلة: " . count($familyMembers) . "\n";
    echo "   - معيلين: " . count($guardians) . "\n\n";

    // حفظ البيانات في ملف JSON
    $output = [
        'extracted_at' => date('Y-m-d H:i:s'),
        'total_count' => count($missingPersons),
        'family_members_count' => count($familyMembers),
        'guardians_count' => count($guardians),
        'family_members' => $familyMembers,
        'guardians' => $guardians,
    ];

    file_put_contents($outputFile, json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo "✅ تم حفظ البيانات في: {$outputFile}\n\n";

    // عرض عينة من البيانات
    echo "=== عينة من أفراد العائلة (أول 5) ===\n";
    foreach (array_slice($familyMembers, 0, 5) as $person) {
        echo "   - {$person['name']} (هوية: {$person['identity']})\n";
    }

    echo "\n=== عينة من المعيلين (أول 5) ===\n";
    foreach (array_slice($guardians, 0, 5) as $person) {
        echo "   - {$person['name']} (هوية: {$person['identity']})\n";
    }

} else {
    echo "❌ لم يتم العثور على بيانات الأشخاص المفقودين في السجل\n";
    echo "تأكد من تشغيل عملية الفحص (Check Only) أولاً\n";
}

/**
 * استخراج الأشخاص المفقودين بطريقة بديلة
 */
function extractMissingPersonsAlternative($content) {
    $missingPersons = [];

    // البحث عن أنماط مثل: "type":"فرد عائله","identity":"..."
    $pattern = '/\{"row":\d+,"type":"([^"]+)","target_table":"([^"]+)","identity":"([^"]+)","name":"([^"]*)"[^}]*\}/';

    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $missingPersons[] = [
                'type' => $match[1],
                'target_table' => $match[2],
                'identity' => $match[3],
                'name' => $match[4],
            ];
        }
    }

    return $missingPersons;
}

/**
 * تطبيع نوع الشخص
 */
function normalizePersonType($type) {
    $type = trim($type);
    // تطبيع الحروف العربية
    $replacements = [
        'ه' => 'ه',
        'ة' => 'ه',
        'ی' => 'ي',
        'ى' => 'ي',
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
    ];

    foreach ($replacements as $from => $to) {
        $type = str_replace($from, $to, $type);
    }

    return $type;
}
