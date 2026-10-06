<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Sponsorship;
use App\Models\Sponsor;
use App\Services\ExportStatus;

/**
 * Job لتصدير استمارات التحديث بشكل جماعي
 *
 * يقوم بمعالجة الاستمارات على دفعات (batches) لتجنب استنزاف الذاكرة
 * ورفعها إلى Google Drive باستخدام Rclone
 */
class BulkExportSponsorshipForms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sponsorId;
    protected $sponsorshipStatusId;
    protected $updatedOnly;
    protected $batchSize = 10; // معالجة 10 استمارات في كل دفعة
    protected $offset = 0; // رقم البداية للمعالجة
    protected $processInSingleJob = true; // معالجة كل الملفات في job واحد

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1; // محاولة واحدة فقط لتجنب التكرار

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 120;

    /**
     * The maximum number of seconds the job should run.
     */
    public $timeout = 7200; // ساعتين للسماح بمعالجة عدد كبير من الملفات

    /**
     * Create a new job instance.
     *
     * @param int $sponsorId معرف الجمعية
     * @param int|null $sponsorshipStatusId معرف حالة الكفالة (اختياري)
     */
    public function __construct(int $sponsorId, ?int $sponsorshipStatusId = null, bool $updatedOnly = false)
    {
        $this->sponsorId = $sponsorId;
        $this->sponsorshipStatusId = $sponsorshipStatusId;
        $this->updatedOnly = $updatedOnly;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // إنشاء مفتاح قفل فريد لهذه العملية
        $lockKey = "export_forms_{$this->sponsorId}_{$this->sponsorshipStatusId}_" . ($this->updatedOnly ? 'updated' : 'all');

        // مفتاح حالة التصدير لعرض التقدّم في الواجهة
        $statusKey = ExportStatus::key(
            ExportStatus::TYPE_FORMS,
            (int) $this->sponsorId,
            $this->sponsorshipStatusId ? (int) $this->sponsorshipStatusId : null,
            (bool) $this->updatedOnly
        );

        // محاولة الحصول على القفل (timeout: 7200 ثانية = 2 ساعة)
        $lock = Cache::lock($lockKey, 7200);

        if (!$lock->get()) {
            Log::warning('BulkExportSponsorshipForms: Another export is already running', [
                'sponsor_id' => $this->sponsorId,
                'sponsorship_status_id' => $this->sponsorshipStatusId,
                'lock_key' => $lockKey
            ]);
            ExportStatus::running($statusKey, ['message' => 'تصدير آخر لهذه الجمعية قيد التشغيل بالفعل']);
            return; // عملية أخرى قيد التنفيذ، نتجاهل هذه المحاولة
        }

        try {
            Log::info('BulkExportSponsorshipForms: Starting bulk export', [
                'sponsor_id' => $this->sponsorId,
                'sponsorship_status_id' => $this->sponsorshipStatusId,
                'lock_key' => $lockKey
            ]);

            // التحقق من وجود الجمعية
            $sponsor = Sponsor::find($this->sponsorId);

            if (!$sponsor) {
                Log::error('BulkExportSponsorshipForms: Sponsor not found', [
                    'sponsor_id' => $this->sponsorId
                ]);
                ExportStatus::fail($statusKey, 'الجمعية غير موجودة');
                return;
            }

            // التحقق من تفعيل Google Drive للجمعية
            $driveEnabled = (bool) $sponsor->google_drive_enabled;
            if (!$driveEnabled) {
                Log::warning('BulkExportSponsorshipForms: Google Drive not enabled for sponsor', [
                    'sponsor_id' => $this->sponsorId,
                    'sponsor_name' => $sponsor->sponsor_name
                ]);
                // نستمر في المعالجة ولكن لن يتم الرفع إلى Google Drive
            }

            // ✅ FIX: بناء استعلام الكفالات من sponsor_id الأساسي فقط
            // السبب: جدول sponsorship_sponsor كان يحتوي على بيانات خاطئة
            // الحل: استخدام sponsor_id المباشر (الصحيح دائماً)
            $query = Sponsorship::where('sponsor_id', $this->sponsorId);

            // فلترة حسب حالة الكفالة إذا تم تحديدها
            if ($this->sponsorshipStatusId) {
                $query->where('sponsorship_status_id', $this->sponsorshipStatusId);
            }

            if ($this->updatedOnly) {
                $query->where(function ($q) {
                    $q->whereExists(function ($sub) {
                        $sub->select('id')
                            ->from('portal_general_registration_field_values as pgv')
                            ->whereColumn('pgv.sponsorship_id', 'sponsorships.id');
                    })->orWhereExists(function ($sub) {
                        $sub->select('id')
                            ->from('portal_general_registration_field_values as pgv')
                            ->whereColumn('pgv.identity_number', 'sponsorships.identity_number');
                    });
                });
            }

            // حساب إجمالي عدد الكفالات
            $totalCount = $query->count();

            Log::info('BulkExportSponsorshipForms: Total sponsorships to process', [
                'count' => $totalCount,
                'sponsor_id' => $this->sponsorId,
                'status_filter' => $this->sponsorshipStatusId,
                'updated_only' => $this->updatedOnly
            ]);

            if ($totalCount === 0) {
                Log::info('BulkExportSponsorshipForms: No sponsorships found to export');
                ExportStatus::finish($statusKey, [
                    'total'   => 0,
                    'message' => 'لا توجد كفالات مطابقة للمعايير المحددة',
                    'drive'   => $driveEnabled ? 'enabled' : 'disabled',
                ]);
                return;
            }

            ExportStatus::running($statusKey, [
                'total'   => $totalCount,
                'message' => 'جارٍ معالجة الاستمارات...',
            ]);

            // معالجة الكفالات على دفعات (بدون dispatch منفصل لكل ملف)
            $processedCount = 0;
            $successCount = 0;
            $failedCount = 0;

            // معالجة جميع الملفات في نفس الـ job (بدلاً من إنشاء jobs منفصلة)
            $query->chunk($this->batchSize, function ($sponsorships) use (&$processedCount, &$successCount, &$failedCount, $totalCount, $statusKey) {
                foreach ($sponsorships as $sponsorship) {
                    try {
                        $processedCount++;

                        Log::info('BulkExportSponsorshipForms: Processing sponsorship', [
                            'sponsorship_id' => $sponsorship->id,
                            'sponsor_id' => $this->sponsorId,
                            'progress' => "{$processedCount}/{$totalCount}"
                        ]);

                        // معالجة الاستمارة مباشرة في نفس الـ job (بدون dispatch)
                        $pdfJob = new \App\Jobs\GenerateOrphanReportPdf($sponsorship->id, $this->sponsorId);
                        $pdfJob->handle();

                        $successCount++;

                        Log::info('BulkExportSponsorshipForms: Sponsorship processed successfully', [
                            'sponsorship_id' => $sponsorship->id,
                            'success_count' => $successCount
                        ]);

                    } catch (\Exception $e) {
                        $failedCount++;

                        Log::error('BulkExportSponsorshipForms: Failed to process sponsorship', [
                            'sponsorship_id' => $sponsorship->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'failed_count' => $failedCount
                        ]);
                        // نستمر في معالجة باقي الملفات حتى لو فشل أحدها
                    }
                }

                // إضافة تأخير صغير بين الدفعات لتجنب الضغط على النظام
                usleep(200000); // 0.2 ثانية

                // تحديث حالة التقدّم لعرضها في الواجهة
                ExportStatus::progress($statusKey, $processedCount, $successCount, $failedCount, $totalCount);
            });

            Log::info('BulkExportSponsorshipForms: Bulk export completed', [
                'sponsor_id' => $this->sponsorId,
                'total' => $totalCount,
                'processed' => $processedCount,
                'success' => $successCount,
                'failed' => $failedCount,
                'updated_only' => $this->updatedOnly
            ]);

            ExportStatus::finish($statusKey, [
                'total'     => $totalCount,
                'processed' => $processedCount,
                'success'   => $successCount,
                'failed'    => $failedCount,
                'drive'     => $driveEnabled ? 'enabled' : 'disabled',
                'message'   => sprintf(
                    'اكتملت المعالجة: %d نجح، %d فشل من %d',
                    $successCount,
                    $failedCount,
                    $totalCount
                ) . ($driveEnabled ? '' : ' — Google Drive غير مفعّل لهذه الجمعية، الملفات محفوظة محلياً'),
            ]);

        } catch (\Exception $e) {
            Log::error('BulkExportSponsorshipForms: Critical error during bulk export', [
                'sponsor_id' => $this->sponsorId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            ExportStatus::fail($statusKey, $e->getMessage());

            throw $e; // Re-throw to mark job as failed
        } finally {
            // تحرير القفل في جميع الأحوال
            $lock->release();
            Log::info('BulkExportSponsorshipForms: Lock released', [
                'lock_key' => $lockKey
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BulkExportSponsorshipForms: Job failed after all retries', [
            'sponsor_id' => $this->sponsorId,
            'sponsorship_status_id' => $this->sponsorshipStatusId,
            'updated_only' => $this->updatedOnly,
            'error' => $exception->getMessage()
        ]);

        ExportStatus::fail(
            ExportStatus::key(
                ExportStatus::TYPE_FORMS,
                (int) $this->sponsorId,
                $this->sponsorshipStatusId ? (int) $this->sponsorshipStatusId : null,
                (bool) $this->updatedOnly
            ),
            $exception->getMessage()
        );

        // يمكن إضافة إشعار للمسؤول هنا
    }
}
