<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Data;
use App\Models\DocumentType;
use App\Models\MemberTransferRequest;
use App\Models\RePeople;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * الفحص التلقائي لطلبات البوابة قبل اعتمادها كـ «مقبول».
 *
 * سياسة العمل المعتمدة من مالك المشروع: الزر يفحص ثم يطلب تأكيداً ثم يعتمد،
 * وأي فحص يفشل يعني «لا يفعل شيئاً» — لا اعتماد جزئي ولا تجاوز صامت.
 * كل الفحوصات تُعيد الفشل عند أدنى شك (فشل الاتصال، أعمدة مفقودة، هوية مجهولة)،
 * لأن الاعتماد يعني أن الملف جاهز للصرف.
 */
class RegistrationAutoAcceptService
{
    /** الحد الأدنى لعمر المعيل، محسوباً من الأصغر بين تاريخ السجل المدني وتاريخ الطلب. */
    public const MIN_GUARDIAN_AGE = 18;

    public const ACCEPTED_DESCRIPTION = 'مقبول';

    /** أعمدة جدول document_types، تُقرأ مرة واحدة داخل العملية الواحدة. */
    private static ?array $documentTypeColumns = null;

    /**
     * تقييم الطلب: لا يكتب شيئاً، يُعيد تقريراً فقط.
     *
     * @return array{can_accept:bool,already_accepted:bool,accepted:bool,checks:array,missing_documents:array}
     */
    public function evaluate(Data $data): array
    {
        $report = [
            'can_accept' => false,
            'already_accepted' => false,
            'accepted' => false,
            'checks' => [],
            'missing_documents' => [],
        ];

        if ($this->currentStatusDescription($data) === self::ACCEPTED_DESCRIPTION) {
            $report['already_accepted'] = true;
            $report['checks'][] = $this->check('status', 'حالة الطلب', false, 'هذا الطلب معتمد («مقبول») بالفعل.');

            return $report;
        }

        $civil = $this->civilPersonFor($data);

        $report['checks'][] = $this->check(
            'identity',
            'رقم هوية المعيل في السجل المدني',
            $civil['found'],
            $civil['found']
                ? 'رقم «' . $civil['identity'] . '» مطابق لسجل موجود في السجل المدني.'
                : $civil['reason']
        );

        $report['checks'][] = $this->ageCheck($data, $civil);

        $documents = $this->documentsReport($data);
        $report['checks'][] = $documents['check'];
        $report['missing_documents'] = $documents['missing'];

        $report['checks'][] = $this->pendingTransfersCheck($data);

        $report['can_accept'] = collect($report['checks'])->every(fn ($check) => $check['passed'] === true);

        return $report;
    }

    /**
     * إعادة التقييم ثم الاعتماد بحقل واحد فقط.
     * لا تُنَفَّذ أي كتابة إذا فشل أي فحص — القرار المُسلَّم من المالك.
     */
    public function accept(Data $data): array
    {
        $report = $this->evaluate($data);

        if (!$report['can_accept']) {
            return $report;
        }

        $acceptedStatusId = $this->acceptedStatusId();

        if ($acceptedStatusId === null) {
            $report['can_accept'] = false;
            $report['checks'][] = $this->check(
                'status_row',
                'حالة «مقبول»',
                false,
                'لم يُعثر على صف «مقبول» في جدول حالات الطلب، فلا يمكن تعيين الحالة.'
            );

            return $report;
        }

        $data->data_request_status = $acceptedStatusId;
        $data->save();

        Log::info('✅ اعتماد تلقائي لطلب بوابة بعد فحص السجل المدني والوثائق', [
            'data_id' => (int) $data->id,
            'file_id_number' => $data->file_id_number,
            'status_id' => $acceptedStatusId,
        ]);

        $report['accepted'] = true;
        $report['status_id'] = $acceptedStatusId;

        return $report;
    }

