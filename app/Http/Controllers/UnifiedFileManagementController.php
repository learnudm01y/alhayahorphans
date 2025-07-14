<?php

namespace App\Http\Controllers;

use App\Services\FileOrganizationService;
use App\Services\CloudIntegrationService;
use App\Services\ImageProcessingService;
use App\Services\ExcelManagementService;
use App\Services\PdfManagementService;
use App\Services\ExcelImportService;
use App\Services\DuplicateFileDetectionService;
use App\Models\Attachment;
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
    protected $duplicateFileDetector;

    public function __construct(
        ?FileOrganizationService $fileOrganizer = null,
        ?CloudIntegrationService $cloudIntegration = null,
        ?ImageProcessingService $imageProcessor = null,
        ?ExcelManagementService $excelManager = null,
        ?PdfManagementService $pdfManager = null,
        ?ExcelImportService $excelImportService = null,
        ?DuplicateFileDetectionService $duplicateFileDetector = null
    ) {
        $this->fileOrganizer = $fileOrganizer;
        $this->cloudIntegration = $cloudIntegration;
        $this->imageProcessor = $imageProcessor;
        $this->excelManager = $excelManager;
        $this->pdfManager = $pdfManager;
        $this->excelImportService = $excelImportService ?: new ExcelImportService();
        $this->duplicateFileDetector = $duplicateFileDetector ?: new DuplicateFileDetectionService();
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
            $analytics = [
                'overview' => $this->getOverviewStats(),
                'file_types' => $this->getFileTypeStats(),
                'processing_status' => $this->getProcessingStats(),
                'storage_usage' => $this->getStorageStats(),
                'upload_trends' => $this->getUploadTrends($request->input('period', '30')),
                'top_uploaders' => $this->getTopUploaders(),
                'cloud_sync_status' => $this->getCloudSyncStats()
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'generated_at' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics error: ' . $e->getMessage(), [
                'period' => $request->input('period', '30'),
                'user_id' => auth()->id(),
                'ip' => request()->ip()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء الإحصائيات. يرجى المحاولة مرة أخرى لاحقاً.'
            ], 500);
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

                // معالجة الملف حسب النوع
                $processedFile = $this->processValidatedFolderFile(
                    $file,
                    $targetFileId,
                    $originalFolderName,
                    $path,
                    $validatedFolders[$originalFolderName] // تمرير معلومات المجلد كاملة
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
                            'download_url' => route('file.download-duplicates', ['session_id' => $duplicateSessionId]),
                            'summary_url' => route('file.duplicate-summary', ['session_id' => $duplicateSessionId])
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
     * Process folder upload with duplicate detection and handling
     */
    public function processFolderUploadWithDuplicateDetection(Request $request)
    {
        try {
            $request->validate([
                'files.*' => 'required|file|max:1024000',
                'folder_name' => 'required|string|max:255',
                'paths.*' => 'nullable|string',
                'check_duplicates' => 'nullable|boolean',
                'handle_duplicates' => 'nullable|string|in:skip,store_temp,replace'
            ]);

            $files = $request->file('files');
            $folderName = $request->input('folder_name');
            $checkDuplicates = $request->input('check_duplicates', true);
            $handleDuplicates = $request->input('handle_duplicates', 'store_temp');

            Log::info('Processing folder upload with duplicate detection', [
                'folder_name' => $folderName,
                'files_count' => count($files),
                'check_duplicates' => $checkDuplicates,
                'handle_duplicates' => $handleDuplicates
            ]);

            $results = [
                'success' => true,
                'folder_name' => $folderName,
                'total_files' => count($files),
                'processed_files' => 0,
                'uploaded_files' => 0,
                'duplicate_files' => 0,
                'skipped_files' => 0,
                'errors' => [],
                'duplicates_session_id' => null,
                'duplicates_info' => []
            ];

            // إذا كان فحص الملفات المكررة مفعل
            if ($checkDuplicates) {
                Log::info('Starting duplicate detection for folder', ['folder_name' => $folderName]);

                $duplicateResults = $this->duplicateFileDetector->processFolderForDuplicates($files, $folderName);

                $results['duplicates_session_id'] = $duplicateResults['session_id'];
                $results['duplicate_files'] = $duplicateResults['duplicates_found'];
                $results['duplicates_info'] = $duplicateResults['duplicate_files'];

                Log::info('Duplicate detection completed', [
                    'duplicates_found' => $duplicateResults['duplicates_found'],
                    'session_id' => $duplicateResults['session_id']
                ]);

                // إذا كان التعامل مع المكررات هو "تخطي"، قم بإزالة الملفات المكررة من قائمة المعالجة
                if ($handleDuplicates === 'skip' && $duplicateResults['duplicates_found'] > 0) {
                    $duplicateFileNames = array_column($duplicateResults['duplicate_files'], 'original_name');
                    $files = array_filter($files, function($file) use ($duplicateFileNames) {
                        return !in_array($file->getClientOriginalName(), $duplicateFileNames);
                    });
                    $results['skipped_files'] = $duplicateResults['duplicates_found'];

                    Log::info('Skipped duplicate files', [
                        'skipped_count' => $results['skipped_files'],
                        'duplicate_files' => $duplicateFileNames
                    ]);
                }
            }

            // معالجة الملفات المتبقية (غير المكررة أو المكررة حسب الإعداد)
            foreach ($files as $file) {
                try {
                    $fileResult = $this->processIndividualFile($file, $folderName, $request);

                    if ($fileResult['success']) {
                        $results['uploaded_files']++;
                    } else {
                        $results['errors'][] = [
                            'file_name' => $file->getClientOriginalName(),
                            'error' => $fileResult['message'] ?? 'خطأ غير معروف'
                        ];
                    }

                    $results['processed_files']++;

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'file_name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];

                    Log::error('Error processing individual file', [
                        'file_name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // إعداد الرسالة النهائية
            $message = "تم معالجة {$results['total_files']} ملف. ";
            $message .= "رفع ناجح: {$results['uploaded_files']}, ";

            if ($results['duplicate_files'] > 0) {
                $message .= "ملفات مكررة: {$results['duplicate_files']}, ";
            }

            if ($results['skipped_files'] > 0) {
                $message .= "ملفات متخطاة: {$results['skipped_files']}, ";
            }

            if (count($results['errors']) > 0) {
                $message .= "أخطاء: " . count($results['errors']);
            }

            $results['message'] = trim($message, ', ');

            Log::info('Folder upload processing completed', $results);

            return response()->json($results);

        } catch (\Exception $e) {
            Log::error('Error in folder upload with duplicate detection', [
                'error' => $e->getMessage(),
                'folder_name' => $request->input('folder_name', 'unknown'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في معالجة المجلد: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process individual file (helper method)
     */
    private function processIndividualFile($file, $folderName, $request)
    {
        try {
            // تحديد نوع الملف
            $fileType = $this->determineFileType($file);

            // معالجة حسب نوع الملف
            switch ($fileType) {
                case 'image':
                    return $this->processImageFileIndividual($file, $folderName, $request);
                case 'pdf':
                case 'document':
                    return $this->processDocumentFileIndividual($file, $folderName, $request);
                case 'excel':
                    return $this->processExcelFileIndividual($file, $folderName, $request);
                default:
                    return [
                        'success' => false,
                        'message' => 'نوع ملف غير مدعوم: ' . $fileType
                    ];
            }

        } catch (\Exception $e) {
            Log::error('Error processing individual file', [
                'file_name' => $file->getClientOriginalName(),
                'folder_name' => $folderName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'فشل في معالجة الملف: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process individual image file
     */
    private function processImageFileIndividual($file, $folderName, $request)
    {
        try {
            // Generate record number if not provided
            $recordNumber = $request->input('record_number') ?: $this->autoGenerateRecordNumber();

            // Store the image file
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public');

            // Save to attachments table
            $attachment = Attachment::create([
                'person_identity_number' => $folderName, // Using folder name as identity
                'stored_file_name' => $fileName,
                'file_name' => $file->getClientOriginalName(), // Original file name for duplicate detection
                'file_path' => $filePath,
                'file_type' => 'image',
                'file_size' => $file->getSize(),
                'uploaded_at' => now(),
                'upload_source' => 'folder_upload'
            ]);

            Log::info('Image file processed successfully', [
                'file_name' => $file->getClientOriginalName(),
                'stored_name' => $fileName,
                'attachment_id' => $attachment->id
            ]);

            return [
                'success' => true,
                'message' => 'تم رفع الصورة بنجاح',
                'attachment_id' => $attachment->id,
                'file_path' => $filePath
            ];

        } catch (\Exception $e) {
            Log::error('Error processing image file', [
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'فشل في معالجة الصورة: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process individual document file
     */
    private function processDocumentFileIndividual($file, $folderName, $request)
    {
        try {
            // Store the document file
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/documents', $fileName, 'public');

            // For now, storing in attachments table - could be moved to enhanced_attachments
            $attachment = Attachment::create([
                'person_identity_number' => $folderName,
                'stored_file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => 'document',
                'file_size' => $file->getSize()
            ]);

            Log::info('Document file processed successfully', [
                'file_name' => $file->getClientOriginalName(),
                'stored_name' => $fileName,
                'attachment_id' => $attachment->id
            ]);

            return [
                'success' => true,
                'message' => 'تم رفع المستند بنجاح',
                'attachment_id' => $attachment->id,
                'file_path' => $filePath
            ];

        } catch (\Exception $e) {
            Log::error('Error processing document file', [
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'فشل في معالجة المستند: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process individual Excel file
     */
    private function processExcelFileIndividual($file, $folderName, $request)
    {
        try {
            // Store the Excel file
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/excel', $fileName, 'public');

            // Store in attachments table (or could use enhanced_attachments)
            $attachment = Attachment::create([
                'person_identity_number' => $folderName,
                'stored_file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => 'excel',
                'file_size' => $file->getSize()
            ]);

            Log::info('Excel file processed successfully', [
                'file_name' => $file->getClientOriginalName(),
                'stored_name' => $fileName,
                'attachment_id' => $attachment->id
            ]);

            return [
                'success' => true,
                'message' => 'تم رفع ملف Excel بنجاح',
                'attachment_id' => $attachment->id,
                'file_path' => $filePath
            ];

        } catch (\Exception $e) {
            Log::error('Error processing Excel file', [
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'فشل في معالجة ملف Excel: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get duplicate files summary for frontend
     */
    public function getDuplicateFilesSummary(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $duplicates = $this->duplicateFileDetector->getDuplicateFiles($sessionId);

            return response()->json([
                'success' => true,
                'message' => $duplicates['total_duplicates'] > 0
                    ? "تم العثور على {$duplicates['total_duplicates']} ملف مكرر"
                    : "لا توجد ملفات مكررة",
                'data' => $duplicates
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting duplicate files summary', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب ملخص الملفات المكررة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download duplicate files as ZIP
     */
    public function downloadDuplicateFiles(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $zipPath = $this->duplicateFileDetector->createDuplicatesZip($sessionId);

            if (!$zipPath || !file_exists($zipPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ملفات مكررة للتحميل'
                ], 404);
            }

            $zipFileName = "duplicate_files_{$sessionId}_" . date('Y-m-d_H-i-s') . '.zip';

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error downloading duplicate files', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في تحميل الملفات المكررة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete duplicate files for session
     */
    public function deleteDuplicateFiles(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $result = $this->duplicateFileDetector->deleteDuplicatesForSession($sessionId);

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$result['deleted_files']} ملف و {$result['deleted_records']} سجل",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting duplicate files', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في حذف الملفات المكررة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show Excel upload gateway page
     */
    public function showExcelGateway()
    {
        try {
            return view('file-management.excel-gateway');
        } catch (\Exception $e) {
            Log::error('Error showing Excel gateway', [
                'error' => $e->getMessage()
            ]);

            return response()->view('errors.500', [
                'message' => 'فشل في تحميل بوابة Excel'
            ], 500);
        }
    }

    /**
     * Show PHP diagnostic page
     */
    public function showPhpDiagnostic()
    {
        try {
            $phpSettings = [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'max_input_vars' => ini_get('max_input_vars'),
                'max_file_uploads' => ini_get('max_file_uploads'),
            ];

            return view('file-management.php-diagnostic', compact('phpSettings'));
        } catch (\Exception $e) {
            Log::error('Error showing PHP diagnostic', [
                'error' => $e->getMessage()
            ]);

            return response()->view('errors.500', [
                'message' => 'فشل في تحميل صفحة التشخيص'
            ], 500);
        }
    }
}
