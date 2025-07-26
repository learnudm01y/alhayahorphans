<?php

require_once 'vendor/autoload.php';

use App\Services\SearchService;

echo "=== اختبار محرك البحث المحسن ===\n\n";

$searchService = new SearchService();

// اختبارات مختلفة للبحث
$testCases = [
    [
        'name' => 'البحث بالاسم الكامل',
        'filters' => [
            'search_text' => 'Keith Zachery',
            'search_type' => 'all'
        ]
    ],
    [
        'name' => 'البحث بالاسم الأول والأخير',
        'filters' => [
            'search_text' => 'Keith Short',
            'search_type' => 'all'
        ]
    ],
    [
        'name' => 'البحث بكلمة واحدة',
        'filters' => [
            'search_text' => 'Keith',
            'search_type' => 'all'
        ]
    ],
    [
        'name' => 'البحث في السجلات الرئيسية فقط',
        'filters' => [
            'search_text' => 'Keith',
            'search_type' => 'main_records'
        ]
    ],
    [
        'name' => 'البحث في أفراد الأسرة فقط',
        'filters' => [
            'search_text' => 'Fuller',
            'search_type' => 'family_members'
        ]
    ]
];

foreach ($testCases as $test) {
    echo "--- {$test['name']} ---\n";
    echo "البحث عن: {$test['filters']['search_text']}\n";

    try {
        $results = $searchService->smartSearch($test['filters'], 5);

        if (isset($results['data'])) {
            // نتائج البحث المُصفح
            echo "النتائج: " . count($results['data']) . " سجل\n";
            foreach ($results['data'] as $record) {
                $name = $record['full_name'] ?? ($record['data_first_name'] ?? 'غير محدد');
                $type = $record['type'] ?? 'غير محدد';
                echo "- {$name} ({$type})\n";
            }
        } else {
            // نتائج البحث الشامل
            $total = $results['total_count'] ?? 0;
            echo "إجمالي النتائج: {$total}\n";

            if (!empty($results['main_records'])) {
                echo "السجلات الرئيسية: " . count($results['main_records']) . "\n";
                foreach (array_slice($results['main_records'], 0, 3) as $record) {
                    echo "- {$record['full_name']}\n";
                }
            }

            if (!empty($results['family_members'])) {
                echo "أفراد الأسرة: " . count($results['family_members']) . "\n";
                foreach (array_slice($results['family_members'], 0, 3) as $record) {
                    echo "- {$record['full_name']}\n";
                }
            }
        }

    } catch (Exception $e) {
        echo "خطأ: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "=== اختبار الاقتراحات التلقائية ===\n";
$suggestions = $searchService->getSearchSuggestions('Keith', 5);
echo "اقتراحات للبحث عن 'Keith':\n";
foreach ($suggestions as $suggestion) {
    echo "- {$suggestion}\n";
}

echo "\n=== إحصائيات البحث ===\n";
$stats = $searchService->getSearchStatistics();
foreach ($stats as $key => $value) {
    echo "{$key}: {$value}\n";
}

?>
