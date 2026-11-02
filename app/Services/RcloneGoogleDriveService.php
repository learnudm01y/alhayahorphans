<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * خدمة رفع الملفات إلى Google Drive باستخدام Rclone
 *
 * المعمارية:
 * temp/
 * └── [اسم الجمعية]/
 *     └── [اسم الشخص]/
 *         ├── صورة هوية.jpg
 *         ├── صورة طولية.jpg
 *         └── ...
 */
class RcloneGoogleDriveService
{
    /**
     * مسار Rclone executable
     */
    private string $rclonePath;

    /**
     * اسم Remote في Rclone config
     */
    private string $remoteName;

    /**
     * المجلد الجذر في Google Drive
     */
    private string $rootFolder;

    /**
     * مسار ملف إعدادات Rclone
     */
    private ?string $configPath;

    public function __construct()
    {
        // قراءة الإعدادات من config (تعمل بشكل صحيح مع config:cache)
        $this->rclonePath = config('services.rclone.path', env('RCLONE_PATH', '/usr/bin/rclone'));
        $this->remoteName = config('services.rclone.remote_name', env('RCLONE_REMOTE_NAME', 'alhayahorphans'));
        $this->rootFolder = config('services.rclone.root_folder', env('RCLONE_ROOT_FOLDER', 'temp'));
        $this->configPath = config('services.rclone.config', env('RCLONE_CONFIG', null));

        // التحقق من وجود Rclone
        if (!file_exists($this->rclonePath)) {
            Log::warning('Rclone not found at: ' . $this->rclonePath);
        }
    }

    /**
     * تنظيف اسم المجلد/الملف من الأحرف غير المسموحة
     */
    private function sanitizeName(string $name): string
    {
        // إزالة الأحرف غير المسموحة في أسماء الملفات
        $name = preg_replace('/[<>:"\/\\|?*]/', '_', $name);
        // إزالة المسافات الزائدة
        $name = preg_replace('/\s+/', ' ', $name);
        // إزالة النقاط في البداية والنهاية
        $name = trim($name, '. ');

        return $name ?: 'unnamed';
    }

    /**
     * بناء المسار الكامل للملف في Google Drive
     *
     * @param string $organizationName اسم الجمعية
     * @param string $personName اسم الشخص
     * @param string|null $filename اسم الملف (اختياري)
     * @return string المسار الكامل
     */
    private function buildRemotePath(string $organizationName, string $personName, ?string $filename = null): string
    {
        $orgName = $this->sanitizeName($organizationName);
        $persName = $this->sanitizeName($personName);

        $path = "{$this->remoteName}:{$this->rootFolder}/{$orgName}/{$persName}";

        if ($filename) {
            $path .= '/' . $this->sanitizeName($filename);
        }

        return $path;
    }

