<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\EnhancedAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * خدمة كشف الملفات المكررة للمجلدات مع دعم معمارية النظام الحالي
 */
class FolderDuplicateDetectionService
{
    protected $sessionId;
    protected $currentBatch = [];
    protected $detectionResults = [];

    public function __construct()
    {
        // تم إزالة tempStoragePath - لم نعد نحفظ الملفات المكررة
    }

    /**
     * إنشاء جلسة جديدة للكشف عن الملفات المكررة
     */
    public function initializeSession(string $customSessionId = null): string
    {
        $this->sessionId = $customSessionId ?: ('dup_folder_' . time() . '_' . Str::random(8));
        Log::info('Folder duplicate detection session initialized', [
            'session_id' => $this->sessionId,
            'is_custom' => $customSessionId !== null
        ]);
        return $this->sessionId;
    }

    /**
     * تعيين معرف جلسة موجود
     */
    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * معالجة مجموعة ملفات للكشف عن التكرار مع دعم معمارية المجلدات
     */
    public function processFolderFilesForDuplicates(
        array $files,
        array $validatedFolders,
        array $pathToFolderMapping,
        string $customSessionId = null,
        bool $duplicateDetectionEnabled = true
    ): array
    {
        if (!$this->sessionId) {
            $this->initializeSession($customSessionId);
        }

        $results = [
            'session_id' => $this->sessionId,
            'total_files' => count($files),
            'duplicates_found' => 0,
            'files_saved' => 0,
            'processed_files' => 0,
            'errors' => [],
            'warnings' => [],
            'duplicate_files' => [],
            'saved_files' => [],
            'folder_analysis' => [],
            'duplicate_detection_enabled' => $duplicateDetectionEnabled
        ];

        Log::info('Starting folder duplicate detection', [
            'session_id' => $this->sessionId,
            'total_files' => count($files),
            'validated_folders' => array_keys($validatedFolders),
            'duplicate_detection_enabled' => $duplicateDetectionEnabled
        ]);

        // إضافة حماية من timeout للحلقة الرئيسية
        $overallStartTime = microtime(true);
        $maxProcessingTime = 300; // 5 دقائق كحد أقصى

        foreach ($files as $index => $file) {
            try {
                // فحص الوقت المنقضي لتجنب timeout
                $currentTime = microtime(true);
                $elapsedTime = $currentTime - $overallStartTime;

                if ($elapsedTime > $maxProcessingTime) {
                    Log::error('Processing timeout reached', [
                        'elapsed_time' => $elapsedTime,
                        'max_time' => $maxProcessingTime,
                        'processed_files' => $results['processed_files'],
                        'total_files' => count($files)
                    ]);

                    $results['errors'][] = [
                        'error' => 'Processing timeout reached after ' . round($elapsedTime, 2) . ' seconds',
                        'processed_files' => $results['processed_files'],
                        'remaining_files' => count($files) - $results['processed_files']
                    ];
                    break;
                }
                // تحديد المجلد المقصود لهذا الملف
                $targetFolderId = null;
                $originalFolderName = null;

                if (isset($pathToFolderMapping[$index])) {
                    $originalFolderName = $pathToFolderMapping[$index];
                    if (isset($validatedFolders[$originalFolderName])) {
                        $targetFolderId = $validatedFolders[$originalFolderName]['file_id_number'];
                    }
                }

                if (!$targetFolderId) {
                    $results['errors'][] = [
                        'file_name' => $file->getClientOriginalName(),
                        'error' => 'لا يمكن تحديد المجلد المقصود للملف',
                        'original_folder' => $originalFolderName
                    ];
                    continue;
                }

                // ⭐ كشف التكرار للملف (يعمل دائماً - حتى لو كان الزر OFF)
                $duplicateInfo = $this->checkFileForDuplicateInFolder($file, $targetFolderId, $originalFolderName);

                if ($duplicateInfo['is_duplicate']) {
                    $results['duplicates_found']++;
                    $results['duplicate_files'][] = $duplicateInfo;

                    Log::info('Duplicate file detected in folder', [
                        'file_name' => $file->getClientOriginalName(),
                        'target_folder' => $targetFolderId,
                        'existing_file' => $duplicateInfo['existing_file_name'],
                        'session_id' => $this->sessionId,
                        'auto_ignore' => !$duplicateDetectionEnabled
                    ]);

                    // ⚠️ إذا كان كشف التكرار معطلاً (OFF)، يتم تجاهل الملف تلقائياً
                    if (!$duplicateDetectionEnabled) {
                        Log::warning('Duplicate detection is OFF - File will be auto-ignored (not uploaded)', [
                            'file_name' => $file->getClientOriginalName(),
                            'target_folder' => $targetFolderId,
                            'existing_file' => $duplicateInfo['existing_file_name']
                        ]);
                        // ⛔ لا يتم رفع الملف المكرر - يتم تجاهله تماماً
                        // لا نفعل شيء - الملف لن يُحفظ
                    }
                    // إذا كان كشف التكرار مفعلاً (ON)، سيتم عرض الملف للمستخدم
                } else {
                    // حفظ الملف إذا لم يكن مكرراً
                    $savedFile = $this->saveNonDuplicateFileToFolder($file, $targetFolderId, $originalFolderName);
                    if ($savedFile) {
                        $results['files_saved']++;
                        $results['saved_files'][] = $savedFile;

                        Log::info('Non-duplicate file saved to folder', [
                            'file_name' => $file->getClientOriginalName(),
                            'target_folder' => $targetFolderId,
                            'saved_path' => $savedFile['path'],
                            'session_id' => $this->sessionId
                        ]);
                    }
                }

                $results['processed_files']++;

                // تحليل المجلد
                if (!isset($results['folder_analysis'][$targetFolderId])) {
                    $results['folder_analysis'][$targetFolderId] = [
                        'folder_id' => $targetFolderId,
                        'original_name' => $originalFolderName,
                        'total_files' => 0,
                        'duplicates' => 0,
                        'saved' => 0,
                        'file_types' => []
                    ];
                }

                $results['folder_analysis'][$targetFolderId]['total_files']++;
                if ($duplicateInfo['is_duplicate']) {
                    $results['folder_analysis'][$targetFolderId]['duplicates']++;
                } else {
                    $results['folder_analysis'][$targetFolderId]['saved']++;
                }

                $fileExtension = strtolower($file->getClientOriginalExtension());
                if (!isset($results['folder_analysis'][$targetFolderId]['file_types'][$fileExtension])) {
                    $results['folder_analysis'][$targetFolderId]['file_types'][$fileExtension] = 0;
                }
                $results['folder_analysis'][$targetFolderId]['file_types'][$fileExtension]++;

            } catch (\Exception $e) {
                $error = [
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'original_folder' => $originalFolderName ?? 'unknown'
                ];
                $results['errors'][] = $error;

                Log::error('Error processing file for folder duplicates', [
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'session_id' => $this->sessionId
                ]);
            }
        }

        // حفظ معرف الجلسة في الـ session للاستخدام لاحقاً
        session(['duplicate_files_session_id' => $this->sessionId]);

        Log::info('Folder duplicate detection completed', [
            'session_id' => $this->sessionId,
            'results' => $results
        ]);

        return $results;
    }

