<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\DataTables\ManageTheUserRequestDataTable;
use App\Models\Data;
use App\Models\MemberTransferRequest;
use App\Models\RequestStatus;
use App\Services\RegistrationAutoAcceptService;
use Illuminate\Http\JsonResponse;

class ManageTheUserRequestController extends Controller
{
    // عرض الصفحة مع الداتاتابل
    public function index(ManageTheUserRequestDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.civil_registry.ManageTheUserRequest');
    }

    /**
     * الفحص التلقائي: قراءة فقط، لا يكتب أي حالة.
     */
    public function autoAcceptCheck(Request $request, RegistrationAutoAcceptService $service): JsonResponse
    {
        $request->validate(['id' => 'required|integer|exists:data,id']);

        $data = Data::findOrFail($request->id);

        return response()->json([
            'success' => true,
            'report' => $service->evaluate($data),
        ]);
    }

    /**
     * الاعتماد التلقائي: يُعيد الفحص على الخادم ثم يكتب الحالة إذا نجح كل فحص.
     * لا يُوثق بتقرير الجاني — المتصفح قد يكون عرض نتائج فحص قبل تغيير البيانات.
     */
    public function autoAcceptApply(Request $request, RegistrationAutoAcceptService $service): JsonResponse
    {
        $request->validate(['id' => 'required|integer|exists:data,id']);

        $data = Data::findOrFail($request->id);
        $report = $service->accept($data);

        if (!$report['accepted']) {
            return response()->json([
                'success' => false,
                'message' => 'لم يُعتمد الطلب: أحد الفحوصات لم يستكمل.',
                'report' => $report,
            ], 422);
        }

        $status = RequestStatus::find($report['status_id']);

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد الطلب وتعيين الحالة «' . ($status ? $status->description : 'مقبول') . '».',
            'status' => $status ? $status->description : 'مقبول',
            'status_html' => $this->statusBadgeHtml($status),
            'report' => $report,
        ]);
    }

    /**
     * شارة الحالة بنفس تنسيق الداتاتابل.
     */
    private function statusBadgeHtml(?RequestStatus $status): string
    {
        $color = 'secondary';
        if ($status) {
            switch ($status->description) {
                case 'مقبول': $color = 'success'; break;
                case 'مرفوض': $color = 'danger'; break;
                case 'قيد المراجعة': $color = 'warning'; break;
                case 'جديد': $color = 'info'; break;
            }
        }

        return '<span class="badge badge-' . $color . '">' . e($status ? $status->description : 'غير محدد') . '</span>';
    }

    // تغيير حالة الطلب عبر AJAX
    public function changeStatus(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:data,id',
            'status_id' => 'required|exists:request_status,id',
        ]);
        $data = Data::findOrFail($request->id);
        $status = RequestStatus::find($request->status_id);

        // 🚫 لا تُقبل ملفات فيها أفراد معلّقون بانتظار موافقة النقل:
        //    القبول يعني أن الأسرة مكتملة عند هذا المعيل، والنقل لم يحدث بعد.
        if ($status && $status->description === 'مقبول' && MemberTransferRequest::tableExists()) {
            $pendingCount = MemberTransferRequest::pending()
                ->where(function ($q) use ($data) {
                    $q->where('data_id', $data->id);
                    if (!empty($data->file_id_number)) {
                        $q->orWhere('file_id_number', $data->file_id_number);
                    }
                })
                ->count();

            if ($pendingCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن اعتماد هذا الطلب كمقبول: '
                        . $pendingCount . ' من أفراد الأسرة مرتبطون بمعيل آخر وبانتظار موافقة النقل. '
                        . 'أتمّ طلبات النقل أولاً من شاشة طلبات نقل الأفراد.',
                    'pending_transfers_count' => $pendingCount,
                ], 422);
            }
        }

        $data->data_request_status = $request->status_id;
        $data->save();
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
