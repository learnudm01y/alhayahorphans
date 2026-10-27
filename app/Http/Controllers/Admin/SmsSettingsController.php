<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsSetting;
use App\Services\NsmsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SmsSettingsController extends Controller
{
    private const TOGGLES = ['confirm_bulk'];   // مفاتيح تفعيل/تعطيل

    public function __construct(private NsmsClient $sms) {}

    /** شاشة إعدادات الرسائل */
    public function index()
    {
        $senders = $this->senders();

        return view('admin.sms.settings', [
            'senders'       => $senders,
            'defaultSender' => SmsSetting::get('default_sender') ?: '',
            'envSender'     => (string) config('services.nsms.sender'),
            'confirmBulk'   => SmsSetting::enabled('confirm_bulk', true),
        ]);
    }

    /** حفظ الإعدادات */
    public function update(Request $request)
    {
        $senders = $this->senders();

        $data = $request->validate([
            // '' يعني: استخدام قيمة .env (NSMS_SENDER)
            'default_sender' => ['nullable', 'string', 'max:50',
                Rule::in(array_merge([''], $senders))],
        ], [
            'default_sender.in' => 'اسم المرسل غير مسموح به لدى الخدمة',
        ]);

        SmsSetting::set('default_sender', ($data['default_sender'] ?? '') !== '' ? $data['default_sender'] : null);

        foreach (self::TOGGLES as $toggle) {
            SmsSetting::set($toggle, $request->input($toggle) === '1' ? '1' : '0');
        }

        return redirect()->route('admin.sms.settings')->with('success', 'حُفظت إعدادات الرسائل.');
    }

    /** أسماء المرسلين لدى الخدمة (مع تخزين مؤقت) */
    private function senders(): array
    {
        $cached = Cache::get('nsms.senders');
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $list = $this->sms->senders();
        if ($list !== []) {
            Cache::put('nsms.senders', $list, 300);
        }

        return $list;
    }
}
