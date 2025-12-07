<?php

/**
 * سكريبت لإنشاء ملف Excel نموذجي للاستيراد
 *
 * الاستخدام:
 * php create_import_template.php
 *
 * سيتم إنشاء ملف: public/templates/sponsorships_import_template.xlsx
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('قالب استيراد الكفالات');

    // رؤوس الأعمدة
    $headers = [
        'هوية المعيل *',           // A - رقم هوية ولي الأمر (مطلوب)
        'هوية اليتيم',             // B - رقم هوية اليتيم
        'اسم اليتيم',              // C
        'اسم المعيل',              // D
        'رقم الملف الداخلي',       // E
        'رقم الملف الخارجي',       // F
        'المؤسسة الراعية',         // G
        'تاريخ بدء الكفالة',       // H
        'تاريخ انتهاء الكفالة',    // I
        'مدة الكفالة (بالأشهر)',   // J
        'المبلغ الشهري',           // K
        'ملاحظات',                 // L
        'المحفظة',                 // M - اسم البنك
        'IBAN بالدولار',          // N
        'IBAN بالشيكل',           // O
        'رقم الهاتف',              // P
        'رقم الحساب/الهاتف المرتبط'  // Q
    ];

    // كتابة الرؤوس
    $sheet->fromArray($headers, NULL, 'A1');

    // تنسيق الرؤوس
    $headerStyle = [
        'font' => [
            'bold' => true,
            'size' => 12,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '009EF7']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    $sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);

    // إضافة صفوف أمثلة
    $exampleData = [
        ['123456789', '987654321', 'محمد أحمد علي', 'أحمد علي محمد', 'INT-2024-001', 'EXT-100', 'جمعية الحياة الخيرية', '2024-01-15', '2025-01-15', '12', '500', 'كفالة شهرية منتظمة', 'بنك فلسطين', 'PS00PALS0000000000000000000', 'PS00PALS0000000000000000001', '0599123456', '0599123456'],
        ['111222333', '444555666', 'فاطمة خالد', 'خالد محمود', 'INT-2024-002', '', 'مؤسسة الأيتام', '2024-02-01', '2025-02-01', '12', '600', 'كفالة سنوية', 'بنك القدس', 'PS00ALQD0000000000000000000', 'PS00ALQD0000000000000000001', '0598765432', '123456789'],
        ['777888999', '222333444', 'سارة عبدالله', 'عبدالله حسن', '', 'EXT-200', '', '2024-03-10', '', '6', '450', '', 'بنك الاستثمار الفلسطيني', '', 'PS00TICO0000000000000000000', '0597654321', '0597654321'],
    ];

    $sheet->fromArray($exampleData, NULL, 'A2');

    // تلوين الصفوف بالتبادل
    for ($row = 2; $row <= 4; $row++) {
        $color = ($row % 2 == 0) ? 'F3F6F9' : 'FFFFFF';
        $sheet->getStyle("A{$row}:Q{$row}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $color]
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E4E6EF']
                ]
            ]
        ]);
    }

    // ضبط عرض الأعمدة
    $columnWidths = [
        'A' => 18,  // رقم هوية ولي الأمر
        'B' => 15,  // رقم هوية اليتيم
        'C' => 20,  // اسم اليتيم
        'D' => 20,  // اسم ولي الأمر
        'E' => 18,  // رقم الملف الداخلي
        'F' => 18,  // رقم الملف الخارجي
        'G' => 25,  // المؤسسة الراعية
        'H' => 18,  // تاريخ البداية
        'I' => 18,  // تاريخ النهاية
        'J' => 15,  // مدة الكفالة
        'K' => 15,  // المبلغ الشهري
        'L' => 30,  // ملاحظات
        'M' => 25,  // اسم البنك
        'N' => 35,  // IBAN دولار
        'O' => 35,  // IBAN شيكل
        'P' => 15,  // رقم الهاتف
        'Q' => 20,  // رقم الحساب/الهاتف
    ];

    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    // ضبط ارتفاع صف الرؤوس
    $sheet->getRowDimension(1)->setRowHeight(25);

    // إضافة ورقة تعليمات
    $instructionsSheet = $spreadsheet->createSheet();
    $instructionsSheet->setTitle('التعليمات');

    $instructions = [
        ['تعليمات استخدام قالب استيراد الكفالات'],
        [''],
        ['⚠️ الحقول المطلوبة:'],
        ['* هوية المعيل: مطلوب - رقم هوية ولي الأمر/المعيل، يجب أن يكون موجوداً في قاعدة البيانات (جدول data)'],
        [''],
        ['📋 الحقول الاختيارية:'],
        ['- هوية اليتيم: رقم هوية الشخص المكفول'],
        ['- اسم اليتيم: اسم الطفل اليتيم'],
        ['- اسم المعيل: اسم ولي الأمر/المعيل'],
        ['- رقم الملف الداخلي: رقم الملف الداخلي للكفالة'],
        ['- رقم الملف الخارجي: رقم الملف الخارجي من المؤسسة'],
        ['- اسم الكافل: اسم الكافل/الداعم الذي يقدم الكفالة'],
        ['- تواريخ الكفالة: تنسيق YYYY-MM-DD (مثل: 2024-12-06)'],
        ['- مدة الكفالة (بالأشهر): عدد الأشهر (رقم)'],
        ['- المبلغ الشهري: رقم فقط بدون فواصل'],
        ['- ملاحظات: أي ملاحظات إضافية'],
        ['- المحفظة: اسم البنك (يجب أن يكون مسجلاً مسبقاً في النظام)'],
        ['- IBAN بالدولار: رقم الحساب الدولي بالدولار'],
        ['- IBAN بالشيكل: رقم الحساب الدولي بالشيكل'],
        ['- رقم الهاتف: رقم هاتف المعيل'],
        ['- رقم الحساب/الهاتف المرتبط: رقم الحساب البنكي أو رقم الهاتف المرتبط بالحساب'],
        [''],
        ['📌 ملاحظات مهمة جداً:'],
        ['1. عمود "هوية المعيل" هو العمود الأساسي للتحقق من وجود الشخص في النظام'],
        ['2. عمود "المحفظة" يعني اسم البنك الذي يتعامل معه المعيل'],
        ['3. يجب اختيار المؤسسة الكافلة ونوع الكفالة وحالة الكفالة قبل رفع الملف'],
        ['4. إذا لم يكن رقم هوية المعيل موجوداً، سيتم إظهار رسالة خطأ ويجب إضافته أولاً'],
        ['5. يجب إضافة البنوك المفقودة من قسم إدارة البنوك قبل الاستيراد'],
        ['6. يمكن ترك الحقول الاختيارية فارغة'],
        ['7. تأكد من صحة البيانات قبل الرفع'],
        ['8. البيانات البنكية ستُربط تلقائياً بـ guardian_registration (file_id_number من جدول data)'],
        [''],
        ['📅 أمثلة على تنسيق التواريخ:'],
        ['✓ صحيح: 2024-12-06'],
        ['✓ صحيح: 2024-01-15'],
        ['✗ خطأ: 06/12/2024'],
        ['✗ خطأ: 15-01-2024'],
        [''],
        ['💡 للمساعدة، راجع الأمثلة في ورقة "قالب استيراد الكفالات"'],
    ];

    $instructionsSheet->fromArray($instructions, NULL, 'A1');

    // تنسيق ورقة التعليمات
    $instructionsSheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => '009EF7']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ]
    ]);

    $instructionsSheet->mergeCells('A1:E1');
    $instructionsSheet->getColumnDimension('A')->setWidth(80);

    // حفظ الملف
    $outputPath = __DIR__ . '/public/templates/sponsorships_import_template.xlsx';

    // إنشاء المجلد إذا لم يكن موجوداً
    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($outputPath);

    echo "✅ تم إنشاء ملف القالب بنجاح:\n";
    echo "📁 المسار: {$outputPath}\n";
    echo "📊 عدد الأوراق: " . $spreadsheet->getSheetCount() . "\n";
    echo "📝 الأوراق: قالب استيراد الكفالات، التعليمات\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
