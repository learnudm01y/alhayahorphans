<?php

namespace App\Http\Controllers;

use App\Jobs\WhatsAppInboundJob;
use Illuminate\Http\Request;

class WhatsAppBotController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $apiKey = (string) config('services.whatsapp.api_key');

        if ($apiKey === '') {
            return response()->json(['error' => 'إعدادات واتساب غير مهيأة: WHATSAPP_API_KEY مفقود.'], 503);
        }

        if (!hash_equals($apiKey, (string) $request->input('apikey'))) {
            return response()->json(['error' => 'مفتاح ويب هوك غير صالح.'], 401);
        }

        $event = strtolower(str_replace(['-', '_'], '.', (string) $request->input('event')));
        $data = $request->input('data');

        if (is_array($data) && in_array($event, ['messages.upsert', 'messages.update'], true)) {
            WhatsAppInboundJob::dispatch($event, $data);
        }

        return response()->json(['status' => 'ok']);
    }
}
