<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         🧪 اختبار شامل لنظام إدارة الجمعيات            ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

$errors = [];
$warnings = [];
$successes = [];

// ============================================================
// 1. فحص بنية قاعدة البيانات
// ============================================================
echo "📊 [1/7] فحص بنية قاعدة البيانات...\n";
echo str_repeat('─', 60) . "\n";

try {
    $columns = DB::select('SHOW FULL COLUMNS FROM sponsors');
    $columnNames = array_column($columns, 'Field');

    $requiredColumns = ['id', 'file_id', 'sponsor_name', 'created_at'];
    $missingColumns = array_diff($requiredColumns, $columnNames);

    if (empty($missingColumns)) {
        echo "  ✅ جدول sponsors موجود وصحيح\n";
        $successes[] = "بنية الجدول صحيحة";
    } else {
        echo "  ❌ أعمدة مفقودة: " . implode(', ', $missingColumns) . "\n";
        $errors[] = "أعمدة مفقودة في جدول sponsors";
    }

    // التحقق من نوع file_id
    foreach ($columns as $col) {
        if ($col->Field == 'file_id') {
            if (strpos($col->Type, 'varchar') !== false) {
                echo "  ✅ نوع file_id: {$col->Type} (صحيح)\n";
                $successes[] = "نوع file_id صحيح (varchar)";
            } else {
                echo "  ⚠️  نوع file_id: {$col->Type} (يفضل varchar(6))\n";
                $warnings[] = "نوع file_id ليس varchar";
            }
            break;
        }
    }

} catch (\Exception $e) {
    echo "  ❌ خطأ: {$e->getMessage()}\n";
    $errors[] = "فشل فحص بنية الجدول";
}

echo "\n";

// ============================================================
// 2. اختبار دالة generateUniqueReservedCode
// ============================================================
echo "🔢 [2/7] اختبار توليد رقم الملف...\n";
echo str_repeat('─', 60) . "\n";

try {
    $file_id = generateUniqueReservedCode('sponsors', 'file_id');

    if ($file_id) {
        echo "  ✅ تم توليد رقم الملف: {$file_id}\n";
        echo "  📏 الطول: " . strlen($file_id) . " أحرف\n";
        echo "  🔤 النوع: " . gettype($file_id) . "\n";

        if (strlen($file_id) == 6) {
            $successes[] = "توليد رقم الملف يعمل بشكل صحيح";
        } else {
            $warnings[] = "رقم الملف ليس 6 أرقام";
        }

        // التحقق من reserved_codes
        $reserved = DB::table('reserved_codes')->where('code', $file_id)->first();
        if ($reserved) {
            echo "  ✅ الرقم محجوز في reserved_codes\n";
            $successes[] = "نظام الحجز يعمل";
        } else {
            echo "  ⚠️  الرقم غير محجوز في reserved_codes\n";
            $warnings[] = "الرقم لم يُسجل في reserved_codes";
        }
    } else {
        echo "  ❌ فشل توليد رقم الملف\n";
        $errors[] = "دالة generateUniqueReservedCode لا تعمل";
    }
} catch (\Exception $e) {
    echo "  ❌ خطأ: {$e->getMessage()}\n";
    $errors[] = "فشل اختبار توليد رقم الملف";
    $file_id = null;
}

echo "\n";

// ============================================================
// 3. اختبار إدخال بيانات تجريبية
// ============================================================
echo "💾 [3/7] اختبار إدخال بيانات تجريبية...\n";
echo str_repeat('─', 60) . "\n";

$testFileId = $file_id ?? '999999';

DB::beginTransaction();

