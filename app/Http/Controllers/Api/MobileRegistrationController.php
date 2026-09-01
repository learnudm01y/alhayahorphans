<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdditionalDeceased;
use App\Models\Attachment;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\GuardianBankAccount;
use App\Models\RePeople;
use App\Services\RcloneGoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * MobileRegistrationController
 *
 * Mobile endpoints that mirror the web "general registration" (store)
 * and the admin "records management" (update) flows, so a file entered
 * from the Android app arrives with data_request_status = 1
 * (بانتظار المراجعة) and is reviewed by the admin like any web entry.
 */
class MobileRegistrationController extends Controller
{
    /**
     * POST /api/mobile/registration/new-file-id
     *
     * Reserve a unique 6-digit file number before syncing an offline draft,
     * so attachment file names ({file_type}_{file_id}_{person_id}.ext)
     * can be built consistently by the app.
     */
    public function newFileId(Request $request): JsonResponse
    {
        try {
            $fileId = generateUniqueReservedCode('data', 'file_id_number');
            if (!$fileId) {
                $fileId = generateFileIdFromDataTable();
            }

            DB::table('reserved_codes')
                ->where('code', $fileId)
                ->update(['notes' => 'mobile registration draft', 'updated_at' => now()]);

            return response()->json([
                'success' => true,
                'file_id_number' => str_pad($fileId, 6, '0', STR_PAD_LEFT),
                'message' => 'تم توليد رقم الملف بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('MobileRegistration newFileId failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل توليد رقم الملف: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/mobile/registration/upload-chunk
     *
     * Chunked upload mirroring the web GeneralRegistrationController@uploadChunk.
     * Chunks are appended into storage/app/public/temp_uploads/{file_id}/{name}.
     * When the last chunk arrives the endpoint returns the temp path that the
     * store/update endpoints will move into uploads/{file_id}.
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        try {
            $chunk = $request->file('chunk');
            $fileName = $request->input('file_name');
            $fileId = $request->input('file_id_number');
            $chunkIndex = $request->input('chunk_index');
            $totalChunks = $request->input('total_chunks');

            $chunkIndex = $chunkIndex !== null ? (int) $chunkIndex : null;
            $totalChunks = $totalChunks !== null ? (int) $totalChunks : null;

            if (!$chunk || !$fileName || !$fileId || $chunkIndex === null || $totalChunks === null || $totalChunks <= 0) {
                return response()->json(['success' => false, 'error' => 'بيانات مفقودة للرفع المجزأ'], 400);
            }

            $tempDir = storage_path('app/public/temp_uploads/' . $fileId);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $safeFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
            $tempPath = $tempDir . '/' . $safeFileName;

            file_put_contents($tempPath, file_get_contents($chunk->getRealPath()), FILE_APPEND);

            if ($chunkIndex === $totalChunks - 1) {
                return response()->json([
                    'success' => true,
                    'complete' => true,
                    'path' => 'temp_uploads/' . $fileId . '/' . $safeFileName
                ]);
            }

            return response()->json(['success' => true, 'complete' => false]);
        } catch (\Exception $e) {
            Log::error('MobileRegistration uploadChunk failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/registration/lookups
     *
     * Sections (القسم), provinces (المحافظة), صلة القرابة (relations),
     * cities and death reasons used by the mobile registration form.
     */
    public function lookups(): JsonResponse
    {
        try {
            $safeSelect = function (string $table, array $cols, string $orderBy, array $where = []) {
                if (!Schema::hasTable($table)) {
                    return collect();
                }
                $cols = array_values(array_filter($cols, fn ($col) => $col === '*' || Schema::hasColumn($table, $col)));
                if (empty($cols)) {
                    return collect();
                }
                $query = DB::table($table)->select($cols);
                foreach ($where as $col => $value) {
                    if (is_array($value) && count($value) === 2) {
                        $query->where($col, $value[0], $value[1]);
                    } else {
                        $query->where($col, $value);
                    }
                }
                try {
                    return $query->orderBy($orderBy)->get();
                } catch (\Exception $e) {
                    Log::warning('lookup query failed', ['table' => $table, 'error' => $e->getMessage()]);
                    return collect();
                }
            };

            $sections = $safeSelect('general_category', ['id', 'description'], 'id');
            $provinces = $safeSelect('provinces', ['id', 'description'], 'id');
            $relations = $safeSelect('category_of_relations', ['id', 'attribute'], 'id', ['attribute' => ['<>', 'Unknown']]);
            $cities = $safeSelect('city', ['id', 'city', 'province_id'], 'city');
            $deathReasons = $safeSelect('death_reasons', ['id', 'description'], 'id');

            return response()->json([
                'success' => true,
                'sections' => $sections,
                'provinces' => $provinces,
                'relations' => $relations,
                'cities' => $cities,
                'death_reasons' => $deathReasons,
            ]);
        } catch (\Exception $e) {
            Log::error('MobileRegistration lookups failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل تحميل القوائم'], 500);
        }
    }

    /**
     * GET /api/mobile/registration/photo/{personId}
     *
     * Serve the personal photo (file_type = 12) of a person if available,
     * otherwise 404 JSON so the app can hide the "عرض الصورة" button.
     */
    public function photo(string $personId): \Symfony\Component\HttpFoundation\Response
    {
        if (!preg_match('/^\d+$/', $personId)) {
            return response()->json(['success' => false, 'message' => 'رقم هوية غير صالح'], 400);
        }

        $attachment = Attachment::where('person_identity_number', $personId)
            ->where('file_type', 12)
            ->orderByDesc('id')
            ->first();

        if (!$attachment) {
            return response()->json(['success' => false, 'message' => 'لا توجد صورة شخصية'], 404);
        }

        $resolved = $this->resolveAttachmentContent($attachment);
        if (!$resolved) {
            return response()->json(['success' => false, 'message' => 'الملف غير موجود'], 404);
        }

        return response($resolved['content'], 200, [
            'Content-Type' => $resolved['mime'],
            'Content-Disposition' => 'inline; filename="' . ($attachment->stored_file_name ?? 'photo.jpg') . '"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * GET /api/mobile/registration/photo/{personId}/exists
     *
     * فحص سريع لوجود الصورة قبل تنزيلها كاملاً (بدون سحب محتوى الملف).
     */
    public function photoExists(string $personId): JsonResponse
    {
        if (!preg_match('/^\d+$/', $personId)) {
            return response()->json(['success' => false, 'exists' => false], 400);
        }

        $attachment = Attachment::where('person_identity_number', $personId)
            ->where('file_type', 12)
            ->orderByDesc('id')
            ->first();

        if (!$attachment) {
            return response()->json(['success' => false, 'exists' => false], 404);
        }

        try {
            $filePath = (string) ($attachment->file_path ?? '');

            // مسار Rclone → فحص بدون تنزيل المحتوى
            if (preg_match('/^[a-zA-Z0-9_-]+:/', $filePath) === 1) {
                $exists = (new RcloneGoogleDriveService())->fileExists($filePath);
                return response()->json(['success' => true, 'exists' => $exists]);
            }

            // رابط مباشر → فحص HEAD سريع
            if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
                $ch = curl_init($filePath);
                curl_setopt_array($ch, [
                    CURLOPT_NOBODY => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                return response()->json(['success' => true, 'exists' => $status >= 200 && $status < 400]);
            }

            // مسار محلي
            $storagePath = str_replace('storage/', '', $filePath);
            $exists = $storagePath !== '' && Storage::disk('public')->exists($storagePath);
            if (!$exists && $filePath !== '') {
                $exists = file_exists(public_path($filePath));
            }
            return response()->json(['success' => true, 'exists' => $exists]);
        } catch (\Exception $e) {
            Log::warning('photoExists check failed', ['id' => $attachment->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => true, 'exists' => false]);
        }
    }

    /**
     * تحويل محتوى مرفق (صورة شخصية) إلى بايتات وميما مهما كان مصدره:
     * مسار Rclone (remote:...)، رابط مباشر، أو مسار محلي.
     */
    private function resolveAttachmentContent(Attachment $attachment): ?array
    {
        try {
            $filePath = (string) ($attachment->file_path ?? '');
            if ($filePath === '') {
                return null;
            }

            // مسارات Rclone تبدأ بـ remoteName:
            if (preg_match('/^[a-zA-Z0-9_-]+:/', $filePath) === 1) {
                $result = (new RcloneGoogleDriveService())->getFileContent($filePath);
                if (!empty($result['success']) && $result['content'] !== null) {
                    return [
                        'content' => $result['content'],
                        'mime' => ($result['mime_type'] ?? '') ?: 'image/jpeg',
                    ];
                }
                Log::warning('photo rclone failed', ['file_path' => $filePath, 'msg' => $result['message'] ?? '']);
                return null;
            }

            // روابط مباشرة
            if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
                $ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
                $content = @file_get_contents($filePath, false, $ctx);
                if ($content !== false) {
                    return ['content' => $content, 'mime' => 'image/jpeg'];
                }
                return null;
            }

            // مسار محلي (storage/... أو uploads/...)
            $storagePath = str_replace('storage/', '', $filePath);
            if ($storagePath !== '' && Storage::disk('public')->exists($storagePath)) {
                return [
                    'content' => Storage::disk('public')->get($storagePath),
                    'mime' => Storage::disk('public')->mimeType($storagePath) ?: 'image/jpeg',
                ];
            }

            $absolute = public_path($filePath);
            if (file_exists($absolute)) {
                return [
                    'content' => file_get_contents($absolute),
                    'mime' => mime_content_type($absolute) ?: 'image/jpeg',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('photo resolve failed', ['id' => $attachment->id, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * GET /api/mobile/registration/file/{fileId}
     *
     * Full serialization of one file for the mobile "full file" editor:
     * guardian (data), bank accounts, family members, deceased,
     * additional deceased and all attachments.
     */
    public function getFile(string $fileId): JsonResponse
    {
        $fileIdNumber = str_pad($fileId, 6, '0', STR_PAD_LEFT);
        $data = Data::where('file_id_number', $fileIdNumber)->first();

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'رقم الملف غير موجود'], 404);
        }

        return response()->json([
            'success' => true,
            'file_id_number' => $fileIdNumber,
            'data' => $data,
            'bank_accounts' => GuardianBankAccount::where('guardian_registration', $fileIdNumber)->get(),
            'family_members' => RePeople::where('registration_id', $fileIdNumber)->get(),
            'deceased' => DeadPepole::where('re_file_id', $fileIdNumber)->first(),
            'additional_deceased' => AdditionalDeceased::where('re_file_id', $fileIdNumber)->get(),
            'attachments' => $this->attachmentsForFile($fileIdNumber, $data->data_id_number),
        ]);
    }

    /**
     * POST /api/mobile/registration/store
     *
     * Mirror of GeneralRegistrationController@store for the mobile app.
     * Always writes data_request_status = 1 (بانتظار المراجعة).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_id_number' => 'nullable|string',
                'data_section_id' => 'required|integer',
                'data_id_number' => 'required|string|digits:9',
                'data_first_name' => 'required|string|max:255',
                'data_father_name' => 'nullable|string|max:255',
                'data_grand_father_name' => 'nullable|string|max:255',
                'data_family_name' => 'nullable|string|max:255',
                'data_relationship' => 'nullable|string|max:100',
                'data_birth_date' => 'nullable|date',
                'data_gender' => 'nullable|in:1,2',
                'data_phone_number' => 'nullable|string|max:20',
                'data_alt_phone_number' => 'nullable|string|max:20',
                'data_number_of_individuals' => 'nullable|integer',
                'data_marital_status' => 'nullable|integer',
                'data_academic_qualification' => 'nullable|integer',
                'data_displacement_status' => 'nullable|integer',
                'data_address_before_displacement' => 'nullable|string',
                'data_current_address' => 'nullable|string|max:500',
                'data_city' => 'nullable|integer',
                'data_province' => 'required|integer|exists:provinces,id',
                'data_health_status' => 'nullable|integer',
                'data_description_needs' => 'nullable|string',
                'data_number_mail' => 'nullable|integer',
                'data_number_female' => 'nullable|integer',
                'data_number_of_individuals_with_chronic_diseases' => 'nullable|integer',
                'data_number_of_people_with_special_needs' => 'nullable|integer',
                'data_employment_status_breadwinner' => 'nullable|integer',
                'data_housing_status' => 'nullable|integer',
                'data_current_housing_type' => 'nullable|string',
                'family_members' => 'sometimes|array',
                'bank_accounts' => 'sometimes|array',
                'additional_deceased' => 'sometimes|array',
                'attachments' => 'sometimes|array',
            ]);

            DB::beginTransaction();

            $fileIdNumber = $request->input('file_id_number');
            if (!empty($fileIdNumber) && preg_match('/^\d+$/', $fileIdNumber)) {
                $fileIdNumber = str_pad($fileIdNumber, 6, '0', STR_PAD_LEFT);
            }

            // رقم ملف جديد عندما لا يقدم التطبيق واحداً
            if (!$fileIdNumber || $fileIdNumber === 'undefined') {
                $fileIdNumber = generateUniqueReservedCode('data', 'file_id_number');
                if (!$fileIdNumber) {
                    $fileIdNumber = generateFileIdFromDataTable();
                }
                $fileIdNumber = str_pad($fileIdNumber, 6, '0', STR_PAD_LEFT);
            }

            if (Data::where('file_id_number', $fileIdNumber)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'رقم الملف العام مستخدم مسبقاً. يرجى استخدام رقم جديد.'
                ], 422);
            }

            if (Data::where('data_id_number', $request->input('data_id_number'))->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'رقم الهوية مستخدم مسبقاً لمعيل آخر. يرجى تسجيل الدخول.'
                ], 422);
            }

            $userId = optional($request->user())->id;

            $data = Data::create([
                'file_id_number' => $fileIdNumber,
                'data_section_id' => $request->input('data_section_id'),
                'data_id_number' => $request->input('data_id_number'),
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
                'data_user_insert_data' => $this->userMarker($userId),
                'data_request_status' => 1,
            ]);

            markCodeAsUsed($fileIdNumber, $userId, 'mobile registration store');

            $this->storeBankAccounts($fileIdNumber, $request->input('bank_accounts', []), $request->input('data_id_number'));

            if ((int) $request->input('data_section_id') === 1) {
                $this->storeDeceased($fileIdNumber, $request);
                $this->storeAdditionalDeceased($fileIdNumber, $request->input('additional_deceased', []));
            }

            $this->replaceFamilyMembers($fileIdNumber, $request->input('family_members', []), $fileIdNumber);

            $this->storeAttachments($request, $fileIdNumber);

            DB::commit();

            Log::info('Mobile registration stored', [
                'file_id_number' => $fileIdNumber,
                'user_id' => $userId,
                'identity' => $request->input('data_id_number'),
            ]);

            return response()->json([
                'success' => true,
                'file_id_number' => $fileIdNumber,
                'message' => 'تم إرسال التسجيل بنجاح وسيتم مراجعته'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'بيانات غير صحيحة'
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mobile registration store failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'error' => 'حدث خطأ أثناء حفظ السجل: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/mobile/registration/update
     *
     * Mirror of RecordsManagementEditController@update for the mobile app.
     * data_request_status is always set to 1 (بانتظار المراجعة) so the
     * admin re-reviews edited data. It accepts the same flat attachments
     * format as store plus attachments_to_delete (existing row ids).
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_id_number' => 'required|string',
                'data_section_id' => 'required|integer',
                'data_id_number' => 'required|string',
                'data_first_name' => 'required|string|max:255',
                'data_father_name' => 'nullable|string|max:255',
                'data_grand_father_name' => 'nullable|string|max:255',
                'data_family_name' => 'nullable|string|max:255',
                'data_relationship' => 'nullable|string|max:255',
                'data_birth_date' => 'nullable|date',
                'data_gender' => 'nullable|in:1,2',
                'data_phone_number' => 'nullable|string|max:20',
                'data_alt_phone_number' => 'nullable|string|max:20',
                'data_number_of_individuals' => 'nullable|integer',
                'data_marital_status' => 'nullable|integer',
                'data_academic_qualification' => 'nullable|integer',
                'data_displacement_status' => 'nullable|integer',
                'data_address_before_displacement' => 'nullable|string',
                'data_current_address' => 'nullable|string|max:500',
                'data_city' => 'nullable|integer',
                'data_province' => 'nullable|integer',
                'data_health_status' => 'nullable|integer',
                'data_description_needs' => 'nullable|string',
                'data_number_mail' => 'nullable|integer',
                'data_number_female' => 'nullable|integer',
                'data_number_of_individuals_with_chronic_diseases' => 'nullable|integer',
                'data_number_of_people_with_special_needs' => 'nullable|integer',
                'data_employment_status_breadwinner' => 'nullable|integer',
                'data_housing_status' => 'nullable|integer',
                'data_current_housing_type' => 'nullable|string',
                'attachments_to_delete' => 'sometimes|array',
                'attachments_to_delete.*' => 'integer',
                'family_members' => 'sometimes|array',
                'bank_accounts' => 'sometimes|array',
                'additional_deceased' => 'sometimes|array',
                'attachments' => 'sometimes|array',
            ]);

            DB::beginTransaction();

            $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);

            $data = Data::where('file_id_number', $fileIdNumber)->first();
            if (!$data) {
                DB::rollBack();
                return response()->json(['success' => false, 'error' => 'رقم الملف غير موجود'], 404);
            }

            // 1) حذف المرفقات المطلوبة
            foreach ($request->input('attachments_to_delete', []) as $attId) {
                $attachment = Attachment::find($attId);
                if ($attachment) {
                    $storagePath = str_replace('storage/', '', $attachment->file_path ?? '');
                    if ($storagePath && Storage::disk('public')->exists($storagePath)) {
                        Storage::disk('public')->delete($storagePath);
                    }
                    $attachment->delete();
                }
            }

            $oldDataIdNumber = $data->getOriginal('data_id_number');
            $newDataIdNumber = $request->input('data_id_number');

            // 2) تحديث البيانات الأساسية (الحالة دائماً 1 بانتظار المراجعة)
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
                'data_user_insert_data' => $this->userMarker(optional($request->user())->id),
                'data_request_status' => 1,
            ]);

            // 3) إعادة تسمية مرفقات المعيل عند تغيير رقم الهوية
            if ($oldDataIdNumber && $oldDataIdNumber !== $newDataIdNumber) {
                $this->renamePersonAttachments($oldDataIdNumber, $fileIdNumber, $newDataIdNumber);
            }

            // 4) أفراد الأسرة: حذف ثم إعادة إدراج
            $oldFamily = RePeople::where('registration_id', $fileIdNumber)->get()->keyBy('id');
            RePeople::where('registration_id', $fileIdNumber)->delete();

            foreach ($request->input('family_members', []) as $member) {
                $oldMember = isset($member['id']) ? $oldFamily->get($member['id']) : null;
                $oldPersonId = $oldMember ? $oldMember->person_id : null;
                $newPersonId = $member['person_id'] ?? null;

                RePeople::create([
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
                    'person_note' => $member['person_note'] ?? null,
                ]);

                if ($oldPersonId && $newPersonId && $oldPersonId != $newPersonId) {
                    $this->renamePersonAttachments($oldPersonId, $fileIdNumber, $newPersonId);
                }
            }

            // 5) المتوفون (أب/أم) والمتوفون الإضافيون
            if ((int) $request->input('data_section_id') === 1) {
                $this->storeDeceased($fileIdNumber, $request, true);
            }
            $this->replaceAdditionalDeceased($fileIdNumber, $request->input('additional_deceased', []));

            // 6) المرفقات الجديدة
            $this->storeAttachments($request, $fileIdNumber);

            // 7) الحسابات البنكية (تحديث أو إنشاء)
            $this->upsertBankAccounts($fileIdNumber, $request->input('bank_accounts', []), $newDataIdNumber);

            DB::commit();

            return response()->json([
                'success' => true,
                'file_id_number' => $fileIdNumber,
                'message' => 'تم تحديث السجل بنجاح وسيتم مراجعته'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'بيانات غير صحيحة'
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mobile registration update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_id_number' => $request->input('file_id_number'),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'حدث خطأ أثناء تحديث السجل: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== Private helpers ====================

    private function userMarker(?int $userId): string
    {
        return $userId ? 'APP-' . $userId : 'APP-user';
    }

    private function storeBankAccounts(string $fileIdNumber, array $bankAccounts, string $guardianIdentity): void
    {
        if (empty($bankAccounts) || !is_array($bankAccounts)) {
            return;
        }

        foreach ($bankAccounts as $account) {
            $reIdNumber = $account['person_owner_identity_number'] ?? null;
            $hasData = !empty($account['bank_name']) || !empty($account['iban_usd']) ||
                       !empty($account['iban_shekel']) || !empty($account['re_guardian_name']) ||
                       !empty($account['re_phone_number']) || !empty($reIdNumber);

            if (!$hasData) {
                continue;
            }

            $existingCount = GuardianBankAccount::where('guardian_registration', $fileIdNumber)->count();
            GuardianBankAccount::create([
                'guardian_registration' => $fileIdNumber,
                'bank_name' => $account['bank_name'] ?? null,
                'iban_usd' => $account['iban_usd'] ?? null,
                'iban_shekel' => $account['iban_shekel'] ?? null,
                'person_owner_identity_number' => $reIdNumber,
                're_id_number' => $reIdNumber ?: $guardianIdentity,
                're_guardian_name' => $account['re_guardian_name'] ?? null,
                're_phone_number' => $account['re_phone_number'] ?? null,
                'check_account' => $existingCount === 0 ? 1 : 0,
            ]);
        }
    }

    private function storeDeceased(string $fileIdNumber, Request $request, bool $update = false): void
    {
        $fatherFilled = $request->filled('father_first_name') || $request->filled('father_last_name') || $request->filled('father_id');
        $motherFilled = $request->filled('mother_first_name') || $request->filled('mother_last_name') || $request->filled('mother_id');

        if (!$fatherFilled && !$motherFilled) {
            return;
        }

        $deadData = [
            're_file_id' => $fileIdNumber,
            'father_first_name' => $request->input('father_first_name'),
            'father_second_name' => $request->input('father_second_name'),
            'father_third_name' => $request->input('father_third_name'),
            'father_last_name' => $request->input('father_last_name'),
            'father_id' => $request->input('father_id'),
            'father_death_date' => $request->input('father_death_date'),
            'father_death_reason' => $request->input('father_death_reason'),
            'mother_first_name' => $request->input('mother_first_name'),
            'mother_second_name' => $request->input('mother_second_name'),
            'mother_third_name' => $request->input('mother_third_name'),
            'mother_last_name' => $request->input('mother_last_name'),
            'mother_id' => $request->input('mother_id'),
            'mother_death_date' => $request->input('mother_death_date'),
            'mother_death_reason' => $request->input('mother_death_reason'),
        ];

        if ($update) {
            $dead = DeadPepole::where('re_file_id', $fileIdNumber)->first();
            if ($dead) {
                $oldFatherId = $dead->father_id;
                $oldMotherId = $dead->mother_id;
                $dead->update($deadData);

                if ($oldFatherId && $request->filled('father_id') && $oldFatherId !== $request->input('father_id')) {
                    $this->renamePersonAttachments($oldFatherId, $fileIdNumber, $request->input('father_id'));
                }
                if ($oldMotherId && $request->filled('mother_id') && $oldMotherId !== $request->input('mother_id')) {
                    $this->renamePersonAttachments($oldMotherId, $fileIdNumber, $request->input('mother_id'));
                }
            } else {
                DeadPepole::create($deadData);
            }
            return;
        }

        DeadPepole::create($deadData);
    }

    private function storeAdditionalDeceased(string $fileIdNumber, array $additionalDeceased): void
    {
        if (!is_array($additionalDeceased) || count($additionalDeceased) === 0) {
            return;
        }

        foreach ($additionalDeceased as $deceased) {
            $personId = $deceased['id_number'] ?? $deceased['person_id'] ?? null;
            if (empty($deceased['first_name']) && empty($deceased['last_name']) && empty($personId)) {
                continue;
            }

            AdditionalDeceased::create([
                're_file_id' => $fileIdNumber,
                'person_id' => $personId,
                'first_name' => $deceased['first_name'] ?? null,
                'second_name' => $deceased['second_name'] ?? null,
                'third_name' => $deceased['third_name'] ?? null,
                'last_name' => $deceased['last_name'] ?? null,
                'relationship' => $deceased['relationship'] ?? 'other',
                'death_date' => $deceased['death_date'] ?? null,
                'death_reason' => $deceased['death_reason'] ?? null,
            ]);
        }
    }

    private function replaceAdditionalDeceased(string $fileIdNumber, array $additionalDeceased): void
    {
        AdditionalDeceased::where('re_file_id', $fileIdNumber)->delete();
        $this->storeAdditionalDeceased($fileIdNumber, $additionalDeceased);
    }

    private function replaceFamilyMembers(string $fileIdNumber, array $familyMembers, string $defaultFileId): void
    {
        if (!is_array($familyMembers) || count($familyMembers) === 0) {
            return;
        }

        foreach ($familyMembers as $member) {
            RePeople::create([
                'registration_id' => $fileIdNumber,
                'sponsorship_status' => $member['sponsorship_status'] ?? null,
                'first_name' => $member['first_name'] ?? null,
                'second_name' => $member['second_name'] ?? null,
                'third_name' => $member['third_name'] ?? null,
                'last_name' => $member['last_name'] ?? null,
                'person_id' => $member['person_id'] ?? null,
                'person_birth_date' => $member['person_birth_date'] ?? null,
                'person_age' => $member['person_age'] ?? null,
                'person_gender' => $member['person_gender'] ?? null,
                'person_health_status' => $member['person_health_status'] ?? null,
                'person_type_of_guarantee' => $member['person_type_of_guarantee'] ?? null,
                'person_note' => $member['person_note'] ?? null,
            ]);
        }
    }

    private function upsertBankAccounts(string $fileIdNumber, array $bankAccounts, string $guardianIdentity): void
    {
        if (empty($bankAccounts) || !is_array($bankAccounts)) {
            return;
        }

        $processedIds = [];

        foreach ($bankAccounts as $account) {
            $hasData = !empty($account['bank_name']) || !empty($account['iban_usd']) ||
                       !empty($account['iban_shekel']) || !empty($account['re_guardian_name']) ||
                       !empty($account['person_owner_identity_number']) || !empty($account['re_phone_number']);

            if (!$hasData) {
                continue;
            }

            $existingCount = GuardianBankAccount::where('guardian_registration', $fileIdNumber)->count();
            $accountData = [
                'guardian_registration' => $fileIdNumber,
                're_id_number' => $guardianIdentity,
                'bank_name' => $account['bank_name'] ?? null,
                're_guardian_name' => $account['re_guardian_name'] ?? null,
                'person_owner_identity_number' => $account['person_owner_identity_number'] ?? null,
                're_phone_number' => $account['re_phone_number'] ?? null,
                'iban_usd' => $account['iban_usd'] ?? null,
                'iban_shekel' => $account['iban_shekel'] ?? null,
                'check_account' => $existingCount === 0 ? 1 : 0,
            ];

            if (!empty($account['id'])) {
                GuardianBankAccount::where('id', $account['id'])->update($accountData);
                $processedIds[] = $account['id'];
            } else {
                $created = GuardianBankAccount::create($accountData);
                $processedIds[] = $created->id;
            }
        }
    }

    /**
     * معالجة المرفقات الجديدة (مصفوفة مسطحة مثل واجهة التسجيل العامة).
     * كل مرفق: person_identity_number (main/deceased_father/deceased_mother/family_{idx}/رقم)،
     * file_type, stored_file_name, file_id_number + إما attachments.{i}.file (multipart)
     * أو attachments.{i}.temp_path (من الرفع المجزأ). المُخرجات في uploads/{file_id}.
     */
    private function storeAttachments(Request $request, string $fileIdNumber): void
    {
        $attachmentsData = $request->input('attachments', []);
        if (!is_array($attachmentsData) || count($attachmentsData) === 0) {
            return;
        }

        foreach ($attachmentsData as $index => $data) {
            $file = $request->hasFile("attachments.$index.file") ? $request->file("attachments.$index.file") : null;
            $tempPath = $data['temp_path'] ?? null;

            if (!$file && !$tempPath) {
                Log::warning('Mobile attachment skipped: no file or temp_path', ['index' => $index]);
                continue;
            }

            $personType = $data['person_identity_number'] ?? null;
            $fileType = $data['file_type'] ?? null;
            $storedFileName = $data['stored_file_name'] ?? null;

            $fileIdNumberAttach = (isset($data['file_id_number']) && preg_match('/^\d+$/', (string) $data['file_id_number']))
                ? str_pad($data['file_id_number'], 6, '0', STR_PAD_LEFT)
                : $fileIdNumber;

            if ($file && ($file->getMimeType() === 'image/jpeg' || $file->getMimeType() === 'image/png')) {
                $hasCropped = strpos($file->getClientOriginalName(), '_cropped') !== false ||
                              strpos((string) $storedFileName, '_cropped') !== false;
                if (!$hasCropped) {
                    Log::warning('Mobile attachment skipped: uncropped image', ['index' => $index]);
                    continue;
                }
            }

            $realPersonId = $this->resolvePersonIdentity($personType, $request);
            if (!$realPersonId || !$fileType || !$storedFileName || !preg_match('/^\d+$/', $realPersonId)) {
                Log::warning('Mobile attachment skipped: missing identity/type/name', ['index' => $index, 'personType' => $personType]);
                continue;
            }

            $extension = $file ? $file->getClientOriginalExtension() : pathinfo($storedFileName, PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'jpg';
            }
            $extension = strtolower($extension);

            $newFileName = "{$fileType}_{$fileIdNumberAttach}_{$realPersonId}.{$extension}";
            $folder = 'uploads/' . $fileIdNumberAttach;

            $path = '';
            $fileSize = 0;

            if ($file) {
                $path = $file->storeAs($folder, $newFileName, 'public');
                $fileSize = $file->getSize();
            } else {
                $finalPath = $folder . '/' . $newFileName;
                if (Storage::disk('public')->exists($tempPath)) {
                    Storage::disk('public')->move($tempPath, $finalPath);
                    $path = $finalPath;
                    $fileSize = Storage::disk('public')->size($finalPath);

                    $tempDir = dirname($tempPath);
                    if (Storage::disk('public')->exists($tempDir)) {
                        $remainingFiles = Storage::disk('public')->allFiles($tempDir);
                        if (empty($remainingFiles)) {
                            Storage::disk('public')->deleteDirectory($tempDir);
                        }
                    }
                } else {
                    Log::error('Mobile attachment temp file not found', ['tempPath' => $tempPath, 'index' => $index]);
                    continue;
                }
            }

            Attachment::create([
                'person_identity_number' => $realPersonId,
                'stored_file_name' => $newFileName,
                'file_path' => 'storage/' . $path,
                'file_type' => $fileType,
                'file_size' => $fileSize,
            ]);
        }
    }

    /**
     * تحديد رقم هوية الشخص المرتبط بالمرفق، بنفس منطق الواجهة العامة.
     */
    private function resolvePersonIdentity(?string $personType, Request $request): ?string
    {
        if (empty($personType)) {
            return null;
        }

        if ($personType === 'main') {
            return $request->input('data_id_number');
        }

        if ($personType === 'deceased_father') {
            return $request->input('father_id');
        }

        if ($personType === 'deceased_mother') {
            return $request->input('mother_id');
        }

        if (strpos($personType, 'family_') === 0) {
            $familyIndex = (int) str_replace('family_', '', $personType);
            $familyMembers = $request->input('family_members', []);
            return $familyMembers[$familyIndex]['person_id'] ?? null;
        }

        return is_numeric($personType) ? $personType : null;
    }

    /**
     * إعادة تسمية المرفقات المرتبطة برقم هوية قديم إلى الرقم الجديد
     * مع نقل الملف في نفس المجلد.
     */
    private function renamePersonAttachments(string $oldIdentity, string $fileIdNumber, string $newIdentity): void
    {
        $attachments = Attachment::where('person_identity_number', $oldIdentity)->get();
        foreach ($attachments as $attachment) {
            $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
            $parts = explode('_', $attachment->stored_file_name);
            $docType = $parts[0] ?? 'doc';
            $newFileName = "{$docType}_{$fileIdNumber}_{$newIdentity}.{$extension}";

            $oldStoragePath = str_replace('storage/', '', $attachment->file_path ?? '');

            if ($oldStoragePath) {
                $folder = dirname($oldStoragePath);
                $newStoragePath = ($folder === '.' ? '' : $folder . '/') . $newFileName;

                if (Storage::disk('public')->exists($oldStoragePath)) {
                    Storage::disk('public')->move($oldStoragePath, $newStoragePath);
                }

                $attachment->update([
                    'person_identity_number' => $newIdentity,
                    'stored_file_name' => $newFileName,
                    'file_path' => 'storage/' . $newStoragePath,
                ]);
            }
        }
    }

    /**
     * مرفقات ملف كامل (لشاشة عرض/تعديل الملف من التطبيق).
     */
    private function attachmentsForFile(string $fileIdNumber, string $guardianIdentity): array
    {
        $ids = [$guardianIdentity];

        RePeople::where('registration_id', $fileIdNumber)->pluck('person_id')->each(function ($personId) use (&$ids) {
            if ($personId && preg_match('/^\d+$/', $personId)) {
                $ids[] = $personId;
            }
        });

        $dead = DeadPepole::where('re_file_id', $fileIdNumber)->first();
        if ($dead) {
            if ($dead->father_id) {
                $ids[] = $dead->father_id;
            }
            if ($dead->mother_id) {
                $ids[] = $dead->mother_id;
            }
        }

        $additional = AdditionalDeceased::where('re_file_id', $fileIdNumber)->pluck('person_id')->all();
        foreach ($additional as $personId) {
            if ($personId && preg_match('/^\d+$/', $personId)) {
                $ids[] = $personId;
            }
        }

        return Attachment::whereIn('person_identity_number', array_values(array_unique($ids)))->get()->toArray();
    }

    /**
     * POST /api/mobile/registration/find-by-id
     *
     * يحل مشكلة اختلاف رقم الملف بين بيانات الكفالة والتسجيل: يبحث برقم الهوية
     * في جداول data / re_people / dead_people / additional_deceased /
     * sponsorships ويعيد رقم الملف الحقيقي في جدول data للدخول للملف الكامل.
     *
     * Input: id_number (مطلوب) + optional type (guardian|family|deceased|any)
     */
    public function findById(Request $request): JsonResponse
    {
        try {
            $request->validate(['id_number' => 'required|string|max:20']);

            $id = trim($request->input('id_number'));
            if (!preg_match('/^\d{6,20}$/', $id)) {
                return response()->json(['success' => false, 'message' => 'رقم هوية غير صالح'], 422);
            }
            $type = $request->input('type', 'any');

            $result = null;
            $matchedIn = null;

            // 1) المعيل في data
            if (in_array($type, ['any', 'guardian', 'breadwinner'], true)) {
                $data = DB::table('data')
                    ->where('data_id_number', $id)
                    ->select('file_id_number', 'data_id_number', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name', 'data_section_id', 'data_province', 'data_request_status')
                    ->first();
                if ($data) {
                    $result = $data;
                    $matchedIn = 'data';
                }
            }

            // 2) أفراد الأسرة في re_people
            if ($result === null && in_array($type, ['any', 'family'], true)) {
                $member = DB::table('re_people')
                    ->where('person_id', $id)
                    ->select('registration_id', 'first_name', 'second_name', 'third_name', 'last_name', 'person_id', 'person_birth_date', 'person_gender')
                    ->first();
                if ($member) {
                    $result = $member;
                    $matchedIn = 're_people';

                    $guardian = DB::table('data')->where('file_id_number', $member->registration_id)
                        ->select('file_id_number', 'data_id_number', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name', 'data_province', 'data_request_status')
                        ->first();
                    if ($guardian) {
                        $result->guardian_file_id_number = $guardian->file_id_number;
                    }
                }
            }

            // 3) المتوفون (أب/أم) في dead_people أو المتوفون الإضافيون
            if ($result === null && in_array($type, ['any', 'deceased'], true)) {
                $dead = DB::table('dead_people')
                    ->where('father_id', $id)
                    ->orWhere('mother_id', $id)
                    ->select('re_file_id', 'father_id', 'mother_id', 'father_first_name', 'father_last_name', 'mother_first_name', 'mother_last_name')
                    ->first();
                if ($dead) {
                    $isFather = (string) $dead->father_id === $id;
                    $result = (object) [
                        'person_id' => $id,
                        'first_name' => $isFather ? $dead->father_first_name : $dead->mother_first_name,
                        'last_name' => $isFather ? $dead->father_last_name : $dead->mother_last_name,
                        'registration_id' => $dead->re_file_id,
                    ];
                    $matchedIn = 'dead_people';
                } else {
                    $add = DB::table('additional_deceased')
                        ->where('person_id', $id)
                        ->select('re_file_id', 'first_name', 'last_name', 'person_id', 'relationship')
                        ->first();
                    if ($add) {
                        $result = $add;
                        $result->registration_id = $add->re_file_id;
                        $matchedIn = 'additional_deceased';
                    }
                }
            }

            // 4) الكفالات: identity_number أو guardian_identity_number
            $sponsorship = null;
            if (in_array($type, ['any', 'sponsorship'], true)) {
                $sponsorship = DB::table('sponsorships')
                    ->where('identity_number', $id)
                    ->orWhere('guardian_identity_number', $id)
                    ->select('id', 'internal_file_number', 'relation_id_number', 'identity_number', 'guardian_identity_number', 'orphan_name', 'guardian_name', 'person_type', 'sponsorship_status_id')
                    ->first();
            }

            if ($result === null && $sponsorship === null) {
                return response()->json(['success' => false, 'message' => 'لا توجد بيانات مسجلة بهذا الرقم'], 404);
            }

            // رقم الملف الحقيقي للتسجيل (data.file_id_number)
            $fileIdNumber = null;
            if ($matchedIn === 'data') {
                $fileIdNumber = $result->file_id_number;
            } elseif ($matchedIn === 're_people' || $matchedIn === 'dead_people' || $matchedIn === 'additional_deceased') {
                $fileIdNumber = $result->registration_id ?? null;
            }
            if (!$fileIdNumber && $sponsorship) {
                $fileIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number;
            }

            return response()->json([
                'success' => true,
                'matched_in' => $matchedIn,
                'file_id_number' => $fileIdNumber ? str_pad((string) $fileIdNumber, 6, '0', STR_PAD_LEFT) : null,
                'record' => $result ? (array) $result : null,
                'sponsorship' => $sponsorship,
                'sponsorship_file_number' => $sponsorship ? ($sponsorship->internal_file_number ? str_pad((string) $sponsorship->internal_file_number, 6, '0', STR_PAD_LEFT) : null) : null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors(), 'message' => 'بيانات غير صحيحة'], 422);
        } catch (\Exception $e) {
            Log::error('MobileRegistration findById failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل البحث: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/mobile/registration/deceased-lookup
     *
     * بجلب بيانات شخص متوفي مسجل على الموقع (dead_people / additional_deceased)
     * أو من السجل المدني (مع CI_DEAD_DT) لملء بيانات المتوفي تلقائياً.
     * Input: id_number
     */
    public function deceasedLookup(Request $request): JsonResponse
    {
        try {
            $request->validate(['id_number' => 'required|string|max:20']);
            $id = trim($request->input('id_number'));
            if (!preg_match('/^\d{6,20}$/', $id)) {
                return response()->json(['success' => false, 'message' => 'رقم هوية غير صالح'], 422);
            }

            $cityMap = [];
            foreach (DB::table('city')->select('id', 'city')->get() as $c) {
                $cityMap[$c->id] = $c->city;
            }

            // أ) سجلات المتوفين على الموقع
            $siteRecords = [];

            $dead = DB::table('dead_people')
                ->where('father_id', $id)
                ->orWhere('mother_id', $id)
                ->select('re_file_id', 'father_id', 'mother_id', 'father_first_name', 'father_last_name', 'father_death_date', 'mother_first_name', 'mother_last_name', 'mother_death_date')
                ->get();
            foreach ($dead as $d) {
                foreach (['father', 'mother'] as $role) {
                    $pid = $d->{$role . '_id'};
                    if ($pid !== null && (string) $pid === $id) {
                        $siteRecords[] = [
                            'first_name' => $d->{$role . '_first_name'},
                            'last_name' => $d->{$role . '_last_name'},
                            'death_date' => $d->{$role . '_death_date'},
                            'file_id_number' => $d->re_file_id,
                        ];
                    }
                }
            }

            $add = DB::table('additional_deceased')
                ->where('person_id', $id)
                ->select('first_name', 'last_name', 'relationship', 'death_date', 're_file_id')
                ->get();
            foreach ($add as $a) {
                $siteRecords[] = [
                    'first_name' => $a->first_name,
                    'last_name' => $a->last_name,
                    'relationship' => $a->relationship,
                    'death_date' => $a->death_date,
                    'file_id_number' => $a->re_file_id,
                ];
            }

            // ب) السجل المدني
            $civil = DB::connection('civilregistry')->table('persons')
                ->where('CI_ID_NUM', $id)
                ->select('CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CI_DEAD_DT', 'CITY')
                ->first();

            $isDeceased = $civil !== null && $civil->CI_DEAD_DT !== null && $civil->CI_DEAD_DT !== '';

            return response()->json([
                'success' => true,
                'found' => $civil !== null || count($siteRecords) > 0,
                'is_deceased' => $isDeceased,
                'civil' => $civil ? [
                    'id_number' => (string) $civil->CI_ID_NUM,
                    'first_name' => $civil->CI_FIRST_ARB,
                    'father_name' => $civil->CI_FATHER_ARB,
                    'grand_father_name' => $civil->CI_GRAND_FATHER_ARB,
                    'family_name' => $civil->CI_FAMILY_ARB,
                    'mother_name' => $civil->MOTHER_NAME1,
                    'birth_date' => $civil->CI_BIRTH_DT,
                    'gender' => (string) $civil->CI_SEX_CD,
                    'death_date' => $civil->CI_DEAD_DT,
                    'city' => isset($cityMap[(int) $civil->CITY]) && $civil->CITY ? $cityMap[(int) $civil->CITY] : ($civil->CITY ?: null),
                ] : null,
                'site_records' => $siteRecords,
                'message' => $isDeceased ? 'الشخص متوفي' : ($civil ? 'الشخص موجود' : 'لا توجد بيانات'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors(), 'message' => 'بيانات غير صحيحة'], 422);
        } catch (\Exception $e) {
            Log::error('MobileRegistration deceasedLookup failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل البحث: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/civil-registry/manifest
     *
     * قائمة ملفات السجل المدني المضغوطة لتحميلها على الهاتف (نسخة خفيفة).
     */
    public function civilRegistryManifest(): JsonResponse
    {
        $path = 'civil_registry/manifest.json';
        if (!Storage::disk('local')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تجهيز ملف السجل المدني بعد. يرجى تشغيل: php artisan civil-registry:export'
            ], 404);
        }

        $manifest = json_decode(Storage::disk('local')->get($path), true);
        if (!$manifest) {
            return response()->json(['success' => false, 'message' => 'ملف السجل المدني تالف'], 500);
        }

        return response()->json([
            'success' => true,
            'total_records' => $manifest['total_records'] ?? 0,
            'created_at' => $manifest['created_at'] ?? null,
            'chunks' => array_map(function ($chunk) {
                return [
                    'file' => $chunk['file'],
                    'size' => $chunk['size'],
                    'sha256' => $chunk['sha256'],
                    'records' => $chunk['records'],
                ];
            }, $manifest['chunks'] ?? []),
        ]);
    }

    /**
     * GET /api/mobile/civil-registry/person/{id}
     *
     * جلب شخص واحد من السجل المدني برقم الهوية (للبحث الفردي عند عدم
     * اكتمال التحميل المحلي على الهاتف).
     */
    public function civilRegistryPerson(string $id): JsonResponse
    {
        if (!preg_match('/^\d{6,20}$/', $id)) {
            return response()->json(['success' => false, 'message' => 'رقم هوية غير صالح'], 422);
        }

        $person = DB::connection('civilregistry')->table('persons')
            ->where('CI_ID_NUM', $id)
            ->select('CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CI_PERSONAL_CD', 'CI_DEAD_DT', 'CITY')
            ->first();

        if (!$person) {
            return response()->json(['success' => false, 'found' => false, 'message' => 'لا توجد بيانات في السجل المدني'], 404);
        }

        $cityMap = [];
        foreach (DB::table('city')->select('id', 'city')->get() as $c) {
            $cityMap[$c->id] = $c->city;
        }

        return response()->json([
            'success' => true,
            'found' => true,
            'is_deceased' => $person->CI_DEAD_DT !== null && $person->CI_DEAD_DT !== '',
            'person' => [
                'id_number' => (string) $person->CI_ID_NUM,
                'first_name' => $person->CI_FIRST_ARB,
                'father_name' => $person->CI_FATHER_ARB,
                'grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                'family_name' => $person->CI_FAMILY_ARB,
                'mother_name' => $person->MOTHER_NAME1,
                'birth_date' => $person->CI_BIRTH_DT,
                'gender' => (string) $person->CI_SEX_CD,
                'personal_cd' => (string) $person->CI_PERSONAL_CD,
                'death_date' => $person->CI_DEAD_DT,
                'city' => isset($cityMap[(int) $person->CITY]) && $person->CITY ? $cityMap[(int) $person->CITY] : ($person->CITY ?: null),
            ],
        ]);
    }

    /**
     * GET /api/mobile/civil-registry/file/{file}
     *
     * يبثّ ملف سجل مدني مضغوط من storage/app/civil_registry (محمي بمصادقة).
     */
    public function serveCivilRegistryFile(string $file): \Symfony\Component\HttpFoundation\Response
    {
        if (!preg_match('/^chunk\.\d{4}\.json\.gz$/', $file) && $file !== 'manifest.json') {
            return response()->json(['success' => false, 'message' => 'ملف غير صالح'], 400);
        }

        $path = 'civil_registry/' . $file;
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'الملف غير موجود'], 404);
        }

        return response()->streamDownload(function () use ($path) {
            $stream = Storage::disk('local')->readStream($path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $file, [
            'Content-Type' => 'application/gzip',
            'Content-Length' => Storage::disk('local')->size($path),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}