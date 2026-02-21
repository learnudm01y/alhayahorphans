<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص جداول employment و health_statuses ===\n\n";

// فحص جدول employment
echo "📋 جدول employment:\n";
echo str_repeat('-', 80) . "\n";
$employmentStatuses = DB::table('employment')->get();
foreach ($employmentStatuses as $emp) {
    echo "ID: {$emp->id} - {$emp->description}\n";
}

echo "\n\n";

// فحص جدول health_statuses
echo "📋 جدول health_statuses:\n";
echo str_repeat('-', 80) . "\n";
$healthStatuses = DB::table('health_statuses')->get();
foreach ($healthStatuses as $health) {
    echo "ID: {$health->id} - {$health->description}\n";
}

echo "\n\n";

// اختبار getEmploymentStatus(1)
echo "🧪 اختبار getEmploymentStatus(1):\n";
$result = DB::table('employment')->where('id', 1)->value('description');
echo "النتيجة: " . ($result ?? 'NULL') . "\n";
