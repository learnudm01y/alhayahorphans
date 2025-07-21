<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Data;

echo "=== اختبار استعلام DataTable ===\n";

// محاكاة استعلام DataTable
$query = Data::query()
    ->whereHas('requestStatus', function($q) {
        $q->where('description', 'مقبول');
    });

$count = $query->count();
echo "عدد السجلات التي يجب أن تظهر في DataTable: $count\n\n";

// عرض عينة من البيانات مع العلاقات
echo "=== عينة من البيانات مع العلاقات ===\n";
$sample = $query->with(['requestStatus', 'section', 'city', 'maritalStatus'])
    ->take(5)
    ->get();

foreach($sample as $record) {
    echo "- ID: {$record->id}, رقم الملف: {$record->file_id_number}, الاسم: {$record->data_first_name} {$record->data_family_name}\n";
    echo "  حالة الطلب: " . optional($record->requestStatus)->description . "\n";
    echo "  القسم: " . optional($record->section)->description . "\n";
    echo "  المدينة: " . optional($record->city)->city . "\n";
    echo "  الحالة الاجتماعية: " . optional($record->maritalStatus)->description . "\n";
    echo "  ---\n";
}
