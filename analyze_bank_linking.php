<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تحليل الكفالات حسب نوع الشخص ===" . PHP_EOL . PHP_EOL;

// جلب كل أنواع الكفالات
$sponsorships = DB::table('sponsorships')
    ->whereNotNull('person_type')
    ->get();

foreach ($sponsorships as $sp) {
    echo "========================================" . PHP_EOL;
    echo "Sponsorship ID: {$sp->id}" . PHP_EOL;
    echo "Person Type: {$sp->person_type}" . PHP_EOL;
    echo "Identity Number: {$sp->identity_number}" . PHP_EOL;
    echo "Guardian Identity: {$sp->guardian_identity_number}" . PHP_EOL;
    echo "Relation ID: {$sp->relation_id_number}" . PHP_EOL;

    // البحث عن file_id_number
    $data = DB::table('data')
        ->where('data_id_number', $sp->identity_number)
        ->orWhere('file_id_number', $sp->relation_id_number)
        ->first();

    if ($data) {
        echo "Found in DATA - file_id_number: {$data->file_id_number}" . PHP_EOL;
    }

    // البحث في guardian_bank_accounts
    if ($data) {
        $bank = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $data->file_id_number)
            ->first();

        if ($bank) {
            echo "Bank Account Found - ID: {$bank->id}, check_account: {$bank->check_account}" . PHP_EOL;
        } else {
            echo "NO Bank Account for file_id_number: {$data->file_id_number}" . PHP_EOL;
        }
    }

    // للمتوفين - البحث في dead_people
    if (in_array($sp->person_type, ['deceased_father', 'deceased_mother'])) {
        $dead = DB::table('dead_people')
            ->where('father_id', $sp->identity_number)
            ->orWhere('mother_id', $sp->identity_number)
            ->first();

        if ($dead) {
            echo "Found in DEAD_PEOPLE - re_file_id: {$dead->re_file_id}" . PHP_EOL;

            // البحث عن الحساب البنكي باستخدام re_file_id
            $bank = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $dead->re_file_id)
                ->first();

            if ($bank) {
                echo "Bank Account via dead_people - ID: {$bank->id}" . PHP_EOL;
            } else {
                echo "NO Bank Account via dead_people" . PHP_EOL;
            }
        }
    }

    echo PHP_EOL;
}
