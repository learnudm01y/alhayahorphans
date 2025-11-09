<?php
/**
 * اختبار سريع للبحث بأسماء محددة
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$controller = new \App\Http\Controllers\Admin\FamilyRelationController();

echo "\n=================================================================\n";
echo "           اختبار البحث بأسماء محددة من العائلة                 \n";
echo "=================================================================\n\n";

$tests = [
    'هدى حمزه سليمان' => '3 أسماء (الأم)',
    'عبد الناصر رفيق جباره' => '3 أسماء (الأب)',
    'رفيق عبد الناصر' => 'اسمين (الابن)',
    'محمد عبد الناصر رفيق' => '3 أسماء (الابن)',
];

foreach ($tests as $name => $description) {
    echo "🔍 {$description}: \"{$name}\"\n";
    
    $request = new \Illuminate\Http\Request();
    $request->merge(['name' => $name]);
    
    try {
        $response = $controller->searchFamilyRelationsByName($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success']) {
            if (isset($data['data']['multiple_results'])) {
                echo "   ✅ {$data['data']['count']} شخص مطابق\n";
                foreach ($data['data']['persons'] as $person) {
                    echo "      - {$person['full_name']} (رقم: {$person['id_number']})\n";
                }
            } else {
                echo "   ✅ شخص واحد: {$data['data']['person']['full_name']}\n";
                echo "   📋 رقم الهوية: {$data['data']['person']['id_number']}\n";
                echo "   👥 عدد أفراد العائلة: " . count($data['data']['family_members']) . "\n\n";
                
                // عرض العائلة بالتفصيل
                foreach ($data['data']['family_members'] as $member) {
                    $childrenCount = isset($member['children']) ? count($member['children']) : 0;
                    echo "   🌳 {$member['relation_type']}: {$member['full_name']}\n";
                    echo "      └─ {$childrenCount} مرتبط(ين)\n";
                }
            }
        } else {
            echo "   ❌ لا يوجد نتائج: {$data['message']}\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat('-', 65) . "\n\n";
}

echo "=================================================================\n";
echo "✅ انتهى الاختبار\n";
echo "=================================================================\n\n";
