<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح بالدخول'
                ], 401);
            }
        });

        $this->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى تسجيل الدخول أولاً'
                ], 401);
            }
        });

        $this->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صالحة',
                    'errors' => $e->errors()
                ], 422);
            }
        });

        /*
         * ⚠️ شبكة أمان عامة لمسارات api/*.
         *
         * كانت استجابات 429 (تجاوز الحد) و413 (حجم كبير) والأخطاء الفادحة
         * تُرجَع كصفحات HTML. تطبيق الأندرويد يقرأ الرد كـ JSON، فيفشل التحليل
         * ويُفسَّر الأمر كخطأ عام مجهول: لا يعرف العميل أنه محظور مؤقتاً وأن
         * عليه الانتظار، فيدخل في حلقة إعادة رفع محمومة تزيد الحظر سوءاً.
         *
         * ملاحظة: لا يُغيّر هذا رموز الحالة، فهو متوافق مع نسخ التطبيق القديمة.
         */
        $this->renderable(function (Throwable $e, $request) {
            if (!($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            $status = 500;
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
            }

            $payload = [
                'success' => false,
                'error' => true,
                'message' => $this->apiMessageFor($status, $e),
            ];

            if ($status === 429) {
                $retryAfter = $e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException
                    ? ($e->getHeaders()['Retry-After'] ?? 60)
                    : 60;
                $payload['retry_after'] = (int) $retryAfter;
            }

            if (config('app.debug')) {
                $payload['debug'] = $e->getMessage();
            }

            return response()->json($payload, $status);
        });
    }

    private function apiMessageFor(int $status, Throwable $e): string
    {
        switch ($status) {
            case 401: return 'يرجى تسجيل الدخول أولاً';
            case 403: return 'غير مصرح بهذا الإجراء';
            case 404: return 'المسار غير موجود';
            case 413: return 'حجم الطلب أكبر من المسموح';
            case 429: return 'تجاوزت الحد المسموح من الطلبات، حاول بعد قليل';
            case 503: return 'الخدمة غير متاحة مؤقتاً';
            default:
                return $status >= 500 ? 'خطأ في الخادم' : 'تعذّر تنفيذ الطلب';
        }
    }
}
