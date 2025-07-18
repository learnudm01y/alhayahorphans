<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SpeedTestController extends Controller
{
    private $speedtestPath;
    private $timeout;
    private $maxAttempts;
    private $cacheEnabled;
    private $cacheDuration;

    public function __construct()
    {
        // إعدادات للإنتاج
        $this->speedtestPath = $this->getSpeedtestPath();
        $this->timeout = config('speedtest.timeout', 120);
        $this->maxAttempts = config('speedtest.max_attempts', 3);
        $this->cacheEnabled = config('speedtest.cache_results', true);
        $this->cacheDuration = config('speedtest.cache_duration', 300); // 5 minutes
    }

    /**
     * البحث عن مسار Speedtest CLI
     */
    private function getSpeedtestPath()
    {
        $paths = [
            '/usr/local/bin/speedtest-safe',
            '/usr/bin/speedtest',
            '/usr/local/bin/speedtest',
            'speedtest' // Windows PATH
        ];

        foreach ($paths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * التحقق من وجود الأمر
     */
    private function commandExists($command)
    {
        try {
            $process = new Process(['which', $command]);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            try {
                $process = new Process([$command, '--version']);
                $process->setTimeout(5);
                $process->run();
                return $process->isSuccessful();
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function run(Request $request)
    {
        try {
            // التحقق من الـ cache أولاً
            if ($this->cacheEnabled) {
                $cacheKey = 'speedtest_' . md5(request()->ip());
                $cachedResult = Cache::get($cacheKey);
                if ($cachedResult) {
                    $cachedResult['cached'] = true;
                    return response()->json($cachedResult);
                }
            }

            // محاولة تشغيل Speedtest CLI
            $result = $this->runSpeedtestCLI();

            if ($result) {
                // حفظ النتيجة في الـ cache
                if ($this->cacheEnabled) {
                    Cache::put($cacheKey, $result, $this->cacheDuration);
                }

                return response()->json($result);
            } else {
                // استخدام النظام الاحتياطي
                return response()->json($this->getFallbackData());
            }

        } catch (\Exception $e) {
            Log::error('Speedtest error: ' . $e->getMessage());
            return response()->json($this->getFallbackData());
        }
    }

    /**
     * تشغيل Speedtest CLI الحقيقي
     */
    private function runSpeedtestCLI()
    {
        if (!$this->speedtestPath) {
            return false;
        }

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $process = new Process([
                    $this->speedtestPath,
                    '--format=json',
                    '--accept-license',
                    '--accept-gdpr'
                ]);

                $process->setTimeout($this->timeout);
                $process->run();

                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $data = json_decode($output, true);

                    if ($data && isset($data['download']) && isset($data['upload'])) {
                        return [
                            'success' => true,
                            'download_mbps' => round(($data['download']['bandwidth'] * 8) / 1_000_000, 2),
                            'upload_mbps' => round(($data['upload']['bandwidth'] * 8) / 1_000_000, 2),
                            'ping_ms' => round($data['ping']['latency'] ?? 0, 2),
                            'server' => $data['server']['name'] ?? 'Unknown Server',
                            'location' => $data['server']['location'] ?? 'Unknown Location',
                            'timestamp' => $data['timestamp'] ?? now()->toISOString(),
                            'isp' => $data['isp'] ?? 'Unknown ISP',
                            'external_ip' => $data['interface']['externalIp'] ?? request()->ip(),
                            'attempt' => $attempt,
                            'cached' => false,
                            'raw_data' => $data
                        ];
                    }
                }

                Log::warning("Speedtest attempt {$attempt} failed: " . $process->getErrorOutput());

            } catch (\Exception $e) {
                Log::error("Speedtest attempt {$attempt} exception: " . $e->getMessage());

                if ($attempt < $this->maxAttempts) {
                    sleep(2); // انتظار قبل المحاولة التالية
                }
            }
        }

        return false;
    }

    /**
     * بيانات احتياطية محسنة
     */
    private function getFallbackData()
    {
        // محاكاة أكثر واقعية بناءً على الوقت والموقع
        $baseDownload = 30;
        $baseUpload = 10;
        $basePing = 20;

        // تغيير بسيط بناءً على الوقت
        $timeVariation = sin(time() / 3600) * 10;
        $randomVariation = (rand(-1000, 1000) / 100);

        return [
            'success' => false,
            'message' => 'Speedtest CLI not available - using simulation',
            'fallback_data' => [
                'download_mbps' => round($baseDownload + $timeVariation + $randomVariation, 2),
                'upload_mbps' => round($baseUpload + ($timeVariation * 0.3) + ($randomVariation * 0.5), 2),
                'ping_ms' => round($basePing + abs($randomVariation), 2),
                'server' => 'Simulation Server',
                'location' => 'Cairo, Egypt',
                'timestamp' => now()->toISOString(),
                'isp' => 'Simulated ISP',
                'external_ip' => request()->ip(),
                'simulated' => true
            ]
        ];
    }

    /**
     * فحص توفر Speedtest CLI
     */
    public function checkAvailability()
    {
        try {
            if (!$this->speedtestPath) {
                return response()->json([
                    'available' => false,
                    'message' => 'Speedtest CLI not found in system PATH',
                    'searched_paths' => [
                        '/usr/local/bin/speedtest-safe',
                        '/usr/bin/speedtest',
                        '/usr/local/bin/speedtest',
                        'speedtest (Windows PATH)'
                    ]
                ]);
            }

            $process = new Process([$this->speedtestPath, '--version']);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                $version = trim($process->getOutput());

                return response()->json([
                    'available' => true,
                    'version' => $version,
                    'path' => $this->speedtestPath,
                    'timeout' => $this->timeout,
                    'max_attempts' => $this->maxAttempts,
                    'cache_enabled' => $this->cacheEnabled
                ]);
            } else {
                return response()->json([
                    'available' => false,
                    'error' => 'Speedtest CLI found but not working',
                    'path' => $this->speedtestPath,
                    'stderr' => $process->getErrorOutput()
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => $e->getMessage(),
                'path' => $this->speedtestPath
            ]);
        }
    }

    /**
     * مسح الـ cache
     */
    public function clearCache()
    {
        if ($this->cacheEnabled) {
            $cacheKey = 'speedtest_' . md5(request()->ip());
            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Cache not enabled'
        ]);
    }
}
