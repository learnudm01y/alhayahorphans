<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiagnosticController extends Controller
{
    public function testFolderSystem()
    {
        try {
            $results = [];

            // Test 1: Database connection
            $results['database'] = [
                'attachments_count' => DB::table('attachments')->count(),
                'enhanced_attachments_count' => DB::getSchemaBuilder()->hasTable('enhanced_attachments')
                    ? DB::table('enhanced_attachments')->count() : 0,
            ];

            // Test 2: Physical paths
            $storagePath = storage_path('app/public/uploads');
            $publicPath = public_path('storage/uploads');

            $results['paths'] = [
                'storage_path' => $storagePath,
                'storage_exists' => is_dir($storagePath),
                'public_path' => $publicPath,
                'public_exists' => is_dir($publicPath),
            ];

            // Test 3: Folder extraction
            $folderQuery = DB::table('attachments')
                ->select(DB::raw("
                    CASE
                        WHEN file_path LIKE '%storage/uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1)
                        WHEN file_path LIKE '%uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, 'uploads/', -1), '/', 1)
                        ELSE person_identity_number
                    END as folder_name,
                    COUNT(*) as files_count
                "))
                ->where(function($query) {
                    $query->where('file_path', 'LIKE', '%storage/uploads/%')
                          ->orWhere('file_path', 'LIKE', '%uploads/%')
                          ->orWhereNotNull('person_identity_number');
                })
                ->whereNotNull('file_path')
                ->where('file_path', '!=', '')
                ->groupBy(DB::raw("
                    CASE
                        WHEN file_path LIKE '%storage/uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1)
                        WHEN file_path LIKE '%uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, 'uploads/', -1), '/', 1)
                        ELSE person_identity_number
                    END
                "))
                ->orderBy('files_count', 'desc')
                ->take(10)
                ->get();

            $results['extracted_folders'] = $folderQuery->toArray();

            // Test 4: Physical folders
            if (is_dir($storagePath)) {
                $physicalFolders = array_filter(glob($storagePath . '/*'), 'is_dir');
                $results['physical_folders'] = array_map('basename', array_slice($physicalFolders, 0, 10));
                $results['physical_folders_count'] = count($physicalFolders);
            }

            // Test 5: Sample files
            $sampleFiles = DB::table('attachments')
                ->where('file_path', 'LIKE', '%uploads%')
                ->take(5)
                ->get(['id', 'file_path', 'stored_file_name', 'person_identity_number']);

            $results['sample_files'] = $sampleFiles->toArray();

            return response()->json([
                'success' => true,
                'message' => 'جميع الاختبارات نجحت!',
                'results' => $results,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في الاختبار: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }
}
