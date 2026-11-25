<?php

namespace App\Services;

use App\Models\Data;
use App\Models\MemberTransferRequest;
use App\Models\RePeople;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * منطق نقل فرد أسرة بين ملفي معيلين، في مكان واحد.
 *
 * نقل الفرد بين ملفي المعيلين ينقل الرابط فقط دون أعداد أو حقول إضافية.
 * تحويل المعيل إلى فرد أسرة ينقل أسرته ووفياته ثم يحذف ملف المصدر داخل معاملة واحدة.
 */
class FamilyMemberTransferService
{
    public function __construct(
        private readonly GuardianDuplicateMergeService $duplicateMergeService,
        private readonly GuardianFileService $guardianFileService,
    ) {}

    /**
     * رسالة واحدة يقارنها المتحكم لمعرفتها: الفرد لم يعد في ملف المصدر.
     * تُعلَّم الحالة failed خارج المعاملة، لأن أي كتابة داخلها تُلغى مع rollback.
     */
    public const MEMBER_GONE_MESSAGE = 'لم يعد هذا الفرد ضمن ملف المعيل المصدر، فلا يمكن إتمام النقل.';
    /**
     * إقفال صف الفرد داخل معاملة قائمة.
     */
    public function lockMember(int $memberId): ?RePeople
    {
        return RePeople::whereKey($memberId)->lockForUpdate()->first();
    }

    /**
     * إقفال فرد الأسرة بالهوية ورقم ملف المصدر.
     *
     * هذا هو المدخل الصحيح للموافقة على طلب نقل معلّق: الطلب يعرف هوية الفرد
     * وملف المصدر الذي انتُقل منه، لا المعرّف المحلي لصف re_people.
     */
    public function lockMemberByIdentityAndSourceFile(string $personId, $sourceFileIdNumber): ?RePeople
    {
        $normalizedSource = ltrim(trim((string) $sourceFileIdNumber), '0');

        $candidates = RePeople::where('person_id', $personId)->lockForUpdate()->get();

        foreach ($candidates as $candidate) {
            if (ltrim(trim((string) $candidate->registration_id), '0') === $normalizedSource) {
                return $candidate;
            }
        }

        return null;
    }

    public function lockGuardianByFileNumber($fileIdNumber): ?Data
    {
        return Data::where('file_id_number', (int) $fileIdNumber)->lockForUpdate()->first();
    }

    /**
     * كل ملفات المعيل المرتبطة بهذه الهوية، مقفلة.
     *
     * أكثر من نتيجة يعني أن الهوية معيلتان لملفين: النقل التلقائي حينها قرار
     * إداري يدوي لا ينفذه النظام.
     */
    public function lockGuardianTargetsByIdentity($identity): Collection
    {
        return Data::where('data_id_number', (int) $identity)->lockForUpdate()->get();
    }

    /**
     * نقل الرابط فعلياً: تحقق ثم تحديث registration_id فقط.
     *
     * @return array{member_id:int,person_id:mixed,from_file:string,to_file:string}
     */
    public function moveMemberToGuardian(RePeople $member, Data $target): array
    {
        if ((int) $target->file_id_number === (int) $member->registration_id) {
            throw new \RuntimeException('الفرد موجود بالفعل في هذا الملف');
        }

        $alreadyInTarget = RePeople::where('registration_id', $target->file_id_number)
            ->where('person_id', $member->person_id)
            ->where('id', '!=', $member->id)
            ->exists();

        if ($alreadyInTarget) {
            throw new \RuntimeException('هذا الفرد موجود بالفعل في ملف المعيل الهدف');
        }

        $fromFile = $this->formatFileNumber($member->registration_id);

        $member->registration_id = $target->file_id_number;
        $member->save();

        return [
            'member_id' => $member->id,
            'person_id' => $member->person_id,
            'from_file' => $fromFile,
            'to_file' => $this->formatFileNumber($target->file_id_number),
        ];
    }

