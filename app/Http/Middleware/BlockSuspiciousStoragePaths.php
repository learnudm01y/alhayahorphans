<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BlockSuspiciousStoragePaths
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $uri = $request->getRequestUri();

        // قائمة الأنماط المحظورة
        $blockedPatterns = [
            '/storage/i:',
            '/storage/c:',
            '/storage/I:',
            '/storage/C:',
            'unit%20test',
            'unit test',
            '/storage/.*unit.*test.*/',
            '/storage/.*ASO.*Copy.*/',
        ];

        // فحص إذا كان المسار يحتوي على أنماط محظورة
        foreach ($blockedPatterns as $pattern) {
            if (stripos($uri, $pattern) !== false) {
                \Log::warning('تم حجب طلب مشبوه: ' . $uri, [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'referer' => $request->header('referer')
                ]);

                // إرجاع استجابة فارغة بدلاً من 404
                return new Response('', 200, [
                    'Content-Type' => 'application/octet-stream',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache'
                ]);
            }
        }

        return $next($request);
    }
}
