<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Data;

echo "=== فحص data_relationship للأم (803315837) ===\n\n";

$guardianIdentity = '803315837';
$data = Data::where('data_id_number', $guardianIdentity)->first();

if ($data) {
    echo "✅ تم العثور على سجل في جدول data:\n";
    echo "  - data_first_name: {$data->data_first_name}\n";
    echo "  - data_father_name: {$data->data_father_name}\n";
    echo "  - data_relationship: " . ($data->data_relationship ?? 'NULL') . "\n";
    echo "  - data_id_number: {$data->data_id_number}\n";
} else {
    echo "❌ لا يوجد سجل في جدول data\n";
}
