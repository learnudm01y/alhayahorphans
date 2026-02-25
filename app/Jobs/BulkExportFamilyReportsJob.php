<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Data;
use App\Models\Sponsorship;
use App\Models\Sponsor;

/**
 * Job لتصدير التقارير الشاملة (Family Reports) بشكل جماعي عبر Chromium.
 *
 * المنطق:
 * - تصفية الأشخاص بناءً على re_people.sponsorship_status (جميع أفراد الموقع)
 * - استخدام تصميم الجمعية المحددة للـ PDF (وليس اشكال الكفالة التابعة لها)
 * - رفع مجلد العملية إلى Google Drive الخاص بتلك الجمعية
 *
 * المخرجات:
 * - مجلد مؤقت باسم العملية أثناء المعالجة
 * - ملف PDF لكل أسرة
 * - ملف Excel (CSV) يحتوي جميع بيانات المصدرين
 * - رفع المجلد كاملاً إلى Google Drive ثم حذفه محلياً
 */
class BulkExportFamilyReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int  $sponsorId;
    protected ?int $sponsorshipStatusId;
    protected int  $batchSize = 50;

    public $tries   = 1;
    public $timeout = 7200;

    private array $lookups     = [];
    private array $familyCache = [];

    public function __construct(
        int  $sponsorId,
        ?int $sponsorshipStatusId = null
    ) {
        $this->sponsorId           = $sponsorId;
        $this->sponsorshipStatusId = $sponsorshipStatusId;
    }

    public function handle(): void
    {
        $lockKey = "bulk_family_reports_{$this->sponsorId}_{$this->sponsorshipStatusId}";
        $lock    = Cache::lock($lockKey, 7200);

        if (!$lock->get()) {
            Log::warning('BulkExportFamilyReportsJob: already running', ['sponsor_id' => $this->sponsorId]);
            return;
        }

        try {
            $sponsor = Sponsor::find($this->sponsorId);
            if (!$sponsor) {
                Log::error('BulkExportFamilyReportsJob: Sponsor not found', ['sponsor_id' => $this->sponsorId]);
                return;
            }

            $statusName = 'جميع_الحالات';
            if ($this->sponsorshipStatusId) {
                $statusName = DB::table('sponsorship_statuses')
                    ->where('id', $this->sponsorshipStatusId)
                    ->value('description') ?? "حالة_{$this->sponsorshipStatusId}";
            }

            $operationName     = $this->buildOperationName($sponsor->sponsor_name, $statusName);
            $safeOperationName = $this->sanitizeForFilesystem($operationName);
            $outputDir         = storage_path("app/family-reports/{$safeOperationName}");
            if (!is_dir($outputDir)) { @mkdir($outputDir, 0775, true); }

            $reportDesign  = \App\Models\SponsorReportDesign::where('sponsor_id', $this->sponsorId)->first();
            $this->lookups = $this->preloadLookups();

            $success   = 0;
            $failed    = 0;
            $excelRows = [];

            // ===========================================================
            // أفراد re_people
            // ===========================================================
            $persons = DB::table('re_people')
                ->whereNotNull('sponsorship_status')
                ->whereNotNull('person_id')
                ->whereNotNull('registration_id')
                ->when($this->sponsorshipStatusId, fn($q) => $q->where('sponsorship_status', $this->sponsorshipStatusId))
                ->select('id', 'person_id', 'registration_id',
                         'first_name', 'second_name', 'third_name', 'last_name',
                         'person_birth_date', 'person_age', 'person_gender',
                         'person_health_status', 'person_note', 'sponsorship_status')
                ->get();

            Log::info('BulkExportFamilyReportsJob: Starting', [
                'design_sponsor'  => $sponsor->sponsor_name,
                'status'          => $statusName,
                'total_re_people' => $persons->count(),
                'dir'             => $outputDir,
            ]);

            foreach ($persons->chunk($this->batchSize) as $batch) {
                $this->familyCache = [];
                $batchFileIds = $batch->pluck('registration_id')->unique()->filter()->values();
                $batchFamilies = Data::with([
                    'city', 'province', 'attachments',
                    'rePeople.healthStatus', 'rePeople.guaranteeType',
                    'rePeople.sponsorshipStatus', 'rePeople.attachments',
                    'maritalStatus', 'academicQualification', 'displacementStatus',
                    'employmentStatusBreadwinner', 'housingStatus', 'currentHousingType',
                ])->whereIn('file_id_number', $batchFileIds)->get()->keyBy('file_id_number');

                foreach ($batchFamilies as $fid => $rec) {
                    $this->familyCache[(string)$fid] = $rec;
                }

                foreach ($batch as $person) {
                    try {
                        $row = $this->processSinglePerson($person, $reportDesign, $outputDir);
                        if ($row) { $excelRows[] = $row; }
                        $success++;
                        if ($success % 100 === 0) {
                            Log::info("BulkExport: {$success} processed so far");
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('BulkExport: failed', ['person_id' => $person->person_id, 'error' => $e->getMessage()]);
                    }
                }
                usleep(200000);
            }

            // توليد CSV حتى لو بعض الأشخاص فشلوا — نُدرج ما نجح
            $excelPath = null;
            if (!empty($excelRows)) {
                $excelPath = $this->generateExcel($excelRows, $outputDir, $safeOperationName);
            } else {
                Log::warning('BulkExport: no Excel rows collected — CSV will not be generated', [
                    'success' => $success, 'failed' => $failed,
                ]);
            }

            Log::info('BulkExport: PDFs+Excel done', [
                'success' => $success, 'failed' => $failed,
                'excel'   => $excelPath, 'dir' => $outputDir,
            ]);

            $this->uploadToGoogleDrive($sponsor, $outputDir, $safeOperationName, $success);

        } catch (\Throwable $e) {
            Log::error('BulkExportFamilyReportsJob: Critical', ['sponsor_id' => $this->sponsorId, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function buildOperationName(string $sponsorName, string $statusName): string
    {
        return "{$sponsorName} - {$statusName} - " . now()->format('Y-m-d_H-i');
    }

    private function sanitizeForFilesystem(string $name): string
    {
        // إزالة الرموز غير المسموحة في أسماء المجلدات فقط (< > : " / \ | ? *)
        $name = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $name);
        // تنظيف المسافات المتعددة
        $name = preg_replace('/\s{2,}/', ' ', trim($name));
        return $name ?: 'عملية_' . time();
    }

    /**
     * تحميل جميع جداول الـ lookup مرة واحدة قبل الحلقة.
     * النتيجة: array مفاتيحه أسماء الجداول وقيمه collections مفهرسة بـ id.
     */
    private function preloadLookups(): array
    {
        return [
            'sponsorship_statuses'  => DB::table('sponsorship_statuses')->pluck('description', 'id'),
            'city'                  => DB::table('city')->pluck('city', 'id'),
            'provinces'             => DB::table('provinces')->pluck('description', 'id'),
            'displacement_statuses' => DB::table('displacement_statuses')->pluck('description', 'id'),
            'academic_degrees'      => DB::table('academic_degrees')->pluck('description', 'id'),
            'employment'            => DB::table('employment')->pluck('description', 'id'),
            'housing_status'        => DB::table('housing_status')->pluck('description', 'id'),
            'type_of_accommodation' => DB::table('type_of_accommodation')->pluck('description', 'id'),
            'marital_status'        => DB::table('marital_status')->pluck('description', 'id'),
            'health_statuses'       => DB::table('health_statuses')->pluck('description', 'id'),
        ];
    }

    /** جلب قيمة نصية من الـ lookup المحمَّل مسبقاً */
    private function lu(string $table, $id): string
    {
        if ($id === null || $id === '') return '';
        return (string) ($this->lookups[$table][$id] ?? $id);
    }

    /** ترجمة رقم الجنس إلى نص */
    private function resolveGender($value): string
    {
        return match ((string) $value) {
            '1' => 'ذكر',
            '2' => 'أنثى',
            default => (string) ($value ?? ''),
        };
    }

    /**
     * معالجة شخص واحد وتوليد PDF + صف CSV.
     */
    protected function processSinglePerson(object $person, $reportDesign, string $outputDir): ?array
    {
        $fileId = (string) $person->registration_id;

        // استخدام كاش الأسرة (تم تحميله مسبقاً لكل الـ batch)
        $data = $this->familyCache[$fileId] ?? null;

        if (!$data) {
            $data = Data::with([
                'city', 'province', 'attachments',
                'rePeople.healthStatus', 'rePeople.guaranteeType',
                'rePeople.sponsorshipStatus', 'rePeople.attachments',
            ])->where('file_id_number', $fileId)->first();
        }

        if (!$data) {
            Log::warning('BulkExport: data not found', [
                'person_id' => $person->person_id,
                'file_id'   => $fileId,
            ]);
            return null;
        }

        $allFamilyMembers = $data->rePeople ?? collect();

        if ($allFamilyMembers->isEmpty()) {
            Log::warning('BulkExport: no re_people for file_id', ['file_id' => $fileId]);
            return null;
        }

        $selectedMember = $allFamilyMembers->firstWhere('person_id', (string) $person->person_id)
                          ?? $allFamilyMembers->first();

        $familyMembers = collect([$selectedMember])->merge(
            $allFamilyMembers->filter(fn($m) => $m->id !== $selectedMember->id)
        );

        foreach ($familyMembers as $member) {
            $member->is_sponsored = Sponsorship::where('identity_number', $member->person_id)->exists();
        }
        $data->is_sponsored = Sponsorship::where('identity_number', $data->data_id_number)->exists();

        $deadPeople = \App\Models\DeadPepole::with(['fatherDeathReason', 'motherDeathReason'])
            ->where('re_file_id', $data->file_id_number)->first();

        $liveMother   = null;
        $motherStatus = \App\Models\PortalGeneralRegistrationFieldValue
            ::where('file_id_number', $data->file_id_number)->where('field_key', 'field_mother_status')->value('field_value');
        $deadMotherId = $deadPeople?->mother_id;

        if (!$deadMotherId && in_array(trim((string)$motherStatus), ['حية', 'على قيد الحياة'], true)) {
            $livingMotherFields = \App\Models\PortalGeneralRegistrationFieldValue
                ::where('file_id_number', $data->file_id_number)
                ->whereIn('field_key', [
                    'field_living_mother_id', 'field_living_mother_first_name', 'field_living_mother_second_name',
                    'field_living_mother_third_name', 'field_living_mother_last_name', 'field_living_mother_birth_date',
                    'field_living_mother_health_status', 'field_living_mother_phone',
                ])->get()->keyBy('field_key');
            if ($livingMotherFields->isNotEmpty()) {
                $liveMother = (object)[
                    'person_id'         => $livingMotherFields->get('field_living_mother_id')?->field_value,
                    'first_name'        => $livingMotherFields->get('field_living_mother_first_name')?->field_value,
                    'second_name'       => $livingMotherFields->get('field_living_mother_second_name')?->field_value,
                    'third_name'        => $livingMotherFields->get('field_living_mother_third_name')?->field_value,
                    'last_name'         => $livingMotherFields->get('field_living_mother_last_name')?->field_value,
                    'person_birth_date' => $livingMotherFields->get('field_living_mother_birth_date')?->field_value,
                    'phone'             => $livingMotherFields->get('field_living_mother_phone')?->field_value,
                    'health_status'     => $livingMotherFields->get('field_living_mother_health_status')?->field_value,
                ];
            }
        }

        $allAttachments = collect($data->attachments ?? []);
        foreach ($familyMembers as $member) { $allAttachments = $allAttachments->merge($member->attachments ?? []); }
        $documentTypes = DB::table('document_types')->get()->keyBy('pref');
        $personalPhotos = collect(); $otherDocuments = collect();
        foreach ($allAttachments as $attachment) {
            $isPersonalPhoto = false;
            $docType = $documentTypes->get($attachment->file_type);
            if ($docType) { $isPersonalPhoto = str_contains(strtolower($docType->description ?? ''), 'صور شخصية') || $attachment->file_type == '12'; }
            if (!$isPersonalPhoto && $attachment->stored_file_name) { $isPersonalPhoto = str_starts_with(strtolower($attachment->stored_file_name), '12_'); }
            $isPersonalPhoto ? $personalPhotos->push($attachment) : $otherDocuments->push($attachment);
        }
        $documentImages = $allAttachments->filter(fn($a) => Str::endsWith(strtolower($a->stored_file_name ?? ''), ['jpg','jpeg','png','gif']));

        $backgroundPath   = public_path('background102.jpg');
        $backgroundBase64 = file_exists($backgroundPath) ? base64_encode(file_get_contents($backgroundPath)) : '';
        $customDesign = null;
        if ($reportDesign) {
            $customDesign = [
                'background_type' => $reportDesign->background_type,
                'theme_colors'    => $reportDesign->theme_colors ?? ['primary'=>'#1a1a1a','secondary'=>'#4a4a4a','accent'=>'#007bff'],
                'single_image'    => $reportDesign->single_image_base64,
                'header_image'    => $reportDesign->header_image_base64,
                'main_image'      => $reportDesign->main_image_base64,
                'footer_image'    => $reportDesign->footer_image_base64,
            ];
        }

        $viewData = [
            'guardian'           => $data,
            'selectedMember'     => $selectedMember,
            'familyMembers'      => $familyMembers,
            'deadPeople'         => $deadPeople,
            'liveMother'         => $liveMother,
            'documentImages'     => $documentImages,
            'personalPhotos'     => $personalPhotos,
            'otherDocuments'     => $otherDocuments,
            'computedDependents' => $familyMembers->count(),
            'backgroundBase64'   => $backgroundBase64,
            'customDesign'       => $customDesign,
        ];

        // اسم الملف: تقرير_{person_id}.pdf — فريد لكل شخص
        $safePersonId = preg_replace('/[^\w\-]/', '_', (string) $person->person_id);
        $fileName = "تقرير_{$safePersonId}.pdf";
        $this->generatePdf($viewData, $outputDir . DIRECTORY_SEPARATOR . $fileName);

        return $this->buildExcelRow($data, $person, $familyMembers);
    }

    private function generatePdf(array $viewData, string $filePath): void
    {
        $bc = 'Spatie\\Browsershot\\Browsershot';
        if (!class_exists($bc)) { throw new \RuntimeException('Browsershot not installed.'); }
        @mkdir(storage_path('app/chromium-home'), 0775, true);
        @mkdir(storage_path('app/puppeteer-cache'), 0775, true);
        putenv('HOME=' . storage_path('app/chromium-home'));
        putenv('PUPPETEER_CACHE_DIR=' . storage_path('app/puppeteer-cache'));

        $html = view('admin.dashboard.reports.family_report', $viewData)->render();
        $b = $bc::html($html)->format('A4')->margins(0,0,0,0)->showBackground()->emulateMedia('print');
        if (is_executable('/usr/bin/node')) { $b->setNodeBinary('/usr/bin/node'); }
        if (is_executable('/usr/bin/npm'))  { $b->setNpmBinary('/usr/bin/npm'); }
        if (config('app.env') === 'production' || env('BROWSERSHOT_NO_SANDBOX', false)) { $b->noSandbox(); }
        foreach (array_filter([(string)env('BROWSERSHOT_CHROME_PATH',''), (string)env('PUPPETEER_EXECUTABLE_PATH',''), '/usr/bin/google-chrome-stable', '/usr/bin/chromium-browser', '/usr/bin/chromium']) as $c) {
            if (!str_contains($c, '/snap/bin/chromium') && is_executable($c)) { $b->setChromePath($c); break; }
        }
        $b->save($filePath);
    }

    /**
     * بناء صف Excel لشخص واحد.
     *
     * الترتيب: بيانات فرد العائلة أولاً → ثم بيانات ولي الأمر/المعيل في النهاية.
     * يتم تمييز نوع الشخص عبر عمود «العلاقة»: «معيل / ولي أمر» أو «فرد عائلة».
     * يتم تمييز الأحياء عن المتوفين عبر عمود «حالة_الشخص».
     *
     * @param Data   $data          سجل ولي الأمر (جدول data)
     * @param object $person        سجل re_people
     * @param mixed  $familyMembers أفراد الأسرة
     */
    private function buildExcelRow(Data $data, object $person, $familyMembers): array
    {
        $fullName = implode(' ', array_filter([
            $person->first_name  ?? '',
            $person->second_name ?? '',
            $person->third_name  ?? '',
            $person->last_name   ?? '',
        ]));

        $guardianFullName = implode(' ', array_filter([
            $data->data_first_name,
            $data->data_father_name,
            $data->data_grand_father_name,
            $data->data_family_name,
        ]));

        // هل هذا الشخص هو المعيل (ولي الأمر) نفسه؟
        $isGuardian = (string)($person->person_id ?? '') === (string)$data->data_id_number;

        // عدد المكفولين في الأسرة
        $sponsoredCount = $familyMembers->filter(fn($m) => !empty($m->sponsorship_status))->count();

        return [
            // ==========================================================
            // القسم الأول: بيانات فرد العائلة
            // ==========================================================
            'رقم_الملف'                     => $data->file_id_number,
            'حالة_الشخص'                    => 'حي',
            'العلاقة'                       => $isGuardian ? 'معيل / ولي أمر' : 'فرد عائلة',
            'رقم_هوية_فرد_العائلة'          => $person->person_id          ?? '',
            'الاسم_الكامل_لفرد_العائلة'     => $fullName,
            'تاريخ_ميلاد_فرد_العائلة'       => $person->person_birth_date   ?? '',
            'عمر_فرد_العائلة'               => $person->person_age           ?? '',
            'جنس_فرد_العائلة'               => $this->resolveGender($person->person_gender ?? ''),
            'الحالة_الصحية_لفرد_العائلة'    => $this->lu('health_statuses', $person->person_health_status ?? ''),
            'حالة_الكفالة_لفرد_العائلة'     => $this->lu('sponsorship_statuses', $person->sponsorship_status ?? ''),
            'ملاحظات_فرد_العائلة'           => $person->person_note          ?? '',

            // ==========================================================
            // القسم الثاني: بيانات ولي الأمر / المعيل
            // ==========================================================
            'رقم_هوية_ولي_الأمر'            => $data->data_id_number,
            'اسم_ولي_الأمر'                 => $guardianFullName,
            'هاتف_ولي_الأمر'                => $data->data_phone_number        ?? '',
            'هاتف_بديل_ولي_الأمر'           => $data->data_alt_phone_number    ?? '',
            'تاريخ_ميلاد_ولي_الأمر'         => $data->data_birth_date           ?? '',
            'جنس_ولي_الأمر'                 => $this->resolveGender($data->data_gender),
            'الحالة_الاجتماعية'             => $this->lu('marital_status',        $data->data_marital_status),
            'المؤهل_العلمي'                 => $this->lu('academic_degrees',       $data->data_academic_qualification),
            'حالة_النزوح'                   => $this->lu('displacement_statuses',  $data->data_displacement_status),
            'العنوان_الحالي'                 => $data->data_current_address      ?? '',
            'المدينة'                       => $this->lu('city',                   $data->data_city),
            'المحافظة'                      => $this->lu('provinces',              $data->data_province),
            'الوضع_الوظيفي'                 => $this->lu('employment',             $data->data_employment_status_breadwinner),
            'حالة_السكن'                    => $this->lu('housing_status',         $data->data_housing_status),
            'نوع_السكن'                     => $this->lu('type_of_accommodation',  $data->data_current_housing_type),
            'عدد_أفراد_الأسرة'              => $data->data_number_of_individuals                     ?? 0,
            'عدد_الذكور'                    => $data->data_number_mail                                ?? 0,
            'عدد_الإناث'                    => $data->data_number_female                              ?? 0,
            'حالات_مزمنة'                   => $data->data_number_of_individuals_with_chronic_diseases ?? 0,
            'ذوو_احتياجات_خاصة'             => $data->data_number_of_people_with_special_needs         ?? 0,
            'المكفولون_في_الأسرة'            => $sponsoredCount,
        ];
    }

    private function generateExcel(array $rows, string $outputDir, string $operationName): string
    {
        // اسم الملف: "بيانات التصدير.csv" — بسيط وثابت داخل مجلد العملية
        $filePath = $outputDir . DIRECTORY_SEPARATOR . 'بيانات التصدير.csv';
        $handle   = fopen($filePath, 'w');
        // BOM لدعم Excel مع العربية
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) { fputcsv($handle, array_values($row)); }
        fclose($handle);
        Log::info('BulkExport: Excel generated', ['path' => $filePath, 'rows' => count($rows)]);
        return $filePath;
    }

    private function uploadToGoogleDrive(Sponsor $sponsor, string $outputDir, string $operationName, int $fileCount): void
    {
        if (!$sponsor->google_drive_enabled) {
            throw new \RuntimeException("Google Drive غير مفعّل للجمعية [{$sponsor->sponsor_name}] — لا يُسمح بالحفظ المحلي.");
        }

        $rclonePath   = config('services.rclone.path',        env('RCLONE_PATH'));
        $rcloneRemote = config('services.rclone.remote_name', env('RCLONE_REMOTE_NAME'));

        if (!$rclonePath || !$rcloneRemote) {
            throw new \RuntimeException('Rclone غير مهيأ (RCLONE_PATH / RCLONE_REMOTE_NAME) — تعذّر الرفع إلى Google Drive.');
        }

        $result = app(\App\Services\RcloneGoogleDriveService::class)->uploadFolder(
            $outputDir,
            $sponsor->sponsor_name,
            'تقارير شاملة',
            $operationName
        );

        if (!$result['success']) {
            throw new \RuntimeException("فشل رفع الملفات إلى Google Drive: " . ($result['message'] ?? 'unknown error'));
        }

        Log::info('BulkExport: Uploaded to Google Drive', [
            'remote' => $result['remote_path'] ?? '',
            'files'  => $fileCount,
        ]);

        // حذف المجلد المؤقت المحلي بعد الرفع الناجح
        $this->deleteLocalFolder($outputDir);
        Log::info('BulkExport: Local temp folder deleted', ['dir' => $outputDir]);
    }

    private function deleteLocalFolder(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileinfo) {
            $fileinfo->isDir() ? @rmdir($fileinfo->getRealPath()) : @unlink($fileinfo->getRealPath());
        }

        @rmdir($dir);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BulkExportFamilyReportsJob: Job failed', ['sponsor_id' => $this->sponsorId, 'error' => $exception->getMessage()]);
    }
}
