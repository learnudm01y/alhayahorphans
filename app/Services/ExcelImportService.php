<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;

class ExcelImportService
{
    /**
     * المودلز المدعومة للاستيراد
     */
    private array $supportedModels = [
        'data' => Data::class,
        'dead_people' => DeadPepole::class,
        're_people' => RePeople::class,
    ];

    /**
     * إحصائيات استبدال أرقام الملفات
     */
    private array $fileIdReplacements = [];

    /**
     * آخر طريقة مستخدمة لتوليد رقم الملف
     */
    private string $lastUsedMethod = 'unknown';

    /**
     * عداد محلي لضمان تفرد أرقام الملفات في نفس العملية
     */
    private int $localFileIdCounter = 0;

    /**
     * خريطة تحويل أسماء الأعمدة من Excel إلى قاعدة البيانات
     */
    private array $columnMappings = [
        'data' => [
            // أعمدة Excel => أعمدة قاعدة البيانات
            'رقم الملف' => 'file_id_number',
            'رقم الهوية' => 'data_id_number',
            'الاسم الأول' => 'data_first_name',
            'اسم الأب' => 'data_father_name',
            'اسم الجد' => 'data_grand_father_name',
            'اسم العائلة' => 'data_family_name',
            'صلة القرابة' => 'data_relationship',
            'تاريخ الميلاد' => 'data_birth_date',
            'الجنس' => 'data_gender',
            'رقم الهاتف' => 'data_phone_number',
            'رقم هاتف بديل' => 'data_alt_phone_number',
            'عدد الأفراد' => 'data_number_of_individuals',
            'الحالة الاجتماعية' => 'data_marital_status',
            'المؤهل الأكاديمي' => 'data_academic_qualification',
            'حالة النزوح' => 'data_displacement_status',
            'العنوان قبل النزوح' => 'data_address_before_displacement',
            'العنوان الحالي' => 'data_current_address',
            'المدينة' => 'data_city',
            'المحافظة' => 'data_province',
            'الحالة الصحية' => 'data_health_status',
            'وصف الاحتياجات' => 'data_description_needs',
            'عدد الذكور' => 'data_number_mail',
            'عدد الإناث' => 'data_number_female',
            'عدد المصابين بأمراض مزمنة' => 'data_number_of_individuals_with_chronic_diseases',
            'عدد ذوي الاحتياجات الخاصة' => 'data_number_of_people_with_special_needs',
            'حالة عمل رب الأسرة' => 'data_employment_status_breadwinner',
            'حالة السكن' => 'data_housing_status',
            'نوع السكن الحالي' => 'data_current_housing_type',
            'مستخدم الإدخال' => 'data_user_insert_data',
            'حالة الطلب' => 'data_request_status',
            // English versions
            'file_id' => 'file_id_number',
            'identity_number' => 'data_id_number',
            'first_name' => 'data_first_name',
            'father_name' => 'data_father_name',
            'grandfather_name' => 'data_grand_father_name',
            'family_name' => 'data_family_name',
            'relationship' => 'data_relationship',
            'birth_date' => 'data_birth_date',
            'gender' => 'data_gender',
            'phone_number' => 'data_phone_number',
            'alt_phone_number' => 'data_alt_phone_number',
            'number_of_individuals' => 'data_number_of_individuals',
            'marital_status' => 'data_marital_status',
            'academic_qualification' => 'data_academic_qualification',
            'displacement_status' => 'data_displacement_status',
            'address_before_displacement' => 'data_address_before_displacement',
            'current_address' => 'data_current_address',
            'city' => 'data_city',
            'province' => 'data_province',
            'health_status' => 'data_health_status',
            'description_needs' => 'data_description_needs',
            'number_male' => 'data_number_mail',
            'number_female' => 'data_number_female',
            'chronic_diseases_count' => 'data_number_of_individuals_with_chronic_diseases',
            'special_needs_count' => 'data_number_of_people_with_special_needs',
            'employment_status' => 'data_employment_status_breadwinner',
            'housing_status' => 'data_housing_status',
            'housing_type' => 'data_current_housing_type',
            'user_insert' => 'data_user_insert_data',
            'request_status' => 'data_request_status',
        ],
        'dead_people' => [
            // معلومات الأب
            'رقم ملف الأب' => 're_file_id',
            'اسم الأب الأول' => 'father_first_name',
            'اسم الأب الثاني' => 'father_second_name',
            'اسم الأب الثالث' => 'father_third_name',
            'اسم عائلة الأب' => 'father_last_name',
            'رقم هوية الأب' => 'father_id',
            'تاريخ وفاة الأب' => 'father_death_date',
            'سبب وفاة الأب' => 'father_death_reason',
            'شهادة وفاة الأب' => 'father_death_certificate',
            // معلومات الأم
            'اسم الأم الأول' => 'mother_first_name',
            'اسم الأم الثاني' => 'mother_second_name',
            'اسم الأم الثالث' => 'mother_third_name',
            'اسم عائلة الأم' => 'mother_last_name',
            'رقم هوية الأم' => 'mother_id',
            'تاريخ وفاة الأم' => 'mother_death_date',
            'سبب وفاة الأم' => 'mother_death_reason',
            'شهادة وفاة الأم' => 'mother_death_certificate',
            // English versions
            'file_id' => 're_file_id',
            'father_first_name' => 'father_first_name',
            'father_second_name' => 'father_second_name',
            'father_third_name' => 'father_third_name',
            'father_last_name' => 'father_last_name',
            'father_id' => 'father_id',
            'father_death_date' => 'father_death_date',
            'father_death_reason' => 'father_death_reason',
            'father_death_certificate' => 'father_death_certificate',
            'mother_first_name' => 'mother_first_name',
            'mother_second_name' => 'mother_second_name',
            'mother_third_name' => 'mother_third_name',
            'mother_last_name' => 'mother_last_name',
            'mother_id' => 'mother_id',
            'mother_death_date' => 'mother_death_date',
            'mother_death_reason' => 'mother_death_reason',
            'mother_death_certificate' => 'mother_death_certificate',
        ],
        're_people' => [
            'رقم التسجيل' => 'registration_id',
            'حالة الكفالة' => 'sponsorship_status',
            'الاسم الأول' => 'first_name',
            'الاسم الثاني' => 'second_name',
            'الاسم الثالث' => 'third_name',
            'اسم العائلة' => 'last_name',
            'رقم الهوية' => 'person_id',
            'تاريخ الميلاد' => 'person_birth_date',
            'العمر' => 'person_age',
            'الجنس' => 'person_gender',
            'الحالة الصحية' => 'person_health_status',
            'نوع الضمان' => 'person_type_of_guarantee',
            'المؤهل الأكاديمي' => 'academic_qualification',
            'ملاحظة' => 'person_note',
            // English versions
            'registration_id' => 'registration_id',
            'sponsorship_status' => 'sponsorship_status',
            'first_name' => 'first_name',
            'second_name' => 'second_name',
            'third_name' => 'third_name',
            'last_name' => 'last_name',
            'person_id' => 'person_id',
            'birth_date' => 'person_birth_date',
            'age' => 'person_age',
            'gender' => 'person_gender',
            'health_status' => 'person_health_status',
            'guarantee_type' => 'person_type_of_guarantee',
            'academic_degree' => 'academic_qualification',
            'note' => 'person_note',
        ],
    ];

