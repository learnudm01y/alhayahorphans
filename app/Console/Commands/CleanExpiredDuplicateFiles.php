<?php

namespace App\Console\Commands;

use App\Services\DuplicateFileDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanExpiredDuplicateFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'duplicates:clean-expired
                            {--force : Force deletion without confirmation}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired duplicate files from temporary storage';

    protected $duplicateDetectionService;

    public function __construct(DuplicateFileDetectionService $duplicateDetectionService)
    {
        parent::__construct();
        $this->duplicateDetectionService = $duplicateDetectionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        $this->info('🧹 بدء تنظيف الملفات المكررة منتهية الصلاحية...');

        if ($isDryRun) {
            $this->warn('⚠️ وضع المحاكاة مفعل - لن يتم حذف أي ملفات فعلياً');
        }

        try {
            // Get information about what will be cleaned
            $stats = $this->getExpiredStats();

            if ($stats['expired_records'] === 0) {
                $this->info('✅ لا توجد ملفات مكررة منتهية الصلاحية للتنظيف');
                return 0;
            }

            $this->displayCleanupStats($stats);

            // Ask for confirmation unless forced or dry run
            if (!$isDryRun && !$isForced) {
                if (!$this->confirm('هل أنت متأكد من المتابعة؟')) {
                    $this->info('❌ تم إلغاء العملية');
                    return 0;
                }
            }

            if (!$isDryRun) {
                // Perform actual cleanup
                $result = $this->duplicateDetectionService->cleanExpiredFiles();
                $this->displayCleanupResults($result);

                Log::info('Expired duplicate files cleaned via command', $result);
            } else {
                $this->info('💡 في الوضع العادي، سيتم حذف الملفات المذكورة أعلاه');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ حدث خطأ أثناء تنظيف الملفات: ' . $e->getMessage());
            Log::error('Error in CleanExpiredDuplicateFiles command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Get statistics about expired files
     */
    private function getExpiredStats(): array
    {
        $expiredRecords = \App\Models\DuplicateFileTemp::where('expires_at', '<', now())->get();

        $stats = [
            'expired_records' => $expiredRecords->count(),
            'total_size' => 0,
            'sessions' => [],
            'file_types' => []
        ];

        foreach ($expiredRecords as $record) {
            // Calculate total size
            if (file_exists($record->temp_path)) {
                $stats['total_size'] += filesize($record->temp_path);
            }

            // Count by session
            if (!isset($stats['sessions'][$record->session_id])) {
                $stats['sessions'][$record->session_id] = 0;
            }
            $stats['sessions'][$record->session_id]++;

            // Count by file type
            $extension = pathinfo($record->original_name, PATHINFO_EXTENSION);
            if (!isset($stats['file_types'][$extension])) {
                $stats['file_types'][$extension] = 0;
            }
            $stats['file_types'][$extension]++;
        }

        return $stats;
    }

    /**
     * Display cleanup statistics
     */
    private function displayCleanupStats(array $stats): void
    {
        $this->info("📊 إحصائيات الملفات منتهية الصلاحية:");
        $this->line("   • عدد السجلات: {$stats['expired_records']}");
        $this->line("   • الحجم الإجمالي: " . $this->formatBytes($stats['total_size']));
        $this->line("   • عدد الجلسات: " . count($stats['sessions']));

        if (!empty($stats['sessions'])) {
            $this->line("\n🔍 توزيع الجلسات:");
            foreach ($stats['sessions'] as $sessionId => $count) {
                $this->line("   • {$sessionId}: {$count} ملف");
            }
        }

        if (!empty($stats['file_types'])) {
            $this->line("\n📁 أنواع الملفات:");
            foreach ($stats['file_types'] as $type => $count) {
                $this->line("   • {$type}: {$count} ملف");
            }
        }
    }

    /**
     * Display cleanup results
     */
    private function displayCleanupResults(array $result): void
    {
        $this->info("\n✅ نتائج التنظيف:");
        $this->line("   • سجلات محذوفة: {$result['deleted_records']}");
        $this->line("   • ملفات محذوفة: {$result['deleted_files']}");

        if (!empty($result['errors'])) {
            $this->warn("\n⚠️ أخطاء حدثت أثناء التنظيف:");
            foreach ($result['errors'] as $error) {
                $this->line("   • " . ($error['duplicate_id'] ?? 'unknown') . ": " . $error['error']);
            }
        } else {
            $this->info("✅ تم التنظيف بنجاح بدون أخطاء");
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