    /**
     * فحص ملف واحد للتكرار في مجلد محدد
     */
    /**
     * فحص ملف للتحقق من وجود تكرار حقيقي في مجلد محدد (تحسين لتجنب الملفات المكررة الوهمية)
     */
    public function checkFileForDuplicateInFolder(UploadedFile $file, string $targetFolderId, string $originalFolderName): array
    {
        try {
            $originalName = $file->getClientOriginalName();

            // استخراج معلومات الملف للمقارنة الدقيقة
            $fileInfo = $this->extractFileIdentificationInfo($originalName);

            Log::info('فحص الملف للتحقق من التكرار بناء على معلومات شاملة', [
                'original_name' => $originalName,
                'file_info' => $fileInfo,
                'target_folder' => $targetFolderId,
                'original_folder' => $originalFolderName
            ]);

            // البحث عن ملف مطابق باستخدام النظام المحسن
            $existingFile = $this->findExactMatchingFile($fileInfo, $targetFolderId);

            if ($existingFile) {
                Log::info('تم العثور على ملف مطابق بالفعل', [
                    'original_name' => $originalName,
                    'file_info' => $fileInfo,
                    'existing_file' => $existingFile->stored_file_name ?? $existingFile->file_name,
                    'target_folder' => $targetFolderId,
                    'match_type' => 'exact_match'
                ]);

                // توليد اسم الملف الصحيح للملف المكرر
                $correctFileName = $this->generateCorrectFileName($file, $targetFolderId, $originalFolderName);

                return $this->handleDuplicateFileInFolder($file, $originalFolderName, $targetFolderId, $existingFile, $correctFileName);
            }

            // الملف غير مكرر - أو على الأقل لا يوجد تطابق دقيق
            Log::info('لم يتم العثور على تطابق دقيق - الملف ليس مكرراً', [
                'original_name' => $originalName,
                'file_info' => $fileInfo,
                'target_folder' => $targetFolderId
            ]);

            return [
                'is_duplicate' => false,
                'original_name' => $originalName,
                'file_info' => $fileInfo,
                'target_folder' => $targetFolderId,
                'original_folder' => $originalFolderName,
                'check_method' => 'enhanced_duplicate_detection'
            ];

        } catch (\Exception $e) {
            Log::error('خطأ في فحص التكرار المحسن', [
                'file_name' => $file->getClientOriginalName(),
                'target_folder' => $targetFolderId,
                'error' => $e->getMessage()
            ]);

            return [
                'is_duplicate' => false,
                'original_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'check_method' => 'error_fallback'
            ];
        }
    }

