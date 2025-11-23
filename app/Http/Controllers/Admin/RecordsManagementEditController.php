<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Data;
use App\Models\GeneralCategory;
use App\Models\CategoryOfRelation;
use App\Models\CI_PERSONAL_CD;
use App\Models\AcademicDegree;
use App\Models\DisplacementStatus;
use App\Models\City;
use App\Models\Province;
use App\Models\HealthStatus;
use App\Models\Employment;
use App\Models\HousingStatus;
use App\Models\TypeOfAccommodation;
use App\Models\DocumentType;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfGuarantee;
use App\Models\DeathReason;
use App\Models\Attachment;
use App\Models\RePeople;
use Illuminate\Support\Facades\Log;

class RecordsManagementEditController extends Controller
{
    /**
     * عرض تقرير نشاط الموظفين (admins) مع كروت إحصائية
     */
    public function adminActivityReport()
    {
        // جلب جميع المستخدمين من نوع admin
        $admins = \App\Models\User::where('role', 'admin')->get();

        // جلب جميع السجلات من جدول Data لمطابقة الإدخالات
        $allData = Data::select('id', 'data_user_insert_data', 'created_at')
            ->get();

        // اليوم الحالي
        $today = now()->format('Y-m-d');
        $month = now()->format('Y-m');
        $year = now()->format('Y');

        $adminsStats = $admins->map(function($admin) use ($allData, $today, $month, $year) {
            $userName = $admin->name;
            // جميع السجلات التي أدخلها هذا المستخدم
            $userData = $allData->where('data_user_insert_data', $userName);

            // عدد السجلات اليوم
            $countToday = $userData->filter(function($row) use ($today) {
                return optional($row->created_at)->format('Y-m-d') === $today;
            })->count();
            // عدد السجلات هذا الشهر
            $countMonth = $userData->filter(function($row) use ($month) {
                return optional($row->created_at)->format('Y-m') === $month;
            })->count();
            // عدد السجلات هذه السنة
            $countYear = $userData->filter(function($row) use ($year) {
                return optional($row->created_at)->format('Y') === $year;
            })->count();

            return [
                'user' => $admin,
                'count_today' => $countToday,
                'count_month' => $countMonth,
                'count_year' => $countYear,
            ];
        });

        return view('admin.dashboard.admin_activity_report', [
            'adminsStats' => $adminsStats
        ]);
    }
    public function edit($id)
    {
        $data = Data::findOrFail($id);
        // جلب نفس البيانات المساعدة كما في create
        $generalSection = GeneralCategory::all();
        $category_of_relationship = CategoryOfRelation::all();
        $ci_personal_cd = CI_PERSONAL_CD::all(); // الحالة الاجتماعية
        $academic_qualification = AcademicDegree::all();
        $displacement_status = DisplacementStatus::all();
        $city = City::all();
        $province = Province::all();
        $health_status = HealthStatus::all();
        $employment_status_breadwinner = Employment::all();
        $housing_status = HousingStatus::all();
        $TypeOfAccommodation = TypeOfAccommodation::all();
        $documentTypes = DocumentType::all();
        $sponsorship_status = SponsorshipStatus::all();
        $guarantee_types = TypeOfGuarantee::all();
        $death_reasons = DeathReason::all();
        return view('admin.dashboard.records_management.edit', compact(
            'data',
            'generalSection',
            'category_of_relationship',
            'ci_personal_cd',
            'academic_qualification',
            'displacement_status',
            'city',
            'province',
            'health_status',
            'employment_status_breadwinner',
            'housing_status',
            'TypeOfAccommodation',
            'documentTypes',
            'sponsorship_status',
            'guarantee_types',
            'death_reasons'
        ));
    }

    public function show($id)
    {
        $data = Data::with([
            'section',
            'requestStatus',
            'categoryOfRelation',
            'healthStatus',
            'maritalStatus',
            'academicQualification',
            'city',
            'employmentStatusBreadwinner',
            'province',
            'housingStatus',
            'currentHousingType',
            'attachments',
            'rePeople.attachments', // إضافة مرفقات أفراد الأسرة
            'deadPepole.fatherAttachments', // إضافة مرفقات الأب
            'deadPepole.motherAttachments', // إضافة مرفقات الأم
        ])->findOrFail($id);
        return view('admin.dashboard.records_management.show', compact('data'));
    }

