<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class FileSystemSyncController extends Controller
{
    /**
     * Sync physical files with database
     */
    public function syncPhysicalFiles(Request $request)
    {
        $folderName = $request->get('folder');
        $dryRun = $request->get('dry_run', true); // Default to dry run

        try {
            $results = [
                'success' => true,
                'folder' => $folderName,
                'physical_files' => [],
                'database_files' => [],
                'missing_in_db' => [],
                'missing_physical' => [],
                'actions_taken' => []
            ];

            // Get physical files
            $physicalPath = public_path("storage/uploads/{$folderName}");
            $physicalFiles = [];

            if (is_dir($physicalPath)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($physicalPath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $relativePath = str_replace($physicalPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                        $relativePath = str_replace('\\', '/', $relativePath);

                        $physicalFiles[] = [
                            'name' => $file->getFilename(),
                            'path' => $relativePath,
                            'full_path' => $file->getPathname(),
                            'size' => $file->getSize(),
                            'extension' => strtolower($file->getExtension()),
                            'modified' => date('Y-m-d H:i:s', $file->getMTime())
                        ];
                    }
                }
            }

            $results['physical_files'] = $physicalFiles;

            // Get database files
            $databaseFiles = DB::table('enhanced_attachments')
                ->where('record_number', $folderName)
                ->whereNull('deleted_at')
                ->get()
                ->toArray();

            $results['database_files'] = $databaseFiles;

            // Find missing files in database
            $dbFileNames = array_column($databaseFiles, 'stored_file_name');
            $dbOriginalNames = array_column($databaseFiles, 'original_file_name');
            $allDbNames = array_merge($dbFileNames, $dbOriginalNames);

            foreach ($physicalFiles as $physicalFile) {
                if (!in_array($physicalFile['name'], $allDbNames)) {
                    $results['missing_in_db'][] = $physicalFile;

                    // Add to database if not dry run
                    if (!$dryRun) {
                        $fileData = $this->createFileRecord($physicalFile, $folderName);
                        $insertId = DB::table('enhanced_attachments')->insertGetId($fileData);
                        $results['actions_taken'][] = "Added file {$physicalFile['name']} to database with ID {$insertId}";
                    }
                }
            }

            // Find missing physical files
            foreach ($databaseFiles as $dbFile) {
                $fileName = $dbFile->stored_file_name ?: $dbFile->original_file_name;
                $found = false;

                foreach ($physicalFiles as $physicalFile) {
                    if ($physicalFile['name'] === $fileName) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $results['missing_physical'][] = $dbFile;
                }
            }

            $results['summary'] = [
                'total_physical' => count($physicalFiles),
                'total_database' => count($databaseFiles),
                'missing_in_db' => count($results['missing_in_db']),
                'missing_physical' => count($results['missing_physical']),
                'dry_run' => $dryRun
            ];

            return response()->json($results);

        } catch (\Exception $e) {
            Log::error('Sync error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء المزامنة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create file record for database
     */
    private function createFileRecord($physicalFile, $folderName)
    {
        $mimeType = $this->getMimeType($physicalFile['extension']);
        $fileType = $this->getFileType($physicalFile['extension'], $mimeType);

        return [
            'original_file_name' => $physicalFile['name'],
            'stored_file_name' => $physicalFile['name'],
            'file_path' => "storage/uploads/{$folderName}/{$physicalFile['path']}",
            'file_size' => $physicalFile['size'],
            'file_extension' => $physicalFile['extension'],
            'mime_type' => $mimeType,
            'file_type' => $fileType,
            'record_number' => $folderName,
            'upload_method' => 'system_sync',
            'file_metadata' => json_encode([
                'synced_at' => now()->toDateTimeString(),
                'source' => 'physical_file_sync'
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Get MIME type from extension
     */
    private function getMimeType($extension)
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Get file type category
     */
    private function getFileType($extension, $mimeType)
    {
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']) ||
            strpos($mimeType, 'image/') === 0) {
            return 'image';
        }

        if ($extension === 'pdf' || strpos($mimeType, 'pdf') !== false) {
            return 'pdf';
        }

        if (in_array($extension, ['xls', 'xlsx', 'csv']) ||
            strpos($mimeType, 'spreadsheet') !== false ||
            strpos($mimeType, 'excel') !== false) {
            return 'excel';
        }

        if (in_array($extension, ['doc', 'docx']) ||
            strpos($mimeType, 'word') !== false) {
            return 'document';
        }

        return 'document';
    }
}
