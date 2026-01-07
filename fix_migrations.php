<?php

// سكريبت لإضافة migrations المعلقة إلى جدول migrations

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pendingMigrations = [
    '2014_10_11_000003_create_city_table',
    '2014_10_12_000000_create_users_table',
    '2014_10_12_000001_create_persons_table',
    '2024_06_08_000000_create_general_categories_table',
    '2024_12_01_000001_add_indexes_for_search_optimization',
    '2025_05_01_000001_add_search_indexes_to_persons_table',
    '2025_05_01_000002_add_fast_indexes_to_persons',
];

$batch = \DB::table('migrations')->max('batch') + 1;

foreach ($pendingMigrations as $migration) {
    // تحقق من عدم وجود الـ migration
    $exists = \DB::table('migrations')->where('migration', $migration)->exists();

    if (!$exists) {
        \DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => $batch
        ]);
        echo "✅ تم إضافة: {$migration}\n";
    } else {
        echo "⏭️ موجود مسبقاً: {$migration}\n";
    }
}

echo "\n✅ تم الانتهاء!\n";
