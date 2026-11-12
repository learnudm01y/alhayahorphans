<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * رسائل رفض التسجيل العام: عربية، تسمّي الحقل وموضعه (التبويب ورقم البطاقة)،
 * ولا تحمل أي تفاصيل تقنية. التفاصيل التقنية تبقى في اللوغ تحت رمز المتابعة نفسه.
 */
class PortalErrorMessages
{
    /** المجموعات المتكررة: المفتاح => [اسم القسم، وحدة العدّ] */
    private const REPEATED_GROUPS = [
        'family_members' => ['أفراد الأسرة', 'البطاقة'],
        'additional_deceased' => ['المتوفى الإضافي', 'البطاقة'],
        'bank_accounts' => ['الحسابات البنكية', 'الحساب'],
        'attachments' => ['المرفقات', 'المرفق'],
        'document_file' => ['المرفقات', 'الملف'],
    ];

    /** حقول تبويب «الأفراد المتوفون»؛ وما عداها يرجع لتبويب البيانات الأساسية */
    private const DECEASED_ROOTS = [
        'additional_deceased' => true,
        'father_id' => true,
        'father_first_name' => true,
        'father_second_name' => true,
        'father_third_name' => true,
        'father_last_name' => true,
        'father_death_date' => true,
        'father_death_reason' => true,
        'deceased_mother_id' => true,
        'deceased_mother_first_name' => true,
        'deceased_mother_second_name' => true,
        'deceased_mother_third_name' => true,
        'deceased_mother_last_name' => true,
        'mother_death_date' => true,
        'mother_death_reason' => true,
    ];

    private const ATTACHMENT_ROOTS = [
        'attachments' => true,
        'document_file' => true,
    ];

    /** رمز متابعة قصير يربط رسالة المستخدم بسطر اللوغ. ليس كوداً تقنياً ولا يكشف شيئاً. */
    public static function reference(): string
    {
        return 'R-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /** هيكل الرسالة كما يُخزَّن في الجلسة وكما يُعاد في JSON. */
    public static function payload(string $message, array $fields = [], ?string $reference = null): array
    {
        return [
            'message' => $message,
            'reference' => $reference ?? self::reference(),
            'fields' => array_values($fields),
        ];
    }

    /** سبب واحد مربوط بحقله: التسمية والتبويب ورقم البطاقة بالعربية. */
    public static function field(string $key, string $message): array
    {
        return [
            'field' => $key,
            'label' => self::label($key),
            'tab' => self::tab($key),
            'message' => $message,
        ];
    }

    /** يحوّل أخطاء Laravel (مفتاح => رسائل) إلى أسباب مرتبة قابلة للعرض. */
    public static function fieldsFromErrors(array $errors): array
    {
        $fields = [];

        foreach ($errors as $key => $messages) {
            foreach ((array) $messages as $message) {
                if (is_string($message) && $message !== '') {
                    $fields[] = self::field((string) $key, $message);
                }
            }
        }

        return $fields;
    }

    /** أجزاء اسم فهرس التفرّد في رسالة MySQL => الحقل الذي يدلّ عليه (الأطول أولاً) */
    private const DUPLICATE_FIELD_HINTS = [
        'file_id_number' => 'file_id_number',
        'data_id_number' => 'data_id_number',
        'person_id' => 'person_id',
        'person_type_hash' => 'attachments',
        'id_number' => 'id_number',
        'iban' => 'iban_usd',
        'email' => 'email',
        'client_uuid' => 'request_id',
        'code' => 'request_id',
    ];

    /**
     * خطأ تكرار من قاعدة البيانات (1062): اسم الفهرس يُترجَم إلى حقل مفهوم للمستخدم.
     * القيمة المكرّرة نفسها لا تُعرض ولا تُسجَّل.
     */
    public static function fieldsFromDuplicate(string $technical): array
    {
        $key = '';
        if (preg_match("/for key ['\"]?([A-Za-z0-9_.]+)['\"]?/", $technical, $matches) === 1) {
            $key = $matches[1];
        }

        foreach (self::DUPLICATE_FIELD_HINTS as $hint => $field) {
            if ($key !== '' && str_contains($key, $hint)) {
                return [self::field($field, 'هذه القيمة مسجّلة مسبقاً في سجل آخر، ولا يمكن استخدامها مرة أخرى.')];
            }
        }

        return [];
    }

    /** يحجب الهويات والهواتف وأرقام الحسابات من أي نص قبل تسجيله في اللوغ. */
    public static function maskTechnical(string $text): string
    {
        $text = preg_replace('/\b[A-Z]{2}\d{2}[A-Z0-9]{10,30}\b/', '[IBAN]', $text) ?? $text;

        return preg_replace('/\d{9,}/', '[رقم محجوب]', $text) ?? $text;
    }

    /** تسمية عربية للحقل، مسبوقة بالقسم ورقم البطاقة عند الحقول المتكررة. */
    public static function label(string $field): string
    {
        $parts = self::parts($field);
        $prefix = '';

        if (count($parts) >= 2 && isset(self::REPEATED_GROUPS[$parts[0]]) && ctype_digit($parts[1])) {
            [$section, $unit] = self::REPEATED_GROUPS[$parts[0]];
            $prefix = $section . ' — ' . $unit . ' ' . ((int) $parts[1] + 1) . ' — ';
            $parts = array_slice($parts, 2);
        }

        $attributes = Lang::get('validation.attributes');
        $attributes = is_array($attributes) ? $attributes : [];

        $name = null;
        foreach ([implode('.', $parts), end($parts) ?: '', $parts[0] ?? ''] as $candidate) {
            if ($candidate !== '' && isset($attributes[$candidate]) && is_string($attributes[$candidate])) {
                $name = $attributes[$candidate];
                break;
            }
        }

        if ($name === null) {
            // مفتاح يشير إلى المجموعة كلها بلا حقل فرعي: يكفي اسم القسم ورقم البطاقة
            return $prefix !== '' ? mb_substr($prefix, 0, -3) : 'أحد حقول النموذج';
        }

        return $prefix . $name;
    }

    /** معرّف تبويب الصفحة الذي يوجد فيه الحقل، ليفتحه زر «اذهب إلى الحقل». */
    public static function tab(string $field): string
    {
        $root = self::parts($field)[0] ?? '';

        if ($root === 'family_members') {
            return 'family-members';
        }

        if (isset(self::ATTACHMENT_ROOTS[$root])) {
            return 'attachments';
        }

        if (isset(self::DECEASED_ROOTS[$root])) {
            return 'deceased';
        }

        return 'basic';
    }

    /** يقبل صيغتي المفاتيح: family_members.0.person_id و family_members[0][person_id]. */
    private static function parts(string $field): array
    {
        $normalized = str_replace(['[', ']'], ['.', ''], trim($field));

        return array_values(array_filter(explode('.', $normalized), static function ($part) {
            return $part !== '';
        }));
    }
}