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
     * حارس ضد اسم رفع خبيث. X-Upload-Id يُدرج مباشرةً في مسار نظام ملفات،
     * فبدون تعقيم يمكن الخروج من المجلد المقصود عبر ../
     */
    private function safeUploadId(?string $uploadId): ?string
    {
        if ($uploadId === null) {
            return null;
        }
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '', $uploadId);
        return ($clean === '' || strlen($clean) > 190) ? null : $clean;
    }

    /** مسار العلامة التي تُثبت أن هذه الرفعة جُمِّعت وسُلِّمت بالفعل. */
    private function markerPath(string $uploadId): string
    {
        return storage_path("app/chunks/_done/{$uploadId}.json");
    }

    /**
     * يقرأ علامة الاكتمال إن وُجدت.
     *
     * ⚠️ هذه العلامة هي إصلاح السبب الجذري الأول لتعليق الملفات الكبيرة:
     * كان التجميع يحذف مجلد الأجزاء بالكامل، ثم يُرجع uploadStatus() قائمة
     * أجزاء فارغة. فإذا انتهت مهلة العميل أثناء التجميع (وهو ما يحدث دائماً مع
     * الفيديو الكبير) اعتقد العميل أن الجزء فشل، فسأل عن الحالة، فوجد "لم يصل
     * شيء"، فأعاد رفع الفيديو كاملاً من البايت صفر — إلى ما لا نهاية.
     */
    private function readMarker(string $uploadId): ?array
    {
        $path = $this->markerPath($uploadId);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function writeMarker(string $uploadId, array $payload): void
    {
        $dir = dirname($this->markerPath($uploadId));
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // كتابة ذرّية: ملف مؤقت ثم rename، حتى لا يقرأ أحد علامة نصف مكتوبة.
        $tmp = $this->markerPath($uploadId) . '.tmp';
        if (@file_put_contents($tmp, json_encode($payload)) !== false) {
            @rename($tmp, $this->markerPath($uploadId));
        }
    }

    /**
     * Handle incoming octet-stream chunk from the mobile app.
     * The app sends chunks directly without init/complete steps.
     */
    public function handleChunk(Request $request)
    {
        $uploadId = $this->safeUploadId($request->header('X-Upload-Id'));

        try {
            $chunkIndex = (int) $request->header('X-Chunk-Index');
            $totalChunks = (int) $request->header('X-Total-Chunks');
            $fileName = $request->header('X-File-Name');
            $fileType = $request->header('X-File-Type', 'application/octet-stream');
            $sponsorshipId = $request->header('X-Sponsorship-Id');
            $declaredSize = (int) $request->header('X-Chunk-Size', 0);

            if (!$uploadId || $totalChunks <= 0 || !$fileName) {
                return response()->json(['error' => 'Missing or invalid required headers'], 400);
            }

            // إن كانت هذه الرفعة قد اكتملت سابقاً فالطلب مكرّر (العميل لم يستلم
            // ردّنا الأول بسبب انتهاء المهلة). نُعيد نفس النتيجة بدل إعادة
            // التجميع — وهذا ما يجعل العملية آمنة التكرار (idempotent).
            if ($marker = $this->readMarker($uploadId)) {
                return response()->json($marker['response'] ?? [
                    'success' => true,
                    'message' => 'سبق تجميع هذه الرفعة',
                    'sync_state' => 'processing',
                ]);
            }

            // Create temporary directory for this upload
            $chunkDir = storage_path("app/chunks/{$uploadId}");
            if (!file_exists($chunkDir)) {
                mkdir($chunkDir, 0755, true);
            }

            if ($request->hasFile('chunk')) {
                $chunkData = file_get_contents($request->file('chunk')->getRealPath());
            } else {
                $chunkData = file_get_contents('php://input');
            }

            if ($chunkData === false) {
                return response()->json(['error' => 'Failed to read chunk data'], 500);
            }

            // تحقّق من سلامة الجزء: العميل يُعلن حجمه في X-Chunk-Size.
            // بدون هذا يُقبل جزء مبتور بصمت ويُخزَّن ويُبلَّغ عنه كناجح، ثم
            // يُجمَّع ملف تالف ويُرفع إلى Drive.
            if ($declaredSize > 0 && strlen($chunkData) !== $declaredSize) {
                Log::warning('Chunk size mismatch', [
                    'upload_id' => $uploadId,
                    'chunk_index' => $chunkIndex,
                    'declared' => $declaredSize,
                    'actual' => strlen($chunkData),
                ]);
                // 408 = عابر؛ العميل سيعيد إرسال هذا الجزء وحده.
                return response()->json([
                    'error' => 'Chunk truncated in transit',
                    'expected' => $declaredSize,
                    'received' => strlen($chunkData),
                ], 408);
            }

            // كتابة ذرّية للجزء: بدونها يترك طلب انقطع في منتصفه ملفَ جزء مبتور
            // يبدو "مستلَماً" فيتخطّاه العميل عند الاستئناف.
            $chunkPath = "{$chunkDir}/chunk_{$chunkIndex}";
            $chunkTmp = $chunkPath . '.part';
            if (file_put_contents($chunkTmp, $chunkData) === false) {
                return response()->json(['error' => 'Failed to persist chunk'], 500);
            }
            rename($chunkTmp, $chunkPath);

            // Check if all chunks are received
            $receivedChunks = 0;
            for ($i = 0; $i < $totalChunks; $i++) {
                if (file_exists("{$chunkDir}/chunk_{$i}")) {
                    $receivedChunks++;
                }
            }

            $deviceId = $request->header('X-Device-Id', 'mobile_app');

            if ($receivedChunks === $totalChunks) {
                // قفل: طلبان متزامنان للجزء الأخير كانا يُجمّعان نفس الرفعة معاً
                // في نفس الملف المفتوح بوضع الإلحاق (ab) فيتضاعف المحتوى.
                $lock = \Illuminate\Support\Facades\Cache::lock("chunk_assembly:{$uploadId}", 900);

                if (!$lock->get()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'التجميع جارٍ بالفعل',
                        'sync_state' => 'processing',
                    ]);
                }

                try {
                    // فحص ثانٍ بعد الحصول على القفل.
                    if ($marker = $this->readMarker($uploadId)) {
                        return response()->json($marker['response'] ?? ['success' => true, 'sync_state' => 'processing']);
                    }
                    return $this->assembleAndUpload($uploadId, $totalChunks, $fileName, $fileType, $sponsorshipId, $deviceId);
                } finally {
                    optional($lock)->release();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk {$chunkIndex} received",
                'received_chunks' => $receivedChunks,
                'total_chunks' => $totalChunks
            ]);

        } catch (\Exception $e) {
            Log::error('Chunk upload error: ' . $e->getMessage(), [
                'upload_id' => $uploadId,
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

            // ⚠️ الاسم مشتق من upload_id لا من time().
            // time() يعطي اسماً مختلفاً في كل محاولة، فكل إعادة محاولة كانت
            // تُخلّف ملفاً كاملاً مهجوراً على القرص (رُصد ١٧٨ ملفاً مسرّباً).
            $safeFileName = $uploadId . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($fileName));
            $finalPath = "{$tempDir}/{$safeFileName}";

            // ⚠️ 'wb' لا 'ab'.
            // الإلحاق كان يعني أن محاولة تجميع ثانية تُضيف المحتوى فوق الأول
            // فينتج ملف مضاعف الحجم وتالف.
            $target = fopen($finalPath, 'wb');
            if ($target === false) {
                throw new \Exception("Could not open target file for writing");
            }

            try {
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkFile = "{$chunkDir}/chunk_{$i}";
                    if (!file_exists($chunkFile)) {
                        throw new \Exception("Missing chunk {$i}");
                    }

                    $source = fopen($chunkFile, 'rb');
                    if ($source === false) {
                        throw new \Exception("Could not read chunk {$i}");
                    }

                    // ⚠️ لا نحذف الأجزاء أثناء التجميع.
                    // الحذف أثناء الدوران كان يعني أن أي انهيار في المنتصف يُتلف
                    // نصف الأجزاء، فيصبح استئناف الرفعة مستحيلاً ويُجبَر العميل
                    // على إعادة رفع الفيديو كاملاً. الأجزاء تُحذف بعد النجاح فقط.
                    try {
                        while (!feof($source)) {
                            $buf = fread($source, 262144);
                            if ($buf === false) {
                                throw new \Exception("Read failure on chunk {$i}");
                            }
                            if ($buf !== '' && fwrite($target, $buf) === false) {
                                throw new \Exception("Write failure while assembling chunk {$i}");
                            }
                        }
                    } finally {
                        fclose($source);
                    }
                }
            } catch (\Throwable $t) {
                fclose($target);
                @unlink($finalPath);   // لا نترك ملفاً نصف مُجمَّع على القرص
                throw $t;
            }

            fclose($target);

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

            $payload = [
                'success' => true,
                'message' => 'Upload assembled successfully and queued for background processing.',
                'file_id' => 'queued',
                'status' => 'processing',
                'sync_state' => 'processing'
            ];

            // العلامة تُكتب قبل حذف الأجزاء: إن مات الطلب بين الاثنين نبقى في
            // حالة "مكتمل + أجزاء زائدة"، وهي غير مؤذية ويُنظّفها الكانس الدوري.
            // العكس (حذف ثم موت قبل العلامة) هو الذي كان يُنتج التعليق الأبدي.
            $this->writeMarker($uploadId, [
                'completed_at' => now()->toIso8601String(),
                'total_chunks' => $totalChunks,
                'file_name' => $fileName,
                'response' => $payload,
            ]);

            $this->purgeChunkDir($chunkDir);

            return response()->json($payload);

        } catch (\Exception $e) {
            Log::error('Assembly and upload error: ' . $e->getMessage(), [
                'upload_id' => $uploadId,
                'sponsorship_id' => $sponsorshipId
            ]);
            // الأجزاء ما تزال موجودة، فالمحاولة التالية ستستأنف لا تبدأ من الصفر.
            return response()->json(['error' => 'Assembly failed: ' . $e->getMessage()], 500);
        }
    }

    /** حذف مجلد الأجزاء بأمان بعد نجاح التجميع. */
    private function purgeChunkDir(string $chunkDir): void
    {
        if (!is_dir($chunkDir)) {
            return;
        }
        // ⚠️ rmdir على مجلد غير فارغ يُطلق تحذير PHP يُحوّله Laravel إلى استثناء
        // فيفشل الطلب بعد أن يكون الملف قد جُمِّع ورُفع فعلاً. نُفرّغه أولاً.
        foreach ((array) glob($chunkDir . '/*') as $leftover) {
            @unlink($leftover);
        }
        @rmdir($chunkDir);
    }

    /**
     * Get the status of an upload (which chunks have been received)
     *
     * هذه نقطة الاستئناف. صحّتها هي الفارق بين "يُكمل من حيث توقف" و"يبدأ
     * الفيديو من الصفر في كل مرة".
     */
    public function uploadStatus($uploadId)
    {
        $uploadId = $this->safeUploadId($uploadId);
        if (!$uploadId) {
            return response()->json(['error' => 'Invalid upload id'], 400);
        }

        // ✅ الحالة الأهم: الرفعة اكتملت بالفعل.
        // نُرجع كل الفهارس كمُستلَمة، فيتخطّى العميل — القديم والجديد — كل
        // الأجزاء ويعتبر الملف مرفوعاً، بدل إعادة رفع الفيديو كاملاً.
        if ($marker = $this->readMarker($uploadId)) {
            $total = (int) ($marker['total_chunks'] ?? 0);
            return response()->json([
                'upload_id' => $uploadId,
                'completed' => true,
                'sync_state' => 'processing',
                'total_chunks' => $total,
                'received_chunks' => $total > 0 ? range(0, $total - 1) : [],
            ]);
        }

        $chunkDir = storage_path("app/chunks/{$uploadId}");
        $receivedChunks = [];

        if (is_dir($chunkDir)) {
            foreach ((array) scandir($chunkDir) as $file) {
                if (preg_match('/^chunk_(\d+)$/', $file, $matches)) {
                    $receivedChunks[] = (int) $matches[1];
                }
            }
            sort($receivedChunks);
        }

        // ⚠️ إسقاط أعلى فهرس — يبقى ضرورياً، ولا يجوز حذفه.
        //
        // التجميع لا يُطلَق إلا داخل طلب جزء. فلو أعدنا للعميل قائمة كاملة
        // بكل الأجزاء الموجودة دون وجود علامة اكتمال، لتخطّى العميل كل شيء
        // واعتبر الملف مرفوعاً — بينما التجميع لم يجرِ أبداً، فيبقى الملف على
        // الخادم بلا رفع إلى Drive إلى الأبد. هذا ينطبق خصوصاً على نسخ
        // التطبيق القديمة التي ما تزال في الميدان ولا تفهم حقل completed.
        //
        // الكلفة جزء واحد يُعاد إرساله عند الاستئناف فقط، وهي كلفة مقبولة —
        // والحالة الشائعة (اكتمل التجميع فعلاً) تُختصر عبر العلامة أعلاه بلا
        // إعادة إرسال أي شيء.
        if (!empty($receivedChunks)) {
            $maxChunk = max($receivedChunks);
            $receivedChunks = array_values(array_filter(
                $receivedChunks,
                fn ($c) => $c !== $maxChunk
            ));
        }

        return response()->json([
            'upload_id' => $uploadId,
            'completed' => false,
            'received_chunks' => array_values($receivedChunks)
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
