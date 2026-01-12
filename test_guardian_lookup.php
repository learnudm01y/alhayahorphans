<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص جلب بيانات المعيل ===" . PHP_EOL . PHP_EOL;

// عينة من sponsorships
$s = DB::table('sponsorships')->where('id', 4879)->first();
echo "بيانات الكفالة:" . PHP_EOL;
echo "  relation_id_number: " . ($s->relation_id_number ?? 'NULL') . PHP_EOL;
echo "  guardian_identity_number: " . ($s->guardian_identity_number ?? 'NULL') . PHP_EOL;
echo "  guardian_name: " . ($s->guardian_name ?? 'NULL') . PHP_EOL;
echo PHP_EOL;

// البحث في data باستخدام relation_id_number
$d = DB::table('data')->where('file_id_number', $s->relation_id_number)->first();
if ($d) {
    echo "✅ وُجد في data عبر relation_id_number -> file_id_number:" . PHP_EOL;
    echo "  data_first_name: " . ($d->data_first_name ?? 'NULL') . PHP_EOL;
    echo "  data_father_name: " . ($d->data_father_name ?? 'NULL') . PHP_EOL;
    echo "  data_grand_father_name: " . ($d->data_grand_father_name ?? 'NULL') . PHP_EOL;
    echo "  data_family_name: " . ($d->data_family_name ?? 'NULL') . PHP_EOL;
} else {
    echo "❌ لم يُوجد في data عبر relation_id_number" . PHP_EOL;

    // لنبحث بطريقة أخرى
    echo PHP_EOL . "محاولة البحث بطريقة أخرى..." . PHP_EOL;

    // هل الـ relation_id_number هو رقم هوية؟
    $d2 = DB::table('data')->where('data_id_number', $s->relation_id_number)->first();
    if ($d2) {
        echo "✅ وُجد في data عبر data_id_number:" . PHP_EOL;
        echo "  data_first_name: " . ($d2->data_first_name ?? 'NULL') . PHP_EOL;
    } else {
        echo "❌ لم يُوجد عبر data_id_number أيضاً" . PHP_EOL;
    }

    // نبحث بـ guardian_identity_number
    $d3 = DB::table('data')->where('data_id_number', $s->guardian_identity_number)->first();
    if ($d3) {
        echo PHP_EOL . "✅ وُجد في data عبر guardian_identity_number -> data_id_number:" . PHP_EOL;
        echo "  data_first_name: " . ($d3->data_first_name ?? 'NULL') . PHP_EOL;
        echo "  data_father_name: " . ($d3->data_father_name ?? 'NULL') . PHP_EOL;
    }

    $d4 = DB::table('data')->where('file_id_number', $s->guardian_identity_number)->first();
    if ($d4) {
        echo PHP_EOL . "✅ وُجد في data عبر guardian_identity_number -> file_id_number:" . PHP_EOL;
        echo "  data_first_name: " . ($d4->data_first_name ?? 'NULL') . PHP_EOL;
        echo "  data_father_name: " . ($d4->data_father_name ?? 'NULL') . PHP_EOL;
    }
}

// نعرض بنية الجدول
echo PHP_EOL . "=== بنية جدول data ===" . PHP_EOL;
$sample = DB::table('data')->first();
if ($sample) {
    $cols = array_keys((array)$sample);
    $idCols = array_filter($cols, function($c) {
        return strpos($c, 'id') !== false || strpos($c, 'number') !== false;
    });
    echo "حقول الـ ID والأرقام: " . implode(', ', $idCols) . PHP_EOL;
}

// علاقة بين sponsorships و data
echo PHP_EOL . "=== تحليل العلاقة ===" . PHP_EOL;
echo "relation_id_number في sponsorships = " . ($s->relation_id_number ?? 'NULL') . PHP_EOL;
echo "guardian_identity_number في sponsorships = " . ($s->guardian_identity_number ?? 'NULL') . PHP_EOL;

// نبحث بالاسم
echo PHP_EOL . "=== البحث بالاسم ===" . PHP_EOL;
$guardianName = $s->guardian_name;
$nameParts = explode(' ', $guardianName);
echo "اسم المعيل: $guardianName" . PHP_EOL;
echo "الجزء الأول: " . ($nameParts[0] ?? '') . PHP_EOL;

$byName = DB::table('data')
    ->where('data_first_name', $nameParts[0] ?? '')
    ->where('data_family_name', 'like', '%' . ($nameParts[count($nameParts)-1] ?? '') . '%')
    ->first();

if ($byName) {
    echo "✅ وُجد بالبحث بالاسم:" . PHP_EOL;
    echo "  file_id_number: " . ($byName->file_id_number ?? 'NULL') . PHP_EOL;
    echo "  data_id_number: " . ($byName->data_id_number ?? 'NULL') . PHP_EOL;
    echo "  الاسم الكامل: {$byName->data_first_name} {$byName->data_father_name} {$byName->data_grand_father_name} {$byName->data_family_name}" . PHP_EOL;
}

echo PHP_EOL . "=== انتهى الفحص ===" . PHP_EOL;
