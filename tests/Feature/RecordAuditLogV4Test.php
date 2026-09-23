<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsV4ReferenceData;
use Tests\TestCase;

class RecordAuditLogV4Test extends TestCase
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
            'Authorization' => 'Bearer ' . $this->user->createToken('v4-audit')->plainTextToken,
            'X-Device-Id' => $deviceId,
            'X-Sync-Source' => 'app_v4',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extra);
    }

    public function test_migration_creates_record_audit_log_v4_table(): void
    {
        $this->assertTrue(\Schema::hasTable('record_audit_log_v4'));
        foreach ([
            'entity_type', 'client_uuid', 'record_id', 'operation', 'field_name',
            'old_value', 'new_value', 'is_sensitive', 'changed_by_device',
            'idempotency_key', 'changed_at',
        ] as $col) {
            $this->assertTrue(\Schema::hasColumn('record_audit_log_v4', $col), "missing column {$col}");
        }
    }

    public function test_create_writes_audit_row(): void
    {
        $uuid = (string) Str::uuid();

        $r = $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuid,
            'payload' => [
                'registration_id' => '910001',
                'first_name' => 'نور',
                'second_name' => 'محمد',
                'person_id' => '888888888',
                'person_birth_date' => '2011-01-01',
            ],
        ], $this->headers('device-audit-1', [
            'X-Idempotency-Key' => hash('sha256', 'audit-create-' . $uuid),
        ]));

        $r->assertStatus(201);

        $this->assertDatabaseHas('record_audit_log_v4', [
            'entity_type' => 're_people',
            'client_uuid' => $uuid,
            'operation' => 'create',
            'changed_by_device' => 'device-audit-1',
        ]);
    }

    public function test_blocked_sensitive_field_is_audited_and_not_applied(): void
    {
        $uuid = (string) Str::uuid();

        if (!\Schema::hasTable('bank_names') || !\DB::table('bank_names')->where('id', 1)->exists()) {
            if (\Schema::hasTable('bank_names')) {
                \DB::table('bank_names')->insertOrIgnore([
                    'id' => 1,
                    'description' => 'Test Bank',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // سجل ملف للربط بـ guardian_registration
        $fileId = 950001;
        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 'data',
            'client_uuid' => (string) Str::uuid(),
            'payload' => $this->validDataPayload([
                'file_id_number' => $fileId,
                'data_id_number' => 950000001,
            ]),
        ], $this->headers('device-audit-2', [
            'X-Idempotency-Key' => hash('sha256', 'data-for-bank-' . $fileId),
        ]))->assertSuccessful();

        // إنشاء حساب بنكي أوفلاين
        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 'guardian_bank_accounts',
            'client_uuid' => $uuid,
            'payload' => [
                'guardian_registration' => $fileId,
                'iban_shekel' => 'IL11-0000-0000-0000-0000',
                'iban_usd' => 'US00-OLD',
                'bank_name' => 1,
            ],
        ], $this->headers('device-audit-2', [
            'X-Idempotency-Key' => hash('sha256', 'bank-create-' . $uuid),
        ]))->assertStatus(201);

        // محاولة تعديل حقل حسّاس من جهاز آخر — يجب أن يُمنع ويُسجَّل
        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 'guardian_bank_accounts',
            'client_uuid' => $uuid,
            'payload' => [
                'guardian_registration' => $fileId,
                'iban_shekel' => 'IL11-HACKED-NEW',
                'iban_usd' => 'US00-HACKED',
                'bank_name' => 1,
            ],
        ], $this->headers('device-audit-3', [
            'X-Idempotency-Key' => hash('sha256', 'bank-update-' . $uuid),
        ]))->assertSuccessful();

        $row = \DB::table('guardian_bank_accounts')->where('client_uuid', $uuid)->first();
        $this->assertSame('IL11-0000-0000-0000-0000', $row->iban_shekel, 'sensitive field must NOT change');
        $this->assertSame('US00-OLD', $row->iban_usd);

        $blocked = \DB::table('record_audit_log_v4')
            ->where('client_uuid', $uuid)
            ->where('operation', 'blocked_sensitive')
            ->where('is_sensitive', 1)
            ->get();

        $this->assertGreaterThanOrEqual(1, $blocked->count(), 'blocked sensitive changes must be audited');
        $fields = $blocked->pluck('field_name')->all();
        $this->assertContains('iban_shekel', $fields);
    }

    public function test_non_sensitive_update_logs_old_and_new_values(): void
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuid,
            'payload' => [
                'registration_id' => '910010',
                'first_name' => 'قديم',
                'person_id' => '777000111',
                'person_birth_date' => '2010-02-02',
            ],
        ], $this->headers('device-audit-4', [
            'X-Idempotency-Key' => hash('sha256', 'upd-a-' . $uuid),
        ]))->assertStatus(201);

        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuid,
            'payload' => [
                'registration_id' => '910010',
                'first_name' => 'جديد',
                'person_id' => '777000111',
                'person_birth_date' => '2010-02-02',
            ],
        ], $this->headers('device-audit-4', [
            'X-Idempotency-Key' => hash('sha256', 'upd-b-' . $uuid),
        ]))->assertOk();

        $log = \DB::table('record_audit_log_v4')
            ->where('client_uuid', $uuid)
            ->where('operation', 'update')
            ->where('field_name', 'first_name')
            ->first();

        $this->assertNotNull($log, 'field-level update must be audited');
        $this->assertSame('قديم', $log->old_value);
        $this->assertSame('جديد', $log->new_value);
        $this->assertSame('device-audit-4', $log->changed_by_device);
    }

    public function test_audit_api_returns_history_for_client_uuid(): void
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuid,
            'payload' => [
                'registration_id' => '910020',
                'first_name' => 'أ',
                'person_id' => '555111222',
                'person_birth_date' => '2009-03-03',
            ],
        ], $this->headers('device-audit-5', [
            'X-Idempotency-Key' => hash('sha256', 'api-a-' . $uuid),
        ]))->assertStatus(201);

        $this->postJson('/api/mobile/v4/sync/registration', [
            'entity_type' => 're_people',
            'client_uuid' => $uuid,
            'payload' => [
                'registration_id' => '910020',
                'first_name' => 'ب',
                'person_id' => '555111222',
                'person_birth_date' => '2009-03-03',
            ],
        ], $this->headers('device-audit-5', [
            'X-Idempotency-Key' => hash('sha256', 'api-b-' . $uuid),
        ]))->assertOk();

        $res = $this->getJson('/api/mobile/v4/audit?client_uuid=' . $uuid, $this->headers('device-audit-5'));
        $res->assertOk()->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(2, $res->json('total'));

        $ops = collect($res->json('data'))->pluck('operation')->all();
        $this->assertContains('create', $ops);
        $this->assertContains('update', $ops);
    }

    public function test_idempotent_retry_does_not_duplicate_audit_rows(): void
    {
        $uuid = (string) Str::uuid();
        $key = hash('sha256', 'idem-audit-' . $uuid);

        $payload = [
            'entity_type' => 're_people',
            'client_uuid' => $uuid,
            'payload' => [
                'registration_id' => '910030',
                'first_name' => 'توكر',
                'person_id' => '444555666',
                'person_birth_date' => '2008-04-04',
            ],
        ];

        $this->postJson('/api/mobile/v4/sync/registration', $payload, $this->headers('device-audit-6', [
            'X-Idempotency-Key' => $key,
        ]))->assertStatus(201);

        $countAfterFirst = \DB::table('record_audit_log_v4')->where('client_uuid', $uuid)->count();

        $this->postJson('/api/mobile/v4/sync/registration', $payload, $this->headers('device-audit-6', [
            'X-Idempotency-Key' => $key,
        ]))->assertStatus(201);

        $countAfterRetry = \DB::table('record_audit_log_v4')->where('client_uuid', $uuid)->count();
        $this->assertSame($countAfterFirst, $countAfterRetry, 'retry must not duplicate audit rows');
    }
}
