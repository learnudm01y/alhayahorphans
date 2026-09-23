<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOfflineExportControllerV4 extends Controller
{
    /**
     * GET /api/mobile/v4/admin/dashboard-snapshot
     * لقطة دورية من أرقام لوحة القيادة (offline admin).
     */
    public function dashboardSnapshot(Request $request): JsonResponse
    {
        try {
            $snapshotKey = 'dashboard_' . now()->format('Y-m-d_H');
            $cached = DB::table('admin_dashboard_snapshot_v4')
                ->where('snapshot_key', $snapshotKey)
                ->first();

            if ($cached) {
                return response()->json([
                    'success' => true,
                    'snapshot' => json_decode($cached->snapshot_value_json, true),
                    'generated_at' => $cached->generated_at,
                    'cached' => true,
                ]);
            }

            $snapshot = [
                'total_files' => DB::table('data')->count(),
                'total_family_members' => DB::table('re_people')->count(),
                'total_dead' => DB::table('dead_people')->count(),
                'total_sponsorships' => DB::table('sponsorships')->count(),
                'total_bank_accounts' => DB::table('guardian_bank_accounts')->count(),
                'open_conflicts' => DB::table('conflict_review_queue_v4')->where('status', 'open')->count(),
                'pending_registrations' => DB::table('data')->where('data_request_status', 1)->count(),
                'registered_devices' => DB::table('device_registry_v4')->count(),
            ];

            $generatedAt = now();
            DB::table('admin_dashboard_snapshot_v4')->updateOrInsert(
                ['snapshot_key' => $snapshotKey],
                [
                    'snapshot_value_json' => json_encode($snapshot),
                    'generated_at' => $generatedAt,
                    'created_at' => $generatedAt,
                ]
            );

            return response()->json([
                'success' => true,
                'snapshot' => $snapshot,
                'generated_at' => $generatedAt->toDateTimeString(),
                'cached' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminOfflineExportV4 dashboardSnapshot failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate dashboard snapshot',
            ], 500);
        }
    }

    /**
     * GET /api/mobile/v4/admin/reports-source
     * بيانات مسطّحة جاهزة للتقارير المحلية.
     */
    public function reportsSource(Request $request): JsonResponse
    {
        try {
            $since = $request->query('since');
            $reportType = $request->query('type', 'all');

            $reports = [];

            if ($reportType === 'all' || $reportType === 'sponsorships') {
                $query = DB::table('sponsorships')
                    ->select('id', 'identity_number', 'orphan_name', 'guardian_name', 'guardian_identity_number',
                        'sponsorship_start_date', 'sponsorship_end_date', 'sponsorship_status_id', 'client_uuid', 'updated_at');
                if ($since) {
                    $query->where('updated_at', '>', $since);
                }
                $reports['sponsorships'] = $query->limit(1000)->get();
            }

            if ($reportType === 'all' || $reportType === 'files') {
                $query = DB::table('data')
                    ->select('id', 'file_id_number', 'data_id_number', 'data_first_name', 'data_family_name',
                        'data_province', 'data_city', 'data_request_status', 'client_uuid', 'updated_at');
                if ($since) {
                    $query->where('updated_at', '>', $since);
                }
                $reports['files'] = $query->limit(1000)->get();
            }

            if ($reportType === 'all' || $reportType === 'bank_accounts') {
                $query = DB::table('guardian_bank_accounts')
                    ->select('id', 'guardian_registration', 'bank_name', 'iban_usd', 'iban_shekel',
                        're_guardian_name', 'check_account', 'client_uuid', 'updated_at');
                if ($since) {
                    $query->where('updated_at', '>', $since);
                }
                $reports['bank_accounts'] = $query->limit(1000)->get();
            }

            // حفظ نسخة في admin_reports_source_v4 للمراجعة
            foreach ($reports as $type => $rows) {
                foreach ($rows as $row) {
                    DB::table('admin_reports_source_v4')->updateOrInsert(
                        [
                            'report_type' => $type,
                            'source_client_uuid' => $row->client_uuid ?? null,
                        ],
                        [
                            'row_data_json' => json_encode($row),
                            'last_updated_at' => $row->updated_at ?? now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'since' => $since,
                'server_time' => now()->toDateTimeString(),
                'reports' => $reports,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminOfflineExportV4 reportsSource failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to export reports source',
            ], 500);
        }
    }

    /**
     * GET /api/mobile/v4/admin/permissions-manifest
     * صلاحيات المستخدم موقّعة (HMAC).
     */
    public function permissionsManifest(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $permissions = $user->getAllPermissions()->pluck('name')->values();
            $roles = $user->getRoleNames()->values();

            $manifest = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'roles' => $roles,
                'permissions' => $permissions,
                'issued_at' => now()->toDateTimeString(),
                'expires_at' => now()->addHours(24)->toDateTimeString(),
            ];

            $signature = hash_hmac('sha256', json_encode($manifest), config('app.key'));

            DB::table('permissions_manifest_v4')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'manifest_json' => json_encode($manifest),
                    'signature' => $signature,
                    'issued_at' => now(),
                    'expires_at' => now()->addHours(24),
                    'created_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'manifest' => $manifest,
                'signature' => $signature,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminOfflineExportV4 permissionsManifest failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate permissions manifest',
            ], 500);
        }
    }
}
