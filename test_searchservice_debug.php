<?php

require_once 'vendor/autoload.php';

use App\Services\SearchService;
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'aso',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== تتبع مشكلة SearchService ===\n\n";

try {
    $searchService = new SearchService();

    $filters = [
        'search_type' => 'all',
        'search_text' => 'Kyle Christine Byers Brenda Mason Nayda Ayers'
    ];

    echo "البحث باستخدام SearchService:\n";
    echo "النص المدخل: {$filters['search_text']}\n\n";

    $results = $searchService->smartSearch($filters, 25);

    echo "نتائج البحث:\n";
    echo "- السجلات الرئيسية: " . count($results['main_records'] ?? []) . "\n";
    echo "- أفراد الأسرة: " . count($results['family_members'] ?? []) . "\n";
    echo "- المتوفين: " . count($results['deceased'] ?? []) . "\n";
    echo "- إجمالي العدد: " . ($results['total_count'] ?? 0) . "\n\n";

    if (!empty($results['main_records'])) {
        echo "تفاصيل السجلات الرئيسية:\n";
        foreach ($results['main_records'] as $record) {
            echo "- ID: {$record['id']}, الاسم: {$record['full_name']}\n";
        }
    } else {
        echo "❌ لم يتم العثور على أي سجل في السجلات الرئيسية\n";
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
    echo "المسار: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== اختبار مباشر للبحث في جدول data ===\n";

// 1. البحث التقليدي
echo "1. البحث التقليدي في الحقول:\n";
$traditionalSearch = Capsule::table('data')
    ->where('data_request_status', 2)
    ->where(function($q) {
        $searchText = 'Kyle Christine Byers Brenda Mason Nayda Ayers';
        $q->where('data_first_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_father_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_family_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_id_number', 'LIKE', "%{$searchText}%")
          ->orWhere('file_id_number', 'LIKE', "%{$searchText}%");
    })
    ->count();
echo "   النتائج: {$traditionalSearch}\n";

// 2. البحث بـ CONCAT
echo "\n2. البحث بـ CONCAT:\n";
$concatSearch = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ['%Kyle Christine Byers Brenda Mason Nayda Ayers%'])
    ->count();
echo "   النتائج: {$concatSearch}\n";

// 3. البحث المركب كما في SearchService
echo "\n3. البحث المركب (محاكاة SearchService):\n";
$combinedSearch = Capsule::table('data')
    ->where('data_request_status', 2)
    ->where(function($q) {
        $searchText = 'Kyle Christine Byers Brenda Mason Nayda Ayers';

        // البحث بـ CONCAT
        $q->orWhereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchText}%"]);

        // البحث التقليدي
        $q->orWhere('data_first_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_father_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_family_name', 'LIKE', "%{$searchText}%")
          ->orWhere('data_id_number', 'LIKE', "%{$searchText}%")
          ->orWhere('file_id_number', 'LIKE', "%{$searchText}%");
    })
    ->count();
echo "   النتائج: {$combinedSearch}\n";

echo "\n=== خلاصة المشكلة ===\n";
if ($combinedSearch > 0) {
    echo "✅ الاستعلام يعمل خارج SearchService\n";
    echo "❓ المشكلة في SearchService - تحقق من filters أو configuration\n";
} else {
    echo "❌ مشكلة في الاستعلام نفسه\n";
}

?>
