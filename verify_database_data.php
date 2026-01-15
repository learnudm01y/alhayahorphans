<?php
/**
 * التحقق من البيانات المحفوظة في قاعدة البيانات
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🔍 التحقق من البيانات المحفوظة في قاعدة البيانات\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// الكفالات المختبرة
$sponsorshipIds = [4879, 4881, 100000, 100005, 100004];

foreach ($sponsorshipIds as $spId) {
    $sponsorship = DB::table('sponsorships')->where('id', $spId)->first();

    if (!$sponsorship) {
        echo "❌ الكفالة {$spId} غير موجودة\n\n";
        continue;
    }

    echo "📋 الكفالة ID: {$spId}\n";
    echo str_repeat("-", 50) . "\n";
    echo "   النوع: " . ($sponsorship->person_type ?? 'غير محدد') . "\n";
    echo "   المكفول: " . ($sponsorship->orphan_name ?? 'N/A') . "\n";
    echo "   هوية المكفول: " . ($sponsorship->identity_number ?? 'N/A') . "\n";
    echo "   المعيل: " . ($sponsorship->guardian_name ?? 'N/A') . "\n";
    echo "   هوية المعيل: " . ($sponsorship->guardian_identity_number ?? 'N/A') . "\n";
    echo "   relation_id_number: " . ($sponsorship->relation_id_number ?? 'فارغ') . "\n";

    // جلب البيانات من الجداول المرتبطة حسب النوع
    $personType = $sponsorship->person_type ?? 'orphan';
    $relationId = $sponsorship->relation_id_number;

    if (!empty($relationId)) {
        switch ($personType) {
            case 'breadwinner':
                $dataRecord = DB::table('data')->where('file_id_number', $relationId)->first();
                if ($dataRecord) {
                    echo "\n   📊 بيانات المعيل (جدول data):\n";
                    echo "      الاسم: {$dataRecord->data_first_name} {$dataRecord->data_father_name} {$dataRecord->data_grand_father_name} {$dataRecord->data_family_name}\n";
                    echo "      الهاتف: " . ($dataRecord->data_phone_number ?? 'N/A') . "\n";
                    echo "      الهاتف البديل: " . ($dataRecord->data_alt_phone_number ?? 'N/A') . "\n";
                    echo "      العنوان: " . ($dataRecord->data_current_address ?? 'N/A') . "\n";
                }
                break;

            case 'family_member':
            case 'orphan':
                $dataRecord = DB::table('data')->where('file_id_number', $relationId)->first();
                $rePeopleRecord = DB::table('re_people')->where('registration_id', $relationId)->first();

                if ($dataRecord) {
                    echo "\n   📊 بيانات المعيل (جدول data):\n";
                    echo "      الاسم: {$dataRecord->data_first_name} {$dataRecord->data_father_name} {$dataRecord->data_grand_father_name} {$dataRecord->data_family_name}\n";
                    echo "      الهاتف: " . ($dataRecord->data_phone_number ?? 'N/A') . "\n";
                    echo "      العنوان: " . ($dataRecord->data_current_address ?? 'N/A') . "\n";
                }

                if ($rePeopleRecord) {
                    echo "\n   📊 بيانات المكفول (جدول re_people):\n";
                    echo "      الاسم: {$rePeopleRecord->first_name} {$rePeopleRecord->second_name} {$rePeopleRecord->third_name} {$rePeopleRecord->last_name}\n";
                    echo "      الهوية: " . ($rePeopleRecord->person_id ?? 'N/A') . "\n";
                }
                break;

            case 'deceased_father':
            case 'deceased_mother':
                $deadRecord = DB::table('dead_people')->where('re_file_id', $relationId)->first();

                if ($deadRecord) {
                    echo "\n   📊 بيانات المتوفي (جدول dead_people):\n";
                    if ($personType == 'deceased_father') {
                        echo "      الأب: {$deadRecord->father_first_name} {$deadRecord->father_second_name} {$deadRecord->father_third_name} {$deadRecord->father_last_name}\n";
                    } else {
                        echo "      الأم: {$deadRecord->mother_first_name} {$deadRecord->mother_second_name} {$deadRecord->mother_third_name} {$deadRecord->mother_last_name}\n";
                    }
                }

                // البيانات الإضافية
                $extraFields = DB::table('portal_general_registration_field_values')
                    ->where('sponsorship_id', $spId)
                    ->get();

                if ($extraFields->isNotEmpty()) {
                    echo "\n   📊 البيانات الإضافية (portal_general_registration_field_values):\n";
                    foreach ($extraFields as $field) {
                        echo "      {$field->field_key}: {$field->field_value}\n";
                    }
                }
                break;
        }
    }

    // الحسابات البنكية
    $bankAccounts = DB::table('guardian_bank_accounts')
        ->where('guardian_registration', $relationId)
        ->get();

    if ($bankAccounts->isNotEmpty()) {
        echo "\n   🏦 الحسابات البنكية:\n";
        foreach ($bankAccounts as $i => $bank) {
            echo "      " . ($i + 1) . ". البنك: {$bank->bank_name} | IBAN: " . substr($bank->iban_usd ?? '', 0, 20) . "...\n";
        }
    }

    echo "\n" . str_repeat("═", 60) . "\n\n";
}

echo "✅ تم التحقق من جميع البيانات!\n";
