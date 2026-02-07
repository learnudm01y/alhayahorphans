<?php

namespace App\Services;

use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;
use App\Models\PortalGeneralRegistrationFieldValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordsExportService
{
    // حجم الدفعة لمعالجة البيانات (يمكن تعديله حسب الحاجة)
    private const CHUNK_SIZE = 500;

    /**
     * Export all records from Data, DeadPepole and RePeople into one Excel file
     * with three sheets. Returns a StreamedResponse for download.
     * ✅ يتم تحويل IDs إلى القيم النصية + إضافة حقول portal المستخدمة فقط (بدون تكرار)
     * ✅ معالجة البيانات على دفعات لتقليل استهلاك الذاكرة
     *
     * @return StreamedResponse
     */
    public function exportAll(): StreamedResponse
    {
        try {
            // رفع حد الذاكرة مؤقتاً (كحل احتياطي)
            ini_set('memory_limit', '512M');

            // تعطيل الحد الزمني للتنفيذ
            set_time_limit(0);

            Log::info('بدء عملية تصدير السجلات...');

            $spreadsheet = new Spreadsheet();

            // ✅ Sheet 1: Data - مع تحويل IDs إلى قيم نصية + حقول portal المستخدمة فعلياً
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Data');

            // ✅ جلب فقط field_keys التي لها قيم فعلية من portal للأشخاص في جدول data
            // استخدام chunking لتجنب تحميل كل الـ IDs في الذاكرة دفعة واحدة
            $dynamicFieldKeys = [];
            $processedKeys = [];

            Data::select('data_id_number')
                ->whereNotNull('data_id_number')
                ->where('data_id_number', '!=', '')
                ->chunk(self::CHUNK_SIZE, function ($records) use (&$processedKeys) {
                    $identityNumbers = $records->pluck('data_id_number')->unique()->toArray();

                    if (!empty($identityNumbers)) {
                        $keys = PortalGeneralRegistrationFieldValue::whereIn('identity_number', $identityNumbers)
                            ->whereNotNull('field_value')
                            ->where('field_value', '!=', '')
                            ->distinct()
                            ->pluck('field_key')
                            ->toArray();

                        $processedKeys = array_unique(array_merge($processedKeys, $keys));
                    }

                    // تحرير الذاكرة
                    unset($identityNumbers, $keys);
                });

            $dynamicFieldKeys = $processedKeys;
            unset($processedKeys);

        $dataHeaders = [
            'id',
            'file_id_number',
            'القسم', // ✅ اسم القسم بدلاً من ID
            'data_id_number',
            'data_first_name',
            'data_father_name',
            'data_grand_father_name',
            'data_family_name',
            'صلة القرابة', // ✅ اسم صلة القرابة بدلاً من ID
            'data_birth_date',
            'data_gender',
            'data_phone_number',
            'data_alt_phone_number',
            'data_number_of_individuals',
            'الحالة الاجتماعية', // ✅ اسم الحالة بدلاً من ID
            'المؤهل الأكاديمي', // ✅ اسم المؤهل بدلاً من ID
            'حالة النزوح', // ✅ اسم حالة النزوح بدلاً من ID
            'data_address_before_displacement',
            'data_current_address',
            'المدينة', // ✅ اسم المدينة بدلاً من ID
            'المحافظة', // ✅ اسم المحافظة بدلاً من ID
            'الحالة الصحية', // ✅ اسم الحالة الصحية بدلاً من ID
            'data_description_needs',
            'حالة التوظيف', // ✅ حالة توظيف المعيل
            'حالة السكن', // ✅ حالة السكن
            'نوع السكن الحالي', // ✅ نوع السكن
            'المستخدم الذي أضاف البيانات', // ✅ اسم المستخدم
            'حالة الطلب', // ✅ حالة الطلب
            // ✅ البيانات البنكية
            'اسم البنك',
            'IBAN USD',
            'IBAN Shekel',
            'رقم الحساب الجاري',
            'رقم هوية صاحب الحساب',
            'اسم ولي الأمر',
            'رقم هاتف ولي الأمر',
            'created_at',
            'updated_at'
        ];

        // ✅ إضافة الحقول الديناميكية المستخدمة فقط من portal
        foreach ($dynamicFieldKeys as $fieldKey) {
            $dataHeaders[] = $fieldKey;
        }

        $col = 'A';
        foreach ($dataHeaders as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

            // ✅ معالجة البيانات على دفعات لتقليل استهلاك الذاكرة
            $rowNum = 2;
            $totalRecords = Data::count();
            $processedRecords = 0;

            Log::info("جاري تصدير {$totalRecords} سجل من جدول Data...");

            Data::with([
                'section',
                'categoryOfRelation',
                'maritalStatus',
                'academicQualification',
                'displacementStatus',
                'city',
                'province',
                'healthStatus',
                'employmentStatusBreadwinner',
                'housingStatus',
                'currentHousingType',
                'userInserted',
                'requestStatus',
                'guardianBankAccount.bank'
            ])
            ->chunk(self::CHUNK_SIZE, function ($dataRecords) use ($sheet, &$rowNum, $dynamicFieldKeys, &$processedRecords, $totalRecords) {
                foreach ($dataRecords as $row) {
                    $col = 'A';

                    // ✅ جلب الحقول الديناميكية لهذا الشخص من portal
                    $portalFields = [];
                    if ($row->data_id_number) {
                        $portalFields = PortalGeneralRegistrationFieldValue::where('identity_number', $row->data_id_number)
                            ->whereNotNull('field_value')
                            ->where('field_value', '!=', '')
                            ->pluck('field_value', 'field_key')
                            ->toArray();
                    }

                    // الأعمدة الأساسية
                    $sheet->setCellValue($col++ . $rowNum, $row->id);
                    $sheet->setCellValue($col++ . $rowNum, $row->file_id_number);
                    $sheet->setCellValue($col++ . $rowNum, $row->section?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->data_id_number);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_first_name);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_father_name);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_grand_father_name);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_family_name);
                    $sheet->setCellValue($col++ . $rowNum, $row->categoryOfRelation?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->data_birth_date);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_gender);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_phone_number);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_alt_phone_number);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_number_of_individuals);
                    $sheet->setCellValue($col++ . $rowNum, $row->maritalStatus?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->academicQualification?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->displacementStatus?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->data_address_before_displacement);
                    $sheet->setCellValue($col++ . $rowNum, $row->data_current_address);
                    $sheet->setCellValue($col++ . $rowNum, $row->city?->city ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->province?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->healthStatus?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->data_description_needs);
                    $sheet->setCellValue($col++ . $rowNum, $row->employmentStatusBreadwinner?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->housingStatus?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->currentHousingType?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->userInserted?->name ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $row->requestStatus?->description ?? '-');

                    // ✅ البيانات البنكية
                    $bankAccount = $row->guardianBankAccount;
                    $sheet->setCellValue($col++ . $rowNum, $bankAccount?->bank?->description ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $bankAccount?->iban_usd ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $bankAccount?->iban_shekel ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $bankAccount?->check_account ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $bankAccount?->person_owner_identity_number ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $bankAccount?->re_guardian_name ?? '-');
                    $sheet->setCellValue($col++ . $rowNum, $bankAccount?->re_phone_number ?? '-');

                    $sheet->setCellValue($col++ . $rowNum, $row->created_at);
                    $sheet->setCellValue($col++ . $rowNum, $row->updated_at);

                    // ✅ إضافة الحقول الديناميكية من portal
                    foreach ($dynamicFieldKeys as $fieldKey) {
                        $sheet->setCellValue($col++ . $rowNum, $portalFields[$fieldKey] ?? '-');
                    }

                    $rowNum++;
                    $processedRecords++;

                    // تحرير الذاكرة
                    unset($portalFields, $bankAccount);
                }

                // تسجيل التقدم وتحرير الذاكرة بعد كل دفعة
                Log::info("تم معالجة {$processedRecords} من {$totalRecords} سجل من Data");
                gc_collect_cycles();
            });

            // ✅ Sheet 2: DeadPepole - مع تحويل IDs إلى قيم نصية + حقول portal
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('DeadPepole');

            // ✅ جلب field_keys المستخدمة للمتوفين (father_id و mother_id) باستخدام chunking
            $dynamicFieldKeysForDead = [];
            $processedKeys = [];

            DeadPepole::select('father_id', 'mother_id')
                ->chunk(self::CHUNK_SIZE, function ($records) use (&$processedKeys) {
                    $fatherIds = $records->pluck('father_id')->filter()->unique()->toArray();
                    $motherIds = $records->pluck('mother_id')->filter()->unique()->toArray();
                    $allDeadIds = array_unique(array_merge($fatherIds, $motherIds));

                    if (!empty($allDeadIds)) {
                        $keys = PortalGeneralRegistrationFieldValue::whereIn('identity_number', $allDeadIds)
                            ->whereNotNull('field_value')
                            ->where('field_value', '!=', '')
                            ->distinct()
                            ->pluck('field_key')
                            ->toArray();

                        $processedKeys = array_unique(array_merge($processedKeys, $keys));
                    }

                    unset($fatherIds, $motherIds, $allDeadIds, $keys);
                });

            $dynamicFieldKeysForDead = $processedKeys;
            unset($processedKeys);

        $deadHeaders = [
            'id',
            're_file_id',
            'father_first_name',
            'father_second_name',
            'father_third_name',
            'father_last_name',
            'father_id',
            'father_death_date',
            'سبب وفاة الأب', // ✅ اسم سبب الوفاة بدلاً من ID
            'mother_first_name',
            'mother_second_name',
            'mother_third_name',
            'mother_last_name',
            'mother_id',
            'mother_death_date',
            'سبب وفاة الأم', // ✅ اسم سبب الوفاة بدلاً من ID
            'created_at',
            'updated_at'
        ];

        // ✅ إضافة حقول ديناميكية للأب
        foreach ($dynamicFieldKeysForDead as $fieldKey) {
            $deadHeaders[] = 'father_' . $fieldKey;
        }

        // ✅ إضافة حقول ديناميكية للأم
        foreach ($dynamicFieldKeysForDead as $fieldKey) {
            $deadHeaders[] = 'mother_' . $fieldKey;
        }

        $col = 'A';
        foreach ($deadHeaders as $header) {
            $sheet2->setCellValue($col . '1', $header);
            $col++;
        }

            // ✅ معالجة البيانات على دفعات لتقليل استهلاك الذاكرة
            $rowNum = 2;
            $totalDeadRecords = DeadPepole::count();
            $processedDeadRecords = 0;

            Log::info("جاري تصدير {$totalDeadRecords} سجل من جدول DeadPepole...");

            DeadPepole::with(['fatherDeathReason', 'motherDeathReason'])
                ->chunk(self::CHUNK_SIZE, function ($deadRecords) use ($sheet2, &$rowNum, $dynamicFieldKeysForDead, &$processedDeadRecords, $totalDeadRecords) {
                    foreach ($deadRecords as $row) {
                        $col = 'A';

                        // ✅ جلب الحقول الديناميكية للأب
                        $fatherPortalFields = [];
                        if ($row->father_id) {
                            $fatherPortalFields = PortalGeneralRegistrationFieldValue::where('identity_number', $row->father_id)
                                ->whereNotNull('field_value')
                                ->where('field_value', '!=', '')
                                ->pluck('field_value', 'field_key')
                                ->toArray();
                        }

                        // ✅ جلب الحقول الديناميكية للأم
                        $motherPortalFields = [];
                        if ($row->mother_id) {
                            $motherPortalFields = PortalGeneralRegistrationFieldValue::where('identity_number', $row->mother_id)
                                ->whereNotNull('field_value')
                                ->where('field_value', '!=', '')
                                ->pluck('field_value', 'field_key')
                                ->toArray();
                        }

                        // الأعمدة الأساسية
                        $sheet2->setCellValue($col++ . $rowNum, $row->id);
                        $sheet2->setCellValue($col++ . $rowNum, $row->re_file_id);
                        $sheet2->setCellValue($col++ . $rowNum, $row->father_first_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->father_second_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->father_third_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->father_last_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->father_id);
                        $sheet2->setCellValue($col++ . $rowNum, $row->father_death_date);
                        $sheet2->setCellValue($col++ . $rowNum, $row->fatherDeathReason?->description ?? '-');
                        $sheet2->setCellValue($col++ . $rowNum, $row->mother_first_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->mother_second_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->mother_third_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->mother_last_name);
                        $sheet2->setCellValue($col++ . $rowNum, $row->mother_id);
                        $sheet2->setCellValue($col++ . $rowNum, $row->mother_death_date);
                        $sheet2->setCellValue($col++ . $rowNum, $row->motherDeathReason?->description ?? '-');
                        $sheet2->setCellValue($col++ . $rowNum, $row->created_at);
                        $sheet2->setCellValue($col++ . $rowNum, $row->updated_at);

                        // ✅ إضافة الحقول الديناميكية للأب
                        foreach ($dynamicFieldKeysForDead as $fieldKey) {
                            $sheet2->setCellValue($col++ . $rowNum, $fatherPortalFields[$fieldKey] ?? '-');
                        }

                        // ✅ إضافة الحقول الديناميكية للأم
                        foreach ($dynamicFieldKeysForDead as $fieldKey) {
                            $sheet2->setCellValue($col++ . $rowNum, $motherPortalFields[$fieldKey] ?? '-');
                        }

                        $rowNum++;
                        $processedDeadRecords++;

                        unset($fatherPortalFields, $motherPortalFields);
                    }

                    Log::info("تم معالجة {$processedDeadRecords} من {$totalDeadRecords} سجل من DeadPepole");
                    gc_collect_cycles();
                });

            // ✅ Sheet 3: RePeople - مع تحويل IDs إلى قيم نصية + حقول portal
            $sheet3 = $spreadsheet->createSheet();
            $sheet3->setTitle('RePeople');

            // ✅ جلب field_keys المستخدمة لأفراد الأسرة (person_id) باستخدام chunking
            $dynamicFieldKeysForRePeople = [];
            $processedKeys = [];

            RePeople::select('person_id')
                ->whereNotNull('person_id')
                ->where('person_id', '!=', '')
                ->chunk(self::CHUNK_SIZE, function ($records) use (&$processedKeys) {
                    $personIds = $records->pluck('person_id')->unique()->toArray();

                    if (!empty($personIds)) {
                        $keys = PortalGeneralRegistrationFieldValue::whereIn('identity_number', $personIds)
                            ->whereNotNull('field_value')
                            ->where('field_value', '!=', '')
                            ->distinct()
                            ->pluck('field_key')
                            ->toArray();

                        $processedKeys = array_unique(array_merge($processedKeys, $keys));
                    }

                    unset($personIds, $keys);
                });

            $dynamicFieldKeysForRePeople = $processedKeys;
            unset($processedKeys);

        $repHeaders = [
            'id',
            'registration_id',
            'حالة الكفالة', // ✅ اسم حالة الكفالة بدلاً من ID
            'first_name',
            'second_name',
            'third_name',
            'last_name',
            'person_id',
            'person_birth_date',
            'person_age',
            'person_gender',
            'الحالة الصحية', // ✅ اسم الحالة الصحية بدلاً من ID
            'نوع الكفالة', // ✅ اسم نوع الكفالة بدلاً من ID
            'created_at',
            'updated_at'
        ];

        // ✅ إضافة الحقول الديناميكية من portal
        foreach ($dynamicFieldKeysForRePeople as $fieldKey) {
            $repHeaders[] = $fieldKey;
        }

        $col = 'A';
        foreach ($repHeaders as $header) {
            $sheet3->setCellValue($col . '1', $header);
            $col++;
        }

            // ✅ معالجة البيانات على دفعات لتقليل استهلاك الذاكرة
            $rowNum = 2;
            $totalRePeopleRecords = RePeople::count();
            $processedRePeopleRecords = 0;

            Log::info("جاري تصدير {$totalRePeopleRecords} سجل من جدول RePeople...");

            RePeople::with([
                'sponsorshipStatus',
                'healthStatus',
                'guaranteeType'
            ])
            ->chunk(self::CHUNK_SIZE, function ($rePeopleRecords) use ($sheet3, &$rowNum, $dynamicFieldKeysForRePeople, &$processedRePeopleRecords, $totalRePeopleRecords) {
                foreach ($rePeopleRecords as $row) {
                    $col = 'A';

                    // ✅ جلب الحقول الديناميكية لهذا الشخص
                    $portalFields = [];
                    if ($row->person_id) {
                        $portalFields = PortalGeneralRegistrationFieldValue::where('identity_number', $row->person_id)
                            ->whereNotNull('field_value')
                            ->where('field_value', '!=', '')
                            ->pluck('field_value', 'field_key')
                            ->toArray();
                    }

                    // الأعمدة الأساسية
                    $sheet3->setCellValue($col++ . $rowNum, $row->id);
                    $sheet3->setCellValue($col++ . $rowNum, $row->registration_id);
                    $sheet3->setCellValue($col++ . $rowNum, $row->sponsorshipStatus?->description ?? '-');
                    $sheet3->setCellValue($col++ . $rowNum, $row->first_name);
                    $sheet3->setCellValue($col++ . $rowNum, $row->second_name);
                    $sheet3->setCellValue($col++ . $rowNum, $row->third_name);
                    $sheet3->setCellValue($col++ . $rowNum, $row->last_name);
                    $sheet3->setCellValue($col++ . $rowNum, $row->person_id);
                    $sheet3->setCellValue($col++ . $rowNum, $row->person_birth_date);
                    $sheet3->setCellValue($col++ . $rowNum, $row->person_age);
                    $sheet3->setCellValue($col++ . $rowNum, $row->person_gender);
                    $sheet3->setCellValue($col++ . $rowNum, $row->healthStatus?->description ?? '-');
                    $sheet3->setCellValue($col++ . $rowNum, $row->guaranteeType?->description ?? '-');
                    $sheet3->setCellValue($col++ . $rowNum, $row->created_at);
                    $sheet3->setCellValue($col++ . $rowNum, $row->updated_at);

                    // ✅ إضافة الحقول الديناميكية
                    foreach ($dynamicFieldKeysForRePeople as $fieldKey) {
                        $sheet3->setCellValue($col++ . $rowNum, $portalFields[$fieldKey] ?? '-');
                    }

                    $rowNum++;
                    $processedRePeopleRecords++;

                    unset($portalFields);
                }

                Log::info("تم معالجة {$processedRePeopleRecords} من {$totalRePeopleRecords} سجل من RePeople");
                gc_collect_cycles();
            });

            // ✅ تحرير الذاكرة قبل الكتابة
            Log::info('تم الانتهاء من معالجة البيانات، جاري إنشاء ملف Excel...');
            gc_collect_cycles();

            // Prepare writer and streamed response
            $writer = new Xlsx($spreadsheet);
            $fileName = 'records_export_' . date('Ymd_His') . '.xlsx';

            $response = new StreamedResponse(function () use ($writer, $spreadsheet) {
                // Ensure output buffer is clean
                if (ob_get_length()) {
                    ob_end_clean();
                }
                $writer->save('php://output');

                // تحرير الذاكرة بعد الكتابة
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $writer);
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
            $response->headers->set('Cache-Control', 'max-age=0');

            Log::info('تم إرسال ملف Excel بنجاح');

            return $response;

        } catch (\Exception $e) {
            Log::error('خطأ أثناء تصدير السجلات: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // تحرير الذاكرة في حالة الخطأ
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
            gc_collect_cycles();

            throw $e;
        }
    }
}
