<?php

namespace App\Http\Controllers;

use App\Services\AttachmentAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AttachmentAuditController extends Controller
{
    protected $auditService;

    public function __construct(AttachmentAuditService $auditService)
    {
        $this->auditService = $auditService;
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
     * فحص الملفات الموجودة على القرص بدون سجل في DB
     */
    public function findOrphanFiles()
    {
        try {
            $results = $this->auditService->findOrphanFiles();

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('AttachmentAudit: Error finding orphan files', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء فحص الملفات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إضافة ملفات محددة إلى جدول attachments
     */
    public function addOrphanFiles(Request $request)
    {
        try {
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
}
