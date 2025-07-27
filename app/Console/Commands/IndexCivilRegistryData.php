<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CivilRegistryPerson;
use App\Services\CivilRegistryScoutSearchService;

class IndexCivilRegistryData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'civil-registry:index
                           {--chunk=1000 : حجم الدفعة للفهرسة}
                           {--force : فرض إعادة الفهرسة}';

    /**
     * The console command description.
     */
    protected $description = 'فهرسة بيانات السجل المدني لمحرك البحث Scout';

    protected $searchService;

    public function __construct(CivilRegistryScoutSearchService $searchService)
    {
        parent::__construct();
        $this->searchService = $searchService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('بدء فهرسة بيانات السجل المدني...');

        $chunkSize = (int) $this->option('chunk');
        $force = $this->option('force');

        if ($force) {
            $this->warn('سيتم حذف الفهرس الحالي وإعادة إنشاءه...');

            if (!$this->confirm('هل أنت متأكد من المتابعة؟')) {
                $this->info('تم إلغاء العملية');
                return;
            }
        }

        // الحصول على إحصائيات قاعدة البيانات
        $this->info('جاري الحصول على إحصائيات قاعدة البيانات...');
        $stats = $this->searchService->getDatabaseStats();

        if (!$stats['success']) {
            $this->error('فشل في الاتصال بقاعدة البيانات: ' . $stats['error']);
            return 1;
        }

        $this->table(
            ['المقياس', 'القيمة'],
            [
                ['إجمالي السجلات', number_format($stats['total_records'])],
                ['السجلات الحية', number_format($stats['alive_records'])],
                ['السجلات المتوفاة', number_format($stats['deceased_records'])],
                ['الذكور', number_format($stats['male_records'])],
                ['الإناث', number_format($stats['female_records'])],
                ['حالة الاتصال', $stats['connection_status']],
            ]
        );

        $totalRecords = $stats['total_records'];

        if ($totalRecords == 0) {
            $this->warn('لا توجد سجلات للفهرسة');
            return 0;
        }

        $this->info("بدء فهرسة {$totalRecords} سجل بحجم دفعة {$chunkSize}...");

        // إنشاء شريط التقدم
        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->setFormat('verbose');

        $processedRecords = 0;
        $skippedRecords = 0;
        $errorRecords = 0;

        try {
            // فهرسة البيانات على دفعات
            CivilRegistryPerson::chunk($chunkSize, function ($persons) use (&$processedRecords, &$skippedRecords, &$errorRecords, $progressBar, $force) {
                foreach ($persons as $person) {
                    try {
                        if ($person->shouldBeSearchable() || $force) {
                            $person->searchable();
                            $processedRecords++;
                        } else {
                            $skippedRecords++;
                        }
                        $progressBar->advance();
                    } catch (\Exception $e) {
                        $errorRecords++;
                        $this->warn("خطأ في فهرسة السجل {$person->ID}: " . $e->getMessage());
                        $progressBar->advance();
                    }
                }

                // إظهار التقدم كل 10000 سجل
                if (($processedRecords + $skippedRecords + $errorRecords) % 10000 == 0) {
                    $this->newLine();
                    $this->info("تمت معالجة " . number_format($processedRecords + $skippedRecords + $errorRecords) . " سجل...");
                }
            });

            $progressBar->finish();
            $this->newLine(2);

            // عرض النتائج
            $this->info('تمت عملية الفهرسة بنجاح!');

            $this->table(
                ['النتيجة', 'العدد'],
                [
                    ['السجلات المفهرسة', number_format($processedRecords)],
                    ['السجلات المتجاهلة', number_format($skippedRecords)],
                    ['السجلات التي بها أخطاء', number_format($errorRecords)],
                    ['إجمالي السجلات المعالجة', number_format($processedRecords + $skippedRecords + $errorRecords)],
                ]
            );

            // اختبار البحث
            $this->info('اختبار محرك البحث...');
            $testResult = $this->searchService->testConnection();

            if ($testResult['success']) {
                $this->info('✅ محرك البحث يعمل بشكل صحيح');
                $this->info('وقت الاستجابة: ' . $testResult['total_execution_time']);
            } else {
                $this->error('❌ فشل في اختبار محرك البحث: ' . $testResult['error']);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('حدث خطأ أثناء الفهرسة: ' . $e->getMessage());
            return 1;
        }
    }
}
