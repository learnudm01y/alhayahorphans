<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\DataTables\PersonsDataTable;
use App\Services\NormalizedSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CivilRegistryController extends Controller
{
    protected $searchService;

    public function __construct(NormalizedSearchService $searchService)
    {
        $this->searchService = $searchService;
    }
    /**
     * عرض قائمة السجلات
     */
    public function index(PersonsDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.civil_registry.index');
    }

    /**
     * عرض نموذج إنشاء سجل جديد
     */
    public function create()
    {
        // جلب البيانات المساعدة
        $CI_BIRTH_CD = collect([
            (object)['id' => 1, 'ci_birth_cd' => 'العراق'],
            (object)['id' => 2, 'ci_birth_cd' => 'سوريا'],
            (object)['id' => 3, 'ci_birth_cd' => 'الأردن'],
            (object)['id' => 4, 'ci_birth_cd' => 'لبنان'],
            (object)['id' => 5, 'ci_birth_cd' => 'مصر'],
            (object)['id' => 6, 'ci_birth_cd' => 'السعودية'],
            (object)['id' => 7, 'ci_birth_cd' => 'أخرى']
        ]);

        $CI_BIRTH_TB_CD = collect([
            (object)['id' => 1, 'CI_BIRTH_TB_CD' => 'بغداد'],
            (object)['id' => 2, 'CI_BIRTH_TB_CD' => 'البصرة'],
            (object)['id' => 3, 'CI_BIRTH_TB_CD' => 'الموصل'],
            (object)['id' => 4, 'CI_BIRTH_TB_CD' => 'أربيل'],
            (object)['id' => 5, 'CI_BIRTH_TB_CD' => 'النجف'],
            (object)['id' => 6, 'CI_BIRTH_TB_CD' => 'كربلاء'],
            (object)['id' => 7, 'CI_BIRTH_TB_CD' => 'الأنبار'],
            (object)['id' => 8, 'CI_BIRTH_TB_CD' => 'أخرى']
        ]);

        $socialStatuses = collect([
            (object)['id' => 1, 'CI_PERSONAL_CD' => 'أعزب'],
            (object)['id' => 2, 'CI_PERSONAL_CD' => 'متزوج'],
            (object)['id' => 3, 'CI_PERSONAL_CD' => 'مطلق'],
            (object)['id' => 4, 'CI_PERSONAL_CD' => 'أرمل']
        ]);

        $city = collect([
            (object)['id' => 1, 'city' => 'بغداد'],
            (object)['id' => 2, 'city' => 'البصرة'],
            (object)['id' => 3, 'city' => 'الموصل'],
            (object)['id' => 4, 'city' => 'أربيل'],
            (object)['id' => 5, 'city' => 'النجف'],
            (object)['id' => 6, 'city' => 'كربلاء'],
            (object)['id' => 7, 'city' => 'الأنبار'],
            (object)['id' => 8, 'city' => 'أخرى']
        ]);

        return view('admin.dashboard.civil_registry.create', compact('CI_BIRTH_CD', 'CI_BIRTH_TB_CD', 'socialStatuses', 'city'));
    }

    /**
     * حفظ سجل جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CI_ID_NUM' => 'required|numeric|unique:civilregistry.persons,CI_ID_NUM',
            'CI_FIRST_ARB' => 'required|string|max:255',
            'CI_FATHER_ARB' => 'required|string|max:255',
            'CI_FAMILY_ARB' => 'required|string|max:255',
            'CI_BIRTH_DT' => 'nullable|date',
            'CI_SEX_CD' => 'required|in:1,2',
            'CI_PERSONAL_CD' => 'nullable|numeric',
            'MOTHER_NAME1' => 'nullable|string|max:255',
            'CITY' => 'nullable|numeric',
            'STREET' => 'nullable|string|max:255',
            'HOUSE_NO' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::connection('civilregistry')->table('persons')->insert([
                'CI_ID_NUM' => $request->CI_ID_NUM,
                'CI_FIRST_ARB' => $request->CI_FIRST_ARB,
                'CI_FATHER_ARB' => $request->CI_FATHER_ARB,
                'CI_GRAND_FATHER_ARB' => $request->CI_GRAND_FATHER_ARB,
                'CI_FAMILY_ARB' => $request->CI_FAMILY_ARB,
                'CI_BIRTH_DT' => $request->CI_BIRTH_DT,
                'CI_SEX_CD' => $request->CI_SEX_CD,
                'CI_PERSONAL_CD' => $request->CI_PERSONAL_CD,
                'MOTHER_NAME1' => $request->MOTHER_NAME1,
                'CITY' => $request->CITY,
                'STREET' => $request->STREET,
                'HOUSE_NO' => $request->HOUSE_NO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('civil-registry.index')
                ->with('success', 'تم إنشاء السجل بنجاح');

        } catch (\Exception $e) {
            return back()->with('error', 'خطأ في إنشاء السجل: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * عرض سجل محدد
     */
    public function show($id)
    {
        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            return view('admin.civil_registry.show', compact('person'));

        } catch (\Exception $e) {
            return redirect()->route('civil-registry.index')
                ->with('error', 'خطأ في جلب البيانات: ' . $e->getMessage());
        }
    }

    /**
     * عرض نموذج تعديل السجل
     */
    public function edit($id)
    {
        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            // جلب البيانات المساعدة
            $CI_BIRTH_CD = collect([
                (object)['id' => 1, 'ci_birth_cd' => 'العراق'],
                (object)['id' => 2, 'ci_birth_cd' => 'سوريا'],
                (object)['id' => 3, 'ci_birth_cd' => 'الأردن'],
                (object)['id' => 4, 'ci_birth_cd' => 'لبنان'],
                (object)['id' => 5, 'ci_birth_cd' => 'مصر'],
                (object)['id' => 6, 'ci_birth_cd' => 'السعودية'],
                (object)['id' => 7, 'ci_birth_cd' => 'أخرى']
            ]);

            $CI_BIRTH_TB_CD = collect([
                (object)['id' => 1, 'CI_BIRTH_TB_CD' => 'بغداد'],
                (object)['id' => 2, 'CI_BIRTH_TB_CD' => 'البصرة'],
                (object)['id' => 3, 'CI_BIRTH_TB_CD' => 'الموصل'],
                (object)['id' => 4, 'CI_BIRTH_TB_CD' => 'أربيل'],
                (object)['id' => 5, 'CI_BIRTH_TB_CD' => 'النجف'],
                (object)['id' => 6, 'CI_BIRTH_TB_CD' => 'كربلاء'],
                (object)['id' => 7, 'CI_BIRTH_TB_CD' => 'الأنبار'],
                (object)['id' => 8, 'CI_BIRTH_TB_CD' => 'أخرى']
            ]);

            $socialStatuses = collect([
                (object)['id' => 1, 'CI_PERSONAL_CD' => 'أعزب'],
                (object)['id' => 2, 'CI_PERSONAL_CD' => 'متزوج'],
                (object)['id' => 3, 'CI_PERSONAL_CD' => 'مطلق'],
                (object)['id' => 4, 'CI_PERSONAL_CD' => 'أرمل']
            ]);

            $city = collect([
                (object)['id' => 1, 'city' => 'بغداد'],
                (object)['id' => 2, 'city' => 'البصرة'],
                (object)['id' => 3, 'city' => 'الموصل'],
                (object)['id' => 4, 'city' => 'أربيل'],
                (object)['id' => 5, 'city' => 'النجف'],
                (object)['id' => 6, 'city' => 'كربلاء'],
                (object)['id' => 7, 'city' => 'الأنبار'],
                (object)['id' => 8, 'city' => 'أخرى']
            ]);

            return view('admin.dashboard.civil_registry.edit', compact('person', 'CI_BIRTH_CD', 'CI_BIRTH_TB_CD', 'socialStatuses', 'city'));

        } catch (\Exception $e) {
            return redirect()->route('civil-registry.index')
                ->with('error', 'خطأ في جلب البيانات: ' . $e->getMessage());
        }
    }

    /**
     * تحديث السجل
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'CI_ID_NUM' => 'required|numeric|unique:civilregistry.persons,CI_ID_NUM,' . $id . ',ID',
            'CI_FIRST_ARB' => 'required|string|max:255',
            'CI_FATHER_ARB' => 'required|string|max:255',
            'CI_FAMILY_ARB' => 'required|string|max:255',
            'CI_BIRTH_DT' => 'nullable|date',
            'CI_SEX_CD' => 'required|in:1,2',
            'CI_PERSONAL_CD' => 'nullable|numeric',
            'MOTHER_NAME1' => 'nullable|string|max:255',
            'CITY' => 'nullable|numeric',
            'STREET' => 'nullable|string|max:255',
            'HOUSE_NO' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            DB::connection('civilregistry')->table('persons')->where('ID', $id)->update([
                'CI_ID_NUM' => $request->CI_ID_NUM,
                'CI_FIRST_ARB' => $request->CI_FIRST_ARB,
                'CI_FATHER_ARB' => $request->CI_FATHER_ARB,
                'CI_GRAND_FATHER_ARB' => $request->CI_GRAND_FATHER_ARB,
                'CI_FAMILY_ARB' => $request->CI_FAMILY_ARB,
                'CI_BIRTH_DT' => $request->CI_BIRTH_DT,
                'CI_SEX_CD' => $request->CI_SEX_CD,
                'CI_PERSONAL_CD' => $request->CI_PERSONAL_CD,
                'MOTHER_NAME1' => $request->MOTHER_NAME1,
                'CITY' => $request->CITY,
                'STREET' => $request->STREET,
                'HOUSE_NO' => $request->HOUSE_NO,
                'updated_at' => now(),
            ]);

            return redirect()->route('civil-registry.index')
                ->with('success', 'تم تحديث السجل بنجاح');

        } catch (\Exception $e) {
            return back()->with('error', 'خطأ في تحديث السجل: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * حذف السجل
     */
    public function destroy($id)
    {
        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            DB::connection('civilregistry')->table('persons')->where('ID', $id)->delete();

            return redirect()->route('civil-registry.index')
                ->with('success', 'تم حذف السجل بنجاح');

        } catch (\Exception $e) {
            return redirect()->route('civil-registry.index')
                ->with('error', 'خطأ في حذف السجل: ' . $e->getMessage());
        }
    }

    /**
     * البحث في السجلات (مع التطبيع)
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'نص البحث مطلوب'
            ]);
        }

        try {
            // استخدام خدمة البحث المطبع
            $results = $this->searchService->searchCivilRegistry($query, 20);

            return response()->json([
                'success' => true,
                'data' => $results,
                'count' => $results->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إحصائيات السجلات
     */
    public function stats()
    {
        try {
            $totalRecords = DB::connection('civilregistry')->table('persons')->count();
            $maleCount = DB::connection('civilregistry')->table('persons')->where('CI_SEX_CD', 1)->count();
            $femaleCount = DB::connection('civilregistry')->table('persons')->where('CI_SEX_CD', 2)->count();
            $aliveCount = DB::connection('civilregistry')->table('persons')->whereNull('CI_DEAD_DT')->orWhere('CI_DEAD_DT', 0)->count();
            $deadCount = DB::connection('civilregistry')->table('persons')->where('CI_DEAD_DT', '>', 0)->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $totalRecords,
                    'male' => $maleCount,
                    'female' => $femaleCount,
                    'alive' => $aliveCount,
                    'dead' => $deadCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تنزيل قالب Excel لرفع بيانات المواطنين إلى السجل المدني
     */
    public function importTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('المواطنون');

            // رؤوس الأعمدة المتوقعة (يجب تطابق أسمائها في الملف المرفوع)
            $headers = [
                'رقم الهوية' => 'CI_ID_NUM',
                'الاسم الأول' => 'CI_FIRST_ARB',
                'اسم الأب' => 'CI_FATHER_ARB',
                'اسم الجد' => 'CI_GRAND_FATHER_ARB',
                'اسم العائلة' => 'CI_FAMILY_ARB',
                'اسم الأم' => 'MOTHER_NAME1',
                'تاريخ الميلاد' => 'CI_BIRTH_DT',
                'الجنس (ذكر/أنثى)' => 'CI_SEX_CD',
                'الحالة الاجتماعية' => 'CI_PERSONAL_CD',
                'تاريخ الوفاة' => 'CI_DEAD_DT',
                'المدينة' => 'CITY',
                'الشارع' => 'STREET',
                'رقم المنزل' => 'HOUSE_NO',
            ];

            $rowNum = 1;
            foreach ($headers as $arabic => $column) {
                $sheet->setCellValueByColumnAndRow($rowNum, 1, $arabic);
                $sheet->getStyleByColumnAndRow($rowNum, 1)
                    ->getFont()->setBold(true);
                $rowNum++;
            }

            // تلوين رأس الجدول
            $sheet->getStyle('A1:M1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF0D6EFD');
            $sheet->getStyle('A1:M1')->getFont()->getColor()->setARGB('FFFFFFFF');

            // سطر مثال
            $sheet->setCellValue('A2', 123456789);
            $sheet->setCellValue('B2', 'محمد');
            $sheet->setCellValue('C2', 'أحمد');
            $sheet->setCellValue('D2', 'خالد');
            $sheet->setCellValue('E2', 'العبد الله');
            $sheet->setCellValue('G2', '1990-01-15');
            $sheet->setCellValue('H2', 'ذكر');

            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $tempPath = storage_path('app/temp_civil_registry_template_' . time() . '.xlsx');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, 'قالب_رفع_المواطنين.xlsx')->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return back()->with('error', 'خطأ في إنشاء القالب: ' . $e->getMessage());
        }
    }

    /**
     * رفع بيانات المواطنين من ملف Excel إلى السجل المدني (civilregistry.persons)
     * - يتجاهل الصفوف ذات رقم الهوية المكرر (المستخدم أو المتكرر ضمن الملف)
     * - يرجّع تقرير بالنتائج: المستورد، المتكرر، والأخطاء
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

        // مؤشرات إجمالية لكل الملفات
        $imported = 0;
        $duplicates = 0;
        $errors = [];
        $processedFileStats = [];

        // خريطة كل الأرقام الموجودة مسبقاً مرة واحدة (بدلاً من قراءتها لكل ملف)
        $existingIds = DB::connection('civilregistry')->table('persons')
            ->pluck('CI_ID_NUM')
            ->map(fn($v) => trim((string) $v))
            ->flip()
            ->all();

        $this->syncReferenceTablesIfNeeded();

        try {
            // معالجة الملفات واحداً تلو الآخر
            foreach ($files as $order => $file) {
                $fileName = $file->getClientOriginalName();
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
                } catch (\Throwable $loadErr) {
                    $errors[] = "الملف لا يمكن فتحه ({$fileName}): " . $loadErr->getMessage();
                    $processedFileStats[] = [
                        'file' => $fileName,
                        'imported' => 0,
                        'duplicates' => 0,
                        'errors' => 1,
                    ];
                    continue;
                }

                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray(null, true, true, true);

                // إزالة صف الرؤوس
                $headers = array_shift($rows);

                $headerMap = $this->buildHeaderMap(is_array($headers) ? $headers : []);

                $fileImported = 0;
                $fileDuplicates = 0;
                $fileErrors = 0;
                $batch = [];
            //    $seenInFile = []; // لمنع تكرار رقم الهوية داخل الملف الواحد

                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2;

                    // تخطي الصفوف الفارغة تماماً
                    $cellValues = array_values($row);
                    if (empty(array_filter($cellValues, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                        continue;
                    }

                    $get = fn (array $synonyms) => $this->getCellValue($row, $headerMap, $synonyms);

                    $identity = trim((string) ($get(['رقم الهوية', 'الهوية', 'CI_ID_NUM']) ?? ''));

                    // التحقق من الحقل الأساسي (رقم الهوية)
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
                  //  if (isset($existingIds[$identity]) || isset($seenInFile[$identity])) {
                   //     $duplicates++;
                  //      $fileDuplicates++;
                   //     continue;
                   // }
                   // $seenInFile[$identity] = true;
                 //   $existingIds[$identity] = true; // حتى لا يتكرر في الملفات التالية

                    $batch[] = [
                        'CI_ID_NUM' => $identity,
                        'CI_FIRST_ARB' => trim((string) ($get(['الاسم الأول', 'الاسم الاول', 'CI_FIRST_ARB']) ?? '')),
                        'CI_FATHER_ARB' => trim((string) ($get(['اسم الأب', 'اسم الاب', 'CI_FATHER_ARB']) ?? '')),
                        'CI_GRAND_FATHER_ARB' => trim((string) ($get(['اسم الجد', 'CI_GRAND_FATHER_ARB']) ?? '')),
                        'CI_FAMILY_ARB' => trim((string) ($get(['اسم العائلة', 'اسم العائله', 'العائلة', 'العائله', 'CI_FAMILY_ARB']) ?? '')),
                        'MOTHER_NAME1' => trim((string) ($get(['اسم الأم', 'اسم الام', 'MOTHER_NAME1']) ?? '')),
                        'CI_BIRTH_DT' => $this->parseDate($get(['تاريخ الميلاد', 'تاريخ الازدياد', 'CI_BIRTH_DT'])),
                        'CI_SEX_CD' => $this->parseSex($get(['الجنس', 'الجنس (ذكر/أنثى)', 'الجنس (ذكر/انثى)', 'جنس', 'CI_SEX_CD'])),
                        'CI_PERSONAL_CD' => $this->parseSocialStatus($get(['الحالة الاجتماعية', 'الحالة المدنية', 'CI_PERSONAL_CD'])),
                        'CI_DEAD_DT' => $this->parseDeathStatus($get(['تاريخ الوفاة', 'تاريخ وفاة', 'حالة الوفاة', 'CI_DEAD_DT'])),
                        'CITY' => $this->parseCity($get(['المدينة', 'المدينه', 'CITY'])),
                        'STREET' => trim((string) ($get(['الشارع', 'العنوان', 'STREET']) ?? '')),
                        'HOUSE_NO' => trim((string) ($get(['رقم المنزل', 'خانة المنزل', 'HOUSE_NO']) ?? '')),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // إدراج دفعات لتقليل عدد الاستعلامات
                    if (count($batch) >= 500) {
                        $insertedNow = $this->insertPersonBatch($batch, $errors, $fileName, $fileErrors);
                        $imported += $insertedNow;
                        $fileImported += $insertedNow;
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    $insertedNow = $this->insertPersonBatch($batch, $errors, $fileName, $fileErrors);
                    $imported += $insertedNow;
                    $fileImported += $insertedNow;
                }

                $processedFileStats[] = [
                    'file' => $fileName,
                    'imported' => $fileImported,
                    'duplicates' => $fileDuplicates,
                    'errors' => $fileErrors,
                ];

                // تحرير الذاكرة بعد كل ملف
                unset($spreadsheet, $worksheet, $rows, $headers, $headerMap, $batch);
            }
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'خطأ في معالجة الملفات: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'خطأ في معالجة الملفات: ' . $e->getMessage());
        }

        // بناء رسالة التقرير النهائي
        $message = "تم رفع {$imported} مواطن بنجاح";
        if ($duplicates > 0) {
            $message .= "، وتخطي {$duplicates} رقم مكرر";
        }
        if (!empty($errors)) {
            $message .= '، وتخطي ' . count($errors) . ' صف بسبب أخطاء';
            \Illuminate\Support\Facades\Log::warning('أخطاء رفع المواطنين للسجل المدني', $errors);
        }

        $report = '';
        foreach ($processedFileStats as $stat) {
            $report .= "\n• " . $stat['file'] . ": رفع " . $stat['imported'] . '، مكرر ' . $stat['duplicates'] . '، أخطاء ' . $stat['errors'];
        }

        $result = [
            'success' => $imported > 0 || ($duplicates > 0 && count($errors) === 0),
            'imported' => $imported,
            'duplicates' => $duplicates,
            'message' => $message,
            'report' => nl2br(trim($report)),
            'errors_count' => count($errors),
            'errors' => $errors,
        ];

        // استجابة JSON للرفع عبر AJAX مع شريط التقدم
        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if ($imported === 0 && $duplicates === 0 && empty($errors)) {
            return back()->with('error', 'لم يتم العثور على بيانات في الملفات المرفوعة. تأكد من تطابق صف الرؤوس مع القالب.');
        }

        if ($imported > 0) {
            $detail = !empty($errors) ? ' — أول 5 أخطاء: ' . implode('، ', array_slice($errors, 0, 5)) : '';
            return redirect()->route('civil-registry.index')->with('success', $message . $report . $detail);
        }

        return back()->with('error', 'لم يتم استيراد أي صف. ' . $message . ' — ' . implode('، ', array_slice($errors, 0, 10)));
    }

    /**
     * Copy lookup tables from the main database when the civil registry DB is empty.
     */
    private function syncReferenceTablesIfNeeded(): void
    {
        foreach (['city', 'ci_personal_cd', 'ci_birth_cd', 'ci_birth_tb_cd'] as $table) {
            try {
                if (DB::connection('civilregistry')->table($table)->count() > 0) {
                    continue;
                }

                $rows = DB::table($table)->get();
                if ($rows->isEmpty()) {
                    continue;
                }

                foreach ($rows->chunk(100) as $chunk) {
                    DB::connection('civilregistry')->table($table)->insertOrIgnore(
                        $chunk->map(fn ($row) => (array) $row)->all()
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("تعذر مزامنة جدول {$table} للسجل المدني: " . $e->getMessage());
            }
        }
    }

    /**
     * Build a normalized header map that supports Arabic labels and English column codes.
     */
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

    /**
     * Normalize a spreadsheet header for consistent lookup.
     */
    private function normalizeHeaderKey(string $header): string
    {
        return mb_strtolower(normalizeArabicText(trim($header)));
    }

    /**
     * Read a cell value using Arabic or English header aliases.
     */
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

    /**
     * Insert rows in bulk, falling back to single-row inserts on constraint errors.
     */
    private function insertPersonBatch(array $batch, array &$errors, string $fileName, int &$fileErrors): int
    {
        if (empty($batch)) {
            return 0;
        }

        try {
            DB::connection('civilregistry')->table('persons')->insert($batch);

            return count($batch);
        } catch (\Throwable $e) {
            $inserted = 0;

            foreach ($batch as $row) {
                try {
                    DB::connection('civilregistry')->table('persons')->insert($row);
                    $inserted++;
                } catch (\Throwable $rowErr) {
                    $errors[] = "الملف ({$fileName}) الهوية {$row['CI_ID_NUM']}: " . $this->friendlyInsertError($rowErr);
                    $fileErrors++;
                }
            }

            return $inserted;
        }
    }

    /**
     * Convert low-level SQL errors into readable Arabic messages.
     */
    private function friendlyInsertError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'foreign key constraint fails') && str_contains($message, 'persons_city_foreign')) {
            return 'رمز المدينة غير موجود في جدول المدن';
        }

        if (str_contains($message, 'foreign key constraint fails') && str_contains($message, 'persons_ci_personal_cd_foreign')) {
            return 'رمز الحالة الاجتماعية غير موجود';
        }

        if (str_contains($message, 'Duplicate entry')) {
            return 'رقم الهوية مكرر';
        }

        return 'فشل حفظ السجل';
    }

    /**
     * Convert sex value truth table (male/female/1/2) to numeric code
     */
    private function parseSex($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = normalizeArabicText(trim((string) $value));
        if (in_array($clean, ['1', 'ذ', 'ذكر', 'ذكور', 'رجل'], true)) {
            return 1;
        }
        if (in_array($clean, ['2', 'انثى', 'أنثى', 'انثي', 'انث', 'female'], true)) {
            return 2;
        }
        return null;
    }

    /**
     * Convert marital status text to numeric code (1 single, 2 married, 3 widower, 4 divorced)
     */
    private function parseSocialStatus($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = normalizeArabicText(trim((string) $value));
        if (in_array($clean, ['اعزب', 'أعزب', 'اعزوب', '1'], true)) {
            return 1;
        }
        if (in_array($clean, ['متزوج', 'متزوجه', 'متزوجة', '2'], true)) {
            return 2;
        }
        if (in_array($clean, ['ارمل', 'أرمل', 'ارمله', 'أرملة', '3'], true)) {
            return 3;
        }
        if (in_array($clean, ['مطلق', 'مطلقه', 'مطلقة', '4'], true)) {
            return 4;
        }
        if (preg_match('/^\d+$/', $clean)) {
            $id = (int) $clean;
            if ($this->referenceIdExists('ci_personal_cd', $id)) {
                return $id;
            }

            return null;
        }

        return null;
    }

    /**
     * Parse death flag values (0 alive, non-zero deceased). Not a calendar date.
     */
    private function parseDeathStatus($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $clean = normalizeArabicText(trim((string) $value));

        if (in_array($clean, ['0', 'حي', 'alive', 'على قيد الحياة', 'غير متوفي', 'غير متوفى'], true)) {
            return null;
        }

        if (in_array($clean, ['1', '2', 'متوفي', 'متوفى', 'dead'], true)) {
            return (int) $clean === 0 ? 1 : (int) $clean;
        }

        if (preg_match('/^\d+$/', $clean)) {
            $num = (int) $clean;
            return $num === 0 ? null : $num;
        }

        return null;
    }

    /**
     * Parse any date format (text or Excel serial) into Y-m-d
     */
    private function parseDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            if (is_numeric($value) && $value > 20000 && $value < 80000) {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
                return $dt->format('Y-m-d');
            }

            $str = trim((string) $value);

            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $str, $matches)) {
                $dt = \DateTime::createFromFormat('!d/m/Y', sprintf('%02d/%02d/%04d', $matches[1], $matches[2], $matches[3]));
                if ($dt instanceof \DateTime) {
                    return $dt->format('Y-m-d');
                }
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
                return $str;
            }

            $dt = strtotime($str);
            if ($dt === false) {
                return null;
            }
            return date('Y-m-d', $dt);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve a city name to its id if it exists in the city table, otherwise keep as-is
     */
    private function parseCity($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = normalizeArabicText(trim((string) $value));
        if (preg_match('/^\d+$/', $clean)) {
            $id = (int) $clean;
            if ($this->referenceIdExists('city', $id)) {
                return $id;
            }

            return null;
        }
        try {
            $city = DB::connection('civilregistry')->table('city')
                ->whereRaw('LOWER(`city`) = ?', [mb_strtolower($clean)])
                ->orWhereRaw('LOWER(`city`) = ?', [mb_strtolower((string) $value)])
                ->first();
            return $city ? $city->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check whether a lookup id exists in the civil registry database.
     */
    private function referenceIdExists(string $table, int $id): bool
    {
        try {
            return DB::connection('civilregistry')->table($table)->where('id', $id)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
