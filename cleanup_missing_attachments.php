<?php
/**
 * سكريبت لتنظيف سجلات المرفقات المحذوفة من Google Drive
 *
 * الاستخدام:
 * php cleanup_missing_attachments.php          - عرض الملفات المفقودة فقط
 * php cleanup_missing_attachments.php --delete - حذف السجلات المفقودة
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\RcloneGoogleDriveService;

echo "=== فحص المرفقات المفقودة من Google Drive ===\n\n";

$deleteMode = in_array('--delete', $argv);

if ($deleteMode) {
    echo "⚠️  وضع الحذف مفعّل - سيتم حذف السجلات المفقودة\n\n";
} else {
    echo "ℹ️  وضع المعاينة - لن يتم حذف أي شيء\n";
    echo "   أضف --delete لحذف السجلات المفقودة\n\n";
}

// الحصول على جميع المرفقات التي تستخدم مسار Rclone
$attachments = DB::table('attachments')
    ->whereRaw("file_path REGEXP '^[a-zA-Z0-9_-]+:'")
    ->get();

echo "عدد المرفقات مع مسار Rclone: " . $attachments->count() . "\n\n";

if ($attachments->count() === 0) {
    echo "✓ لا توجد مرفقات Rclone للفحص\n";
    exit(0);
}

$rcloneService = new RcloneGoogleDriveService();

$missingCount = 0;
$existingCount = 0;
$missingIds = [];

foreach ($attachments as $attachment) {
    echo "فحص: {$attachment->file_path}... ";

    $exists = $rcloneService->fileExists($attachment->file_path);

    if ($exists) {
        echo "✓ موجود\n";
        $existingCount++;
    } else {
        echo "✗ مفقود\n";
        $missingCount++;
        $missingIds[] = $attachment->id;
    }
}

echo "\n=== ملخص ===\n";
echo "الملفات الموجودة: {$existingCount}\n";
echo "الملفات المفقودة: {$missingCount}\n";

if ($missingCount > 0 && $deleteMode) {
    echo "\nحذف السجلات المفقودة...\n";

    $deleted = DB::table('attachments')
        ->whereIn('id', $missingIds)
        ->delete();

    echo "✓ تم حذف {$deleted} سجل\n";
} elseif ($missingCount > 0) {
    echo "\nمعرفات السجلات المفقودة: " . implode(', ', $missingIds) . "\n";
    echo "\nلحذف هذه السجلات، قم بتشغيل:\n";
    echo "php cleanup_missing_attachments.php --delete\n";
}

echo "\n=== انتهى ===\n";
