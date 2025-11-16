<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelValidationService
{
    protected $errors = [];
    protected $warnings = [];
    protected $validRows = [];
    protected $invalidRows = [];
    protected $statistics = [
        'total_rows' => 0,
        'valid_rows' => 0,
        'invalid_rows' => 0,
        'duplicate_rows' => 0
    ];

    /**
     * فحص شامل للملف قبل الإدخال
     */
    public function validateExcelFile($filePath, $targetTable, $options = [])
    {
        try {
            // 1. التحقق من وجود الملف
            if (!file_exists($filePath)) {
                throw new \Exception('الملف غير موجود: ' . $filePath);
            }

            Log::info('📂 بدء قراءة ملف Excel', ['path' => $filePath, 'table' => $targetTable]);

            // 2. قراءة الملف
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            Log::info('📊 تم قراءة الصفوف', ['total_rows_with_header' => count($rows)]);

            if (empty($rows)) {
                throw new \Exception('الملف فارغ أو لا يحتوي على بيانات');
            }

            // 3. استخراج العناوين والبيانات
            $headers = array_shift($rows);
            $this->statistics['total_rows'] = count($rows);

            Log::info('📋 العناوين والصفوف', [
                'headers' => $headers,
                'data_rows_count' => count($rows),
                'first_row_sample' => $rows[0] ?? null
            ]);

            // 4. الحصول على تعريف الجدول من قاعدة البيانات
            $tableStructure = $this->getTableStructure($targetTable);
            $columnMapping = $this->getColumnMapping($targetTable);

            Log::info('🗂️ تعيين الأعمدة', ['mapping' => $columnMapping]);

            // 5. التحقق من العناوين
            $this->validateHeaders($headers, $columnMapping, $targetTable);

            // 6. فحص كل صف
            $rowNumber = 2; // نبدأ من 2 لأن 1 هو العناوين
            foreach ($rows as $row) {
                $this->validateRow($row, $headers, $tableStructure, $columnMapping, $targetTable, $rowNumber);
                $rowNumber++;

                // ⚡ تحرير الذاكرة كل 1000 صف
                if ($rowNumber % 1000 === 0) {
                    gc_collect_cycles(); // تشغيل garbage collector
                    Log::info("🔄 تحرير الذاكرة بعد معالجة {$rowNumber} صف");
                }
            }

            // تحرير الذاكرة بعد انتهاء المعالجة
            unset($rows, $spreadsheet, $worksheet);
            gc_collect_cycles();

            Log::info('✅ انتهى فحص الصفوف', [
                'valid_rows' => $this->statistics['valid_rows'],
                'invalid_rows' => $this->statistics['invalid_rows'],
                'total_rows' => $this->statistics['total_rows'],
                'memory_used' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB'
            ]);

            // 7. فحص التكرار
            $this->checkDuplicates($targetTable);

            // 8. إعداد التقرير النهائي
            $report = $this->prepareValidationReport();

            Log::info('📋 التقرير النهائي', ['report' => $report]);

            return $report;

        } catch (\Exception $e) {
            Log::error('❌ خطأ في التحقق', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $this->errors,
                'can_proceed' => false,
                'statistics' => $this->statistics
            ];
        }
    }

    /**
     * الحصول على هيكل الجدول من قاعدة البيانات
     */
    protected function getTableStructure($tableName)
    {
        $columns = DB::select("DESCRIBE {$tableName}");
        $structure = [];

        foreach ($columns as $column) {
            $structure[$column->Field] = [
                'type' => $column->Type,
                'null' => $column->Null === 'YES',
                'key' => $column->Key,
                'default' => $column->Default,
                'extra' => $column->Extra
            ];
        }

        return $structure;
    }

    /**
     * الحصول على تعيين الأعمدة حسب الجدول
     */
    protected function getColumnMapping($tableName)
    {
        $mappings = [
            'data' => [
                // Arabic headers
                'رقم الهوية' => 'data_id_number',
                'الاسم الكامل' => 'data_first_name',
                'تاريخ الميلاد' => 'data_birth_date',
                'الجنس' => 'data_gender',
                'رقم الهاتف' => 'data_phone_number',
                'العنوان' => 'data_current_address',
                'المحافظة' => 'data_province',
                'الحالة الاجتماعية' => 'data_marital_status',
                'الحالة الصحية' => 'data_health_status',
                'المؤهل العلمي' => 'data_academic_qualification',
                'الحالة الوظيفية' => 'data_employment_status_breadwinner',
                'عدد أفراد الأسرة' => 'data_number_of_individuals',
                'نوع السكن' => 'data_current_housing_type',
                'ملاحظات' => 'data_description_needs',
                // English headers (direct column names - keep as-is!)
                'data_id_number' => 'data_id_number',
                'file_id_number' => 'file_id_number',
                'data_section_id' => 'data_section_id',
                'data_first_name' => 'data_first_name',
                'data_father_name' => 'data_father_name',
                'data_grand_father_name' => 'data_grand_father_name',
                'data_family_name' => 'data_family_name',
                'data_relationship' => 'data_relationship',
                'data_birth_date' => 'data_birth_date',
                'data_gender' => 'data_gender',
                'data_phone_number' => 'data_phone_number',
                'data_alt_phone_number' => 'data_alt_phone_number',
                'data_number_of_individuals' => 'data_number_of_individuals',
                'data_marital_status' => 'data_marital_status',
                'data_academic_qualification' => 'data_academic_qualification',
                'data_displacement_status' => 'data_displacement_status',
                'data_address_before_displacement' => 'data_address_before_displacement',
                'data_current_adress' => 'data_current_address',
                'data_city' => 'data_city',
                'data_province' => 'data_province',
                'data_health_status' => 'data_health_status',
                'data_description_needs' => 'data_description_needs',
                'data_number_mail' => 'data_number_mail',
                'data_number_female' => 'data_number_female',
                'data_number_of_individuals_with_chronic_diseases' => 'data_number_of_individuals_with_chronic_diseases',
                'data_number_of_people_with_special_needs' => 'data_number_of_people_with_special_needs',
                'data_employment_status_breadwinner' => 'data_employment_status_breadwinner',
                'data_housing_status' => 'data_housing_status',
                'data_current_housing_type' => 'data_current_housing_type'
            ],
            'dead_people' => [
                'رقم الهوية' => 'id',
                'رقم هوية الشخص المرتبط' => 'person_id',
                'نوع العلاقة' => 'relationship_type',
                'سبب وفاة الأب' => 'father_death_reason',
                'سبب وفاة الأم' => 'mother_death_reason',
                'رقم هوية الأب' => 'father_id',
                'رقم هوية الأم' => 'mother_id',
                'ملاحظات' => 'notes'
            ],
            're_people' => [
                'رقم هوية المعيل' => 'registration_id',  // رقم هوية الأب/المعيل الذي سيتم البحث عنه في data
                'رقم الهوية' => 'id',
                'رقم هوية الشخص الرئيسي' => 'person_id',
                'الاسم' => 'person_name',
                'العمر' => 'person_age',
                'الجنس' => 'person_gender',
                'العلاقة' => 'person_relationship',
                'الحالة الصحية' => 'person_health_status',
                'المؤهل العلمي' => 'academic_qualification',
                'الحالة الوظيفية' => 'person_work_status',
                'ملاحظات' => 'notes'
            ]
        ];

        return $mappings[$tableName] ?? [];
    }

    /**
     * التحقق من صحة العناوين
     */
    protected function validateHeaders($headers, $columnMapping, $tableName)
    {
        $requiredColumns = array_keys($columnMapping);
        $missingColumns = [];

        foreach ($requiredColumns as $required) {
            if (!in_array($required, $headers)) {
                $missingColumns[] = $required;
            }
        }

        if (!empty($missingColumns)) {
            $this->errors[] = [
                'type' => 'headers',
                'severity' => 'critical',
                'message' => 'أعمدة مفقودة في الملف',
                'details' => 'الأعمدة التالية مطلوبة ولكنها غير موجودة: ' . implode(', ', $missingColumns),
                'solution' => 'يرجى التأكد من وجود جميع الأعمدة المطلوبة في الملف'
            ];
        }
    }

    /**
     * التحقق من صحة الصف
     */
    protected function validateRow($row, $headers, $tableStructure, $columnMapping, $tableName, $rowNumber)
    {
        $rowData = [];
        $rowErrors = [];

        // تحويل الصف إلى array associative
        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? null;

            // محاولة الحصول على اسم العمود من mapping أولاً
            $dbColumn = $columnMapping[$header] ?? null;

            // إذا لم يوجد في mapping، تحقق إذا كان اسم العمود موجود مباشرة في tableStructure
            if (!$dbColumn && isset($tableStructure[$header])) {
                $dbColumn = $header;
            }

            if ($dbColumn) {
                $rowData[$dbColumn] = $value;
            }
        }

        // Log first row for debugging
        if ($rowNumber === 2) {
            Log::info('🔍 فحص أول صف', [
                'row_number' => $rowNumber,
                'row_data' => $rowData,
                'headers' => $headers
            ]);
        }

        // ✅ فحص صارم: رقم الهوية وتاريخ الميلاد - حقول إلزامية بشكل مطلق
        if ($tableName === 'data') {
            // فحص رقم الهوية
            $idNumber = $rowData['data_id_number'] ?? null;
            if (empty($idNumber) || trim($idNumber) === '') {
                $rowErrors[] = [
                    'valid' => false,
                    'column' => 'data_id_number',
                    'value' => $idNumber ?? 'فارغ',
                    'error' => "❌ رقم الهوية مفقود في الصف {$rowNumber}",
                    'row' => $rowNumber,
                    'severity' => 'critical',
                    'solution' => '⚠️ رقم الهوية حقل إلزامي ولا يمكن أن يكون فارغاً. سيتم تجاهل هذا السطر بالكامل.'
                ];
            }

            // فحص تاريخ الميلاد
            $birthDate = $rowData['data_birth_date'] ?? null;
            if (empty($birthDate) || trim($birthDate) === '') {
                $rowErrors[] = [
                    'valid' => false,
                    'column' => 'data_birth_date',
                    'value' => $birthDate ?? 'فارغ',
                    'error' => "❌ تاريخ الميلاد مفقود في الصف {$rowNumber}",
                    'row' => $rowNumber,
                    'severity' => 'critical',
                    'solution' => '⚠️ تاريخ الميلاد حقل إلزامي ولا يمكن أن يكون فارغاً. سيتم تجاهل هذا السطر بالكامل.'
                ];
            }

            // إذا كانت هناك أخطاء حرجة، نرفض السطر فوراً
            if (!empty($rowErrors)) {
                $this->invalidRows[] = [
                    'row_number' => $rowNumber,
                    'data' => $rowData,
                    'errors' => $rowErrors
                ];
                $this->statistics['invalid_rows']++;

                Log::error('❌ سطر مرفوض - حقل إلزامي مفقود', [
                    'row_number' => $rowNumber,
                    'missing_fields' => array_column($rowErrors, 'column'),
                    'row_data' => $rowData
                ]);

                return; // إيقاف التحقق من هذا الصف
            }
        }

        // التحقق من كل عمود
        foreach ($rowData as $column => $value) {
            if (!isset($tableStructure[$column])) {
                continue;
            }

            $columnInfo = $tableStructure[$column];
            $validation = $this->validateColumnValue($value, $column, $columnInfo, $tableName, $rowNumber);

            if (!$validation['valid']) {
                $rowErrors[] = $validation;
            }
        }

        // التحقق من العلاقات الخارجية
        $foreignKeyErrors = $this->validateForeignKeys($rowData, $tableName, $rowNumber);
        $rowErrors = array_merge($rowErrors, $foreignKeyErrors);

        // التحقق من القيود الفريدة
        $uniqueErrors = $this->validateUniqueConstraints($rowData, $tableName, $rowNumber);
        $rowErrors = array_merge($rowErrors, $uniqueErrors);

        if (empty($rowErrors)) {
            $this->validRows[] = [
                'row_number' => $rowNumber,
                'data' => $rowData
            ];
            $this->statistics['valid_rows']++;
        } else {
            $this->invalidRows[] = [
                'row_number' => $rowNumber,
                'data' => $rowData,
                'errors' => $rowErrors
            ];
            $this->statistics['invalid_rows']++;

            // Log errors for first invalid row
            if ($this->statistics['invalid_rows'] === 1) {
                Log::warning('⚠️ أول صف خاطئ', [
                    'row_number' => $rowNumber,
                    'errors' => $rowErrors
                ]);
            }
        }
    }

    /**
     * التحقق من قيمة العمود
     */
    protected function validateColumnValue($value, $column, $columnInfo, $tableName, $rowNumber)
    {
        // التحقق من NULL
        if (is_null($value) || $value === '') {
            if (!$columnInfo['null'] && $columnInfo['default'] === null && $columnInfo['extra'] !== 'auto_increment') {
                return [
                    'valid' => false,
                    'column' => $column,
                    'value' => $value,
                    'error' => "العمود '{$column}' مطلوب ولا يمكن أن يكون فارغاً",
                    'row' => $rowNumber,
                    'solution' => 'يجب إدخال قيمة صحيحة في هذا الحقل'
                ];
            }
            return ['valid' => true];
        }

        // التحقق من نوع البيانات
        $type = strtolower($columnInfo['type']);

        // أنواع الأرقام
        if (preg_match('/^(int|integer|bigint|smallint|tinyint)/', $type)) {
            if (!is_numeric($value) || floor($value) != $value) {
                return [
                    'valid' => false,
                    'column' => $column,
                    'value' => $value,
                    'error' => "القيمة '{$value}' في العمود '{$column}' يجب أن تكون رقماً صحيحاً",
                    'row' => $rowNumber,
                    'solution' => 'يجب إدخال رقم صحيح بدون فواصل عشرية (مثال: 25، 100، 1500)'
                ];
            }
        }

        // أنواع الأرقام العشرية
        if (preg_match('/^(decimal|float|double|numeric)/', $type)) {
            if (!is_numeric($value)) {
                return [
                    'valid' => false,
                    'column' => $column,
                    'value' => $value,
                    'error' => "القيمة '{$value}' في العمود '{$column}' يجب أن تكون رقماً",
                    'row' => $rowNumber,
                    'solution' => 'يجب إدخال رقم (مثال: 25.5، 100.75، 1500)'
                ];
            }
        }

        // التواريخ
        if (preg_match('/^(date|datetime|timestamp)/', $type)) {
            if (!$this->isValidDate($value)) {
                return [
                    'valid' => false,
                    'column' => $column,
                    'value' => $value,
                    'error' => "القيمة '{$value}' في العمود '{$column}' ليست تاريخاً صحيحاً",
                    'row' => $rowNumber,
                    'solution' => 'يجب إدخال تاريخ صحيح بالصيغة: YYYY-MM-DD (مثال: 2024-01-15)'
                ];
            }
        }

        // النصوص (طول النص)
        if (preg_match('/^(varchar|char)\((\d+)\)/', $type, $matches)) {
            $maxLength = (int)$matches[2];
            if (strlen($value) > $maxLength) {
                return [
                    'valid' => false,
                    'column' => $column,
                    'value' => $value,
                    'error' => "النص في العمود '{$column}' طويل جداً (الطول الحالي: " . strlen($value) . "، الحد الأقصى: {$maxLength})",
                    'row' => $rowNumber,
                    'solution' => "يجب أن لا يتجاوز النص {$maxLength} حرف"
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * التحقق من المفاتيح الخارجية
     */
    protected function validateForeignKeys($rowData, $tableName, $rowNumber)
    {
        $errors = [];
        $warnings = [];

        // تعريف العلاقات الخارجية لجدول data
        $foreignKeyRelations = [
            'data' => [
                'data_section_id' => ['table' => 'general_category', 'column' => 'id', 'name' => 'القسم', 'default' => 0],
                'data_relationship' => ['table' => 'category_of_relations', 'column' => 'id', 'name' => 'العلاقة', 'default' => 0],
                'data_marital_status' => ['table' => 'marital_status', 'column' => 'id', 'name' => 'الحالة الاجتماعية', 'default' => 0],
                'data_academic_qualification' => ['table' => 'academic_degrees', 'column' => 'id', 'name' => 'المؤهل العلمي', 'default' => 0],
                'data_displacement_status' => ['table' => 'general_category', 'column' => 'id', 'name' => 'حالة النزوح', 'default' => 0],
                'data_city' => ['table' => 'city', 'column' => 'id', 'name' => 'المدينة', 'default' => 0],
                'data_province' => ['table' => 'provinces', 'column' => 'id', 'name' => 'المحافظة', 'default' => 0],
                'data_health_status' => ['table' => 'health_statuses', 'column' => 'id', 'name' => 'الحالة الصحية', 'default' => 0],
                'data_employment_status_breadwinner' => ['table' => 'employment', 'column' => 'id', 'name' => 'حالة العمل', 'default' => 0],
                'data_housing_status' => ['table' => 'housing_status', 'column' => 'id', 'name' => 'حالة السكن', 'default' => 0],
                'data_current_housing_type' => ['table' => 'type_of_accommodation', 'column' => 'id', 'name' => 'نوع السكن', 'default' => 0],
            ]
        ];

        if ($tableName === 'data' && isset($foreignKeyRelations['data'])) {
            foreach ($foreignKeyRelations['data'] as $column => $relation) {
                if (isset($rowData[$column]) && !empty($rowData[$column]) && $rowData[$column] != '0') {
                    try {
                        $exists = DB::table($relation['table'])
                            ->where($relation['column'], $rowData[$column])
                            ->exists();

                        if (!$exists) {
                            // الحصول على القيمة الافتراضية أو أول قيمة متاحة
                            $defaultValue = $relation['default'];

                            if ($defaultValue === null) {
                                // محاولة الحصول على أول قيمة من الجدول
                                $firstRecord = DB::table($relation['table'])->first();
                                $defaultValue = $firstRecord ? $firstRecord->{$relation['column']} : null;
                            }

                            $this->warnings[] = [
                                'type' => 'foreign_key',
                                'severity' => 'warning',
                                'row' => $rowNumber,
                                'column' => $column,
                                'original_value' => $rowData[$column],
                                'default_value' => $defaultValue ?? 'NULL',
                                'message' => "⚠️ الصف {$rowNumber}: القيمة '{$rowData[$column]}' في عمود '{$relation['name']}' غير موجودة",
                                'action' => $defaultValue !== null
                                    ? "سيتم استبدالها بالقيمة الافتراضية: {$defaultValue}"
                                    : "سيتم تعيينها كـ NULL",
                                'solution' => "يُنصح بمراجعة جدول '{$relation['name']}' وإضافة القيم المطلوبة"
                            ];

                            // تحديث القيمة في البيانات
                            $rowData[$column] = $defaultValue;
                        }
                    } catch (\Exception $e) {
                        // تجاهل الأخطاء إذا كان الجدول غير موجود
                        Log::warning("⚠️ تخطي التحقق من العلاقة: {$relation['table']}", [
                            'column' => $column,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        if ($tableName === 'dead_people') {
            // التحقق من person_id
            if (isset($rowData['person_id']) && !empty($rowData['person_id'])) {
                $exists = DB::table('data')->where('data_id_number', $rowData['person_id'])->exists();
                if (!$exists) {
                    $errors[] = [
                        'valid' => false,
                        'column' => 'person_id',
                        'value' => $rowData['person_id'],
                        'error' => "رقم الهوية '{$rowData['person_id']}' غير موجود في جدول البيانات الرئيسي",
                        'row' => $rowNumber,
                        'solution' => 'يجب أن يكون رقم الهوية موجوداً في جدول البيانات الرئيسي أولاً'
                    ];
                }
            }
        }

        if ($tableName === 're_people') {
            // التحقق من registration_id (رقم هوية المعيل/الأب)
            // ملاحظة: registration_id يحتوي على رقم هوية المعيل وليس رقم الملف
            // سيتم البحث عنه في data_id_number وليس file_id_number
            if (isset($rowData['registration_id']) && !empty($rowData['registration_id'])) {
                $exists = DB::table('data')->where('data_id_number', $rowData['registration_id'])->exists();
                if (!$exists) {
                    $errors[] = [
                        'valid' => false,
                        'column' => 'registration_id',
                        'value' => $rowData['registration_id'],
                        'error' => "رقم هوية المعيل '{$rowData['registration_id']}' غير موجود في جدول البيانات الرئيسي",
                        'row' => $rowNumber,
                        'solution' => 'يجب أن يكون رقم هوية المعيل موجوداً في جدول البيانات الرئيسي أولاً'
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * التحقق من القيود الفريدة
     */
    protected function validateUniqueConstraints($rowData, $tableName, $rowNumber)
    {
        $errors = [];
        $uniqueFields = $this->getUniqueFields($tableName);

        // أسماء الحقول بالعربية للرسائل
        $fieldNames = [
            'file_id_number' => 'رقم الملف',
            'data_id_number' => 'رقم الهوية',
            're_file_id' => 'رقم الملف',
            'mother_id' => 'رقم هوية الأم',
            'person_id' => 'رقم هوية الشخص'
        ];

        foreach ($uniqueFields as $field) {
            if (isset($rowData[$field]) && !empty($rowData[$field])) {
                $exists = DB::table($tableName)->where($field, $rowData[$field])->exists();
                if ($exists) {
                    $fieldNameAr = $fieldNames[$field] ?? $field;
                    $errors[] = [
                        'valid' => false,
                        'column' => $field,
                        'value' => $rowData[$field],
                        'error' => "❌ {$fieldNameAr} '{$rowData[$field]}' موجود مسبقاً في قاعدة البيانات",
                        'row' => $rowNumber,
                        'type' => 'duplicate',
                        'severity' => 'critical',
                        'solution' => '⚠️ لا يمكن إدخال قيمة مكررة. يجب استخدام قيمة فريدة غير موجودة مسبقاً في النظام'
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * الحصول على الحقول الفريدة
     */
    protected function getUniqueFields($tableName)
    {
        $uniqueFields = [
            'data' => [
                // فقط نفحص رقم الهوية - رقم الملف سيتم توليده تلقائياً من النظام
                'data_id_number'       // رقم الهوية - يجب أن يكون فريد
            ],
            'dead_people' => [
                're_file_id',          // رقم الملف - يجب أن يكون فريد
                'mother_id'            // رقم هوية الأم - يجب أن يكون فريد
            ],
            're_people' => [
                'person_id'            // رقم هوية الشخص - يجب أن يكون فريد
            ]
        ];

        return $uniqueFields[$tableName] ?? [];
    }

    /**
     * فحص التكرار في الملف نفسه
     */
    protected function checkDuplicates($tableName)
    {
        $uniqueFields = $this->getUniqueFields($tableName);
        $seen = [];

        Log::info('🔍 فحص التكرار داخل الملف', [
            'table' => $tableName,
            'unique_fields' => $uniqueFields,
            'valid_rows_count' => count($this->validRows)
        ]);

        // أسماء الحقول بالعربية للرسائل
        $fieldNames = [
            'file_id_number' => 'رقم الملف',
            'data_id_number' => 'رقم الهوية',
            're_file_id' => 'رقم الملف',
            'mother_id' => 'رقم هوية الأم',
            'person_id' => 'رقم هوية الشخص'
        ];

        foreach ($this->validRows as $index => $row) {
            foreach ($uniqueFields as $field) {
                if (isset($row['data'][$field])) {
                    $value = $row['data'][$field];

                    if (isset($seen[$field][$value])) {
                        $fieldNameAr = $fieldNames[$field] ?? $field;

                        Log::warning('🔴 اكتشاف تكرار', [
                            'field' => $field,
                            'value' => $value,
                            'current_row' => $row['row_number'],
                            'first_seen_row' => $seen[$field][$value]
                        ]);

                        // نقل الصف من valid إلى invalid
                        $this->invalidRows[] = [
                            'row_number' => $row['row_number'],
                            'data' => $row['data'],
                            'errors' => [[
                                'valid' => false,
                                'column' => $field,
                                'value' => $value,
                                'error' => "❌ {$fieldNameAr} '{$value}' مكرر داخل الملف (موجود في الصف {$seen[$field][$value]})",
                                'row' => $row['row_number'],
                                'type' => 'duplicate_in_file',
                                'severity' => 'critical',
                                'solution' => '⚠️ تم اكتشاف تكرار داخل نفس الملف. يجب أن تكون جميع القيم فريدة'
                            ]]
                        ];

                        unset($this->validRows[$index]);
                        $this->statistics['valid_rows']--;
                        $this->statistics['invalid_rows']++;
                        $this->statistics['duplicate_rows']++;
                    } else {
                        $seen[$field][$value] = $row['row_number'];
                    }
                }
            }
        }

        // إعادة فهرسة المصفوفة
        $this->validRows = array_values($this->validRows);

        Log::info('✅ انتهى فحص التكرار', [
            'duplicates_found' => $this->statistics['duplicate_rows'] ?? 0,
            'remaining_valid_rows' => count($this->validRows),
            'total_invalid_rows' => count($this->invalidRows)
        ]);
    }

    /**
     * التحقق من صحة التاريخ
     */
    protected function isValidDate($date)
    {
        if (empty($date)) {
            return false;
        }

        // محاولة تحويل التاريخ
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'm/d/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }

        // محاولة تحويل تاريخ Excel
        if (is_numeric($date)) {
            try {
                Date::excelToDateTimeObject($date);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * إعداد تقرير التحقق النهائي
     */
    protected function prepareValidationReport()
    {
        $canProceed = $this->statistics['valid_rows'] > 0;

        // ⚡ حماية من البيانات الضخمة - الحد الأقصى للأخطاء والتحذيرات
        // لا حاجة لحد الأخطاء بعد تطبيق نظام التجميع في الـ frontend
        // حيث سيتم تجميع الأخطاء المتشابهة معاً مما يقلل الحمل على المتصفح
        $invalidRowsToReturn = $this->invalidRows;
        $warningsToReturn = $this->warnings;
        $hasMoreErrors = false;
        $hasMoreWarnings = false;

        return [
            'success' => true,
            'can_proceed' => $canProceed,
            'statistics' => $this->statistics,
            'valid_rows' => $this->validRows,
            'invalid_rows' => $invalidRowsToReturn,
            'errors' => $this->errors,
            'warnings' => $warningsToReturn,
            'has_more_errors' => $hasMoreErrors,
            'has_more_warnings' => $hasMoreWarnings,
            'total_errors_count' => count($this->invalidRows),
            'total_warnings_count' => count($this->warnings),
            'summary' => [
                'total' => $this->statistics['total_rows'],
                'valid' => $this->statistics['valid_rows'],
                'invalid' => $this->statistics['invalid_rows'],
                'duplicate' => $this->statistics['duplicate_rows'],
                'success_rate' => $this->statistics['total_rows'] > 0
                    ? round(($this->statistics['valid_rows'] / $this->statistics['total_rows']) * 100, 2)
                    : 0
            ],
            'message' => $this->generateSummaryMessage()
        ];
    }

    /**
     * توليد رسالة ملخص
     */
    protected function generateSummaryMessage()
    {
        $total = $this->statistics['total_rows'];
        $valid = $this->statistics['valid_rows'];
        $invalid = $this->statistics['invalid_rows'];

        if ($valid === 0) {
            return "❌ جميع الصفوف ({$total}) تحتوي على أخطاء ولا يمكن إدخالها. يرجى مراجعة التفاصيل أدناه.";
        }

        if ($invalid === 0) {
            return "✅ جميع الصفوف ({$total}) صالحة للإدخال. يمكن المتابعة بأمان.";
        }

        return "⚠️ الملف يحتوي على {$valid} صف صحيح و {$invalid} صف يحتوي على أخطاء. سيتم إدخال الصفوف الصحيحة فقط.";
    }

    /**
     * الحصول على الصفوف الصحيحة للإدخال
     */
    public function getValidRowsForInsertion()
    {
        return array_map(function($row) {
            return $row['data'];
        }, $this->validRows);
    }
}
