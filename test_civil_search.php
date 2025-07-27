<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "اختبار البحث في السجل المدني الجديد:\n";
echo str_repeat("=", 50) . "\n";

try {
    // اختبار البحث برقم الهوية
    echo "البحث برقم الهوية: 407015692\n";
    $result = \App\Models\CivilRegistryPerson::search('407015692')->take(5)->get();

    echo "عدد النتائج: " . $result->count() . "\n";

    if ($result->count() > 0) {
        foreach ($result as $person) {
            echo "ID: " . $person->ID . " | رقم الهوية: " . $person->CI_ID_NUM . " | الاسم: " .
                 ($person->CI_FIRST_ARB ?? '') . " " . ($person->CI_FATHER_ARB ?? '') . "\n";
        }
    } else {
        echo "لم يتم العثور على نتائج\n";
    }

    echo "\n" . str_repeat("-", 30) . "\n";

    // اختبار البحث بالاسم
    echo "البحث بالاسم: محمد\n";
    $result2 = \App\Models\CivilRegistryPerson::search('محمد')->take(3)->get();

    echo "عدد النتائج: " . $result2->count() . "\n";

    if ($result2->count() > 0) {
        foreach ($result2 as $person) {
            echo "ID: " . $person->ID . " | رقم الهوية: " . $person->CI_ID_NUM . " | الاسم: " .
                 ($person->CI_FIRST_ARB ?? '') . " " . ($person->CI_FATHER_ARB ?? '') . "\n";
        }
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
}
