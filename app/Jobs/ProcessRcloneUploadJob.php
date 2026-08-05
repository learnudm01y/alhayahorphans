<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Attachment;
use App\Models\GoogleDriveUpload;
use App\Models\ServerSyncAction;
use App\Services\RcloneGoogleDriveService;

class ProcessRcloneUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5; // Retry up to 5 times on failure
    public $timeout = 3600; // Allow 1 hour for huge files

    /**
     * ⚠️ بدون $backoff كانت المحاولات الخمس تنطلق متتالية بلا أي تأخير، فأي
     * عطل عابر في Drive أو الشبكة يحرقها كلها في ثوانٍ ويقتل الملف نهائياً.
     * الآن تراجع تصاعدي: دقيقة، ٥، ١٥، ٣٠ دقيقة.
     */
    public $backoff = [60, 300, 900, 1800];

    /** لا نُهدر كل المحاولات على استثناء واحد متكرّر. */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public int $attachmentId,
        public int $googleDriveUploadId,
        public string $finalPath,
        public string $associationName,
        public string $orphanName,
        public string $documentTypeName,
        public string $extension
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(RcloneGoogleDriveService $rcloneService)
    {
        Log::info("🚀 Starting async Rclone upload for attachment ID: {$this->attachmentId}");

        if (!file_exists($this->finalPath)) {
            Log::error("❌ Local file not found for async Rclone upload: {$this->finalPath}");
            GoogleDriveUpload::where('id', $this->googleDriveUploadId)->update([
                'upload_status' => 'failed',
            ]);

            $uploadRecord = GoogleDriveUpload::find($this->googleDriveUploadId);
            $fileName = $uploadRecord ? $uploadRecord->file_name : ($this->documentTypeName . '.' . $this->extension);

            // [NEW] Insert into offline_upload_statuses (Inbox) for FAILURE due to missing file
            \Illuminate\Support\Facades\DB::table('offline_upload_statuses')->insert([
                'user_id' => $uploadRecord ? ($uploadRecord->uploaded_by ?? null) : null,
                'file_name' => $fileName,
                'status' => 'failed',
                'error_message' => 'Local file lost on server',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Broadcast real-time update for failure
            try {
                $payload = json_encode([
                    'event' => 'UploadStatusUpdated',
                    'file_name' => $fileName,
                    'status' => 'failed'
                ]);
                \Illuminate\Support\Facades\Http::withBody($payload, 'application/json')->post('http://127.0.0.1:6001/broadcast');
            } catch (\Exception $broadcastEx) {
            }

            return;
        }

        try {
            $result = $rcloneService->uploadFile(
                $this->finalPath,
                $this->associationName,
                $this->orphanName,
                $this->documentTypeName,
                $this->extension,
                1
            );

            if (!$result['success']) {
                throw new \Exception("Failed to upload via Rclone: " . ($result['message'] ?? 'Unknown error'));
            }

            $remotePath = $result['remote_path'];
            $fileId = 'rclone_' . uniqid(); 

            // Update Attachment Record
            Attachment::where('id', $this->attachmentId)->update([
                'file_path' => $remotePath
            ]);

            // Update Google Drive Upload Record
            GoogleDriveUpload::where('id', $this->googleDriveUploadId)->update([
                'google_drive_file_id' => $fileId,
                'google_drive_path' => $remotePath,
                'upload_status' => 'completed',
                'upload_progress' => 100,
                'synced_to_server' => true,
            ]);

            Log::info("✅ Async Rclone upload successful to path: {$remotePath}");

            $uploadRecord = GoogleDriveUpload::find($this->googleDriveUploadId);
            $fileName = $uploadRecord ? $uploadRecord->file_name : ($this->documentTypeName . '.' . $this->extension);

            // [NEW] Insert into offline_upload_statuses (Inbox)
            \Illuminate\Support\Facades\DB::table('offline_upload_statuses')->insert([
                'user_id' => $uploadRecord ? ($uploadRecord->uploaded_by ?? null) : null,
                'file_name' => $fileName,
                'status' => 'completed',
                'google_drive_file_id' => $fileId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Broadcast real-time update to Mobile App (Fallback/Optimistic update)
            try {
                $payload = json_encode([
                    'event' => 'UploadStatusUpdated',
                    'file_name' => $fileName,
                    'status' => 'completed'
                ]);
                
                \Illuminate\Support\Facades\Http::withBody($payload, 'application/json')->post('http://127.0.0.1:6001/broadcast');
            } catch (\Exception $e) {
                Log::error("❌ Failed to broadcast UploadStatusUpdated: " . $e->getMessage());
            }

            // Clean up temporary file
            @unlink($this->finalPath);

        } catch (\Exception $e) {
            Log::error("❌ Rclone async upload failed: " . $e->getMessage());
            
            // Allow Laravel to retry it if tries < max. If it fails ultimately, the failed_jobs table catches it.
            // But we should also mark it failed in our table if it reaches the end of its retries.
            if ($this->attempts() >= $this->tries) {
                GoogleDriveUpload::where('id', $this->googleDriveUploadId)->update([
                    'upload_status' => 'failed',
                ]);
                
                $uploadRecord = GoogleDriveUpload::find($this->googleDriveUploadId);
                $fileName = $uploadRecord ? $uploadRecord->file_name : ($this->documentTypeName . '.' . $this->extension);

                // [NEW] Insert into offline_upload_statuses (Inbox) for FAILURE
                \Illuminate\Support\Facades\DB::table('offline_upload_statuses')->insert([
                    'user_id' => $uploadRecord ? ($uploadRecord->uploaded_by ?? null) : null,
                    'file_name' => $fileName,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Broadcast real-time update for failure
                try {
                    $payload = json_encode([
                        'event' => 'UploadStatusUpdated',
                        'file_name' => $fileName,
                        'status' => 'failed'
                    ]);
                    
                    \Illuminate\Support\Facades\Http::withBody($payload, 'application/json')->post('http://127.0.0.1:6001/broadcast');
                } catch (\Exception $broadcastEx) {
                    Log::error("❌ Failed to broadcast UploadStatusUpdated (failed): " . $broadcastEx->getMessage());
                }
            }
            
            throw $e;
        }
    }

    /**
     * ⚠️ هذه الدالة كانت غائبة تماماً — وهي السبب الجذري لبقاء الملفات في
     * حالة "processing_server" إلى الأبد على الجهاز.
     *
     * handle() تكتب حالة الفشل فقط داخل catch. لكن أكثر أسباب موت المهمة
     * شيوعاً لا تمرّ بـ catch إطلاقاً:
     *   • قتل المهمة عند تجاوز $timeout (إشارة SIGALRM)
     *   • استنفاد المحاولات عبر إعادة توزيع الطابور (لا استثناء أصلاً)
     *   • إعادة تشغيل عامل الطابور أو نفاد الذاكرة
     * في كل هذه الحالات لم يكن يُكتب أي شيء: لا حالة فشل في قاعدة البيانات،
     * ولا إشعار في صندوق وارد الجهاز. فيبقى الهاتف ينتظر خبراً لن يصل أبداً.
     *
     * failed() يستدعيها Laravel في كل مسارات الموت النهائي، فهي الضمانة
     * الوحيدة لإبلاغ الجهاز.
     */
    public function failed(?\Throwable $exception): void
    {
        $reason = $exception ? $exception->getMessage() : 'انتهت مهلة المهمة أو استُنفدت محاولاتها';

        Log::error("💀 ProcessRcloneUploadJob failed terminally", [
            'google_drive_upload_id' => $this->googleDriveUploadId,
            'attachment_id' => $this->attachmentId,
            'reason' => $reason,
        ]);

        try {
            $uploadRecord = GoogleDriveUpload::find($this->googleDriveUploadId);
            $fileName = $uploadRecord
                ? $uploadRecord->file_name
                : ($this->documentTypeName . '.' . $this->extension);

            if ($uploadRecord && $uploadRecord->upload_status !== 'completed') {
                $uploadRecord->update([
                    'upload_status' => 'failed',
                    'error_message' => mb_substr($reason, 0, 1000),
                ]);
            }

            // إبلاغ الجهاز: بدون هذا السطر يظل الملف معلقاً على الهاتف للأبد.
            // نتجنّب التكرار إن كان catch قد سجّل الإشعار بالفعل.
            $alreadyNotified = \Illuminate\Support\Facades\DB::table('offline_upload_statuses')
                ->where('file_name', $fileName)
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->exists();

            if (!$alreadyNotified) {
                \Illuminate\Support\Facades\DB::table('offline_upload_statuses')->insert([
                    'user_id' => $uploadRecord ? ($uploadRecord->uploaded_by ?? null) : null,
                    'file_name' => $fileName,
                    'status' => 'failed',
                    'error_message' => mb_substr($reason, 0, 500),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            try {
                \Illuminate\Support\Facades\Http::withBody(
                    json_encode([
                        'event' => 'UploadStatusUpdated',
                        'file_name' => $fileName,
                        'status' => 'failed',
                    ]),
                    'application/json'
                )->post('http://127.0.0.1:6001/broadcast');
            } catch (\Throwable $ignored) {
                // البث اختياري؛ صندوق الوارد هو القناة الموثوقة.
            }
        } catch (\Throwable $t) {
            Log::error("❌ failed() handler itself failed: " . $t->getMessage());
        }
    }
}
