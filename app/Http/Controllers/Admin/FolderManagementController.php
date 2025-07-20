<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attachment;
use App\Models\EnhancedAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class FolderManagementController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('type', 'images'); // 'images' or 'excel'

        if ($type === 'excel') {
            return $this->getExcelFiles();
        }

        return $this->getImageFolders();
    }

    private function getImageFolders()
    {
        try {
            // البحث في جدول attachments (الجدول الأساسي للصور) - استخراج اسم المجلد من file_path
            $attachmentFolders = DB::table('attachments')
                ->select(DB::raw("
                    SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) as folder_name,
                    COUNT(*) as files_count,
                    SUM(file_size) as total_size,
                    MAX(updated_at) as last_modified,
                    GROUP_CONCAT(DISTINCT file_type) as file_types,
                    'attachments' as source
                "))
                ->where('file_path', 'LIKE', '%storage/uploads/%')
                ->whereNotNull('file_path')
                ->where('file_path', '!=', '')
                ->groupBy(DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1)"))
                ->get();

            // البحث في enhanced_attachments كنسخة احتياطية
            $enhancedFolders = collect();
            if (DB::getSchemaBuilder()->hasTable('enhanced_attachments')) {
                $columns = DB::getSchemaBuilder()->getColumnListing('enhanced_attachments');
                $recordNumberColumn = 'record_number';

                if (!in_array('record_number', $columns)) {
                    if (in_array('folder_id', $columns)) {
                        $recordNumberColumn = 'folder_id';
                    } elseif (in_array('original_folder_name', $columns)) {
                        $recordNumberColumn = 'original_folder_name';
                    }
                }

                if (in_array($recordNumberColumn, $columns)) {
                    $enhancedFolders = DB::table('enhanced_attachments')
                        ->select(DB::raw("
                            {$recordNumberColumn} as folder_name,
                            COUNT(*) as files_count,
                            SUM(file_size) as total_size,
                            MAX(updated_at) as last_modified,
                            GROUP_CONCAT(DISTINCT file_type) as file_types,
                            GROUP_CONCAT(DISTINCT mime_type) as mime_types,
                            'enhanced_attachments' as source
                        "))
                        ->whereNotNull($recordNumberColumn)
                        ->where($recordNumberColumn, '!=', '')
                        ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
                        ->whereNotIn('file_type', ['excel'])
                        ->whereNull('deleted_at')
                        ->groupBy($recordNumberColumn)
                        ->get();
                }
            }

            // دمج النتائج من كلا الجدولين
            $allFolders = collect();
            $processedFolders = [];

            // إضافة مجلدات attachments
            foreach ($attachmentFolders as $folder) {
                $folderName = $folder->folder_name;
                if (!isset($processedFolders[$folderName])) {
                    $processedFolders[$folderName] = $folder;
                } else {
                    // دمج البيانات إذا كان المجلد موجود من مصدر آخر
                    $processedFolders[$folderName]->files_count += $folder->files_count;
                    $processedFolders[$folderName]->total_size += $folder->total_size;
                    if ($folder->last_modified > $processedFolders[$folderName]->last_modified) {
                        $processedFolders[$folderName]->last_modified = $folder->last_modified;
                    }
                }
            }

            // إضافة مجلدات enhanced_attachments (فقط إذا لم تكن موجودة)
            foreach ($enhancedFolders as $folder) {
                $folderName = $folder->folder_name;
                if (!isset($processedFolders[$folderName])) {
                    $processedFolders[$folderName] = $folder;
                } else {
                    // دمج البيانات
                    $processedFolders[$folderName]->files_count += $folder->files_count;
                    $processedFolders[$folderName]->total_size += $folder->total_size;
                    if ($folder->last_modified > $processedFolders[$folderName]->last_modified) {
                        $processedFolders[$folderName]->last_modified = $folder->last_modified;
                    }
                }
            }

            // تحويل إلى collection وتطبيق الترقيم التصفحي
            $folders = collect($processedFolders)->sortByDesc('last_modified');

            // إذا لم نجد مجلدات، جرب المسح الفيزيائي
            if ($folders->isEmpty()) {
                return $this->scanPhysicalFolders();
            }

            // تطبيق الترقيم التصفحي
            $currentPage = request()->get('page', 1);
            $perPage = 50;
            $totalItems = $folders->count();
            $foldersForPage = $folders->forPage($currentPage, $perPage);

            $paginatedFolders = new LengthAwarePaginator(
                $foldersForPage->values(),
                $totalItems,
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'pageName' => 'page']
            );

            // تحسين البيانات وإضافة أسماء الأشخاص
            foreach ($paginatedFolders as $folder) {
                $folder->formatted_size = $this->formatFileSize($folder->total_size ?? 0);
                $folder->formatted_date = date('Y-m-d H:i', strtotime($folder->last_modified));
                $folder->file_types_array = !empty($folder->file_types) ? explode(',', $folder->file_types) : [];
                $folder->mime_types_array = !empty($folder->mime_types) ? explode(',', $folder->mime_types) : [];
                $folder->folder_path = "storage/uploads/{$folder->folder_name}";
                $folder->has_images = !empty(array_intersect($folder->file_types_array, ['image', 'photo']));
                $folder->has_documents = !empty(array_intersect($folder->file_types_array, ['document', 'pdf']));

                // البحث عن اسم الشخص من جدول data مع دعم التنسيقات المختلفة
                $personData = $this->getPersonName($folder->folder_name);
                $folder->person_name = $personData;
            }

            return view('file-management.folders-management.index', compact('paginatedFolders'))
                ->with('type', 'images')
                ->with('folders', $paginatedFolders);

        } catch (\Exception $e) {
            Log::error('Error fetching folders: ' . $e->getMessage());

            // Fallback to physical folder scanning
            return $this->scanPhysicalFolders();
        }
    }

    private function getExcelFiles()
    {
        try {
            // فحص الأعمدة المتوفرة في enhanced_attachments
            $columns = DB::getSchemaBuilder()->getColumnListing('enhanced_attachments');

            // تحديد العمود المناسب لرقم السجل
            $recordNumberColumn = 'record_number';
            if (!in_array('record_number', $columns)) {
                // جرب عمود بديل
                if (in_array('folder_id', $columns)) {
                    $recordNumberColumn = 'folder_id';
                } elseif (in_array('original_folder_name', $columns)) {
                    $recordNumberColumn = 'original_folder_name';
                } else {
                    Log::warning('No suitable record number column found for Excel files');
                    return $this->scanPhysicalFolders();
                }
            }

            // جلب مجلدات Excel من قاعدة البيانات بناءً على رقم السجل
            $folders = DB::table('enhanced_attachments')
                ->select(DB::raw("
                    {$recordNumberColumn} as folder_name,
                    COUNT(*) as files_count,
                    SUM(file_size) as total_size,
                    MAX(updated_at) as last_modified,
                    GROUP_CONCAT(DISTINCT file_type) as file_types,
                    GROUP_CONCAT(DISTINCT mime_type) as mime_types
                "))
                ->whereNotNull($recordNumberColumn)
                ->where($recordNumberColumn, '!=', '')
                ->where('file_type', 'excel')
                ->whereNull('deleted_at')
                ->groupBy($recordNumberColumn) // Use the same column variable here
                ->orderBy('last_modified', 'desc')
                ->paginate(20);

            // حساب إجمالي عدد ملفات Excel
            $totalExcelFiles = DB::table('enhanced_attachments')
                ->where('file_type', 'excel')
                ->whereNull('deleted_at')
                ->count();

            // تحسين البيانات وإضافة أسماء الأشخاص
            foreach ($folders as $folder) {
                $folder->formatted_size = $this->formatFileSize($folder->total_size ?? 0);
                $folder->formatted_date = date('Y-m-d H:i', strtotime($folder->last_modified));
                $folder->file_types_array = !empty($folder->file_types) ? explode(',', $folder->file_types) : [];
                $folder->mime_types_array = !empty($folder->mime_types) ? explode(',', $folder->mime_types) : [];
                $folder->folder_path = "excel/{$folder->folder_name}";
                $folder->has_excel = true;
                $folder->excel_only = true; // علامة لتمييز مجلدات Excel

                // البحث عن اسم الشخص من جدول data
                $folder->person_name = $this->getPersonName($folder->folder_name);
            }

            return view('file-management.folders-management.index', compact('folders'))
                ->with('type', 'excel')
                ->with('excelMode', true)
                ->with('totalExcelFiles', $totalExcelFiles);

        } catch (\Exception $e) {
            Log::error('Error fetching excel files: ' . $e->getMessage());

            return view('file-management.folders-management.index')
                ->with('folders', $this->createEmptyPaginator())
                ->with('type', 'excel')
                ->with('excelMode', true)
                ->with('error', 'حدث خطأ في جلب ملفات Excel');
        }
    }

    public function getFolderContents(Request $request)
    {
        $folderName = $request->get('folder');
        $type = $request->get('type', 'images');

        try {
            if ($type === 'excel') {
                // معالجة خاصة لملفات Excel
                return $this->getExcelFolderContents($folderName);
            }

            if ($type === 'images') {
                // البحث الشامل في كلا الجدولين
                $files = collect();

                // 1. البحث في جدول attachments (للصور التقليدية)
                // البحث في جدول attachments للصور (إزالة record_number)
                $attachmentFiles = DB::table('attachments')
                    ->where('person_identity_number', $folderName)
                    ->orderBy('updated_at', 'desc')
                    ->get();                // تحويل بيانات attachments للبنية المطلوبة
                foreach ($attachmentFiles as $file) {
                    $fileObject = (object) [
                        'id' => $file->id,
                        'original_file_name' => $file->stored_file_name, // استخدام stored_file_name فقط
                        'stored_file_name' => $file->stored_file_name,
                        'file_path' => $file->file_path,
                        'file_size' => $file->file_size,
                        'file_type' => $file->file_type ?: 'image',
                        'file_extension' => pathinfo($file->stored_file_name, PATHINFO_EXTENSION),
                        'mime_type' => $this->getMimeTypeFromExtension(pathinfo($file->stored_file_name, PATHINFO_EXTENSION)),
                        'record_number' => $file->person_identity_number, // إزالة مرجع record_number
                        'updated_at' => $file->updated_at,
                        'created_at' => $file->created_at,
                        'source' => 'attachments_table'
                    ];
                    $files->push($fileObject);
                }

                // 2. البحث في جدول enhanced_attachments (للملفات المحسنة)
                $enhancedColumns = DB::getSchemaBuilder()->getColumnListing('enhanced_attachments');
                $recordNumberColumn = 'record_number';

                if (!in_array('record_number', $enhancedColumns)) {
                    if (in_array('folder_id', $enhancedColumns)) {
                        $recordNumberColumn = 'folder_id';
                    } elseif (in_array('original_folder_name', $enhancedColumns)) {
                        $recordNumberColumn = 'original_folder_name';
                    }
                }

                if (in_array($recordNumberColumn, $enhancedColumns)) {
                    $enhancedFiles = DB::table('enhanced_attachments')
                        ->where($recordNumberColumn, $folderName)
                        ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
                        ->whereNotIn('file_type', ['excel']) // استثناء ملفات Excel من بوابة الصور
                        ->whereNull('deleted_at')
                        ->orderBy('updated_at', 'desc')
                        ->get();

                    // إضافة ملفات enhanced_attachments إلى القائمة
                    foreach ($enhancedFiles as $enhancedFile) {
                        $enhancedFile->source = 'enhanced_attachments_table';
                        $files->push($enhancedFile);
                    }
                }

                // 3. إذا لم نجد ملفات في أي من الجدولين، جرب المسح الفيزيائي
                if ($files->isEmpty()) {
                    return $this->getFolderContentsFromPhysical($folderName);
                }

                foreach ($files as $file) {
                    $file->formatted_size = $this->formatFileSize($file->file_size ?? 0);
                    $file->formatted_date = date('Y-m-d H:i', strtotime($file->updated_at));

                    // Enhanced file processing
                    $fileName = $file->stored_file_name ?: $file->original_file_name;
                    $basePath = 'storage/';
                    $file->download_url = null;

                    // Debug: Log the file info
                    Log::info("Processing file: {$fileName}", [
                        'record_number' => $file->record_number,
                        'original_file_name' => $file->original_file_name,
                        'stored_file_name' => $file->stored_file_name,
                        'file_path' => $file->file_path,
                        'file_extension' => $file->file_extension
                    ]);

                    // Priority path checking with enhanced logic
                    $pathsToCheck = [];

                    // 1. If file_path exists in database, try it first
                    if (!empty($file->file_path)) {
                        $cleanPath = $file->file_path;
                        // إزالة storage/ من البداية إذا كانت موجودة لتجنب التكرار
                        $cleanPath = preg_replace('#^/?storage/#', '', $cleanPath);
                        $pathsToCheck[] = "storage/" . ltrim($cleanPath, '/');
                    }

                    // 2. Direct path in record folder (most common for newer records)
                    $pathsToCheck[] = "storage/uploads/{$file->record_number}/{$fileName}";

                    // 3. Try with original filename if different
                    if ($file->stored_file_name && $file->original_file_name && $file->stored_file_name !== $file->original_file_name) {
                        $pathsToCheck[] = "storage/uploads/{$file->record_number}/{$file->original_file_name}";
                    }

                    // 4. Subfolders (legacy structure)
                    $subfolders = ['images', 'documents', 'excels', 'files'];
                    foreach ($subfolders as $subfolder) {
                        $pathsToCheck[] = "storage/uploads/{$file->record_number}/{$subfolder}/{$fileName}";
                        if ($file->stored_file_name && $file->original_file_name && $file->stored_file_name !== $file->original_file_name) {
                            $pathsToCheck[] = "storage/uploads/{$file->record_number}/{$subfolder}/{$file->original_file_name}";
                        }
                    }

                    // Check each path and use the first existing one
                    $foundPath = null;
                    foreach ($pathsToCheck as $testPath) {
                        $fullPath = public_path($testPath);
                        if (file_exists($fullPath)) {
                            $foundPath = $testPath;
                            Log::info("✅ Found file at: {$testPath} for record {$file->record_number}");
                            break;
                        }
                    }

                    // Set download URL
                    if ($foundPath) {
                        $file->download_url = asset($foundPath);
                    } else {
                        // Fallback: use the first attempted path even if file doesn't exist
                        $fallbackPath = $pathsToCheck[0] ?? "storage/uploads/{$file->record_number}/{$fileName}";
                        $file->download_url = asset($fallbackPath);
                        Log::warning("⚠️ File not found for record {$file->record_number}, file: {$fileName}. Using fallback: {$fallbackPath}");
                    }

                    // Set additional properties for frontend
                    $file->extension = $file->file_extension;
                    $file->file_name = $file->original_file_name;
                    $file->is_image = in_array(strtolower($file->file_extension ?? ''),
                        ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']) ||
                        strpos($file->mime_type ?? '', 'image/') === 0;
                    $file->is_pdf = strtolower($file->file_extension ?? '') === 'pdf' ||
                        strpos($file->mime_type ?? '', 'pdf') !== false;
                    $file->thumbnail_url = !empty($file->thumbnail_path) ? asset($file->thumbnail_path) : null;
                    $file->metadata_array = !empty($file->file_metadata) ? json_decode($file->file_metadata, true) : [];
                }

                return response()->json([
                    'success' => true,
                    'files' => $files,
                    'folder_name' => $folderName
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error fetching folder contents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب محتويات المجلد'
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $query = $request->get('search');
        $type = $request->get('type', 'images');

        try {
            if ($type === 'images') {
                // البحث المحسن مع الخوارزمية الجديدة
                $results = collect();

                // 1. البحث في جدول attachments بالخوارزمية الجديدة
                $attachmentResults = DB::table('attachments')
                    ->where(function($q) use ($query) {
                        // البحث في اسم الملف
                        $q->where('stored_file_name', 'LIKE', "%{$query}%")
                          ->orWhere('file_path', 'LIKE', "%{$query}%")
                          // البحث برقم الهوية في person_identity_number
                          ->orWhere('person_identity_number', 'LIKE', "%{$query}%")
                          // البحث في اسم المجلد المستخرج من file_path
                          ->orWhereRaw('SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) LIKE ?', ["%{$query}%"]);
                    })
                    ->where('file_path', 'LIKE', '%storage/uploads/%')
                    ->whereNotNull('file_path')
                    ->select([
                        'id',
                        'stored_file_name as original_file_name',
                        'stored_file_name',
                        'file_path',
                        'file_size',
                        'file_type',
                        'mime_type',
                        'person_identity_number',
                        'updated_at',
                        'created_at',
                        DB::raw('SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) as extracted_folder_name'),
                        DB::raw('"attachments" as source_table')
                    ])
                    ->orderBy('updated_at', 'desc')
                    ->get();

                // إضافة النتائج من attachments
                foreach ($attachmentResults as $result) {
                    $result->record_number = $result->extracted_folder_name;
                    $result->file_extension = pathinfo($result->stored_file_name, PATHINFO_EXTENSION);
                    $results->push($result);
                }

                // 2. البحث في enhanced_attachments (إضافي)
                $enhancedResults = DB::table('enhanced_attachments')
                    ->where(function($q) use ($query) {
                        $q->where('original_file_name', 'LIKE', "%{$query}%")
                          ->orWhere('stored_file_name', 'LIKE', "%{$query}%")
                          ->orWhere('file_path', 'LIKE', "%{$query}%")
                          ->orWhere('record_number', 'LIKE', "%{$query}%");
                    })
                    ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
                    ->whereNotIn('file_type', ['excel'])
                    ->whereNull('deleted_at')
                    ->select([
                        'id',
                        'original_file_name',
                        'stored_file_name',
                        'file_path',
                        'file_size',
                        'file_type',
                        'mime_type',
                        'record_number',
                        'updated_at',
                        'created_at',
                        'file_extension',
                        DB::raw('"enhanced_attachments" as source_table')
                    ])
                    ->orderBy('updated_at', 'desc')
                    ->get();

                // إضافة النتائج من enhanced_attachments
                foreach ($enhancedResults as $result) {
                    $results->push($result);
                }

                // 3. البحث بأسماء الأشخاص من جدول data
                $personResults = DB::table('data')
                    ->where(function($q) use ($query) {
                        $q->where('data_first_name', 'LIKE', "%{$query}%")
                          ->orWhere('data_father_name', 'LIKE', "%{$query}%")
                          ->orWhere('data_grand_father_name', 'LIKE', "%{$query}%")
                          ->orWhere('data_family_name', 'LIKE', "%{$query}%")
                          ->orWhereRaw('CONCAT(data_first_name, " ", data_father_name, " ", data_grand_father_name, " ", data_family_name) LIKE ?', ["%{$query}%"]);
                    })
                    ->get();

                // للأشخاص الموجودين، ابحث عن ملفاتهم
                foreach ($personResults as $person) {
                    $personFiles = DB::table('attachments')
                        ->where('file_path', 'LIKE', '%storage/uploads/%')
                        ->whereRaw('SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) = ?', [$person->file_id_number])
                        ->select([
                            'id',
                            'stored_file_name as original_file_name',
                            'stored_file_name',
                            'file_path',
                            'file_size',
                            'file_type',
                            'mime_type',
                            'person_identity_number',
                            'updated_at',
                            'created_at',
                            DB::raw('SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) as extracted_folder_name'),
                            DB::raw('"attachments_person_search" as source_table')
                        ])
                        ->get();

                    foreach ($personFiles as $file) {
                        $file->record_number = $file->extracted_folder_name;
                        $file->file_extension = pathinfo($file->stored_file_name, PATHINFO_EXTENSION);
                        $file->person_name_match = trim($person->data_first_name . ' ' . $person->data_father_name . ' ' . $person->data_grand_father_name . ' ' . $person->data_family_name);
                        $results->push($file);
                    }
                }

                // إزالة التكرار وترتيب النتائج
                $results = $results->unique('id')->sortByDesc('updated_at')->take(20);

                // تحويل إلى pagination format
                $enhancedData = $results->map(function ($file) {
                    // التأكد من وجود download_url وإصلاح المسار
                    if (empty($file->download_url) && !empty($file->file_path)) {
                        $cleanPath = ltrim($file->file_path, '/');
                        if (strpos($cleanPath, 'storage/') === 0) {
                            $cleanPath = substr($cleanPath, 8);
                        }
                        $file->download_url = asset('storage/' . $cleanPath);
                    }

                    // إضافة اسم الشخص
                    if (empty($file->person_name_match)) {
                        $file->person_name = $this->getPersonName($file->record_number ?? $file->extracted_folder_name ?? '');
                    } else {
                        $file->person_name = $file->person_name_match;
                    }

                    // إضافة formatted size
                    if (isset($file->file_size)) {
                        $file->formatted_size = $this->formatFileSize($file->file_size);
                    }

                    // إضافة formatted date
                    if (isset($file->created_at)) {
                        $file->formatted_date = date('Y-m-d', strtotime($file->created_at));
                    }

                    return $file;
                });

                // إنشاء pagination response محسن
                $paginationData = [
                    'data' => $enhancedData->toArray(),
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => $enhancedData->count(),
                    'from' => 1,
                    'to' => $enhancedData->count()
                ];
            } else {
                // تحسين البحث في ملفات Excel - بحث بسيط وآمن
                $excelResults = DB::table('enhanced_attachments')
                    ->where(function($q) use ($query) {
                        $q->where('original_file_name', 'LIKE', "%{$query}%")
                          ->orWhere('stored_file_name', 'LIKE', "%{$query}%")
                          ->orWhere('file_path', 'LIKE', "%{$query}%")
                          ->orWhere('record_number', 'LIKE', "%{$query}%");

                        // البحث في metadata JSON إذا كان العمود موجود
                        $columns = DB::getSchemaBuilder()->getColumnListing('enhanced_attachments');
                        if (in_array('file_metadata', $columns)) {
                            $q->orWhere('file_metadata', 'LIKE', "%{$query}%");
                        }
                    })
                    ->where(function($query) {
                        $query->where('file_extension', 'xlsx')
                              ->orWhere('file_extension', 'xls')
                              ->orWhere('file_extension', 'csv')
                              ->orWhere('file_extension', 'xlsm')
                              ->orWhere('mime_type', 'LIKE', '%excel%')
                              ->orWhere('mime_type', 'LIKE', '%spreadsheet%')
                              ->orWhere('file_type', 'excel');
                    })
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);

                // إضافة معلومات إضافية للنتائج
                $enhancedData = collect($excelResults->items())->map(function ($file) {
                    // التأكد من وجود download_url وإصلاح المسار
                    if (empty($file->download_url) && !empty($file->file_path)) {
                        $cleanPath = ltrim($file->file_path, '/');
                        if (strpos($cleanPath, 'storage/') === 0) {
                            $cleanPath = substr($cleanPath, 8);
                        }
                        $file->download_url = asset('storage/' . $cleanPath);
                    }

                    // إضافة formatted size
                    if (isset($file->file_size)) {
                        $file->formatted_size = $this->formatFileSize($file->file_size);
                    }

                    // إضافة formatted date
                    if (isset($file->created_at)) {
                        $file->formatted_date = date('Y-m-d', strtotime($file->created_at));
                    }

                    return $file;
                });

                // إنشاء pagination response محسن
                $paginationData = [
                    'data' => $enhancedData->toArray(),
                    'current_page' => $excelResults->currentPage(),
                    'last_page' => $excelResults->lastPage(),
                    'per_page' => $excelResults->perPage(),
                    'total' => $excelResults->total(),
                    'from' => $excelResults->firstItem(),
                    'to' => $excelResults->lastItem()
                ];
            }

            return response()->json([
                'success' => true,
                'results' => $paginationData,
                'search_query' => $query,
                'search_type' => $type
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث'
            ], 500);
        }
    }

    private function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $base = 1024;
        $index = floor(log($bytes) / log($base));

        return round($bytes / pow($base, $index), 2) . ' ' . $units[$index];
    }

    private function getStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">قيد المعالجة</span>',
            'completed' => '<span class="badge badge-success">مكتمل</span>',
            'failed' => '<span class="badge badge-danger">فشل</span>',
            'processing' => '<span class="badge badge-info">جاري المعالجة</span>'
        ];

        return $badges[$status] ?? '<span class="badge badge-secondary">غير معروف</span>';
    }

    /**
     * جلب محتويات مجلد Excel محدد
     */
    private function getExcelFolderContents($folderName)
    {
        try {
            Log::info("Getting Excel folder contents for: {$folderName}");

            $files = DB::table('enhanced_attachments')
                ->where('record_number', $folderName)
                ->where('file_type', 'excel')
                ->whereNull('deleted_at')
                ->orderBy('updated_at', 'desc')
                ->get();

            Log::info("Found {$files->count()} Excel files in folder {$folderName}");

            $processedFiles = [];
            foreach ($files as $file) {
                $file->formatted_size = $this->formatFileSize($file->file_size ?? 0);
                $file->formatted_date = date('Y-m-d H:i', strtotime($file->updated_at));

                // معالجة مسار الملف لـ Excel
                $fileName = $file->stored_file_name ?: $file->original_file_name;
                $file->download_url = null;
                $file->is_excel = true;
                $file->excel_type = $file->file_extension ?: 'xlsx';

                // مسارات محتملة لملفات Excel
                $pathsToCheck = [
                    "storage/documents/excel/{$folderName}/{$fileName}",
                    "storage/documents/{$folderName}/{$fileName}",
                    "storage/uploads/{$folderName}/{$fileName}",
                    "storage/{$file->file_path}",
                ];

                // التحقق من وجود الملف
                foreach ($pathsToCheck as $path) {
                    $fullPath = public_path($path);
                    if (file_exists($fullPath)) {
                        $file->download_url = url($path);
                        $file->file_exists = true;
                        break;
                    }
                }

                if (!$file->download_url) {
                    $file->download_url = '#';
                    $file->file_exists = false;
                    Log::warning("Excel file not found: {$fileName} in folder {$folderName}");
                }

                $processedFiles[] = $file;
            }

            return response()->json([
                'success' => true,
                'files' => $processedFiles,
                'folder_name' => $folderName,
                'total_files' => count($processedFiles),
                'type' => 'excel'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting Excel folder contents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب محتويات مجلد Excel: ' . $e->getMessage(),
                'type' => 'excel'
            ]);
        }
    }

    private function getExcelStatusBadge($processingStatus, $isProcessed)
    {
        if ($isProcessed) {
            return '<span class="badge badge-success">تم المعالجة</span>';
        }

        $badges = [
            'pending' => '<span class="badge badge-warning">في الانتظار</span>',
            'processing' => '<span class="badge badge-info">جاري المعالجة</span>',
            'completed' => '<span class="badge badge-success">مكتمل</span>',
            'failed' => '<span class="badge badge-danger">فشل</span>',
            'uploaded' => '<span class="badge badge-primary">تم الرفع</span>',
            'validated' => '<span class="badge badge-light-success">تم التحقق</span>'
        ];

        return $badges[$processingStatus] ?? '<span class="badge badge-secondary">غير محدد</span>';
    }

    /**
     * إنشاء paginator فارغ
     */
    private function createEmptyPaginator()
    {
        return new LengthAwarePaginator(
            collect([]), // العناصر
            0, // العدد الكلي
            20, // عدد العناصر في الصفحة
            1, // الصفحة الحالية
            [
                'path' => request()->url(),
                'pageName' => 'page'
            ]
        );
    }

    /**
     * Scan physical folders when database is not available
     */
    private function scanPhysicalFolders()
    {
        try {
            $uploadsPath = public_path('storage/uploads');

            if (!is_dir($uploadsPath)) {
                return view('file-management.folders-management.index')
                    ->with('folders', $this->createEmptyPaginator())
                    ->with('type', 'images')
                    ->with('error', 'مجلد التحميلات غير موجود');
            }

            $foldersData = [];
            $directories = array_filter(glob($uploadsPath . '/*'), 'is_dir');

            foreach ($directories as $directory) {
                $folderName = basename($directory);

                // Skip non-numeric folders or include them with special handling
                $filesCount = 0;
                $totalSize = 0;
                $lastModified = filemtime($directory);
                $hasImages = false;
                $hasDocuments = false;

                // Count files in the folder and its subfolders
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $filesCount++;
                        $totalSize += $file->getSize();

                        $extension = strtolower($file->getExtension());
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                            $hasImages = true;
                        } elseif (in_array($extension, ['pdf', 'doc', 'docx'])) {
                            $hasDocuments = true;
                        }
                    }
                }

                $foldersData[] = (object) [
                    'folder_name' => $folderName,
                    'files_count' => $filesCount,
                    'total_size' => $totalSize,
                    'formatted_size' => $this->formatFileSize($totalSize),
                    'last_modified' => date('Y-m-d H:i:s', $lastModified),
                    'formatted_date' => date('Y-m-d H:i', $lastModified),
                    'folder_path' => "storage/uploads/{$folderName}",
                    'has_images' => $hasImages,
                    'has_documents' => $hasDocuments,
                    'file_types_array' => [],
                    'mime_types_array' => []
                ];
            }

            // Sort by folder name (numeric folders first, then others)
            usort($foldersData, function($a, $b) {
                $aIsNumeric = is_numeric($a->folder_name);
                $bIsNumeric = is_numeric($b->folder_name);

                if ($aIsNumeric && $bIsNumeric) {
                    return (int)$a->folder_name - (int)$b->folder_name;
                } elseif ($aIsNumeric && !$bIsNumeric) {
                    return -1;
                } elseif (!$aIsNumeric && $bIsNumeric) {
                    return 1;
                } else {
                    return strcmp($a->folder_name, $b->folder_name);
                }
            });

            // Create paginated result
            $perPage = 50;
            $currentPage = request()->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedFolders = array_slice($foldersData, $offset, $perPage);

            $folders = new LengthAwarePaginator(
                $paginatedFolders,
                count($foldersData),
                $perPage,
                $currentPage,
                [
                    'path' => request()->url(),
                    'pageName' => 'page'
                ]
            );

            return view('file-management.folders-management.index', compact('folders'))
                ->with('type', 'images')
                ->with('scanned_from_disk', true)
                ->with('total_folders_found', count($foldersData));

        } catch (\Exception $e) {
            Log::error('Error scanning physical folders: ' . $e->getMessage());

            return view('file-management.folders-management.index')
                ->with('folders', $this->createEmptyPaginator())
                ->with('type', 'images')
                ->with('error', 'حدث خطأ في مسح المجلدات الفيزيائية: ' . $e->getMessage());
        }
    }

    /**
     * Construct file path with fallback logic
     */
    private function constructFilePath($file, $basePath)
    {
        $fileName = $file->stored_file_name ?: $file->original_file_name;

        // Try different path combinations in order of preference
        $pathCombinations = [
            // Direct path without images subfolder (most common for newer records)
            "uploads/{$file->record_number}/{$fileName}",

            // Path with images subfolder (for older records)
            "uploads/{$file->record_number}/images/{$fileName}",

            // Try with original file name if stored name fails
            "uploads/{$file->record_number}/" . ($file->original_file_name ?: $fileName),
            "uploads/{$file->record_number}/images/" . ($file->original_file_name ?: $fileName),

            // Path in documents subfolder
            "uploads/{$file->record_number}/documents/{$fileName}",
            "uploads/{$file->record_number}/documents/" . ($file->original_file_name ?: $fileName),

            // Path in excels subfolder
            "uploads/{$file->record_number}/excels/{$fileName}",
            "uploads/{$file->record_number}/excels/" . ($file->original_file_name ?: $fileName),
        ];

        // Check each path combination
        foreach ($pathCombinations as $pathCombination) {
            $fullPath = $basePath . $pathCombination;
            if (file_exists(public_path($fullPath))) {
                $file->download_url = asset($fullPath);

                // Log successful path for debugging
                Log::info("Found file at path: {$fullPath} for record {$file->record_number}");
                return;
            }
        }

        // If no file found, use the original file_path or construct a default
        $defaultPath = $file->file_path ?: "uploads/{$file->record_number}/{$fileName}";
        $file->download_url = asset($defaultPath);

        // Log missing file for debugging
        Log::warning("File not found for record {$file->record_number}, file: {$fileName}. Using default path: {$defaultPath}");
    }

    /**
     * Get folder contents using Eloquent Model as fallback
     */
    private function getFolderContentsUsingModel($folderName, $type)
    {
        try {
            // محاولة استخدام EnhancedAttachment Model
            $files = EnhancedAttachment::where(function($query) use ($folderName) {
                $query->where('folder_id', $folderName)
                      ->orWhere('original_folder_name', $folderName)
                      ->orWhere('file_path', 'LIKE', "%{$folderName}%");
            })
            ->whereIn('file_type', ['image', 'photo', 'document', 'pdf', 'excel'])
            ->orderBy('updated_at', 'desc')
            ->get();

            // إذا لم نجد شيء، جرب جدول attachments القديم
            if ($files->isEmpty()) {
                $files = Attachment::where('person_identity_number', $folderName)
                    ->orWhere('file_path', 'LIKE', "%{$folderName}%")
                    ->orderBy('updated_at', 'desc')
                    ->get();

                // تحويل البيانات لتتوافق مع البنية المتوقعة
                $files = $files->map(function($file) {
                    return (object) [
                        'id' => $file->id,
                        'original_file_name' => $file->stored_file_name, // استخدام stored_file_name بدلاً من file_name
                        'stored_file_name' => $file->stored_file_name,
                        'file_path' => $file->file_path,
                        'file_size' => $file->file_size,
                        'file_type' => $file->file_type,
                        'file_extension' => pathinfo($file->stored_file_name, PATHINFO_EXTENSION),
                        'mime_type' => $this->getMimeTypeFromExtension(pathinfo($file->stored_file_name, PATHINFO_EXTENSION)),
                        'record_number' => $file->person_identity_number,
                        'updated_at' => $file->updated_at,
                        'created_at' => $file->created_at
                    ];
                });
            }

            // إذا لم نجد شيء في قواعد البيانات، جرب الفحص الفيزيائي
            if ($files->isEmpty()) {
                return $this->getFolderContentsFromPhysical($folderName);
            }

            // معالجة الملفات
            foreach ($files as $file) {
                $file->formatted_size = $this->formatFileSize($file->file_size ?? 0);
                $file->formatted_date = date('Y-m-d H:i', strtotime($file->updated_at));

                // إنشاء URL للتحميل
                $this->processFileUrl($file, $folderName);

                // إضافة خصائص للواجهة الأمامية
                $file->extension = $file->file_extension;
                $file->file_name = $file->original_file_name;
                $file->is_image = in_array(strtolower($file->file_extension ?? ''),
                    ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                $file->is_pdf = strtolower($file->file_extension ?? '') === 'pdf';
                $file->metadata_array = [];
            }

            return response()->json([
                'success' => true,
                'files' => $files,
                'folder_name' => $folderName,
                'source' => 'model_fallback'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getFolderContentsUsingModel: ' . $e->getMessage());
            return $this->getFolderContentsFromPhysical($folderName);
        }
    }

    /**
     * Get folder contents from physical files as last resort
     */
    private function getFolderContentsFromPhysical($folderName)
    {
        try {
            $physicalPath = public_path("storage/uploads/{$folderName}");

            if (!is_dir($physicalPath)) {
                return response()->json([
                    'success' => true,
                    'files' => [],
                    'folder_name' => $folderName,
                    'message' => 'المجلد غير موجود فيزيائياً'
                ]);
            }

            $files = [];
            $physicalFiles = scandir($physicalPath);

            foreach ($physicalFiles as $fileName) {
                if (in_array($fileName, ['.', '..'])) continue;

                $filePath = $physicalPath . '/' . $fileName;
                if (!is_file($filePath)) continue;

                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $mimeType = $this->getMimeTypeFromExtension($extension);
                $fileType = $this->getFileTypeFromExtension($extension);

                $files[] = (object) [
                    'id' => 'physical_' . md5($fileName),
                    'original_file_name' => $fileName,
                    'stored_file_name' => $fileName,
                    'file_path' => "storage/uploads/{$folderName}/{$fileName}",
                    'file_size' => filesize($filePath),
                    'file_extension' => $extension,
                    'mime_type' => $mimeType,
                    'file_type' => $fileType,
                    'record_number' => $folderName,
                    'updated_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'created_at' => date('Y-m-d H:i:s', filectime($filePath)),
                    'formatted_size' => $this->formatFileSize(filesize($filePath)),
                    'formatted_date' => date('Y-m-d H:i', filemtime($filePath)),
                    'download_url' => asset("storage/uploads/{$folderName}/{$fileName}"),
                    'extension' => $extension,
                    'file_name' => $fileName,
                    'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']),
                    'is_pdf' => $extension === 'pdf',
                    'metadata_array' => []
                ];
            }

            return response()->json([
                'success' => true,
                'files' => $files,
                'folder_name' => $folderName,
                'source' => 'physical_scan'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getFolderContentsFromPhysical: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في فحص الملفات الفيزيائية'
            ], 500);
        }
    }

    /**
     * Process file URL with multiple fallback paths
     */
    private function processFileUrl($file, $folderName)
    {
        $fileName = $file->stored_file_name ?: $file->original_file_name ?: $file->file_name;

        // التأكد من وجود اسم الملف
        if (empty($fileName)) {
            logger()->warning("File without name found in folder {$folderName}", ['file' => $file]);
            $file->download_url = null;
            return;
        }

        // مسارات متعددة للبحث بترتيب الأولوية
        $pathsToCheck = [
            "storage/uploads/{$folderName}/{$fileName}",
            "storage/uploads/{$folderName}/images/{$fileName}",
            "storage/uploads/{$folderName}/documents/{$fileName}",
            $file->file_path,
            str_replace(['storage/', '/storage/'], ['', ''], $file->file_path)
        ];

        $foundPath = null;

        foreach ($pathsToCheck as $testPath) {
            if (empty($testPath)) continue;

            $fullPath = public_path($testPath);
            if (file_exists($fullPath)) {
                $foundPath = $testPath;
                break;
            }
        }

        // إنشاء URL بناءً على المسار الموجود أو الافتراضي
        if ($foundPath) {
            $file->download_url = asset($foundPath);
            logger()->info("File URL created successfully", [
                'folder' => $folderName,
                'file' => $fileName,
                'path' => $foundPath,
                'url' => $file->download_url
            ]);
        } else {
            // مسار افتراضي حتى لو لم يكن الملف موجوداً فعلياً
            $defaultPath = "storage/uploads/{$folderName}/{$fileName}";
            $file->download_url = asset($defaultPath);
            logger()->warning("File not found physically, using default path", [
                'folder' => $folderName,
                'file' => $fileName,
                'default_path' => $defaultPath,
                'url' => $file->download_url,
                'checked_paths' => $pathsToCheck
            ]);
        }

        // التأكد من أن download_url ليس فارغاً
        if (empty($file->download_url)) {
            $file->download_url = asset("storage/uploads/{$folderName}/{$fileName}");
            logger()->error("Empty download_url detected, forcing default", [
                'folder' => $folderName,
                'file' => $fileName,
                'forced_url' => $file->download_url
            ]);
        }
    }

    /**
     * Get MIME type from file extension
     */
    private function getMimeTypeFromExtension($extension)
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp',
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain', 'csv' => 'text/csv'
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Get file type category from extension
     */
    private function getFileTypeFromExtension($extension)
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            return 'image';
        } elseif ($extension === 'pdf') {
            return 'pdf';
        } elseif (in_array($extension, ['xls', 'xlsx', 'csv'])) {
            return 'excel';
        } elseif (in_array($extension, ['doc', 'docx'])) {
            return 'document';
        }

        return 'document';
    }

    /**
     * Get person name from data table with support for different formats
     */
    private function getPersonName($folderName)
    {
        try {
            // محاولة 1: مقارنة مباشرة (للأرقام الكبيرة مثل 000029)
            $personData = DB::table('data')
                ->where('file_id_number', $folderName)
                ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
                ->first();

            // محاولة 2: إزالة الأصفار البادئة (للأرقام الصغيرة مثل 000010)
            if (!$personData && preg_match('/^0+(\d+)$/', $folderName, $matches)) {
                $numericPart = (int)$matches[1];
                $personData = DB::table('data')
                    ->where('file_id_number', $numericPart)
                    ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
                    ->first();
            }

            if ($personData) {
                // تكوين الاسم الكامل
                $nameComponents = array_filter([
                    $personData->data_first_name ?? '',
                    $personData->data_father_name ?? '',
                    $personData->data_grand_father_name ?? '',
                    $personData->data_family_name ?? ''
                ]);

                $fullName = trim(implode(' ', $nameComponents));
                return $fullName ?: 'غير محدد';
            }

            return 'غير مسجل';

        } catch (\Exception $e) {
            Log::error('Error getting person name for folder ' . $folderName . ': ' . $e->getMessage());
            return 'خطأ في البيانات';
        }
    }
}
