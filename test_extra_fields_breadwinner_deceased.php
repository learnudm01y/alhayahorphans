<?php
/**
 * اختبار حفظ الحقول الإضافية (الهاتف والعنوان) لحالتي Breadwinner و Deceased
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\SponsorshipSyncController;
use Illuminate\Http\Request;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 اختبار حفظ الحقول الإضافية (الهاتف والعنوان)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// إنشاء مستخدم وهمي للاختبار
$testUser = new stdClass();
$testUser->id = 1;

$results = [];

// ═══════════════════════════════════════════════════════════════
// اختبار 1: Breadwinner - حفظ الهاتف والعنوان في جدول data
// ═══════════════════════════════════════════════════════════════
echo "📋 اختبار 1: Breadwinner - حفظ الهاتف والعنوان\n";
echo str_repeat("-", 50) . "\n";

// البحث عن كفالة من نوع breadwinner
$breadwinnerSponsorship = DB::table('sponsorships')
    ->where('person_type', 'breadwinner')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->first();

if (!$breadwinnerSponsorship) {
    echo "⚠️ لا توجد كفالات من نوع breadwinner مربوطة\n";
    echo "   سيتم البحث عن أي كفالة مربوطة وتحويلها\n";

    $breadwinnerSponsorship = DB::table('sponsorships')
        ->whereNotNull('relation_id_number')
        ->where('relation_id_number', '!=', '')
        ->first();

    if ($breadwinnerSponsorship) {
        // تحويل النوع إلى breadwinner للاختبار
        DB::table('sponsorships')
            ->where('id', $breadwinnerSponsorship->id)
            ->update(['person_type' => 'breadwinner']);
        $breadwinnerSponsorship->person_type = 'breadwinner';
        echo "   ✅ تم تحويل الكفالة {$breadwinnerSponsorship->id} إلى breadwinner\n";
    }
}

if ($breadwinnerSponsorship) {
    echo "✅ كفالة breadwinner: ID {$breadwinnerSponsorship->id}\n";
    echo "   relation_id_number: {$breadwinnerSponsorship->relation_id_number}\n\n";

    // تجهيز البيانات
    $testPhone = '059' . rand(1000000, 9999999);
    $testPhone2 = '059' . rand(1000000, 9999999);
    $testAddress = 'غزة - اختبار Breadwinner - ' . date('Y-m-d H:i:s');

    $updates = [
        'first_name' => 'معيل',
        'second_name' => 'اختبار',
        'third_name' => 'هاتف',
        'last_name' => 'عنوان',
        'person_type' => 'breadwinner',
        'orphan_phone' => $testPhone,
        'orphan_phone2' => $testPhone2,
        'orphan_detailed_address' => $testAddress
    ];

    echo "📤 البيانات المُرسلة:\n";
    echo "   - orphan_phone: {$testPhone}\n";
    echo "   - orphan_phone2: {$testPhone2}\n";
    echo "   - orphan_detailed_address: {$testAddress}\n\n";

    // إنشاء Request وهمي
    $request = Request::create('/api/sync/upload', 'POST', [
        'sponsorship_id' => $breadwinnerSponsorship->id,
        'updates' => $updates
    ]);
    $request->setUserResolver(function() use ($testUser) {
        return $testUser;
    });

    try {
        $controller = new SponsorshipSyncController();
        $response = $controller->uploadSyncData($request);
        $responseData = json_decode($response->getContent(), true);

        echo "📥 الاستجابة: " . ($responseData['success'] ? '✅ نجاح' : '❌ فشل') . "\n";

        // التحقق من حفظ البيانات في جدول data
        $dataRecord = DB::table('data')
            ->where('file_id_number', $breadwinnerSponsorship->relation_id_number)
            ->first();

        if ($dataRecord) {
            echo "\n📊 البيانات في جدول data:\n";
            echo "   - data_phone_number: " . ($dataRecord->data_phone_number ?? 'NULL') . "\n";
            echo "   - data_alt_phone_number: " . ($dataRecord->data_alt_phone_number ?? 'NULL') . "\n";
            echo "   - data_current_address: " . ($dataRecord->data_current_address ?? 'NULL') . "\n";

            // التحقق من التطابق
            $phoneMatch = (int)preg_replace('/[^0-9]/', '', $testPhone) == $dataRecord->data_phone_number;
            $phone2Match = (int)preg_replace('/[^0-9]/', '', $testPhone2) == $dataRecord->data_alt_phone_number;
            $addressMatch = $testAddress == $dataRecord->data_current_address;

            $results['breadwinner'] = $phoneMatch && $phone2Match && $addressMatch;

            echo "\n   التحقق:\n";
            echo "   - الهاتف: " . ($phoneMatch ? '✅' : '❌') . "\n";
            echo "   - الهاتف البديل: " . ($phone2Match ? '✅' : '❌') . "\n";
            echo "   - العنوان: " . ($addressMatch ? '✅' : '❌') . "\n";
        } else {
            echo "❌ لم يتم العثور على سجل data\n";
            $results['breadwinner'] = false;
        }
    } catch (\Exception $e) {
        echo "❌ خطأ: " . $e->getMessage() . "\n";
        $results['breadwinner'] = false;
    }
} else {
    echo "❌ لا توجد كفالات للاختبار\n";
    $results['breadwinner'] = false;
}

echo "\n" . str_repeat("═", 60) . "\n\n";

// ═══════════════════════════════════════════════════════════════
// اختبار 2: Deceased Father - حفظ في portal_general_registration_field_values
// ═══════════════════════════════════════════════════════════════
echo "📋 اختبار 2: Deceased Father - حفظ الهاتف والعنوان\n";
echo str_repeat("-", 50) . "\n";

// البحث عن كفالة من نوع deceased_father
$deceasedSponsorship = DB::table('sponsorships')
    ->where('person_type', 'deceased_father')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->first();

if (!$deceasedSponsorship) {
    echo "⚠️ لا توجد كفالات من نوع deceased_father مربوطة\n";
    echo "   سيتم البحث عن أي كفالة مربوطة وتحويلها\n";

    $deceasedSponsorship = DB::table('sponsorships')
        ->whereNotNull('relation_id_number')
        ->where('relation_id_number', '!=', '')
        ->orderBy('id', 'desc')
        ->skip(15)
        ->first();

    if ($deceasedSponsorship) {
        // تحويل النوع إلى deceased_father للاختبار
        DB::table('sponsorships')
            ->where('id', $deceasedSponsorship->id)
            ->update(['person_type' => 'deceased_father']);
        $deceasedSponsorship->person_type = 'deceased_father';
        echo "   ✅ تم تحويل الكفالة {$deceasedSponsorship->id} إلى deceased_father\n";

        // إنشاء سجل في dead_people إذا لم يكن موجوداً
        $deadRecord = DB::table('dead_people')
            ->where('re_file_id', $deceasedSponsorship->relation_id_number)
            ->first();

        if (!$deadRecord) {
            DB::table('dead_people')->insert([
                're_file_id' => $deceasedSponsorship->relation_id_number,
                'father_first_name' => 'أب',
                'father_second_name' => 'متوفي',
                'father_third_name' => 'اختبار',
                'father_last_name' => 'عائلة',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "   ✅ تم إنشاء سجل في dead_people\n";
        }
    }
}

if ($deceasedSponsorship) {
    echo "✅ كفالة deceased_father: ID {$deceasedSponsorship->id}\n";
    echo "   relation_id_number: {$deceasedSponsorship->relation_id_number}\n\n";

    // تجهيز البيانات
    $testPhone = '059' . rand(1000000, 9999999);
    $testPhone2 = '059' . rand(1000000, 9999999);
    $testAddress = 'غزة - اختبار Deceased - ' . date('Y-m-d H:i:s');

    $updates = [
        'first_name' => 'متوفي',
        'second_name' => 'اختبار',
        'third_name' => 'هاتف',
        'last_name' => 'عنوان',
        'person_type' => 'deceased_father',
        'orphan_phone' => $testPhone,
        'orphan_phone2' => $testPhone2,
        'orphan_detailed_address' => $testAddress
    ];

    echo "📤 البيانات المُرسلة:\n";
    echo "   - orphan_phone: {$testPhone}\n";
    echo "   - orphan_phone2: {$testPhone2}\n";
    echo "   - orphan_detailed_address: {$testAddress}\n\n";

    // إنشاء Request وهمي
    $request = Request::create('/api/sync/upload', 'POST', [
        'sponsorship_id' => $deceasedSponsorship->id,
        'updates' => $updates
    ]);
    $request->setUserResolver(function() use ($testUser) {
        return $testUser;
    });

    try {
        $controller = new SponsorshipSyncController();
        $response = $controller->uploadSyncData($request);
        $responseData = json_decode($response->getContent(), true);

        echo "📥 الاستجابة: " . ($responseData['success'] ? '✅ نجاح' : '❌ فشل') . "\n";

        // التحقق من حفظ البيانات في portal_general_registration_field_values
        $fileId = $deceasedSponsorship->internal_file_number ?? $deceasedSponsorship->relation_id_number;

        $phoneField = DB::table('portal_general_registration_field_values')
            ->where('sponsorship_id', $deceasedSponsorship->id)
            ->where('field_key', 'field_data_phone_number')
            ->first();

        $altPhoneField = DB::table('portal_general_registration_field_values')
            ->where('sponsorship_id', $deceasedSponsorship->id)
            ->where('field_key', 'field_data_alt_phone_number')
            ->first();

        $addressField = DB::table('portal_general_registration_field_values')
            ->where('sponsorship_id', $deceasedSponsorship->id)
            ->where('field_key', 'field_housing_address_detail')
            ->first();

        echo "\n📊 البيانات في portal_general_registration_field_values:\n";
        echo "   - field_data_phone_number: " . ($phoneField ? $phoneField->field_value : 'NULL') . "\n";
        echo "   - field_data_alt_phone_number: " . ($altPhoneField ? $altPhoneField->field_value : 'NULL') . "\n";
        echo "   - field_housing_address_detail: " . ($addressField ? $addressField->field_value : 'NULL') . "\n";

        // التحقق من التطابق
        $phoneMatch = $phoneField && $phoneField->field_value == $testPhone;
        $phone2Match = $altPhoneField && $altPhoneField->field_value == $testPhone2;
        $addressMatch = $addressField && $addressField->field_value == $testAddress;

        $results['deceased'] = $phoneMatch && $phone2Match && $addressMatch;

        echo "\n   التحقق:\n";
        echo "   - الهاتف: " . ($phoneMatch ? '✅' : '❌') . "\n";
        echo "   - الهاتف البديل: " . ($phone2Match ? '✅' : '❌') . "\n";
        echo "   - العنوان: " . ($addressMatch ? '✅' : '❌') . "\n";

    } catch (\Exception $e) {
        echo "❌ خطأ: " . $e->getMessage() . "\n";
        echo "   Trace: " . substr($e->getTraceAsString(), 0, 500) . "\n";
        $results['deceased'] = false;
    }
} else {
    echo "❌ لا توجد كفالات للاختبار\n";
    $results['deceased'] = false;
}

echo "\n" . str_repeat("═", 60) . "\n";

// ═══════════════════════════════════════════════════════════════
// الملخص النهائي
// ═══════════════════════════════════════════════════════════════
echo "\n📊 ملخص الاختبار:\n";
echo str_repeat("═", 40) . "\n";
echo "   Breadwinner (الهاتف + العنوان): " . (isset($results['breadwinner']) && $results['breadwinner'] ? "✅ نجاح" : "❌ فشل") . "\n";
echo "   Deceased (الهاتف + العنوان): " . (isset($results['deceased']) && $results['deceased'] ? "✅ نجاح" : "❌ فشل") . "\n";
echo str_repeat("═", 40) . "\n";

$allSuccess = ($results['breadwinner'] ?? false) && ($results['deceased'] ?? false);
if ($allSuccess) {
    echo "\n🎉🎉🎉 جميع الاختبارات ناجحة! 🎉🎉🎉\n";
} else {
    echo "\n⚠️ بعض الاختبارات فشلت\n";
}
