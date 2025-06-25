<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserLoginContoller extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_email' => 'required|numeric',
            'login_password' => 'required|digits:4',
        ], [
            'login_email.required' => 'يرجى إدخال رقم الهوية',
            'login_email.numeric' => 'رقم الهوية يجب أن يكون أرقام فقط',
            'login_password.required' => 'يرجى إدخال كلمة المرور',
            'login_password.digits' => 'كلمة المرور يجب أن تكون 4 أرقام',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $credentials = [
            'email' => $request->input('login_email'),
            'password' => $request->input('login_password'),
        ];

        Log::info('محاولة تسجيل دخول من البوابة الرئيسية', $credentials);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->route('user.dashboard')->with('success', 'تم تسجيل الدخول بنجاح.');
        }

        return back()->withErrors([
            'login_email' => 'بيانات الدخول غير صحيحة أو الحساب غير مفعل.',
        ])->withInput();
    }
}
