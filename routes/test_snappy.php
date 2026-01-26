<?php

// اختبار بسيط لـ Laravel Snappy
// يمكن الوصول له عبر: php artisan serve ثم زيارة /test-snappy

use Illuminate\Support\Facades\Route;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

Route::get('/test-snappy', function () {
    $html = '
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: "Arial", "Tahoma", sans-serif;
                direction: rtl;
                text-align: right;
                padding: 20px;
            }
            h1 {
                color: #003366;
                text-align: center;
                border-bottom: 2px solid #003366;
                padding-bottom: 10px;
            }
            .info-box {
                background: #f5f5f5;
                padding: 15px;
                margin: 10px 0;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <h1>اختبار مكتبة Laravel Snappy</h1>
        <div class="info-box">
            <p><strong>التاريخ:</strong> ' . date('Y-m-d H:i:s') . '</p>
            <p><strong>الحالة:</strong> المكتبة تعمل بنجاح!</p>
            <p><strong>ملاحظة:</strong> هذا اختبار للتحقق من عمل مكتبة Snappy مع اللغة العربية</p>
        </div>
        <h2>مميزات المكتبة:</h2>
        <ul>
            <li>دعم اللغة العربية والاتجاه من اليمين لليسار</li>
            <li>جودة عالية في التصدير</li>
            <li>سرعة في المعالجة</li>
            <li>دعم CSS3 بشكل كامل</li>
        </ul>
    </body>
    </html>
    ';

    return PDF::loadHTML($html)
        ->setOption('encoding', 'UTF-8')
        ->setOption('page-size', 'A4')
        ->setOption('enable-local-file-access', true)
        ->inline('test_snappy.pdf');
});
