<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AttachmentRotationService
{
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif'];
    private const MAX_SOURCE_BYTES = 20 * 1024 * 1024;
    private const MAX_SOURCE_PIXELS = 24000000;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * تدوير ملف المرفق 90° مع عقارب الساعة وحفظه فوق الملف الأصلي نفسه.
     *
     * @return array{success: bool, status: string, message: string, file_name?: string, width?: int, height?: int}
     */
    public function rotate(int $attachmentId): array
    {
        $attachment = Attachment::find($attachmentId);
        if (!$attachment) {
            return ['success' => false, 'status' => 'missing', 'message' => 'المرفق غير موجود.'];
        }
        if (in_array((string) $attachment->document_enhancement_status, ['queued', 'processing'], true)) {
            return ['success' => false, 'status' => 'busy', 'message' => 'لا يمكن التدوير أثناء تحسين هذا المستند.'];
        }
        $source = $this->localPath((string) $attachment->file_path);
        if ($source === null) {
            return ['success' => false, 'status' => 'not-allowed', 'message' => 'هذه الصيغة لا تدعم التدوير.'];
        }
        if (!$this->withinLimits($source)) {
            return ['success' => false, 'status' => 'not-allowed', 'message' => 'حجم الصورة أكبر من الحد المسموح لتدويرها.'];
        }

        $sourceHash = hash_file('sha256', $source);
        // Same-directory staging keeps the replacement a single atomic rename on one filesystem.
        $temp = dirname($source).'/.rotate-'.bin2hex(random_bytes(8)).'.'.strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $bytes = 0;

        try {
            $image = $this->manager->read($source)->orient();
            $width = $image->width();
            $height = $image->height();
            $image->rotate(-90);
            $image->save($temp);

            clearstatcache(true, $temp);
            $rotated = is_file($temp) ? @getimagesize($temp) : false;
            $bytes = $rotated ? (int) filesize($temp) : 0;
            if (!$rotated || $bytes < 1 || [$rotated[0], $rotated[1]] !== [$height, $width]) {
                return ['success' => false, 'status' => 'failed', 'message' => 'تعذّر توليد نسخة التدوير، بقي الملف الأصلي دون تغيير.'];
            }
            clearstatcache(true, $source);
            if (!is_file($source) || !hash_equals($sourceHash, hash_file('sha256', $source))) {
                return ['success' => false, 'status' => 'failed', 'message' => 'تغيّر الملف الأصلي أثناء التدوير، لم تُكتب أي تعديل.'];
            }
            if (!@rename($temp, $source)) {
                return ['success' => false, 'status' => 'failed', 'message' => 'تعذّر حفظ الصورة بعد التدوير فوق الملف الأصلي.'];
            }
            clearstatcache(true, $source);
        } catch (\Throwable $exception) {
            Log::warning('attachment-rotation: failed', [
                'attachment_id' => $attachment->id, 'exception' => $exception::class,
            ]);

            return ['success' => false, 'status' => 'failed', 'message' => 'تعذّر تدوير المرفق، بقي الملف الأصلي دون تغيير.'];
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }

        // file_hash, file_path and stored_file_name stay untouched: the path remains the upload identity.
        if (Schema::hasColumn('attachments', 'file_size')) {
            Attachment::whereKey($attachment->id)
                ->where('file_path', $attachment->file_path)
                ->update(['file_size' => $bytes]);
        }

        return [
            'success' => true,
            'status' => 'rotated',
            'message' => 'تم تدوير المرفق 90° باتجاه عقارب الساعة.',
            'file_name' => $attachment->stored_file_name ?: basename($source),
            'width' => $height,
            'height' => $width,
        ];
    }

    private function withinLimits(string $source): bool
    {
        if ((int) filesize($source) > self::MAX_SOURCE_BYTES) {
            return false;
        }
        $dimensions = @getimagesize($source);

        return $dimensions !== false && $dimensions[0] * $dimensions[1] <= self::MAX_SOURCE_PIXELS;
    }

    private function localPath(string $path): ?string
    {
        if (!preg_match('#^storage/uploads/[0-9]{6}/[^/\\\\\x00]+$#D', $path)) {
            return null;
        }
        $disk = Storage::disk('public');
        $root = realpath($disk->path('uploads'));
        $resolved = realpath($disk->path(substr($path, strlen('storage/'))));
        if ($root === false || $resolved === false || !is_file($resolved)
            || !str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return in_array(strtolower(pathinfo($resolved, PATHINFO_EXTENSION)), self::SUPPORTED_EXTENSIONS, true)
            ? $resolved : null;
    }
}