    /**
     * تنفيذ أمر Rclone
     *
     * @param array $arguments الوسائط
     * @return array ['success' => bool, 'output' => string, 'error' => string]
     */
    private function executeRclone(array $arguments, int $timeoutSeconds = 600, bool $logFullOutput = true): array
    {
        // إضافة مسار ملف الإعدادات إذا كان محدداً
        if ($this->configPath && file_exists($this->configPath)) {
            array_unshift($arguments, '--config', $this->configPath);
        }

        // prepend the executable to arguments
        array_unshift($arguments, $this->rclonePath);

        // Build a readable command string for logging
        $commandStr = implode(' ', array_map('escapeshellarg', $arguments));
        Log::info('RCLONE_COMMAND', ['command' => $commandStr]);

        try {
            // Laravel's Process facade; المهلة الافتراضية 10 دقائق وتُرفع للعمليات الجماعية
            $result = Process::timeout($timeoutSeconds)->run($arguments);

            $outputStr = $result->output() . "\n" . $result->errorOutput();
            $returnCode = $result->exitCode();

            Log::info('RCLONE_RESULT', [
                'return_code' => $returnCode,
                // العمليات الجماعية تُنتج مخرجات كبيرة: نسجّل آخر 2000 حرف فقط
                'output' => $logFullOutput ? $outputStr : mb_substr($outputStr, -2000)
            ]);

            return [
                'success' => $result->successful(),
                'output' => $outputStr,
                'return_code' => $returnCode
            ];
            
        } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
            Log::error('RCLONE_TIMEOUT', [
                'command' => $commandStr,
                'message' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'output' => "Process Timed Out after {$timeoutSeconds} seconds",
                'return_code' => 124 // Standard timeout exit code
            ];
        } catch (\Exception $e) {
            Log::error('RCLONE_EXECUTION_ERROR', [
                'command' => $commandStr,
                'message' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'output' => $e->getMessage(),
                'return_code' => 1
            ];
        }
    }

    /**
     * إنشاء مجلد في Google Drive (إذا لم يكن موجوداً)
     *
     * @param string $organizationName اسم الجمعية
     * @param string $personName اسم الشخص
     * @return bool نجاح العملية
     */
    public function createFolderStructure(string $organizationName, string $personName): bool
    {
        $remotePath = $this->buildRemotePath($organizationName, $personName);

        // Rclone ينشئ المجلدات تلقائياً عند الرفع
        // لكن يمكننا إنشاؤها مسبقاً باستخدام mkdir
        $result = $this->executeRclone(['mkdir', $remotePath]);

        if ($result['success']) {
            Log::info('RCLONE_FOLDER_CREATED', [
                'organization' => $organizationName,
                'person' => $personName,
                'path' => $remotePath
            ]);
        }

        return $result['success'];
    }

    /**
     * رفع ملف إلى Google Drive
     *
     * @param string $localFilePath مسار الملف المحلي
     * @param string $organizationName اسم الجمعية
     * @param string $personName اسم الشخص
     * @param string $documentTypeName اسم نوع الوثيقة (سيكون اسم الملف)
     * @param string|null $extension امتداد الملف
     * @param int $fileIndex ترتيب الملف (إذا كان هناك أكثر من ملف لنفس النوع)
     * @return array ['success' => bool, 'remote_path' => string, 'message' => string]
     */
    public function uploadFile(
        string $localFilePath,
        string $organizationName,
        string $personName,
        string $documentTypeName,
        ?string $extension = null,
        int $fileIndex = 1
    ): array {
        // التحقق من وجود الملف المحلي
        if (!file_exists($localFilePath)) {
            return [
                'success' => false,
                'remote_path' => null,
                'message' => 'الملف المحلي غير موجود: ' . $localFilePath
            ];
        }

        // بناء اسم الملف
        $baseName = $this->sanitizeName($documentTypeName);
        if ($fileIndex > 1) {
            $baseName .= '_' . $fileIndex;
        }
        if ($extension) {
            $baseName .= '.' . strtolower($extension);
        }

        // بناء المسار البعيد (المجلد فقط)
        $remoteFolder = $this->buildRemotePath($organizationName, $personName);
        $remoteFilePath = $remoteFolder . '/' . $baseName;

        // إنشاء ملف مؤقت باسم الوثيقة
        $tempDir = sys_get_temp_dir() . '/rclone_upload_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempFile = $tempDir . '/' . $baseName;
        copy($localFilePath, $tempFile);

        // رفع الملف باستخدام Rclone copy
        $result = $this->executeRclone([
            'copy',
            $tempFile,
            $remoteFolder,
            '--progress'
        ]);

        // تنظيف الملف المؤقت
        @unlink($tempFile);
        @rmdir($tempDir);

        if ($result['success']) {
            Log::info('RCLONE_FILE_UPLOADED', [
                'local_path' => $localFilePath,
                'remote_path' => $remoteFilePath,
                'organization' => $organizationName,
                'person' => $personName,
                'document_type' => $documentTypeName
            ]);

            return [
                'success' => true,
                'remote_path' => $remoteFilePath,
                'filename' => $baseName,
                'message' => 'تم رفع الملف بنجاح'
            ];
        } else {
            Log::error('RCLONE_UPLOAD_FAILED', [
                'local_path' => $localFilePath,
                'remote_path' => $remoteFilePath,
                'error' => $result['output']
            ]);

            return [
                'success' => false,
                'remote_path' => null,
                'message' => 'فشل رفع الملف: ' . $result['output']
            ];
        }
    }

    /**
     * رفع مجموعة من المرفقات
     *
     * @param array $attachments مصفوفة المرفقات [docTypeId => [files]]
     * @param string $organizationName اسم الجمعية
     * @param string $personName اسم الشخص
     * @param string $personIdentity رقم هوية الشخص
     * @return array نتائج الرفع
     */
    public function uploadAttachments(
        array $attachments,
        string $organizationName,
        string $personName,
        string $personIdentity
    ): array {
        $results = [
            'success' => true,
            'uploaded' => 0,
            'failed' => 0,
            'files' => []
        ];

        // إنشاء هيكل المجلدات أولاً
        $this->createFolderStructure($organizationName, $personName);

        foreach ($attachments as $docTypeId => $files) {
            // الحصول على نوع المستند
            $documentType = \App\Models\DocumentType::find($docTypeId);
            if (!$documentType) {
                Log::warning('RCLONE_UNKNOWN_DOC_TYPE', ['doc_type_id' => $docTypeId]);
                continue;
            }

            $documentTypeName = $documentType->description ?: 'وثيقة';
            $fileIndex = 0;

            foreach ((array)$files as $file) {
                $fileIndex++;

                // التحقق من صلاحية الملف
                if (!$file || !$file->isValid()) {
                    Log::warning('RCLONE_INVALID_FILE', [
                        'doc_type' => $documentTypeName,
                        'error' => $file ? $file->getErrorMessage() : 'null file'
                    ]);
                    $results['failed']++;
                    continue;
                }

                $extension = strtolower($file->getClientOriginalExtension() ?: '');
                $localPath = $file->getRealPath();

                $uploadResult = $this->uploadFile(
                    $localPath,
                    $organizationName,
                    $personName,
                    $documentTypeName,
                    $extension,
                    $fileIndex
                );

                if ($uploadResult['success']) {
                    // حفظ في قاعدة البيانات
                    \App\Models\Attachment::create([
                        'person_identity_number' => $personIdentity,
                        'stored_file_name' => $uploadResult['filename'],
                        'file_path' => $uploadResult['remote_path'],
                        'file_type' => $docTypeId,
                    ]);

                    $results['uploaded']++;
                    $results['files'][] = [
                        'document_type' => $documentTypeName,
                        'filename' => $uploadResult['filename'],
                        'remote_path' => $uploadResult['remote_path']
                    ];
                } else {
                    $results['failed']++;
                    $results['success'] = false;
                }
            }
        }

        Log::info('RCLONE_BATCH_UPLOAD_COMPLETED', [
            'organization' => $organizationName,
            'person' => $personName,
            'identity' => $personIdentity,
            'uploaded' => $results['uploaded'],
            'failed' => $results['failed']
        ]);

        return $results;
    }

    /**
     * التحقق من اتصال Rclone
     */
    public function testConnection(): array
    {
        $result = $this->executeRclone(['about', $this->remoteName . ':']);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'الاتصال بـ Google Drive ناجح',
                'details' => $result['output']
            ];
        } else {
            return [
                'success' => false,
                'message' => 'فشل الاتصال بـ Google Drive',
                'error' => $result['output']
            ];
        }
    }

    /**
     * عرض محتويات مجلد
     */
    public function listFolder(string $organizationName = null, string $personName = null): array
    {
        if ($organizationName && $personName) {
            $remotePath = $this->buildRemotePath($organizationName, $personName);
        } elseif ($organizationName) {
            $remotePath = "{$this->remoteName}:{$this->rootFolder}/" . $this->sanitizeName($organizationName);
        } else {
            $remotePath = "{$this->remoteName}:{$this->rootFolder}";
        }

        $result = $this->executeRclone(['ls', $remotePath]);

        return [
            'success' => $result['success'],
            'path' => $remotePath,
            'files' => $result['success'] ? explode("\n", trim($result['output'])) : []
        ];
    }

    /**
     * تحميل محتوى ملف من Google Drive
     *
     * @param string $remotePath المسار الكامل للملف (مثل alhayah:temp/org/person/file.jpg)
     * @return array ['success' => bool, 'content' => string, 'mime_type' => string]
     */
    public function getFileContent(string $remotePath): array
    {
        // إنشاء ملف مؤقت لتحميل الملف إليه
        $tempFile = sys_get_temp_dir() . '/rclone_download_' . uniqid() . '_' . basename($remotePath);

        // تحميل الملف
        $result = $this->executeRclone(['copyto', $remotePath, $tempFile]);

        if (!$result['success'] || !file_exists($tempFile)) {
            @unlink($tempFile);
            return [
                'success' => false,
                'content' => null,
                'mime_type' => null,
                'message' => 'فشل تحميل الملف: ' . ($result['output'] ?? 'الملف غير موجود')
            ];
        }

        // قراءة محتوى الملف
        $content = file_get_contents($tempFile);
        $mimeType = mime_content_type($tempFile) ?: 'application/octet-stream';

        // حذف الملف المؤقت
        @unlink($tempFile);

        return [
            'success' => true,
            'content' => $content,
            'mime_type' => $mimeType,
            'message' => 'تم تحميل الملف بنجاح'
        ];
    }

    /**
     * رفع مجلد كامل إلى Google Drive (رفع جماعي).
     *
     * يُنشئ المسار: {remoteName}:{rootFolder}/{organizationName}/{subFolder}/{operationName}/
     *
     * @param string $localFolderPath   المسار المحلي الكامل للمجلد
     * @param string $organizationName  اسم الجمعية
     * @param string $subFolder         مجلد فرعي داخل الجمعية (مثل "تقارير شاملة")
     * @param string $operationName     اسم العملية (اسم المجلد النهائي)
     * @return array ['success' => bool, 'remote_path' => string, 'message' => string]
     */
    public function uploadFolder(
        string $localFolderPath,
        string $organizationName,
        string $subFolder,
        string $operationName
    ): array {
        if (!is_dir($localFolderPath)) {
            return ['success' => false, 'remote_path' => null, 'message' => 'المجلد المحلي غير موجود'];
        }

        $orgSafe       = $this->sanitizeName($organizationName);
        $subSafe       = $this->sanitizeName($subFolder);
        $opSafe        = $this->sanitizeName($operationName);
        $remotePath    = "{$this->remoteName}:{$this->rootFolder}/{$orgSafe}/{$subSafe}/{$opSafe}";

        $result = $this->executeRclone([
            'copy',
            $localFolderPath,
            $remotePath,
            '--progress',
            '--no-update-modtime',
        ]);

        if ($result['success']) {
            Log::info('RCLONE_FOLDER_UPLOADED', [
                'local'  => $localFolderPath,
                'remote' => $remotePath,
            ]);
            return ['success' => true, 'remote_path' => $remotePath, 'message' => 'تم رفع المجلد بنجاح'];
        }

        return ['success' => false, 'remote_path' => $remotePath, 'message' => $result['output'] ?? 'فشل الرفع'];
    }

    /**
     * رفع شجرة مجلدات كاملة بعملية rclone واحدة مباشرة تحت مجلد الجهة الداعمة.
     *
     * المسار الناتج: {remoteName}:{rootFolder}/{organizationName}[/{subFolder}]/<محتويات المجلد المحلي>
     * (بخلاف uploadFolder لا يُضاف مجلد وسيط؛ ومعامل $subFolder اختياري فقط)
     *
     * - عملية واحدة بدل عملية لكل مجلد (أسرع بكثير مع آلاف المجلدات).
     * - `rclone copy` لا يحذف شيئاً من الوجهة ويتخطى الملفات المطابقة (بالحجم) عند إعادة التشغيل.
     * - المجلدات الفارغة لا تُنشأ على الدرايف.
     *
     * @param string      $localDir          المجلد المحلي الذي تُرفع محتوياته
     * @param string      $organizationName  اسم الجهة الداعمة
     * @param string|null $subFolder         مجلد فرعي اختياري داخل الجهة
     * @param int         $timeoutSeconds    مهلة العملية بالثواني
     * @param array       $extraArgs         وسائط rclone إضافية (مثل --exclude)
     * @return array ['success' => bool, 'remote_path' => string, 'message' => string]
     */
    public function uploadTree(
        string $localDir,
        string $organizationName,
        ?string $subFolder = null,
        int $timeoutSeconds = 14400,
        array $extraArgs = []
    ): array {
        if (!is_dir($localDir)) {
            return ['success' => false, 'remote_path' => null, 'message' => 'المجلد المحلي غير موجود'];
        }

        $remotePath = "{$this->remoteName}:{$this->rootFolder}/" . $this->sanitizeName($organizationName);
        if ($subFolder !== null && trim($subFolder) !== '') {
            $remotePath .= '/' . $this->sanitizeName($subFolder);
        }

        $transfers = (int) config('services.rclone.transfers', 8);
        $checkers  = (int) config('services.rclone.checkers', 16);

        $arguments = array_merge([
            'copy',
            $localDir,
            $remotePath,
            '--transfers', (string) max(1, $transfers),
            '--checkers', (string) max(1, $checkers),
            '--retries', '5',
            '--low-level-retries', '20',
            '--retries-sleep', '10s',
            '--size-only',          // الملفات ثابتة المحتوى (الاسم يبدأ بمعرّف المرفق)، فيكفي الحجم لتخطي ما رُفع
            '--no-update-modtime',
            '--fast-list',
            '--stats', '60s',
            '--stats-one-line',
        ], $extraArgs);

        $result = $this->executeRclone($arguments, $timeoutSeconds, false);

        if ($result['success']) {
            Log::info('RCLONE_TREE_UPLOADED', ['local' => $localDir, 'remote' => $remotePath]);
            return ['success' => true, 'remote_path' => $remotePath, 'message' => 'تم رفع المجلد بنجاح'];
        }

        Log::error('RCLONE_TREE_UPLOAD_FAILED', [
            'local' => $localDir,
            'remote' => $remotePath,
            'return_code' => $result['return_code'] ?? null,
            'output_tail' => mb_substr((string) ($result['output'] ?? ''), -1500),
        ]);

        return [
            'success' => false,
            'remote_path' => $remotePath,
            'message' => mb_substr((string) ($result['output'] ?? 'فشل الرفع'), -500),
        ];
    }

    /**
     * التحقق من وجود ملف
     *
     * @param string $remotePath المسار الكامل للملف
     * @return bool
     */
    public function fileExists(string $remotePath): bool
    {
        $result = $this->executeRclone(['lsf', $remotePath]);
        return $result['success'] && !empty(trim($result['output']));
    }

    /**
     * الحصول على رابط مباشر للملف (إذا كان متاحاً)
     *
     * @param string $remotePath المسار الكامل للملف
     * @return array ['success' => bool, 'url' => string]
     */
    public function getPublicLink(string $remotePath): array
    {
        $result = $this->executeRclone(['link', $remotePath]);

        if ($result['success'] && !empty(trim($result['output']))) {
            return [
                'success' => true,
                'url' => trim($result['output'])
            ];
        }

        return [
            'success' => false,
            'url' => null,
            'message' => 'لا يمكن إنشاء رابط مباشر للملف'
        ];
    }
}
