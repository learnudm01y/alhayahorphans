<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsV4ReferenceData;
use Tests\TestCase;

class SyncIdempotencyV4Test extends TestCase
{
    use RefreshDatabase, SeedsV4ReferenceData;

    protected User $user;
    protected string $deviceId = 'test-device-A';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedV4ReferenceData();
        $this->user = User::factory()->create();
    }

    protected function headers(array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $this->user->createToken('v4-test')->plainTextToken,
            'X-Device-Id' => $this->deviceId,
            'X-Sync-Source' => 'app_v4',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extra);
    }

    public function test_registration_requires_idempotency_key(): void
    {
        $response = $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => (string) Str::uuid(),
            'payload' => ['first_name' => 'Test'],
        ], $this->headers());

        $response->assertStatus(400);
    }

    public function test_duplicate_idempotency_key_returns_cached_response_without_new_insert(): void
    {
        $clientUuid = (string) Str::uuid();
        $idempotencyKey = hash('sha256', $clientUuid . '|create|payload-v1');

        $payload = [
            'entity_type' => 're_people',
            'client_uuid' => $clientUuid,
            'payload' => [
                'registration_id' => '000001',
                'first_name' => 'أحمد',
                'person_id' => '123456789',
                'person_birth_date' => '2010-01-01',
            ],
        ];

        $r1 = $this->postJson('/api/mobile/v4/sync/registration', $payload, $this->headers([
            'X-Idempotency-Key' => $idempotencyKey,
        ]));
        $r1->assertSuccessful();

        $countAfterFirst = \DB::table('re_people')->count();
        $this->assertSame(1, $countAfterFirst);

        // إعادة إرسال 3 مرات إضافية بنفس المفتاح (محاكاة Retry بعد انقطاع)
        for ($i = 0; $i < 3; $i++) {
            $r = $this->postJson('/api/mobile/v4/sync/registration', $payload, $this->headers([
                'X-Idempotency-Key' => $idempotencyKey,
            ]));
            $r->assertSuccessful();
            $this->assertEquals($r1->json(), $r->json(), "Retry #{$i} response mismatch");
        }

        $this->assertSame(1, \DB::table('re_people')->count(), 'No duplicate rows after retries');
        $this->assertSame(1, \DB::table('sync_idempotency_log_v4')->where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_same_client_uuid_upserts_not_duplicates(): void
    {
        $clientUuid = (string) Str::uuid();

        $payload = [
            'entity_type' => 'data',
            'client_uuid' => $clientUuid,
            'payload' => $this->validDataPayload(),
        ];

        $r1 = $this->postJson('/api/mobile/v4/sync/registration', $payload, $this->headers([
            'X-Idempotency-Key' => hash('sha256', 'key-1-' . $clientUuid),
        ]));
        $r1->assertStatus(201);

        // idempotency key مختلف لكن نفس client_uuid → upsert لا insert
        $payload['payload']['data_first_name'] = 'سارة محدثة';
        $r2 = $this->postJson('/api/mobile/v4/sync/registration', $payload, $this->headers([
            'X-Idempotency-Key' => hash('sha256', 'key-2-' . $clientUuid),
        ]));
        $r2->assertSuccessful();

        $this->assertSame(1, \DB::table('data')->where('client_uuid', $clientUuid)->count());
        $this->assertSame('سارة محدثة', \DB::table('data')->where('client_uuid', $clientUuid)->value('data_first_name'));
        $this->assertTrue($r2->json('created') === false || $r2->json('id') === $r1->json('id'));
    }

    public function test_different_client_uuids_create_separate_records(): void
    {
        $uuidA = (string) Str::uuid();
        $uuidB = (string) Str::uuid();

        foreach ([$uuidA, $uuidB] as $i => $uuid) {
            $this->postJson('/api/mobile/v4/sync/registration', [
                'entity_type' => 're_people',
                'client_uuid' => $uuid,
                'payload' => [
                    'registration_id' => '000002',
                    'first_name' => 'Person' . $i,
                    'person_id' => '11111111' . $i,
                ],
            ], $this->headers([
                'X-Idempotency-Key' => hash('sha256', 'k-' . $uuid),
            ]))->assertSuccessful();
        }

        $this->assertSame(2, \DB::table('re_people')->count());
    }

    public function test_sync_actions_batch_is_idempotent(): void
    {
        $uuid1 = (string) Str::uuid();
        $key1 = hash('sha256', 'batch-1-' . $uuid1);

        $batch = [
            'actions' => [
                [
                    'idempotency_key' => $key1,
                    'entity_type' => 'sponsorships',
                    'client_uuid' => $uuid1,
                    'payload' => [
                        'identity_number' => '987654321',
                        'orphan_name' => 'أحمد تجريبي',
                        'guardian_name' => 'خالد',
                        'guardian_identity_number' => '987654320',
                    ],
                ],
            ],
        ];

        $r1 = $this->postJson('/api/mobile/v4/sync/actions', $batch, $this->headers());
        $r1->assertSuccessful()->assertJsonPath('processed', 1);

        $countAfterFirst = \DB::table('sponsorships')->count();

        // Retry Storm: نفس الدفعة 3 مرات
        for ($i = 0; $i < 3; $i++) {
            $r = $this->postJson('/api/mobile/v4/sync/actions', $batch, $this->headers());
            $r->assertSuccessful();
            $this->assertSame($countAfterFirst, \DB::table('sponsorships')->count());
        }

        $this->assertSame($countAfterFirst, \DB::table('sponsorships')->count());
    }
}
