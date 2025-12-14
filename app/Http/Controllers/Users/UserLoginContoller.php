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
            'login_password.required' => 'يرجى إدخال رقم الملف الداخلي',
            'login_password.string' => 'رقم الملف الداخلي يجب أن يكون نص صحيح',
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
        $sponsorship = Sponsorship::with('relationData')
            ->where('identity_number', $identityNumber)
            ->first();

        if (!$sponsorship) {
            Log::warning('⚠️ لم يتم العثور على كفالة برقم الهوية', ['identity_number' => $identityNumber]);
            return back()->withErrors([
                'login_email' => 'رقم الهوية غير مسجل في النظام أو لا توجد كفالة مرتبطة به.',
            ])->withInput();
        }

        // التحقق من رقم الملف الداخلي فقط
        $correctFileNumber = $sponsorship->internal_file_number;

        if (empty($correctFileNumber)) {
            Log::error('❌ رقم الملف الداخلي غير موجود للكفالة', [
                'sponsorship_id' => $sponsorship->id,
                'identity_number' => $identityNumber
            ]);
            return back()->withErrors([
                'login_email' => 'لا يوجد رقم ملف داخلي مسجل لهذه الكفالة. يرجى التواصل مع الإدارة.',
            ])->withInput();
        }

        if ($fileNumber !== $correctFileNumber) {
            Log::warning('❌ رقم الملف الداخلي غير صحيح', [
                'identity_number' => $identityNumber,
                'provided_file_number' => $fileNumber,
                'expected_file_number' => $correctFileNumber
            ]);
            return back()->withErrors([
                'login_password' => 'رقم الملف الداخلي غير صحيح.',
            ])->withInput();
        }

        // البحث عن المستخدم المرتبط - البحث فقط برقم هوية المكفول (identity_number)
        $user = \App\Models\User::where('email', $identityNumber)->first();

        if (!$user) {
            // إنشاء مستخدم جديد تلقائياً للمكفول
            Log::info('🆕 إنشاء مستخدم جديد للمكفول', [
                'identity_number' => $identityNumber,
                'sponsorship_id' => $sponsorship->id,
                'name' => $sponsorship->orphan_name
            ]);

            $user = \App\Models\User::create([
                'name' => $sponsorship->orphan_name ?: 'مستخدم',
                'phone' => $sponsorship->relationData->data_phone_number ?? '0000000000',
                'email' => $identityNumber,  // نستخدم رقم هوية المكفول كـ email
                'password' => bcrypt($correctFileNumber),
                'role' => 'user',
                'email_verified_at' => now(),  // تفعيل البريد مباشرة
            ]);

            Log::info('✅ تم إنشاء المستخدم بنجاح', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        }

        // تسجيل الدخول
        Auth::login($user);
        $request->session()->regenerate();

        Log::info('✅ تم تسجيل الدخول بنجاح', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'identity_number' => $identityNumber,
            'sponsorship_id' => $sponsorship->id
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
