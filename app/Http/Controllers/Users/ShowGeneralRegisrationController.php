<?php

namespace App\Http\Controllers\Users;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Data;
use App\Models\Sponsorship;
use App\Models\SponsorFieldSetting;
use App\Models\GuardianBankAccount;
use App\Models\RePeople;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;


class ShowGeneralRegisrationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $userIdNumber = trim($user->email); // رقم الهوية من الإيميل

        Log::info('=== USER DATA LOADING ===', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'identity_search' => $userIdNumber
        ]);

        // البحث عن الكفالة بطرق متعددة
        $sponsorship = Sponsorship::where('identity_number', $userIdNumber)
            ->orWhere('identity_number', 'LIKE', "%{$userIdNumber}%")
            ->first();

        // إذا لم نجد، نبحث برقم الملف
        if (!$sponsorship && is_numeric($userIdNumber)) {
            $sponsorship = Sponsorship::where('internal_file_number', $userIdNumber)->first();
        }

        if (!$sponsorship) {
            Log::error('SPONSORSHIP NOT FOUND', ['identity' => $userIdNumber]);
            return view('user.dashboard.component.generalRegisrationIndex', [
                'sponsorship' => null,
                'error' => 'لم يتم العثور على بيانات الكفالة'
            ]);
        }

        // تحميل جميع العلاقات
        $sponsorship->load([
            'sponsor',
            'relationData.province',
            'relationData.city',
            'relationData.healthStatus',
            'relationData.maritalStatus',
            'relationData.academicQualification',
            'relationData.housingStatus',
            'relationData.currentHousingType',
            'relationData.rePeople',
            'relationData.deadPepole',
        ]);

        Log::info('SPONSORSHIP FOUND', [
            'sponsorship_id' => $sponsorship->id,
            'orphan_name' => $sponsorship->orphan_name,
            'sponsor_id' => $sponsorship->sponsor_id
        ]);

        // جلب إعدادات الحقول للجمعية
        $sponsorId = $sponsorship->sponsor_id;
        $fieldSettings = null;
        $enabledFields = [];
        $fieldsConfig = config('sponsor_fields.fields', []);

        if ($sponsorId) {
            $fieldSettings = SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

            if ($fieldSettings) {
                // جلب الحقول المفعلة فقط (القيمة = 1)
                foreach ($fieldsConfig as $fieldKey => $fieldInfo) {
                    if (isset($fieldSettings->{$fieldKey}) && $fieldSettings->{$fieldKey} == 1) {
                        $enabledFields[$fieldKey] = $fieldInfo;
                    }
                }
            } else {
                // إذا لم توجد إعدادات، نعرض كل الحقول
                $enabledFields = $fieldsConfig;
            }
        }

        // جلب الحساب البنكي المعتمد فقط
        $approvedBankAccount = GuardianBankAccount::where('guardian_registration', $sponsorship->internal_file_number)
            ->where('check_account', 1)
            ->first();

        // تجميع الحقول حسب الفئات
        $groupedFields = [];
        $categories = config('sponsor_fields.categories', []);

        foreach ($enabledFields as $fieldKey => $fieldInfo) {
            $categoryId = $fieldInfo['category_id'];
            if (!isset($groupedFields[$categoryId])) {
                $groupedFields[$categoryId] = [
                    'name' => $categories[$categoryId] ?? 'غير محدد',
                    'fields' => []
                ];
            }
            $groupedFields[$categoryId]['fields'][] = $fieldInfo;
        }

        // ترتيب الحقول داخل كل فئة حسب order
        foreach ($groupedFields as $categoryId => $categoryData) {
            usort($groupedFields[$categoryId]['fields'], function($a, $b) {
                return ($a['order'] ?? 999) - ($b['order'] ?? 999);
            });
        }

        // ترتيب الفئات حسب الترتيب
        ksort($groupedFields);

        // Prepare field values from all sources
        $fieldValues = $this->extractFieldValues($sponsorship);

        // جلب قوائم السكن
        $housingStatuses = \App\Models\HousingStatus::all();
        $housingTypes = \App\Models\TypeOfAccommodation::all();

        // جلب قائمة الحالات الصحية
        $healthStatuses = \DB::table('health_statuses')->get();

        // جلب قائمة البنوك
        $bankNames = \DB::table('bank_names')->get();

        // جلب قائمة أسباب الوفاة
        $deathReasons = \DB::table('death_reasons')->get();

        // جلب قوائم المحافظات والمدن
        $provinces = \DB::table('provinces')->get();
        $cities = \DB::table('city')->get();

        // جلب جميع أفراد الأسرة من re_people
        $familyMembers = collect();
        if ($sponsorship->relation_id_number) {
            $familyMembers = \DB::table('re_people')
                ->where('registration_id', $sponsorship->relation_id_number)
                ->orderBy('person_id')
                ->get();
        }

        // جلب الحساب البنكي المعتمد فقط مع اسم البنك
        $approvedBankAccount = null;
        if ($sponsorship->relationData) {
            $approvedBankAccount = \DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $sponsorship->relationData->file_id_number)
                ->where('check_account', 1)
                ->first();

            // إضافة اسم البنك
            if ($approvedBankAccount) {
                $bankName = \DB::table('bank_names')
                    ->where('id', $approvedBankAccount->bank_name)
                    ->value('description');
                $approvedBankAccount->bank_name_text = $bankName;
            }
        }

        return view('user.dashboard.component.generalRegisrationIndex', compact(
            'sponsorship',
            'enabledFields',
            'groupedFields',
            'approvedBankAccount',
            'fieldsConfig',
            'fieldValues',
            'housingStatuses',
            'housingTypes',
            'healthStatuses',
            'familyMembers',
            'bankNames',
            'deathReasons',
            'provinces',
            'cities'
        ));
    }

    /**
     * استخراج قيم الحقول من جميع المصادر
     */
    private function extractFieldValues($sponsorship)
    {
        $values = [];
        $identityNumber = $sponsorship->identity_number;

        // From sponsorships table
        $values['field_sponsor_name'] = $sponsorship->orphan_name; // إسم المكفول
        $values['field_orphan_name'] = $sponsorship->orphan_name;
        $values['field_identity_number'] = $sponsorship->identity_number;
        $values['field_internal_file_number'] = $sponsorship->internal_file_number;

        // تحديد نوع الشخص المكفول
        $personType = null; // data, dead_people, re_people
        $guardianData = null; // بيانات المعيل

        // 1. البحث في data (معيل)
        $dataByIdentity = \DB::table('data')->where('data_id_number', $identityNumber)->first();
        if ($dataByIdentity) {
            $personType = 'data';
            $guardianData = $dataByIdentity;
        }

        // 2. البحث في dead_people (متوفي)
        if (!$personType) {
            $deadPerson = \DB::table('dead_people')
                ->where('father_id', $identityNumber)
                ->orWhere('mother_id', $identityNumber)
                ->first();
            if ($deadPerson) {
                $personType = 'dead_people';
                // جلب بيانات المعيل من data
                $guardianData = \DB::table('data')->where('file_id_number', $deadPerson->re_file_id)->first();
            }
        }

        // 3. البحث في re_people (فرد أسرة)
        $rePerson = null;
        if (!$personType) {
            $rePerson = \DB::table('re_people')->where('person_id', $identityNumber)->first();
            if ($rePerson) {
                $personType = 're_people';
                // جلب بيانات المعيل من data بناءً على registration_id
                $guardianData = \DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
            }
        }

        \Log::info('PERSON TYPE DETERMINED', [
            'identity' => $identityNumber,
            'type' => $personType,
            'has_guardian_data' => $guardianData ? true : false
        ]);

        // تاريخ الميلاد
        if ($personType == 're_people' && $rePerson && $rePerson->person_birth_date) {
            $values['field_person_birth_date'] = $rePerson->person_birth_date;
        } elseif ($personType == 'data' && $dataByIdentity && $dataByIdentity->data_birth_date) {
            $values['field_person_birth_date'] = $dataByIdentity->data_birth_date;
        }

        // الحالة الصحية
        $healthStatusValue = '';
        if ($personType == 're_people' && $rePerson && $rePerson->person_health_status) {
            $healthStatus = \DB::table('health_statuses')->where('id', $rePerson->person_health_status)->first();
            $healthStatusValue = $healthStatus->description ?? '';
        } elseif ($personType == 'data' && $dataByIdentity && $dataByIdentity->data_health_status) {
            $healthStatus = \DB::table('health_statuses')->where('id', $dataByIdentity->data_health_status)->first();
            $healthStatusValue = $healthStatus->description ?? '';
        }
        $values['field_health_status'] = $healthStatusValue;

        // حالة السكن ونوع السكن - دائماً من بيانات المعيل (data)
        if ($guardianData) {
            $housingStatus = \DB::table('housing_status')->where('id', $guardianData->data_housing_status)->first();
            $values['field_housing_status'] = $housingStatus->description ?? '';

            $housingType = \DB::table('type_of_accommodation')->where('id', $guardianData->data_current_housing_type)->first();
            $values['field_housing_type'] = $housingType->description ?? '';

            \Log::info('HOUSING DATA EXTRACTED', [
                'housing_status_id' => $guardianData->data_housing_status,
                'housing_status' => $values['field_housing_status'],
                'housing_type_id' => $guardianData->data_current_housing_type,
                'housing_type' => $values['field_housing_type']
            ]);
        }

        $values['field_internal_file_number'] = $sponsorship->internal_file_number;
        $values['field_external_file_number'] = $sponsorship->external_file_number;
        $values['field_guardian_name'] = $sponsorship->guardian_name;
        $values['field_guardian_identity_number'] = $sponsorship->guardian_identity_number;
        $values['field_sponsoring_organization'] = $sponsorship->sponsoring_organization;
        $values['field_sponsorship_start_date'] = $sponsorship->sponsorship_start_date;
        $values['field_sponsorship_end_date'] = $sponsorship->sponsorship_end_date;
        $values['field_sponsorship_duration_months'] = $sponsorship->sponsorship_duration_months;
        $values['field_notes'] = $sponsorship->notes;

        // From relationData (data table)
        if ($sponsorship->relationData) {
            $data = $sponsorship->relationData;
            $values['field_data_id_number'] = $data->data_id_number;
            $values['field_data_first_name'] = $data->data_first_name;
            $values['field_data_father_name'] = $data->data_father_name;
            $values['field_data_grand_father_name'] = $data->data_grand_father_name;
            $values['field_data_family_name'] = $data->data_family_name;
            $values['field_data_birth_date'] = $data->data_birth_date;
            $values['field_data_phone_number'] = $data->data_phone_number;
            $values['field_data_address'] = $data->data_address ?? $data->data_current_address;

            // جلب المحافظة والمدينة
            $values['field_data_province'] = optional($data->province)->description ?? '';
            $values['field_data_city'] = optional($data->city)->city ?? $data->data_city;

            // Dead people info
            if ($data->deadPepole) {
                $dead = $data->deadPepole;
                $values['field_father_first_name'] = trim("{$dead->father_first_name} {$dead->father_second_name} {$dead->father_third_name} {$dead->father_last_name}");
                $values['field_father_id'] = $dead->father_id;
                $values['field_father_death_date'] = $dead->father_death_date;

                // جلب سبب وفاة الأب
                if ($dead->father_death_reason) {
                    $fatherDeathReason = \DB::table('death_reasons')
                        ->where('id', $dead->father_death_reason)
                        ->first();
                    $values['field_father_death_reason'] = $fatherDeathReason ? $fatherDeathReason->description : '';
                }

                $values['field_mother_first_name'] = trim("{$dead->mother_first_name} {$dead->mother_second_name} {$dead->mother_third_name} {$dead->mother_last_name}");
                $values['field_mother_id'] = $dead->mother_id;
                $values['field_mother_death_date'] = $dead->mother_death_date;

                // جلب سبب وفاة الأم
                if ($dead->mother_death_reason) {
                    $motherDeathReason = \DB::table('death_reasons')
                        ->where('id', $dead->mother_death_reason)
                        ->first();
                    $values['field_mother_death_reason'] = $motherDeathReason ? $motherDeathReason->description : '';
                }
            }

            // Re people info (orphan data) - نأخذ أول سجل من المجموعة
            if ($data->rePeople && $data->rePeople->count() > 0) {
                $re = $data->rePeople->first();
                $values['field_first_name'] = trim("{$re->first_name} {$re->second_name} {$re->third_name} {$re->last_name}");
                $values['field_person_id'] = $re->person_id;
                $values['field_person_birth_date'] = $re->person_birth_date;
                $values['field_person_age'] = $re->person_age;
                $values['field_person_gender'] = $re->person_gender == 1 ? 'ذكر' : ($re->person_gender == 2 ? 'أنثى' : '');
                $values['field_person_note'] = $re->person_note;
            }
        }

        // جلب بيانات أفراد الأسرة من re_people
        if ($sponsorship->relation_id_number) {
            $familyMembersData = \DB::table('re_people')
                ->where('registration_id', $sponsorship->relation_id_number)
                ->orderBy('person_id')
                ->get();

            // دمج البيانات في حقول مفصولة بفاصلة
            $siblings_names = [];
            $siblings_birthdates = [];
            $siblings_grades = [];
            $siblings_notes = [];

            foreach ($familyMembersData as $member) {
                if (!empty($member->first_name)) {
                    $siblings_names[] = trim("{$member->first_name} {$member->second_name} {$member->third_name} {$member->last_name}");
                }
                if (!empty($member->person_birth_date)) {
                    $siblings_birthdates[] = $member->person_birth_date;
                }
                if (!empty($member->person_class)) {
                    $siblings_grades[] = $member->person_class;
                }
                if (!empty($member->person_note)) {
                    $siblings_notes[] = $member->person_note;
                }
            }

            $values['field_siblings_names'] = implode("\n", $siblings_names);
            $values['field_sibling_birthdate'] = implode("\n", $siblings_birthdates);
            $values['field_sibling_grade'] = implode("\n", $siblings_grades);
            $values['field_sibling_notes'] = implode("\n", $siblings_notes);
        }

        // جلب الحساب البنكي المعتمد من guardian_bank_accounts
        if ($guardianData && $guardianData->file_id_number) {
            $approvedBankAccount = \DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardianData->file_id_number)
                ->where('check_account', 1)
                ->first();

            if ($approvedBankAccount) {
                // استخدام أسماء الحقول الجديدة بنفس الترتيب في الصورة
                $values['field_guardian_account_owner_name'] = $approvedBankAccount->re_guardian_name;
                $values['field_guardian_bank_name'] = $approvedBankAccount->bank_name; // سيتم عرضه كـ dropdown
                $values['field_guardian_id_owner'] = $approvedBankAccount->person_owner_identity_number;
                $values['field_guardian_phone_number'] = $approvedBankAccount->re_phone_number;
                $values['field_guardian_iban_usd'] = $approvedBankAccount->iban_usd;
                $values['field_guardian_iban_shekel'] = $approvedBankAccount->iban_shekel;

                \Log::info('BANK ACCOUNT DATA LOADED', [
                    'account_owner' => $approvedBankAccount->re_guardian_name,
                    'bank_id' => $approvedBankAccount->bank_name,
                    'iban_usd' => $approvedBankAccount->iban_usd
                ]);
            } else {
                \Log::warning('NO APPROVED BANK ACCOUNT FOUND', [
                    'guardian_file' => $guardianData->file_id_number
                ]);
            }
        }

        Log::info('FIELD VALUES EXTRACTED', [
            'total_values' => count($values),
            'has_bank_account' => isset($bankAccount)
        ]);

        return $values;
    }

    /**
     * تحديث بيانات الكفالة
     */
    public function updateSponsorshipData(Request $request)
    {
        try {
            $user = Auth::user();
            $sponsorshipId = $request->input('sponsorship_id');

            // التحقق من أن المستخدم يملك هذه الكفالة
            $sponsorship = Sponsorship::with('relationData')
                ->where('id', $sponsorshipId)
                ->where('identity_number', $user->email)
                ->firstOrFail();

            // التحقق من البيانات
            $request->validate([
                'fields' => 'array',
                'family_members' => 'array',
                'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB
            ]);

            DB::beginTransaction();

            // تحديث الحقول الأساسية في جدول sponsorships
            $sponsorshipFields = ['orphan_name', 'identity_number', 'birth_date', 'internal_file_number',
                                 'guardian_name', 'guardian_phone', 'guardian_relationship'];

            $fieldsData = $request->input('fields', []);

            // حقول البنك الجديدة
            $bankFields = [];
            // حقول أسباب الوفاة
            $deathReasonFields = [];

            foreach ($fieldsData as $fieldKey => $fieldValue) {
                $cleanFieldKey = str_replace('field_', '', $fieldKey);

                // جمع حقول البنك
                if (in_array($fieldKey, [
                    'field_guardian_account_owner_name',
                    'field_guardian_bank_name',
                    'field_guardian_id_owner',
                    'field_guardian_phone_number',
                    'field_guardian_iban_usd',
                    'field_guardian_iban_shekel'
                ])) {
                    $bankFields[$fieldKey] = $fieldValue;
                    continue;
                }

                // جمع حقول أسباب الوفاة
                if (in_array($fieldKey, ['field_father_death_reason', 'field_mother_death_reason'])) {
                    $deathReasonFields[$fieldKey] = $fieldValue;
                    continue;
                }

                // تحديث في جدول sponsorships
                if (in_array($cleanFieldKey, $sponsorshipFields)) {
                    $sponsorship->$cleanFieldKey = $fieldValue;
                }
                // تحديث في جدول data (relationData)
                else if ($sponsorship->relationData) {
                    $dataColumn = 'data_' . $cleanFieldKey;
                    if (Schema::hasColumn('data', $dataColumn)) {
                        $sponsorship->relationData->$dataColumn = $fieldValue;
                    }
                }
            }

            // تحديث البيانات البنكية
            if (!empty($bankFields) && $sponsorship->relationData) {
                $guardianFileNumber = $sponsorship->relationData->file_id_number;

                // البحث عن الحساب المعتمد
                $bankAccount = \DB::table('guardian_bank_accounts')
                    ->where('guardian_registration', $guardianFileNumber)
                    ->where('check_account', 1)
                    ->first();

                if ($bankAccount) {
                    // تحديث البيانات البنكية
                    \DB::table('guardian_bank_accounts')
                        ->where('id', $bankAccount->id)
                        ->update([
                            're_guardian_name' => $bankFields['field_guardian_account_owner_name'] ?? $bankAccount->re_guardian_name,
                            'bank_name' => $bankFields['field_guardian_bank_name'] ?? $bankAccount->bank_name,
                            'person_owner_identity_number' => $bankFields['field_guardian_id_owner'] ?? $bankAccount->person_owner_identity_number,
                            're_phone_number' => $bankFields['field_guardian_phone_number'] ?? $bankAccount->re_phone_number,
                            'iban_usd' => $bankFields['field_guardian_iban_usd'] ?? $bankAccount->iban_usd,
                            'iban_shekel' => $bankFields['field_guardian_iban_shekel'] ?? $bankAccount->iban_shekel,
                        ]);
                }
            }

            // تحديث أسباب الوفاة في جدول dead_people
            if (!empty($deathReasonFields) && $sponsorship->relationData) {
                $deadPeople = \DB::table('dead_people')
                    ->where('re_file_id', $sponsorship->relationData->file_id_number)
                    ->first();

                if ($deadPeople) {
                    $updates = [];

                    // تحويل وصف سبب الوفاة إلى ID
                    if (isset($deathReasonFields['field_father_death_reason'])) {
                        $reason = \DB::table('death_reasons')
                            ->where('description', $deathReasonFields['field_father_death_reason'])
                            ->first();
                        if ($reason) {
                            $updates['father_death_reason'] = $reason->id;
                        }
                    }

                    if (isset($deathReasonFields['field_mother_death_reason'])) {
                        $reason = \DB::table('death_reasons')
                            ->where('description', $deathReasonFields['field_mother_death_reason'])
                            ->first();
                        if ($reason) {
                            $updates['mother_death_reason'] = $reason->id;
                        }
                    }

                    if (!empty($updates)) {
                        \DB::table('dead_people')
                            ->where('id', $deadPeople->id)
                            ->update($updates);
                    }
                }
            }

            $sponsorship->save();

            if ($sponsorship->relationData) {
                $sponsorship->relationData->save();
            }

            // تحديث أفراد الأسرة من حقول النص
            if (isset($fieldsData['field_siblings_names'])) {
                $this->updateFamilyMembersFromFields(
                    $sponsorship,
                    $fieldsData['field_siblings_names'] ?? '',
                    $fieldsData['field_sibling_birthdate'] ?? '',
                    $fieldsData['field_sibling_grade'] ?? '',
                    $fieldsData['field_sibling_notes'] ?? ''
                );
            }

            // تحديث أفراد الأسرة (الطريقة القديمة - للتوافق)
            if ($request->has('family_members')) {
                $familyMembers = $request->input('family_members');

                foreach ($familyMembers as $memberData) {
                    // تخطي السجلات الفارغة
                    if (empty($memberData['first_name'])) {
                        continue;
                    }

                    if (isset($memberData['id']) && $memberData['id']) {
                        // تحديث فرد موجود
                        \DB::table('re_people')
                            ->where('id', $memberData['id'])
                            ->update([
                                'first_name' => $memberData['first_name'] ?? '',
                                'person_birth_date' => $memberData['person_birth_date'] ?? null,
                                'person_class' => $memberData['person_class'] ?? '',
                                'person_note' => $memberData['person_note'] ?? '',
                                'updated_at' => now(),
                            ]);
                    } else {
                        // إضافة فرد جديد
                        if ($sponsorship->relation_id_number) {
                            // توليد person_id جديد
                            $newPersonId = \DB::table('re_people')->max('person_id') + 1;
                            
                            \DB::table('re_people')->insert([
                                'registration_id' => $sponsorship->relation_id_number,
                                'person_id' => $newPersonId,
                                'first_name' => $memberData['first_name'],
                                'person_birth_date' => $memberData['person_birth_date'] ?? null,
                                'person_class' => $memberData['person_class'] ?? '',
                                'person_note' => $memberData['person_note'] ?? '',
                                'person_type_of_guarantee' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // معالجة المرفقات الجديدة
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs('attachments', $filename, 'public');

                    if ($sponsorship->relationData) {
                        Attachment::create([
                            'file_id_number' => $sponsorship->relationData->file_id_number,
                            'stored_file_name' => $filename,
                            'file_path' => 'storage/attachments/' . $filename,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('user.generalRegistration.index')
                ->with('success', 'تم حفظ التغييرات بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UPDATE_SPONSORSHIP_ERROR', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'sponsorship_id' => $request->input('sponsorship_id')
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage());
        }
    }

    /**
     * تحديث أفراد الأسرة من حقول النص المفصولة بأسطر
     */
    private function updateFamilyMembersFromFields($sponsorship, $names, $birthdates, $grades, $notes)
    {
        if (empty($names) || !$sponsorship->relation_id_number) {
            return;
        }

        // تقسيم البيانات إلى مصفوفات
        $namesArray = array_filter(explode("\n", $names));
        $birthdatesArray = explode("\n", $birthdates);
        $gradesArray = explode("\n", $grades);
        $notesArray = explode("\n", $notes);

        // حذف أفراد الأسرة الحاليين
        \DB::table('re_people')
            ->where('registration_id', $sponsorship->relation_id_number)
            ->delete();

        // إضافة أفراد الأسرة الجدد
        foreach ($namesArray as $index => $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            // تقسيم الاسم إلى أجزاء
            $nameParts = explode(' ', $name);
            $firstName = $nameParts[0] ?? '';
            $secondName = $nameParts[1] ?? '';
            $thirdName = $nameParts[2] ?? '';
            $lastName = $nameParts[3] ?? '';

            // توليد person_id جديد
            $newPersonId = \DB::table('re_people')->max('person_id') + 1;

            \DB::table('re_people')->insert([
                'registration_id' => $sponsorship->relation_id_number,
                'person_id' => $newPersonId,
                'first_name' => $firstName,
                'second_name' => $secondName,
                'third_name' => $thirdName,
                'last_name' => $lastName,
                'person_birth_date' => trim($birthdatesArray[$index] ?? ''),
                'person_class' => trim($gradesArray[$index] ?? ''),
                'person_note' => trim($notesArray[$index] ?? ''),
                'person_type_of_guarantee' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
