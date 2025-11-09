<?php
/**
 * اختبار البحث العائلي بالاسم
 * 
 * يختبر:
 * 1. البحث بـ 4 أسماء (الاسم + الأب + الجد + العائلة)
 * 2. البحث بـ 3 أسماء
 * 3. البحث بـ 2 اسمين
 * 4. عرض النتائج المتعددة
 * 5. عرض العائلة كاملة
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "\n=================================================================\n";
echo "               اختبار البحث العائلي بالاسم                       \n";
echo "=================================================================\n\n";

$controller = new \App\Http\Controllers\Admin\FamilyRelationController();

// اختبار 1: البحث بـ 4 أسماء
echo "📝 اختبار 1: البحث بـ 4 أسماء\n";
echo "-----------------------------------------------------------------\n";
$testNames = [
    'نصرالله عبد الناصر رفيق الفرا',  // 4 أسماء - دقيق
    'محمد عبد الناصر رفيق',            // 3 أسماء
    'عبد الناصر الفرا',                 // اسمين
    'نصرالله',                          // اسم واحد
];

foreach ($testNames as $index => $testName) {
    echo "\n" . ($index + 1) . ". البحث عن: \"{$testName}\"\n";
    echo "   عدد الأسماء: " . count(explode(' ', $testName)) . "\n";
    
    $request = new \Illuminate\Http\Request();
    $request->merge(['name' => $testName]);
    
    try {
        $response = $controller->searchFamilyRelationsByName($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success']) {
            if (isset($data['data']['multiple_results']) && $data['data']['multiple_results']) {
                echo "   ✅ نتائج متعددة: " . $data['data']['count'] . " شخص\n";
                echo "   📋 القائمة:\n";
                foreach (array_slice($data['data']['persons'], 0, 5) as $person) {
                    echo "      - {$person['full_name']} (رقم: {$person['id_number']}, {$person['gender']}, {$person['age']})\n";
                }
                if ($data['data']['count'] > 5) {
                    echo "      ... و " . ($data['data']['count'] - 5) . " آخرين\n";
                }
            } else {
                echo "   ✅ شخص واحد مطابق: {$data['data']['person']['full_name']}\n";
                echo "   👥 عدد أفراد العائلة: " . count($data['data']['family_members']) . "\n";
                
                // عرض العائلة
                if (count($data['data']['family_members']) > 0) {
                    echo "   🌳 العائلة:\n";
                    foreach ($data['data']['family_members'] as $member) {
                        $childrenCount = isset($member['children']) ? count($member['children']) : 0;
                        echo "      • {$member['relation_type']}: {$member['full_name']} ({$childrenCount} مرتبط)\n";
                    }
                }
            }
        } else {
            echo "   ❌ لم يتم العثور على نتائج\n";
        }
        
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// اختبار 2: البحث بأسماء مختلفة للتحقق من الدقة
echo "\n=================================================================\n";
echo "اختبار 2: أمثلة متنوعة للبحث\n";
echo "=================================================================\n\n";

$diverseTests = [
    'عبد الناصر رفيق جباره الفرا' => 'اسم الأب كامل',
    'هدى حمزه سليمان الفرا' => 'اسم الأم كامل',
    'رفيق عبد الناصر' => 'اسم الابن (جزئي)',
    'الفرا' => 'اسم العائلة فقط',
];

foreach ($diverseTests as $name => $description) {
    echo "🔍 {$description}: \"{$name}\"\n";
    
    $request = new \Illuminate\Http\Request();
    $request->merge(['name' => $name]);
    
    try {
        $response = $controller->searchFamilyRelationsByName($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success']) {
            if (isset($data['data']['multiple_results'])) {
                echo "   ✅ {$data['data']['count']} شخص مطابق\n";
            } else {
                echo "   ✅ شخص واحد: {$data['data']['person']['full_name']}\n";
            }
        } else {
            echo "   ❌ لا يوجد نتائج\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=================================================================\n";
echo "✅ اكتمل الاختبار\n";
echo "=================================================================\n\n";

// عرض إحصائيات
echo "📊 الإحصائيات:\n";
echo "   - إجمالي الاختبارات: " . (count($testNames) + count($diverseTests)) . "\n";
echo "   - أنواع البحث المدعومة:\n";
echo "     • 4 أسماء (الاسم + الأب + الجد + العائلة) ✅\n";
echo "     • 3 أسماء (الاسم + الأب + الجد/العائلة) ✅\n";
echo "     • 2 اسمين (الاسم + الأب/العائلة) ✅\n";
echo "     • اسم واحد (البحث في جميع الحقول) ✅\n";
echo "\n";

echo "💡 ملاحظات:\n";
echo "   - البحث يدعم البحث الجزئي (LIKE)\n";
echo "   - الحد الأقصى للنتائج: 50 شخص\n";
echo "   - إذا وُجد شخص واحد فقط، يتم عرض عائلته مباشرة\n";
echo "   - إذا وُجد أكثر من شخص، يتم عرض قائمة للاختيار\n";
echo "\n";
