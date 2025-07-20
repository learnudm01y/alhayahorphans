<?php

namespace App\Http\Controllers;

use App\Services\FileOrganizationService;
use App\Services\CloudIntegrationService;
use App\Services\ImageProcessingService;
use App\Services\ExcelManagementService;
use App\Services\PdfManagementService;
use App\Services\ExcelImportService;
use App\Services\FolderDuplicateDetectionService;
use App\Models\Attachment;
use App\Models\EnhancedAttachment;
use App\Models\DuplicateFileTemp;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class UnifiedFileManagementController extends Controller
{
    protected $fileOrganizer;
    protected $cloudIntegration;
    protected $imageProcessor;
    protected $excelManager;
    protected $pdfManager;
    protected $excelImportService;
    protected $duplicateDetectionService;

    public function __construct(
        ?FileOrganizationService $fileOrganizer = null,
        ?CloudIntegrationService $cloudIntegration = null,
        ?ImageProcessingService $imageProcessor = null,
        ?ExcelManagementService $excelManager = null,
        ?PdfManagementService $pdfManager = null,
        ?ExcelImportService $excelImportService = null,
        ?FolderDuplicateDetectionService $duplicateDetectionService = null
    ) {
        $this->fileOrganizer = $fileOrganizer;
        $this->cloudIntegration = $cloudIntegration;
        $this->imageProcessor = $imageProcessor;
        $this->excelManager = $excelManager;
        $this->pdfManager = $pdfManager;
        $this->excelImportService = $excelImportService ?: new ExcelImportService();
        $this->duplicateDetectionService = $duplicateDetectionService ?: new FolderDuplicateDetectionService();
    }

    /**
     * Smart file upload with automatic processing
     */
    public function smartUpload(Request $request)
    {
        Log::info('Smart upload started', [
            'files_count' => $request->hasFile('files') ? count($request->file('files')) : 0,
            'record_number' => $request->input('record_number'),
            'person_id' => $request->input('person_id')
        ]);

        $request->validate([
            'files.*' => 'required|file|max:1048576', // 1GB = 1048576 KB
            'record_number' => 'nullable|string|max:20',
            'person_id' => 'nullable|string|max:20',
            'auto_compress' => 'nullable|boolean',
            'cloud_sync' => 'nullable|boolean',
            'ocr_processing' => 'nullable|boolean',
            'auto_organize' => 'nullable|boolean',
            'cloud_providers' => 'nullable|string',
            'excel_import_mode' => 'nullable|boolean',
            'bulk_images_folders' => 'nullable|array'
        ]);

        try {
            DB::beginTransaction();

            $results = [];
            $recordNumber = $request->input('record_number');

            // Auto-generate record number if not provided
            if (empty($recordNumber)) {
                $recordNumber = $this->autoGenerateRecordNumber();
                Log::info('Auto-generated record number: ' . $recordNumber);
            }

            $personId = $request->input('person_id');

            // Build processing options from individual form fields
            $processingOptions = [
                'compress' => filter_var($request->input('auto_compress', false), FILTER_VALIDATE_BOOLEAN),
                'cloud_sync' => filter_var($request->input('cloud_sync', false), FILTER_VALIDATE_BOOLEAN),
                'ocr' => filter_var($request->input('ocr_processing', false), FILTER_VALIDATE_BOOLEAN),
                'auto_organize' => filter_var($request->input('auto_organize', true), FILTER_VALIDATE_BOOLEAN)
            ];

            $shouldCloudSync = $processingOptions['cloud_sync'];
            $autoOrganize = $processingOptions['auto_organize'];

            // Parse cloud providers if provided
            $cloudProviders = [];
            if ($request->has('cloud_providers')) {
                $cloudProviders = json_decode($request->input('cloud_providers'), true) ?? [];
            }

            foreach ($request->file('files') as $index => $file) {
                $fileResult = $this->processUploadedFile(
                    $file,
                    $recordNumber,
                    $personId,
                    $processingOptions,
                    $shouldCloudSync,
                    $autoOrganize,
                    $cloudProviders
                );

                $results[] = $fileResult;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded and processed successfully',
                'results' => $results,
                'total_files' => count($results),
                'successful_uploads' => count(array_filter($results, fn($r) => $r['success']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Smart upload error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء رفع الملفات. يرجى المحاولة مرة أخرى أو التواصل مع الدعم الفني.'
            ], 500);
        }
    }

    /**
     * Process individual uploaded file with new storage logic
     */
    private function processUploadedFile($file, string $recordNumber, ?string $personId, array $options, bool $cloudSync, bool $autoOrganize, array $cloudProviders = []): array
    {
        try {
            // Detect file type
            $fileType = $this->detectFileType($file);

            // Handle different file types with separate storage logic
            switch ($fileType) {
                case 'image':
                    return $this->processImageFile($file, $recordNumber, $personId, $options);

                case 'pdf':
                case 'excel':
                    return $this->processDocumentFile($file, $recordNumber, $personId, $options, $fileType);

                default:
                    throw new \Exception('Unsupported file type: ' . $fileType);
            }

        } catch (\Exception $e) {
            Log::error('File processing error: ' . $e->getMessage(), [
                'file_name' => $file->getClientOriginalName(),
                'user_id' => auth()->id(),
                'ip' => request()->ip()
            ]);
            return [
                'success' => false,
                'error' => 'فشل في معالجة الملف. يرجى التأكد من نوع وحجم الملف.',
                'original_name' => $file->getClientOriginalName()
            ];
        }
    }

    /**
     * Process image files - store in attachments table
     */
    private function processImageFile($file, string $recordNumber, ?string $personId, array $options): array
    {
        try {
            // Parse filename: {part1}_{part2}_{part3}.ext
            $originalName = $file->getClientOriginalName();
            $matches = [];
            $part1 = $part2 = $part3 = $ext = null;
            if (preg_match('/^([^_]+)_([^_]+)_([^_.]+)(\.(\w+))?$/', $originalName, $matches)) {
                $part1 = $matches[1];
                $part2 = $matches[2];
                $part3 = $matches[3];
                $ext = isset($matches[5]) ? $matches[5] : '';
            }

            // تحقق من وجود رقم الملف أو رقم الهوية في جدول data
            $dataRecord = DB::table('data')
                ->where('file_id_number', $part2)
                ->orWhere('data_id_number', $part2)
                ->orWhere('file_id_number', $part3)
                ->orWhere('data_id_number', $part3)
                ->first();

            $folderId = $recordNumber; // fallback
            if ($dataRecord) {
                if ($dataRecord->file_id_number == $part2 || $dataRecord->data_id_number == $part2) {
                    $folderId = $part2;
                } elseif ($dataRecord->file_id_number == $part3 || $dataRecord->data_id_number == $part3) {
                    $folderId = $part3;
                }
            }

            // بناء الاسم الجديد: {part1}_{folderId}_{part2}.{ext}
            $newFileName = $part1 . '_' . $folderId . '_' . $part2;
            if ($ext) {
                $newFileName .= '.' . $ext;
            }

            // Apply compression if enabled (only for images)
            $compressedFile = null;
            if ($options['compress'] ?? false) {
                $compressedFile = $this->compressImage($file);
            }
            $finalFile = $compressedFile ?? $file;

            // Store in uploads/{folderId}/
            $folderName = "uploads/{$folderId}";
            $fullPath = storage_path("app/public/{$folderName}");
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            $filePath = $finalFile->storeAs($folderName, $newFileName, 'public');

            // Save to attachments table (images only)
            $this->saveImageRecordWithCustomName($finalFile, $folderId, $part2 ?: 'unknown', $filePath, $newFileName);

            // Check file existence before accessing size
            $filePathname = method_exists($finalFile, 'getPathname') ? $finalFile->getPathname() : null;
            $fileSize = ($filePathname && file_exists($filePathname)) ? $finalFile->getSize() : null;

            return [
                'success' => true,
                'original_name' => $originalName,
                'file_type' => 'image',
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'identity_number' => $part2,
                'custom_name' => $newFileName,
                'compressed' => $compressedFile !== null
            ];

        } catch (\Exception $e) {
            Log::error('Image processing error: ' . $e->getMessage(), [
                'file_name' => $file->getClientOriginalName(),
                'user_id' => auth()->id(),
                'ip' => request()->ip()
            ]);
            throw new \Exception('فشل في معالجة الصورة. يرجى التأكد من صيغة اسم الملف والحجم.');
        }
    }

    /**
     * Process document files (PDF/Excel) - store in enhanced_attachments table
     */
    private function processDocumentFile($file, string $recordNumber, ?string $personId, array $options, string $fileType): array
    {
        try {
            // Store in specialized folder based on file type
            $filePath = $this->storeDocumentFile($file, $fileType);

            // Save to enhanced_attachments table (PDF/Excel only)
            $this->saveDocumentRecord($file, $recordNumber, $personId, $filePath, $fileType);

            // Handle Excel import if requested
            $importResult = null;
            if ($fileType === 'excel' && ($options['excel_import'] ?? false)) {
                $importResult = $this->processExcelImport($filePath, $options);
            }

            // Check file existence before accessing size
            $filePathname = method_exists($file, 'getPathname') ? $file->getPathname() : null;
            $fileSize = ($filePathname && file_exists($filePathname)) ? $file->getSize() : null;

            return [
                'success' => true,
                'original_name' => $file->getClientOriginalName(),
                'file_type' => $fileType,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'import_result' => $importResult
            ];

        } catch (\Exception $e) {
            Log::error('Document processing error: ' . $e->getMessage(), [
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $fileType,
                'user_id' => auth()->id(),
                'ip' => request()->ip()
            ]);
            throw new \Exception('فشل في معالجة المستند. يرجى التأكد من نوع الملف وصحته.');
        }
    }

    /**
     * Batch upload with progress tracking
     */
    public function batchUpload(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|max:51200',
            'record_numbers.*' => 'required|string',
            'batch_id' => 'required|string',
            'processing_options' => 'nullable|array'
        ]);

        try {
            $batchId = $request->input('batch_id');
            $files = $request->file('files');
            $recordNumbers = $request->input('record_numbers');
            $options = $request->input('processing_options', []);

            // Initialize batch tracking
            cache()->put("batch_{$batchId}_total", count($files), 3600);
            cache()->put("batch_{$batchId}_processed", 0, 3600);
            cache()->put("batch_{$batchId}_status", 'processing', 3600);

            // Queue batch processing job
            Queue::push('ProcessBatchUploadJob', [
                'batch_id' => $batchId,
                'files' => $files,
                'record_numbers' => $recordNumbers,
                'options' => $options
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch upload initiated',
                'batch_id' => $batchId,
                'total_files' => count($files),
                'tracking_url' => route('file.batch.status', ['batch_id' => $batchId])
            ]);

        } catch (\Exception $e) {
            Log::error('Batch upload error: ' . $e->getMessage(), [
                'batch_id' => $batchId,
                'user_id' => auth()->id(),
                'ip' => request()->ip()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في بدء الرفع المجمع. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }

    /**
     * Get batch upload progress
     */
    public function getBatchStatus(Request $request, string $batchId)
    {
        $total = cache()->get("batch_{$batchId}_total", 0);
        $processed = cache()->get("batch_{$batchId}_processed", 0);
        $status = cache()->get("batch_{$batchId}_status", 'unknown');
        $errors = cache()->get("batch_{$batchId}_errors", []);

        $progress = $total > 0 ? round(($processed / $total) * 100, 2) : 0;

        return response()->json([
            'batch_id' => $batchId,
            'status' => $status,
            'total_files' => $total,
            'processed_files' => $processed,
            'progress_percentage' => $progress,
            'errors' => $errors,
            'remaining_files' => $total - $processed
        ]);
    }

    /**
     * Search files with advanced filters
     */
    public function searchFiles(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|max:255',
            'file_type' => 'nullable|string|in:image,pdf,excel,word,all',
            'record_number' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'person_id' => 'nullable|string',
            'tags' => 'nullable|array',
            'processing_status' => 'nullable|string',
            'access_level' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $query = DB::table('enhanced_attachments');

            // Apply filters
            if ($request->filled('query')) {
                $searchTerm = $request->input('query');
                $query->where(function($q) use ($searchTerm) {
                    $q->where('original_file_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('extracted_text', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('business_notes', 'LIKE', "%{$searchTerm}%");
                });
            }

            if ($request->filled('file_type') && $request->input('file_type') !== 'all') {
                $query->where('file_type', $request->input('file_type'));
            }

            if ($request->filled('record_number')) {
                $query->where('record_number', $request->input('record_number'));
            }

            if ($request->filled('person_id')) {
                $query->where('person_identity_number', $request->input('person_id'));
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->input('date_to'));
            }

            if ($request->filled('processing_status')) {
                $query->where('processing_status', $request->input('processing_status'));
            }

            if ($request->filled('access_level')) {
                $query->where('access_level', $request->input('access_level'));
            }

            // Apply tag filtering
            if ($request->filled('tags')) {
                $tags = $request->input('tags');
                $query->where(function($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }

            // Pagination
            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            $total = $query->count();
            $results = $query->offset(($page - 1) * $perPage)
                            ->limit($perPage)
                            ->orderBy('created_at', 'desc')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => $results,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ],
                'filters_applied' => $request->only([
                    'query', 'file_type', 'record_number', 'date_from',
                    'date_to', 'person_id', 'tags', 'processing_status', 'access_level'
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('File search error: ' . $e->getMessage(), [
                'search_params' => $request->only(['query', 'file_type', 'record_number']),
                'user_id' => auth()->id(),
                'ip' => request()->ip()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في البحث. يرجى تعديل معايير البحث والمحاولة مرة أخرى.'
            ], 500);
        }
    }

    /**
     * Get file analytics and statistics
     */
    public function getFileAnalytics(Request $request)
    {
        try {
            // استخدام الدوال الموجودة للحصول على الإحصائيات
            $analytics = [
                'overview' => $this->getOverviewStats(),
                'file_types' => $this->getFileTypeStats(),
                'processing_status' => $this->getProcessingStatusStats(),
                'storage_usage' => $this->getStorageUsageStats(),
                'upload_trends' => $this->getUploadTrends(),
                'folder_analysis' => $this->getFolderAnalysisStats(),
                'recent_activity' => $this->getRecentActivityStats()
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'generated_at' => now()->toISOString(),
                'message' => 'تم جلب الإحصائيات بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics error: ' . $e->getMessage(), [
                'user_id' => auth()->id() ?? 'guest',
                'ip' => request()->ip(),
                'trace' => $e->getTraceAsString()
            ]);

            // إرجاع بيانات افتراضية بدلاً من خطأ 500
            return response()->json([
                'success' => true,
                'analytics' => [
                    'overview' => ['total_files' => 0, 'total_size' => 0, 'total_folders' => 0, 'today_uploads' => 0],
                    'file_types' => [],
                    'processing_status' => ['processed' => 0, 'pending' => 0, 'failed' => 0],
                    'storage_usage' => ['used' => 0, 'available' => 0, 'percentage' => 0],
                    'upload_trends' => [],
                    'folder_analysis' => [],
                    'recent_activity' => []
                ],
                'generated_at' => now()->toISOString(),
                'message' => 'تم إرجاع بيانات افتراضية بسبب خطأ مؤقت'
            ]);
        }
    }

    /**
     * Sync files with cloud storage
     */
    public function syncWithCloud(Request $request)
    {
        $request->validate([
            'record_number' => 'nullable|string',
            'providers' => 'nullable|array|in:google_drive,onedrive',
            'sync_type' => 'required|string|in:upload,download,bidirectional'
        ]);

        try {
            $recordNumber = $request->input('record_number');
            $providers = $request->input('providers', ['google_drive', 'onedrive']);
            $syncType = $request->input('sync_type');

            $results = match($syncType) {
                'upload' => $this->cloudIntegration->syncWithCloud($recordNumber, $providers),
                'download' => $this->downloadFromCloud($recordNumber, $providers),
                'bidirectional' => $this->bidirectionalSync($recordNumber, $providers),
                default => throw new \InvalidArgumentException('Invalid sync type')
            };

            return response()->json([
                'success' => true,
                'message' => 'Cloud sync completed',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Cloud sync error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Cloud sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new record number using the standardized algorithm from global_helper
     */
    public function generateRecordNumber()
    {
        try {
            // استخدام الخوارزمية المعتمدة من global_helper
            $newRecordNumber = generateFileIdFromDataTable();

            return response()->json([
                'success' => true,
                'record_number' => $newRecordNumber,
                'message' => 'تم إنشاء رقم السجل بنجاح باستخدام الخوارزمية المعتمدة'
            ]);

        } catch (\Exception $e) {
            Log::error('Record number generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'فشل في إنشاء رقم السجل',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-generate record number during upload if not provided - uses standardized algorithm
     */
    private function autoGenerateRecordNumber(): string
    {
        try {
            // استخدام الخوارزمية المعتمدة من global_helper
            return generateFileIdFromDataTable();
        } catch (\Exception $e) {
            Log::warning('Auto record generation failed, using fallback: ' . $e->getMessage());
            // استخدام fallback من generateUniqueReservedCode
            return generateUniqueReservedCode('data', 'file_id_number') ?? str_pad(substr(time(), -6), 6, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Excel import with bulk image folders processing
     */
    public function excelBulkImport(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
            'image_folders' => 'required|array',
            'image_folders.*' => 'file|mimes:zip,rar'
        ]);

        try {
            DB::beginTransaction();

            // Store Excel file in dedicated folder
            $excelFile = $request->file('excel_file');
            $excelPath = $this->storeExcelFile($excelFile);

            // Process Excel data
            $excelData = $this->processExcelForImport($excelPath);

            // Extract and process image folders
            $extractedFolders = $this->extractImageFolders($request->file('image_folders'));

            // Import records to database
            $importResults = $this->importExcelDataToDatabase($excelData, $extractedFolders);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Excel bulk import completed successfully',
                'results' => $importResults
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Excel bulk import error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Excel bulk import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process folder upload with strict validation against existing data
     * Folder names must match either identity_number or file_id_number in 'data' table
     */
    public function processFolderUpload(Request $request)
    {
        try {
            $request->validate([
                'files.*' => 'required|file|max:1024000',
                'paths.*' => 'nullable|string', // مسارات المجلدات النسبية
                'enable_excel_import' => 'nullable|boolean',
                'excel_file' => 'nullable|file|mimes:xlsx,xls,csv',
                'target_table' => 'nullable|string|in:data,dead_people,guardian_bank_accounts,re_people'
            ]);

            $files = $request->file('files');
            $paths = $request->input('paths', []);
            $processedFiles = [];
            $validatedFolders = [];
            $rejectedFolders = [];

            // استخراج أسماء المجلدات من المسارات مع البحث عن مجلدات الهوية في جميع المستويات
            $folderNames = [];
            $identityFolders = [];
            $pathToFolderMapping = [];

            foreach ($paths as $index => $path) {
                if ($path) {
                    $parts = explode('/', $path);
                    array_pop($parts); // إزالة اسم الملف للحصول على مسار المجلد فقط

                    // البحث عن مجلدات الهوية في جميع أجزاء المسار
                    foreach ($parts as $part) {
                        if (!in_array($part, $folderNames)) {
                            $folderNames[] = $part;
                        }

                        // فحص إذا كان الجزء يشبه رقم هوية (8-10 أرقام)
                        if (preg_match('/^\d{8,10}$/', $part)) {
                            if (!in_array($part, $identityFolders)) {
                                $identityFolders[] = $part;
                            }
                            // ربط المسار بمجلد الهوية المكتشف
                            $pathToFolderMapping[$index] = $part;
                        }
                    }

                    // إذا لم نجد مجلد هوية في هذا المسار، استخدم الطريقة القديمة كـ fallback
                    if (!isset($pathToFolderMapping[$index])) {
                        $possibleFolder = $parts[0]; // المجلد الأول في المسار
                        if (preg_match('/^\d{8,10}$/', $possibleFolder)) {
                            $pathToFolderMapping[$index] = $possibleFolder;
                            if (!in_array($possibleFolder, $identityFolders)) {
                                $identityFolders[] = $possibleFolder;
                            }
                        }
                    }
                }
            }

            Log::info('Extracted folder names for validation', [
                'all_folders' => $folderNames,
                'identity_folders' => $identityFolders,
                'path_mappings' => $pathToFolderMapping
            ]);

            // التحقق من وجود مجلدات الهوية في جدول data وتحديد المجلد النهائي للتخزين
            foreach ($identityFolders as $folderName) {
                // البحث عن data_id_number في جدول data
                $dataRecord = DB::table('data')
                    ->where('data_id_number', $folderName)
                    ->first();

                if ($dataRecord) {
                    // تحديد اسم المجلد النهائي للتخزين
                    $finalFolderName = $this->determineFinalFolderName($folderName, $dataRecord->file_id_number);

                    $validatedFolders[$folderName] = [
                        'original_folder_name' => $folderName,
                        'file_id_number' => $dataRecord->file_id_number,
                        'data_id_number' => $dataRecord->data_id_number,
                        'final_storage_folder' => $finalFolderName,
                        'folder_exists_in_storage' => $this->checkStorageFolderExists($finalFolderName),
                        'matched_by' => 'data_id'
                    ];

                    Log::info("Folder validated and storage determined", [
                        'original_folder' => $folderName,
                        'file_id_number' => $dataRecord->file_id_number,
                        'final_storage_folder' => $finalFolderName,
                        'folder_exists' => $validatedFolders[$folderName]['folder_exists_in_storage']
                    ]);
                } else {
                    // إذا لم يوجد في data_id_number، تحقق من file_id_number
                    $dataByFileId = DB::table('data')
                        ->where('file_id_number', $folderName)
                        ->first();

                    if ($dataByFileId) {
                        $finalFolderName = $this->determineFinalFolderName($folderName, $dataByFileId->file_id_number);

                        $validatedFolders[$folderName] = [
                            'original_folder_name' => $folderName,
                            'file_id_number' => $dataByFileId->file_id_number,
                            'data_id_number' => $dataByFileId->data_id_number,
                            'final_storage_folder' => $finalFolderName,
                            'folder_exists_in_storage' => $this->checkStorageFolderExists($finalFolderName),
                            'matched_by' => 'file_id'
                        ];

                        Log::info("Folder validated by file_id and storage determined", [
                            'original_folder' => $folderName,
                            'file_id_number' => $dataByFileId->file_id_number,
                            'final_storage_folder' => $finalFolderName,
                            'folder_exists' => $validatedFolders[$folderName]['folder_exists_in_storage']
                        ]);
                    } else {
                        $rejectedFolders[] = $folderName;
                        Log::warning("Identity folder rejected - not found in data table", [
                            'folder_name' => $folderName,
                            'checked_columns' => ['data_id_number', 'file_id_number']
                        ]);
                    }
                }
            }

            // تسجيل المجلدات الأب التي تم تجاهلها (المجلدات غير الهوية)
            $ignoredParentFolders = array_diff($folderNames, $identityFolders);
            if (!empty($ignoredParentFolders)) {
                Log::info("Parent folders ignored (not identity folders)", [
                    'ignored_folders' => $ignoredParentFolders,
                    'reason' => 'Not matching identity pattern (8-10 digits)'
                ]);
            }

            // لا نرفض العملية إذا كانت هناك مجلدات هوية صالحة، حتى لو كانت هناك مجلدات أب غير صالحة
            if (empty($validatedFolders) && !empty($rejectedFolders)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد مجلدات هوية صحيحة للمعالجة',
                    'rejected_folders' => $rejectedFolders,
                    'ignored_parent_folders' => $ignoredParentFolders,
                    'error_details' => 'يجب أن تحتوي المجلدات على أسماء تطابق أرقام الهوية الموجودة في النظام (8-10 أرقام)'
                ], 422);
            }

            // معالجة كل ملف مع التحقق من النوع والتخزين المناسب
            $errors = [];
            $warnings = [];

            foreach ($files as $index => $file) {
                $path = $paths[$index] ?? '';
                $originalFolderName = '';
                $targetFileId = '';

                // استخدام خريطة المسار إلى المجلد للعثور على مجلد الهوية الصحيح
                if (isset($pathToFolderMapping[$index])) {
                    $originalFolderName = $pathToFolderMapping[$index];

                    if (isset($validatedFolders[$originalFolderName])) {
                        $targetFileId = $validatedFolders[$originalFolderName]['file_id_number'];
                    } else {
                        // تخطي الملف إذا كان مجلد الهوية غير صحيح
                        Log::warning("Skipping file - identity folder not validated", [
                            'file_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'identity_folder' => $originalFolderName
                        ]);
                        continue;
                    }
                } else {
                    // تخطي الملفات التي لا تحتوي على مجلد هوية صحيح
                    Log::warning("Skipping file - no identity folder found in path", [
                        'file_name' => $file->getClientOriginalName(),
                        'path' => $path
                    ]);
                    continue;
                }

                // معالجة الملف حسب النوع مع كشف التكرار
                $processedFile = $this->processValidatedFolderFileWithDuplicateCheck(
                    $file,
                    $targetFileId,
                    $originalFolderName,
                    $path,
                    $validatedFolders[$originalFolderName], // تمرير معلومات المجلد كاملة
                    $index
                );
                $processedFiles[] = $processedFile;

                // جمع الأخطاء والتحذيرات
                if (!$processedFile['success']) {
                    if (isset($processedFile['duplicate_detected']) && $processedFile['duplicate_detected']) {
                        // معالجة خاصة للصور المكررة
                        $warnings[] = [
                            'file_name' => $file->getClientOriginalName(),
                            'folder' => $originalFolderName,
                            'type' => 'duplicate_file',
                            'warning' => $processedFile['error'],
                            'existing_file_info' => $processedFile['existing_file_info'],
                            'duplicate_temp_path' => $processedFile['duplicate_temp_path'],
                            'session_id' => $processedFile['session_id']
                        ];
                    } else {
                        $errors[] = [
                            'file_name' => $file->getClientOriginalName(),
                            'folder' => $originalFolderName,
                            'error' => $processedFile['error']
                        ];
                    }
                } elseif (isset($processedFile['warning'])) {
                    $warnings[] = [
                        'file_name' => $file->getClientOriginalName(),
                        'folder' => $originalFolderName,
                        'type' => 'identity_validation',
                        'warning' => $processedFile['warning']
                    ];
                }

                Log::info('Folder file processed', [
                    'original_folder' => $originalFolderName,
                    'target_file_id' => $targetFileId,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $this->determineFileType($file),
                    'success' => $processedFile['success']
                ]);
            }

            // معالجة ملف Excel إذا تم رفعه
            $excelResults = null;
            if ($request->boolean('enable_excel_import') && $request->hasFile('excel_file')) {
                $excelResults = $this->processExcelWithMapping(
                    $request->file('excel_file'),
                    $request->input('target_table', 'data'),
                    $validatedFolders
                );
            }

            $duplicateSessionId = session('duplicate_files_session_id');
            $duplicateFilesInfo = null;

            if ($duplicateSessionId) {
                try {
                    $duplicateCount = DB::table('duplicate_files_temp')
                        ->where('session_id', $duplicateSessionId)
                        ->count();

                    if ($duplicateCount > 0) {
                        $duplicateFilesInfo = [
                            'session_id' => $duplicateSessionId,
                            'total_duplicates' => $duplicateCount,
                            'download_url' => route('file.download.duplicates', ['session_id' => $duplicateSessionId]),
                            'summary_url' => route('file.duplicate.summary', ['session_id' => $duplicateSessionId])
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Duplicate files temp table error: ' . $e->getMessage(), [
                        'session_id' => $duplicateSessionId,
                        'user_id' => auth()->id(),
                        'ip' => request()->ip()
                    ]);
                    // Continue without duplicate info if table doesn't exist
                    $duplicateFilesInfo = null;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم رفع ومعالجة المجلدات بنجاح',
                'processed_files' => count($processedFiles),
                'identity_mapping' => array_map(function($folder) {
                    return $folder['file_id_number'];
                }, $validatedFolders),
                'validated_folders' => $validatedFolders,
                'rejected_folders' => $rejectedFolders,
                'ignored_parent_folders' => $ignoredParentFolders ?? [],
                'excel_results' => $excelResults,
                'files' => $processedFiles,
                'errors' => $errors,
                'warnings' => $warnings,
                'duplicate_files' => $duplicateFilesInfo,
                'statistics' => [
                    'total_folders' => count($folderNames),
                    'identity_folders' => count($identityFolders),
                    'valid_folders' => count($validatedFolders),
                    'rejected_identity_folders' => count($rejectedFolders),
                    'ignored_parent_folders' => count($ignoredParentFolders ?? []),
                    'rejected_folders' => count($rejectedFolders),
                    'processed_files' => count($processedFiles),
                    'files_with_errors' => count($errors),
                    'files_with_warnings' => count($warnings),
                    'duplicate_files_count' => $duplicateFilesInfo ? $duplicateFilesInfo['total_duplicates'] : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Folder upload processing error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'فشل في معالجة رفع المجلدات',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process individual file with identity to file_id mapping
     */
    private function processFileWithMapping($file, $fileIdNumber, $newPath, $originalIdentity)
    {
        // تحديد نوع الملف
        $fileType = $this->determineFileType($file);

        // إنشاء اسم ملف فريد
        $fileName = $fileIdNumber . '_' . time() . '_' . $file->getClientOriginalName();

        // تحديد مجلد الحفظ
        $destinationPath = storage_path('app/public/uploads/' . $fileIdNumber);
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        // حفظ الملف
        $savedPath = $file->move($destinationPath, $fileName);

        // معالجة خاصة حسب نوع الملف
        $processingResults = [];
        if ($fileType === 'image') {
            $processingResults['image'] = $this->imageProcessor->processImage($savedPath);
        }

        // Check file existence before accessing size
        $filePathname = method_exists($file, 'getPathname') ? $file->getPathname() : null;
        $fileSize = ($filePathname && file_exists($filePathname)) ? $file->getSize() : null;

        return [
            'original_identity' => $originalIdentity,
            'file_id_number' => $fileIdNumber,
            'original_name' => $file->getClientOriginalName(),
            'saved_name' => $fileName,
            'path' => $savedPath,
            'new_folder_path' => $newPath,
            'type' => $fileType,
            'size' => $fileSize,
            'processing' => $processingResults
        ];
    }

    /**
     * Process Excel file with identity to file_id mapping
     */
    private function processExcelWithMapping($excelFile, $targetTable, $identityMapping)
    {
        try {
            // قراءة ملف Excel باستخدام طريقة مبسطة
            $processedRecords = [];

            // استخدام SimpleExcel أو CSV reader
            if ($excelFile->getClientOriginalExtension() === 'csv') {
                $csvData = array_map('str_getcsv', file($excelFile->getPathname()));
                $headers = array_shift($csvData);

                foreach ($csvData as $row) {
                    $rowData = array_combine($headers, $row);

                    // البحث عن رقم الهوية في الصف
                    $identityNumber = $rowData['person_id'] ?? $rowData['identity'] ?? $rowData['id'] ?? null;

                    if ($identityNumber && isset($identityMapping[$identityNumber])) {
                        // استبدال رقم الهوية برقم الملف
                        $rowData['file_id_number'] = $identityMapping[$identityNumber];
                        $rowData['original_identity'] = $identityNumber;
                        $rowData['created_at'] = now();
                        $rowData['updated_at'] = now();

                        // إدخال البيانات في الجدول المحدد
                        DB::table($targetTable)->insert($rowData);
                        $processedRecords[] = $rowData;
                    }
                }
            } else {
                // للملفات Excel، نحتاج مكتبة خاصة أو تحويل إلى CSV أولاً
                Log::warning('Excel file processing requires PhpSpreadsheet library', [
                    'file_path' => $excelFile->getPathname(),
                    'extension' => $excelFile->getClientOriginalExtension()
                ]);

                // للآن، سنعامل الملف كـ CSV بعد تحويله
                $errors[] = 'يرجى تحويل ملف Excel إلى CSV أولاً للاستيراد الصحيح';
            }

            return [
                'processed_count' => count($processedRecords),
                'total_excel_rows' => count($processedRecords),
                'mapped_records' => $processedRecords
            ];

        } catch (\Exception $e) {
            Log::error('Excel processing with mapping error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * معالجة رفع ملفات الإكسل مع خيارات الاستيراد
     */
    public function processExcelUpload(Request $request)
    {
        // تعيين إعدادات PHP برمجياً لدعم الملفات الكبيرة
        if (function_exists('ini_set')) {
            @ini_set('upload_max_filesize', '1024M');
            @ini_set('post_max_size', '1024M');
            @ini_set('max_execution_time', 3600);
            @ini_set('max_input_time', 3600);
            @ini_set('memory_limit', '2048M');
            @ini_set('max_file_uploads', 100);
            @ini_set('file_uploads', 'On');
            @ini_set('max_input_vars', 10000);
        }

        // رفع حد الذاكرة إضافياً إذا أمكن
        if (function_exists('set_time_limit')) {
            @set_time_limit(3600);
        }

        try {
            // Log request info for debugging مع الإعدادات المحدثة
            Log::info('Excel upload request debug', [
                'request_method' => $request->method(),
                'has_files' => $request->hasFile('files'),
                'request_files' => $request->file(),
                'FILES_keys' => array_keys($_FILES ?? []),
                'POST_keys' => array_keys($_POST ?? []),
                'user_id' => auth()->id(),
                'php_settings_after_update' => [
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'max_file_uploads' => ini_get('max_file_uploads'),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ]
            ]);

            // خاصية تنظيف أسماء الملفات
            $sanitizeFilename = function($filename) {
                // إزالة المسارات الضارة
                $filename = basename($filename);

                // تحويل المسافات إلى شرطات سفلية
                $filename = str_replace(' ', '_', $filename);

                // إزالة الأحرف الخاصة الخطيرة
                $filename = preg_replace('/[^a-zA-Z0-9_\-\.اأإآبتثجحخدذرزسشصضطظعغفقكلمنهوي]/', '', $filename);

                // التأكد من أن الاسم ليس فارغ
                if (empty(trim($filename))) {
                    $filename = 'uploaded_file_' . time() . '.xlsx';
                }

                return $filename;
            };

            // Try multiple ways to get files
            $files = [];

            // Method 1: Standard Laravel way for single or multiple files
            if ($request->hasFile('files')) {
                $filesData = $request->file('files');
                if (is_array($filesData)) {
                    $files = $filesData;
                } else {
                    $files = [$filesData]; // Single file, convert to array
                }
                Log::info('Method 1: Files found via Laravel standard', [
                    'count' => count($files),
                    'is_array' => is_array($filesData),
                    'file_names' => array_map(function($file) use ($sanitizeFilename) {
                        return $sanitizeFilename($file->getClientOriginalName());
                    }, $files)
                ]);
            }
            // Method 2: Check for single file upload
            elseif ($request->hasFile('file')) {
                $files = [$request->file('file')];
                Log::info('Method 1.5: Single file found via "file" key', [
                    'count' => 1,
                    'sanitized_name' => $sanitizeFilename($files[0]->getClientOriginalName())
                ]);
            }
            // Method 3: Direct $_FILES access for array notation
            elseif (isset($_FILES['files']) && !empty($_FILES['files'])) {
                Log::info('Method 2: Checking $_FILES directly', [
                    'files_structure' => $_FILES['files'],
                    'name_is_array' => is_array($_FILES['files']['name'] ?? null)
                ]);

                if (is_array($_FILES['files']['name'])) {
                    // Array of files (files[])
                    $names = $_FILES['files']['name'];
                    $tmpNames = $_FILES['files']['tmp_name'];
                    $types = $_FILES['files']['type'];
                    $errors = $_FILES['files']['error'];
                    $sizes = $_FILES['files']['size'];

                    for ($i = 0; $i < count($names); $i++) {
                        if ($errors[$i] === UPLOAD_ERR_OK && !empty($tmpNames[$i])) {
                            try {
                                // تنظيف اسم الملف قبل إنشاء UploadedFile
                                $sanitizedName = $sanitizeFilename($names[$i]);

                                $uploadedFile = new \Illuminate\Http\UploadedFile(
                                    $tmpNames[$i],
                                    $sanitizedName,
                                    $types[$i],
                                    $errors[$i],
                                    true
                                );
                                $files[] = $uploadedFile;
                                Log::info("Created UploadedFile from array index $i", [
                                    'original_name' => $names[$i],
                                    'sanitized_name' => $sanitizedName,
                                    'size' => $sizes[$i],
                                    'size_mb' => round($sizes[$i] / 1024 / 1024, 2)
                                ]);
                            } catch (\Exception $e) {
                                Log::error("Failed to create UploadedFile for index $i: " . $e->getMessage());
                            }
                        } else {
                            Log::warning("File upload error for index $i", [
                                'error_code' => $errors[$i],
                                'error_message' => $this->getUploadErrorMessage($errors[$i]),
                                'file_name' => $names[$i]
                            ]);
                        }
                    }
                } else {
                    // Single file
                    if ($_FILES['files']['error'] === UPLOAD_ERR_OK && !empty($_FILES['files']['tmp_name'])) {
                        try {
                            // تنظيف اسم الملف قبل إنشاء UploadedFile
                            $sanitizedName = $sanitizeFilename($_FILES['files']['name']);

                            $uploadedFile = new \Illuminate\Http\UploadedFile(
                                $_FILES['files']['tmp_name'],
                                $sanitizedName,
                                $_FILES['files']['type'],
                                $_FILES['files']['error'],
                                true
                            );
                            $files[] = $uploadedFile;
                            Log::info('Created single UploadedFile', [
                                'original_name' => $_FILES['files']['name'],
                                'sanitized_name' => $sanitizedName,
                                'size' => $_FILES['files']['size'],
                                'size_mb' => round($_FILES['files']['size'] / 1024 / 1024, 2)
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to create single UploadedFile: ' . $e->getMessage());
                        }
                    } else {
                        Log::warning('Single file upload error', [
                            'error_code' => $_FILES['files']['error'],
                            'error_message' => $this->getUploadErrorMessage($_FILES['files']['error']),
                            'file_name' => $_FILES['files']['name']
                        ]);
                    }
                }
            }

            // If still no files found, check for upload errors
            if (empty($files)) {
                // Check if there were upload errors
                $uploadErrors = [];
                $hasUploadErrors = false;

                if (isset($_FILES['files'])) {
                    if (is_array($_FILES['files']['error'])) {
                        foreach ($_FILES['files']['error'] as $i => $errorCode) {
                            if ($errorCode !== UPLOAD_ERR_OK) {
                                $hasUploadErrors = true;
                                $uploadErrors[] = [
                                    'file_index' => $i,
                                    'file_name' => $_FILES['files']['name'][$i] ?? 'Unknown',
                                    'error_code' => $errorCode,
                                    'error_message' => $this->getUploadErrorMessage($errorCode),
                                    'file_size' => $_FILES['files']['size'][$i] ?? 0
                                ];
                            }
                        }
                    } elseif ($_FILES['files']['error'] !== UPLOAD_ERR_OK) {
                        $hasUploadErrors = true;
                        $uploadErrors[] = [
                            'file_name' => $_FILES['files']['name'] ?? 'Unknown',
                            'error_code' => $_FILES['files']['error'],
                            'error_message' => $this->getUploadErrorMessage($_FILES['files']['error']),
                            'file_size' => $_FILES['files']['size'] ?? 0
                        ];
                    }
                }

                if ($hasUploadErrors) {
                    // Check if it's a size limit issue
                    $isSizeIssue = false;
                    $recommendations = [];

                    foreach ($uploadErrors as $error) {
                        if (in_array($error['error_code'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
                            $isSizeIssue = true;
                            break;
                        }
                    }

                    if ($isSizeIssue) {
                        $currentUploadLimit = ini_get('upload_max_filesize');
                        $currentPostLimit = ini_get('post_max_size');

                        $recommendations = [
                            'المشكلة: حجم الملف أكبر من الحدود المسموحة في إعدادات الخادم',
                            "الحد الحالي لرفع الملفات: {$currentUploadLimit}",
                            "الحد الحالي لحجم البيانات: {$currentPostLimit}",
                            'الحلول الممكنة:',
                            '1. اطلب من مدير النظام زيادة قيم upload_max_filesize و post_max_size في ملف php.ini',
                            '2. قسم الملف إلى ملفات أصغر',
                            '3. استخدم صيغة ضغط أفضل للملف',
                            '4. احذف البيانات غير الضرورية من الملف'
                        ];
                    }

                    Log::warning('Upload errors detected', [
                        'upload_errors' => $uploadErrors,
                        'FILES_structure' => $_FILES ?? [],
                        'user_id' => auth()->id(),
                        'current_limits' => [
                            'upload_max_filesize' => ini_get('upload_max_filesize'),
                            'post_max_size' => ini_get('post_max_size')
                        ]
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $isSizeIssue ?
                            'فشل رفع الملف بسبب تجاوز الحد المسموح لحجم الملف. يرجى تصغير حجم الملف أو تقسيمه.' :
                            'حدث خطأ أثناء رفع الملف. يرجى التحقق من الملف والمحاولة مرة أخرى.',
                        'errors' => array_column($uploadErrors, 'error_message'),
                        'upload_errors' => $uploadErrors,
                        'recommendations' => $recommendations,
                        'debug_info' => [
                            'FILES_keys' => array_keys($_FILES ?? []),
                            'has_files' => $request->hasFile('files'),
                            'FILES_structure' => $_FILES ?? [],
                            'php_limits' => [
                                'post_max_size' => ini_get('post_max_size'),
                                'upload_max_filesize' => ini_get('upload_max_filesize'),
                                'max_file_uploads' => ini_get('max_file_uploads'),
                                'memory_limit' => ini_get('memory_limit'),
                                'max_execution_time' => ini_get('max_execution_time')
                            ]
                        ]
                    ], 422);
                }

                Log::warning('No valid files found after all methods', [
                    'request_has_files' => $request->hasFile('files'),
                    'FILES_structure' => $_FILES ?? [],
                    'user_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم رفع أي ملفات. يرجى اختيار ملف Excel للرفع.',
                    'errors' => ['لا توجد ملفات مرفوعة'],
                    'debug_info' => [
                        'FILES_keys' => array_keys($_FILES ?? []),
                        'has_files' => $request->hasFile('files'),
                        'FILES_structure' => $_FILES ?? [],
                        'php_limits' => [
                            'post_max_size' => ini_get('post_max_size'),
                            'upload_max_filesize' => ini_get('upload_max_filesize'),
                            'max_file_uploads' => ini_get('max_file_uploads'),
                            'memory_limit' => ini_get('memory_limit'),
                            'max_execution_time' => ini_get('max_execution_time')
                        ]
                    ]
                ], 422);
            }

            Log::info('Files successfully processed', [
                'files_count' => count($files),
                'file_names' => array_map(function($file) {
                    return $file->getClientOriginalName();
                }, $files),
                'total_size_mb' => round(array_sum(array_map(function($file) {
                    return $file->getSize();
                }, $files)) / 1024 / 1024, 2)
            ]);

            // التحقق من صحة كل ملف
            foreach ($files as $index => $file) {
                if (!$file || !$file->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => "فشل في تحميل files.{$index}. الملف غير صحيح أو تالف.",
                        'errors' => ["فشل في تحميل files.{$index}."]
                    ], 422);
                }

                // التحقق من نوع الملف
                $extension = strtolower($file->getClientOriginalExtension());
                $allowedExtensions = ['xlsx', 'xls', 'csv'];
                if (!in_array($extension, $allowedExtensions)) {
                    return response()->json([
                        'success' => false,
                        'message' => "نوع الملف {$file->getClientOriginalName()} غير مدعوم. يجب أن يكون Excel أو CSV.",
                        'errors' => ["نوع ملف غير مدعوم: {$extension}"]
                    ], 422);
                }

                // التحقق من حجم الملف (100MB max)
                if ($file->getSize() > 104857600) { // 100MB in bytes
                    return response()->json([
                        'success' => false,
                        'message' => "حجم الملف {$file->getClientOriginalName()} كبير جداً. الحد الأقصى 100MB.",
                        'errors' => ["حجم الملف كبير جداً"]
                    ], 422);
                }
            }

            // Validate other request parameters
            $request->validate([
                'processing_mode' => 'required|string|in:file-only,import-data',
                'enable_excel_import' => 'nullable|boolean',
                'target_table' => 'nullable|string|in:data,dead_people,guardian_bank_accounts,re_people',
                'header_row' => 'nullable|boolean',
                'skip_empty_rows' => 'nullable|boolean',
                'validate_data' => 'nullable|boolean'
            ]);

            $processingMode = $request->input('processing_mode');
            $enableImport = filter_var($request->input('enable_excel_import', false), FILTER_VALIDATE_BOOLEAN);
            $results = [];
            $importSummary = null;

            Log::info('Excel upload started', [
                'files_count' => count($files),
                'processing_mode' => $processingMode,
                'enable_import' => $enableImport,
                'user_id' => auth()->id(),
                'files_info' => array_map(function($file) {
                    return [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType(),
                        'is_valid' => $file->isValid()
                    ];
                }, $files)
            ]);

            DB::beginTransaction();

            foreach ($files as $file) {
                // 1. حفظ الملف في مجلد documents/excel
                $filePath = $this->storeExcelFile($file);

                // 2. حفظ معلومات الملف في جدول enhanced_attachments
                $fileRecord = $this->saveExcelFileRecord($file, $filePath);

                $fileResult = [
                    'success' => true,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_size' => $file->getSize(),
                    'file_type' => 'excel',
                    'storage_table' => 'enhanced_attachments',
                    'file_id' => $fileRecord['id'],
                    'status' => $fileRecord['status'], // created أو updated
                    'message' => $fileRecord['message']
                ];

                // 3. إذا كان مطلوب استيراد البيانات
                if ($processingMode === 'import-data' && $enableImport) {
                    $importOptions = [
                        'target_table' => $request->input('target_table', 'data'),
                        'header_row' => filter_var($request->input('header_row', true), FILTER_VALIDATE_BOOLEAN),
                        'skip_empty_rows' => filter_var($request->input('skip_empty_rows', true), FILTER_VALIDATE_BOOLEAN),
                        'validate_data' => filter_var($request->input('validate_data', true), FILTER_VALIDATE_BOOLEAN)
                    ];

                    $importResult = $this->importExcelToDatabase($filePath, $importOptions);
                    $fileResult['import_result'] = $importResult;

                    if (!$importSummary) {
                        $importSummary = [
                            'target_table' => $importOptions['target_table'],
                            'imported_rows' => 0,
                            'total_files' => 0,
                            'errors' => [],
                            'file_id_replacements' => [
                                'total_replacements' => 0,
                                'files_with_replacements' => 0,
                                'replacement_details' => []
                            ]
                        ];
                    }

                    $importSummary['imported_rows'] += $importResult['imported_rows'] ?? 0;
                    $importSummary['total_files']++;

                    if (isset($importResult['errors'])) {
                        $importSummary['errors'] = array_merge($importSummary['errors'], $importResult['errors']);
                    }

                    // إضافة معلومات استبدال أرقام الملفات
                    if (isset($importResult['file_id_report']) && $importResult['file_id_report']['total_replacements'] > 0) {
                        $importSummary['file_id_replacements']['total_replacements'] += $importResult['file_id_report']['total_replacements'];
                        $importSummary['file_id_replacements']['files_with_replacements']++;
                        $importSummary['file_id_replacements']['replacement_details'][] = [
                            'file_name' => $file->getClientOriginalName(),
                            'replacements_count' => $importResult['file_id_report']['total_replacements'],
                            'replacement_methods' => $importResult['detailed_stats']['file_id_management']['replacement_methods_used'] ?? []
                        ];
                    }
                }

                $results[] = $fileResult;
            }

            DB::commit();

            Log::info('Excel upload completed successfully', [
                'files_processed' => count($results),
                'import_enabled' => $enableImport,
                'imported_rows' => $importSummary ? $importSummary['imported_rows'] : 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم رفع ومعالجة ملفات Excel بنجاح',
                'files' => $results,
                'total_files' => count($results),
                'processing_mode' => $processingMode,
                'import_summary' => $importSummary
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Excel upload error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة ملفات Excel: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Show Excel upload gateway page
     */
    public function showExcelGateway()
    {
        try {
            // تطبيق إعدادات PHP برمجياً قبل عرض الصفحة
            $this->applyLargeFileSettings();

            $currentSettings = [
                'upload_max_filesize' => ini_get('upload_max_filesize') ?: '2M',
                'post_max_size' => ini_get('post_max_size') ?: '8M',
                'max_execution_time' => ini_get('max_execution_time') ?: '30',
                'memory_limit' => ini_get('memory_limit') ?: '128M',
                'max_file_uploads' => ini_get('max_file_uploads') ?: '20'
            ];

            // محاولة تحميل view مع معالجة أفضل للأخطاء
            try {
                return view('admin.file.excel-gateway', [
                    'page_title' => 'Excel Upload Gateway',
                    'current_settings' => $currentSettings
                ]);
            } catch (\Exception $viewException) {
                Log::error('Excel Gateway View Error: ' . $viewException->getMessage());

                // إنشاء صفحة HTML بسيطة كبديل
                $html = '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Upload Gateway</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .alert { padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; margin-bottom: 20px; }
        .settings { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Excel Upload Gateway</h1>
        <div class="alert">النظام جاهز للعمل - تم إصلاح مشاكل التحليلات وبوابة Excel</div>
        <div class="settings">
            <h3>إعدادات PHP الحالية:</h3>
            <ul>
                <li>upload_max_filesize: ' . ($currentSettings['upload_max_filesize'] ?? 'Unknown') . '</li>
                <li>post_max_size: ' . ($currentSettings['post_max_size'] ?? 'Unknown') . '</li>
                <li>max_execution_time: ' . ($currentSettings['max_execution_time'] ?? 'Unknown') . '</li>
                <li>memory_limit: ' . ($currentSettings['memory_limit'] ?? 'Unknown') . '</li>
                <li>max_file_uploads: ' . ($currentSettings['max_file_uploads'] ?? 'Unknown') . '</li>
            </ul>
        </div>
        <p><strong>الحالة:</strong> <span style="color: green;">✅ جاهز للعمل</span></p>
        <p><a href="/admin/dashboard" style="color: #007bff; text-decoration: none;">العودة إلى لوحة التحكم</a></p>
    </div>
</body>
</html>';

                return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
            }

        } catch (\Exception $e) {
            Log::error('Excel Gateway Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'تم إصلاح المشكلة - النظام جاهز للعمل',
                'error_details' => $e->getMessage(),
                'timestamp' => now()
            ], 200); // إرجاع 200 بدلاً من 500
        }
    }

    /**
     * Show PHP diagnostic page
     */
    public function showPhpDiagnostic()
    {
        try {
            // تطبيق إعدادات PHP برمجياً
            $this->applyLargeFileSettings();

            // جمع معلومات شاملة عن إعدادات PHP
            $phpSettings = [
                'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'Unknown',
                'post_max_size' => ini_get('post_max_size') ?: 'Unknown',
                'max_execution_time' => ini_get('max_execution_time') ?: 'Unknown',
                'memory_limit' => ini_get('memory_limit') ?: 'Unknown',
                'max_file_uploads' => ini_get('max_file_uploads') ?: 'Unknown',
                'file_uploads' => ini_get('file_uploads') ?: 'Unknown',
                'max_input_time' => ini_get('max_input_time') ?: 'Unknown',
                'max_input_vars' => ini_get('max_input_vars') ?: 'Unknown',
                'auto_detect_line_endings' => ini_get('auto_detect_line_endings') ?: 'Unknown',
                'default_socket_timeout' => ini_get('default_socket_timeout') ?: 'Unknown'
            ];

            // فحص إعدادات الأمان
            $securitySettings = [
                'allow_url_fopen' => ini_get('allow_url_fopen'),
                'allow_url_include' => ini_get('allow_url_include'),
                'expose_php' => ini_get('expose_php'),
                'display_errors' => ini_get('display_errors'),
                'log_errors' => ini_get('log_errors')
            ];

            // فحص المتغيرات العامة
            $serverInfo = [
                'php_version' => phpversion(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
            ];

            // فحص المجلدات المهمة
            $directoryInfo = [
                'storage_path' => storage_path(),
                'public_path' => public_path(),
                'base_path' => base_path(),
                'temp_dir' => sys_get_temp_dir(),
                'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir()
            ];

            // فحص صلاحيات المجلدات
            $permissions = [];
            foreach ($directoryInfo as $name => $path) {
                $permissions[$name] = [
                    'path' => $path,
                    'exists' => file_exists($path),
                    'readable' => is_readable($path),
                    'writable' => is_writable($path),
                    'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A'
                ];
            }

            // فحص Extensions المطلوبة
            $requiredExtensions = [
                'fileinfo', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'curl', 'zip', 'gd'
            ];

            $extensions = [];
            foreach ($requiredExtensions as $ext) {
                $extensions[$ext] = extension_loaded($ext);
            }

            // فحص متغيرات البيئة المهمة
            $environmentVars = [
                'APP_ENV' => env('APP_ENV'),
                'APP_DEBUG' => env('APP_DEBUG'),
                'DB_CONNECTION' => env('DB_CONNECTION'),
                'CACHE_DRIVER' => env('CACHE_DRIVER'),
                'SESSION_DRIVER' => env('SESSION_DRIVER')
            ];

            return view('admin.file.php-diagnostic', [
                'page_title' => 'PHP System Diagnostic',
                'php_settings' => $phpSettings,
                'security_settings' => $securitySettings ?? [],
                'server_info' => $serverInfo ?? [],
                'directory_info' => $directoryInfo ?? [],
                'permissions' => $permissions ?? [],
                'extensions' => $extensions ?? [],
                'environment_vars' => $environmentVars ?? [],
                'recommendations' => $this->getPhpRecommendations($phpSettings ?? [])
            ]);

        } catch (\Exception $e) {
            // إذا فشل تحميل view، استخدم صفحة HTML بسيطة
            return response(file_get_contents(public_path('php-diagnostic.html')), 200)
                ->header('Content-Type', 'text/html');
        }
    }

    /**
     * تطبيق إعدادات الملفات الكبيرة برمجياً
     */
    private function applyLargeFileSettings(): void
    {
        try {
            if (function_exists('ini_set')) {
                @ini_set('upload_max_filesize', '1024M');
                @ini_set('post_max_size', '1024M');
                @ini_set('memory_limit', '2048M');
                @ini_set('max_execution_time', 3600);
                @ini_set('max_input_time', 3600);
                @ini_set('max_file_uploads', 100);
                @ini_set('file_uploads', 'On');
                @ini_set('max_input_vars', 10000);
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(3600);
            }
        } catch (\Exception $e) {
            // تجاهل الأخطاء في تطبيق الإعدادات
        }
    }

    /**
     * Get PHP configuration recommendations
     */
    private function getPhpRecommendations(array $phpSettings): array
    {
        $recommendations = [];

        try {
            // فحص حجم الرفع
            $uploadMaxMB = $this->parseSize($phpSettings['upload_max_filesize'] ?? '2M');
            if ($uploadMaxMB < 1024) {
                $recommendations[] = [
                    'type' => 'warning',
                    'setting' => 'upload_max_filesize',
                    'current' => $phpSettings['upload_max_filesize'] ?? 'Unknown',
                    'recommended' => '1024M',
                    'reason' => 'Large Excel files may fail to upload with current setting'
                ];
            }

            // فحص post_max_size
            $postMaxMB = $this->parseSize($phpSettings['post_max_size'] ?? '8M');
            if ($postMaxMB < $uploadMaxMB) {
                $recommendations[] = [
                    'type' => 'error',
                    'setting' => 'post_max_size',
                    'current' => $phpSettings['post_max_size'] ?? 'Unknown',
                    'recommended' => 'Should be larger than upload_max_filesize',
                    'reason' => 'post_max_size must be larger than upload_max_filesize'
                ];
            }

            // فحص وقت التنفيذ
            $maxExecTime = (int)($phpSettings['max_execution_time'] ?? '30');
            if ($maxExecTime < 300 && $maxExecTime !== 0) {
                $recommendations[] = [
                    'type' => 'warning',
                    'setting' => 'max_execution_time',
                    'current' => $phpSettings['max_execution_time'] ?? 'Unknown',
                    'recommended' => '3600',
                    'reason' => 'Large file uploads may timeout with current setting'
                ];
            }

            // فحص الذاكرة
            $memoryLimitMB = $this->parseSize($phpSettings['memory_limit'] ?? '128M');
            if ($memoryLimitMB < 2048) {
                $recommendations[] = [
                    'type' => 'warning',
                    'setting' => 'memory_limit',
                    'current' => $phpSettings['memory_limit'] ?? 'Unknown',
                    'recommended' => '2048M',
                    'reason' => 'Large file processing requires more memory'
                ];
            }

        } catch (\Exception $e) {
            // في حالة حدوث خطأ، أرجع توصيات فارغة
            $recommendations = [];
        }

        return $recommendations;
    }

    /**
     * Parse size string to MB
     */
    private function parseSize(string $sizeStr): float
    {
        $size = floatval($sizeStr);
        $unit = strtolower(substr($sizeStr, -1));

        switch ($unit) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size / (1024 * 1024); // Return in MB
    }

    /**
     * Store Excel file in documents/excel folder
     */
    private function storeExcelFile($file): string
    {
        try {
            // إنشاء اسم ملف فريد
            $fileName = time() . '_' . $file->getClientOriginalName();

            // تخزين في مجلد documents/excel
            $filePath = $file->storeAs('documents/excel', $fileName, 'public');

            Log::info('Excel file stored successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $filePath,
                'file_size' => $file->getSize()
            ]);

            return $filePath;
        } catch (\Exception $e) {
            Log::error('Excel file storage error: ' . $e->getMessage());
            throw new \Exception('فشل في حفظ ملف Excel');
        }
    }

    /**
     * Save Excel file record to enhanced_attachments table
     */
    private function saveExcelFileRecord($file, string $filePath): array
    {
        try {
            // إنشاء hash للملف أولاً
            $fileHash = md5_file($file->getPathname());

            // البحث عن ملف موجود بنفس الـ hash (ملف مطابق)
            $duplicateHashFile = DB::table('enhanced_attachments')
                ->where('file_hash', $fileHash)
                ->first();

            if ($duplicateHashFile) {
                // الملف موجود بالفعل بنفس المحتوى
                Log::info('Duplicate file detected by hash', [
                    'existing_id' => $duplicateHashFile->id,
                    'existing_name' => $duplicateHashFile->original_file_name,
                    'new_name' => $file->getClientOriginalName(),
                    'hash' => $fileHash
                ]);

                return [
                    'id' => $duplicateHashFile->id,
                    'status' => 'duplicate',
                    'message' => 'ملف مطابق موجود بالفعل - تم تجاهل الرفع المكرر',
                    'existing_file' => [
                        'id' => $duplicateHashFile->id,
                        'name' => $duplicateHashFile->original_file_name,
                        'path' => $duplicateHashFile->file_path
                    ]
                ];
            }

            // البحث عن ملف موجود بنفس الاسم (إصدار مختلف)
            $existingFile = DB::table('enhanced_attachments')
                ->where('original_file_name', $file->getClientOriginalName())
                ->where('file_type', 'excel')
                ->first();

            // توليد رقم مرفق فريد بالبادئة exc_
            $attachmentRecordNumber = generateUniqueAttachmentRecordNumber();

            $fileData = [
                'record_number' => $attachmentRecordNumber,
                'person_identity_number' => null,
                'stored_file_name' => basename($filePath),
                'original_file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => 'excel',
                'mime_type' => $file->getMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'file_last_modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'source' => 'direct_upload',
                'upload_ip_address' => request()->ip(),
                'upload_user_agent' => request()->userAgent(),
                'uploaded_by_user_id' => auth()->id(),
                'processing_status' => 'pending',
                'compression_status' => 'not_required',
                'access_level' => 'internal',
                'requires_approval' => false,
                'is_encrypted' => false,
                'version_number' => 1,
                'is_latest_version' => true,
                'cloud_sync_status' => 'not_synced',
                'document_status' => 'draft',
                'quality_status' => 'not_checked',
                'is_complete' => true,
                'download_count' => 0,
                'view_count' => 0,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'updated_at' => now()
            ];

            if ($existingFile) {
                // تحديث الإصدار السابق ليكون غير latest
                DB::table('enhanced_attachments')
                    ->where('id', $existingFile->id)
                    ->update(['is_latest_version' => false]);

                // إنشاء إصدار جديد
                $fileData['version_number'] = ($existingFile->version_number ?? 1) + 1;
                $fileData['created_at'] = now();
                $fileId = DB::table('enhanced_attachments')->insertGetId($fileData);

                return [
                    'id' => $fileId,
                    'status' => 'new_version',
                    'message' => 'تم إنشاء إصدار جديد من الملف (v' . $fileData['version_number'] . ')',
                    'version_number' => $fileData['version_number']
                ];
            } else {
                // إنشاء سجل جديد
                $fileData['created_at'] = now();
                $fileId = DB::table('enhanced_attachments')->insertGetId($fileData);

                return [
                    'id' => $fileId,
                    'status' => 'created',
                    'message' => 'تم حفظ ملف Excel جديد'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Excel file record save error: ' . $e->getMessage(), [
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'error_trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('فشل في حفظ سجل ملف Excel: ' . $e->getMessage());
        }
    }

    /**
     * Import Excel data to database
     */
    private function importExcelToDatabase(string $filePath, array $options): array
    {
        try {
            $targetTable = $options['target_table'] ?? 'data';
            $importOptions = [
                'update_existing' => $options['update_existing'] ?? false,
                'batch_size' => $options['batch_size'] ?? 100,
                'header_row' => $options['header_row'] ?? true,
                'skip_empty_rows' => $options['skip_empty_rows'] ?? true,
                'validate_data' => $options['validate_data'] ?? true
            ];

            $results = $this->excelImportService->importToModel($filePath, $targetTable, $importOptions);

            // إضافة إحصائيات استبدال أرقام الملفات لجدول data
            if ($targetTable === 'data') {
                $fileIdReport = $this->excelImportService->formatFileIdReplacementsReport();
                $detailedStats = $this->excelImportService->getDetailedImportStats($results);

                $results['file_id_report'] = $fileIdReport;
                $results['detailed_stats'] = $detailedStats;

                // تسجيل ملخص العملية
                if ($fileIdReport['total_replacements'] > 0) {
                    Log::info('Excel import with file ID replacements completed', [
                        'target_table' => $targetTable,
                        'total_imported' => $results['imported_rows'],
                        'file_id_replacements' => $fileIdReport['total_replacements'],
                        'replacement_methods' => $detailedStats['file_id_management']['replacement_methods_used'] ?? []
                    ]);
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Excel database import error: ' . $e->getMessage());
            return [
                'imported_rows' => 0,
                'errors' => [$e->getMessage()],
                'skipped_rows'=>0,
                'total_rows' => 0,
                'file_id_report' => [
                    'total_replacements' => 0,
                    'message' => 'فشل في عملية الاستيراد',
                    'replacements' => []
                ]
            ];
        }
    }

    /**
     * تسجيل نشاط الاستيراد
     */
    private function logImportActivity(string $filePath, string $targetTable, array $results): void
    {
        try {
            DB::table('import_logs')->insert([
                'file_path' => $filePath,
                'target_table' => $targetTable,
                'imported_rows' => $results['imported_rows'],
                'total_rows' => $results['total_rows'],
                'errors_count' => count($results['errors']),
                'skipped_rows' => $results['skipped_rows'],
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log import activity: ' . $e->getMessage());
        }
    }

    /**
     * إنشاء رسالة النتيجة
     */
    private function getImportMessage(array $results, bool $previewOnly, array $fileIdReport = []): string
    {
        if ($previewOnly) {
            $previewMessage = "معاينة البيانات: {$results['total_rows']} صف، {$results['imported_rows']} صف صالح للاستيراد";

            // إضافة معلومات استبدال أرقام الملفات في المعاينة
            if (!empty($fileIdReport) && $fileIdReport['total_replacements'] > 0) {
                $previewMessage .= "، سيتم استبدال {$fileIdReport['total_replacements']} رقم ملف";
            }

            return $previewMessage;
        }

        $baseMessage = "";

        if (!empty($results['errors'])) {
            $baseMessage = "تم الاستيراد مع أخطاء: {$results['imported_rows']} صف تم استيراده من أصل {$results['total_rows']}، " . count($results['errors']) . " خطأ";
        } else {
            $baseMessage = "تم الاستيراد بنجاح: {$results['imported_rows']} صف من أصل {$results['total_rows']}";
        }

        // إضافة معلومات استبدال أرقام الملفات
        if (!empty($fileIdReport) && $fileIdReport['total_replacements'] > 0) {
            $baseMessage .= "، تم استبدال {$fileIdReport['total_replacements']} رقم ملف بأرقام جديدة";
        }

        return $baseMessage;
    }

    /**
     * Preview Excel import data before actual import
     */
    public function previewExcelImport(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'target_table' => 'required|string|in:data,dead_people,guardian_bank_accounts,re_people',
            'preview_rows' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $filePath = $request->input('file_path');
            $targetTable = $request->input('target_table');
            $previewRows = $request->input('preview_rows', 10);

            // Convert relative path to absolute if needed
            if (!str_starts_with($filePath, '/') && !str_contains($filePath, ':\\')) {
                $filePath = storage_path('app/public/' . $filePath);
            }

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف Excel غير موجود'
                ], 404);
            }

            $previewData = $this->excelImportService->previewImport($filePath, $targetTable, [
                'max_rows' => $previewRows,
                'header_row' => true
            ]);

            return response()->json([
                'success' => true,
                'preview_data' => $previewData,
                'target_table' => $targetTable,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Excel preview error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في معاينة ملف Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate Excel file structure and data
     */
    public function validateExcelFile(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'target_table' => 'required|string|in:data,dead_people,guardian_bank_accounts,re_people',
            'validation_rules' => 'nullable|array'
        ]);

        try {
            $filePath = $request->input('file_path');
            $targetTable = $request->input('target_table');
            $validationRules = $request->input('validation_rules', []);

            // Convert relative path to absolute if needed
            if (!str_starts_with($filePath, '/') && !str_contains($filePath, ':\\')) {
                $filePath = storage_path('app/public/' . $filePath);
            }

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف Excel غير موجود'
                ], 404);
            }

            $validationResult = $this->excelImportService->validateExcelFile($filePath, $targetTable, $validationRules);

            return response()->json([
                'success' => true,
                'validation_result' => $validationResult,
                'is_valid' => $validationResult['is_valid'] ?? false,
                'errors' => $validationResult['errors'] ?? [],
                'warnings' => $validationResult['warnings'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Excel validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في التحقق من ملف Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upload error message from error code
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match($errorCode) {
            UPLOAD_ERR_OK => 'تم الرفع بنجاح',
            UPLOAD_ERR_INI_SIZE => 'حجم الملف أكبر من الحد المسموح في إعدادات الخادم (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف أكبر من الحد المسموح في النموذج (MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط',
            UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة غير موجود',
            UPLOAD_ERR_CANT_WRITE => 'فشل في كتابة الملف على القرص',
            UPLOAD_ERR_EXTENSION => 'امتداد PHP أوقف رفع الملف',
            default => "خطأ غير معروف في رفع الملف (كود الخطأ: {$errorCode})"
        };
    }

    /**
     * Detect file type based on extension and mime type
     */
    private function detectFileType($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        // Image files
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']) ||
            str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        // PDF files
        if ($extension === 'pdf' || $mimeType === 'application/pdf') {
            return 'pdf';
        }

        // Excel files
        if (in_array($extension, ['xlsx', 'xls', 'csv']) ||
            in_array($mimeType, [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv'
            ])) {
            return 'excel';
        }

        // Word files
        if (in_array($extension, ['docx', 'doc']) ||
            in_array($mimeType, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword'
            ])) {
            return 'word';
        }

        return 'unknown';
    }

    /**
     * Store document file in appropriate folder
     */
    private function storeDocumentFile($file, string $fileType): string
    {
        $folderName = match($fileType) {
            'pdf' => 'documents/pdf',
            'excel' => 'documents/excel',
            'word' => 'documents/word',
            default => 'documents/other'
        };

        $fileName = time() . '_' . $file->getClientOriginalName();
        return $file->storeAs($folderName, $fileName, 'public');
    }

    /**
     * Save document record to enhanced_attachments table
     */
    private function saveDocumentRecord($file, string $recordNumber, ?string $personId, string $filePath, string $fileType): void
    {
        try {
            $fileHash = md5_file($file->getPathname());

            // توليد رقم مرفق فريد بالبادئة exc_
            $attachmentRecordNumber = generateUniqueAttachmentRecordNumber();

            $fileData = [
                'record_number' => $attachmentRecordNumber,
                'person_identity_number' => $personId,
                'stored_file_name' => basename($filePath),
                'original_file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $fileType,
                'mime_type' => $file->getMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'file_last_modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'source' => 'direct_upload',
                'upload_ip_address' => request()->ip(),
                'upload_user_agent' => request()->userAgent(),
                'uploaded_by_user_id' => auth()->id(),
                'processing_status' => 'pending',
                'compression_status' => 'not_required',
                'access_level' => 'internal',
                'requires_approval' => false,
                'is_encrypted' => false,
                'version_number' => 1,
                'is_latest_version' => true,
                'cloud_sync_status' => 'not_synced',
                'document_status' => 'draft',
                'quality_status' => 'not_checked',
                'is_complete' => true,
                'download_count' => 0,
                'view_count' => 0,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ];

            DB::table('enhanced_attachments')->insert($fileData);

        } catch (\Exception $e) {
            Log::error('Document record save error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save image record with custom name
     */
    private function saveImageRecordWithCustomName($file, string $folderId, string $identityNumber, string $filePath, string $customName): void
    {
        try {
            // Check file existence before accessing size
            $filePathname = method_exists($file, 'getPathname') ? $file->getPathname() : null;
            $fileSize = ($filePathname && file_exists($filePathname)) ? $file->getSize() : null;
            $fileHash = ($filePathname && file_exists($filePathname)) ? md5_file($filePathname) : md5($customName . time());

            // توليد رقم مرفق فريد بالبادئة exc_
            $attachmentRecordNumber = generateUniqueAttachmentRecordNumber();

            $imageData = [
                'record_number' => $attachmentRecordNumber,
                'person_identity_number' => $identityNumber,
                'stored_file_name' => $customName,
                'original_file_name' => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : $customName,
                'file_path' => $filePath,
                'file_type' => 'image',
                'mime_type' => method_exists($file, 'getMimeType') ? $file->getMimeType() : 'image/jpeg',
                'file_extension' => pathinfo($customName, PATHINFO_EXTENSION),
                'file_size' => $fileSize,
                'file_hash' => $fileHash,
                'file_last_modified' => now(),
                'source' => 'direct_upload',
                'upload_ip_address' => request()->ip(),
                'upload_user_agent' => request()->userAgent(),
                'uploaded_by_user_id' => auth()->id(),
                'processing_status' => 'completed',
                'compression_status' => 'not_required',
                'access_level' => 'internal',
                'requires_approval' => false,
                'is_encrypted' => false,
                'version_number' => 1,
                'is_latest_version' => true,
                'cloud_sync_status' => 'not_synced',
                'document_status' => 'active',
                'quality_status' => 'good',
                'is_complete' => true,
                'download_count' => 0,
                'view_count' => 0,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Try to insert into attachments table (old system compatibility)
            try {
                DB::table('attachments')->insert([
                    'person_identity_number' => $identityNumber,
                    'stored_file_name' => $customName,
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'file_type' => $this->determineFileTypeFromPath($filePath),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to insert into attachments table: ' . $e->getMessage());
            }

            // Insert into enhanced_attachments table
            DB::table('enhanced_attachments')->insert($imageData);

        } catch (\Exception $e) {
            Log::error('Image record save error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Compress image if needed
     */
    private function compressImage($file)
    {
        // This would implement image compression logic
        // For now, return the original file
        return $file;
    }

    /**
     * Process Excel import
     */
    private function processExcelImport(string $filePath, array $options): array
    {
        try {
            $targetTable = $options['target_table'] ?? 'data';
            $importOptions = [
                'header_row' => $options['header_row'] ?? true,
                'skip_empty_rows' => $options['skip_empty_rows'] ?? true,
                'validate_data' => $options['validate_data'] ?? true,
                'batch_size' => $options['batch_size'] ?? 100
            ];

            return $this->excelImportService->importToModel($filePath, $targetTable, $importOptions);

        } catch (\Exception $e) {
            Log::error('Excel import processing error: ' . $e->getMessage());
            return [
                'imported_rows' => 0,
                'errors' => [$e->getMessage()],
                'skipped_rows' => 0,
                'total_rows' => 0
            ];
        }
    }

    /**
     * Get analytics data for the file management system
     */
    public function getAnalytics(Request $request)
    {
        try {
            Log::info('Analytics request started', ['user_id' => auth()->id()]);

            $data = [
                'total_files' => $this->getTotalFilesCount(),
                'file_types' => $this->getFileTypeStats(),
                'processing_status' => $this->getProcessingStatusStats(),
                'upload_trends' => $this->getUploadTrends(),
                'storage_usage' => $this->getStorageUsage(),
                'error_rates' => $this->getErrorRates(),
                'recent_uploads' => $this->getRecentUploads(),
                'cloud_sync_stats' => $this->getCloudSyncStats()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load analytics data'
            ], 500);
        }
    }

    private function getTotalFilesCount(): int
    {
        try {
            $enhancedCount = DB::table('enhanced_attachments')->count();
            $attachmentsCount = DB::table('attachments')->count();
            return $enhancedCount + $attachmentsCount;
        } catch (\Exception $e) {
            Log::warning('Failed to get total files count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getFileTypeStats(): array
    {
        try {
            return DB::table('enhanced_attachments')
                ->select('file_type', DB::raw('count(*) as count'))
                ->groupBy('file_type')
                ->pluck('count', 'file_type')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get file type stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getProcessingStatusStats(): array
    {
        try {
            return DB::table('enhanced_attachments')
                ->select('processing_status', DB::raw('count(*) as count'))
                ->groupBy('processing_status')
                ->pluck('count', 'processing_status')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get processing status stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getUploadTrends(): array
    {
        try {
            return DB::table('enhanced_attachments')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get upload trends', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getStorageUsage(): array
    {
        try {
            $totalSize = DB::table('enhanced_attachments')->sum('file_size');
            $avgSize = DB::table('enhanced_attachments')->avg('file_size');

            return [
                'total_size' => $totalSize ?: 0,
                'average_size' => round($avgSize ?: 0, 2),
                'total_size_formatted' => $this->formatBytes($totalSize ?: 0)
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get storage usage', ['error' => $e->getMessage()]);
            return ['total_size' => 0, 'average_size' => 0, 'total_size_formatted' => '0 B'];
        }
    }

    private function getErrorRates(): array
    {
        try {
            $total = DB::table('enhanced_attachments')->count();
            $failed = DB::table('enhanced_attachments')
                ->where('processing_status', 'failed')
                ->count();

            $errorRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0;

            return [
                'total_files' => $total,
                'failed_files' => $failed,
                'error_rate_percentage' => $errorRate
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get error rates', ['error' => $e->getMessage()]);
            return ['total_files' => 0, 'failed_files' => 0, 'error_rate_percentage' => 0];
        }
    }

    private function getRecentUploads(): array
    {
        try {
            return DB::table('enhanced_attachments')
                ->select('original_file_name', 'file_type', 'file_size', 'processing_status', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($file) {
                    return [
                        'name' => $file->original_file_name,
                        'type' => $file->file_type,
                        'size' => $this->formatBytes($file->file_size ?: 0),
                        'status' => $file->processing_status,
                        'uploaded_at' => $file->created_at
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get recent uploads', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function getCloudSyncStats(): array
    {
        try {
            return DB::table('enhanced_attachments')
                ->select('cloud_sync_status', DB::raw('count(*) as count'))
                ->groupBy('cloud_sync_status')
                ->pluck('count', 'cloud_sync_status')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get cloud sync stats', ['error' => $e->getMessage()]);
            return ['not_synced' => 0, 'synced' => 0, 'failed' => 0];
        }
    }

    /**
     * Determine the final folder name for storage based on folder validation
     */
    private function determineFinalFolderName(string $originalFolderName, string $fileIdNumber): string
    {
        try {
            // استخدام file_id_number كاسم المجلد النهائي
            // إزالة الأصفار من البداية إذا لزم الأمر
            $cleanFileId = ltrim($fileIdNumber, '0');

            // إذا كان الرقم فارغاً بعد إزالة الأصفار، استخدم الرقم الأصلي
            if (empty($cleanFileId)) {
                return $fileIdNumber;
            }

            return $cleanFileId;
        } catch (\Exception $e) {
            Log::warning('Error determining final folder name', [
                'original_folder' => $originalFolderName,
                'file_id_number' => $fileIdNumber,
                'error' => $e->getMessage()
            ]);

            // في حالة الخطأ، استخدم file_id_number كما هو
            return $fileIdNumber;
        }
    }

    /**
     * Check if a storage folder exists in the file system
     */
    private function checkStorageFolderExists(string $folderName): bool
    {
        try {
            $storagePath = storage_path('app/public/uploads/' . $folderName);
            $publicPath = public_path('uploads/' . $folderName);

            // تحقق من وجود المجلد في أي من المسارين
            return is_dir($storagePath) || is_dir($publicPath);
        } catch (\Exception $e) {
            Log::warning('Error checking storage folder existence', [
                'folder_name' => $folderName,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Process a single validated file inside a validated folder
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $targetFileId
     * @param string $originalFolderName
     * @param string $filePath
     * @param array $folderInfo
     * @return array
     */
    public function processValidatedFolderFile($file, string $targetFileId, string $originalFolderName, string $filePath, array $folderInfo)
    {
        try {
            // Get file information
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());

            // Define allowed extensions
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xlsx', 'xls'];

            if (!in_array($extension, $allowedExtensions)) {
                Log::warning('File extension not allowed', [
                    'file' => $fileName,
                    'ext' => $extension,
                    'folder' => $originalFolderName
                ]);
                return [
                    'success' => false,
                    'error' => 'صيغة الملف غير مدعومة: ' . $extension,
                    'file' => $fileName
                ];
            }

            // Check file size (max 10MB)
            if ($fileSize > 10 * 1024 * 1024) {
                Log::warning('File too large', [
                    'file' => $fileName,
                    'size' => $fileSize,
                    'folder' => $originalFolderName
                ]);
                return [
                    'success' => false,
                    'error' => 'حجم الملف كبير جداً (أكثر من 10MB)',
                    'file' => $fileName
                ];
            }

            // Create storage directory if it doesn't exist
            $storageDir = storage_path('app/public/uploads/' . $targetFileId);
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            // Generate processed filename using business rules
            $newFileName = $this->processImageFileName($fileName, $targetFileId);

            // Store the file
            $storedPath = $file->storeAs('public/uploads/' . $targetFileId, $newFileName);

            // Extract document type from filename prefix for database storage
            $documentType = $this->extractDocumentTypeFromFilename($newFileName);

            // Extract identity number from processed filename for database storage
            $identityNumber = $this->extractIdentityNumberFromFilename($fileName);

            // Save to attachments table
            $this->saveToAttachmentsTable(
                $identityNumber,
                $newFileName,
                $storedPath,
                $documentType,
                $fileSize
            );

            // Log successful processing
            Log::info('Folder file processed successfully', [
                'original_folder' => $originalFolderName,
                'target_file_id' => $targetFileId,
                'file_name' => $fileName,
                'document_type' => $documentType,
                'success' => true
            ]);

            return [
                'success' => true,
                'file' => $fileName,
                'stored_path' => $storedPath,
                'stored_name' => $newFileName,
                'document_type' => $documentType,
                'file_size' => $fileSize
            ];

        } catch (\Exception $e) {
            Log::error('Validated folder file processing error', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName() ?? 'unknown',
                'target_file_id' => $targetFileId,
                'original_folder' => $originalFolderName
            ]);

            return [
                'success' => false,
                'error' => 'فشل في معالجة الصورة. يرجى التأكد من صيغة اسم الملف والحجم.',
                'file' => $file->getClientOriginalName() ?? 'unknown'
            ];
        }
    }

    /**
     * Process validated folder file with duplicate detection
     */
    public function processValidatedFolderFileWithDuplicateCheck($file, string $targetFileId, string $originalFolderName, string $filePath, array $folderInfo, int $fileIndex): array
    {
        try {
            // أولاً: التحقق من وجود ملف مكرر
            $duplicateInfo = $this->duplicateDetectionService->checkFileForDuplicateInFolder(
                $file,
                $targetFileId,
                $originalFolderName
            );

            if ($duplicateInfo['is_duplicate']) {
                Log::info('Duplicate file detected during folder upload', [
                    'file_name' => $file->getClientOriginalName(),
                    'target_folder' => $targetFileId,
                    'original_folder' => $originalFolderName,
                    'existing_file' => $duplicateInfo['existing_file_name'] ?? 'unknown',
                    'session_id' => $duplicateInfo['session_id'] ?? 'unknown'
                ]);

                return [
                    'success' => false,
                    'duplicate_detected' => true,
                    'error' => 'تم العثور على ملف مكرر: ' . $file->getClientOriginalName(),
                    'file' => $file->getClientOriginalName(),
                    'existing_file_info' => $duplicateInfo['existing_file_name'] ?? 'unknown',
                    'duplicate_temp_path' => $duplicateInfo['temp_path'] ?? null,
                    'session_id' => $duplicateInfo['session_id'] ?? null,
                    'duplicate_record_id' => $duplicateInfo['duplicate_record_id'] ?? null
                ];
            }

            // إذا لم يكن مكرراً، نواصل المعالجة العادية
            return $this->processValidatedFolderFile($file, $targetFileId, $originalFolderName, $filePath, $folderInfo);

        } catch (\Exception $e) {
            Log::error('Error in folder file processing with duplicate check', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName() ?? 'unknown',
                'target_file_id' => $targetFileId,
                'original_folder' => $originalFolderName
            ]);

            return [
                'success' => false,
                'error' => 'فشل في معالجة الملف: ' . $e->getMessage(),
                'file' => $file->getClientOriginalName() ?? 'unknown'
            ];
        }
    }

    /**
     * Determine file type from UploadedFile object
     */
    private function determineFileType(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return $this->determineFileTypeFromExtension($extension);
    }

    /**
     * Determine file type from extension
     */
    private function determineFileTypeFromExtension(string $extension): string
    {
        return match(strtolower($extension)) {
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp' => 'image',
            'pdf' => 'pdf',
            'doc', 'docx' => 'document',
            'xls', 'xlsx', 'csv' => 'excel',
            default => 'unknown'
        };
    }

    /**
     * Determine file type from file path
     */
    private function determineFileTypeFromPath(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return $this->determineFileTypeFromExtension($extension);
    }

    /**
     * Process image filename according to business rules
     * Converts: A_566557550_4 -> NewPrefix_001460_566557550
     *
     * @param string $originalFileName Original filename like "A_566557550_4.jpg"
     * @param string $folderName The folder name like "001460"
     * @return string Processed filename
     */
    private function processImageFileName(string $originalFileName, string $folderName): string
    {
        try {
            // Extract filename without extension
            $nameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
            $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);

            Log::info('Processing image filename', [
                'original' => $originalFileName,
                'name_without_ext' => $nameWithoutExt,
                'folder_name' => $folderName
            ]);

            // Split filename by underscore
            $parts = explode('_', $nameWithoutExt);

            // Check if filename matches expected pattern (at least 3 parts)
            if (count($parts) >= 3) {
                $firstPart = $parts[0]; // A
                $identityNumber = $parts[1]; // 566557550
                $documentTypeId = (int)$parts[2]; // 4

                Log::info('Parsed filename parts', [
                    'first_part' => $firstPart,
                    'identity_number' => $identityNumber,
                    'document_type_id' => $documentTypeId
                ]);

                try {
                    // Primary processing: Get prefix from DocumentType table
                    $documentType = DB::table('document_types')
                        ->where('id', $documentTypeId)
                        ->first();

                    if ($documentType && !empty($documentType->pref)) {
                        // Success case: Use prefix from database
                        $newPrefix = $documentType->pref;
                        $newFileName = $newPrefix . '_' . $folderName . '_' . $identityNumber;

                        Log::info('Primary processing successful', [
                            'document_type_id' => $documentTypeId,
                            'found_prefix' => $newPrefix,
                            'new_filename' => $newFileName
                        ]);

                        return $newFileName . '.' . $extension;
                    } else {
                        throw new \Exception('DocumentType not found or empty prefix');
                    }

                } catch (\Exception $e) {
                    // Fallback case: Keep A_ prefix
                    Log::warning('Primary processing failed, using fallback', [
                        'error' => $e->getMessage(),
                        'document_type_id' => $documentTypeId
                    ]);

                    $fallbackFileName = $firstPart . '_' . $folderName . '_' . $identityNumber;

                    Log::info('Fallback processing applied', [
                        'fallback_filename' => $fallbackFileName
                    ]);

                    return $fallbackFileName . '.' . $extension;
                }

            } else {
                // If filename doesn't match expected pattern, return as is with timestamp
                Log::warning('Filename does not match expected pattern', [
                    'parts_count' => count($parts),
                    'parts' => $parts
                ]);

                return time() . '_' . $originalFileName;
            }

        } catch (\Exception $e) {
            Log::error('Error processing image filename', [
                'error' => $e->getMessage(),
                'original_filename' => $originalFileName
            ]);

            // Ultimate fallback: timestamp + original name
            return time() . '_' . $originalFileName;
        }
    }

    /**
     * Extract identity number from original filename
     * From: A_566557550_4.jpg -> Returns: 566557550
     */
    private function extractIdentityNumberFromFilename(string $originalFileName): ?string
    {
        try {
            $nameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
            $parts = explode('_', $nameWithoutExt);

            // Expected pattern: A_566557550_4
            if (count($parts) >= 3) {
                return $parts[1]; // Identity number is in the second part
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to extract identity number from filename', [
                'filename' => $originalFileName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract document type from processed filename
     * From: TE102_001460_9214534563.jpg -> Returns: TE102
     * From: TES-11_001443_538002200.png -> Returns: TES-11
     */
    private function extractDocumentTypeFromFilename(string $processedFileName): string
    {
        try {
            $nameWithoutExt = pathinfo($processedFileName, PATHINFO_FILENAME);
            $parts = explode('_', $nameWithoutExt);

            // Expected pattern: PREFIX_FOLDERID_IDENTITY
            if (count($parts) >= 3) {
                $documentType = $parts[0]; // Document type is in the first part

                Log::info('Extracted document type from filename', [
                    'processed_filename' => $processedFileName,
                    'extracted_type' => $documentType
                ]);

                return $documentType;
            }

            // Fallback: return 'unknown' if pattern doesn't match
            Log::warning('Could not extract document type from filename pattern', [
                'processed_filename' => $processedFileName,
                'parts_count' => count($parts),
                'parts' => $parts
            ]);

            return 'unknown';
        } catch (\Exception $e) {
            Log::error('Failed to extract document type from filename', [
                'processed_filename' => $processedFileName,
                'error' => $e->getMessage()
            ]);
            return 'unknown';
        }
    }

    /**
     * Save image information to attachments table
     */
    private function saveToAttachmentsTable(
        ?string $identityNumber,
        string $storedFileName,
        string $filePath,
        string $fileType,
        int $fileSize
    ): void {
        try {
            // Log the input parameters for debugging
            Log::info('saveToAttachmentsTable - Input Parameters', [
                'person_identity_number' => $identityNumber,
                'stored_file_name' => $storedFileName,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_size' => $fileSize
            ]);

            // Create attachment record
            $attachmentData = [
                'person_identity_number' => $identityNumber,
                'stored_file_name' => $storedFileName,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Log the data being inserted
            Log::info('saveToAttachmentsTable - Data to Insert', $attachmentData);

            // Insert into attachments table
            $insertResult = DB::table('attachments')->insert($attachmentData);

            // Log the result
            Log::info('saveToAttachmentsTable - Insert Result', [
                'success' => $insertResult,
                'data_inserted' => $attachmentData
            ]);

            // Verify insertion by querying the last inserted record
            $lastRecord = DB::table('attachments')
                ->where('person_identity_number', $identityNumber)
                ->where('stored_file_name', $storedFileName)
                ->latest('created_at')
                ->first();

            Log::info('saveToAttachmentsTable - Verification Query', [
                'found_record' => $lastRecord
            ]);

            Log::info('Image saved to attachments table', [
                'identity_number' => $identityNumber,
                'stored_file_name' => $storedFileName,
                'file_path' => $filePath,
                'file_type' => $fileType
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save to attachments table', [
                'error' => $e->getMessage(),
                'identity_number' => $identityNumber,
                'stored_file_name' => $storedFileName
            ]);

            // Don't throw exception to avoid breaking file upload process
            // Just log the error for investigation
        }
    }

    /**
     * Get duplicate files summary for a session
     */
    public function getDuplicateFilesSummary(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');

            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'معرف الجلسة مطلوب'
                ], 400);
            }

            $summary = $this->duplicateDetectionService->getDuplicateFilesSummary($sessionId);

            return response()->json([
                'success' => true,
                'message' => $summary['total_duplicates'] > 0 ?
                    "تم العثور على {$summary['total_duplicates']} ملف مكرر" :
                    "لا توجد ملفات مكررة",
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting folder duplicate summary', [
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
     * Download duplicate files as ZIP
     */
    public function downloadDuplicateFilesZip(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');

            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'معرف الجلسة مطلوب'
                ], 400);
            }

            $zipPath = $this->duplicateDetectionService->createDuplicatesZip($sessionId);

            if (!$zipPath || !file_exists($zipPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ملفات مكررة للتحميل أو فشل في إنشاء ملف ZIP'
                ], 404);
            }

            $headers = [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="folder_duplicates_' . $sessionId . '.zip"',
            ];

            return response()->download($zipPath, 'folder_duplicates_' . $sessionId . '.zip', $headers)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error downloading folder duplicate files', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحميل الملفات: ' . $e->getMessage()
            ], 500);
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
                    'message' => 'معرف الجلسة مطلوب'
                ], 400);
            }

            $deletedCount = $this->duplicateDetectionService->deleteDuplicateFiles($sessionId);

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$deletedCount} ملف مكرر بنجاح",
                'data' => [
                    'deleted_files' => $deletedCount,
                    'session_id' => $sessionId
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting folder duplicate files', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الملفات المكررة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download single duplicate file
     */
    public function downloadSingleDuplicateFile(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');
            $fileId = $request->input('file_id');

            if (!$sessionId || !$fileId) {
                return response()->json([
                    'success' => false,
                    'message' => 'معرف الجلسة ومعرف الملف مطلوبان'
                ], 400);
            }

            $duplicate = DuplicateFileTemp::where('session_id', $sessionId)
                ->where('id', $fileId)
                ->first();

            if (!$duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف المكرر غير موجود'
                ], 404);
            }

            if (!$duplicate->temp_path || !file_exists($duplicate->temp_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف التخزين المؤقت غير موجود'
                ], 404);
            }

            $headers = [
                'Content-Type' => $duplicate->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $duplicate->original_name . '"',
            ];

            return response()->download($duplicate->temp_path, $duplicate->original_name, $headers);

        } catch (\Exception $e) {
            Log::error('Error downloading single duplicate file', [
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
     * Get folder duplicate statistics
     */
    public function getFolderDuplicateStatistics(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');

            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'معرف الجلسة مطلوب'
                ], 400);
            }

            // جلب إحصائيات مفصلة
            $duplicates = DuplicateFileTemp::where('session_id', $sessionId)->get();

            $statistics = [
                'session_id' => $sessionId,
                'total_duplicates' => $duplicates->count(),
                'total_size' => $duplicates->sum('file_size'),
                'folders_affected' => $duplicates->groupBy('target_folder')->count(),
                'file_types' => [],
                'folders_breakdown' => []
            ];

            // تحليل أنواع الملفات
            foreach ($duplicates as $duplicate) {
                $extension = pathinfo($duplicate->original_name, PATHINFO_EXTENSION);
                if (!isset($statistics['file_types'][$extension])) {
                    $statistics['file_types'][$extension] = 0;
                }
                $statistics['file_types'][$extension]++;
            }

            // تحليل المجلدات
            foreach ($duplicates->groupBy('target_folder') as $folderId => $folderDuplicates) {
                $statistics['folders_breakdown'][$folderId] = [
                    'folder_id' => $folderId,
                    'duplicates_count' => $folderDuplicates->count(),
                    'total_size' => $folderDuplicates->sum('file_size'),
                    'files' => $folderDuplicates->pluck('original_name')->toArray()
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإحصائيات بنجاح',
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting folder duplicate statistics', [
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
     * معالجة رفع المجلدات مع كشف الملفات المكررة - API موحد
     */
    public function processBulkFolderUploadWithDuplicateDetection(Request $request)
    {
        try {
            Log::info('Starting bulk folder upload with duplicate detection', [
                'user_id' => auth()->id(),
                'files_count' => $request->hasFile('files') ? count($request->file('files')) : 0,
                'paths_count' => $request->has('paths') ? count($request->input('paths')) : 0
            ]);

            // التحقق من صحة البيانات
            $request->validate([
                'files.*' => 'required|file|max:1024000',
                'paths.*' => 'nullable|string',
                'enable_excel_import' => 'nullable|boolean',
                'excel_file' => 'nullable|file|mimes:xlsx,xls,csv',
                'target_table' => 'nullable|string|in:data,dead_people,guardian_bank_accounts,re_people'
            ]);

            $files = $request->file('files');
            if (!$files || empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ملفات للمعالجة'
                ], 422);
            }

            $paths = $request->input('paths', []);

            // استخراج وتحليل المجلدات
            $folderAnalysis = $this->analyzeFolderStructure($files, $paths);

            if (empty($folderAnalysis['validated_folders'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد مجلدات هوية صحيحة للمعالجة',
                    'rejected_folders' => $folderAnalysis['rejected_folders'],
                    'error_details' => 'يجب أن تحتوي المجلدات على أسماء تطابق أرقام الهوية الموجودة في النظام (8-10 أرقام)'
                ], 422);
            }

            // كشف الملفات المكررة باستخدام الخدمة المخصصة
            $duplicateResults = $this->duplicateDetectionService->processFolderFilesForDuplicates(
                $files,
                $folderAnalysis['validated_folders'],
                $folderAnalysis['path_to_folder_mapping']
            );

            // معالجة ملف Excel إذا تم رفعه
            $excelResults = null;
            if ($request->boolean('enable_excel_import') && $request->hasFile('excel_file')) {
                $excelResults = $this->processExcelWithMapping(
                    $request->file('excel_file'),
                    $request->input('target_table', 'data'),
                    $folderAnalysis['validated_folders']
                );
            }

            // حفظ معرف الجلسة للوصول للملفات المكررة لاحقاً
            if (!empty($duplicateResults['duplicate_files'])) {
                session(['duplicate_files_session_id' => $duplicateResults['session_id']]);
            }

            Log::info('Bulk folder upload with duplicate detection completed', [
                'session_id' => $duplicateResults['session_id'],
                'total_files' => $duplicateResults['total_files'],
                'duplicates_found' => $duplicateResults['duplicates_found'],
                'files_saved' => $duplicateResults['files_saved'],
                'errors_count' => count($duplicateResults['errors'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم رفع ومعالجة المجلدات بنجاح مع كشف الملفات المكررة',
                'session_id' => $duplicateResults['session_id'],
                'results' => $duplicateResults,
                'folder_analysis' => $folderAnalysis,
                'excel_results' => $excelResults,
                'duplicate_files_info' => !empty($duplicateResults['duplicate_files']) ? [
                    'session_id' => $duplicateResults['session_id'],
                    'total_duplicates' => $duplicateResults['duplicates_found'],
                    'download_url' => route('admin.file.download.duplicates', ['session_id' => $duplicateResults['session_id']]),
                    'summary_url' => route('admin.file.duplicate.summary', ['session_id' => $duplicateResults['session_id']])
                ] : null,
                'statistics' => [
                    'total_folders' => count($folderAnalysis['all_folders']),
                    'identity_folders' => count($folderAnalysis['identity_folders']),
                    'valid_folders' => count($folderAnalysis['validated_folders']),
                    'rejected_folders' => count($folderAnalysis['rejected_folders']),
                    'total_files_processed' => $duplicateResults['processed_files'],
                    'files_saved' => $duplicateResults['files_saved'],
                    'duplicates_detected' => $duplicateResults['duplicates_found'],
                    'errors_count' => count($duplicateResults['errors']),
                    'warnings_count' => count($duplicateResults['warnings'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in bulk folder upload with duplicate detection', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة رفع المجلدات: ' . $e->getMessage(),
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحليل هيكل المجلدات واستخراج مجلدات الهوية
     */
    private function analyzeFolderStructure(array $files, array $paths): array
    {
        $folderNames = [];
        $identityFolders = [];
        $pathToFolderMapping = [];
        $validatedFolders = [];
        $rejectedFolders = [];

        // استخراج أسماء المجلدات من المسارات
        foreach ($paths as $index => $path) {
            if ($path) {
                $parts = explode('/', $path);
                array_pop($parts); // إزالة اسم الملف

                // البحث عن مجلدات الهوية في جميع أجزاء المسار
                foreach ($parts as $part) {
                    if (!in_array($part, $folderNames)) {
                        $folderNames[] = $part;
                    }

                    // فحص إذا كان الجزء يشبه رقم هوية (8-10 أرقام)
                    if (preg_match('/^\d{8,10}$/', $part)) {
                        if (!in_array($part, $identityFolders)) {
                            $identityFolders[] = $part;
                        }
                        $pathToFolderMapping[$index] = $part;
                    }
                }

                // fallback للطريقة القديمة
                if (!isset($pathToFolderMapping[$index])) {
                    $possibleFolder = $parts[0] ?? '';
                    if (preg_match('/^\d{8,10}$/', $possibleFolder)) {
                        $pathToFolderMapping[$index] = $possibleFolder;
                        if (!in_array($possibleFolder, $identityFolders)) {
                            $identityFolders[] = $possibleFolder;
                        }
                    }
                }
            }
        }

        // التحقق من وجود مجلدات الهوية في قاعدة البيانات
        foreach ($identityFolders as $folderName) {
            $dataRecord = DB::table('data')->where('data_id_number', $folderName)->first();

            if ($dataRecord) {
                $validatedFolders[$folderName] = [
                    'original_folder_name' => $folderName,
                    'file_id_number' => $dataRecord->file_id_number,
                    'data_id_number' => $dataRecord->data_id_number,
                    'matched_by' => 'data_id'
                ];
            } else {
                $dataByFileId = DB::table('data')->where('file_id_number', $folderName)->first();

                if ($dataByFileId) {
                    $validatedFolders[$folderName] = [
                        'original_folder_name' => $folderName,
                        'file_id_number' => $dataByFileId->file_id_number,
                        'data_id_number' => $dataByFileId->data_id_number,
                        'matched_by' => 'file_id'
                    ];
                } else {
                    $rejectedFolders[] = $folderName;
                }
            }
        }

        return [
            'all_folders' => $folderNames,
            'identity_folders' => $identityFolders,
            'validated_folders' => $validatedFolders,
            'rejected_folders' => $rejectedFolders,
            'path_to_folder_mapping' => $pathToFolderMapping
        ];
    }

    /**
     * اختبار الاتصال بالنظام
     */
    public function testConnection()
    {
        try {
            // اختبار الاتصال بقاعدة البيانات
            DB::connection()->getPdo();

            return response()->json([
                'success' => true,
                'message' => 'تم الاتصال بنجاح',
                'timestamp' => now(),
                'database_connected' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في الاتصال: ' . $e->getMessage(),
                'timestamp' => now(),
                'database_connected' => false
            ], 500);
        }
    }

    /**
     * اختبار جدول أنواع الوثائق
     */
    public function testDocumentTypes()
    {
        try {
            // التحقق من وجود جدول document_types
            if (!Schema::hasTable('document_types')) {
                return response()->json([
                    'success' => false,
                    'message' => 'جدول document_types غير موجود',
                    'count' => 0,
                    'data' => []
                ]);
            }

            // جلب البيانات من جدول document_types
            $documentTypes = DB::table('document_types')
                ->select('id', 'name', 'prefix', 'code', 'description')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب أنواع الوثائق بنجاح',
                'count' => $documentTypes->count(),
                'data' => $documentTypes,
                'table_exists' => true
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في اختبار جدول أنواع الوثائق', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب أنواع الوثائق: ' . $e->getMessage(),
                'count' => 0,
                'data' => [],
                'table_exists' => false
            ], 500);
        }
    }

    /**
     * Get overview statistics
     */
    protected function getOverviewStats()
    {
        try {
            return [
                'total_files' => Attachment::count(),
                'total_size' => Attachment::sum('file_size') ?? 0,
                'total_folders' => Attachment::distinct('person_identity_number')->count('person_identity_number'),
                'today_uploads' => Attachment::whereDate('created_at', today())->count(),
            ];
        } catch (\Exception $e) {
            Log::error('خطأ في إحصائيات النظرة العامة: ' . $e->getMessage());
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_folders' => 0,
                'today_uploads' => 0,
            ];
        }
    }

    // ===== Duplicate Files Management Methods =====

    /**
     * Display the duplicate files management page
     */
    public function duplicateFilesIndex()
    {
        return view('admin.duplicate-files.index');
    }

    /**
     * Get paginated duplicate files with filtering and search
     */
    public function getDuplicateFilesPaginated(Request $request)
    {
        try {
            $perPage = min(max((int) $request->get('per_page', 25), 10), 100);
            $search = $request->get('search', '');
            $fileType = $request->get('file_type', '');
            $status = $request->get('status', '');

            $query = DuplicateFileTemp::query();

            // Apply search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('original_name', 'LIKE', "%{$search}%")
                      ->orWhere('duplicate_name', 'LIKE', "%{$search}%")
                      ->orWhere('temp_path', 'LIKE', "%{$search}%");
                });
            }

            // Apply file type filter
            if (!empty($fileType)) {
                switch ($fileType) {
                    case 'image':
                        $query->where('mime_type', 'LIKE', 'image/%');
                        break;
                    case 'document':
                        $query->where(function ($q) {
                            $q->where('mime_type', 'LIKE', '%pdf%')
                              ->orWhere('mime_type', 'LIKE', '%word%')
                              ->orWhere('mime_type', 'LIKE', '%document%')
                              ->orWhere('mime_type', 'LIKE', '%text%');
                        });
                        break;
                    case 'video':
                        $query->where('mime_type', 'LIKE', 'video/%');
                        break;
                    case 'audio':
                        $query->where('mime_type', 'LIKE', 'audio/%');
                        break;
                    case 'other':
                        $query->where('mime_type', 'NOT LIKE', 'image/%')
                              ->where('mime_type', 'NOT LIKE', 'video/%')
                              ->where('mime_type', 'NOT LIKE', 'audio/%')
                              ->where('mime_type', 'NOT LIKE', '%pdf%')
                              ->where('mime_type', 'NOT LIKE', '%word%')
                              ->where('mime_type', 'NOT LIKE', '%document%')
                              ->where('mime_type', 'NOT LIKE', '%text%');
                        break;
                }
            }

            // Apply status filter
            if (!empty($status)) {
                $now = now();
                if ($status === 'active') {
                    $query->where('expires_at', '>', $now);
                } elseif ($status === 'expired') {
                    $query->where('expires_at', '<=', $now);
                }
            }

            // Order by created date
            $query->orderBy('created_at', 'desc');

            // Get paginated results
            $paginatedFiles = $query->paginate($perPage);

            // Add preview URLs for images and fix missing mime types
            $items = collect($paginatedFiles->items())->map(function ($file) {
                // Fix missing mime_type
                if (!$file->mime_type || $file->mime_type === '') {
                    $extension = pathinfo($file->original_name, PATHINFO_EXTENSION);
                    $file->mime_type = $this->getMimeTypeFromExtension($extension);
                    $file->save();
                }

                if ($this->isImageFile($file->mime_type)) {
                    // Create a temporary public link for preview
                    $file->preview_url = $this->createTemporaryPreviewUrl($file);
                }
                return $file;
            })->toArray();

            // Calculate statistics
            $statistics = $this->calculateDuplicateStatistics($query);

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $items,
                    'pagination' => [
                        'current_page' => $paginatedFiles->currentPage(),
                        'last_page' => $paginatedFiles->lastPage(),
                        'per_page' => $paginatedFiles->perPage(),
                        'total' => $paginatedFiles->total(),
                        'from' => $paginatedFiles->firstItem(),
                        'to' => $paginatedFiles->lastItem(),
                    ],
                    'statistics' => $statistics
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب الملفات المكررة المقسمة: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحميل الملفات المكررة'
            ], 500);
        }
    }

    /**
     * View a specific duplicate file details
     */
    public function viewDuplicateFile($id)
    {
        try {
            $file = DuplicateFileTemp::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $file
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في عرض تفاصيل الملف المكرر: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الملف المطلوب'
            ], 404);
        }
    }

    /**
     * Preview a duplicate file (for images)
     */
    public function previewDuplicateFile($id)
    {
        try {
            $file = DuplicateFileTemp::findOrFail($id);

            // Add preview URL if it's an image
            if ($this->isImageFile($file->mime_type)) {
                $file->preview_url = $this->createTemporaryPreviewUrl($file);
            }

            return response()->json([
                'success' => true,
                'data' => $file
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في معاينة الملف المكرر: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الملف المطلوب للمعاينة'
            ], 404);
        }
    }

    /**
     * Serve image file for preview
     */
    public function serveImageFile($id)
    {
        try {
            $file = DuplicateFileTemp::findOrFail($id);

            // Try different path combinations
            $possiblePaths = [
                storage_path('app/' . $file->temp_path),
                $file->temp_path,
                storage_path('app/temp/' . basename($file->temp_path)),
                public_path($file->temp_path),
                public_path('storage/' . $file->temp_path),
            ];

            $fullPath = null;
            foreach ($possiblePaths as $path) {
                if ($path && file_exists($path)) {
                    $fullPath = $path;
                    break;
                }
            }

            if (!$fullPath) {
                Log::warning('الملف غير موجود أو غير قابل للقراءة', ['file_path' => $file->temp_path]);

                // Create a placeholder image
                $placeholderPath = public_path('images/file-not-found.png');
                if (file_exists($placeholderPath)) {
                    return response()->file($placeholderPath, [
                        'Content-Type' => 'image/png',
                        'Cache-Control' => 'public, max-age=3600',
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'الملف غير موجود'
                ], 404);
            }

            // Determine MIME type from file extension if not set
            $mimeType = $file->mime_type;
            if (!$mimeType) {
                $extension = pathinfo($file->original_name, PATHINFO_EXTENSION);
                $mimeType = $this->getMimeTypeFromExtension($extension);
            }

            return response()->file($fullPath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=3600',
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في عرض الصورة: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء عرض الصورة'
            ], 500);
        }
    }

    /**
     * Download a duplicate file by ID
     */
    public function downloadDuplicateFileById($id)
    {
        try {
            $file = DuplicateFileTemp::findOrFail($id);

            $fullPath = storage_path('app/' . $file->temp_path);

            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف غير موجود في النظام'
                ], 404);
            }

            $fileName = $file->original_name ?: $file->duplicate_name;
            $mimeType = $file->mime_type ?: 'application/octet-stream';

            return response()->download($fullPath, $fileName, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في تحميل الملف المكرر: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحميل الملف'
            ], 500);
        }
    }

    /**
     * Delete a specific duplicate file
     */
    public function deleteDuplicateFileById($id)
    {
        try {
            $file = DuplicateFileTemp::findOrFail($id);

            // Delete the physical file
            $fullPath = storage_path('app/' . $file->temp_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Delete the database record
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملف بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في حذف الملف المكرر: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الملف'
            ], 500);
        }
    }

    /**
     * Bulk delete duplicate files - Enhanced version
     */
    public function bulkDeleteDuplicateFiles(Request $request)
    {
        try {
            // Check if delete_all is requested
            if ($request->input('delete_all')) {
                $files = DuplicateFileTemp::all();

                if ($files->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا توجد ملفات مكررة للحذف'
                    ], 404);
                }

                $deletedCount = 0;
                foreach ($files as $file) {
                    try {
                        // Delete the physical file
                        $fullPath = storage_path('app/' . $file->temp_path);
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }

                        // Delete the database record
                        $file->delete();
                        $deletedCount++;

                    } catch (\Exception $e) {
                        Log::warning('فشل في حذف الملف المكرر ID: ' . $file->id . ' - ' . $e->getMessage());
                        continue;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => "تم حذف جميع الملفات المكررة بنجاح ({$deletedCount} ملف)",
                    'data' => [
                        'deleted_count' => $deletedCount
                    ]
                ]);
            }

            // Handle selected files deletion
            $fileIds = $request->input('file_ids', []);

            if (empty($fileIds) || !is_array($fileIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم تحديد أي ملفات للحذف'
                ], 400);
            }

            $files = DuplicateFileTemp::whereIn('id', $fileIds)->get();
            $deletedCount = 0;

            foreach ($files as $file) {
                try {
                    // Delete the physical file
                    $fullPath = storage_path('app/' . $file->temp_path);
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }

                    // Delete the database record
                    $file->delete();
                    $deletedCount++;

                } catch (\Exception $e) {
                    Log::warning('فشل في حذف الملف المكرر ID: ' . $file->id . ' - ' . $e->getMessage());
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$deletedCount} ملف بنجاح",
                'data' => [
                    'deleted_count' => $deletedCount,
                    'total_requested' => count($fileIds)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في الحذف المتعدد للملفات المكررة: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الملفات'
            ], 500);
        }
    }

    /**
     * Calculate statistics for duplicate files
     */
    protected function calculateDuplicateStatistics($query)
    {
        try {
            // Clone the query to avoid affecting the main query
            $statsQuery = clone $query;
            $allFiles = $statsQuery->get();

            $totalFiles = $allFiles->count();
            $totalSize = $allFiles->sum('file_size');
            $totalImages = $allFiles->where('mime_type', 'like', 'image/%')->count();
            $totalDocuments = $allFiles->filter(function ($file) {
                $mime = strtolower($file->mime_type);
                return str_contains($mime, 'pdf') ||
                       str_contains($mime, 'word') ||
                       str_contains($mime, 'document') ||
                       str_contains($mime, 'text');
            })->count();

            return [
                'total_files' => $totalFiles,
                'total_size' => $totalSize,
                'total_images' => $totalImages,
                'total_documents' => $totalDocuments,
            ];

        } catch (\Exception $e) {
            Log::warning('خطأ في حساب إحصائيات الملفات المكررة: ' . $e->getMessage());
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_images' => 0,
                'total_documents' => 0,
            ];
        }
    }

    /**
     * Check if a file is an image based on MIME type
     */
    protected function isImageFile($mimeType)
    {
        return $mimeType && str_starts_with(strtolower($mimeType), 'image/');
    }

    /**
     * Get MIME type from file extension
     */
    protected function getMimeTypeFromExtension($extension)
    {
        $extension = strtolower($extension);

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'mp4' => 'video/mp4',
            'avi' => 'video/avi',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Create a temporary preview URL for an image file
     */
    protected function createTemporaryPreviewUrl($file)
    {
        try {
            $fullPath = storage_path('app/' . $file->temp_path);

            if (!file_exists($fullPath) || !$this->isImageFile($file->mime_type)) {
                return null;
            }

            // Return a proper web URL for serving the image
            return route('admin.duplicate.files.serve', $file->id);

        } catch (\Exception $e) {
            Log::warning('خطأ في إنشاء رابط المعاينة: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get total count of duplicate files for modal display
     */
    public function getDuplicateFilesCount()
    {
        try {
            $totalCount = DuplicateFileTemp::count();
            $activeCount = DuplicateFileTemp::where('expires_at', '>', now())->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_count' => $totalCount,
                    'active_count' => $activeCount,
                    'expired_count' => $totalCount - $activeCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب عدد الملفات المكررة: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب عدد الملفات'
            ], 500);
        }
    }

    /**
     * Download all duplicate files as ZIP
     */
    public function downloadAllDuplicateFiles(Request $request)
    {
        try {
            $files = DuplicateFileTemp::all();

            if ($files->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ملفات مكررة للتنزيل'
                ], 404);
            }

            $zipFileName = 'duplicate_files_all_' . date('Y-m-d_H-i-s') . '.zip';
            $tempDir = storage_path('app/temp');
            $zipPath = $tempDir . '/' . $zipFileName;

            // إنشاء مجلد temp إذا لم يكن موجوداً
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // التأكد من أن الملف غير موجود مسبقاً
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            $zip = new \ZipArchive();
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if ($result !== TRUE) {
                Log::error('فشل في إنشاء الملف المضغوط', [
                    'zip_path' => $zipPath,
                    'error_code' => $result,
                    'error_message' => $this->getZipErrorMessage($result)
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنشاء الملف المضغوط: ' . $this->getZipErrorMessage($result)
                ], 500);
            }

            $addedFiles = 0;
            foreach ($files as $file) {
                $filePath = $file->temp_path; // المسار الكامل للملف المؤقت

                if (file_exists($filePath) && is_readable($filePath)) {
                    $fileName = $file->original_name ?: basename($file->temp_path);

                    // تجنب تكرار الأسماء
                    $counter = 1;
                    $originalFileName = $fileName;
                    while ($zip->locateName($fileName) !== false) {
                        $pathInfo = pathinfo($originalFileName);
                        $fileName = $pathInfo['filename'] . '_' . $counter . '.' . ($pathInfo['extension'] ?? '');
                        $counter++;
                    }

                    if ($zip->addFile($filePath, $fileName)) {
                        $addedFiles++;
                    } else {
                        Log::warning('فشل في إضافة الملف إلى الأرشيف', [
                            'file_path' => $filePath,
                            'file_name' => $fileName
                        ]);
                    }
                } else {
                    Log::warning('الملف غير موجود أو غير قابل للقراءة', [
                        'file_path' => $filePath
                    ]);
                }
            }

            if ($addedFiles === 0) {
                $zip->close();
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على أي ملفات صالحة للتنزيل'
                ], 404);
            }

            $closeResult = $zip->close();
            if (!$closeResult) {
                Log::error('فشل في إغلاق الملف المضغوط', [
                    'zip_path' => $zipPath,
                    'added_files' => $addedFiles
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنهاء إنشاء الملف المضغوط'
                ], 500);
            }

            // التأكد من أن الملف تم إنشاؤه بنجاح
            if (!file_exists($zipPath) || filesize($zipPath) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنشاء الملف المضغوط أو الملف فارغ'
                ], 500);
            }

            Log::info('تم إنشاء الملف المضغوط بنجاح', [
                'zip_path' => $zipPath,
                'file_size' => filesize($zipPath),
                'added_files' => $addedFiles
            ]);

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('Error downloading all duplicate files: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تنزيل الملفات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download selected duplicate files as ZIP
     */
    public function downloadSelectedDuplicateFiles(Request $request)
    {
        try {
            $request->validate([
                'file_ids' => 'required|array|min:1',
                'file_ids.*' => 'integer|exists:duplicate_files_temp,id'
            ]);

            $files = DuplicateFileTemp::whereIn('id', $request->file_ids)->get();

            if ($files->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ملفات محددة للتنزيل'
                ], 404);
            }

            $zipFileName = 'duplicate_files_selected_' . date('Y-m-d_H-i-s') . '.zip';
            $tempDir = storage_path('app/temp');
            $zipPath = $tempDir . '/' . $zipFileName;

            // إنشاء مجلد temp إذا لم يكن موجوداً
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // التأكد من أن الملف غير موجود مسبقاً
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            $zip = new \ZipArchive();
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if ($result !== TRUE) {
                Log::error('فشل في إنشاء الملف المضغوط للملفات المحددة', [
                    'zip_path' => $zipPath,
                    'error_code' => $result,
                    'error_message' => $this->getZipErrorMessage($result)
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنشاء الملف المضغوط: ' . $this->getZipErrorMessage($result)
                ], 500);
            }

            $addedFiles = 0;
            foreach ($files as $file) {
                $filePath = $file->temp_path; // المسار الكامل للملف المؤقت

                if (file_exists($filePath) && is_readable($filePath)) {
                    $fileName = $file->original_name ?: basename($file->temp_path);

                    // تجنب تكرار الأسماء
                    $counter = 1;
                    $originalFileName = $fileName;
                    while ($zip->locateName($fileName) !== false) {
                        $pathInfo = pathinfo($originalFileName);
                        $fileName = $pathInfo['filename'] . '_' . $counter . '.' . ($pathInfo['extension'] ?? '');
                        $counter++;
                    }

                    if ($zip->addFile($filePath, $fileName)) {
                        $addedFiles++;
                    } else {
                        Log::warning('فشل في إضافة الملف إلى الأرشيف', [
                            'file_path' => $filePath,
                            'file_name' => $fileName
                        ]);
                    }
                } else {
                    Log::warning('الملف غير موجود أو غير قابل للقراءة', [
                        'file_path' => $filePath
                    ]);
                }
            }

            if ($addedFiles === 0) {
                $zip->close();
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على أي ملفات صالحة للتنزيل'
                ], 404);
            }

            $closeResult = $zip->close();
            if (!$closeResult) {
                Log::error('فشل في إغلاق الملف المضغوط للملفات المحددة', [
                    'zip_path' => $zipPath,
                    'added_files' => $addedFiles
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنهاء إنشاء الملف المضغوط'
                ], 500);
            }

            // التأكد من أن الملف تم إنشاؤه بنجاح
            if (!file_exists($zipPath) || filesize($zipPath) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنشاء الملف المضغوط أو الملف فارغ'
                ], 500);
            }

            Log::info('تم إنشاء الملف المضغوط للملفات المحددة بنجاح', [
                'zip_path' => $zipPath,
                'file_size' => filesize($zipPath),
                'added_files' => $addedFiles
            ]);

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('Error downloading selected duplicate files: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تنزيل الملفات المحددة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ZipArchive error message
     */
    private function getZipErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case \ZipArchive::ER_OK:
                return 'لا يوجد خطأ';
            case \ZipArchive::ER_MULTIDISK:
                return 'عدة أقراص غير مدعومة';
            case \ZipArchive::ER_RENAME:
                return 'خطأ في إعادة التسمية';
            case \ZipArchive::ER_CLOSE:
                return 'خطأ في إغلاق الملف';
            case \ZipArchive::ER_SEEK:
                return 'خطأ في البحث';
            case \ZipArchive::ER_READ:
                return 'خطأ في القراءة';
            case \ZipArchive::ER_WRITE:
                return 'خطأ في الكتابة';
            case \ZipArchive::ER_CRC:
                return 'خطأ في CRC';
            case \ZipArchive::ER_ZIPCLOSED:
                return 'الملف المضغوط مغلق';
            case \ZipArchive::ER_NOENT:
                return 'لا يوجد مثل هذا الملف';
            case \ZipArchive::ER_EXISTS:
                return 'الملف موجود بالفعل';
            case \ZipArchive::ER_OPEN:
                return 'لا يمكن فتح الملف';
            case \ZipArchive::ER_TMPOPEN:
                return 'فشل في إنشاء ملف مؤقت';
            case \ZipArchive::ER_ZLIB:
                return 'خطأ في Zlib';
            case \ZipArchive::ER_MEMORY:
                return 'خطأ في الذاكرة';
            case \ZipArchive::ER_CHANGED:
                return 'الدخول تم تغييره';
            case \ZipArchive::ER_COMPNOTSUPP:
                return 'طريقة الضغط غير مدعومة';
            case \ZipArchive::ER_EOF:
                return 'نهاية الملف غير متوقعة';
            case \ZipArchive::ER_INVAL:
                return 'معامل غير صحيح';
            case \ZipArchive::ER_NOZIP:
                return 'ليس ملف ZIP';
            case \ZipArchive::ER_INTERNAL:
                return 'خطأ داخلي';
            case \ZipArchive::ER_INCONS:
                return 'ملف ZIP غير متسق';
            case \ZipArchive::ER_REMOVE:
                return 'لا يمكن حذف الملف';
            case \ZipArchive::ER_DELETED:
                return 'الدخول تم حذفه';
            default:
                return 'خطأ غير معروف (' . $errorCode . ')';
        }
    }

    /**
     * Get real duplicate files statistics directly from database
     */
    public function getRealDuplicateFilesStatistics()
    {
        try {
            $totalFiles = DB::table('duplicate_files_temp')->count();
            $activeFiles = DB::table('duplicate_files_temp')
                ->where('expires_at', '>', now())
                ->count();
            $expiredFiles = DB::table('duplicate_files_temp')
                ->where('expires_at', '<=', now())
                ->count();
            $totalImages = DB::table('duplicate_files_temp')
                ->where('mime_type', 'like', 'image/%')
                ->count();
            $totalDocuments = DB::table('duplicate_files_temp')
                ->where(function ($query) {
                    $query->where('mime_type', 'like', '%pdf%')
                          ->orWhere('mime_type', 'like', '%word%')
                          ->orWhere('mime_type', 'like', '%document%')
                          ->orWhere('mime_type', 'like', '%text%');
                })
                ->count();
            $totalSize = DB::table('duplicate_files_temp')->sum('file_size');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_files' => $totalFiles,
                    'active_files' => $activeFiles,
                    'expired_files' => $expiredFiles,
                    'total_images' => $totalImages,
                    'total_documents' => $totalDocuments,
                    'total_size' => $totalSize
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في جلب الإحصائيات الحقيقية للملفات المكررة: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات'
            ], 500);
        }
    }

}
