<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppSetting;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppSettingsController extends Controller
{
    private const TOGGLES = ['auto_replies', 'ai_enabled'];

    /** شاشة إعدادات الربط + مفاتيح التحكم */
    public function index()
    {
        return view('admin.whatsapp.settings', [
            'apiUrl' => (string) (WhatsAppSetting::get('api_url') ?? config('services.whatsapp.base_url')),
            'instance' => (string) (WhatsAppSetting::get('instance') ?? config('services.whatsapp.instance')),
            'hasApiKey' => filled(WhatsAppSetting::get('api_key') ?? config('services.whatsapp.api_key')),
            'autoReplies' => WhatsAppSetting::enabled('auto_replies', true),
            'aiEnabled' => WhatsAppSetting::enabled('ai_enabled', true),
            'configured' => app(WhatsAppService::class)->isConfigured(),
        ]);
    }

    /** حفظ حقول الربط + مفتاحي التفعيل/التعطيل */
    public function update(Request $request)
    {
        $request->validate([
            'api_url' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'instance' => ['nullable', 'string', 'max:100'],
        ]);

        foreach (['api_url', 'api_key', 'instance'] as $key) {
            if ($request->filled($key)) {
                WhatsAppSetting::set($key, trim((string) $request->input($key)));
            }
        }

        foreach (self::TOGGLES as $toggle) {
            WhatsAppSetting::set($toggle, $request->input($toggle) === '1' ? '1' : '0');
        }

        return redirect()->route('admin.whatsapp.settings')->with('success', 'حُفظت إعدادات الواتساب.');
    }

    /** فحص حالة اتصال الرقم في Evolution */
    public function check(WhatsAppService $whatsapp)
    {
        $result = $whatsapp->connectionState();

        return redirect()->route('admin.whatsapp.settings')->with(
            $result['ok'] ? 'wa_state' : 'wa_error',
            $result['ok'] ? ('حالة الاتصال: ' . $result['state']) : (string) $result['error']
        );
    }

    /** عرض QR لربط الرقم */
    public function qr(WhatsAppService $whatsapp)
    {
        $result = $whatsapp->connectQr();

        return redirect()->route('admin.whatsapp.settings')->with(
            $result['ok'] ? 'wa_qr' : 'wa_error',
            $result['ok'] ? (string) $result['qr'] : (string) $result['error']
        );
    }

    /** فصل الرقم عن الـInstance */
    public function logout(WhatsAppService $whatsapp)
    {
        $result = $whatsapp->logoutInstance();

        return redirect()->route('admin.whatsapp.settings')->with(
            $result['ok'] ? 'success' : 'wa_error',
            $result['ok'] ? 'تم فصل الرقم عن خادم Evolution.' : (string) $result['error']
        );
    }
}
