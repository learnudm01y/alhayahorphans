<?php

use App\Services\SearchService;
use Illuminate\Support\Facades\Log;

// تشغيل البحث مباشرة
try {
    echo "=== اختبار SearchService مباشرة ===\n\n";

    $searchService = new SearchService();

    $filters = [
        'search_type' => 'main_records',  // البحث في جدول data فقط
        'search_text' => 'Kyle Christine Byers Brenda Mason Nayda Ayers'
    ];

    echo "البحث في: {$filters['search_text']}\n";
    echo "نوع البحث: {$filters['search_type']}\n\n";

    $results = $searchService->smartSearch($filters, 25);

    echo "=== النتائج ===\n";
    if (isset($results['data']) && !empty($results['data'])) {
        echo "عدد النتائج: " . count($results['data']) . "\n";

        foreach ($results['data'] as $record) {
            echo "- ID: {$record['id']}, الاسم: " . ($record['full_name'] ?? 'غير محدد') . "\n";
        }

        if (isset($results['pagination'])) {
            echo "\nمعلومات التصفح:\n";
            echo "- الصفحة الحالية: {$results['pagination']['current_page']}\n";
            echo "- إجمالي الصفحات: {$results['pagination']['total_pages']}\n";
            echo "- إجمالي السجلات: {$results['pagination']['total_records']}\n";
        }
    } else {
        echo "❌ لم يتم العثور على أي نتائج\n";
        echo "بنية النتائج:\n";
        print_r($results);
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
    echo "في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
    echo "التفاصيل:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== انتهى الاختبار ===\n";

?>
