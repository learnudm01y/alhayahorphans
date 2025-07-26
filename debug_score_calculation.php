<?php

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "🔍 فحص النقاط المُحتسبة للبحث\n";
    echo "=" . str_repeat("=", 50) . "\n";

    $query = "Kyle Christine Byers Brenda Mason Nayda Ayers";
    echo "🎯 البحث عن: '$query'\n";

    // الحصول على السجل المطلوب
    $targetRecord = \App\Models\Data::find(24);
    if (!$targetRecord) {
        echo "❌ السجل غير موجود\n";
        return;
    }

    echo "📋 بيانات السجل:\n";
    echo "   الاسم الأول: '{$targetRecord->data_first_name}'\n";
    echo "   اسم الأب: '{$targetRecord->data_father_name}'\n";
    echo "   اسم الجد: '{$targetRecord->data_grand_father_name}'\n";
    echo "   اسم العائلة: '{$targetRecord->data_family_name}'\n";

    // تنسيق الاسم الكامل
    $fullName = implode(' ', array_filter([
        $targetRecord->data_first_name,
        $targetRecord->data_father_name,
        $targetRecord->data_grand_father_name,
        $targetRecord->data_family_name
    ]));

    echo "📝 الاسم الكامل: '$fullName'\n\n";

    // حساب النقاط باستخدام نفس الخوارزمية
    $score = 0;
    $queryLower = strtolower($query);
    $fullNameLower = strtolower($fullName);

    echo "🔢 حساب النقاط:\n";

    // التطابق الكامل
    if ($fullNameLower === $queryLower) {
        $score += 100;
        echo "   ✅ تطابق كامل: +100 نقطة\n";
    } elseif (strpos($fullNameLower, $queryLower) === 0) {
        $score += 80;
        echo "   ✅ يبدأ بالنص: +80 نقطة\n";
    } elseif (strpos($fullNameLower, $queryLower) !== false) {
        $score += 60;
        echo "   ✅ يحتوي على النص: +60 نقطة\n";
    }

    // فحص الكلمات
    $words = preg_split('/\s+/', trim($query));
    $words = array_filter($words, function($word) {
        return strlen(trim($word)) >= 2;
    });

    echo "   📝 الكلمات: " . implode(', ', $words) . "\n";
    echo "   📊 عدد الكلمات: " . count($words) . "\n";

    foreach ($words as $word) {
        $wordLower = strtolower($word);
        if (strpos($fullNameLower, $wordLower) !== false) {
            $score += 30;
            echo "   ✅ كلمة '$word' موجودة: +30 نقطة\n";
        } else {
            echo "   ❌ كلمة '$word' غير موجودة: +0 نقطة\n";
        }
    }

    echo "\n🎯 النقاط الإجمالية: $score نقطة\n";

    // فحص الحد الأدنى المطلوب
    $minRelevance = count($words) > 3 ? 50 : 30;
    echo "🔍 الحد الأدنى المطلوب: $minRelevance نقطة\n";
    echo "📊 النتيجة: " . ($score >= $minRelevance ? "✅ سيظهر" : "❌ لن يظهر") . "\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
