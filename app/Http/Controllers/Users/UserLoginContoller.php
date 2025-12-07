<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserLoginContoller extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_email' => 'required|string',
            'login_password' => 'required|string',
        ], [
            'login_email.required' => 'يرجى إدخال رقم هوية المكفول',
            'login_email.string' => 'رقم الهوية يجب أن يكون نص صحيح',
            'login_password.required' => 'يرجى إدخال رقم الملف',
            'login_password.string' => 'رقم الملف يجب أن يكون نص صحيح',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $identityNumber = $request->input('login_email');
        $fileNumber = $request->input('login_password');

        Log::info('🔐 محاولة تسجيل دخول من البوابة الرئيسية', [
            'identity_number' => $identityNumber,
            'file_number_length' => strlen($fileNumber)
        ]);

        // البحث عن كفالة بناءً على رقم هوية المكفول
        $sponsorship = Sponsorship::where('identity_number', $identityNumber)->first();

        if (!$sponsorship) {
            Log::warning('⚠️ لم يتم العثور على كفالة برقم الهوية', ['identity_number' => $identityNumber]);
            return back()->withErrors([
                'login_email' => 'رقم الهوية غير مسجل في النظام أو لا توجد كفالة مرتبطة به.',
            ])->withInput();
        }

        // التحقق من رقم الملف (خارجي أولاً، ثم داخلي)
        $correctFileNumber = $sponsorship->external_file_number ?: $sponsorship->internal_file_number;

        if ($fileNumber !== $correctFileNumber) {
            Log::warning('❌ رقم الملف غير صحيح', [
                'identity_number' => $identityNumber,
                'provided_file_number' => $fileNumber,
                'expected_file_number' => $correctFileNumber
            ]);
            return back()->withErrors([
                'login_password' => 'رقم الملف غير صحيح.',
            ])->withInput();
        }

        // البحث عن المستخدم المرتبط
        $user = \App\Models\User::where('email', $sponsorship->guardian_identity_number)
            ->orWhere('email', $identityNumber)
            ->first();

        if (!$user) {
            // إنشاء مستخدم جديد تلقائياً للمكفول
            Log::info('🆕 إنشاء مستخدم جديد للمكفول', [
                'identity_number' => $identityNumber,
                'name' => $sponsorship->orphan_name
            ]);

            $user = \App\Models\User::create([
                'name' => $sponsorship->orphan_name ?: 'مستخدم',
                'phone' => null,
                'email' => $identityNumber,
                'password' => bcrypt($correctFileNumber),
                'role' => 'user',
            ]);
        }

        // تسجيل الدخول
        Auth::login($user);
        $request->session()->regenerate();

        Log::info('✅ تم تسجيل الدخول بنجاح', [
            'user_id' => $user->id,
            'identity_number' => $identityNumber
        ]);

        return redirect()->route('user.generalRegistration.index')->with('success', 'تم تسجيل الدخول بنجاح.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login.page.index');
    }
}
