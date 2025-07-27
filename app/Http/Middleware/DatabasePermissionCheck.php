<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DatabasePermissionCheck
{
    public function handle(Request $request, Closure $next)
    {
        // تسجيل محاولات الوصول لـ reserved_codes
        if ($request->has('code_generation')) {
            Log::info('Code generation request', [
                'user_ip' => $request->ip(),
                'session' => $request->session()->getId(),
                'timestamp' => now()
            ]);
        }
        
        return $next($request);
    }
}