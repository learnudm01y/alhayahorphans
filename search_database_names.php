<?php
require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 البحث عن الأسماء المشابهة في قاعدة البيانات...\n";
echo str_repeat("=", 50) . "\n";

// البحث عن أسماء تحتوي على "سعاد" و "صلاح"
echo "\n1. البحث عن 'سعاد' + 'صلاح':\n";
$results1 = DB::table('persons')
    ->where('CI_FIRST_ARB', 'LIKE', '%سعاد%')
    ->where('CI_FATHER_ARB', 'LIKE', '%صلاح%')
    ->limit(10)
    ->get(['CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_ID_NUM']);

if ($results1->count() > 0) {
    foreach ($results1 as $r) {
        echo "   " . trim($r->CI_FIRST_ARB . ' ' . $r->CI_FATHER_ARB . ' ' . $r->CI_GRAND_FATHER_ARB . ' ' . $r->CI_FAMILY_ARB) . " (ID: {$r->CI_ID_NUM})\n";
    }
} else {
    echo "   لا توجد نتائج\n";
}

// البحث عن "صلاح" في أي مكان
echo "\n2. البحث عن 'صلاح' في جميع الحقول:\n";
$results2 = DB::table('persons')
    ->where(function($q) {
        $q->where('CI_FIRST_ARB', 'LIKE', '%صلاح%')
          ->orWhere('CI_FATHER_ARB', 'LIKE', '%صلاح%')
          ->orWhere('CI_GRAND_FATHER_ARB', 'LIKE', '%صلاح%')
          ->orWhere('CI_FAMILY_ARB', 'LIKE', '%صلاح%');
    })
    ->limit(5)
    ->get(['CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB']);

if ($results2->count() > 0) {
    foreach ($results2 as $r) {
        echo "   " . trim($r->CI_FIRST_ARB . ' ' . $r->CI_FATHER_ARB . ' ' . $r->CI_GRAND_FATHER_ARB . ' ' . $r->CI_FAMILY_ARB) . "\n";
    }
} else {
    echo "   لا توجد نتائج\n";
}

// البحث عن "بلال" في أي مكان
echo "\n3. البحث عن 'بلال' في جميع الحقول:\n";
$results3 = DB::table('persons')
    ->where(function($q) {
        $q->where('CI_FIRST_ARB', 'LIKE', '%بلال%')
          ->orWhere('CI_FATHER_ARB', 'LIKE', '%بلال%')
          ->orWhere('CI_GRAND_FATHER_ARB', 'LIKE', '%بلال%')
          ->orWhere('CI_FAMILY_ARB', 'LIKE', '%بلال%');
    })
    ->limit(5)
    ->get(['CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB']);

if ($results3->count() > 0) {
    foreach ($results3 as $r) {
        echo "   " . trim($r->CI_FIRST_ARB . ' ' . $r->CI_FATHER_ARB . ' ' . $r->CI_GRAND_FATHER_ARB . ' ' . $r->CI_FAMILY_ARB) . "\n";
    }
} else {
    echo "   لا توجد نتائج\n";
}

// البحث العام لأسماء مشابهة
echo "\n4. البحث العام بـ CONCAT:\n";
$results4 = DB::table('persons')
    ->whereRaw("CONCAT(COALESCE(CI_FIRST_ARB, ''), ' ', COALESCE(CI_FATHER_ARB, ''), ' ', COALESCE(CI_GRAND_FATHER_ARB, ''), ' ', COALESCE(CI_FAMILY_ARB, '')) LIKE ?", ['%سعاد%صلاح%'])
    ->limit(5)
    ->get(['CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB']);

if ($results4->count() > 0) {
    foreach ($results4 as $r) {
        echo "   " . trim($r->CI_FIRST_ARB . ' ' . $r->CI_FATHER_ARB . ' ' . $r->CI_GRAND_FATHER_ARB . ' ' . $r->CI_FAMILY_ARB) . "\n";
    }
} else {
    echo "   لا توجد نتائج\n";
}

echo "\n✅ انتهى البحث\n";
?>
