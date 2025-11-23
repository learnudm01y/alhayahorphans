<?php

namespace App\Http\Controllers;

use App\Services\DuplicateFileDetectionService;
use App\Models\DuplicateFileTemp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DuplicateFileController extends Controller
{
    protected $duplicateDetectionService;

    public function __construct(DuplicateFileDetectionService $duplicateDetectionService)
    {
        $this->duplicateDetectionService = $duplicateDetectionService;

        // إزالة جميع middleware للاختبار
        // $this->middleware('auth')->except([
        //     'getDuplicateFilesSummary',
        //     'downloadDuplicateFiles',
        //     'deleteDuplicateFiles',
        //     'processFolderForDuplicates'
        // ]);
    }

    /**
     * Process folder upload and detect duplicates
     */
    public function processFolderForDuplicates(Request $request)
    {
        // رسالة debug فورية لتأكيد وصول الطلب
        error_log('=== DUPLICATE CONTROLLER REACHED ===');
        Log::emergency('DUPLICATE CONTROLLER REACHED - REQUEST RECEIVED');

        // رسائل debug أساسية لتتبع البيانات الواردة
        Log::info('=== START: processFolderForDuplicates ===');
        Log::info('Files received:', [
            'has_files' => $request->hasFile('files'),
            'files_count' => $request->hasFile('files') ? count($request->file('files')) : 0,
            'folder_name' => $request->input('folder_name')
        ]);

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file',
            'folder_name' => 'required|string|max:255'
        ]);

        try {
            $files = $request->file('files');
            $folderName = $request->input('folder_name');

            Log::info('Validation passed, processing files:', [
                'files_count' => count($files),
                'folder_name' => $folderName
            ]);

            // بدء جلسة كشف الملفات المكررة
            $sessionId = $this->duplicateDetectionService->initializeSession();

            // معالجة الملفات للبحث عن المكررة
            $results = $this->duplicateDetectionService->processFolderForDuplicates($files, $folderName);

            Log::info('Folder processed for duplicates', [
                'session_id' => $sessionId,
                'folder_name' => $folderName,
                'total_files' => $results['total_files'],
                'duplicates_found' => $results['duplicates_found']
            ]);

            return response()->json([
                'success' => true,
                'message' => $this->generateResultMessage($results),
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing folder for duplicates', [
                'error' => $e->getMessage(),
                'folder_name' => $request->input('folder_name', 'unknown')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة المجلد: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get duplicate files summary for a session
     */
    public function getDuplicateSummary(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $duplicates = $this->duplicateDetectionService->getDuplicateFiles($sessionId);

            if (empty($duplicates['files'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'لا توجد ملفات مكررة لهذه الجلسة',
                    'data' => $duplicates
                ]);
            }

            // إضافة روابط التحميل للملفات
            foreach ($duplicates['files'] as &$file) {
                if ($file['file_exists']) {
                    $file['download_url'] = route('admin.files.download-duplicate', [
                        'session_id' => $sessionId,
                        'file_id' => $file['id']
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "تم العثور على {$duplicates['total_duplicates']} ملف مكرر",
                'data' => $duplicates
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting duplicate summary', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب قائمة الملفات المكررة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a specific duplicate file
     */
    public function downloadDuplicateFile(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'file_id' => 'required|integer'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $fileId = $request->input('file_id');

            $duplicate = DuplicateFileTemp::bySession($sessionId)
                ->where('id', $fileId)
                ->notExpired()
                ->first();

            if (!$duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف المطلوب غير موجود أو انتهت صلاحيته'
                ], 404);
            }

            if (!File::exists($duplicate->temp_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف المطلوب غير موجود في التخزين'
                ], 404);
            }

            $headers = [
                'Content-Type' => File::mimeType($duplicate->temp_path),
                'Content-Disposition' => 'attachment; filename="' . $duplicate->original_name . '"',
            ];

            return response()->download($duplicate->temp_path, $duplicate->original_name, $headers);

        } catch (\Exception $e) {
            Log::error('Error downloading duplicate file', [
                'session_id' => $request->input('session_id'),
                'file_id' => $request->input('file_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحميل الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download all duplicate files as ZIP
     */
    public function downloadDuplicateFiles(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');

            // تسجيل محاولة التنزيل
            Log::info('Download request received', [
                'session_id' => $sessionId,
                'request_ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            if (!$sessionId) {
                Log::warning('Download failed: missing session_id');
                return response()->json([
                    'success' => false,
                    'message' => 'Session ID مطلوب'
                ], 400);
            }

            // إنشاء ZIP file
            $zipPath = $this->duplicateDetectionService->createDuplicatesZip($sessionId);

            if (!$zipPath || !file_exists($zipPath)) {
                Log::warning('Download failed: ZIP creation failed', [
                    'session_id' => $sessionId,
                    'zip_path' => $zipPath
                ]);

                // إنشاء ملف ZIP بديل للاختبار
                $fallbackZipPath = $this->createFallbackZip($sessionId);
                if ($fallbackZipPath && file_exists($fallbackZipPath)) {
                    Log::info('Using fallback ZIP file', ['path' => $fallbackZipPath]);
                    return response()->download($fallbackZipPath, 'duplicate_files_' . $sessionId . '.zip')->deleteFileAfterSend(true);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ملفات مكررة للتحميل أو فشل في إنشاء الملف المضغوط'
                ], 404);
            }

            Log::info('ZIP file created successfully', [
                'session_id' => $sessionId,
                'zip_path' => $zipPath,
                'file_size' => filesize($zipPath)
            ]);

            return response()->download($zipPath, 'duplicate_files_' . $sessionId . '.zip')->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error downloading duplicate files', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحميل الملفات المكررة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a fallback ZIP file for testing purposes
     */
    private function createFallbackZip(string $sessionId): ?string
    {
        try {
            $zipFileName = 'fallback_duplicate_files_' . $sessionId . '_' . time() . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);

            // إنشاء مجلد temp إذا لم يكن موجوداً
            if (!file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new \ZipArchive();

            if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
                return null;
            }

            // إضافة ملف معلومات
            $infoContent = "معلومات الجلسة\n";
            $infoContent .= "================\n\n";
            $infoContent .= "Session ID: $sessionId\n";
            $infoContent .= "تاريخ الإنشاء: " . Carbon::now()->format('Y-m-d H:i:s') . "\n";
            $infoContent .= "الوضع: ملف اختبار (لا توجد ملفات مكررة)\n\n";
            $infoContent .= "هذا الملف تم إنشاؤه تلقائياً لاختبار وظيفة التنزيل.\n";
            $infoContent .= "إذا كان هناك ملفات مكررة فعلية، ستظهر هنا.\n";

            $zip->addFromString('session_info.txt', $infoContent);

            // إضافة ملف JSON بمعلومات تقنية
            $technicalInfo = [
                'session_id' => $sessionId,
                'created_at' => Carbon::now()->toISOString(),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'zip_support' => class_exists('ZipArchive'),
                    'storage_path' => storage_path('app/temp')
                ],
                'download_info' => [
                    'endpoint' => '/api/duplicate-files/download',
                    'method' => 'GET',
                    'content_type' => 'application/octet-stream'
                ]
            ];

            $zip->addFromString('technical_info.json', json_encode($technicalInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $zip->close();

            return $zipPath;

        } catch (\Exception $e) {
            Log::error('Error creating fallback ZIP', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete duplicate files for a session
     */
    public function deleteDuplicateFiles(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');

            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session ID مطلوب'
                ], 400);
            }

            $deleted = $this->duplicateDetectionService->deleteDuplicateFiles($sessionId);

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$deleted} ملف مكرر بنجاح",
                'data' => [
                    'deleted_files' => $deleted,
                    'deleted_records' => $deleted
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting duplicate files', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الملفات المكررة'
            ], 500);
        }
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $stats = $this->duplicateDetectionService->getSessionStatistics($sessionId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات الجلسة بنجاح',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting session statistics', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean expired duplicate files (Admin function)
     */
    public function cleanExpiredFiles()
    {
        try {
            $result = $this->duplicateDetectionService->cleanExpiredFiles();

            $message = "تم تنظيف {$result['deleted_files']} ملف و {$result['deleted_records']} سجل منتهي الصلاحية";

            if (!empty($result['errors'])) {
                $message .= " مع " . count($result['errors']) . " خطأ";
            }

            Log::info('Expired files cleaned', $result);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error cleaning expired files', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تنظيف الملفات منتهية الصلاحية: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check single file for duplicate
     */
    public function checkSingleFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'folder_name' => 'nullable|string|max:255'
        ]);

        try {
            $file = $request->file('file');
            $folderName = $request->input('folder_name', 'single_check');

            $result = $this->duplicateDetectionService->checkFileForDuplicate($file, $folderName);

            $message = $result['is_duplicate']
                ? "الملف مكرر - يوجد ملف مماثل: {$result['existing_file_name']}"
                : "الملف غير مكرر";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking single file', [
                'file_name' => $request->file('file')->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء فحص الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get duplicate files summary for a session
     */
    public function getDuplicateFilesSummary(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');

            // إذا لم يتم تمرير session_id، جلب جميع الملفات المكررة من قاعدة البيانات
            if (!$sessionId) {
                try {
                    $duplicateFiles = DB::table('duplicate_files_temp')
                        ->select('id', 'original_name', 'duplicate_name', 'file_size', 'temp_path', 'created_at')
                        ->orderBy('created_at', 'desc')
                        ->get();

                    if ($duplicateFiles->isEmpty()) {
                        return response()->json([
                            'success' => true,
                            'data' => [],
                            'message' => 'لا توجد ملفات مكررة'
                        ]);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => $duplicateFiles
                    ]);

                } catch (\Exception $e) {
                    Log::error('Error fetching duplicate files from database', [
                        'error' => $e->getMessage()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'خطأ في جلب الملفات المكررة من قاعدة البيانات'
                    ], 500);
                }
            }

            $summary = $this->duplicateDetectionService->getDuplicateFilesSummary($sessionId);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting duplicate files summary', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب ملخص الملفات المكررة'
            ], 500);
        }
    }

    /**
     * Generate result message based on processing results
     */
    private function generateResultMessage(array $results): string
    {
        $total = $results['total_files'];
        $duplicates = $results['duplicates_found'];
        $saved = $results['files_saved'] ?? 0;
        $errors = count($results['errors']);

        $messages = [];

        if ($saved > 0) {
            $messages[] = "تم حفظ {$saved} ملف جديد بنجاح";
        }

        if ($duplicates > 0) {
            $messages[] = "تم العثور على {$duplicates} ملف مكرر";
        }

        if ($errors > 0) {
            $messages[] = "فشل في معالجة {$errors} ملف";
        }

        if (empty($messages)) {
            return "تمت المعالجة بنجاح - {$total} ملف";
        }

        $result = implode(' | ', $messages);
        return "{$result} من أصل {$total} ملف";
    }

    /**
     * Get duplicate file info for comparison modal
     */
    public function getDuplicateFileInfo($fileId)
    {
        try {
            Log::info('Getting duplicate file info', ['file_id' => $fileId]);

            // البحث عن الملف في جدول الملفات المؤقتة المكررة
            $duplicateFile = DuplicateFileTemp::where('id', $fileId)
                ->orWhere('original_name', 'LIKE', "%{$fileId}%")
                ->first();

            if (!$duplicateFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على معلومات الملف المكرر'
                ], 404);
            }

            // جلب معلومات الملف القديم من جدول attachments
            $oldFile = DB::table('attachments')
                ->where('hash', $duplicateFile->hash)
                ->orWhere('file_path', 'LIKE', "%{$duplicateFile->original_name}%")
                ->first();

            if (!$oldFile) {
                // محاولة البحث بالـ hash في جدول الملفات المؤقتة
                $oldFile = DuplicateFileTemp::where('hash', $duplicateFile->hash)
                    ->where('id', '!=', $fileId)
                    ->orderBy('created_at', 'asc')
                    ->first();
            }

            if (!$oldFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على الملف القديم في النظام'
                ], 404);
            }

            // تحضير URL للملف القديم
            $oldFileUrl = null;
            if (isset($oldFile->stored_file_name)) {
                $oldFileUrl = route('admin.file.show', ['filename' => $oldFile->stored_file_name]);
            } elseif (isset($oldFile->file_path)) {
                $oldFileUrl = asset('storage/' . $oldFile->file_path);
            }

            return response()->json([
                'success' => true,
                'oldFile' => [
                    'id' => $oldFile->id ?? null,
                    'original_name' => $oldFile->original_name ?? $oldFile->file_name ?? 'غير متوفر',
                    'file_size' => $oldFile->file_size ?? null,
                    'mime_type' => $oldFile->mime_type ?? $oldFile->file_type ?? null,
                    'hash' => $oldFile->hash ?? null,
                    'url' => $oldFileUrl,
                    'created_at' => $oldFile->created_at ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting duplicate file info', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب معلومات الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Replace duplicate file - استبدال الملف القديم بالجديد
     */
    public function replaceDuplicateFile(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'old_file_name' => 'required|string',
                'old_file_path' => 'required|string'
            ]);

            Log::info('Replacing duplicate file', [
                'old_file_name' => $request->old_file_name,
                'old_file_path' => $request->old_file_path
            ]);

            // استخراج folder_id من old_file_path (مثال: uploads/123/file.jpg -> 123)
            $pathParts = explode('/', $request->old_file_path);
            $folderId = null;

            // البحث عن رقم المجلد في المسار
            if (count($pathParts) >= 2 && $pathParts[0] === 'uploads') {
                $folderId = $pathParts[1];
            }

            if (!$folderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تحديد المجلد من المسار المعطى'
                ], 400);
            }

            // البحث عن الملف القديم في جدول attachments
            $oldFile = DB::table('attachments')
                ->where('stored_file_name', $request->old_file_name)
                ->where('file_path', $request->old_file_path)
                ->first();

            $tableName = 'attachments';

            // إذا لم يوجد في attachments، ابحث في enhanced_attachments
            if (!$oldFile) {
                $oldFile = DB::table('enhanced_attachments')
                    ->where('stored_file_name', $request->old_file_name)
                    ->where('file_path', $request->old_file_path)
                    ->first();
                $tableName = 'enhanced_attachments';
            }

            if (!$oldFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على الملف القديم في قاعدة البيانات'
                ], 404);
            }

            // التحقق من وجود الملف الفعلي في التخزين
            $oldFilePath = storage_path('app/public/' . $request->old_file_path);
            if (!File::exists($oldFilePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف القديم غير موجود في التخزين: ' . $request->old_file_path
                ], 404);
            }

            // رفع الملف الجديد
            $newFile = $request->file('file');

            // استخدام نفس اسم الملف القديم
            $newFileName = $request->old_file_name;
            $uploadPath = "uploads/{$folderId}";

            // حذف الملف القديم من التخزين أولاً
            File::delete($oldFilePath);
            Log::info('Old file deleted from storage', ['path' => $oldFilePath]);

            // رفع الملف الجديد في نفس المكان بنفس الاسم
            $newFilePath = $newFile->storeAs($uploadPath, $newFileName, 'public');
            Log::info('New file uploaded to replace old file', [
                'new_path' => $newFilePath,
                'folder_id' => $folderId
            ]);

            // تحديث السجل في قاعدة البيانات
            DB::table($tableName)
                ->where('id', $oldFile->id)
                ->update([
                    'stored_file_name' => $newFileName,
                    'file_path' => $newFilePath,
                    'updated_at' => now()
                ]);

            Log::info('File replaced successfully', [
                'old_file_id' => $oldFile->id,
                'new_file_name' => $newFileName,
                'new_file_path' => $newFilePath,
                'table' => $tableName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم استبدال الملف بنجاح',
                'new_file' => [
                    'id' => $oldFile->id,
                    'name' => $newFileName,
                    'path' => $newFilePath
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error replacing duplicate file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء استبدال الملف: ' . $e->getMessage()
            ], 500);
        }
    }
}

