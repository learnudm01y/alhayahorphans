<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Sponsorship;
use App\Models\Sponsor;
use App\Models\Attachment;
use App\Services\ExportStatus;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Job لتصدير استمارات التحديث (وإرفاقاتها) بشكل جماعي.
 *
 * يعمل بنظام الدفعات المتتابعة بدل Job واحد طويل:
 *
 *  1) وضع المنسّق (يُنشأ من الواجهة كما كان: بدون runId):
 *     يتحقق من الإعدادات، يجلب معرّفات الكفالات، يقسمها إلى دفعات (افتراضياً 250)،
 *     يحفظ حالة التشغيل في ملف state.json داخل مجلد عمل، ثم يُطلق الدفعة الأولى.
 *
 *  2) وضع الدفعة (runId + chunkIds):
 *     يعالج دفعة واحدة: نسخ المرفقات ورفعها بعملية rclone واحدة ثم توليد الاستمارات،
 *     ثم يُطلق الدفعة التالية؛ والأخيرة تبني تقرير الإكسل وتُنهي التصدير.
 *
 * الفوائد: كل Job قصير (دقائق) فلا يتجاوز المهلة ولا retry_after، وتتخلله مهام أخرى في الطابور،
 * وكل دفعة تُعاد تلقائياً عند الفشل (آمنة التكرار: rclone copy يتخطى ما رُفع).
 */
