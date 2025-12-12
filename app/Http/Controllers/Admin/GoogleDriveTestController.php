<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleDriveTestController extends Controller
{
    private $driveService;

    public function __construct()
    {
        try {
            $this->driveService = new GoogleDriveService();
        } catch (Exception $e) {
            Log::error('فشل تهيئة GoogleDriveService: ' . $e->getMessage());
        }
    }

    /**
     * عرض صفحة الاختبار
     */
    public function index()
    {
        return view('admin.google_drive_test');
    }

    /**
     * اختبار الاتصال بـ Google Drive
     */
    public function testConnection(Request $request)
    {
        try {
            if (!$this->driveService) {
                throw new Exception('فشل تهيئة خدمة Google Drive');
            }

            // محاولة سرد Shared Drives المتاحة
            $sharedDrives = $this->driveService->listSharedDrives();

            // محاولة سرد الملفات كاختبار للاتصال
            $files = $this->driveService->listFiles(null, 5);

            return response()->json([
                'success' => true,
                'message' => 'تم الاتصال بـ Google Drive بنجاح! ✅',
                'data' => [
                    'files_count' => count($files['files'] ?? []),
                    'files' => $files['files'] ?? [],
                    'shared_drives_count' => count($sharedDrives['drives'] ?? []),
                    'shared_drives' => $sharedDrives['drives'] ?? []
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال بـ Google Drive',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * رفع ملف تجريبي
     */
    public function uploadTest(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // حد أقصى 10MB
                'folder_id' => 'required|string', // Folder ID مطلوب
            ]);

            if (!$this->driveService) {
                throw new Exception('فشل تهيئة خدمة Google Drive');
            }

            $file = $request->file('file');
            $filePath = $file->getRealPath();
            $fileName = $file->getClientOriginalName();
            $folderId = $request->input('folder_id');

            // رفع الملف إلى المجلد المحدد
            $result = $this->driveService->uploadFile($filePath, $fileName, $folderId);

            return response()->json([
                'success' => true,
                'message' => 'تم رفع الملف بنجاح! ✅',
                'data' => [
                    'file_id' => $result['id'],
                    'file_name' => $result['name'],
                    'web_view_link' => "https://drive.google.com/file/d/{$result['id']}/view"
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل رفع الملف',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * سرد الملفات
     */
    public function listFiles(Request $request)
    {
        try {
            if (!$this->driveService) {
                throw new Exception('فشل تهيئة خدمة Google Drive');
            }

            $pageSize = $request->input('page_size', 20);
            $folderId = $request->input('folder_id', null);

            $result = $this->driveService->listFiles($folderId, $pageSize);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة الملفات بنجاح',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب قائمة الملفات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف ملف
     */
    public function deleteFile(Request $request)
    {
        try {
            $request->validate([
                'file_id' => 'required|string'
            ]);

            if (!$this->driveService) {
                throw new Exception('فشل تهيئة خدمة Google Drive');
            }

            $fileId = $request->input('file_id');
            $this->driveService->deleteFile($fileId);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملف بنجاح! ✅'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل حذف الملف',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء مجلد جديد
     */
    public function createFolder(Request $request)
    {
        try {
            $request->validate([
                'folder_name' => 'required|string|max:255'
            ]);

            if (!$this->driveService) {
                throw new Exception('فشل تهيئة خدمة Google Drive');
            }

            $folderName = $request->input('folder_name');
            $parentFolderId = $request->input('parent_folder_id', null);

            $result = $this->driveService->createFolder($folderName, $parentFolderId);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المجلد بنجاح! ✅',
                'data' => [
                    'folder_id' => $result['id'],
                    'folder_name' => $result['name']
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء المجلد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث عن ملفات
     */
    public function searchFiles(Request $request)
    {
        try {
            $request->validate([
                'query' => 'required|string|min:1'
            ]);

            if (!$this->driveService) {
                throw new Exception('فشل تهيئة خدمة Google Drive');
            }

            $query = $request->input('query');
            $pageSize = $request->input('page_size', 20);

            $result = $this->driveService->searchFiles($query, $pageSize);

            return response()->json([
                'success' => true,
                'message' => 'تم البحث بنجاح',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل البحث',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحصول على معلومات ملف
     */
    public function getFileInfo(Request $request)
    {
        try {
            $request->validate([
                'file_id' => 'required|string'
            ]);

            if (!$this->driveService) {
                throw new Exception('فشل تهيئة خدمة Google Drive');
            }

            $fileId = $request->input('file_id');
            $result = $this->driveService->getFile($fileId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب معلومات الملف بنجاح',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب معلومات الملف',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
