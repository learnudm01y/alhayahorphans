<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CI_BIRTH_CD;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard.component.indexPageForStatistics');
    }

    public function profile(): View
    {
        return view('admin.dashboard.component.profile');
    }
    public function profileIndex(): View
    {
        return view('admin.dashboard.component.profile_index');
    }
    public function settings(): View
    {
        $user = auth()->user();
        $countries = CI_BIRTH_CD::all();  // جلب جميع الدول من جدول Ci_Birth_Cd
        $countryName = $user->birthCountry->CI_BIRTH_CD ?? 'غير محدد';

        return view('admin.dashboard.component.settings', compact('user', 'countryName', 'countries'));
    }
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'alt_phone' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'country_code' => 'nullable|string',
        ]);

        $user = Auth::user();
        $user->name = $validated['name'];
        $user->phone = $validated['phone'];
        $user->alt_phone = $validated['alt_phone'];
        $user->country_code = $validated['country_code'];

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads'); // مجلد داخل public
            $file->move($destinationPath, $fileName);
            $user->avatar = 'uploads/' . $fileName; // حفظ المسار داخل قاعدة البيانات
        }

        $user->save();

        return response()->json(['success' => true]);
    }


    public function softDeleteProfile($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete(); // Soft Delete
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المستخدم بنجاح!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المستخدم: ' . $e->getMessage()
            ]);
        }
    }

    public function updateEmail(Request $request)
    {
        $request->validate([
            'emailaddress' => 'required|email',
            'confirmemailpassword' => 'required|string',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->confirmemailpassword, $user->password)) {
            return response()->json(['success' => false, 'message' => 'كلمة المرور غير صحيحة']);
        }

        $user->email = $request->emailaddress;
        $user->save();

        return response()->json(['success' => true]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'currentpassword' => 'required|string',
            'newpassword' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->currentpassword, $user->password)) {
            return response()->json(['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة']);
        }

        $user->password = Hash::make($request->newpassword);
        $user->save();

        return response()->json(['success' => true]);
    }

    public function updateAvatar(Request $request)
{
    $request->validate([
        'avatar' => 'required|file|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    try {
        $user = Auth::user();

        // حذف الصورة القديمة إن وُجدت وليست avatar.jpg
        if ($user->avatar && $user->avatar !== 'uploads/avatar.jpg') {
            $oldAvatarPath = public_path($user->avatar);
            if (File::exists($oldAvatarPath)) {
                File::delete($oldAvatarPath);
            }
        }

        // رفع الصورة الجديدة
        $file = $request->file('avatar');
        $filename = Str::random(5) . '.' . $file->getClientOriginalExtension();
        $uploadPath = public_path('uploads');

        File::ensureDirectoryExists($uploadPath);
        $file->move($uploadPath, $filename);

        // حفظ في قاعدة البيانات
        $user->avatar = 'uploads/' . $filename;
        $user->save();

        return response()->json([
            'success' => true,
            'avatar' => asset('uploads/' . $filename),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء رفع الصورة',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function showExcelGatewaySidebar(): View
    {
        return view('admin.file.sidebar-excel-gateway');

    }

}