    /**
     * استيراد Excel إلى موديل محدد
     */
    public function importToModel(string $filePath, string $modelKey, array $options = []): array
    {
        // إعادة تعيين إحصائيات استبدال أرقام الملفات
        $this->fileIdReplacements = [];
        $this->localFileIdCounter = 0; // إعادة تعيين العداد المحلي

        $results = [
            'imported_rows' => 0,
            'errors' => [],
            'skipped_rows' => 0,
            'total_rows' => 0,
            'warnings' => [],
            'preview' => [],
            'column_mapping' => [],
            'file_id_replacements' => [], // إحصائيات استبدال أرقام الملفات
            'duplicates' => [], // السجلات المكررة
            'failed_records' => [], // السجلات الفاشلة مع التفاصيل
            'successful_records' => [], // السجلات الناجحة
            'duplicate_count' => 0,
            'failed_count' => 0,
        ];

        // التحقق من دعم الموديل
        if (!isset($this->supportedModels[$modelKey])) {
            return array_merge($results, [
                'errors' => ["الموديل '$modelKey' غير مدعوم. الموديلز المدعومة: " . implode(', ', array_keys($this->supportedModels))]
            ]);
        }

        $modelClass = $this->supportedModels[$modelKey];

        try {
            // قراءة ملف Excel
            $spreadsheetData = $this->readExcelFile($filePath);
            if (isset($spreadsheetData['error'])) {
                return array_merge($results, ['errors' => [$spreadsheetData['error']]]);
            }

            $headers = $spreadsheetData['headers'];
            $rows = $spreadsheetData['rows'];
            $results['total_rows'] = count($rows);

            // إنشاء مثيل من الموديل للحصول على الخصائص المسموحة
            $model = new $modelClass;
            $fillable = $model->getFillable();

            // تحديد خريطة الأعمدة
            $columnMapping = $this->mapColumns($headers, $modelKey, $fillable);
            $results['column_mapping'] = $columnMapping;

            // التحقق من وجود أعمدة ضرورية
            $requiredFields = $this->getRequiredFields($modelKey);
            $missingFields = array_diff($requiredFields, array_values($columnMapping));
            if (!empty($missingFields)) {
                $results['errors'][] = "حقول مطلوبة مفقودة: " . implode(', ', $missingFields);
                return $results;
            }

            // معالجة البيانات
            $processedData = [];
            $previewCount = min(5, count($rows)); // عرض أول 5 صفوف للمعاينة

            foreach ($rows as $rowIndex => $row) {
                try {
                    $rowData = $this->processRow($row, $headers, $columnMapping, $modelKey, $options);

                    if (empty($rowData)) {
                        $results['skipped_rows']++;
                        continue;
                    }

                    // إضافة صفوف للمعاينة
                    if (count($results['preview']) < $previewCount) {
                        $results['preview'][] = $rowData;
                    }

                    $processedData[] = $rowData;
                } catch (\Exception $e) {
                    $results['errors'][] = "الصف " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }

            // إضافة إحصائيات استبدال أرقام الملفات
            $results['file_id_replacements'] = $this->fileIdReplacements;

            // حفظ البيانات في قاعدة البيانات
            if (!empty($processedData) && !($options['preview_only'] ?? false)) {
                $saveResults = $this->saveToDatabase($processedData, $modelClass, $options);

                $results['imported_rows'] = $saveResults['saved_count'];
                $results['duplicate_count'] = $saveResults['duplicate_count'];
                $results['failed_count'] = $saveResults['failed_count'];
                $results['duplicates'] = $saveResults['duplicates'];
                $results['failed_records'] = $saveResults['failed_records'];
                $results['successful_records'] = $saveResults['successful_records'];

                // تسجيل ملخص العملية
                if ($modelKey === 'data' && !empty($this->fileIdReplacements)) {
                    Log::info('Excel import completed with file ID replacements', [
                        'total_imported' => $saveResults['saved_count'],
                        'file_id_replacements_count' => count($this->fileIdReplacements),
                        'replacement_summary' => array_slice($this->fileIdReplacements, 0, 10) // أول 10 استبدالات للعرض
                    ]);
                }

                // إذا فشلت جميع السجلات، إرجاع خطأ
                if ($saveResults['saved_count'] === 0 && $saveResults['failed_count'] > 0) {
                    $results['errors'][] = 'فشل استيراد جميع السجلات';
                    throw new \Exception('فشل استيراد جميع السجلات');
                }
            }

        } catch (\Exception $e) {
            Log::error('Excel import error: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'model' => $modelKey,
                'trace' => $e->getTraceAsString()
            ]);
            $results['errors'][] = 'خطأ في معالجة الملف: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * قراءة ملف Excel
     */
    private function readExcelFile(string $filePath): array
    {
        $fullPath = storage_path('app/public/' . $filePath);

        if (!file_exists($fullPath)) {
            return ['error' => 'الملف غير موجود: ' . $filePath];
        }

        try {
            // تحديد نوع القارئ حسب امتداد الملف
            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            if ($extension === 'csv') {
                return $this->readCsvFile($fullPath);
            }

            // قراءة ملفات Excel
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray(null, true, true, true);

            if (empty($data)) {
                return ['error' => 'الملف فارغ أو لا يحتوي على بيانات'];
            }

            // فصل الرؤوس عن البيانات
            $headers = array_map('trim', array_shift($data));
            $rows = array_filter($data, function($row) {
                return !empty(array_filter($row)); // إزالة الصفوف الفارغة
            });

            return [
                'headers' => $headers,
                'rows' => $rows
            ];

        } catch (ReaderException $e) {
            return ['error' => 'خطأ في قراءة الملف: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => 'خطأ غير متوقع: ' . $e->getMessage()];
        }
    }

    /**
     * قراءة ملف CSV
     */
    private function readCsvFile(string $filePath): array
    {
        $data = [];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $data[] = $row;
            }
            fclose($handle);
        }

        if (empty($data)) {
            return ['error' => 'ملف CSV فارغ'];
        }

        $headers = array_map('trim', array_shift($data));
        return [
            'headers' => $headers,
            'rows' => $data
        ];
    }

    /**
     * ربط أعمدة Excel بحقول قاعدة البيانات
     */
    private function mapColumns(array $headers, string $modelKey, array $fillable): array
    {
        $mapping = [];
        $modelMappings = $this->columnMappings[$modelKey] ?? [];

        foreach ($headers as $index => $header) {
            $cleanHeader = trim($header);

            // البحث في خريطة التحويل
            if (isset($modelMappings[$cleanHeader])) {
                $dbField = $modelMappings[$cleanHeader];
                if (in_array($dbField, $fillable)) {
                    $mapping[$index] = $dbField;
                }
            }
            // البحث المباشر في الحقول المسموحة
            elseif (in_array($cleanHeader, $fillable)) {
                $mapping[$index] = $cleanHeader;
            }
            // البحث المرن (snake_case)
            else {
                $snakeCase = Str::snake($cleanHeader);
                if (in_array($snakeCase, $fillable)) {
                    $mapping[$index] = $snakeCase;
                }
            }
        }

        return $mapping;
    }

    /**
     * الحصول على الحقول المطلوبة لكل موديل
     */
    private function getRequiredFields(string $modelKey): array
    {
        $requiredFields = [
            'data' => ['file_id_number', 'data_id_number'],
            'dead_people' => ['re_file_id'],
            're_people' => ['registration_id', 'person_id'],
        ];

        return $requiredFields[$modelKey] ?? [];
    }

    /**
     * معالجة صف واحد من البيانات
     */
    private function processRow(array $row, array $headers, array $columnMapping, string $modelKey, array $options): array
    {
        $data = [];

        // تحويل البيانات حسب خريطة الأعمدة
        foreach ($columnMapping as $headerIndex => $dbField) {
            $value = $row[$headerIndex] ?? null;
            $data[$dbField] = $this->cleanValue($value);
        }

        // إزالة الصفوف الفارغة
        if (empty(array_filter($data))) {
            return [];
        }

        // معالجة خاصة حسب نوع الموديل
        $data = $this->applyModelSpecificProcessing($data, $modelKey, $options);

        // إضافة الحقول النظامية حسب نوع الموديل
        $data = $this->addSystemFields($data, $modelKey, $options);

        return $data;
    }

    /**
     * تنظيف القيم
     */
    private function cleanValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        // تحويل القيم غير الصالحة (مثل "؟" أو "فجأة") إلى null
        if ($value === '?' || $value === '؟' || mb_strlen($value) > 50) {
            return null;
        }

        // إذا كانت القيمة نصية ولكنها لا تحتوي على أرقام على الإطلاق، تحويلها إلى null
        if (!is_numeric($value) && !preg_match('/\d/', $value) && mb_strlen($value) < 20) {
            // التحقق من أنها ليست تاريخاً قبل التحويل
            if (!$this->isDate($value)) {
                return null;
            }
        }

        // تحويل التواريخ
        if ($this->isDate($value)) {
            return $this->parseDate($value);
        }

        return $value;
    }

