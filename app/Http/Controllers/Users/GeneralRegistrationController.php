<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\AcademicDegree;
use App\Models\Attachment;
use App\Models\BankName;
use App\Models\CategoryOfRelation;
use App\Models\CI_PERSONAL_CD;
use App\Models\City;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\AdditionalDeceased;
use App\Models\DeathReason;
use App\Models\DisplacementStatus;
use App\Models\DocumentType;
use App\Models\Employment;
use App\Models\GeneralCategory;
use App\Models\HealthStatus;
use App\Models\HousingStatus;
use App\Models\MaritalStatus;
use App\Models\MemberTransferRequest;
use App\Models\Province;
use App\Models\RePeople;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfAccommodation;
use App\Models\TypeOfGuarantee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\GuardianBankAccount;
use App\Services\BankAccountValidationService;
use App\Services\GuardianFileService;

class GeneralRegistrationController extends Controller
{
    /**
     * تحويل بيانات النموذج إلى مصفوفة بيانات المعيل
     */
    private function mapGuardianData(Request $request, string $fileIdNumber): array
    {
        return [
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
        ];
    }

    /**
     * (2.6) عند تحديث المعيل: إبقاء الحقول الغائبة عن الطلب كما هي بدل كتابة null.
     */
    private function filterPresentGuardianFields(Request $request, array $guardianData): array
    {
        $filtered = ['file_id_number' => $guardianData['file_id_number']];

        foreach ($guardianData as $key => $value) {
            if ($key === 'file_id_number') {
                continue;
            }

            if ($request->exists($key)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * سياسة العمل: هل هذا الشخص (معيل أو فرد أسرة) مرتبط حالياً بملف معيل آخر؟
     *
     * يفحص جدول data (معيلون) وجدول re_people (أفراد الأسرة) بالهوية ورقم الملف.
     *
     * @return array{type: string, file_id_number: string}|null نوع الارتباط ورقم الملف الآخر، أو null عند لا ارتباط
     */
    private function classifyIdentityLink(string $identity, string $fileIdNumber): ?array
    {
        $identity = trim($identity);

        if ($identity === '') {
            return null;
        }

        // (2.6) مقارنة أرقام الملفات بشكل موحّد (بلا أصفار بادئة)
        $normalizedFileId = ltrim(trim($fileIdNumber), '0');

        $linkedData = Data::where('data_id_number', $identity)->first();
        if ($linkedData && ltrim(trim((string) $linkedData->file_id_number), '0') !== $normalizedFileId) {
            return ['type' => 'guardian', 'file_id_number' => (string) $linkedData->file_id_number];
        }

        $linkedRePeople = RePeople::where('person_id', $identity)->get(['registration_id'])
            ->first(function ($row) use ($normalizedFileId) {
                return ltrim(trim((string) $row->registration_id), '0') !== $normalizedFileId;
            });
        if ($linkedRePeople) {
            return ['type' => 'member', 'file_id_number' => (string) $linkedRePeople->registration_id];
        }

        return null;
    }

    private function findOtherFileForIdentity(string $identity, string $fileIdNumber): ?string
    {
        $link = $this->classifyIdentityLink($identity, $fileIdNumber);

        return $link === null ? null : $link['file_id_number'];
    }

    /**
     * معرّف حالة الطلب التي تُبقي الملف في طابور المراجعة.
     * يُبحث بالوصف لا بالرقم: قيم request_status مرجعية وقد تختلف بين الاستضافات.
     */
    private function resolveReviewStatusId(): ?int
    {
        foreach (['مراجعة', 'قيد المراجعة'] as $description) {
            $statusId = \DB::table('request_status')->where('description', $description)->value('id');

            if ($statusId !== null) {
                return (int) $statusId;
            }
        }

        return null;
    }

    /**
     * إلغاء طلبات النقل المعلّقة التي لم يعد الفرد ضمنها مسجَّلاً في الطلب.
     *
     * تعمل فقط عند مصفوفة family_members مُرسلة فعلياً: غياب المصفوفة يعني
     * "لم تُرسل" لا "لا يوجد أفراد"، فلا نُلغي طلبات قائمة بسبب حقول ناقصة.
     */
    private function cancelStalePendingTransfers(Data $data, string $fileIdNumber, $familyMembers): void
    {
        if (!is_array($familyMembers) || !MemberTransferRequest::tableExists()) {
            return;
        }

        $stillRequested = [];
        foreach ($familyMembers as $member) {
            $identity = ltrim(trim((string) ($member['person_id'] ?? '')), '0');
            if ($identity !== '') {
                $stillRequested[$identity] = true;
            }
        }

        $stale = MemberTransferRequest::pending()
            ->where('file_id_number', $fileIdNumber)
            ->where('data_id', $data->id)
            ->get(['id', 'identity_number']);

        foreach ($stale as $request) {
            $identity = ltrim(trim((string) $request->identity_number), '0');
            if (isset($stillRequested[$identity])) {
                continue;
            }

            $request->update([
                'status' => MemberTransferRequest::STATUS_CANCELLED,
                'reason' => 'أُلغي بإعادة إرسال الطلب: لم يعد الفرد ضمن أفراد الأسرة.',
            ]);

            Log::info('🗑️ أُلغي طلب نقل فرد لم يعد ضمن الطلب', [
                'file_id_number' => $fileIdNumber,
                'transfer_request_id' => $request->id,
                'person_id' => $request->identity_number,
            ]);
        }
    }

    /**
     * سياسة العمل: هل رقم الملف محجوز أو مستخدم فعلاً في أحد الجداول الحاملة لرقم الملف؟
     *
     * يفحص data (معيلون) و re_people (أفراد الأسرة) و dead_people (المتوفون)
     * و sponsorships (الكفالة). يُستخدم داخل المعاملة قبل الكتابة، وعند وجود
     * تكرار يُولَّد رقم بديل فوراً بدل كتابة سجلات فوق ملف آخر.
     *
     * @return string|null اسم الجدول المتعارض مع الرقم، أو null إذا كان الرقم حراً
     */
    private function findFileNumberConflict(string $fileIdNumber): ?string
    {
        $fileIdNumber = trim($fileIdNumber);

        if ($fileIdNumber === '') {
            return null;
        }

        // (2.6) توحيد شكل رقم الملف: قد يكون مخزّناً بأصفار بادئة أو بدونها
        $fileIdVariants = array_values(array_unique(array_filter([
            $fileIdNumber,
            ltrim($fileIdNumber, '0'),
        ], static function ($value) {
            return $value !== '';
        })));

        if (Data::whereIn('file_id_number', $fileIdVariants)->exists()) {
            return 'data';
        }

        if (RePeople::whereIn('registration_id', $fileIdVariants)->exists()) {
            return 're_people';
        }

        if (DeadPepole::whereIn('re_file_id', $fileIdVariants)->exists()) {
            return 'dead_people';
        }

        if (\App\Models\Sponsorship::whereIn('internal_file_number', $fileIdVariants)->exists()
            || \App\Models\Sponsorship::whereIn('relation_id_number', $fileIdVariants)->exists()) {
            return 'sponsorships';
        }

        return null;
    }

    /**
     * (3.3) رقم الملف المطلوب للنموذج: يُحجز مرة واحدة لكل جلسة ويُعاد استخدامه
     * حتى الحفظ (أو 12 ساعة كحد أقصى) بدل حجز رقم جديد عند كل زيارة.
     */
    private function resolveReservedFileNumber(): string
    {
        $sessionCode = session('reserved_code');
        $reservedAt = (int) session('reserved_code_at', 0);

        if (is_string($sessionCode)
            && preg_match('/^\d{6}$/', $sessionCode)
            && $reservedAt > 0
            && (time() - $reservedAt) < 43200) {
            return $sessionCode;
        }

        try {
            $fileIdNumber = generateUniqueReservedCode('data', 'file_id_number', session()->getId());
        } catch (\Throwable $e) {
            Log::warning('generateUniqueReservedCode failed, using temp fallback', [
                'error' => $e->getMessage(),
            ]);
            $fileIdNumber = null;
        }

        if (!is_string($fileIdNumber) || $fileIdNumber === '') {
            // رقم مؤقت فريد لا يُحفظ في DB - المستخدم سيحصل على رقم حقيقي عند الحفظ
            return 'TEMP-' . strtoupper(substr(uniqid(), -6));
        }

        session([
            'reserved_code' => $fileIdNumber,
            'reserved_code_at' => time(),
        ]);

        return $fileIdNumber;
    }

    public function index(): View
    {
        // (3.3) لا نحجز رقماً جديداً عند كل زيارة — نعيد استخدام رقم الجلسة المحجوز
        $file_id_number = $this->resolveReservedFileNumber();

        // (3.3) كاش قوائم النموذج لمدة ساعة (يُبطَل تلقائياً عند تعديل الجداول)
        $lookups = Cache::remember('portal.lookups', 3600, static function () {
            return [
                'generalSection' => GeneralCategory::all(),
                'category_of_relationship' => CategoryOfRelation::all(),
                'ci_personal_cd' => CI_PERSONAL_CD::all(),
                'academic_qualification' => AcademicDegree::all(),
                'displacement_status' => DisplacementStatus::all(),
                'city' => City::all(),
                'province' => Province::all(),
                'health_status' => HealthStatus::all(),
                'employment_status_breadwinner' => Employment::all(),
                'HousingStatus' => HousingStatus::all(),
                'TypeOfAccommodation' => TypeOfAccommodation::all(),
                'documentTypes' => \App\Models\DocumentType::all(),
                'sponsorship_status' => SponsorshipStatus::all(),
                'guarantee_types' => TypeOfGuarantee::all(),
                'death_reasons' => DeathReason::all(),
                'bank_name' => BankName::all(),
            ];
        });

        $generalSection = $lookups['generalSection'];
        $category_of_relationship = $lookups['category_of_relationship'];
        $ci_personal_cd = $lookups['ci_personal_cd'];
        $academic_qualification = $lookups['academic_qualification'];
        $displacement_status = $lookups['displacement_status'];
        $city = $lookups['city'];
        $province = $lookups['province'];
        $health_status = $lookups['health_status'];
        $employment_status_breadwinner = $lookups['employment_status_breadwinner'];
        $HousingStatus = $lookups['HousingStatus'];
        $TypeOfAccommodation = $lookups['TypeOfAccommodation'];
        $documentTypes = $lookups['documentTypes'];
        $sponsorship_status = $lookups['sponsorship_status'];
        $guarantee_types = $lookups['guarantee_types'];
        $death_reasons = $lookups['death_reasons'];
        $bank_name = $lookups['bank_name'];
        
        // القيمة الافتراضية للضغط: مفعّل للتسجيل الرئيسي (لا توجد جمعية محددة بعد)
        $compressAttachments = true;
        
        return view(
            'user.generalRegistration.create',
            compact(
                'generalSection',
                'file_id_number',
                'category_of_relationship',
                'ci_personal_cd',
                'academic_qualification',
                'displacement_status',
                'city',
                'province',
                'health_status',
                'employment_status_breadwinner',
                'HousingStatus',
                'TypeOfAccommodation',
                'documentTypes',
                'sponsorship_status',
                'guarantee_types',
                'death_reasons',
                'bank_name',
                'compressAttachments'
            )
        );
    }

    /** مدة اعتبار الطلب «قيد المعالجة» (ثوانٍ) وبعدها يُسمح بإعادة المحاولة بنفس المعرّف */
    private const REQUEST_ID_PROCESSING_TTL = 600;
    /** مدة حفظ نتيجة الطلب الناجح لإعادتها عند تكرار الإرسال بنفس المعرّف */
    private const REQUEST_ID_DONE_TTL = 3600;

    /** تتبّع تقدّم الحفظ (يُقرأ عبر مسار save-progress بنفس معرّف الطلب) */
    private ?\App\Support\PortalSaveProgress $saveProgress = null;

    /**
     * نقطة الدخول لحفظ التسجيل العام.
     *
     * إن وصل معرّف طلب فريد (الترويسة X-Request-Id أو الحقل request_id) يُعالَج الطلب مرة واحدة فقط:
     *  - نفس المعرّف بعد نجاح الطلب الأول: تُعاد النتيجة السابقة دون إعادة معالجة (لا مرفقات مكررة).
     *  - نفس المعرّف والطلب الأول ما زال يعمل: 409 «قيد المعالجة».
     *  - بعد أي فشل يُحرَّر المعرّف فيُسمح بإعادة المحاولة.
     * بدون معرّف (مثل مسار واتساب الداخلي) يبقى السلوك القديم كما هو.
     */
    public function store(Request $request)
    {
        $requestId = $this->sanitizeRequestId($request->header('X-Request-Id') ?: $request->input('request_id'));

        if ($requestId === null) {
            return $this->storeInternal($request);
        }

        $key = 'public_reg_req:' . $requestId;
        $wantsJson = $request->ajax() || $request->wantsJson();

        if (!Cache::add($key, ['status' => 'processing', 'at' => time()], self::REQUEST_ID_PROCESSING_TTL)) {
            $existing = Cache::get($key);

            if (is_array($existing) && ($existing['status'] ?? null) === 'done') {
                Log::info('♻️ طلب تسجيل مكرر بنفس المعرّف: إعادة النتيجة السابقة دون معالجة', [
                    'request_id_prefix' => substr($requestId, 0, 8),
                ]);

                $redirect = (string) ($existing['redirect'] ?? route('user.thank.you.page'));
                if ($wantsJson) {
                    return response()->json([
                        'success' => true,
                        'duplicate' => true,
                        'redirect' => $redirect,
                        'warnings' => $existing['warnings'] ?? [],
                        'skipped_attachments' => $existing['warnings'] ?? [],
                        'pending_transfers_count' => $existing['pending_transfers_count'] ?? 0,
                    ]);
                }

                return redirect($redirect);
            }

            Log::info('⏳ طلب تسجيل مكرر بنفس المعرّف والطلب الأول قيد المعالجة', [
                'request_id_prefix' => substr($requestId, 0, 8),
            ]);

            $processingMessage = 'طلبك قيد الحفظ حالياً، يرجى الانتظار وعدم إعادة الإرسال.';
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'processing' => true,
                    'message' => $processingMessage,
                ], 409);
            }

            return redirect()->back()->withInput()->with('error', $processingMessage);
        }

        $this->saveProgress = class_exists(\App\Support\PortalSaveProgress::class)
            ? new \App\Support\PortalSaveProgress($requestId)
            : null;
        $this->progressUpdate(2, 'جاري استلام البيانات...');

        try {
            $response = $this->storeInternal($request);
        } catch (\Throwable $e) {
            Cache::forget($key);
            $this->progressFail('تعذّر إكمال الحفظ، يرجى المحاولة مرة أخرى.');
            throw $e;
        }

        $this->settleRequest($key, $response);

        return $response;
    }

    private function sanitizeRequestId($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^[A-Za-z0-9\-]{16,64}$/', $value) === 1 ? $value : null;
    }

