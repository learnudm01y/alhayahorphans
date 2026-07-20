<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServerSyncAction;
use Illuminate\Support\Facades\Log;

class ServerActionController extends Controller
{
    /**
     * Get pending actions
     */
    public function pullActions(Request $request)
    {
        $actions = ServerSyncAction::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        if ($actions->isNotEmpty()) {
            ServerSyncAction::whereIn('id', $actions->pluck('id'))
                ->update(['delivery_status' => 'delivered_to_app']);
        }

        return response()->json([
            'success' => true,
            'actions' => $actions
        ]);
    }

    /**
     * Acknowledge actions
     */
    public function ackActions(Request $request)
    {
        $actionIds = $request->input('action_ids', []);
        
        if (!empty($actionIds)) {
            ServerSyncAction::whereIn('id', $actionIds)
                ->update([
                    'status' => 'completed',
                    'delivery_status' => 'executed_in_app'
                ]);
        }

        return response()->json([
            'success' => true
        ]);
    }
}
