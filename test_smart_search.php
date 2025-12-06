<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\BankName;
use Illuminate\Support\Facades\DB;

// دالة normalization محسّنة
function normalizeArabicText($text)
{
    // تحويل جميع أشكال الألف إلى ألف عادية
    $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);

    // تحويل التاء المربوطة إلى هاء
    $text = str_replace(['ة'], 'ه', $text);

    // تحويل الياء المختلفة
    $text = str_replace(['ى'], 'ي', $text);

    // إزالة التشكيل (الحركات)
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);

    // إزالة المسافات الزائدة
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

// دالة البحث الذكي
function smartSearch($query, $column, $searchValue, $exactMatch = false)
{
    $normalized = normalizeArabicText($searchValue);

    return $query->where(function($q) use ($column, $searchValue, $normalized, $exactMatch) {
        // 1. البحث الدقيق أولاً
        $q->where($column, $searchValue);

        if ($exactMatch) {
            // 2. البحث مع normalization فقط
            $q->orWhereRaw(
                'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(' . $column . ', "أ", "ا"), "إ", "ا"), "آ", "ا"), "ٱ", "ا"), "ة", "ه"), "ى", "ي") = ?',
                [$normalized]
            );
        } else {
            // 2. البحث الجزئي
            $q->orWhere($column, 'LIKE', "%{$searchValue}%");

            // 3. البحث الجزئي مع normalization
            $q->orWhereRaw(
                'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(' . $column . ', "أ", "ا"), "إ", "ا"), "آ", "ا"), "ٱ", "ا"), "ة", "ه"), "ى", "ي") LIKE ?',
                ["%{$normalized}%"]
            );
        }
    });
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         🔍 اختبار نظام البحث الذكي المتقدم                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// حالات اختبار متنوعة
$testCases = [
    // اختبار البنوك
    ['type' => 'بنك', 'search' => 'محفظة Palpay بال بي', 'exact' => false],
    ['type' => 'بنك', 'search' => 'بال بي', 'exact' => false],
    ['type' => 'بنك', 'search' => 'Palpay', 'exact' => false],
    ['type' => 'بنك', 'search' => 'محفظه', 'exact' => false], // تاء مربوطة → هاء

    // اختبار الألف بأشكالها
    ['type' => 'بنك', 'search' => 'محفظة', 'exact' => false],
    ['type' => 'بنك', 'search' => 'محفظه', 'exact' => false], // ة → ه

    // اختبار البحث الدقيق vs الجزئي
    ['type' => 'بنك', 'search' => 'اختبار', 'exact' => true],
    ['type' => 'بنك', 'search' => 'اختبار اسم البنك', 'exact' => false],
];

foreach ($testCases as $index => $test) {
    $testNum = $index + 1;
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "🧪 اختبار #{$testNum}\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo "📝 نوع: {$test['type']}\n";
    echo "🔎 البحث عن: '{$test['search']}'\n";
    echo "⚙️  نوع البحث: " . ($test['exact'] ? 'دقيق' : 'جزئي') . "\n";
    echo "📐 بعد normalization: '" . normalizeArabicText($test['search']) . "'\n\n";

    $bank = BankName::where(function($query) use ($test) {
        smartSearch($query, 'description', $test['search'], $test['exact']);
    })->first();

    if ($bank) {
        echo "✅ تم العثور على النتيجة:\n";
        echo "   🆔 ID: {$bank->id}\n";
        echo "   📛 الاسم: {$bank->description}\n";
    } else {
        echo "❌ لم يتم العثور على نتائج\n";
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "📊 جميع البنوك المتاحة في النظام:\n";
echo "───────────────────────────────────────────────────────────────\n";
$allBanks = BankName::select('id', 'description')->get();
foreach ($allBanks as $bank) {
    echo sprintf("   %2d. %s\n", $bank->id, $bank->description);
}
echo "\n";

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           ✨ مميزات البحث الذكي المحسّن                       ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
echo "║  ✓ دعم جميع أشكال الألف (أ، إ، آ، ٱ → ا)                     ║\n";
echo "║  ✓ تحويل التاء المربوطة (ة → ه)                              ║\n";
echo "║  ✓ تحويل الياء المختلفة (ى → ي)                              ║\n";
echo "║  ✓ إزالة التشكيل والحركات تلقائياً                           ║\n";
echo "║  ✓ إزالة المسافات الزائدة                                     ║\n";
echo "║  ✓ البحث الدقيق والجزئي                                       ║\n";
echo "║  ✓ دعم النصوص المختلطة (عربي + إنجليزي)                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
