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
                'user_id' => $uploadRecord ? $uploadRecord->user_id : null,
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
                'user_id' => $uploadRecord ? $uploadRecord->user_id : null,
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
                    'user_id' => $uploadRecord ? $uploadRecord->user_id : null,
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
}
