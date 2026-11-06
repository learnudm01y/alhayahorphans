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
use Illuminate\Support\Facades\Log;

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
     * القبول التلقائي الجماعي: يعالج دفعة من الطلبات غير المقبولة،
     * ويعتمد فقط الطلبات التي تجتاز كل شروط RegistrationAutoAcceptService.
     * لا كتابة إطلاقاً للطلبات التي يفشل أي فحص فيها.
     */
    public function autoAcceptBulk(Request $request, RegistrationAutoAcceptService $service): JsonResponse
    {
        $validated = $request->validate([
            'status_id' => 'nullable|integer|exists:request_status,id',
            'after_id' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $limit = (int) ($validated['limit'] ?? 25);
        $afterId = (int) ($validated['after_id'] ?? 0);
        $statusFilter = $validated['status_id'] ?? null;

        // نفس نطاق جدول إدارة الطلبات: سجلات البوابة غير المقبولة فقط.
        $query = Data::query()
            ->where(function ($q) {
                $q->whereNull('data_user_insert_data')
                    ->orWhere('data_user_insert_data', 'N_user');
            })
            ->whereHas('requestStatus', function ($q) {
                $q->where('description', '!=', RegistrationAutoAcceptService::ACCEPTED_DESCRIPTION);
            })
            ->where('id', '>', $afterId)
            ->orderBy('id');

        // احترام فلتر الحالة الحالي إن اختاره الموظف.
        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('data_request_status', $statusFilter);
        }

        $rows = $query->limit($limit)->get();

        $accepted = 0;
        $skipped = 0;
        $alreadyAccepted = 0;
        $failed = 0;
        $skipReasons = [];

        foreach ($rows as $data) {
            try {
                $report = $service->accept($data);

                if (!empty($report['accepted'])) {
                    $accepted++;
                    continue;
                }

                if (!empty($report['already_accepted'])) {
                    $alreadyAccepted++;
                    continue;
                }

                $skipped++;

                // تجميع أسباب التخطي لعرضها للموظف دون كشف تفاصيل ثقيلة.
                foreach (($report['checks'] ?? []) as $check) {
                    if (empty($check['passed'])) {
                        $label = (string) ($check['label'] ?? 'فحص غير مسمى');
                        $skipReasons[$label] = ($skipReasons[$label] ?? 0) + 1;
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('AUTO_ACCEPT_BULK_RECORD_FAILED', [
                    'data_id' => (int) $data->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $lastId = $rows->isNotEmpty() ? (int) $rows->last()->id : $afterId;
        $hasMore = $rows->count() === $limit;

        Log::info('AUTO_ACCEPT_BULK_BATCH', [
            'status_filter' => $statusFilter,
            'after_id' => $afterId,
            'processed' => $rows->count(),
            'accepted' => $accepted,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return response()->json([
            'success' => true,
            'processed' => $rows->count(),
            'accepted' => $accepted,
            'skipped' => $skipped,
            'already_accepted' => $alreadyAccepted,
            'failed' => $failed,
            'skip_reasons' => $skipReasons,
            'next_after_id' => $lastId,
            'has_more' => $hasMore,
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
