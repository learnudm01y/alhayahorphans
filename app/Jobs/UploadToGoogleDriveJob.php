<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\GoogleDriveService;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadToGoogleDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * ⚠️ لم تكن هذه المهمة تُعرّف $timeout ولا $tries إطلاقاً، فكانت ترث
     * --timeout=300 من إعداد supervisor. لكنها تُشغّل rclone بمهلة ٦٠٠ ثانية
     * داخلها (السطر ~٩٩)، أي أن العامل يقتل المهمة بعد ٥ دقائق بينما rclone
     * ما يزال في منتصف رفع الفيديو — وبلا استثناء يُلتقط، فلا يُسجَّل أي فشل
     * في أي مكان ولا يعلم الجهاز بشيء.
     */
    public $timeout = 900;   // أطول من مهلة rclone الداخلية (٦٠٠)
    public $tries = 3;
    public $backoff = [120, 600];

    public $attachmentId;
    public $localFilePath;
    public $fileName;
    public $sponsorName;
    public $orphanName;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($attachmentId, $localFilePath, $fileName, $sponsorName, $orphanName)
    {
        $this->attachmentId = $attachmentId;
        $this->localFilePath = $localFilePath;
        $this->fileName = $fileName;
        $this->sponsorName = $sponsorName;
        $this->orphanName = $orphanName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("🚀 Starting Google Drive Upload Job for File: {$this->fileName}");

        try {
            $fullLocalPath = storage_path("app/public/{$this->localFilePath}");

            if (!file_exists($fullLocalPath)) {
                Log::error("❌ Local file not found: {$fullLocalPath}");
                return;
            }

            // تحديد مسار المجلد في Google Drive
            // مثال: 'أيتام الحياة/اسم الكافل/اسم اليتيم' أو حسب التنظيم المتبع
            $sponsor = $this->sponsorName ?: 'Unknown_Sponsor';
            $orphan = $this->orphanName ?: 'Unknown_Orphan';
            $driveFolderPath = "{$sponsor}/{$orphan}";

            Log::info("📂 Target Drive Path: {$driveFolderPath}");

            // الرفع إلى Google Drive باستخدام Rclone (المسار الأساسي بدون credentials.json)
            $useRclone = config('services.rclone.enabled', false);

            if ($useRclone) {
                Log::info("🚀 Using Rclone for upload: {$fullLocalPath}");
                $remoteName = config('services.rclone.remote_name', 'alhayahorphans');
                $rootFolder = config('services.rclone.root_folder', 'temp');

                $rclonePath = config('services.rclone.path', 'rclone');
                $rcloneConfig = config('services.rclone.config', '');

                $destination = "{$remoteName}:{$rootFolder}/{$driveFolderPath}";

                Log::info("🏃‍♂️ Running Rclone command", [
                    'path' => $rclonePath,
                    'config' => $rcloneConfig,
                    'local' => $fullLocalPath,
                    'destination' => $destination
                ]);

                try {
                    $args = [];
                    if ($rcloneConfig) {
                        $args[] = '--config';
                        $args[] = $rcloneConfig;
                    }
                    $args[] = 'copy';
                    $args[] = escapeshellarg($fullLocalPath);
                    $args[] = escapeshellarg($destination);

                    $commandString = "\"{$rclonePath}\" " . implode(' ', $args);

                    $result = \Illuminate\Support\Facades\Process::timeout(600)->run($commandString);

                    $output = $result->output() . "\n" . $result->errorOutput();

                    if (!$result->successful() || stripos($output, 'Failed to') !== false || stripos($output, 'ERROR') !== false) {
                        throw new \Exception("Rclone upload failed: {$output}");
                    }

                    Log::info("✅ Rclone upload successful.", ['output' => $output]);

                } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
                    throw new \Exception("Rclone upload timed out after 600 seconds.");
                }

            } else {
                // المسار الاحتياطي: GoogleDriveService يتطلب credentials.json
                $googleDriveService = app(\App\Services\GoogleDriveService::class);
                $fileId = $googleDriveService->uploadToPath($fullLocalPath, $driveFolderPath, $this->fileName);

                if (!$fileId) {
                    Log::error("❌ Failed to get File ID from Google Drive Service.");
                    $this->release(60);
                    return;
                }
                Log::info("✅ File uploaded successfully to Google Drive. File ID: {$fileId}");
            }

            Log::info("✅ File successfully uploaded to Google Drive: {$this->fileName}");

            // 3. حذف الملف المحلي بعد نجاح الرفع لتوفير المساحة
            if (file_exists($fullLocalPath)) {
                unlink($fullLocalPath);
                Log::info("🗑️ Local file deleted after successful upload: {$fullLocalPath}");
            }

        } catch (\Exception $e) {
            Log::error("❌ Exception during Google Drive Upload: " . $e->getMessage());
            $this->release(120); // إعادة المحاولة بعد دقيقتين
        }
    }
}
