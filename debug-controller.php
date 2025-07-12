<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function debugFileUpload(Request $request)
    {
        Log::info('=== DEBUG FILE UPLOAD START ===');

        // Log all request info
        Log::info('Request method: ' . $request->method());
        Log::info('Request headers: ', $request->header());
        Log::info('Request all input: ', $request->all());
        Log::info('Request files(): ', $request->file());

        // Check $_FILES directly
        Log::info('$_FILES structure: ', $_FILES ?? []);

        // Check if request has file with different names
        $hasFilesStandard = $request->hasFile('files');
        $hasFilesArray = $request->hasFile('files[]');

        Log::info('hasFile checks', [
            'hasFile("files")' => $hasFilesStandard,
            'hasFile("files[]")' => $hasFilesArray,
        ]);

        // Try to get files in different ways
        $files = [];

        if ($hasFilesStandard) {
            $files = $request->file('files');
            Log::info('Got files via hasFile("files")', [
                'type' => gettype($files),
                'count' => is_array($files) ? count($files) : 1
            ]);
        }

        if ($hasFilesArray) {
            $filesArray = $request->file('files[]');
            Log::info('Got files via hasFile("files[]")', [
                'type' => gettype($filesArray),
                'count' => is_array($filesArray) ? count($filesArray) : 1
            ]);
        }

        // Manual $_FILES processing
        if (isset($_FILES['files'])) {
            Log::info('$_FILES["files"] exists', $_FILES['files']);

            if (is_array($_FILES['files']['name'])) {
                Log::info('$_FILES["files"]["name"] is array with ' . count($_FILES['files']['name']) . ' items');

                for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                    Log::info("File $i details", [
                        'name' => $_FILES['files']['name'][$i],
                        'type' => $_FILES['files']['type'][$i],
                        'size' => $_FILES['files']['size'][$i],
                        'error' => $_FILES['files']['error'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    ]);
                }
            } else {
                Log::info('$_FILES["files"]["name"] is single file', [
                    'name' => $_FILES['files']['name'],
                    'type' => $_FILES['files']['type'],
                    'size' => $_FILES['files']['size'],
                    'error' => $_FILES['files']['error'],
                    'tmp_name' => $_FILES['files']['tmp_name'],
                ]);
            }
        }

        Log::info('=== DEBUG FILE UPLOAD END ===');

        return response()->json([
            'debug' => true,
            'message' => 'Debug info logged',
            'files_found' => !empty($files),
            'files_count' => is_array($files) ? count($files) : (empty($files) ? 0 : 1),
            'request_has_files' => $hasFilesStandard,
            'request_has_files_array' => $hasFilesArray,
            'FILES_structure' => $_FILES ?? []
        ]);
    }
}
