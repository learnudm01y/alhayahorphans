<?php

namespace App\Services;

use App\Models\Data;
use App\Models\Sponsorship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * خدمة ربط الكفالات بملف المعيل الموجود مسبقاً في قاعدة البيانات.
 *
 * عند العثور على المعيل في جدول data يتم:
 * 1) أخذ أحدث سجل له (عند تكرار رقم الهوية).
 * 2) إعادة ربط كل الجداول التابعة للملف برقم ملف المعيل الموجود:
 *    - sponsorships.relation_id_number
 *    - re_people.registration_id
 *    - dead_people.re_file_id
 *    - additional_deceased.re_file_id
 *    - guardian_bank_accounts.guardian_registration
 *    - portal_general_registration_field_values.file_id_number
 *
 * لا يتم المساس بـ sponsorships.internal_file_number (رقم دخول الكفالة).
 */
class GuardianFileService
{
    /**
     * أحدث سجل معيل في جدول data برقم الهوية (الأحدث أولاً عند التكرار).
     */
    public function findLatestDataByIdentity(string $identity): ?Data
    {
        $identity = trim($identity);

        if ($identity === '') {
            return null;
        }

        return Data::where('data_id_number', $identity)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * رقم ملف أحدث معيل مسجل برقم الهوية، أو null إذا لم يوجد.
     * يعاد الرقم منسقاً بستة أرقام ليتطابق مع صيغة generateNewFileNumber().
     */
    public function findFileByIdentity(string $identity): ?string
    {
        $record = $this->findLatestDataByIdentity($identity);

        if (!$record) {
            return null;
        }

        return $this->normalizeFileNumber((string) $record->file_id_number);
    }

    /**
     * إعادة ربط كفالة واحدة بملف معيل موجود مسبقاً.
     *
     * @param Sponsorship $sponsorship الكفالة المراد ربطها
     * @param string      $targetFile  رقم ملف المعيل الموجود في قاعدة البيانات
     * @param string|null $guardianIdentity هوية المعيل (لمنع سحب ملف يخص شخصاً آخر)
     * @return array ملخص ما تم (للتسجيل فقط - العملية صامتة للمستخدم)
     */
    public function relinkSponsorship(Sponsorship $sponsorship, string $targetFile, ?string $guardianIdentity = null): array
    {
        $targetFile = $this->normalizeFileNumber($targetFile);

        if ($targetFile === '') {
            return [];
        }

        $summary = [
            'target' => $targetFile,
            'sources' => [],
            'skipped_sources' => [],
            'counts' => [],
            'sponsorship_updated' => false,
        ];

        // المرشحون لرقم الملف الحالي: رقم الربط ثم رقم الملف الداخلي
        $candidates = [];
        foreach ([(string) $sponsorship->relation_id_number, (string) $sponsorship->internal_file_number] as $candidate) {
            $candidate = $this->normalizeFileNumber($candidate);

            if ($candidate !== '' && $candidate !== $targetFile && !in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        foreach ($candidates as $sourceFile) {
            if ($this->sourceBelongsToAnotherGuardian($sourceFile, $guardianIdentity)) {
                $summary['skipped_sources'][$sourceFile] = 'الملف المصدر مرتبط بهوية معيل مختلفة - تم تجاوزه';
                continue;
            }

            $summary['sources'][] = $sourceFile;
            $summary['counts'][$sourceFile] = $this->relinkFile($sourceFile, $targetFile, $sponsorship->id, $guardianIdentity);
        }

        if ($this->normalizeFileNumber((string) $sponsorship->relation_id_number) !== $targetFile) {
            $sponsorship->relation_id_number = $targetFile;
            $sponsorship->save();
            $summary['sponsorship_updated'] = true;
        }

        if (!empty($summary['sources']) || $summary['sponsorship_updated']) {
            Log::info('GUARDIAN_FILE_RELINKED', [
                'sponsorship_id' => $sponsorship->id,
                'target_file' => $targetFile,
                'guardian_identity' => $guardianIdentity,
                'sources' => $summary['sources'],
                'skipped_sources' => $summary['skipped_sources'],
                'counts' => $summary['counts'],
                'sponsorship_updated' => $summary['sponsorship_updated'],
            ]);
        }

        return $summary;
    }

    /**
     * إعادة ربط كل السجلات التابعة لملف إلى ملف آخر.
     *
     * @param string   $fromFile       رقم الملف المصدر
     * @param string   $toFile         رقم الملف الهدف (ملف المعيل الموجود)
     * @param int|null $sponsorshipId  لتحديد كفالة واحدة عند توفرها
     * @param string|null $expectedIdentity هوية المعيل المتوقعة للملف المصدر (حماية)
     * @return array   عدد السجلات المحدثة لكل جدول
     */
    public function relinkFile(string $fromFile, string $toFile, ?int $sponsorshipId = null, ?string $expectedIdentity = null): array
    {
        $fromFile = trim($fromFile);
        $toFile = $this->normalizeFileNumber(trim($toFile));

        if ($fromFile === '' || $toFile === '') {
            return [];
        }

        if ($this->normalizeFileNumber($fromFile) === $toFile) {
            return [];
        }

        if ($this->sourceBelongsToAnotherGuardian($fromFile, $expectedIdentity)) {
            Log::warning('GUARDIAN_FILE_RELINK_SKIPPED', [
                'from_file' => $fromFile,
                'to_file' => $toFile,
                'expected_identity' => $expectedIdentity,
                'reason' => 'الملف المصدر مرتبط بهوية معيل مختلفة - تم تجاوزه',
            ]);

            return [];
        }

        $fromCandidates = $this->fileCandidates($fromFile);
        $toCandidates = $this->fileCandidates($toFile);
        $counts = [];

        try {
            // 1) الكفالات: relation_id_number فقط (لا نغير internal_file_number)
            $sponsorshipsQuery = DB::table('sponsorships')->whereIn('relation_id_number', $fromCandidates);

            if ($sponsorshipId !== null) {
                $sponsorshipsQuery->where('id', $sponsorshipId);
            }

            $counts['sponsorships'] = $sponsorshipsQuery->update([
                'relation_id_number' => $toFile,
                'updated_at' => now(),
            ]);

            // 2) أفراد الأسرة
            $counts['re_people'] = DB::table('re_people')
                ->whereIn('registration_id', $fromCandidates)
                ->update([
                    'registration_id' => $toFile,
                    'updated_at' => now(),
                ]);

            // 3) المتوفون
            $counts['dead_people'] = DB::table('dead_people')
                ->whereIn('re_file_id', $fromCandidates)
                ->update([
                    're_file_id' => $toFile,
                    'updated_at' => now(),
                ]);

            // 4) المتوفون الإضافيون
            if (Schema::hasTable('additional_deceased')) {
                $counts['additional_deceased'] = DB::table('additional_deceased')
                    ->whereIn('re_file_id', $fromCandidates)
                    ->update([
                        're_file_id' => $toFile,
                        'updated_at' => now(),
                    ]);
            }

            // 5) الحسابات البنكية
            $counts['guardian_bank_accounts'] = DB::table('guardian_bank_accounts')
                ->whereIn('guardian_registration', $fromCandidates)
                ->update([
                    'guardian_registration' => $toFile,
                    'updated_at' => now(),
                ]);

            // 6) حقول البوابة - فهرس فريد على (file_id_number, field_key)
            //    لذا نحذف تعارضات الهدف أولاً ثم ننقل قيم المصدر
            if (Schema::hasTable('portal_general_registration_field_values')) {
                $portalKeys = DB::table('portal_general_registration_field_values')
                    ->whereIn('file_id_number', $fromCandidates)
                    ->pluck('field_key')
                    ->unique()
                    ->values();

                if ($portalKeys->isNotEmpty()) {
                    $deletedConflicts = DB::table('portal_general_registration_field_values')
                        ->whereIn('file_id_number', $toCandidates)
                        ->whereIn('field_key', $portalKeys)
                        ->delete();

                    $counts['portal_general_registration_field_values'] = DB::table('portal_general_registration_field_values')
                        ->whereIn('file_id_number', $fromCandidates)
                        ->update([
                            'file_id_number' => $toFile,
                            'updated_at' => now(),
                        ]);

                    if ($deletedConflicts > 0) {
                        $counts['portal_conflicts_removed'] = $deletedConflicts;
                    }
                }
            }

            Log::info('GUARDIAN_FILE_RELINK_APPLIED', [
                'from_file' => $fromFile,
                'to_file' => $toFile,
                'sponsorship_id' => $sponsorshipId,
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            Log::error('GUARDIAN_FILE_RELINK_FAILED', [
                'from_file' => $fromFile,
                'to_file' => $toFile,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $counts;
    }

    /**
     * تجهيز بيانات المعيل المأخوذة من جدول data بصيغتين:
     * - أسماء حقول السجل المدني (first_name ...) للبوابة
     * - أسماء أعمدة جدول data (data_first_name ...) للإدارة
     * حتى تعمل دوال الملء في الواجهتين دون تغيير.
     */
    public function buildGuardianPayload(object $guardianRecord): array
    {
        $genderValue = (int) ($guardianRecord->data_gender ?? 0);
        $genderText = $genderValue === 1 ? 'ذكر' : ($genderValue === 2 ? 'أنثى' : '');

        $cityId = (string) ($guardianRecord->data_city ?? '');
        $cityName = $cityId !== ''
            ? (string) (DB::table('city')->where('id', $cityId)->value('city') ?? '')
            : '';

        $firstName = (string) ($guardianRecord->data_first_name ?? '');
        $secondName = (string) ($guardianRecord->data_father_name ?? '');
        $thirdName = (string) ($guardianRecord->data_grand_father_name ?? '');
        $lastName = (string) ($guardianRecord->data_family_name ?? '');

        return [
            'source' => 'data',
            'file_id_number' => str_pad((string) $guardianRecord->file_id_number, 6, '0', STR_PAD_LEFT),
            'identity_number' => (string) ($guardianRecord->data_id_number ?? ''),
            'full_name' => trim($firstName . ' ' . $secondName . ' ' . $thirdName . ' ' . $lastName),

            // بصيغة السجل المدني (بوابة المستخدم)
            'first_name' => $firstName,
            'second_name' => $secondName,
            'third_name' => $thirdName,
            'last_name' => $lastName,
            'birth_date' => (string) ($guardianRecord->data_birth_date ?? ''),
            'gender' => $genderText,
            'city' => $cityName,
            'phone_number' => (string) ($guardianRecord->data_phone_number ?? ''),
            'current_address' => (string) ($guardianRecord->data_current_address ?? ''),
            'relationship' => (string) ($guardianRecord->data_relationship ?? ''),

            // بصيغة جدول data (مسار الإدارة)
            'data_id_number' => (string) ($guardianRecord->data_id_number ?? ''),
            'data_first_name' => $firstName,
            'data_father_name' => $secondName,
            'data_grand_father_name' => $thirdName,
            'data_family_name' => $lastName,
            'data_birth_date' => (string) ($guardianRecord->data_birth_date ?? ''),
            'data_gender' => $genderValue ?: null,
            'data_marital_status' => (string) ($guardianRecord->data_marital_status ?? ''),
            'data_phone_number' => (string) ($guardianRecord->data_phone_number ?? ''),
            'data_alt_phone_number' => (string) ($guardianRecord->data_alt_phone_number ?? ''),
            'data_current_address' => (string) ($guardianRecord->data_current_address ?? ''),
            'data_city' => $cityId,
            'data_province' => (string) ($guardianRecord->data_province ?? ''),
            'data_relationship' => (string) ($guardianRecord->data_relationship ?? ''),
        ];
    }

    /**
     * جلب جميع السجلات المرتبطة بملف المعيل
     * (أفراد الأسرة، المتوفون، الحسابات البنكية، حقول البوابة)
     */
    public function buildLinkedFilePayload(string $fileIdNumber): array
    {
        $candidates = $this->fileCandidates($fileIdNumber);

        if (empty($candidates)) {
            return [
                'file_id_number' => $this->normalizeFileNumber($fileIdNumber),
                'family_members' => [],
                'dead_people' => [],
                'additional_deceased' => [],
                'bank_accounts' => [],
                'portal_fields' => [],
                'portal_fields_count' => 0,
            ];
        }

        $familyMembers = DB::table('re_people')
            ->whereIn('registration_id', $candidates)
            ->get();

        $deadPeople = DB::table('dead_people')
            ->whereIn('re_file_id', $candidates)
            ->get();

        $bankAccounts = DB::table('guardian_bank_accounts')
            ->whereIn('guardian_registration', $candidates)
            ->get();

        $portalValues = DB::table('portal_general_registration_field_values')
            ->whereIn('file_id_number', $candidates)
            ->get();

        $additionalDeceased = Schema::hasTable('additional_deceased')
            ? DB::table('additional_deceased')->whereIn('re_file_id', $candidates)->get()
            : collect();

        return [
            'file_id_number' => $this->normalizeFileNumber($fileIdNumber),
            'family_members' => $familyMembers->map(fn ($member) => [
                'id' => (int) $member->id,
                'person_id' => (string) ($member->person_id ?? ''),
                'full_name' => trim(
                    ($member->first_name ?? '') . ' ' .
                    ($member->second_name ?? '') . ' ' .
                    ($member->third_name ?? '') . ' ' .
                    ($member->last_name ?? '')
                ),
                'first_name' => (string) ($member->first_name ?? ''),
                'second_name' => (string) ($member->second_name ?? ''),
                'third_name' => (string) ($member->third_name ?? ''),
                'last_name' => (string) ($member->last_name ?? ''),
                'birth_date' => (string) ($member->person_birth_date ?? ''),
                'gender' => (int) ($member->person_gender ?? 0),
                'notes' => (string) ($member->person_note ?? ''),
            ])->values()->all(),
            'dead_people' => $deadPeople->map(fn ($dead) => [
                'id' => (int) $dead->id,
                'father_id' => (string) ($dead->father_id ?? ''),
                'father_first_name' => (string) ($dead->father_first_name ?? ''),
                'father_second_name' => (string) ($dead->father_second_name ?? ''),
                'father_third_name' => (string) ($dead->father_third_name ?? ''),
                'father_last_name' => (string) ($dead->father_last_name ?? ''),
                'father_name' => trim(
                    ($dead->father_first_name ?? '') . ' ' .
                    ($dead->father_second_name ?? '') . ' ' .
                    ($dead->father_third_name ?? '') . ' ' .
                    ($dead->father_last_name ?? '')
                ),
                'father_death_date' => (string) ($dead->father_death_date ?? ''),
                'father_death_reason' => (string) ($dead->father_death_reason ?? ''),
                'mother_id' => (string) ($dead->mother_id ?? ''),
                'mother_first_name' => (string) ($dead->mother_first_name ?? ''),
                'mother_second_name' => (string) ($dead->mother_second_name ?? ''),
                'mother_third_name' => (string) ($dead->mother_third_name ?? ''),
                'mother_last_name' => (string) ($dead->mother_last_name ?? ''),
                'mother_name' => trim(
                    ($dead->mother_first_name ?? '') . ' ' .
                    ($dead->mother_second_name ?? '') . ' ' .
                    ($dead->mother_third_name ?? '') . ' ' .
                    ($dead->mother_last_name ?? '')
                ),
                'mother_death_date' => (string) ($dead->mother_death_date ?? ''),
                'mother_death_reason' => (string) ($dead->mother_death_reason ?? ''),
            ])->values()->all(),
            'additional_deceased' => $additionalDeceased->map(fn ($dead) => [
                'id' => (int) $dead->id,
                'person_id' => (string) ($dead->person_id ?? ''),
                'first_name' => (string) ($dead->first_name ?? ''),
                'second_name' => (string) ($dead->second_name ?? ''),
                'third_name' => (string) ($dead->third_name ?? ''),
                'last_name' => (string) ($dead->last_name ?? ''),
                'full_name' => trim(
                    ($dead->first_name ?? '') . ' ' .
                    ($dead->second_name ?? '') . ' ' .
                    ($dead->third_name ?? '') . ' ' .
                    ($dead->last_name ?? '')
                ),
                'relationship' => (string) ($dead->relationship ?? ''),
                'death_date' => (string) ($dead->death_date ?? ''),
                'death_reason' => (string) ($dead->death_reason ?? ''),
            ])->values()->all(),
            'bank_accounts' => $bankAccounts->map(fn ($account) => [
                'id' => (int) $account->id,
                'bank_name' => $account->bank_name,
                'account_owner_name' => (string) ($account->re_guardian_name ?? ''),
                'phone_number' => (string) ($account->re_phone_number ?? ''),
                're_id_number' => (string) ($account->re_id_number ?? ''),
                'iban_usd' => (string) ($account->iban_usd ?? ''),
                'iban_shekel' => (string) ($account->iban_shekel ?? ''),
                'person_owner_identity_number' => (string) ($account->person_owner_identity_number ?? ''),
                'check_account' => (int) ($account->check_account ?? 0),
            ])->values()->all(),
            'portal_fields' => $portalValues->pluck('field_value', 'field_key')->all(),
            'portal_fields_count' => $portalValues->count(),
        ];
    }

    /**
     * هل ينتمي الملف المصدر إلى معيل آخر غير الهوية المستهدفة؟
     * (حماية من سحب أفراد أسرة يخصون ملفاً مختلفاً)
     */
    private function sourceBelongsToAnotherGuardian(string $sourceFile, ?string $guardianIdentity): bool
    {
        $guardianIdentity = trim((string) $guardianIdentity);

        if ($guardianIdentity === '' || $sourceFile === '') {
            return false;
        }

        $candidates = $this->fileCandidates($sourceFile);

        return DB::table('data')
            ->whereIn('file_id_number', $candidates)
            ->whereNotNull('data_id_number')
            ->where('data_id_number', '!=', $guardianIdentity)
            ->exists();
    }

    /**
     * تطبيع رقم الملف إلى صيغة الستة أرقام (إن كان رقماً).
     */
    public function normalizeFileNumber(string $fileNumber): string
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '' || !ctype_digit($fileNumber)) {
            return $fileNumber;
        }

        return str_pad(ltrim($fileNumber, '0') === '' ? '0' : ltrim($fileNumber, '0'), 6, '0', STR_PAD_LEFT);
    }

    /**
     * صيغ بديلة لرقم الملف للمطابقة مع القيم المخزنة بأصفار وبلا أصفار.
     *
     * @return array<int, string>
     */
    public function fileCandidates(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '') {
            return [];
        }

        $candidates = [$fileNumber];

        if (ctype_digit($fileNumber)) {
            $unpadded = ltrim($fileNumber, '0');
            $unpadded = $unpadded === '' ? '0' : $unpadded;

            if (!in_array($unpadded, $candidates, true)) {
                $candidates[] = $unpadded;
            }

            $padded = str_pad($unpadded, 6, '0', STR_PAD_LEFT);
            if (!in_array($padded, $candidates, true)) {
                $candidates[] = $padded;
            }
        }

        return $candidates;
    }
}