    /** يحسم نتيجة الطلب: النجاح يُخزَّن لإعادته عند التكرار، والفشل يُحرّر المعرّف. */
    private function settleRequest(string $key, $response): void
    {
        $success = false;
        $redirect = null;
        $extra = [];
        $failureMessage = 'تعذّر إكمال الحفظ، يرجى المحاولة مرة أخرى.';

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);
            $success = $response->getStatusCode() < 400 && !empty($data['success']);
            $redirect = $data['redirect'] ?? null;
            $extra = [
                'warnings' => $data['warnings'] ?? [],
                'pending_transfers_count' => $data['pending_transfers_count'] ?? 0,
            ];
            if (!empty($data['message']) && is_string($data['message'])) {
                $failureMessage = $data['message'];
            }
        } elseif ($response instanceof \Illuminate\Http\RedirectResponse) {
            $thankYou = route('user.thank.you.page');
            $success = $response->getTargetUrl() === $thankYou;
            $redirect = $success ? $thankYou : null;
        }

        if ($success) {
            Cache::put($key, array_merge([
                'status' => 'done',
                'redirect' => $redirect,
            ], $extra), self::REQUEST_ID_DONE_TTL);
            $this->progressDone($redirect);
            return;
        }

        Cache::forget($key);
        $this->progressFail($failureMessage);
    }

    private function progressUpdate(int $percent, string $message): void
    {
        if ($this->saveProgress !== null) {
            $this->saveProgress->update($percent, $message);
        }
    }

    private function progressStartFiles(int $total): void
    {
        if ($this->saveProgress !== null && $total > 0) {
            $this->saveProgress->startFiles($total, 0);
        }
    }

    private function progressAttachmentStep(string $label): void
    {
        if ($this->saveProgress !== null) {
            $this->saveProgress->fileStart($label);
            $this->saveProgress->fileDone(0);
        }
    }

    private function progressDone(?string $redirect): void
    {
        if ($this->saveProgress !== null) {
            $this->saveProgress->done('تم حفظ الطلب بنجاح', ['redirect' => $redirect]);
        }
    }

    private function progressFail(string $message): void
    {
        if ($this->saveProgress !== null) {
            $this->saveProgress->fail($message);
        }
    }

    private function storeInternal(Request $request)
    {
        $maxRetries = 3;
        $retryCount = 0;
        // (2.3) الملفات المرحّلة حتى ما بعد commit (لا تُكتب ملفات نهائية داخل المعاملة)
        $pendingStores = [];

        // (3.1) قفل لكل هوية معيل: يمنع حفظين متزامنين لنفس الشخص
        $lockIdentity = trim((string) $request->input('data_id_number'));
        $storeLock = null;
        if ($lockIdentity !== '') {
            $storeLock = Cache::lock('guardian:' . sha1($lockIdentity), 30);
            try {
                $storeLock->block(5);
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException $lockTimeout) {
                Log::warning('⏳ تعذر الحصول على قفل الحفظ لهوية معيل (طلب متزامن)', [
                    'identity_masked' => $this->maskTail($lockIdentity, 3),
                ]);
                $lockMessage = 'يوجد طلب آخر قيد الحفظ لنفس الشخص. يرجى الانتظار قليلاً ثم إعادة المحاولة.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $lockMessage,
                    ], 409);
                }
                return redirect()->back()->withInput()->with('error', $lockMessage);
            }
        }

        try {
        while (true) {
            try {
                // 🔹 استبعاد بطاقات أفراد الأسرة الفارغة تماماً (بلا هوية ولا أسماء)
                //    مع الحفاظ على أرقامها الأصلية حتى لا تتغير مطابقة مرفقات family_{idx}
                $rawFamilyMembers = $request->input('family_members');
                if (is_array($rawFamilyMembers)) {
                    $rawFamilyMembers = array_filter($rawFamilyMembers, function ($member) {
                        if (!is_array($member)) {
                            return false;
                        }
                        return !empty($member['person_id'])
                            || !empty($member['first_name'])
                            || !empty($member['second_name'])
                            || !empty($member['third_name'])
                            || !empty($member['last_name']);
                    });
                    $request->merge(['family_members' => $rawFamilyMembers]);
                }

                // عدّادات ملخص التسجيل (تُسجَّل بعد commit للتأكد من دخول جميع الأقسام)
                $attachmentsSaved = 0;
                $userCreated = false;
                // (2.4) المرفقات التي لم تُحفظ مع سببها — تُعاد في warnings بدل إسقاطها بصمت
                $skippedAttachments = [];
                // ما غطّاه الطلب فعلياً من (هوية → نوع وثيقة) لفحص الوثائق الإلزامية
                $coveredAttachments = [];

                // 1. Validate basic data and attachments
                // صفحة التسجيل العامة (المسار store.generalRegistration) تفرض «صور فقط»،
                // بينما مسار واتساب الداخلي (Request مُصنَّع بلا Route) يبقى محتفظاً بدعم PDF.
                $imagesOnly = $request->route() !== null
                    && $request->route()->getName() === 'store.generalRegistration';

                $documentFileMimes = $imagesOnly
                    ? 'jpg,jpeg,png,webp,gif,bmp,avif,tif,tiff,ico,jfif,heic,heif'
                    : 'jpg,jpeg,png,pdf,heic';

                $attachmentMimetypes = 'image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/x-bmp,image/x-ms-bmp,image/avif,image/heic,image/heif,image/tiff,image/x-icon,image/vnd.microsoft.icon,application/octet-stream'
                    . ($imagesOnly ? '' : ',application/pdf');

                $request->validate([
                'file_id_number' => 'required|string|regex:/^[0-9]{6}$/',
                'original_file_id_number' => 'nullable|string|regex:/^[0-9]{6}$/',
                'data_section_id' => 'required|integer',
                'data_id_number' => 'required|string|digits:9',
                'data_first_name' => 'required|string|max:255',
                'data_father_name' => 'nullable|string|max:255',
                'data_grand_father_name' => 'nullable|string|max:255',
                'data_family_name' => 'nullable|string|max:255',
                'data_relationship' => 'nullable|string|max:100',
                'data_birth_date' => 'nullable|date',
                'data_gender' => 'nullable|in:1,2',
                'data_phone_number' => 'nullable|string|max:15',
                'data_alt_phone_number' => 'nullable|string|max:15',
                'data_number_of_individuals' => 'nullable|integer',
                'data_marital_status' => 'nullable|string',
                'data_academic_qualification' => 'nullable|string',
                'data_displacement_status' => 'nullable|string',
                'data_address_before_displacement' => 'nullable|string',
                'data_current_address' => 'nullable|string',
                'data_city' => 'nullable|string',
                'data_province' => 'required|integer|exists:provinces,id',
                'data_health_status' => 'nullable|string',
                'data_description_needs' => 'nullable|string',
                'data_number_mail' => 'nullable|integer',
                'data_number_female' => 'nullable|integer',
                'data_number_of_individuals_with_chronic_diseases' => 'nullable|integer',
                'data_number_of_people_with_special_needs' => 'nullable|integer',
                'data_employment_status_breadwinner' => 'nullable|string',
                'data_housing_status' => 'nullable|string',
                'data_current_housing_type' => 'nullable|string',
                'data_request_status' => 'nullable|integer',
                // Mother fields (living - baseTap)
                'mother_id' => 'nullable|string|digits:9',
                'mother_first_name' => 'nullable|string|max:255',
                'mother_second_name' => 'nullable|string|max:255',
                'mother_third_name' => 'nullable|string|max:255',
                'mother_last_name' => 'nullable|string|max:255',
                'mother_is_alive' => 'nullable|in:0,1',
                // Mother fields (deceased - deceased tab)
                'deceased_mother_id' => 'nullable|string|digits:9',
                'deceased_mother_first_name' => 'nullable|string|max:255',
                'deceased_mother_second_name' => 'nullable|string|max:255',
                'deceased_mother_third_name' => 'nullable|string|max:255',
                'deceased_mother_last_name' => 'nullable|string|max:255',
                // Family members (required — at least one)
                'family_members' => 'required|array|min:1',
                // Attachments
                'document_file.*' => 'required|file|mimes:' . $documentFileMimes . '|max:5120',
                // 🛡️ (2.2) مرفقات البوابة: قائمة سماح للامتداد + حد للحجم + مسار مؤقت مُقيَّد
                'attachments' => 'nullable|array',
                // mimetypes (نوع المحتوى) بدل mimes (الامتداد).
                // application/octet-stream مسموح فقط لصور HEIC التي يعجز finfo عن كشفها،
                // ويُرفض لاحقاً في resolveAttachmentExtension إن لم تكن صورة HEIC حقيقية.
                'attachments.*.file' => 'nullable|file|max:5120|mimetypes:' . $attachmentMimetypes,
                // مصفوفة لا نص: فاصل القواعد "|" موجود داخل النمط نفسه
                'attachments.*.temp_path' => ['nullable', 'string', 'max:255', 'regex:' . self::TEMP_ATTACHMENT_PATH_PATTERN],
                'attachments.*.stored_file_name' => 'nullable|string|max:255',
                'attachments.*.file_type' => 'nullable|string|max:64',
                'attachments.*.person_identity_number' => 'nullable|string|max:32',
                'attachments.*.file_id_number' => 'nullable|string|max:16',
            ], [
                'file_id_number.required' => 'رقم الملف الموحد مطلوب.',
                'file_id_number.regex' => 'رقم الملف يجب أن يتكون من 6 أرقام فقط (لا يقبل TEMP- أو أي رمز آخر). يرجى إعادة تحميل الصفحة ثم المحاولة مرة أخرى.',
                'original_file_id_number.regex' => 'رقم الملف الأصلي يجب أن يتكون من 6 أرقام فقط. يرجى إعادة تحميل الصفحة ثم المحاولة مرة أخرى.',
                'data_id_number.required' => 'رقم الهوية مطلوب.',
                'data_id_number.digits' => 'رقم الهوية يجب أن يكون 9 أرقام بالضبط.',
                'mother_id.digits' => 'رقم هوية الأم يجب أن يكون 9 أرقام بالضبط.',
                'deceased_mother_id.digits' => 'رقم هوية الأم المتوفية يجب أن يكون 9 أرقام بالضبط.',
                'document_file.*.mimes' => $imagesOnly
                    ? 'يجب أن تكون صيغة الملف صورة فقط (jpg، jpeg، png، webp، gif، bmp، avif، tiff، heic).'
                    : 'يجب أن تكون صيغة الملف jpg أو jpeg أو png أو pdf أو heic.',
                'document_file.*.max' => 'حجم الملف لا يجوز أن يتجاوز 5 ميغابايت.',
                'attachments.*.file.mimetypes' => $imagesOnly
                    ? 'صيغة المرفق غير مدعومة. تُقبل الصور فقط وتُحوَّل تلقائياً إلى JPG، ولا تُقبل ملفات PDF أو الفيديو أو المستندات.'
                    : 'صيغة المرفق غير مدعومة. الصور تُقبل بكل صيغها وتُحوَّل تلقائياً إلى JPG، كما يُقبل PDF.',
                'attachments.*.file.max' => 'حجم المرفق لا يجوز أن يتجاوز 5 ميغابايت.',
                'attachments.*.temp_path.regex' => 'مسار الرفع المؤقت غير صالح.',
                'family_members.required' => 'يجب إضافة فرد أسرة واحد على الأقل قبل الحفظ.',
                'family_members.min' => 'يجب إضافة فرد أسرة واحد على الأقل قبل الحفظ.',
            ]);


            // استخدم رقم الملف مع الأصفار البادئة دائماً
            $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);

            $guardianIdentity = $request->input('data_id_number');
            $useExistingGuardian = false;
            $existingGuardianData = null;
            $existingFileIdNumber = null;
            // رقم الملف الذي وُلِّد لهذه الصفحة قبل أي استبدال
            // (يُستخدم لإعادة ربط السجلات التابعة له بملف المعيل الموجود)
            $originalPageFileId = $request->input('original_file_id_number');
            $requestedFileIdNumber = $originalPageFileId !== null && $originalPageFileId !== ''
                ? str_pad($originalPageFileId, 6, '0', STR_PAD_LEFT)
                : $fileIdNumber;

            // 1️⃣ أولاً: البحث عن معيل موجود في قاعدة البيانات (أحدث سجل عند تكرار الهوية)
            if (!empty($guardianIdentity)) {
                $existingGuardianData = app(GuardianFileService::class)->findLatestDataByIdentity((string) $guardianIdentity);

                if ($existingGuardianData) {
                    $useExistingGuardian = true;
                    $existingFileIdNumber = $existingGuardianData->file_id_number;
                    $fileIdNumber = $existingFileIdNumber; // استخدام رقم الملف الموجود
                    Log::info('🔗 تم العثور على معيل موجود', [
                        'file_id' => $existingFileIdNumber,
                        'identity_masked' => $this->maskTail((string) $guardianIdentity, 3),
                    ]);
                }
            }

            // لم يُعثر على ملف للمعيل: استخدام رقم الملف المولَّد لهذه الصفحة
            // (يُلغي أي تعديل قد تسببت به الواجهة عند وجود ملف سابق)
            if (!$useExistingGuardian && $requestedFileIdNumber !== $fileIdNumber) {
                $fileIdNumber = $requestedFileIdNumber;
            }

            // 2️⃣ ثانياً: التحقق من عدم ارتباط المعيل بمعيل آخر
            if (!$useExistingGuardian && !empty($guardianIdentity)) {
                if ($this->findOtherFileForIdentity((string) $guardianIdentity, $fileIdNumber) !== null) {
                    $errorMsg = 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $errorMsg], 422);
                    }
                    return redirect()->back()->withInput()->with('error', $errorMsg);
                }
            }

            // تحقق من تكرار رقم الملف العام
            // سياسة العمل: عند وجود بيانات قديمة لنفس رقم الملف يتم تحديثها فقط دون حذف أي سجل
            //              (بدلاً من رفض الحفظ برسالة خطأ)
            if (!$useExistingGuardian && \App\Models\Data::where('file_id_number', $fileIdNumber)->exists()) {
                $conflictingFileData = \App\Models\Data::where('file_id_number', $fileIdNumber)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first();

                if ($conflictingFileData) {
                    $useExistingGuardian = true;
                    $existingGuardianData = $conflictingFileData;
                    $existingFileIdNumber = $conflictingFileData->file_id_number;

                    Log::info('ℹ️ رقم الملف موجود مسبقاً - سيتم تحديث السجل الموجود بدلاً من إنشاء سجل جديد', [
                        'file_id_number' => $fileIdNumber,
                        'existing_identity_masked' => $this->maskTail((string) $conflictingFileData->data_id_number, 3),
                        'submitted_identity_masked' => $this->maskTail((string) $guardianIdentity, 3),
                    ]);
                }
            }

            // 1️⃣ التحقق من أفراد الأسرة قبل فتح المعاملة
            //    (أي فشل هنا يعيد الرد قبل أي كتابة في القاعدة، فلا تبقى معاملة مفتوحة)
            $familyMembers = $request->input('family_members');

            if (is_array($familyMembers) && count($familyMembers) > 0) {
                // منع تكرار نفس رقم الهوية داخل الطلب الواحد
                $seenFamilyIdentities = [];

                // التحقق من صحة أرقام الهوية لأفراد الأسرة ومنع التكرار
                foreach ($familyMembers as $index => $member) {
                    if (!empty($member['person_id'])) {
                        // التحقق من أن رقم الهوية 9 أرقام
                        if (!preg_match('/^\d{9}$/', $member['person_id'])) {
                            if ($request->ajax() || $request->wantsJson()) {
                                return response()->json([
                                    'success' => false,
                                    'errors' => [
                                        "family_members.{$index}.person_id" => [
                                            "رقم هوية فرد الأسرة رقم " . ($index + 1) . " يجب أن يكون 9 أرقام بالضبط"
                                        ]
                                    ]
                                ], 422);
                            }
                            return redirect()->back()
                                ->withInput()
                                ->with('error', "رقم هوية فرد الأسرة رقم " . ($index + 1) . " يجب أن يكون 9 أرقام بالضبط");
                        }

                        // 🆕 منع تكرار نفس الرقم داخل الطلب الواحد (وإلا ضاع فرد بصمت)
                        $memberIdentityKey = (string) $member['person_id'];
                        if (isset($seenFamilyIdentities[$memberIdentityKey])) {
                            $errorMsg = "الشخص برقم هوية " . $memberIdentityKey . " مُدخل أكثر من مرة في أفراد الأسرة. يرجى تصحيح التكرار قبل الحفظ.";
                            if ($request->ajax() || $request->wantsJson()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => $errorMsg
                                ], 422);
                            }
                            return redirect()->back()->withInput()->with('error', $errorMsg);
                        }
                        $seenFamilyIdentities[$memberIdentityKey] = true;

                        // 🆕 الشخص مرتبط بملف معيل آخر:
                        //    - معيل لملف آخر: يبقى رفضاً، فضمّه فرداً يعني تجريده من ملفه (قرار إداري)
                        //    - فرد أسرة في ملف آخر: الطلب يُقبل والنقل يُعلَّق حتى موافقة الإدارة (يُسجَّل داخل المعاملة)
                        $memberLink = $this->classifyIdentityLink($memberIdentityKey, $fileIdNumber);

                        if ($memberLink !== null && $memberLink['type'] === 'guardian') {
                            $errorMsg = "الشخص برقم هوية " . $memberIdentityKey . " مسجل كمعيل لملف آخر (رقم ملف " . $memberLink['file_id_number'] . "). لا يمكن ضمه كفرد أسرة. يرجى التواصل مع إدارة المؤسسة.";
                            if ($request->ajax() || $request->wantsJson()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => $errorMsg
                                ], 422);
                            }
                            return redirect()->back()->withInput()->with('error', $errorMsg);
                        }
                    }
                }
            }

            DB::beginTransaction();
            $this->progressUpdate(10, 'جاري إنشاء وحفظ بيانات الاستمارة...');

            // ================================================
            // 🔄 إعادة فحص تكرار رقم الملف نفسه داخل المعاملة
            //    قد كتب طلب متزامن هذا الرقم في data / re_people /
            //    dead_people / sponsorships بين الفحص السابق والكتابة.
            //    عند التكرار يُولَّد رقم بديل فوراً ويُستبدل في كل ما بعده
            //    (سجل المعيل، الأفراد، المتوفون، البنك، المرفقات، الملخص).
            // ================================================
            if (!$useExistingGuardian) {
                $fileNumberConflictTable = $this->findFileNumberConflict($fileIdNumber);

                if ($fileNumberConflictTable !== null) {
                    $regeneratedFileIdNumber = generateUniqueReservedCode('data', 'file_id_number');

                    if (empty($regeneratedFileIdNumber) || $regeneratedFileIdNumber === $fileIdNumber) {
                        DB::rollBack();
                        $errorMsg = 'تعذر حفظ الطلب: رقم الملف مستخدم في سجل آخر ولم ينجح توليد رقم بديل. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.';
                        if ($request->ajax() || $request->wantsJson()) {
                            return response()->json(['success' => false, 'message' => $errorMsg], 422);
                        }
                        return redirect()->back()->withInput()->with('error', $errorMsg);
                    }

                    Log::warning('♻️ رقم ملف مكرر داخل المعاملة - تم توليد رقم بديل', [
                        'old_file_id' => $fileIdNumber,
                        'new_file_id' => $regeneratedFileIdNumber,
                        'conflict_table' => $fileNumberConflictTable,
                        'guardian_identity_masked' => $this->maskTail((string) $guardianIdentity, 3),
                    ]);

                    $fileIdNumber = $regeneratedFileIdNumber;
                    $requestedFileIdNumber = $regeneratedFileIdNumber;
                    $originalPageFileId = $regeneratedFileIdNumber;
                }
            }

            // ================================================
            // 🔒 إعادة فحص الارتباط بملف آخر داخل المعاملة
            //    (يُغلق سباق التزامن: قد يُدخل طلب آخر نفس الشخص بين الفحص السابق والكتابة)
            //    سياسة العمل:
            //      - معيل مرتبط بملف آخر (المعيل المتقدم أو فرد مُدخل وهو ولي أمر ملف) ⇒ إيقاف العملية دون كتابة
            //      - فرد أسرة مرتبط بملف آخر ⇒ الطلب يُقبل، ويُصف الفرد في طابور نقل معلّق حتى موافقة الإدارة
            // ================================================
            $inTransactionConflictMsg = null;
            $pendingTransfers = [];

            if (!$useExistingGuardian && !empty($guardianIdentity)) {
                if ($this->findOtherFileForIdentity((string) $guardianIdentity, $fileIdNumber) !== null) {
                    $inTransactionConflictMsg = 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.';
                }
            }

            if ($inTransactionConflictMsg === null && is_array($familyMembers)) {
                foreach ($familyMembers as $member) {
                    $memberIdentity = trim((string) ($member['person_id'] ?? ''));
                    if ($memberIdentity === '') {
                        continue;
                    }

                    $memberLink = $this->classifyIdentityLink($memberIdentity, $fileIdNumber);

                    if ($memberLink === null) {
                        continue;
                    }

                    if ($memberLink['type'] === 'guardian') {
                        $inTransactionConflictMsg = "الشخص برقم هوية " . $memberIdentity . " مسجل كمعيل لملف آخر (رقم ملف " . $memberLink['file_id_number'] . "). لا يمكن ضمه كفرد أسرة. يرجى التواصل مع إدارة المؤسسة.";
                        break;
                    }

                    // جدول الطابور غير مثبَّت: لا نكتب طلباً ولا نسقط الفرد بصمت، بل نعود للسلوك القديم
                    if (!MemberTransferRequest::tableExists()) {
                        $inTransactionConflictMsg = "الشخص برقم هوية " . $memberIdentity . " مرتبط بمعيل آخر (رقم ملف " . $memberLink['file_id_number'] . "). لا يمكن ضمّه الآن. يرجى التواصل مع إدارة المؤسسة.";
                        break;
                    }

                    $pendingTransfers[$memberIdentity] = [
                        'source_file_id_number' => (int) $memberLink['file_id_number'],
                        'member' => $member,
                    ];
                }
            }

            if ($inTransactionConflictMsg !== null) {
                DB::rollBack();
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $inTransactionConflictMsg], 422);
                }
                return redirect()->back()->withInput()->with('error', $inTransactionConflictMsg);
            }

            if (!empty($pendingTransfers)) {
                Log::info('🕒 أفراد مرتبطون بمعيل آخر: الطلب أُقبل والنقل معلّق بانتظار موافقة الإدارة', [
                    'file_id_number' => $fileIdNumber,
                    'pending_count' => count($pendingTransfers),
                    'pending_identities' => array_keys($pendingTransfers),
                ]);
            }

            // ================================================
            // 🆕 عند العثور على معيل له ملف في قاعدة البيانات:
            // إعادة ربط كل السجلات التابعة للملف المطلوب برقم ملف
            // المعيل الموجود (الأفراد، المتوفون، الكفالات، البنك، البوابة)
            // ================================================
            if ($useExistingGuardian && $existingFileIdNumber !== null) {
                $requestedNorm = ltrim((string) $requestedFileIdNumber, '0');
                $existingNorm = ltrim((string) $existingFileIdNumber, '0');

                if ($requestedNorm !== $existingNorm) {
                    $relinkCounts = app(GuardianFileService::class)->relinkFile(
                        (string) $requestedFileIdNumber,
                        (string) $existingFileIdNumber,
                        null,
                        (string) $guardianIdentity
                    );

                    Log::info('🔗 تم إعادة ربط سجلات الملف المطلوب بملف المعيل الموجود', [
                        'requested_file' => $requestedFileIdNumber,
                        'existing_file' => $existingFileIdNumber,
                        'guardian_identity_masked' => $this->maskTail((string) $guardianIdentity, 3),
                        'counts' => $relinkCounts,
                    ]);
                }
            }

            // 2. Store main Data record أو استخدام المعيل الموجود
            $data = null;

            // وجود أفراد معلّقين ⇒ الملف في «مراجعة» حتى تُحسم طلبات النقل من لوحة الإدارة
            $reviewStatusId = empty($pendingTransfers) ? null : $this->resolveReviewStatusId();

            if (!empty($pendingTransfers) && $reviewStatusId === null) {
                Log::warning('⚠️ لم تُعثر حالة مراجعة في جدول request_status — بقيت حالة الطلب كما هي مع وجود أفراد معلّقين', [
                    'file_id_number' => $fileIdNumber,
                    'pending_count' => count($pendingTransfers),
                ]);
            }

            if ($useExistingGuardian && $existingGuardianData) {
                $guardianData = $this->mapGuardianData($request, $fileIdNumber);
                // (2.6) لا نكتب null لحقل غاب من الطلب؛ المسح الصريح (حقل مُرسَل فارغاً) يبقى مسموحاً
                $guardianData = $this->filterPresentGuardianFields($request, $guardianData);
                $guardianData['data_request_status'] = $reviewStatusId ?? 2; // تحديث طلب موجود
                $existingGuardianData->update($guardianData);
                $data = $existingGuardianData;
                $fileIdNumber = $existingFileIdNumber;

                Log::info('✅ تم تحديث بيانات المعيل', [
                    'file_id_number' => $fileIdNumber,
                    'data_request_status' => $guardianData['data_request_status'],
                ]);
            } else {
                $guardianData = $this->mapGuardianData($request, $fileIdNumber);
                $guardianData['data_user_insert_data'] = "N_user";
                $guardianData['data_request_status'] = $reviewStatusId ?? 1;
                $data = Data::create($guardianData);

                // وضع علامة على الرقم كمستخدم في جدول reserved_codes
                markCodeAsUsed($fileIdNumber);
                Log::info('✅ تم إنشاء سجل معيل جديد: ' . $fileIdNumber);
            } // نهاية else (إنشاء سجل جديد)

            // ⚠️ حلقة المرفقات تعيد ربط $data بمصفوفة المرفق، لذا يُحفظ معرّف السجل هنا
            $registrationDataId = (int) $data->id;

            // إضافة بيانات الحساب البنكي إذا وُجدت أي قيمة بنكية مع التحقق من التكرار
            $bankAccounts = $request->input('bank_accounts', []);
            $bankValidationService = app(BankAccountValidationService::class);
            $duplicateBankErrors = [];

            Log::info('🟢 الحسابات البنكية المستلمة من الواجهة', [
                'count' => is_array($bankAccounts) ? count($bankAccounts) : 0,
            ]);
            if (is_array($bankAccounts) && count($bankAccounts) > 0) {
                // جلب كل الحسابات البنكية الموجودة مسبقاً دفعة واحدة (تحسين الأداء)
                $existingAccounts = GuardianBankAccount::where('guardian_registration', $fileIdNumber)->get();
                $existingAccountsCount = $existingAccounts->count();
                $existingAccountsByIdentity = $existingAccounts->keyBy('person_owner_identity_number');
                $accountsCreatedInRequest = 0;

                foreach ($bankAccounts as $index => $bankAccount) {
                    Log::info('🔵 معالجة حساب بنكي', [
                        'index' => $index,
                        'has_usd_iban' => !empty($bankAccount['iban_usd']),
                        'has_shekel_iban' => !empty($bankAccount['iban_shekel']),
                    ]);
                    // تأكد من استقبال وتخزين person_owner_identity_number
                    $reIdNumber = $bankAccount['person_owner_identity_number'] ?? null;
                    if (
                        (!empty($bankAccount['bank_name'])) ||
                        (!empty($bankAccount['iban_usd'])) ||
                        (!empty($bankAccount['iban_shekel'])) ||
                        (!empty($bankAccount['re_guardian_name'])) ||
                        (!empty($bankAccount['re_phone_number'])) ||
                        (!empty($reIdNumber))
                    ) {
                        // (2.6) الحساب الجاري تحديثه مستثنى من فحص التكرار
                        $existingBankAccount = null;
                        if (!empty($reIdNumber)) {
                            $existingBankAccount = $existingAccountsByIdentity->get($reIdNumber);
                        }

                        // (2.6) التكرار يُقارن بالـ IBAN ونوعه (دولار/شيكل) بدل هوية المالك
                        $incomingUsdIban = trim((string) ($bankAccount['iban_usd'] ?? ''));
                        $incomingShekelIban = trim((string) ($bankAccount['iban_shekel'] ?? ''));

                        $isDuplicate = false;
                        foreach ($existingAccounts as $existingAccount) {
                            if ($existingBankAccount && (int) $existingAccount->id === (int) $existingBankAccount->id) {
                                continue;
                            }

                            $sameUsdIban = $incomingUsdIban !== ''
                                && trim((string) $existingAccount->iban_usd) === $incomingUsdIban;
                            $sameShekelIban = $incomingShekelIban !== ''
                                && trim((string) $existingAccount->iban_shekel) === $incomingShekelIban;

                            if ($sameUsdIban || $sameShekelIban) {
                                $isDuplicate = true;
                                $duplicateBankErrors[] = [
                                    'index' => $index + 1,
                                    'message' => 'الحساب البنكي مكرر - نفس الـ IBAN مسجّل مسبقاً في هذا الملف.',
                                ];
                                Log::warning('⚠️ محاولة إضافة حساب بنكي مكرر في التسجيل العام', [
                                    'index' => $index,
                                    'existing_account' => $existingAccount->id,
                                    'iban_type' => $sameUsdIban ? 'usd' : 'shekel',
                                ]);
                                break;
                            }
                        }

                        if ($isDuplicate) {
                            continue; // تجاوز هذا الحساب المكرر
                        }

                        // (2.6) أول حساب في الملف فقط يأخذ check_account=1
                        //       مع احتساب ما أُضيف في هذا الطلب نفسه
                        $isFirstAccount = ($existingAccountsCount === 0 && $accountsCreatedInRequest === 0);

                        $bankData = [
                            'guardian_registration' => $fileIdNumber,
                            'bank_name' => $bankAccount['bank_name'] ?? null,
                            'iban_usd' => $bankAccount['iban_usd'] ?? null,
                            'iban_shekel' => $bankAccount['iban_shekel'] ?? null,
                            'person_owner_identity_number' => $reIdNumber,
                            're_id_number' => $reIdNumber,
                            're_guardian_name' => $bankAccount['re_guardian_name'] ?? null,
                            're_phone_number' => $bankAccount['re_phone_number'] ?? null,
                            'check_account' => $isFirstAccount ? 1 : 0,
                        ];

                        $createdBankAccount = null;

                        if ($existingBankAccount) {
                            // لا نُصفِّر حساباً أساسياً قائماً: نحفظ حالته إلا إذا أرسل العميل قيمة صريحة
                            $bankData['check_account'] = array_key_exists('check_account', $bankAccount)
                                ? (int) (!empty($bankAccount['check_account']) ? 1 : 0)
                                : (int) $existingBankAccount->check_account;

                            $existingBankAccount->update($bankData);
                            Log::info('✅ تم تحديث حساب بنكي موجود', [
                                'index' => $index,
                                'person_identity_masked' => $this->maskTail((string) $reIdNumber, 3),
                            ]);
                        } else {
                            $createdBankAccount = GuardianBankAccount::create($bankData);
                            $accountsCreatedInRequest++;
                            Log::info('🟢 تم إنشاء حساب بنكي جديد', [
                                'index' => $index,
                                'person_identity_masked' => $this->maskTail((string) $reIdNumber, 3),
                            ]);
                        }

                        // (2.6) خدمة التحقق البنكي تُفعَّل كتحذير فقط (لا تمنع الحفظ)
                        try {
                            $serviceCheck = $bankValidationService->checkDuplicateBankAccount(
                                $bankData,
                                $existingBankAccount?->id ?? ($createdBankAccount->id ?? null)
                            );

                            if (!empty($serviceCheck['is_duplicate'])) {
                                Log::warning('⚠️ خدمة التحقق البنكي أشارت إلى تكرار محتمل (لم يُمنع الحفظ)', [
                                    'index' => $index,
                                    'existing_account' => $serviceCheck['existing_account']['id'] ?? null,
                                ]);
                            }
                        } catch (\Throwable $bankCheckError) {
                            Log::warning('تعذر تشغيل خدمة التحقق البنكي: ' . $bankCheckError->getMessage());
                        }
                    } else {
                        Log::warning('⚠️ لم يتم تخزين حساب بنكي بسبب نقص البيانات', ['index' => $index]);
                    }
                }
            }


            // 3. Store deceased only إذا كان القسم أيتام ويوجد بيانات للأب أو الأم
            if ($request->input('data_section_id') == 1) {
                $fatherFilled = $request->filled('father_first_name') || $request->filled('father_last_name') || $request->filled('father_id');
                $motherFilled = $request->filled('deceased_mother_first_name') || $request->filled('deceased_mother_last_name') || $request->filled('deceased_mother_id');

                if ($fatherFilled || $motherFilled) {
                    // التحقق من صحة أرقام الهوية
                    $validationRules = [];
                    $validationMessages = [];

                    if ($request->filled('father_id')) {
                        $validationRules['father_id'] = 'required|digits:9';
                        $validationMessages['father_id.required'] = 'رقم هوية الأب مطلوب';
                        $validationMessages['father_id.digits'] = 'رقم هوية الأب يجب أن يكون 9 أرقام بالضبط';
                    }

                    if ($request->filled('deceased_mother_id')) {
                        $validationRules['deceased_mother_id'] = 'required|digits:9';
                        $validationMessages['deceased_mother_id.required'] = 'رقم هوية الأم مطلوب';
                        $validationMessages['deceased_mother_id.digits'] = 'رقم هوية الأم يجب أن يكون 9 أرقام بالضبط';
                    }

                    // تنفيذ التحقق إذا كان هناك قواعد
                    if (!empty($validationRules)) {
                        $request->validate($validationRules, $validationMessages);
                    }

                    // تحديث أو إنشاء بيانات الأب والأم المتوفين
                    $existingDeadRecord = DeadPepole::where('re_file_id', $fileIdNumber)->first();
                    $deceasedData = [
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
                        'mother_first_name' => $request->input('deceased_mother_first_name'),
                        'mother_second_name' => $request->input('deceased_mother_second_name'),
                        'mother_third_name' => $request->input('deceased_mother_third_name'),
                        'mother_last_name' => $request->input('deceased_mother_last_name'),
                        'mother_id' => $request->input('deceased_mother_id'),
                        'mother_death_date' => $request->input('mother_death_date'),
                        'mother_death_reason' => $request->input('mother_death_reason'),
                    ];

                    if ($existingDeadRecord) {
                        $existingDeadRecord->update($deceasedData);
                        Log::info('✅ تم تحديث بيانات الأب والأم المتوفين', ['file_id' => $fileIdNumber]);
                    } else {
                        DeadPepole::create($deceasedData);
                        Log::info('✅ تم إنشاء سجل بيانات الأب والأم المتوفين', ['file_id' => $fileIdNumber]);
                    }
                }

                // 🆕 حفظ المتوفين الإضافيين (غير الأب والأم) - يتم تنفيذه دائماً للقسم 1 (أيتام)
                $additionalDeceased = $request->input('additional_deceased', []);

                Log::info('📋 المتوفون الإضافيون المستلمون من النموذج', [
                    'is_array' => is_array($additionalDeceased),
                    'count' => is_array($additionalDeceased) ? count($additionalDeceased) : 0,
                ]);

                if (is_array($additionalDeceased) && count($additionalDeceased) > 0) {
                    Log::info('🟢 بدء حفظ المتوفين الإضافيين:', ['count' => count($additionalDeceased)]);

                    foreach ($additionalDeceased as $index => $deceased) {
                        // الحصول على رقم الهوية (يمكن أن يكون id_number أو person_id)
                        $personId = $deceased['id_number'] ?? $deceased['person_id'] ?? null;

                        // تجاهل السجلات الفارغة
                        if (empty($deceased['first_name']) && empty($deceased['last_name']) && empty($personId)) {
                            Log::info('⏭️ تجاهل متوفي إضافي فارغ:', ['index' => $index]);
                            continue;
                        }

                        // التحقق من صحة رقم الهوية إذا تم إدخاله
                        if (!empty($personId) && !preg_match('/^\d{9}$/', $personId)) {
                            Log::warning('⚠️ رقم هوية متوفي إضافي غير صالح', [
                                'index' => $index,
                            ]);
                        }

                        // تحديث أو إنشاء متوفي إضافي
                        $existingAdditional = null;
                        if (!empty($personId)) {
                            $existingAdditional = \App\Models\AdditionalDeceased::where('re_file_id', $fileIdNumber)
                                ->where('person_id', $personId)
                                ->first();
                        }

                        $additionalData = [
                            're_file_id' => $fileIdNumber,
                            'person_id' => $personId,
                            'first_name' => $deceased['first_name'] ?? null,
                            'second_name' => $deceased['second_name'] ?? null,
                            'third_name' => $deceased['third_name'] ?? null,
                            'last_name' => $deceased['last_name'] ?? null,
                            'relationship' => $deceased['relationship'] ?? 'other',
                            'death_date' => $deceased['death_date'] ?? null,
                            'death_reason' => $deceased['death_reason'] ?? null,
                        ];

                        if ($existingAdditional) {
                            $existingAdditional->update($additionalData);
                            Log::info('✅ تم تحديث متوفي إضافي', ['index' => $index]);
                        } else {
                            \App\Models\AdditionalDeceased::create($additionalData);
                            Log::info('✅ تم إنشاء متوفي إضافي جديد', ['index' => $index]);
                        }
                    }
                }
            } // نهاية شرط if ($request->input('data_section_id') == 1)

            // ═══════════════════════════════════════════════════════════════
            // 3.5. معالجة بيانات الأم — ذكية (حية أو متوفية أو المعيل هي الأم)
            // ═══════════════════════════════════════════════════════════════
            $guardianRelationship = $request->input('data_relationship');
            $guardianRelationshipText = $request->input('data_relationship_text', '');
            $motherIsAlive = $request->input('mother_is_alive');
            // الهوية تتبع الحالة المعلنة: الحية من mother_id والمتوفاة من deceased_mother_id
            $motherId = $motherIsAlive === '1'
                ? ($request->input('mother_id') ?: $request->input('deceased_mother_id'))
                : ($request->input('deceased_mother_id') ?: $request->input('mother_id'));
            $motherFilled = $request->filled('mother_first_name') || $request->filled('mother_last_name') || !empty($motherId)
                || $request->filled('deceased_mother_first_name') || $request->filled('deceased_mother_last_name')
                || $request->filled('deceased_mother_id');

            Log::info('🔍 فحص بيانات الأم', [
                'relationship' => $guardianRelationship,
                'mother_is_alive' => $motherIsAlive,
                'mother_filled' => $motherFilled,
                'file_id' => $fileIdNumber,
            ]);

            // تحديد ما إذا كان المعيل هو الأم
            $guardianIsMother = ($guardianRelationship == 1 || $guardianRelationshipText === 'أم' || $guardianRelationshipText === 'الأم');

            if ($guardianIsMother) {
                // ═══════════════════════════════════════════════════════════
                // الحالة 1: المعيل هو الأم — نسخ بيانات المعيل كبيانات للأم
                // ═══════════════════════════════════════════════════════════
                $this->saveLivingMotherToPortal($request, $fileIdNumber, null, true);
                Log::info('✅ تم نسخ بيانات المعيل (الأم) إلى portal_general_registration_field_values', [
                    'guardian_identity_masked' => $this->maskTail((string) $guardianIdentity, 3),
                    'file_id' => $fileIdNumber,
                ]);

            } elseif ($motherFilled) {
                // ═══════════════════════════════════════════════════════════
                // الحالة 2: المعيل ليس الأم — حفظ حسب الحالة (حية/متوفية)
                // ═══════════════════════════════════════════════════════════
                if ($motherIsAlive === '1') {
                    // الأم حية — حفظ البيانات في portal_general_registration_field_values
                    $this->saveLivingMotherToPortal($request, $fileIdNumber, $motherId, false);
                    Log::info('✅ تم حفظ بيانات الأم الحية في portal_general_registration_field_values', [
                        'mother_identity_masked' => $this->maskTail((string) $motherId, 3),
                        'file_id' => $fileIdNumber,
                    ]);
                } else {
                    // الأم متوفاة — حفظ البيانات في dead_people
                    $deadRecord = DeadPepole::where('re_file_id', $fileIdNumber)->first();

                    $motherData = [
                        'mother_first_name' => $request->input('deceased_mother_first_name'),
                        'mother_second_name' => $request->input('deceased_mother_second_name'),
                        'mother_third_name' => $request->input('deceased_mother_third_name'),
                        'mother_last_name' => $request->input('deceased_mother_last_name'),
                        'mother_id' => $motherId,
                        'mother_death_date' => $request->input('mother_death_date'),
                    ];

                    if ($request->filled('mother_death_reason')) {
                        $motherData['mother_death_reason'] = $request->input('mother_death_reason');
                    }

                    if ($deadRecord) {
                        $deadRecord->update($motherData);
                        Log::info('✅ تم تحديث بيانات الأم المتوفاة في dead_people', ['file_id' => $fileIdNumber]);
                    } else {
                        $motherData['re_file_id'] = $fileIdNumber;
                        DeadPepole::create($motherData);
                        Log::info('✅ تم إنشاء سجل بيانات الأم المتوفاة في dead_people', ['file_id' => $fileIdNumber]);
                    }

                    // حفظ بيانات الأم المتوفية في portal_general_registration_field_values
                    $this->saveDeceasedMotherToPortal($request, $fileIdNumber, $motherId);
                }
            }


            // 4. Store family members
            $familyMembers = $request->input('family_members');

            // 🔎 توثيق فقط (بدون أي تغيير بالمنطق أو بقواعد التحقق): حالة family_members كما وصلت
            Log::info('🔎 حالة family_members عند الحفظ', [
                'file_id_number' => $fileIdNumber,
                'family_members_state' => $familyMembers === null ? 'غير موجودة' : (!is_array($familyMembers) ? 'غير صالحة' : (count($familyMembers) === 0 ? 'فارغة' : 'موجودة')),
                'family_members_count' => is_array($familyMembers) ? count($familyMembers) : 0,
            ]);

            if (is_array($familyMembers) && count($familyMembers) > 0) {
                // حفظ أفراد الأسرة (تحديث أو إنشاء)
                foreach ($familyMembers as $member) {
                    $memberData = [
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
                    ];

                    // 🕒 الفرد مرتبط بمعيل آخر: لا يُكتب في re_people إطلاقاً
                    //    (سجل واحد لكل فرد ⇒ كتابته الآن تعني ملكيتين لملفين معاً).
                    //    تُحفظ لقطة بياناته في طلب النقل ويُستكمل عند موافقة الإدارة.
                    $memberIdentityKey = trim((string) ($member['person_id'] ?? ''));
                    if ($memberIdentityKey !== '' && isset($pendingTransfers[$memberIdentityKey])) {
                        $snapshot = $memberData;
                        unset($snapshot['registration_id']);

                        MemberTransferRequest::recordPending(
                            $registrationDataId,
                            (int) $fileIdNumber,
                            (int) $memberIdentityKey,
                            $pendingTransfers[$memberIdentityKey]['source_file_id_number'],
                            $snapshot
                        );

                        Log::info('🕒 فرد أُوقف نقله بانتظار موافقة الإدارة', [
                            'file_id_number' => $fileIdNumber,
                            'person_id' => $memberIdentityKey,
                            'source_file_id_number' => $pendingTransfers[$memberIdentityKey]['source_file_id_number'],
                        ]);
                        continue;
                    }

                    // البحث عن سجل موجود لنفس الشخص في نفس الملف
                    $existingMember = null;
                    if (!empty($member['person_id'])) {
                        $existingMember = RePeople::where('registration_id', $fileIdNumber)
                            ->where('person_id', $member['person_id'])
                            ->first();
                    }

                    if ($existingMember) {
                        $existingMember->update($memberData);
                    } else {
                        RePeople::create($memberData);
                    }
                }
            }

            // إلغاء طلبات النقل المعلّقة التي لم تعد ضمن قائمة الأفراد المُرسلة في هذا الطلب
            $this->cancelStalePendingTransfers($data, $fileIdNumber, $familyMembers);



            // معالجة المرفقات الجديدة كمصفوفة Laravel
            $attachmentsData = $request->input('attachments', []);
            $allFiles = $request->allFiles();
            Log::info('🟢 عدد المرفقات المستلمة من جميع البوابات', [
                'count' => is_array($attachmentsData) ? count($attachmentsData) : 0,
                'files_count' => count($allFiles),
            ]);
            // 🛡️ هل أُضيف عمود file_hash إلى جدول attachments؟ (حماية من ترتيب الـ migrations)
            $attachmentsHasFileHash = \Illuminate\Support\Facades\Schema::hasColumn('attachments', 'file_hash');

            // 🛡️ منع التكرار داخل نفس الطلب: مفتاح فريد = شخص + نوع الوثيقة + بصمة المحتوى
            $seenAttachments = [];
            $pendingStores = [];
            // مصادر مؤقتة أصلية لا تُحذف إلا بعد نجاح الـ commit (وإلا ضاعت عند إعادة المحاولة/التراجع)
            $deferredTempCleanup = [];

            $this->progressUpdate(40, 'تم حفظ بيانات الاستمارة — جاري معالجة المرفقات...');
            $this->progressStartFiles(is_array($attachmentsData) ? count($attachmentsData) : 0);

            foreach ($attachmentsData as $index => $data) {
                $this->progressAttachmentStep('مرفق ' . (is_numeric($index) ? ((int) $index + 1) : $index));
                // استقبال الملف بشكل صحيح
                $file = $request->hasFile("attachments.$index.file") ? $request->file("attachments.$index.file") : null;
                $tempPath = $data['temp_path'] ?? null;

                // 🛡️ (2.2) temp_path يُستقبل من العميل: صيغة صارمة + احتواء فعلي داخل مجلد الرفع المؤقت
                $tempPath = is_string($tempPath) ? trim($tempPath) : null;
                $tempPathRejected = false;
                $tempPathRejection = null;
                if ($tempPath === '') {
                    $tempPath = null;
                } elseif ($tempPath !== null && ($tempPathRejection = $this->tempPathRejection($tempPath)) !== null) {
                    // السبب يُسجَّل بدقة؛ وإن وصل مع المرفق ملف حقيقي يُستخدم الملف ولا يُسقط المرفق
                    Log::warning('🚫 تجاهل temp_path غير صالح أو خارج مجلد الرفع المؤقت', [
                        'index' => $index,
                        'rejection' => $tempPathRejection,
                        'has_uploaded_file' => (bool) $file,
                    ]);
                    $tempPath = null;
                    $tempPathRejected = true;
                }

                if (!$file && !$tempPath) {
                    // الملف المؤقت المفقود غالباً نُقل/حُذف (طلب سابق أو تنظيف دوري) فيُطلب من المستخدم إعادة رفعه
                    $tempMissing = in_array($tempPathRejection, ['file_not_found', 'not_a_file'], true);
                    $this->recordSkippedAttachment(
                        $skippedAttachments,
                        $index,
                        $data,
                        $tempPathRejected ? 'invalid_temp_path' : 'missing_file',
                        $tempPathRejected
                            ? ($tempMissing
                                ? 'انتهت صلاحية الملف المؤقت أو نُقل بطلب سابق ولم يصل الملف مع الطلب، يرجى إعادة رفعه.'
                                : 'مسار الرفع المؤقت غير صالح، ولم يصل الملف مع الطلب.')
                            : 'لم يتم استقبال ملف المرفق.',
                        $tempPathRejection
                    );
                    continue;
                }
                Log::info('📸 استلام مرفق', [
                    'index' => $index,
                    'source' => $file ? 'upload' : 'temp_path',
                    'size' => $file ? $file->getSize() : null,
                ]);
                $personType = $data['person_identity_number'] ?? null;
                $fileType = $data['file_type'] ?? null;
                // 📎 رقم المرفق يُوحَّد دائماً مع رقم الملف النهائي لهذه المعاملة
                //    (الرقم القادم من الواجهة قد يكون قديماً إذا استُبدل الرقم
                //    عند إعادة التوليد أو عند اعتماد ملف معيل موجود)
                $fileIdNumberAttach = $fileIdNumber;
                if (isset($data['file_id_number']) && $data['file_id_number'] && $data['file_id_number'] !== $fileIdNumber) {
                    Log::info('📎 توحيد رقم ملف المرفق مع رقم الملف النهائي', [
                        'index' => $index,
                        'attachment_file_id' => $data['file_id_number'],
                        'final_file_id' => $fileIdNumber,
                    ]);
                }
                if (!$fileIdNumberAttach || $fileIdNumberAttach === 'undefined') {
                    $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'missing_file_id', 'رقم الملف غير صالح، تعذّر ربط المرفق بالملف.');
                    continue;
                }
                $storedFileName = $data['stored_file_name'] ?? ($file ? $file->getClientOriginalName() : null);
                if (!$storedFileName && $file) {
                    $storedFileName = $file->getClientOriginalName();
                }
                // (2.4) لم تعد الصورة الأصلية (غير المقصوصة) تُرفض: تُحفظ كنسخة أصلية
                if (!$storedFileName) {
                    $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'missing_file_name', 'اسم الملف مفقود.');
                    continue;
                }
                $realPersonId = null;
                // الواجهة تضع default_main/default_father/default_mother مؤقتاً لمن رفع وثائقه قبل إدخال الهوية؛
                // نعاملها كوسوم البوابات نفسها بدل إسقاط المرفق
                if ($personType === 'main' || $personType === 'default_main') {
                    $realPersonId = $request->input('data_id_number');
                } elseif ($personType === 'deceased_father' || $personType === 'default_father') {
                    $realPersonId = $request->input('father_id');
                } elseif ($personType === 'deceased_mother' || $personType === 'default_mother') {
                    // (2.4) الأولوية لهوية الأم المتوفاة، ثم هوية الأم الحية كاحتياط
                    $realPersonId = $request->input('deceased_mother_id') ?: $request->input('mother_id');
                } elseif (strpos($personType, 'family_') === 0) {
                    $familyIndex = (int)str_replace('family_', '', $personType);
                    $familyMembers = $request->input('family_members', []);
                    if (isset($familyMembers[$familyIndex]['person_id'])) {
                        $realPersonId = $familyMembers[$familyIndex]['person_id'];
                    } else {
                        foreach ($familyMembers as $member) {
                            if (isset($member['person_id']) && $member['person_id'] == $personType) {
                                $realPersonId = $member['person_id'];
                                break;
                            }
                        }
                    }
                    // (2.4) فرد أسرة بلا رقم هوية: هوية الطلب إن وُجدت، وإلا مفتاح بديل ثابت
                    //        (9 + رقم الملف + رقم الفرد) بدل إسقاط المرفق بصمت
                    if (empty($realPersonId) || !preg_match('/^\d+$/', (string) $realPersonId)) {
                        $submittedIdentity = trim((string) ($data['person_identity_number'] ?? ''));
                        $realPersonId = preg_match('/^\d{9,10}$/', $submittedIdentity)
                            ? $submittedIdentity
                            : $this->buildFamilyFallbackIdentity((string) $fileIdNumberAttach, $familyIndex);
                        Log::info('🪪 ربط مرفق فرد أسرة بلا هوية بمفتاح بديل', [
                            'index' => $index,
                            'family_index' => $familyIndex,
                            'fallback_identity_masked' => $this->maskTail((string) $realPersonId, 3),
                        ]);
                    }
                } else {
                    $realPersonId = is_numeric($personType) ? $personType : null;
                }
                if (($file || $tempPath) && $realPersonId && $fileType && $fileIdNumberAttach && $storedFileName && preg_match('/^\d+$/', $realPersonId)) {
                    // ملاحظة: فحص الوجه يتم الآن Client-Side عبر face-api.js في الواجهة الأمامية

                    // 🛡️ (2.2) النوع الحقيقي للمحتوى عبر finfo بدل الثقة باسم الملف،
                    // والامتداد النهائي يُشتق من قائمة سماح ثابتة فقط.
                    $absolutePath = $file
                        ? $file->getRealPath()
                        : ($tempPath ? \Storage::disk('public')->path($tempPath) : null);

                    if (!$absolutePath || !is_file($absolutePath)) {
                        $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'unreadable_file', 'تعذّر الوصول إلى محتوى الملف.');
                        continue;
                    }

                    $detectedMime = $this->detectUploadMime($absolutePath);
                    $extension = $this->resolveAttachmentExtension($detectedMime, $absolutePath);
                    if ($extension === null) {
                        $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'unsupported_type', 'نوع محتوى الملف غير مدعوم.');
                        continue;
                    }

                    // صفحة التسجيل العامة: صور فقط حتى لو تلاعب أحدهم بامتداد أو نوع الملف
                    if ($imagesOnly && !in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                        $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'unsupported_type', 'يُسمح بالصور فقط في نموذج التسجيل العام.');
                        continue;
                    }

                    $fileSize = (int) @filesize($absolutePath);

                    // 🔒 بصمة محتوى الملف (SHA-1) — تُحسب على المحتوى الأصلي لتوافق السجلات السابقة
                    $fileHash = null;
                    try {
                        $fileHash = @hash_file('sha1', $absolutePath) ?: null;
                    } catch (\Throwable $hashError) {
                        $fileHash = null;
                    }

                    // 🛡️ (1) منع التكرار داخل نفس الطلب — قد تصل الواجهة بنفس الملف أكثر من مرة
                    $dedupKey = $realPersonId . '|' . $fileType . '|' . ($fileHash ?? ($storedFileName . '|' . $fileSize));
                    if (isset($seenAttachments[$dedupKey])) {
                        $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'duplicate_in_request', 'المرفق مكرر داخل الطلب نفسه.');
                        continue;
                    }

                    // 🛡️ (2) منع التكرار مع ما هو مسجل مسبقاً في قاعدة البيانات (طلبات مكررة/متداخلة)
                    if ($attachmentsHasFileHash && $fileHash && Attachment::where('person_identity_number', $realPersonId)
                            ->where('file_type', $fileType)
                            ->where('file_hash', $fileHash)
                            ->exists()) {
                        $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'duplicate_in_db', 'المرفق مسجّل مسبقاً في النظام.');
                        continue;
                    }

                    $seenAttachments[$dedupKey] = true;

                    // 🖼️ كل صورة غير JPEG تُحوَّل إلى JPG قبل الحفظ (GD ثم ImageMagick احتياطاً)
                    $convertedTempPath = null;
                    if (in_array($extension, self::JPEG_CONVERSION_EXTENSIONS, true)) {
                        $convertedTempPath = $this->convertImageToJpeg($absolutePath);
                        if ($convertedTempPath === null) {
                            $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'conversion_failed', 'تعذّر تحويل الصورة إلى JPG.');
                            continue;
                        }

                        $absolutePath = $convertedTempPath;
                        $extension = 'jpg';
                        $fileSize = (int) @filesize($convertedTempPath);
                    }

                    // اسم الملف: نوع الوثيقة _ رقم الملف الخاص بالشخص _ رقم هوية الشخص _ تاريخ ووقت اليوم + مُعرّف فريد
                    // المعرّف الفريد ضروري: الطابع الزمني دقيقته الثانية فقط، فكانت محاولات متعددة داخل نفس الثانية
                    // تُنشئ نفس الاسم وتكتب فوق الملف الفعلي على القرص مع بقاء سجلات مكررة في قاعدة البيانات.
                    $safeFileType = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $fileType) ?: 'doc';
                    $newFileName = "{$safeFileType}_{$fileIdNumberAttach}_{$realPersonId}_" . date('Ymd_His') . '_' . uniqid() . ".{$extension}";
                    $folder = 'uploads/' . $fileIdNumberAttach;
                    if ($folder === 'public' || $folder === 'public/') {
                        throw new \Exception('خطأ في مسار التخزين: يجب تحديد مجلد فرعي داخل uploads');
                    }

                    // (2.3) لا نكتب الملف النهائي داخل المعاملة: نرحّل مصدره وننقله بعد commit
                    $finalPath = $folder . '/' . $newFileName;
                    $stagedStore = null;

                    if ($convertedTempPath) {
                        $fileSize = (int) @filesize($convertedTempPath);
                        $stagedStore = [
                            'type' => 'absolute',
                            'source' => $convertedTempPath,
                            'final_path' => $finalPath,
                            'folder' => $folder,
                            'file_name' => $newFileName,
                        ];

                        // المصدر المؤقت الأصلي لا يُحذف هنا (قبل الـ commit): لو تراجعت المعاملة أو أُعيدت
                        // المحاولة (Deadlock أو إعادة إرسال المستخدم) لوجد الطلب التالي الملف مفقوداً (invalid_temp_path).
                        // يُحذف بعد نجاح الحفظ النهائي.
                        if ($tempPath) {
                            $deferredTempCleanup[] = $tempPath;
                        }
                    } elseif ($file) {
                        $fileSize = (int) $file->getSize();
                        $stagedStore = [
                            'type' => 'uploaded',
                            'file' => $file,
                            'final_path' => $finalPath,
                            'folder' => $folder,
                            'file_name' => $newFileName,
                        ];
                    } else {
                        if (!\Storage::disk('public')->exists($tempPath)) {
                            $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'temp_file_missing', 'الملف المؤقت لم يعد موجوداً.');
                            continue;
                        }

                        try {
                            $fileSize = (int) \Storage::disk('public')->size($tempPath);
                        } catch (\Throwable $sizeError) {
                            $fileSize = 0;
                        }

                        $stagedStore = [
                            'type' => 'temp_path',
                            'source' => $tempPath,
                            'final_path' => $finalPath,
                            'folder' => $folder,
                            'file_name' => $newFileName,
                        ];
                    }

                    $attachmentPayload = [
                        'person_identity_number' => $realPersonId,
                        'stored_file_name' => $newFileName,
                        'file_path' => 'storage/' . $finalPath,
                        'file_type' => $fileType,
                        'file_size' => $fileSize,
                    ];
                    if ($attachmentsHasFileHash) {
                        $attachmentPayload['file_hash'] = $fileHash;
                    }

                    try {
                        $attachment = Attachment::create($attachmentPayload);
                    } catch (\Illuminate\Database\QueryException $queryException) {
                        // فهرس الفريدة uniq_person_type_hash رفض سجلاً مكرراً (سباق بين طلبين) — لا نُفشل التسجيل كاملاً
                        if ((string) $queryException->getCode() === '23000') {
                            $this->recordSkippedAttachment($skippedAttachments, $index, $data, 'duplicate_in_db', 'المرفق مسجّل مسبقاً في النظام.');
                            continue;
                        }
                        throw $queryException;
                    }

                    $stagedStore['attachment_id'] = $attachment->id;
                    $stagedStore['index'] = $index;
                    $stagedStore['data'] = $data;
                    $pendingStores[] = $stagedStore;

                    $coveredAttachments[(string) $realPersonId][strtolower(trim((string) $fileType))] = true;
                    Log::info('🟢 تم تسجيل مرفق بانتظار النقل بعد commit', [
                        'index' => $index,
                        'file_type' => $fileType,
                        'new_file_name' => $newFileName,
                        'file_hash' => $fileHash,
                        'file_size' => $fileSize,
                    ]);
                } else {
                    $this->recordSkippedAttachment(
                        $skippedAttachments,
                        $index,
                        $data,
                        'incomplete_attachment_data',
                        'بيانات المرفق غير مكتملة (نوع الوثيقة أو ربط الشخص).'
                    );
                }
            }

            if (!empty($skippedAttachments)) {
                Log::warning('⚠️ مرفقات لم تُحفظ في الطلب', [
                    'file_id_number' => $fileIdNumber,
                    'skipped_count' => count($skippedAttachments),
                    'reasons' => array_count_values(array_column($skippedAttachments, 'reason')),
                ]);
            }


            // 🛡️ (2.4) فحص الوثائق الإلزامية: تحذير + تسجيل افتراضياً،
            //      والحجب فقط عند تفعيل config('portal.strict_attachments') بقرار من مالك المشروع
            $missingRequiredAttachments = $this->collectMissingRequiredAttachments($request, $fileIdNumber, $coveredAttachments);
            if (!empty($missingRequiredAttachments)) {
                foreach ($missingRequiredAttachments as $missingAttachment) {
                    $skippedAttachments[] = $missingAttachment;
                }

                Log::warning('⚠️ وثائق إلزامية غير مرفوعة في الطلب', [
                    'file_id_number' => $fileIdNumber,
                    'missing_count' => count($missingRequiredAttachments),
                ]);

                if (config('portal.strict_attachments', false)) {
                    $missingTypeNames = array_values(array_unique(array_column($missingRequiredAttachments, 'file_type')));
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'attachments' => 'وثائق إلزامية ناقصة: ' . implode('، ', $missingTypeNames),
                    ]);
                }
            }

            // إضافة مستخدم جديد عند التسجيل العام
            // توليد كلمة مرور عشوائية من 8 أحرف
            $randomPassword = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);

            // (2.5) firstOrCreate يمنع فشل الاستمارة عند سباق إنشاء نفس المستخدم
            $user = \App\Models\User::firstOrCreate(
                ['email' => $request->input('data_id_number')],
                [
                    'name' => $request->input('data_first_name'),
                    'phone' => $request->input('data_phone_number') ?? $request->input('data_id_number'), // قيمة افتراضية = رقم الهوية
                    'password' => bcrypt($randomPassword),
                    'role' => 'user',
                ]
            );
            $userCreated = $user->wasRecentlyCreated;
            Log::info($userCreated ? 'ℹ️ تم إنشاء حساب مستخدم جديد' : 'ℹ️ المستخدم مسجل مسبقاً، تخطي إنشاء حساب جديد', [
                'file_id_number' => $fileIdNumber,
            ]);

            DB::commit();
            $this->progressUpdate(92, 'جاري نقل الملفات إلى مكانها النهائي...');

            // (3.3) انتهى استخدام الرقم المحجوز لهذه الجلسة
            if ($request->hasSession()) {
                $request->session()->forget(['reserved_code', 'reserved_code_at']);
            }

            // (2.3) اكتملت كتابة السجلات — ننقل ملفات المرفقات إلى مكانها النهائي الآن فقط
            $attachmentsSaved = $this->finalizePendingAttachmentStores($pendingStores, $skippedAttachments);
            $pendingStores = [];

            // اكتمل الحفظ: نحذف الآن المصادر المؤقتة الأصلية التي حُوِّلت إلى JPG
            foreach (array_unique($deferredTempCleanup) as $deferredTemp) {
                $this->deleteTempUploadIfEmpty($deferredTemp);
            }
            $deferredTempCleanup = [];
            $this->progressUpdate(97, 'جاري إنهاء الطلب...');

            // 📋 ملخص التسجيل — للتأكد من دخول جميع الأقسام (قراءة فقط بعد الحفظ)
            try {
                $summaryFamilyMembers = RePeople::where('registration_id', $fileIdNumber)->count();
                $summaryBankAccounts = GuardianBankAccount::where('guardian_registration', $fileIdNumber)->count();
                $summaryHasDeadPeople = DeadPepole::where('re_file_id', $fileIdNumber)->exists();
                $summaryPendingTransfers = MemberTransferRequest::tableExists()
                    ? MemberTransferRequest::pending()->where('data_id', $registrationDataId)->count()
                    : 0;

                $emptySections = [];
                if ($summaryFamilyMembers === 0) {
                    $emptySections[] = 'family_members';
                }
                if ($summaryBankAccounts === 0) {
                    $emptySections[] = 'bank_accounts';
                }
                if (!$summaryHasDeadPeople) {
                    $emptySections[] = 'dead_people';
                }
                if ($attachmentsSaved === 0) {
                    $emptySections[] = 'attachments';
                }

                Log::info('📋 ملخص التسجيل العام', [
                    'file_id_number' => $fileIdNumber,
                    'mode' => $useExistingGuardian ? 'update' : 'create',
                    'guardian_identity_masked' => $this->maskTail((string) $guardianIdentity, 3),
                    'family_members_total' => $summaryFamilyMembers,
                    'bank_accounts_total' => $summaryBankAccounts,
                    'has_dead_people' => $summaryHasDeadPeople,
                    'attachments_saved_this_request' => $attachmentsSaved,
                    'pending_transfers_count' => $summaryPendingTransfers,
                    'user_created' => $userCreated,
                    'empty_sections' => $emptySections,
                ]);
            } catch (\Throwable $summaryError) {
                Log::warning('تعذر تسجيل ملخص التسجيل العام: ' . $summaryError->getMessage());
            }

            $pendingTransfersCount = MemberTransferRequest::tableExists()
                ? MemberTransferRequest::pending()->where('data_id', $registrationDataId)->count()
                : 0;

            if ($request->ajax() || $request->wantsJson()) {
                // (2.4) المرفقات غير المحفوظة تُعاد للواجهة كتحذيرات بدل إسقاطها بصمت
                return response()->json([
                    'success' => true,
                    'redirect' => route('user.thank.you.page'),
                    'warnings' => $skippedAttachments,
                    'skipped_attachments' => $skippedAttachments,
                    'pending_transfers_count' => $pendingTransfersCount,
                ]);
            }

            $thankYouRedirect = redirect()->route('user.thank.you.page');
            if (!empty($skippedAttachments)) {
                $thankYouRedirect->with('attachment_warnings', $skippedAttachments);
            }
            return $thankYouRedirect;

            } catch (\Illuminate\Validation\ValidationException $e) {
                // فشل تحقق (ValidationException) ليس عطلاً في الخادم:
                // يُعاد بـ 422 مع أخطاء الحقول الصحيحة بدل 500
                $this->cleanupPendingAttachmentTemps($pendingStores);
                $pendingStores = [];
                DB::rollBack();
                Log::warning('⚠️ فشل تحقق أثناء حفظ السجل العام: ' . $e->getMessage());

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                    ], 422);
                }
                return redirect()->back()
                    ->withInput()
                    ->withErrors($e->errors())
                    ->with('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->cleanupPendingAttachmentTemps($pendingStores);
                $pendingStores = [];
                DB::rollBack();

                // (2.5) أخطاء التكرار (SQLSTATE 23000 / MySQL 1062) تُعاد كطلب مكرر مفهوم بلا كشف SQL
                if ($e instanceof \Illuminate\Database\QueryException) {
                    $sqlState = (string) $e->getCode();
                    $driverCode = (int) ($e->errorInfo[1] ?? 0);

                    if ($sqlState === '23000' || $driverCode === 1062) {
                        Log::warning('⚠️ تكرار في قاعدة البيانات أثناء حفظ التسجيل العام', [
                            'sql_state' => $sqlState,
                            'driver_code' => $driverCode,
                        ]);

                        $duplicateMessage = 'يبدو أن هذا الطلب مسجّل مسبقاً في النظام (تكرار في البيانات). يرجى مراجعة البيانات والمحاولة مرة أخرى، أو التواصل مع الإدارة.';

                        if ($request->ajax() || $request->wantsJson()) {
                            return response()->json([
                                'success' => false,
                                'message' => $duplicateMessage,
                            ], 409);
                        }

                        return redirect()->back()->withInput()->with('error', $duplicateMessage);
                    }
                }

                // إعادة المحاولة في حالة Deadlock
                $isDeadlock = str_contains($e->getMessage(), 'Deadlock') ||
                              str_contains($e->getMessage(), 'deadlock') ||
                              str_contains($e->getMessage(), 'Lock wait timeout');

                if ($isDeadlock && $retryCount < $maxRetries) {
                    $retryCount++;
                    Log::warning("🔄 Deadlock detected, retrying transaction (attempt {$retryCount}/{$maxRetries})");
                    sleep(1); // انتظار ثانية قبل إعادة المحاولة
                    continue;
                }

                Log::error('خطأ في تخزين السجل: ' . $e->getMessage());
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => $e->getMessage()
                    ], 500);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'حدث خطأ أثناء حفظ السجل: ' . $e->getMessage());
            }
        } // end while
        } finally {
            // (3.1) تحرير القفل دائماً — وإن فشل يتحرر تلقائياً بعد انتهاء مدته
            if ($storeLock !== null) {
                try {
                    $storeLock->release();
                } catch (\Throwable $releaseError) {
                    Log::warning('تعذر تحرير قفل الحفظ: ' . $releaseError->getMessage());
                }
            }
        }
    }
    // إضافة دالة الرفع المجزأ
    public function uploadChunk(Request $request)
    {
        try {
            $chunk = $request->file('chunk');
            $fileName = (string) $request->input('file_name');
            $fileId = (string) $request->input('file_id_number');
            $chunkIndex = $request->input('chunk_index');
            $totalChunks = $request->input('total_chunks');

            if (!$chunk || $fileName === '' || $fileId === '') {
                return response()->json(['success' => false, 'error' => 'بيانات مفقودة للرفع المجزأ'], 400);
            }

            // رقم الملف: 6 أرقام (أو الصيغة الاحتياطية TEMP التي تولّدها الواجهة)
            if (!preg_match('/^(?:\d{6}|TEMP-[A-Z0-9]{6})$/', $fileId)) {
                return response()->json(['success' => false, 'error' => 'رقم الملف غير صالح للرفع'], 422);
            }

            // الصور فقط (تُحوَّل إلى JPG عند الحفظ) — لا PDF ولا فيديو ولا مستندات
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'tif', 'tiff', 'ico', 'heic', 'heif', 'jfif'], true)) {
                return response()->json(['success' => false, 'error' => 'نوع الملف غير مسموح'], 422);
            }

            if (!is_numeric($chunkIndex) || !is_numeric($totalChunks)) {
                return response()->json(['success' => false, 'error' => 'بيانات الأجزاء غير صالحة'], 422);
            }

            $chunkIndex = (int) $chunkIndex;
            $totalChunks = (int) $totalChunks;

            if ($totalChunks < 1 || $totalChunks > 200) {
                return response()->json(['success' => false, 'error' => 'عدد الأجزاء غير مسموح'], 422);
            }

            if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
                return response()->json(['success' => false, 'error' => 'فهرس الجزء غير صالح'], 422);
            }

            // حجم الجزء 1MB في الواجهة، ونسمح بهامش حتى 2MB
            if ($chunk->getSize() > 2 * 1024 * 1024) {
                return response()->json(['success' => false, 'error' => 'حجم الجزء أكبر من المسموح'], 422);
            }

            // تنظيف اسم الملف لتجنب أي مشاكل أمنية
            $safeFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
            if ($safeFileName === '' || $safeFileName === '.' || $safeFileName === '..') {
                return response()->json(['success' => false, 'error' => 'اسم الملف غير صالح'], 422);
            }

            $tempDir = storage_path('app/public/temp_uploads/' . $fileId);
            if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                return response()->json(['success' => false, 'error' => 'تعذر تجهيز مجلد الرفع'], 500);
            }

            $finalPath = $tempDir . '/' . $safeFileName;
            $partPath = $finalPath . '.part' . $chunkIndex;

            // الجزء الأخير بعد استجابة مفقودة: الملف مجمّع مسبقاً بالفعل
            if ($chunkIndex === $totalChunks - 1 && empty(glob($finalPath . '.part*')) && is_file($finalPath)) {
                return response()->json([
                    'success' => true,
                    'complete' => true,
                    'path' => 'temp_uploads/' . $fileId . '/' . $safeFileName
                ]);
            }

            if ($chunkIndex === 0) {
                // بداية رفع جديد: احذف بقايا نفس الاسم
                foreach ((array) glob($finalPath . '.part*') as $stalePart) {
                    @unlink($stalePart);
                }
                @unlink($finalPath);
            }

            // اكتب كل جزء في ملف مستقل لتجنّب تداخل الأجزاء المتزامنة
            $chunkContents = file_get_contents($chunk->getRealPath());
            if ($chunkContents === false || file_put_contents($partPath, $chunkContents) === false) {
                @unlink($partPath);
                return response()->json(['success' => false, 'error' => 'تعذر كتابة الجزء'], 500);
            }

            // إذا لم يكن هذا الجزء الأخير
            if ($chunkIndex !== $totalChunks - 1) {
                return response()->json(['success' => true, 'complete' => false]);
            }

            // الجزء الأخير: تأكد من اكتمال جميع الأجزاء ثم اجمعها بالترتيب
            $parts = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                $candidate = $finalPath . '.part' . $i;
                if (!is_file($candidate)) {
                    return response()->json(['success' => false, 'error' => 'أجزاء ناقصة، أعد المحاولة'], 409);
                }
                $parts[] = $candidate;
            }

            $out = fopen($finalPath, 'wb');
            if ($out === false) {
                return response()->json(['success' => false, 'error' => 'تعذر تجميع الملف'], 500);
            }

            try {
                foreach ($parts as $part) {
                    $in = fopen($part, 'rb');
                    if ($in === false) {
                        throw new \RuntimeException('تعذر قراءة جزء من الأجزاء');
                    }
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                }
            } finally {
                fclose($out);
            }

            foreach ($parts as $part) {
                @unlink($part);
            }

            return response()->json([
                'success' => true,
                'complete' => true,
                'path' => 'temp_uploads/' . $fileId . '/' . $safeFileName
            ]);
        } catch (\Exception $e) {
            Log::error('خطأ في الرفع المجزأ: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'تعذر إكمال الرفع المجزأ'], 500);
        }
    }

    // فحص وجود رقم الهوية في الجداول المختلفة
    public function check(Request $request)
    {
        $id = $request->input('id_number');
        $exists = false;

        // تحقق في جدول البيانات الأساسية
        if (Data::where('data_id_number', $id)->exists()) $exists = true;

        // تحقق في جدول الأفراد المتوفين (الأب أو الأم)
        if (
            DeadPepole::where('father_id', $id)->orWhere('mother_id', $id)->exists()
        ) $exists = true;

        // تحقق في جدول أفراد الأسرة
        if (RePeople::where('person_id', $id)->exists()) $exists = true;

        return response()->json(['exists' => $exists]);
    }

    /**
     * مسارات الرفع المؤقت المسموحة: رفع البوابة المجزأ ورفع واتساب.
     * ملف واحد فقط تحت مجلد معروف، بلا شرطات مائلة داخل الاسم.
     */
    private const TEMP_ATTACHMENT_PATH_PATTERN = '#^temp_uploads/(?:(?:\d{6}|TEMP-[A-Z0-9]{6})|whatsapp/\d{6,20})/[^/\\\\]+$#';

    /** أنواع المحتوى المسموحة للمرفقات، مشتقة من فحص finfo الحقيقي فقط. */
    private const ATTACHMENT_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/x-bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/avif' => 'avif',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
        'image/tiff' => 'tif',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'application/pdf' => 'pdf',
    ];

    /** امتدادات الصور فقط — تُستخدم لفرض «صور فقط» على صفحة التسجيل العامة. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'heic', 'tif', 'ico'];

    /** كل صورة غير JPEG تُحوَّل إلى JPG قبل الحفظ. */
    private const JPEG_CONVERSION_EXTENSIONS = ['png', 'webp', 'gif', 'bmp', 'avif', 'heic', 'tif', 'ico'];

    /** الحد الأقصى لحجم المرفق بعد التحويل (5 ميغابايت). */
    private const MAX_ATTACHMENT_BYTES = 5242880;

    /**
     * نوع المحتوى الحقيقي للملف عبر finfo (وليس امتداد الاسم).
     */
    private function detectUploadMime(string $absolutePath): ?string
    {
        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($absolutePath);
            if (!is_string($mime) || $mime === '') {
                return null;
            }

            return strtolower(trim(explode(';', $mime)[0]));
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * يعيد الامتداد المسموح المقابل للنوع الحقيقي، أو null إذا كان النوع مرفوضاً.
     */
    private function resolveAttachmentExtension(?string $mime, string $absolutePath): ?string
    {
        if ($mime !== null && isset(self::ATTACHMENT_MIME_EXTENSIONS[$mime])) {
            return self::ATTACHMENT_MIME_EXTENSIONS[$mime];
        }

        // بعض إصدارات libmagic تُرجع application/octet-stream لصور HEIC — نتحقق من ترويسة ftyp
        if (($mime === null || $mime === 'application/octet-stream') && $this->looksLikeHeic($absolutePath)) {
            return 'heic';
        }

        return null;
    }

    private function looksLikeHeic(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 12);
        fclose($handle);

        if (!is_string($header) || strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp') {
            return false;
        }

        return in_array(substr($header, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true);
    }

    /**
     * يحوّل أي صورة مدعومة إلى JPEG على خلفية بيضاء ويُعيد مساراً مؤقتاً، أو null عند الفشل.
     */
    private function convertImageToJpeg(string $sourcePath): ?string
    {
        $base = @tempnam(sys_get_temp_dir(), 'attachment_');
        if ($base === false) {
            return null;
        }
        $destination = $base . '.jpg';
        @unlink($base);

        // الصور الضخمة تُترك لـ ImageMagick (يستخدم ذاكرة مؤقتة على القرص) تفادياً لاستنزاف ذاكرة PHP
        $probe = @getimagesize($sourcePath);
        $pixels = is_array($probe) ? ((int) $probe[0] * (int) $probe[1]) : 0;

        if (($pixels === 0 || $pixels <= 24000000) && function_exists('imagecreatefromstring')) {
            $contents = @file_get_contents($sourcePath);
            $image = $contents !== false ? @imagecreatefromstring($contents) : false;
            unset($contents);

            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $longest = max($width, $height);

                // حد أقصى 4096px للضلع الأطول: يحافظ على وضوح الوثائق ويضبط الحجم
                if ($longest > 4096) {
                    $ratio = 4096 / $longest;
                    $resized = imagescale(
                        $image,
                        max(1, (int) round($width * $ratio)),
                        max(1, (int) round($height * $ratio)),
                        IMG_BICUBIC
                    );
                    if ($resized !== false) {
                        imagedestroy($image);
                        $image = $resized;
                        $width = imagesx($image);
                        $height = imagesy($image);
                    }
                }

                $canvas = $width > 0 && $height > 0 ? imagecreatetruecolor($width, $height) : false;

                if ($canvas !== false) {
                    $white = imagecolorallocate($canvas, 255, 255, 255);
                    imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
                    imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

                    // جودة تنازلية حتى يبقى الحجم ضمن الحد المسموح
                    foreach ([85, 70, 55] as $quality) {
                        if (@imagejpeg($canvas, $destination, $quality)
                            && is_file($destination)
                            && filesize($destination) > 0
                            && filesize($destination) <= self::MAX_ATTACHMENT_BYTES) {
                            imagedestroy($canvas);
                            imagedestroy($image);
                            return $destination;
                        }
                    }

                    imagedestroy($canvas);
                }

                imagedestroy($image);
            }
        }

        // محرك احتياطي: ImageMagick يقرأ HEIC/TIFF/ICO عند توفّر delegates
        if ($this->convertImageWithImageMagick($sourcePath, $destination)) {
            return $destination;
        }

        @unlink($destination);
        return null;
    }

    private function convertImageWithImageMagick(string $sourcePath, string $destination): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        static $available = null;
        if ($available === null) {
            $output = [];
            $exitCode = 1;
            @exec('magick -version 2>&1', $output, $exitCode);
            $available = $exitCode === 0;
        }

        if (!$available) {
            return false;
        }

        $command = 'magick ' . escapeshellarg($sourcePath)
            . ' -auto-orient -resize "4096x4096>" -background white -alpha remove -alpha off -quality 85 '
            . escapeshellarg($destination) . ' 2>&1';

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        return $exitCode === 0
            && is_file($destination)
            && filesize($destination) > 0
            && filesize($destination) <= self::MAX_ATTACHMENT_BYTES;
    }

    /**
     * حذف الملف المؤقت (بعد التحويل) ومجلده إن أصبح فارغاً.
     */
    private function deleteTempUploadIfEmpty(string $tempPath): void
    {
        try {
            $disk = \Storage::disk('public');
            $disk->delete($tempPath);

            $dir = dirname($tempPath);
            if ($dir === 'temp_uploads' || $dir === '.') {
                return;
            }

            if ($disk->exists($dir) && empty($disk->allFiles($dir))) {
                $disk->deleteDirectory($dir);
            }
        } catch (\Throwable $exception) {
            // التنظيف ليس حرجاً — الكنس الدوري يتكفل بالبقايا
        }
    }

    /**
     * مسار الرفع المؤقت يجب أن يطابق temp_uploads/{ملف}/اسم وأن يبقى فعلياً داخل temp_uploads.
     */
    private function isAllowedTempPath(string $tempPath): bool
    {
        return $this->tempPathRejection($tempPath) === null;
    }

    /**
     * سبب رفض مسار الرفع المؤقت (null = مقبول):
     *  pattern_mismatch | temp_root_missing | file_not_found | not_a_file | outside_temp_root
     *
     * لا حاجة لفحص «..» نصياً: الصيغة تمنع الشرطات في اسم الملف، وأي مسار يخرج من المجلد
     * يُرفض بفحص realpath والاحتواء. (اسم مثل «scan..jpg» آمن ولا يُرفض.)
     */
    private function tempPathRejection(string $tempPath): ?string
    {
        if (!preg_match(self::TEMP_ATTACHMENT_PATH_PATTERN, $tempPath)) {
            return 'pattern_mismatch';
        }

        $tempRoot = realpath(storage_path('app/public/temp_uploads'));
        if ($tempRoot === false) {
            return 'temp_root_missing';
        }

        // ذاكرة realpath تبقى بين الطلبات في php-fpm؛ نمسحها لئلا يُرفض ملف أُنشئ للتوّ
        $absolute = storage_path('app/public/' . $tempPath);
        clearstatcache(true, $absolute);
        $resolved = realpath($absolute);

        if ($resolved === false) {
            return 'file_not_found';
        }
        if (!is_file($resolved)) {
            return 'not_a_file';
        }
        if (strpos($resolved, $tempRoot . DIRECTORY_SEPARATOR) !== 0) {
            return 'outside_temp_root';
        }

        return null;
    }

    /**
     * (2.3) نقل ملفات المرفقات بعد commit. أي فشل يتراجع عن سجل المرفق ويُعاد في warnings.
     */
    private function finalizePendingAttachmentStores(array $pendingStores, array &$skippedAttachments): int
    {
        $disk = \Storage::disk('public');
        $storedCount = 0;

        foreach ($pendingStores as $pending) {
            $stored = false;
            $failureReason = null;

            try {
                if ($pending['type'] === 'uploaded') {
                    $stored = $pending['file']->storeAs($pending['folder'], $pending['file_name'], 'public') !== false;
                } elseif ($pending['type'] === 'temp_path') {
                    $stored = $disk->exists($pending['source'])
                        && $disk->move($pending['source'], $pending['final_path']);
                } else {
                    $handle = @fopen($pending['source'], 'rb');
                    if ($handle !== false) {
                        $stored = $disk->put($pending['final_path'], $handle) !== false;
                        fclose($handle);
                    }
                }
            } catch (\Throwable $storeError) {
                $failureReason = $storeError->getMessage();
                $stored = false;
            }

            if ($pending['type'] === 'absolute') {
                @unlink($pending['source']);
            }

            if (!$stored) {
                // الملف لم يُحفظ فعلياً: إزالة السجل الذي أُنشئ في هذه العملية وعدم إبقاء ملف جزئي
                try {
                    Attachment::whereKey($pending['attachment_id'])->delete();
                } catch (\Throwable $cleanupError) {
                    // لا شيء إضافي: السجل سيظهر لاحقاً كمرفق بلا ملف ويُعالج يدوياً
                }

                try {
                    $disk->delete($pending['final_path']);
                } catch (\Throwable $cleanupError) {
                    // تجاهل
                }

                $this->recordSkippedAttachment(
                    $skippedAttachments,
                    $pending['index'] ?? null,
                    $pending['data'] ?? [],
                    'storage_failed',
                    'تعذّر نقل الملف إلى مكانه النهائي بعد حفظ السجل.'
                );

                Log::error('تعذر نقل ملف مرفق بعد commit', [
                    'attachment_id' => $pending['attachment_id'],
                    'error' => $failureReason,
                ]);
                continue;
            }

            if ($pending['type'] === 'temp_path') {
                $tempDir = dirname($pending['source']);
                if ($tempDir !== 'temp_uploads' && $tempDir !== '.') {
                    try {
                        if ($disk->exists($tempDir) && empty($disk->allFiles($tempDir))) {
                            $disk->deleteDirectory($tempDir);
                        }
                    } catch (\Throwable $cleanupError) {
                        // التنظيف غير حرج
                    }
                }
            }

            $storedCount++;
        }

        return $storedCount;
    }

    /**
     * (2.3) حذف الملفات المؤقتة الناتجة عن التحويل عند فشل/إعادة الطلب.
     */
    private function cleanupPendingAttachmentTemps(array $pendingStores): void
    {
        foreach ($pendingStores as $pending) {
            if (($pending['type'] ?? null) === 'absolute' && !empty($pending['source'])) {
                @unlink($pending['source']);
            }
        }
    }

    /**
     * (2.4) تسجيل مرفق لم يُحفظ مع سبب مفهوم، وإعادته للواجهة ضمن warnings.
     */
    private function recordSkippedAttachment(array &$skipped, $index, $data, string $reason, string $message, ?string $detail = null): void
    {
        $fileType = is_array($data) ? ($data['file_type'] ?? null) : null;
        $personIdentity = is_array($data) ? ($data['person_identity_number'] ?? null) : null;

        $skipped[] = [
            'index' => $index,
            'file_type' => $fileType,
            'person_identity_number' => $personIdentity,
            'reason' => $reason,
            'message' => $message,
        ];

        Log::warning('⚠️ مرفق لم يُحفظ — سيُعاد للمستخدم في warnings', [
            'index' => $index,
            'file_type' => $fileType,
            'person_identity_masked' => $this->maskTail((string) $personIdentity, 3),
            'reason' => $reason,
            'detail' => $detail,
        ]);
    }

    /**
     * (2.4) مفتاح بديل ثابت لفرد أسرة بلا رقم هوية: 9 + رقم الملف (6) + رقم الفرد (2).
     */
    private function buildFamilyFallbackIdentity(string $fileIdNumber, int $familyIndex): string
    {
        return '9'
            . str_pad($fileIdNumber, 6, '0', STR_PAD_LEFT)
            . str_pad((string) max(0, min($familyIndex, 99)), 2, '0', STR_PAD_LEFT);
    }

    /**
     * (2.4) الوثائق الإلزامية الناقصة لكل شخص في الطلب.
     * تحذير فقط افتراضياً؛ الحجب يحتاج تفعيل config('portal.strict_attachments').
     */
    private function collectMissingRequiredAttachments(Request $request, string $fileIdNumber, array $coveredAttachments): array
    {
        try {
            $documentTypes = \App\Models\DocumentType::query()
                ->get(['pref', 'basic_required', 'deceased_required', 'family_required']);
        } catch (\Throwable $exception) {
            Log::warning('تعذر جلب أنواع الوثائق لفحص المرفقات الإلزامية: ' . $exception->getMessage());
            return [];
        }

        $groups = [];

        $mainIdentity = trim((string) $request->input('data_id_number', ''));
        if ($mainIdentity !== '') {
            $groups[$mainIdentity] = ['label' => 'البيانات الأساسية', 'flag' => 'basic_required'];
        }

        $fatherIdentity = trim((string) $request->input('father_id', ''));
        if ($fatherIdentity !== '') {
            $groups[$fatherIdentity] = ['label' => 'الأب المتوفى', 'flag' => 'deceased_required'];
        }

        $motherIdentity = trim((string) ($request->input('deceased_mother_id') ?: $request->input('mother_id', '')));
        if ($motherIdentity !== '') {
            $groups[$motherIdentity] = ['label' => 'الأم المتوفاة', 'flag' => 'deceased_required'];
        }

        foreach ((array) $request->input('family_members', []) as $index => $member) {
            if (!is_array($member)) {
                continue;
            }

            $identity = trim((string) ($member['person_id'] ?? ''));
            if ($identity === '') {
                $identity = $this->buildFamilyFallbackIdentity($fileIdNumber, (int) $index);
            }

            $groups[$identity] = ['label' => 'فرد أسرة', 'flag' => 'family_required'];
        }

        $identities = array_keys($groups);
        if (empty($identities)) {
            return [];
        }

        $existingCoverage = [];
        Attachment::whereIn('person_identity_number', $identities)
            ->get(['person_identity_number', 'file_type'])
            ->each(function ($attachment) use (&$existingCoverage) {
                $existingCoverage[(string) $attachment->person_identity_number][strtolower(trim((string) $attachment->file_type))] = true;
            });

        $missing = [];
        foreach ($groups as $identity => $group) {
            $requiredFlag = $group['flag'];

            foreach ($documentTypes as $documentType) {
                if (!(int) $documentType->{$requiredFlag}) {
                    continue;
                }

                $pref = strtolower(trim((string) $documentType->pref));
                if ($pref === '') {
                    continue;
                }

                $isCovered = !empty($coveredAttachments[(string) $identity][$pref])
                    || !empty($existingCoverage[(string) $identity][$pref]);

                if ($isCovered) {
                    continue;
                }

                $missing[] = [
                    'index' => null,
                    'file_type' => (string) $documentType->pref,
                    'person_identity_number' => (string) $identity,
                    'reason' => 'missing_required_document',
                    'message' => "وثيقة إلزامية غير مرفوعة ({$documentType->pref}) — {$group['label']}.",
                ];
            }
        }

        return $missing;
    }

    /**
     * إخفاء جزء من القيمة وإبقاء آخر عدد من الخانات (هاتف/هوية/IBAN).
     */
    private function maskTail($value, int $keep): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $length = mb_strlen($value);

        if ($length <= $keep) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $keep) . mb_substr($value, $length - $keep);
    }

    /**
     * تقليل بيانات الحساب البنكي قبل إرجاعه في الاستجابات العامة.
     */
    private function maskBankAccountForPublic(array $account): array
    {
        $account['iban_usd'] = $this->maskTail($account['iban_usd'] ?? '', 4);
        $account['iban_shekel'] = $this->maskTail($account['iban_shekel'] ?? '', 4);
        $account['person_owner_identity_number'] = $this->maskTail($account['person_owner_identity_number'] ?? '', 3);
        $account['re_phone_number'] = $this->maskTail($account['re_phone_number'] ?? '', 3);

        return $account;
    }

    /**
     * البحث الشامل في جميع الجداول - محسّن بـ NormalizedSearchService
     */
    public function searchAllTables(Request $request)
    {
        $startTime = microtime(true);

        try {
            $searchTerm = $request->input('search_term');

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الرجاء إدخال كلمة البحث'
                ]);
            }

            $results = [
                'found' => false,
                'has_account' => false,
                'data' => [],
                'message' => '',
                'search_time' => 0
            ];

            // تحديد نوع البحث: رقم هوية (8-10 أرقام) أو اسم
            $isIdNumber = is_numeric($searchTerm) && strlen($searchTerm) >= 8 && strlen($searchTerm) <= 10;

            if ($isIdNumber) {
                // البحث برقم الهوية (9 أرقام) في الجداول: re_people, data, dead_people, persons (civil registry)

                // 1. البحث في جدول re_people
                $rePeopleResult = $this->searchInRePeopleOptimized($searchTerm);
                if ($rePeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 're_people';
                    $results['data'] = $rePeopleResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 2. البحث في جدول data
                $dataResult = $this->searchInDataTableOptimized($searchTerm);
                if ($dataResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 'data';
                    $results['data'] = $dataResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً. يرجى تسجيل الدخول.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 3. البحث في جدول dead_people
                $deadPeopleResult = $this->searchInDeadPeopleOptimized($searchTerm);
                if ($deadPeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = false;
                    $results['source'] = 'dead_people';
                    $results['data'] = $deadPeopleResult;
                    $results['message'] = 'تم العثور على سجل في قائمة المتوفين.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 4. البحث في جدول persons في civilregistry
                $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
                $civilRegistryResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);
            } else {
                // البحث بالاسم في الجداول: dead_people, data, re_people فقط

                // 1. البحث في جدول dead_people
                $deadPeopleResult = $this->searchInDeadPeopleOptimized($searchTerm);
                if ($deadPeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = false;
                    $results['source'] = 'dead_people';
                    $results['data'] = $deadPeopleResult;
                    $results['message'] = 'تم العثور على سجل في قائمة المتوفين.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 2. البحث في جدول data
                $dataResult = $this->searchInDataTableOptimized($searchTerm);
                if ($dataResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 'data';
                    $results['data'] = $dataResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً. يرجى تسجيل الدخول.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 3. البحث في جدول re_people
                $rePeopleResult = $this->searchInRePeopleOptimized($searchTerm);
                if ($rePeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 're_people';
                    $results['data'] = $rePeopleResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // لا نبحث في السجل المدني للأسماء
                $civilRegistryResults = null;
            }

            // معالجة نتائج السجل المدني (فقط لرقم الهوية)

            if ($civilRegistryResults && $civilRegistryResults->isNotEmpty()) {
                $personsResult = $civilRegistryResults->first();

                $results['found'] = true;
                $results['has_account'] = false;
                $results['source'] = 'civil_registry';

                // تحويل تاريخ الميلاد بشكل صحيح بالميلادي
                $birthDate = null;
                $birthDateDisplay = null;
                if ($personsResult->CI_BIRTH_DT) {
                    try {
                        $carbonDate = null;
                        if ($personsResult->CI_BIRTH_DT instanceof \Carbon\Carbon) {
                            $carbonDate = $personsResult->CI_BIRTH_DT;
                        } elseif (is_string($personsResult->CI_BIRTH_DT)) {
                            $carbonDate = \Carbon\Carbon::parse($personsResult->CI_BIRTH_DT);
                        }

                        if ($carbonDate) {
                            $birthDate = $carbonDate->format('Y-m-d');
                            $birthDateDisplay = $carbonDate->format('Y/m/d');
                        }
                    } catch (\Exception $e) {
                        Log::warning('خطأ في تحويل تاريخ الميلاد: ' . $e->getMessage());
                    }
                }

                // جلب اسم المدينة
                $cityName = null;
                if (isset($personsResult->CITY) && $personsResult->CITY) {
                    $city = \App\Models\City::find($personsResult->CITY);
                    $cityName = $city ? $city->city : null;
                }

                // جلب الحالة الاجتماعية
                $maritalStatusName = null;
                if (isset($personsResult->CI_PERSONAL_CD) && $personsResult->CI_PERSONAL_CD) {
                    $maritalStatus = \App\Models\CI_PERSONAL_CD::find($personsResult->CI_PERSONAL_CD);
                    $maritalStatusName = $maritalStatus ? $maritalStatus->CI_PERSONAL_CD : null;
                }

                $results['data'] = [
                    'id_number' => $personsResult->id_number ?? $personsResult->CI_ID_NUM,
                    'first_name' => $personsResult->CI_FIRST_ARB ?? null,
                    'father_name' => $personsResult->CI_FATHER_ARB ?? null,
                    'grand_father_name' => $personsResult->CI_GRAND_FATHER_ARB ?? null,
                    'family_name' => $personsResult->CI_FAMILY_ARB ?? null,
                    'mother_name' => $personsResult->MOTHER_NAME1 ?? null,
                    'birth_date' => $birthDate,
                    'birth_date_display' => $birthDateDisplay,
                    'birth_date_raw' => $personsResult->CI_BIRTH_DT ?? null,
                    'gender' => $personsResult->CI_SEX_CD ?? null,
                    'marital_status' => $personsResult->CI_PERSONAL_CD ?? null,
                    'marital_status_name' => $maritalStatusName,
                    'city' => $personsResult->CITY ?? null,
                    'city_name' => $cityName,
                    'street' => $personsResult->STREET ?? null,
                    'house_no' => $personsResult->HOUSE_NO ?? null,
                    'full_name' => trim(
                        ($personsResult->CI_FIRST_ARB ?? '') . ' ' .
                        ($personsResult->CI_FATHER_ARB ?? '') . ' ' .
                        ($personsResult->CI_GRAND_FATHER_ARB ?? '') . ' ' .
                        ($personsResult->CI_FAMILY_ARB ?? '')
                    ),
                    'type' => 'موجود في السجل المدني'
                ];
                $results['message'] = 'لا يوجد بيانات لهذه الأسرة. يمكنك المتابعة لإنشاء حساب جديد.';
                $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                return response()->json($results);
            }

            // إذا لم يتم العثور على نتائج
            $results['message'] = 'لم يتم العثور على أي بيانات مطابقة. يرجى التأكد من رقم الهوية والمحاولة مرة أخرى.';
            $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
            return response()->json($results);

        } catch (\Exception $e) {
            Log::error('خطأ في البحث: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage(),
                'search_time' => round((microtime(true) - $startTime) * 1000, 2) . ' ms'
            ], 500);
        }
    }

    /**
     * البحث المحسّن في جدول data
     */
    private function searchInDataTableOptimized($searchTerm)
    {
        // إذا كان رقماً، ابحث في أرقام الهوية والملفات
        if (is_numeric($searchTerm)) {
            $result = \App\Models\Data::where('data_id_number', $searchTerm)
                ->orWhere('file_id_number', $searchTerm)
                ->first();

            if ($result) {
                return [
                    'id_number' => $result->data_id_number,
                    'full_name' => trim(
                        ($result->data_first_name ?? '') . ' ' .
                        ($result->data_father_name ?? '') . ' ' .
                        ($result->data_grand_father_name ?? '') . ' ' .
                        ($result->data_family_name ?? '')
                    ),
                    'phone' => $this->maskTail($result->data_phone_number, 3),
                    'type' => 'معيل أسرة'
                ];
            }
        }

        // البحث بالاسم باستخدام NormalizedSearchService
        $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
        $results = $normalizedSearchService->searchDataTable($searchTerm, 1);

        if ($results && $results->isNotEmpty()) {
            $result = $results->first();
            return [
                'id_number' => $result->data_id_number,
                'full_name' => trim(
                    ($result->data_first_name ?? '') . ' ' .
                    ($result->data_father_name ?? '') . ' ' .
                    ($result->data_grand_father_name ?? '') . ' ' .
                    ($result->data_family_name ?? '')
                ),
                'phone' => $this->maskTail($result->data_phone_number ?? null, 3),
                'type' => 'معيل أسرة'
            ];
        }

        return null;
    }

    /**
     * البحث المحسّن في جدول re_people
     */
    private function searchInRePeopleOptimized($searchTerm)
    {
        // البحث برقم الهوية أولاً (أسرع)
        if (is_numeric($searchTerm) && strlen($searchTerm) >= 9) {
            $result = \App\Models\RePeople::where('person_id', $searchTerm)->first();

            if ($result) {
                return [
                    'id_number' => $result->person_id,
                    'full_name' => trim(
                        ($result->first_name ?? '') . ' ' .
                        ($result->second_name ?? '') . ' ' .
                        ($result->third_name ?? '') . ' ' .
                        ($result->last_name ?? '')
                    ),
                    'type' => $result->person_type_id == 1 ? 'يتيم' : 'شخص مسجل'
                ];
            }
        }

        // البحث بالاسم
        $normalizedTerm = normalizeArabicText($searchTerm);
        $result = \App\Models\RePeople::where(function($query) use ($normalizedTerm) {
            $query->where('first_name', 'LIKE', $normalizedTerm . '%')
                  ->orWhere('last_name', 'LIKE', $normalizedTerm . '%');
        })->first();

        if ($result) {
            return [
                'id_number' => $result->person_id,
                'full_name' => trim(
                    ($result->first_name ?? '') . ' ' .
                    ($result->second_name ?? '') . ' ' .
                    ($result->third_name ?? '') . ' ' .
                    ($result->last_name ?? '')
                ),
                'type' => $result->person_type_id == 1 ? 'يتيم' : 'شخص مسجل'
            ];
        }

        return null;
    }

    /**
     * البحث المحسّن في جدول dead_people
     */
    private function searchInDeadPeopleOptimized($searchTerm)
    {
        // البحث برقم الهوية (الأب أو الأم)
        if (is_numeric($searchTerm) && strlen($searchTerm) >= 9) {
            $result = \App\Models\DeadPepole::where('father_id', $searchTerm)
                ->orWhere('mother_id', $searchTerm)
                ->first();

            if ($result) {
                $fullName = '';
                if ($result->father_id == $searchTerm) {
                    $fullName = trim(
                        ($result->father_first_name ?? '') . ' ' .
                        ($result->father_second_name ?? '') . ' ' .
                        ($result->father_third_name ?? '') . ' ' .
                        ($result->father_last_name ?? '')
                    );
                } else {
                    $fullName = trim(
                        ($result->mother_first_name ?? '') . ' ' .
                        ($result->mother_second_name ?? '') . ' ' .
                        ($result->mother_third_name ?? '') . ' ' .
                        ($result->mother_last_name ?? '')
                    );
                }

                return [
                    'id_number' => $searchTerm,
                    'full_name' => $fullName,
                    'type' => 'متوفى'
                ];
            }
        }

        // البحث بالاسم
        $normalizedTerm = normalizeArabicText($searchTerm);
        $result = \App\Models\DeadPepole::where(function($query) use ($normalizedTerm) {
            $query->where('father_first_name', 'LIKE', '%' . $normalizedTerm . '%')
                  ->orWhere('father_last_name', 'LIKE', '%' . $normalizedTerm . '%')
                  ->orWhere('mother_first_name', 'LIKE', '%' . $normalizedTerm . '%')
                  ->orWhere('mother_last_name', 'LIKE', '%' . $normalizedTerm . '%');
        })->first();

        if ($result) {
            $fullName = '';
            if (!empty($result->father_first_name)) {
                $fullName = trim(
                    ($result->father_first_name ?? '') . ' ' .
                    ($result->father_second_name ?? '') . ' ' .
                    ($result->father_third_name ?? '') . ' ' .
                    ($result->father_last_name ?? '')
                );
            } else {
                $fullName = trim(
                    ($result->mother_first_name ?? '') . ' ' .
                    ($result->mother_second_name ?? '') . ' ' .
                    ($result->mother_third_name ?? '') . ' ' .
                    ($result->mother_last_name ?? '')
                );
            }

            return [
                'id_number' => $result->father_id ?? $result->mother_id,
                'full_name' => $fullName,
                'type' => 'متوفى'
            ];
        }

        return null;
    }

    /**
     * ملف الحقول تلقائياً من السجل المدني
     */
    public function fillFromCivilRegistry(Request $request)
    {
        try {
            $idNumber = $request->input('id_number');

            if (empty($idNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الرجاء إدخال رقم الهوية'
                ]);
            }

            // البحث في السجل المدني باستخدام NormalizedSearchService (أسرع)
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $results = $normalizedSearchService->searchCivilRegistry($idNumber, 1);

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يلا يوجد بيانات لهذه الأسرة'
                ]);
            }

            $person = $results->first();

            // تحويل تاريخ الميلاد بشكل صحيح بالميلادي
            $birthDate = null;
            if ($person->CI_BIRTH_DT) {
                try {
                    $carbonDate = null;
                    if ($person->CI_BIRTH_DT instanceof \Carbon\Carbon) {
                        $carbonDate = $person->CI_BIRTH_DT;
                    } elseif (is_string($person->CI_BIRTH_DT)) {
                        $carbonDate = \Carbon\Carbon::parse($person->CI_BIRTH_DT);
                    }

                    if ($carbonDate) {
                        $birthDate = $carbonDate->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    Log::warning('خطأ في تحويل تاريخ الميلاد: ' . $e->getMessage());
                }
            }

            // جلب اسم المدينة
            $cityName = null;
            if ($person->CITY) {
                $city = \App\Models\City::find($person->CITY);
                $cityName = $city ? $city->city : null;
            }

            // إرجاع البيانات
            return response()->json([
                'success' => true,
                'data' => [
                    'data_id_number' => $person->id_number ?? $person->CI_ID_NUM,
                    'data_first_name' => $person->CI_FIRST_ARB,
                    'data_father_name' => $person->CI_FATHER_ARB,
                    'data_grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                    'data_family_name' => $person->CI_FAMILY_ARB,
                    'data_birth_date' => $birthDate,
                    'data_gender' => $person->CI_SEX_CD,
                    'data_marital_status' => $person->CI_PERSONAL_CD,
                    'data_city' => $person->CITY,
                    'data_city_name' => $cityName,
                    'data_current_address' => $person->STREET ?? null,
                    'mother_name' => $person->MOTHER_NAME1 ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب البيانات من السجل المدني: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🆕 التحقق من وجود المعيل وجلب بياناته من الجداول المختلفة
     * هذه الدالة تُستخدم عبر API لجلب بيانات المعيل الموجود مسبقاً
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkExistingGuardian(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');

            if (empty($identityNumber) || strlen($identityNumber) != 9) {
                return response()->json([
                    'success' => false,
                    'exists' => false,
                    'message' => 'رقم الهوية يجب أن يكون 9 أرقام'
                ]);
            }

            $result = [
                'exists' => false,
                'source' => null,
                'file_id_number' => null,
                'guardian_data' => null,
                'bank_accounts' => [],
                'message' => ''
            ];

            // 1️⃣ البحث في جدول data (المعيلين) - أحدث سجل عند تكرار الهوية
            $dataRecord = app(GuardianFileService::class)->findLatestDataByIdentity($identityNumber);

            if ($dataRecord) {
                $result['exists'] = true;
                $result['source'] = 'data';
                $result['file_id_number'] = $dataRecord->file_id_number;
                $result['guardian_data'] = [
                    'file_id_number' => $dataRecord->file_id_number,
                    'identity_number' => $dataRecord->data_id_number,
                    'first_name' => $dataRecord->data_first_name,
                    'father_name' => $dataRecord->data_father_name,
                    'grand_father_name' => $dataRecord->data_grand_father_name,
                    'family_name' => $dataRecord->data_family_name,
                    'full_name' => trim(
                        ($dataRecord->data_first_name ?? '') . ' ' .
                        ($dataRecord->data_father_name ?? '') . ' ' .
                        ($dataRecord->data_grand_father_name ?? '') . ' ' .
                        ($dataRecord->data_family_name ?? '')
                    ),
                    'phone_number' => $this->maskTail($dataRecord->data_phone_number, 3),
                    'alt_phone_number' => $this->maskTail($dataRecord->data_alt_phone_number, 3),
                    'birth_date' => $dataRecord->data_birth_date,
                    'gender' => $dataRecord->data_gender,
                    'marital_status' => $dataRecord->data_marital_status,
                    'province' => $dataRecord->data_province,
                    'city' => $dataRecord->data_city,
                    'current_address' => $dataRecord->data_current_address,
                    'section_id' => $dataRecord->data_section_id,
                ];

                // جلب الحسابات البنكية
                $bankAccounts = GuardianBankAccount::whereIn(
                    'guardian_registration',
                    app(GuardianFileService::class)->fileCandidates((string) $dataRecord->file_id_number)
                )->get();
                $result['bank_accounts'] = $bankAccounts->map(function($account) {
                    return $this->maskBankAccountForPublic([
                        'id' => $account->id,
                        'bank_name' => $account->bank_name,
                        'iban_usd' => $account->iban_usd,
                        'iban_shekel' => $account->iban_shekel,
                        'person_owner_identity_number' => $account->person_owner_identity_number,
                        're_guardian_name' => $account->re_guardian_name,
                        're_phone_number' => $account->re_phone_number,
                        'check_account' => $account->check_account,
                    ]);
                })->toArray();

                $result['message'] = ''; // سياسة العمل: لا نكشف للمستخدم وجود بيانات قديمة

                Log::info('✅ تم العثور على معيل موجود في جدول data', [
                    'identity' => $this->maskTail($identityNumber, 3),
                    'file_id_number' => $dataRecord->file_id_number,
                    'bank_accounts_count' => count($result['bank_accounts'])
                ]);

                return response()->json($result);
            }

            // 2️⃣ البحث في جدول dead_people (الأب أو الأم المتوفي)
            $deadRecord = DeadPepole::where('father_id', $identityNumber)
                ->orWhere('mother_id', $identityNumber)
                ->first();

            if ($deadRecord) {
                $isFather = ($deadRecord->father_id == $identityNumber);
                $result['exists'] = true;
                $result['source'] = $isFather ? 'dead_people_father' : 'dead_people_mother';
                $result['file_id_number'] = $deadRecord->re_file_id;

                $result['guardian_data'] = [
                    'file_id_number' => $deadRecord->re_file_id,
                    'identity_number' => $identityNumber,
                    'first_name' => $isFather ? $deadRecord->father_first_name : $deadRecord->mother_first_name,
                    'father_name' => $isFather ? $deadRecord->father_second_name : $deadRecord->mother_second_name,
                    'grand_father_name' => $isFather ? $deadRecord->father_third_name : $deadRecord->mother_third_name,
                    'family_name' => $isFather ? $deadRecord->father_last_name : $deadRecord->mother_last_name,
                    'full_name' => $isFather ?
                        trim(
                            ($deadRecord->father_first_name ?? '') . ' ' .
                            ($deadRecord->father_second_name ?? '') . ' ' .
                            ($deadRecord->father_third_name ?? '') . ' ' .
                            ($deadRecord->father_last_name ?? '')
                        ) :
                        trim(
                            ($deadRecord->mother_first_name ?? '') . ' ' .
                            ($deadRecord->mother_second_name ?? '') . ' ' .
                            ($deadRecord->mother_third_name ?? '') . ' ' .
                            ($deadRecord->mother_last_name ?? '')
                        ),
                    'death_date' => $isFather ? $deadRecord->father_death_date : $deadRecord->mother_death_date,
                    'death_reason' => $isFather ? $deadRecord->father_death_reason : $deadRecord->mother_death_reason,
                    'is_deceased' => true,
                    'deceased_type' => $isFather ? 'father' : 'mother',
                ];

                // جلب البيانات الإضافية من portal_general_registration_field_values
                $portalFields = \App\Models\PortalGeneralRegistrationFieldValue::where('file_id_number', $deadRecord->re_file_id)
                    ->orWhere('identity_number', $identityNumber)
                    ->get()
                    ->keyBy('field_key');

                if ($portalFields->isNotEmpty()) {
                    $result['guardian_data']['phone_number'] = $this->maskTail($portalFields->get('phone_number')?->field_value, 3);
                    $result['guardian_data']['alt_phone_number'] = $this->maskTail($portalFields->get('alt_phone_number')?->field_value, 3);
                    $result['guardian_data']['current_address'] = $portalFields->get('current_address')?->field_value;
                    $result['guardian_data']['city'] = $portalFields->get('city')?->field_value;
                    $result['guardian_data']['province'] = $portalFields->get('province')?->field_value;
                }

                // جلب الحسابات البنكية
                $bankAccounts = GuardianBankAccount::where('guardian_registration', $deadRecord->re_file_id)->get();
                $result['bank_accounts'] = $bankAccounts->map(function($account) {
                    return $this->maskBankAccountForPublic([
                        'id' => $account->id,
                        'bank_name' => $account->bank_name,
                        'iban_usd' => $account->iban_usd,
                        'iban_shekel' => $account->iban_shekel,
                        'person_owner_identity_number' => $account->person_owner_identity_number,
                        're_guardian_name' => $account->re_guardian_name,
                        're_phone_number' => $account->re_phone_number,
                        'check_account' => $account->check_account,
                    ]);
                })->toArray();

                $result['message'] = ''; // سياسة العمل: لا نكشف للمستخدم وجود بيانات قديمة

                Log::info('✅ تم العثور على شخص في جدول dead_people', [
                    'identity' => $this->maskTail($identityNumber, 3),
                    'file_id_number' => $deadRecord->re_file_id,
                    'type' => $isFather ? 'father' : 'mother',
                    'bank_accounts_count' => count($result['bank_accounts'])
                ]);

                return response()->json($result);
            }

            // 3️⃣ لم يتم العثور على الشخص
            $result['message'] = ''; // سياسة العمل: لا نكشف للمستخدم وجود بيانات قديمة أو عدمها

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في التحقق من وجود المعيل', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'exists' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث الشامل عن المعيل: يجلب كل البيانات من كل الجداول المرتبطة
     */
    public function lookupGuardian(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');

            if (empty($identityNumber) || strlen($identityNumber) < 9) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهوية يجب أن يكون 9 أرقام على الأقل'
                ]);
            }

            // 🆕 أولاً: البحث عن ملف المعيل في قاعدة البيانات المركزية (جدول data)
            $guardianFileService = app(GuardianFileService::class);
            $existingGuardian = $guardianFileService->findLatestDataByIdentity((string) $identityNumber);

            if ($existingGuardian) {
                $payload = $guardianFileService->buildGuardianPayload($existingGuardian);
                $payload['phone_number'] = $this->maskTail($payload['phone_number'] ?? '', 3);
                $payload['data_phone_number'] = $this->maskTail($payload['data_phone_number'] ?? '', 3);
                $payload['data_alt_phone_number'] = $this->maskTail($payload['data_alt_phone_number'] ?? '', 3);

                return response()->json([
                    'success' => true,
                    'source' => 'data',
                    'exists' => true,
                    'file_id_number' => str_pad((string) $existingGuardian->file_id_number, 6, '0', STR_PAD_LEFT),
                    'data' => $payload,
                    'message' => '', // سياسة العمل: لا نكشف للمستخدم وجود بيانات قديمة
                ]);
            }

            // البحث في السجل المدني فقط
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $civilResults = $normalizedSearchService->searchCivilRegistry($identityNumber, 1);

            if ($civilResults && $civilResults->isNotEmpty()) {
                $person = $civilResults->first();
                $birthDate = null;
                if ($person->CI_BIRTH_DT) {
                    try {
                        $carbonDate = $person->CI_BIRTH_DT instanceof \Carbon\Carbon
                            ? $person->CI_BIRTH_DT
                            : \Carbon\Carbon::parse($person->CI_BIRTH_DT);
                        $birthDate = $carbonDate->format('Y-m-d');
                    } catch (\Exception $e) {}
                }
                return response()->json([
                    'success' => true,
                    'source' => 'civil_registry',
                    'data' => [
                        'data_id_number' => $person->id_number,
                        'data_first_name' => $person->CI_FIRST_ARB,
                        'data_father_name' => $person->CI_FATHER_ARB,
                        'data_grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                        'data_family_name' => $person->CI_FAMILY_ARB,
                        'data_birth_date' => $birthDate,
                        'data_gender' => $person->CI_SEX_CD,
                        'is_alive' => empty($person->CI_DEAD_DT),
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'source' => 'not_found',
                'exists' => false,
                'data' => null,
                'message' => 'لم يتم العثور على بيانات لهذا الرقم في السجل المدني.'
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في البحث عن المعيل: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث عن بيانات الأم برقم الهوية في السجل المدني
     */
    public function lookupMother(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');

            if (empty($identityNumber) || strlen($identityNumber) < 9) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهوية يجب أن يكون 9 أرقام على الأقل'
                ]);
            }

            // البحث في السجل المدني
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $results = $normalizedSearchService->searchCivilRegistry($identityNumber, 1);

            if ($results && $results->isNotEmpty()) {
                $person = $results->first();
                $birthDate = null;
                if ($person->CI_BIRTH_DT) {
                    try {
                        $carbonDate = $person->CI_BIRTH_DT instanceof \Carbon\Carbon
                            ? $person->CI_BIRTH_DT
                            : \Carbon\Carbon::parse($person->CI_BIRTH_DT);
                        $birthDate = $carbonDate->format('Y-m-d');
                    } catch (\Exception $e) {}
                }
                return response()->json([
                    'success' => true,
                    'data' => [
                        'mother_id' => $person->id_number,
                        'mother_first_name' => $person->CI_FIRST_ARB,
                        'mother_second_name' => $person->CI_FATHER_ARB,
                        'mother_third_name' => $person->CI_GRAND_FATHER_ARB,
                        'mother_last_name' => $person->CI_FAMILY_ARB,
                        'mother_birth_date' => $birthDate,
                        'mother_gender' => $person->CI_SEX_CD,
                        'is_alive' => empty($person->CI_DEAD_DT),
                        'death_date' => !empty($person->CI_DEAD_DT) ? $person->CI_DEAD_DT : null,
                    ],
                    'message' => 'تم جلب بيانات الأم من السجل المدني.'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'لم يتم العثور على بيانات لهذا الرقم في السجل المدني.'
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في البحث عن الأم: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * التحقق مما إذا كان الشخص مرتبطاً بمعيل آخر
     */
    public function checkPersonLinked(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');
            $currentFileId = $request->input('current_file_id');

            if (empty($identityNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهوية مطلوب'
                ]);
            }

            // فحص في جدول data (هل هو معيل لملف آخر؟) - أحدث سجل عند تكرار الهوية
            $dataRecord = app(GuardianFileService::class)->findLatestDataByIdentity($identityNumber);
            if ($dataRecord && $dataRecord->file_id_number != $currentFileId) {
                return response()->json([
                    'success' => true,
                    'is_linked' => true,
                    'message' => 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.'
                ]);
            }

            // فحص في جدول re_people (هل هو فرد أسرة في ملف آخر؟)
            $rePeopleRecord = RePeople::where('person_id', $identityNumber)
                ->where('registration_id', '!=', $currentFileId)
                ->first();
            if ($rePeopleRecord) {
                return response()->json([
                    'success' => true,
                    'is_linked' => true,
                    'message' => 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.'
                ]);
            }

            return response()->json([
                'success' => true,
                'is_linked' => false,
                'message' => ''
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في التحقق من ربط الشخص: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق'
            ], 500);
        }
    }

    /**
     * جلب بيانات المعيل الموجود مع الحسابات البنكية لعرضها في النموذج
     */
    public function getGuardianWithBankAccounts(Request $request)
    {
        try {
            $fileIdNumber = $request->input('file_id_number');
            $identityNumber = $request->input('identity_number');

            if (empty($fileIdNumber) && empty($identityNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب توفير رقم الملف أو رقم الهوية'
                ]);
            }

            // البحث بالأولوية: رقم الملف ثم رقم الهوية
            $guardian = null;

            if (!empty($fileIdNumber)) {
                $guardian = Data::where('file_id_number', $fileIdNumber)->first();
            }

            if (!$guardian && !empty($identityNumber)) {
                $guardian = app(GuardianFileService::class)->findLatestDataByIdentity((string) $identityNumber);
            }

            if (!$guardian) {
                return response()->json([
                    'success' => false,
                    'message' => '', // سياسة العمل: لا نكشف للمستخدم وجود بيانات قديمة أو عدمها
                ]);
            }

            // جلب الحسابات البنكية
            $bankAccounts = GuardianBankAccount::whereIn(
                'guardian_registration',
                app(GuardianFileService::class)->fileCandidates((string) $guardian->file_id_number)
            )
                ->with(['bankNameRelation'])
                ->get();

            return response()->json([
                'success' => true,
                'guardian' => [
                    'file_id_number' => $guardian->file_id_number,
                    'identity_number' => $guardian->data_id_number,
                    'full_name' => trim(
                        ($guardian->data_first_name ?? '') . ' ' .
                        ($guardian->data_father_name ?? '') . ' ' .
                        ($guardian->data_grand_father_name ?? '') . ' ' .
                        ($guardian->data_family_name ?? '')
                    ),
                    'phone_number' => $this->maskTail($guardian->data_phone_number, 3),
                    'alt_phone_number' => $this->maskTail($guardian->data_alt_phone_number, 3),
                ],
                'bank_accounts' => $bankAccounts->map(function($account) {
                    return $this->maskBankAccountForPublic([
                        'id' => $account->id,
                        'bank_name' => $account->bank_name,
                        'bank_description' => $account->bankNameRelation?->description ?? '',
                        'iban_usd' => $account->iban_usd,
                        'iban_shekel' => $account->iban_shekel,
                        'person_owner_identity_number' => $account->person_owner_identity_number,
                        're_guardian_name' => $account->re_guardian_name,
                        're_phone_number' => $account->re_phone_number,
                        'check_account' => $account->check_account,
                    ]);
                })->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب بيانات المعيل مع الحسابات البنكية', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حفظ بيانات الأم الحية في portal_general_registration_field_values
     * 
     * @param Request $request
     * @param string $fileIdNumber رقم الملف
     * @param string|null $motherId رقم هوية الأم (إذا كانت المعيل ليست الأم)
     * @param bool $guardianIsMother هل المعيل هو الأم؟
     */
    private function saveLivingMotherToPortal(Request $request, string $fileIdNumber, ?string $motherId, bool $guardianIsMother = false): void
    {
        if ($guardianIsMother) {
            // ═══════════════════════════════════════════════════════════
            // الحالة 1: المعيل هو الأم — نسخ بيانات المعيل من جدول data
            // ═══════════════════════════════════════════════════════════
            $guardianData = \App\Models\Data::where('file_id_number', $fileIdNumber)->first();

            if ($guardianData) {
                $portalFields = [
                    'field_mother_status' => 'حية',
                    'field_living_mother_id' => $guardianData->data_id_number,
                    'field_living_mother_first_name' => $guardianData->data_first_name,
                    'field_living_mother_second_name' => $guardianData->data_father_name,
                    'field_living_mother_third_name' => $guardianData->data_grand_father_name,
                    'field_living_mother_last_name' => $guardianData->data_family_name,
                ];
                $guardianIdentity = $guardianData->data_id_number;
            } else {
                Log::warning('⚠️ لم يتم العثور على بيانات المعيل لنسخها كبيانات الأم', [
                    'file_id' => $fileIdNumber
                ]);
                return;
            }
        } else {
            // ═══════════════════════════════════════════════════════════
            // الحالة 2: المعيل ليس الأم — استخدام بيانات الأم من النموذج
            // ═══════════════════════════════════════════════════════════
            $portalFields = [
                'field_mother_status' => 'حية',
                'field_living_mother_id' => $motherId,
                'field_living_mother_first_name' => $request->input('mother_first_name'),
                'field_living_mother_second_name' => $request->input('mother_second_name'),
                'field_living_mother_third_name' => $request->input('mother_third_name'),
                'field_living_mother_last_name' => $request->input('mother_last_name'),
                'field_living_mother_birth_date' => $request->input('mother_birth_date'),
           //     'field_living_mother_phone' => $request->input('mother_phone'),
            ];

            $guardianIdentity = $request->input('data_id_number');
        }
        // جلب sponsorship_id من جدول sponsorships (أحدث كفالة عند التكرار)
        $sponsorship = \App\Models\Sponsorship::where('identity_number', $guardianIdentity)
            ->orderByDesc('id')
            ->first();
        $sponsorshipId = $sponsorship?->id;
        $fieldIdentityNumber = $guardianIsMother
            ? $guardianIdentity
            : ($motherId ?: $guardianIdentity);

        foreach ($portalFields as $fieldKey => $fieldValue) {
            if (is_null($fieldValue) || $fieldValue === '') {
                continue;
            }

            \App\Models\PortalGeneralRegistrationFieldValue::updateOrCreate(
                [
                    'identity_number' => $fieldIdentityNumber,
                    'field_key' => $fieldKey,
                ],
                [
                    'file_id_number' => $fileIdNumber,
                    'identity_number' => $fieldIdentityNumber,
                    'sponsorship_id' => $sponsorshipId,
                    'field_value' => $fieldValue,
                    'updated_by_user_id' => auth()->id(),
                ]
            );
        }

        Log::info('✅ تم حفظ بيانات الأم الحية في portal_general_registration_field_values', [
            'guardian_is_mother' => $guardianIsMother,
            'file_id' => $fileIdNumber,
            'fields_count' => count(array_filter($portalFields, fn($v) => !is_null($v) && $v !== ''))
        ]);
    }

    /**
     * حفظ بيانات الأم المتوفية في portal_general_registration_field_values
     */
    private function saveDeceasedMotherToPortal(Request $request, string $fileIdNumber, ?string $motherId): void
    {
        $guardianIdentity = $request->input('data_id_number');

        $portalFields = [
            'field_mother_status' => 'متوفية',
            'field_mother_id' => $motherId,
            'field_mother_first_name' => $request->input('deceased_mother_first_name'),
            'field_mother_second_name' => $request->input('deceased_mother_second_name'),
            'field_mother_third_name' => $request->input('deceased_mother_third_name'),
            'field_mother_last_name' => $request->input('deceased_mother_last_name'),
            'field_mother_death_date' => $request->input('mother_death_date'),
            'field_mother_death_reason' => $request->input('mother_death_reason'),
        ];

        $sponsorship = \App\Models\Sponsorship::where('identity_number', $guardianIdentity)
            ->orderByDesc('id')
            ->first();
        $sponsorshipId = $sponsorship?->id;
        $fieldIdentityNumber = $motherId ?: $guardianIdentity;

        foreach ($portalFields as $fieldKey => $fieldValue) {
            if (is_null($fieldValue) || $fieldValue === '') {
                continue;
            }

            \App\Models\PortalGeneralRegistrationFieldValue::updateOrCreate(
                [
                    'identity_number' => $fieldIdentityNumber,
                    'field_key' => $fieldKey,
                ],
                [
                    'file_id_number' => $fileIdNumber,
                    'identity_number' => $fieldIdentityNumber,
                    'sponsorship_id' => $sponsorshipId,
                    'field_value' => $fieldValue,
                    'updated_by_user_id' => auth()->id(),
                ]
            );
        }

        Log::info('✅ تم حفظ بيانات الأم المتوفية في portal_general_registration_field_values', [
            'file_id' => $fileIdNumber,
            'mother_id' => $motherId,
            'fields_count' => count(array_filter($portalFields, fn($v) => !is_null($v) && $v !== ''))
        ]);
    }
}
