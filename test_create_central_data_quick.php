<?php

/**
 * اختبار سريع لإنشاء البيانات المركزية للمكفول بدون تأكيد
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;

echo "=" . str_repeat("=", 70) . "\n";
echo "  اختبار إنشاء البيانات المركزية للمكفول\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// بيانات الاختبار
$testIdentity = '000073366';

$sponsorship = Sponsorship::where('identity_number', $testIdentity)->first();

if (!$sponsorship) {
    echo "❌ لم يتم العثور على الكفالة!\n";
    exit(1);
}

echo "✅ الكفالة #{$sponsorship->id}:\n";
echo "   - اسم المكفول: {$sponsorship->orphan_name}\n";
echo "   - relation_id_number: " . ($sponsorship->relation_id_number ?: 'NULL') . "\n\n";

// حساب رقم الملف الجديد
$maxFromData = DB::table('data')
    ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
    ->max(DB::raw('CAST(file_id_number AS UNSIGNED)'));

$maxFromDeadPeople = DB::table('dead_people')
    ->whereRaw("re_file_id REGEXP '^[0-9]+$'")
    ->max(DB::raw('CAST(re_file_id AS UNSIGNED)'));

$maxFromRePeople = DB::table('re_people')
    ->whereRaw("registration_id REGEXP '^[0-9]+$'")
    ->max(DB::raw('CAST(registration_id AS UNSIGNED)'));

$nextNumber = max(
    intval($maxFromData ?? 0),
    intval($maxFromDeadPeople ?? 0),
    intval($maxFromRePeople ?? 0)
) + 1;

$newFileNumber = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

echo "📊 الرقم الجديد المقترح: {$newFileNumber}\n\n";

// بيانات الإدخال
$namesData = [
    'sponsored' => [
        'first_name' => 'عماد',
        'second_name' => 'محمود',
        'third_name' => 'عبد الحفيظ',
        'last_name' => 'جحجوح'
    ],
    'guardian' => [
        'first_name' => 'فاطمة',
        'second_name' => 'أحمد',
        'third_name' => 'محمد',
        'last_name' => 'جحجوح'
    ],
    'father' => [
        'first_name' => 'محمود',
        'second_name' => 'عبد الحفيظ',
        'third_name' => 'حسين',
        'last_name' => 'جحجوح'
    ]
];

$fieldsData = [
    'field_person_birth_date' => '2015-03-15',
    'field_person_gender' => 'ذكر',
    'field_father_id' => '987654321',
    'field_father_death_date' => '2023-01-10',
];

echo "🚀 بدء إنشاء البيانات...\n\n";

try {
    DB::beginTransaction();

    // 1. إنشاء سجل في data
    $dataRecord = [
        'file_id_number' => $newFileNumber,
        'data_id_number' => $testIdentity,
        'data_first_name' => $namesData['guardian']['first_name'],
        'data_father_name' => $namesData['guardian']['second_name'],
        'data_grand_father_name' => $namesData['guardian']['third_name'],
        'data_family_name' => $namesData['guardian']['last_name'],
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $dataId = DB::table('data')->insertGetId($dataRecord);
    echo "   ✅ data: id={$dataId}, file_id_number={$newFileNumber}\n";

    // 2. إنشاء سجل في re_people
    $rePeopleRecord = [
        'registration_id' => $newFileNumber,
        'person_id' => $testIdentity,
        'first_name' => $namesData['sponsored']['first_name'],
        'second_name' => $namesData['sponsored']['second_name'],
        'third_name' => $namesData['sponsored']['third_name'],
        'last_name' => $namesData['sponsored']['last_name'],
        'person_birth_date' => $fieldsData['field_person_birth_date'],
        'person_gender' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $rePeopleId = DB::table('re_people')->insertGetId($rePeopleRecord);
    echo "   ✅ re_people: id={$rePeopleId}, registration_id={$newFileNumber}\n";

    // 3. إنشاء سجل في dead_people
    $deadPeopleRecord = [
        're_file_id' => $newFileNumber,
        'father_first_name' => $namesData['father']['first_name'],
        'father_second_name' => $namesData['father']['second_name'],
        'father_third_name' => $namesData['father']['third_name'],
        'father_last_name' => $namesData['father']['last_name'],
        'father_id' => $fieldsData['field_father_id'],
        'father_death_date' => $fieldsData['field_father_death_date'],
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $deadPeopleId = DB::table('dead_people')->insertGetId($deadPeopleRecord);
    echo "   ✅ dead_people: id={$deadPeopleId}, re_file_id={$newFileNumber}\n";

    // 4. تحديث sponsorships
    $fullSponsoredName = trim("{$namesData['sponsored']['first_name']} {$namesData['sponsored']['second_name']} {$namesData['sponsored']['third_name']} {$namesData['sponsored']['last_name']}");
    $fullGuardianName = trim("{$namesData['guardian']['first_name']} {$namesData['guardian']['second_name']} {$namesData['guardian']['third_name']} {$namesData['guardian']['last_name']}");

    DB::table('sponsorships')
        ->where('id', $sponsorship->id)
        ->update([
            'relation_id_number' => $newFileNumber,
            'orphan_name' => $fullSponsoredName,
            'guardian_name' => $fullGuardianName,
            'sponsored_birth_date' => $fieldsData['field_person_birth_date'],
            'updated_at' => now(),
        ]);

    echo "   ✅ sponsorships: relation_id_number={$newFileNumber}\n";

    DB::commit();

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🎉 تم إنشاء البيانات المركزية بنجاح!\n";
    echo str_repeat("=", 70) . "\n\n";

    // التحقق النهائي
    $sponsorship->refresh();
    echo "📌 التحقق النهائي:\n";
    echo "   - relation_id_number: {$sponsorship->relation_id_number}\n";
    echo "   - orphan_name: {$sponsorship->orphan_name}\n";
    echo "   - guardian_name: {$sponsorship->guardian_name}\n";
    echo "   - sponsored_birth_date: {$sponsorship->sponsored_birth_date}\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
