<?php
/**
 * سكريبت إضافة عمود person_type إلى جدول sponsorships
 * لتخزين نوع الشخص (معيل، فرد عائلة، أب متوفي، أم متوفية)
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== إضافة عمود person_type إلى جدول sponsorships ===\n\n";

try {
    // التحقق من وجود العمود
    if (Schema::hasColumn('sponsorships', 'person_type')) {
        echo "✅ العمود person_type موجود بالفعل!\n";
    } else {
        // إضافة العمود الجديد
        DB::statement("ALTER TABLE sponsorships ADD COLUMN person_type VARCHAR(50) NULL DEFAULT NULL COMMENT 'نوع الشخص: breadwinner=معيل, family_member=فرد عائلة, deceased_father=أب متوفي, deceased_mother=أم متوفية' AFTER notes");

        echo "✅ تم إضافة عمود person_type بنجاح!\n";
    }

    // عرض أنواع الأشخاص المتاحة
    echo "\n📋 أنواع الأشخاص المتاحة:\n";
    echo "   - breadwinner: معيل\n";
    echo "   - family_member: فرد عائلة\n";
    echo "   - deceased_father: أب متوفي\n";
    echo "   - deceased_mother: أم متوفية\n";

    // التحقق من الأعمدة الحالية
    echo "\n📊 أعمدة جدول sponsorships:\n";
    $columns = Schema::getColumnListing('sponsorships');
    foreach ($columns as $column) {
        echo "   - $column\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== انتهى ===\n";
