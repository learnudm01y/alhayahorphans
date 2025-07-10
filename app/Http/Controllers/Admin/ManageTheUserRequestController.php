<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\DataTables\ManageTheUserRequestDataTable;
use App\Models\Data;
use App\Models\RequestStatus;
use Illuminate\Http\JsonResponse;

class ManageTheUserRequestController extends Controller
{
    // عرض الصفحة مع الداتاتابل
    public function index(ManageTheUserRequestDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.civil_registry.ManageTheUserRequest');
    }

    // تغيير حالة الطلب عبر AJAX
    public function changeStatus(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:data,id',
            'status_id' => 'required|exists:request_status,id',
        ]);
        $data = Data::findOrFail($request->id);
        $data->data_request_status = $request->status_id;
        $data->save();
        $status = RequestStatus::find($request->status_id);
        // توليد HTML مطابق للbadge كما في الداتاتابل
        $color = 'secondary';
        if($status) {
            switch($status->description) {
                case 'مقبول': $color = 'success'; break;
                case 'مرفوض': $color = 'danger'; break;
                case 'قيد المراجعة': $color = 'warning'; break;
                case 'جديد': $color = 'info'; break;
            }
        }
        $status_html = '<span class="badge badge-' . $color . '">' . ($status ? $status->description : 'غير محدد') . '</span>';
        // إعادة بناء خيارات القائمة المنسدلة (select) من قاعدة البيانات
        $allStatuses = RequestStatus::all();
        $status_options_html = '';
        foreach($allStatuses as $s) {
            $selected = $s->id == $request->status_id ? 'selected' : '';
            $status_options_html .= '<option value="' . $s->id . '" ' . $selected . '>' . $s->description . '</option>';
        }
        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة الطلب بنجاح',
            'status' => $status ? $status->description : '',
            'status_html' => $status_html,
            'status_options_html' => $status_options_html,
        ]);
    }
}
