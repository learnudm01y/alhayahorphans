<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page_title ?? 'Excel Upload Gateway' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .content {
            padding: 30px;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .setting-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .setting-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 10px;
        }
        .setting-value {
            font-size: 1.2em;
            color: #007bff;
            font-weight: bold;
        }
        .upload-section {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 30px;
            margin: 20px 0;
            border: 2px dashed #2196f3;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f44336;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4caf50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Excel Upload Gateway</h1>
            <p>نظام رفع ملفات Excel المتقدم - دعم ملفات حتى 1GB</p>
        </div>

        <div class="content">
            @if(isset($error))
                <div class="error">
                    <strong>خطأ:</strong> {{ $error }}
                </div>
            @endif

            <h2>⚙️ إعدادات PHP الحالية</h2>
            <div class="settings-grid">
                @if(isset($current_settings) && !empty($current_settings))
                    @foreach($current_settings as $setting => $value)
                        <div class="setting-card">
                            <div class="setting-label">{{ $setting }}</div>
                            <div class="setting-value">{{ $value }}</div>
                        </div>
                    @endforeach
                @else
                    <div class="setting-card">
                        <div class="setting-label">لا توجد إعدادات متاحة</div>
                        <div class="setting-value">-</div>
                    </div>
                @endif
            </div>

            <div class="upload-section">
                <h3>📁 رفع ملفات Excel</h3>
                <p>يمكنك رفع ملفات Excel بحجم يصل إلى 1GB. الصيغ المدعومة: .xlsx, .xls, .csv</p>

                <form id="excelUploadForm" action="/admin/file/process-excel" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div style="margin: 20px 0;">
                        <input type="file" name="files[]" multiple accept=".xlsx,.xls,.csv" style="margin: 10px 0; padding: 10px; width: 100%; border: 1px solid #ccc; border-radius: 5px;">
                    </div>

                    <div style="margin: 20px 0;">
                        <label>
                            <input type="radio" name="processing_mode" value="file-only" checked>
                            حفظ الملف فقط
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="processing_mode" value="import-data">
                            حفظ الملف + استيراد البيانات
                        </label>
                    </div>

                    <div id="importOptions" style="display: none; margin: 20px 0; padding: 15px; background: #f0f0f0; border-radius: 5px;">
                        <label>
                            <input type="checkbox" name="enable_excel_import" value="1">
                            تفعيل استيراد البيانات
                        </label>
                        <br>
                        <select name="target_table" style="margin: 10px 0; padding: 5px;">
                            <option value="data">جدول البيانات الرئيسي</option>
                            <option value="dead_people">جدول المتوفين</option>
                            <option value="re_people">جدول إعادة التسجيل</option>
                        </select>
                    </div>

                    <button type="submit" class="btn">🚀 رفع الملفات</button>
                </form>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="/admin/file/php-diagnostic" class="btn">🔧 تشخيص النظام</a>
                <a href="/admin/dashboard" class="btn">🏠 العودة للوحة التحكم</a>
            </div>
        </div>
    </div>

    <script>
        // Show/hide import options based on processing mode
        document.querySelectorAll('input[name="processing_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const importOptions = document.getElementById('importOptions');
                if (this.value === 'import-data') {
                    importOptions.style.display = 'block';
                } else {
                    importOptions.style.display = 'none';
                }
            });
        });

        // Handle form submission
        document.getElementById('excelUploadForm').addEventListener('submit', function(e) {
            const fileInput = this.querySelector('input[type="file"]');
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('يرجى اختيار ملف واحد على الأقل');
                return;
            }

            // Show loading message
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.textContent = '⏳ جاري الرفع...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
