<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// إعادة الكفالة لحالتها الأصلية (غير مربوطة)
DB::table('sponsorships')
    ->where('internal_file_number', '006999')
    ->update([
        'relation_id_number' => null,
        'guardian_name' => 'محمد أحمد علي السعيد',
        'guardian_identity_number' => null,
        'orphan_name' => 'خالد أحمد عبدالله المصري',
        'identity_number' => null
    ]);

// حذف السجلات التي أنشأناها
DB::table('guardian_bank_accounts')->where('guardian_registration', '053452')->delete();
DB::table('re_people')->where('registration_id', '053452')->delete();
DB::table('data')->where('file_id_number', '053452')->delete();

echo "✅ تم إعادة الكفالة 006999 لحالتها الأصلية\n";
