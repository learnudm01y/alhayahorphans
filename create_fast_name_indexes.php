<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔧 إنشاء فهارس محسنة للبحث السريع في الأسماء المركبة:\n";
echo str_repeat("=", 60) . "\n";

try {
    // فهرس مركب للاسم الأول + اسم الأب (للبحث السريع بأول كلمتين)
    echo "📋 إنشاء فهرس مركب للاسم الأول + اسم الأب...\n";
    try {
        DB::connection('civilregistry')->statement('DROP INDEX idx_first_father_fast ON persons');
    } catch (Exception $e) { /* تجاهل إذا لم يكن موجود */ }

    DB::connection('civilregistry')->statement('
        CREATE INDEX idx_first_father_fast
        ON persons (CI_FIRST_ARB(10), CI_FATHER_ARB(10))
    ');
    echo "✅ تم إنشاء فهرس idx_first_father_fast\n";

    // فهرس مركب للاسم الأول + اسم العائلة
    echo "📋 إنشاء فهرس مركب للاسم الأول + اسم العائلة...\n";
    try {
        DB::connection('civilregistry')->statement('DROP INDEX idx_first_family_fast ON persons');
    } catch (Exception $e) { /* تجاهل إذا لم يكن موجود */ }

    DB::connection('civilregistry')->statement('
        CREATE INDEX idx_first_family_fast
        ON persons (CI_FIRST_ARB(10), CI_FAMILY_ARB(10))
    ');
    echo "✅ تم إنشاء فهرس idx_first_family_fast\n";

    // فهرس مركب لاسم الأب + اسم العائلة
    echo "📋 إنشاء فهرس مركب لاسم الأب + اسم العائلة...\n";
    try {
        DB::connection('civilregistry')->statement('DROP INDEX idx_father_family_fast ON persons');
    } catch (Exception $e) { /* تجاهل إذا لم يكن موجود */ }

    DB::connection('civilregistry')->statement('
        CREATE INDEX idx_father_family_fast
        ON persons (CI_FATHER_ARB(10), CI_FAMILY_ARB(10))
    ');
    echo "✅ تم إنشاء فهرس idx_father_family_fast\n";

    // فهرس للبحث السريع في البداية (أول 15 حرف)
    echo "📋 إنشاء فهارس سريعة للبحث بالبداية...\n";
    try {
        DB::connection('civilregistry')->statement('DROP INDEX idx_first_arb_prefix ON persons');
    } catch (Exception $e) { /* تجاهل إذا لم يكن موجود */ }

    DB::connection('civilregistry')->statement('
        CREATE INDEX idx_first_arb_prefix
        ON persons (CI_FIRST_ARB(15))
    ');
    echo "✅ تم إنشاء فهرس idx_first_arb_prefix\n";

    try {
        DB::connection('civilregistry')->statement('DROP INDEX idx_father_arb_prefix ON persons');
    } catch (Exception $e) { /* تجاهل إذا لم يكن موجود */ }

    DB::connection('civilregistry')->statement('
        CREATE INDEX idx_father_arb_prefix
        ON persons (CI_FATHER_ARB(15))
    ');
    echo "✅ تم إنشاء فهرس idx_father_arb_prefix\n";

    try {
        DB::connection('civilregistry')->statement('DROP INDEX idx_family_arb_prefix ON persons');
    } catch (Exception $e) { /* تجاهل إذا لم يكن موجود */ }

    DB::connection('civilregistry')->statement('
        CREATE INDEX idx_family_arb_prefix
        ON persons (CI_FAMILY_ARB(15))
    ');
    echo "✅ تم إنشاء فهرس idx_family_arb_prefix\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎯 تم إنشاء جميع الفهارس المحسنة بنجاح!\n";
    echo "💡 هذه الفهارس ستسرع البحث بالأسماء المركبة بشكل كبير.\n";

} catch (Exception $e) {
    echo "❌ خطأ في إنشاء الفهارس: " . $e->getMessage() . "\n";
}
