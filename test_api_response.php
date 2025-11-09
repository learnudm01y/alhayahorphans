<?php
/**
 * اختبار API العلاقات العائلية
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "\n=================================================================\n";
echo "               اختبار API العلاقات العائلية                     \n";
echo "=================================================================\n\n";

$testId = '407015692';

echo "🔍 اختبار API للرقم: {$testId}\n\n";

try {
    // استدعاء الـ Controller مباشرة
    $controller = new \App\Http\Controllers\Admin\FamilyRelationController();

    $request = new \Illuminate\Http\Request();
    $request->merge(['id_number' => $testId]);

    $response = $controller->searchFamilyRelations($request);
    $data = json_decode($response->getContent(), true);

    if ($data['success']) {
        echo "✅ نجح الاستعلام\n\n";

        $familyMembers = $data['data']['family_members'];

        echo "📊 عدد الأفراد الرئيسيين: " . count($familyMembers) . "\n\n";

        foreach ($familyMembers as $index => $member) {
            echo "┌─────────────────────────────────────────────────────────────┐\n";
            echo "│ فرد رقم " . ($index + 1) . ": {$member['full_name']}\n";
            echo "│ العلاقة: {$member['relation_type']}\n";
            echo "│ الرقم: {$member['id_number']}\n";

            if (isset($member['children']) && is_array($member['children'])) {
                echo "│ عدد الأبناء: " . count($member['children']) . "\n";

                if (count($member['children']) > 0) {
                    echo "│\n";
                    echo "│ الأبناء:\n";
                    foreach ($member['children'] as $childIndex => $child) {
                        echo "│   " . ($childIndex + 1) . ". {$child['full_name']} ({$child['relation_type']})\n";
                    }
                }
            } else {
                echo "│ ⚠️ لا توجد بيانات children أو ليست مصفوفة!\n";
            }

            echo "└─────────────────────────────────────────────────────────────┘\n\n";
        }

        // عرض JSON لأول فرد للتحقق من البنية
        echo "\n📄 بنية JSON لأول فرد:\n";
        echo "=================================================================\n";
        if (count($familyMembers) > 0) {
            echo json_encode($familyMembers[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        echo "\n=================================================================\n";

    } else {
        echo "❌ فشل: {$data['message']}\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n✅ انتهى الاختبار\n\n";
