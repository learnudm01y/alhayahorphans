<?php

namespace App\Http\Controllers\Api\Desktop\V1;

use App\Http\Controllers\Controller;
use App\Services\DesktopSync\CapabilityService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(private readonly CapabilityService $capabilities)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->capabilities->health());
    }
}
