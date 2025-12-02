<?php
// اختبار DataTable بشكل مباشر
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sponsor;
use Illuminate\Support\Facades\Log;

echo "=== اختبار DataTable ===\n\n";

// 1. عدد السجلات
$count = Sponsor::count();
echo "📊 عدد الجمعيات: {$count}\n\n";

// 2. استرجاع البيانات بنفس طريقة DataTable
$sponsors = Sponsor::with('bankName')
    ->orderBy('created_at', 'desc')
    ->get();

echo "📋 البيانات:\n";
foreach ($sponsors as $sponsor) {
    echo "  - ID: {$sponsor->id}\n";
    echo "    file_id: {$sponsor->file_id}\n";
    echo "    الاسم: {$sponsor->sponsor_name}\n";
    echo "    البنك: " . ($sponsor->bankName ? $sponsor->bankName->description : '-') . "\n";
    echo "    created_at: {$sponsor->created_at}\n\n";
}

// 3. محاكاة استجابة DataTable
echo "🔍 محاكاة استجابة DataTable:\n";
$response = [
    'draw' => 1,
    'recordsTotal' => $count,
    'recordsFiltered' => $count,
    'data' => $sponsors->map(function($sponsor) {
        return [
            'id' => $sponsor->id,
            'sponsor_name' => $sponsor->sponsor_name,
            'sponsor_short_name' => $sponsor->sponsor_short_name ?? '-',
            'sponsor_phone_number' => $sponsor->sponsor_phone_number ?? '-',
            'sponsor_email' => $sponsor->sponsor_email ?? '-',
            'bank_name' => $sponsor->bankName ? $sponsor->bankName->description : '-',
            'sponsor_account_bank_number' => $sponsor->sponsor_account_bank_number ?? '-',
            'created_at' => $sponsor->created_at->format('Y-m-d H:i:s'),
            'actions' => '<!-- HTML buttons -->'
        ];
    })->toArray()
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "\n\n";

// 4. التحقق من العلاقة
echo "🔗 التحقق من علاقة البنك:\n";
$sponsor = Sponsor::first();
if ($sponsor) {
    echo "  - ID: {$sponsor->id}\n";
    echo "  - sponsor_bank_name_id: " . ($sponsor->sponsor_bank_name_id ?? 'NULL') . "\n";
    echo "  - البنك: " . ($sponsor->bankName ? $sponsor->bankName->description : 'لا يوجد') . "\n";
}

echo "\n✨ الاختبار اكتمل!\n";
