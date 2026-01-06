<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║               تشخيص مشكلة بيانات المعيل                                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

$sponsoredId = '42979087';
$guardianId = '400001285';

// 1. فحص الكفالة الحالية
echo "1️⃣  فحص الكفالة الحالية:\n";
$sponsorship = DB::table('sponsorships')->where('identity_number', $sponsoredId)->first();
if ($sponsorship) {
    echo "   ✓ وُجدت الكفالة\n";
    echo "   - guardian_identity_number: " . ($sponsorship->guardian_identity_number ?: '❌ فارغ') . "\n";
    echo "   - guardian_name: " . ($sponsorship->guardian_name ?: '❌ فارغ') . "\n";
} else {
    echo "   ❌ لم تُوجد الكفالة\n";
}

// 2. فحص بيانات المعيل في جدول data
echo "\n2️⃣  فحص المعيل ({$guardianId}) في جدول data:\n";
$guardianData = DB::table('data')->where('data_id_number', $guardianId)->first();
if ($guardianData) {
    echo "   ✓ وُجد المعيل في data:\n";
    echo "   - data_first_name: " . ($guardianData->data_first_name ?? 'NULL') . "\n";
    echo "   - data_father_name: " . ($guardianData->data_father_name ?? 'NULL') . "\n";
    echo "   - data_grand_father_name: " . ($guardianData->data_grand_father_name ?? 'NULL') . "\n";
    echo "   - data_family_name: " . ($guardianData->data_family_name ?? 'NULL') . "\n";
    echo "   - data_phone_number: " . ($guardianData->data_phone_number ?? 'NULL') . "\n";
    echo "   - file_id_number: " . ($guardianData->file_id_number ?? 'NULL') . "\n";
} else {
    echo "   ❌ لم يُوجد المعيل في data\n";
}

// 3. فحص المعيل في السجل المدني
echo "\n3️⃣  فحص المعيل ({$guardianId}) في السجل المدني:\n";
$civilDB = DB::connection('civilregistry');
$guardianCivil = $civilDB->table('persons')->where('CI_ID_NUM', $guardianId)->first();
if ($guardianCivil) {
    echo "   ✓ وُجد المعيل في السجل المدني:\n";
    echo "   - CI_FIRST_ARB: " . ($guardianCivil->CI_FIRST_ARB ?? 'NULL') . "\n";
    echo "   - CI_FATHER_ARB: " . ($guardianCivil->CI_FATHER_ARB ?? 'NULL') . "\n";
    echo "   - CI_GRAND_FATHER_ARB: " . ($guardianCivil->CI_GRAND_FATHER_ARB ?? 'NULL') . "\n";
    echo "   - CI_FAMILY_ARB: " . ($guardianCivil->CI_FAMILY_ARB ?? 'NULL') . "\n";
} else {
    echo "   ❌ لم يُوجد المعيل في السجل المدني\n";
}

// 4. تحديث الكفالة برقم هوية المعيل الصحيح
echo "\n4️⃣  تحديث الكفالة برقم هوية المعيل:\n";
if ($sponsorship && empty($sponsorship->guardian_identity_number)) {
    DB::table('sponsorships')
        ->where('identity_number', $sponsoredId)
        ->update([
            'guardian_identity_number' => $guardianId,
            'updated_at' => now()
        ]);
    echo "   ✓ تم تحديث guardian_identity_number إلى {$guardianId}\n";
} else if ($sponsorship) {
    echo "   ℹ️ guardian_identity_number موجود بالفعل: {$sponsorship->guardian_identity_number}\n";

    if ($sponsorship->guardian_identity_number != $guardianId) {
        DB::table('sponsorships')
            ->where('identity_number', $sponsoredId)
            ->update([
                'guardian_identity_number' => $guardianId,
                'updated_at' => now()
            ]);
        echo "   ✓ تم تحديثه إلى {$guardianId}\n";
    }
}

// 5. إعادة جلب الكفالة للتحقق
echo "\n5️⃣  التحقق بعد التحديث:\n";
$updatedSponsorship = DB::table('sponsorships')->where('identity_number', $sponsoredId)->first();
echo "   - identity_number: {$updatedSponsorship->identity_number}\n";
echo "   - guardian_identity_number: " . ($updatedSponsorship->guardian_identity_number ?: '❌ فارغ') . "\n";
echo "   - guardian_name: " . ($updatedSponsorship->guardian_name ?: '❌ فارغ') . "\n";

// 6. فحص العلاقات في Model
echo "\n6️⃣  فحص العلاقات:\n";
echo "   فحص علاقة relationData...\n";

// البحث عن سجل data مرتبط بالكفالة
// عادة يكون الربط عبر file_id_number أو sponsor_id
$dataByFileId = DB::table('data')->where('file_id_number', $updatedSponsorship->id)->first();
$dataBySponsorId = DB::table('data')->where('sponsor_id', $updatedSponsorship->sponsor_id)->first();
$dataByGuardianId = DB::table('data')->where('data_id_number', $guardianId)->first();

echo "   - data by file_id_number ({$updatedSponsorship->id}): " . ($dataByFileId ? '✓ موجود' : '❌ غير موجود') . "\n";
echo "   - data by sponsor_id ({$updatedSponsorship->sponsor_id}): " . ($dataBySponsorId ? '✓ موجود' : '❌ غير موجود') . "\n";
echo "   - data by guardian_id ({$guardianId}): " . ($dataByGuardianId ? '✓ موجود' : '❌ غير موجود') . "\n";

echo "\n" . str_repeat("═", 78) . "\n";
echo "                             الخلاصة                                          \n";
echo str_repeat("═", 78) . "\n\n";

echo "📊 المشكلة:\n";
echo "   الكود في extractFieldValues يبحث عن relationData عبر علاقة Eloquent\n";
echo "   لكن لا يوجد ربط مباشر بين الكفالة وجدول data برقم هوية المعيل\n\n";

echo "📋 الحل المطلوب:\n";
echo "   يجب تعديل extractFieldValues للبحث عن المعيل برقم هويته (guardian_identity_number)\n";
echo "   في جدول data أو السجل المدني\n";
