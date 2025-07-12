<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تشخيص إعدادات PHP - دعم الملفات الكبيرة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .diagnostic-container { background: rgba(255, 255, 255, 0.95); border-radius: 20px; backdrop-filter: blur(10px); }
        .setting-row { border-bottom: 1px solid #eee; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="diagnostic-container p-4">
            <h1 class="text-center mb-4">تشخيص إعدادات PHP للملفات الكبيرة</h1>

            <div class="row">
                <div class="col-12">
                    <h3>الإعدادات الحالية</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>الإعداد</th>
                                    <th>القيمة الحالية</th>
                                    <th>القيمة المطلوبة</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $settings = [
                                    'upload_max_filesize' => ['current' => ini_get('upload_max_filesize'), 'required' => '1024M'],
                                    'post_max_size' => ['current' => ini_get('post_max_size'), 'required' => '1024M'],
                                    'max_execution_time' => ['current' => ini_get('max_execution_time'), 'required' => '3600'],
                                    'max_input_time' => ['current' => ini_get('max_input_time'), 'required' => '3600'],
                                    'memory_limit' => ['current' => ini_get('memory_limit'), 'required' => '2048M'],
                                    'max_file_uploads' => ['current' => ini_get('max_file_uploads'), 'required' => '100'],
                                    'file_uploads' => ['current' => ini_get('file_uploads') ? 'On' : 'Off', 'required' => 'On'],
                                    'max_input_vars' => ['current' => ini_get('max_input_vars'), 'required' => '10000']
                                ];

                                function convertToBytes($value) {
                                    $value = trim($value);
                                    $unit = strtolower(substr($value, -1));
                                    $number = (int) $value;

                                    switch($unit) {
                                        case 'g': return $number * 1024 * 1024 * 1024;
                                        case 'm': return $number * 1024 * 1024;
                                        case 'k': return $number * 1024;
                                        default: return $number;
                                    }
                                }

                                function getStatus($current, $required, $setting) {
                                    if (in_array($setting, ['upload_max_filesize', 'post_max_size', 'memory_limit'])) {
                                        $currentBytes = convertToBytes($current);
                                        $requiredBytes = convertToBytes($required);

                                        if ($currentBytes >= $requiredBytes) {
                                            return '<span class="status-good">✓ جيد</span>';
                                        } elseif ($currentBytes >= $requiredBytes * 0.5) {
                                            return '<span class="status-warning">⚠ يحتاج تحسين</span>';
                                        } else {
                                            return '<span class="status-bad">✗ غير كافي</span>';
                                        }
                                    } else {
                                        $currentNum = (int) $current;
                                        $requiredNum = (int) $required;

                                        if ($setting === 'file_uploads') {
                                            return $current === $required ? '<span class="status-good">✓ مفعل</span>' : '<span class="status-bad">✗ معطل</span>';
                                        }

                                        if ($currentNum >= $requiredNum) {
                                            return '<span class="status-good">✓ جيد</span>';
                                        } elseif ($currentNum >= $requiredNum * 0.5) {
                                            return '<span class="status-warning">⚠ يحتاج تحسين</span>';
                                        } else {
                                            return '<span class="status-bad">✗ غير كافي</span>';
                                        }
                                    }
                                }

                                foreach ($settings as $setting => $values) {
                                    echo "<tr class='setting-row'>";
                                    echo "<td><strong>{$setting}</strong></td>";
                                    echo "<td>{$values['current']}</td>";
                                    echo "<td>{$values['required']}</td>";
                                    echo "<td>" . getStatus($values['current'], $values['required'], $setting) . "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <h4>معلومات الخادم</h4>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>إصدار PHP:</span>
                            <strong><?php echo PHP_VERSION; ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>نظام التشغيل:</span>
                            <strong><?php echo PHP_OS; ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>خادم الويب:</span>
                            <strong><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'غير محدد'; ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>مسار php.ini:</span>
                            <small><?php echo php_ini_loaded_file() ?: 'غير محدد'; ?></small>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h4>اختبار رفع الملفات</h4>
                    <form action="/admin/unified-file-management/excel-upload" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <input type="file" class="form-control" name="files[]" accept=".xlsx,.xls" multiple>
                        </div>
                        <div class="mb-3">
                            <input type="text" class="form-control" name="record_number" placeholder="رقم السجل (اختياري)">
                        </div>
                        <button type="submit" class="btn btn-primary">اختبار الرفع</button>
                    </form>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <h5>التوصيات:</h5>
                        <ul class="mb-0">
                            <?php
                            $recommendations = [];

                            if (convertToBytes(ini_get('upload_max_filesize')) < convertToBytes('1024M')) {
                                $recommendations[] = "زيادة upload_max_filesize إلى 1024M في ملف php.ini";
                            }

                            if (convertToBytes(ini_get('post_max_size')) < convertToBytes('1024M')) {
                                $recommendations[] = "زيادة post_max_size إلى 1024M في ملف php.ini";
                            }

                            if ((int) ini_get('max_execution_time') < 3600) {
                                $recommendations[] = "زيادة max_execution_time إلى 3600 ثانية";
                            }

                            if (convertToBytes(ini_get('memory_limit')) < convertToBytes('2048M')) {
                                $recommendations[] = "زيادة memory_limit إلى 2048M";
                            }

                            if (empty($recommendations)) {
                                echo "<li class='text-success'>جميع الإعدادات مناسبة لرفع الملفات الكبيرة!</li>";
                            } else {
                                foreach ($recommendations as $rec) {
                                    echo "<li>{$rec}</li>";
                                }
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12 text-center">
                    <button onclick="location.reload()" class="btn btn-outline-primary">تحديث المعلومات</button>
                    <button onclick="window.open('/admin/unified-file-management/excel-gateway', '_blank')" class="btn btn-success">فتح بوابة Excel</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
