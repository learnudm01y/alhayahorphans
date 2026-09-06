<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\SponsorDataTable;
use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Models\CI_BIRTH_CD;
use App\Models\BankName;
use App\Models\CurrencyType;
use App\Models\AssociationEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SponsorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SponsorDataTable $dataTable)
    {
        try {
            $countries = CI_BIRTH_CD::whereNotNull('flag')
                ->where('flag', '!=', '')
                ->where('code', '!=', '0')
                ->get();
        } catch (\Exception $e) {
            $countries = collect();
        }

        $banks = BankName::all();
        $currencies = CurrencyType::all();
        $file_id = generateUniqueReservedCode('sponsors', 'file_id');

        return $dataTable->render('admin.dashboard.sponsors.index', compact('countries', 'banks', 'currencies', 'file_id'));
    }    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            Log::info('=== بدء إضافة جمعية ===');
            Log::info('البيانات الواردة:', $request->all());

            // التحقق من البيانات
            $validatedData = $request->validate([
                'file_id' => 'required|string|size:6',
                'sponsor_name' => 'required|string|max:255',
                'sponsor_short_name' => 'nullable|string|max:255',
                'sponsor_phone_number' => 'nullable|string|max:50',
                'sponsor_email' => 'nullable|email|max:255',
                'sponsor_address' => 'nullable|string|max:500',
                'sponsor_bank_name_id' => 'nullable|integer|exists:bank_names,id',
                'sponsor_account_bank_number' => 'nullable|string|max:100',
                'sponsor_bank_swift_code' => 'nullable|string|max:50',
                'sponsor_bank_related_phone_number' => 'nullable|string|max:50',
                'sponsor_bank_account_currency' => 'nullable|integer|exists:currency_types,id',
                'country_code' => 'nullable|string|max:10',
            ]);

            Log::info('البيانات بعد التحقق:', $validatedData);

            // إنشاء السجل
            $sponsor = new Sponsor();
            $sponsor->file_id = $validatedData['file_id'];
            $sponsor->sponsor_name = $validatedData['sponsor_name'];
            $sponsor->sponsor_short_name = $validatedData['sponsor_short_name'] ?? null;
            $sponsor->sponsor_phone_number = $validatedData['sponsor_phone_number'] ?? null;
            $sponsor->sponsor_email = $validatedData['sponsor_email'] ?? null;
            $sponsor->sponsor_address = $validatedData['sponsor_address'] ?? null;
            $sponsor->sponsor_bank_name_id = $validatedData['sponsor_bank_name_id'] ?? null;
            $sponsor->sponsor_account_bank_number = $validatedData['sponsor_account_bank_number'] ?? null;
            $sponsor->sponsor_bank_swift_code = $validatedData['sponsor_bank_swift_code'] ?? null;
            $sponsor->sponsor_bank_related_phone_number = $validatedData['sponsor_bank_related_phone_number'] ?? null;
            $sponsor->sponsor_bank_account_currency = $validatedData['sponsor_bank_account_currency'] ?? null;
            $sponsor->country_code = $validatedData['country_code'] ?? null;

            $sponsor->save();

            Log::info('تم الحفظ بنجاح! ID: ' . $sponsor->id);

            // وضع علامة على file_id كمستخدم
            markCodeAsUsed($validatedData['file_id']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الجمعية بنجاح',
                'data' => $sponsor
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('خطأ في التحقق:', $e->errors());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في الحفظ:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $sponsor = Sponsor::findOrFail($id);
            return response()->json($sponsor);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الجمعية'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $sponsor = Sponsor::findOrFail($id);

            $validatedData = $request->validate([
                'sponsor_name' => 'required|string|max:255',
                'sponsor_short_name' => 'nullable|string|max:255',
                'sponsor_phone_number' => 'nullable|string|max:50',
                'sponsor_email' => 'nullable|email|max:255',
                'sponsor_address' => 'nullable|string|max:500',
                'sponsor_bank_name_id' => 'nullable|integer|exists:bank_names,id',
                'sponsor_account_bank_number' => 'nullable|string|max:100',
                'sponsor_bank_swift_code' => 'nullable|string|max:50',
                'sponsor_bank_related_phone_number' => 'nullable|string|max:50',
                'sponsor_bank_account_currency' => 'nullable|integer|exists:currency_types,id',
                'country_code' => 'nullable|string|max:10',
            ]);

            $sponsor->update($validatedData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الجمعية بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في التحديث:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $sponsor = Sponsor::findOrFail($id);
            $sponsor->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الجمعية بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new file_id for sponsors
     */
    public function generateFileId()
    {
        try {
            $file_id = generateUniqueReservedCode('sponsors', 'file_id');

            return response()->json([
                'success' => true,
                'file_id' => $file_id
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating file_id:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في توليد رقم الملف'
            ], 500);
        }
    }

    /**
     * Show test form (final version)
     */
    public function testFinal()
    {
        $banks = BankName::all();
        $file_id = generateUniqueReservedCode('sponsors', 'file_id');

        return view('admin.dashboard.sponsors.test_final', compact('banks', 'file_id'));
    }

    /**
     * Store association employee
     */
    public function storeEmployee(Request $request)
    {
        try {
            $validated = $request->validate([
                'sponsor_id' => 'required|exists:sponsors,id',
                'employee_name' => 'required|string|max:255'
            ]);

            $employee = AssociationEmployee::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المندوب بنجاح',
                'employee' => $employee
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding employee:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة المندوب'
            ], 500);
        }
    }

    /**
     * Get association employees
     */
    public function getEmployees($sponsorId)
    {
        try {
            $employees = AssociationEmployee::where('sponsor_id', $sponsorId)->get();

            return response()->json([
                'success' => true,
                'employees' => $employees
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ'
            ], 500);
        }
    }

    /**
     * Delete association employee
     */
    public function destroyEmployee($id)
    {
        try {
            $employee = AssociationEmployee::findOrFail($id);
            $employee->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المندوب بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف'
            ], 500);
        }
    }

    /**
     * عرض صفحة إدارة حقول الجمعيات
     */
    public function fieldsManagement()
    {
        return view('admin.dashboard.sponsors.fields-management');
    }

    /**
     * الحصول على بيانات الجمعيات للـ DataTable (بدون حجز رقم ملف)
     */
    public function getSponsorsForFieldsManagement(Request $request)
    {
        if ($request->ajax()) {
            $sponsors = Sponsor::with('country')->select('sponsors.*');

            return datatables()->of($sponsors)
                ->addIndexColumn()
                ->addColumn('country_name', function($sponsor) {
                    return $sponsor->country ? $sponsor->country->description_ar : '-';
                })
                ->make(true);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    /**
     * الحصول على إعدادات حقول جمعية معينة
     */
    public function getSponsorFields($sponsorId)
    {
        try {
            $sponsor = Sponsor::findOrFail($sponsorId);
            $legacyUnmanagedColumns = [
                'field_re_guardian_name',
                'field_re_guardian_phone',
                'field_re_guardian_id',
                'field_family_members_count',
                'field_mother_name',
            ];

            // جلب أو إنشاء إعدادات الحقول للجمعية
            $fieldSettings = $sponsor->fieldSettings;

            if (!$fieldSettings) {
                // إنشاء إعدادات افتراضية إذا لم تكن موجودة
                $fieldSettings = \App\Models\SponsorFieldSetting::create([
                    'sponsor_id' => $sponsorId,
                ]);
            }

            // تحميل ملف الـ config
            $fieldsConfig = config('sponsor_fields.fields');
            $sectionFields = config('sponsor_fields.section_fields', []);
            $settingsTable = (new \App\Models\SponsorFieldSetting())->getTable();
            $hasDataRelationshipColumn = Schema::hasColumn($settingsTable, 'field_data_relationship');
            $hasLegacyRelationshipColumn = Schema::hasColumn($settingsTable, 'field_relationship');

            // بناء مصفوفة الحقول مع حالتها
            $fields = [];
            foreach ($fieldsConfig as $key => $fieldInfo) {
                $isActive = in_array($fieldInfo['db_column'], $legacyUnmanagedColumns, true)
                    ? false
                    : (bool) ($fieldSettings->{$key} ?? false);

                if ($fieldInfo['db_column'] === 'field_data_relationship') {
                    if ($hasDataRelationshipColumn) {
                        $isActive = (bool) ($fieldSettings->field_data_relationship ?? false);
                    } elseif ($hasLegacyRelationshipColumn) {
                        $isActive = (bool) ($fieldSettings->field_relationship ?? false);
                    }
                }

                $fields[] = [
                    'id' => $fieldInfo['id'],
                    'name' => $fieldInfo['display_name'],
                    'db_column' => $fieldInfo['db_column'],
                    'category' => $fieldInfo['category'],
                    'category_id' => $fieldInfo['category_id'],
                    'order' => $fieldInfo['order'],
                    'required' => $fieldInfo['required'],
                    'active' => $isActive,
                ];
            }

            // إضافة حقول التحكم في الأقسام
            foreach ($sectionFields as $key => $fieldInfo) {
                $fields[] = [
                    'id' => $fieldInfo['id'],
                    'name' => $fieldInfo['display_name'],
                    'db_column' => $fieldInfo['db_column'],
                    'category' => $fieldInfo['category'],
                    'category_id' => $fieldInfo['category_id'],
                    'order' => $fieldInfo['order'],
                    'required' => $fieldInfo['required'] ?? false,
                    'active' => (bool) ($fieldSettings->{$key} ?? true), // افتراضياً مفعل
                    'is_section' => true,
                ];
            }

            return response()->json([
                'success' => true,
                'fields' => $fields,
                'compress_attachments_images' => (bool) ($fieldSettings->compress_attachments_images ?? true),
                'sponsor' => [
                    'id' => $sponsor->id,
                    'name' => $sponsor->sponsor_name,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading sponsor fields:', [
                'sponsor_id' => $sponsorId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحميل الحقول'
            ], 500);
        }
    }

    /**
     * حفظ إعدادات حقول جمعية معينة
     */
    public function saveSponsorFields(Request $request, $sponsorId)
    {
        DB::beginTransaction();

        try {
            $sponsor = Sponsor::findOrFail($sponsorId);

            // التحقق من البيانات
            $validated = $request->validate([
                'fields' => 'required|array',
                'fields.*' => 'required|string',
            ]);

            // جلب أو إنشاء إعدادات الحقول
            $fieldSettings = $sponsor->fieldSettings;

            if (!$fieldSettings) {
                $fieldSettings = new \App\Models\SponsorFieldSetting();
                $fieldSettings->sponsor_id = $sponsorId;
            }

            // تحميل جميع الحقول من الـ config
            $fieldsConfig = config('sponsor_fields.fields');
            $sectionFields = config('sponsor_fields.section_fields', []);
            $allDbColumns = array_column($fieldsConfig, 'db_column');
            $sectionDbColumns = array_column($sectionFields, 'db_column');
            $settingsTable = (new \App\Models\SponsorFieldSetting())->getTable();
            $hasDataRelationshipColumn = Schema::hasColumn($settingsTable, 'field_data_relationship');
            $hasLegacyRelationshipColumn = Schema::hasColumn($settingsTable, 'field_relationship');
            $legacyUnmanagedColumns = [
                'field_re_guardian_name',
                'field_re_guardian_phone',
                'field_re_guardian_id',
                'field_family_members_count',
                'field_mother_name',
            ];

            // تعيين جميع الحقول إلى 0 (غير مفعل)
            foreach ($allDbColumns as $column) {
                $fieldSettings->{$column} = 0;
            }
            // تعيين حقول الأقسام إلى 0 (غير مفعل)
            foreach ($sectionDbColumns as $column) {
                $fieldSettings->{$column} = 0;
            }

            // تفعيل الحقول المختارة فقط
            $allColumns = array_merge($allDbColumns, $sectionDbColumns);
            foreach ($validated['fields'] as $dbColumn) {
                if (in_array($dbColumn, $allColumns)) {
                    if ($dbColumn === 'field_data_relationship') {
                        if ($hasDataRelationshipColumn) {
                            $fieldSettings->field_data_relationship = 1;
                        }
                        if ($hasLegacyRelationshipColumn) {
                            $fieldSettings->field_relationship = 1;
                        }
                    } else {
                        $fieldSettings->{$dbColumn} = 1;
                    }
                }
            }

            // إيقاف الحقول غير المُدارة بشكل صارم
            foreach ($legacyUnmanagedColumns as $legacyColumn) {
                if (in_array($legacyColumn, $allColumns, true)) {
                    $fieldSettings->{$legacyColumn} = 0;
                }
            }

            // حفظ إعداد ضغط الصور (يُقبل كمعامل منفصل عن الحقول)
            if ($request->has('compress_attachments_images')) {
                $fieldSettings->compress_attachments_images = $request->boolean('compress_attachments_images', true);
            }

            $fieldSettings->save();

            DB::commit();

            Log::info('Sponsor fields updated successfully', [
                'sponsor_id' => $sponsorId,
                'active_fields_count' => count($validated['fields'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ إعدادات الحقول بنجاح',
                'active_fields_count' => count($validated['fields'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error saving sponsor fields:', [
                'sponsor_id' => $sponsorId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الإعدادات'
            ], 500);
        }
    }

    /**
     * جلب إعدادات الوثائق لجمعية معينة
     */
    public function getDocumentSettings($sponsorId)
    {
        try {
            $sponsor = Sponsor::findOrFail($sponsorId);

            // جلب إعدادات الحقول للجمعية
            $fieldSettings = $sponsor->fieldSettings;

            Log::info('getDocumentSettings called', [
                'sponsor_id' => $sponsorId,
                'sponsor_name' => $sponsor->sponsor_name,
                'field_settings_exists' => $fieldSettings !== null,
                'field_settings_id' => $fieldSettings ? $fieldSettings->id : null
            ]);

            // الحصول على معرفات الوثائق المفعلة
            $enabledDocumentIds = [];
            if ($fieldSettings && $fieldSettings->enabled_documents) {
                $enabledDocumentIds = $fieldSettings->enabled_documents;
                if (!is_array($enabledDocumentIds)) {
                    $enabledDocumentIds = json_decode($enabledDocumentIds, true) ?: [];
                }
            }

            // الحصول على معرفات الوثائق المفعّل فيها الحفظ المحلي
            $saveLocalDocIds = [];
            if ($fieldSettings && $fieldSettings->save_local_attachments) {
                $saveLocalDocIds = $fieldSettings->save_local_attachments;
                if (!is_array($saveLocalDocIds)) {
                    $saveLocalDocIds = json_decode($saveLocalDocIds, true) ?: [];
                }
            }

            Log::info('Enabled document IDs', [
                'enabled_ids' => $enabledDocumentIds,
                'save_local_ids' => $saveLocalDocIds,
                'type' => gettype($enabledDocumentIds),
                'count' => count($enabledDocumentIds)
            ]);

            // جلب جميع أنواع الوثائق
            $documentTypes = \App\Models\DocumentType::all();

            // تحويل البيانات إلى صيغة مناسبة
            $settings = $documentTypes->map(function($docType) use ($enabledDocumentIds, $saveLocalDocIds) {
                $isEnabled = in_array($docType->id, $enabledDocumentIds);
                $saveLocal = in_array($docType->id, $saveLocalDocIds);

                return [
                    'id' => $docType->id,
                    'description' => $docType->description,
                    'pref' => $docType->pref,
                    'is_enabled' => $isEnabled,
                    'save_local' => $saveLocal
                ];
            });

            Log::info('Returning document settings', [
                'total_documents' => $settings->count(),
                'enabled_count' => $settings->where('is_enabled', true)->count(),
                'sample' => $settings->take(3)->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $settings,
                'debug' => [
                    'enabled_ids' => $enabledDocumentIds,
                    'field_settings_id' => $fieldSettings ? $fieldSettings->id : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching document settings:', [
                'sponsor_id' => $sponsorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إعدادات الوثائق'
            ], 500);
        }
    }

    /**
     * تفعيل/إيقاف رفع PDF إلى Google Drive للجمعية
     */
    public function toggleGoogleDrive(Request $request, $sponsorId)
    {
        try {
            $sponsor = Sponsor::findOrFail($sponsorId);

            $enabled = $request->input('enabled', 0);
            $sponsor->google_drive_enabled = (bool) $enabled;

            // إذا كان التفعيل للمرة الأولى، قم بإنشاء اسم المجلد من اسم الجمعية
            if ($enabled && empty($sponsor->google_drive_folder_name)) {
                $sponsor->google_drive_folder_name = $sponsor->file_id . '_' . $sponsor->sponsor_name;
            }

            $sponsor->save();

            Log::info('Google Drive toggle for sponsor', [
                'sponsor_id' => $sponsorId,
                'enabled' => $enabled,
                'folder_name' => $sponsor->google_drive_folder_name
            ]);

            return response()->json([
                'success' => true,
                'message' => $enabled ? 'تم تفعيل رفع PDF إلى Google Drive بنجاح' : 'تم إيقاف رفع PDF إلى Google Drive',
                'enabled' => (bool) $enabled,
                'folder_name' => $sponsor->google_drive_folder_name
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling Google Drive:', [
                'sponsor_id' => $sponsorId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الإعدادات'
            ], 500);
        }
    }

    /**
     * حفظ إعدادات الوثائق لجمعية معينة
     */
    public function saveDocumentSettings(Request $request, $sponsorId)
    {
        // تسجيل البيانات المستلمة
        Log::info('saveDocumentSettings called', [
            'sponsor_id' => $sponsorId,
            'request_data' => $request->all(),
            'raw_input' => $request->getContent()
        ]);

        DB::beginTransaction();

        try {
            $sponsor = Sponsor::findOrFail($sponsorId);

            // التحقق من البيانات
            $validated = $request->validate([
                'documents' => 'required|array',
                'documents.*.document_type_id' => 'required|integer',
                'documents.*.is_enabled' => 'required|boolean',
                'documents.*.save_local' => 'nullable|boolean'
            ]);

            Log::info('Validation passed', ['validated' => $validated]);

            // جمع معرفات الوثائق المفعلة والمفعّل فيها الحفظ المحلي
            $enabledDocumentIds = [];
            $saveLocalDocIds = [];
            foreach ($validated['documents'] as $doc) {
                if ($doc['is_enabled'] === true) {
                    $enabledDocumentIds[] = (int)$doc['document_type_id'];
                }
                if (!empty($doc['save_local'])) {
                    $saveLocalDocIds[] = (int)$doc['document_type_id'];
                }
            }

            Log::info('Enabled documents extracted', [
                'enabled_ids' => $enabledDocumentIds,
                'save_local_ids' => $saveLocalDocIds
            ]);

            // جلب أو إنشاء إعدادات الحقول
            $fieldSettings = $sponsor->fieldSettings;

            if (!$fieldSettings) {
                Log::info('Creating new field settings for sponsor', ['sponsor_id' => $sponsorId]);
                $fieldSettings = new \App\Models\SponsorFieldSetting();
                $fieldSettings->sponsor_id = $sponsorId;
            } else {
                Log::info('Updating existing field settings', ['settings_id' => $fieldSettings->id]);
            }

            // حفظ معرفات الوثائق المفعلة + الحفظ المحلي
            $fieldSettings->enabled_documents = $enabledDocumentIds;
            $fieldSettings->save_local_attachments = $saveLocalDocIds;
            $saved = $fieldSettings->save();

            Log::info('Field settings save result', [
                'saved' => $saved,
                'settings_id' => $fieldSettings->id,
                'enabled_documents' => $fieldSettings->enabled_documents
            ]);

            DB::commit();

            Log::info('Sponsor document settings saved successfully', [
                'sponsor_id' => $sponsorId,
                'enabled_documents' => $enabledDocumentIds,
                'count' => count($enabledDocumentIds)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ إعدادات الوثائق بنجاح',
                'data' => [
                    'enabled_count' => count($enabledDocumentIds),
                    'enabled_documents' => $enabledDocumentIds,
                    'save_local_documents' => $saveLocalDocIds
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            Log::error('Validation error saving document settings:', [
                'sponsor_id' => $sponsorId,
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المرسلة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error saving document settings:', [
                'sponsor_id' => $sponsorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ إعدادات الوثائق: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * فحص إعدادات الحقول (للتشخيص)
     */
    public function checkFieldSettings($sponsorId)
    {
        try {
            $sponsor = Sponsor::findOrFail($sponsorId);
            $fieldSettings = $sponsor->fieldSettings;

            return response()->json([
                'success' => true,
                'sponsor_id' => $sponsorId,
                'sponsor_name' => $sponsor->sponsor_name,
                'field_settings_exists' => $fieldSettings ? true : false,
                'field_settings_id' => $fieldSettings ? $fieldSettings->id : null,
                'enabled_documents' => $fieldSettings ? $fieldSettings->enabled_documents : null,
                'enabled_documents_type' => $fieldSettings && $fieldSettings->enabled_documents ? gettype($fieldSettings->enabled_documents) : null,
                'enabled_documents_count' => $fieldSettings && $fieldSettings->enabled_documents ? count($fieldSettings->enabled_documents) : 0,
                'raw_data' => $fieldSettings ? $fieldSettings->toArray() : null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تصدير استمارات التحديث بشكل جماعي
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportForms(Request $request)
    {
        try {
            // التحقق من صحة البيانات
            $validated = $request->validate([
                'sponsor_id' => 'required|exists:sponsors,id',
                'sponsorship_status_id' => 'nullable|exists:sponsorship_statuses,id',
                'updated_only' => 'nullable|boolean'
            ]);

            $sponsorId = $validated['sponsor_id'];
            $sponsorshipStatusId = $validated['sponsorship_status_id'] ?? null;
            $updatedOnly = (bool)($validated['updated_only'] ?? false);

            // الحصول على معلومات الجمعية
            $sponsor = \App\Models\Sponsor::findOrFail($sponsorId);

            // ✅ FIX: حساب عدد الكفالات من sponsor_id الأساسي فقط
            // السبب: جدول sponsorship_sponsor كان يحتوي على بيانات خاطئة
            // الحل: استخدام sponsor_id المباشر (الصحيح دائماً)
            $query = \App\Models\Sponsorship::where('sponsor_id', $sponsorId);

            if ($sponsorshipStatusId) {
                $query->where('sponsorship_status_id', $sponsorshipStatusId);
            }

            if ($updatedOnly) {
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

            $count = $query->count();

            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد كفالات مطابقة للمعايير المحددة'
                ], 404);
            }

            // إرسال Job للمعالجة في الخلفية
            \App\Jobs\BulkExportSponsorshipForms::dispatch($sponsorId, $sponsorshipStatusId, $updatedOnly);

            Log::info('Bulk export forms job dispatched', [
                'sponsor_id' => $sponsorId,
                'sponsor_name' => $sponsor->sponsor_name,
                'sponsorship_status_id' => $sponsorshipStatusId,
                'updated_only' => $updatedOnly,
                'estimated_count' => $count
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم بدء عملية التصدير بنجاح',
                'sponsor_name' => $sponsor->sponsor_name,
                'count' => $count,
                'updated_only' => $updatedOnly,
                'status_filter' => $sponsorshipStatusId ? \App\Models\SponsorshipStatus::find($sponsorshipStatusId)->description : 'جميع الحالات'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'البيانات المدخلة غير صحيحة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error exporting forms', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء بدء عملية التصدير: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تصدير التقارير الشاملة (Family Reports) بشكل جماعي عبر Chromium.
     * يُصدّر تقرير الأسرة لكل مكفول بتصميم الجمعية المحددة.
     */
    public function bulkExportFamilyReports(Request $request)
    {
        try {
            $validated = $request->validate([
                'sponsor_id'            => 'required|exists:sponsors,id',
                'sponsorship_status_id' => 'nullable|exists:sponsorship_statuses,id',
            ]);

            $sponsorId           = (int) $validated['sponsor_id'];
            $sponsorshipStatusId = isset($validated['sponsorship_status_id']) ? (int) $validated['sponsorship_status_id'] : null;

            $sponsor   = \App\Models\Sponsor::findOrFail($sponsorId);
            $hasDesign = \App\Models\SponsorReportDesign::where('sponsor_id', $sponsorId)->exists();

            $count = DB::table('re_people')
                ->whereNotNull('sponsorship_status')
                ->whereNotNull('person_id')
                ->whereNotNull('registration_id')
                ->when($sponsorshipStatusId, fn($q) => $q->where('sponsorship_status', $sponsorshipStatusId))
                ->count();

            if ($count === 0) {
                return response()->json(['success' => false, 'message' => 'لا توجد بيانات مطابقة للمعايير المحددة'], 404);
            }

            \App\Jobs\BulkExportFamilyReportsJob::dispatch($sponsorId, $sponsorshipStatusId);

            Log::info('BulkExportFamilyReports: Job dispatched', [
                'sponsor_id'            => $sponsorId,
                'sponsorship_status_id' => $sponsorshipStatusId,
                'estimated_count'       => $count,
            ]);

            $statusLabel = $sponsorshipStatusId
                ? optional(\App\Models\SponsorshipStatus::find($sponsorshipStatusId))->description ?? 'غير محددة'
                : 'جميع الحالات';

            return response()->json([
                'success'        => true,
                'message'        => 'تم بدء عملية التصدير بنجاح',
                'design_sponsor' => $sponsor->sponsor_name,
                'count'          => $count,
                'status_filter'  => $statusLabel,
                'has_design'     => $hasDesign,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'البيانات المدخلة غير صحيحة', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('BulkExportFamilyReports: Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * تشغيل مزامنة حالة الكفالة بين الجداول الثلاثة في الخلفية.
     * sponsorships.sponsorship_status_id → data.sponsorship_status
     * sponsorships.sponsorship_status_id → re_people.sponsorship_status
     * المزامنة تعتمد على رقم الهوية كحلقة ربط.
     */
    public function syncSponsorshipStatus(Request $request)
    {
        try {
            // التحقق من أن المزامنة ليست قيد التشغيل بالفعل
            $lockKey = 'sync_sponsorship_status_job';
            if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'عملية المزامنة قيد التشغيل بالفعل، يرجى الانتظار.',
                ], 409);
            }

            // حساب عدد الكفالات التي لها حالة
            $count = \App\Models\Sponsorship::whereNotNull('sponsorship_status_id')
                ->whereNotNull('identity_number')
                ->count();

            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد كفالات بحالات محددة للمزامنة.',
                ], 404);
            }

            // تشغيل Job المزامنة في الخلفية
            \App\Jobs\SyncSponsorshipStatusJob::dispatch();

            Log::info('SyncSponsorshipStatus: Job dispatched', ['estimated_count' => $count]);

            return response()->json([
                'success'         => true,
                'message'         => 'تم بدء عملية المزامنة بنجاح',
                'estimated_count' => $count,
                'note'            => 'سيتم تحديث حالة الكفالة في جداول data و re_people تلقائياً.',
            ]);

        } catch (\Exception $e) {
            Log::error('SyncSponsorshipStatus: Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }
}

