<?php

namespace App\Services;

use App\Http\Controllers\Users\GeneralRegistrationController;
use App\Models\RePeople;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppFlowService
{
    public const MENU = "أهلاً بك في مؤسسة الأيتام.\n(1) تسجيل أو تحديث ملف يتيم\n(2) اسأل سؤالاً\n(0) إلغاء";

    private const TTL = 1800;

    private const GUARDIAN_STEPS = [
        'reg_first_name' => 'data_first_name',
        'reg_father_name' => 'data_father_name',
        'reg_grand_name' => 'data_grand_father_name',
        'reg_family_name' => 'data_family_name',
        'reg_birth' => 'data_birth_date',
        'reg_gender' => 'data_gender',
        'reg_phone' => 'data_phone_number',
        'reg_address' => 'data_current_address',
        'reg_city' => 'data_city',
        'reg_province' => 'data_province',
    ];

    private const ZERO_CANCEL_EXEMPT = ['reg_father_dead', 'reg_orphan_id', 'reg_phone', 'reg_attachments', 'reg_attach_person', 'reg_attach_send', 'faq_mode'];

    private const MAX_ATTACHMENTS = 10;

    public function __construct(private WhatsAppMediaService $media, private WhatsAppKnowledgeService $knowledge)
    {
    }

    public function menu(): string
    {
        return self::MENU;
    }

    public function hasState(string $phone): bool
    {
        return $this->load($phone) !== null;
    }

    public function handle(string $phone, string $text, bool $isFirstContact): ?string
    {
        $text = trim($text);
        $state = $this->load($phone);

        if ($state === null) {
            return $this->idle($phone, $text, $isFirstContact);
        }

        $state['phone'] = $phone;

        if ($text === 'إلغاء') {
            $this->forget($phone);

            return 'تم إلغاء التسجيل. للمتابعة أرسل 1.';
        }

        try {
            $reply = $this->advance($phone, $state, $text);
        } catch (\Throwable $e) {
            Log::error('فشل معالجة حوار واتساب', ['phone' => $phone, 'error' => $e->getMessage()]);
            $this->forget($phone);

            return 'حدث خطأ أثناء معالجة الرسالة. أرسل 1 للبدء من جديد.';
        }

        if (($state['done'] ?? false) === true) {
            $this->forget($phone);
        } else {
            $this->save($phone, $state);
        }

        return $reply;
    }

    private function idle(string $phone, string $text, bool $isFirstContact): ?string
    {
        if ($text === '1') {
            $this->save($phone, ['step' => 'reg_id', 'data' => [], 'orphans' => []]);

            return "لنبدأ تسجيل ملف يتيم.\n\nرقم هوية الوصي/الوصية (9 أرقام):";
        }

        if ($text === '2') {
            $this->save($phone, ['step' => 'faq_mode', 'data' => [], 'orphans' => []]);

            return "تفضل، اكتب سؤالك وسأجيبك 💬\n(أرسل 0 للعودة)";
        }

        if ($text === '0') {
            return 'تم الإلغاء. للمتابعة أرسل 1.';
        }

        return $isFirstContact ? self::MENU : null;
    }

    private function advance(string $phone, array &$state, string $text): string
    {
        $step = (string) ($state['step'] ?? '');

        if ($text === '0' && !in_array($step, self::ZERO_CANCEL_EXEMPT, true)) {
            $state['done'] = true;

            return 'تم إلغاء التسجيل. للمتابعة أرسل 1.';
        }

        return match ($step) {
            'reg_id' => $this->stepGuardianId($state, $text),

            'reg_first_name' => $this->stepSimple($state, 'data_first_name', $text, 'reg_father_name'),
            'reg_father_name' => $this->stepSimple($state, 'data_father_name', $text, 'reg_grand_name'),
            'reg_grand_name' => $this->stepSimple($state, 'data_grand_father_name', $text, 'reg_family_name'),
            'reg_family_name' => $this->stepSimple($state, 'data_family_name', $text, 'reg_birth'),
            'reg_birth' => $this->stepDate($state, 'data_birth_date', $text, 'reg_gender', true),
            'reg_gender' => $this->stepChoice($state, 'data_gender', $text, ['1', '2'], 'reg_phone'),
            'reg_phone' => $this->stepPhone($state, $phone, $text),
            'reg_address' => $this->stepSimple($state, 'data_current_address', $text, 'reg_city'),
            'reg_city' => $this->stepList($state, 'data_city', $text, 'city', 'city', 'اختر المدينة برقم:', 'reg_province'),
            'reg_province' => $this->stepList($state, 'data_province', $text, 'provinces', 'description', 'اختر المحافظة برقم:', 'reg_mother_status'),

            'reg_mother_status' => $this->stepMotherStatus($state, $text),
            'reg_mother_id' => $this->stepId($state, $this->motherKey($state, 'id'), $text, $this->motherNext($state, 'id')),
            'reg_mother_first' => $this->stepSimple($state, $this->motherKey($state, 'first'), $text, $this->motherNext($state, 'first')),
            'reg_mother_second' => $this->stepSimple($state, $this->motherKey($state, 'second'), $text, $this->motherNext($state, 'second')),
            'reg_mother_third' => $this->stepSimple($state, $this->motherKey($state, 'third'), $text, $this->motherNext($state, 'third')),
            'reg_mother_family' => $this->stepSimple($state, $this->motherKey($state, 'family'), $text, $this->motherNext($state, 'family')),
            'reg_mother_birth' => $this->stepDate($state, 'mother_birth_date', $text, 'reg_father_dead', true),
            'reg_mother_death_date' => $this->stepDate($state, 'mother_death_date', $text, 'reg_mother_death_reason', true),
            'reg_mother_death_reason' => $this->stepReason($state, 'mother_death_reason', $text, 'reg_father_dead'),

            'reg_father_dead' => $this->stepFatherDead($state, $text),
            'reg_father_id' => $this->stepId($state, 'father_id', $text, 'reg_father_first'),
            'reg_father_first' => $this->stepSimple($state, 'father_first_name', $text, 'reg_father_second'),
            'reg_father_second' => $this->stepSimple($state, 'father_second_name', $text, 'reg_father_third'),
            'reg_father_third' => $this->stepSimple($state, 'father_third_name', $text, 'reg_father_family'),
            'reg_father_family' => $this->stepSimple($state, 'father_last_name', $text, 'reg_father_death_date'),
            'reg_father_death_date' => $this->stepDate($state, 'father_death_date', $text, 'reg_father_death_reason', true),
            'reg_father_death_reason' => $this->stepReason($state, 'father_death_reason', $text, 'reg_orphan_id'),

            'reg_orphan_id' => $this->stepOrphanId($state, $text),
            'reg_orphan_first' => $this->stepSimple($state, 'orphan_draft.first_name', $text, 'reg_orphan_second'),
            'reg_orphan_second' => $this->stepSimple($state, 'orphan_draft.second_name', $text, 'reg_orphan_third'),
            'reg_orphan_third' => $this->stepSimple($state, 'orphan_draft.third_name', $text, 'reg_orphan_family'),
            'reg_orphan_family' => $this->stepSimple($state, 'orphan_draft.last_name', $text, 'reg_orphan_birth'),
            'reg_orphan_birth' => $this->stepDate($state, 'orphan_draft.person_birth_date', $text, 'reg_orphan_gender', true),
            'reg_orphan_gender' => $this->stepChoiceNested($state, 'orphan_draft.person_gender', $text, ['1', '2'], 'reg_orphan_sponsorship'),
            'reg_orphan_sponsorship' => $this->stepOrphanSponsorship($state, $text),

            'reg_attachments' => $this->stepAttachments($state, $text),
            'reg_attach_person' => $this->stepAttachPerson($state, $text),
            'reg_attach_send' => $this->stepAttachSend($state, $text),

            'faq_mode' => $this->stepFaq($state, $phone, $text),

            default => $this->dropUnexpected($state, $phone),
        };
    }

    private function stepFaq(array &$state, string $phone, string $text): string
    {
        if ($text === '0') {
            $state['done'] = true;

            return $this->menu();
        }

        return $this->knowledge->answer($phone, $text);
    }

    private function dropUnexpected(array &$state, string $phone): string
    {
        $state['done'] = true;
        $this->forget($phone);

        return 'تعذر متابعة المحادثة. أرسل 1 للبدء من جديد.';
    }

    private function unexpected(): string
    {
        return 'تعذر متابعة المحادثة. أرسل 1 للبدء من جديد.';
    }

    private function stepGuardianId(array &$state, string $text): string
    {
        if (!preg_match('/^[0-9]{9}$/', $text)) {
            return "رقم الهوية يجب أن يتكون من 9 أرقام بالضبط.\n\nأعد الإدخال — رقم هوية الوصي/الوصية (9 أرقام):";
        }

        $lookup = $this->lookupGuardian($text);

        if (($lookup['exists'] ?? false) === true && ($lookup['source'] ?? null) === 'data') {
            $file = str_pad((string) $lookup['file_id_number'], 6, '0', STR_PAD_LEFT);
            $complete = RePeople::where('registration_id', $file)->exists();
            $status = $complete ? 'مكتمل' : 'ناقص';

            $state['mode'] = 'update';
            $state['file'] = $file;
            $state['status'] = $status;
            $state['data'] = $this->prefillFromGuardian($lookup['guardian_data'] ?? [], $text);

            $prefix = "وجدنا ملفك رقم {$file} — حالته: {$status}. سنعتمد بياناتك المسجلة.";

            return $prefix . "\n\n" . $this->questionFor($this->nextGuardianStep($state['data']), $state);
        }

        $state['mode'] = 'new';
        $state['data'] = ['data_id_number' => $text];

        $note = null;
        $civil = $this->fillFromCivilRegistry($text);

        if (!empty($civil)) {
            $state['data'] = array_merge($civil, ['data_id_number' => $text]);
            $note = 'تم العثور على بياناتك في السجل المدني ✅';
        }

        $question = $this->questionFor($this->nextGuardianStep($state['data']), $state);

        return ($note !== null ? $note . "\n\n" : '') . $question;
    }

    private function nextGuardianStep(array $data): string
    {
        foreach (self::GUARDIAN_STEPS as $step => $field) {
            if (empty($data[$field])) {
                return $step;
            }
        }

        return 'reg_mother_status';
    }

    private function stepSimple(array &$state, string $field, string $text, string $next): string
    {
        if ($text === '') {
            return 'القيمة مطلوبة.\n\n' . $this->questionFor($next, $state);
        }

        $this->assign($state, $field, $text);

        return $this->questionFor($next, $state);
    }

    private function stepId(array &$state, string $field, string $text, string $next): string
    {
        if (!preg_match('/^[0-9]{9}$/', $text)) {
            return "رقم الهوية يجب أن يتكون من 9 أرقام بالضبط.\n\n" . $this->questionFor($state['step'], $state);
        }

        $this->assign($state, $field, $text);

        return $this->questionFor($next, $state);
    }

    private function stepDate(array &$state, string $field, string $text, string $next, bool $allowUnknown): string
    {
        if ($allowUnknown && $text === '-') {
            $this->assign($state, $field, null);

            return $this->questionFor($next, $state);
        }

        try {
            $date = Carbon::parse($text)->format('Y-m-d');
        } catch (\Throwable $e) {
            return 'صيغة التاريخ غير صحيحة.\n\n' . $this->questionFor($state['step'], $state);
        }

        $this->assign($state, $field, $date);

        return $this->questionFor($next, $state);
    }

    private function stepChoice(array &$state, string $field, string $text, array $allowed, string $next): string
    {
        if (!in_array($text, $allowed, true)) {
            return 'اختيار غير صحيح.\n\n' . $this->questionFor($state['step'], $state);
        }

        $state['data'][$field] = $text;

        return $this->questionFor($next, $state);
    }

    private function stepChoiceNested(array &$state, string $field, string $text, array $allowed, string $next): string
    {
        if (!in_array($text, $allowed, true)) {
            return 'اختيار غير صحيح.\n\n' . $this->questionFor($state['step'], $state);
        }

        $this->assign($state, $field, $text);

        return $this->questionFor($next, $state);
    }

    private function stepPhone(array &$state, string $phone, string $text): string
    {
        $value = $text === '0' ? $phone : preg_replace('/[^0-9]/', '', $text);

        if ($value === '' || strlen($value) < 9) {
            return "رقم الجوال غير صحيح.\n\n" . $this->questionFor('reg_phone', $state);
        }

        $state['data']['data_phone_number'] = $value;

        return $this->questionFor('reg_address', $state);
    }

    private function stepList(array &$state, string $field, string $text, string $table, string $column, string $title, string $next): string
    {
        $rows = DB::table($table)->orderBy('id')->get();
        $index = ctype_digit($text) ? (int) $text : 0;

        if ($index < 1 || $index > $rows->count()) {
            return 'اختيار غير صحيح.\n\n' . $this->listQuestion($title, $rows, $column);
        }

        $state['data'][$field] = (string) $rows[$index - 1]->id;

        return $this->questionFor($next, $state);
    }

    private function stepReason(array &$state, string $field, string $text, string $next): string
    {
        if ($text === '-') {
            $this->assign($state, $field, null);

            return $this->questionFor($next, $state);
        }

        $rows = DB::table('death_reasons')->orderBy('id')->get();
        $index = ctype_digit($text) ? (int) $text : 0;

        if ($index < 1 || $index > $rows->count()) {
            return "اختيار غير صحيح.\n\n" . $this->listQuestion('سبب الوفاة برقم (أو - إذا غير معروف):', $rows, 'description');
        }

        $this->assign($state, $field, (string) $rows[$index - 1]->id);

        return $this->questionFor($next, $state);
    }

    private function stepMotherStatus(array &$state, string $text): string
    {
        if ($text === '1') {
            $state['data']['data_relationship'] = '1';
            $state['mother_choice'] = '1';
            $state['step'] = 'reg_father_dead';

            return "تم — الوصية هي الأم وستُنسخ بياناتها.\n\n" . $this->questionFor('reg_father_dead', $state);
        }

        if ($text === '2' || $text === '3') {
            $state['mother_choice'] = $text;
            $state['data']['data_relationship'] = '2';
            $state['data']['mother_is_alive'] = $text === '2' ? '1' : '0';
            $state['step'] = 'reg_mother_id';

            $ack = $text === '2' ? 'الأم حية.' : 'الأم متوفية.';

            return $ack . "\n\n" . $this->questionFor('reg_mother_id', $state);
        }

        return 'اختيار غير صحيح.\n\n' . $this->questionFor('reg_mother_status', $state);
    }

    private function stepFatherDead(array &$state, string $text): string
    {
        if ($text === '1') {
            $state['father_dead'] = true;
            $state['step'] = 'reg_father_id';

            return $this->questionFor('reg_father_id', $state);
        }

        if ($text === '0') {
            $state['father_dead'] = false;
            $state['step'] = 'reg_orphan_id';

            return "تم.\n\n" . $this->questionFor('reg_orphan_id', $state);
        }

        return 'اختيار غير صحيح.\n\n' . $this->questionFor('reg_father_dead', $state);
    }

    private function stepOrphanId(array &$state, string $text): string
    {
        if ($text === '0' || $text === 'تم') {
            if (empty($state['orphans'])) {
                return "يجب إضافة يتيم واحد على الأقل قبل الإنهاء.\n\n" . $this->questionFor('reg_orphan_id', $state);
            }

            return $this->startAttachments($state);
        }

        if (!preg_match('/^[0-9]{9}$/', $text)) {
            return "رقم الهوية يجب أن يتكون من 9 أرقام بالضبط.\n\n" . $this->questionFor('reg_orphan_id', $state);
        }

        $taken = array_column($state['orphans'], 'person_id');

        if (in_array($text, $taken, true) || (($state['orphan_draft']['person_id'] ?? null) === $text)) {
            return "هذه الهوية مضافة مسبقاً في هذا الطلب.\n\n" . $this->questionFor('reg_orphan_id', $state);
        }

        $state['orphan_draft'] = ['person_id' => $text];
        $state['step'] = 'reg_orphan_first';

        return $this->questionFor('reg_orphan_first', $state);
    }

    private function stepOrphanSponsorship(array &$state, string $text): string
    {
        $rows = DB::table('sponsorship_statuses')->orderBy('id')->get();
        $index = ctype_digit($text) ? (int) $text : 0;

        if ($index < 1 || $index > $rows->count()) {
            return 'اختيار غير صحيح.' . "\n\n" . $this->listQuestion('نوع الكفالة برقم:', $rows, 'description');
        }

        $state['orphan_draft']['sponsorship_status'] = (string) $rows[$index - 1]->id;
        $state['orphans'][] = $state['orphan_draft'];
        $name = trim(($state['orphan_draft']['first_name'] ?? '') . ' ' . ($state['orphan_draft']['last_name'] ?? ''));
        $state['orphan_draft'] = null;
        $state['step'] = 'reg_orphan_id';

        return "تمت إضافة {$name} ✅\n\n" . $this->questionFor('reg_orphan_id', $state);
    }

    private function startAttachments(array &$state): string
    {
        if ($this->documentTypes()->isEmpty()) {
            return $this->finalize($state);
        }

        return $this->questionFor('reg_attachments', $state);
    }

    private function documentTypes()
    {
        return DB::table('document_types')->orderBy('id')->get();
    }

    private function attachmentMenu(array $state): string
    {
        $rows = $this->documentTypes();
        $count = count($state['attachments'] ?? []);

        $lines = [
            'أرفق وثائقك المطلوبة: أرسل الملف (صورة أو PDF حتى 5MB) مع كتابة رقم الوثيقة في التعليق،',
            'أو أرسل رقم الوثيقة أولاً ثم الملف. أرسل 0 لإنهاء التسجيل.',
            "الملفات المستلمة: {$count}",
            '',
            'الوثائق:',
        ];

        foreach ($rows as $i => $row) {
            $lines[] = ($i + 1) . '. ' . $row->description;
        }

        return implode("\n", $lines);
    }

    private function personOptions(array $state): array
    {
        $options = [];

        $guardianName = trim(($state['data']['data_first_name'] ?? '') . ' ' . ($state['data']['data_family_name'] ?? ''));
        $options[] = ['key' => 'main', 'label' => 'الوصي/الوصية' . ($guardianName !== '' ? ' - ' . $guardianName : '')];

        if (!empty($state['father_dead']) && filled($state['data']['father_id'] ?? null)) {
            $name = trim(($state['data']['father_first_name'] ?? '') . ' ' . ($state['data']['father_last_name'] ?? ''));
            $options[] = ['key' => (string) $state['data']['father_id'], 'label' => 'الأب المتوفى' . ($name !== '' ? ' - ' . $name : '')];
        }

        if (($state['mother_choice'] ?? '') === '3' && filled($state['data']['deceased_mother_id'] ?? null)) {
            $name = trim(($state['data']['deceased_mother_first_name'] ?? '') . ' ' . ($state['data']['deceased_mother_last_name'] ?? ''));
            $options[] = ['key' => (string) $state['data']['deceased_mother_id'], 'label' => 'الأم المتوفية' . ($name !== '' ? ' - ' . $name : '')];
        }

        foreach (($state['orphans'] ?? []) as $orphan) {
            $name = trim(($orphan['first_name'] ?? '') . ' ' . ($orphan['last_name'] ?? ''));
            $options[] = ['key' => (string) ($orphan['person_id'] ?? ''), 'label' => 'اليتيم' . ($name !== '' ? ' - ' . $name : '')];
        }

        return $options;
    }

    private function personQuestion(array $state): string
    {
        $lines = ['لأي شخص تُرفق هذه الوثيقة؟'];

        foreach ($this->personOptions($state) as $i => $option) {
            $lines[] = ($i + 1) . '. ' . $option['label'];
        }

        $lines[] = '0. رجوع لقائمة الوثائق';

        return implode("\n", $lines);
    }

    private function stepAttachments(array &$state, string $text): string
    {
        if ($text === '0') {
            return $this->finalize($state);
        }

        $types = $this->documentTypes();

        if (!ctype_digit($text) || (int) $text < 1 || (int) $text > $types->count()) {
            return 'اختيار غير صحيح.' . "\n\n" . $this->attachmentMenu($state);
        }

        $row = $types[(int) $text - 1];
        $state['pending'] = ['doc' => ['file_type' => (string) $row->file_type, 'description' => (string) $row->description]];

        return $this->questionFor('reg_attach_person', $state);
    }

    private function stepAttachPerson(array &$state, string $text): string
    {
        if ($text === '0') {
            unset($state['pending']);

            return $this->questionFor('reg_attachments', $state);
        }

        $options = $this->personOptions($state);

        if (!ctype_digit($text) || (int) $text < 1 || (int) $text > count($options)) {
            return 'اختيار غير صحيح.' . "\n\n" . $this->personQuestion($state);
        }

        $state['pending']['person'] = $options[(int) $text - 1]['key'];

        if (!empty($state['pending']['media_msg_id'])) {
            return $this->ingestPending($state);
        }

        return $this->questionFor('reg_attach_send', $state);
    }

    private function stepAttachSend(array &$state, string $text): string
    {
        if ($text === '0') {
            return $this->finalize($state);
        }

        $types = $this->documentTypes();

        if (ctype_digit($text) && (int) $text >= 1 && (int) $text <= $types->count()) {
            $row = $types[(int) $text - 1];
            $state['pending']['doc'] = ['file_type' => (string) $row->file_type, 'description' => (string) $row->description];

            return $this->questionFor('reg_attach_person', $state);
        }

        return 'أرسل الملف الآن (صورة أو PDF حتى 5MB).' . "\n\n" . $this->questionFor('reg_attach_send', $state);
    }

    public function handleMedia(string $phone, string $messageId, string $mediaType, ?string $caption): ?string
    {
        $state = $this->load($phone);

        if ($state === null) {
            return 'لم تبدأ التسجيل بعد. أرسل 1 للبدء بالقائمة الرئيسية.';
        }

        $state['phone'] = $phone;
        $caption = trim((string) $caption);
        $step = (string) ($state['step'] ?? '');

        try {
            if ($step === 'faq_mode') {
                $this->save($phone, $state);

                return 'اكتب سؤالك نصاً من فضلك.';
            }

            if ($step === 'reg_attachments') {
                if (ctype_digit($caption) && $caption !== '0') {
                    $reply = $this->stepAttachments($state, $caption);

                    if (($state['step'] ?? '') === 'reg_attach_person') {
                        $state['pending']['media_msg_id'] = $messageId;
                        $state['pending']['media_type'] = $mediaType;
                    }

                    $this->save($phone, $state);

                    return $reply;
                }

                $this->save($phone, $state);

                return "أرفق الملف مع كتابة رقم الوثيقة في التعليق (مثال: 1)، أو أرسل رقم الوثيقة أولاً.\n\n" . $this->attachmentMenu($state);
            }

            if ($step === 'reg_attach_person') {
                if (ctype_digit($caption) && $caption !== '0') {
                    $reply = $this->stepAttachments($state, $caption);
                } else {
                    $reply = $this->personQuestion($state);
                }

                $state['pending']['media_msg_id'] = $messageId;
                $state['pending']['media_type'] = $mediaType;
                $this->save($phone, $state);

                return $reply;
            }

            if ($step === 'reg_attach_send') {
                if (ctype_digit($caption) && $caption !== '0') {
                    $types = $this->documentTypes();

                    if ((int) $caption >= 1 && (int) $caption <= $types->count()) {
                        $row = $types[(int) $caption - 1];
                        $state['pending']['doc'] = ['file_type' => (string) $row->file_type, 'description' => (string) $row->description];
                    }
                }

                $state['pending']['media_msg_id'] = $messageId;
                $state['pending']['media_type'] = $mediaType;
                $reply = $this->ingestPending($state);
                $this->save($phone, $state);

                return $reply;
            }

            $this->save($phone, $state);

            return 'أكمل خطوات التسجيل أولاً — تُرفق الوثائق في الخطوة الأخيرة بعد إضافة الأيتام.';
        } catch (\Throwable $e) {
            Log::error('فشل معالجة مرفق واتساب', ['phone' => $phone, 'message_id' => $messageId, 'error' => $e->getMessage()]);

            return 'تعذر معالجة الملف. أعد الإرسال، أو أرسل 1 للبدء من جديد.';
        }
    }

    private function ingestPending(array &$state): string
    {
        $pending = $state['pending'] ?? [];

        if (empty($pending['media_msg_id'])) {
            return 'أرسل الملف الآن (صورة أو PDF حتى 5MB):';
        }

        $messageId = (string) $pending['media_msg_id'];
        $mediaType = (string) ($pending['media_type'] ?? 'image');
        unset($state['pending']['media_msg_id'], $state['pending']['media_type']);

        $result = $this->media->ingest((string) ($state['phone'] ?? ''), $messageId, $mediaType);

        if (($result['ok'] ?? false) !== true) {
            return $result['error'] . "\n\n" . $this->questionFor('reg_attach_send', $state);
        }

        return $this->addAttachment($state, $result);
    }

    private function addAttachment(array &$state, array $result): string
    {
        foreach (($state['attachments'] ?? []) as $existing) {
            if (($existing['hash'] ?? '') === ($result['hash'] ?? '')) {
                $this->media->discard($result['temp_path'] ?? null);
                $state['step'] = 'reg_attachments';

                return "هذا الملف مُرسَل مسبقاً في هذه الجلسة — تم تجاوزه.\n\n" . $this->attachmentMenu($state);
            }
        }

        if (count($state['attachments'] ?? []) >= self::MAX_ATTACHMENTS) {
            $this->media->discard($result['temp_path'] ?? null);
            $state['step'] = 'reg_attachments';

            return 'بلغت الحد الأقصى (' . self::MAX_ATTACHMENTS . " ملفات).\n\n" . $this->attachmentMenu($state);
        }

        $doc = $state['pending']['doc'];
        $person = (string) $state['pending']['person'];

        $state['attachments'][] = [
            'person_identity_number' => $person,
            'file_type' => (string) $doc['file_type'],
            'temp_path' => (string) $result['temp_path'],
            'stored_file_name' => $doc['file_type'] . '_' . basename((string) $result['temp_path']),
            'hash' => (string) $result['hash'],
        ];

        unset($state['pending']);
        $state['step'] = 'reg_attachments';
        $count = count($state['attachments']);

        return "تم استلام «{$doc['description']}» ✅ ({$count} ملفات).\n\n" . $this->attachmentMenu($state);
    }

    private function questionFor(string $step, array &$state): string
    {
        $this->setStep($state, $step);

        return match ($step) {
            'reg_id' => 'رقم هوية الوصي/الوصية (9 أرقام):',
            'reg_first_name' => 'الاسم الأول:',
            'reg_father_name' => 'اسم الأب:',
            'reg_grand_name' => 'اسم الجد:',
            'reg_family_name' => 'اسم العائلة:',
            'reg_birth' => 'تاريخ الميلاد بالميلادي (مثال: 1990-01-01):',
            'reg_gender' => "الجنس:\n(1) ذكر\n(2) أنثى",
            'reg_phone' => 'رقم جوال الوصي (أرسل 0 لاستخدام رقم الواتساب الحالي):',
            'reg_address' => 'العنوان الحالي (المحافظة/المخيم/الحي):',
            'reg_city' => $this->listQuestion('اختر المدينة برقم:', DB::table('city')->orderBy('id')->get(), 'city'),
            'reg_province' => $this->listQuestion('اختر المحافظة برقم:', DB::table('provinces')->orderBy('id')->get(), 'description'),
            'reg_mother_status' => "ما وضع الأم؟\n(1) الوصية هي الأم\n(2) الأم حية\n(3) الأم متوفية",
            'reg_mother_id' => 'رقم هوية الأم (9 أرقام):',
            'reg_mother_first' => 'الاسم الأول للأم:',
            'reg_mother_second' => 'اسم الأب للأم:',
            'reg_mother_third' => 'اسم الجد للأم:',
            'reg_mother_family' => 'اسم العائلة للأم:',
            'reg_mother_birth' => 'تاريخ ميلاد الأم (مثال: 1995-04-04، أو - إذا غير معروف):',
            'reg_mother_death_date' => 'تاريخ وفاة الأم (مثال: 2021-03-10، أو - إذا غير معروف):',
            'reg_mother_death_reason' => $this->listQuestion('سبب وفاة الأم برقم (أو - إذا غير معروف):', DB::table('death_reasons')->orderBy('id')->get(), 'description'),
            'reg_father_dead' => "هل الأب متوفى؟\n(1) نعم\n(0) لا",
            'reg_father_id' => 'رقم هوية الأب (9 أرقام):',
            'reg_father_first' => 'الاسم الأول للأب:',
            'reg_father_second' => 'اسم الأب للأب:',
            'reg_father_third' => 'اسم الجد للأب:',
            'reg_father_family' => 'اسم العائلة للأب:',
            'reg_father_death_date' => 'تاريخ وفاة الأب (مثال: 2020-06-01، أو - إذا غير معروف):',
            'reg_father_death_reason' => $this->listQuestion('سبب وفاة الأب برقم (أو - إذا غير معروف):', DB::table('death_reasons')->orderBy('id')->get(), 'description'),
            'reg_orphan_id' => 'رقم هوية الـيتيم (9 أرقام)، أو أرسل 0 لإنهاء القائمة:',
            'reg_orphan_first' => 'الاسم الأول لليتيم:',
            'reg_orphan_second' => 'اسم الأب لليتيم:',
            'reg_orphan_third' => 'اسم الجد لليتيم:',
            'reg_orphan_family' => 'اسم العائلة لليتيم:',
            'reg_orphan_birth' => 'تاريخ ميلاد اليتيم (مثال: 2018-03-03):',
            'reg_orphan_gender' => "جنس اليتيم:\n(1) ذكر\n(2) أنثى",
            'reg_orphan_sponsorship' => $this->listQuestion('نوع الكفالة برقم:', DB::table('sponsorship_statuses')->orderBy('id')->get(), 'description'),
            'reg_attachments' => $this->attachmentMenu($state),
            'reg_attach_person' => $this->personQuestion($state),
            'reg_attach_send' => 'أرسل الملف الآن (صورة أو PDF حتى 5MB)، أو أرسل رقم وثيقة أخرى، أو 0 لإنهاء التسجيل:',
            default => $this->unexpected(),
        };
    }

    private function setStep(array &$state, string $step): void
    {
        $state['step'] = $step;
    }

    private function listQuestion(string $title, $rows, string $column): string
    {
        if ($rows->isEmpty()) {
            return $title . "\n(لا توجد خيارات — أرسل - للمتابعة)";
        }

        $lines = [$title];

        foreach ($rows as $i => $row) {
            $lines[] = ($i + 1) . '. ' . $row->{$column};
        }

        return implode("\n", $lines);
    }

    private function motherKey(array $state, string $part): string
    {
        $prefix = ($state['mother_choice'] ?? '') === '2' ? 'mother_' : 'deceased_mother_';

        $suffix = [
            'id' => 'id',
            'first' => 'first_name',
            'second' => 'second_name',
            'third' => 'third_name',
            'family' => 'last_name',
        ][$part];

        return $prefix . $suffix;
    }

    private function motherNext(array $state, string $part): string
    {
        $order = ['id', 'first', 'second', 'third', 'family'];
        $pos = array_search($part, $order, true);

        if ($pos !== false && $pos < count($order) - 1) {
            return 'reg_mother_' . $order[$pos + 1];
        }

        return ($state['mother_choice'] ?? '') === '2' ? 'reg_mother_birth' : 'reg_mother_death_date';
    }

    private function assign(array &$state, string $field, ?string $value): void
    {
        if (str_starts_with($field, 'orphan_draft.')) {
            $key = substr($field, strlen('orphan_draft.'));
            $state['orphan_draft'][$key] = $value;

            return;
        }

        $state['data'][$field] = $value;
    }

    private function prefillFromGuardian(array $guardian, string $identity): array
    {
        $map = [
            'data_first_name' => $guardian['first_name'] ?? null,
            'data_father_name' => $guardian['father_name'] ?? null,
            'data_grand_father_name' => $guardian['grand_father_name'] ?? null,
            'data_family_name' => $guardian['family_name'] ?? null,
            'data_birth_date' => $guardian['birth_date'] ?? null,
            'data_gender' => $guardian['gender'] ?? null,
            'data_marital_status' => $guardian['marital_status'] ?? null,
            'data_phone_number' => $guardian['phone_number'] ?? null,
            'data_city' => $guardian['city'] ?? null,
            'data_province' => $guardian['province'] ?? null,
            'data_current_address' => $guardian['current_address'] ?? null,
        ];

        $data = ['data_id_number' => $identity];

        foreach ($map as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value instanceof \DateTimeInterface ? Carbon::instance($value)->format('Y-m-d') : (string) $value;
            }
        }

        return $data;
    }

    private function lookupGuardian(string $identity): array
    {
        try {
            $request = Request::create('/general-registration/check-existing-guardian', 'POST', [
                'identity_number' => $identity,
            ], [], [], ['HTTP_ACCEPT' => 'application/json']);

            $response = (new GeneralRegistrationController())->checkExistingGuardian($request);
            $body = json_decode($response->getContent(), true);

            return is_array($body) ? $body : [];
        } catch (\Throwable $e) {
            Log::warning('فشل فحص الوصي الموجود', ['identity' => $identity, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function fillFromCivilRegistry(string $identity): array
    {
        try {
            $request = Request::create('/general-registration/fill-from-civil-registry', 'POST', [
                'id_number' => $identity,
            ], [], [], ['HTTP_ACCEPT' => 'application/json']);

            $response = (new GeneralRegistrationController())->fillFromCivilRegistry($request);
            $body = json_decode($response->getContent(), true);

            if (!is_array($body) || empty($body['success']) || empty($body['data'])) {
                return [];
            }

            $data = [];

            foreach ([
                'data_first_name',
                'data_father_name',
                'data_grand_father_name',
                'data_family_name',
                'data_birth_date',
                'data_gender',
                'data_marital_status',
                'data_city',
                'data_current_address',
            ] as $key) {
                $value = $body['data'][$key] ?? null;

                if ($value !== null && $value !== '') {
                    if ($key === 'data_gender' && !in_array((string) $value, ['1', '2'], true)) {
                        continue;
                    }

                    $data[$key] = (string) $value;
                }
            }

            return $data;
        } catch (\Throwable $e) {
            Log::warning('فشل جلب بيانات من السجل المدني', ['identity' => $identity, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function finalize(array &$state): string
    {
        $data = $state['data'];
        $isUpdate = ($state['mode'] ?? 'new') === 'update' && !empty($state['file']);

        if (empty($data['data_first_name']) || empty($data['data_province'])) {
            $state['done'] = true;

            return 'بيانات الوصي غير مكتملة. أرسل 1 للبدء من جديد.';
        }

        $fileId = $isUpdate ? $state['file'] : generateUniqueReservedCode('data', 'file_id_number');

        if (blank($fileId)) {
            return 'تعذر توليد رقم ملف جديد. أرسل 0 ثم أعد المحاولة لاحقاً.';
        }

        $fileId = str_pad((string) $fileId, 6, '0', STR_PAD_LEFT);

        $family = [];

        foreach ($state['orphans'] as $orphan) {
            $family[] = [
                'person_id' => $orphan['person_id'],
                'first_name' => $orphan['first_name'],
                'second_name' => $orphan['second_name'],
                'third_name' => $orphan['third_name'],
                'last_name' => $orphan['last_name'],
                'person_gender' => $orphan['person_gender'],
                'person_birth_date' => $orphan['person_birth_date'],
                'sponsorship_status' => $orphan['sponsorship_status'],
            ];
        }

        $defaults = [
            'data_marital_status' => '1',
            'data_academic_qualification' => '1',
            'data_displacement_status' => '1',
            'data_health_status' => '1',
            'data_employment_status_breadwinner' => '1',
            'data_housing_status' => '1',
            'data_current_housing_type' => '1',
            'data_number_mail' => 0,
            'data_number_female' => 0,
            'data_number_alt' => 0,
            'data_number_of_individuals_with_chronic_diseases' => 0,
            'data_number_of_people_with_special_needs' => 0,
            'data_description_needs' => '',
            'data_user_insert_data' => 'whatsapp',
            'data_alt_phone_number' => '',
            'data_relationship' => '2',
        ];

        $attachments = [];

        foreach (($state['attachments'] ?? []) as $entry) {
            $attachments[] = [
                'person_identity_number' => $entry['person_identity_number'],
                'file_type' => $entry['file_type'],
                'temp_path' => $entry['temp_path'],
                'stored_file_name' => $entry['stored_file_name'],
                'file_id_number' => $fileId,
            ];
        }

        $payload = array_merge($defaults, $data, [
            'file_id_number' => $fileId,
            'data_section_id' => 1,
            'data_request_status' => 1,
            'data_number_of_individuals' => count($family),
            'data_address_before_displacement' => $data['data_current_address'] ?? '',
            'family_members' => $family,
            'attachments' => $attachments,
        ]);

        $request = Request::create('/general-registration/store', 'POST', $payload, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = (new GeneralRegistrationController())->store($request);
        $body = json_decode($response->getContent(), true) ?? [];
        $state['done'] = true;

        if ($response->getStatusCode() === 200 && ($body['success'] ?? false) === true) {
            return "تم التسجيل بنجاح ✅\nرقم الملف: {$fileId}\n\nسيقوم الفريق بمراجعة الملف. شكراً لتعاونك.";
        }

        $message = (string) ($body['message'] ?? '');

        if ($message === '' && !empty($body['errors'])) {
            $errors = [];

            foreach ($body['errors'] as $fieldErrors) {
                foreach ((array) $fieldErrors as $error) {
                    $errors[] = $error;
                }
            }

            $message = implode("\n", $errors);
        }

        if ($message === '') {
            $message = 'تعذر حفظ البيانات.';
        }

        return "تعذر إتمام التسجيل:\n{$message}\n\nأرسل 1 للبدء من جديد.";
    }

    private function save(string $phone, array $state): void
    {
        $state['phone'] = $phone;
        Cache::put('bot_step_' . $phone, $state['step'], self::TTL);
        Cache::put('reg_data_' . $phone, $state, self::TTL);
    }

    private function load(string $phone): ?array
    {
        $step = Cache::get('bot_step_' . $phone);

        if (!is_string($step) || $step === '') {
            return null;
        }

        $state = Cache::get('reg_data_' . $phone);

        if (!is_array($state)) {
            return null;
        }

        $state['step'] = $step;

        return $state;
    }

    private function forget(string $phone): void
    {
        Cache::forget('bot_step_' . $phone);
        Cache::forget('reg_data_' . $phone);
    }
}
