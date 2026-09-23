<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsV4ReferenceData;
use Tests\TestCase;

class ConflictDetectionV4Test extends TestCase
{
    use RefreshDatabase, SeedsV4ReferenceData;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedV4ReferenceData();
        $this->user = User::factory()->create();
    }

    protected function headers(string $deviceId, array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $this->user->createToken('v4-test')->plainTextToken,
            'X-Device-Id' => $deviceId,
            'X-Sync-Source' => 'app_v4',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extra);
    }

    public function test_two_devices_same_national_id_flagged_as_conflict_not_duplicate(): void
    {
        $uuidA = (string) Str::uuid();
        $uuidB = (string) Str::uuid();
        $nationalId = '777777777';

        // جهاز A يسجّل شخصاً
        $rA = $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuidA,
            'payload' => [
                'registration_id' => '000100',
                'first_name' => 'محمد',
                'second_name' => 'أحمد',
                'person_id' => $nationalId,
                'person_birth_date' => '2012-05-05',
            ],
        ], $this->headers('device-A', [
            'X-Idempotency-Key' => hash('sha256', 'A-' . $uuidA),
        ]));
        $rA->assertStatus(201);

        // جهاز B يسجّل نفس الشخص (نفس رقم الهوية) — UUID مختلف
        $rB = $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuidB,
            'payload' => [
                'registration_id' => '000101',
                'first_name' => 'محمد',
                'second_name' => 'أحمد',
                'person_id' => $nationalId,
                'person_birth_date' => '2012-05-05',
            ],
        ], $this->headers('device-B', [
            'X-Idempotency-Key' => hash('sha256', 'B-' . $uuidB),
        ]));

        $rB->assertSuccessful();
        $this->assertTrue((bool) $rB->json('conflict'), 'Second device should detect conflict');

        // سجلان منفصلان (لا دمج تلقائي) لكن الثاني معلّق مراجعة
        $this->assertSame(2, \DB::table('re_people')->where('person_id', $nationalId)->count());

        // طابور التعارض موجود
        $this->assertDatabaseHas('conflict_review_queue_v4', [
            'entity_type' => 're_people',
            'match_reason' => 'person_id_match',
            'status' => 'open',
        ]);

        // السجل الثاني needs_review=1
        $this->assertSame(1, (int) \DB::table('re_people')
            ->where('client_uuid', $uuidB)
            ->value('needs_review'));
    }

    public function test_conflicts_index_and_resolve(): void
    {
        $uuidA = (string) Str::uuid();
        $uuidB = (string) Str::uuid();
        $nationalId = '777777788';

        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuidA,
            'payload' => ['registration_id' => '000200', 'first_name' => 'X', 'person_id' => $nationalId],
        ], $this->headers('device-A', ['X-Idempotency-Key' => hash('sha256', $uuidA)]));

        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuidB,
            'payload' => ['registration_id' => '000201', 'first_name' => 'Y', 'person_id' => $nationalId],
        ], $this->headers('device-B', ['X-Idempotency-Key' => hash('sha256', $uuidB)]));

        // جلب الطابور
        $list = $this->getJson('/api/mobile/v4/conflicts', $this->headers('device-A'));
        $list->assertOk()->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $list->json('total'));

        $conflictId = $list->json('data.0.id');
        $this->assertNotNull($conflictId);

        // حسم
        $resolve = $this->postJson("/api/mobile/v4/conflicts/{$conflictId}/resolve", [
            'decision' => 'keep_both',
            'notes' => 'حالتان مختلفتان فعلاً',
        ], $this->headers('device-A'));
        $resolve->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('conflict_review_queue_v4', [
            'id' => $conflictId,
            'status' => 'resolved',
        ]);
    }

    public function test_device_register_and_health(): void
    {
        $reg = $this->postJson('/api/mobile/v4/device/register', [
            'device_id' => 'field-device-01',
            'device_label' => 'جهاز ميداني 1',
            'app_version' => '4.0',
            'health_status' => 'healthy',
        ], $this->headers('field-device-01'));
        $reg->assertStatus(201)->assertJsonPath('success', true);

        // إعادة التسجيل لا تكرر
        $this->postJson('/api/mobile/v4/device/register', [
            'device_id' => 'field-device-01',
            'app_version' => '4.0',
        ], $this->headers('field-device-01'));

        $this->assertSame(1, \DB::table('device_registry_v4')->where('device_id', 'field-device-01')->count());

        $health = $this->getJson('/api/mobile/v4/device/health', $this->headers('field-device-01'));
        $health->assertOk()->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $health->json('count'));
    }

    public function test_admin_endpoints_require_auth(): void
    {
        $this->getJson('/api/mobile/v4/admin/dashboard-snapshot')->assertUnauthorized();
        $this->getJson('/api/mobile/v4/admin/reports-source')->assertUnauthorized();
        $this->getJson('/api/mobile/v4/admin/permissions-manifest')->assertUnauthorized();
        $this->getJson('/api/mobile/v4/sync/pull')->assertUnauthorized();
        $this->postJson('/api/mobile/v4/sync/actions', ['actions' => []])->assertUnauthorized();
    }

    public function test_admin_dashboard_snapshot_returns_counts(): void
    {
        $res = $this->getJson('/api/mobile/v4/admin/dashboard-snapshot', $this->headers('admin-device'));
        $res->assertOk()->assertJsonPath('success', true);
        $this->assertArrayHasKey('total_files', $res->json('snapshot'));
        $this->assertArrayHasKey('open_conflicts', $res->json('snapshot'));
    }

    public function test_sync_pull_returns_incremental_data(): void
    {
        $res = $this->getJson('/api/mobile/v4/sync/pull?since=2020-01-01 00:00:00', $this->headers('device-A'));
        $res->assertOk()->assertJsonPath('success', true);
        $this->assertArrayHasKey('data', $res->json());
        $this->assertArrayHasKey('re_people', $res->json('data'));
    }
}
