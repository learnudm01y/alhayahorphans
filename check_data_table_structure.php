<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           فحص بنية جدول data والعلاقات\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. فحص أعمدة جدول data
echo "1️⃣  أعمدة جدول data:\n";
$columns = DB::select('SHOW COLUMNS FROM data');
foreach ($columns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}

// 2. فحص البيانات الموجودة في جدول data
echo "\n2️⃣  عينة من البيانات في جدول data:\n";
$samples = DB::table('data')->limit(3)->get();
foreach ($samples as $sample) {
    echo "   ────────────────────────────────\n";
    foreach ((array)$sample as $key => $value) {
        if ($value !== null && $value !== '') {
            echo "   - $key: $value\n";
        }
    }
}

// 3. فحص موديل Sponsorship لمعرفة العلاقات
echo "\n3️⃣  فحص علاقات موديل Sponsorship:\n";
$sponsorshipModelPath = 'app/Models/Sponsorship.php';
if (file_exists($sponsorshipModelPath)) {
    $content = file_get_contents($sponsorshipModelPath);

    // البحث عن علاقة relationData
    if (preg_match('/function\s+relationData[^{]*{[^}]+}/s', $content, $matches)) {
        echo "   علاقة relationData:\n";
        echo "   " . trim($matches[0]) . "\n";
    }

    // البحث عن جميع العلاقات
    if (preg_match_all('/function\s+(\w+)\s*\([^)]*\)\s*(?::\s*\w+)?\s*{\s*return\s+\$this->(belongsTo|hasOne|hasMany|belongsToMany)/s', $content, $matches, PREG_SET_ORDER)) {
        echo "\n   جميع العلاقات:\n";
        foreach ($matches as $match) {
            echo "   - {$match[1]}() : {$match[2]}\n";
        }
    }
}

// 4. البحث عن الربط بين data و sponsorship
echo "\n4️⃣  محاولة فهم العلاقة:\n";
$sponsorship = DB::table('sponsorships')->where('identity_number', '42979087')->first();
if ($sponsorship) {
    echo "   الكفالة identity_number: {$sponsorship->identity_number}\n";
    echo "   الكفالة guardian_identity_number: " . ($sponsorship->guardian_identity_number ?? 'فارغ') . "\n";

    // محاولة البحث في data بطرق مختلفة
    echo "\n   محاولات البحث في data:\n";

    // بواسطة data_id_number = guardian_identity_number
    if (!empty($sponsorship->guardian_identity_number)) {
        $dataByGuardian = DB::table('data')
            ->where('data_id_number', $sponsorship->guardian_identity_number)
            ->first();
        echo "   - بـ data_id_number = guardian_identity_number: " . ($dataByGuardian ? 'وُجد' : 'لم يوجد') . "\n";
    }

    // بواسطة file_id_number = identity_number
    $dataByFile = DB::table('data')
        ->where('file_id_number', $sponsorship->identity_number)
        ->first();
    echo "   - بـ file_id_number = identity_number: " . ($dataByFile ? 'وُجد' : 'لم يوجد') . "\n";

    // إذا وجدنا بيانات
    if ($dataByFile) {
        echo "\n   📋 بيانات المعيل المرتبطة:\n";
        echo "   - data_id_number: " . ($dataByFile->data_id_number ?? 'فارغ') . "\n";
        echo "   - data_first_name: " . ($dataByFile->data_first_name ?? 'فارغ') . "\n";
        echo "   - data_father_name: " . ($dataByFile->data_father_name ?? 'فارغ') . "\n";
        echo "   - data_grand_father_name: " . ($dataByFile->data_grand_father_name ?? 'فارغ') . "\n";
        echo "   - data_family_name: " . ($dataByFile->data_family_name ?? 'فارغ') . "\n";
    }
}

echo "\n";
