<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "=== هيكل جدول sponsorships ===" . PHP_EOL;
$cols = Schema::getColumnListing('sponsorships');
foreach ($cols as $c) {
    echo "  - {$c}" . PHP_EOL;
}

echo PHP_EOL . "=== هيكل جدول data (أهم الأعمدة) ===" . PHP_EOL;
$dataCols = ['id', 'file_id_number', 'data_id_number', 'data_first_name', 'data_father_name',
             'data_grand_father_name', 'data_family_name', 'data_birth_date', 'data_phone_number',
             'data_province', 'data_city', 'data_address', 'data_health_status'];
foreach ($dataCols as $c) {
    if (Schema::hasColumn('data', $c)) {
        echo "  ✅ {$c}" . PHP_EOL;
    } else {
        echo "  ❌ {$c}" . PHP_EOL;
    }
}

echo PHP_EOL . "=== هيكل جدول re_people (أهم الأعمدة) ===" . PHP_EOL;
$reCols = ['id', 'registration_id', 'person_id', 'first_name', 'second_name', 'third_name',
           'last_name', 'person_birth_date', 'person_gender', 'person_age', 'person_note'];
foreach ($reCols as $c) {
    if (Schema::hasColumn('re_people', $c)) {
        echo "  ✅ {$c}" . PHP_EOL;
    } else {
        echo "  ❌ {$c}" . PHP_EOL;
    }
}

echo PHP_EOL . "=== هيكل جدول dead_people (أهم الأعمدة) ===" . PHP_EOL;
$deadCols = ['id', 're_file_id', 'father_first_name', 'father_second_name', 'father_third_name',
             'father_last_name', 'father_id', 'father_death_date', 'father_death_reason',
             'mother_first_name', 'mother_id', 'mother_death_date', 'mother_death_reason'];
foreach ($deadCols as $c) {
    if (Schema::hasColumn('dead_people', $c)) {
        echo "  ✅ {$c}" . PHP_EOL;
    } else {
        echo "  ❌ {$c}" . PHP_EOL;
    }
}
