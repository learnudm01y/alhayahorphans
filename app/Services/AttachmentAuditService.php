<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class AttachmentAuditService
{
    /**
     * فحص المكررات حسب رقم الهوية + نوع الوثيقة
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
     * فحص المكررات حسب المسار (نفس الملف مسجل أكثر من مرة)
     * يستخدم REPLACE للتوافق مع storage/attachments/ و attachments/
     */
    public function findDuplicatePaths(): array
    {
        $normalizedExpr = "REPLACE(file_path, 'storage/', '')";

        // 1. استعلام واحد — يلاقي المسارات المكررة
        $duplicates = DB::table('attachments')
            ->select(
                DB::raw("{$normalizedExpr} as normalized_path"),
                DB::raw('COUNT(*) as count'),
                DB::raw('MIN(id) as keep_id')
            )
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->groupBy(DB::raw("{$normalizedExpr}"))
            ->having('count', '>', 1)
            ->orderByDesc('count')
            ->get();

        if ($duplicates->isEmpty()) {
            return [
                'total_groups' => 0,
                'total_duplicates' => 0,
                'total_to_delete' => 0,
                'groups' => [],
            ];
        }

        // 2. استعلام واحد — يجيب كل السجلات المكررة دفعة وحدة
        $normalizedPaths = $duplicates->pluck('normalized_path')->toArray();
        $placeholders = implode(',', array_fill(0, count($normalizedPaths), '?'));

        $allRecords = DB::table('attachments')
            ->whereRaw("{$normalizedExpr} IN ({$placeholders})", $normalizedPaths)
            ->orderBy('id', 'asc')
            ->get();

        // 3. يربط كل سجل بمجموعته في الذاكرة
        $recordsByPath = [];
        foreach ($allRecords as $record) {
            $np = str_replace('storage/', '', $record->file_path);
            $recordsByPath[$np][] = [
                'id' => $record->id,
                'person_identity_number' => $record->person_identity_number,
                'stored_file_name' => $record->stored_file_name,
                'file_path' => $record->file_path,
                'file_type' => $record->file_type,
                'file_size' => $record->file_size,
                'created_at' => $record->created_at,
            ];
        }

        // 4. يبني النتيجة النهائية
        $results = [];
        foreach ($duplicates as $dup) {
            $results[] = [
                'file_path' => $dup->normalized_path,
                'count' => (int) $dup->count,
                'keep_id' => (int) $dup->keep_id,
                'records' => $recordsByPath[$dup->normalized_path] ?? [],
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
        $deletedFiles = 0;
        $errors = [];

        foreach ($duplicates as $dup) {
            $idsToDelete = DB::table('attachments')
                ->where('person_identity_number', $dup->person_identity_number)
                ->where('file_type', $dup->file_type)
                ->where('id', '!=', $dup->keep_id)
                ->pluck('id');

            foreach ($idsToDelete as $id) {
                try {
                    $record = DB::table('attachments')->where('id', $id)->first();
                    if ($record) {
                        $this->deleteFileFromDiskIfNeeded($record->file_path);
                        DB::table('attachments')->where('id', $id)->delete();
                        $deletedCount++;
                    }
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
     * حذف مكرر واحد — يحذف السجل من DB + الملف من القرص إذا ما في سجل تاني يشير إليه
     */
    public function deleteSingleDuplicate(int $id): bool
    {
        $record = DB::table('attachments')->where('id', $id)->first();
        if (!$record) return false;

        $this->deleteFileFromDiskIfNeeded($record->file_path);

        return DB::table('attachments')->where('id', $id)->delete() > 0;
    }

    /**
     * حذف ملف من القرص بشرط أمان:
     * يتأكد إنو ما في أي سجل تاني في DB يشير لنفس الملف الفعلي
     */
    private function deleteFileFromDiskIfNeeded(?string $filePath): void
    {
        if (empty($filePath)) return;

        $normalized = $this->normalizeFilePath($filePath);

        $otherRecordsCount = DB::table('attachments')
            ->where('file_path', '!=', $filePath)
            ->whereRaw("REPLACE(file_path, 'storage/', '') = ?", [$normalized])
            ->count();

        if ($otherRecordsCount > 0) return;

        $fullPath = storage_path('app/public/' . $normalized);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * تطبيع المسار — يشيل storage/ من البداية
     */
    private function normalizeFilePath(string $filePath): string
    {
        $path = ltrim($filePath, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }
        return $path;
    }

    /**
     * حذف رابط مكسور واحد — يحذف السجل من DB + الملف من القرص إذا ما في سجل تاني
     */
    public function deleteBrokenLink(int $id): bool
    {
        $record = DB::table('attachments')->where('id', $id)->first();
        if (!$record) return false;

        $this->deleteFileFromDiskIfNeeded($record->file_path);

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
     * حذف كل الروابط المكسورة
     */
    public function deleteAllBrokenLinks(array $brokenIds): array
    {
        $deletedCount = 0;
        $errors = [];

        foreach ($brokenIds as $id) {
            try {
                $record = DB::table('attachments')->where('id', $id)->first();
                if ($record) {
                    $this->deleteFileFromDiskIfNeeded($record->file_path);
                    DB::table('attachments')->where('id', $id)->delete();
                    $deletedCount++;
                }
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
     * يخزن النتائج في Cache ويُرجع إحصائيات أولية فقط
     */
    public function initOrphanScan(): array
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

        $existingPaths = [];
        $rows = DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->select('file_path')
            ->cursor();
        foreach ($rows as $row) {
            $path = ltrim($row->file_path, '/');
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, 8);
            }
            $existingPaths[$path] = true;
        }
        unset($rows);

        $orphanFiles = [];
        foreach ($allFiles as $file) {
            $relativePath = $file['relative_path'];
            if (isset($existingPaths[$relativePath])) continue;

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
        $totalOnDisk = count($allFiles);
        unset($allFiles, $existingPaths, $rows);

        usort($orphanFiles, fn($a, $b) => strcmp($a['file_path'], $b['file_path']));

        $cacheKey = 'orphan_scan_results';
        Cache::put($cacheKey, $orphanFiles, 3600);

        return [
            'total_files_on_disk' => $totalOnDisk,
            'total_in_db' => DB::table('attachments')->count(),
            'orphan_count' => count($orphanFiles),
            'total_pages' => (int) ceil(count($orphanFiles) / 50),
        ];
    }

    /**
     * جلب صفحة من نتائج الفحص المخزنة في Cache
     */
    public function getOrphanFilesPage(int $page = 1, int $perPage = 50): array
    {
        $cacheKey = 'orphan_scan_results';
        $allOrphans = Cache::get($cacheKey, []);

        $total = count($allOrphans);
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageFiles = array_slice($allOrphans, $offset, $perPage);

        return [
            'orphan_files' => $pageFiles,
            'orphan_count' => $total,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'per_page' => $perPage,
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
     * إضافة جميع الملفات المفقودة — يفحص ويضيف دفعة 1000 بدون cache
     */
    public function addAllOrphanFilesFromCache(int $batchSize = 1000): array
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

        $existingPaths = [];
        $rows = DB::table('attachments')->whereNotNull('file_path')->where('file_path', '!=', '')->select('file_path')->cursor();
        foreach ($rows as $row) {
            $path = ltrim($row->file_path, '/');
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, 8);
            }
            $existingPaths[$path] = true;
        }
        unset($rows);

        $added = 0;
        $errors = [];
        $batch = [];
        $now = now();

        foreach ($allFiles as $file) {
            $relativePath = $file['relative_path'];
            if (isset($existingPaths[$relativePath])) continue;

            $parsed = $this->parseOrphanFileName($file['file_name'], $file['folder']);

            $batch[] = [
                'person_identity_number' => $parsed['identity_number'],
                'file_type' => $parsed['doc_type_id'],
                'file_path' => $relativePath,
                'stored_file_name' => $file['file_name'],
                'file_size' => $file['file_size'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                try {
                    DB::table('attachments')->insert($batch);
                    $added += count($batch);
                } catch (\Exception $e) {
                    $errors[] = ['file' => 'batch', 'error' => $e->getMessage()];
                }
                $batch = [];
                break;
            }
        }

        if (!empty($batch)) {
            try {
                DB::table('attachments')->insert($batch);
                $added += count($batch);
            } catch (\Exception $e) {
                $errors[] = ['file' => 'batch', 'error' => $e->getMessage()];
            }
        }

        $hasMore = ($added >= $batchSize);

        return [
            'added_count' => $added,
            'remaining' => $hasMore ? 'more' : 0,
            'errors' => $errors,
        ];
    }

    /**
     * إضافة ملفات محددة إلى جدول attachments (بإدخالات جماعية)
     */
    public function addOrphanFiles(array $files): array
    {
        $added = 0;
        $errors = [];
        $now = now();

        foreach ($files as $file) {
            try {
                $identity = $file['identity_number'] ?? null;
                $docType = $file['doc_type_id'] ?? null;
                $filePath = $file['file_path'] ?? null;

                if (!$filePath) {
                    $errors[] = ['file' => 'unknown', 'error' => 'مسار الملف فارغ'];
                    continue;
                }

                $fileSize = (isset($file['full_path']) && file_exists($file['full_path'])) ? filesize($file['full_path']) : ($file['file_size'] ?? 0);

                DB::table('attachments')->insert([
                    'person_identity_number' => $identity,
                    'file_type' => $docType,
                    'file_path' => $filePath,
                    'stored_file_name' => $file['file_name'] ?? basename($filePath),
                    'file_size' => $fileSize,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $added++;
            } catch (\Exception $e) {
                $errors[] = ['file' => $file['file_path'] ?? 'unknown', 'error' => $e->getMessage()];
            }
        }

        return [
            'added_count' => $added,
            'errors' => $errors,
        ];
    }
}
