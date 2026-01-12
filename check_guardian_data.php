<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص بيانات المعيل ===\n\n";

// جلب عينة من الكفالات
$sample = DB::table('sponsorships')->first();
echo "عينة من جدول sponsorships:\n";
echo "- id: " . ($sample->id ?? 'NULL') . "\n";
echo "- guardian_identity_number: " . ($sample->guardian_identity_number ?? 'NULL') . "\n";
echo "- relation_id_number: " . ($sample->relation_id_number ?? 'NULL') . "\n";
echo "- guardian_name: " . ($sample->guardian_name ?? 'NULL') . "\n";
echo "- person_type: " . ($sample->person_type ?? 'NULL') . "\n";

// فحص جدول data
if ($sample->relation_id_number) {
    $dataInfo = DB::table('data')->where('file_id_number', $sample->relation_id_number)->first();
    if ($dataInfo) {
        echo "\nبيانات المعيل من جدول data:\n";
        echo "- data_first_name: " . ($dataInfo->data_first_name ?? 'NULL') . "\n";
        echo "- data_father_name: " . ($dataInfo->data_father_name ?? 'NULL') . "\n";
        echo "- data_grand_father_name: " . ($dataInfo->data_grand_father_name ?? 'NULL') . "\n";
        echo "- data_family_name: " . ($dataInfo->data_family_name ?? 'NULL') . "\n";
        echo "- data_phone_number: " . ($dataInfo->data_phone_number ?? 'NULL') . "\n";
        echo "- data_alt_phone_number: " . ($dataInfo->data_alt_phone_number ?? 'NULL') . "\n";
        echo "- data_current_address: " . ($dataInfo->data_current_address ?? 'NULL') . "\n";
    } else {
        echo "\n❌ لا توجد بيانات في جدول data لـ relation_id_number = {$sample->relation_id_number}\n";
    }
} else {
    echo "\n❌ relation_id_number فارغ!\n";
}

// فحص السجل المدني
echo "\n=== فحص enrichSponsorshipData ===\n";

// محاكاة ما يحدث في enrichSponsorshipData
$result = [];
$result['guardian_first_name'] = '';
$result['guardian_father_name'] = '';
$result['guardian_grandfather_name'] = '';
$result['guardian_family_name'] = '';

// شرط جلب بيانات المعيل
echo "\nفحص الشروط:\n";
echo "- guardian_identity_number موجود؟ " . (!empty($sample->guardian_identity_number) ? 'نعم' : 'لا') . "\n";
echo "- guardian_name موجود؟ " . (!empty($sample->guardian_name) ? 'نعم' : 'لا') . "\n";

if (!empty($sample->guardian_name)) {
    // تقسيم الاسم
    $parts = preg_split('/\s+/', trim($sample->guardian_name), -1, PREG_SPLIT_NO_EMPTY);
    $count = count($parts);
    
    $result['guardian_first_name'] = $parts[0] ?? '';
    $result['guardian_father_name'] = $parts[1] ?? '';
    $result['guardian_grandfather_name'] = $parts[2] ?? '';
    $result['guardian_family_name'] = $count > 3 ? implode(' ', array_slice($parts, 3)) : ($parts[3] ?? '');
    
    echo "\nتقسيم اسم المعيل:\n";
    echo "- guardian_first_name: {$result['guardian_first_name']}\n";
    echo "- guardian_father_name: {$result['guardian_father_name']}\n";
    echo "- guardian_grandfather_name: {$result['guardian_grandfather_name']}\n";
    echo "- guardian_family_name: {$result['guardian_family_name']}\n";
}

// فحص بيانات الاتصال من data
if (!empty($sample->relation_id_number)) {
    $guardianInfo = DB::table('data')
        ->where('file_id_number', $sample->relation_id_number)
        ->first();
    
    if ($guardianInfo) {
        echo "\n✅ سيتم جلب بيانات الاتصال من جدول data:\n";
        echo "- guardian_phone: " . ($guardianInfo->data_phone_number ?? 'NULL') . "\n";
        echo "- guardian_phone2: " . ($guardianInfo->data_alt_phone_number ?? 'NULL') . "\n";
        echo "- guardian_detailed_address: " . ($guardianInfo->data_current_address ?? 'NULL') . "\n";
    }
}

echo "\n=== انتهى الفحص ===\n";