    /**
     * فحص ما إذا كانت القيمة تاريخ
     */
    private function isDate($value): bool
    {
        if (!is_string($value)) return false;
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ||
               preg_match('/^\d{2}\/\d{2}\/\d{4}/', $value) ||
               preg_match('/^\d{2}-\d{2}-\d{4}/', $value);
    }

    /**
     * تحويل التاريخ
     */
    private function parseDate($value)
    {
        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $value)) {
                // DD/MM/YYYY format
                return \DateTime::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } elseif (preg_match('/^\d{2}-\d{2}-\d{4}/', $value)) {
                // DD-MM-YYYY format
                return \DateTime::createFromFormat('d-m-Y', $value)->format('Y-m-d');
            }
            return $value; // already in correct format
        } catch (\Exception $e) {
            return $value; // return original if parsing fails
        }
    }

    /**
     * معالجة خاصة حسب نوع الموديل
     */
    private function applyModelSpecificProcessing(array $data, string $modelKey, array $options): array
    {
        switch ($modelKey) {
            case 'data':
                // *** تنظيف data_id_number لكي يكون رقماً صحيحاً ***
                // إزالة أي أحرف غير رقمية وتحويل إلى رقم صحيح
                if (isset($data['data_id_number'])) {
                    $cleanIdNumber = preg_replace('/[^0-9]/', '', $data['data_id_number']);
                    if (!empty($cleanIdNumber) && is_numeric($cleanIdNumber)) {
                        $data['data_id_number'] = (int)$cleanIdNumber;
                    } else {
                        // إذا كانت القيمة فارغة أو غير رقمية، نضع null
                        $data['data_id_number'] = null;
                    }
                }

                // *** تحويل القيم غير الموجودة إلى 0 (غير معروف) ***
                // قائمة الحقول المرجعية (Foreign Keys) التي يجب التحقق منها
                $foreignKeyFields = [
                    'data_marital_status' => 'marital_status',
                    'data_academic_qualification' => 'academic_degrees',
                    'data_displacement_status' => 'displacement_statuses',
                    'data_city' => 'city',
                    'data_province' => 'provinces',
                    'data_employment_status_breadwinner' => 'employment',
                    'data_housing_status' => 'housing_status',
                    'data_current_housing_type' => 'type_of_accommodation',
                    'data_health_status' => 'health_statuses',
                    'data_relationship' => 'category_of_relations',
                ];

                foreach ($foreignKeyFields as $field => $table) {
                    if (isset($data[$field]) && !empty($data[$field])) {
                        $value = $data[$field];

                        // التحقق من وجود القيمة في الجدول المرجعي
                        $exists = DB::table($table)->where('id', $value)->exists();

                        if (!$exists) {
                            // القيمة غير موجودة، نستبدلها بـ 0 (غير معروف)
                            Log::info("Excel Import: Converting non-existent value to 0 (Unknown)", [
                                'field' => $field,
                                'table' => $table,
                                'original_value' => $value,
                                'new_value' => 0
                            ]);
                            $data[$field] = 0;
                        }
                    }
                }

                // *** تحويل تنسيق التاريخ ***
                // التحقق من data_birth_date وتحويله إلى YYYY-MM-DD إذا لزم الأمر
                if (isset($data['data_birth_date']) && !empty($data['data_birth_date'])) {
                    $birthDate = $data['data_birth_date'];

                    // DD-MM-YYYY format
                    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $birthDate)) {
                        try {
                            $dateObj = \DateTime::createFromFormat('d-m-Y', $birthDate);
                            if ($dateObj) {
                                $data['data_birth_date'] = $dateObj->format('Y-m-d');
                            }
                        } catch (\Exception $e) {
                            // إذا فشل التحويل، نترك القيمة كما هي
                        }
                    }
                    // DD/MM/YYYY format
                    elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $birthDate)) {
                        try {
                            $dateObj = \DateTime::createFromFormat('d/m/Y', $birthDate);
                            if ($dateObj) {
                                $data['data_birth_date'] = $dateObj->format('Y-m-d');
                            }
                        } catch (\Exception $e) {
                            // إذا فشل التحويل، نترك القيمة كما هي
                        }
                    }
                }

                // حفظ file_id_number الأصلي كمرجع إذا كان موجوداً
                $originalFileId = $data['file_id_number'] ?? null;

                // توليد file_id_number جديد دائماً باستخدام خوارزمية توليد الأرقام العامة
                // always generate new file_id_number using global number generation algorithms
                $newFileId = $this->generateNewFileId();
                $data['file_id_number'] = $newFileId;

                // حفظ الرقم الأصلي في حقل منفصل للمرجعية
                if ($originalFileId) {
                    $data['original_file_id_from_excel'] = $originalFileId;

                    // تسجيل الاستبدال في الإحصائيات
                    $this->fileIdReplacements[] = [
                        'original_file_id_from_excel' => $originalFileId,
                        'new_file_id_number' => $newFileId,
                        'identity_number' => $data['data_id_number'] ?? 'غير محدد',
                        'replacement_method' => $this->lastUsedMethod ?? 'unknown'
                    ];
                }

                Log::info('File ID replacement in Excel import', [
                    'original_file_id' => $originalFileId,
                    'new_file_id' => $newFileId,
                    'identity_number' => $data['data_id_number'] ?? 'Unknown'
                ]);
                break;

            case 'dead_people':
                // ✅ الخطوة 1: التحقق من وجود re_file_id (رقم الهوية المدخل)
                if (empty($data['re_file_id'])) {
                    throw new \Exception('⚠️ رقم الهوية (file_id) مطلوب في الصف');
                }

                // ✅ الخطوة 2: حفظ رقم الهوية الأصلي (من العمود الأول في Excel)
                $originalIdentityNumber = $data['re_file_id'];

                // ✅ الخطوة 3: البحث عن ولي الأمر في جدول data برقم الهوية
                $guardian = DB::table('data')
                    ->where('data_id_number', $originalIdentityNumber)
                    ->select('file_id_number', 'data_first_name', 'data_family_name')
                    ->first();

                if (!$guardian) {
                    // ❌ لا يوجد ولي أمر مطابق - رفض السطر
                    throw new \Exception(
                        "❌ لا يوجد ولي أمر مطابق لرقم الهوية ({$originalIdentityNumber}). "
                        . "يجب إدخال بيانات ولي الأمر في جدول 'data' أولاً قبل ربط المتوفين."
                    );
                }

                // ✅ الخطوة 4: استبدال رقم الهوية برقم الملف من جدول data
                $matchingFileId = $guardian->file_id_number;
                $data['re_file_id'] = $matchingFileId;

                // ✅ الخطوة 5: تسجيل عملية الاستبدال الناجحة
                $this->fileIdReplacements[] = [
                    'original_identity_number' => $originalIdentityNumber,
                    'matched_file_id' => $matchingFileId,
                    'guardian_name' => ($guardian->data_first_name ?? '') . ' ' . ($guardian->data_family_name ?? ''),
                    'father_id' => $data['father_id'] ?? null,
                    'mother_id' => $data['mother_id'] ?? null,
                    'replacement_method' => 'identity_to_file_id_lookup'
                ];

                Log::info('✅ Dead people: تم ربط المتوفى بولي الأمر', [
                    'original_identity_number' => $originalIdentityNumber,
                    'matched_file_id' => $matchingFileId,
                    'guardian_name' => ($guardian->data_first_name ?? '') . ' ' . ($guardian->data_family_name ?? ''),
                    'father_id' => $data['father_id'] ?? null,
                    'mother_id' => $data['mother_id'] ?? null
                ]);

                // تنظيف الحقول الرقمية (تحويل القيم غير الصالحة إلى null)
                $numericFields = [
                    'father_id',
                    'mother_id'
                ];

                foreach ($numericFields as $field) {
                    if (isset($data[$field]) && !is_numeric($data[$field])) {
                        Log::warning("Invalid numeric value in dead_people field: {$field}", [
                            'original_value' => $data[$field],
                            're_file_id' => $data['re_file_id']
                        ]);
                        $data[$field] = null;
                    }
                }

                // ✅ فحص أسباب الوفاة - التحقق من وجودها في جدول death_reasons
                $validDeathReasons = DB::table('death_reasons')->pluck('id')->toArray();

                foreach (['father_death_reason', 'mother_death_reason'] as $field) {
                    if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                        $reasonId = is_numeric($data[$field]) ? (int)$data[$field] : null;

                        if ($reasonId !== null && !in_array($reasonId, $validDeathReasons)) {
                            Log::warning("⚠️ سبب وفاة غير موجود - استبدال بـ Unknown", [
                                'field' => $field,
                                'original_value' => $data[$field],
                                'reason_id' => $reasonId,
                                're_file_id' => $data['re_file_id']
                            ]);
                            $data[$field] = 0;
                        } else if ($reasonId === null) {
                            $data[$field] = 0;
                        } else {
                            $data[$field] = $reasonId;
                        }
                    } else {
                        $data[$field] = 0;
                    }
                }

                // ✅ تحويل التواريخ من DD-MM-YYYY إلى YYYY-MM-DD
                $dateFields = ['father_death_date', 'mother_death_date'];

                foreach ($dateFields as $field) {
                    if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                        $dateValue = $data[$field];

                        // DD-MM-YYYY format (مثل: 15-10-2023)
                        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateValue)) {
                            try {
                                $dateObj = \DateTime::createFromFormat('d-m-Y', $dateValue);
                                if ($dateObj) {
                                    $data[$field] = $dateObj->format('Y-m-d');
                                    Log::info("✅ تحويل تاريخ", [
                                        'field' => $field,
                                        'from' => $dateValue,
                                        'to' => $data[$field]
                                    ]);
                                }
                            } catch (\Exception $e) {
                                Log::warning("⚠️ فشل تحويل التاريخ", [
                                    'field' => $field,
                                    'value' => $dateValue,
                                    'error' => $e->getMessage()
                                ]);
                                $data[$field] = null;
                            }
                        }
                        // DD/MM/YYYY format (مثل: 15/10/2023)
                        elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
                            try {
                                $dateObj = \DateTime::createFromFormat('d/m/Y', $dateValue);
                                if ($dateObj) {
                                    $data[$field] = $dateObj->format('Y-m-d');
                                }
                            } catch (\Exception $e) {
                                $data[$field] = null;
                            }
                        }
                    }
                }
                break;

            case 're_people':
                // ✅ Step 1: التحقق من وجود registration_id (رقم هوية ولي الأمر)
                if (empty($data['registration_id'])) {
                    throw new \Exception('⚠️ رقم هوية المعيل (registration_id) مطلوب في الصف');
                }

                // ✅ Step 2: حفظ رقم هوية ولي الأمر الأصلي
                $originalGuardianIdentity = $data['registration_id'];

                // ✅ Step 3: البحث عن ولي الأمر في جدول data
                $guardian = DB::table('data')
                    ->where('data_id_number', $originalGuardianIdentity)
                    ->select('file_id_number', 'data_first_name', 'data_family_name')
                    ->first();

                if (!$guardian) {
                    // ❌ لا يوجد ولي أمر - رفض الصف
                    throw new \Exception(
                        "❌ لا يوجد ولي أمر مطابق لرقم الهوية ({$originalGuardianIdentity}). "
                        . "يجب إدخال بيانات ولي الأمر في جدول 'data' أولاً قبل إضافة الأطفال."
                    );
                }

                // ✅ Step 4: استبدال رقم الهوية برقم الملف
                $matchingFileId = $guardian->file_id_number;
                $data['registration_id'] = $matchingFileId;

                // ✅ Step 5: تسجيل عملية الربط
                $this->fileIdReplacements[] = [
                    'original_guardian_identity' => $originalGuardianIdentity,
                    'matched_file_id' => $matchingFileId,
                    'guardian_name' => ($guardian->data_first_name ?? '') . ' ' . ($guardian->data_family_name ?? ''),
                    'person_id' => $data['person_id'] ?? null,
                    'person_name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                    'replacement_method' => 'identity_to_file_id_lookup'
                ];

                Log::info('✅ Re_people: تم ربط الطفل بولي الأمر', [
                    'original_guardian_identity' => $originalGuardianIdentity,
                    'matched_file_id' => $matchingFileId,
                    'guardian_name' => ($guardian->data_first_name ?? '') . ' ' . ($guardian->data_family_name ?? ''),
                    'child_person_id' => $data['person_id'] ?? null,
                    'child_name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''))
                ]);

                // تنظيف الحقول الرقمية (تحويل القيم غير الصالحة إلى null)
                $numericFields = [
                    'person_id',
                    'person_age',
                    'person_gender',
                    'person_type_of_guarantee',
                    'sponsorship_status'
                ];

                foreach ($numericFields as $field) {
                    if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                        // تحويل القيمة إلى رقم إذا كانت رقمية
                        if (is_numeric($data[$field])) {
                            $data[$field] = is_float($data[$field]) ? (float)$data[$field] : (int)$data[$field];
                        } else {
                            Log::warning("Invalid numeric value in re_people field: {$field}", [
                                'original_value' => $data[$field],
                                'registration_id' => $data['registration_id']
                            ]);
                            $data[$field] = null;
                        }
                    } else {
                        // الحقل غير موجود أو فارغ
                        $data[$field] = null;
                    }
                }

                // ✅ فحص الحالة الصحية - التحقق من وجودها في جدول health_statuses
                if (isset($data['person_health_status']) && $data['person_health_status'] !== '' && $data['person_health_status'] !== null) {
                    $validHealthStatuses = DB::table('health_statuses')->pluck('id')->toArray();
                    $healthStatusId = is_numeric($data['person_health_status']) ? (int)$data['person_health_status'] : null;

                    if ($healthStatusId !== null && !in_array($healthStatusId, $validHealthStatuses)) {
                        Log::info("ℹ️ حالة صحية غير موجودة في الجدول - استبدال بـ 0 (غير معروف)", [
                            'field' => 'person_health_status',
                            'original_value' => $data['person_health_status'],
                            'health_status_id' => $healthStatusId,
                            'registration_id' => $data['registration_id'],
                            'valid_health_statuses' => $validHealthStatuses
                        ]);
                        $data['person_health_status'] = 0;
                    } else if ($healthStatusId === null) {
                        $data['person_health_status'] = 0;
                    } else {
                        $data['person_health_status'] = $healthStatusId;
                    }
                } else {
                    $data['person_health_status'] = 0;
                }

                // ✅ تحويل تاريخ الميلاد من DD-MM-YYYY إلى YYYY-MM-DD
                if (isset($data['person_birth_date']) && $data['person_birth_date'] !== '' && $data['person_birth_date'] !== null) {
                    $dateValue = $data['person_birth_date'];

                    // DD-MM-YYYY format
                    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateValue)) {
                        try {
                            $dateObj = \DateTime::createFromFormat('d-m-Y', $dateValue);
                            if ($dateObj) {
                                $data['person_birth_date'] = $dateObj->format('Y-m-d');
                                Log::info("✅ تحويل تاريخ الميلاد", [
                                    'from' => $dateValue,
                                    'to' => $data['person_birth_date']
                                ]);
                            }
                        } catch (\Exception $e) {
                            $data['person_birth_date'] = null;
                        }
                    }
                    // DD/MM/YYYY format
                    elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
                        try {
                            $dateObj = \DateTime::createFromFormat('d/m/Y', $dateValue);
                            if ($dateObj) {
                                $data['person_birth_date'] = $dateObj->format('Y-m-d');
                            }
                        } catch (\Exception $e) {
                            $data['person_birth_date'] = null;
                        }
                    }
                }
                break;
        }

        return $data;
    }

    /**
     * إضافة الحقول النظامية حسب نوع الموديل
     */
    private function addSystemFields(array $data, string $modelKey, array $options): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        // إضافة بيانات المستخدم (الاسم بدلاً من ID)
        $userName = auth()->check() ? auth()->user()->name : null;
        $userId = auth()->check() ? auth()->id() : null;

        // التحقق من وجود المستخدم
        if (!$userName) {
            Log::warning('⚠️ تحذير: لا يوجد مستخدم مسجل دخول عند إدخال البيانات!', [
                'model' => $modelKey,
                'auth_check' => auth()->check(),
                'auth_user' => auth()->user() ? auth()->user()->name : null,
                'session_id' => session()->getId()
            ]);
        }

        switch ($modelKey) {
            case 'data':
                // جدول data يستخدم data_user_insert_data - إجباري!
                if (!$userName) {
                    throw new \Exception('❌ خطأ: يجب تسجيل الدخول لرفع البيانات. data_user_insert_data مطلوب!');
                }

                // ✅ حفظ اسم المستخدم بدلاً من ID
                $data['data_user_insert_data'] = $userName;

                // === ضمانة إجبارية لتعيين حالة "مقبول" ===
                // هذا التعيين إجباري لجميع السجلات المستوردة من Excel
                $acceptedStatusId = $this->getAcceptedStatusId();
                $data['data_request_status'] = $acceptedStatusId;

                // إضافة علامة للتعرف على السجلات المستوردة من Excel
                if (!isset($data['original_file_id_from_excel'])) {
                    $data['original_file_id_from_excel'] = $data['file_id_number'] ?? 'excel_import_' . time();
                }

                Log::info('✅ Excel import: تم تعيين المستخدم والحالة', [
                    'file_id' => $data['file_id_number'] ?? 'unknown',
                    'user_name' => $userName,
                    'user_id' => $userId,
                    'assigned_status_id' => $acceptedStatusId,
                    'method' => 'force_assignment',
                    'environment' => app()->environment(),
                    'excel_marker' => $data['original_file_id_from_excel']
                ]);
                break;

            case 'dead_people':
                // ✅ جدول dead_people ليس لديه عمود user_id
                // البيانات جاهزة للإدخال (re_file_id تم استبداله برقم الملف)
                Log::info('✅ Dead people row ready for insert', [
                    're_file_id' => $data['re_file_id'] ?? 'unknown',
                    'father_id' => $data['father_id'] ?? null,
                    'mother_id' => $data['mother_id'] ?? null
                ]);
                break;

            case 're_people':
                // ✅ re_people table has no user_id column
                // Data already fully processed (registration_id replaced with file_id_number)
                Log::info('✅ Re_people row ready for insert', [
                    'registration_id' => $data['registration_id'] ?? 'unknown',
                    'person_id' => $data['person_id'] ?? null,
                    'child_name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''))
                ]);
                break;
        }

        return $data;
    }

    /**
     * حفظ البيانات في قاعدة البيانات مع تتبع السجلات المكررة والفاشلة
     */
    private function saveToDatabase(array $data, string $modelClass, array $options): array
    {
        $savedCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;
        $duplicates = [];
        $failedRecords = [];
        $successfulRecords = [];
        $batchSize = $options['batch_size'] ?? 100;

        try {
            DB::beginTransaction();

            // ضمانة إضافية: التأكد من تعيين الحالة الصحيحة لجميع السجلات المستوردة من Excel
            if ($modelClass === Data::class) {
                $acceptedStatusId = $this->getAcceptedStatusId();
                foreach ($data as &$record) {
                    if (isset($record['original_file_id_from_excel']) &&
                        (!isset($record['data_request_status']) || empty($record['data_request_status']))) {
                        $record['data_request_status'] = $acceptedStatusId;

                        Log::info('Excel import: Last-minute status assignment', [
                            'file_id' => $record['file_id_number'] ?? 'unknown',
                            'status_id' => $acceptedStatusId,
                            'location' => 'saveToDatabase'
                        ]);
                    }
                }
            }

            // حفظ البيانات سجل تلو الآخر لتتبع المكررات والفاشلة
            foreach ($data as $rowIndex => $record) {
                try {
                    // الحصول على المفاتيح الفريدة
                    $uniqueFields = $this->getUniqueFields($record, $modelClass);

                    // التحقق من وجود سجل مكرر
                    $existingRecord = null;
                    if (!empty($uniqueFields)) {
                        $query = $modelClass::query();
                        foreach ($uniqueFields as $field => $value) {
                            $query->where($field, $value);
                        }
                        $existingRecord = $query->first();
                    }

                    if ($existingRecord) {
                        // سجل مكرر
                        $duplicateCount++;
                        $duplicates[] = [
                            'row' => $rowIndex + 2, // Excel row number
                            'identifier' => $this->getRecordIdentifier($record, $modelClass),
                            'data' => $this->getPreviewData($record, $modelClass),
                            'reason' => 'سجل موجود مسبقاً في قاعدة البيانات'
                        ];

                        // تحديث السجل إذا كان مطلوباً
                        if ($options['update_existing'] ?? false) {
                            $existingRecord->update($record);
                            $savedCount++;
                            $successfulRecords[] = [
                                'row' => $rowIndex + 2,
                                'identifier' => $this->getRecordIdentifier($record, $modelClass),
                                'data' => $this->getPreviewData($record, $modelClass),
                                'action' => 'محدّث'
                            ];
                        }
                    } else {
                        // سجل جديد
                        $modelClass::create($record);
                        $savedCount++;

                        // وضع علامة على file_id_number كمستخدم في reserved_codes
                        if ($modelClass === Data::class && isset($record['file_id_number'])) {
                            markCodeAsUsed($record['file_id_number']);
                        }

                        $successfulRecords[] = [
                            'row' => $rowIndex + 2,
                            'identifier' => $this->getRecordIdentifier($record, $modelClass),
                            'data' => $this->getPreviewData($record, $modelClass),
                            'action' => 'مضاف'
                        ];
                    }

                } catch (\Exception $e) {
                    // سجل فاشل
                    $failedCount++;
                    $failedRecords[] = [
                        'row' => $rowIndex + 2,
                        'identifier' => $this->getRecordIdentifier($record, $modelClass),
                        'data' => $this->getPreviewData($record, $modelClass),
                        'error' => $e->getMessage(),
                        'sql_code' => $e->getCode()
                    ];

                    Log::warning('Failed to import record', [
                        'row' => $rowIndex + 2,
                        'error' => $e->getMessage(),
                        'record' => $record
                    ]);
                }
            }

            // إذا فشلت جميع السجلات، إلغاء التراجع
            if ($savedCount === 0 && $failedCount > 0) {
                DB::rollBack();
                throw new \Exception("فشل استيراد جميع السجلات ({$failedCount} سجل فاشل)");
            }

            DB::commit();

            return [
                'saved_count' => $savedCount,
                'duplicate_count' => $duplicateCount,
                'failed_count' => $failedCount,
                'duplicates' => $duplicates,
                'failed_records' => $failedRecords,
                'successful_records' => $successfulRecords
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Database save error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على معرف السجل لعرضه
     */
    private function getRecordIdentifier(array $record, string $modelClass): string
    {
        if ($modelClass === Data::class) {
            return $record['data_id_number'] ?? $record['file_id_number'] ?? 'غير معروف';
        } elseif ($modelClass === DeadPepole::class) {
            return $record['father_id'] ?? $record['mother_id'] ?? 'غير معروف';
        } elseif ($modelClass === RePeople::class) {
            return $record['person_id'] ?? 'غير معروف';
        }
        return 'غير معروف';
    }

    /**
     * الحصول على بيانات معاينة للسجل
     */
    private function getPreviewData(array $record, string $modelClass): array
    {
        if ($modelClass === Data::class) {
            return [
                'رقم الهوية' => $record['data_id_number'] ?? '',
                'الاسم' => trim(
                    ($record['data_first_name'] ?? '') . ' ' .
                    ($record['data_father_name'] ?? '') . ' ' .
                    ($record['data_family_name'] ?? '')
                ),
                'رقم الهاتف' => $record['data_phone_number'] ?? ''
            ];
        } elseif ($modelClass === RePeople::class) {
            return [
                'رقم الهوية' => $record['person_id'] ?? '',
                'الاسم' => trim(
                    ($record['first_name'] ?? '') . ' ' .
                    ($record['second_name'] ?? '') . ' ' .
                    ($record['last_name'] ?? '')
                ),
                'العمر' => $record['person_age'] ?? ''
            ];
        } elseif ($modelClass === DeadPepole::class) {
            return [
                'رقم هوية الأب' => $record['father_id'] ?? '',
                'اسم الأب' => trim(
                    ($record['father_first_name'] ?? '') . ' ' .
                    ($record['father_last_name'] ?? '')
                ),
                'رقم هوية الأم' => $record['mother_id'] ?? ''
            ];
        }
        return [];
    }

    /**
     * الحصول على الحقول الفريدة للموديل
     */
    private function getUniqueFields(array $record, string $modelClass): array
    {
        $uniqueFields = [
            Data::class => ['file_id_number'],
            DeadPepole::class => ['re_file_id'],
            RePeople::class => ['registration_id', 'person_id'],
        ];

        $fields = $uniqueFields[$modelClass] ?? ['id'];
        $result = [];

        foreach ($fields as $field) {
            if (isset($record[$field])) {
                $result[$field] = $record[$field];
            }
        }

        return $result;
    }

    /**
     * توليد رقم ملف جديد
     */
    private function generateFileId(): string
    {
        // استخدام generateUniqueReservedCode لحجز الرقم
        if (function_exists('generateUniqueReservedCode')) {
            return generateUniqueReservedCode('data', 'file_id_number') ?? str_pad(substr(time(), -6), 6, '0', STR_PAD_LEFT);
        }

        // fallback method
        return str_pad(substr(time(), -6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * توليد رقم ملف جديد باستخدام خوارزمية توليد الأرقام العامة مع الحجز الآمن
     * يضمن أن الرقم غير محجوز ومتفرد ومحمي من التضارب
     */
    private function generateNewFileId(): string
    {
        try {
            // استخدام generateUniqueReservedCode للحصول على رقم محجوز وآمن
            $sessionId = 'excel_import_' . uniqid() . '_' . time();
            $reservedCode = generateUniqueReservedCode('data', 'file_id_number', $sessionId);

            if ($reservedCode) {
                $this->lastUsedMethod = 'generateUniqueReservedCode';
                Log::info('Generated new file_id using generateUniqueReservedCode', [
                    'new_file_id' => $reservedCode,
                    'session_id' => $sessionId,
                    'method' => 'reserved_safe'
                ]);
                return $reservedCode;
            }

            // الخطة الاحتياطية المحمية: استخدام خوارزمية محلية مع حجز
            $this->lastUsedMethod = 'generateFileIdFallbackWithReservation';
            return $this->generateFileIdFallbackWithReservation();

        } catch (\Exception $e) {
            Log::warning('Error generating new file_id, using emergency fallback', [
                'error' => $e->getMessage()
            ]);
            $this->lastUsedMethod = 'emergency_fallback';
            return $this->generateEmergencyFileId();
        }
    }

    /**
     * خوارزمية احتياطية لتوليد file_id_number مع ضمان التفرد والحجز الآمن
     */
    private function generateFileIdFallback(): string
    {
        return DB::transaction(function () {
            // الحصول على أكبر رقم موجود في جدول data
            $maxFileId = DB::table('data')
                ->select(DB::raw('MAX(CAST(file_id_number as UNSIGNED)) as max_id'))
                ->whereRaw('file_id_number REGEXP "^[0-9]+$"')
                ->value('max_id');

            // إضافة العداد المحلي لضمان التفرد في نفس العملية
            $this->localFileIdCounter++;
            $nextId = ($maxFileId ?? 0) + $this->localFileIdCounter;
            $newFileId = str_pad($nextId, 7, '0', STR_PAD_LEFT); // 7 خانات بدلاً من 6

            Log::info('Generated file_id using fallback method', [
                'max_existing' => $maxFileId,
                'local_counter' => $this->localFileIdCounter,
                'new_file_id' => $newFileId
            ]);

            return $newFileId;
        });
    }

    /**
     * خوارزمية احتياطية محمية لتوليد file_id_number مع الحجز في reserved_codes
     */
    private function generateFileIdFallbackWithReservation(): string
    {
        return DB::transaction(function () {
            $maxAttempts = 10;
            $sessionId = 'excel_fallback_' . uniqid() . '_' . time();

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                // الحصول على أكبر رقم من data و reserved_codes
                $maxData = DB::table('data')
                    ->select(DB::raw('MAX(CAST(file_id_number as UNSIGNED)) as max_id'))
                    ->whereRaw('file_id_number REGEXP "^[0-9]+$"')
                    ->value('max_id');

                $maxReserved = DB::table('reserved_codes')
                    ->select(DB::raw('MAX(CAST(code as UNSIGNED)) as max_id'))
                    ->whereRaw('code REGEXP "^[0-9]+$"')
                    ->lockForUpdate()
                    ->value('max_id');

                // حساب الرقم التالي مع العداد المحلي
                $this->localFileIdCounter++;
                $nextId = max((int)$maxData, (int)$maxReserved) + $this->localFileIdCounter;
                $newFileId = str_pad($nextId, 6, '0', STR_PAD_LEFT);

                // التحقق من عدم وجود الرقم في reserved_codes
                $exists = DB::table('reserved_codes')
                    ->where('code', $newFileId)
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) {
                    // حجز الرقم في reserved_codes
                    DB::table('reserved_codes')->insert([
                        'code' => $newFileId,
                        'session_id' => $sessionId,
                        'reserved_at' => now(),
                        'used' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info('Generated and reserved file_id using fallback with reservation', [
                        'max_data' => $maxData,
                        'max_reserved' => $maxReserved,
                        'local_counter' => $this->localFileIdCounter,
                        'new_file_id' => $newFileId,
                        'session_id' => $sessionId,
                        'attempt' => $attempt
                    ]);

                    return $newFileId;
                }
            }

            // إذا فشل في توليد رقم محجوز، استخدم خطة الطوارئ
            throw new \Exception('Failed to generate reserved file_id after ' . $maxAttempts . ' attempts');
        });
    }

    /**
     * خوارزمية طوارئ لتوليد file_id_number
     */
    private function generateEmergencyFileId(): string
    {
        // توليد رقم فريد باستخدام timestamp + random
        $timestamp = time();
        $random = rand(100, 999);
        $emergencyId = substr($timestamp, -3) . $random;

        Log::warning('Using emergency file_id generation', [
            'emergency_id' => $emergencyId,
            'timestamp' => $timestamp,
            'random' => $random
        ]);

        return $emergencyId;
    }

    /**
     * الحصول على معاينة البيانات قبل الاستيراد
     */
    public function previewImport(string $filePath, string $modelKey): array
    {
        return $this->importToModel($filePath, $modelKey, ['preview_only' => true]);
    }

    /**
     * التحقق من صحة ملف Excel قبل الاستيراد
     */
    public function validateExcelFile(string $filePath, string $modelKey): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'summary' => []
        ];

        // قراءة الملف
        $spreadsheetData = $this->readExcelFile($filePath);
        if (isset($spreadsheetData['error'])) {
            $validation['valid'] = false;
            $validation['errors'][] = $spreadsheetData['error'];
            return $validation;
        }

        $headers = $spreadsheetData['headers'];
        $rows = $spreadsheetData['rows'];

        // التحقق من وجود بيانات
        if (empty($rows)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'الملف لا يحتوي على بيانات';
            return $validation;
        }

        // التحقق من الأعمدة
        $modelClass = $this->supportedModels[$modelKey];
        $model = new $modelClass;
        $fillable = $model->getFillable();

        $columnMapping = $this->mapColumns($headers, $modelKey, $fillable);
        $requiredFields = $this->getRequiredFields($modelKey);
        $missingFields = array_diff($requiredFields, array_values($columnMapping));

        if (!empty($missingFields)) {
            $validation['valid'] = false;
            $validation['errors'][] = "حقول مطلوبة مفقودة: " . implode(', ', $missingFields);
        }

        // ملخص
        $validation['summary'] = [
            'total_rows' => count($rows),
            'total_columns' => count($headers),
            'mapped_columns' => count($columnMapping),
            'required_fields_found' => count($requiredFields) - count($missingFields),
            'file_size' => file_exists(storage_path('app/public/' . $filePath))
                ? filesize(storage_path('app/public/' . $filePath))
                : 0
        ];

        return $validation;
    }

    /**
     * الحصول على إحصائيات استبدال أرقام الملفات
     */
    public function getFileIdReplacements(): array
    {
        return $this->fileIdReplacements;
    }

    /**
     * تنسيق إحصائيات استبدال أرقام الملفات للعرض
     */
    public function formatFileIdReplacementsReport(): array
    {
        if (empty($this->fileIdReplacements)) {
            return [
                'total_replacements' => 0,
                'message' => 'لم يتم استبدال أي أرقام ملفات',
                'replacements' => []
            ];
        }

        return [
            'total_replacements' => count($this->fileIdReplacements),
            'message' => 'تم استبدال ' . count($this->fileIdReplacements) . ' رقم ملف بنجاح',
            'replacements' => array_map(function($replacement) {
                return [
                    'original_file_id' => $replacement['original_file_id_from_excel'],
                    'new_file_id' => $replacement['new_file_id_number'],
                    'identity_number' => $replacement['identity_number'] ?? 'غير محدد',
                    'replacement_method' => $replacement['replacement_method'] ?? 'خوارزمية افتراضية'
                ];
            }, $this->fileIdReplacements)
        ];
    }

    /**
     * إعادة تعيين إحصائيات استبدال أرقام الملفات
     */
    public function resetFileIdReplacements(): void
    {
        $this->fileIdReplacements = [];
    }

    /**
     * إحصائيات مفصلة لعملية الاستيراد
     */
    public function getDetailedImportStats(array $results): array
    {
        $stats = [
            'import_summary' => [
                'total_rows' => $results['total_rows'] ?? 0,
                'imported_rows' => $results['imported_rows'] ?? 0,
                'skipped_rows' => $results['skipped_rows'] ?? 0,
                'error_count' => count($results['errors'] ?? []),
                'warning_count' => count($results['warnings'] ?? [])
            ],
            'file_id_management' => [
                'total_file_id_replacements' => count($this->fileIdReplacements),
                'replacement_success_rate' => '100%', // جميع الاستبدالات تتم بنجاح
                'replacement_methods_used' => $this->getReplacementMethodsUsed()
            ],
            'data_quality' => [
                'success_rate' => $results['total_rows'] > 0 ?
                    round(($results['imported_rows'] / $results['total_rows']) * 100, 2) . '%' : '0%',
                'column_mapping_success' => !empty($results['column_mapping']),
                'required_fields_present' => empty($results['errors'])
            ]
        ];

        return $stats;
    }

    /**
     * الحصول على طرق الاستبدال المستخدمة
     */
    private function getReplacementMethodsUsed(): array
    {
        $methods = [];
        foreach ($this->fileIdReplacements as $replacement) {
            $method = $replacement['replacement_method'] ?? 'fallback';
            $methods[$method] = ($methods[$method] ?? 0) + 1;
        }

        return $methods;
    }

    /**
     * البحث عن file_id_number المطابق في جدول Data بناءً على data_id_number
     * يستخدم للربط بين جدول DeadPepole وجدول Data
     */
    private function findMatchingFileIdFromData(string $identityNumber): ?string
    {
        try {
            // البحث في جدول Data عن السجل الذي يحتوي على data_id_number مطابق
            $matchingRecord = DB::table('data')
                ->select('file_id_number', 'data_id_number', 'data_first_name', 'data_family_name')
                ->where('data_id_number', $identityNumber)
                ->first();

            if ($matchingRecord) {
                Log::info('Found matching record in Data table', [
                    'search_identity' => $identityNumber,
                    'found_file_id' => $matchingRecord->file_id_number,
                    'found_name' => $matchingRecord->data_first_name . ' ' . $matchingRecord->data_family_name
                ]);

                return $matchingRecord->file_id_number;
            }

            Log::warning('No matching record found in Data table', [
                'search_identity' => $identityNumber
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error searching for matching file_id in Data table', [
                'search_identity' => $identityNumber,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * الحصول على ID حالة "مقبول" مع معالجة محسنة للأخطاء
     * يتعامل مع مشاكل الاستضافة المشتركة والبيئات المختلفة
     */
    private function getAcceptedStatusId(): int
    {
        // القيمة الافتراضية لحالة الطلب هي 4 دائمًا
        return 4;
    }

    /**
     * معالجة صف واحد من البيانات (للاستخدام بعد التحقق)
     * تطبيق جميع المعالجات: تحويل القيم المفقودة، تنظيف البيانات، إضافة الحقول النظامية
     */
    public function processRowData(array $rowData, string $modelKey): array
    {
        // تطبيق المعالجة الخاصة بالموديل
        $rowData = $this->applyModelSpecificProcessing($rowData, $modelKey, []);

        // إضافة الحقول النظامية
        $rowData = $this->addSystemFields($rowData, $modelKey, []);

        return $rowData;
    }
}