    /**
     * استخراج معلومات تعريف شاملة للملف لمقارنة أكثر دقة
     */
    protected function extractFileIdentificationInfo(string $originalName): array
    {
        $parts = explode('_', pathinfo($originalName, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $info = [
            'original_name' => $originalName,
            'extension' => $extension,
            'parts' => $parts,
            'parts_count' => count($parts),
            'prefix' => null,
            'identity_number' => null,
            'document_type' => null,
            'file_pattern' => 'unknown'
        ];

        // تحديد نمط الملف وتحليله
        if (count($parts) >= 3) {
            // النمط الجديد: PREFIX_FOLDER_IDENTITY
            if (preg_match('/^\d{8,10}$/', $parts[2])) {
                $info['file_pattern'] = 'new_format';
                $info['prefix'] = $parts[0];
                $info['folder_number'] = $parts[1];
                $info['identity_number'] = $parts[2];
                // إنشاء توقيع موحد بناء على نوع الوثيقة (البادئة) والهوية
                $info['unique_signature'] = $info['prefix'] . '_' . $info['identity_number'] . '_' . $extension;
            }
            // النمط القديم: PREFIX_IDENTITY_DOCTYPE
            elseif (preg_match('/^\d{8,10}$/', $parts[1])) {
                $info['file_pattern'] = 'old_format';
                $info['prefix'] = $parts[0];
                $info['identity_number'] = $parts[1];
                $info['document_type'] = $parts[2];
                // إنشاء توقيع موحد بناء على نوع الوثيقة والهوية
                $info['unique_signature'] = $info['document_type'] . '_' . $info['identity_number'] . '_' . $extension;
            }
        }

        // إذا لم نجد النمط المعروف، ابحث عن رقم هوية في أي مكان
        if (!$info['identity_number']) {
            foreach ($parts as $part) {
                if (preg_match('/^\d{8,10}$/', $part)) {
                    $info['identity_number'] = $part;
                    $info['file_pattern'] = 'custom_format';
                    break;
                }
            }
        }

        // إنشاء توقيع فريد للملف
        if (!isset($info['unique_signature'])) {
            $info['unique_signature'] = md5($originalName) . '_' . $extension;
        }

        return $info;
    }

    /**
     * البحث عن ملف مطابق بالضبط باستخدام معلومات شاملة
     */
    protected function findExactMatchingFile(array $fileInfo, string $targetFolderId)
    {
        // إذا لم نحصل على معرف هوية، لا يمكننا المقارنة بدقة
        if (!$fileInfo['identity_number']) {
            Log::debug('لا يمكن البحث عن تطابق بدون رقم هوية', [
                'file_info' => $fileInfo,
                'target_folder' => $targetFolderId
            ]);
            return null;
        }

        // البحث في قاعدة البيانات باستخدام التوقيع الفريد
        $query = Attachment::where('file_path', 'like', '%/' . $targetFolderId . '/%');

        // البحث بناءً على نمط الملف
        if ($fileInfo['file_pattern'] === 'new_format') {
            // للنمط الجديد: ابحث عن ملفات بنفس البادئة ورقم الهوية والامتداد
            $query->where('stored_file_name', 'like', $fileInfo['prefix'] . '_%_' . $fileInfo['identity_number'] . '.' . $fileInfo['extension']);
        } elseif ($fileInfo['file_pattern'] === 'old_format') {
            // للنمط القديم: ابحث عن ملفات بنفس البادئة ورقم الهوية ونوع الوثيقة والامتداد
            $query->where('stored_file_name', 'like', $fileInfo['prefix'] . '_' . $fileInfo['identity_number'] . '_' . $fileInfo['document_type'] . '.' . $fileInfo['extension']);
        } else {
            // للأنماط المخصصة: ابحث بناءً على رقم الهوية والامتداد
            $query->where('stored_file_name', 'like', '%' . $fileInfo['identity_number'] . '%.' . $fileInfo['extension']);
        }

        $attachment = $query->first();

        if ($attachment) {
            Log::info('تم العثور على ملف مطابق في قاعدة البيانات', [
                'file_info' => $fileInfo,
                'target_folder' => $targetFolderId,
                'existing_file' => $attachment->stored_file_name,
                'match_pattern' => $fileInfo['file_pattern']
            ]);
            return $attachment;
        }

        // البحث المباشر في نظام الملفات مع المطابقة الدقيقة
        $storageBasePath = storage_path('app/public/uploads/' . $targetFolderId);
        if (is_dir($storageBasePath)) {
            $files = File::files($storageBasePath);
            foreach ($files as $file) {
                $fileName = $file->getFilename();
                $existingFileInfo = $this->extractFileIdentificationInfo($fileName);

                // مقارنة التواقيع الفريدة أو المعلومات المهمة
                if ($this->areFilesExactMatch($fileInfo, $existingFileInfo)) {
                    Log::info('تم العثور على ملف مطابق في نظام الملفات', [
                        'file_info' => $fileInfo,
                        'existing_file_info' => $existingFileInfo,
                        'target_folder' => $targetFolderId,
                        'existing_file' => $fileName
                    ]);

                    return (object) [
                        'id' => null,
                        'file_name' => $fileName,
                        'stored_file_name' => $fileName,
                        'original_file_name' => $fileName,
                        'folder_id' => $targetFolderId,
                        'file_path' => 'uploads/' . $targetFolderId . '/' . $fileName,
                        'storage_type' => 'file_system'
                    ];
                }
            }
        }

        return null;
    }

    /**
     * مقارنة ملفين لتحديد ما إذا كانا متطابقين بالضبط
     */
    protected function areFilesExactMatch(array $fileInfo1, array $fileInfo2): bool
    {
        // الشروط الأساسية للتطابق
        $basicMatch = (
            $fileInfo1['identity_number'] === $fileInfo2['identity_number'] &&
            $fileInfo1['extension'] === $fileInfo2['extension']
        );

        if (!$basicMatch) {
            return false;
        }

        // للنمط الجديد: تحقق من البادئة أيضاً
        if ($fileInfo1['file_pattern'] === 'new_format' && $fileInfo2['file_pattern'] === 'new_format') {
            return $fileInfo1['prefix'] === $fileInfo2['prefix'];
        }

        // للنمط القديم: تحقق من البادئة ونوع الوثيقة
        if ($fileInfo1['file_pattern'] === 'old_format' && $fileInfo2['file_pattern'] === 'old_format') {
            return (
                $fileInfo1['prefix'] === $fileInfo2['prefix'] &&
                $fileInfo1['document_type'] === $fileInfo2['document_type']
            );
        }

        // للأنماط المختلطة: مقارنة دقيقة بناء على نوع الوثيقة
        // يجب أن يكون نوع الوثيقة متطابق بين الأنماط المختلفة
        if ($fileInfo1['file_pattern'] !== $fileInfo2['file_pattern']) {
            // استخراج نوع الوثيقة من كلا الملفين
            $docType1 = isset($fileInfo1['document_type']) ? $fileInfo1['document_type'] :
                       (isset($fileInfo1['prefix']) ? $fileInfo1['prefix'] : null);
            $docType2 = isset($fileInfo2['document_type']) ? $fileInfo2['document_type'] :
                       (isset($fileInfo2['prefix']) ? $fileInfo2['prefix'] : null);

            // يجب أن يكون نوع الوثيقة متطابق
            return $docType1 === $docType2 && !empty($docType1);
        }

        // إذا لم تطابق أي من الحالات أعلاه، فالملفات غير متطابقة
        return false;
    }

    /**
     * البحث عن ملف موجود بناء على رقم الهوية ونوع الوثيقة في مجلد محدد (تحسين مشكلة الملفات المكررة الوهمية)
     */
    protected function findExistingFileByIdentityInFolder(string $identityNumber, string $targetFolderId)
    {
        // التحقق من صحة المدخلات
        if (empty($identityNumber) || empty($targetFolderId)) {
            Log::warning('معاملات غير صحيحة للبحث عن الملف', [
                'identity_number' => $identityNumber,
                'target_folder' => $targetFolderId
            ]);
            return null;
        }

        // البحث في جدول attachments باستخدام رقم الهوية بدقة أكبر
        // نبحث عن التطابق الدقيق لرقم الهوية كجزء من اسم الملف
        $attachment = Attachment::where('file_path', 'like', '%/' . $targetFolderId . '/%')
            ->where(function($query) use ($identityNumber) {
                // البحث عن رقم الهوية في الجزء الثاني من اسم الملف
                $query->where('stored_file_name', 'like', '%_' . $identityNumber . '_%')
                      ->orWhere('stored_file_name', 'like', '%_' . $identityNumber . '.%');
            })
            ->first();

        if ($attachment) {
            // التحقق المزدوج: التأكد من أن رقم الهوية في المكان الصحيح
            $fileNameParts = explode('_', pathinfo($attachment->stored_file_name, PATHINFO_FILENAME));

            if (count($fileNameParts) >= 3 && $fileNameParts[2] === $identityNumber) {
                Log::info('تم العثور على ملف مطابق في قاعدة البيانات', [
                    'identity_number' => $identityNumber,
                    'target_folder' => $targetFolderId,
                    'existing_file' => $attachment->stored_file_name,
                    'file_parts' => $fileNameParts
                ]);
                return $attachment;
            }
        }

        // البحث المباشر في نظام الملفات مع التحقق الدقيق
        $storageBasePath = storage_path('app/public/uploads/' . $targetFolderId);
        if (is_dir($storageBasePath)) {
            $files = File::files($storageBasePath);
            foreach ($files as $file) {
                $fileName = $file->getFilename();
                $fileNameParts = explode('_', pathinfo($fileName, PATHINFO_FILENAME));

                // التحقق من النمط الصحيح: PREFIX_FOLDER_IDENTITY
                if (count($fileNameParts) >= 3 && $fileNameParts[2] === $identityNumber) {
                    Log::info('تم العثور على ملف مطابق في نظام الملفات', [
                        'identity_number' => $identityNumber,
                        'folder_id' => $targetFolderId,
                        'existing_file' => $fileName,
                        'file_parts' => $fileNameParts
                    ]);

                    return (object) [
                        'id' => null,
                        'file_name' => $fileName,
                        'stored_file_name' => $fileName,
                        'original_file_name' => $fileName,
                        'folder_id' => $targetFolderId,
                        'file_path' => 'uploads/' . $targetFolderId . '/' . $fileName,
                        'storage_type' => 'file_system'
                    ];
                }
            }
        }

        Log::debug('لم يتم العثور على أي ملف مطابق', [
            'identity_number' => $identityNumber,
            'target_folder' => $targetFolderId,
            'search_path' => $storageBasePath ?? 'unknown'
        ]);

        return null;
    }

    /**
     * البحث عن ملف موجود في مجلد محدد
     */
    protected function findExistingFileInFolder(string $preparedFileName, string $targetFolderId)
    {
        // البحث في جدول attachments العادي (الأساسي)
        $attachment = Attachment::where('file_path', 'like', '%/' . $targetFolderId . '/%')
            ->whereRaw('LOWER(REPLACE(REPLACE(stored_file_name, " ", "_"), "-", "_")) = ?', [strtolower($preparedFileName)])
            ->first();

        if ($attachment) {
            return $attachment;
        }

        // البحث المباشر في الملفات المحفوظة
        $storageBasePath = storage_path('app/public/uploads/' . $targetFolderId);
        if (is_dir($storageBasePath)) {
            $files = File::files($storageBasePath);
            foreach ($files as $file) {
                $fileName = $file->getFilename();
                $preparedStorageFileName = $this->prepareFileNameForComparison($fileName);

                if ($preparedStorageFileName === $preparedFileName) {
                    // إنشاء كائن وهمي للملف الموجود
                    return (object) [
                        'id' => null,
                        'file_name' => $fileName,
                        'original_file_name' => $fileName,
                        'folder_id' => $targetFolderId,
                        'file_path' => 'uploads/' . $targetFolderId . '/' . $fileName,
                        'storage_type' => 'file_system'
                    ];
                }
            }
        }

        return null;
    }

    /**
     * تحضير اسم الملف للمقارنة
     */
    protected function prepareFileNameForComparison(string $fileName): string
    {
        // إزالة المسار والحصول على اسم الملف فقط
        $fileName = basename($fileName);

        // تحويل المسافات والشرطات إلى شرطات سفلية
        $fileName = str_replace([' ', '-'], '_', $fileName);

        // إزالة الأحرف الخاصة
        $fileName = preg_replace('/[^\w\.\-_]/', '', $fileName);

        // تحويل إلى أحرف صغيرة
        $fileName = strtolower($fileName);

        return $fileName;
    }

    /**
     * معالجة الملف المكرر في المجلد
     */
    /**
     * معالجة ملف مكرر في مجلد محدد - سيتم حفظه في مسار مؤقت للمراجعة مع آليات حماية
     */
    protected function handleDuplicateFileInFolder(UploadedFile $file, string $originalFolderName, string $targetFolderId, $existingFile, string $preparedFileName): array
    {
        try {
            $originalName = $file->getClientOriginalName();

            // ⛔ لا يتم رفع أو حفظ الملفات المكررة - فقط تسجيلها
            Log::info('⛔ ملف مكرر تم رفضه - لن يتم حفظه', [
                'original_name' => $originalName,
                'target_folder' => $targetFolderId,
                'existing_file' => $existingFile->stored_file_name ?? $existingFile->file_name ?? 'unknown'
            ]);

            // الحصول على معلومات الملف الأساسية فقط
            $fileSize = 0;
            try {
                $fileSize = $file->getSize();
            } catch (\Exception $e) {
                $fileSize = 0;
            }

            $mimeType = 'application/octet-stream';
            try {
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            } catch (\Exception $e) {
                // استخدام extension
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = match($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'pdf' => 'application/pdf',
                    default => 'application/octet-stream'
                };
            }

            return [
                'is_duplicate' => true,
                'original_name' => $originalName,
                'existing_file_name' => $existingFile->stored_file_name ?? $existingFile->file_name ?? basename($existingFile->file_path ?? ''),
                'existing_file_path' => $existingFile->file_path ?? 'uploads/' . $targetFolderId . '/' . ($existingFile->stored_file_name ?? $existingFile->file_name),
                'target_folder' => $targetFolderId,
                'reason' => 'same_identity_number',
                'message' => 'الملف مكرر - لم يتم رفعه أو حفظه'
            ];

        } catch (\Exception $e) {
            Log::error('خطأ في معالجة الملف المكرر', [
                'file_name' => $file->getClientOriginalName(),
                'target_folder' => $targetFolderId,
                'error' => $e->getMessage()
            ]);

            return [
                'is_duplicate' => true,
                'error' => $e->getMessage(),
                'original_name' => $file->getClientOriginalName(),
                'existing_file_name' => isset($existingFile) ? ($existingFile->stored_file_name ?? $existingFile->file_name ?? 'unknown') : 'unknown',
                'target_folder' => $targetFolderId
            ];
        }
    }

    /**
     * حفظ الملف غير المكرر في المجلد
     */
    protected function saveNonDuplicateFileToFolder(UploadedFile $file, string $targetFolderId, string $originalFolderName): ?array
    {
        try {
            // تحديد مسار التخزين في المجلد المحدد
            $uploadPath = 'uploads/' . $targetFolderId;
            $fullUploadPath = storage_path('app/public/' . $uploadPath);

            // إنشاء المجلد إذا لم يكن موجوداً
            if (!File::exists($fullUploadPath)) {
                File::makeDirectory($fullUploadPath, 0755, true);
            }

            // إنشاء اسم ملف حسب النمط القديم: نوع_الوثيقة_رقم_الملف_رقم_الهوية.extension
            $fileName = $this->generateCorrectFileName($file, $targetFolderId, $originalFolderName);

            // حفظ الملف
            $filePath = $file->storeAs($uploadPath, $fileName, 'public');

            if (!$filePath) {
                throw new \Exception('فشل في حفظ الملف في storage');
            }

            // حفظ معلومات الملف في جدول attachments العادي بدلاً من EnhancedAttachment
            $extension = $file->getClientOriginalExtension();

            // استخراج نوع الوثيقة من اسم الملف الجديد
            $documentType = $this->extractDocumentTypeFromFilename($fileName);

            // الحصول على MIME type بشكل آمن
            try {
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            } catch (\Exception $e) {
                // استخدام extension للحصول على MIME type تقريبي
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = match($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    default => 'application/octet-stream'
                };
            }

            $attachment = Attachment::create([
                'person_identity_number' => $this->extractIdentityNumberFromOriginalName($file->getClientOriginalName()),
                'stored_file_name' => $fileName,
                'original_file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $mimeType,
                'file_type' => $documentType, // استخدام نوع الوثيقة المستخرج من اسم الملف
                'file_extension' => $extension,
                'file_hash' => hash_file('md5', $file->getRealPath()),
                'file_last_modified' => now(),
                'source' => 'folder_upload',
                'upload_ip_address' => request()->ip(),
                'upload_user_agent' => request()->userAgent(),
                'uploaded_by_user_id' => auth()->id(),
                'processing_status' => 'completed',
                'access_level' => 'internal'
            ]);

            return [
                'attachment_id' => $attachment->id,
                'path' => $filePath,
                'full_path' => $fullUploadPath . '/' . $fileName,
                'original_name' => $file->getClientOriginalName(),
                'saved_name' => $fileName,
                'size' => $file->getSize(),
                'folder_id' => $targetFolderId,
                'original_folder' => $originalFolderName
            ];

        } catch (\Exception $e) {
            Log::error('Error saving non-duplicate file to folder', [
                'file_name' => $file->getClientOriginalName(),
                'target_folder' => $targetFolderId,
                'error' => $e->getMessage(),
                'session_id' => $this->sessionId
            ]);

            return null;
        }
    }

    /**
     * تحديد نوع الملف
     */
    protected function determineFileType(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // الحصول على MIME type بشكل آمن
        try {
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        } catch (\Exception $e) {
            $mimeType = 'application/octet-stream';
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']) ||
            str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (in_array($extension, ['pdf']) || $mimeType === 'application/pdf') {
            return 'document';
        }

        if (in_array($extension, ['xlsx', 'xls', 'csv']) ||
            str_contains($mimeType, 'spreadsheet') ||
            str_contains($mimeType, 'excel')) {
            return 'excel';
        }

        if (in_array($extension, ['doc', 'docx']) ||
            str_contains($mimeType, 'msword') ||
            str_contains($mimeType, 'wordprocessingml')) {
            return 'document';
        }

        if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz']) ||
            str_contains($mimeType, 'zip') ||
            str_contains($mimeType, 'compress')) {
            return 'archive';
        }

        return 'other';
    }











    /**
     * إنشاء اسم ملف صحيح حسب النمط القديم
     * النمط: نوع_الوثيقة_رقم_الملف_رقم_الهوية.extension
     */
    /**
     * توليد اسم الملف الصحيح باستخدام البادئة من جدول document_types
     * النمط المطلوب: prefix_folderID_identityNumber (مثل TE102_001447_82369962)
     */
    protected function generateCorrectFileName(UploadedFile $file, string $targetFolderId, string $originalFolderName): string
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        // استخراج رقم الهوية من المقطع الثاني في اسم الملف الأصلي
        $identityNumber = $this->extractIdentityNumberFromOriginalName($originalName);

        // الحصول على البادئة من جدول document_types باستخدام المقطع الثالث
        $documentTypePrefix = $this->getDocumentTypePrefixFromDatabase($originalName);

        // إذا لم نجد البادئة، نستخدم البادئة الافتراضية
        if (empty($documentTypePrefix)) {
            $documentTypePrefix = 'DOC'; // بادئة افتراضية
            Log::warning('استخدام بادئة افتراضية', [
                'original_name' => $originalName,
                'default_prefix' => $documentTypePrefix
            ]);
        }

        // تنسيق الاسم الجديد: prefix_folderID_identityNumber.extension
        $newFileName = sprintf('%s_%s_%s.%s',
            $documentTypePrefix,
            $targetFolderId,
            $identityNumber,
            $extension
        );

        Log::info('تم توليد اسم الملف الجديد', [
            'original_name' => $originalName,
            'document_prefix' => $documentTypePrefix,
            'target_folder_id' => $targetFolderId,
            'identity_number' => $identityNumber,
            'new_file_name' => $newFileName
        ]);

        return $newFileName;
    }

    /**
     * الحصول على البادئة من جدول document_types باستخدام المقطع الثالث من اسم الملف
     */
    protected function getDocumentTypePrefixFromDatabase(string $originalFileName): ?string
    {
        try {
            // استخراج المقطع الثالث من اسم الملف (مثل _2 من H_82573265_2)
            $parts = explode('_', pathinfo($originalFileName, PATHINFO_FILENAME));

            if (count($parts) >= 3) {
                $documentTypeId = $parts[2]; // المقطع الثالث

                // البحث في جدول document_types باستخدام الـ id
                $documentType = DB::table('document_types')
                    ->where('id', $documentTypeId)
                    ->first();

                if ($documentType && isset($documentType->pref)) {
                    Log::info('تم العثور على بادئة نوع الوثيقة', [
                        'original_file' => $originalFileName,
                        'document_type_id' => $documentTypeId,
                        'found_prefix' => $documentType->pref
                    ]);
                    return $documentType->pref;
                }
            }

            Log::warning('لم يتم العثور على نوع الوثيقة', [
                'original_file' => $originalFileName,
                'parts_count' => count($parts ?? []),
                'parts' => $parts ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في الحصول على بادئة نوع الوثيقة', [
                'original_file' => $originalFileName,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * الحصول على البادئة الافتراضية
     */
    protected function getDefaultPrefix(string $folderName): string
    {
        // استخدام أول حرف من اسم المجلد كبادئة افتراضية
        return strtolower(substr($folderName, 0, 1));
    }

    /**
     * استخراج رقم الهوية من اسم الملف الأصلي (المقطع الثالث بدلاً من الثاني لتجنب الملفات المكررة الوهمية)
     */
    protected function extractIdentityNumberFromOriginalName(string $originalName): string
    {
        // تحليل الاسم لاستخراج رقم الهوية بناءً على النمط المحدث
        $parts = explode('_', pathinfo($originalName, PATHINFO_FILENAME));

        Log::info('تحليل اسم الملف لاستخراج رقم الهوية', [
            'original_name' => $originalName,
            'parts' => $parts,
            'parts_count' => count($parts)
        ]);

        // النمط المتوقع الجديد: PREFIX_FOLDER_IDENTITY أو النمط القديم: PREFIX_IDENTITY_DOCTYPE
        if (count($parts) >= 3) {
            // أولاً: تجربة النمط الجديد PREFIX_FOLDER_IDENTITY (المقطع الثالث)
            $thirdPart = $parts[2];
            if (preg_match('/^\d{8,10}$/', $thirdPart)) {
                Log::info('تم استخراج رقم الهوية من المقطع الثالث (النمط الجديد)', [
                    'original_name' => $originalName,
                    'identity_number' => $thirdPart,
                    'pattern' => 'new_format_PREFIX_FOLDER_IDENTITY'
                ]);
                return $thirdPart;
            }

            // ثانياً: تجربة النمط القديم PREFIX_IDENTITY_DOCTYPE (المقطع الثاني)
            $secondPart = $parts[1];
            if (preg_match('/^\d{8,10}$/', $secondPart)) {
                Log::info('تم استخراج رقم الهوية من المقطع الثاني (النمط القديم)', [
                    'original_name' => $originalName,
                    'identity_number' => $secondPart,
                    'pattern' => 'old_format_PREFIX_IDENTITY_DOCTYPE'
                ]);
                return $secondPart;
            }
        }

        // إذا لم نجد النمط المتوقع، ابحث عن أي رقم يشبه رقم هوية في جميع الأجزاء
        foreach ($parts as $index => $part) {
            if (preg_match('/^\d{8,10}$/', $part)) {
                Log::info('تم العثور على رقم هوية محتمل في جزء غير متوقع', [
                    'original_name' => $originalName,
                    'identity_number' => $part,
                    'part_index' => $index,
                    'pattern' => 'fallback_search'
                ]);
                return $part;
            }
        }

        Log::warning('فشل في استخراج رقم الهوية - استخدام timestamp كبديل', [
            'original_name' => $originalName,
            'parts_count' => count($parts ?? []),
            'parts' => $parts ?? []
        ]);

        // إذا لم نجد أي شيء، أرجع timestamp
        return (string) time();
    }

    /**
     * استخراج نوع الوثيقة من اسم الملف
     * From: TE102_001460_9214534563.jpg -> Returns: TE102
     * From: TES-11_001443_538002200.png -> Returns: TES-11
     */
    private function extractDocumentTypeFromFilename(string $fileName): string
    {
        try {
            $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
            $parts = explode('_', $nameWithoutExt);

            // Expected pattern: PREFIX_FOLDERID_IDENTITY
            if (count($parts) >= 3) {
                $documentType = $parts[0]; // Document type is in the first part

                Log::info('Extracted document type from filename', [
                    'filename' => $fileName,
                    'extracted_type' => $documentType
                ]);

                return $documentType;
            }

            // Fallback: return 'unknown' if pattern doesn't match
            Log::warning('Could not extract document type from filename pattern', [
                'filename' => $fileName,
                'parts_count' => count($parts),
                'parts' => $parts
            ]);

            return 'unknown';
        } catch (\Exception $e) {
            Log::error('Failed to extract document type from filename', [
                'filename' => $fileName,
                'error' => $e->getMessage()
            ]);
            return 'unknown';
        }
    }
}
