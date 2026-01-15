<?php
/**
 * ملف اختبار لفحص بيانات المعيل والحسابات البنكية
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;

echo "=== فحص بيانات المعيل والحسابات البنكية ===\n\n";

// جلب أول كفالة
$sponsorship = Sponsorship::first();

if (!$sponsorship) {
    echo "❌ لا توجد كفالات!\n";
    exit;
}

echo "📋 بيانات الكفالة:\n";
echo "   - ID: {$sponsorship->id}\n";
echo "   - orphan_name: {$sponsorship->orphan_name}\n";
echo "   - guardian_name: {$sponsorship->guardian_name}\n";
echo "   - identity_number: {$sponsorship->identity_number}\n";
echo "   - guardian_identity_number: {$sponsorship->guardian_identity_number}\n";
echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
echo "   - relation_id_number: {$sponsorship->relation_id_number}\n";

// فحص جدول data
echo "\n📊 فحص جدول data:\n";
if ($sponsorship->internal_file_number) {
    $dataRecord = DB::table('data')
        ->where('file_id_number', $sponsorship->internal_file_number)
        ->first();

    if ($dataRecord) {
        echo "   ✅ سجل موجود!\n";
        echo "   - guardian_first_name: " . ($dataRecord->guardian_first_name ?? 'NULL') . "\n";
        echo "   - guardian_father_name: " . ($dataRecord->guardian_father_name ?? 'NULL') . "\n";
        echo "   - guardian_phone: " . ($dataRecord->guardian_phone ?? $dataRecord->main_phone ?? 'NULL') . "\n";
    } else {
        echo "   ❌ لا يوجد سجل في data بـ file_id_number = {$sponsorship->internal_file_number}\n";

        // فحص الأعمدة الموجودة
        $columns = DB::select("SHOW COLUMNS FROM `data`");
        echo "   📋 الأعمدة في جدول data:\n";
        foreach ($columns as $col) {
            if (strpos($col->Field, 'guardian') !== false || strpos($col->Field, 'file') !== false) {
                echo "      - {$col->Field}\n";
            }
        }
    }
} else {
    echo "   ⚠️ internal_file_number فارغ!\n";
}

// فحص جدول re_people
echo "\n👤 فحص جدول re_people:\n";
if ($sponsorship->identity_number) {
    $rePeople = DB::table('re_people')
        ->where('person_id', $sponsorship->identity_number)
        ->first();

    if ($rePeople) {
        echo "   ✅ سجل موجود!\n";
        echo "   - first_name: " . ($rePeople->first_name ?? 'NULL') . "\n";
        echo "   - father_name: " . ($rePeople->father_name ?? 'NULL') . "\n";
        echo "   - gender: " . ($rePeople->gender ?? 'NULL') . "\n";
    } else {
        echo "   ❌ لا يوجد سجل في re_people بـ person_id = {$sponsorship->identity_number}\n";

        // فحص بطريقة أخرى
        $rePeople2 = DB::table('re_people')
            ->where('id_number', $sponsorship->identity_number)
            ->first();

        if ($rePeople2) {
            echo "   ✅ وجدته بـ id_number!\n";
            echo "   - first_name: " . ($rePeople2->first_name ?? 'NULL') . "\n";
        }
    }
} else {
    echo "   ⚠️ identity_number فارغ!\n";
}

// فحص الحسابات البنكية
echo "\n🏦 فحص الحسابات البنكية:\n";

// الطريقة 1: relation_id_number
if ($sponsorship->relation_id_number) {
    $bankAccounts1 = DB::table('guardian_bank_accounts')
        ->where('guardian_registration', $sponsorship->relation_id_number)
        ->get();

    echo "   البحث بـ guardian_registration = {$sponsorship->relation_id_number}: ";
    echo $bankAccounts1->count() . " حساب\n";
}

// الطريقة 2: guardian_identity_number
if ($sponsorship->guardian_identity_number) {
    $bankAccounts2 = DB::table('guardian_bank_accounts')
        ->where('re_id_number', $sponsorship->guardian_identity_number)
        ->get();

    echo "   البحث بـ re_id_number = {$sponsorship->guardian_identity_number}: ";
    echo $bankAccounts2->count() . " حساب\n";
}

// فحص أعمدة guardian_bank_accounts
echo "\n📋 أعمدة جدول guardian_bank_accounts:\n";
$columns = DB::select("SHOW COLUMNS FROM `guardian_bank_accounts`");
foreach ($columns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}

echo "\n=== انتهى الفحص ===\n";
