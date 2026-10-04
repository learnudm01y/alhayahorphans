<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class NsmsClient
{
    /** رسائل عربية لأشهر الأكواد */
    public const ERRORS = [
        1002 => 'لم يتم إرسال التوكن',
        1003 => 'التوكن غير صالح أو ملغي، سجّل دخول جديد وحدّث NSMS_TOKEN',
        1101 => 'الرصيد غير كافٍ',
        1201 => 'فشل الإرسال من المزود، أعد المحاولة لاحقًا',
        1401 => 'خدمة الـ API غير مفعّلة للحساب',
        1402 => 'عنوان IP السيرفر غير مسموح، أضفه في إعدادات الحساب',
        1601 => 'عدد الأرقام يتجاوز الحد المسموح',
        1602 => 'بيانات الطلب غير صالحة',
    ];

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.nsms.base_url'))
            ->withToken(config('services.nsms.token'))
            ->acceptJson()
            ->timeout(30);
    }

    private function call(string $method, string $uri, array $payload = []): array
    {
        try {
            $r = $this->http()->{$method}($uri, $payload);
        } catch (ConnectionException $e) {
            return ['status' => false, 'code' => 0, 'message' => 'تعذر الاتصال بخدمة الرسائل'];
        }

        $json = $r->json();
        $res  = is_array($json)
            ? $json
            : ['status' => false, 'code' => $r->status(), 'message' => 'استجابة غير متوقعة (HTTP '.$r->status().')'];

        if (!($res['status'] ?? false)) {
            $res['message'] = self::ERRORS[$res['code'] ?? 0] ?? ($res['message'] ?? 'خطأ غير معروف');
        }
        return $res;
    }

    /* ---------- عمليات الإرسال ---------- */

    public function send(string|array $mobiles, string $message, ?string $sender = null): array
    {
        return $this->call('post', 'messages/send', [
            'mobile'  => implode(',', (array) $mobiles),
            'message' => $message,
            'sender'  => $sender ?? config('services.nsms.sender'),
        ]);
    }

    public function summary(string|array $mobiles, string $message, ?string $sender = null): array
    {
        return $this->call('post', 'messages/send/summary', [
            'mobile'  => implode(',', (array) $mobiles),
            'message' => $message,
            'sender'  => $sender ?? config('services.nsms.sender'),
        ]);
    }

    public function sendVar(string $sender, string $mobileColumn, string $template, array $rows): array
    {
        return $this->call('post', 'messages/sendvar', [
            'sender_id'        => $sender,
            'mobile_column'    => $mobileColumn,
            'message_template' => $template,
            'rows'             => $rows,
        ]);
    }

    public function sendVarSummary(string $sender, string $mobileColumn, string $template, array $rows): array
    {
        return $this->call('post', 'messages/sendvar/summary', [
            'sender_id'        => $sender,
            'mobile_column'    => $mobileColumn,
            'message_template' => $template,
            'rows'             => $rows,
        ]);
    }

    /* ---------- معلومات الحساب ---------- */

    public function credits(): ?int
    {
        $res = $this->call('get', 'credits');
        return ($res['status'] ?? false) ? (int) data_get($res, 'data.credits') : null;
    }

    public function senders(): array
    {
        $res  = $this->call('get', 'senders');
        $list = data_get($res, 'data.senders');

        return is_array($list) && $list
            ? array_values($list)
            : array_values(array_filter([config('services.nsms.sender')]));
    }

    /* ---------- أدوات الأرقام ---------- */

    public function normalize(?string $m): string
    {
        $cc = config('services.nsms.country_code', '970');
        $m  = (string) $m;

        // أرقام Excel الكبيرة قد تأتي بصيغة علمية 5.99E+8
        if (is_numeric($m) && stripos($m, 'e') !== false) {
            $m = sprintf('%.0f', (float) $m);
        }

        $m = preg_replace('/\D+/', '', $m);

        if (str_starts_with($m, '00'))                         $m = substr($m, 2);
        elseif (str_starts_with($m, '0'))                      $m = $cc.substr($m, 1);   // 0599... → 970599...
        elseif (strlen($m) === 9 && str_starts_with($m, '5'))  $m = $cc.$m;              // 599... (صفر مفقود من Excel)

        return $m;
    }

    public function isValid(string $m): bool
    {
        return (bool) preg_match('/^\d{11,15}$/', $m);
    }
}
