<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Services\FaceEmbeddingService;
use App\Services\FaceMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ScanAttachmentFaces extends Command
{
    protected $signature = 'attachments:face-scan
        {--dry-run : إجراء الفحص والتقرير فقط دون حذف}
        {--person= : فحص رقم هوية واحد فقط}
        {--force : إعادة استخراج البصمات المخزنة}';

    protected $description = 'مقارنة صور المرفقات للشخص والنوع نفسيهما وإصدار تقرير تجريبي دون حذف';

    public function handle(FaceEmbeddingService $embeddings, FaceMatchingService $matching): int
    {
        if (!$this->option('dry-run')) {
            $this->error('هذه الدفعة تدعم الوضع التجريبي فقط. أضف --dry-run؛ لا يوجد أي مسار حذف.');

            return self::INVALID;
        }

        foreach (['attachments', 'document_types', 'attachment_face_embeddings'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("الجدول المطلوب غير موجود: {$table}. شغّل الهجرة الجديدة أولاً.");

                return self::FAILURE;
            }
        }

        $person = trim((string) $this->option('person'));
        if (mb_strlen($person) > 64) {
            $this->error('رقم الهوية المدخل أطول من الحد المسموح.');

            return self::INVALID;
        }

        $stats = array_fill_keys([
            'input_images',
            'usable_embeddings',
            'skipped_embeddings',
            'comparison_groups',
            'single_image_groups',
            'auto_pairs',
            'review_pairs',
            'different_pairs',
            'auto_duplicate_groups',
            'deletion_candidates',
        ], 0);
        $processing = array_fill_keys(['ready_new', 'ready_cached', 'skipped_new', 'skipped_cached', 'failed'], 0);
        $duplicateGroups = [];
        $sampleSize = max(1, (int) config('face_matching.calibration_sample_size', 100));
        $perClass = max(1, intdiv($sampleSize, 3));
        $calibration = ['auto' => [], 'review' => [], 'different' => []];
        $seen = ['auto' => 0, 'review' => 0, 'different' => 0];

        $groups = DB::table('attachments')
            ->join('document_types', 'attachments.file_type', '=', 'document_types.id')
            ->where('document_types.file_type', 'image')
            ->when($person !== '', fn ($query) => $query->where('attachments.person_identity_number', $person))
            ->select('attachments.person_identity_number', 'attachments.file_type')
            ->groupBy('attachments.person_identity_number', 'attachments.file_type')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('attachments.person_identity_number')
            ->orderBy('attachments.file_type')
            ->cursor();

        foreach ($groups as $group) {
            $attachments = Attachment::query()
                ->join('document_types', 'attachments.file_type', '=', 'document_types.id')
                ->where('document_types.file_type', 'image')
                ->where('attachments.person_identity_number', $group->person_identity_number)
                ->where('attachments.file_type', $group->file_type)
                ->select('attachments.*')
                ->orderBy('attachments.id')
                ->get();

            $records = [];
            foreach ($attachments as $attachment) {
                $result = $embeddings->process($attachment, (bool) $this->option('force'));
                $status = $result['status'];
                $cached = (bool) ($result['cached'] ?? false);

                if ($status === 'ready') {
                    $processing[$cached ? 'ready_cached' : 'ready_new']++;
                } elseif ($status === 'skipped') {
                    $processing[$cached ? 'skipped_cached' : 'skipped_new']++;
                } else {
                    $processing['failed']++;
                }

                $records[] = [
                    'id' => $attachment->id,
                    'person_identity_number' => $attachment->person_identity_number,
                    'file_type' => $attachment->file_type,
                    'created_at' => $attachment->created_at?->toDateTimeString(),
                    'embedding' => $result['embedding'] ?? null,
                ];
            }

            $analysis = $matching->analyze($records);
            foreach ($stats as $key => $_) {
                $stats[$key] += $analysis['stats'][$key];
            }

            $maskedPerson = $this->maskIdentity($group->person_identity_number);
            foreach ($analysis['groups'] as $duplicate) {
                $duplicate['person_identity_number'] = $maskedPerson;
                $duplicateGroups[] = $duplicate;
            }

            foreach ($analysis['pairs'] as $pair) {
                $class = $pair['classification'];
                $pair['person_identity_number'] = $maskedPerson;
                $seen[$class]++;
                $bucket = &$calibration[$class];

                if (count($bucket) < $perClass) {
                    $bucket[] = $pair;
                } else {
                    $replacement = random_int(1, $seen[$class]);
                    if ($replacement <= $perClass) {
                        $bucket[$replacement - 1] = $pair;
                    }
                }
                unset($bucket);
            }
        }

        $calibrationPairs = array_merge(...array_values($calibration));
        usort($calibrationPairs, fn (array $a, array $b): int => [$a['classification'], $a['distance']]
            <=> [$b['classification'], $b['distance']]);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => 'dry-run',
            'deletion_performed' => false,
            'model_version' => config('face_matching.model_version'),
            'thresholds' => [
                'auto' => config('face_matching.auto_threshold'),
                'review' => config('face_matching.review_threshold'),
            ],
            'similarity_note' => 'display_similarity قيمة مشتقة من المسافة للعرض فقط وليست احتمالاً للتطابق.',
            'stats' => array_merge($stats, $processing),
            'calibration_counts' => array_map('count', $calibration),
            'calibration_pairs' => $calibrationPairs,
            'duplicate_groups' => $duplicateGroups,
        ];

        $filename = 'face-matching/calibration-' . now()->format('Ymd-His') . '.json';
        $written = Storage::disk('local')->put(
            $filename,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        if (!$written) {
            $this->error('تعذر حفظ تقرير المعايرة.');

            return self::FAILURE;
        }

        $this->info('اكتمل الفحص التجريبي. لم يُحذف أو يُعدَّل أي مرفق.');
        $this->table(['البند', 'القيمة'], [
            ['صور داخل مجموعات قابلة للمقارنة', $stats['input_images']],
            ['بصمات جديدة', $processing['ready_new']],
            ['بصمات مستخدمة من التخزين', $processing['ready_cached']],
            ['صور متخطاة', $stats['skipped_embeddings']],
            ['أخطاء تشغيل قابلة لإعادة المحاولة', $processing['failed']],
            ['أزواج تطابق تلقائي', $stats['auto_pairs']],
            ['أزواج تحتاج مراجعة', $stats['review_pairs']],
            ['مجموعات تكرار مؤكدة آلياً', $stats['auto_duplicate_groups']],
            ['مرشحون للحذف مستقبلاً فقط', $stats['deletion_candidates']],
            ['أزواج تقرير المعايرة', count($calibrationPairs)],
        ]);
        $this->line('التقرير: storage/app/' . $filename);

        return self::SUCCESS;
    }

    private function maskIdentity(?string $identity): string
    {
        return '***' . substr((string) $identity, -3);
    }
}
