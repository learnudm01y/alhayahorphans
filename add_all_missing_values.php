<?php

/**
 * FIX SCRIPT: Add ALL Missing Lookup Values
 *
 * This script adds ALL missing IDs (1-10) to lookup tables
 * to ensure Excel imports work correctly.
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "ADD MISSING LOOKUP VALUES\n";
echo "========================================\n\n";

// تعريف القيم المطلوبة لكل جدول
$lookupTables = [
    'marital_status' => [
        'id_column' => 'id',
        'name_column' => 'marital_status_name',
        'values' => [
            ['id' => 1, 'name' => 'أعزب / عزباء'],
            ['id' => 2, 'name' => 'متزوج / متزوجة'],
            ['id' => 3, 'name' => 'مطلق / مطلقة'],
            ['id' => 4, 'name' => 'أرمل / أرملة'],
        ]
    ],
    'academic_degrees' => [
        'id_column' => 'id',
        'name_column' => 'academic_degree_name',
        'values' => [
            ['id' => 1, 'name' => 'أمي / لا يقرأ ولا يكتب'],
            ['id' => 2, 'name' => 'ابتدائية'],
            ['id' => 3, 'name' => 'متوسطة'],
            ['id' => 4, 'name' => 'إعدادية'],
            ['id' => 5, 'name' => 'دبلوم'],
            ['id' => 6, 'name' => 'بكالوريوس'],
            ['id' => 7, 'name' => 'ماجستير'],
            ['id' => 8, 'name' => 'دكتوراه'],
        ]
    ],
    'displacement_statuses' => [
        'id_column' => 'id',
        'name_column' => 'displacement_status_name',
        'values' => [
            ['id' => 1, 'name' => 'نازح'],
            ['id' => 2, 'name' => 'غير نازح'],
            ['id' => 3, 'name' => 'عائد'],
        ]
    ],
    'employment' => [
        'id_column' => 'id',
        'name_column' => 'employment_name',
        'values' => [
            ['id' => 1, 'name' => 'موظف'],
            ['id' => 2, 'name' => 'عاطل عن العمل'],
            ['id' => 3, 'name' => 'متقاعد'],
        ]
    ],
    'housing_status' => [
        'id_column' => 'id',
        'name_column' => 'housing_status_name',
        'values' => [
            ['id' => 1, 'name' => 'ملك'],
            ['id' => 2, 'name' => 'إيجار'],
            ['id' => 3, 'name' => 'مع العائلة'],
        ]
    ],
    'type_of_accommodation' => [
        'id_column' => 'id',
        'name_column' => 'type_of_accommodation_name',
        'values' => [
            ['id' => 1, 'name' => 'منزل مستقل'],
            ['id' => 2, 'name' => 'شقة'],
            ['id' => 3, 'name' => 'مخيم'],
            ['id' => 4, 'name' => 'كرفان'],
            ['id' => 5, 'name' => 'أخرى'],
        ]
    ],
    'provinces' => [
        'id_column' => 'id',
        'name_column' => 'province_name',
        'values' => [
            ['id' => 1, 'name' => 'بغداد'],
            ['id' => 2, 'name' => 'البصرة'],
            ['id' => 3, 'name' => 'نينوى'],
            ['id' => 4, 'name' => 'الأنبار'],
            ['id' => 5, 'name' => 'كربلاء'],
            ['id' => 6, 'name' => 'النجف'],
            ['id' => 7, 'name' => 'ذي قار'],
            ['id' => 8, 'name' => 'بابل'],
            ['id' => 9, 'name' => 'ديالى'],
            ['id' => 10, 'name' => 'صلاح الدين'],
        ]
    ],
    'city' => [
        'id_column' => 'id',
        'name_column' => 'city_name',
        'values' => [
            ['id' => 52, 'name' => 'مدينة 52'],
            ['id' => 54, 'name' => 'مدينة 54'],
        ]
    ],
];

DB::beginTransaction();

try {
    $totalAdded = 0;

    foreach ($lookupTables as $tableName => $config) {
        echo "Processing table: {$tableName}\n";
        echo str_repeat('-', 50) . "\n";

        $idColumn = $config['id_column'];
        $nameColumn = $config['name_column'];

        foreach ($config['values'] as $value) {
            $id = $value['id'];
            $name = $value['name'];

            // Check if exists
            $exists = DB::table($tableName)->where($idColumn, $id)->exists();

            if (!$exists) {
                // Insert with NO_AUTO_VALUE_ON_ZERO mode
                DB::statement("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");

                DB::table($tableName)->insert([
                    $idColumn => $id,
                    $nameColumn => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                echo "  ✓ Added ID {$id}: {$name}\n";
                $totalAdded++;
            } else {
                echo "  - ID {$id} already exists\n";
            }
        }
        echo "\n";
    }

    DB::commit();

    echo "========================================\n";
    echo "SUCCESS!\n";
    echo "========================================\n\n";
    echo "Total values added: {$totalAdded}\n";
    echo "All missing lookup values have been added.\n\n";
    echo "✓ Now you can import Excel files successfully!\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Fix failed. Please check the error and try again.\n\n";
}
