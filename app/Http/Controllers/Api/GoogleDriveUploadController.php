<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleDriveUpload;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller: GoogleDriveUploadController
 *
 * Purpose: Handle file upload notifications from mobile app
 *
 * Workflow:
 * 1. Mobile app uploads files directly to Google Drive via Rclone
 * 2. After successful upload, mobile notifies this controller
 * 3. Controller creates attachment record and links it
 * 4. No sync for attachments - direct upload only
 */
class GoogleDriveUploadController extends Controller
{
    /**
     * POST /api/uploads/check-duplicate
     *
     * Check if a file with the given hash already exists
     * to prevent duplicate uploads
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'file_hash' => 'required|string|size:64' // SHA256 is 64 characters
        ]);

        $exists = GoogleDriveUpload::hashExists($request->file_hash);

        if ($exists) {
            // Get existing upload info
            $existingUpload = GoogleDriveUpload::where('local_file_hash', $request->file_hash)
                ->where('upload_status', GoogleDriveUpload::STATUS_COMPLETED)
                ->first();

            return response()->json([
                'exists' => true,
                'message' => 'الملف موجود بالفعل',
                'google_drive_file_id' => $existingUpload?->google_drive_file_id,
                'attachment_id' => $existingUpload?->server_attachment_id
            ]);
        }

        return response()->json([
            'exists' => false,
            'message' => 'الملف غير موجود - يمكن الرفع'
        ]);
    }

    /**
     * POST /api/uploads/notify-completed
     *
     * Notification from mobile app that a file has been
     * successfully uploaded to Google Drive
     */
    public function notifyCompleted(Request $request): JsonResponse
    {
        $request->validate([
            'google_drive_file_id' => 'required|string|max:100',
            'google_drive_path' => 'required|string|max:500',
            'file_hash' => 'required|string|size:64',
            'file_name' => 'required|string|max:255',
            'file_size_bytes' => 'required|integer|min:1',
            'mime_type' => 'required|string|max:100',
            'entity_type' => 'required|in:sponsorship,orphan,guardian,deceased,bank_account',
            'entity_id' => 'required|string|max:50',
            'attachment_type' => 'required|string|max:100',
            'attachment_description' => 'nullable|string|max:500',
            'device_id' => 'required|string|max:100',
            'local_file_path' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Step 1: Check if already processed
            $existingUpload = GoogleDriveUpload::where('local_file_hash', $request->file_hash)
                ->where('upload_status', GoogleDriveUpload::STATUS_COMPLETED)
                ->where('synced_to_server', true)
                ->first();

            if ($existingUpload) {
                DB::rollBack();
                return response()->json([
                    'success' => true,
                    'already_processed' => true,
                    'attachment_id' => $existingUpload->server_attachment_id,
                    'upload_id' => $existingUpload->id,
                    'message' => 'الملف مسجل بالفعل'
                ]);
            }

            // Step 2: Create or update google_drive_uploads record
            $upload = GoogleDriveUpload::updateOrCreate(
                ['local_file_hash' => $request->file_hash],
                [
                    'local_file_path' => $request->local_file_path ?? '',
                    'google_drive_file_id' => $request->google_drive_file_id,
                    'google_drive_path' => $request->google_drive_path,
                    'file_name' => $request->file_name,
                    'file_size_bytes' => $request->file_size_bytes,
                    'mime_type' => $request->mime_type,
                    'upload_status' => GoogleDriveUpload::STATUS_COMPLETED,
                    'upload_progress' => 100,
                    'entity_type' => $request->entity_type,
                    'entity_id' => $request->entity_id,
                    'attachment_type' => $request->attachment_type,
                    'attachment_description' => $request->attachment_description,
                    'device_id' => $request->device_id,
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now(),
                    'synced_to_server' => true
                ]
            );

            // Step 3: Create attachment record
            $attachment = Attachment::create([
                'file_name' => $request->file_name,
                'stored_file_name' => $request->google_drive_file_id,
                'file_path' => $request->google_drive_path,
                'file_size' => $request->file_size_bytes,
                'mime_type' => $request->mime_type,
                'attachment_type' => $request->attachment_type,
                'description' => $request->attachment_description,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'storage_type' => 'google_drive',
                'google_drive_file_id' => $request->google_drive_file_id,
                'google_drive_path' => $request->google_drive_path,
                'uploaded_by' => auth()->id(),
                'is_synced' => true
            ]);

            // Step 4: Link upload to attachment
            $upload->update([
                'server_attachment_id' => $attachment->id
            ]);

            DB::commit();

            Log::info('Google Drive upload notification processed', [
                'google_drive_file_id' => $request->google_drive_file_id,
                'attachment_id' => $attachment->id,
                'upload_id' => $upload->id,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id
            ]);

            return response()->json([
                'success' => true,
                'attachment_id' => $attachment->id,
                'upload_id' => $upload->id,
                'message' => 'تم تسجيل الملف بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Google Drive upload notification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->except(['file_hash'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل تسجيل الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/uploads/stats
     *
     * Get upload statistics for dashboard
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = GoogleDriveUpload::selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN upload_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN upload_status = 'uploading' THEN 1 ELSE 0 END) as uploading,
                SUM(CASE WHEN upload_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN upload_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN synced_to_server = 0 AND upload_status = 'completed' THEN 1 ELSE 0 END) as unsynced,
                COALESCE(SUM(file_size_bytes), 0) as total_size,
                COALESCE(SUM(CASE WHEN upload_status = 'completed' THEN file_size_bytes ELSE 0 END), 0) as uploaded_size
            ")->first();

            // Today's uploads
            $todayStats = GoogleDriveUpload::whereDate('created_at', today())
                ->selectRaw("COUNT(*) as count, COALESCE(SUM(file_size_bytes), 0) as size")
                ->first();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $stats->total ?? 0,
                    'pending' => $stats->pending ?? 0,
                    'uploading' => $stats->uploading ?? 0,
                    'completed' => $stats->completed ?? 0,
                    'failed' => $stats->failed ?? 0,
                    'unsynced' => $stats->unsynced ?? 0,
                    'total_size_bytes' => $stats->total_size ?? 0,
                    'uploaded_size_bytes' => $stats->uploaded_size ?? 0,
                    'total_size_formatted' => $this->formatBytes($stats->total_size ?? 0),
                    'uploaded_size_formatted' => $this->formatBytes($stats->uploaded_size ?? 0),
                    'today' => [
                        'count' => $todayStats->count ?? 0,
                        'size_bytes' => $todayStats->size ?? 0,
                        'size_formatted' => $this->formatBytes($todayStats->size ?? 0)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get upload stats', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'فشل جلب الإحصائيات'
            ], 500);
        }
    }

    /**
     * GET /api/uploads/entity/{type}/{id}
     *
     * Get all uploads for a specific entity
     */
    public function getEntityUploads(string $type, string $id): JsonResponse
    {
        try {
            $uploads = GoogleDriveUpload::forEntity($type, $id)
                ->where('upload_status', GoogleDriveUpload::STATUS_COMPLETED)
                ->orderBy('uploaded_at', 'desc')
                ->get()
                ->map(function ($upload) {
                    return [
                        'id' => $upload->id,
                        'file_name' => $upload->file_name,
                        'file_size' => $upload->file_size_bytes,
                        'file_size_formatted' => $this->formatBytes($upload->file_size_bytes),
                        'mime_type' => $upload->mime_type,
                        'attachment_type' => $upload->attachment_type,
                        'google_drive_file_id' => $upload->google_drive_file_id,
                        'google_drive_path' => $upload->google_drive_path,
                        'attachment_id' => $upload->server_attachment_id,
                        'uploaded_at' => $upload->uploaded_at?->format('Y-m-d H:i:s'),
                        'uploaded_by' => $upload->user?->name
                    ];
                });

            return response()->json([
                'success' => true,
                'entity_type' => $type,
                'entity_id' => $id,
                'count' => $uploads->count(),
                'uploads' => $uploads
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get entity uploads', [
                'error' => $e->getMessage(),
                'entity_type' => $type,
                'entity_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل جلب الملفات'
            ], 500);
        }
    }

    /**
     * GET /api/uploads/pending
     *
     * Get pending and failed uploads for retry
     */
    public function getPendingUploads(): JsonResponse
    {
        try {
            $pendingUploads = GoogleDriveUpload::whereIn('upload_status', [
                GoogleDriveUpload::STATUS_PENDING,
                GoogleDriveUpload::STATUS_FAILED
            ])
            ->where('retry_count', '<', 3)
            ->orderBy('created_at', 'asc')
            ->get();

            return response()->json([
                'success' => true,
                'count' => $pendingUploads->count(),
                'uploads' => $pendingUploads
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب الملفات المعلقة'
            ], 500);
        }
    }

    /**
     * POST /api/uploads/mark-failed
     *
     * Mark an upload as failed
     */
    public function markFailed(Request $request): JsonResponse
    {
        $request->validate([
            'file_hash' => 'required|string',
            'error_message' => 'required|string'
        ]);

        try {
            $upload = GoogleDriveUpload::where('local_file_hash', $request->file_hash)->first();

            if (!$upload) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف غير موجود'
                ], 404);
            }

            $upload->markAsFailed($request->error_message);

            Log::warning('Upload marked as failed', [
                'file_hash' => $request->file_hash,
                'error' => $request->error_message,
                'retry_count' => $upload->retry_count
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الفشل',
                'retry_count' => $upload->retry_count,
                'can_retry' => $upload->retry_count < 3
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تسجيل الحالة'
            ], 500);
        }
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
