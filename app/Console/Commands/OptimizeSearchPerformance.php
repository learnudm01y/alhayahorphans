<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;

class OptimizeSearchPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:optimize
                            {--analyze : تحليل الجداول وتحديث الإحصائيات}
                            {--clear-cache : مسح ذاكرة التخزين المؤقت للبحث}
                            {--rebuild-indexes : إعادة بناء الفهارس}
                            {--full : تنفيذ جميع عمليات التحسين}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تحسين أداء البحث في النظام';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 بدء عملية تحسين أداء البحث...');

        if ($this->option('full')) {
            $this->optimizeAll();
        } else {
            if ($this->option('analyze')) {
                $this->analyzeTables();
            }

            if ($this->option('clear-cache')) {
                $this->clearSearchCache();
            }

            if ($this->option('rebuild-indexes')) {
                $this->rebuildIndexes();
            }
        }

        $this->info('✅ اكتملت عملية تحسين أداء البحث بنجاح!');
    }

    /**
     * تنفيذ جميع عمليات التحسين
     */
    private function optimizeAll()
    {
        $this->analyzeTables();
        $this->clearSearchCache();
        $this->rebuildIndexes();
        $this->generateSearchStatistics();
    }

    /**
     * تحليل الجداول وتحديث الإحصائيات
     */
    private function analyzeTables()
    {
        $this->info('📊 تحليل الجداول...');

        $tables = ['data', 're_people', 'dead_people'];

        foreach ($tables as $table) {
            try {
                $this->line("تحليل جدول: {$table}");

                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ANALYZE TABLE {$table}");
                    $this->info("✓ تم تحليل جدول {$table}");
                } else {
                    $this->warn("تحليل الجداول مدعوم فقط في MySQL");
                }

            } catch (\Exception $e) {
                $this->error("خطأ في تحليل جدول {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * مسح ذاكرة التخزين المؤقت للبحث
     */
    private function clearSearchCache()
    {
        $this->info('🧹 مسح ذاكرة التخزين المؤقت...');

        try {
            // مسح جميع مفاتيح البحث
            $cacheKeys = [
                'search_*',
                'search_statistics',
                'search_suggestions_*'
            ];

            foreach ($cacheKeys as $pattern) {
                Cache::flush(); // مسح الكل مؤقتاً
            }

            $this->info('✓ تم مسح ذاكرة التخزين المؤقت');
        } catch (\Exception $e) {
            $this->error('خطأ في مسح ذاكرة التخزين المؤقت: ' . $e->getMessage());
        }
    }

    /**
     * إعادة بناء الفهارس
     */
    private function rebuildIndexes()
    {
        $this->info('🔧 إعادة بناء الفهارس...');

        if (DB::getDriverName() !== 'mysql') {
            $this->warn('إعادة بناء الفهارس مدعومة فقط في MySQL');
            return;
        }

        $tables = ['data', 're_people', 'dead_people'];

        foreach ($tables as $table) {
            try {
                $this->line("إعادة بناء فهارس جدول: {$table}");

                // الحصول على قائمة الفهارس
                $indexes = DB::select("SHOW INDEX FROM {$table}");
                $indexNames = collect($indexes)->pluck('Key_name')->unique()->reject(function($name) {
                    return $name === 'PRIMARY';
                });

                foreach ($indexNames as $indexName) {
                    DB::statement("ALTER TABLE {$table} DISABLE KEYS");
                    DB::statement("ALTER TABLE {$table} ENABLE KEYS");
                }

                $this->info("✓ تم إعادة بناء فهارس جدول {$table}");

            } catch (\Exception $e) {
                $this->error("خطأ في إعادة بناء فهارس جدول {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * توليد إحصائيات البحث
     */
    private function generateSearchStatistics()
    {
        $this->info('📈 توليد إحصائيات البحث...');

        try {
            $stats = [
                'main_records' => Data::count(),
                'family_members' => RePeople::count(),
                'deceased_records' => DeadPepole::count(),
                'last_updated' => now()
            ];

            $stats['total_records'] = $stats['main_records'] +
                                     $stats['family_members'] +
                                     $stats['deceased_records'];

            // حفظ الإحصائيات في الكاش
            Cache::put('search_statistics', $stats, 60 * 60); // ساعة واحدة

            // عرض الإحصائيات
            $this->table(
                ['نوع السجل', 'العدد'],
                [
                    ['السجلات الرئيسية', number_format($stats['main_records'])],
                    ['أفراد الأسرة', number_format($stats['family_members'])],
                    ['سجلات المتوفين', number_format($stats['deceased_records'])],
                    ['الإجمالي', number_format($stats['total_records'])]
                ]
            );

            $this->info('✓ تم توليد إحصائيات البحث');

        } catch (\Exception $e) {
            $this->error('خطأ في توليد الإحصائيات: ' . $e->getMessage());
        }
    }

    /**
     * فحص أداء الاستعلامات
     */
    private function checkQueryPerformance()
    {
        $this->info('⚡ فحص أداء الاستعلامات...');

        try {
            // استعلام تجريبي لقياس الأداء
            $startTime = microtime(true);

            $testQueries = [
                "SELECT COUNT(*) FROM data WHERE data_first_name LIKE '%أحمد%'",
                "SELECT COUNT(*) FROM re_people WHERE person_id LIKE '%123%'",
                "SELECT COUNT(*) FROM dead_people WHERE father_id LIKE '%456%'"
            ];

            foreach ($testQueries as $query) {
                $queryStart = microtime(true);
                DB::select($query);
                $queryTime = (microtime(true) - $queryStart) * 1000;

                $this->line(sprintf("استعلام: %s - الوقت: %.2f ms",
                    substr($query, 0, 50) . '...', $queryTime));
            }

            $totalTime = (microtime(true) - $startTime) * 1000;
            $this->info(sprintf("إجمالي وقت الاختبار: %.2f ms", $totalTime));

        } catch (\Exception $e) {
            $this->error('خطأ في فحص الأداء: ' . $e->getMessage());
        }
    }

    /**
     * نصائح لتحسين الأداء
     */
    private function showPerformanceTips()
    {
        $this->info('💡 نصائح لتحسين الأداء:');

        $tips = [
            'استخدم البحث المحدد بدلاً من البحث العام عند الإمكان',
            'قم بتشغيل هذا الأمر دورياً (مرة أسبوعياً مثلاً)',
            'راقب استخدام ذاكرة التخزين المؤقت',
            'تأكد من وجود فهارس على الحقول المستخدمة في البحث',
            'استخدم LIMIT في الاستعلامات الكبيرة',
            'فعّل slow query log في MySQL لمراقبة الاستعلامات البطيئة'
        ];

        foreach ($tips as $index => $tip) {
            $this->line(($index + 1) . ". {$tip}");
        }
    }
}
