<?php

namespace App\Services;

use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;
use App\Models\Attachment;
use App\Models\PortalGeneralRegistrationFieldValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RecordsExportCSVService
{
    private const CHUNK_SIZE = 1000;

    /**
     * تصدير جميع السجلات في ملف Excel واحد مع 6 sheets
     * الأخف على الذاكرة والأسهل للاستخدام
     */
    public function exportAllAsCSV()
    {
        try {
            ini_set('memory_limit', '2048M'); // رفع الحد إلى 2GB للبيانات الكبيرة مع Portal fields
            set_time_limit(0);

            Log::info('بدء عملية تصدير السجلات في ملف Excel موحد...');
            Log::info('💾 Memory limit: ' . ini_get('memory_limit'));

            // إنشاء Spreadsheet جديد
            $spreadsheet = new Spreadsheet();

            // حذف الـ sheet الافتراضي
            $spreadsheet->removeSheetByIndex(0);

            // إنشاء 6 sheets
            $this->createDataSheet($spreadsheet);
            gc_collect_cycles(); // تحرير الذاكرة بعد أول sheet

            $this->createDeadPeopleSheet($spreadsheet);
            gc_collect_cycles();

            $this->createRePeopleSheet($spreadsheet);
            gc_collect_cycles();

            $this->createAttachmentsSheet($spreadsheet);
            gc_collect_cycles();

            $this->createGuardianBankAccountsSheet($spreadsheet);
            gc_collect_cycles();

            $this->createPortalFieldValuesSheet($spreadsheet);
            gc_collect_cycles();

            // تفعيل أول sheet
            $spreadsheet->setActiveSheetIndex(0);

            // حفظ الملف
            $fileName = 'all_records_' . date('Ymd_His') . '.xlsx';
            $filePath = storage_path('app/' . $fileName);

            Log::info('💾 بدء حفظ الملف - Memory usage: ' . round(memory_get_usage(true) / 1048576, 2) . ' MB');

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            // تحرير الذاكرة
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            gc_collect_cycles();

            Log::info('✅ تم إنشاء ملف Excel بنجاح - Memory usage: ' . round(memory_get_usage(true) / 1048576, 2) . ' MB');

            // إرسال الملف للتحميل
            return Response::download($filePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('خطأ أثناء تصدير Excel: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * إنشاء Sheet لجدول Data (المعيلين) - بدون الحقول البنكية والديناميكية
     */
    private function createDataSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('المعيلين');

        // Headers الأساسية فقط (بدون البيانات البنكية أو الحقول الديناميكية)
        $headers = [
            'ID', 'رقم الملف', 'القسم', 'رقم الهوية', 'الاسم الأول', 'اسم الأب',
            'اسم الجد', 'اسم العائلة', 'صلة القرابة', 'تاريخ الميلاد', 'الجنس',
            'رقم الهاتف', 'رقم هاتف بديل', 'عدد الأفراد', 'الحالة الاجتماعية',
            'المؤهل الأكاديمي', 'حالة النزوح', 'العنوان قبل النزوح', 'العنوان الحالي',
            'المدينة', 'المحافظة', 'الحالة الصحية', 'وصف الاحتياجات',
            'حالة التوظيف', 'حالة السكن', 'نوع السكن', 'المستخدم', 'حالة الطلب',
            'تاريخ الإنشاء', 'تاريخ التحديث'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        // تنسيق الهيدر
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        $row = 2;
        $count = 0;

        Data::with([
            'section', 'categoryOfRelation', 'maritalStatus', 'academicQualification',
            'displacementStatus', 'city', 'province', 'healthStatus',
            'employmentStatusBreadwinner', 'housingStatus', 'currentHousingType',
            'userInserted', 'requestStatus'
        ])
        ->chunk(self::CHUNK_SIZE, function ($records) use ($sheet, &$row, &$count) {
            foreach ($records as $record) {
                $data = [
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
                    $record->updated_at
                ];

                $sheet->fromArray($data, NULL, 'A' . $row);
                $row++;
                $count++;
            }
            Log::info("تم تصدير {$count} سجل من Data (المعيلين)");
            gc_collect_cycles();
        });

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        Log::info("اكتمل تصدير Data (المعيلين) - إجمالي {$count} سجل");
    }

    /**
     * إنشاء Sheet لجدول DeadPeople
     */
    private function createDeadPeopleSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('المتوفين');

        $headers = [
            'ID', 'رقم الملف', 'اسم الأب الأول', 'اسم الأب الثاني', 'اسم الأب الثالث',
            'لقب الأب', 'رقم هوية الأب', 'تاريخ وفاة الأب', 'سبب وفاة الأب',
            'اسم الأم الأول', 'اسم الأم الثاني', 'اسم الأم الثالث', 'لقب الأم',
            'رقم هوية الأم', 'تاريخ وفاة الأم', 'سبب وفاة الأم',
            'تاريخ الإنشاء', 'تاريخ التحديث'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E74C3C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        $row = 2;
        $count = 0;

        DeadPepole::with(['fatherDeathReason', 'motherDeathReason'])
            ->chunk(self::CHUNK_SIZE, function ($records) use ($sheet, &$row, &$count) {
                foreach ($records as $record) {
                    $data = [
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
                        $record->updated_at
                    ];

                    $sheet->fromArray($data, NULL, 'A' . $row);
                    $row++;
                    $count++;
                }
                Log::info("تم تصدير {$count} سجل من DeadPeople");
                gc_collect_cycles();
            });

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        Log::info("اكتمل تصدير DeadPeople - إجمالي {$count} سجل");
    }

    /**
     * إنشاء Sheet لجدول RePeople
     */
    private function createRePeopleSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('أفراد الأسرة');

        $headers = [
            'ID', 'رقم التسجيل', 'حالة الكفالة', 'الاسم الأول', 'الاسم الثاني',
            'الاسم الثالث', 'اللقب', 'رقم الهوية', 'تاريخ الميلاد', 'العمر',
            'الجنس', 'الحالة الصحية', 'نوع الكفالة', 'تاريخ الإنشاء', 'تاريخ التحديث'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2ECC71']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        $row = 2;
        $count = 0;

        RePeople::with(['sponsorshipStatus', 'healthStatus', 'guaranteeType'])
            ->chunk(self::CHUNK_SIZE, function ($records) use ($sheet, &$row, &$count) {
                foreach ($records as $record) {
                    $data = [
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
                        $record->updated_at
                    ];

                    $sheet->fromArray($data, NULL, 'A' . $row);
                    $row++;
                    $count++;
                }
                Log::info("تم تصدير {$count} سجل من RePeople");
                gc_collect_cycles();
            });

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        Log::info("اكتمل تصدير RePeople - إجمالي {$count} سجل");
    }

    /**
     * إنشاء Sheet لجدول Attachments
     */
    private function createAttachmentsSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('المرفقات');

        $headers = [
            'ID',
            'رقم الهوية',
            'اسم الملف المخزن',
            'مسار الملف',
            'نوع الملف',
            'تاريخ الإضافة',
            'تاريخ التحديث'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F39C12']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        $row = 2;
        $count = 0;

        Attachment::chunk(self::CHUNK_SIZE, function ($records) use ($sheet, &$row, &$count) {
            foreach ($records as $record) {
                $data = [
                    $record->id,
                    $record->person_identity_number,
                    $record->stored_file_name,
                    $record->file_path,
                    $record->file_type,
                    $record->created_at,
                    $record->updated_at
                ];

                $sheet->fromArray($data, NULL, 'A' . $row);
                $row++;
                $count++;
            }
            Log::info("تم تصدير {$count} سجل من Attachments");
            gc_collect_cycles();
        });

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        Log::info("اكتمل تصدير Attachments - إجمالي {$count} سجل");
    }

    /**
     * إنشاء Sheet لجدول GuardianBankAccounts
     */
    private function createGuardianBankAccountsSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('الحسابات البنكية');

        $headers = [
            'ID',
            'رقم تسجيل المعيل',
            'اسم البنك',
            'IBAN USD',
            'IBAN Shekel',
            'رقم الحساب',
            'رقم هوية صاحب الحساب',
            'رقم هوية ولي الأمر',
            'اسم ولي الأمر',
            'رقم هاتف ولي الأمر',
            'تاريخ الإنشاء',
            'تاريخ التحديث'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9B59B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        $row = 2;
        $count = 0;

        \App\Models\GuardianBankAccount::with(['bank', 'guardian'])
            ->chunk(self::CHUNK_SIZE, function ($records) use ($sheet, &$row, &$count) {
                foreach ($records as $record) {
                    $data = [
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
                        $record->updated_at
                    ];

                    $sheet->fromArray($data, NULL, 'A' . $row);
                    $row++;
                    $count++;
                }
                Log::info("تم تصدير {$count} سجل من GuardianBankAccounts");
                gc_collect_cycles();
            });

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        Log::info("اكتمل تصدير GuardianBankAccounts - إجمالي {$count} سجل");
    }

    /**
     * إنشاء Sheet لجدول PortalGeneralRegistrationFieldValues
     */
    private function createPortalFieldValuesSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('حقول Portal الديناميكية');

        $headers = [
            'ID',
            'Sponsorship ID',
            'رقم الملف',
            'رقم الهوية',
            'مفتاح الحقل',
            'قيمة الحقل',
            'تم التحديث بواسطة',
            'تاريخ الإنشاء',
            'تاريخ التحديث'
        ];

        $sheet->fromArray($headers, NULL, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A085']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        $row = 2;
        $count = 0;

        PortalGeneralRegistrationFieldValue::chunk(self::CHUNK_SIZE, function ($records) use ($sheet, &$row, &$count) {
            foreach ($records as $record) {
                $data = [
                    $record->id,
                    $record->sponsorship_id ?? '-',
                    $record->file_id_number ?? '-',
                    $record->identity_number ?? '-',
                    $record->field_key ?? '-',
                    $record->field_value ?? '-',
                    $record->updated_by_user_id ?? '-',
                    $record->created_at,
                    $record->updated_at
                ];

                $sheet->fromArray($data, NULL, 'A' . $row);
                $row++;
                $count++;
            }
            Log::info("تم تصدير {$count} سجل من PortalGeneralRegistrationFieldValues");
            gc_collect_cycles();
        });

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        Log::info("اكتمل تصدير PortalGeneralRegistrationFieldValues - إجمالي {$count} سجل");
    }
}
