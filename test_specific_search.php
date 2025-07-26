<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;

$searchQuery = "Kyle Christine Byers Brenda Mason Nayda Ayers";

echo "🔍 البحث عن: {$searchQuery}\n\n";

// البحث في جدول Data
echo "📄 البحث في جدول Data:\n";
$dataResults = Data::where(function($q) use ($searchQuery) {
    $q->where('data_first_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('data_father_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('data_grand_father_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('data_family_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('data_id_number', 'LIKE', "%{$searchQuery}%")
      ->orWhere('file_id_number', 'LIKE', "%{$searchQuery}%");
})->limit(10)->get();

if ($dataResults->count() > 0) {
    foreach ($dataResults as $result) {
        echo "   - ID: {$result->id}, الاسم: {$result->data_first_name} {$result->data_father_name} {$result->data_grand_father_name} {$result->data_family_name}\n";
    }
} else {
    echo "   ❌ لا توجد نتائج في جدول Data\n";
}

// البحث بكلمات منفصلة
echo "\n📝 البحث بكلمات منفصلة:\n";
$words = ['Kyle', 'Christine', 'Byers', 'Brenda', 'Mason', 'Nayda', 'Ayers'];

foreach ($words as $word) {
    echo "   البحث عن: {$word}\n";
    $results = Data::where(function($q) use ($word) {
        $q->where('data_first_name', 'LIKE', "%{$word}%")
          ->orWhere('data_father_name', 'LIKE', "%{$word}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$word}%")
          ->orWhere('data_family_name', 'LIKE', "%{$word}%");
    })->limit(3)->get();

    if ($results->count() > 0) {
        foreach ($results as $result) {
            echo "     ✅ وجد: {$result->data_first_name} {$result->data_father_name} {$result->data_grand_father_name} {$result->data_family_name}\n";
        }
    } else {
        echo "     ❌ لم يوجد نتائج لـ {$word}\n";
    }
}

// البحث في جدول أفراد الأسرة
echo "\n👥 البحث في جدول أفراد الأسرة:\n";
$familyResults = RePeople::where(function($q) use ($searchQuery) {
    $q->where('first_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('second_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('third_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('last_name', 'LIKE', "%{$searchQuery}%")
      ->orWhere('person_id', 'LIKE', "%{$searchQuery}%")
      ->orWhere('registration_id', 'LIKE', "%{$searchQuery}%");
})->limit(10)->get();

if ($familyResults->count() > 0) {
    foreach ($familyResults as $result) {
        echo "   - ID: {$result->id}, الاسم: {$result->first_name} {$result->second_name} {$result->third_name} {$result->last_name}\n";
    }
} else {
    echo "   ❌ لا توجد نتائج في جدول أفراد الأسرة\n";
}

// عرض عينة من البيانات الموجودة
echo "\n📊 عينة من البيانات الموجودة:\n";
$sampleData = Data::limit(5)->get(['id', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name']);
foreach ($sampleData as $sample) {
    echo "   - {$sample->data_first_name} {$sample->data_father_name} {$sample->data_grand_father_name} {$sample->data_family_name}\n";
}

echo "\n🔍 اختبار مباشر للـ API:\n";
// محاكاة طلب API
$request = new \Illuminate\Http\Request(['query' => 'Kyle']);

try {
    $searchService = new \App\Services\SearchService();
    $controller = new \App\Http\Controllers\Admin\ProfileSearchController($searchService);
    $response = $controller->quickSearch($request);
    $responseData = json_decode($response->getContent(), true);

    echo "   📤 استجابة API: " . ($responseData['success'] ? 'نجح' : 'فشل') . "\n";
    echo "   📊 عدد النتائج: " . ($responseData['total'] ?? 0) . "\n";

    if (isset($responseData['data']) && count($responseData['data']) > 0) {
        foreach ($responseData['data'] as $result) {
            echo "   - {$result['title']} ({$result['type']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ خطأ في API: " . $e->getMessage() . "\n";
}
