<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\UnifiedFileManagementController;
use App\Http\Controllers\SimpleFileUploadController;
use App\Http\Controllers\DuplicateFileController;
use App\Http\Controllers\Api\FileAnalyticsController;
use App\Models\City;
use App\Services\UltraFastSearchService;
use App\Services\LightningSearchService;
use App\Services\ExactMatchSearchService;
use App\Services\SmartExactSearchService;
use App\Services\SimpleExactSearchService;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Ultra Fast Search API - بدون middleware للحصول على أقصى سرعة
Route::get('/search/ultra-fast', function (Request $request) {
    $query = $request->get('q', '');
    $limit = $request->get('limit', 20);

    $searchService = new UltraFastSearchService();
    return response()->json($searchService->search($query, (int)$limit));
});

// Lightning Fast Search API - PDO مباشر للسرعة القصوى
Route::get('/search/lightning', function (Request $request) {
    $query = $request->get('q', '');
    $limit = $request->get('limit', 20);

    $searchService = new LightningSearchService();
    return response()->json($searchService->search($query, (int)$limit));
});

// Exact Match Search API - للبحث الدقيق والمطابقة التامة
Route::get('/search/exact', function (Request $request) {
    $query = $request->get('q', '');
    $limit = $request->get('limit', 10);

    $searchService = new ExactMatchSearchService();
    return response()->json($searchService->search($query, (int)$limit));
});

// Smart Exact Search API - للبحث الذكي الدقيق مع فهم الأسماء المركبة
Route::get('/search/smart', function (Request $request) {
    $query = $request->get('q', '');
    $limit = $request->get('limit', 10);

    $searchService = new SmartExactSearchService();
    return response()->json($searchService->search($query, (int)$limit));
});

// Final Exact Search API - للبحث الدقيق النهائي
Route::get('/search/final-exact', function (Request $request) {
    $query = $request->get('q', '');
    $limit = $request->get('limit', 10);

    $startTime = microtime(true);

    if (empty($query)) {
        return response()->json([
            'results' => [],
            'count' => 0,
            'search_time' => 0,
            'query' => $query,
            'engine' => 'Final Exact Match'
        ]);
    }

    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=aso;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // تحليل الاسم إلى أجزاء
        $words = array_filter(explode(' ', trim($query)));
        $wordCount = count($words);

        $results = [];

        if ($wordCount == 5) {
            // حالة خاصة: 5 كلمات (اسم + عبد + الرحمن + جد + عائلة)
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?
                    ORDER BY ID DESC LIMIT " . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1] . ' ' . $words[2], $words[3], $words[4]]);
            $results = $stmt->fetchAll();
        } elseif ($wordCount == 4) {
            // 4 كلمات
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?
                    ORDER BY ID DESC LIMIT " . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1], $words[2], $words[3]]);
            $results = $stmt->fetchAll();
        } elseif ($wordCount == 3) {
            // 3 كلمات
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ?
                    ORDER BY ID DESC LIMIT " . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1], $words[2]]);
            $results = $stmt->fetchAll();
        } elseif ($wordCount == 2) {
            // كلمتان
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ?
                    ORDER BY ID DESC LIMIT " . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1]]);
            $results = $stmt->fetchAll();
        } elseif ($wordCount == 1) {
            // كلمة واحدة
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ?
                    ORDER BY ID DESC LIMIT " . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0]]);
            $results = $stmt->fetchAll();
        }

        // تحويل النتائج
        $mappedResults = array_map(function($p) {
            return [
                'id' => $p['ID'],
                'id_num' => $p['CI_ID_NUM'] ?: '',
                'full_name' => trim(($p['CI_FIRST_ARB'] ?: '') . ' ' . ($p['CI_FATHER_ARB'] ?: '') . ' ' . ($p['CI_GRAND_FATHER_ARB'] ?: '') . ' ' . ($p['CI_FAMILY_ARB'] ?: '')),
                'first_name' => $p['CI_FIRST_ARB'] ?: '',
                'father_name' => $p['CI_FATHER_ARB'] ?: '',
                'grand_father_name' => $p['CI_GRAND_FATHER_ARB'] ?: '',
                'family_name' => $p['CI_FAMILY_ARB'] ?: '',
                'mother_name' => $p['MOTHER_NAME1'] ?: '',
                'birth_date' => $p['CI_BIRTH_DT'] ?: '',
                'gender' => $p['CI_SEX_CD'] == 1 ? 'ذكر' : ($p['CI_SEX_CD'] == 2 ? 'أنثى' : ''),
                'city' => $p['CITY'] ?: '',
            ];
        }, $results);

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'results' => $mappedResults,
            'count' => count($mappedResults),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Final Exact Match',
            'word_count' => $wordCount
        ]);

    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'results' => [],
            'count' => 0,
            'search_time' => round((microtime(true) - $startTime) * 1000, 2),
            'query' => $query,
            'engine' => 'Final Exact Match'
        ], 500);
    }
});// Simple file upload for testing
Route::prefix('simple')->group(function () {
    Route::get('/test', [SimpleFileUploadController::class, 'test']);
    Route::post('/upload', [SimpleFileUploadController::class, 'simpleUpload']);
});

