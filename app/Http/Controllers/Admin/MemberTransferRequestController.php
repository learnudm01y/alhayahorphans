<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Data;
use App\Models\MemberTransferRequest;
use App\Models\RePeople;
use App\Services\FamilyMemberTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * طابور طلبات نقل الأفراد المرتبطين بمعيلين آخرين.
 *
 * التسجيل الرئيسي يقبل هذه الطلبات ويُبقيها في «مراجعة»؛ والنقل لا ينفَّذ إلا
 * بضغطتين هنا (موافقة/رفض) من صاحب صلاحية «تعديل سجل».
 */
class MemberTransferRequestController extends Controller
{
    public function index(Request $request, FamilyMemberTransferService $transferService)
    {
        $status = $request->input('status', MemberTransferRequest::STATUS_PENDING);
        $search = trim((string) $request->input('q', ''));

        $requests = null;
        $guardians = collect();
        $liveMembers = collect();

        // الجدول غير مثبَّت بعد على هذه الاستضافة: شاشة فارغة بتوضيح بدل خطأ 500
        if (!MemberTransferRequest::tableExists()) {
            $requests = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);

            return view('admin.dashboard.civil_registry.member_transfer_requests', compact('requests', 'guardians', 'liveMembers', 'status', 'search') + ['tableMissing' => true]);
        }

        $query = MemberTransferRequest::with('data');

        if ($status !== 'all') {
            if (!in_array($status, MemberTransferRequest::ALL_STATUSES, true)) {
                $status = MemberTransferRequest::STATUS_PENDING;
            }
            $query->where('status', $status);
        }

        // الأعمدة أرقام: البحث مطابِق رقمي فقط، وإلا MySQL يُحوّل النص إلى 0 ويعطي نتائج خاطئة
        if ($search !== '' && preg_match('/^\d{1,10}$/', $search)) {
            $query->where(function ($q) use ($search) {
                $q->where('identity_number', $search)
                    ->orWhere('file_id_number', $search)
                    ->orWhere('source_file_id_number', $search);
            });
        }

        $requests = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        // ملفا كل طلب (المصدر والهدف) لعرض اسم المعيل: جلب دفعة واحدة بلا N+1
        $fileNumbers = $requests->pluck('file_id_number')
            ->merge($requests->pluck('source_file_id_number'))
            ->filter()
            ->unique();

        $guardians = Data::whereIn('file_id_number', $fileNumbers)
            ->get(['file_id_number', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'])
            ->map(function ($guardian) use ($transferService) {
                return (object) [
                    'file_id_number' => (int) $guardian->file_id_number,
                    'display_name' => $transferService->guardianFullName($guardian) ?: '—',
                ];
            })
            ->keyBy('file_id_number');

        // هل الفرد ما زال في ملف المصدر؟ يتغير الجواب إذا حُذف أو نُقل يدوياً
        $liveMembers = RePeople::whereIn('person_id', $requests->pluck('identity_number'))
            ->get(['id', 'person_id', 'registration_id'])
            ->groupBy(fn ($member) => ltrim(trim((string) $member->registration_id), '0') . '|' . ltrim(trim((string) $member->person_id), '0'));

        return view('admin.dashboard.civil_registry.member_transfer_requests', compact('requests', 'guardians', 'liveMembers', 'status', 'search'));
    }

    public function approve(Request $request, MemberTransferRequest $transfer, FamilyMemberTransferService $transferService): JsonResponse
    {
        if ($transfer->status !== MemberTransferRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب غير معلّق، لا يمكن الموافقة عليه.',
            ], 422);
        }

        try {
            $moved = $transferService->approvePendingTransfer($transfer, auth()->id());

            $remaining = MemberTransferRequest::pending()
                ->where('file_id_number', $transfer->file_id_number)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'تم نقل «' . $moved['person_id'] . '» إلى ملف ' . $moved['to_file'] . ' بنجاح.',
                'remaining_pending' => $remaining,
                'can_accept_file' => $remaining === 0,
            ]);
        } catch (\RuntimeException $e) {
            // الفرد لم يعد في ملف المصدر: تُسجَّل failed خارج المعاملة،
            // وبقيد مشروط بحالة pending حتى لا يُلغى طلب نُقل فعلاً في سباق تزامني.
            if ($e->getMessage() === FamilyMemberTransferService::MEMBER_GONE_MESSAGE) {
                MemberTransferRequest::where('id', $transfer->id)
                    ->where('status', MemberTransferRequest::STATUS_PENDING)
                    ->update([
                        'status' => MemberTransferRequest::STATUS_FAILED,
                        'reason' => 'تعذّر النقل: لم يعد الفرد مرتبطاً بملف المصدر عند الموافقة.',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                    ]);
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('خطأ في الموافقة على طلب نقل فرد: ' . $e->getMessage(), [
                'transfer_request_id' => $transfer->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تنفيذ النقل: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, MemberTransferRequest $transfer): JsonResponse
    {
        if ($transfer->status !== MemberTransferRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب غير معلّق، لا يمكن رفضه.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ], [
            'reason.max' => 'سبب الرفض يجب ألا يتجاوز 500 حرف.',
        ]);

        // التحديث مشروط ببقاء الحالة pending: رفضٌ بعد موافقة إدارية أخرى لا يُلغي نقلاً تم
        $rejected = MemberTransferRequest::where('id', $transfer->id)
            ->where('status', MemberTransferRequest::STATUS_PENDING)
            ->update([
                'status' => MemberTransferRequest::STATUS_REJECTED,
                'reason' => $validated['reason'] ?? 'رُفض طلب النقل من لوحة الإدارة.',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

        if (!$rejected) {
            return response()->json([
                'success' => false,
                'message' => 'لم يعد هذا الطلب معلّقاً، تمّت معالجته من مستخدم آخر.',
            ], 422);
        }

        Log::info('⛔ رفض طلب نقل فرد أسرة', [
            'transfer_request_id' => $transfer->id,
            'file_id_number' => $transfer->file_id_number,
            'person_id' => $transfer->identity_number,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض طلب النقل، وبقي الفرد في ملف معيله الحالي.',
        ]);
    }
}
