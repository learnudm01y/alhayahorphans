<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\DuplicateFileTemp;

class ZipTestController extends Controller
{
    /**
     * Test ZIP creation functionality
     */
    public function testZipCreation()
    {
        try {
            $results = [];

            // Test 1: Check if ZipArchive is available
            $results['zip_available'] = extension_loaded('zip');

            // Test 2: Check temp directory
            $tempDir = storage_path('app/temp');
            $results['temp_dir_exists'] = file_exists($tempDir);
            $results['temp_dir_writable'] = is_writable($tempDir);

            if (!$results['temp_dir_exists']) {
                mkdir($tempDir, 0755, true);
                $results['temp_dir_created'] = true;
            }

            // Test 3: Create a simple ZIP file
            $testZipPath = $tempDir . '/test_' . date('Y-m-d_H-i-s') . '.zip';
            $zip = new \ZipArchive();
            $result = $zip->open($testZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $results['zip_creation_result'] = $result;
            $results['zip_creation_success'] = ($result === TRUE);

            if ($result === TRUE) {
                // Add a test file
                $zip->addFromString('test.txt', 'This is a test file');
                $closeResult = $zip->close();
                $results['zip_close_result'] = $closeResult;

                // Check if file was created
                $results['zip_file_exists'] = file_exists($testZipPath);
                $results['zip_file_size'] = file_exists($testZipPath) ? filesize($testZipPath) : 0;

                // Clean up
                if (file_exists($testZipPath)) {
                    unlink($testZipPath);
                }
            }

            // Test 4: Check duplicate files in database
            $duplicateFiles = DuplicateFileTemp::take(5)->get();
            $results['duplicate_files_count'] = $duplicateFiles->count();
            $results['duplicate_files_paths'] = [];

            foreach ($duplicateFiles as $file) {
                $filePath = storage_path('app/public/' . $file->temp_path);
                $results['duplicate_files_paths'][] = [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'temp_path' => $file->temp_path,
                    'full_path' => $filePath,
                    'exists' => file_exists($filePath),
                    'readable' => file_exists($filePath) ? is_readable($filePath) : false,
                    'size' => file_exists($filePath) ? filesize($filePath) : 0
                ];
            }

            // Test 5: System info
            $results['system_info'] = [
                'php_version' => phpversion(),
                'temp_dir_path' => $tempDir,
                'storage_path' => storage_path('app'),
                'public_path' => storage_path('app/public'),
                'disk_free_space' => disk_free_space($tempDir),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ];

            return response()->json([
                'success' => true,
                'results' => $results,
                'message' => 'تم إجراء جميع الاختبارات بنجاح'
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في اختبار ZIP: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Test download functionality with better error handling
     */
    public function testDownloadWithBetterErrorHandling()
    {
        try {
            $files = DuplicateFileTemp::take(3)->get();

            if ($files->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ملفات للاختبار'
                ], 404);
            }

            $zipFileName = 'test_download_' . date('Y-m-d_H-i-s') . '.zip';
            $tempDir = storage_path('app/temp');

            // إنشاء المجلد إذا لم يكن موجوداً
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0755, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'فشل في إنشاء مجلد التخزين المؤقت'
                    ], 500);
                }
            }

            $zipPath = $tempDir . '/' . $zipFileName;

            // حذف الملف إذا كان موجوداً
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            // إنشاء الملف المضغوط
            $zip = new \ZipArchive();
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if ($result !== TRUE) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إنشاء ZIP: ' . $this->getZipErrorMessage($result),
                    'error_code' => $result
                ], 500);
            }

            $addedFiles = 0;
            $errors = [];

            foreach ($files as $file) {
                $filePath = storage_path('app/public/' . $file->temp_path);

                if (!file_exists($filePath)) {
                    $errors[] = "الملف غير موجود: {$file->original_name}";
                    continue;
                }

                if (!is_readable($filePath)) {
                    $errors[] = "الملف غير قابل للقراءة: {$file->original_name}";
                    continue;
                }

                $fileName = $file->original_name ?: basename($file->temp_path);

                if ($zip->addFile($filePath, $fileName)) {
                    $addedFiles++;
                } else {
                    $errors[] = "فشل في إضافة الملف: {$fileName}";
                }
            }

            if ($addedFiles === 0) {
                $zip->close();
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم إضافة أي ملفات',
                    'errors' => $errors
                ], 400);
            }

            $closeResult = $zip->close();

            if (!$closeResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إغلاق الملف المضغوط'
                ], 500);
            }

            // التحقق من الملف النهائي
            if (!file_exists($zipPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف المضغوط لم يتم إنشاؤه'
                ], 500);
            }

            $fileSize = filesize($zipPath);
            if ($fileSize === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف المضغوط فارغ'
                ], 500);
            }

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('خطأ في اختبار التنزيل: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
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
}
