<?php

namespace App\Traits;

use App\Models\ServerSyncAction;
use Illuminate\Support\Facades\Log;

trait SyncsLookupToMobile
{
    public static function bootSyncsLookupToMobile()
    {
        static::saved(function ($model) {
            $model->queueLookupSyncAction();
        });

        static::deleted(function ($model) {
            $model->queueLookupSyncAction();
        });
    }

    protected function queueLookupSyncAction()
    {
        try {
            $lookupType = $this->getLookupType();
            
            if (!$lookupType) {
                return;
            }

            // بدلاً من إرسال السجل الواحد، سنرسل قائمة التصنيفات كاملة للنوع المحدد
            // لضمان التزامن الكامل وتجنب التعارضات في تطبيق الموبايل
            $data = $this->fetchAllLookupsForSync();

            $action = ServerSyncAction::create([
                'action_type' => 'lookup_update',
                'entity_id' => 0, // 0 means a full table update
                'payload' => [
                    'type' => $lookupType,
                    'data' => $data
                ],
                'status' => 'pending',
                'delivery_status' => 'pending',
                'user_id' => auth()->id() ?? 1
            ]);

            Log::info("✅ [SYNC ACTION] Queued lookup_update for {$lookupType}");

            // Broadcast via WebSocket immediately
            try {
                $payloadData = [
                    'type' => 'realtimeUpdate',
                    'action_type' => 'lookup_update',
                    'lookup_type' => $lookupType
                ];
                \Illuminate\Support\Facades\Http::timeout(3)->post('http://127.0.0.1:6001/broadcast', $payloadData);
            } catch (\Exception $e) {
                Log::warning("⚠️ [SYNC ACTION] WebSocket broadcast failed: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            Log::error("❌ [SYNC ACTION] Failed to queue lookup_update: " . $e->getMessage());
        }
    }

    protected function getLookupType()
    {
        $table = $this->getTable();
        
        switch ($table) {
            case 'sponsors':
                return 'sponsors';
            case 'sponsorship_statuses':
                return 'sponsorship_statuses';
            case 'bank_names':
                return 'bank_names';
            case 'health_statuses':
                return 'health_statuses';
            case 'city':
                return 'cities';
            case 'type_of_guarantee':
                return 'sponsorship_types';
            default:
                return null;
        }
    }

    protected function fetchAllLookupsForSync()
    {
        $table = $this->getTable();
        
        switch ($table) {
            case 'sponsors':
                return \Illuminate\Support\Facades\DB::table('sponsors')
                    ->select('id', 'sponsor_name as name', 'sponsor_short_name as short_name')
                    ->whereNotNull('sponsor_name')
                    ->orderBy('sponsor_name')
                    ->get();
            case 'sponsorship_statuses':
                return \Illuminate\Support\Facades\DB::table('sponsorship_statuses')
                    ->select('id', 'description as name')
                    ->orderBy('id')
                    ->get();
            case 'bank_names':
                return \Illuminate\Support\Facades\DB::table('bank_names')
                    ->select('id', 'description')
                    ->orderBy('description')
                    ->get();
            case 'health_statuses':
                return \Illuminate\Support\Facades\DB::table('health_statuses')
                    ->select('id', 'description')
                    ->orderBy('id')
                    ->get();
            case 'city':
                return \Illuminate\Support\Facades\DB::table('city')
                    ->select('id', 'city')
                    ->orderBy('city')
                    ->get();
            case 'type_of_guarantee':
                return \Illuminate\Support\Facades\DB::table('type_of_guarantee')
                    ->select('id', 'description')
                    ->orderBy('id')
                    ->get();
            default:
                return [];
        }
    }
}
