<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    // إنشاء ملف Excel جديد
    $spreadsheet = new Spreadsheet();

    // الورقة الأولى - بيانات الموظفين
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('الموظفين');

    $employeeData = [
        ['اسم الموظف', 'القسم', 'الراتب', 'تاريخ التوظيف'],
        ['أحمد محمد', 'المبيعات', 5000, '2023-01-15'],
        ['فاطمة أحمد', 'المحاسبة', 4500, '2023-02-20'],
        ['محمد علي', 'تقنية المعلومات', 6000, '2023-03-10'],
        ['نور الدين', 'الموارد البشرية', 4800, '2023-04-05'],
        ['سارة أحمد', 'التسويق', 4200, '2023-05-12'],
        ['خالد محمود', 'المشتريات', 3800, '2023-06-08']
    ];

    $sheet1->fromArray($employeeData, null, 'A1');

    // تنسيق الرأس
    $sheet1->getStyle('A1:D1')->getFont()->setBold(true);
    $sheet1->getStyle('A1:D1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet1->getStyle('A1:D1')->getFill()->getStartColor()->setARGB('FF4CAF50');

    // ضبط عرض الأعمدة
    $sheet1->getColumnDimension('A')->setWidth(20);
    $sheet1->getColumnDimension('B')->setWidth(25);
    $sheet1->getColumnDimension('C')->setWidth(15);
    $sheet1->getColumnDimension('D')->setWidth(20);

    // إضافة ورقة ثانية - بيانات المنتجات
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('المنتجات');

    $productData = [
        ['المنتج', 'السعر', 'الكمية', 'الإجمالي'],
        ['لابتوب Dell', 2500, 10, '=B2*C2'],
        ['طابعة HP', 800, 5, '=B3*C3'],
        ['ماوس لاسلكي', 50, 100, '=B4*C4'],
        ['لوحة مفاتيح', 120, 50, '=B5*C5'],
        ['شاشة LCD', 1200, 8, '=B6*C6'],
        ['كابل USB', 25, 200, '=B7*C7']
    ];

    $sheet2->fromArray($productData, null, 'A1');

    // تنسيق الرأس للورقة الثانية
    $sheet2->getStyle('A1:D1')->getFont()->setBold(true);
    $sheet2->getStyle('A1:D1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet2->getStyle('A1:D1')->getFill()->getStartColor()->setARGB('FF2196F3');

    // ضبط عرض الأعمدة
    $sheet2->getColumnDimension('A')->setWidth(20);
    $sheet2->getColumnDimension('B')->setWidth(15);
    $sheet2->getColumnDimension('C')->setWidth(15);
    $sheet2->getColumnDimension('D')->setWidth(15);

    // إضافة ورقة ثالثة - تقرير مالي
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('التقرير المالي');

    $financialData = [
        ['البند', 'يناير', 'فبراير', 'مارس', 'الإجمالي'],
        ['المبيعات', 50000, 65000, 72000, '=B2+C2+D2'],
        ['المصروفات', 30000, 35000, 40000, '=B3+C3+D3'],
        ['الربح الصافي', '=B2-B3', '=C2-C3', '=D2-D3', '=E2-E3']
    ];

    $sheet3->fromArray($financialData, null, 'A1');

    // تنسيق الرأس للورقة الثالثة
    $sheet3->getStyle('A1:E1')->getFont()->setBold(true);
    $sheet3->getStyle('A1:E1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet3->getStyle('A1:E1')->getFill()->getStartColor()->setARGB('FFFF9800');

    // تعيين الورقة الأولى كورقة نشطة
    $spreadsheet->setActiveSheetIndex(0);

    // حفظ الملف
    $writer = new Xlsx($spreadsheet);

    // إنشاء المجلدات إذا لم تكن موجودة
    $directory = 'storage/app/public/documents/excel/000010';
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $filePath = $directory . '/sample_excel_file.xlsx';
    $writer->save($filePath);

    echo "✅ تم إنشاء ملف Excel بنجاح!\n";
    echo "📄 مسار الملف: {$filePath}\n";
    echo "📊 الملف يحتوي على 3 أوراق عمل:\n";
    echo "   1. الموظفين (6 موظفين)\n";
    echo "   2. المنتجات (6 منتجات)\n";
    echo "   3. التقرير المالي (بيانات مالية)\n";
    echo "🔗 رابط الوصول: http://127.0.0.1:8000/storage/documents/excel/000010/sample_excel_file.xlsx\n";

} catch (Exception $e) {
    echo "❌ خطأ في إنشاء ملف Excel: " . $e->getMessage() . "\n";
}
