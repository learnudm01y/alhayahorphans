<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConvertJsonToFormData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // إذا كان الطلب يحتوي على JSON في body
        if ($request->isJson() || $request->getContent()) {
            $data = $request->json()->all();
            if (!empty($data)) {
                $request->merge($data);
            }
        }

        return $next($request);
    }
}
