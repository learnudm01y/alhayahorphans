<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "📋 كسر قيد FK على guardian_registration\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    // حذف الـ FK
    DB::statement("ALTER TABLE guardian_bank_accounts DROP FOREIGN KEY guardian_bank_accounts_guardian_registration_foreign");
    echo "✅ تم حذف قيد FK بنجاح!\n";
    echo "   الآن guardian_registration يمكن أن يأخذ أي قيمة\n";

} catch (\Exception $e) {
    echo "⚠️ " . $e->getMessage() . "\n";
}

// التحقق
echo "\n📌 Foreign Keys المتبقية:\n";
$constraints = DB::select("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'guardian_bank_accounts'
    AND REFERENCED_TABLE_NAME IS NOT NULL
");
foreach ($constraints as $c) {
    echo "   - {$c->COLUMN_NAME} → {$c->REFERENCED_TABLE_NAME}\n";
}
if (empty($constraints)) {
    echo "   لا توجد قيود FK على guardian_registration\n";
}
