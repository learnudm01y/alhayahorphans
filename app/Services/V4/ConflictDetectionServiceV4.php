<?php

namespace App\Services\V4;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ConflictDetectionServiceV4
 *
 * كاشف التعارض بالمحتوى (Content-Level) — يعمل بغضّ النظر عن مصدر السجل
 * (موقع Laravel، جهاز v3، أو جهاز v4). لا يُدمج تلقائياً — يسجّل في conflict_review_queue_v4.
 */
class ConflictDetectionServiceV4
{
    /** حقول المطابقة لكل نوع كيان */
    protected array $matchFieldsMap = [
        're_people' => ['person_id'],
        'person' => ['person_id'],
        'data' => ['data_id_number'],
        'guardian_bank_accounts' => [],
        'sponsorships' => ['identity_number'],
        'dead_people' => [],
        'additional_deceased' => ['person_id'],
    ];

    /**
     * @param string $entityType
     * @param object $record       السجل الموجود بعد الـ upsert
     * @param array  $incomingPayload
     * @return array{reason: string, confidence: float}|null
     */
    public function check(string $entityType, object $record, array $incomingPayload): ?array
    {
        $matchFields = $this->matchFieldsMap[$entityType] ?? [];

        if (empty($matchFields)) {
            return null;
        }

        $table = $this->resolveTable($entityType);

        foreach ($matchFields as $field) {
            if (empty($incomingPayload[$field])) {
                continue;
            }

            $exact = DB::table($table)
                ->where($field, $incomingPayload[$field])
                ->where('id', '!=', $record->id ?? 0)
                ->first();

            if ($exact) {
                return $this->logConflict($entityType, $record, $incomingPayload, "{$field}_match", 1.0);
            }
        }

        // تشابه احتمالي: الاسم الكامل + تاريخ الميلاد (re_people فقط)
        if (in_array($entityType, ['re_people', 'person'], true)) {
            $fullNameParts = array_filter([
                $incomingPayload['first_name'] ?? null,
                $incomingPayload['second_name'] ?? null,
                $incomingPayload['third_name'] ?? null,
                $incomingPayload['last_name'] ?? null,
            ]);
            $dob = $incomingPayload['person_birth_date'] ?? null;

            if (!empty($fullNameParts) && $dob) {
                $fullName = implode(' ', $fullNameParts);
                $similar = DB::table('re_people')
                    ->where('person_birth_date', $dob)
                    ->where('id', '!=', $record->id ?? 0)
                    ->where(function ($q) use ($incomingPayload) {
                        if (!empty($incomingPayload['person_id'])) {
                            $q->where('person_id', $incomingPayload['person_id']);
                        }
                    })
                    ->first();

                if ($similar) {
                    return $this->logConflict($entityType, $record, $incomingPayload, 'name_dob_match', 0.75);
                }
            }
        }

        return null;
    }

    protected function logConflict(string $entityType, object $record, array $incomingPayload, string $reason, float $confidence): array
    {
        DB::table('conflict_review_queue_v4')->insert([
            'entity_type' => $entityType,
            'existing_record_id' => $record->id ?? 0,
            'existing_record_uuid' => $record->client_uuid ?? null,
            'incoming_payload_json' => json_encode($incomingPayload),
            'incoming_source' => request()->header('X-Sync-Source', 'app_v4'),
            'incoming_device_id' => request()->header('X-Device-Id'),
            'match_reason' => $reason,
            'match_confidence' => $confidence,
            'status' => 'open',
            'created_at' => now(),
        ]);

        return ['reason' => $reason, 'confidence' => $confidence];
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

        return $map[$entityType] ?? $entityType;
    }
}
