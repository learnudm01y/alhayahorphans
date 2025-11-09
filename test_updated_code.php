<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=================================================================\n";
echo "        اختبار الكود المحدث - leftJoin\n";
echo "=================================================================\n\n";

// اختبار API مباشرة
$controller = new \App\Http\Controllers\Admin\FamilyRelationController();

$testId = '407015692';

echo "🔍 اختبار البحث عن: {$testId}\n";
echo "-----------------------------------------------------------------\n\n";

try {
    // إنشاء request مزيف
    $request = new \Illuminate\Http\Request();
    $request->merge(['id_number' => $testId]);

    $response = $controller->searchFamilyRelations($request);
    $data = $response->getData(true);

    if ($data['success']) {
        echo "✅ API نجح!\n\n";

        // طباعة جميع البيانات المتاحة
        echo "📋 البيانات المسترجعة:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

        echo "📊 إحصائيات:\n";
        if (isset($data['data']['total_members'])) {
            echo "   • عدد أفراد العائلة: " . $data['data']['total_members'] . "\n";
        }
        if (isset($data['data']['execution_time'])) {
            echo "   • وقت التنفيذ: " . $data['data']['execution_time'] . " ثانية\n";
        }
        if (isset($data['data']['family_members'])) {
            echo "   • عدد الأفراد: " . count($data['data']['family_members']) . "\n";
        }
        echo "\n";

        if (!empty($data['data']['family_members'])) {
            echo "👥 أفراد العائلة:\n";
            echo "-----------------------------------------------------------------\n";
            foreach ($data['data']['family_members'] as $i => $member) {
                echo "\n" . ($i + 1) . ". {$member['relation_type']}:\n";
                echo "   • رقم الهوية: {$member['id_number']}\n";
                echo "   • الاسم: {$member['full_name']}\n";

                if (isset($member['data_available'])) {
                    echo "   • توفر البيانات: " . ($member['data_available'] ? '✅ متوفرة' : '❌ غير متوفرة') . "\n";
                }

                if (!empty($member['children'])) {
                    echo "   • عدد الأبناء: " . count($member['children']) . "\n";
                    foreach ($member['children'] as $child) {
                        echo "      - {$child['full_name']} ({$child['relation_type']})\n";
                    }
                }
            }
        }

        if (!empty($data['data']['relation_types'])) {
            echo "\n\n📈 إحصائيات أنواع العلاقات:\n";
            echo "-----------------------------------------------------------------\n";
            foreach ($data['data']['relation_types'] as $type => $count) {
                echo "   • {$type}: {$count}\n";
            }
        }

    } else {
        echo "❌ API فشل!\n";
        echo "   الرسالة: {$data['message']}\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ في التنفيذ:\n";
    echo "   {$e->getMessage()}\n";
    echo "   السطر: {$e->getLine()}\n";
    echo "   الملف: {$e->getFile()}\n";
}

echo "\n=================================================================\n";
echo "✅ انتهى الاختبار\n";
echo "=================================================================\n\n";
