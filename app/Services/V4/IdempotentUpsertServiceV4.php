<?php

namespace App\Services\V4;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IdempotentUpsertServiceV4
 *
 * منطق Upsert الآمن (بدلاً من Insert الأعمى):
 * 1. فحص X-Idempotency-Key في sync_idempotency_log_v4 → موجود؟ أرجع الاستجابة المحفوظة.
 * 2. Upsert بمفتاح client_uuid لا id.
 * 3. كشف تعارض عبر ConflictDetectionServiceV4.
 * 4. حفظ الاستجابة في sync_idempotency_log_v4.
 */
class IdempotentUpsertServiceV4
{
    protected ConflictDetectionServiceV4 $conflictService;
    protected AuditLoggerV4 $auditLogger;

    public function __construct(ConflictDetectionServiceV4 $conflictService, AuditLoggerV4 $auditLogger)
    {
        $this->conflictService = $conflictService;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param string $idempotencyKey  X-Idempotency-Key من الطلب
     * @param string $entityType      نوع الكيان: data|re_people|dead_people|additional_deceased|guardian_bank_accounts|sponsorships
     * @param array  $payload         البيانات الواردة (يجب أن تحتوي client_uuid)
     * @param string $deviceId        معرّف الجهاز X-Device-Id
     * @return array{id: int, conflict: bool, client_uuid: string, created: bool}
     */
    public function handle(string $idempotencyKey, string $entityType, array $payload, string $deviceId): array
    {
        // 1) فحص التكرار على مستوى الطلب — Retry-safe
        $existingLog = DB::table('sync_idempotency_log_v4')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingLog) {
            return json_decode($existingLog->response_json, true);
        }

        return DB::transaction(function () use ($idempotencyKey, $entityType, $payload, $deviceId) {
            $table = $this->resolveTable($entityType);
            $clientUuid = $payload['client_uuid'] ?? (string) Str::uuid();

            // 2) Upsert بمفتاح client_uuid
            $existing = DB::table($table)->where('client_uuid', $clientUuid)->first();
            $created = false;
            $before = $existing;

            if ($existing) {
                // LWW: تحديث العادي، منع الحقول الحسّاسة + queue
                $sensitiveKeys = $this->blockedSensitiveKeys($entityType, $payload);
                $prepared = $this->stripSensitiveFields($entityType, $payload);
                $prepared['sync_origin_device_id'] = $deviceId;
                DB::table($table)
                    ->where('client_uuid', $clientUuid)
                    ->update($prepared);
                $recordId = (int) $existing->id;

                $this->auditLogger->logUpdateDiffs(
                    $entityType,
                    $clientUuid,
                    $recordId,
                    $before,
                    $payload,
                    $sensitiveKeys,
                    $deviceId,
                    $idempotencyKey
                );
            } else {
                $payload['client_uuid'] = $clientUuid;
                $payload['sync_origin_device_id'] = $deviceId;
                $payload['needs_review'] = $payload['needs_review'] ?? 0;
                if (!isset($payload['created_at'])) {
                    $payload['created_at'] = now();
                }
                $payload['updated_at'] = now();
                $recordId = DB::table($table)->insertGetId($payload);
                $created = true;

                $this->auditLogger->logCreate(
                    $entityType,
                    $clientUuid,
                    $recordId,
                    $payload,
                    $deviceId,
                    $idempotencyKey
                );
            }

            $record = DB::table($table)->where('id', $recordId)->first();

            // 3) كشف تعارض المحتوى (مستقل عن نجاح الـ upsert)
            $conflict = $this->conflictService->check($entityType, $record, $payload);
            if ($conflict) {
                DB::table($table)
                    ->where('id', $recordId)
                    ->update(['needs_review' => 1]);
            }

            $response = [
                'success' => true,
                'id' => $recordId,
                'client_uuid' => $clientUuid,
                'created' => $created,
                'conflict' => (bool) $conflict,
            ];

            // 4) حفظ في سجل idempotency
            DB::table('sync_idempotency_log_v4')->insert([
                'idempotency_key' => $idempotencyKey,
                'response_json' => json_encode($response),
                'entity_type' => $entityType,
                'entity_id' => $recordId,
                'created_at' => now(),
            ]);

            return $response;
        });
    }

    /**
     * الحقول الحسّاسة لا تُحدَّث تلقائياً (تذهب لطابور المراجعة).
     */
    protected function stripSensitiveFields(string $entityType, array $payload): array
    {
        $sensitiveMap = [
            'guardian_bank_accounts' => ['iban_usd', 'iban_shekel', 'bank_name'],
            'sponsorships' => ['sponsorship_status_id', 'sponsorship_end_date'],
            'dead_people' => ['father_death_date', 'mother_death_date', 'father_death_reason', 'mother_death_reason'],
        ];

        $sensitive = $sensitiveMap[$entityType] ?? [];
        foreach ($sensitive as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    protected function blockedSensitiveKeys(string $entityType, array $payload): array
    {
        $sensitiveMap = [
            'guardian_bank_accounts' => ['iban_usd', 'iban_shekel', 'bank_name'],
            'sponsorships' => ['sponsorship_status_id', 'sponsorship_end_date'],
            'dead_people' => ['father_death_date', 'mother_death_date', 'father_death_reason', 'mother_death_reason'],
        ];

        return array_values(array_intersect(array_keys($payload), $sensitiveMap[$entityType] ?? []));
    }

    protected function resolveTable(string $entityType): string
    {
        $map = [
            'data' => 'data',
            're_people' => 're_people',
            'person' => 're_people',
            'dead_people' => 'dead_people',
            'additional_deceased' => 'additional_deceased',
            'guardian_bank_accounts' => 'guardian_bank_accounts',
            'sponsorships' => 'sponsorships',
        ];

        if (!isset($map[$entityType])) {
            throw new \InvalidArgumentException("Unknown entity_type: {$entityType}");
        }

        return $map[$entityType];
    }
}