 public function update(Request $request, $id)
    {
        try {
            Log::info('--- Start update with nested attachmentsByPerson ---');
            Log::info('Request all:', $request->all());
            Log::info('Request files:', $request->allFiles());

            // معالجة file_id لأفراد الأسرة إذا كان فارغاً
            if ($request->has('family_members')) {
                $familyMembers = $request->input('family_members');
                $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);
                foreach ($familyMembers as $idx => $member) {
                    if (empty($member['file_id'])) {
                        $familyMembers[$idx]['file_id'] = $fileIdNumber;
                    }
                }
                $request->merge(['family_members' => $familyMembers]);
            }

            // 1. فلترة attachments_to_delete وتحويلها إلى أرقام صحيحة
            if ($request->has('attachments_to_delete')) {
                $filtered = array_filter((array)$request->input('attachments_to_delete'), function($v){
                    // دعم القيم النصية الرقمية
                    return is_numeric($v) && intval($v) == $v;
                });
                // تحويل كل عنصر إلى int فعلياً
                $filtered = array_map('intval', $filtered);
                $request->merge(['attachments_to_delete' => array_values($filtered)]);
            }

            // 2. Validation للحقلات الأساسية + attachmentsByPerson
            $validationRules = [
                'file_id_number' => 'required|string',
                'data_section_id' => 'required|integer',
                'data_id_number' => 'required|string',
                'data_first_name' => 'required|string|max:255',
                'data_father_name' => 'nullable|string|max:255',
                'data_grand_father_name' => 'nullable|string|max:255',
                'data_family_name' => 'nullable|string|max:255',
                'data_relationship' => 'nullable|string|max:255',
                'data_birth_date' => 'nullable|date',
                'data_gender' => 'nullable|string|max:10',
                'data_phone_number' => 'nullable|string|max:20',
                'data_alt_phone_number' => 'nullable|string|max:20',
                'data_number_of_individuals' => 'nullable|integer',
                'data_marital_status' => 'nullable|integer',
                'data_academic_qualification' => 'nullable|integer',
                'data_displacement_status' => 'nullable|integer',
                'data_address_before_displacement' => 'nullable|string',
                'data_current_address' => 'nullable|string',
                'data_city' => 'nullable|integer',
                'data_province' => 'nullable|integer',
                'data_health_status' => 'nullable|integer',
                'data_description_needs' => 'nullable|string',
                'data_number_mail' => 'nullable|string',
                'data_number_female' => 'nullable|integer',
                'data_number_of_individuals_with_chronic_diseases' => 'nullable|integer',
                'data_number_of_people_with_special_needs' => 'nullable|integer',
                'data_employment_status_breadwinner' => 'nullable|integer',
                'data_housing_status' => 'nullable|integer',
                'data_current_housing_type' => 'nullable|string',
                'data_user_insert_data' => 'nullable|string',
                'data_request_status' => 'nullable|integer',
                'attachments_to_delete' => 'sometimes|array',
                // هنا: استخدم integer فقط (بدون exists) أو استخدم exists:attachments,id إذا كنت متأكد أن القيم أرقام صحيحة
                'attachments_to_delete.*' => 'integer|exists:attachments,id',
                'attachmentsByPerson.*.person_identity_number' => 'required',
                'attachmentsByPerson.*.file_id_number' => 'required',
                // لا تضف قاعدة file هنا نهائياً
                'attachmentsByPerson.*.documents.*.file_type' => 'required|string',
                'attachmentsByPerson.*.documents.*.stored_file_name' => 'required|string',
                // تأكد أن أفراد الأسرة لديهم file_id_number
                'family_members.*.file_id' => 'required|string',
            ];
            $validationMessages = [
                'attachmentsByPerson.*.documents.*.file.required' => 'ملف الوثيقة مطلوب.',
                'attachmentsByPerson.*.documents.*.file.mimes' => 'صيغة الملف غير مدعومة.',
                'attachmentsByPerson.*.documents.*.file.max' => 'حجم الملف لا يتجاوز 5 ميغابايت.',
                'attachmentsByPerson.*.documents.*.file_type.required' => 'نوع الوثيقة مطلوب.',
                'attachmentsByPerson.*.person_identity_number.required' => 'رقم هوية الشخص مطلوب.',
                'attachmentsByPerson.*.file_id_number.required' => 'رقم الملف العام مطلوب.',
            ];
            $request->validate($validationRules, $validationMessages);


            DB::beginTransaction();

            // جلب السجل الرئيسي
            $data = Data::findOrFail($id);
            Log::info('Loaded Data for update:', $data->toArray());

            // 3. حذف المرفقات المطلوبة (existing attachments_to_delete)
            $attachmentsToDelete = $request->input('attachments_to_delete', []);
            Log::info('Attachments to delete:', $attachmentsToDelete);
            foreach ($attachmentsToDelete as $attId) {
                $attachment = Attachment::find($attId);
                if ($attachment) {
                    $storagePath = str_replace('storage/', '', $attachment->file_path);
                    if (Storage::disk('public')->exists($storagePath)) {
                        Storage::disk('public')->delete($storagePath);
                    }
                    $attachment->delete();
                    Log::info("Deleted existing attachment ID {$attId}");
                }
            }

            // 4. تحديث البيانات الأساسية
            $fileIdNumberRaw = $request->input('file_id_number');
            $fileIdNumber = str_pad($fileIdNumberRaw, 6, '0', STR_PAD_LEFT);
            $oldDataIdNumber = $data->getOriginal('data_id_number');
            $newDataIdNumber = $request->input('data_id_number');

            $data->update([
                'file_id_number' => $fileIdNumber,
                'data_section_id' => $request->input('data_section_id'),
                'data_id_number' => $newDataIdNumber,
                'data_first_name' => $request->input('data_first_name'),
                'data_father_name' => $request->input('data_father_name'),
                'data_grand_father_name' => $request->input('data_grand_father_name'),
                'data_family_name' => $request->input('data_family_name'),
                'data_relationship' => $request->input('data_relationship'),
                'data_birth_date' => $request->input('data_birth_date'),
                'data_gender' => $request->input('data_gender'),
                'data_phone_number' => $request->input('data_phone_number'),
                'data_alt_phone_number' => $request->input('data_alt_phone_number'),
                'data_number_of_individuals' => $request->input('data_number_of_individuals'),
                'data_marital_status' => $request->input('data_marital_status'),
                'data_academic_qualification' => $request->input('data_academic_qualification'),
                'data_displacement_status' => $request->input('data_displacement_status'),
                'data_address_before_displacement' => $request->input('data_address_before_displacement'),
                'data_current_address' => $request->input('data_current_address'),
                'data_city' => $request->input('data_city'),
                'data_province' => $request->input('data_province'),
                'data_health_status' => $request->input('data_health_status'),
                'data_description_needs' => $request->input('data_description_needs'),
                'data_number_mail' => $request->input('data_number_mail'),
                'data_number_female' => $request->input('data_number_female'),
                'data_number_of_individuals_with_chronic_diseases' => $request->input('data_number_of_individuals_with_chronic_diseases'),
                'data_number_of_people_with_special_needs' => $request->input('data_number_of_people_with_special_needs'),
                'data_employment_status_breadwinner' => $request->input('data_employment_status_breadwinner'),
                'data_housing_status' => $request->input('data_housing_status'),
                'data_current_housing_type' => $request->input('data_current_housing_type'),
                'data_user_insert_data' => $request->input('data_user_insert_data'),
                'data_request_status' => 2,
            ]);

            Log::info('Updated Data basic info');

            // 5. معالجة نقل/إعادة تسمية مرفقات قديمة عند تغيير رقم الهوية الرئيسي
            if ($oldDataIdNumber && $oldDataIdNumber !== $newDataIdNumber) {
                Log::info('Updating attachments for changed data_id_number', ['old'=>$oldDataIdNumber,'new'=>$newDataIdNumber]);
                $oldAttachments = Attachment::where('person_identity_number', $oldDataIdNumber)->get();
                foreach ($oldAttachments as $attachment) {
                    $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                    $parts = explode('_', $attachment->stored_file_name);
                    $docType = $parts[0] ?? 'doc';
                    $newFileName = "{$docType}_{$fileIdNumber}_{$newDataIdNumber}.{$extension}";
                    $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                    $newRelativePath = "attachments/{$fileIdNumber}/{$newFileName}";
                    if (Storage::disk('public')->exists($oldStoragePath)) {
                        Storage::disk('public')->move($oldStoragePath, $newRelativePath);
                    }
                    $attachment->update([
                        'person_identity_number' => $newDataIdNumber,
                        'stored_file_name' => $newFileName,
                        'file_path' => "storage/{$newRelativePath}",
                    ]);
                    Log::info("Renamed old attachment to {$newFileName}");
                }
            }

            // 7. معالجة أفراد الأسرة (حذف القدامى ثم إضافة الجدد)
            if ($request->has('family_members')) {
                // حذف جميع أفراد الأسرة المرتبطين بهذا السجل
                $oldFamilyMembers = $data->rePeople()->get()->keyBy('id');
                $data->rePeople()->delete();

                $familyMembers = $request->input('family_members');
                $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);
                foreach ($familyMembers as $member) {
                    // إذا كان هناك id قديم، تحقق من تغيير رقم الهوية
                    $oldMember = isset($member['id']) ? $oldFamilyMembers->get($member['id']) : null;
                    $oldPersonId = $oldMember ? $oldMember->person_id : null;
                    $newPersonId = $member['person_id'] ?? null;

                    // إضافة فرد الأسرة الجديد
                    $newRePeople = \App\Models\RePeople::create([
                        'file_id' => $member['file_id'] ?? $fileIdNumber,
                        'registration_id' => $member['registration_id'] ?? $fileIdNumber,
                        'sponsorship_status' => $member['sponsorship_status'] ?? null,
                        'first_name' => $member['first_name'] ?? null,
                        'second_name' => $member['second_name'] ?? null,
                        'third_name' => $member['third_name'] ?? null,
                        'last_name' => $member['last_name'] ?? null,
                        'person_id' => $newPersonId,
                        'person_birth_date' => $member['person_birth_date'] ?? null,
                        'person_age' => $member['person_age'] ?? null,
                        'person_gender' => $member['person_gender'] ?? null,
                        'person_health_status' => $member['person_health_status'] ?? null,
                        'person_type_of_guarantee' => $member['person_type_of_guarantee'] ?? null,
                    ]);

                    // إذا تغير رقم الهوية، عدل المرفقات المرتبطة
                    if ($oldPersonId && $newPersonId && $oldPersonId != $newPersonId) {
                        $attachments = \App\Models\Attachment::where('person_identity_number', $oldPersonId)->get();
                        foreach ($attachments as $attachment) {
                            $folder = dirname(str_replace('storage/', '', $attachment->file_path));
                            $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                            $parts = explode('_', $attachment->stored_file_name);
                            $docType = $parts[0] ?? 'doc';
                            $newFileName = "{$docType}_{$fileIdNumber}_{$newPersonId}.{$extension}";
                            $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                            $newStoragePath = $folder . '/' . $newFileName;

                            // إعادة تسمية الملف في نفس المجلد
                            if (Storage::disk('public')->exists($oldStoragePath)) {
                                Storage::disk('public')->move($oldStoragePath, $newStoragePath);
                            }

                            $attachment->update([
                                'person_identity_number' => $newPersonId,
                                'stored_file_name' => $newFileName,
                                'file_path' => "storage/{$newStoragePath}",
                            ]);
                        }
                    }
                }
            }