// File management API routes
Route::prefix('files')->group(function () {
    // إحصائيات محدّثة من الجداول الجديدة (يجب أن تكون الأولى)
    Route::get('/analytics/detailed', [FileAnalyticsController::class, 'getDetailedAnalytics']);
    Route::get('/analytics/new', [FileAnalyticsController::class, 'getAnalytics']); // استخدام endpoint جديد

    // Routes القديمة (للتوافق مع الأنظمة الموجودة)
    Route::get('/analytics-legacy', [UnifiedFileManagementController::class, 'getAnalytics']);
    Route::get('/analytics', [UnifiedFileManagementController::class, 'getAnalytics']); // الـ route القديم
    Route::post('/smart-upload', [UnifiedFileManagementController::class, 'smartUpload']);
    Route::post('/batch-upload', [UnifiedFileManagementController::class, 'batchUpload']);
    Route::get('/batch-status/{batch_id}', [UnifiedFileManagementController::class, 'getBatchStatus']);
    Route::get('/download/{fileId}', [UnifiedFileManagementController::class, 'downloadFile']);

    // New endpoints for enhanced functionality
    Route::post('/generate-record-number', [UnifiedFileManagementController::class, 'generateRecordNumber']);
    Route::post('/excel-bulk-import', [UnifiedFileManagementController::class, 'excelBulkImport']);
    Route::post('/process-folder-upload', [UnifiedFileManagementController::class, 'processFolderUpload']);
    Route::get('/analytics/overview', [UnifiedFileManagementController::class, 'getAnalyticsOverview']);
    Route::get('/analytics/storage', [UnifiedFileManagementController::class, 'getStorageAnalytics']);
    Route::get('/analytics/activity', [UnifiedFileManagementController::class, 'getActivityAnalytics']);
});

// Duplicate Files API Routes - بدون authentication
Route::prefix('duplicate-files')->group(function () {
    Route::get('/summary', [DuplicateFileController::class, 'getDuplicateFilesSummary']);
    Route::delete('/delete', [DuplicateFileController::class, 'deleteDuplicateFiles']);
    Route::get('/download', [DuplicateFileController::class, 'downloadDuplicateFiles']);
    Route::post('/process', [DuplicateFileController::class, 'processFolderForDuplicates']);
});

// البحث الدقيق الوحيد - لإرجاع نتيجة واحدة فقط (حل مشكلة النتائج المتعددة)
Route::get('/search/exact-only', function (Request $request) {
    $query = $request->get('q', '');
    $limit = 1; // نتيجة واحدة فقط دائماً

    $startTime = microtime(true);

    if (empty($query)) {
        return response()->json([
            'success' => false,
            'message' => 'يرجى إدخال اسم للبحث',
            'results' => [],
            'count' => 0,
            'search_time' => 0,
            'query' => $query,
            'engine' => 'Exact Only Search'
        ]);
    }

    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=aso;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // تحليل الاسم إلى أجزاء
        $words = array_filter(explode(' ', trim($query)));
        $wordCount = count($words);

        $results = [];

        // البحث الدقيق حسب عدد الكلمات
        if ($wordCount == 5) {
            // 5 كلمات: اسم + عبد + الرحمن + جد + عائلة
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1] . ' ' . $words[2], $words[3], $words[4]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 4) {
            // 4 كلمات: اسم + أب + جد + عائلة
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1], $words[2], $words[3]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 3) {
            // 3 كلمات: اسم + أب + جد
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1], $words[2]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 2) {
            // كلمتان: اسم + أب
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 1) {
            // كلمة واحدة: الاسم الأول فقط
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0]]);
            $results = $stmt->fetchAll();
        }

        // تحويل النتائج
        $mappedResults = [];
        if (!empty($results)) {
            $person = $results[0]; // نتيجة واحدة فقط
            $mappedResults[] = [
                'id' => $person['ID'],
                'id_num' => $person['CI_ID_NUM'] ?: 'غير محدد',
                'full_name' => trim(($person['CI_FIRST_ARB'] ?: '') . ' ' .
                              ($person['CI_FATHER_ARB'] ?: '') . ' ' .
                              ($person['CI_GRAND_FATHER_ARB'] ?: '') . ' ' .
                              ($person['CI_FAMILY_ARB'] ?: '')),
                'first_name' => $person['CI_FIRST_ARB'] ?: 'غير محدد',
                'father_name' => $person['CI_FATHER_ARB'] ?: 'غير محدد',
                'grand_father_name' => $person['CI_GRAND_FATHER_ARB'] ?: 'غير محدد',
                'family_name' => $person['CI_FAMILY_ARB'] ?: 'غير محدد',
                'mother_name' => $person['MOTHER_NAME1'] ?: 'غير محدد',
                'birth_date' => $person['CI_BIRTH_DT'] ?: 'غير محدد',
                'gender' => $person['CI_SEX_CD'] == 1 ? 'ذكر' : ($person['CI_SEX_CD'] == 2 ? 'أنثى' : 'غير محدد'),
                'city' => $person['CITY'] ?: 'غير محدد',
            ];
        }

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'success' => count($mappedResults) > 0,
            'message' => count($mappedResults) > 0 ? 'تم العثور على مطابقة دقيقة واحدة' : 'لم يتم العثور على مطابقة دقيقة',
            'results' => $mappedResults,
            'count' => count($mappedResults),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Exact Only Search - One Result',
            'word_count' => $wordCount
        ]);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ في البحث: ' . $e->getMessage(),
            'results' => [],
            'count' => 0,
            'search_time' => round((microtime(true) - $startTime) * 1000, 2),
            'query' => $query,
            'engine' => 'Exact Only Search'
        ], 500);
    }
});

