<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsV4ReferenceData;
use Tests\TestCase;

class AdminOfflineV4Test extends TestCase
{
    use RefreshDatabase, SeedsV4ReferenceData;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedV4ReferenceData();
        $this->user = User::factory()->create();
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->user->createToken('v4-admin-test')->plainTextToken,
            'X-Device-Id' => 'device-admin-test',
            'X-Sync-Source' => 'app_v4',
            'Accept' => 'application/json',
        ];
    }

    public function test_dashboard_snapshot_returns_counts(): void
    {
        $res = $this->getJson('/api/mobile/v4/admin/dashboard-snapshot', $this->headers());
        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $snapshot = $res->json('snapshot');
        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('total_files', $snapshot);
        $this->assertArrayHasKey('total_sponsorships', $snapshot);
        $this->assertArrayHasKey('open_conflicts', $snapshot);
        $this->assertArrayHasKey('registered_devices', $snapshot);
        $this->assertIsInt($snapshot['total_files']);
        $this->assertIsInt($snapshot['open_conflicts']);

        // Second call within the same hour should hit the cache.
        $res2 = $this->getJson('/api/mobile/v4/admin/dashboard-snapshot', $this->headers());
        $res2->assertStatus(200)->assertJsonPath('cached', true);
    }

    public function test_reports_source_returns_flat_rows_and_persists(): void
    {
        DB::table('data')->insert($this->validDataPayload([
            'file_id_number' => 910001,
            'data_id_number' => 910000001,
            'data_first_name' => 'تقرير',
            'data_family_name' => 'تجريبي',
            'client_uuid' => (string) Str::uuid(),
        ]));

        $res = $this->getJson('/api/mobile/v4/admin/reports-source?type=files', $this->headers());
        $res->assertStatus(200)->assertJsonPath('success', true);

        $reports = $res->json('reports');
        $this->assertArrayHasKey('files', $reports);
        $this->assertNotEmpty($reports['files']);

        // Server-side cache table must receive rows.
        $this->assertGreaterThan(0,
            DB::table('admin_reports_source_v4')->where('report_type', 'files')->count());
    }

    public function test_permissions_manifest_is_signed_and_persisted(): void
    {
        $res = $this->getJson('/api/mobile/v4/admin/permissions-manifest', $this->headers());
        $res->assertStatus(200)->assertJsonPath('success', true);

        $manifest = $res->json('manifest');
        $signature = $res->json('signature');

        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature)); // sha256 hex
        $this->assertEquals($this->user->id, $manifest['user_id']);
        $this->assertArrayHasKey('roles', $manifest);
        $this->assertArrayHasKey('permissions', $manifest);
        $this->assertArrayHasKey('expires_at', $manifest);

        $expected = hash_hmac('sha256', json_encode($manifest), config('app.key'));
        $this->assertEquals($expected, $signature);

        $stored = DB::table('permissions_manifest_v4')
            ->where('user_id', $this->user->id)->first();
        $this->assertNotNull($stored);
        $this->assertEquals($signature, $stored->signature);
    }

    public function test_permissions_manifest_requires_auth(): void
    {
        $res = $this->getJson('/api/mobile/v4/admin/permissions-manifest');
        // route has auth:sanctum — unauthenticated must be rejected (401 or 404 depending on guard)
        $this->assertContains($res->status(), [401, 403]);
    }

    public function test_conflicts_index_returns_open_queue(): void
    {
        DB::table('conflict_review_queue_v4')->insert([
            'entity_type' => 're_people',
            'existing_record_id' => 1,
            'existing_record_uuid' => (string) Str::uuid(),
            'incoming_payload_json' => json_encode(['first_name' => 'x']),
            'incoming_source' => 'device',
            'incoming_device_id' => 'dev-1',
            'match_reason' => 'national_id_match',
            'match_confidence' => 95.00,
            'status' => 'open',
            'created_at' => now(),
        ]);

        $res = $this->getJson('/api/mobile/v4/conflicts?status=open', $this->headers());
        $res->assertStatus(200)->assertJsonPath('success', true);
        $this->assertGreaterThan(0, $res->json('total'));
        $first = $res->json('data.0');
        $this->assertEquals('re_people', $first['entity_type']);
        $this->assertEquals('open', $first['status']);
    }

    public function test_conflict_resolve_marks_resolved_and_clears_needs_review(): void
    {
        $uuid = (string) Str::uuid();
        DB::table('data')->insert($this->validDataPayload([
            'file_id_number' => 920001,
            'data_id_number' => 920000001,
            'data_first_name' => 'تعارض',
            'data_family_name' => 'اختبار',
            'client_uuid' => $uuid,
            'needs_review' => 1,
        ]));
        $dataId = DB::table('data')->where('client_uuid', $uuid)->value('id');

        DB::table('conflict_review_queue_v4')->insert([
            'entity_type' => 'data',
            'existing_record_id' => $dataId,
            'existing_record_uuid' => $uuid,
            'incoming_payload_json' => json_encode(['data_first_name' => 'مدموج']),
            'incoming_source' => 'device',
            'incoming_device_id' => 'dev-2',
            'match_reason' => 'name_dob_match',
            'match_confidence' => 88.00,
            'status' => 'open',
            'created_at' => now(),
        ]);
        $conflictId = DB::table('conflict_review_queue_v4')->orderByDesc('id')->value('id');

        $res = $this->postJson(
            "/api/mobile/v4/conflicts/{$conflictId}/resolve",
            ['decision' => 'merge', 'notes' => 'test merge'],
            $this->headers()
        );
        $res->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonPath('decision', 'merge');

        $updated = DB::table('conflict_review_queue_v4')->where('id', $conflictId)->first();
        $this->assertEquals('resolved', $updated->status);
        $this->assertNotNull($updated->resolved_at);
        $this->assertEquals($this->user->id, $updated->resolved_by_user_id);

        // needs_review cleared on the underlying record.
        $this->assertEquals(0, (int) DB::table('data')->where('id', $dataId)->value('needs_review'));
        // Incoming merge applied data_first_name.
        $this->assertEquals('مدموج', DB::table('data')->where('id', $dataId)->value('data_first_name'));
    }

    public function test_file_index_v4_table_exists_and_backfilled_from_attachments(): void
    {
        $this->assertTrue(\Schema::hasTable('file_index_v4'));

        if (\Schema::hasTable('attachments')) {
            DB::table('attachments')->insert([
                'person_identity_number' => '999888777',
                'stored_file_name' => '999888777_id.jpg',
                'file_path' => 'uploads/999888777_id.jpg',
                'file_type' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $attId = DB::table('attachments')->orderByDesc('id')->value('id');

            // Re-run backfill insert (same logic as migration) — idempotent.
            $affected = DB::insert("
                INSERT INTO file_index_v4
                    (client_uuid, identity_number, stored_file_name, file_path, file_type,
                     source, source_id, status, last_synced_at, created_at, updated_at)
                SELECT UUID(), a.person_identity_number, a.stored_file_name, a.file_path, a.file_type,
                       'attachments', a.id, 'active', a.updated_at, a.created_at, a.updated_at
                FROM attachments a
                LEFT JOIN file_index_v4 f
                    ON f.source = 'attachments' AND f.source_id = a.id
                WHERE f.id IS NULL AND a.id = ?
            ", [$attId]);

            $row = DB::table('file_index_v4')
                ->where('source', 'attachments')
                ->where('source_id', $attId)
                ->first();
            $this->assertNotNull($row);
            $this->assertEquals('999888777', $row->identity_number);
            $this->assertEquals('active', $row->status);
            $this->assertNotEmpty($row->client_uuid);
        } else {
            $this->markSkipped('attachments table not present');
        }
    }

    public function test_device_health_returns_registry(): void
    {
        DB::table('device_registry_v4')->insert([
            'device_id' => 'dev-health-1',
            'device_label' => 'Samsung Test',
            'app_version' => '3.2',
            'health_status' => 'healthy',
            'pending_ops_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getJson('/api/mobile/v4/device/health', $this->headers());
        $res->assertStatus(200)->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $res->json('count'));
        $devices = $res->json('devices');
        $found = false;
        foreach ($devices as $d) {
            if (($d['device_id'] ?? null) === 'dev-health-1') $found = true;
        }
        $this->assertTrue($found, 'inserted device must appear in health list');
    }
}
