<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchRateLimiter
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
        $userId = auth()->id() ?? $request->ip();
        $cacheKey = "search_rate_limit_{$userId}";

        // السماح بـ 60 عملية بحث كل دقيقة للمستخدم الواحد
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= 60) {
            return response()->json([
                'success' => false,
                'message' => 'تم تجاوز حد البحث المسموح. يرجى المحاولة لاحقاً.',
                'retry_after' => 60
            ], 429);
        }

        // زيادة عدد المحاولات
        Cache::put($cacheKey, $attempts + 1, 60);

        // تسجيل عملية البحث
        Log::info('Search request', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'search_type' => $request->get('search_type'),
            'attempts' => $attempts + 1
        ]);

        return $next($request);
    }
}
