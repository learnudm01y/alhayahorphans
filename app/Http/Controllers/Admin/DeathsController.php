<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DeathsDataTable;
use App\Http\Controllers\Controller;
use App\Models\DeathRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeathsController extends Controller
{
    /** شاشة قائمة الوفيات */
    public function index(DeathsDataTable $dataTable)
    {
        // تُعرض المتوفين فقط — بنفس فلتر الجدول
        $deceased = DeathRecord::query()->where('status', 'متوفى');

        return $dataTable->render('admin.dashboard.deaths.index', [
            'total'     => (clone $deceased)->count(),
            'matched'   => (clone $deceased)->where('civil_matched', true)->count(),
            'unmatched' => (clone $deceased)->where('civil_matched', false)->count(),
        ]);
    }

    /**
     * تنزيل قالب Excel لرفع قائمة الوفيات (٣ أعمدة: رقم الهوية، الاسم، الحالة)
     */
    public function importTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('قائمة الوفيات');

            $headers = ['رقم الهوية', 'الاسم', 'الحالة'];
            foreach ($headers as $i => $header) {
                $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
                $sheet->getStyleByColumnAndRow($i + 1, 1)->getFont()->setBold(true);
            }

            $sheet->getStyle('A1:C1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFDC3545');
            $sheet->getStyle('A1:C1')->getFont()->getColor()->setARGB('FFFFFFFF');

            // سطر مثال
            $sheet->setCellValue('A2', 123456789);
            $sheet->setCellValue('B2', 'محمد أحمد خالد العبد الله');
            $sheet->setCellValue('C2', 'متوفى');

            foreach (['A', 'B', 'C'] as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $tempPath = storage_path('app/temp_deaths_template_' . time() . '.xlsx');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, 'قالب_قائمة_الوفيات.xlsx')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return back()->with('error', 'خطأ في إنشاء القالب: ' . $e->getMessage());
        }
    }

    /**
     * رفع قائمة الوفيات من ملف Excel + الدمج التلقائي مع السجل المدني
     * - يقرأ: رقم الهوية، الاسم، الحالة
     * - يحفظ في deaths_list
     * - يضع علامة متوفى (CI_DEAD_DT=1) على كل هوية مطابقة في persons
     */
    public function importFromExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_files' => 'required|array|min:1',
            'excel_files.*' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ], [
            'excel_files.required' => 'يرجى اختيار ملف واحد على الأقل',
            'excel_files.min' => 'يرجى اختيار ملف واحد على الأقل',
            'excel_files.*.mimes' => 'إحدى الصيغ غير مدعومة. يجب أن يكون XLSX أو XLS أو CSV',
            'excel_files.*.max' => 'أحد الملفات يتجاوز الحد المسموح (10MB)',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()->all()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $files = $request->file('excel_files');
        $userId = auth()->id();

        // مؤشرات إجمالية
        $imported = 0;
        $duplicates = 0;
        $merged = 0;
        $notFound = 0;
        $errors = [];
        $processedFileStats = [];

        // كل أرقام الهويات الموجودة مسبقاً في deaths_list مرة واحدة
        $existingIds = DeathRecord::query()
            ->pluck('identity_number')
            ->map(fn ($v) => trim((string) $v))
            ->flip()
            ->all();

        try {
            foreach ($files as $file) {
                $fileName = $file->getClientOriginalName();
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
                } catch (\Throwable $loadErr) {
                    $errors[] = "الملف لا يمكن فتحه ({$fileName}): " . $loadErr->getMessage();
                    $processedFileStats[] = ['file' => $fileName, 'imported' => 0, 'duplicates' => 0, 'errors' => 1];
                    continue;
                }

                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray(null, true, true, true);
                $headers = array_shift($rows); // إزالة صف الرؤوس
                $headerMap = $this->buildHeaderMap(is_array($headers) ? $headers : []);

                $fileImported = 0;
                $fileDuplicates = 0;
                $fileErrors = 0;
                $batch = [];
                $seenInFile = []; // منع تكرار الهوية داخل الملف

                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2;

                    $cellValues = array_values($row);
                    if (empty(array_filter($cellValues, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                        continue;
                    }

                    $get = fn (array $synonyms) => $this->getCellValue($row, $headerMap, $synonyms);

                    $identity = trim((string) ($get(['رقم الهوية', 'الهوية', 'identity', 'CI_ID_NUM']) ?? ''));

                    if ($identity === '') {
                        $errors[] = "الملف ({$fileName}) السطر {$rowNumber}: رقم الهوية فارغ";
                        $fileErrors++;
                        continue;
                    }
                    if (!preg_match('/^\d+$/', $identity)) {
                        $errors[] = "الملف ({$fileName}) السطر {$rowNumber}: رقم الهوية ({$identity}) غير صالح";
                        $fileErrors++;
                        continue;
                    }
                    if (isset($existingIds[$identity]) || isset($seenInFile[$identity])) {
                        $duplicates++;
                        $fileDuplicates++;
                        continue;
                    }
                    $seenInFile[$identity] = true;
                    $existingIds[$identity] = true;

                    $name = trim((string) ($get(['الاسم', 'الاسم الكامل', 'name']) ?? ''));
                    $status = $this->parseStatus($get(['الحالة', 'status']));

                    $batch[] = [
                        'identity_number' => $identity,
                        'name' => $name,
                        'status' => $status,
                        'civil_matched' => false,
                        'source_file' => $fileName,
                        'imported_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= 500) {
                        $insertedNow = $this->insertBatch($batch, $errors, $fileName, $fileErrors);
                        $imported += $insertedNow;
                        $fileImported += $insertedNow;
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    $insertedNow = $this->insertBatch($batch, $errors, $fileName, $fileErrors);
                    $imported += $insertedNow;
                    $fileImported += $insertedNow;
                }

                $processedFileStats[] = [
                    'file' => $fileName,
                    'imported' => $fileImported,
                    'duplicates' => $fileDuplicates,
                    'errors' => $fileErrors,
                ];

                unset($spreadsheet, $worksheet, $rows, $headers, $headerMap, $batch, $seenInFile);
            }
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'خطأ في معالجة الملفات: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'خطأ في معالجة الملفات: ' . $e->getMessage());
        }

        // ===== الدمج التلقائي مع السجل المدني بعد الاستيراد =====
        [$merged, $notFound] = $this->mergeWithCivilRegistry();

        // بناء رسالة التقرير النهائي
        $message = "تم رفع {$imported} سجل وفاة";
        if ($merged > 0) {
            $message .= "، ودمج {$merged} مع السجل المدني";
        }
        if ($notFound > 0) {
            $message .= "، و{$notFound} غير موجود في السجل المدني";
        }
        if ($duplicates > 0) {
            $message .= "، وتخطي {$duplicates} مكرر";
        }
        if (!empty($errors)) {
            $message .= '، وتخطي ' . count($errors) . ' صف بسبب أخطاء';
            \Illuminate\Support\Facades\Log::warning('أخطاء رفع قائمة الوفيات', $errors);
        }

        $report = '';
        foreach ($processedFileStats as $stat) {
            $report .= "\n• " . $stat['file'] . ': رفع ' . $stat['imported'] . '، مكرر ' . $stat['duplicates'] . '، أخطاء ' . $stat['errors'];
        }

        $result = [
            'success' => $imported > 0 || $merged > 0,
            'imported' => $imported,
            'merged' => $merged,
            'not_found' => $notFound,
            'duplicates' => $duplicates,
            'message' => $message,
            'report' => nl2br(trim($report)),
            'errors_count' => count($errors),
            'errors' => $errors,
        ];

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if ($imported === 0 && $merged === 0 && empty($errors)) {
            return back()->with('error', 'لم يتم العثور على بيانات في الملفات. تأكد من تطابق صف الرؤوس مع القالب.');
        }

        return redirect()->route('civil-registry.deaths.index')->with('success', $message . $report);
    }

    /**
     * إعادة الدمج: تطبيق الوفيات على أي سجلات أُضيفت لاحقاً في persons
     */
    public function merge()
    {
        [$merged, $notFound] = $this->mergeWithCivilRegistry();

        $message = "تم دمج {$merged} سجل مع السجل المدني";
        if ($notFound > 0) {
            $message .= "، و{$notFound} غير موجود في السجل المدني";
        }

        return redirect()->route('civil-registry.deaths.index')->with('success', $message);
    }

    /**
     * حذف سجل من قائمة الوفيات
     */
    public function destroy(DeathRecord $death)
    {
        $death->delete();

        return redirect()->route('civil-registry.deaths.index')->with('success', 'تم حذف السجل');
    }

    /* ===================== الدمج مع السجل المدني ===================== */

    /**
     * يضع علامة متوفى (CI_DEAD_DT=1) على كل هوية في deaths_list موجودة في persons
     * @return array [$merged, $notFound]
     */
    private function mergeWithCivilRegistry(): array
    {
        $merged = 0;
        $notFound = 0;

        // كل الهويات غير المدموجة بعد
        $pending = DeathRecord::query()
            ->where('civil_matched', false)
            ->pluck('identity_number')
            ->map(fn ($v) => trim((string) $v))
            ->unique()
            ->values();

        if ($pending->isEmpty()) {
            return [0, 0];
        }

        foreach ($pending->chunk(500) as $chunk) {
            // جلب الهويات الموجودة فعلياً في persons
            $found = DB::connection('civilregistry')->table('persons')
                ->whereIn('CI_ID_NUM', $chunk->all())
                ->pluck('CI_ID_NUM')
                ->map(fn ($v) => trim((string) $v))
                ->flip();

            foreach ($chunk as $identity) {
                if (isset($found[$identity])) {
                    // وضع علامة الوفاة على السجل المطابق
                    DB::connection('civilregistry')->table('persons')
                        ->where('CI_ID_NUM', $identity)
                        ->where(fn ($q) => $q->whereNull('CI_DEAD_DT')->orWhere('CI_DEAD_DT', 0))
                        ->update(['CI_DEAD_DT' => 1]);

                    DeathRecord::query()
                        ->whereIn('identity_number', [$identity])
                        ->where('civil_matched', false)
                        ->update(['civil_matched' => true]);

                    $merged++;
                } else {
                    $notFound++;
                }
            }
        }

        return [$merged, $notFound];
    }

    /* ===================== أدوات قراءة الملف ===================== */

    private function insertBatch(array $batch, array &$errors, string $fileName, int &$fileErrors): int
    {
        if (empty($batch)) {
            return 0;
        }

        try {
            DeathRecord::query()->insert($batch);
            return count($batch);
        } catch (\Throwable $e) {
            $inserted = 0;
            foreach ($batch as $row) {
                try {
                    DeathRecord::query()->insert($row);
                    $inserted++;
                } catch (\Throwable $rowErr) {
                    $errors[] = "الملف ({$fileName}) الهوية {$row['identity_number']}: فشل حفظ السجل";
                    $fileErrors++;
                }
            }
            return $inserted;
        }
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            if ($header === null || trim((string) $header) === '') {
                continue;
            }
            $map[$this->normalizeHeaderKey((string) $header)] = $index;
        }
        return $map;
    }

    private function normalizeHeaderKey(string $header): string
    {
        return mb_strtolower(normalizeArabicText(trim($header)));
    }

    private function getCellValue(array $row, array $headerMap, array $synonyms)
    {
        foreach ($synonyms as $alias) {
            $key = $headerMap[$this->normalizeHeaderKey((string) $alias)] ?? null;
            if ($key !== null && array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
                return $row[$key];
            }
        }
        return null;
    }

    /** تحويل قيمة الحالة إلى نص موحد (متوفى افتراضياً) */
    private function parseStatus($value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return 'متوفى';
        }
        $clean = normalizeArabicText(trim((string) $value));
        if (in_array($clean, ['0', 'حي', 'alive', 'على قيد الحياة', 'غير متوفي', 'غير متوفى'], true)) {
            return 'حي';
        }
        return 'متوفى';
    }
}
