<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AttachmentFaceResult;
use App\Models\AttachmentFaceScan;
use App\Models\AttachmentTrash;
use App\Services\AttachmentTrashService;
use App\Services\FaceEmbeddingService;
use App\Services\FaceScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentFaceScanController extends Controller
{
    public function store(Request $request, FaceScanService $scans): JsonResponse
    {
        if (!Schema::hasTable('attachment_face_scans')) {
            return response()->json(['success' => false, 'message' => 'هجرة فحص الوجوه غير مشغلة بعد.'], 503);
        }

        $data = $request->validate(['person' => ['nullable', 'string', 'max:64']]);
        $person = trim((string) ($data['person'] ?? ''));

        try {
            $scan = $scans->start($person, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'بدأ الفحص التجريبي. لن يُحذف أي مرفق.',
            'data' => $this->scanData($scan),
        ], 202);
    }

    public function latest(): JsonResponse
    {
        if (!Schema::hasTable('attachment_face_scans')) {
            return response()->json(['success' => true, 'ready' => false, 'data' => null]);
        }

        $scan = AttachmentFaceScan::latest('id')->first();

        return response()->json(['success' => true, 'ready' => true, 'data' => $scan ? $this->scanData($scan) : null]);
    }

    public function show(AttachmentFaceScan $scan): JsonResponse
    {
        $this->ensureAvailable($scan);

        return response()->json(['success' => true, 'data' => $this->scanData($scan)]);
    }

    public function stop(AttachmentFaceScan $scan, Request $request, FaceScanService $scans): JsonResponse
    {
        $this->ensureAvailable($scan);

        try {
            $scan = $scans->stop($scan, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $this->scanData($scan)]);
    }

    public function resume(AttachmentFaceScan $scan, Request $request, FaceScanService $scans): JsonResponse
    {
        $this->ensureAvailable($scan);

        try {
            $scan = $scans->resume($scan, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $this->scanData($scan)], 202);
    }

    public function results(AttachmentFaceScan $scan, Request $request): JsonResponse
    {
        $this->ensureAvailable($scan);
        $data = $request->validate([
            'decision' => ['nullable', 'in:auto_duplicate,review'],
        ]);
        $results = AttachmentFaceResult::where('scan_id', $scan->id)
            ->when($data['decision'] ?? null, fn ($query, $decision) => $query->where('decision', $decision))
            ->orderBy('id')
            ->paginate(20);
        $ids = $results->getCollection()
            ->flatMap(fn (AttachmentFaceResult $result) => [$result->attachment_id, $result->matched_attachment_id])
            ->unique()
            ->values();
        $attachments = Attachment::query()
            ->join('document_types', 'attachments.file_type', '=', 'document_types.id')
            ->whereIn('attachments.id', $ids)
            ->select('attachments.*', 'document_types.description as document_type_description')
            ->get()
            ->keyBy('id');
        $trashMap = Schema::hasTable('attachment_trash')
            ? AttachmentTrash::whereIn('attachment_id', $ids)
                ->where('status', 'trashed')
                ->get()
                ->keyBy('attachment_id')
            : collect();
        $items = $results->getCollection()->map(function (AttachmentFaceResult $result) use ($attachments, $scan, $trashMap): array {
            return [
                'id' => $result->id,
                'decision' => $result->decision,
                'person' => $this->maskIdentity($result->person_identity_number),
                'distance' => $result->distance,
                'display_similarity' => round(max(0, 1 - $result->distance), 4),
                'attachment' => $this->attachmentData(
                    $attachments->get($result->attachment_id),
                    $result->attachment_id,
                    $scan,
                    $trashMap->get($result->attachment_id),
                ),
                'matched_attachment' => $this->attachmentData(
                    $attachments->get($result->matched_attachment_id),
                    $result->matched_attachment_id,
                    $scan,
                    $trashMap->get($result->matched_attachment_id),
                ),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function image(
        AttachmentFaceScan $scan,
        Attachment $attachment,
        FaceEmbeddingService $embeddings,
    ): BinaryFileResponse {
        $this->ensureAvailable($scan);
        $belongsToResult = AttachmentFaceResult::where('scan_id', $scan->id)
            ->where(function ($query) use ($attachment): void {
                $query->where('attachment_id', $attachment->id)
                    ->orWhere('matched_attachment_id', $attachment->id);
            })
            ->exists();
        abort_unless($belongsToResult, 404);

        $path = $embeddings->localPath($attachment);
        abort_if($path === null || !is_readable($path), 404);
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mime, 'image/'), 404);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function trashIndex()
    {
        $items = Schema::hasTable('attachment_trash')
            ? AttachmentTrash::where('status', 'trashed')->orderByDesc('id')->paginate(20)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

        $service = app(AttachmentTrashService::class);
        $rows = $items->getCollection()->map(function (AttachmentTrash $trash) use ($service): array {
            $snapshot = is_array($trash->snapshot) ? $trash->snapshot : [];
            $file = $trash->trashed_path ? $service->resolveTrashedPath($trash->trashed_path) : null;

            return [
                'id' => $trash->id,
                'attachment_id' => $trash->attachment_id,
                'person' => $this->maskIdentity((string) ($snapshot['person_identity_number'] ?? '')),
                'original_name' => basename((string) $trash->original_path),
                'file_size' => $file !== null && is_file($file) ? (int) filesize($file) : null,
                'trashed_at' => $trash->trashed_at?->toDateTimeString(),
                'restore_deadline_at' => $trash->restore_deadline_at?->toDateTimeString(),
                'expired' => $trash->isExpired(),
                'reason' => $trash->reason,
                'preview_url' => route('attachment-audit.face-trash.image', $trash),
                'restore_url' => route('attachment-audit.face-trash.restore', $trash),
            ];
        })->values();

        return view('admin.dashboard.attachment-audit.face-trash', [
            'items' => $items,
            'rows' => $rows,
        ]);
    }

    public function trashImage(AttachmentTrash $trash, AttachmentTrashService $service): BinaryFileResponse
    {
        $path = $trash->trashed_path ? $service->resolveTrashedPath($trash->trashed_path) : null;
        abort_if($path === null || !is_readable($path), 404);
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mime, 'image/'), 404);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function emptyTrash(Request $request, AttachmentTrashService $service): JsonResponse
    {
        $outcome = $service->emptyTrash();

        return response()->json([
            'success' => true,
            'message' => 'أُفرغت السلة: ' . $outcome['purged'] . ' عنصر (ملفات محذوفة نهائياً: ' . $outcome['files_removed'] . ').',
            'data' => $outcome,
        ]);
    }

    public function restoreTrash(
        AttachmentTrash $trash,
        Request $request,
        AttachmentTrashService $service,
    ): JsonResponse {
        $outcome = $service->restore($trash, (int) $request->user()->id);

        if ($outcome['status'] === 'restored') {
            return response()->json([
                'success' => true,
                'message' => 'استُعيد المرفق من السلة.',
                'data' => ['attachment_id' => $outcome['attachment_id']],
            ]);
        }

        $messages = [
            'expired' => 'انتهت مدة السلة ولا يمكن الاسترجاع.',
            'not-trashed' => 'هذا العنصر ليس في السلة.',
            'move-failed' => 'تعذر إعادة الملف إلى مكانه الأصلي.',
            'db-failed' => 'تعذر استرجاع السجل.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$outcome['status']] ?? 'تعذر الاسترجاع.',
        ], 422);
    }

    private function scanData(AttachmentFaceScan $scan): array
    {
        $expired = $scan->isExpired();
        $progress = $scan->status === 'completed'
            ? 100
            : ($scan->total_images > 0
                ? (int) floor(($scan->processed_images / $scan->total_images) * 100)
                : 0);

        return [
            'id' => $scan->id,
            'status' => $scan->status,
            'person' => $scan->person_identity_number ? $this->maskIdentity($scan->person_identity_number) : null,
            'total_groups' => $scan->total_groups,
            'processed_groups' => $scan->processed_groups,
            'total_images' => $scan->total_images,
            'processed_images' => $scan->processed_images,
            'duplicate_count' => $scan->duplicate_count,
            'review_count' => $scan->review_count,
            'trashed_count' => Schema::hasColumn('attachment_face_scans', 'trashed_count') ? (int) $scan->trashed_count : 0,
            'progress' => min(100, $progress),
            'last_error' => $scan->last_error,
            'started_at' => $scan->started_at?->toDateTimeString(),
            'finished_at' => $scan->finished_at?->toDateTimeString(),
            'expires_at' => $scan->expires_at?->toDateTimeString(),
            'expired' => $expired,
            'can_stop' => in_array($scan->status, AttachmentFaceScan::ACTIVE_STATUSES, true),
            'can_resume' => !$expired && in_array($scan->status, ['paused', 'failed'], true),
            'results_url' => route('attachment-audit.face-scans.results', $scan),
        ];
    }

    private function attachmentData(
        ?Attachment $attachment,
        int $id,
        AttachmentFaceScan $scan,
        ?AttachmentTrash $trash = null,
    ): array {
        if (!$attachment && $trash) {
            return [
                'id' => $id,
                'missing' => true,
                'trashed' => true,
                'trashed_at' => $trash->trashed_at?->toDateTimeString(),
                'restore_deadline_at' => $trash->restore_deadline_at?->toDateTimeString(),
                'restore_url' => route('attachment-audit.face-trash.restore', $trash),
            ];
        }

        if (!$attachment) {
            return ['id' => $id, 'missing' => true];
        }

        return [
            'id' => $id,
            'missing' => false,
            'document_type' => $attachment->document_type_description,
            'file_size' => $attachment->file_size,
            'created_at' => $attachment->created_at?->toDateTimeString(),
            'image_url' => route('attachment-audit.face-scans.image', [$scan, $attachment]),
        ];
    }

    private function ensureAvailable(AttachmentFaceScan $scan): void
    {
        abort_if($scan->isExpired(), 410, 'انتهت صلاحية نتائج هذا الفحص. ابدأ فحصاً جديداً.');
    }

    private function maskIdentity(?string $identity): string
    {
        return '***' . substr((string) $identity, -3);
    }
}
