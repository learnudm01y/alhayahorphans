<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\GoogleDriveService;
use App\Models\Attachment;
use App\Models\GoogleDriveUpload;

class ChunkedUploadController extends Controller
{
    /**
     * Handle incoming octet-stream chunk from the mobile app.
     * The app sends chunks directly without init/complete steps.
     */
    public function handleChunk(Request $request)
    {
        try {
            $uploadId = $request->header('X-Upload-Id');
            $chunkIndex = (int) $request->header('X-Chunk-Index');
            $totalChunks = (int) $request->header('X-Total-Chunks');
            $fileName = $request->header('X-File-Name');
            $fileType = $request->header('X-File-Type', 'application/octet-stream');
            $sponsorshipId = $request->header('X-Sponsorship-Id');

            if (!$uploadId || $totalChunks <= 0 || !$fileName) {
                return response()->json(['error' => 'Missing required headers'], 400);
            }

            // Create temporary directory for this upload
            $chunkDir = storage_path("app/chunks/{$uploadId}");
            if (!file_exists($chunkDir)) {
                mkdir($chunkDir, 0755, true);
            }

            // Save the raw chunk data
            $chunkPath = "{$chunkDir}/chunk_{$chunkIndex}";
            
            if ($request->hasFile('chunk')) {
                $chunkData = file_get_contents($request->file('chunk')->getRealPath());
            } else {
                $chunkData = file_get_contents('php://input');
            }
            
            if ($chunkData === false) {
                return response()->json(['error' => 'Failed to read chunk data'], 500);
            }
            
            file_put_contents($chunkPath, $chunkData);

            // Check if all chunks are received
            $receivedChunks = 0;
            for ($i = 0; $i < $totalChunks; $i++) {
                if (file_exists("{$chunkDir}/chunk_{$i}")) {
                    $receivedChunks++;
                }
            }

            $deviceId = $request->header('X-Device-Id', 'mobile_app');

            if ($receivedChunks === $totalChunks) {
                // All chunks received, assemble the file
                return $this->assembleAndUpload($uploadId, $totalChunks, $fileName, $fileType, $sponsorshipId, $deviceId);
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk {$chunkIndex} received",
                'received_chunks' => $receivedChunks,
                'total_chunks' => $totalChunks
            ]);

        } catch (\Exception $e) {
            Log::error('Chunk upload error: ' . $e->getMessage(), [
                'upload_id' => $request->header('X-Upload-Id'),
                'chunk_index' => $request->header('X-Chunk-Index')
            ]);
            return response()->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Assemble the chunks and upload directly via Rclone
     */
    private function assembleAndUpload($uploadId, $totalChunks, $fileName, $fileType, $sponsorshipId, $deviceId)
    {
        try {
            $chunkDir = storage_path("app/chunks/{$uploadId}");
            $tempDir = storage_path("app/temp_uploads");
            
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $safeFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($fileName));
            $finalPath = "{$tempDir}/{$safeFileName}";
            
            $target = fopen($finalPath, 'ab');
            if ($target === false) {
                throw new \Exception("Could not open target file for writing");
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = "{$chunkDir}/chunk_{$i}";
                if (!file_exists($chunkFile)) {
                    fclose($target);
                    throw new \Exception("Missing chunk {$i}");
                }

                $source = fopen($chunkFile, 'rb');
                if ($source) {
                    while (!feof($source)) {
                        fwrite($target, fread($source, 8192));
                    }
                    fclose($source);
                    unlink($chunkFile);
                }
            }
            
            fclose($target);
            rmdir($chunkDir);

            // Fetch sponsorship to determine the folder structure
            $sponsorship = DB::table('sponsorships')
                ->leftJoin('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
                ->select('sponsorships.*', 'sponsors.sponsor_name')
                ->where('sponsorships.id', $sponsorshipId)
                ->first();

            $associationName = $sponsorship ? ($sponsorship->sponsor_name ?? 'General') : 'General';
            $orphanName = $sponsorship ? ($sponsorship->orphan_name ?? 'Unknown_' . $sponsorshipId) : 'Unknown_' . $sponsorshipId;
            
            $fileSize = filesize($finalPath);

            // Determine Attachment Type
            $attachmentType = 'document';
            if (strpos($fileType, 'image/') === 0) {
                $attachmentType = 'photo';
            } elseif (strpos($fileType, 'video/') === 0) {
                $attachmentType = 'video';
            }

            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $documentTypeName = pathinfo($fileName, PATHINFO_FILENAME);
            $fileHash = hash_file('sha256', $finalPath);

            // Check if this exact file was already uploaded or is currently uploading
            $existingUpload = GoogleDriveUpload::where('local_file_hash', $fileHash)
                ->where('entity_type', 'sponsorship')
                ->where('entity_id', $sponsorshipId)
                ->first();

            if ($existingUpload) {
                // Delete the redundant newly assembled file
                if (file_exists($finalPath)) {
                    @unlink($finalPath);
                }

                if ($existingUpload->upload_status === 'completed') {
                    return response()->json([
                        'success' => true,
                        'message' => 'تم رفعه مسبقاً',
                        'file_id' => $existingUpload->google_drive_file_id,
                        'sync_state' => 'uploaded_to_drive'
                    ]);
                } else {
                    // It's in 'failed', 'uploading', or 'pending' state.
                    // If the mobile app is re-uploading it, it means it's stuck. Let's force a retry!
                    $existingUpload->update([
                        'upload_status' => 'uploading',
                        'upload_progress' => 100,
                        'retry_count' => 0
                    ]);

                    \App\Jobs\ProcessRcloneUploadJob::dispatch(
                        $existingUpload->server_attachment_id,
                        $existingUpload->id,
                        $existingUpload->local_file_path,
                        $associationName,
                        $orphanName,
                        $documentTypeName,
                        $extension
                    );

                    return response()->json([
                        'success' => true,
                        'message' => 'تم إعادة جدولة رفع الملف',
                        'sync_state' => 'processing'
                    ]);
                }
            }

            // Create Attachment Record as Pending
            $attachment = Attachment::create([
                'file_name' => $fileName,
                'stored_file_name' => $fileName,
                'file_path' => 'pending_rclone_upload',
                'file_size' => $fileSize,
                'mime_type' => $fileType,
                'attachment_type' => $attachmentType,
                'description' => 'Uploaded via Chunked Mobile App (Queued for Rclone)',
                'entity_type' => 'sponsorship',
                'entity_id' => $sponsorshipId,
                'storage_type' => 'google_drive',
                'google_drive_file_id' => null,
                'google_drive_path' => null,
                'uploaded_by' => auth()->id() ?? 1,
                'is_synced' => false
            ]);

            // Create Google Drive Upload Record as Processing
            $googleUpload = GoogleDriveUpload::create([
                'local_file_hash' => hash_file('sha256', $finalPath),
                'local_file_path' => $finalPath,
                'google_drive_file_id' => null,
                'google_drive_path' => null,
                'file_name' => $fileName,
                'file_size_bytes' => $fileSize,
                'mime_type' => $fileType,
                'device_id' => $deviceId,
                'upload_status' => 'uploading',
                'upload_progress' => 100,
                'entity_type' => 'sponsorship',
                'entity_id' => $sponsorshipId,
                'attachment_type' => $attachmentType,
                'server_attachment_id' => $attachment->id,
                'synced_to_server' => false,
                'uploaded_by' => auth()->id() ?? 1,
                'uploaded_at' => now(),
            ]);

            // Dispatch Background Job to run Rclone
            \App\Jobs\ProcessRcloneUploadJob::dispatch(
                $attachment->id,
                $googleUpload->id,
                $finalPath,
                $associationName,
                $orphanName,
                $documentTypeName,
                $extension
            );

            return response()->json([
                'success' => true,
                'message' => 'Upload assembled successfully and queued for background processing.',
                'file_id' => 'queued',
                'status' => 'processing',
                'sync_state' => 'processing'
            ]);

        } catch (\Exception $e) {
            Log::error('Assembly and upload error: ' . $e->getMessage(), [
                'upload_id' => $uploadId,
                'sponsorship_id' => $sponsorshipId
            ]);
            return response()->json(['error' => 'Assembly failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get the status of an upload (which chunks have been received)
     */
    public function uploadStatus($uploadId)
    {
        $chunkDir = storage_path("app/chunks/{$uploadId}");
        $receivedChunks = [];

        if (file_exists($chunkDir) && is_dir($chunkDir)) {
            $files = scandir($chunkDir);
            foreach ($files as $file) {
                if (preg_match('/^chunk_(\d+)$/', $file, $matches)) {
                    $receivedChunks[] = (int) $matches[1];
                }
            }

            // If we have chunks, remove the highest chunk index to force the mobile app
            // to re-upload it. This ensures that the assembly logic (which triggers on the last chunk)
            // will run again if a previous assembly attempt crashed or failed.
            if (!empty($receivedChunks)) {
                $maxChunk = max($receivedChunks);
                $receivedChunks = array_values(array_filter($receivedChunks, function($c) use ($maxChunk) {
                    return $c !== $maxChunk;
                }));
            }
        }

        return response()->json([
            'upload_id' => $uploadId,
            'received_chunks' => $receivedChunks
        ]);
    }

    /**
     * Retry Rclone upload directly without re-uploading chunks from mobile.
     */
    public function retryRcloneUpload(Request $request)
    {
        try {
            $fileName = $request->input('fileName');

            if (!$fileName) {
                return response()->json(['error' => 'Missing fileName'], 400);
            }

            // Find the most recent failed GoogleDriveUpload for this file name
            $upload = GoogleDriveUpload::where('file_name', $fileName)
                ->orderBy('id', 'desc')
                ->first();

            if (!$upload) {
                return response()->json(['error' => 'File not found on server'], 404);
            }

            if ($upload->upload_status === 'completed') {
                return response()->json(['success' => true, 'message' => 'File is already uploaded.']);
            }

            if (!file_exists($upload->local_file_path)) {
                return response()->json(['error' => 'Local file on server was deleted. Full re-upload required.'], 404);
            }

            // Update status
            $upload->update([
                'upload_status' => 'uploading',
                'retry_count' => 0
            ]);

            // We need attachment to get its info
            $attachment = Attachment::find($upload->server_attachment_id);
            if (!$attachment) {
                return response()->json(['error' => 'Attachment record missing'], 500);
            }

            // Fetch sponsorship
            $sponsorship = DB::table('sponsorships')
                ->leftJoin('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
                ->select('sponsorships.*', 'sponsors.sponsor_name')
                ->where('sponsorships.id', $upload->entity_id)
                ->first();

            $associationName = $sponsorship ? ($sponsorship->sponsor_name ?? 'General') : 'General';
            $orphanName = $sponsorship ? ($sponsorship->orphan_name ?? 'Unknown_' . $upload->entity_id) : 'Unknown_' . $upload->entity_id;
            
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $documentTypeName = pathinfo($fileName, PATHINFO_FILENAME);

            \App\Jobs\ProcessRcloneUploadJob::dispatch(
                $attachment->id,
                $upload->id,
                $upload->local_file_path,
                $associationName,
                $orphanName,
                $documentTypeName,
                $extension
            );

            return response()->json(['success' => true, 'message' => 'Rclone background job re-dispatched.']);
        } catch (\Exception $e) {
            Log::error('Retry Rclone error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }
}
