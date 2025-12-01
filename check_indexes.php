<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== فحص INDEX على جدول persons في السجل المدني ===\n\n";

$indexes = DB::connection('civilregistry')->select("
    SHOW INDEXES FROM persons
    WHERE Column_name IN ('CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_FAMILY_ARB')
");

if (empty($indexes)) {
    echo "❌ لا يوجد INDEX على أعمدة الأسماء!\n\n";
    echo "هذا هو السبب في بطء البحث (36 ثانية)\n";
    echo "يجب إضافة INDEX على:\n";
    echo "  - CI_FIRST_ARB\n";
    echo "  - CI_FATHER_ARB\n";
    echo "  - CI_FAMILY_ARB\n\n";
} else {
    echo "✅ تم العثور على INDEX:\n\n";
    foreach ($indexes as $idx) {
        echo "  {$idx->Column_name} => {$idx->Key_name} ({$idx->Index_type})\n";
    }
}

echo "\n=== فحص حجم الجدول ===\n\n";
$count = DB::connection('civilregistry')->table('persons')->count();
echo "عدد السجلات: " . number_format($count) . "\n";