class BulkExportSponsorshipForms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sponsorId;
    protected $sponsorshipStatusId;
    protected $updatedOnly;
    protected $exportAttachments;
    protected $exportForms;

    /** معرّف التشغيل (null = وضع المنسّق) */
    protected $runId;
    /** معرّفات الكفالات في هذه الدفعة */
    protected $chunkIds;
    protected $chunkIndex;
    protected $chunkCount;

    protected $batchSize = 10; // تحديث التقدّم كل 10 كفالات داخل الدفعة

    /**
     * عدد المحاولات لكل Job (دفعة). الدفعة آمنة التكرار.
     */
    public $tries = 3;

    /**
     * الانتظار قبل إعادة المحاولة (ثوانٍ).
     */
    public $backoff = 120;

    /**
     * أقصى مدة لتشغيل Job واحد (دفعة واحدة). أقل بكثير من retry_after في config/queue.php (7500).
     */
    public $timeout = 3600;

    private const DEFAULT_CHUNK_SIZE = 250;
    private const RCLONE_UPLOAD_TIMEOUT = 3000;   // رفع مرفقات دفعة واحدة
    private const RCLONE_REPORT_TIMEOUT = 600;    // رفع تقرير الإكسل
    private const RUN_LOCK_TTL = 3600;            // يُجدَّد مع كل تقدّم، فينتهي وحده لو توقف التشغيل
    private const REPORT_FOLDER_NAME = 'تقرير التصدير';

    /** كاش لنتيجة hasTable لتجنب تكرار الاستعلام لكل كفالة */
    private ?bool $hasAdditionalDeceasedTable = null;

    /**
     * @param int      $sponsorId           معرف الجمعية
     * @param int|null $sponsorshipStatusId معرف حالة الكفالة (اختياري)
     * @param string|null $runId            للاستخدام الداخلي فقط (دفعات التشغيل)
     * @param array    $chunkIds            للاستخدام الداخلي فقط
     */
    public function __construct(
        int $sponsorId,
        ?int $sponsorshipStatusId = null,
        bool $updatedOnly = false,
        bool $exportAttachments = false,
        bool $exportForms = true,
        ?string $runId = null,
        array $chunkIds = [],
        int $chunkIndex = 0,
        int $chunkCount = 0
    ) {
        $this->sponsorId = $sponsorId;
        $this->sponsorshipStatusId = $sponsorshipStatusId;
        $this->updatedOnly = $updatedOnly;
        $this->exportAttachments = $exportAttachments;
        $this->exportForms = $exportForms;
        $this->runId = $runId;
        $this->chunkIds = $chunkIds;
        $this->chunkIndex = $chunkIndex;
        $this->chunkCount = $chunkCount;
    }

    public function handle(): void
    {
        if ($this->runId === null) {
            $this->startRun();
            return;
        }

        $this->processChunk();
    }

    // ------------------------------------------------------------------
    // مفاتيح مساعدة
    // ------------------------------------------------------------------

    private function lockKey(): string
    {
        return "export_forms_{$this->sponsorId}_{$this->sponsorshipStatusId}_"
            . ($this->updatedOnly ? 'updated' : 'all') . '_'
            . ($this->exportAttachments ? 'attachments' : 'no_attachments') . '_'
            . ($this->exportForms ? 'forms' : 'no_forms');
    }

    private function statusKey(): string
    {
        return ExportStatus::key(
            ExportStatus::TYPE_FORMS,
            (int) $this->sponsorId,
            $this->sponsorshipStatusId ? (int) $this->sponsorshipStatusId : null,
            (bool) $this->updatedOnly
        );
    }

    private function phasesCount(): int
    {
        return (int) $this->exportAttachments + (int) $this->exportForms;
    }

    private function workDirFor(string $runId): string
    {
        return storage_path('app/attachments-export/' . $runId);
    }

    // ------------------------------------------------------------------
    // وضع المنسّق
    // ------------------------------------------------------------------

    private function startRun(): void
    {
        $lockKey = $this->lockKey();
        $statusKey = $this->statusKey();
        $runId = (string) Str::uuid();

        // قفل لمنع تشغيلين متطابقين؛ يُجدَّد مع كل تقدّم وينتهي وحده إن توقف التشغيل
        if (!Cache::add($lockKey, $runId, self::RUN_LOCK_TTL)) {
            Log::warning('BulkExportSponsorshipForms: Another export is already running', [
                'sponsor_id' => $this->sponsorId,
                'sponsorship_status_id' => $this->sponsorshipStatusId,
                'lock_key' => $lockKey,
            ]);
            ExportStatus::running($statusKey, ['message' => 'تصدير آخر لهذه الجمعية قيد التشغيل بالفعل']);
            return;
        }

        $dispatched = false;
        $workDir = null;

        try {
            Log::info('BulkExportSponsorshipForms: Starting bulk export', [
                'sponsor_id' => $this->sponsorId,
                'sponsorship_status_id' => $this->sponsorshipStatusId,
                'run_id' => $runId,
            ]);

            $sponsor = Sponsor::find($this->sponsorId);

            if (!$sponsor) {
                Log::error('BulkExportSponsorshipForms: Sponsor not found', ['sponsor_id' => $this->sponsorId]);
                ExportStatus::fail($statusKey, 'الجمعية غير موجودة');
                return;
            }

            $driveEnabled = (bool) $sponsor->google_drive_enabled;
            if (!$driveEnabled) {
                Log::warning('BulkExportSponsorshipForms: Google Drive not enabled for sponsor', [
                    'sponsor_id' => $this->sponsorId,
                    'sponsor_name' => $sponsor->sponsor_name,
                ]);
                // نستمر في المعالجة ولكن لن يتم الرفع إلى Google Drive
            }

            // فحص إعداد Rclone مبكراً بدل اكتشاف المشكلة بعد نسخ الملفات
            if ($driveEnabled && $this->exportAttachments) {
                $rclonePath = config('services.rclone.path', env('RCLONE_PATH'));
                $rcloneRemote = config('services.rclone.remote_name', env('RCLONE_REMOTE_NAME'));
                if (!$rclonePath || !$rcloneRemote) {
                    Log::error('BulkExportSponsorshipForms: Rclone is not configured', ['sponsor_id' => $this->sponsorId]);
                    ExportStatus::fail($statusKey, 'Rclone غير مهيأ — تعذّر رفع المرفقات إلى Google Drive.');
                    return;
                }
            }

            // استعلام الكفالات من sponsor_id الأساسي فقط (sponsorship_sponsor فيه بيانات خاطئة)
            $ids = $this->sponsorshipQuery()->orderBy('id')->pluck('id')->all();
            $totalCount = count($ids);

            Log::info('BulkExportSponsorshipForms: Total sponsorships to process', [
                'count' => $totalCount,
                'sponsor_id' => $this->sponsorId,
                'status_filter' => $this->sponsorshipStatusId,
                'updated_only' => $this->updatedOnly,
            ]);

            if ($totalCount === 0) {
                Log::info('BulkExportSponsorshipForms: No sponsorships found to export');
                ExportStatus::finish($statusKey, [
                    'total' => 0,
                    'message' => 'لا توجد كفالات مطابقة للمعايير المحددة',
                    'drive' => $driveEnabled ? 'enabled' : 'disabled',
                ]);
                return;
            }

            if ($this->phasesCount() === 0) {
                ExportStatus::finish($statusKey, [
                    'total' => 0,
                    'message' => 'لم يتم تحديد نوع التصدير (مرفقات أو استمارات)',
                    'drive' => $driveEnabled ? 'enabled' : 'disabled',
                ]);
                return;
            }

            $chunkSize = max(25, (int) config('services.export.chunk_size', self::DEFAULT_CHUNK_SIZE));
            $chunks = array_chunk($ids, $chunkSize);

            $this->purgeStaleExportDirs();

            $workDir = $this->workDirFor($runId);
            if (!is_dir($workDir) && !mkdir($workDir, 0775, true) && !is_dir($workDir)) {
                throw new \RuntimeException('تعذّر إنشاء مجلد عمل التصدير.');
            }

            $this->writeState($workDir, [
                'run_id' => $runId,
                'sponsor_id' => $this->sponsorId,
                'total' => $totalCount,
                'chunk_count' => count($chunks),
                'chunks' => $chunks,
                'counters' => ['processed' => 0, 'success' => 0, 'failed' => 0, 'attachments_exported' => 0],
                'identities' => [],
                'used_dir_names' => [],
                'done_chunks' => [],
                'dispatched' => [0],
                'failed_chunks' => [],
                'failed_attachment_chunks' => [],
                'attachments_error' => null,
                'last_error' => null,
                'finalized' => false,
            ]);

            ExportStatus::running($statusKey, [
                'total' => $totalCount * $this->phasesCount(),
                'message' => sprintf('تم تقسيم التصدير إلى %d دفعة — جارٍ البدء...', count($chunks)),
            ]);

            $this->dispatchChunk($runId, 0, $chunks[0], count($chunks));
            $dispatched = true;
        } catch (\Throwable $e) {
            Log::error('BulkExportSponsorshipForms: Critical error while starting bulk export', [
                'sponsor_id' => $this->sponsorId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($workDir !== null) {
                File::deleteDirectory($workDir);
            }
            ExportStatus::fail($statusKey, $e->getMessage());

            throw $e;
        } finally {
            if (!$dispatched) {
                // لم يبدأ أي تشغيل: نحرّر القفل
                Cache::forget($lockKey);
            }
        }
    }

    private function dispatchChunk(string $runId, int $index, array $ids, int $count): void
    {
        $job = new self(
            $this->sponsorId,
            $this->sponsorshipStatusId,
            $this->updatedOnly,
            $this->exportAttachments,
            $this->exportForms,
            $runId,
            $ids,
            $index,
            $count
        );

        // نفس الاتصال والطابور الذي أُطلق عليه التصدير الأصلي
        if ($this->queue) {
            $job->onQueue($this->queue);
        }
        if ($this->connection) {
            $job->onConnection($this->connection);
        }

        dispatch($job);
    }

    // ------------------------------------------------------------------
    // وضع الدفعة
    // ------------------------------------------------------------------

    private function processChunk(): void
    {
        $statusKey = $this->statusKey();
        $lockKey = $this->lockKey();
        $workDir = $this->workDirFor($this->runId);

        $state = $this->readState($workDir);
        if ($state === null) {
            Log::error('BulkExportSponsorshipForms: Run state is missing', [
                'run_id' => $this->runId,
                'chunk' => $this->chunkIndex + 1,
            ]);
            ExportStatus::fail($statusKey, 'فُقدت حالة التصدير (مجلد العمل غير موجود). أعد تشغيل التصدير.');
            Cache::forget($lockKey);
            return;
        }

        $sponsor = Sponsor::find($this->sponsorId);

        // تشغيل مكرر لدفعة منتهية (مثلاً إعادة محاولة بعد انقطاع): نكمل المسار فقط
        if (in_array($this->chunkIndex, $state['done_chunks'], true)) {
            Log::warning('BulkExportSponsorshipForms: Chunk already completed, continuing run', [
                'run_id' => $this->runId,
                'chunk' => $this->chunkIndex + 1,
            ]);
            $this->advance($state, $sponsor);
            return;
        }

        if (!$sponsor) {
            throw new \RuntimeException('الجمعية غير موجودة');
        }

        Cache::put($lockKey, $this->runId, self::RUN_LOCK_TTL);

        $progressTotal = (int) $state['total'] * $this->phasesCount();
        $counters = $state['counters'];

        Log::info('BulkExportSponsorshipForms: Processing chunk', [
            'run_id' => $this->runId,
            'chunk' => $this->chunkIndex + 1,
            'of' => $this->chunkCount,
            'size' => count($this->chunkIds),
        ]);

        $sponsorships = Sponsorship::whereIn('id', $this->chunkIds)->orderBy('id')->get();

        if ($this->exportAttachments) {
            $processedBefore = $counters['processed'];

            try {
                $this->exportAttachmentsForChunk(
                    $sponsorships,
                    $sponsor,
                    $statusKey,
                    $state,
                    $counters,
                    $progressTotal
                );
            } catch (\Throwable $e) {
                // فشل مرفقات هذه الدفعة لا يوقف الاستمارات ولا باقي الدفعات (إعادة التصدير تكمل ما نقص)
                $message = sprintf('الدفعة %d: %s', $this->chunkIndex + 1, $e->getMessage());
                $state['attachments_error'] = $state['attachments_error'] ?? $message;
                $state['failed_attachment_chunks'][] = $this->chunkIndex + 1;

                Log::error('BulkExportSponsorshipForms: Attachments export failed for chunk', [
                    'run_id' => $this->runId,
                    'chunk' => $this->chunkIndex + 1,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // الكفالات التي لم تُحسب قبل الخطأ تُحسب فاشلة في مرحلة المرفقات
                $remaining = max(0, $sponsorships->count() - ($counters['processed'] - $processedBefore));
                $counters['processed'] += $remaining;
                $counters['failed'] += $remaining;
            }
        }

        if ($this->exportForms) {
            foreach ($sponsorships->chunk($this->batchSize) as $batch) {
                foreach ($batch as $sponsorship) {
                    try {
                        $counters['processed']++;

                        $pdfJob = new \App\Jobs\GenerateOrphanReportPdf($sponsorship->id, $this->sponsorId);
                        $pdfJob->handle();

                        $counters['success']++;
                    } catch (\Exception $e) {
                        $counters['failed']++;

                        Log::error('BulkExportSponsorshipForms: Failed to process sponsorship', [
                            'sponsorship_id' => $sponsorship->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'failed_count' => $counters['failed'],
                        ]);
                    }
                }

                usleep(200000);

                ExportStatus::progress(
                    $statusKey,
                    $counters['processed'],
                    $counters['success'],
                    $counters['failed'],
                    $progressTotal
                );
                Cache::put($lockKey, $this->runId, self::RUN_LOCK_TTL);
            }
        }

        $state['counters'] = $counters;
        $state['done_chunks'][] = $this->chunkIndex;
        $this->writeState($workDir, $state);

        $this->advance($state, $sponsor);
    }

    /**
     * ينسخ مرفقات كفالات الدفعة إلى مجلد chunk-N ثم يرفعها بعملية rclone واحدة
     * مباشرة إلى {الجهة الداعمة}/{اسم الشخص}، ويحذف المجلد المحلي بعد النجاح.
     */
    private function exportAttachmentsForChunk(
        $sponsorships,
        Sponsor $sponsor,
        string $statusKey,
        array &$state,
        array &$counters,
        int $progressTotal
    ): void {
        $workDir = $this->workDirFor($this->runId);
        $chunkDir = $workDir . DIRECTORY_SEPARATOR . 'chunk-' . $this->chunkIndex;
        $rowsFile = $workDir . DIRECTORY_SEPARATOR . 'rows-' . $this->chunkIndex . '.jsonl';

        // تنظيف بقايا محاولة سابقة لنفس الدفعة (إعادة المحاولة آمنة)
        File::deleteDirectory($chunkDir);
        @unlink($rowsFile);

        if (!mkdir($chunkDir, 0775, true) && !is_dir($chunkDir)) {
            throw new \RuntimeException('تعذّر إنشاء مجلد مرفقات الدفعة.');
        }

        $rowsHandle = fopen($rowsFile, 'w');
        if ($rowsHandle === false) {
            throw new \RuntimeException('تعذّر إنشاء ملف صفوف التقرير.');
        }

        $documentTypeRows = DB::table('document_types')->get();
        $documentTypesById = $documentTypeRows->keyBy('id');
        $documentTypesByPref = $documentTypeRows->keyBy('pref');

        $filesExported = 0;
        $lockKey = $this->lockKey();

        try {
            foreach ($sponsorships->chunk($this->batchSize) as $batch) {
                foreach ($batch as $sponsorship) {
                    $counters['processed']++;
                    $identityNumber = trim((string) $sponsorship->identity_number);
                    $personName = trim((string) ($sponsorship->orphan_name ?? ''));
                    $exportedTypes = [];
                    $recordFailed = false;

                    if ($identityNumber === '') {
                        $recordFailed = true;
                        Log::warning('BulkExportSponsorshipForms: Attachment export skipped for missing identity number', [
                            'sponsorship_id' => $sponsorship->id,
                        ]);
                    } elseif (!isset($state['identities'][$identityNumber])) {
                        // اسم المجلد = اسم الشخص فقط؛ وعند تكرار الاسم لشخص آخر: الاسم (2)
                        // (الأسماء المستخدمة محفوظة في الحالة عبر كل الدفعات)
                        $baseDirName = $this->sanitizeExportName($personName ?: 'unknown');
                        $dirName = $baseDirName;
                        $suffix = 2;
                        while (isset($state['used_dir_names'][$dirName])) {
                            $dirName = $baseDirName . ' (' . $suffix++ . ')';
                        }
                        $state['used_dir_names'][$dirName] = true;

                        $personDir = $chunkDir . DIRECTORY_SEPARATOR . $dirName;
                        if (!mkdir($personDir, 0775, true) && !is_dir($personDir)) {
                            throw new \RuntimeException('تعذّر إنشاء مجلد مرفقات الشخص.');
                        }

                        $relatedIdentities = $this->relatedAttachmentIdentities($sponsorship);
                        $attachments = Attachment::whereIn('person_identity_number', $relatedIdentities)
                            ->orderBy('id')
                            ->get()
                            ->unique('id');

                        foreach ($attachments as $attachment) {
                            $sourcePath = $this->resolveAttachmentPath($attachment->file_path);
                            if (!$sourcePath) {
                                $recordFailed = true;
                                Log::warning('BulkExportSponsorshipForms: Attachment file is missing from public archive', [
                                    'attachment_id' => $attachment->id,
                                    'identity_number' => $identityNumber,
                                    'file_path' => $attachment->file_path,
                                ]);
                                continue;
                            }

                            $originalName = basename(str_replace('\\', '/', (string) ($attachment->stored_file_name ?: $sourcePath)));
                            $safeName = $this->sanitizeExportName($originalName);
                            $targetPath = $personDir . DIRECTORY_SEPARATOR . $attachment->id . '_' . $safeName;

                            if (!copy($sourcePath, $targetPath)) {
                                $recordFailed = true;
                                Log::warning('BulkExportSponsorshipForms: Failed to copy archived attachment', [
                                    'attachment_id' => $attachment->id,
                                    'identity_number' => $identityNumber,
                                    'source_path' => $sourcePath,
                                ]);
                                continue;
                            }

                            $documentType = $documentTypesById->get((int) $attachment->file_type)
                                ?? $documentTypesByPref->get((string) $attachment->file_type);
                            $documentTypeName = trim((string) ($documentType->description ?? ''));
                            if ($documentTypeName === '' && !is_numeric($attachment->file_type)) {
                                $documentTypeName = trim((string) $attachment->file_type);
                            }
                            if ($documentTypeName !== '') {
                                $exportedTypes[] = $documentTypeName;
                            }
                            $filesExported++;
                        }

                        // لا نترك مجلداً فارغاً لمن لا مرفقات له (rmdir تنجح فقط إن كان المجلد فارغاً)
                        @rmdir($personDir);

                        $exportedTypes = array_values(array_unique($exportedTypes));
                        $state['identities'][$identityNumber] = [
                            'types' => $exportedTypes,
                            'failed' => $recordFailed,
                        ];
                    } else {
                        // الهوية مكررة (في هذه الدفعة أو دفعة سابقة): ترث النتيجة من أول معالجة
                        $known = $state['identities'][$identityNumber];
                        $exportedTypes = $known['types'] ?? [];
                        $recordFailed = (bool) ($known['failed'] ?? false);
                    }

                    fwrite($rowsHandle, json_encode(
                        [$personName, $identityNumber, implode('، ', $exportedTypes)],
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                    ) . "\n");

                    if ($recordFailed) {
                        $counters['failed']++;
                    } else {
                        $counters['success']++;
                    }
                }

                ExportStatus::progress(
                    $statusKey,
                    $counters['processed'],
                    $counters['success'],
                    $counters['failed'],
                    $progressTotal
                );
                Cache::put($lockKey, $this->runId, self::RUN_LOCK_TTL);
            }
        } finally {
            fclose($rowsHandle);
        }

        if ($sponsor->google_drive_enabled && $filesExported > 0) {
            ExportStatus::running($statusKey, [
                'total' => $progressTotal,
                'processed' => $counters['processed'],
                'success' => $counters['success'],
                'failed' => $counters['failed'],
                'message' => sprintf(
                    'جارٍ رفع مرفقات الدفعة %d من %d إلى Google Drive...',
                    $this->chunkIndex + 1,
                    $this->chunkCount
                ),
            ]);

            // عملية rclone واحدة للدفعة مباشرة تحت {الجهة الداعمة}/
            $uploadResult = app(\App\Services\RcloneGoogleDriveService::class)->uploadTree(
                $chunkDir,
                $sponsor->sponsor_name,
                null,
                self::RCLONE_UPLOAD_TIMEOUT
            );

            if (!$uploadResult['success']) {
                throw new \RuntimeException(
                    'فشل رفع المرفقات إلى Google Drive: ' . ($uploadResult['message'] ?? 'خطأ غير معروف')
                );
            }

            // بعد نجاح الرفع نحذف النسخة المؤقتة لهذه الدفعة
            File::deleteDirectory($chunkDir);
        } elseif (!$sponsor->google_drive_enabled) {
            Log::warning('BulkExportSponsorshipForms: Google Drive disabled; attachment export remains local', [
                'sponsor_id' => $sponsor->id,
                'chunk_dir' => $chunkDir,
            ]);
        } else {
            // لا ملفات في هذه الدفعة
            File::deleteDirectory($chunkDir);
        }

        $counters['attachments_exported'] += $filesExported;
    }

    /**
     * ينتقل إلى الدفعة التالية، أو يُنهي التصدير بعد الدفعة الأخيرة.
     */
    private function advance(array $state, ?Sponsor $sponsor): void
    {
        $workDir = $this->workDirFor($this->runId);
        $next = $this->chunkIndex + 1;

        if ($next < (int) $state['chunk_count']) {
            // لا نُطلق الدفعة نفسها مرتين لو أُعيدت هذه الدفعة بعد انقطاع
            if (in_array($next, $state['dispatched'], true)) {
                return;
            }

            $this->dispatchChunk($this->runId, $next, $state['chunks'][$next], (int) $state['chunk_count']);

            $state['dispatched'][] = $next;
            $this->writeState($workDir, $state);
            return;
        }

        if (!empty($state['finalized'])) {
            return;
        }

        $this->finalizeRun($state, $sponsor);
    }

    private function finalizeRun(array $state, ?Sponsor $sponsor): void
    {
        $statusKey = $this->statusKey();
        $lockKey = $this->lockKey();
        $workDir = $this->workDirFor($this->runId);

        $driveEnabled = $sponsor ? (bool) $sponsor->google_drive_enabled : false;
        $counters = $state['counters'];
        $attachmentsError = $state['attachments_error'] ?? null;
        $reportUploaded = false;

        try {
            if ($this->exportAttachments) {
                $reportUploaded = $this->buildAndUploadReport($workDir, $state, $sponsor, $driveEnabled);
            }
        } catch (\Throwable $e) {
            $attachmentsError = $attachmentsError ?? ('تعذّر رفع تقرير المرفقات: ' . $e->getMessage());
            Log::error('BulkExportSponsorshipForms: Failed to build/upload attachments report', [
                'run_id' => $this->runId,
                'message' => $e->getMessage(),
            ]);
        }

        $state['finalized'] = true;
        try {
            $this->writeState($workDir, $state);
        } catch (\Throwable $e) {
            Log::warning('BulkExportSponsorshipForms: Could not persist final state', ['message' => $e->getMessage()]);
        }

        // الملفات المحلية تُحذف فقط عند اكتمال رفعها كلها؛ وإلا تبقى لمراجعتها (وتُنظَّف تلقائياً بعد 3 أيام)
        $keepLocalFiles = $this->exportAttachments
            && (!$driveEnabled || !empty($state['failed_attachment_chunks']) || !$reportUploaded);
        if (!$keepLocalFiles) {
            $this->cleanupWorkDir($workDir);
        }

        $totalAll = (int) $state['total'] * $this->phasesCount();

        Log::info('BulkExportSponsorshipForms: Bulk export completed', [
            'run_id' => $this->runId,
            'sponsor_id' => $this->sponsorId,
            'total' => (int) $state['total'],
            'processed' => $counters['processed'],
            'success' => $counters['success'],
            'failed' => $counters['failed'],
            'chunks' => (int) $state['chunk_count'],
            'failed_chunks' => $state['failed_chunks'],
            'updated_only' => $this->updatedOnly,
            'export_attachments' => $this->exportAttachments,
            'export_forms' => $this->exportForms,
            'attachments_exported' => $counters['attachments_exported'],
            'attachments_error' => $attachmentsError,
        ]);

        ExportStatus::finish($statusKey, [
            'total' => $totalAll,
            'processed' => $counters['processed'],
            'success' => $counters['success'],
            'failed' => $counters['failed'],
            'attachments_exported' => $counters['attachments_exported'],
            'attachments_error' => $attachmentsError,
            'drive' => $driveEnabled ? 'enabled' : 'disabled',
            'message' => sprintf(
                'اكتمل التصدير: %d سجل ناجح، %d سجل فاشل',
                $counters['success'],
                $counters['failed']
            ) . ($driveEnabled ? '' : ' — Google Drive غير مفعّل لهذه الجمعية، الملفات محفوظة محلياً')
              . ($attachmentsError ? ' — تعذّر تصدير المرفقات: ' . $attachmentsError : '')
              . (!empty($state['failed_chunks'])
                    ? sprintf(' — تعذّر إكمال %d دفعة', count($state['failed_chunks']))
                    : ''),
        ]);

        Cache::forget($lockKey);
    }

    /**
     * يبني attachments_export.xlsx من ملفات الصفوف ويرفعه إلى {الجهة}/تقرير التصدير/.
     *
     * @return bool هل رُفع التقرير إلى الدرايف
     */
    private function buildAndUploadReport(string $workDir, array $state, ?Sponsor $sponsor, bool $driveEnabled): bool
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('المرفقات المصدرة');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(['الاسم', 'رقم الهوية', 'نوع المرفق'], null, 'A1');

        $rowNumber = 2;
        for ($i = 0; $i < (int) $state['chunk_count']; $i++) {
            $rowsFile = $workDir . DIRECTORY_SEPARATOR . 'rows-' . $i . '.jsonl';
            if (!is_file($rowsFile)) {
                continue;
            }

            $handle = fopen($rowsFile, 'r');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $sheet->fromArray($row, null, 'A' . $rowNumber);
                    $rowNumber++;
                }
            }
            fclose($handle);
        }

        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $reportPath = $workDir . DIRECTORY_SEPARATOR . 'attachments_export.xlsx';
        (new Xlsx($spreadsheet))->save($reportPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (!$driveEnabled || !$sponsor) {
            return false;
        }

        // مجلد مؤقت يحوي "تقرير التصدير/attachments_export.xlsx" فيُرفع تحت مجلد الجهة مباشرة
        $reportRoot = $workDir . DIRECTORY_SEPARATOR . '_report';
        $reportDir = $reportRoot . DIRECTORY_SEPARATOR . self::REPORT_FOLDER_NAME;
        File::deleteDirectory($reportRoot);
        if (!mkdir($reportDir, 0775, true) && !is_dir($reportDir)) {
            throw new \RuntimeException('تعذّر إنشاء مجلد تقرير التصدير.');
        }
        copy($reportPath, $reportDir . DIRECTORY_SEPARATOR . basename($reportPath));

        $result = app(\App\Services\RcloneGoogleDriveService::class)->uploadTree(
            $reportRoot,
            $sponsor->sponsor_name,
            null,
            self::RCLONE_REPORT_TIMEOUT
        );

        if (!$result['success']) {
            throw new \RuntimeException('فشل رفع تقرير المرفقات: ' . ($result['message'] ?? 'خطأ غير معروف'));
        }

        return true;
    }

    // ------------------------------------------------------------------
    // حالة التشغيل (ملف state.json)
    // ------------------------------------------------------------------

    private function readState(string $workDir): ?array
    {
        $file = $workDir . DIRECTORY_SEPARATOR . 'state.json';
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    private function writeState(string $workDir, array $state): void
    {
        $file = $workDir . DIRECTORY_SEPARATOR . 'state.json';
        $tmp = $file . '.tmp';

        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)) {
            throw new \RuntimeException('تعذّر حفظ حالة التصدير.');
        }
    }

    /** يحذف محتويات مجلد العمل ويُبقي state.json فقط (لتفادي اعتبار دفعة متأخرة «حالة مفقودة») */
    private function cleanupWorkDir(string $workDir): void
    {
        if (!is_dir($workDir)) {
            return;
        }

        foreach (File::directories($workDir) as $dir) {
            File::deleteDirectory($dir);
        }
        foreach (File::files($workDir) as $file) {
            if ($file->getFilename() !== 'state.json') {
                @unlink($file->getPathname());
            }
        }
    }

    // ------------------------------------------------------------------
    // استعلام الكفالات ودوال مساعدة
    // ------------------------------------------------------------------

    private function sponsorshipQuery()
    {
        $query = Sponsorship::where('sponsor_id', $this->sponsorId);

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

        return $query;
    }

    private function relatedAttachmentIdentities(Sponsorship $sponsorship): array
    {
        $identities = array_filter([
            trim((string) $sponsorship->identity_number),
            trim((string) $sponsorship->guardian_identity_number),
        ]);

        $fileNumbers = array_values(array_unique(array_filter([
            trim((string) $sponsorship->relation_id_number),
            trim((string) $sponsorship->internal_file_number),
        ])));

        if ($fileNumbers !== []) {
            $identities = array_merge(
                $identities,
                DB::table('data')
                    ->whereIn('file_id_number', $fileNumbers)
                    ->whereNotNull('data_id_number')
                    ->pluck('data_id_number')
                    ->map(fn ($identity) => trim((string) $identity))
                    ->all()
            );

            $deadPeople = DB::table('dead_people')->whereIn('re_file_id', $fileNumbers)->get(['father_id', 'mother_id']);
            foreach ($deadPeople as $deceased) {
                $identities[] = trim((string) ($deceased->father_id ?? ''));
                $identities[] = trim((string) ($deceased->mother_id ?? ''));
            }

            if ($this->hasAdditionalDeceasedTable ??= DB::getSchemaBuilder()->hasTable('additional_deceased')) {
                $identities = array_merge(
                    $identities,
                    DB::table('additional_deceased')
                        ->whereIn('re_file_id', $fileNumbers)
                        ->whereNotNull('person_id')
                        ->pluck('person_id')
                        ->map(fn ($identity) => trim((string) $identity))
                        ->all()
                );
            }
        }

        return array_values(array_unique(array_filter($identities, function ($identity) {
            return $identity !== '';
        })));
    }

    private function resolveAttachmentPath(?string $filePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', (string) $filePath), '/');

        if (str_starts_with($relativePath, 'storage/app/public/')) {
            $relativePath = substr($relativePath, strlen('storage/app/public/'));
        } elseif (str_starts_with($relativePath, 'storage/')) {
            $relativePath = substr($relativePath, strlen('storage/'));
        } elseif (str_starts_with($relativePath, 'public/')) {
            $relativePath = substr($relativePath, strlen('public/'));
        }

        if ($relativePath === '' || str_contains($relativePath, '../')) {
            return null;
        }

        $disk = Storage::disk('public');
        return $disk->exists($relativePath) ? $disk->path($relativePath) : null;
    }

    /**
     * حذف مجلدات التصدير المؤقتة القديمة (أكثر من 3 أيام) التي تُركت من تشغيلات فاشلة أو سابقة.
     */
    private function purgeStaleExportDirs(): void
    {
        $base = storage_path('app/attachments-export');
        if (!is_dir($base)) {
            return;
        }

        $threshold = time() - (3 * 24 * 60 * 60);
        foreach (File::directories($base) as $dir) {
            if (@filemtime($dir) !== false && filemtime($dir) < $threshold) {
                File::deleteDirectory($dir);
            }
        }
    }

    private function sanitizeExportName(string $name): string
    {
        $name = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '_', $name);
        $name = trim((string) $name, '. ');

        return $name !== '' ? $name : 'unknown';
    }

    // ------------------------------------------------------------------
    // الفشل النهائي
    // ------------------------------------------------------------------

    /**
     * يُستدعى بعد استنفاد كل المحاولات.
     * - المنسّق: يُفشل الحالة ويحرّر القفل.
     * - الدفعة: تُسجَّل كفاشلة ويستمر التصدير بالدفعة التالية (أو الإنهاء إن كانت الأخيرة).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BulkExportSponsorshipForms: Job failed after all retries', [
            'run_id' => $this->runId,
            'chunk' => $this->runId === null ? null : $this->chunkIndex + 1,
            'sponsor_id' => $this->sponsorId,
            'sponsorship_status_id' => $this->sponsorshipStatusId,
            'updated_only' => $this->updatedOnly,
            'export_attachments' => $this->exportAttachments,
            'export_forms' => $this->exportForms,
            'error' => $exception->getMessage(),
        ]);

        $statusKey = $this->statusKey();
        $lockKey = $this->lockKey();

        if ($this->runId === null) {
            ExportStatus::fail($statusKey, $exception->getMessage());
            Cache::forget($lockKey);
            return;
        }

        try {
            $workDir = $this->workDirFor($this->runId);
            $state = $this->readState($workDir);

            if ($state === null) {
                ExportStatus::fail($statusKey, $exception->getMessage());
                Cache::forget($lockKey);
                return;
            }

            if (in_array($this->chunkIndex, $state['done_chunks'], true)) {
                // الدفعة اكتملت فعلاً قبل هذا الفشل (مثلاً فشل إطلاق التالية): نكمل المسار فقط
                $this->advance($state, Sponsor::find($this->sponsorId));
                return;
            }

            $perChunk = count($this->chunkIds) * $this->phasesCount();
            $state['counters']['processed'] += $perChunk;
            $state['counters']['failed'] += $perChunk;
            $state['failed_chunks'][] = $this->chunkIndex + 1;
            $state['last_error'] = $exception->getMessage();
            $state['done_chunks'][] = $this->chunkIndex;
            $this->writeState($workDir, $state);

            ExportStatus::progress(
                $statusKey,
                $state['counters']['processed'],
                $state['counters']['success'],
                $state['counters']['failed'],
                (int) $state['total'] * $this->phasesCount()
            );

            $this->advance($state, Sponsor::find($this->sponsorId));
        } catch (\Throwable $e) {
            Log::error('BulkExportSponsorshipForms: Could not continue run after chunk failure', [
                'run_id' => $this->runId,
                'message' => $e->getMessage(),
            ]);
            ExportStatus::fail($statusKey, $exception->getMessage());
            Cache::forget($lockKey);
        }
    }
}
