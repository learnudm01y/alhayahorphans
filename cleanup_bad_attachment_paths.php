<?php
/**
 * سكريبت لتنظيف المرفقات ذات المسارات الخاطئة
 *
 * المشكلة: بعض المرفقات القديمة تم حفظها بمسار خاطئ مثل:
 * alhayah:temp/صورة الهوية.jpg/جمعية/شخص
 * بدلاً من:
 * alhayah:temp/جمعية/شخص/صورة الهوية.jpg
 *
 * الاستخدام:
 * php cleanup_bad_attachment_paths.php          - عرض السجلات الخاطئة فقط
 * php cleanup_bad_attachment_paths.php --delete - حذف السجلات الخاطئة
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص المرفقات ذات المسارات الخاطئة ===\n\n";

$deleteMode = in_array('--delete', $argv);

if ($deleteMode) {
    echo "⚠️  وضع الحذف مفعّل - سيتم حذف السجلات الخاطئة\n\n";
} else {
    echo "ℹ️  وضع المعاينة - لن يتم حذف أي شيء\n";
    echo "   أضف --delete لحذف السجلات الخاطئة\n\n";
}

// الحصول على جميع المرفقات التي تستخدم مسار Rclone
$attachments = DB::table('attachments')
    ->whereRaw("file_path REGEXP '^[a-zA-Z0-9_-]+:'")
    ->get();

echo "إجمالي المرفقات مع مسار Rclone: " . $attachments->count() . "\n\n";

$badPaths = [];
$goodPaths = [];

foreach ($attachments as $attachment) {
    $path = $attachment->file_path;

    // المسار الصحيح: alhayah:temp/جمعية/شخص/ملف.امتداد
    // المسار الخاطئ: alhayah:temp/ملف.امتداد/جمعية/شخص

    // نتحقق إذا كان الجزء الثاني بعد temp/ يحتوي على امتداد ملف
    if (preg_match('/^([a-zA-Z0-9_-]+):temp\/([^\/]+\.(jpg|jpeg|png|gif|pdf|doc|docx|mp4|avi|mov|webp))\//', $path, $matches)) {
        // هذا مسار خاطئ - اسم الملف قبل المجلدات
        $badPaths[] = $attachment;
        echo "❌ خاطئ [ID: {$attachment->id}]: {$path}\n";
    } else {
        // مسار صحيح
        $goodPaths[] = $attachment;
    }
}

echo "\n=== ملخص ===\n";
echo "المسارات الصحيحة: " . count($goodPaths) . "\n";
echo "المسارات الخاطئة: " . count($badPaths) . "\n";

if (count($badPaths) > 0) {
    $badIds = array_map(fn($a) => $a->id, $badPaths);

    if ($deleteMode) {
        echo "\nحذف السجلات الخاطئة...\n";

        $deleted = DB::table('attachments')
            ->whereIn('id', $badIds)
            ->delete();

        echo "✓ تم حذف {$deleted} سجل\n";
    } else {
        echo "\nمعرفات السجلات الخاطئة: " . implode(', ', $badIds) . "\n";
        echo "\nلحذف هذه السجلات، قم بتشغيل:\n";
        echo "php cleanup_bad_attachment_paths.php --delete\n";
    }
}

echo "\n=== انتهى ===\n";
