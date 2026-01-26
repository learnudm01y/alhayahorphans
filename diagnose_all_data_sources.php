<?php
// فحص شامل لجميع مصادر البيانات لـ sponsorship_id=198

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Sponsorship;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;
use App\Models\PortalGeneralRegistrationFieldValue;
use Illuminate\Support\Facades\DB;

$sponsorshipId = 198;
$sponsorship = Sponsorship::find($sponsorshipId);

if (!$sponsorship) {
    echo "❌ Sponsorship not found!\n";
    exit;
}

echo "=== SPONSORSHIP DATA ===\n";
echo "ID: {$sponsorship->id}\n";
echo "Orphan Name: {$sponsorship->orphan_name}\n";
echo "Identity: {$sponsorship->identity_number}\n";
echo "Guardian Identity: {$sponsorship->guardian_identity_number}\n";
echo "File Number: {$sponsorship->internal_file_number}\n";
echo "Relation ID: {$sponsorship->relation_id_number}\n";
echo "Person Type: {$sponsorship->person_type}\n\n";

// 1. فحص جدول data
echo "=== 1. TABLE: data ===\n";
$dataByFile = Data::where('file_id_number', $sponsorship->internal_file_number)->first();
$dataByRelation = Data::where('file_id_number', $sponsorship->relation_id_number)->first();
$dataByIdentity = Data::where('data_id_number', $sponsorship->identity_number)->first();

echo "By file_id_number ({$sponsorship->internal_file_number}): " . ($dataByFile ? "✅ FOUND" : "❌ NOT FOUND") . "\n";
echo "By file_id_number ({$sponsorship->relation_id_number}): " . ($dataByRelation ? "✅ FOUND" : "❌ NOT FOUND") . "\n";
echo "By data_id_number ({$sponsorship->identity_number}): " . ($dataByIdentity ? "✅ FOUND" : "❌ NOT FOUND") . "\n";

$dataRecord = $dataByFile ?: $dataByRelation ?: $dataByIdentity;
if ($dataRecord) {
    echo "📋 DATA FOUND - Key fields:\n";
    echo "  Phone: {$dataRecord->data_phone_number}\n";
    echo "  City: {$dataRecord->data_city}\n";
    echo "  Housing Status: {$dataRecord->data_housing_status}\n";
    echo "  Employment: {$dataRecord->data_employment_status_breadwinner}\n";
    echo "  Individuals: {$dataRecord->data_number_of_individuals}\n";
}
echo "\n";

// 2. فحص جدول dead_people
echo "=== 2. TABLE: dead_people ===\n";
$deadByRelation = DeadPepole::where('re_file_id', $sponsorship->relation_id_number)->first();
$deadByFile = DeadPepole::where('re_file_id', $sponsorship->internal_file_number)->first();

echo "By re_file_id ({$sponsorship->relation_id_number}): " . ($deadByRelation ? "✅ FOUND" : "❌ NOT FOUND") . "\n";
echo "By re_file_id ({$sponsorship->internal_file_number}): " . ($deadByFile ? "✅ FOUND" : "❌ NOT FOUND") . "\n";

$deadRecord = $deadByRelation ?: $deadByFile;
if ($deadRecord) {
    echo "📋 DEAD_PEOPLE FOUND - Key fields:\n";
    echo "  Mother: {$deadRecord->mother_first_name} {$deadRecord->mother_last_name}\n";
    echo "  Mother ID: {$deadRecord->mother_id}\n";
    echo "  Father: {$deadRecord->father_first_name} {$deadRecord->father_last_name}\n";
    echo "  Father ID: {$deadRecord->father_id}\n";
}
echo "\n";

// 3. فحص جدول re_people
echo "=== 3. TABLE: re_people ===\n";
$rePeopleByFileId = RePeople::where('registration_id', $sponsorship->relation_id_number ?: $sponsorship->internal_file_number)->get();
$rePersonByIdentity = RePeople::where('person_id', $sponsorship->identity_number)->first();

echo "By registration_id (" . ($sponsorship->relation_id_number ?: $sponsorship->internal_file_number) . "): " . $rePeopleByFileId->count() . " records\n";
echo "By person_id ({$sponsorship->identity_number}): " . ($rePersonByIdentity ? "✅ FOUND" : "❌ NOT FOUND") . "\n";

if ($rePeopleByFileId->count() > 0) {
    echo "📋 RE_PEOPLE FAMILY MEMBERS:\n";
    foreach ($rePeopleByFileId as $person) {
        echo "  - {$person->first_name} {$person->last_name} (ID: {$person->person_id})\n";
    }
}

if ($rePersonByIdentity) {
    echo "📋 ORPHAN IN RE_PEOPLE:\n";
    echo "  Name: {$rePersonByIdentity->first_name} {$rePersonByIdentity->last_name}\n";
    echo "  Birth: {$rePersonByIdentity->person_birth_date}\n";
    echo "  Health: {$rePersonByIdentity->person_health_status}\n";
}
echo "\n";

// 4. فحص جدول portal_general_registration_field_values
echo "=== 4. TABLE: portal_general_registration_field_values ===\n";
$portalFields = PortalGeneralRegistrationFieldValue::where('sponsorship_id', $sponsorship->id)
    ->orWhere('file_id_number', $sponsorship->internal_file_number)
    ->orWhere('identity_number', $sponsorship->identity_number)
    ->get();

echo "Total fields: " . $portalFields->count() . "\n";

if ($portalFields->count() > 0) {
    echo "📋 KEY PORTAL FIELDS:\n";
    $keyFields = [
        'field_data_first_name', 'field_data_father_name', 'field_data_family_name',
        'field_data_phone_number', 'field_data_city', 'field_school_name',
        'field_grade', 'field_health_status', 'field_guardian_relationship'
    ];

    foreach ($keyFields as $field) {
        $value = $portalFields->where('field_key', $field)->first()?->field_value ?? '(not found)';
        echo "  {$field}: {$value}\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Data sources found:\n";
echo "- data table: " . ($dataRecord ? "✅" : "❌") . "\n";
echo "- dead_people table: " . ($deadRecord ? "✅" : "❌") . "\n";
echo "- re_people table: " . ($rePeopleByFileId->count() > 0 || $rePersonByIdentity ? "✅" : "❌") . "\n";
echo "- portal fields: " . ($portalFields->count() > 0 ? "✅" : "❌") . "\n";
