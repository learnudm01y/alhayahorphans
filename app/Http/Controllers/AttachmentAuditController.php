<?php

namespace App\Http\Controllers;

use App\Models\Data;
use App\Models\Sponsorship;
use App\Services\AttachmentAuditService;
use App\Services\GuardianDuplicateMergeService;
use App\Services\GuardianFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AttachmentAuditController extends Controller
{
    protected $auditService;

    protected $mergeService;

    protected $guardianFileService;

    public function __construct(AttachmentAuditService $auditService, GuardianDuplicateMergeService $mergeService, GuardianFileService $guardianFileService)
    {
        $this->auditService = $auditService;
        $this->mergeService = $mergeService;
        $this->guardianFileService = $guardianFileService;
    }

    /**
     * الصفحة الرئيسية
     */
    public function index()
    {
        return view('admin.dashboard.attachment-audit.index');
    }

    /**
     * كشف المكررات
     */
    public function findDuplicates()
    {
        try {
            $results = $this->auditService->findDuplicates();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error finding duplicates', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء كشف المكررات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * كشف الأفراد المكررين في جدول re_people (للقراءة فقط)
     */
    public function findDuplicatePersons()
    {
        try {
            $results = $this->auditService->findDuplicatePersons();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error finding duplicate persons', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء فحص الأفراد المكررين: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف صف فرد مكرر من re_people مع الحفاظ على المرفقات
     */
    public function deleteDuplicatePerson(Request $request)
    {
        $data = $request->validate([
            'row_id' => 'required|integer|min:1',
            'person_id' => 'nullable|string',
        ]);

        try {
            $result = $this->auditService->deleteDuplicatePersonRow(
                (int) $data['row_id'],
                $data['person_id'] ?? null
            );

            return response()->json([
                'success' => $result['deleted'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['deleted'] ? 200 : 422);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error deleting duplicate person row', [
                'error' => $e->getMessage(),
                'row_id' => $data['row_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف السجل: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * كشف المكررات حسب المسار
     */
    public function findDuplicatePaths()
    {
        try {
            $results = $this->auditService->findDuplicatePaths();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error finding duplicate paths', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف المكررات مع الاحتفاظ بالأقدم
     */
    public function deleteDuplicates()
    {
        try {
            $results = $this->auditService->deleteDuplicatesKeepOldest();

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$results['deleted_count']} سجل مكرر",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error deleting duplicates', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف مكرر واحد
     */
    public function deleteSingleDuplicate(Request $request)
    {
        try {
            $id = $request->input('id');
            $deleted = $this->auditService->deleteSingleDuplicate($id);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'تم الحذف بنجاح' : 'لم يتم العثور على السجل',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * فحص الروابط المكسورة
     */
    public function checkBrokenLinks()
    {
        try {
            $results = $this->auditService->checkBrokenLinks();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error checking broken links', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء فحص الروابط: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف رابط مكسور واحد
     */
    public function deleteBrokenLink(Request $request)
    {
        try {
            $id = $request->input('id');
            $deleted = $this->auditService->deleteBrokenLink($id);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'تم الحذف بنجاح' : 'لم يتم العثور على السجل',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف كل الروابط المكسورة
     */
    public function deleteAllBrokenLinks(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            $results = $this->auditService->deleteAllBrokenLinks($ids);

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$results['deleted_count']} رابط مكسور",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error deleting broken links', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * فحص المعيلين والافراد والمتوفين — من لا يملك مرفقات
     */
    public function getWithoutAttachments()
    {
        try {
            $results = $this->auditService->findAllPersonsWithAttachmentStatus();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error checking persons without attachments', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الفحص: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تصدير بيانات المعيلين والافراد والمتوفين إلى Excel
     */
    public function exportWithoutAttachments(Request $request)
    {
        try {
            $raw = $request->input('data', '[]');
            $data = is_string($raw) ? json_decode($raw, true) : $raw;

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Headers
            $headers = ['#', 'رقم الهوية', 'الاسم الكامل', 'نوع الشخص', 'موجود في الكفالات', 'عدد المرفقات', 'ملاحظات'];
            $sheet->fromArray($headers, NULL, 'A1');

            // Style headers
            $headerStyle = [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '009EF7']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

            // Data rows
            $row = 2;
            $index = 1;
            foreach ($data as $person) {
                $sheet->setCellValue('A' . $row, $index);
                $sheet->setCellValue('B' . $row, $person['person_id'] ?? '');
                $sheet->setCellValue('C' . $row, $person['full_name'] ?? '');
                $sheet->setCellValue('D' . $row, $person['person_type_ar'] ?? '');
                $sheet->setCellValue('E' . $row, ($person['in_sponsorships'] ?? false) ? 'نعم' : 'لا');
                $sheet->setCellValue('F' . $row, $person['attachment_count'] ?? 0);
                $sheet->setCellValue('G' . $row, ($person['attachment_count'] ?? 0) == 0 ? 'بدون مرفقات' : '');

                // Highlight row red if no attachments
                if (($person['attachment_count'] ?? 0) == 0) {
                    $sheet->getStyle('A' . $row . ':G' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FDE8E8');
                }

                $row++;
                $index++;
            }

            // Auto-size columns
            for ($i = 0; $i < count($headers); $i++) {
                $sheet->getColumnDimension(chr(65 + $i))->setAutoSize(true);
            }

            // Save and download
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'persons_attachments_');
            $writer->save($tempFile);

            $fileName = 'بيانات_المرفقات_' . date('Y-m-d_H-i-s') . '.xlsx';

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error exporting persons without attachments', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التصدير: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * كشف المعيلين الذين يملكون أكثر من ملف
     */
    public function findDuplicateGuardians()
    {
        try {
            $results = $this->mergeService->scan();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error scanning duplicate guardians', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء فحص المعيلين المكررين: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * دمج ملفات معيل مكرر في ملف واحد
     */
    public function mergeDuplicateGuardian(Request $request)
    {
        try {
            $identity = trim((string) $request->input('identity'));

            if ($identity === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهوية مطلوب',
                ], 400);
            }

            $report = $this->mergeService->mergeGroup($identity, $request->input('keep_file_id_number'));

            return response()->json([
                'success' => !empty($report['merged']),
                'message' => $report['message'] ?? null,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error merging duplicate guardian', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الدمج: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * دمج جميع المعيلين المكررين
     */
    public function mergeAllDuplicateGuardians()
    {
        try {
            $results = $this->mergeService->mergeAll();

            return response()->json([
                'success' => true,
                'message' => "تم دمج {$results['merged_groups']} مجموعة، وفشل {$results['failed_groups']}",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error merging all duplicate guardians', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الدمج: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تصدير التقرير CSV
     */
    public function export(Request $request)
    {
        try {
            $type = $request->input('type', 'broken');
            $data = json_decode($request->input('data', '[]'), true);

            $csv = $this->auditService->exportReport($data, $type);

            $fileName = 'attachment_audit_' . $type . '_' . date('Y-m-d_H-i-s') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التصدير: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * بدء فحص الملفات بدون سجل
     */
    public function initOrphanScan()
    {
        try {
            $results = $this->auditService->initOrphanScan();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error init orphan scan', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الفحص: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب صفحة من نتائج الفحص
     */
    public function getOrphanFilesPage(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 50);
            $results = $this->auditService->getOrphanFilesPage($page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إضافة ملفات محددة إلى جدول attachments
     */
    public function addOrphanFiles(Request $request)
    {
        try {
            if ($request->input('add_all')) {
                $results = $this->auditService->addAllOrphanFilesFromCache();

                return response()->json([
                    'success' => true,
                    'message' => "تم إضافة {$results['added_count']} ملف بنجاح",
                    'data' => $results,
                ]);
            }

            $files = $request->input('files', []);

            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم تحديد أي ملفات',
                ], 400);
            }

            $results = $this->auditService->addOrphanFiles($files);

            return response()->json([
                'success' => true,
                'message' => "تم إضافة {$results['added_count']} ملف بنجاح",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error adding orphan files', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الإضافة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * الكشف عن كفالات بدون بيانات معيل متطابقة في جدول data
     */
    public function findMissingGuardians()
    {
        try {
            $results = $this->auditService->findMissingGuardians();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error finding missing guardians', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء فحص بيانات المعيلين: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إصلاح بيانات معيل كفالة واحدة (تلقائياً أو بهوية بديلة مُدخلة يدوياً)
     */
    public function fixGuardian(Request $request)
    {
        try {
            $request->validate([
                'sponsorship_id' => 'required|integer|exists:sponsorships,id',
                'new_guardian_identity' => 'nullable|string|max:20',
            ]);

            $sponsorship = Sponsorship::find($request->input('sponsorship_id'));

            if (!$sponsorship) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على الكفالة المطلوبة',
                ], 404);
            }

            $result = $this->attemptGuardianFix($sponsorship, (string) $request->input('new_guardian_identity'));

            if ($result['ok']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'needs_manual' => !empty($result['needs_manual']),
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error fixing guardian', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إصلاح بيانات المعيل: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إصلاح بيانات المعيلين لجميع الكفالات المتأثرة دفعة واحدة
     * (مع إمكانية التحديد بحالة معينة عبر الحقل status: empty_identity أو identity_not_found)
     */
    public function fixAllGuardians(Request $request)
    {
        try {
            $statusFilter = trim((string) $request->input('status', ''));
            $missing = $this->auditService->findMissingGuardians();

            $items = $missing['items'] ?? [];

            if ($statusFilter !== '') {
                $items = array_values(array_filter(
                    $items,
                    fn($item) => ($item['status'] ?? '') === $statusFilter
                ));
            } else {
                // الإبقاء على نطاق "إصلاح الكل" السابق دون توسيعه لحالات المطابقة الجديدة
                // (حالات المطابقة تُصلح فردياً إلى حين إضافة منطق إصلاح خاص بها)
                $items = array_values(array_filter(
                    $items,
                    fn($item) => in_array($item['status'] ?? '', ['empty_identity', 'identity_not_found'], true)
                ));
            }

            if (empty($items)) {
                return response()->json([
                    'success' => true,
                    'message' => 'لا توجد كفالات تحتاج إلى إصلاح',
                    'data' => ['fixed' => 0, 'needs_manual' => 0, 'skipped' => 0, 'failed' => 0, 'manual_items' => []],
                ]);
            }

            $fixed = 0;
            $needsManual = 0;
            $skipped = 0;
            $failed = 0;
            $manualItems = [];

            foreach ($items as $item) {
                $sponsorship = Sponsorship::find($item['sponsorship_id']);

                if (!$sponsorship) {
                    $failed++;
                    continue;
                }

                // في حالة "تعارض ملف المعيل" نربط بملف المعيل المسجّل في سجل الأسرة (re_people)
                // وفي حالة "هوية غير موجودة" نستخدمه كأفضلية أولى مع الرجوع للمنطق السابق عند تعذره
                $itemStatus = $item['status'] ?? '';
                $rePeopleGuardianFile = trim((string) ($item['orphan_re_people_guardian_file'] ?? ''));

                $preferredFile = null;
                $requirePreferredFile = false;

                if ($itemStatus === 'file_mismatch' && $rePeopleGuardianFile !== '') {
                    $preferredFile = $rePeopleGuardianFile;
                    $requirePreferredFile = true;
                } elseif (in_array($itemStatus, ['identity_not_found', 'empty_identity'], true) && $rePeopleGuardianFile !== '') {
                    $preferredFile = $rePeopleGuardianFile;
                }

                $result = $this->attemptGuardianFix($sponsorship, '', $preferredFile, $requirePreferredFile);

                if ($result['ok']) {
                    $fixed++;
                } elseif (!empty($result['skipped'])) {
                    $skipped++;
                } elseif (!empty($result['needs_manual'])) {
                    $needsManual++;
                    $manualItems[] = $item['orphan_name'] !== '' ? $item['orphan_name'] : ('#' . $item['sponsorship_id']);
                } else {
                    $failed++;
                }
            }

            $message = "تم الإصلاح: {$fixed} | يحتاج إدخالاً يدوياً: {$needsManual}";

            if ($skipped > 0) {
                $message .= " | تم التخطي: {$skipped}";
            }

            if ($failed > 0) {
                $message .= " | فشل: {$failed}";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'fixed' => $fixed,
                    'needs_manual' => $needsManual,
                    'skipped' => $skipped,
                    'failed' => $failed,
                    'manual_items' => $manualItems,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error fixing all guardians', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الإصلاح الجماعي: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * محاولة إصلاح كفالة واحدة:
     * 1) برقم ملف معيل محدد (يُستخدم في حالة تعارض ملف المعيل مع سجل الأسرة)، أو
     * 2) بهوية المعيل المُدخلة يدوياً (إن وُجدت)، أو
     * 3) تلقائياً: بهوية المعيل الحالية إن وُجدت في جدول data، وإلا عبر رقم الملف (relation ثم internal)،
     * ثم إعادة الربط عبر GuardianFileService::relinkSponsorship ومزامنة الهوية والاسم.
     */
    private function attemptGuardianFix(
        Sponsorship $sponsorship,
        string $newGuardianIdentity = '',
        ?string $preferredGuardianFile = null,
        bool $requirePreferredFile = false
    ): array {
        $newGuardianIdentity = trim($newGuardianIdentity);
        $preferredGuardianFile = $preferredGuardianFile !== null ? trim($preferredGuardianFile) : null;
        $guardianData = null;
        $source = 'identity';

        if ($preferredGuardianFile !== null && $preferredGuardianFile !== '') {
            $guardianData = Data::whereIn(
                'file_id_number',
                $this->guardianFileService->fileCandidates($preferredGuardianFile)
            )
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($guardianData) {
                $source = 'guardian_file';
            } elseif ($requirePreferredFile) {
                return [
                    'ok' => false,
                    'needs_manual' => true,
                    'message' => 'لم يُعثر على معيل برقم الملف ' . $preferredGuardianFile . ' في جدول المعيلين',
                ];
            }
        }

        if (!$guardianData && $newGuardianIdentity !== '') {
            $guardianData = $this->guardianFileService->findLatestDataByIdentity($newGuardianIdentity);

            if (!$guardianData) {
                return [
                    'ok' => false,
                    'needs_manual' => false,
                    'message' => 'لم يُعثر على معيل بهوية ' . $newGuardianIdentity . ' في جدول المعيلين',
                ];
            }

            $source = 'manual';
        }

        if (!$guardianData) {
            $currentIdentity = trim((string) $sponsorship->guardian_identity_number);

            if ($currentIdentity !== '') {
                $guardianData = $this->guardianFileService->findLatestDataByIdentity($currentIdentity);
            }

            if (!$guardianData) {
                foreach ([(string) $sponsorship->relation_id_number, (string) $sponsorship->internal_file_number] as $fileCandidate) {
                    $fileCandidate = trim($fileCandidate);

                    if ($fileCandidate === '') {
                        continue;
                    }

                    $row = Data::where('file_id_number', $fileCandidate)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($row) {
                        $guardianData = $row;
                        $source = 'file';
                        break;
                    }
                }
            }
        }

        if (!$guardianData) {
            // البحث عن الفرد المكفول في جدول المتوفين بهويته، ثم مطابقة رقم ملف المتوفى مع جدول المعيلين
            $deceasedGuardian = $this->guardianFileService->findGuardianViaDeceasedIdentity(
                (string) $sponsorship->identity_number
            );

            if ($deceasedGuardian !== null) {
                $deceasedIdentity = trim((string) ($deceasedGuardian['data_id_number'] ?? ''));

                if ($deceasedIdentity !== '') {
                    $guardianData = $this->guardianFileService->findLatestDataByIdentity($deceasedIdentity);
                }

                if (!$guardianData && trim((string) ($deceasedGuardian['file_id_number'] ?? '')) !== '') {
                    $guardianData = Data::whereIn(
                        'file_id_number',
                        $this->guardianFileService->fileCandidates((string) $deceasedGuardian['file_id_number'])
                    )
                        ->orderByDesc('id')
                        ->first();
                }

                if ($guardianData) {
                    $source = 'dead_people';
                }
            }
        }

        if (!$guardianData) {
            return [
                'ok' => false,
                'needs_manual' => true,
                'skipped' => true,
                'message' => 'لا يوجد معيل مطابق لهوية المكفول في جدول المتوفين',
            ];
        }

        $targetFile = trim((string) $guardianData->file_id_number);
        $dataIdentity = trim((string) $guardianData->data_id_number);

        if ($targetFile !== '') {
            $this->guardianFileService->relinkSponsorship(
                $sponsorship,
                $targetFile,
                $dataIdentity !== '' ? $dataIdentity : null
            );
        }

        if ($dataIdentity !== '') {
            $sponsorship->guardian_identity_number = $dataIdentity;
        }

        $guardianFullName = trim(implode(' ', array_filter([
            (string) $guardianData->data_first_name,
            (string) $guardianData->data_father_name,
            (string) $guardianData->data_grand_father_name,
            (string) $guardianData->data_family_name,
        ])));

        if ($guardianFullName !== '') {
            $sponsorship->guardian_name = $guardianFullName;
        }

        $sponsorship->save();

        Log::info('GUARDIAN_IDENTITY_FIXED', [
            'sponsorship_id' => $sponsorship->id,
            'guardian_identity' => $dataIdentity,
            'target_file' => $targetFile,
            'source' => $source,
        ]);

        return [
            'ok' => true,
            'message' => 'تم إصلاح بيانات المعيل بنجاح',
            'data' => [
                'guardian_identity_number' => $sponsorship->guardian_identity_number,
                'guardian_name' => $sponsorship->guardian_name,
                'relation_id_number' => $sponsorship->relation_id_number,
            ],
        ];
    }
}
