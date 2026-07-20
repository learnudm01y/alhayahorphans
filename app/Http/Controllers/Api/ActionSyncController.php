<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActionSyncController extends Controller
{
    /**
     * طبقة الأكشن المتكاملة لمعالجة جميع التعديلات بأمان
     */
    public function syncAction(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->all();
            
            // The payload might be just the updates (if coming from old queue format)
            // or a structured action.
            $actionType = $data['action'] ?? $data['dataType'] ?? $data['type'] ?? 'sponsorship_update';
            $payload = $data['data'] ?? $data['payload'] ?? $data; // Fallback to entire data if not structured
            
            //  FIX: The mobile app wraps the actual updates inside another 'payload' key
            $actualUpdates = isset($payload['payload']) && is_array($payload['payload']) ? $payload['payload'] : $payload;
            
            $entityId = $payload['entity_id'] ?? $payload['id'] ?? $data['entity_id'] ?? $data['id'] ?? null;

            if (!$entityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'entity_id is required'
                ], 400);
            }

            Log::info(" [ActionSyncController] Processing action: {$actionType} for entity: {$entityId}");

            if ($actionType === 'sponsorship_update') {
                $this->handleSponsorshipUpdate($entityId, $actualUpdates, $request->user());
            } else {
                Log::warning("⚠️ [ActionSyncController] Unknown action type: {$actionType}");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تطبيق التعديلات بأمان',
                'action_id' => $data['action_id'] ?? null,
                'delivery_status' => 'delivered_to_server'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(" [ActionSyncController] فشل في معالجة الأكشن: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء المزامنة: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleSponsorshipUpdate($id, $updates, $user)
    {
        $userId = $user ? $user->id : null;
        $sponsorship = DB::table('sponsorships')->where('id', $id)->first();
        if (!$sponsorship) {
            Log::warning("⚠️ [ActionSyncController] كفالة غير موجودة: {$id}");
            return;
        }

        // 🆕 أولوية المزامنة والتضارب (Last Write Wins)
        $localUpdatedAt = $updates['local_updated_at'] ?? null;
        if ($localUpdatedAt && isset($sponsorship->updated_at)) {
            $serverTime = strtotime($sponsorship->updated_at);
            $localTime = strtotime($localUpdatedAt);

            if ($serverTime > $localTime) {
                Log::warning("⚠️ [ActionSyncController] تم رفض التحديث: بيانات السيرفر أحدث من التعديل المرسل من الهاتف.", [
                    'sponsorship_id' => $id,
                    'server_updated_at' => $sponsorship->updated_at,
                    'local_updated_at' => $localUpdatedAt
                ]);
                // تجاوز التحديث لحماية البيانات، ويُعتبر ناجحاً في الرد حتى لا يحاول الهاتف إعادة إرساله للأبد
                return; 
            }
        }

        // 1. تحديث جدول sponsorships
        $sponsorshipAllowed = [
            'sponsorship_status_id', 'notes', 'orphan_name', 'guardian_name',
            'identity_number', 'guardian_identity_number', 'health_status_id', 'person_type'
        ];
        $sponsorshipUpdates = array_intersect_key($updates, array_flip($sponsorshipAllowed));
        if (!empty($sponsorshipUpdates)) {
            $sponsorshipUpdates['updated_at'] = now();
            if ($userId) {
                $sponsorshipUpdates['updated_by'] = json_encode([
                    'user_id' => $userId,
                    'timestamp' => now()->toISOString()
                ]);
            }
            DB::table('sponsorships')->where('id', $id)->update($sponsorshipUpdates);
            Log::info("✅ [ActionSyncController] تم تحديث الكفالة {$id} بنجاح.");
        }

        // 2. تحديث الجداول المرتبطة بشخصية المكفول بناءً على person_type
        $personType = $updates['person_type'] ?? $sponsorship->person_type ?? 'orphan';
        $identityNumber = $updates['identity_number'] ?? $sponsorship->identity_number;

        if ($identityNumber) {
            if (in_array($personType, ['orphan', 'family_member'])) {
                // تحديث جدول re_people للأيتام وأفراد الأسرة
                $rePeopleData = [];
                if (array_key_exists('first_name', $updates)) $rePeopleData['first_name'] = $updates['first_name'];
                if (array_key_exists('second_name', $updates)) $rePeopleData['second_name'] = $updates['second_name'];
                if (array_key_exists('third_name', $updates)) $rePeopleData['third_name'] = $updates['third_name'];
                if (array_key_exists('last_name', $updates)) $rePeopleData['last_name'] = $updates['last_name'];
                if (array_key_exists('birth_date', $updates)) $rePeopleData['person_birth_date'] = $updates['birth_date'];
                if (array_key_exists('orphan_gender', $updates)) $rePeopleData['person_gender'] = $updates['orphan_gender'] == 'ذكر' ? 1 : ($updates['orphan_gender'] == 'أنثى' ? 2 : null);
                if (array_key_exists('health_status_id', $updates)) $rePeopleData['person_health_status'] = $updates['health_status_id'];
                
                if (!empty($rePeopleData)) {
                    $rePeopleData['updated_at'] = now();
                    DB::table('re_people')->where('person_id', $identityNumber)->update($rePeopleData);
                    Log::info("✅ [ActionSyncController] تم تحديث بيانات re_people للمكفول (الهوية: {$identityNumber}).");
                }
            } elseif (in_array($personType, ['deceased_father', 'deceased_mother'])) {
                // تحديث جدول dead_people للمتوفين
                $deadPeopleData = [];
                $prefix = $personType === 'deceased_father' ? 'father_' : 'mother_';
                if (array_key_exists('first_name', $updates)) $deadPeopleData[$prefix . 'first_name'] = $updates['first_name'];
                if (array_key_exists('second_name', $updates)) $deadPeopleData[$prefix . 'second_name'] = $updates['second_name'];
                if (array_key_exists('third_name', $updates)) $deadPeopleData[$prefix . 'third_name'] = $updates['third_name'];
                if (array_key_exists('last_name', $updates)) $deadPeopleData[$prefix . 'last_name'] = $updates['last_name'];
                
                if (!empty($deadPeopleData)) {
                    DB::table('dead_people')
                        ->where(function($q) use ($identityNumber) {
                            $q->where('father_id', $identityNumber)->orWhere('mother_id', $identityNumber);
                        })
                        ->update($deadPeopleData);
                    Log::info("✅ [ActionSyncController] تم تحديث بيانات dead_people للمتوفى (الهوية: {$identityNumber}).");
                }
            } elseif ($personType === 'breadwinner') {
                // تحديث جدول data للمعيل المكفول نفسه
                $orphanData = [];
                if (array_key_exists('first_name', $updates)) $orphanData['data_first_name'] = $updates['first_name'];
                if (array_key_exists('second_name', $updates)) $orphanData['data_father_name'] = $updates['second_name'];
                if (array_key_exists('third_name', $updates)) $orphanData['data_grand_father_name'] = $updates['third_name'];
                if (array_key_exists('last_name', $updates)) $orphanData['data_family_name'] = $updates['last_name'];
                if (array_key_exists('birth_date', $updates)) $orphanData['data_birth_date'] = $updates['birth_date'];
                if (array_key_exists('orphan_gender', $updates)) $orphanData['data_gender'] = $updates['orphan_gender'] == 'ذكر' ? 1 : ($updates['orphan_gender'] == 'أنثى' ? 2 : null);
                
                if (!empty($orphanData)) {
                    $orphanData['updated_at'] = now();
                    DB::table('data')->where('data_id_number', $identityNumber)->update($orphanData);
                    Log::info("✅ [ActionSyncController] تم تحديث بيانات data للمعيل المكفول (الهوية: {$identityNumber}).");
                }
            }
        }

        // 3. تحديث جدول data للمعيل/الولي
        $guardianIdentityNumber = $updates['guardian_identity_number'] ?? $sponsorship->guardian_identity_number;
        if ($guardianIdentityNumber) {
            $guardianData = [];
            if (array_key_exists('guardian_first_name', $updates)) $guardianData['data_first_name'] = $updates['guardian_first_name'];
            if (array_key_exists('guardian_father_name', $updates)) $guardianData['data_father_name'] = $updates['guardian_father_name'];
            if (array_key_exists('guardian_grandfather_name', $updates)) $guardianData['data_grand_father_name'] = $updates['guardian_grandfather_name'];
            if (array_key_exists('guardian_family_name', $updates)) $guardianData['data_family_name'] = $updates['guardian_family_name'];
            if (array_key_exists('guardian_phone', $updates)) $guardianData['data_phone_number'] = $updates['guardian_phone'];
            if (array_key_exists('guardian_phone2', $updates)) $guardianData['data_alt_phone_number'] = $updates['guardian_phone2'];
            if (array_key_exists('guardian_detailed_address', $updates)) $guardianData['data_current_address'] = $updates['guardian_detailed_address'];
            if (array_key_exists('guardian_city_id', $updates)) $guardianData['data_city'] = $updates['guardian_city_id'];
            
            if (!empty($guardianData)) {
                $guardianData['updated_at'] = now();
                DB::table('data')->where('data_id_number', $guardianIdentityNumber)->update($guardianData);
                Log::info("✅ [ActionSyncController] تم تحديث بيانات المعيل (الهوية: {$guardianIdentityNumber}).");
            }
        }

        // 4. تحديث portal_general_registration_field_values للهواتف والعناوين
        $fileIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number ?? '';
        
        $phone = $updates['orphan_phone'] ?? $updates['guardian_phone'] ?? null;
        if (!empty($phone)) $this->savePortalField($id, $fileIdNumber, $identityNumber, 'field_data_phone_number', $phone, $userId);
        
        $altPhone = $updates['orphan_phone2'] ?? $updates['guardian_phone2'] ?? null;
        if (!empty($altPhone)) $this->savePortalField($id, $fileIdNumber, $identityNumber, 'field_data_alt_phone_number', $altPhone, $userId);
        
        $address = $updates['orphan_detailed_address'] ?? $updates['guardian_detailed_address'] ?? null;
        if (!empty($address)) $this->savePortalField($id, $fileIdNumber, $identityNumber, 'field_housing_address_detail', $address, $userId);
        
        $cityId = $updates['orphan_city_id'] ?? $updates['guardian_city_id'] ?? null;
        if (!empty($cityId)) $this->savePortalField($id, $fileIdNumber, $identityNumber, 'field_data_city', $cityId, $userId);
        
        $healthStatus = $updates['health_status_id'] ?? null;
        if (!empty($healthStatus)) $this->savePortalField($id, $fileIdNumber, $identityNumber, 'field_health_status', $healthStatus, $userId);

        // 5. تحديث الحسابات البنكية إذا وجدت
        if (isset($updates['bank_accounts_updates']) && is_array($updates['bank_accounts_updates'])) {
            foreach ($updates['bank_accounts_updates'] as $index => $bankUpdate) {
                if (isset($bankUpdate['id'])) {
                    $bankAllowed = ['bank_name_id', 'account_number', 'branch_name', 'iban', 'iban_usd', 'iban_shekel', 'account_owner_name'];
                    $bData = array_intersect_key($bankUpdate, array_flip($bankAllowed));
                    if (!empty($bData)) {
                        DB::table('bank_accounts')->where('id', $bankUpdate['id'])->update($bData);
                        Log::info("✅ [ActionSyncController] تم تحديث حساب بنكي ({$bankUpdate['id']}) للكفالة {$id}.");
                    }
                } else {
                    // حساب جديد
                    $bankAllowed = ['bank_name_id', 'account_number', 'branch_name', 'iban', 'iban_usd', 'iban_shekel', 'account_owner_name'];
                    $bData = array_intersect_key($bankUpdate, array_flip($bankAllowed));
                    if (!empty($bData)) {
                        $bData['sponsorship_id'] = $id;
                        DB::table('bank_accounts')->insert($bData);
                        Log::info("✅ [ActionSyncController] تم إضافة حساب بنكي جديد للكفالة {$id}.");
                    }
                }
            }
        }

        // 6. تحديث حالة الكفالة إلى انتظار الصرف
        $this->updateSponsorshipStatusToWaitingPayment($id, $userId);
    }

    private function updateSponsorshipStatusToWaitingPayment($sponsorshipId, $userId)
    {
        try {
            $waitingPaymentStatus = DB::table('sponsorship_statuses')
                ->where('description', 'LIKE', '%انتظار الصرف%')
                ->orWhere('description', 'LIKE', '%انتظار%الصرف%')
                ->first();

            if (!$waitingPaymentStatus) {
                $waitingPaymentStatus = DB::table('sponsorship_statuses')
                    ->where('description', 'LIKE', '%محدث%')
                    ->orWhere('id', 3)
                    ->first();
            }

            if ($waitingPaymentStatus) {
                DB::table('sponsorships')->where('id', $sponsorshipId)->update([
                    'sponsorship_status_id' => $waitingPaymentStatus->id,
                    'updated_at' => now(),
                    'updated_by' => json_encode(['user_id' => $userId, 'timestamp' => now()->toISOString(), 'note' => 'Auto-status update'])
                ]);
                Log::info("✅ [ActionSyncController] تم تغيير حالة الكفالة {$sponsorshipId} إلى انتظار الصرف.");
            }
        } catch (\Exception $e) {
            Log::error("❌ [ActionSyncController] فشل تحديث حالة الكفالة: " . $e->getMessage());
        }
    }

    private function savePortalField($sponsorshipId, $fileIdNumber, $identityNumber, $fieldKey, $fieldValue, $userId)
    {
        try {
            DB::table('portal_general_registration_field_values')->updateOrInsert(
                ['file_id_number' => $fileIdNumber, 'field_key' => $fieldKey],
                [
                    'sponsorship_id' => $sponsorshipId,
                    'identity_number' => $identityNumber,
                    'field_value' => $fieldValue,
                    'updated_by_user_id' => $userId,
                    'updated_at' => now()
                ]
            );
        } catch (\Exception $e) {
            Log::error("❌ [ActionSyncController] فشل حفظ الحقل {$fieldKey}", ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * جلب العمليات المعلقة للهاتف
     */
    public function getPendingActions(Request $request): JsonResponse
    {
        try {
            // جلب الأوامر المعلقة (يمكن تحديدها للمستخدم أو للجميع)
            $userId = $request->user() ? $request->user()->id : null;
            
            $query = \App\Models\ServerSyncAction::where('status', 'pending');
            
            // إذا كان هناك أوامر مخصصة لمستخدم معين
            if ($userId) {
                $query->where(function($q) use ($userId) {
                    $q->whereNull('user_id')->orWhere('user_id', $userId);
                });
            }
            
            $actions = $query->orderBy('id', 'asc')->get();
            
            if ($actions->isNotEmpty()) {
                \App\Models\ServerSyncAction::whereIn('id', $actions->pluck('id'))
                    ->update(['delivery_status' => 'delivered_to_app']);
            }
            
            return response()->json([
                'success' => true,
                'actions' => $actions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الطوابير: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تأكيد تنفيذ العملية في الهاتف (Acknowledgment)
     */
    public function ackAction(Request $request): JsonResponse
    {
        try {
            $actionIds = $request->input('action_ids', []);
            
            if (empty($actionIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'action_ids is required'
                ], 400);
            }
            
            \App\Models\ServerSyncAction::whereIn('id', $actionIds)
                ->update(['status' => 'completed', 'delivery_status' => 'executed_in_app']);
                
            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد وصول وتنفيذ التحديثات بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تأكيد الطوابير: ' . $e->getMessage()
            ], 500);
        }
    }
}