// Route بسيط لجلب اسم الشخص من جدول data
Route::get('/person-name/{id_number}', function ($id_number) {
    try {
        $person = DB::table('data')
            ->where('data_id_number', $id_number)
            ->first();

        if ($person) {
            $fullName = trim(($person->data_first_name ?: '') . ' ' .
                           ($person->data_father_name ?: '') . ' ' .
                           ($person->data_grand_father_name ?: '') . ' ' .
                           ($person->data_family_name ?: ''));

            // جلب اسم المدينة من جدول city
            $cityName = 'غير محدد';
            if ($person->data_city) {
                $city = City::find($person->data_city);
                if ($city && $city->city && $city->city !== '-' && $city->city !== 'غير معرف') {
                    $cityName = $city->city;
                }
            }

            return response()->json([
                'success' => true,
                'full_name' => $fullName,
                'first_name' => $person->data_first_name ?: '',
                'father_name' => $person->data_father_name ?: '',
                'grand_father_name' => $person->data_grand_father_name ?: '',
                'family_name' => $person->data_family_name ?: '',
                'gender' => $person->data_gender ?: '',
                'city' => $cityName,
                'city_code' => $person->data_city,
                'id_number' => $id_number
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على شخص بهذا الرقم'
            ], 404);
        }
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ: ' . $e->getMessage()
        ], 500);
    }
});

// File Gallery API Routes - لإدارة معاينة وتحميل وحذف الصور
Route::prefix('gallery')->group(function () {
    // جلب ملفات مجلد معين
    Route::get('/folder/{folder_name}', function ($folder_name) {
        try {
            $uploadsPath = public_path('uploads/' . $folder_name);

            if (!is_dir($uploadsPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'المجلد غير موجود',
                    'files' => []
                ]);
            }

            $files = [];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx'];

            foreach (scandir($uploadsPath) as $file) {
                if ($file === '.' || $file === '..') continue;

                $filePath = $uploadsPath . '/' . $file;
                if (is_file($filePath)) {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                    if (in_array($extension, $allowedExtensions)) {
                        $fileInfo = [
                            'name' => $file,
                            'original_name' => $file,
                            'size' => filesize($filePath),
                            'size_formatted' => round(filesize($filePath) / 1024, 2) . ' KB',
                            'extension' => $extension,
                            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']),
                            'url' => url('uploads/' . $folder_name . '/' . $file),
                            'modified_at' => date('Y-m-d H:i:s', filemtime($filePath))
                        ];

                        $files[] = $fileInfo;
                    }
                }
            }

            // ترتيب الملفات حسب تاريخ التعديل (الأحدث أولاً)
            usort($files, function($a, $b) {
                return strtotime($b['modified_at']) - strtotime($a['modified_at']);
            });

            return response()->json([
                'success' => true,
                'folder_name' => $folder_name,
                'files_count' => count($files),
                'files' => $files
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
                'files' => []
            ], 500);
        }
    });

    // حذف ملف
    Route::delete('/file/{folder_name}/{file_name}', function ($folder_name, $file_name) {
        try {
            $filePath = public_path('uploads/' . $folder_name . '/' . $file_name);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف غير موجود'
                ], 404);
            }

            if (unlink($filePath)) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف الملف بنجاح'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في حذف الملف'
                ], 500);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    });

    // تنزيل ملف
    Route::get('/download/{folder_name}/{file_name}', function ($folder_name, $file_name) {
        try {
            $filePath = public_path('uploads/' . $folder_name . '/' . $file_name);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف غير موجود'
                ], 404);
            }

            return response()->download($filePath, $file_name);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    });
});

// ====================================================================
// API للكشف عن الملفات المكررة - نظام التسجيل
// ====================================================================
Route::post('/check-duplicate-file', [UnifiedFileManagementController::class, 'checkSingleDuplicate']);
Route::post('/replace-duplicate-file', [UnifiedFileManagementController::class, 'handleDuplicate']);
