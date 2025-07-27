<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\DataTables\PersonsDataTable;
use Illuminate\Support\Facades\DB;

echo "🧪 اختبار PersonsDataTable مع قاعدة البيانات الجديدة:\n";
echo str_repeat('=', 60) . "\n";

try {
    // اختبار الاتصال
    echo "📡 اختبار الاتصال بقاعدة civilregistry...\n";
    $count = DB::connection('civilregistry')->table('persons')->count();
    echo "✅ الاتصال ناجح - عدد السجلات: " . number_format($count) . "\n\n";

    // اختبار PersonsDataTable
    echo "📊 اختبار PersonsDataTable...\n";
    $dataTable = new PersonsDataTable();

    // اختبار الاستعلام
    $query = $dataTable->query();
    echo "✅ تم إنشاء Query بنجاح\n";

    // اختبار أول 5 سجلات
    $results = $query->limit(5)->get();
    echo "📋 أول 5 سجلات:\n";

    foreach ($results as $i => $person) {
        $fullName = trim(($person->CI_FIRST_ARB ?? '') . ' ' .
                        ($person->CI_FATHER_ARB ?? '') . ' ' .
                        ($person->CI_GRAND_FATHER_ARB ?? '') . ' ' .
                        ($person->CI_FAMILY_ARB ?? ''));

        $gender = $person->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى';
        $status = (!$person->CI_DEAD_DT || $person->CI_DEAD_DT == 0) ? 'على قيد الحياة' : 'متوفى';

        echo ($i + 1) . ". رقم الهوية: {$person->CI_ID_NUM}\n";
        echo "   الاسم: {$fullName}\n";
        echo "   الجنس: {$gender}\n";
        echo "   الحالة: {$status}\n";
        echo "   المدينة: " . ($person->CITY ?? 'غير محدد') . "\n\n";
    }

    // اختبار الأعمدة
    echo "📝 اختبار الأعمدة المُعرَّفة:\n";
    $reflection = new ReflectionClass($dataTable);
    $method = $reflection->getMethod('getColumns');
    $method->setAccessible(true);
    $columns = $method->invoke($dataTable);

    foreach ($columns as $column) {
        $columnData = $column->toArray();
        echo "- " . ($columnData['title'] ?? $columnData['name'] ?? 'بدون عنوان') . "\n";
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "🎯 الاختبار مكتمل! PersonsDataTable جاهز للاستخدام مع قاعدة civilregistry\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . " - السطر: " . $e->getLine() . "\n";
}
