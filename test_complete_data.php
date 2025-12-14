<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار شامل للبيانات المطلوبة ===\n\n";

$identityNumber = '666665457';
$sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();

if (!$sponsorship) {
    echo "✗ لم يتم العثور على الكفالة!\n";
    exit;
}

echo "✓ الكفالة موجودة - ID: {$sponsorship->id}\n";
echo "  - اسم المكفول: {$sponsorship->orphan_name}\n";
echo "  - رقم الهوية: {$sponsorship->identity_number}\n";
echo "  - رقم الملف الداخلي: {$sponsorship->internal_file_number}\n\n";

// 1. تاريخ الميلاد
echo "=== 1. تاريخ الميلاد ===\n";
$birthDate = null;

// البحث في re_people برقم الهوية
$rePerson = DB::table('re_people')
    ->where('person_id', $identityNumber)
    ->first();

if ($rePerson && $rePerson->person_birth_date) {
    $birthDate = $rePerson->person_birth_date;
    echo "✓ تاريخ الميلاد من re_people: {$birthDate}\n";
} else {
    // البحث في data
    $data = DB::table('data')
        ->where('data_id_number', $identityNumber)
        ->first();

    if ($data && $data->data_birth_date) {
        $birthDate = $data->data_birth_date;
        echo "✓ تاريخ الميلاد من data: {$birthDate}\n";
    } else {
        echo "✗ لم يتم العثور على تاريخ الميلاد\n";
    }
}

// 2. الحالة الصحية
echo "\n=== 2. الحالة الصحية ===\n";
$healthStatus = null;

// البحث في re_people
if ($rePerson) {
    $healthStatusRecord = DB::table('health_statuses')
        ->where('id', $rePerson->person_health_status)
        ->first();

    if ($healthStatusRecord) {
        $healthStatus = $healthStatusRecord->health_status;
        echo "✓ الحالة الصحية من re_people: {$healthStatus}\n";
    }
}

// إذا لم نجد، نبحث في data
if (!$healthStatus) {
    $dataRecord = DB::table('data')
        ->where('data_id_number', $identityNumber)
        ->first();

    if ($dataRecord) {
        $healthStatusRecord = DB::table('health_statuses')
            ->where('id', $dataRecord->data_health_status)
            ->first();

        if ($healthStatusRecord) {
            $healthStatus = $healthStatusRecord->health_status;
            echo "✓ الحالة الصحية من data: {$healthStatus}\n";
        }
    }
}

if (!$healthStatus) {
    echo "✗ لم يتم العثور على الحالة الصحية\n";
}

// 3. حالة السكن برقم الهوية
echo "\n=== 3. حالة السكن (برقم الهوية) ===\n";
$housingStatus = null;

$dataRecord = DB::table('data')
    ->where('data_id_number', $identityNumber)
    ->first();

if ($dataRecord) {
    $housingStatusRecord = DB::table('housing_status')
        ->where('id', $dataRecord->data_housing_status)
        ->first();

    if ($housingStatusRecord) {
        $housingStatus = $housingStatusRecord->description;
        echo "✓ حالة السكن: {$housingStatus}\n";
    }

    // نوع السكن
    $housingTypeRecord = DB::table('type_of_accommodation')
        ->where('id', $dataRecord->data_current_housing_type)
        ->first();

    if ($housingTypeRecord) {
        echo "✓ نوع السكن: {$housingTypeRecord->description}\n";
    }
} else {
    echo "✗ لم يتم العثور على معلومات السكن\n";
}

// 4. أفراد الأسرة
echo "\n=== 4. أفراد الأسرة ===\n";
$relationData = $sponsorship->relationData;

if ($relationData) {
    $familyMembers = DB::table('re_people')
        ->where('registration_id', $relationData->file_id_number)
        ->get();

    echo "✓ عدد أفراد الأسرة: " . $familyMembers->count() . "\n";

    foreach ($familyMembers as $index => $member) {
        $num = $index + 1;
        $fullName = trim("{$member->first_name} {$member->second_name} {$member->third_name} {$member->last_name}");
        echo "  {$num}. {$fullName} - العمر: {$member->person_age}\n";
    }
} else {
    echo "✗ لم يتم العثور على بيانات المعيل\n";
}

// 5. الحساب البنكي المعتمد
echo "\n=== 5. الحساب البنكي المعتمد ===\n";

if ($relationData) {
    $approvedAccount = DB::table('guardian_banks_accounts')
        ->where('guardian_registration', $relationData->file_id_number)
        ->where('check_account', 1)
        ->first();

    if ($approvedAccount) {
        echo "✓ الحساب البنكي المعتمد:\n";
        echo "  - اسم صاحب الحساب: {$approvedAccount->guardian_name}\n";
        echo "  - رقم الحساب: {$approvedAccount->guardian_accounts_number}\n";
        echo "  - رقم هوية صاحب الحساب: {$approvedAccount->guardian_id}\n";
        echo "  - رقم ملف المعيل: {$approvedAccount->guardian_registration}\n";

        $bank = DB::table('banks')->where('id', $approvedAccount->guardian_bank)->first();
        if ($bank) {
            echo "  - اسم البنك: {$bank->bank_name}\n";
        }
    } else {
        echo "✗ لا يوجد حساب بنكي معتمد\n";
    }
} else {
    echo "✗ لم يتم العثور على بيانات المعيل\n";
}

echo "\n=== تم ===\n";
