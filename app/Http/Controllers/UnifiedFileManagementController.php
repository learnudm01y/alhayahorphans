<?php

namespace App\Http\Controllers;

use App\Services\FileOrganizationService;
use App\Services\CloudIntegrationService;
use App\Services\ImageProcessingService;
use App\Services\ExcelManagementService;
use App\Services\PdfManagementService;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
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

    public function __construct(
        ?FileOrganizationService $fileOrganizer = null,
        ?CloudIntegrationService $cloudIntegration = null,
        ?ImageProcessingService $imageProcessor = null,
        ?ExcelManagementService $excelManager = null,
        ?PdfManagementService $pdfManager = null,
        ?ExcelImportService $excelImportService = null
    ) {
        $this->fileOrganizer = $fileOrganizer;
        $this->cloudIntegration = $cloudIntegration;
        $this->imageProcessor = $imageProcessor;
        $this->excelManager = $excelManager;
        $this->pdfManager = $pdfManager;
        $this->excelImportService = $excelImportService ?: new ExcelImportService();
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

            // Store in images/{folderId}/
            $folderName = "images/{$folderId}";
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
                'generated_at' => now()->toISOString()
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

            // استخراج أسماء المجلدات من المسارات
            $folderNames = [];
            foreach ($paths as $path) {
                if ($path) {
                    $parts = explode('/', $path);
                    $folderName = $parts[0]; // اسم المجلد الرئيسي
                    if (!in_array($folderName, $folderNames)) {
                        $folderNames[] = $folderName;
                    }
                }
            }

            Log::info('Extracted folder names for validation', ['folders' => $folderNames]);

            // التحقق من وجود المجلدات في جدول data وتحديد المجلد النهائي للتخزين
            foreach ($folderNames as $folderName) {
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
                        Log::warning("Folder rejected - not found in data table", [
                            'folder_name' => $folderName,
                            'checked_columns' => ['data_id_number', 'file_id_number']
                        ]);
                    }
                }
            }

            // رفض العملية إذا كان هناك مجلدات غير صحيحة
            if (!empty($rejectedFolders)) {
                return response()->json([
                    'success' => false,
                    'message' => 'تحتوي المجلدات التالية على أسماء غير صحيحة',
                    'rejected_folders' => $rejectedFolders,
                    'error_details' => 'أسماء المجلدات يجب أن تطابق رقم الهوية أو رقم الملف الموجود في النظام'
                ], 422);
            }

            // معالجة كل ملف مع التحقق من النوع والتخزين المناسب
            $errors = [];
            $warnings = [];

            foreach ($files as $index => $file) {
                $path = $paths[$index] ?? '';
                $originalFolderName = '';
                $targetFileId = '';

                if ($path) {
                    $parts = explode('/', $path);
                    $originalFolderName = $parts[0];

                    if (isset($validatedFolders[$originalFolderName])) {
                        $targetFileId = $validatedFolders[$originalFolderName]['file_id_number'];
                    } else {
                        // تخطي الملف إذا كان المجلد غير صحيح
                        continue;
                    }
                } else {
                    // تخطي الملفات بدون مسار مجلد صحيح
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
                'excel_results' => $excelResults,
                'files' => $processedFiles,
                'errors' => $errors,
                'warnings' => $warnings,
                'duplicate_files' => $duplicateFilesInfo,
                'statistics' => [
                    'total_folders' => count($folderNames),
                    'valid_folders' => count($validatedFolders),
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

            return view('admin.file.excel-gateway', [
                'page_title' => 'Excel Upload Gateway',
                'current_settings' => $currentSettings
            ]);
        } catch (\Exception $e) {
            // إذا فشل تحميل view، استخدم صفحة HTML بسيطة
            return response(file_get_contents(public_path('excel-gateway.html')), 200)
                ->header('Content-Type', 'text/html');
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

            $fileData = [
                'record_number' => '0000000000', // placeholder - can be updated later
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
                // إنشاء إصدار جديد من نفس الملف
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
                'skipped_rows' => 0,
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

            $fileData = [
                'record_number' => $recordNumber,
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

            $imageData = [
                'record_number' => $folderId,
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
                    'folder_id' => $folderId,
                    'identity_number' => $identityNumber,
                    'file_name' => $customName,
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
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

    // Add missing helper methods that might be referenced elsewhere
    private function determineFileType($file): string
    {
        return $this->detectFileType($file);
    }

    private function checkStorageFolderExists(string $folderName): bool
    {
        $folderPath = storage_path('app/public/images/' . $folderName);
        return is_dir($folderPath);
    }

    private function determineFinalFolderName(string $originalFolder, string $fileIdNumber): string
    {
        // Use file_id_number as the final folder name for consistency
        return $fileIdNumber;
    }

    private function processValidatedFolderFile($file, string $targetFileId, string $originalFolderName, string $path, array $folderInfo): array
    {
        try {
            $fileType = $this->determineFileType($file);

            // Process based on file type
            switch ($fileType) {
                case 'image':
                    return $this->processImageFile($file, $targetFileId, null, []);

                case 'pdf':
                case 'excel':
                    return $this->processDocumentFile($file, $targetFileId, null, [], $fileType);

                default:
                    throw new \Exception('Unsupported file type: ' . $fileType);
            }

        } catch (\Exception $e) {
            Log::error('Validated folder file processing error: ' . $e->getMessage(), [
                'file_name' => $file->getClientOriginalName(),
                'target_file_id' => $targetFileId,
                'original_folder' => $originalFolderName
            ]);

            return [
                'success' => false,
                'error' => 'فشل في معالجة الملف: ' . $e->getMessage(),
                'original_name' => $file->getClientOriginalName()
            ];
        }
    }

    // Add missing analytics methods with basic implementations
    private function getOverviewStats(): array
    {
        try {
            return [
                'total_files' => DB::table('enhanced_attachments')->count(),
                'total_size' => DB::table('enhanced_attachments')->sum('file_size'),
                'files_today' => DB::table('enhanced_attachments')->whereDate('created_at', today())->count(),
                'processing_pending' => DB::table('enhanced_attachments')->where('processing_status', 'pending')->count()
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
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
            return ['error' => $e->getMessage()];
        }
    }

    private function getProcessingStats(): array
    {
        try {
            return DB::table('enhanced_attachments')
                ->select('processing_status', DB::raw('count(*) as count'))
                ->groupBy('processing_status')
                ->pluck('count', 'processing_status')
                ->toArray();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getStorageStats(): array
    {
        try {
            return [
                'total_size_mb' => round(DB::table('enhanced_attachments')->sum('file_size') / 1024 / 1024, 2),
                'average_file_size_mb' => round(DB::table('enhanced_attachments')->avg('file_size') / 1024 / 1024, 2),
                'largest_file_mb' => round(DB::table('enhanced_attachments')->max('file_size') / 1024 / 1024, 2)
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getUploadTrends(string $period): array
    {
        try {
            $days = (int) $period;
            return DB::table('enhanced_attachments')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as uploads'))
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getTopUploaders(): array
    {
        try {
            return DB::table('enhanced_attachments')
                ->select('uploaded_by_user_id', DB::raw('count(*) as uploads'))
                ->whereNotNull('uploaded_by_user_id')
                ->groupBy('uploaded_by_user_id')
                ->orderByDesc('uploads')
                ->limit(10)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
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
            return ['error' => $e->getMessage()];
        }
    }

    private function downloadFromCloud(string $recordNumber, array $providers): array
    {
        // Placeholder implementation
        return ['status' => 'not_implemented', 'message' => 'Cloud download not implemented yet'];
    }

    private function bidirectionalSync(string $recordNumber, array $providers): array
    {
        // Placeholder implementation
        return ['status' => 'not_implemented', 'message' => 'Bidirectional sync not implemented yet'];
    }

    private function processExcelForImport(string $excelPath): array
    {
        // Placeholder implementation
        return ['data' => [], 'headers' => []];
    }

    private function extractImageFolders(array $folders): array
    {
        // Placeholder implementation
        return ['extracted_folders' => []];
    }

    private function importExcelDataToDatabase(array $excelData, array $extractedFolders): array
    {
        // Placeholder implementation
        return ['imported_records' => 0, 'errors' => []];
    }
}
