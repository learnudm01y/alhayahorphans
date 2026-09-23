<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use App\Services\V4\AuditLoggerV4;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationControllerV4 extends Controller
{
    protected AuditLoggerV4 $auditLogger;

    public function __construct(AuditLoggerV4 $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * GET /api/mobile/v4/audit
     * سجل التدقيق — شاشة Audit Trail (Doc/08 §3).
     */
    public function audit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_uuid' => 'nullable|string|max:36',
            'entity_type' => 'nullable|string|max:64',
            'field_name' => 'nullable|string|max:128',
            'device_id' => 'nullable|string|max:64',
            'sensitive_only' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = DB::table('record_audit_log_v4');

        if (!empty($validated['client_uuid'])) {
            $query->where('client_uuid', $validated['client_uuid']);
        }
        if (!empty($validated['entity_type'])) {
            $query->where('entity_type', $validated['entity_type']);
        }
        if (!empty($validated['field_name'])) {
            $query->where('field_name', $validated['field_name']);
        }
        if (!empty($validated['device_id'])) {
            $query->where('changed_by_device', $validated['device_id']);
        }
        if (!empty($validated['sensitive_only'])) {
            $query->where('is_sensitive', 1);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * GET /api/mobile/v4/conflicts
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'open');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $query = DB::table('conflict_review_queue_v4');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * POST /api/mobile/v4/conflicts/{id}/resolve
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:merge,keep_both,reject_incoming',
            'notes' => 'nullable|string|max:1000',
        ]);

        $conflict = DB::table('conflict_review_queue_v4')
            ->where('id', $id)
            ->where('status', 'open')
            ->first();

        if (!$conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Conflict not found or already resolved',
            ], 404);
        }

        DB::transaction(function () use ($conflict, $validated, $request) {
            DB::table('conflict_review_queue_v4')
                ->where('id', $conflict->id)
                ->update([
                    'status' => 'resolved',
                    'resolved_by_user_id' => $request->user()?->id,
                    'resolved_at' => now(),
                ]);

            // إزالة علم needs_review من السجل الأصلي بعد الحسم
            $tableMap = [
                'data' => 'data',
                're_people' => 're_people',
                'person' => 're_people',
                'dead_people' => 'dead_people',
                'additional_deceased' => 'additional_deceased',
                'guardian_bank_accounts' => 'guardian_bank_accounts',
                'sponsorships' => 'sponsorships',
            ];

            $table = $tableMap[$conflict->entity_type] ?? null;
            if ($table) {
                DB::table($table)
                    ->where('id', $conflict->existing_record_id)
                    ->update(['needs_review' => 0]);
            }

            // في حالة merge: دمج الحقول الحسّاسة من incoming في السجل الأصلي
            if ($validated['decision'] === 'merge') {
                $incoming = json_decode($conflict->incoming_payload_json, true) ?? [];
                unset($incoming['client_uuid'], $incoming['id'], $incoming['created_at']);
                if ($table && !empty($incoming)) {
                    $incoming['updated_at'] = now();
                    DB::table($table)
                        ->where('id', $conflict->existing_record_id)
                        ->update($incoming);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Conflict resolved',
            'decision' => $validated['decision'],
        ]);
    }
}
