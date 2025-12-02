<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsor;

echo "\n🔍 اختبار إدخال بيانات فعلية...\n\n";

DB::beginTransaction();

try {
    // توليد file_id
    $file_id = generateUniqueReservedCode('sponsors', 'file_id');
    echo "1️⃣  file_id المولد: {$file_id}\n";
    echo "   النوع: " . gettype($file_id) . "\n";
    echo "   الطول: " . strlen($file_id) . "\n\n";

    // إنشاء سجل جديد
    echo "2️⃣  إنشاء سجل جديد...\n";

    $sponsor = new Sponsor();
    $sponsor->file_id = $file_id;
    $sponsor->sponsor_name = 'جمعية الهلال الأحمر';
    $sponsor->sponsor_short_name = 'الهلال';
    $sponsor->sponsor_phone_number = '0599111222';
    $sponsor->sponsor_email = 'info@redcrescent.org';
    $sponsor->sponsor_address = 'غزة - شارع الوحدة';

    echo "   البيانات المُعدة:\n";
    echo "   - file_id: {$sponsor->file_id}\n";
    echo "   - sponsor_name: {$sponsor->sponsor_name}\n";
    echo "   - sponsor_short_name: {$sponsor->sponsor_short_name}\n";
    echo "   - sponsor_phone_number: {$sponsor->sponsor_phone_number}\n\n";

    // محاولة الحفظ
    echo "3️⃣  محاولة الحفظ...\n";
    $saved = $sponsor->save();

    if ($saved) {
        echo "   ✅ تم الحفظ بنجاح!\n";
        echo "   ID: {$sponsor->id}\n\n";

        // التحقق من قاعدة البيانات
        echo "4️⃣  التحقق من قاعدة البيانات...\n";
        $dbCheck = DB::table('sponsors')->where('id', $sponsor->id)->first();

        if ($dbCheck) {
            echo "   ✅ السجل موجود في قاعدة البيانات:\n";
            echo "   - id: {$dbCheck->id}\n";
            echo "   - file_id: {$dbCheck->file_id}\n";
            echo "   - sponsor_name: {$dbCheck->sponsor_name}\n";
            echo "   - created_at: {$dbCheck->created_at}\n\n";

            // وضع علامة على الكود
            echo "5️⃣  وضع علامة 'مستخدم' على الكود...\n";
            markCodeAsUsed($file_id);
            echo "   ✅ تم\n\n";

            DB::commit();

            echo "✨ النجاح الكامل!\n\n";
            echo "📊 العدد الكلي للجمعيات الآن: " . Sponsor::count() . "\n\n";

            echo "🌐 افتح المتصفح على:\n";
            echo "   http://127.0.0.1:8000/admin/sponsors\n\n";

        } else {
            echo "   ❌ السجل غير موجود في قاعدة البيانات!\n";
            DB::rollBack();
        }

    } else {
        echo "   ❌ فشل الحفظ!\n";
        DB::rollBack();
    }

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ حدث خطأ:\n";
    echo "الرسالة: {$e->getMessage()}\n";
    echo "الملف: {$e->getFile()}\n";
    echo "السطر: {$e->getLine()}\n";
    echo "\nStack Trace:\n{$e->getTraceAsString()}\n";
}