try {
    $sponsor = new App\Models\Sponsor();
    $sponsor->file_id = $testFileId;
    $sponsor->sponsor_name = 'جمعية اختبار تلقائي ' . now()->format('His');
    $sponsor->sponsor_short_name = 'اختبار';
    $sponsor->sponsor_phone_number = '0599123456';
    $sponsor->sponsor_email = 'test@autotest.com';
    $sponsor->sponsor_address = 'عنوان اختبار تلقائي';

    $sponsor->save();

    echo "  ✅ تم الحفظ بنجاح!\n";
    echo "  🆔 ID: {$sponsor->id}\n";
    echo "  📄 file_id: {$sponsor->file_id}\n";
    echo "  📛 الاسم: {$sponsor->sponsor_name}\n";

    $successes[] = "إدخال البيانات يعمل بشكل صحيح";

    // ============================================================
    // 4. اختبار markCodeAsUsed
    // ============================================================
    echo "\n";
    echo "🏷️  [4/7] اختبار وضع علامة 'مستخدم' على الرقم...\n";
    echo str_repeat('─', 60) . "\n";

    markCodeAsUsed($testFileId);

    $reservedCheck = DB::table('reserved_codes')->where('code', $testFileId)->first();
    if ($reservedCheck && $reservedCheck->used) {
        echo "  ✅ تم وضع علامة 'مستخدم' بنجاح\n";
        $successes[] = "دالة markCodeAsUsed تعمل";
    } else {
        echo "  ⚠️  لم يتم وضع علامة 'مستخدم'\n";
        $warnings[] = "دالة markCodeAsUsed لا تعمل كما يجب";
    }

    // ============================================================
    // 5. التحقق من البيانات في قاعدة البيانات
    // ============================================================
    echo "\n";
    echo "🔍 [5/7] التحقق من البيانات المحفوظة...\n";
    echo str_repeat('─', 60) . "\n";

    $dbRecord = DB::table('sponsors')->where('id', $sponsor->id)->first();

    if ($dbRecord) {
        echo "  ✅ السجل موجود في قاعدة البيانات\n";
        echo "  ✓ file_id متطابق: " . ($dbRecord->file_id == $testFileId ? 'نعم' : 'لا') . "\n";
        echo "  ✓ الاسم متطابق: " . ($dbRecord->sponsor_name == $sponsor->sponsor_name ? 'نعم' : 'لا') . "\n";

        // التحقق من نوع file_id في قاعدة البيانات
        if (strlen($dbRecord->file_id) == 6 && $dbRecord->file_id[0] == '0') {
            echo "  ✅ الأصفار البادئة محفوظة بشكل صحيح\n";
            $successes[] = "الأصفار البادئة محفوظة";
        } else {
            echo "  ⚠️  الأصفار البادئة قد تكون غير محفوظة\n";
            $warnings[] = "مشكلة محتملة في الأصفار البادئة";
        }

        $successes[] = "البيانات محفوظة بشكل صحيح";
    } else {
        echo "  ❌ السجل غير موجود!\n";
        $errors[] = "فشل التحقق من البيانات";
    }

    // ============================================================
    // 6. حذف البيانات التجريبية
    // ============================================================
    echo "\n";
    echo "🗑️  [6/7] حذف البيانات التجريبية...\n";
    echo str_repeat('─', 60) . "\n";

    $sponsor->delete();
    DB::table('reserved_codes')->where('code', $testFileId)->delete();

    echo "  ✅ تم حذف السجل التجريبي\n";
    echo "  ✅ تم حذف الرقم المحجوز\n";

    DB::commit();

} catch (\Exception $e) {
    DB::rollBack();
    echo "  ❌ حدث خطأ: {$e->getMessage()}\n";
    echo "  📁 الملف: {$e->getFile()}\n";
    echo "  #️⃣  السطر: {$e->getLine()}\n";
    $errors[] = "فشل اختبار الإدخال: {$e->getMessage()}";
}

echo "\n";

// ============================================================
// 7. فحص Routes والواجهات
// ============================================================
echo "🛣️  [7/7] فحص Routes والواجهات...\n";
echo str_repeat('─', 60) . "\n";

$requiredRoutes = [
    'admin.sponsors.index',
    'admin.sponsors.store',
    'admin.sponsors.edit',
    'admin.sponsors.update',
    'admin.sponsors.destroy',
];

$routeList = Illuminate\Support\Facades\Route::getRoutes();
$existingRoutes = [];

foreach ($routeList as $route) {
    $name = $route->getName();
    if ($name && in_array($name, $requiredRoutes)) {
        $existingRoutes[] = $name;
    }
}

$missingRoutes = array_diff($requiredRoutes, $existingRoutes);

if (empty($missingRoutes)) {
    echo "  ✅ جميع Routes المطلوبة موجودة\n";
    $successes[] = "Routes مُعرّفة بشكل صحيح";
} else {
    echo "  ⚠️  Routes مفقودة: " . implode(', ', $missingRoutes) . "\n";
    $warnings[] = "بعض Routes مفقودة";
}

// فحص الملفات
$requiredFiles = [
    'app/Http/Controllers/Admin/SponsorController.php',
    'app/Models/Sponsor.php',
    'app/DataTables/SponsorDataTable.php',
    'resources/views/admin/dashboard/sponsors/index.blade.php',
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missingFiles[] = $file;
    }
}

if (empty($missingFiles)) {
    echo "  ✅ جميع الملفات المطلوبة موجودة\n";
    $successes[] = "جميع الملفات موجودة";
} else {
    echo "  ❌ ملفات مفقودة:\n";
    foreach ($missingFiles as $file) {
        echo "    - {$file}\n";
    }
    $errors[] = "ملفات مفقودة";
}

echo "\n";

// ============================================================
// التقرير النهائي
// ============================================================
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                     📊 التقرير النهائي                  ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "✅ نجاحات: " . count($successes) . "\n";
foreach ($successes as $success) {
    echo "   • {$success}\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  تحذيرات: " . count($warnings) . "\n";
    foreach ($warnings as $warning) {
        echo "   • {$warning}\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ أخطاء: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "   • {$error}\n";
    }
}

echo "\n";

// الخلاصة
if (empty($errors)) {
    if (empty($warnings)) {
        echo "🎉 ممتاز! النظام يعمل بشكل كامل 100%\n\n";
        echo "✨ يمكنك الآن:\n";
        echo "   1. فتح: http://127.0.0.1:8000/admin/sponsors\n";
        echo "   2. الضغط على زر 'إضافة جمعية جديدة'\n";
        echo "   3. ملء البيانات والحفظ\n\n";
        exit(0);
    } else {
        echo "✅ النظام يعمل مع بعض التحذيرات\n\n";
        exit(0);
    }
} else {
    echo "⛔ يوجد أخطاء يجب إصلاحها قبل الاستخدام\n\n";
    exit(1);
}
