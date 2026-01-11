<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص أعمدة الجنس في الجداول المختلفة ===\n\n";

// 1. جدول data
echo "1. جدول data:\n";
$cols = DB::select("SHOW COLUMNS FROM data LIKE '%gender%'");
foreach($cols as $c) {
    echo "   - " . $c->Field . " (" . $c->Type . ")\n";
}
$sample = DB::table('data')->whereNotNull('data_gender')->first();
if ($sample) {
    echo "   قيمة عينة: " . $sample->data_gender . "\n";
}

// 2. جدول re_people
echo "\n2. جدول re_people:\n";
try {
    $cols = DB::select("SHOW COLUMNS FROM re_people LIKE '%gender%'");
    foreach($cols as $c) {
        echo "   - " . $c->Field . " (" . $c->Type . ")\n";
    }
    $sample = DB::table('re_people')->whereNotNull('gender')->first();
    if ($sample) {
        echo "   قيمة عينة: " . $sample->gender . "\n";
    }
} catch (\Exception $e) {
    echo "   خطأ: " . $e->getMessage() . "\n";
}

// 3. جدول dead_people
echo "\n3. جدول dead_people:\n";
try {
    $cols = DB::select("SHOW COLUMNS FROM dead_people LIKE '%gender%'");
    foreach($cols as $c) {
        echo "   - " . $c->Field . " (" . $c->Type . ")\n";
    }
    $sample = DB::table('dead_people')->whereNotNull('gender')->first();
    if ($sample) {
        echo "   قيمة عينة: " . $sample->gender . "\n";
    }
} catch (\Exception $e) {
    echo "   خطأ: " . $e->getMessage() . "\n";
}

// 4. جدول persons (civilregistry)
echo "\n4. جدول persons (civilregistry):\n";
try {
    $cols = DB::connection('civilregistry')->select("SHOW COLUMNS FROM persons LIKE '%SEX%'");
    foreach($cols as $c) {
        echo "   - " . $c->Field . " (" . $c->Type . ")\n";
    }
    $cols2 = DB::connection('civilregistry')->select("SHOW COLUMNS FROM persons LIKE '%GENDER%'");
    foreach($cols2 as $c) {
        echo "   - " . $c->Field . " (" . $c->Type . ")\n";
    }
    $sample = DB::connection('civilregistry')->table('persons')->first();
    if ($sample) {
        $arr = (array)$sample;
        foreach ($arr as $key => $value) {
            if (stripos($key, 'sex') !== false || stripos($key, 'gender') !== false) {
                echo "   $key = $value\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "   خطأ: " . $e->getMessage() . "\n";
}

// 5. فحص كيفية جلب الجنس حالياً في الكفالات
echo "\n\n=== فحص الجنس في الكفالات ===\n";
$sponsorship = DB::table('sponsorships')
    ->whereNotNull('identity_number')
    ->first();

if ($sponsorship) {
    echo "الكفالة ID: " . $sponsorship->id . "\n";
    echo "رقم الهوية: " . $sponsorship->identity_number . "\n";

    // البحث في data
    $data = DB::table('data')
        ->where('data_id_number', $sponsorship->identity_number)
        ->first();
    if ($data) {
        echo "من data: الجنس = " . ($data->data_gender ?? 'غير موجود') . "\n";
    } else {
        echo "لم يتم العثور عليه في data\n";
    }

    // البحث في re_people
    try {
        $rePerson = DB::table('re_people')
            ->where('identity_number', $sponsorship->identity_number)
            ->first();
        if ($rePerson) {
            $arr = (array)$rePerson;
            foreach ($arr as $key => $value) {
                if (stripos($key, 'gender') !== false || stripos($key, 'sex') !== false) {
                    echo "من re_people: $key = $value\n";
                }
            }
        }
    } catch (\Exception $e) {
        echo "re_people: " . $e->getMessage() . "\n";
    }

    // البحث في persons
    try {
        $person = DB::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', $sponsorship->identity_number)
            ->first();
        if ($person) {
            $arr = (array)$person;
            foreach ($arr as $key => $value) {
                if (stripos($key, 'gender') !== false || stripos($key, 'sex') !== false) {
                    echo "من persons: $key = $value\n";
                }
            }
        }
    } catch (\Exception $e) {
        echo "persons: " . $e->getMessage() . "\n";
    }
}
