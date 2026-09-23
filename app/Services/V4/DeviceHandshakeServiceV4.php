<?php

namespace App\Services\V4;

use Illuminate\Support\Facades\DB;

/**
 * DeviceHandshakeServiceV4
 *
 * تسجيل/تحديث الجهاز في device_registry_v4 + تحديث صحة المزامنة.
 */
class DeviceHandshakeServiceV4
{
    /**
     * تسجيل جهاز أو تحديث آخر ظهور.
     */
    public function register(array $data): array
    {
        $deviceId = $data['device_id'] ?? null;
        if (!$deviceId) {
            throw new \InvalidArgumentException('device_id is required');
        }

        $now = now();

        $existing = DB::table('device_registry_v4')
            ->where('device_id', $deviceId)
            ->first();

        if ($existing) {
            DB::table('device_registry_v4')
                ->where('device_id', $deviceId)
                ->update([
                    'device_label' => $data['device_label'] ?? $existing->device_label,
                    'user_id' => $data['user_id'] ?? $existing->user_id,
                    'app_version' => $data['app_version'] ?? $existing->app_version,
                    'last_seen_at' => $now,
                    'health_status' => $data['health_status'] ?? $existing->health_status,
                    'updated_at' => $now,
                ]);
            $id = $existing->id;
        } else {
            $id = DB::table('device_registry_v4')->insertGetId([
                'device_id' => $deviceId,
                'device_label' => $data['device_label'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => $now,
                'last_sync_at' => $data['last_sync_at'] ?? null,
                'pending_ops_count' => $data['pending_ops_count'] ?? 0,
                'health_status' => $data['health_status'] ?? 'healthy',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return json_decode(json_encode(DB::table('device_registry_v4')->where('id', $id)->first()), true);
    }

    /**
     * تحديث حالة صحة جهاز واحد.
     */
    public function updateHealth(string $deviceId, string $status, ?int $pendingOps = null): void
    {
        $update = [
            'health_status' => $status,
            'last_seen_at' => now(),
            'updated_at' => now(),
        ];

        if ($pendingOps !== null) {
            $update['pending_ops_count'] = $pendingOps;
        }

        DB::table('device_registry_v4')
            ->where('device_id', $deviceId)
            ->update($update);
    }

    /**
     * عرض صحة كل الأجهزة.
     */
    public function allDevices()
    {
        return DB::table('device_registry_v4')
            ->orderByDesc('last_seen_at')
            ->get();
    }

    /**
     * تسجيل نجاح مزامنة.
     */
    public function markSynced(string $deviceId): void
    {
        DB::table('device_registry_v4')
            ->where('device_id', $deviceId)
            ->update([
                'last_sync_at' => now(),
                'health_status' => 'healthy',
                'updated_at' => now(),
            ]);
    }
}
