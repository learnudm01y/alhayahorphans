<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use App\Services\V4\DeviceHandshakeServiceV4;
use App\Services\V4\IdempotentUpsertServiceV4;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncControllerV4 extends Controller
{
    protected IdempotentUpsertServiceV4 $upsertService;
    protected DeviceHandshakeServiceV4 $handshakeService;

    public function __construct(
        IdempotentUpsertServiceV4 $upsertService,
        DeviceHandshakeServiceV4 $handshakeService
    ) {
        $this->upsertService = $upsertService;
        $this->handshakeService = $handshakeService;
    }

    /**
     * POST /api/mobile/v4/sync/registration
     * تسجيل/تعديل سجل مع idempotency كاملة.
     */
    public function registration(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');
        $deviceId = $request->header('X-Device-Id', 'unknown');

        if (!$idempotencyKey) {
            return response()->json([
                'success' => false,
                'message' => 'X-Idempotency-Key header is required',
            ], 400);
        }

        $validated = $request->validate([
            'entity_type' => 'required|string|in:data,re_people,person,dead_people,additional_deceased,guardian_bank_accounts,sponsorships',
            'client_uuid' => 'required|string|uuid',
            'payload' => 'required|array',
        ]);

        try {
            $result = $this->upsertService->handle(
                $idempotencyKey,
                $validated['entity_type'],
                array_merge($validated['payload'], ['client_uuid' => $validated['client_uuid']]),
                $deviceId
            );

            $this->handshakeService->updateHealth($deviceId, 'healthy');

            return response()->json($result, $result['created'] ? 201 : 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('SyncControllerV4 registration failed', [
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process registration',
            ], 500);
        }
    }

    /**
     * POST /api/mobile/v4/sync/actions
     * دفعة عمليات من sync_outbox_v4 المحلي.
     */
    public function actions(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Device-Id', 'unknown');

        $validated = $request->validate([
            'actions' => 'required|array|min:1',
            'actions.*.idempotency_key' => 'required|string|max:64',
            'actions.*.entity_type' => 'required|string',
            'actions.*.client_uuid' => 'required|string|uuid',
            'actions.*.payload' => 'required|array',
        ]);

        $results = [];
        $processed = 0;
        $conflicts = 0;

        foreach ($validated['actions'] as $action) {
            try {
                $result = $this->upsertService->handle(
                    $action['idempotency_key'],
                    $action['entity_type'],
                    array_merge($action['payload'], ['client_uuid' => $action['client_uuid']]),
                    $deviceId
                );
                $results[] = $result;
                $processed++;
                if (!empty($result['conflict'])) {
                    $conflicts++;
                }
            } catch (\Exception $e) {
                $results[] = [
                    'idempotency_key' => $action['idempotency_key'],
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->handshakeService->updateHealth($deviceId, 'healthy', 0);

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'conflicts' => $conflicts,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/mobile/v4/sync/pull?since=
     * سحب تزايدي موحّد.
     */
    public function pull(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $tables = ['data', 're_people', 'dead_people', 'additional_deceased', 'guardian_bank_accounts', 'sponsorships'];

        $payload = [];

        foreach ($tables as $table) {
            $query = DB::table($table);

            if ($since) {
                $query->where('updated_at', '>', $since);
            }

            $payload[$table] = $query
                ->orderBy('updated_at')
                ->limit(500)
                ->get();
        }

        return response()->json([
            'success' => true,
            'since' => $since,
            'server_time' => now()->toDateTimeString(),
            'data' => $payload,
        ]);
    }
}
