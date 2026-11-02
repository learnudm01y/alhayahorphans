<?php

namespace App\Jobs;

use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;
use App\Models\Attachment;
use App\Models\GuardianBankAccount;
use App\Models\PortalGeneralRegistrationFieldValue;
use App\Services\ExportStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * مهمة خلفية لتصدير «البيانات المدخلة» إلى ملف Excel بـ 6 شيتات.
 *
 * الهدف: تجنب خطأ Cloudflare 524 — كان التصدير المتزامن يبني الملف كاملاً
 * ثم يرسله، فيتجاوز مهلة Cloudflare (~100 ثانية) وتُقطع الجلسة. هنا يُبنى
 * الملف في الطابور (خلفية) بلا اتصال HTTP مفتوح، ثم يُحمّل الملف الجاهز فورًا.
 *
 * الشيتات (نفس محتوى RecordsExportCSVService — بدون تعديل تلك الخدمة):
 *   1. Data            (المعيلون)
 *   2. DeadPepole      (المتوفون)
 *   3. RePeople        (أفراد الأسرة)
 *   4. Attachment      (المستندات المرفقة)
 *   5. BankAccounts    (الحسابات البنكية)
 *   6. Portal_feiled   (حقول portal)
 */
class ExportAllRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $statusKey;
    protected int $chunkSize = 1000;

    public $tries = 1;
    public $backoff = 120;
    public $timeout = 3600;

    /** عدد الشيتات (لحساب نسبة التقدّم) */
    private const TOTAL_SHEETS = 6;

    public function __construct(string $statusKey)
    {
        $this->statusKey = $statusKey;
    }

    public function handle(): void
    {
        $lock = Cache::lock('export_all_records_lock', 3600);

        if (!$lock->get()) {
            ExportStatus::fail($this->statusKey, 'يوجد تصدير جارٍ بالفعل. انتظر انتهائه ثم أعد المحاولة.');
            return;
        }

        try {
            ini_set('memory_limit', '2048M');
            set_time_limit(0);

            ExportStatus::running($this->statusKey, [
                'message'   => 'جارٍ تجهيز الملف...',
                'processed' => 0,
                'total'     => self::TOTAL_SHEETS,
            ]);

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            // بناء الشيتات مع تحديث التقدّم بعد كل شيت
            $this->buildSheet($spreadsheet, 'Data',            fn ($s) => $this->createDataSheet($s),            1);
            gc_collect_cycles();
            $this->buildSheet($spreadsheet, 'DeadPepole',      fn ($s) => $this->createDeadPeopleSheet($s),      2);
            gc_collect_cycles();
            $this->buildSheet($spreadsheet, 'RePeople',        fn ($s) => $this->createRePeopleSheet($s),        3);
            gc_collect_cycles();
            $this->buildSheet($spreadsheet, 'Attachment',      fn ($s) => $this->createAttachmentsSheet($s),      4);
            gc_collect_cycles();
            $this->buildSheet($spreadsheet, 'BankAccounts',    fn ($s) => $this->createBankAccountsSheet($s),    5);
            gc_collect_cycles();
            $this->buildSheet($spreadsheet, 'Portal_feiled',   fn ($s) => $this->createPortalFieldValuesSheet($s), 6);
            gc_collect_cycles();

            $spreadsheet->setActiveSheetIndex(0);

            ExportStatus::running($this->statusKey, [
                'message' => 'جارٍ حفظ الملف النهائي...',
                'total'   => self::TOTAL_SHEETS,
            ]);

            // الحفظ على قرص الاستضافة (لا اتصال HTTP هنا — خلفية)
            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $fileName = 'all_records_' . date('Ymd_His') . '.xlsx';
            $filePath = $exportDir . DIRECTORY_SEPARATOR . $fileName;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            gc_collect_cycles();

            $downloadUrl = route('admin.records.management.exportRecordsDownload', ['file' => $fileName]);

            ExportStatus::finish($this->statusKey, [
                'total'        => self::TOTAL_SHEETS,
                'processed'    => self::TOTAL_SHEETS,
                'success'      => self::TOTAL_SHEETS,
                'failed'       => 0,
                'file_name'    => $fileName,
                'download_url' => $downloadUrl,
                'message'      => 'اكتمل التصدير — الملف جاهز للتحميل',
            ]);

            Log::info('ExportAllRecordsJob: Completed', [
                'file_name' => $fileName,
                'file_size' => round(filesize($filePath) / 1048576, 2) . ' MB',
            ]);
        } catch (\Exception $e) {
            Log::error('ExportAllRecordsJob: Critical error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            ExportStatus::fail($this->statusKey, $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExportAllRecordsJob: Job failed', ['error' => $exception->getMessage()]);

        ExportStatus::fail($this->statusKey, $exception->getMessage());
    }

    /**
     * بناء شيت واحد ثم تحديث حالة التقدّم.
     */
    private function buildSheet(Spreadsheet $spreadsheet, string $title, callable $builder, int $sheetNumber): void
    {
        $builder($spreadsheet);

        ExportStatus::progress(
            $this->statusKey,
            $sheetNumber,
            $sheetNumber,
            0,
            self::TOTAL_SHEETS
        );

        ExportStatus::running($this->statusKey, [
            'message' => sprintf('جارٍ بناء الشيت %d من %d...', $sheetNumber, self::TOTAL_SHEETS),
        ]);
    }

    /* ===================== بناء الشيتات (نفس محتوى RecordsExportCSVService) ===================== */

    private function createDataSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data');

        $headers = [
            'ID', 'رقم الملف', 'القسم', 'رقم الهوية', 'الاسم الأول', 'اسم الأب',
            'اسم الجد', 'اسم العائلة', 'صلة القرابة', 'تاريخ الميلاد', 'الجنس',
            'رقم الهاتف', 'رقم هاتف بديل', 'عدد الأفراد', 'الحالة الاجتماعية',
            'المؤهل الأكاديمي', 'حالة النزوح', 'العنوان قبل النزوح', 'العنوان الحالي',
            'المدينة', 'المحافظة', 'الحالة الصحية', 'وصف الاحتياجات',
            'حالة التوظيف', 'حالة السكن', 'نوع السكن', 'المستخدم', 'حالة الطلب',
            'تاريخ الإنشاء', 'تاريخ التحديث',
        ];

        $sheet->fromArray($headers, NULL, 'A1');
        $this->styleHeader($sheet, '4472C4');

        $row = 2;

        Data::with([
            'section', 'categoryOfRelation', 'maritalStatus', 'academicQualification',
            'displacementStatus', 'city', 'province', 'healthStatus',
            'employmentStatusBreadwinner', 'housingStatus', 'currentHousingType',
            'userInserted', 'requestStatus',
        ])->chunk($this->chunkSize, function ($records) use ($sheet, &$row) {
            foreach ($records as $record) {
                $sheet->fromArray([
                    $record->id,
                    $record->file_id_number,
                    $record->section?->description ?? '-',
                    $record->data_id_number,
                    $record->data_first_name,
                    $record->data_father_name,
                    $record->data_grand_father_name,
                    $record->data_family_name,
                    $record->categoryOfRelation?->attribute ?? '-',
                    $record->data_birth_date,
                    $record->gender_text,
                    $record->data_phone_number,
                    $record->data_alt_phone_number,
                    $record->data_number_of_individuals,
                    $record->maritalStatus?->CI_PERSONAL_CD ?? '-',
                    $record->academicQualification?->description ?? '-',
                    $record->displacementStatus?->description ?? '-',
                    $record->data_address_before_displacement,
                    $record->data_current_address,
                    $record->city?->city ?? '-',
                    $record->province?->description ?? '-',
                    $record->healthStatus?->description ?? '-',
                    $record->data_description_needs,
                    $record->employmentStatusBreadwinner?->description ?? '-',
                    $record->housingStatus?->description ?? '-',
                    $record->currentHousingType?->description ?? '-',
                    $record->data_user_insert_data ?? $record->userInserted?->name ?? '-',
                    $record->requestStatus?->description ?? '-',
                    $record->created_at,
                    $record->updated_at,
                ], NULL, 'A' . $row);
                $row++;
            }
            gc_collect_cycles();
        });

        $this->autoSize($sheet);
    }

    private function createDeadPeopleSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('DeadPepole');

        $headers = [
            'ID', 'رقم الملف', 'اسم الأب الأول', 'اسم الأب الثاني', 'اسم الأب الثالث',
            'لقب الأب', 'رقم هوية الأب', 'تاريخ وفاة الأب', 'سبب وفاة الأب',
            'اسم الأم الأول', 'اسم الأم الثاني', 'اسم الأم الثالث', 'لقب الأم',
            'رقم هوية الأم', 'تاريخ وفاة الأم', 'سبب وفاة الأم',
            'تاريخ الإنشاء', 'تاريخ التحديث',
        ];

        $sheet->fromArray($headers, NULL, 'A1');
        $this->styleHeader($sheet, 'E74C3C');

        $row = 2;

        DeadPepole::with(['fatherDeathReason', 'motherDeathReason'])
            ->chunk($this->chunkSize, function ($records) use ($sheet, &$row) {
                foreach ($records as $record) {
                    $sheet->fromArray([
                        $record->id,
                        $record->re_file_id,
                        $record->father_first_name,
                        $record->father_second_name,
                        $record->father_third_name,
                        $record->father_last_name,
                        $record->father_id,
                        $record->father_death_date,
                        $record->fatherDeathReason?->description ?? '-',
                        $record->mother_first_name,
                        $record->mother_second_name,
                        $record->mother_third_name,
                        $record->mother_last_name,
                        $record->mother_id,
                        $record->mother_death_date,
                        $record->motherDeathReason?->description ?? '-',
                        $record->created_at,
                        $record->updated_at,
                    ], NULL, 'A' . $row);
                    $row++;
                }
                gc_collect_cycles();
            });

        $this->autoSize($sheet);
    }

    private function createRePeopleSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('RePeople');

        $headers = [
            'ID', 'رقم التسجيل', 'حالة الكفالة', 'الاسم الأول', 'الاسم الثاني',
            'الاسم الثالث', 'اللقب', 'رقم الهوية', 'تاريخ الميلاد', 'العمر',
            'الجنس', 'الحالة الصحية', 'نوع الكفالة', 'تاريخ الإنشاء', 'تاريخ التحديث',
        ];

        $sheet->fromArray($headers, NULL, 'A1');
        $this->styleHeader($sheet, '2ECC71');

        $row = 2;

        RePeople::with(['sponsorshipStatus', 'healthStatus', 'guaranteeType'])
            ->chunk($this->chunkSize, function ($records) use ($sheet, &$row) {
                foreach ($records as $record) {
                    $sheet->fromArray([
                        $record->id,
                        $record->registration_id,
                        $record->sponsorshipStatus?->description ?? '-',
                        $record->first_name,
                        $record->second_name,
                        $record->third_name,
                        $record->last_name,
                        $record->person_id,
                        $record->person_birth_date,
                        $record->person_age,
                        $record->gender_text,
                        $record->healthStatus?->description ?? '-',
                        $record->guaranteeType?->description ?? '-',
                        $record->created_at,
                        $record->updated_at,
                    ], NULL, 'A' . $row);
                    $row++;
                }
                gc_collect_cycles();
            });

        $this->autoSize($sheet);
    }

    private function createAttachmentsSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Attachment');

        $headers = [
            'ID', 'رقم الهوية', 'اسم الملف المخزن', 'مسار الملف', 'نوع الملف',
            'تاريخ الإضافة', 'تاريخ التحديث',
        ];

        $sheet->fromArray($headers, NULL, 'A1');
        $this->styleHeader($sheet, 'F39C12');

        $row = 2;

        Attachment::chunk($this->chunkSize, function ($records) use ($sheet, &$row) {
            foreach ($records as $record) {
                $sheet->fromArray([
                    $record->id,
                    $record->person_identity_number,
                    $record->stored_file_name,
                    $record->file_path,
                    $record->file_type,
                    $record->created_at,
                    $record->updated_at,
                ], NULL, 'A' . $row);
                $row++;
            }
            gc_collect_cycles();
        });

        $this->autoSize($sheet);
    }

    private function createBankAccountsSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('BankAccounts');

        $headers = [
            'ID', 'رقم تسجيل المعيل', 'اسم البنك', 'IBAN USD', 'IBAN Shekel',
            'رقم الحساب', 'رقم هوية صاحب الحساب', 'رقم هوية ولي الأمر',
            'اسم ولي الأمر', 'رقم هاتف ولي الأمر', 'تاريخ الإنشاء', 'تاريخ التحديث',
        ];

        $sheet->fromArray($headers, NULL, 'A1');
        $this->styleHeader($sheet, '9B59B6');

        $row = 2;

        GuardianBankAccount::with(['bank', 'guardian'])
            ->chunk($this->chunkSize, function ($records) use ($sheet, &$row) {
                foreach ($records as $record) {
                    $sheet->fromArray([
                        $record->id,
                        $record->guardian_registration,
                        $record->bank?->description ?? '-',
                        $record->iban_usd ?? '-',
                        $record->iban_shekel ?? '-',
                        $record->check_account ?? '-',
                        $record->person_owner_identity_number ?? '-',
                        $record->re_id_number ?? '-',
                        $record->re_guardian_name ?? '-',
                        $record->re_phone_number ?? '-',
                        $record->created_at,
                        $record->updated_at,
                    ], NULL, 'A' . $row);
                    $row++;
                }
                gc_collect_cycles();
            });

        $this->autoSize($sheet);
    }

    private function createPortalFieldValuesSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Portal_feiled');

        $headers = [
            'ID', 'Sponsorship ID', 'رقم الملف', 'رقم الهوية', 'مفتاح الحقل',
            'قيمة الحقل', 'تم التحديث بواسطة', 'تاريخ الإنشاء', 'تاريخ التحديث',
        ];

        $sheet->fromArray($headers, NULL, 'A1');
        $this->styleHeader($sheet, '16A085');

        $row = 2;

        PortalGeneralRegistrationFieldValue::chunk($this->chunkSize, function ($records) use ($sheet, &$row) {
            foreach ($records as $record) {
                $sheet->fromArray([
                    $record->id,
                    $record->sponsorship_id ?? '-',
                    $record->file_id_number ?? '-',
                    $record->identity_number ?? '-',
                    $record->field_key ?? '-',
                    $record->field_value ?? '-',
                    $record->updated_by_user_id ?? '-',
                    $record->created_at,
                    $record->updated_at,
                ], NULL, 'A' . $row);
                $row++;
            }
            gc_collect_cycles();
        });

        $this->autoSize($sheet);
    }

    /* ===================== أدوات مساعدة ===================== */

    private function styleHeader($sheet, string $color): void
    {
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
    }

    private function autoSize($sheet): void
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }
}
