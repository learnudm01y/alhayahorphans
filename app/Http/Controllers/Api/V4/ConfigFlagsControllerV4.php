<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ConfigFlagsControllerV4 extends Controller
{
    /**
     * GET /api/mobile/v4/config/flags
     * Remote-config flags consumed by RemoteConfigManagerV4 each sync cycle.
     * Phase 7: legacy_sync_enabled=false → client cancels v3 unique works at runtime.
     * Rollback: set LEGACY_SYNC_ENABLED=true → next fetch re-enables v3 within minutes.
     */
    public function flags(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'flags' => [
                'legacy_sync_enabled' => (bool) config('services.sync_v4.legacy_sync_enabled', true),
            ],
        ]);
    }
}