    private function check(string $key, string $label, bool $passed, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'passed' => $passed, 'detail' => $detail];
    }

    private function currentStatusDescription(Data $data): ?string
    {
        $description = DB::table('request_status')
            ->where('id', (int) $data->data_request_status)
            ->value('description');

        return is_string($description) ? trim($description) : null;
    }

    private function acceptedStatusId(): ?int
    {
        $id = DB::table('request_status')
            ->where('description', self::ACCEPTED_DESCRIPTION)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * وجود رقم الهوية في السجل المدني فقط — لا مطابقة أسماء ولا فحص حياة، بقرار المالك.
     *
     * الاستعلام مباشر على الاتصال بدل CivilRegistryPerson::searchByIdNumber()،
     * لأن تلك الطريقة تمر عبر Scout وتُخزَّن نتائجها 300 ثانية، والزر يجب أن يقرأ الواقع الآن.
     *
     * @return array{found:bool,row:object|null,identity:string,reason:string}
     */
    private function civilPersonFor(Data $data): array
    {
        $identity = $this->digitsIdentity((string) $data->data_id_number);

        if ($identity === '') {
            return ['found' => false, 'row' => null, 'identity' => '', 'reason' => 'لا يوجد رقم هوية مسجّل لهذا المعيل.'];
        }

        try {
            $row = DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $identity)
                ->select('CI_ID_NUM', 'CI_BIRTH_DT')
                ->first();

            if ($row) {
                return ['found' => true, 'row' => $row, 'identity' => $identity, 'reason' => ''];
            }
        } catch (\Throwable $exception) {
            Log::warning('تعذّر الاتصال بالسجل المدني أثناء الفحص التلقائي: ' . $exception->getMessage());

            return [
                'found' => false,
                'row' => null,
                'identity' => $identity,
                'reason' => 'تعذّر الاتصال بقاعدة السجل المدني، لا يمكن الاعتماد تلقائياً.',
            ];
        }

        return [
            'found' => false,
            'row' => null,
            'identity' => $identity,
            'reason' => 'رقم الهوية ' . $identity . ' غير موجود في السجل المدني.',
        ];
    }

    /**
     * هوية رقمية صالحة: أرقام فقط بلا أصفار leading، والصفر وحده ليس هوية.
     */
    private function digitsIdentity(string $value): string
    {
        return ltrim((string) preg_replace('/\D/', '', $value), '0');
    }

    /**
     * العمر المعتمد هو الأصغر بين تاريخ السجل المدني وتاريخ الطلب، وكلاهما لازم.
     * لا يُستخدم CivilRegistryPerson::getAgeAttribute() لأنه يطرح السنوات فقط دون الأشهر.
     */
    private function ageCheck(Data $data, array $civil): array
    {
        $civilAge = $civil['row'] ? $this->ageInYears($civil['row']->CI_BIRTH_DT ?? null) : null;
        $submittedAge = $this->ageInYears($data->data_birth_date);

        if (!$civil['found']) {
            return $this->check('age', 'عمر المعيل فوق ' . self::MIN_GUARDIAN_AGE . ' سنة', false, 'لم يُتحقق العمر: لا سجل مدني مطابق.');
        }

        if ($civilAge === null) {
            return $this->check('age', 'عمر المعيل فوق ' . self::MIN_GUARDIAN_AGE . ' سنة', false, 'تاريخ الميلاد في السجل المدني غير موجود أو غير صالح.');
        }

        if ($submittedAge === null) {
            return $this->check('age', 'عمر المعيل فوق ' . self::MIN_GUARDIAN_AGE . ' سنة', false, 'تاريخ الميلاد في بيانات الطلب غير موجود أو غير صالح.');
        }

        $age = min($civilAge, $submittedAge);
        $detail = 'العمر من السجل المدني ' . $civilAge . ' سنة، ومن تاريخ الطلب ' . $submittedAge . ' سنة (المعتمد الأصغر: ' . $age . ').';

        return $this->check(
            'age',
            'عمر المعيل فوق ' . self::MIN_GUARDIAN_AGE . ' سنة',
            $age >= self::MIN_GUARDIAN_AGE,
            $detail
        );
    }

    /**
     * السن بالسنوات الكاملة (شهر ويوم معروفين)، ويرفض التواريخ غير الممكنة بدل التخمين.
     */
    private function ageInYears($value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
            return null;
        }

        if ((int) $matches[1] < 1900) {
            return null;
        }

        try {
            $birth = new \DateTimeImmutable($matches[1] . '-' . $matches[2] . '-' . $matches[3]);
            $today = new \DateTimeImmutable('today');
        } catch (\Throwable $exception) {
            return null;
        }

        if ($birth > $today) {
            return null;
        }

        $age = (int) $birth->diff($today)->y;

        return ($age >= 0 && $age <= 120) ? $age : null;
    }

    /**
     * الوثائق الإلزامية: basic_required للمعيل و family_required لأفراد أسرته.
     * «الوصي» ليس مجموعة مستقلة — المالك بيّنه أنه المعيل نفسه.
     *
     * @return array{check:array,missing:array}
     */
    private function documentsReport(Data $data): array
    {
        $label = 'الوثائق الإلزامية (المعيل والأفراد)';
        $columns = $this->documentTypeColumns();

        foreach (['pref', 'basic_required', 'family_required'] as $required) {
            if (!in_array($required, $columns, true)) {
                return [
                    'check' => $this->check('documents', $label, false, 'إعدادات الوثائق غير مكتملة في هذا الخادم (العمود ' . $required . ' مفقود)، لا يمكن الاعتماد تلقائياً.'),
                    'missing' => [],
                ];
            }
        }

        try {
            $documentTypes = DocumentType::query()
                ->get(['id', 'pref', 'description', 'basic_required', 'family_required']);
        } catch (\Throwable $exception) {
            Log::warning('تعذر جلب أنواع الوثائق للفحص التلقائي: ' . $exception->getMessage());

            return [
                'check' => $this->check('documents', $label, false, 'تعذّر قراءة إعدادات الوثائق: ' . $exception->getMessage()),
                'missing' => [],
            ];
        }

        $byPortal = ['basic' => [], 'family' => []];
        foreach ($documentTypes as $documentType) {
            if ((int) $documentType->basic_required === 1) {
                $byPortal['basic'][] = $documentType;
            }
            if ((int) $documentType->family_required === 1) {
                $byPortal['family'][] = $documentType;
            }
        }

        if (empty($byPortal['basic']) && empty($byPortal['family'])) {
            return [
                'check' => $this->check('documents', $label, true, 'لا وثيقة معلَّمة كإلزامية في الإعدادات، لذلك لا يوجد ما ينقص — راجع الإعدادات قبل الاعتماد المتكرر.'),
                'missing' => [],
            ];
        }

        $unverifiedMembers = [];
        $persons = $this->personGroups($data, $unverifiedMembers);
        $covered = $this->coverageMap($persons);
        $missing = [];

        foreach ($persons as $person) {
            foreach ($byPortal[$person['portal']] as $documentType) {
                if ($this->isCovered($covered, $person['identities'], $documentType)) {
                    continue;
                }

                $missing[] = [
                    'person' => $person['label'],
                    'document' => (string) ($documentType->description ?: $documentType->pref),
                ];
            }
        }

        if (!empty($unverifiedMembers)) {
            $names = implode('، ', array_map(fn ($member) => $member['label'], $unverifiedMembers));

            return [
                'check' => $this->check(
                    'documents',
                    $label,
                    false,
                    'لا يمكن إثبات وثائق ' . count($unverifiedMembers) . ' من الأفراد بلا رقم هوية: ' . $names
                ),
                'missing' => $missing,
            ];
        }

        if (empty($missing)) {
            return [
                'check' => $this->check('documents', $label, true, 'كل الوثائق الإلزامية مرفوعة لـ ' . count($persons) . ' شخص (المعيل وأفراد الأسرة).'),
                'missing' => [],
            ];
        }

        return [
            'check' => $this->check('documents', $label, false, 'ينقص ' . count($missing) . ' بنداً — التفاصيل في قائمة النواقص.'),
            'missing' => $missing,
        ];
    }

    /**
     * أشخاص الفحص: المعيل ثم أفراد أسرته في نفس الملف.
     *
     * كل شخص يحمل قائمة مفاتيح هوية (كما وردت، ثم أرقام فقط، ثم بلا أصفار leading)
     * لأن الرفع والخزن لا يتفقان دائماً على صيغة واحدة.
     */
    private function personGroups(Data $data, array &$unverifiedMembers): array
    {
        $persons = [];
        $unverifiedMembers = [];

        $guardianIdentity = trim((string) $data->data_id_number);
        $guardianName = $this->guardianName($data);

        $persons[] = [
            'label' => 'المعيل' . ($guardianName !== '' ? ': ' . $guardianName : ''),
            'portal' => 'basic',
            'identities' => $this->identityVariants($guardianIdentity),
        ];

        foreach ($this->familyMembers($data) as $member) {
            $memberIdentity = trim((string) $member->person_id);
            $memberName = $this->memberName($member);

            // فرد بلا رقم هوية لا يمكن إثبات وثائقه: المرفقات قد تكون مربوطة بمفتاح مؤقت
            // لا يُعاد بناؤه بشكل موثوق بعد الحفظ، فيُوقف الاعتماد بدل التخمين.
            if ($this->digitsIdentity($memberIdentity) === '') {
                $unverifiedMembers[] = [
                    'label' => $memberName !== '' ? $memberName : 'فرد بدون اسم',
                ];

                continue;
            }

            $persons[] = [
                'label' => 'فرد أسرة' . ($memberName !== '' ? ': ' . $memberName : ''),
                'portal' => 'family',
                'identities' => $this->identityVariants($memberIdentity),
            ];
        }

        return $persons;
    }

    /**
     * @return \Illuminate\Support\Collection<int, RePeople>
     */
    private function familyMembers(Data $data)
    {
        $file = trim((string) $data->file_id_number);

        if ($file === '') {
            return collect();
        }

        $candidates = [];
        foreach ([$file, ltrim($file, '0'), str_pad($file, 6, '0', STR_PAD_LEFT), (string) (int) $file] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $candidate !== '0' && !in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        try {
            return RePeople::whereIn('registration_id', $candidates)
                ->orderBy('id')
                ->get(['id', 'person_id', 'first_name', 'second_name', 'third_name', 'last_name']);
        } catch (\Throwable $exception) {
            Log::warning('تعذّر جلب أفراد الأسرة للفحص التلقائي: ' . $exception->getMessage());

            return collect();
        }
    }

    /**
     * خريطة التغطية: attachments.file_type يكتبه مسار البوابة باسم الوثيقة (pref)
     * ويكتبه مسار الكفالات بمعرّف الوثيقة، لذا تُقبل الصيغتين.
     */
    private function coverageMap(array $persons): array
    {
        $identities = [];
        foreach ($persons as $person) {
            foreach ($person['identities'] as $identity) {
                $identities[$identity] = true;
            }
        }

        $identities = array_keys($identities);

        if (empty($identities)) {
            return [];
        }

        $covered = [];

        try {
            Attachment::whereIn('person_identity_number', $identities)
                ->get(['person_identity_number', 'file_type'])
                ->each(function ($attachment) use (&$covered) {
                    $key = trim((string) $attachment->person_identity_number);
                    $type = strtolower(trim((string) $attachment->file_type));

                    if ($key === '' || $type === '') {
                        return;
                    }

                    $covered[$key][$type] = true;
                });
        } catch (\Throwable $exception) {
            Log::warning('تعذّر جلب المرفقات للفحص التلقائي: ' . $exception->getMessage());
        }

        return $covered;
    }

    private function isCovered(array $covered, array $identities, DocumentType $documentType): bool
    {
        $pref = strtolower(trim((string) $documentType->pref));
        $id = (string) (int) $documentType->id;

        foreach ($identities as $identity) {
            $types = $covered[$identity] ?? [];

            if ($pref !== '' && isset($types[$pref])) {
                return true;
            }

            if (isset($types[$id])) {
                return true;
            }
        }

        return false;
    }

    private function identityVariants(string $identity): array
    {
        $variants = [];
        $digits = $this->digitsIdentity($identity);

        if ($digits === '') {
            return [];
        }

        foreach ([$identity, $digits] as $variant) {
            $variant = trim((string) $variant);
            if ($variant !== '' && !in_array($variant, $variants, true)) {
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    private function guardianName(Data $data): string
    {
        return trim(implode(' ', array_filter([
            $data->data_first_name,
            $data->data_father_name,
            $data->data_grand_father_name,
            $data->data_family_name,
        ], fn ($part) => trim((string) $part) !== '')));
    }

    private function memberName($member): string
    {
        return trim(implode(' ', array_filter([
            $member->first_name,
            $member->second_name,
            $member->third_name,
            $member->last_name,
        ], fn ($part) => trim((string) $part) !== '')));
    }

    private function pendingTransfersCheck(Data $data): array
    {
        $label = 'أفراد معلّقون بانتظار موافقة النقل';

        if (!MemberTransferRequest::tableExists()) {
            return $this->check('transfers', $label, true, 'طابور نقل الأفراد غير مثبَّت في هذا الخادم، فلا طلبات معلّقة.');
        }

        $count = MemberTransferRequest::pending()
            ->where(function ($query) use ($data) {
                $query->where('data_id', (int) $data->id);
                if (!empty($data->file_id_number)) {
                    $query->orWhere('file_id_number', (int) $data->file_id_number);
                }
            })
            ->count();

        if ($count > 0) {
            return $this->check('transfers', $label, false, $count . ' فرد مرتبط بمعيل آخر وبانتظار موافقة النقل.');
        }

        return $this->check('transfers', $label, true, 'لا أفراد معلّقين بانتظار النقل.');
    }

    private function documentTypeColumns(): array
    {
        if (self::$documentTypeColumns === null) {
            try {
                self::$documentTypeColumns = array_map(
                    'strtolower',
                    Schema::getColumnListing('document_types')
                );
            } catch (\Throwable $exception) {
                Log::warning('تعذّر قراءة أعمدة document_types: ' . $exception->getMessage());
                self::$documentTypeColumns = [];
            }
        }

        return self::$documentTypeColumns;
    }
}
