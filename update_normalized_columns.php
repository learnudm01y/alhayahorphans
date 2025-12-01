<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "تحديث الأعمدة المطبّعة لإزالة المسافات...\n\n";

try {
    echo "1. تحديث CI_FIRST_ARB_NORMALIZED... ";
    DB::connection('civilregistry')->statement("
        ALTER TABLE persons
        MODIFY COLUMN CI_FIRST_ARB_NORMALIZED VARCHAR(255)
        GENERATED ALWAYS AS (
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                CI_FIRST_ARB,
                'أ', 'ا'),
                'إ', 'ا'),
                'آ', 'ا'),
                'ى', 'ي'),
                'ة', 'ه'),
                ' ', '')
        ) STORED
    ");
    echo "✅\n";
} catch (\Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

try {
    echo "2. تحديث CI_FATHER_ARB_NORMALIZED... ";
    DB::connection('civilregistry')->statement("
        ALTER TABLE persons
        MODIFY COLUMN CI_FATHER_ARB_NORMALIZED VARCHAR(255)
        GENERATED ALWAYS AS (
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                CI_FATHER_ARB,
                'أ', 'ا'),
                'إ', 'ا'),
                'آ', 'ا'),
                'ى', 'ي'),
                'ة', 'ه'),
                ' ', '')
        ) STORED
    ");
    echo "✅\n";
} catch (\Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

try {
    echo "3. تحديث CI_GRAND_FATHER_ARB_NORMALIZED... ";
    DB::connection('civilregistry')->statement("
        ALTER TABLE persons
        MODIFY COLUMN CI_GRAND_FATHER_ARB_NORMALIZED VARCHAR(255)
        GENERATED ALWAYS AS (
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                CI_GRAND_FATHER_ARB,
                'أ', 'ا'),
                'إ', 'ا'),
                'آ', 'ا'),
                'ى', 'ي'),
                'ة', 'ه'),
                ' ', '')
        ) STORED
    ");
    echo "✅\n";
} catch (\Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

try {
    echo "4. تحديث CI_FAMILY_ARB_NORMALIZED... ";
    DB::connection('civilregistry')->statement("
        ALTER TABLE persons
        MODIFY COLUMN CI_FAMILY_ARB_NORMALIZED VARCHAR(255)
        GENERATED ALWAYS AS (
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                CI_FAMILY_ARB,
                'أ', 'ا'),
                'إ', 'ا'),
                'آ', 'ا'),
                'ى', 'ي'),
                'ة', 'ه'),
                ' ', '')
        ) STORED
    ");
    echo "✅\n";
} catch (\Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

echo "\n✅ تم الانتهاء من تحديث الأعمدة!\n";
echo "الآن الأعمدة المطبّعة تزيل المسافات تلقائياً.\n";
