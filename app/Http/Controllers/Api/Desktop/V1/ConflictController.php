<?php

namespace App\Http\Controllers\Api\Desktop\V1;

use App\Http\Controllers\Controller;
use App\Services\DesktopSync\ConflictService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

class ConflictController extends Controller
{
    public function __construct(private readonly ConflictService $conflicts)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,resolved,all'],
            'entityType' => ['sometimes', 'nullable', 'string', 'max:128'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            return response()->json([
                'data' => $this->conflicts->list(
                    $validated['status'] ?? 'pending',
                    $validated['entityType'] ?? null,
                    isset($validated['limit']) ? (int) $validated['limit'] : 100
                ),
                'server_time' => now('UTC')->toISOString(),
            ]);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function resolve(Request $request, string $conflictUuid): JsonResponse
    {
        $validated = $request->validate([
            'expectedConflictVersion' => ['required', 'integer', 'min:1'],
            'resolution' => ['required', 'in:server,local,selected-fields'],
            'selectedFields' => ['sometimes', 'array'],
            'selectedFields.*' => ['string', 'distinct', 'max:191'],
        ]);
        $operationId = Validator::make(
            ['operationId' => $request->header('X-Operation-Id')],
            ['operationId' => ['nullable', 'uuid']]
        )->validate()['operationId'] ?? null;

        try {
            return response()->json($this->conflicts->resolve(
                $request->user(),
                $conflictUuid,
                (int) $validated['expectedConflictVersion'],
                $validated['resolution'],
                $validated['selectedFields'] ?? [],
                $operationId
            ));
        } catch (DomainException $exception) {
            return response()->json([
                'error' => 'conflict_resolution_rejected',
                'message' => $exception->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'invalid_conflict_resolution',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'desktop_sync_unavailable',
            'message' => 'Desktop conflict handling is temporarily unavailable.',
        ], 503);
    }
}
