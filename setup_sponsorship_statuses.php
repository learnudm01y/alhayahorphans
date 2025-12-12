<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== جميع حالات الكفالة ===\n\n";

$statuses = DB::table('sponsorship_statuses')->select('id', 'description')->get();

foreach ($statuses as $status) {
    echo "ID: {$status->id} - {$status->description}\n";
}

echo "\n=== إنشاء حالة 'جديد' إذا لم تكن موجودة ===\n";

$newStatus = DB::table('sponsorship_statuses')->where('description', 'جديد')->first();

if (!$newStatus) {
    $maxId = DB::table('sponsorship_statuses')->max('id');
    $newId = $maxId + 1;

    DB::table('sponsorship_statuses')->insert([
        'id' => $newId,
        'description' => 'جديد',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    echo "✅ تم إنشاء حالة 'جديد' بـ ID: {$newId}\n";
} else {
    echo "✅ حالة 'جديد' موجودة بالفعل - ID: {$newStatus->id}\n";
}

// Create "ذهب للصرف" status if it doesn't exist
echo "\n=== إنشاء حالة 'ذهب للصرف' إذا لم تكن موجودة ===\n";

$disbursementStatus = DB::table('sponsorship_statuses')
    ->where('description', 'LIKE', '%ذهب%للصرف%')
    ->orWhere('description', 'LIKE', '%صرف%')
    ->first();

if (!$disbursementStatus) {
    $maxId = DB::table('sponsorship_statuses')->max('id');
    $newId = $maxId + 1;

    DB::table('sponsorship_statuses')->insert([
        'id' => $newId,
        'description' => 'ذهب للصرف',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    echo "✅ تم إنشاء حالة 'ذهب للصرف' بـ ID: {$newId}\n";
} else {
    echo "✅ حالة الصرف موجودة: ID {$disbursementStatus->id} - {$disbursementStatus->description}\n";
}

echo "\n=== الحالات النهائية ===\n";
$finalStatuses = DB::table('sponsorship_statuses')->select('id', 'description')->orderBy('id')->get();
foreach ($finalStatuses as $status) {
    echo "ID: {$status->id} - {$status->description}\n";
}
