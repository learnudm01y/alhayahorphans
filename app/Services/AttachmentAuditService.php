<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class AttachmentAuditService
{
    /**
     * كشف المكررات حسب رقم الهوية + نوع الوثيقة
     */
    public function findDuplicates(): array
    {
        $duplicates = DB::table('attachments')
            ->select('person_identity_number', 'file_type', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'), DB::raw('MAX(id) as max_id'))
            ->whereNotNull('person_identity_number')
            ->where('person_identity_number', '!=', '')
            ->whereNotNull('file_type')
            ->where('file_type', '!=', '')
            ->groupBy('person_identity_number', 'file_type')
            ->having('count', '>', 1)
            ->orderByDesc('count')
            ->get();

        $results = [];

        foreach ($duplicates as $dup) {
            $records = DB::table('attachments')
                ->where('person_identity_number', $dup->person_identity_number)
                ->where('file_type', $dup->file_type)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'stored_file_name' => $record->stored_file_name,
                        'file_path' => $record->file_path,
                        'file_size' => $record->file_size,
                        'file_type' => $record->file_type,
                        'created_at' => $record->created_at,
                    ];
                });

            $results[] = [
                'person_identity_number' => $dup->person_identity_number,
                'file_type' => $dup->file_type,
                'count' => (int) $dup->count,
                'keep_id' => (int) $dup->keep_id,
                'records' => $records->toArray(),
            ];
        }

        $totalDuplicates = collect($results)->sum('count');
        $totalToDelete = $totalDuplicates - count($results);

        return [
            'total_groups' => count($results),
            'total_duplicates' => $totalDuplicates,
            'total_to_delete' => $totalToDelete,
            'groups' => $results,
        ];
    }

    /**
     * حذف المكررات مع الاحتفاظ بالأقدم (أقل ID)
     */
    public function deleteDuplicatesKeepOldest(): array
    {
        $duplicates = DB::table('attachments')
            ->select('person_identity_number', 'file_type', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('person_identity_number')
            ->where('person_identity_number', '!=', '')
            ->whereNotNull('file_type')
            ->where('file_type', '!=', '')
            ->groupBy('person_identity_number', 'file_type')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        $deletedCount = 0;
        $errors = [];

        foreach ($duplicates as $dup) {
            $idsToDelete = DB::table('attachments')
                ->where('person_identity_number', $dup->person_identity_number)
                ->where('file_type', $dup->file_type)
                ->where('id', '!=', $dup->keep_id)
                ->pluck('id');

            foreach ($idsToDelete as $id) {
                try {
                    DB::table('attachments')->where('id', $id)->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = ['id' => $id, 'error' => $e->getMessage()];
                }
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ];
    }

    /**
     * حذف مكرر واحد
     */
    public function deleteSingleDuplicate(int $id): bool
    {
        return DB::table('attachments')->where('id', $id)->delete() > 0;
    }

    /**
     * فحص صحة جميع الروابط
     */
    public function checkBrokenLinks(): array
    {
        $allAttachments = DB::table('attachments')
            ->select('id', 'person_identity_number', 'stored_file_name', 'file_path', 'file_type', 'file_size')
            ->orderBy('person_identity_number', 'asc')
            ->get();

        $total = $allAttachments->count();
        $valid = 0;
        $broken = [];
        $skipped = 0;

        foreach ($allAttachments as $attachment) {
            $filePath = $attachment->file_path;

            if (empty($filePath)) {
                $skipped++;
                continue;
            }

            $exists = false;

            if (str_starts_with($filePath, 'storage/')) {
                $publicPath = str_replace('storage/', '', $filePath);
                $exists = Storage::disk('public')->exists($publicPath);
            } elseif (preg_match('/^[a-z0-9_-]+:/', $filePath)) {
                $exists = false;
            } elseif (str_starts_with($filePath, '/')) {
                $exists = file_exists($filePath);
            } elseif (preg_match('/^(uploads|documents|images|files)\//', $filePath)) {
                $exists = Storage::disk('public')->exists($filePath);
            } else {
                $exists = Storage::disk('public')->exists($filePath);
            }

            if ($exists) {
                $valid++;
            } else {
                $broken[] = [
                    'id' => $attachment->id,
                    'person_identity_number' => $attachment->person_identity_number,
                    'stored_file_name' => $attachment->stored_file_name,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                ];
            }
        }

        return [
            'total' => $total,
            'valid' => $valid,
            'broken_count' => count($broken),
            'skipped' => $skipped,
            'broken' => $broken,
        ];
    }

    /**
     * حذف رابط مكسور واحد
     */
    public function deleteBrokenLink(int $id): bool
    {
        return DB::table('attachments')->where('id', $id)->delete() > 0;
    }

    /**
     * حذف كل الروابط المكسورة
     */
    public function deleteAllBrokenLinks(array $brokenIds): array
    {
        $deletedCount = 0;
        $errors = [];

        foreach ($brokenIds as $id) {
            try {
                DB::table('attachments')->where('id', $id)->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ];
    }

    /**
     * فحص جميع الأشخاص من جميع الجداول ومعرفة من لديه مرفقات ومن يوجد في الكفالات
     */
    public function findAllPersonsWithAttachmentStatus(): array
    {
        // 1. جمع كل الهويات من الجداول الأربعة
        $allPersons = [];

        // المعيلين (data table)
        $breadwinners = DB::table('data')
            ->select('data_id_number as person_id', DB::raw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) as full_name"))
            ->whereNotNull('data_id_number')
            ->where('data_id_number', '!=', '')
            ->get();
        foreach ($breadwinners as $b) {
            $allPersons[] = [
                'person_id' => (string) $b->person_id,
                'full_name' => trim($b->full_name),
                'person_type' => 'breadwinner',
                'person_type_ar' => 'معيل',
            ];
        }

        // الأفراد (re_people table)
        $familyMembers = DB::table('re_people')
            ->select('person_id', DB::raw("CONCAT(first_name, ' ', second_name, ' ', third_name, ' ', last_name) as full_name"))
            ->whereNotNull('person_id')
            ->where('person_id', '!=', '')
            ->get();
        foreach ($familyMembers as $f) {
            $allPersons[] = [
                'person_id' => (string) $f->person_id,
                'full_name' => trim($f->full_name),
                'person_type' => 'family_member',
                'person_type_ar' => 'فرد أسرة',
            ];
        }

        // المتوفين (dead_people table - father)
        $deceasedFathers = DB::table('dead_people')
            ->select('father_id as person_id', DB::raw("CONCAT(father_first_name, ' ', father_second_name, ' ', father_third_name, ' ', father_last_name) as full_name"))
            ->whereNotNull('father_id')
            ->where('father_id', '!=', '')
            ->get();
        foreach ($deceasedFathers as $df) {
            $allPersons[] = [
                'person_id' => (string) $df->person_id,
                'full_name' => trim($df->full_name),
                'person_type' => 'deceased_father',
                'person_type_ar' => 'متوفى (أب)',
            ];
        }

        // المتوفين (dead_people table - mother)
        $deceasedMothers = DB::table('dead_people')
            ->select('mother_id as person_id', DB::raw("CONCAT(mother_first_name, ' ', mother_second_name, ' ', mother_third_name, ' ', mother_last_name) as full_name"))
            ->whereNotNull('mother_id')
            ->where('mother_id', '!=', '')
            ->get();
        foreach ($deceasedMothers as $dm) {
            $allPersons[] = [
                'person_id' => (string) $dm->person_id,
                'full_name' => trim($dm->full_name),
                'person_type' => 'deceased_mother',
                'person_type_ar' => 'متوفى (أم)',
            ];
        }

        // المتوفين الإضافيين (additional_deceased table)
        $additionalDeceased = DB::table('additional_deceased')
            ->select('person_id', DB::raw("CONCAT(first_name, ' ', second_name, ' ', third_name, ' ', last_name) as full_name"))
            ->whereNotNull('person_id')
            ->where('person_id', '!=', '')
            ->get();
        foreach ($additionalDeceased as $ad) {
            $allPersons[] = [
                'person_id' => (string) $ad->person_id,
                'full_name' => trim($ad->full_name),
                'person_type' => 'deceased_other',
                'person_type_ar' => 'متوفى (إضافي)',
            ];
        }

        if (empty($allPersons)) {
            return [
                'total' => 0,
                'with_attachments' => 0,
                'without_attachments' => 0,
                'in_sponsorships' => 0,
                'persons' => [],
            ];
        }

        // 2. جلب كل الهويات التي لها مرفقات
        $identitysWithAttachments = DB::table('attachments')
            ->whereNotNull('person_identity_number')
            ->where('person_identity_number', '!=', '')
            ->distinct()
            ->pluck('person_identity_number')
            ->map(fn($id) => (string) $id)
            ->flip();

        // 3. جلب كل الهويات الموجودة في الكفالات
        $identityInSponsorships = DB::table('sponsorships')
            ->whereNotNull('identity_number')
            ->where('identity_number', '!=', '')
            ->distinct()
            ->pluck('identity_number')
            ->map(fn($id) => (string) $id)
            ->flip();

        // 4. حساب عدد المرفقات لكل هوية
        $attachmentCounts = DB::table('attachments')
            ->whereNotNull('person_identity_number')
            ->where('person_identity_number', '!=', '')
            ->select('person_identity_number', DB::raw('COUNT(*) as attach_count'))
            ->groupBy('person_identity_number')
            ->pluck('attach_count', 'person_identity_number')
            ->mapWithKeys(fn($count, $id) => [(string) $id => (int) $count]);

        // 5. تجهيز النتيجة النهائية
        $result = [];
        foreach ($allPersons as $person) {
            $pid = $person['person_id'];
            $hasAttachment = $identitysWithAttachments->has($pid);
            $inSponsorship = $identityInSponsorships->has($pid);

            $result[] = [
                'person_id' => $pid,
                'full_name' => $person['full_name'],
                'person_type' => $person['person_type'],
                'person_type_ar' => $person['person_type_ar'],
                'has_attachments' => $hasAttachment,
                'attachment_count' => $attachmentCounts->get($pid, 0),
                'in_sponsorships' => $inSponsorship,
            ];
        }

        // ترتيب: بدون مرفقات أولاً، ثم حسب نوع الشخص
        usort($result, function ($a, $b) {
            if ($a['has_attachments'] !== $b['has_attachments']) {
                return $a['has_attachments'] ? 1 : -1;
            }
            return strcmp($a['person_type_ar'], $b['person_type_ar']);
        });

        $withAttachments = collect($result)->where('has_attachments', true)->count();
        $withoutAttachments = collect($result)->where('has_attachments', false)->count();
        $inSponsorships = collect($result)->where('in_sponsorships', true)->count();

        return [
            'total' => count($result),
            'with_attachments' => $withAttachments,
            'without_attachments' => $withoutAttachments,
            'in_sponsorships' => $inSponsorships,
            'persons' => $result,
        ];
    }

    /**
     * تصدير التقرير
     */
    public function exportReport(array $data, string $type): string
    {
        $csv = "ID,رقم الهوية,اسم الملف,المسار,نوع الوثيقة,الحجم,ملاحظات\n";

        foreach ($data as $item) {
            $csv .= implode(',', [
                $item['id'] ?? '',
                '"' . ($item['person_identity_number'] ?? '') . '"',
                '"' . ($item['stored_file_name'] ?? '') . '"',
                '"' . ($item['file_path'] ?? '') . '"',
                $item['file_type'] ?? '',
                $item['file_size'] ?? '',
                $type === 'broken' ? 'رابط مكسور' : 'مكرر',
            ]) . "\n";
        }

        return $csv;
    }

    /**
     * فحص الملفات الموجودة فعلياً على القرص ومقارنتها مع جدول attachments
     * يستبعد ملفات PDF
     */
    public function findOrphanFiles(): array
    {
        $basePath = storage_path('app/public');
        $scanFolders = ['attachments', 'uploads'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif', 'svg', 'tiff', 'tif', 'mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'doc', 'docx', 'ppt', 'pptx', 'txt'];

        $allFiles = [];
        foreach ($scanFolders as $folder) {
            $folderPath = $basePath . '/' . $folder;
            if (!is_dir($folderPath)) continue;
            $this->scanDirectoryRecursive($folderPath, $basePath, $folder, $allowedExtensions, $allFiles);
        }

        $existingPaths = DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->pluck('file_path')
            ->map(fn($p) => ltrim($p, '/'))
            ->flip();

        $orphanFiles = [];
        foreach ($allFiles as $file) {
            $relativePath = $file['relative_path'];
            if ($existingPaths->has($relativePath)) continue;

            $parsed = $this->parseOrphanFileName($file['file_name'], $file['folder']);
            $orphanFiles[] = [
                'file_path' => $relativePath,
                'full_path' => $file['full_path'],
                'file_name' => $file['file_name'],
                'file_size' => $file['file_size'],
                'extension' => $file['extension'],
                'folder' => $file['folder'],
                'identity_number' => $parsed['identity_number'],
                'doc_type_id' => $parsed['doc_type_id'],
                'file_id_number' => $parsed['file_id_number'],
            ];
        }

        usort($orphanFiles, fn($a, $b) => strcmp($a['file_path'], $b['file_path']));

        return [
            'total_files_on_disk' => count($allFiles),
            'total_in_db' => $existingPaths->count(),
            'orphan_count' => count($orphanFiles),
            'orphan_files' => $orphanFiles,
        ];
    }

    /**
     * مسح مجلد بشكل تكراري
     */
    private function scanDirectoryRecursive(string $dirPath, string $basePath, string $relativeFolder, array $allowedExtensions, array &$results): void
    {
        $items = File::allFiles($dirPath);
        foreach ($items as $item) {
            $ext = strtolower($item->getExtension());
            if (!in_array($ext, $allowedExtensions)) continue;
            if ($ext === 'pdf') continue;

            $fullPath = $item->getPathname();
            $relativePath = str_replace($basePath . '/', '', $fullPath);
            $relativePath = str_replace('\\', '/', $relativePath);

            $results[] = [
                'full_path' => $fullPath,
                'relative_path' => $relativePath,
                'file_name' => $item->getFilename(),
                'file_size' => $item->getSize(),
                'extension' => $ext,
                'folder' => $relativeFolder,
            ];
        }
    }

    /**
     * تحليل اسم الملف واستخراج البيانات
     * التنسيق: {docTypeId}_{fileIdNumber}_{identityNumber}.{ext}
     */
    private function parseOrphanFileName(string $fileName, string $folder): array
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $parts = explode('_', $name);

        $result = [
            'doc_type_id' => null,
            'identity_number' => null,
            'file_id_number' => null,
        ];

        if (count($parts) >= 3) {
            $result['doc_type_id'] = is_numeric($parts[0]) ? (int) $parts[0] : $parts[0];
            $result['file_id_number'] = $parts[1];
            $result['identity_number'] = $parts[2];
        } elseif (count($parts) === 2) {
            $result['file_id_number'] = $parts[0];
            $result['identity_number'] = $parts[1];
        }

        return $result;
    }

    /**
     * إضافة ملفات محددة إلى جدول attachments
     */
    public function addOrphanFiles(array $files): array
    {
        $added = 0;
        $errors = [];

        foreach ($files as $file) {
            try {
                $fileSize = file_exists($file['full_path']) ? filesize($file['full_path']) : 0;

                DB::table('attachments')->insert([
                    'person_identity_number' => $file['identity_number'],
                    'file_type' => $file['doc_type_id'],
                    'file_path' => $file['file_path'],
                    'stored_file_name' => $file['file_name'],
                    'file_size' => $fileSize,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $added++;
            } catch (\Exception $e) {
                $errors[] = ['file' => $file['file_path'], 'error' => $e->getMessage()];
            }
        }

        return [
            'added_count' => $added,
            'errors' => $errors,
        ];
    }
}