    public function approveGuardianAsMember(MemberTransferRequest $request, ?int $reviewerId): array
    {
        return DB::transaction(function () use ($request, $reviewerId) {
            $locked = MemberTransferRequest::whereKey($request->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== MemberTransferRequest::STATUS_PENDING) {
                throw new \RuntimeException('هذا الطلب غير معلّق، لا يمكن الموافقة عليه.');
            }
            if (!$locked->isGuardianAsMember()) {
                throw new \RuntimeException('هذا الطلب ليس طلب تحويل معيل إلى فرد أسرة.');
            }

            $guardianQuery = Data::where('data_id_number', (string) $locked->identity_number);
            if ($locked->data_id) {
                $guardianQuery->orWhere('id', $locked->data_id);
            }
            $guardians = $guardianQuery->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $sources = $guardians->filter(fn (Data $guardian): bool => trim((string) $guardian->data_id_number)
                === trim((string) $locked->identity_number));
            $sourceDataId = (int) ($locked->member_snapshot['source_data_id'] ?? 0);
            $source = $guardians->get($sourceDataId);
            if ($sourceDataId <= 0 || $sources->count() !== 1 || !$source
                || (int) $source->file_id_number !== (int) $locked->source_file_id_number) {
                throw new \RuntimeException('تغيّر سجل المعيل الأصلي أو أصبحت هويته مكررة؛ لم يتم تنفيذ التحويل.');
            }

            $target = $locked->data_id ? $guardians->get((int) $locked->data_id) : null;
            if (!$target
                || $this->guardianFileService->normalizeFileNumber((string) $target->file_id_number)
                    !== $this->guardianFileService->normalizeFileNumber((string) $locked->file_id_number)) {
                throw new \RuntimeException('ملف المعيل الهدف غير موجود أو تغيّر منذ إنشاء الطلب؛ لم يتم تنفيذ التحويل.');
            }
            if ($this->guardianFileService->normalizeFileNumber((string) $source->file_id_number)
                === $this->guardianFileService->normalizeFileNumber((string) $target->file_id_number)) {
                throw new \RuntimeException('لا يمكن تحويل المعيل وحذف ملفه داخل الملف نفسه.');
            }

            $sourceCandidates = $this->guardianFileService->fileCandidates((string) $source->file_id_number);
            $targetCandidates = $this->guardianFileService->fileCandidates((string) $target->file_id_number);
            $normalizedSource = $this->guardianFileService->normalizeFileNumber((string) $source->file_id_number);
            $normalizedTarget = $this->guardianFileService->normalizeFileNumber((string) $target->file_id_number);
            $lockedMembers = RePeople::where('person_id', (string) $locked->identity_number)
                ->orWhereIn('registration_id', $sourceCandidates)
                ->orWhereIn('registration_id', $targetCandidates)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $existingMemberships = $lockedMembers->filter(fn (RePeople $member): bool => trim((string) $member->person_id)
                === trim((string) $locked->identity_number));
            if ($existingMemberships->isNotEmpty()) {
                throw new \RuntimeException('أصبح هذا الشخص مرتبطاً بملف أسرة؛ لم يتم إنشاء ارتباط مكرر.');
            }

            $sourceMembers = $lockedMembers->filter(fn (RePeople $member): bool =>
                $this->guardianFileService->normalizeFileNumber((string) $member->registration_id) === $normalizedSource
            );
            $targetMembers = $lockedMembers->filter(fn (RePeople $member): bool =>
                $this->guardianFileService->normalizeFileNumber((string) $member->registration_id) === $normalizedTarget
            );
            $targetIdentities = $targetMembers->pluck('person_id')
                ->push($locked->identity_number, $target->data_id_number)
                ->map(fn ($identity) => trim((string) $identity))
                ->filter()
                ->mapWithKeys(fn ($identity) => [$identity => true]);

            $seenIdentities = $targetIdentities;
            foreach ($sourceMembers as $sourceMember) {
                $identity = trim((string) $sourceMember->person_id);
                if ($identity !== '' && isset($seenIdentities[$identity])) {
                    throw new \RuntimeException('توجد هوية مكررة بين أفراد ملف المعيل الأصلي أو مع ملف الهدف؛ راجع تكرار الهويات قبل تنفيذ التحويل.');
                }
                if ($identity !== '') {
                    $seenIdentities[$identity] = true;
                }
            }

            $memberData = $locked->member_snapshot ?: [];
            unset($memberData['request_type'], $memberData['registration_id']);
            $memberData['registration_id'] = $target->file_id_number;
            $member = RePeople::create($memberData);

            foreach ($sourceMembers as $sourceMember) {
                $sourceMember->registration_id = $target->file_id_number;
                $sourceMember->save();
            }

            $deceasedCounts = $this->duplicateMergeService->mergeDeceasedRecords(
                (string) $source->file_id_number,
                (string) $target->file_id_number,
                true
            );

            $attachmentsMoved = 0;
            if (Schema::hasTable('attachments') && Schema::hasColumn('attachments', 'record_number')) {
                $update = ['record_number' => $normalizedTarget];
                if (Schema::hasColumn('attachments', 'updated_at')) {
                    $update['updated_at'] = now();
                }
                $attachmentsMoved = DB::table('attachments')
                    ->whereIn('record_number', $sourceCandidates)
                    ->update($update);
            }

            $enhancedAttachmentsMoved = 0;
            if (Schema::hasTable('enhanced_attachments')) {
                foreach (['record_number', 'folder_id', 'original_folder_name'] as $column) {
                    if (!Schema::hasColumn('enhanced_attachments', $column)) {
                        continue;
                    }
                    $update = [$column => $normalizedTarget];
                    if (Schema::hasColumn('enhanced_attachments', 'updated_at')) {
                        $update['updated_at'] = now();
                    }
                    $enhancedAttachmentsMoved += DB::table('enhanced_attachments')
                        ->whereIn($column, $sourceCandidates)
                        ->update($update);
                }
            }

            $source->delete();

            $locked->update([
                'status' => MemberTransferRequest::STATUS_APPROVED,
                'reason' => null,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);
            $remainingPending = MemberTransferRequest::pending()
                ->where('file_id_number', $locked->file_id_number)
                ->count();

            Log::info('✅ تحويل معيل وأسرته إلى ملف آخر وحذف ملف المصدر', [
                'transfer_request_id' => $locked->id,
                'member_id' => $member->id,
                'source_file' => $source->file_id_number,
                'target_file' => $target->file_id_number,
                'moved_members' => $sourceMembers->count(),
                'deceased' => $deceasedCounts,
                'attachments' => $attachmentsMoved,
                'enhanced_attachments' => $enhancedAttachmentsMoved,
                'user_id' => $reviewerId,
            ]);

            return [
                'member_id' => $member->id,
                'person_id' => $member->person_id,
                'from_file' => $this->formatFileNumber($source->file_id_number),
                'to_file' => $this->formatFileNumber($target->file_id_number),
                'guardian_name' => $this->guardianFullName($target),
                'remaining_pending' => $remainingPending,
            ];
        });
    }

    /**
     * مسار الموافقة على طلب نقل معلّق: تنفيذ النقل وتحديث حالة الطلب في معاملة واحدة.
     *
     * القفل بترتيب ثابت (صف الطلب ثم فرد المصدر ثم ملف الهدف) يمنع deadlock
     * ويضمن أن موافقتين متزامنتين على نفس الطلب تُنتج إحداهما فقط.
     *
     * @return array{member_id:int,person_id:mixed,from_file:string,to_file:string,guardian_name:string,remaining_pending:int}
     */
    public function approvePendingTransfer(MemberTransferRequest $request, ?int $reviewerId): array
    {
        return DB::transaction(function () use ($request, $reviewerId) {
            // قفل صف الطلب أولاً: موافقتان متزامنتان على نفس الطلب يجب أن تُنتج ناجية واحدة
            $locked = MemberTransferRequest::whereKey($request->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== MemberTransferRequest::STATUS_PENDING) {
                throw new \RuntimeException('هذا الطلب غير معلّق، لا يمكن الموافقة عليه.');
            }
            if ($locked->isGuardianPromotion() || $locked->isGuardianAsMember()) {
                throw new \RuntimeException('طلب التحويل يجب اعتماده عبر مساره الخاص.');
            }

            $member = $this->lockMemberByIdentityAndSourceFile(
                (string) $request->identity_number,
                $request->source_file_id_number
            );

            if (!$member) {
                throw new \RuntimeException(self::MEMBER_GONE_MESSAGE);
            }

            $target = $this->lockGuardianByFileNumber($request->file_id_number);

            if (!$target) {
                throw new \RuntimeException('ملف المعيل الهدف غير موجود في النظام.');
            }

            $moved = $this->moveMemberToGuardian($member, $target);

            $request->update([
                'status' => MemberTransferRequest::STATUS_APPROVED,
                'reason' => null,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            $remainingPending = MemberTransferRequest::pending()
                ->where('file_id_number', $locked->file_id_number)
                ->count();

            Log::info('✅ موافقة على نقل فرد أسرة من طابور الطلبات', [
                'transfer_request_id' => $request->id,
                'member_id' => $moved['member_id'],
                'person_id' => $moved['person_id'],
                'from_file' => $moved['from_file'],
                'to_file' => $moved['to_file'],
                'user_id' => $reviewerId,
            ]);

            return $moved + [
                'guardian_name' => $this->guardianFullName($target),
                'remaining_pending' => $remainingPending,
            ];
        });
    }

    public function guardianFullName(Data $guardian): string
    {
        return trim(implode(' ', array_filter([
            (string) ($guardian->data_first_name ?? ''),
            (string) ($guardian->data_father_name ?? ''),
            (string) ($guardian->data_grand_father_name ?? ''),
            (string) ($guardian->data_family_name ?? ''),
        ], static function ($value) {
            return $value !== '';
        })));
    }

    public function formatFileNumber($fileIdNumber): string
    {
        return str_pad((string) $fileIdNumber, 6, '0', STR_PAD_LEFT);
    }
}
