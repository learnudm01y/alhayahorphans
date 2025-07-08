<?php

namespace App\Http\Controllers\Users;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Data;
use Illuminate\Support\Facades\Log;


class ShowGeneralRegisrationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // تحويل البريد الإلكتروني (رقم الهوية كنص) إلى رقم للمقارنة مع data_id_number
        $userIdNumber = preg_replace('/[^0-9]/', '', $user->email); // إزالة أي فواصل أو رموز
        // جرب المطابقة كنص وكعدد (لأن بعض قواعد البيانات قد تخزن الرقم كنص مع فواصل)
        // إضافة تسجيل للخروج بقيمة رقم الهوية المستخدمة في البحث
        Log::info('USER_ID_NUMBER_USED_FOR_DATA_QUERY', ['userIdNumber' => $userIdNumber, 'userEmail' => $user->email]);
        $data = Data::with([
            'section',
            'requestStatus',
            'categoryOfRelation',
            'healthStatus',
            'maritalStatus',
            'academicQualification',
            'city',
            'employmentStatusBreadwinner',
            'province',
            'housingStatus',
            'currentHousingType',
            'attachments',
            'rePeople',
            'deadPepole',
            'guardianBankAccount',
        ])
        ->where(function($q) use ($userIdNumber, $user) {
            $q->where('data_id_number', $userIdNumber)
              ->orWhere('data_id_number', $user->email)
              ->orWhere('data_id_number', (string) $userIdNumber);
        })
        ->first();
        return view('user.dashboard.component.generalRegisrationIndex', compact('data'));
    }
}
