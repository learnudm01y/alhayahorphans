<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           فحص وإضافة عمود تاريخ ميلاد المكفول\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. فحص الأعمدة الموجودة
echo "1️⃣  أعمدة جدول sponsorships الحالية:\n";
$columns = DB::select('SHOW COLUMNS FROM sponsorships');
foreach ($columns as $col) {
    echo "   - {$col->Field}\n";
}

// 2. التحقق من وجود عمود sponsored_birth_date
$hasColumn = Schema::hasColumn('sponsorships', 'sponsored_birth_date');
echo "\n2️⃣  عمود sponsored_birth_date: " . ($hasColumn ? 'موجود ✓' : 'غير موجود ✗') . "\n";

// 3. إضافة العمود إذا لم يكن موجودًا
if (!$hasColumn) {
    echo "\n3️⃣  إضافة عمود sponsored_birth_date...\n";
    try {
        DB::statement('ALTER TABLE sponsorships ADD COLUMN sponsored_birth_date DATE NULL AFTER orphan_name');
        echo "   ✓ تم إضافة العمود بنجاح!\n";
    } catch (Exception $e) {
        echo "   ✗ خطأ: " . $e->getMessage() . "\n";
    }
}

// 4. التحقق مرة أخرى
$hasColumn = Schema::hasColumn('sponsorships', 'sponsored_birth_date');
echo "\n4️⃣  التحقق النهائي: " . ($hasColumn ? '✓ العمود موجود' : '✗ العمود غير موجود') . "\n";

echo "\n";
