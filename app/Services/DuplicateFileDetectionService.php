<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\DuplicateFileTemp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DuplicateFileDetectionService
{
    protected $tempStoragePath;
    protected $sessionId;

    public function __construct()
    {
        $this->tempStoragePath = Config::get('duplicate_detection.storage.temp_path', storage_path('app/temp'));
        $this->ensureTempDirectoryExists();
    }

    /**
     * Initialize detection session
     */
    public function initializeSession(): string
    {
        $this->sessionId = 'dup_' . time() . '_' . Str::random(8);
        Log::info('Duplicate detection session initialized', ['session_id' => $this->sessionId]);
        return $this->sessionId;
    }

    /**
     * Set existing session ID
     */
    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Check and process uploaded folder files for duplicates
     */
    public function processFolderForDuplicates(array $files, string $folderName): array
    {
        if (!$this->sessionId) {
            $this->initializeSession();
        }

        $results = [
            'total_files' => count($files),
            'duplicates_found' => 0,
            'files_saved' => 0,
            'processed_files' => 0,
            'errors' => [],
            'session_id' => $this->sessionId,
            'duplicate_files' => [],
            'saved_files' => []
        ];

        Log::info('Starting duplicate detection for folder', [
            'folder_name' => $folderName,
            'total_files' => count($files),
            'session_id' => $this->sessionId
        ]);

        foreach ($files as $file) {
            try {
                $duplicateInfo = $this->checkFileForDuplicate($file, $folderName);

                if ($duplicateInfo['is_duplicate']) {
                    $results['duplicates_found']++;
                    $results['duplicate_files'][] = $duplicateInfo;

                    Log::info('Duplicate file detected', [
                        'file_name' => $file->getClientOriginalName(),
                        'existing_file' => $duplicateInfo['existing_file_name'],
                        'session_id' => $this->sessionId
                    ]);
                } else {
                    // حفظ الملف الغير مكرر في قاعدة البيانات و storage
                    $savedFile = $this->saveNonDuplicateFile($file, $folderName);
                    if ($savedFile) {
                        $results['files_saved']++;
                        $results['saved_files'][] = $savedFile;

                        Log::info('Non-duplicate file saved', [
                            'file_name' => $file->getClientOriginalName(),
                            'saved_path' => $savedFile['path'],
                            'attachment_id' => $savedFile['attachment_id'],
                            'session_id' => $this->sessionId
                        ]);
                    }
                }

                $results['processed_files']++;

            } catch (\Exception $e) {
                $error = [
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
                $results['errors'][] = $error;

                Log::error('Error processing file for duplicates', [
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'session_id' => $this->sessionId
                ]);
            }
        }

        Log::info('Duplicate detection completed', [
            'session_id' => $this->sessionId,
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Check if a single file is a duplicate
     */
    public function checkFileForDuplicate(UploadedFile $file, string $folderName): array
    {
        try {
            // تحضير اسم الملف للمقارنة
            $originalFileName = $file->getClientOriginalName();

            // التحقق من صحة اسم الملف
            if (empty($originalFileName)) {
                throw new \InvalidArgumentException('اسم الملف فارغ أو غير صحيح');
            }

            $preparedFileName = $this->prepareFileNameForComparison($originalFileName);

            // البحث عن الملف في جدول attachments
            $existingFile = $this->findExistingFile($preparedFileName);

            $isDuplicate = !is_null($existingFile);

        $result = [
            'is_duplicate' => $isDuplicate,
            'original_name' => $originalFileName,
            'prepared_name' => $preparedFileName,
            'existing_file_name' => $existingFile ? $existingFile->file_name : null,
            'existing_file_id' => $existingFile ? $existingFile->id : null,
            'folder_name' => $folderName
        ];

        // إذا كان الملف مكرراً، احفظه في الجدول المؤقت ونقله للمجلد المؤقت
        if ($isDuplicate) {
            $result = array_merge($result, $this->handleDuplicateFile($file, $folderName, $existingFile, $preparedFileName));
        }

        return $result;

        } catch (\Exception $e) {
            Log::error('Error checking file for duplicate', [
                'file_name' => $originalFileName ?? 'unknown',
                'folder_name' => $folderName,
                'error' => $e->getMessage()
            ]);

            // إرجاع نتيجة افتراضية في حالة الخطأ
            return [
                'is_duplicate' => false,
                'original_name' => $originalFileName ?? 'unknown',
                'prepared_name' => '',
                'existing_file_name' => null,
                'existing_file_id' => null,
                'folder_name' => $folderName,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle duplicate file - save to temp table and move to temp storage
     */
    protected function handleDuplicateFile(UploadedFile $file, string $folderName, $existingFile, string $preparedFileName): array
    {
        try {
            // إنشاء اسم ملف فريد للتخزين المؤقت
            $tempFileName = $this->generateTempFileName($file);
            $tempFilePath = $this->tempStoragePath . '/' . $this->sessionId . '/' . $tempFileName;

            // إنشاء مجلد الجلسة إذا لم يكن موجوداً
            $sessionTempPath = $this->tempStoragePath . '/' . $this->sessionId;
            if (!File::exists($sessionTempPath)) {
                File::makeDirectory($sessionTempPath, 0755, true);
            }

            // نقل الملف إلى التخزين المؤقت
            $file->move($sessionTempPath, $tempFileName);

            // حفظ المعلومات في جدول duplicate_files_temp
            $duplicateRecord = DuplicateFileTemp::create([
                'session_id' => $this->sessionId,
                'original_name' => $file->getClientOriginalName(),
                'duplicate_name' => $preparedFileName,
                'temp_path' => $tempFilePath,
                'original_folder' => $folderName,
                'target_folder' => $this->sessionId,
                'existing_file_name' => $existingFile->file_name,
                'created_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(Config::get('duplicate_detection.session.expire_days', 7)) // انتهاء الصلاحية من التكوين
            ]);

            Log::info('Duplicate file saved to temp storage', [
                'session_id' => $this->sessionId,
                'original_name' => $file->getClientOriginalName(),
                'temp_path' => $tempFilePath,
                'duplicate_record_id' => $duplicateRecord->id
            ]);

            return [
                'temp_file_name' => $tempFileName,
                'temp_file_path' => $tempFilePath,
                'duplicate_record_id' => $duplicateRecord->id,
                'expires_at' => $duplicateRecord->expires_at->toDateTimeString()
            ];

        } catch (\Exception $e) {
            Log::error('Error handling duplicate file', [
                'session_id' => $this->sessionId,
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('فشل في معالجة الملف المكرر: ' . $e->getMessage());
        }
    }

    /**
     * Prepare file name for comparison (normalize the name)
     */
    protected function prepareFileNameForComparison(?string $fileName): string
    {
        // التحقق من أن اسم الملف ليس null أو فارغ
        if (empty($fileName)) {
            return '';
        }

        // إزالة المسار إذا كان موجوداً
        $fileName = basename($fileName);

        // تحويل إلى أحرف صغيرة
        $fileName = strtolower($fileName);

        // إزالة المسافات الزائدة
        $fileName = trim($fileName);

        // استبدال المسافات بشرطات سفلية
        $fileName = str_replace(' ', '_', $fileName);

        // إزالة الأحرف الخاصة (الاحتفاظ بالأحرف والأرقام والنقاط والشرطات فقط)
        $fileName = preg_replace('/[^a-z0-9._-]/', '', $fileName);

        // إزالة النقاط المتعددة
        $fileName = preg_replace('/\.+/', '.', $fileName);

        // إزالة الشرطات المتعددة
        $fileName = preg_replace('/[-_]+/', '_', $fileName);

        Log::debug('File name prepared for comparison', [
            'original' => func_get_arg(0),
            'prepared' => $fileName
        ]);

        return $fileName;
    }

    /**
     * Find existing file in attachments table
     */
    protected function findExistingFile(string $preparedFileName)
    {
        // التحقق من أن اسم الملف المحضر ليس فارغاً
        if (empty($preparedFileName)) {
            return null;
        }

        // البحث المباشر بالاسم المحضر
        $exactMatch = Attachment::where('file_name', $preparedFileName)->first();
        if ($exactMatch) {
            return $exactMatch;
        }

        // البحث بالاسم الأصلي مع تحضير اسماء الملفات الموجودة للمقارنة
        $existingFiles = Attachment::whereNotNull('file_name')->select('id', 'file_name')->get();

        foreach ($existingFiles as $existingFile) {
            // التحقق من أن اسم الملف ليس null قبل المعالجة
            if (!empty($existingFile->file_name)) {
                $preparedExistingName = $this->prepareFileNameForComparison($existingFile->file_name);
                if ($preparedExistingName === $preparedFileName) {
                    return $existingFile;
                }
            }
        }

        // البحث بالتشابه (similarity search) للأسماء المتقاربة
        $similarityThreshold = Config::get('duplicate_detection.detection.similarity_threshold', 0.95);
        foreach ($existingFiles as $existingFile) {
            // التحقق من أن اسم الملف ليس null قبل المعالجة
            if (!empty($existingFile->file_name)) {
                $preparedExistingName = $this->prepareFileNameForComparison($existingFile->file_name);
                $similarity = $this->calculateStringSimilarity($preparedFileName, $preparedExistingName);

                // إذا كانت نسبة التشابه أكبر من العتبة المحددة
                if ($similarity > $similarityThreshold) {
                    Log::info('Similar file found', [
                        'new_file' => $preparedFileName,
                        'existing_file' => $preparedExistingName,
                        'similarity' => $similarity
                    ]);
                    return $existingFile;
                }
            }
        }

        return null;
    }

    /**
     * Calculate string similarity between two file names
     */
    protected function calculateStringSimilarity(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        return 1 - (levenshtein($str1, $str2) / max($len1, $len2));
    }

    /**
     * Generate unique temp file name
     */
    protected function generateTempFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $timestamp = time();
        $random = Str::random(6);

        return "{$baseName}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get duplicate files for a session
     */
    public function getDuplicateFiles(string $sessionId): array
    {
        $duplicates = DuplicateFileTemp::bySession($sessionId)
            ->notExpired()
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [
            'session_id' => $sessionId,
            'total_duplicates' => $duplicates->count(),
            'files' => []
        ];

        foreach ($duplicates as $duplicate) {
            $fileInfo = [
                'id' => $duplicate->id,
                'original_name' => $duplicate->original_name,
                'duplicate_name' => $duplicate->duplicate_name,
                'temp_path' => $duplicate->temp_path,
                'original_folder' => $duplicate->original_folder,
                'existing_file_name' => $duplicate->existing_file_name,
                'created_at' => $duplicate->created_at,
                'expires_at' => $duplicate->expires_at,
                'file_exists' => File::exists($duplicate->temp_path),
                'file_size' => File::exists($duplicate->temp_path) ? File::size($duplicate->temp_path) : 0
            ];

            $result['files'][] = $fileInfo;
        }

        return $result;
    }

    /**
     * Clean expired duplicate files
     */
    public function cleanExpiredFiles(): array
    {
        $expired = DuplicateFileTemp::where('expires_at', '<', Carbon::now())->get();

        $cleaned = [
            'deleted_records' => 0,
            'deleted_files' => 0,
            'errors' => []
        ];

        foreach ($expired as $duplicate) {
            try {
                // حذف الملف المؤقت
                if (File::exists($duplicate->temp_path)) {
                    File::delete($duplicate->temp_path);
                    $cleaned['deleted_files']++;
                }

                // حذف السجل من قاعدة البيانات
                $duplicate->delete();
                $cleaned['deleted_records']++;

            } catch (\Exception $e) {
                $cleaned['errors'][] = [
                    'duplicate_id' => $duplicate->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        // تنظيف المجلدات الفارغة
        $this->cleanEmptyTempDirectories();

        Log::info('Expired duplicate files cleaned', $cleaned);

        return $cleaned;
    }

    /**
     * Delete all duplicates for a session
     */
    public function deleteDuplicatesForSession(string $sessionId): array
    {
        $duplicates = DuplicateFileTemp::bySession($sessionId)->get();

        $result = [
            'deleted_records' => 0,
            'deleted_files' => 0,
            'errors' => []
        ];

        foreach ($duplicates as $duplicate) {
            try {
                // حذف الملف المؤقت
                if (File::exists($duplicate->temp_path)) {
                    File::delete($duplicate->temp_path);
                    $result['deleted_files']++;
                }

                // حذف السجل من قاعدة البيانات
                $duplicate->delete();
                $result['deleted_records']++;

            } catch (\Exception $e) {
                $result['errors'][] = [
                    'duplicate_id' => $duplicate->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        // حذف مجلد الجلسة
        $sessionPath = $this->tempStoragePath . '/' . $sessionId;
        if (File::exists($sessionPath)) {
            try {
                File::deleteDirectory($sessionPath);
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'path' => $sessionPath,
                    'error' => 'فشل في حذف مجلد الجلسة: ' . $e->getMessage()
                ];
            }
        }

        Log::info('Session duplicates deleted', array_merge($result, ['session_id' => $sessionId]));

        return $result;
    }

    /**
     * Create ZIP file with all duplicate files for a session
     */
    public function createDuplicatesZip(string $sessionId): ?string
    {
        try {
            $duplicates = DuplicateFileTemp::where('session_id', $sessionId)->get();

            $zipFileName = 'duplicate_files_' . $sessionId . '_' . time() . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);

            // إنشاء مجلد temp إذا لم يكن موجوداً
            if (!file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new \ZipArchive();

            if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
                throw new \Exception('فشل في إنشاء ملف ZIP');
            }

            if ($duplicates->isEmpty()) {
                // إنشاء ملف نصي يوضح أنه لا توجد ملفات مكررة
                $infoContent = "لا توجد ملفات مكررة لـ Session ID: $sessionId\n";
                $infoContent .= "تاريخ الإنشاء: " . Carbon::now()->format('Y-m-d H:i:s') . "\n";
                $infoContent .= "هذا الملف تم إنشاؤه لأغراض الاختبار.";

                $zip->addFromString('no_duplicates_info.txt', $infoContent);
            } else {
                foreach ($duplicates as $duplicate) {
                    if ($duplicate->temp_path && file_exists($duplicate->temp_path)) {
                        $zip->addFile($duplicate->temp_path, $duplicate->original_name);
                    }
                }
            }

            $zip->close();

            return $zipPath;

        } catch (\Exception $e) {
            Log::error('Error creating duplicates ZIP', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Delete duplicate files for a session
     */
    public function deleteDuplicateFiles(string $sessionId): int
    {
        try {
            $duplicates = DuplicateFileTemp::where('session_id', $sessionId)->get();
            $deletedCount = 0;

            foreach ($duplicates as $duplicate) {
                // حذف الملف المؤقت إذا كان موجوداً
                if ($duplicate->temp_path && file_exists($duplicate->temp_path)) {
                    unlink($duplicate->temp_path);
                }

                // حذف السجل من قاعدة البيانات
                $duplicate->delete();
                $deletedCount++;
            }

            // حذف مجلد الجلسة إذا كان فارغاً
            $sessionDir = $this->tempStoragePath . '/' . $sessionId;
            if (is_dir($sessionDir) && count(scandir($sessionDir)) <= 2) {
                rmdir($sessionDir);
            }

            Log::info('Duplicate files deleted', [
                'session_id' => $sessionId,
                'deleted_count' => $deletedCount
            ]);

            return $deletedCount;

        } catch (\Exception $e) {
            Log::error('Error deleting duplicate files', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Ensure temp directory exists
     */
    protected function ensureTempDirectoryExists(): void
    {
        if (!File::exists($this->tempStoragePath)) {
            File::makeDirectory($this->tempStoragePath, 0755, true);
        }
    }

    /**
     * Clean empty temp directories
     */
    protected function cleanEmptyTempDirectories(): void
    {
        try {
            $directories = File::directories($this->tempStoragePath);

            foreach ($directories as $directory) {
                if ($this->isDirectoryEmpty($directory)) {
                    File::deleteDirectory($directory);
                    Log::info('Empty temp directory cleaned', ['directory' => $directory]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error cleaning empty directories', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if directory is empty
     */
    protected function isDirectoryEmpty(string $directory): bool
    {
        $files = File::allFiles($directory);
        $directories = File::directories($directory);

        return empty($files) && empty($directories);
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(string $sessionId): array
    {
        $duplicates = DuplicateFileTemp::bySession($sessionId)->get();

        $stats = [
            'session_id' => $sessionId,
            'total_duplicates' => $duplicates->count(),
            'active_duplicates' => $duplicates->where('expires_at', '>', Carbon::now())->count(),
            'expired_duplicates' => $duplicates->where('expires_at', '<=', Carbon::now())->count(),
            'total_size' => 0,
            'folders' => [],
            'file_types' => []
        ];

        foreach ($duplicates as $duplicate) {
            // حساب الحجم الإجمالي
            if (File::exists($duplicate->temp_path)) {
                $stats['total_size'] += File::size($duplicate->temp_path);
            }

            // إحصائيات المجلدات
            $folder = $duplicate->original_folder;
            if (!isset($stats['folders'][$folder])) {
                $stats['folders'][$folder] = 0;
            }
            $stats['folders'][$folder]++;

            // إحصائيات أنواع الملفات
            $extension = pathinfo($duplicate->original_name, PATHINFO_EXTENSION);
            if (!isset($stats['file_types'][$extension])) {
                $stats['file_types'][$extension] = 0;
            }
            $stats['file_types'][$extension]++;
        }

        return $stats;
    }

    /**
     * Save non-duplicate file to storage and database
     */
    protected function saveNonDuplicateFile(UploadedFile $file, string $folderName): ?array
    {
        try {
            // تحديد مسار التخزين
            $uploadPath = 'uploads/' . date('Y/m');

            // إنشاء اسم ملف فريد
            $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            // حفظ الملف في storage
            $filePath = $file->storeAs($uploadPath, $fileName, 'public');

            if (!$filePath) {
                throw new \Exception('فشل في حفظ الملف في storage');
            }

            // حفظ معلومات الملف في قاعدة البيانات
            $attachment = Attachment::create([
                'file_name' => $file->getClientOriginalName(),
                'original_file_name' => $file->getClientOriginalName(),
                'stored_file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_type' => $file->getClientOriginalExtension(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_hash' => hash_file('md5', $file->getRealPath()),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return [
                'attachment_id' => $attachment->id,
                'path' => $filePath,
                'original_name' => $file->getClientOriginalName(),
                'saved_name' => $fileName,
                'size' => $file->getSize()
            ];

        } catch (\Exception $e) {
            Log::error('Error saving non-duplicate file', [
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'session_id' => $this->sessionId
            ]);

            return null;
        }
    }

    /**
     * Get duplicate files summary for a session
     */
    public function getDuplicateFilesSummary(string $sessionId): array
    {
        try {
            $duplicates = DuplicateFileTemp::where('session_id', $sessionId)->get();

            if ($duplicates->isEmpty()) {
                return [
                    'total_duplicates' => 0,
                    'total_size' => 0,
                    'files' => [],
                    'session_id' => $sessionId
                ];
            }

            $summary = [
                'total_duplicates' => $duplicates->count(),
                'total_size' => 0,
                'session_id' => $sessionId,
                'files' => []
            ];

            foreach ($duplicates as $duplicate) {
                // حساب حجم الملف
                if ($duplicate->temp_path && file_exists($duplicate->temp_path)) {
                    $fileSize = filesize($duplicate->temp_path);
                    $summary['total_size'] += $fileSize;
                } else {
                    $fileSize = 0;
                }

                $summary['files'][] = [
                    'id' => $duplicate->id,
                    'original_name' => $duplicate->original_name,
                    'prepared_name' => $duplicate->prepared_name,
                    'existing_file_name' => $duplicate->existing_file_name,
                    'existing_file_id' => $duplicate->existing_file_id,
                    'folder_name' => $duplicate->original_folder,
                    'file_size' => $fileSize,
                    'created_at' => $duplicate->created_at,
                    'can_download' => $duplicate->temp_path && file_exists($duplicate->temp_path)
                ];
            }

            return $summary;

        } catch (\Exception $e) {
            Log::error('Error getting duplicate files summary', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return [
                'total_duplicates' => 0,
                'total_size' => 0,
                'files' => [],
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ];
        }
    }
}
