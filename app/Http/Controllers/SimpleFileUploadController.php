<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SimpleFileUploadController extends Controller
{
    /**
     * Simple file upload for testing
     */
    public function simpleUpload(Request $request)
    {
        Log::info('Simple upload started', [
            'files_count' => $request->hasFile('files') ? count($request->file('files')) : 0,
            'record_number' => $request->input('record_number'),
        ]);

        try {
            $request->validate([
                'files.*' => 'required|file|max:512000', // 500MB max للفيديوهات
                'record_number' => 'required|string|max:20',
                'person_id' => 'nullable|string|max:20',
            ]);

            $results = [];
            $recordNumber = $request->input('record_number');
            $personId = $request->input('person_id');

            foreach ($request->file('files') as $index => $file) {
                try {
                    // Simple file storage
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('uploads/simple', $fileName, 'public');

                    $results[] = [
                        'success' => true,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $fileName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getMimeType(),
                        'record_number' => $recordNumber,
                        'person_id' => $personId
                    ];

                    Log::info('File uploaded successfully', [
                        'original_name' => $file->getClientOriginalName(),
                        'stored_path' => $filePath,
                        'size' => $file->getSize()
                    ]);

                } catch (\Exception $e) {
                    Log::error('Individual file upload failed', [
                        'file' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);

                    $results[] = [
                        'success' => false,
                        'original_name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
                'results' => $results,
                'total_files' => count($results),
                'successful_uploads' => count(array_filter($results, fn($r) => $r['success']))
            ]);

        } catch (\Exception $e) {
            Log::error('Simple upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Test endpoint
     */
    public function test()
    {
        return response()->json([
            'success' => true,
            'message' => 'Simple file upload controller is working',
            'timestamp' => now()->toISOString()
        ]);
    }
}
