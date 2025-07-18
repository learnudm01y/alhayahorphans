<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SpeedTestController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the speed test interface
     */
    public function index()
    {
        return view('admin.speedtest.index');
    }

    /**
     * Handle speed test API requests
     */
    public function api(Request $request)
    {
        $endpoint = $request->input('endpoint', 'empty');

        switch ($endpoint) {
            case 'empty':
                return $this->handleEmpty($request);
            case 'garbage':
                return $this->handleGarbage($request);
            case 'getIP':
                return $this->handleGetIP($request);
            default:
                return response()->json(['error' => 'Invalid endpoint'], 400);
        }
    }

    /**
     * Handle empty.php functionality for ping/upload tests
     */
    private function handleEmpty(Request $request)
    {
        // Set headers for ping/upload tests
        $headers = [
            'HTTP/1.1 200 OK',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST',
            'Access-Control-Allow-Headers' => 'Content-Encoding, Content-Type',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, s-maxage=0',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive'
        ];

        return response('', 200, $headers);
    }

    /**
     * Handle garbage.php functionality for download tests
     */
    private function handleGarbage(Request $request)
    {
        // Disable compression
        ini_set('zlib.output_compression', 'Off');
        ini_set('output_buffering', 'Off');
        ini_set('output_handler', '');

        $chunkCount = $this->getChunkCount($request);
        $chunkSize = 1048576; // 1MB chunks

        // Set headers for download test
        $headers = [
            'HTTP/1.1 200 OK',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST',
            'Access-Control-Allow-Headers' => 'Content-Encoding, Content-Type',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, s-maxage=0',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Type' => 'application/octet-stream'
        ];

        // Generate garbage data
        $garbage = $this->generateGarbageData($chunkSize);

        return response()->streamDownload(function() use ($garbage, $chunkCount) {
            for ($i = 0; $i < $chunkCount; $i++) {
                echo $garbage;
                flush();
            }
        }, 'garbage.bin', $headers);
    }

    /**
     * Handle getIP.php functionality
     */
    private function handleGetIP(Request $request)
    {
        $clientIP = $this->getClientIP($request);
        $ispInfo = $this->getIspInfo($clientIP);

        $result = [
            'processedString' => $this->formatProcessedString($clientIP, $ispInfo),
            'rawIspInfo' => $ispInfo
        ];

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, s-maxage=0',
            'Content-Type' => 'application/json'
        ];

        return response()->json($result, 200, $headers);
    }

    /**
     * Get chunk count from request
     */
    private function getChunkCount(Request $request)
    {
        $ckSize = $request->input('ckSize', 4);

        if (!is_numeric($ckSize) || $ckSize <= 0) {
            return 4;
        }

        if ($ckSize > 1024) {
            return 1024;
        }

        return (int) $ckSize;
    }

    /**
     * Generate garbage data for download test
     */
    private function generateGarbageData($size)
    {
        $chunk = '';
        for ($i = 0; $i < $size; $i++) {
            $chunk .= chr(mt_rand(0, 255));
        }
        return $chunk;
    }

    /**
     * Get client IP address
     */
    private function getClientIP(Request $request)
    {
        // Check for IP from various headers
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->ip();
    }

    /**
     * Get ISP information for IP
     */
    private function getIspInfo($ip)
    {
        // Basic ISP detection (can be enhanced with external services)
        $ispInfo = [
            'ip' => $ip,
            'country' => 'Unknown',
            'region' => 'Unknown',
            'city' => 'Unknown',
            'isp' => 'Unknown',
            'asn' => 'Unknown'
        ];

        // Try to get basic country info from IP
        try {
            $countryCode = $this->getCountryFromIP($ip);
            if ($countryCode) {
                $ispInfo['country'] = $countryCode;
            }
        } catch (\Exception $e) {
            // Log error but continue
        }

        return $ispInfo;
    }

    /**
     * Get country from IP (basic implementation)
     */
    private function getCountryFromIP($ip)
    {
        // This is a basic implementation
        // In production, you might want to use a GeoIP database
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Iraqi IP ranges (example)
            $ipLong = ip2long($ip);

            // Basic Iraqi IP ranges detection
            $iraqRanges = [
                ['37.75.0.0', '37.75.255.255'],
                ['37.236.0.0', '37.236.255.255'],
                ['94.34.0.0', '94.34.255.255'],
                ['149.255.0.0', '149.255.255.255'],
                ['185.60.0.0', '185.60.255.255']
            ];

            foreach ($iraqRanges as $range) {
                if ($ipLong >= ip2long($range[0]) && $ipLong <= ip2long($range[1])) {
                    return 'IQ';
                }
            }
        }

        return 'Unknown';
    }

    /**
     * Format processed string for display
     */
    private function formatProcessedString($ip, $ispInfo)
    {
        $parts = [];

        if ($ip) {
            $parts[] = $ip;
        }

        if ($ispInfo['country'] !== 'Unknown') {
            $parts[] = $ispInfo['country'];
        }

        if ($ispInfo['isp'] !== 'Unknown') {
            $parts[] = $ispInfo['isp'];
        }

        return implode(' - ', $parts);
    }

    /**
     * Get speed test statistics
     */
    public function stats()
    {
        // This can be enhanced to show usage statistics
        return response()->json([
            'status' => 'active',
            'version' => '1.0.0',
            'server_time' => now()->toDateTimeString(),
            'server_location' => 'Local Server'
        ]);
    }

    /**
     * Display OpenSpeedTest interface
     */
    public function openSpeedTest()
    {
        return view('admin.speedtest.openspeedtest');
    }

    /**
     * Handle OpenSpeedTest download requests
     */
    public function download(Request $request)
    {
        try {
            // توليد بيانات عشوائية للاختبار
            $size = $request->get('size', 10); // MB
            $size = max(1, min($size, 100)); // بين 1-100 MB

            $chunkSize = 1024 * 1024; // 1MB chunks
            $totalChunks = (int) $size;

            $headers = [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
                'Content-Disposition' => 'attachment; filename="speedtest-download.bin"'
            ];

            return response()->stream(function() use ($chunkSize, $totalChunks) {
                // إنشاء chunk واحد من البيانات العشوائية
                $chunk = str_repeat('0', $chunkSize);

                for ($i = 0; $i < $totalChunks; $i++) {
                    echo $chunk;
                    flush();

                    // تجنب timeout
                    if (connection_aborted()) {
                        break;
                    }
                }
            }, 200, $headers);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('خطأ في OpenSpeedTest download: ' . $e->getMessage());

            return response()->json([
                'error' => 'فشل في اختبار التحميل',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle OpenSpeedTest upload requests
     */
    public function upload(Request $request)
    {
        // Just acknowledge the upload without storing
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache'
        ]);
    }

    /**
     * Handle OpenSpeedTest getIP requests
     */
    public function getIP(Request $request)
    {
        return response()->json([
            'ip' => $request->ip(),
            'hostname' => $request->getHost(),
            'country' => 'Local',
            'isp' => 'Local Server'
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate'
        ]);
    }

    /**
     * Handle OpenSpeedTest status requests
     */
    public function status(Request $request)
    {
        return response()->json([
            'status' => 'online',
            'server' => 'Laravel OpenSpeedTest',
            'version' => '1.0.0',
            'timestamp' => now()->toIsoString()
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate'
        ]);
    }
}
