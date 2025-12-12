<?php

namespace App\Services;

use App\Models\GuardianBankAccount;
use Illuminate\Support\Facades\Log;

/**
 * خدمة التحقق من تكرار الحسابات البنكية
 * تمنع إدخال نفس الحساب البنكي لنفس الشخص أكثر من مرة
 */
class BankAccountValidationService
{
    /**
     * التحقق من وجود حساب بنكي مكرر
     *
     * @param array $bankAccountData البيانات البنكية المراد التحقق منها
     * @param int|null $excludeId معرف الحساب المستثنى من التحقق (للتعديل)
     * @return array ['is_duplicate' => bool, 'message' => string, 'existing_account' => array|null]
     */
    public function checkDuplicateBankAccount(array $bankAccountData, ?int $excludeId = null): array
    {
        try {
            // 🔥 استخراج جميع الأعمدة الخمسة المطلوبة للتحقق من التكرار
            $guardianRegistration = $bankAccountData['guardian_registration'] ?? null;
            $personOwnerIdentityNumber = $bankAccountData['person_owner_identity_number'] ?? null;
            $reIdNumber = $bankAccountData['re_id_number'] ?? null;
            $rePhoneNumber = $bankAccountData['re_phone_number'] ?? null;
            $bankName = $bankAccountData['bank_name'] ?? null;

            Log::info('🔍 بدء التحقق من تكرار الحساب البنكي (5 أعمدة):', [
                '1_guardian_registration' => $guardianRegistration,
                '2_person_owner_identity_number' => $personOwnerIdentityNumber,
                '3_re_id_number' => $reIdNumber,
                '4_re_phone_number' => $rePhoneNumber,
                '5_bank_name' => $bankName,
                'exclude_id' => $excludeId
            ]);

            // التحقق من وجود البيانات الأساسية (الأعمدة الخمسة مطلوبة)
            if (empty($guardianRegistration) || empty($personOwnerIdentityNumber) || empty($reIdNumber)) {
                Log::warning('⚠️ بيانات غير كافية للتحقق - الأعمدة الثلاثة الأولى مطلوبة');
                return [
                    'is_duplicate' => false,
                    'message' => 'بيانات غير كافية للتحقق من التكرار (guardian_registration, person_owner_identity_number, re_id_number مطلوبة)',
                    'existing_account' => null
                ];
            }

            // 🎯 بناء الاستعلام للبحث عن حساب مكرر
            // ✅ يجب أن تتطابق جميع الأعمدة الخمسة لاعتبار الحساب مكرر
            $query = GuardianBankAccount::query()
                ->where('guardian_registration', $guardianRegistration)
                ->where('person_owner_identity_number', $personOwnerIdentityNumber)
                ->where('re_id_number', $reIdNumber);

            // ✅ إضافة الشرطين المتبقيين (re_phone_number و bank_name)
            if (!empty($rePhoneNumber)) {
                $query->where('re_phone_number', $rePhoneNumber);
            }

            if (!empty($bankName)) {
                $query->where('bank_name', $bankName);
            }

            // استثناء الحساب الحالي في حالة التعديل
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }

            $existingAccount = $query->first();

            if ($existingAccount) {
                Log::warning('🚫 تم العثور على حساب بنكي مكرر - تم منع الإدخال:', [
                    'existing_account_id' => $existingAccount->id,
                    '1_guardian_registration' => $existingAccount->guardian_registration,
                    '2_person_owner_identity_number' => $existingAccount->person_owner_identity_number,
                    '3_re_id_number' => $existingAccount->re_id_number,
                    '4_re_phone_number' => $existingAccount->re_phone_number,
                    '5_bank_name' => $existingAccount->bank_name,
                    'created_at' => $existingAccount->created_at->format('Y-m-d H:i:s')
                ]);

                return [
                    'is_duplicate' => true,
                    'message' => $this->buildDuplicateMessage($existingAccount),
                    'existing_account' => [
                        'id' => $existingAccount->id,
                        'guardian_registration' => $existingAccount->guardian_registration,
                        'person_owner_identity_number' => $existingAccount->person_owner_identity_number,
                        're_id_number' => $existingAccount->re_id_number,
                        're_phone_number' => $existingAccount->re_phone_number,
                        'bank_name' => $existingAccount->bank_name,
                        'iban_number' => $existingAccount->iban_number,
                        'created_at' => $existingAccount->created_at
                    ]
                ];
            }

            Log::info('✅ لا يوجد حساب بنكي مكرر');
            return [
                'is_duplicate' => false,
                'message' => 'الحساب البنكي غير مكرر',
                'existing_account' => null
            ];

        } catch (\Exception $e) {
            Log::error('❌ خطأ في التحقق من تكرار الحساب البنكي:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'is_duplicate' => false,
                'message' => 'حدث خطأ أثناء التحقق: ' . $e->getMessage(),
                'existing_account' => null
            ];
        }
    }

    /**
     * التحقق من مجموعة حسابات بنكية دفعة واحدة
     *
     * @param array $bankAccounts مصفوفة من الحسابات البنكية
     * @return array ['has_duplicates' => bool, 'duplicates' => array]
     */
    public function checkMultipleBankAccounts(array $bankAccounts): array
    {
        $duplicates = [];
        $hasDuplicates = false;

        foreach ($bankAccounts as $index => $bankAccount) {
            $result = $this->checkDuplicateBankAccount($bankAccount);

            if ($result['is_duplicate']) {
                $hasDuplicates = true;
                $duplicates[] = [
                    'index' => $index,
                    'message' => $result['message'],
                    'existing_account' => $result['existing_account']
                ];
            }
        }

        return [
            'has_duplicates' => $hasDuplicates,
            'duplicates' => $duplicates
        ];
    }

    /**
     * بناء رسالة توضيحية عن الحساب المكرر
     *
     * @param GuardianBankAccount $account
     * @return string
     */
    private function buildDuplicateMessage(GuardianBankAccount $account): string
    {
        $message = "🚫 يوجد حساب بنكي مكرر مسجل مسبقاً بنفس البيانات الخمسة:\n";
        $message .= "1️⃣ رقم الملف الداخلي (guardian_registration): {$account->guardian_registration}\n";
        $message .= "2️⃣ رقم هوية صاحب الحساب (person_owner_identity_number): {$account->person_owner_identity_number}\n";
        $message .= "3️⃣ رقم هوية المعيل (re_id_number): {$account->re_id_number}\n";

        if (!empty($account->re_phone_number)) {
            $message .= "4️⃣ رقم جوال المحفظة (re_phone_number): {$account->re_phone_number}\n";
        }

        if (!empty($account->bank_name)) {
            $bankNameObj = \App\Models\BankName::find($account->bank_name);
            $bankNameText = $bankNameObj ? $bankNameObj->bank_name : $account->bank_name;
            $message .= "5️⃣ اسم البنك/المحفظة (bank_name): {$bankNameText}\n";
        }

        if (!empty($account->iban_number)) {
            $message .= "- رقم الآيبان: {$account->iban_number}\n";
        }

        $message .= "- تاريخ التسجيل: " . $account->created_at->format('Y-m-d H:i:s');

        return $message;
    }

    /**
     * التحقق السريع من التكرار باستخدام المعايير الأساسية فقط
     *
     * @param string $guardianRegistration رقم الملف
     * @param string $personOwnerIdentityNumber رقم هوية صاحب الحساب
     * @param int|null $excludeId معرف الحساب المستثنى
     * @return bool
     */
    public function quickDuplicateCheck(string $guardianRegistration, string $personOwnerIdentityNumber, ?int $excludeId = null): bool
    {
        $query = GuardianBankAccount::where('guardian_registration', $guardianRegistration)
            ->where('person_owner_identity_number', $personOwnerIdentityNumber);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * الحصول على جميع الحسابات البنكية لشخص معين
     *
     * @param string $guardianRegistration رقم الملف
     * @param string $personOwnerIdentityNumber رقم هوية صاحب الحساب
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExistingAccounts(string $guardianRegistration, string $personOwnerIdentityNumber)
    {
        return GuardianBankAccount::where('guardian_registration', $guardianRegistration)
            ->where('person_owner_identity_number', $personOwnerIdentityNumber)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
