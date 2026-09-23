<?php

namespace App\Services\V4;

use Illuminate\Support\Facades\DB;

/**
 * AuditLoggerV4 — يكتب في record_audit_log_v4 (الخيار المعتمد أ).
 *
 * الغرض:
 * - تتبع دقيق للتعديلات على الأجهزة الأوفلاين (10 أجهزة).
 * - حفظ تاريخ موثوق للحقول الحساسة يستخدمه ConflictDetectionServiceV4
 *   كمصدر تاريخي عند عمليات المزامنة المتزامنة.
 *
 * لا يُثبّت أي استثناء على مسار المزامنة — فشل التدقيق يُسجَّل ولا يكسر العملية.
 */
class AuditLoggerV4
{
    /**
     * حقول حسّاسة لكل كيان — نفس خريطة stripSensitiveFields في IdempotentUpsertServiceV4
     * + حقول وفاة حساسة.
     */
    public const SENSITIVE_FIELDS = [
        'guardian_bank_accounts' => ['iban_usd', 'iban_shekel', 'bank_name'],
        'sponsorships' => ['sponsorship_status_id', 'sponsorship_end_date'],
        'dead_people' => ['father_death_date', 'mother_death_date', 'father_death_reason', 'mother_death_reason'],
        'additional_deceased' => ['death_date', 'death_reason'],
        'data' => ['data_id_number', 'data_phone_number', 'data_alt_phone_number'],
        're_people' => ['person_id', 'person_birth_date'],
    ];

    public static function sensitiveFieldsFor(string $entityType): array
    {
        return self::SENSITIVE_FIELDS[$entityType] ?? [];
    }

    public static function isSensitive(string $entityType, string $field): bool
    {
        return in_array($field, self::sensitiveFieldsFor($entityType), true);
    }

    /**
     * سجل إنشاء سجل كامل.
     */
    public function logCreate(
        string $entityType,
        string $clientUuid,
        int $recordId,
        array $payload,
        string $deviceId,
        ?string $idempotencyKey = null,
        ?int $userId = null
    ): void {
        $sensitivePresent = array_filter(
            array_keys($payload),
            fn ($f) => self::isSensitive($entityType, (string) $f)
        );

        $this->insert([
            'entity_type' => $entityType,
            'client_uuid' => $clientUuid,
            'record_id' => $recordId,
            'operation' => 'create',
            'field_name' => null,
            'old_value' => null,
            'new_value' => json_encode($this->subsetSensitive($entityType, $payload), JSON_UNESCAPED_UNICODE),
            'is_sensitive' => !empty($sensitivePresent),
            'changed_by_device' => $deviceId,
            'changed_by_user_id' => $userId,
            'idempotency_key' => $idempotencyKey,
            'source' => 'app_v4',
            'changed_at' => now(),
        ]);
    }

    /**
     * سجل فروقات الحقول بعد upsert + الحقول الحسّاسة المحظورة (لم تُطبَّق).
     *
     * @param object|null $before    السجل قبل التحديث (null عند الإنشاء)
     * @param array       $payload   ما ورد فعلياً من الجهاز
     * @param array       $stripped  الحقول الحسّاسة التي أُزيلت قبل update
     */
    public function logUpdateDiffs(
        string $entityType,
        string $clientUuid,
        int $recordId,
        ?object $before,
        array $payload,
        array $stripped = [],
        string $deviceId = 'unknown',
        ?string $idempotencyKey = null,
        ?int $userId = null
    ): void {
        foreach ($payload as $field => $newValue) {
            if (in_array($field, ['client_uuid', 'sync_origin_device_id', 'needs_review', 'updated_at', 'created_at'], true)) {
                continue;
            }

            $oldValue = $before->{$field} ?? null;
            if ($this->valuesEqual($oldValue, $newValue)) {
                continue;
            }

            $blocked = in_array($field, $stripped, true);

            $this->insert([
                'entity_type' => $entityType,
                'client_uuid' => $clientUuid,
                'record_id' => $recordId,
                'operation' => $blocked ? 'blocked_sensitive' : 'update',
                'field_name' => (string) $field,
                'old_value' => $this->stringify($oldValue),
                'new_value' => $this->stringify($newValue),
                'is_sensitive' => self::isSensitive($entityType, (string) $field) || $blocked,
                'changed_by_device' => $deviceId,
                'changed_by_user_id' => $userId,
                'idempotency_key' => $idempotencyKey,
                'source' => 'app_v4',
                'changed_at' => now(),
            ]);
        }

        // حقول حسّاسة حاول الجهاز تغييرها ولم تكن في $payload المُنقّى بعد strip
        // (strip يجري قبل الاستعلام بالـ payload الأصلي — نمرر الأعمدة المحظورة صراحة)
        foreach ($stripped as $field) {
            // إذا لم تُسجَّل أعلاه لأن القيمة لم تتغير في payload المنقّى
            $already = DB::table('record_audit_log_v4')
                ->where('client_uuid', $clientUuid)
                ->where('idempotency_key', $idempotencyKey)
                ->where('field_name', $field)
                ->where('operation', 'blocked_sensitive')
                ->exists();

            if ($already) {
                continue;
            }

            $oldValue = $before->{$field} ?? null;
            $this->insert([
                'entity_type' => $entityType,
                'client_uuid' => $clientUuid,
                'record_id' => $recordId,
                'operation' => 'blocked_sensitive',
                'field_name' => (string) $field,
                'old_value' => $this->stringify($oldValue),
                'new_value' => null,
                'is_sensitive' => true,
                'changed_by_device' => $deviceId,
                'changed_by_user_id' => $userId,
                'idempotency_key' => $idempotencyKey,
                'source' => 'app_v4',
                'changed_at' => now(),
            ]);
        }
    }

    /**
     * تاريخ تغييرات حقل حسّاس على مستوى سجل — يُستخدم كاشف التعارض.
     *
     * @return array<int, object>
     */
    public function historyFor(string $clientUuid, ?string $field = null, int $limit = 200): array
    {
        $q = DB::table('record_audit_log_v4')
            ->where('client_uuid', $clientUuid)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)));

        if ($field !== null) {
            $q->where('field_name', $field);
        }

        return $q->get()->all();
    }

    protected function subsetSensitive(string $entityType, array $payload): array
    {
        $fields = self::sensitiveFieldsFor($entityType);
        if (empty($fields)) {
            return [];
        }

        $out = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $payload)) {
                $out[$f] = $payload[$f];
            }
        }

        return $out;
    }

    protected function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return (string) $a === (string) $b;
    }

    protected function stringify(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_array($v) || is_object($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return (string) $v;
    }

    protected function insert(array $row): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('record_audit_log_v4')) {
                return;
            }
            DB::table('record_audit_log_v4')->insert($row);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AuditLoggerV4 insert failed', [
                'error' => $e->getMessage(),
                'entity_type' => $row['entity_type'] ?? null,
                'client_uuid' => $row['client_uuid'] ?? null,
            ]);
        }
    }
}
