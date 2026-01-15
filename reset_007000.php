<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// فك ربط الكفالة 007000 لاختبار السيناريو 3ب
DB::table('sponsorships')
    ->where('internal_file_number', '007000')
    ->update([
        'relation_id_number' => null,
        'orphan_name' => 'اختبار breadwinner',
        'identity_number' => null,
        'guardian_name' => null,
        'guardian_identity_number' => null,
        'person_type' => 'breadwinner'
    ]);

// حذف السجلات المرتبطة بـ 053453 إن وجدت
DB::table('guardian_bank_accounts')->where('guardian_registration', '053453')->delete();
DB::table('data')->where('file_id_number', '053453')->delete();

echo "✅ تم فك ربط الكفالة 007000 وإعدادها للاختبار\n";

$s = DB::table('sponsorships')->where('internal_file_number', '007000')->first();
echo "   - relation_id_number: " . ($s->relation_id_number ?? 'NULL') . "\n";
echo "   - person_type: {$s->person_type}\n";
