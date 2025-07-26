<?php

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 === اختبار البحث الشامل لـ 'سعاد صلاح رضوان بلال' ===\n\n";

try {
    // اختبار البحث المباشر في قاعدة البيانات
    echo "1. اختبار البحث المباشر في قاعدة البيانات:\n";

    $searchTerm = 'سعاد صلاح رضوان بلال';

    // البحث بالاسم الكامل
    $fullNameResults = \DB::table('persons')
        ->whereRaw("CONCAT(COALESCE(CI_FIRST_ARB, ''), ' ', COALESCE(CI_FATHER_ARB, ''), ' ', COALESCE(CI_FAMILY_ARB, '')) LIKE ?", ["%{$searchTerm}%"])
        ->limit(10)
        ->get();

    echo "   - البحث بالاسم الكامل: " . $fullNameResults->count() . " نتيجة\n";

    // البحث بأجزاء الاسم
    $namePartsResults = \DB::table('persons')
        ->where(function ($query) use ($searchTerm) {
            $query->where('CI_FIRST_ARB', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('CI_FATHER_ARB', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('CI_FAMILY_ARB', 'LIKE', "%{$searchTerm}%");
        })
        ->limit(10)
        ->get();

    echo "   - البحث بأجزاء الاسم: " . $namePartsResults->count() . " نتيجة\n";

    // البحث بكلمات منفصلة
    $words = explode(' ', $searchTerm);
    $wordResults = \DB::table('persons')
        ->where(function ($query) use ($words) {
            foreach ($words as $word) {
                if (!empty(trim($word))) {
                    $query->orWhere('CI_FIRST_ARB', 'LIKE', "%{$word}%")
                          ->orWhere('CI_FATHER_ARB', 'LIKE', "%{$word}%")
                          ->orWhere('CI_FAMILY_ARB', 'LIKE', "%{$word}%");
                }
            }
        })
        ->limit(10)
        ->get();

    echo "   - البحث بالكلمات المنفصلة: " . $wordResults->count() . " نتيجة\n";

    // عرض أول نتيجة إن وجدت
    $result = $fullNameResults->first() ?: $namePartsResults->first() ?: $wordResults->first();

    if ($result) {
        echo "\n   ✅ تم العثور على نتائج!\n";
        echo "   - الاسم: " . ($result->CI_FIRST_ARB ?? '') . " " . ($result->CI_FATHER_ARB ?? '') . " " . ($result->CI_FAMILY_ARB ?? '') . "\n";
        echo "   - رقم الهوية: " . ($result->CI_ID_NUM ?? 'غير محدد') . "\n";
        echo "   - المدينة: " . ($result->CITY ?? 'غير محدد') . "\n";
    } else {
        echo "\n   ❌ لم يتم العثور على نتائج\n";

        // اختبار وجود أي بيانات
        $totalRecords = \DB::table('persons')->count();
        echo "   - إجمالي السجلات في الجدول: {$totalRecords}\n";

        // اختبار أول 5 أسماء
        $sampleNames = \DB::table('persons')
            ->select('CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_ID_NUM')
            ->whereNotNull('CI_FIRST_ARB')
            ->limit(5)
            ->get();

        echo "   - عينة من الأسماء الموجودة:\n";
        foreach ($sampleNames as $name) {
            echo "     * " . ($name->CI_FIRST_ARB ?? '') . " " . ($name->CI_FATHER_ARB ?? '') . " " . ($name->CI_FAMILY_ARB ?? '') . "\n";
        }
    }

    echo "\n2. اختبار CustomScoutSearchService:\n";

    $scoutService = new \App\Services\CustomScoutSearchService();

    $scoutResults = $scoutService->ultraFastSearch($searchTerm, 10);
    echo "   - نتائج Scout Service: " . count($scoutResults) . "\n";

    if (count($scoutResults) > 0) {
        $first = $scoutResults[0];
        echo "   - أول نتيجة Scout: " . ($first->CI_FIRST_ARB ?? '') . " " . ($first->CI_FATHER_ARB ?? '') . " " . ($first->CI_FAMILY_ARB ?? '') . "\n";
    }

    echo "\n✅ انتهى الاختبار\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "الملف: " . $e->getFile() . "\n";
    echo "السطر: " . $e->getLine() . "\n";
}
