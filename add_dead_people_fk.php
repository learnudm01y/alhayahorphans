<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "═══════════════════════════════════════════════════════════════\n";
echo "📋 تعديل جدول guardian_bank_accounts لدعم dead_people\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    // 1. التحقق من وجود عمود dead_people_registration
    $columns = Schema::getColumnListing('guardian_bank_accounts');

    if (!in_array('dead_people_registration', $columns)) {
        echo "📋 الخطوة 1: إضافة عمود dead_people_registration\n";

        DB::statement("ALTER TABLE guardian_bank_accounts ADD COLUMN dead_people_registration VARCHAR(50) NULL AFTER guardian_registration");

        echo "   ✅ تم إضافة العمود dead_people_registration\n";
    } else {
        echo "   ⚠️ العمود dead_people_registration موجود مسبقاً\n";
    }

    // 2. إضافة FK للعمود الجديد
    echo "\n📋 الخطوة 2: إضافة Foreign Key لـ dead_people\n";

    // التحقق من وجود الـ FK
    $existingFK = DB::select("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'guardian_bank_accounts'
        AND COLUMN_NAME = 'dead_people_registration'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    if (empty($existingFK)) {
        DB::statement("
            ALTER TABLE guardian_bank_accounts
            ADD CONSTRAINT guardian_bank_accounts_dead_people_registration_foreign
            FOREIGN KEY (dead_people_registration)
            REFERENCES dead_people(re_file_id)
            ON DELETE CASCADE
        ");

        echo "   ✅ تم إضافة FK: dead_people_registration → dead_people.re_file_id\n";
    } else {
        echo "   ⚠️ الـ FK موجود مسبقاً\n";
    }

    // 3. تعديل guardian_registration ليكون nullable
    echo "\n📋 الخطوة 3: تعديل guardian_registration ليكون NULL\n";

    // حذف الـ FK القديم أولاً
    try {
        DB::statement("ALTER TABLE guardian_bank_accounts DROP FOREIGN KEY guardian_bank_accounts_guardian_registration_foreign");
        echo "   ✅ تم حذف الـ FK القديم\n";
    } catch (\Exception $e) {
        echo "   ⚠️ الـ FK غير موجود أو تم حذفه مسبقاً\n";
    }

    // تعديل العمود
    DB::statement("ALTER TABLE guardian_bank_accounts MODIFY guardian_registration VARCHAR(50) NULL");
    echo "   ✅ تم تعديل guardian_registration ليكون NULL\n";

    // إعادة إضافة الـ FK
    try {
        DB::statement("
            ALTER TABLE guardian_bank_accounts
            ADD CONSTRAINT guardian_bank_accounts_guardian_registration_foreign
            FOREIGN KEY (guardian_registration)
            REFERENCES data(file_id_number)
            ON DELETE CASCADE
        ");
        echo "   ✅ تم إعادة إضافة FK: guardian_registration → data.file_id_number\n";
    } catch (\Exception $e) {
        echo "   ⚠️ لم يتم إضافة الـ FK: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("═", 60) . "\n";
    echo "✅ تم التعديل بنجاح!\n";
    echo str_repeat("═", 60) . "\n";

    // عرض الهيكل الجديد
    echo "\n📌 الأعمدة بعد التعديل:\n";
    $columns = DB::select("DESCRIBE guardian_bank_accounts");
    foreach ($columns as $col) {
        if (in_array($col->Field, ['guardian_registration', 'dead_people_registration'])) {
            echo "   ✅ {$col->Field} | {$col->Type} | " . ($col->Null == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    }

    echo "\n📌 Foreign Keys بعد التعديل:\n";
    $constraints = DB::select("
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'guardian_bank_accounts'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    foreach ($constraints as $c) {
        echo "   ✅ {$c->COLUMN_NAME} → {$c->REFERENCED_TABLE_NAME}.{$c->REFERENCED_COLUMN_NAME}\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
