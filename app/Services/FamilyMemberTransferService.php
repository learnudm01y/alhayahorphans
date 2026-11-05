<?php

namespace App\Services;

use App\Models\Data;
use App\Models\MemberTransferRequest;
use App\Models\RePeople;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * منطق نقل فرد أسرة بين ملفي معيلين، في مكان واحد.
 *
 * سياسة العمل المعتمدة من مالك المشروع: النقل ينقل الرابط (re_people.registration_id)
 * فقط، ولا يلمس أي حقل من حقول الفرد ولا أعداد المعيلين — لأن تلك الأرقام مصادرُها
 * مختلفة وتُحدَّث من مساراتها الخاصة.
 */
class FamilyMemberTransferService
{
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

    /**
     * مسار الموافقة على طلب نقل معلّق: تنفيذ النقل وتحديث حالة الطلب في معاملة واحدة.
     *
     * القفل بترتيب ثابت (صف الطلب ثم فرد المصدر ثم ملف الهدف) يمنع deadlock
     * ويضمن أن موافقتين متزامنتين على نفس الطلب تُنتج إحداهما فقط.
     *
     * @return array{member_id:int,person_id:mixed,from_file:string,to_file:string,guardian_name:string}
     */
    public function approvePendingTransfer(MemberTransferRequest $request, ?int $reviewerId): array
    {
        return DB::transaction(function () use ($request, $reviewerId) {
            // قفل صف الطلب أولاً: موافقتان متزامنتان على نفس الطلب يجب أن تُنتج ناجية واحدة
            $locked = MemberTransferRequest::whereKey($request->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== MemberTransferRequest::STATUS_PENDING) {
                throw new \RuntimeException('هذا الطلب غير معلّق، لا يمكن الموافقة عليه.');
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

            Log::info('✅ موافقة على نقل فرد أسرة من طابور الطلبات', [
                'transfer_request_id' => $request->id,
                'member_id' => $moved['member_id'],
                'person_id' => $moved['person_id'],
                'from_file' => $moved['from_file'],
                'to_file' => $moved['to_file'],
                'user_id' => $reviewerId,
            ]);

            return $moved + ['guardian_name' => $this->guardianFullName($target)];
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
