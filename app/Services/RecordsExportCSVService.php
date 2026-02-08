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
     * تصدير جميع السجلات في ملف Excel واحد مع 4 sheets
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

            // إنشاء 4 sheets
            $this->createDataSheet($spreadsheet);
            gc_collect_cycles(); // تحرير الذاكرة بعد أول sheet
            
            $this->createDeadPeopleSheet($spreadsheet);
            gc_collect_cycles();
            
            $this->createRePeopleSheet($spreadsheet);
            gc_collect_cycles();
            
            $this->createAttachmentsSheet($spreadsheet);
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
     * إنشاء Sheet لجدول Data (المعيلين) مع الحقول الديناميكية من portal
     */
    private function createDataSheet($spreadsheet)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('المعيلين');
        
        // ✅ جلب field_keys المستخدمة فعلياً من portal_general_registration_field_values
        Log::info('🔍 جمع field_keys من portal_general_registration_field_values للمعيلين...');
        $dynamicFieldKeys = [];
        
        Data::select('data_id_number')
            ->whereNotNull('data_id_number')
            ->where('data_id_number', '!=', '')
            ->chunk(500, function ($records) use (&$dynamicFieldKeys) {
                $identityNumbers = $records->pluck('data_id_number')->unique()->filter()->toArray();
                
                if (!empty($identityNumbers)) {
                    $keys = PortalGeneralRegistrationFieldValue::whereIn('identity_number', $identityNumbers)
                        ->whereNotNull('field_value')
                        ->where('field_value', '!=', '')
                        ->distinct()
                        ->pluck('field_key')
                        ->toArray();
                    
                    $dynamicFieldKeys = array_unique(array_merge($dynamicFieldKeys, $keys));
                }
                
                unset($identityNumbers, $keys);
            });
        
        Log::info("✅ تم جمع " . count($dynamicFieldKeys) . " field_key من portal");
        
        // Headers الأساسية
        $headers = [
            'ID', 'رقم الملف', 'القسم', 'رقم الهوية', 'الاسم الأول', 'اسم الأب',
            'اسم الجد', 'اسم العائلة', 'صلة القرابة', 'تاريخ الميلاد', 'الجنس',
            'رقم الهاتف', 'رقم هاتف بديل', 'عدد الأفراد', 'الحالة الاجتماعية',
            'المؤهل الأكاديمي', 'حالة النزوح', 'العنوان قبل النزوح', 'العنوان الحالي',
            'المدينة', 'المحافظة', 'الحالة الصحية', 'وصف الاحتياجات',
            'حالة التوظيف', 'حالة السكن', 'نوع السكن', 'المستخدم', 'حالة الطلب',
            'اسم البنك', 'IBAN USD', 'IBAN Shekel', 'رقم الحساب', 
            'رقم هوية صاحب الحساب', 'اسم ولي الأمر', 'رقم هاتف ولي الأمر',
            'تاريخ الإنشاء', 'تاريخ التحديث'
        ];
        
        // ✅ إضافة field_keys الديناميكية للهيدر
        foreach ($dynamicFieldKeys as $fieldKey) {
            $headers[] = $fieldKey;
        }
        
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
            'userInserted', 'requestStatus', 'guardianBankAccount.bank'
        ])
        ->chunk(self::CHUNK_SIZE, function ($records) use ($sheet, &$row, &$count, $dynamicFieldKeys) {
            foreach ($records as $record) {
                // ✅ جلب الحقول الديناميكية لهذا المعيل من portal
                $portalFields = [];
                if ($record->data_id_number) {
                    $portalFields = PortalGeneralRegistrationFieldValue::where('identity_number', $record->data_id_number)
                        ->whereNotNull('field_value')
                        ->where('field_value', '!=', '')
                        ->pluck('field_value', 'field_key')
                        ->toArray();
                }
                
                $data = [
                    $record->id,
                    $record->file_id_number,
                    $record->section?->description ?? '-',
                    $record->data_id_number,
                    $record->data_first_name,
                    $record->data_father_name,
                    $record->data_grand_father_name,
                    $record->data_family_name,
                    $record->categoryOfRelation?->description ?? '-',
                    $record->data_birth_date,
                    $record->data_gender,
                    $record->data_phone_number,
                    $record->data_alt_phone_number,
                    $record->data_number_of_individuals,
                    $record->maritalStatus?->description ?? '-',
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
                    $record->userInserted?->name ?? '-',
                    $record->requestStatus?->description ?? '-',
                    $record->guardianBankAccount?->bank?->description ?? '-',
                    $record->guardianBankAccount?->iban_usd ?? '-',
                    $record->guardianBankAccount?->iban_shekel ?? '-',
                    $record->guardianBankAccount?->check_account ?? '-',
                    $record->guardianBankAccount?->person_owner_identity_number ?? '-',
                    $record->guardianBankAccount?->re_guardian_name ?? '-',
                    $record->guardianBankAccount?->re_phone_number ?? '-',
                    $record->created_at,
                    $record->updated_at
                ];
                
                // ✅ إضافة القيم الديناميكية من portal
                foreach ($dynamicFieldKeys as $fieldKey) {
                    $data[] = $portalFields[$fieldKey] ?? '-';
                }
                
                $sheet->fromArray($data, NULL, 'A' . $row);
                $row++;
                $count++;
                
                // تحرير الذاكرة
                unset($portalFields);
            }
            Log::info("تم تصدير {$count} سجل من Data (المعيلين)");
            gc_collect_cycles();
        });

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        Log::info("اكتمل تصدير Data (المعيلين) - إجمالي {$count} سجل مع " . count($dynamicFieldKeys) . " حقل ديناميكي");
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
                        $record->person_gender,
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
}
