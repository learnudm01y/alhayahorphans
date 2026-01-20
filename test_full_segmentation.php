<?php
/**
 * 🧪 اختبار شامل لخوارزمية تقسيم الأسماء وربطها بالاستيراد
 * يختبر:
 * 1. تقسيم الأسماء العربية بشكل صحيح
 * 2. التعامل مع الأسماء المركبة
 * 3. محاكاة عملية الاستيراد من Excel
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Helpers/NameSegmentation.php';

use App\Helpers\NameSegmentation;
use PhpOffice\PhpSpreadsheet\IOFactory;

mb_internal_encoding('UTF-8');

echo "═══════════════════════════════════════════════════════════════\n";
echo "   🧪 اختبار شامل: تقسيم الأسماء وربطها بعملية الاستيراد\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// الجزء 1: اختبار خوارزمية التقسيم
echo "📋 الجزء 1: اختبار خوارزمية تقسيم الأسماء\n";
echo str_repeat('-', 60) . "\n\n";

$testCases = [
    // أسماء عادية (4 مقاطع)
    ['name' => 'عمر احمد نظمي سعدة', 'expected_count' => 4],
    ['name' => 'تالا اسامة محمد شبير', 'expected_count' => 4],

    // أسماء مركبة (عبد + لاحقة)
    ['name' => 'ريم عبد الجليل محمود خميسي', 'expected_count' => 4],
    ['name' => 'عبد الرحمن محمد أحمد الشيخ', 'expected_count' => 4],

    // أسماء مركبة (أبو/ابو)
    ['name' => 'راما عبد الكريم ناصر ابو جرمي', 'expected_count' => 4],
    ['name' => 'أبو بكر محمد علي حسن', 'expected_count' => 4],

    // أسماء طويلة (أكثر من 4 مقاطع)
    ['name' => 'عمر بن الخطاب حسن شعبان خميسي', 'expected_count' => 4],
    ['name' => 'غيث عدي عوني عبدالفتاح عطا الله', 'expected_count' => 4],

    // أسماء قصيرة
    ['name' => 'محمد', 'expected_count' => 1],
    ['name' => 'أحمد علي', 'expected_count' => 2],
    ['name' => 'محمد أحمد علي', 'expected_count' => 3],
];

$passedTests = 0;
$totalTests = count($testCases);

foreach ($testCases as $case) {
    $result = NameSegmentation::segment($case['name']);
    $status = '✅';

    // التحقق من عدم فقدان أي جزء
    $reconstructed = trim(implode(' ', array_filter([
        $result['first_name'],
        $result['father_name'],
        $result['grand_father_name'],
        $result['family_name']
    ])));

    $nameMatch = ($reconstructed === $result['full_name']);

    if (!$nameMatch) {
        $status = '❌';
    } else {
        $passedTests++;
    }

    echo "$status الاسم: {$case['name']}\n";
    echo "   └─ النتيجة: [{$result['first_name']}] [{$result['father_name']}] [{$result['grand_father_name']}] [{$result['family_name']}]\n";
    if (!$nameMatch) {
        echo "   ⚠️ تحذير: فقدان بيانات! الأصل: {$result['full_name']} | المُعاد: $reconstructed\n";
    }
    echo "\n";
}

echo "📊 نتيجة اختبار التقسيم: $passedTests/$totalTests اجتياز\n\n";

// الجزء 2: قراءة ملف Excel الحقيقي
echo "═══════════════════════════════════════════════════════════════\n";
echo "📋 الجزء 2: تحليل أسماء من ملف Excel الحقيقي\n";
echo str_repeat('-', 60) . "\n\n";

$excelPath = __DIR__ . '/template كفالات (2).xlsx';

if (file_exists($excelPath)) {
    $spreadsheet = IOFactory::load($excelPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = min(15, $worksheet->getHighestRow()); // أول 14 صف

    echo "📂 ملف: template كفالات (2).xlsx\n\n";

    for ($row = 2; $row <= $highestRow; $row++) {
        $sponsoredName = $worksheet->getCell('B' . $row)->getValue();
        $guardianName = $worksheet->getCell('D' . $row)->getValue();

        if (empty($sponsoredName) && empty($guardianName)) continue;

        echo "🔸 الصف $row:\n";

        if (!empty($sponsoredName)) {
            $sponsoredResult = NameSegmentation::segment($sponsoredName);
            echo "   المكفول: $sponsoredName\n";
            echo "   └─ التقسيم: ";
            echo "الأول:[{$sponsoredResult['first_name']}] ";
            echo "الأب:[{$sponsoredResult['father_name']}] ";
            echo "الجد:[{$sponsoredResult['grand_father_name']}] ";
            echo "العائلة:[{$sponsoredResult['family_name']}]\n";
        }

        if (!empty($guardianName)) {
            $guardianResult = NameSegmentation::segment($guardianName);
            echo "   المعيل: $guardianName\n";
            echo "   └─ التقسيم: ";
            echo "الأول:[{$guardianResult['first_name']}] ";
            echo "الأب:[{$guardianResult['father_name']}] ";
            echo "الجد:[{$guardianResult['grand_father_name']}] ";
            echo "العائلة:[{$guardianResult['family_name']}]\n";
        }

        echo "\n";
    }
} else {
    echo "⚠️ ملف Excel غير موجود: $excelPath\n";
}

// الجزء 3: ملخص آلية العمل
echo "═══════════════════════════════════════════════════════════════\n";
echo "📋 الجزء 3: ملخص آلية العمل الجديدة\n";
echo str_repeat('-', 60) . "\n\n";

echo "🔹 عند استيراد ملف Excel:\n\n";

echo "   1️⃣ للمعيل (هوية المعيل):\n";
echo "      ├─ البحث في جدول data\n";
echo "      ├─ البحث في جدول dead_people\n";
echo "      ├─ البحث في السجل المدني → إنشاء في data مع الاسم الرباعي\n";
echo "      └─ ✨ جديد: تقسيم الاسم من Excel باستخدام NameSegmentation → إنشاء في data\n\n";

echo "   2️⃣ للمكفول (رقم هوية المكفول):\n";
echo "      ├─ البحث في جدول re_people\n";
echo "      ├─ البحث في السجل المدني → إنشاء في re_people مع الاسم الرباعي\n";
echo "      └─ ✨ جديد: إذا لم يُوجد في السجل المدني → إدخال في sponsorships فقط\n\n";

echo "   3️⃣ خوارزمية تقسيم الأسماء (NameSegmentation):\n";
echo "      ├─ دعم الأسماء المركبة (عبد الرحمن، صلاح الدين، أبو بكر)\n";
echo "      ├─ دعم Unicode/UTF-8 الكامل للأحرف العربية\n";
echo "      ├─ توزيع ذكي على 4 حقول (الأول، الأب، الجد، العائلة)\n";
echo "      └─ دمج المقاطع الزائدة في حقل الجد\n\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "                     ✅ انتهى الاختبار الشامل\n";
echo "═══════════════════════════════════════════════════════════════\n";
