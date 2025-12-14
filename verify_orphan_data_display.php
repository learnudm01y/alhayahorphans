<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== FINAL VERIFICATION - Orphan Data Display ===\n\n";

$identityNumber = '666665457'; // براء محمد عمر صيدم
$password = '002622';

echo "LOGIN CREDENTIALS:\n";
echo "  Username: $identityNumber\n";
echo "  Password: $password\n";
echo "  URL: http://127.0.0.1:8000/user/general-registration\n\n";

// 1. Verify User
$user = App\Models\User::where('email', $identityNumber)->first();
if (!$user) {
    echo "✗ ERROR: User not found!\n";
    exit;
}
echo "✓ User: {$user->name} (ID: {$user->id})\n";

// 2. Verify Sponsorship
$sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();
if (!$sponsorship) {
    echo "✗ ERROR: Sponsorship not found!\n";
    exit;
}
echo "✓ Sponsorship: ID {$sponsorship->id}, File {$sponsorship->internal_file_number}\n";
echo "✓ Orphan Name: {$sponsorship->orphan_name}\n";
echo "✓ Sponsor ID: {$sponsorship->sponsor_id}\n\n";

// 3. Verify Field Settings
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();
if (!$fieldSettings) {
    echo "✗ ERROR: Field settings not found!\n";
    exit;
}
$activeCount = $fieldSettings->getActiveFieldsCount();
echo "✓ Field Settings: {$activeCount} fields enabled\n\n";

// 4. Verify Data Sources
echo "=== DATA SOURCES VERIFICATION ===\n\n";

echo "A. SPONSORSHIPS TABLE (المكفول - براء محمد عمر صيدم):\n";
echo "   - Orphan Name: {$sponsorship->orphan_name}\n";
echo "   - Identity Number: {$sponsorship->identity_number}\n";
echo "   - Internal File: {$sponsorship->internal_file_number}\n";
echo "   - External File: {$sponsorship->external_file_number}\n";
echo "   - Guardian Name: {$sponsorship->guardian_name}\n";
echo "   - Guardian ID: {$sponsorship->guardian_identity_number}\n";
echo "   - Sponsoring Org: {$sponsorship->sponsoring_organization}\n";
echo "   - Duration: {$sponsorship->sponsorship_duration_months} months\n";
echo "   - Notes: {$sponsorship->notes}\n\n";

if ($sponsorship->relation_id_number) {
    echo "B. RELATION DATA (File: {$sponsorship->relation_id_number}):\n";

    $relationData = App\Models\Data::where('file_id_number', $sponsorship->relation_id_number)->first();
    if ($relationData) {
        echo "   ✓ Guardian (from data table):\n";
        echo "     - Name: {$relationData->data_first_name} {$relationData->data_father_name} {$relationData->data_grand_father_name} {$relationData->data_family_name}\n";
        echo "     - ID: {$relationData->data_id_number}\n";
        echo "     - Phone: {$relationData->data_phone_number}\n";
        echo "     - Birth Date: {$relationData->data_birth_date}\n";
        echo "     - Address: {$relationData->data_current_address}\n\n";

        // Check dead people
        $dead = DB::table('dead_people')->where('re_file_id', $sponsorship->relation_id_number)->first();
        if ($dead) {
            echo "   ✓ Dead People Info:\n";
            echo "     - Father: {$dead->father_first_name} {$dead->father_second_name} {$dead->father_third_name} {$dead->father_last_name}\n";
            echo "     - Father ID: {$dead->father_id}\n";
            echo "     - Father Death Date: {$dead->father_death_date}\n";
            echo "     - Mother: {$dead->mother_first_name} {$dead->mother_second_name} {$dead->mother_third_name} {$dead->mother_last_name}\n";
            echo "     - Mother ID: {$dead->mother_id}\n\n";
        }

        // Check re people
        $re = DB::table('re_people')->where('registration_id', $sponsorship->relation_id_number)->first();
        if ($re) {
            echo "   ✓ Re People Info (Orphan Details):\n";
            echo "     - Full Name: {$re->first_name} {$re->second_name} {$re->third_name} {$re->last_name}\n";
            echo "     - Person ID: {$re->person_id}\n";
            echo "     - Birth Date: {$re->person_birth_date}\n";
            echo "     - Age: {$re->person_age}\n";
            echo "     - Gender: " . ($re->person_gender == 1 ? 'ذكر' : ($re->person_gender == 2 ? 'أنثى' : 'غير محدد')) . "\n";
            echo "     - Note: {$re->person_note}\n\n";
        }
    }
}

// 5. Config Check
$fields = config('sponsor_fields.fields');
$categories = config('sponsor_fields.categories');
echo "=== CONFIG VERIFICATION ===\n";
echo "✓ Total fields in config: " . count($fields) . "\n";
echo "✓ Total categories: " . count($categories) . "\n\n";

// 6. Sample enabled fields
echo "=== SAMPLE ENABLED FIELDS (First 15) ===\n";
$activeFields = $fieldSettings->getActiveFields();
$count = 0;
foreach ($activeFields as $field) {
    if ($count >= 15) break;
    $fieldInfo = $fields[$field] ?? null;
    if ($fieldInfo) {
        echo ($count + 1) . ". {$fieldInfo['display_name']} ({$field})\n";
    }
    $count++;
}
echo "... and " . (count($activeFields) - 15) . " more fields\n\n";

echo "✅ ALL SYSTEMS READY!\n\n";
echo "WHAT YOU SHOULD SEE:\n";
echo "1. Header: براء محمد عمر صيدم\n";
echo "2. Internal File: 002622\n";
echo "3. Identity Number: 666665457\n";
echo "4. {$activeCount} fields organized in " . count($categories) . " categories\n";
echo "5. All fields with actual data displayed\n";
echo "6. Ability to edit and save changes\n\n";

echo "=== TEST NOW ===\n";
echo "Login at: http://127.0.0.1:8000/login\n";
echo "Username: $identityNumber\n";
echo "Password: $password\n";
echo "\n=== Done ===\n";
