<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== فحص أعمدة جدول data ===\n";
$dataColumns = DB::select('DESCRIBE data');
foreach ($dataColumns as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}

echo "\n=== فحص أعمدة جدول dead_people ===\n";
$deadColumns = DB::select('DESCRIBE dead_people');
foreach ($deadColumns as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}

echo "\n=== فحص أعمدة جدول sponsorships ===\n";
$sponsorshipsColumns = DB::select('DESCRIBE sponsorships');
foreach ($sponsorshipsColumns as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}

echo "\n=== عينة من جدول data ===\n";
$dataSample = DB::table('data')->limit(2)->get();
foreach ($dataSample as $record) {
    echo "\nfile_id_number: {$record->file_id_number}\n";
    echo "data_id_number: {$record->data_id_number}\n";
    echo "data_first_name: " . ($record->data_first_name ?? 'NULL') . "\n";
    echo "data_father_name: " . ($record->data_father_name ?? 'NULL') . "\n";
    echo "data_grandfather_name: " . ($record->data_grandfather_name ?? 'NULL') . "\n";
    echo "data_family_name: " . ($record->data_family_name ?? 'NULL') . "\n";
    echo "data_phone_number: " . ($record->data_phone_number ?? 'NULL') . "\n";
    echo "data_employment_status_breadwinner: " . ($record->data_employment_status_breadwinner ?? 'NULL') . "\n";
    echo "data_relationship: " . ($record->data_relationship ?? 'NULL') . "\n";
    echo "data_number_of_individuals: " . ($record->data_number_of_individuals ?? 'NULL') . "\n";
    echo "data_number_female: " . ($record->data_number_female ?? 'NULL') . "\n";
    echo "data_number_mail: " . ($record->data_number_mail ?? 'NULL') . "\n";
}

echo "\n\n=== عينة من جدول dead_people ===\n";
$deadSample = DB::table('dead_people')->limit(2)->get();
foreach ($deadSample as $record) {
    echo "\nre_file_id: {$record->re_file_id}\n";
    echo "dead_person_id_number: " . ($record->dead_person_id_number ?? 'NULL') . "\n";
    echo "dead_person_first_name: " . ($record->dead_person_first_name ?? 'NULL') . "\n";
    echo "dead_person_father_name: " . ($record->dead_person_father_name ?? 'NULL') . "\n";
    echo "dead_person_grand_father_name: " . ($record->dead_person_grand_father_name ?? 'NULL') . "\n";
    echo "dead_person_family_name: " . ($record->dead_person_family_name ?? 'NULL') . "\n";
    echo "relationship_id: " . ($record->relationship_id ?? 'NULL') . "\n";
    echo "mother_first_name: " . ($record->mother_first_name ?? 'NULL') . "\n";
    echo "mother_id: " . ($record->mother_id ?? 'NULL') . "\n";
}

echo "\n\n=== عينة من جدول sponsorships ===\n";
$sponsorshipsSample = DB::table('sponsorships')->limit(2)->get();
foreach ($sponsorshipsSample as $record) {
    echo "\nid: {$record->id}\n";
    echo "internal_file_number: {$record->internal_file_number}\n";
    echo "relation_id_number: " . ($record->relation_id_number ?? 'NULL') . "\n";
    echo "identity_number: " . ($record->identity_number ?? 'NULL') . "\n";
    echo "orphan_name: {$record->orphan_name}\n";
    echo "guardian_name: " . ($record->guardian_name ?? 'NULL') . "\n";
    echo "guardian_identity_number: " . ($record->guardian_identity_number ?? 'NULL') . "\n";
    echo "sponsorship_status_id: {$record->sponsorship_status_id}\n";
}