            // 6. معالجة المتوفين وتحديث مرفقاتهم إذا تغيرت الهويات (كما في السابق)
            if ($request->input('data_section_id') == 1) {
                $dead = $data->deadPepole()->first();
                $fatherFilled = $request->filled('father_first_name') || $request->filled('father_last_name') || $request->filled('father_id');
                $motherFilled = $request->filled('mother_first_name') || $request->filled('mother_last_name') || $request->filled('mother_id');
                if ($fatherFilled || $motherFilled) {
                    $deadData = [
                        're_file_id' => $fileIdNumber,
                        // بيانات الأب
                        'father_first_name' => $request->input('father_first_name'),
                        'father_second_name' => $request->input('father_second_name'),
                        'father_third_name' => $request->input('father_third_name'),
                        'father_last_name' => $request->input('father_last_name'),
                        'father_id' => $request->input('father_id'),
                        'father_death_date' => $request->input('father_death_date'),
                        'father_death_reason' => $request->input('father_death_reason'),
                        // بيانات الأم
                        'mother_first_name' => $request->input('mother_first_name'),
                        'mother_second_name' => $request->input('mother_second_name'),
                        'mother_third_name' => $request->input('mother_third_name'),
                        'mother_last_name' => $request->input('mother_last_name'),
                        'mother_id' => $request->input('mother_id'),
                        'mother_death_date' => $request->input('mother_death_date'),
                        'mother_death_reason' => $request->input('mother_death_reason'),
                    ];
                    $oldFatherId = $dead ? $dead->father_id : null;
                    $oldMotherId = $dead ? $dead->mother_id : null;
                    $newFatherId = $request->input('father_id');
                    $newMotherId = $request->input('mother_id');

                    if ($dead) {
                        $dead->update($deadData);
                    } else {
                        \App\Models\DeadPepole::create($deadData);
                    }

                    // إذا تغير رقم هوية الأب، عدل المرفقات المرتبطة
                    if ($oldFatherId && $newFatherId && $oldFatherId != $newFatherId) {
                        $attachments = \App\Models\Attachment::where('person_identity_number', $oldFatherId)->get();
                        foreach ($attachments as $attachment) {
                            $folder = dirname(str_replace('storage/', '', $attachment->file_path));
                            $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                            $parts = explode('_', $attachment->stored_file_name);
                            $docType = $parts[0] ?? 'doc';
                            $newFileName = "{$docType}_{$fileIdNumber}_{$newFatherId}.{$extension}";
                            $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                            $newStoragePath = $folder . '/' . $newFileName;

                            if (Storage::disk('public')->exists($oldStoragePath)) {
                                Storage::disk('public')->move($oldStoragePath, $newStoragePath);
                            }

                            $attachment->update([
                                'person_identity_number' => $newFatherId,
                                'stored_file_name' => $newFileName,
                                'file_path' => "storage/{$newStoragePath}",
                            ]);
                        }
                    }
                    // إذا تغير رقم هوية الأم، عدل المرفقات المرتبطة
                    if ($oldMotherId && $newMotherId && $oldMotherId != $newMotherId) {
                        $attachments = \App\Models\Attachment::where('person_identity_number', $oldMotherId)->get();
                        foreach ($attachments as $attachment) {
                            $folder = dirname(str_replace('storage/', '', $attachment->file_path));
                            $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                            $parts = explode('_', $attachment->stored_file_name);
                            $docType = $parts[0] ?? 'doc';
                            $newFileName = "{$docType}_{$fileIdNumber}_{$newMotherId}.{$extension}";
                            $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                            $newStoragePath = $folder . '/' . $newFileName;

                            if (Storage::disk('public')->exists($oldStoragePath)) {
                                Storage::disk('public')->move($oldStoragePath, $newStoragePath);
                            }

                            $attachment->update([
                                'person_identity_number' => $newMotherId,
                                'stored_file_name' => $newFileName,
                                'file_path' => "storage/{$newStoragePath}",
                            ]);
                        }
                    }
                }
            }

