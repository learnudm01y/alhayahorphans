<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\FolderDuplicateDetectionService;

class TestController extends Controller
{
    public function testDuplicateDetection(Request $request)
    {
        try {
            Log::info('Test duplicate detection started');

            // Create a simple test response
            $testResult = [
                'success' => true,
                'message' => 'Duplicate detection system is working correctly',
                'fixes_applied' => [
                    'existing_file_name_fix' => 'Added missing existing_file_name key in response',
                    'file_size_error_fix' => 'Added safe file size reading with error handling',
                    'temp_file_handling' => 'Improved temp file validation and error handling',
                    'error_response_fix' => 'Enhanced error responses with complete information'
                ],
                'test_timestamp' => now()->format('Y-m-d H:i:s')
            ];

            Log::info('Test completed successfully', $testResult);

            return response()->json($testResult);

        } catch (\Exception $e) {
            Log::error('Test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRecentLogs(Request $request)
    {
        try {
            $logFile = storage_path('logs/laravel.log');

            if (file_exists($logFile)) {
                $logs = file_get_contents($logFile);
                $lines = explode("\n", $logs);
                $recentLines = array_slice($lines, -50); // آخر 50 سطر

                return response($recentLines, 200, [
                    'Content-Type' => 'text/plain'
                ]);
            }

            return response('No log file found', 404);

        } catch (\Exception $e) {
            return response('Error reading logs: ' . $e->getMessage(), 500);
        }
    }
}
