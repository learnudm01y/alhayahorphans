<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\RoleHelper;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        $user = $request->user();

        // التحقق من وجود المستخدم
        if (!$user) {
            return redirect()->route('login');
        }

        // التحقق من صحة بيانات المستخدم
        if (!RoleHelper::validateUserRole($user)) {
            return redirect()->route('login')->withErrors(['error' => 'بيانات المستخدم غير صحيحة']);
        }

        // التحقق من صحة الدور المطلوب
        if ($user->role === $role) {
            return $next($request);
        }

        // إعادة التوجيه للمسار الصحيح بناءً على دور المستخدم
        return redirect(RoleHelper::getCorrectRedirectPath($user));
    }
}
