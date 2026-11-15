<?php

namespace App\Services;

use App\Jobs\ProcessAttachmentFaceScanJob;
use App\Models\Attachment;
use App\Models\AttachmentFaceEmbedding;
use App\Models\AttachmentFaceResult;
use App\Models\AttachmentFaceScan;
use App\Models\AttachmentFaceScanEvent;
use App\Models\AttachmentFaceScanGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FaceScanService
{
    public function __construct(
        private FaceEmbeddingService $embeddings,
        private AttachmentTrashService $trash,
    ) {
    }

    public function start(?string $person, int $userId): AttachmentFaceScan
    {
        $lock = Cache::lock('attachment-face-scan:start', 15);
        $lock->block(5);

        try {
            $active = AttachmentFaceScan::whereIn('status', AttachmentFaceScan::ACTIVE_STATUSES)->first();
            if ($active) {
                throw new \DomainException('يوجد فحص وجوه نشط بالفعل. انتظر انتهاءه أو أوقفه أولاً.');
            }

            $scan = AttachmentFaceScan::create([
                'requested_by' => $userId,
                'status' => 'queued',
                'person_identity_number' => $person !== '' ? $person : null,
                'model_version' => config('face_matching.model_version'),
                'auto_threshold' => config('face_matching.auto_threshold'),
                'review_threshold' => config('face_matching.review_threshold'),
                'expires_at' => now()->addDays(max(1, (int) config('face_matching.result_ttl_days', 7))),
            ]);
            $this->event($scan, 'started', $userId);
        } finally {
            $lock->release();
        }

        $this->dispatch($scan);

        return $scan;
    }

    public function stop(AttachmentFaceScan $scan, int $userId): AttachmentFaceScan
    {
        if (in_array($scan->status, ['queued', 'running', 'stopping'], true)) {
            $scan->update(['status' => 'paused']);
        } else {
            throw new \DomainException('لا يمكن إيقاف هذا الفحص في حالته الحالية.');
        }

        $this->event($scan, 'stop_requested', $userId);

        return $scan->fresh();
    }

    public function resume(AttachmentFaceScan $scan, int $userId): AttachmentFaceScan
    {
        if (!in_array($scan->status, ['paused', 'failed'], true)) {
            throw new \DomainException('يمكن استئناف الفحص المتوقف أو المتعذر فقط.');
        }

        $scan->groups()->where('status', 'failed')->update(['status' => 'pending']);
        $scan->update([
            'status' => 'queued',
            'last_error' => null,
            'finished_at' => null,
        ]);
        $this->event($scan, 'resumed', $userId);
        $this->dispatch($scan);

        return $scan->fresh();
    }

    public function run(int $scanId): void
    {
        $lock = Cache::lock('attachment-face-scan:run:' . $scanId, 3600);
        if (!$lock->get()) {
            return;
        }

        try {
            $this->runLocked($scanId);
        } finally {
            $lock->release();
        }
    }

    public function fail(int $scanId, ?\Throwable $exception = null): void
    {
        $scan = AttachmentFaceScan::find($scanId);
        if (!$scan || in_array($scan->status, ['completed', 'paused'], true)) {
            return;
        }

        $scan->groups()->where('status', 'failed')->update(['status' => 'pending']);
        $scan->update([
            'status' => 'failed',
            'last_error' => 'توقف الفحص بسبب خطأ تقني. يمكن استئنافه.',
            'finished_at' => now(),
        ]);
        $this->event($scan, 'failed', null, ['exception' => $exception ? $exception::class : null]);

        Log::error('face-scan: تعذر إكمال الفحص', [
            'scan_id' => $scanId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }

    private function runLocked(int $scanId): void
    {
        $scan = AttachmentFaceScan::find($scanId);
        if (!$scan) {
            return;
        }

        if ($scan->status === 'stopping') {
            $this->pause($scan);
            return;
        }

        if (!in_array($scan->status, ['queued', 'running'], true)) {
            return;
        }

        if ($scan->status === 'queued') {
            $scan->update(['status' => 'running', 'started_at' => $scan->started_at ?? now()]);
            $this->event($scan, 'processing_started');
        }

        $this->prepare($scan);
        $scan->refresh();

        if ($scan->status === 'stopping') {
            $this->pause($scan);
            return;
        }

        $group = $scan->groups()->where('status', 'pending')->orderBy('id')->first();
        if (!$group) {
            $this->complete($scan);
            return;
        }

        $this->processGroup($scan, $group);
        $scan->refresh();

        if ($scan->status === 'stopping') {
            $this->pause($scan);
            return;
        }

        if ($scan->status === 'running') {
            $this->dispatch($scan);
        }
    }

    private function prepare(AttachmentFaceScan $scan): void
    {
        if ($scan->prepared_at !== null) {
            return;
        }

        $query = DB::table('attachments')
            ->join('document_types', 'attachments.file_type', '=', 'document_types.id')
            ->where('document_types.file_type', 'image')
            ->when(
                $scan->person_identity_number,
                fn ($query) => $query->where('attachments.person_identity_number', $scan->person_identity_number),
            )
            ->select(
                'attachments.person_identity_number',
                'attachments.file_type',
                DB::raw('COUNT(*) as image_count'),
            )
            ->groupBy('attachments.person_identity_number', 'attachments.file_type')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('attachments.person_identity_number')
            ->orderBy('attachments.file_type');

        $rows = [];
        $totalImages = 0;
        $totalGroups = 0;

        foreach ($query->cursor() as $record) {
            $rows[] = [
                'scan_id' => $scan->id,
                'group_hash' => sha1($record->person_identity_number . "\0" . $record->file_type),
                'person_identity_number' => $record->person_identity_number,
                'file_type' => $record->file_type,
                'status' => 'pending',
                'image_count' => (int) $record->image_count,
                'processed_images' => 0,
                'last_attachment_id' => 0,
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $totalImages += (int) $record->image_count;
            $totalGroups++;

            if (count($rows) === 500) {
                AttachmentFaceScanGroup::insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows) {
            AttachmentFaceScanGroup::insertOrIgnore($rows);
        }

        $scan->update([
            'total_groups' => $totalGroups,
            'total_images' => $totalImages,
            'prepared_at' => now(),
        ]);
    }

    private function processGroup(AttachmentFaceScan $scan, AttachmentFaceScanGroup $group): void
    {
        $group->increment('attempts');
        $limit = max(1, min(20, (int) config('face_matching.images_per_run', 5)));
        $attachments = $this->groupAttachments($group)
            ->where('attachments.id', '>', $group->last_attachment_id)
            ->orderBy('attachments.id')
            ->limit($limit)
            ->get();

        foreach ($attachments as $attachment) {
            if (in_array($scan->fresh()->status, ['stopping', 'paused'], true)) {
                return;
            }

            $this->embeddings->process($attachment);

            DB::transaction(function () use ($scan, $group, $attachment): void {
                $group->update([
                    'last_attachment_id' => $attachment->id,
                    'processed_images' => $group->processed_images + 1,
                ]);
                $scan->increment('processed_images');
                $group->refresh();
            });
        }

        $hasRemaining = $this->groupAttachments($group)
            ->where('attachments.id', '>', $group->last_attachment_id)
            ->exists();

        if ($hasRemaining || $scan->fresh()->status === 'stopping') {
            return;
        }

        $this->finalizeGroup($scan, $group);
    }

    private function finalizeGroup(AttachmentFaceScan $scan, AttachmentFaceScanGroup $group): void
    {
        $attachments = $this->groupAttachments($group)->orderBy('attachments.id')->get();
        $embeddingRows = AttachmentFaceEmbedding::whereIn('attachment_id', $attachments->pluck('id'))
            ->where('model_version', $scan->model_version)
            ->where('status', 'ready')
            ->get()
            ->keyBy('attachment_id');
        $records = [];

        foreach ($attachments as $attachment) {
            $records[] = [
                'id' => $attachment->id,
                'person_identity_number' => $attachment->person_identity_number,
                'file_type' => $attachment->file_type,
                'created_at' => $attachment->created_at?->toDateTimeString(),
                'embedding' => $embeddingRows->get($attachment->id)?->embedding,
            ];
        }

        $matching = new FaceMatchingService((float) $scan->auto_threshold, (float) $scan->review_threshold);
        $analysis = $matching->analyze($records);
        $distances = [];
        foreach ($analysis['pairs'] as $pair) {
            $distances[$this->pairKey($pair['a_id'], $pair['b_id'])] = $pair['distance'];
        }

        $deletionCandidates = [];

        DB::transaction(function () use ($scan, $group, $analysis, $distances, &$deletionCandidates): void {
            foreach ($analysis['groups'] as $duplicate) {
                foreach ($duplicate['delete_ids'] as $candidateId) {
                    $result = $this->storeResult(
                        $scan,
                        'auto_duplicate',
                        $duplicate['person_identity_number'],
                        $duplicate['file_type'],
                        $candidateId,
                        $duplicate['keep_id'],
                        $distances[$this->pairKey($candidateId, $duplicate['keep_id'])],
                    );
                    $deletionCandidates[] = [
                        'attachment_id' => $candidateId,
                        'keep_id' => $duplicate['keep_id'],
                        'result_id' => $result->id,
                    ];
                }
            }

            foreach ($analysis['pairs'] as $pair) {
                if ($pair['classification'] !== 'review') {
                    continue;
                }

                $this->storeResult(
                    $scan,
                    'review',
                    $pair['person_identity_number'],
                    $pair['file_type'],
                    $pair['a_id'],
                    $pair['b_id'],
                    $pair['distance'],
                );
            }

            $group->update(['status' => 'completed']);
            $scan->increment('processed_groups');
        });

        $trashed = 0;
        if ($this->trash->deletionEnabled()) {
            foreach ($deletionCandidates as $candidate) {
                $attachment = Attachment::find($candidate['attachment_id']);
                if (!$attachment) {
                    continue;
                }

                $outcome = $this->trash->trash(
                    $attachment,
                    (int) $scan->requested_by,
                    'face-auto-duplicate',
                    $scan->id,
                    $candidate['result_id'],
                );
                if (in_array($outcome['status'], ['trashed', 'already-trashed'], true)) {
                    $trashed++;
                }
            }
        }

        if ($trashed > 0 && Schema::hasColumn('attachment_face_scans', 'trashed_count')) {
            $scan->increment('trashed_count', $trashed);
        }

        $scan->update([
            'duplicate_count' => $scan->results()->where('decision', 'auto_duplicate')->count(),
            'review_count' => $scan->results()->where('decision', 'review')->count(),
        ]);
    }

    private function storeResult(
        AttachmentFaceScan $scan,
        string $decision,
        string $person,
        string $fileType,
        int $attachmentId,
        int $matchedAttachmentId,
        float $distance,
    ): AttachmentFaceResult {
        return AttachmentFaceResult::updateOrCreate([
            'scan_id' => $scan->id,
            'decision' => $decision,
            'attachment_id' => $attachmentId,
            'matched_attachment_id' => $matchedAttachmentId,
        ], [
            'person_identity_number' => $person,
            'file_type' => $fileType,
            'distance' => $distance,
        ]);
    }

    private function groupAttachments(AttachmentFaceScanGroup $group)
    {
        return Attachment::query()
            ->join('document_types', 'attachments.file_type', '=', 'document_types.id')
            ->where('document_types.file_type', 'image')
            ->where('attachments.person_identity_number', $group->person_identity_number)
            ->where('attachments.file_type', $group->file_type)
            ->select('attachments.*');
    }

    private function complete(AttachmentFaceScan $scan): void
    {
        $scan->update(['status' => 'completed', 'finished_at' => now(), 'last_error' => null]);
        $this->event($scan, 'completed');
    }

    private function pause(AttachmentFaceScan $scan): void
    {
        $scan->update(['status' => 'paused']);
        $this->event($scan, 'paused');
    }

    private function dispatch(AttachmentFaceScan $scan): void
    {
        ProcessAttachmentFaceScanJob::dispatch($scan->id)->onQueue(config('face_matching.queue', 'faces'));
    }

    private function event(
        AttachmentFaceScan $scan,
        string $event,
        ?int $userId = null,
        array $metadata = [],
    ): void {
        AttachmentFaceScanEvent::create([
            'scan_id' => $scan->id,
            'user_id' => $userId,
            'event' => $event,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    private function pairKey(int $a, int $b): string
    {
        return min($a, $b) . ':' . max($a, $b);
    }
}
