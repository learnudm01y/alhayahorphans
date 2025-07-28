<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Helpers\RoleHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        // إضافة logging للمساعدة في debugging
        Log::info('User login attempt', [
            'user_id' => $user->id ?? 'unknown',
            'email' => $user->email ?? 'unknown',
            'role' => $user->role ?? 'null',
            'ip' => $request->ip()
        ]);

        // استخدام RoleHelper للتحقق من صحة الدور
        if (RoleHelper::canAccessAdmin($user)) {
            Log::info('Admin login successful', ['user_id' => $user->id, 'redirect' => 'admin.dashboard']);
            return redirect()->intended(RouteServiceProvider::ADMIN);
        }

        // التحقق من صحة البيانات قبل التوجيه
        if (RoleHelper::validateUserRole($user)) {
            Log::info('User login successful', ['user_id' => $user->id, 'redirect' => 'user.dashboard']);
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        // في حالة وجود مشكلة في البيانات، تسجيل خروج وإعادة توجيه لتسجيل الدخول
        Log::warning('Invalid user role detected', [
            'user_id' => $user->id ?? 'unknown',
            'role' => $user->role ?? 'null',
            'action' => 'forced_logout'
        ]);

        Auth::logout();
        return redirect()->route('login')->withErrors(['email' => 'حدث خطأ في بيانات المستخدم. يرجى المحاولة مرة أخرى.']);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