            // 8. معالجة المرفقات الجديدة المتداخلة من attachmentsByPerson
            if ($request->has('attachmentsByPerson')) {
                foreach ($request->input('attachmentsByPerson') as $pIndex => $personData) {
                    $personKey = $personData['person_key'] ?? null;
                    $personIdentityNumber = $personData['person_identity_number'];
                    // استخدم دومًا رقم الملف الحالي للمجلد
                    $fileIdNumberPadded = $fileIdNumber;

                    if (!isset($personData['documents']) || !is_array($personData['documents'])) {
                        continue;
                    }
                    foreach ($personData['documents'] as $dIndex => $docMeta) {
                        // جلب UploadedFile
                        $uploadedFile = $request->file("attachmentsByPerson.{$pIndex}.documents.{$dIndex}.file");
                        if ($uploadedFile instanceof \Illuminate\Http\UploadedFile && $uploadedFile->isValid()) {
                            $allowed = ['jpeg', 'jpg', 'png', 'pdf'];
                            $ext = strtolower($uploadedFile->getClientOriginalExtension());
                            if (!in_array($ext, $allowed)) {
                                throw new \Exception("صيغة الملف غير مدعومة ({$ext})");
                            }
                            $fileType = $docMeta['file_type'];
                            $storedFileName = $docMeta['stored_file_name'];
                            if (!str_ends_with($storedFileName, ".{$ext}")) {
                                $storedFileName = "{$fileType}_{$fileIdNumberPadded}_{$personIdentityNumber}.{$ext}";
                            }
                            // استخدم مجلد uploads وليس attachments
                            $folder = "uploads/{$fileIdNumberPadded}";
                            $path = $uploadedFile->storeAs("public/{$folder}", $storedFileName);
                            $filePath = "storage/{$folder}/{$storedFileName}";
                            Attachment::create([
                                'person_identity_number' => $personIdentityNumber,
                                'stored_file_name' => $storedFileName,
                                'file_path' => $filePath,
                                'file_type' => $fileType,
                                'file_size' => $uploadedFile->getSize(),
                            ]);
                            Log::info("Created new attachment for person {$personIdentityNumber}", [
                                'file_type' => $fileType,
                                'file_path' => $filePath
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            Log::info('--- Update success ---');
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث السجل والمرفقات بنجاح'
                ]);
            }
            return redirect()->route('admin.records.management.edit', $data->id)
                             ->with('success', 'تم تحديث السجل بنجاح');
        } catch (\Exception $e) {
            Log::error('Exception in update:', ['message'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
            DB::rollBack();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث السجل: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء تحديث السجل: ' . $e->getMessage());
        }
    }
    /**
     * حذف فرد الأسرة عبر AJAX
     */
    public function deleteFamilyMember(Request $request, $id)
    {
        try {
            Log::info("🗑️ Starting family member deletion for ID: {$id}");

            // حاول إيجاد العضو
            $member = RePeople::find($id);
            if (!$member) {
                Log::warning("⚠️ Family member not found", ['id' => $id]);
                // إذا لم يُعثر على العضو
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'فرد الأسرة غير موجود'
                    ], 404);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'فرد الأسرة غير موجود');
            }

            Log::info("👤 Family member found", [
                'person_id' => $member->person_id,
                'registration_id' => $member->registration_id
            ]);

            // البحث عن المرفقات المرتبطة بهذا الفرد وحذفها
            if ($member->person_id && preg_match('/^\d+$/', $member->person_id)) {
                $attachments = Attachment::where('person_identity_number', $member->person_id)->get();
                Log::info("📎 Found attachments for family member", ['count' => $attachments->count()]);

                $deletedFiles = 0;
                $failedFiles = 0;

                foreach ($attachments as $attachment) {
                    try {
                        // محاولة حذف الملف الفيزيائي
                        $possiblePaths = [
                            str_replace('storage/', '', $attachment->file_path ?? ''),
                            'uploads/' . $attachment->person_identity_number . '/' . $attachment->stored_file_name,
                            ltrim($attachment->file_path ?? '', '/'),
                        ];

                        $fileDeleted = false;
                        foreach ($possiblePaths as $path) {
                            if ($path && Storage::disk('public')->exists($path)) {
                                Storage::disk('public')->delete($path);
                                Log::info("✅ Deleted attachment file", [
                                    'path' => $path,
                                    'attachment_id' => $attachment->id
                                ]);
                                $deletedFiles++;
                                $fileDeleted = true;
                                break;
                            }
                        }

                        if (!$fileDeleted) {
                            Log::warning("⚠️ Attachment file not found", [
                                'attachment_id' => $attachment->id,
                                'tried_paths' => $possiblePaths
                            ]);
                            $failedFiles++;
                        }

                        // حذف المرفق من قاعدة البيانات
                        $attachment->delete();

                    } catch (\Exception $e) {
                        Log::error("❌ Error deleting attachment: " . $e->getMessage());
                        $failedFiles++;
                    }
                }

                // محاولة حذف مجلد الفرد إذا كان فارغاً
                $memberFolderPath = 'uploads/' . $member->person_id;
                if (Storage::disk('public')->exists($memberFolderPath)) {
                    try {
                        $files = Storage::disk('public')->files($memberFolderPath);
                        if (empty($files)) {
                            Storage::disk('public')->deleteDirectory($memberFolderPath);
                            Log::info("📁 Deleted empty member folder", ['folder' => $memberFolderPath]);
                        }
                    } catch (\Exception $e) {
                        Log::warning("⚠️ Could not delete member folder: " . $e->getMessage());
                    }
                }

                Log::info("📊 Family member attachments deletion summary", [
                    'deleted_files' => $deletedFiles,
                    'failed_files' => $failedFiles,
                    'total_attachments' => $attachments->count()
                ]);
            }

            // حذف العضو من قاعدة البيانات
            $member->delete();
            Log::info("✅ Family member deleted successfully");

            // الرد بنجاح
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->back()
                ->with('success', 'تم حذف فرد الأسرة وجميع مرفقاته بنجاح');

        } catch (\Exception $e) {
            Log::error("❌ Error deleting family member: " . $e->getMessage());
            Log::error("📍 Error trace: " . $e->getTraceAsString());

            // في حال حدوث استثناء أثناء الحذف
            $message = 'حدث خطأ أثناء حذف فرد الأسرة: ' . $e->getMessage();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 500);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', $message);
        }
    }
    /**
     * حذف مرفق عبر AJAX
     */
    public function deleteAttachment(Request $request, $id)
    {
        try {
            $attachment = \App\Models\Attachment::find($id);
            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'المرفق غير موجود'
                ], 404);
            }

            // حذف الملف من التخزين إذا كان موجوداً
            // تأكد أن المسار يبدأ بـ uploads أو attachments حسب تخزينك
            $storagePath = str_replace('storage/', '', $attachment->file_path);
            if ($storagePath && Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
            }

            $attachment->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المرفق: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            Log::info("🗑️ Starting deletion process for record ID: {$id}");

            $record = \App\Models\Data::findOrFail($id);
            Log::info("📋 Record found", ['file_id' => $record->file_id_number, 'name' => $record->data_first_name]);

            // جمع جميع أرقام الهوية المرتبطة بالسجل
            $allIdentityNumbers = [];

            // إضافة رقم الهوية الرئيسي ورقم الملف
            if (preg_match('/^\d+$/', $record->data_id_number)) {
                $allIdentityNumbers[] = (string) $record->data_id_number;
            }
            if (preg_match('/^\d+$/', $record->file_id_number)) {
                $allIdentityNumbers[] = (string) $record->file_id_number;
            }

            // جلب جميع أفراد الأسرة المرتبطين
            $familyMembers = \App\Models\RePeople::where('registration_id', $record->file_id_number)->get();
            $familyPeopleIds = $familyMembers->pluck('person_id')
                ->filter(function($id) {
                    return preg_match('/^\d+$/', $id);
                })
                ->map(function($id) { return (string) $id; })
                ->values()
                ->all();

            $allIdentityNumbers = array_merge($allIdentityNumbers, $familyPeopleIds);
            Log::info("👨‍👩‍👧‍👦 Family members IDs", $familyPeopleIds);

            // جلب بيانات المتوفين المرتبطة
            $dead = \App\Models\DeadPepole::where('re_file_id', $record->file_id_number)->first();
            $deadIds = [];
            if ($dead) {
                if (preg_match('/^\d+$/', $dead->father_id)) {
                    $deadIds[] = (string) $dead->father_id;
                }
                if (preg_match('/^\d+$/', $dead->mother_id)) {
                    $deadIds[] = (string) $dead->mother_id;
                }
                $allIdentityNumbers = array_merge($allIdentityNumbers, $deadIds);
            }
            Log::info("⚰️ Deceased IDs", $deadIds);

            // جلب جميع المرفقات المرتبطة بهذه الأرقام قبل الحذف
            $attachments = [];
            if (!empty($allIdentityNumbers)) {
                $attachments = \App\Models\Attachment::whereIn('person_identity_number', $allIdentityNumbers)->get();
                Log::info("📎 Found attachments", ['count' => $attachments->count()]);
            }

            // إضافة: البحث عن المرفقات بناءً على file_id_number في file_path أيضاً
            $additionalAttachments = \App\Models\Attachment::where('file_path', 'LIKE', "%{$record->file_id_number}%")
                ->orWhere('stored_file_name', 'LIKE', "%{$record->file_id_number}%")
                ->get();

            if ($additionalAttachments->count() > 0) {
                Log::info("📎 Found additional attachments by file_id", ['count' => $additionalAttachments->count()]);
                $attachments = $attachments->merge($additionalAttachments)->unique('id');
                Log::info("📎 Total unique attachments", ['count' => $attachments->count()]);
            }

            // حذف الملفات الفيزيائية من التخزين أولاً
            $deletedFiles = 0;
            $failedFiles = 0;
            foreach ($attachments as $attachment) {
                try {
                    // بناء مسار الملف بطرق مختلفة للتأكد من العثور عليه
                    $possiblePaths = [
                        // المسار الصحيح الأكثر احتمالاً بناءً على file_id_number
                        'uploads/' . $record->file_id_number . '/' . $attachment->stored_file_name,
                        // المسار المباشر من file_path
                        str_replace('storage/', '', $attachment->file_path ?? ''),
                        // مسار بناءً على person_identity_number و stored_file_name (احتياطي)
                        'uploads/' . $attachment->person_identity_number . '/' . $attachment->stored_file_name,
                        // مسار بناءً على file_path الكامل
                        ltrim($attachment->file_path ?? '', '/'),
                    ];

                    $fileDeleted = false;
                    foreach ($possiblePaths as $path) {
                        if ($path && Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                            Log::info("✅ Deleted file", ['path' => $path, 'attachment_id' => $attachment->id]);
                            $deletedFiles++;
                            $fileDeleted = true;
                            break;
                        }
                    }

                    if (!$fileDeleted) {
                        // محاولة حذف مجلد كامل بناءً على file_id_number (الطريقة الصحيحة)
                        $folderPath = 'uploads/' . $record->file_id_number;
                        if (Storage::disk('public')->exists($folderPath)) {
                            Storage::disk('public')->deleteDirectory($folderPath);
                            Log::info("📁 Deleted entire folder using file_id", ['folder' => $folderPath]);
                            $deletedFiles++;
                        } else {
                            // محاولة أخيرة بناءً على person_identity_number كخطة احتياطية
                            $fallbackFolderPath = 'uploads/' . $attachment->person_identity_number;
                            if (Storage::disk('public')->exists($fallbackFolderPath)) {
                                Storage::disk('public')->deleteDirectory($fallbackFolderPath);
                                Log::info("📁 Deleted entire folder using person_identity_number", ['folder' => $fallbackFolderPath]);
                                $deletedFiles++;
                            } else {
                                Log::warning("⚠️ File not found in any expected path", [
                                    'attachment_id' => $attachment->id,
                                    'file_path' => $attachment->file_path,
                                    'stored_file_name' => $attachment->stored_file_name,
                                    'person_identity_number' => $attachment->person_identity_number,
                                    'file_id_number' => $record->file_id_number,
                                    'tried_paths' => $possiblePaths,
                                    'tried_folders' => [$folderPath, $fallbackFolderPath]
                                ]);
                                $failedFiles++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error deleting file for attachment ID {$attachment->id}: " . $e->getMessage());
                    $failedFiles++;
                }
            }

            Log::info("📊 File deletion summary", [
                'deleted_files' => $deletedFiles,
                'failed_files' => $failedFiles,
                'total_attachments' => $attachments->count()
            ]);

            // محاولة أخيرة لحذف مجلد السجل الرئيسي إذا كان موجوداً وفارغاً
            $mainFolderPath = 'uploads/' . $record->file_id_number;
            if (Storage::disk('public')->exists($mainFolderPath)) {
                try {
                    $remainingFiles = Storage::disk('public')->allFiles($mainFolderPath);
                    if (empty($remainingFiles)) {
                        Storage::disk('public')->deleteDirectory($mainFolderPath);
                        Log::info("📁 Final cleanup: Deleted main record folder", ['folder' => $mainFolderPath]);
                    } else {
                        // إذا كانت هناك ملفات متبقية، احذفها بالقوة
                        Log::warning("📁 Forcing deletion of remaining files", [
                            'folder' => $mainFolderPath,
                            'remaining_files' => $remainingFiles
                        ]);

                        foreach ($remainingFiles as $file) {
                            try {
                                Storage::disk('public')->delete($file);
                                Log::info("🗑️ Force deleted file", ['file' => $file]);
                                $deletedFiles++;
                            } catch (\Exception $e) {
                                Log::error("❌ Could not force delete file: {$file} - " . $e->getMessage());
                                $failedFiles++;
                            }
                        }

                        // محاولة حذف المجلد مرة أخيرة
                        if (Storage::disk('public')->exists($mainFolderPath)) {
                            Storage::disk('public')->deleteDirectory($mainFolderPath);
                            Log::info("📁 Force deleted main record folder after cleanup", ['folder' => $mainFolderPath]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("⚠️ Could not perform final folder cleanup: " . $e->getMessage());
                }
            } else {
                Log::info("📁 Main record folder already deleted or not found", ['folder' => $mainFolderPath]);
            }

            // حذف المرفقات من قاعدة البيانات (بما في ذلك الإضافية التي وُجدت)
            if ($attachments->count() > 0) {
                $attachmentIds = $attachments->pluck('id')->toArray();
                $deletedAttachmentsCount = \App\Models\Attachment::whereIn('id', $attachmentIds)->delete();
                Log::info("🗄️ Deleted all related attachments from database", ['count' => $deletedAttachmentsCount]);
            }

            // الآن حذف البيانات من قاعدة البيانات
            DB::beginTransaction();

            // حذف أفراد الأسرة
            $deletedFamilyCount = \App\Models\RePeople::where('registration_id', $record->file_id_number)->delete();
            Log::info("👥 Deleted family members", ['count' => $deletedFamilyCount]);

            // حذف بيانات المتوفين
            $deletedDeadCount = \App\Models\DeadPepole::where('re_file_id', $record->file_id_number)->delete();
            Log::info("⚰️ Deleted deceased records", ['count' => $deletedDeadCount]);

            // حذف السجل الرئيسي
            $record->delete();
            Log::info("📋 Deleted main record");

            DB::commit();
            Log::info("✅ Deletion process completed successfully");

            return redirect()->route('admin.records.management')->with('success', "تم حذف السجل وجميع البيانات المرتبطة به بنجاح. تم حذف {$deletedFiles} ملف من التخزين.");

        } catch (\Exception $e) {
            if (isset($record)) {
                DB::rollBack();
            }
            Log::error("❌ Error during deletion process: " . $e->getMessage());
            Log::error("📍 Error trace: " . $e->getTraceAsString());

            return redirect()->route('admin.records.management')->with('error', 'حدث خطأ أثناء حذف السجل: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: جلب سجلات موظف مع pagination
     */
    public function ajaxAdminRecords($adminId)
    {
        $admin = \App\Models\User::findOrFail($adminId);
        $perPage = 15;
        $page = request('page', 1);
        $records = \App\Models\Data::where('data_user_insert_data', $admin->name)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // بناء جدول HTML
        if ($records->count()) {
            $html = view('admin.dashboard.component._admin_records_table', [
                'records' => $records,
                'admin' => $admin
            ])->render();
            return response()->json(['success' => true, 'html' => $html]);
        } else {
            return response()->json(['success' => false, 'message' => 'لا توجد سجلات مدخلة لهذا الموظف.']);
        }
    }
}

