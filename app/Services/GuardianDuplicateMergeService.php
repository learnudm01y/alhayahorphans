<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GuardianDuplicateMergeService
{
    protected $guardianFileService;

    protected $extendedEntityTypes = ['guardian', 'bank_account', 'deceased'];

    protected $pathTables = ['attachments', 'enhanced_attachments'];

    public function __construct(GuardianFileService $guardianFileService)
    {
        $this->guardianFileService = $guardianFileService;
    }

    public function scan(): array
    {
        $identities = DB::table('data')
            ->whereNotNull('data_id_number')
            ->where('data_id_number', '!=', 0)
            ->groupBy('data_id_number')
            ->havingRaw('COUNT(*) > 1')
            ->select('data_id_number')
            ->orderBy('data_id_number')
            ->pluck('data_id_number');

        $groups = [];
        $totalRows = 0;

        foreach ($identities as $identity) {
            $rows = DB::table('data')
                ->where('data_id_number', $identity)
                ->orderBy('id')
                ->get();

            $files = [];

            foreach ($rows as $row) {
                $candidates = $this->guardianFileService->fileCandidates((string) $row->file_id_number);

                $files[] = [
                    'id' => (int) $row->id,
                    'file_id_number' => $row->file_id_number,
                    'created_at' => (string) $row->created_at,
                    'data_request_status' => $row->data_request_status !== null ? (int) $row->data_request_status : null,
                    'source' => $row->data_user_insert_data,
                    'full_name' => trim(implode(' ', array_filter([
                        $row->data_first_name,
                        $row->data_father_name,
                        $row->data_grand_father_name,
                        $row->data_family_name,
                    ]))),
                    'phone' => $row->data_phone_number ? (string) $row->data_phone_number : null,
                    'counts' => $this->countReferences($candidates),
                ];

                $totalRows++;
            }

            $groups[] = [
                'identity' => (string) $identity,
                'total' => count($files),
                'recommended_keep' => $files[0]['file_id_number'] ?? null,
                'files' => $files,
            ];
        }

        return [
            'groups' => $groups,
            'total_groups' => count($groups),
            'total_rows' => $totalRows,
            'rows_to_merge' => $totalRows - count($groups),
        ];
    }

    public function mergeGroup(string $identity, ?string $keepFileNumber = null): array
    {
        $identity = trim($identity);

        $rows = DB::table('data')
            ->where('data_id_number', $identity)
            ->orderBy('id')
            ->get();

        if ($rows->count() < 2) {
            return [
                'identity' => $identity,
                'merged' => false,
                'message' => 'لا توجد سجلات مكررة لهذه الهوية',
            ];
        }

        $keep = $this->resolveKeepRow($rows, $keepFileNumber);
        $keepFile = (string) $keep->file_id_number;

        $losers = $rows->where('id', '!=', $keep->id)->values();
        $processable = collect();
        $warnings = [];

        foreach ($losers as $loser) {
            if (
                $this->guardianFileService->normalizeFileNumber((string) $loser->file_id_number)
                === $this->guardianFileService->normalizeFileNumber($keepFile)
            ) {
                $warnings[] = 'تم تجاهل السجل ' . $loser->file_id_number . ' لتطابق صيغة رقم الملف مع الملف المحتفظ به';
                continue;
            }

            $processable->push($loser);
        }

        if ($processable->isEmpty()) {
            return [
                'identity' => $identity,
                'keep_file' => $keepFile,
                'merged' => false,
                'message' => 'لا يمكن دمج السجلات المحددة',
                'warnings' => $warnings,
            ];
        }

        $report = [
            'identity' => $identity,
            'keep_file' => $keepFile,
            'removed_files' => $processable->pluck('file_id_number')->values()->all(),
            'moved_counts' => [],
            'merged_fields' => [],
            'data_rows_deleted' => 0,
            'warnings' => $warnings,
        ];

        try {
            DB::transaction(function () use ($processable, $keep, $keepFile, $identity, &$report) {
                $report['moved_counts'] = $this->runMoves($processable, $keepFile, $identity);

                $remaining = $this->countRemaining($processable);

                if (array_sum($remaining) > 0) {
                    $extra = $this->runMoves($processable, $keepFile, $identity);
                    $report['moved_counts'] = $this->sumCounts($report['moved_counts'], $extra);
                    $remaining = $this->countRemaining($processable);
                }

                if (array_sum($remaining) > 0) {
                    throw new \RuntimeException(
                        'تعذر نقل كل السجلات المرتبطة: ' . json_encode($remaining, JSON_UNESCAPED_UNICODE)
                    );
                }

                $report['merged_fields'] = $this->mergeDataColumnValues(
                    (int) $keep->id,
                    $processable->pluck('id')->map(function ($id) {
                        return (int) $id;
                    })->all()
                );

                $report['data_rows_deleted'] = DB::table('data')
                    ->whereIn('id', $processable->pluck('id')->all())
                    ->delete();
            });
        } catch (\Throwable $e) {
            Log::error('GUARDIAN_DUPLICATE_MERGE_FAILED', [
                'identity' => $identity,
                'keep_file' => $keepFile,
                'error' => $e->getMessage(),
            ]);

            return [
                'identity' => $identity,
                'keep_file' => $keepFile,
                'merged' => false,
                'message' => 'فشل الدمج: ' . $e->getMessage(),
                'warnings' => $warnings,
            ];
        }

        $folders = ['files_moved' => 0, 'rows_updated' => 0];

        foreach ($processable as $loser) {
            $folderResult = $this->moveAttachmentFolders((string) $loser->file_id_number, $keepFile);

            $folders['files_moved'] += $folderResult['files_moved'];
            $folders['rows_updated'] += $folderResult['rows_updated'];
            $report['warnings'] = array_merge($report['warnings'], $folderResult['warnings']);
        }

        $report['attachments'] = $folders;
        $report['merged'] = true;
        $report['message'] = 'تم دمج ' . count($report['removed_files']) . ' ملف في الملف ' . $keepFile;

        Log::info('GUARDIAN_DUPLICATE_MERGE_APPLIED', [
            'identity' => $identity,
            'keep_file' => $keepFile,
            'removed_files' => $report['removed_files'],
            'moved_counts' => $report['moved_counts'],
            'data_rows_deleted' => $report['data_rows_deleted'],
        ]);

        return $report;
    }

    public function mergeAll(): array
    {
        $scan = $this->scan();
        $reports = [];

        foreach ($scan['groups'] as $group) {
            try {
                $reports[] = $this->mergeGroup($group['identity'], $group['recommended_keep']);
            } catch (\Throwable $e) {
                $reports[] = [
                    'identity' => $group['identity'],
                    'merged' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'total_groups' => count($reports),
            'merged_groups' => count(array_filter($reports, function ($report) {
                return !empty($report['merged']);
            })),
            'failed_groups' => count(array_filter($reports, function ($report) {
                return empty($report['merged']);
            })),
            'reports' => $reports,
        ];
    }

    public function countReferences(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        $counts = [];

        $counts['sponsorships'] = DB::table('sponsorships')
            ->whereIn('relation_id_number', $candidates)
            ->count();

        $counts['re_people'] = DB::table('re_people')
            ->whereIn('registration_id', $candidates)
            ->count();

        $counts['dead_people'] = DB::table('dead_people')
            ->whereIn('re_file_id', $candidates)
            ->count();

        if (Schema::hasTable('additional_deceased')) {
            $counts['additional_deceased'] = DB::table('additional_deceased')
                ->whereIn('re_file_id', $candidates)
                ->count();
        }

        if (Schema::hasTable('guardian_bank_accounts')) {
            $counts['guardian_bank_accounts'] = DB::table('guardian_bank_accounts')
                ->whereIn('guardian_registration', $candidates)
                ->count();
        }

        if (Schema::hasTable('portal_general_registration_field_values')) {
            $counts['portal_fields'] = DB::table('portal_general_registration_field_values')
                ->whereIn('file_id_number', $candidates)
                ->count();
        }

        if (Schema::hasTable('google_drive_uploads')) {
            $counts['google_drive_uploads'] = DB::table('google_drive_uploads')
                ->whereIn('entity_id', $candidates)
                ->whereIn('entity_type', $this->extendedEntityTypes)
                ->count();
        }

        if (Schema::hasTable('sync_progress')) {
            $counts['sync_progress'] = DB::table('sync_progress')
                ->whereIn('entity_id', $candidates)
                ->whereIn('entity_type', $this->extendedEntityTypes)
                ->count();
        }

        if (Schema::hasTable('enhanced_attachments')) {
            $counts['enhanced_attachments'] = DB::table('enhanced_attachments')
                ->whereIn('record_number', $candidates)
                ->count();
        }

        if (Schema::hasTable('aid_management')) {
            $numeric = $this->numericCandidates($candidates);

            if (!empty($numeric)) {
                $counts['aid_management'] = DB::table('aid_management')
                    ->whereIn('file_id', $numeric)
                    ->count();
            }
        }

        $counts['attachments'] = $this->countAttachmentRows($candidates);

        return $counts;
    }

    protected function resolveKeepRow($rows, ?string $keepFileNumber)
    {
        if ($keepFileNumber !== null && trim($keepFileNumber) !== '') {
            $candidates = $this->guardianFileService->fileCandidates(trim($keepFileNumber));

            foreach ($rows as $row) {
                if (in_array((string) $row->file_id_number, $candidates, true)) {
                    return $row;
                }
            }
        }

        return $rows->first();
    }

    protected function runMoves($losers, string $keepFile, string $identity): array
    {
        $counts = [];

        foreach ($losers as $loser) {
            $part = $this->moveAllRecords((string) $loser->file_id_number, $keepFile, $identity);
            $counts = $this->sumCounts($counts, $part);
        }

        return $counts;
    }

    protected function moveAllRecords(string $fromFile, string $toFile, string $identity): array
    {
        $counts = $this->guardianFileService->relinkFile($fromFile, $toFile, null, $identity);

        return $this->sumCounts($counts ?: [], $this->moveExtendedRecords($fromFile, $toFile));
    }

    protected function moveExtendedRecords(string $fromFile, string $toFile): array
    {
        $counts = [];
        $fromCandidates = $this->guardianFileService->fileCandidates($fromFile);
        $normalizedTo = $this->guardianFileService->normalizeFileNumber($toFile);
        $toCandidates = $this->guardianFileService->fileCandidates($toFile);

        if (empty($fromCandidates)) {
            return $counts;
        }

        if (Schema::hasTable('google_drive_uploads')) {
            $ids = DB::table('google_drive_uploads')
                ->whereIn('entity_id', $fromCandidates)
                ->whereIn('entity_type', $this->extendedEntityTypes)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $hashes = DB::table('google_drive_uploads')
                    ->whereIn('id', $ids)
                    ->pluck('local_file_hash')
                    ->all();

                DB::table('google_drive_uploads')
                    ->whereIn('entity_id', $toCandidates)
                    ->whereIn('entity_type', $this->extendedEntityTypes)
                    ->whereIn('local_file_hash', $hashes)
                    ->delete();

                $counts['google_drive_uploads'] = DB::table('google_drive_uploads')
                    ->whereIn('id', $ids)
                    ->update($this->withTimestamp('google_drive_uploads', [
                        'entity_id' => $normalizedTo,
                    ]));
            }
        }

        if (Schema::hasTable('sync_progress')) {
            $counts['sync_progress'] = DB::table('sync_progress')
                ->whereIn('entity_id', $fromCandidates)
                ->whereIn('entity_type', $this->extendedEntityTypes)
                ->update($this->withTimestamp('sync_progress', [
                    'entity_id' => $normalizedTo,
                ]));
        }

        if (Schema::hasTable('enhanced_attachments')) {
            $counts['enhanced_attachments'] = DB::table('enhanced_attachments')
                ->whereIn('record_number', $fromCandidates)
                ->update($this->withTimestamp('enhanced_attachments', [
                    'record_number' => $normalizedTo,
                ]));
        }

        if (Schema::hasTable('aid_management')) {
            $numeric = $this->numericCandidates($fromCandidates);

            if (!empty($numeric)) {
                $counts['aid_management'] = DB::table('aid_management')
                    ->whereIn('file_id', $numeric)
                    ->update($this->withTimestamp('aid_management', [
                        'file_id' => (int) ltrim($normalizedTo, '0') ?: 0,
                    ]));
            }
        }

        return $counts;
    }

    protected function countRemaining($losers): array
    {
        $remaining = [];

        foreach ($losers as $loser) {
            $candidates = $this->guardianFileService->fileCandidates((string) $loser->file_id_number);
            $counts = $this->countReferences($candidates);
            unset($counts['attachments']);

            $remaining = $this->sumCounts($remaining, $counts);
        }

        return $remaining;
    }

    protected function mergeDataColumnValues(int $keepId, array $loserIds): array
    {
        if (empty($loserIds)) {
            return [];
        }

        $skip = ['id', 'file_id_number', 'created_at', 'updated_at'];
        $columns = array_diff(Schema::getColumnListing('data'), $skip);

        $keep = DB::table('data')->where('id', $keepId)->first();
        $losers = DB::table('data')->whereIn('id', $loserIds)->get();

        if ($keep === null || $losers->isEmpty()) {
            return [];
        }

        $update = [];

        foreach ($columns as $column) {
            if ($this->isEmptyValue($keep->{$column})) {
                foreach ($losers as $loser) {
                    if (!$this->isEmptyValue($loser->{$column})) {
                        $update[$column] = $loser->{$column};
                        break;
                    }
                }
            }
        }

        if (!empty($update)) {
            $update = $this->withTimestamp('data', $update);
            DB::table('data')->where('id', $keepId)->update($update);
        }

        return array_keys($update);
    }

    protected function moveAttachmentFolders(string $fromFile, string $toFile): array
    {
        $result = ['files_moved' => 0, 'rows_updated' => 0, 'warnings' => []];
        $fromCandidates = $this->guardianFileService->fileCandidates($fromFile);
        $normalizedTo = $this->guardianFileService->normalizeFileNumber($toFile);

        $baseDir = storage_path('app/public/uploads');
        $destinationDir = $baseDir . DIRECTORY_SEPARATOR . $normalizedTo;
        $skippedNames = [];

        foreach ($fromCandidates as $candidate) {
            $sourceDir = $baseDir . DIRECTORY_SEPARATOR . $candidate;

            if (!is_dir($sourceDir)) {
                continue;
            }

            if (!is_dir($destinationDir)) {
                @mkdir($destinationDir, 0755, true);
            }

            $entries = array_diff(scandir($sourceDir) ?: [], ['.', '..']);

            foreach ($entries as $entry) {
                $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $entry;

                if (!is_file($sourcePath)) {
                    continue;
                }

                $destinationPath = $destinationDir . DIRECTORY_SEPARATOR . $entry;

                if (file_exists($destinationPath)) {
                    $skippedNames[$entry] = true;
                    $result['warnings'][] = 'تعذر نقل الملف ' . $entry . ' لوجود ملف بالاسم نفسه في الملف المحتفظ به';
                    continue;
                }

                if (@rename($sourcePath, $destinationPath)) {
                    $result['files_moved']++;
                } else {
                    $skippedNames[$entry] = true;
                    $result['warnings'][] = 'فشل نقل الملف ' . $entry;
                }
            }

            $leftovers = array_diff(scandir($sourceDir) ?: [], ['.', '..']);

            if (count($leftovers) === 0) {
                @rmdir($sourceDir);
            }
        }

        foreach ($this->pathTables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($fromCandidates as $candidate) {
                $rows = DB::table($table)
                    ->where('file_path', 'like', '%/uploads/' . $candidate . '/%')
                    ->get(['id', 'file_path']);

                foreach ($rows as $row) {
                    $newPath = str_replace(
                        '/uploads/' . $candidate . '/',
                        '/uploads/' . $normalizedTo . '/',
                        (string) $row->file_path
                    );

                    if (isset($skippedNames[basename($newPath)])) {
                        $result['warnings'][] = 'لم يتم تحديث مسار ' . $table . ' #' . $row->id . ' بسبب تعارض اسم الملف';
                        continue;
                    }

                    if (!is_file($this->absoluteStoragePath($newPath))) {
                        $result['warnings'][] = 'لم يتم تحديث مسار ' . $table . ' #' . $row->id . ' لعدم وجود الملف في الهدف';
                        continue;
                    }

                    $result['rows_updated'] += DB::table($table)
                        ->where('id', $row->id)
                        ->update($this->withTimestamp($table, ['file_path' => $newPath]));
                }
            }
        }

        return $result;
    }

    protected function countAttachmentRows(array $candidates): int
    {
        if (!Schema::hasTable('attachments') || empty($candidates)) {
            return 0;
        }

        return DB::table('attachments')
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $query->orWhere('file_path', 'like', '%/uploads/' . $candidate . '/%');
                }
            })
            ->count();
    }

    protected function numericCandidates(array $candidates): array
    {
        $numeric = [];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $value = (int) $candidate;

                if (!in_array($value, $numeric, true)) {
                    $numeric[] = $value;
                }
            }
        }

        return $numeric;
    }

    protected function withTimestamp(string $table, array $payload): array
    {
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    protected function sumCounts(array $base, array $extra): array
    {
        foreach ($extra as $key => $value) {
            if (is_numeric($value)) {
                $base[$key] = ($base[$key] ?? 0) + $value;
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    protected function isEmptyValue($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' || $value === '0';
        }

        if (is_numeric($value)) {
            return (float) $value === 0.0;
        }

        return false;
    }

    protected function absoluteStoragePath(string $path): string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        return storage_path('app/public/' . $path);
    }
}
