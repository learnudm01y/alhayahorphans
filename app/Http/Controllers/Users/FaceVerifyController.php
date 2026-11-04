<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Services\FaceVerifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * فحص الوجه الخادمي +استقبال تقارير فحص المتصفح.
 *
 * المسارات ضمن مجموعة web (جلسة + CSRF) لأن متصفّح التسجيل العام يحتاج الجلسة،
 * وهي متاحة للزائر والمسجّل معاً مع حدّ طلبات مشدّد.
 */
class FaceVerifyController extends Controller
{
    private const MAX_UPLOAD_KB = 8192;

    public function __construct(private readonly FaceVerifyService $verifier)
    {
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|max:' . self::MAX_UPLOAD_KB . '|mimes:jpeg,jpg,png,webp',
            'file_type' => 'nullable|string|max:20',
            'client_code' => 'nullable|string|max:40',
        ]);

        $dir = storage_path('app/face_tmp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $token = bin2hex(random_bytes(8));
        $path = $dir . DIRECTORY_SEPARATOR . $token . '.src';

        try {
            $request->file('image')->move($dir, $token . '.src');

            $result = $this->verifier->verifyFile($path);
            $result['fileType'] = trim((string) $request->input('file_type', ''));
            $result['clientCode'] = $request->input('client_code');
            $result['userId'] = optional($request->user())->id;

            Log::channel('face_check')->info('server-verify', $result);

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::channel('face_check')->error('server-verify-exception', [
                'message' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'ok' => false,
                'pass' => false,
                'code' => 'engine-error',
                'detail' => 'تعذّر إتمام الفحص الخادمي',
            ], 500);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** تقرير من المتصفح عن نتيجة فحصه — للقياس فقط، لا يغيّر أي شيء */
    public function report(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'stage' => 'nullable|string|max:30',
            'file_type' => 'nullable|string|max:20',
            'code' => 'required|string|max:40',
            'detail' => 'nullable|string|max:255',
            'score' => 'nullable|numeric',
            'rotated' => 'nullable|integer',
            'ms' => 'nullable|integer',
            'engine' => 'nullable|string|max:20',
            'canvas' => 'nullable|string|max:30',
        ]);

        $payload['userId'] = optional($request->user())->id;
        $payload['ip'] = $request->ip();

        Log::channel('face_check')->info('client-report', $payload);

        return response()->json(['ok' => true]);
    }
}
