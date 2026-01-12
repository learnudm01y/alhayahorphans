<?php
/**
 * تقرير شامل: تحليل منطق تخزين البيانات
 * يشمل: المكفول + المعيل + الحسابات البنكية
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    تقرير تحليل منطق تخزين البيانات                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

// =====================================================
// القسم 1: تحليل جدول sponsorships (الجدول الرئيسي)
// =====================================================
echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 1️⃣  جدول sponsorships - الجدول الرئيسي للكفالات                             │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

$sponsorshipColumns = DB::select("SHOW COLUMNS FROM sponsorships");
echo "\n📋 الحقول المتعلقة بالمكفول:\n";
$orphanFields = ['identity_number', 'orphan_name', 'orphan_gender', 'person_type', 'relation_id_number'];
foreach ($sponsorshipColumns as $col) {
    if (in_array($col->Field, $orphanFields) || strpos($col->Field, 'orphan') !== false) {
        echo "   • {$col->Field} ({$col->Type})\n";
    }
}

echo "\n📋 الحقول المتعلقة بالمعيل:\n";
$guardianFields = ['guardian_identity_number', 'guardian_name', 'guardian_phone', 'guardian_phone2', 'guardian_city_id', 'guardian_detailed_address'];
foreach ($sponsorshipColumns as $col) {
    if (in_array($col->Field, $guardianFields) || strpos($col->Field, 'guardian') !== false) {
        echo "   • {$col->Field} ({$col->Type})\n";
    }
}

// =====================================================
// القسم 2: تحليل جداول البيانات المرتبطة
// =====================================================
echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 2️⃣  جداول البيانات المرتبطة                                                 │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

// جدول data
echo "\n📊 جدول data (للمعيل/breadwinner):\n";
$dataColumns = DB::select("SHOW COLUMNS FROM data");
$dataKeys = ['file_id_number', 'data_id_number', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name', 'data_gender', 'data_birth_date'];
foreach ($dataColumns as $col) {
    if (in_array($col->Field, $dataKeys)) {
        echo "   • {$col->Field} ({$col->Type})\n";
    }
}
echo "   🔗 الربط: sponsorships.relation_id_number → data.file_id_number\n";

// جدول re_people
echo "\n📊 جدول re_people (لفرد العائلة/family_member و اليتيم/orphan):\n";
$repeopleColumns = DB::select("SHOW COLUMNS FROM re_people");
$repeopleKeys = ['registration_id', 'person_id', 'first_name', 'second_name', 'third_name', 'last_name', 'person_gender', 'person_birth_date'];
foreach ($repeopleColumns as $col) {
    if (in_array($col->Field, $repeopleKeys)) {
        echo "   • {$col->Field} ({$col->Type})\n";
    }
}
echo "   🔗 الربط: sponsorships.relation_id_number → re_people.registration_id\n";

// جدول dead_people
echo "\n📊 جدول dead_people (للمتوفين):\n";
$deadColumns = DB::select("SHOW COLUMNS FROM dead_people");
echo "   حقول الأب (deceased_father):\n";
foreach ($deadColumns as $col) {
    if (strpos($col->Field, 'father_') === 0) {
        echo "   • {$col->Field} ({$col->Type})\n";
    }
}
echo "   حقول الأم (deceased_mother):\n";
foreach ($deadColumns as $col) {
    if (strpos($col->Field, 'mother_') === 0) {
        echo "   • {$col->Field} ({$col->Type})\n";
    }
}
echo "   🔗 الربط: sponsorships.relation_id_number → dead_people.re_file_id\n";

// =====================================================
// القسم 3: خريطة التخزين التفصيلية
// =====================================================
echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 3️⃣  خريطة التخزين التفصيلية - من التطبيق إلى قاعدة البيانات                 │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

echo "\n📱 بيانات المكفول (من detail.html):\n";
echo "┌────────────────────────┬────────────────────────┬───────────────────────────────┐\n";
echo "│ حقل التطبيق            │ حقل sponsorships       │ حقل الجدول الهدف              │\n";
echo "├────────────────────────┼────────────────────────┼───────────────────────────────┤\n";
echo "│ first_name             │ -                      │ حسب person_type (انظر أدناه)  │\n";
echo "│ second_name            │ -                      │ حسب person_type               │\n";
echo "│ third_name             │ -                      │ حسب person_type               │\n";
echo "│ last_name              │ -                      │ حسب person_type               │\n";
echo "│ identity_number        │ identity_number        │ حسب person_type               │\n";
echo "│ birth_date             │ -                      │ حسب person_type               │\n";
echo "│ orphan_gender          │ orphan_gender          │ حسب person_type               │\n";
echo "└────────────────────────┴────────────────────────┴───────────────────────────────┘\n";

echo "\n📋 توزيع الحقول حسب person_type:\n";
echo "┌─────────────────────┬────────────────────────┬───────────────────────────────────┐\n";
echo "│ person_type         │ الجدول                 │ تحويل الحقول                      │\n";
echo "├─────────────────────┼────────────────────────┼───────────────────────────────────┤\n";
echo "│ breadwinner         │ data                   │ first_name → data_first_name      │\n";
echo "│                     │                        │ second_name → data_father_name    │\n";
echo "│                     │                        │ third_name → data_grand_father_name│\n";
echo "│                     │                        │ last_name → data_family_name      │\n";
echo "│                     │                        │ orphan_gender → data_gender       │\n";
echo "│                     │                        │ identity_number → data_id_number  │\n";
echo "├─────────────────────┼────────────────────────┼───────────────────────────────────┤\n";
echo "│ family_member/orphan│ re_people              │ first_name → first_name           │\n";
echo "│                     │                        │ second_name → second_name         │\n";
echo "│                     │                        │ third_name → third_name           │\n";
echo "│                     │                        │ last_name → last_name             │\n";
echo "│                     │                        │ orphan_gender → person_gender     │\n";
echo "│                     │                        │ identity_number → person_id       │\n";
echo "├─────────────────────┼────────────────────────┼───────────────────────────────────┤\n";
echo "│ deceased_father     │ dead_people            │ first_name → father_first_name    │\n";
echo "│                     │                        │ second_name → father_second_name  │\n";
echo "│                     │                        │ third_name → father_third_name    │\n";
echo "│                     │                        │ last_name → father_last_name      │\n";
echo "│                     │                        │ identity_number → father_id       │\n";
echo "│                     │                        │ الجنس: ذكر (تلقائي)               │\n";
echo "├─────────────────────┼────────────────────────┼───────────────────────────────────┤\n";
echo "│ deceased_mother     │ dead_people            │ first_name → mother_first_name    │\n";
echo "│                     │                        │ second_name → mother_second_name  │\n";
echo "│                     │                        │ third_name → mother_third_name    │\n";
echo "│                     │                        │ last_name → mother_last_name      │\n";
echo "│                     │                        │ identity_number → mother_id       │\n";
echo "│                     │                        │ الجنس: أنثى (تلقائي)              │\n";
echo "└─────────────────────┴────────────────────────┴───────────────────────────────────┘\n";

echo "\n📱 بيانات المعيل (من detail.html):\n";
echo "┌────────────────────────────────┬────────────────────────────────────────────────┐\n";
echo "│ حقل التطبيق                    │ حقل sponsorships                               │\n";
echo "├────────────────────────────────┼────────────────────────────────────────────────┤\n";
echo "│ guardian_first_name            │ guardian_name (مجمع)                           │\n";
echo "│ guardian_father_name           │ guardian_name (مجمع)                           │\n";
echo "│ guardian_grandfather_name      │ guardian_name (مجمع)                           │\n";
echo "│ guardian_family_name           │ guardian_name (مجمع)                           │\n";
echo "│ guardian_identity_number       │ guardian_identity_number                       │\n";
echo "│ guardian_phone                 │ guardian_phone                                 │\n";
echo "│ guardian_phone2                │ guardian_phone2                                │\n";
echo "│ guardian_city_id               │ guardian_city_id                               │\n";
echo "│ guardian_detailed_address      │ guardian_detailed_address                      │\n";
echo "└────────────────────────────────┴────────────────────────────────────────────────┘\n";

echo "\n⚠️  ملاحظة: بيانات المعيل تُخزن أيضاً في جدول data إذا كان relation_id_number موجود:\n";
echo "   guardian_first_name → data.data_first_name\n";
echo "   guardian_father_name → data.data_father_name\n";
echo "   guardian_grandfather_name → data.data_grand_father_name\n";
echo "   guardian_family_name → data.data_family_name\n";
echo "   guardian_phone → data.data_phone_number\n";
echo "   guardian_phone2 → data.data_alt_phone_number\n";

// =====================================================
// القسم 4: تحليل جدول الحسابات البنكية
// =====================================================
echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 4️⃣  تحليل جدول الحسابات البنكية guardian_bank_accounts                      │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

try {
    $bankColumns = DB::select("SHOW COLUMNS FROM guardian_bank_accounts");
    echo "\n📊 هيكل جدول guardian_bank_accounts:\n";
    echo "┌────────────────────────────────┬─────────────────────┬──────────┐\n";
    echo "│ الحقل                          │ النوع               │ مطلوب    │\n";
    echo "├────────────────────────────────┼─────────────────────┼──────────┤\n";
    foreach ($bankColumns as $col) {
        $required = $col->Null === 'NO' ? 'نعم' : 'لا';
        $field = str_pad($col->Field, 30);
        $type = str_pad($col->Type, 19);
        echo "│ {$field}│ {$type}│ {$required}      │\n";
    }
    echo "└────────────────────────────────┴─────────────────────┴──────────┘\n";

    // عرض بعض البيانات
    $sampleAccounts = DB::table('guardian_bank_accounts')->limit(3)->get();
    echo "\n📊 عينة من البيانات الموجودة:\n";
    if ($sampleAccounts->count() > 0) {
        foreach ($sampleAccounts as $acc) {
            echo "   • ID: {$acc->id}\n";
            echo "     - sponsorship_id: " . ($acc->sponsorship_id ?? 'NULL') . "\n";
            echo "     - bank_name: " . ($acc->bank_name ?? 'NULL') . "\n";
            echo "     - re_guardian_name: " . ($acc->re_guardian_name ?? 'NULL') . "\n";
            echo "     - person_owner_identity_number: " . ($acc->person_owner_identity_number ?? 'NULL') . "\n";
            echo "     - iban_usd: " . ($acc->iban_usd ?? $acc->iban ?? 'NULL') . "\n";
            echo "     - iban_shekel: " . ($acc->iban_shekel ?? 'NULL') . "\n";
            echo "     - check_account: " . ($acc->check_account ?? 'NULL') . "\n";
            echo "\n";
        }
    } else {
        echo "   ⚠️ لا توجد حسابات بنكية\n";
    }

} catch (Exception $e) {
    echo "   ❌ خطأ في جدول guardian_bank_accounts: {$e->getMessage()}\n";
}

// =====================================================
// القسم 5: فحص كود تحديث الحسابات البنكية
// =====================================================
echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 5️⃣  فحص منطق تحديث الحسابات البنكية في Controller                           │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

$controllerPath = __DIR__ . '/app/Http/Controllers/Api/SponsorshipSyncController.php';
$controllerContent = file_get_contents($controllerPath);

// البحث عن دالة updateBankAccounts
if (preg_match('/private function updateBankAccounts\([^)]*\)[^{]*\{([\s\S]*?)^\s{4}\}/m', $controllerContent, $match)) {
    echo "\n✅ تم العثور على دالة updateBankAccounts\n";

    // فحص الحقول المدعومة
    $supportedFields = [];
    if (strpos($match[1], 'bank_name') !== false) $supportedFields[] = 'bank_name';
    if (strpos($match[1], 're_guardian_name') !== false) $supportedFields[] = 're_guardian_name';
    if (strpos($match[1], 'person_owner_identity_number') !== false) $supportedFields[] = 'person_owner_identity_number';
    if (strpos($match[1], 're_phone_number') !== false) $supportedFields[] = 're_phone_number';
    if (strpos($match[1], 'iban_usd') !== false) $supportedFields[] = 'iban_usd';
    if (strpos($match[1], 'iban_shekel') !== false) $supportedFields[] = 'iban_shekel';
    if (strpos($match[1], 'iban') !== false && !in_array('iban', $supportedFields)) $supportedFields[] = 'iban';

    echo "\n📋 الحقول المدعومة للتحديث:\n";
    foreach ($supportedFields as $field) {
        echo "   ✓ {$field}\n";
    }
} else {
    echo "\n⚠️ لم يتم العثور على دالة updateBankAccounts\n";
}

// =====================================================
// القسم 6: مقارنة حقول التطبيق مع قاعدة البيانات
// =====================================================
echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 6️⃣  مقارنة حقول التطبيق (detail.html) مع جدول guardian_bank_accounts        │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

// قراءة detail.html للتحقق من الحقول المستخدمة
$detailPath = __DIR__ . '/mobile-app/dist/detail.html';
$detailContent = file_get_contents($detailPath);

echo "\n📱 حقول الحسابات البنكية في التطبيق:\n";
$appBankFields = [];
if (preg_match_all('/data-bank-field="([^"]+)"/', $detailContent, $matches)) {
    $appBankFields = array_unique($matches[1]);
    foreach ($appBankFields as $field) {
        echo "   • {$field}\n";
    }
}

echo "\n📊 حقول جدول guardian_bank_accounts:\n";
$dbBankFields = [];
foreach ($bankColumns as $col) {
    $dbBankFields[] = $col->Field;
    echo "   • {$col->Field}\n";
}

echo "\n🔍 تحليل التوافق:\n";
foreach ($appBankFields as $appField) {
    if (in_array($appField, $dbBankFields)) {
        echo "   ✅ {$appField} - موجود في قاعدة البيانات\n";
    } else {
        echo "   ⚠️ {$appField} - غير موجود! (قد يحتاج معالجة خاصة)\n";
    }
}

// =====================================================
// القسم 7: ملخص التوصيات
// =====================================================
echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 7️⃣  ملخص وتوصيات                                                            │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

$issues = [];

// فحص وجود حقل iban_usd
$hasIbanUsd = false;
$hasIban = false;
foreach ($bankColumns as $col) {
    if ($col->Field === 'iban_usd') $hasIbanUsd = true;
    if ($col->Field === 'iban') $hasIban = true;
}

if (!$hasIbanUsd && $hasIban) {
    $issues[] = "⚠️ الجدول يستخدم 'iban' بينما التطبيق يرسل 'iban_usd' - يحتاج معالجة";
}

if (empty($issues)) {
    echo "\n✅ لا توجد مشاكل كبيرة في التوافق\n";
} else {
    echo "\n⚠️ مشاكل تحتاج معالجة:\n";
    foreach ($issues as $issue) {
        echo "   {$issue}\n";
    }
}

echo "\n";
echo "════════════════════════════════════════════════════════════════════════════════\n";
echo "                              انتهى التقرير                                    \n";
echo "════════════════════════════════════════════════════════════════════════════════\n\n";
