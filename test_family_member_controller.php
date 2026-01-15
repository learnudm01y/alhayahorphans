<?php
/**
 * اختبار سيناريوهات family_member عبر الـ Controller
 * يحاكي الطلبات القادمة من التطبيق المحمول
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\SponsorshipSyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 اختبار سيناريوهات family_member عبر Controller\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// إنشاء مستخدم وهمي للاختبار
$testUser = new stdClass();
$testUser->id = 1;

// ═══════════════════════════════════════════════════════════════
// السيناريو 1: كفالة غير مربوطة - إنشاء سجلات جديدة
// ═══════════════════════════════════════════════════════════════
echo "📋 السيناريو 1: كفالة غير مربوطة\n";
echo str_repeat("-", 50) . "\n";

// البحث عن كفالة غير مربوطة من نوع family_member
$unlinkedSponsorship = DB::table('sponsorships')
    ->where(function($q) {
        $q->whereNull('relation_id_number')
          ->orWhere('relation_id_number', '');
    })
    ->whereNotNull('internal_file_number')
    ->where('internal_file_number', '!=', '')
    ->where(function($q) {
        $q->where('person_type', 'family_member')
          ->orWhereNull('person_type')
          ->orWhere('person_type', 'orphan');
    })
    ->first();

if (!$unlinkedSponsorship) {
    echo "❌ لا توجد كفالات غير مربوطة من نوع family_member/orphan!\n";
    echo "   سيتم تخطي السيناريو 1\n\n";
    $scenario1Success = false;
} else {
    echo "✅ تم العثور على كفالة غير مربوطة:\n";
    echo "   - ID: {$unlinkedSponsorship->id}\n";
    echo "   - internal_file_number: {$unlinkedSponsorship->internal_file_number}\n";
    echo "   - relation_id_number: [" . ($unlinkedSponsorship->relation_id_number ?: 'فارغ') . "]\n";
    echo "   - person_type: " . ($unlinkedSponsorship->person_type ?? 'NULL') . "\n\n";

    // تجهيز البيانات المُرسلة من التطبيق
    $guardianIdentity = '901' . rand(100000, 999999);
    $orphanIdentity = '401' . rand(100000, 999999);

    $updates = [
        // بيانات المكفول (family_member)
        'first_name' => 'سعيد',
        'second_name' => 'فهد',
        'third_name' => 'عبدالله',
        'last_name' => 'الاختبار',
        'identity_number' => $orphanIdentity,
        'birth_date' => '2015-03-20',
        'orphan_gender' => 'ذكر',
        'person_type' => 'family_member',

        // بيانات المعيل
        'guardian_first_name' => 'فهد',
        'guardian_father_name' => 'عبدالله',
        'guardian_grandfather_name' => 'سالم',
        'guardian_family_name' => 'الاختبار',
        'guardian_identity_number' => $guardianIdentity,
        'guardian_phone' => '0591234567',
        'guardian_phone2' => '0597654321',
        'guardian_detailed_address' => 'غزة - الاختبار - السيناريو 1',

        // بيانات الحساب البنكي
        'bank_accounts_updates' => [
            [
                'bank_name' => 4,
                'iban_usd' => 'PS92PALS000000000TEST' . rand(10000, 99999) . 'USD',
                'iban_shekel' => 'PS92PALS000000000TEST' . rand(10000, 99999) . 'ILS',
                're_guardian_name' => 'فهد عبدالله سالم الاختبار',
                'person_owner_identity_number' => $guardianIdentity,
                're_phone_number' => '0591234567'
            ]
        ]
    ];

    echo "📤 البيانات المُرسلة:\n";
    echo "   المكفول: {$updates['first_name']} {$updates['second_name']} {$updates['third_name']} {$updates['last_name']}\n";
    echo "   هوية المكفول: {$updates['identity_number']}\n";
    echo "   المعيل: {$updates['guardian_first_name']} {$updates['guardian_father_name']} {$updates['guardian_grandfather_name']} {$updates['guardian_family_name']}\n";
    echo "   هوية المعيل: {$updates['guardian_identity_number']}\n\n";

    // إنشاء Request وهمي
    $request = Request::create('/api/sync/upload', 'POST', [
        'sponsorship_id' => $unlinkedSponsorship->id,
        'updates' => $updates
    ]);
    $request->setUserResolver(function() use ($testUser) {
        return $testUser;
    });

    // استدعاء الـ Controller
    try {
        $controller = new SponsorshipSyncController();
        $response = $controller->uploadSyncData($request);
        $responseData = json_decode($response->getContent(), true);

        echo "📥 الاستجابة:\n";
        echo "   - success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
        echo "   - message: " . ($responseData['message'] ?? 'N/A') . "\n\n";

        // التحقق من النتائج
        $updatedSponsorship = DB::table('sponsorships')->where('id', $unlinkedSponsorship->id)->first();

        echo "📋 التحقق من النتائج:\n";
        echo "   - relation_id_number: [" . ($updatedSponsorship->relation_id_number ?: 'فارغ') . "]\n";
        echo "   - guardian_name: " . ($updatedSponsorship->guardian_name ?? 'NULL') . "\n";
        echo "   - guardian_identity_number: " . ($updatedSponsorship->guardian_identity_number ?? 'NULL') . "\n";
        echo "   - orphan_name: " . ($updatedSponsorship->orphan_name ?? 'NULL') . "\n";
        echo "   - identity_number: " . ($updatedSponsorship->identity_number ?? 'NULL') . "\n";

        // التحقق من وجود السجلات في الجداول
        if (!empty($updatedSponsorship->relation_id_number)) {
            $dataRecord = DB::table('data')
                ->where('file_id_number', $updatedSponsorship->relation_id_number)
                ->first();

            $rePeopleRecord = DB::table('re_people')
                ->where('registration_id', $updatedSponsorship->relation_id_number)
                ->first();

            $bankRecord = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $updatedSponsorship->relation_id_number)
                ->first();

            echo "\n   📊 السجلات المُنشأة:\n";
            echo "   - data: " . ($dataRecord ? "✅ موجود (ID: {$dataRecord->id})" : "❌ غير موجود") . "\n";
            echo "   - re_people: " . ($rePeopleRecord ? "✅ موجود (ID: {$rePeopleRecord->id})" : "❌ غير موجود") . "\n";
            echo "   - guardian_bank_accounts: " . ($bankRecord ? "✅ موجود (ID: {$bankRecord->id})" : "❌ غير موجود") . "\n";

            $scenario1Success = !empty($updatedSponsorship->relation_id_number) &&
                               $dataRecord &&
                               $rePeopleRecord;
        } else {
            $scenario1Success = false;
        }
    } catch (\Exception $e) {
        echo "❌ خطأ: " . $e->getMessage() . "\n";
        $scenario1Success = false;
    }

    echo "\n" . ($scenario1Success ? "✅✅✅ السيناريو 1: نجاح! ✅✅✅" : "❌ السيناريو 1: فشل!") . "\n";
}

echo "\n" . str_repeat("═", 60) . "\n\n";

// ═══════════════════════════════════════════════════════════════
// السيناريو 2: كفالة مربوطة - تحديث السجلات الموجودة
// ═══════════════════════════════════════════════════════════════
echo "📋 السيناريو 2: كفالة مربوطة - تحديث\n";
echo str_repeat("-", 50) . "\n";

// البحث عن كفالة مربوطة من نوع family_member
$linkedSponsorship = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->whereNotNull('internal_file_number')
    ->where('internal_file_number', '!=', '')
    ->where(function($q) {
        $q->where('person_type', 'family_member')
          ->orWhereNull('person_type')
          ->orWhere('person_type', 'orphan');
    })
    ->first();

if (!$linkedSponsorship) {
    echo "❌ لا توجد كفالات مربوطة من نوع family_member/orphan!\n";
    echo "   سيتم تخطي السيناريو 2\n\n";
    $scenario2Success = false;
} else {
    echo "✅ تم العثور على كفالة مربوطة:\n";
    echo "   - ID: {$linkedSponsorship->id}\n";
    echo "   - internal_file_number: {$linkedSponsorship->internal_file_number}\n";
    echo "   - relation_id_number: {$linkedSponsorship->relation_id_number}\n";
    echo "   - person_type: " . ($linkedSponsorship->person_type ?? 'NULL') . "\n\n";

    // جلب السجلات الموجودة
    $existingData = DB::table('data')
        ->where('file_id_number', $linkedSponsorship->relation_id_number)
        ->first();

    $existingRePeople = DB::table('re_people')
        ->where('registration_id', $linkedSponsorship->relation_id_number)
        ->first();

    echo "📊 السجلات الموجودة:\n";
    echo "   - data: " . ($existingData ? "✅ (ID: {$existingData->id})" : "❌") . "\n";
    echo "   - re_people: " . ($existingRePeople ? "✅ (ID: {$existingRePeople->id})" : "❌") . "\n\n";

    // تجهيز البيانات المُحدّثة
    $newGuardianIdentity = '902' . rand(100000, 999999);
    $newOrphanIdentity = '402' . rand(100000, 999999);

    $updates = [
        // بيانات المكفول المحدثة
        'first_name' => 'محمد',
        'second_name' => 'خالد',
        'third_name' => 'أحمد',
        'last_name' => 'التحديث',
        'identity_number' => $newOrphanIdentity,
        'birth_date' => '2016-07-10',
        'orphan_gender' => 'ذكر',
        'person_type' => 'family_member',

        // بيانات المعيل المحدثة
        'guardian_first_name' => 'خالد',
        'guardian_father_name' => 'أحمد',
        'guardian_grandfather_name' => 'علي',
        'guardian_family_name' => 'التحديث',
        'guardian_identity_number' => $newGuardianIdentity,
        'guardian_phone' => '0599999999',
        'guardian_phone2' => '0598888888',
        'guardian_detailed_address' => 'غزة - التحديث - السيناريو 2'
    ];

    echo "📤 البيانات المُحدّثة:\n";
    echo "   المكفول: {$updates['first_name']} {$updates['second_name']} {$updates['third_name']} {$updates['last_name']}\n";
    echo "   هوية المكفول: {$updates['identity_number']}\n";
    echo "   المعيل: {$updates['guardian_first_name']} {$updates['guardian_father_name']} {$updates['guardian_grandfather_name']} {$updates['guardian_family_name']}\n";
    echo "   هوية المعيل: {$updates['guardian_identity_number']}\n\n";

    // إنشاء Request وهمي
    $request = Request::create('/api/sync/upload', 'POST', [
        'sponsorship_id' => $linkedSponsorship->id,
        'updates' => $updates
    ]);
    $request->setUserResolver(function() use ($testUser) {
        return $testUser;
    });

    // استدعاء الـ Controller
    try {
        $controller = new SponsorshipSyncController();
        $response = $controller->uploadSyncData($request);
        $responseData = json_decode($response->getContent(), true);

        echo "📥 الاستجابة:\n";
        echo "   - success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
        echo "   - message: " . ($responseData['message'] ?? 'N/A') . "\n\n";

        // التحقق من النتائج
        $updatedSponsorship = DB::table('sponsorships')->where('id', $linkedSponsorship->id)->first();

        echo "📋 التحقق من النتائج:\n";
        echo "   - guardian_name: " . ($updatedSponsorship->guardian_name ?? 'NULL') . "\n";
        echo "   - orphan_name: " . ($updatedSponsorship->orphan_name ?? 'NULL') . "\n";
        echo "   - identity_number: " . ($updatedSponsorship->identity_number ?? 'NULL') . "\n";

        // التحقق من تحديث السجلات
        $updatedData = DB::table('data')
            ->where('file_id_number', $updatedSponsorship->relation_id_number)
            ->first();

        $updatedRePeople = DB::table('re_people')
            ->where('registration_id', $updatedSponsorship->relation_id_number)
            ->first();

        if ($updatedData) {
            $dataFullName = "{$updatedData->data_first_name} {$updatedData->data_father_name} {$updatedData->data_grand_father_name} {$updatedData->data_family_name}";
            echo "\n   📊 المعيل في data:\n";
            echo "   - الاسم: {$dataFullName}\n";
            echo "   - العنوان: " . ($updatedData->data_current_address ?? 'NULL') . "\n";
        }

        if ($updatedRePeople) {
            $rePeopleFullName = "{$updatedRePeople->first_name} {$updatedRePeople->second_name} {$updatedRePeople->third_name} {$updatedRePeople->last_name}";
            echo "\n   📊 المكفول في re_people:\n";
            echo "   - الاسم: {$rePeopleFullName}\n";
            echo "   - person_id: " . ($updatedRePeople->person_id ?? 'NULL') . "\n";
        }

        // التحقق من التحديث
        $expectedOrphanName = "{$updates['first_name']} {$updates['second_name']} {$updates['third_name']} {$updates['last_name']}";
        $expectedGuardianName = "{$updates['guardian_first_name']} {$updates['guardian_father_name']} {$updates['guardian_grandfather_name']} {$updates['guardian_family_name']}";

        $scenario2Success = $responseData['success'] &&
                           strpos($updatedSponsorship->orphan_name, $updates['first_name']) !== false &&
                           strpos($updatedSponsorship->guardian_name, $updates['guardian_first_name']) !== false;

    } catch (\Exception $e) {
        echo "❌ خطأ: " . $e->getMessage() . "\n";
        echo "   Trace: " . $e->getTraceAsString() . "\n";
        $scenario2Success = false;
    }

    echo "\n" . ($scenario2Success ? "✅✅✅ السيناريو 2: نجاح! ✅✅✅" : "❌ السيناريو 2: فشل!") . "\n";
}

echo "\n" . str_repeat("═", 60) . "\n";

// ═══════════════════════════════════════════════════════════════
// الملخص النهائي
// ═══════════════════════════════════════════════════════════════
echo "\n📊 ملخص الاختبار:\n";
echo str_repeat("═", 40) . "\n";
echo "   السيناريو 1 (غير مربوط): " . (isset($scenario1Success) && $scenario1Success ? "✅ نجاح" : "❌ فشل/تخطي") . "\n";
echo "   السيناريو 2 (مربوط): " . (isset($scenario2Success) && $scenario2Success ? "✅ نجاح" : "❌ فشل/تخطي") . "\n";
echo str_repeat("═", 40) . "\n";

$allSuccess = (isset($scenario1Success) && $scenario1Success) && (isset($scenario2Success) && $scenario2Success);
if ($allSuccess) {
    echo "\n🎉🎉🎉 جميع الاختبارات ناجحة! 🎉🎉🎉\n";
} else {
    echo "\n⚠️ بعض الاختبارات فشلت أو تم تخطيها\n";
}
