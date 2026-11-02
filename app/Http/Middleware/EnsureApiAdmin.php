<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حماية مسارات API الإدارية.
 *
 * يعيد 403 JSON بدلاً من redirect، لأن مسارات API لا يجوز أن تُرجع صفحة HTML
 * (خصوصاً عند استخدام توكن Sanctum في تطبيق الجوال).
 */
class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || ($user->role ?? null) !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول إلى هذا المورد',
            ], 403);
        }

        return $next($request);
    }
}
