<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$s = DB::table('sponsorships')->where('identity_number', '42979087')->first();

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    بيانات تسجيل الدخول                                        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

if ($s) {
    echo "📋 بيانات الكفالة:\n";
    echo "   - ID: {$s->id}\n";
    echo "   - رقم هوية المكفول: {$s->identity_number}\n";
    echo "   - اسم المكفول: {$s->orphan_name}\n";
    echo "   - رقم الملف الداخلي: " . ($s->internal_file_number ?? 'NULL') . "\n";
    echo "   - رقم هوية المعيل: " . ($s->guardian_identity_number ?? 'NULL') . "\n";
    echo "   - اسم المعيل: " . ($s->guardian_name ?? 'NULL') . "\n\n";

    // إذا لم يكن هناك رقم ملف داخلي، نضيفه
    if (empty($s->internal_file_number)) {
        $internalFileNumber = '12345';
        DB::table('sponsorships')
            ->where('id', $s->id)
            ->update(['internal_file_number' => $internalFileNumber]);
        echo "⚠️ تم إضافة رقم ملف داخلي: {$internalFileNumber}\n\n";
    } else {
        $internalFileNumber = $s->internal_file_number;
    }

    echo "═══════════════════════════════════════════════════════════════════════════════\n";
    echo "                      بيانات تسجيل الدخول                                       \n";
    echo "═══════════════════════════════════════════════════════════════════════════════\n\n";
    echo "📌 صفحة تسجيل الدخول:\n";
    echo "   http://127.0.0.1:8000/users/generalRegistration/login\n\n";
    echo "📌 بيانات الدخول:\n";
    echo "   ┌─────────────────────────────────────────────────────┐\n";
    echo "   │  رقم هوية المكفول: {$s->identity_number}                      │\n";
    echo "   │  رقم الملف الداخلي: {$internalFileNumber}                           │\n";
    echo "   └─────────────────────────────────────────────────────┘\n";
} else {
    echo "❌ لا يوجد سجل كفالة برقم الهوية 42979087\n";
}
