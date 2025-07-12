<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page_title ?? 'PHP System Diagnostic' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
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
        .section {
            margin: 30px 0;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }
        .section-header {
            background: #343a40;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 1.2em;
        }
        .section-content {
            padding: 20px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
        }
        .card-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        .card-value {
            color: #007bff;
            font-family: monospace;
            word-break: break-all;
        }
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        .status-warning {
            color: #ffc107;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .recommendations {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .recommendation {
            margin: 10px 0;
            padding: 10px;
            border-left: 4px solid #ffc107;
            background: #fff;
        }
        .recommendation.error {
            border-left-color: #dc3545;
        }
        .btn {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
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
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f44336;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .table th, .table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: right;
        }
        .table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .permission-ok {
            background: #d4edda;
            color: #155724;
        }
        .permission-warning {
            background: #fff3cd;
            color: #856404;
        }
        .permission-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 PHP System Diagnostic</h1>
            <p>تشخيص شامل لإعدادات PHP ونظام رفع الملفات</p>
        </div>

        <div class="content">
            @if(isset($error))
                <div class="error-message">
                    <strong>خطأ:</strong> {{ $error }}
                </div>
            @endif

            <!-- PHP Settings Section -->
            <div class="section">
                <div class="section-header">⚙️ إعدادات PHP الأساسية</div>
                <div class="section-content">
                    <div class="grid">
                        @if(isset($php_settings) && !empty($php_settings))
                            @foreach($php_settings as $setting => $value)
                                <div class="card">
                                    <div class="card-label">{{ $setting }}</div>
                                    <div class="card-value">{{ $value ?: 'غير محدد' }}</div>
                                </div>
                            @endforeach
                        @else
                            <div class="card">
                                <div class="card-label">لا توجد إعدادات متاحة</div>
                                <div class="card-value">-</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Server Information -->
            <div class="section">
                <div class="section-header">🌐 معلومات الخادم</div>
                <div class="section-content">
                    <div class="grid">
                        @if(isset($server_info) && !empty($server_info))
                            @foreach($server_info as $info => $value)
                                <div class="card">
                                    <div class="card-label">{{ $info }}</div>
                                    <div class="card-value">{{ $value ?: 'غير متاح' }}</div>
                                </div>
                            @endforeach
                        @else
                            <div class="card">
                                <div class="card-label">لا توجد معلومات متاحة</div>
                                <div class="card-value">-</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Directory Permissions -->
            <div class="section">
                <div class="section-header">📁 صلاحيات المجلدات</div>
                <div class="section-content">
                    @if(isset($permissions) && !empty($permissions))
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>المجلد</th>
                                    <th>المسار</th>
                                    <th>موجود</th>
                                    <th>قابل للقراءة</th>
                                    <th>قابل للكتابة</th>
                                    <th>الصلاحيات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($permissions as $name => $perm)
                                    <tr>
                                        <td><strong>{{ $name }}</strong></td>
                                        <td style="font-family: monospace; font-size: 0.9em;">{{ $perm['path'] }}</td>
                                        <td class="{{ $perm['exists'] ? 'permission-ok' : 'permission-error' }}">
                                            {{ $perm['exists'] ? '✓ نعم' : '✗ لا' }}
                                        </td>
                                        <td class="{{ $perm['readable'] ? 'permission-ok' : 'permission-error' }}">
                                            {{ $perm['readable'] ? '✓ نعم' : '✗ لا' }}
                                        </td>
                                        <td class="{{ $perm['writable'] ? 'permission-ok' : 'permission-error' }}">
                                            {{ $perm['writable'] ? '✓ نعم' : '✗ لا' }}
                                        </td>
                                        <td style="font-family: monospace;">{{ $perm['permissions'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p>لا توجد معلومات صلاحيات متاحة</p>
                    @endif
                </div>
            </div>

            <!-- PHP Extensions -->
            <div class="section">
                <div class="section-header">🔌 إضافات PHP المطلوبة</div>
                <div class="section-content">
                    <div class="grid">
                        @if(isset($extensions) && !empty($extensions))
                            @foreach($extensions as $extension => $loaded)
                                <div class="card">
                                    <div class="card-label">{{ $extension }}</div>
                                    <div class="card-value {{ $loaded ? 'status-ok' : 'status-error' }}">
                                        {{ $loaded ? '✓ مثبت' : '✗ غير مثبت' }}
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="card">
                                <div class="card-label">لا توجد معلومات إضافات</div>
                                <div class="card-value">-</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            @if(isset($recommendations) && !empty($recommendations))
                <div class="section">
                    <div class="section-header">💡 التوصيات والتحسينات</div>
                    <div class="section-content">
                        <div class="recommendations">
                            @foreach($recommendations as $rec)
                                <div class="recommendation {{ $rec['type'] }}">
                                    <strong>{{ $rec['setting'] }}:</strong><br>
                                    القيمة الحالية: <code>{{ $rec['current'] }}</code><br>
                                    القيمة المُوصى بها: <code>{{ $rec['recommended'] }}</code><br>
                                    السبب: {{ $rec['reason'] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Security Settings -->
            <div class="section">
                <div class="section-header">🔒 إعدادات الأمان</div>
                <div class="section-content">
                    <div class="grid">
                        @if(isset($security_settings) && !empty($security_settings))
                            @foreach($security_settings as $setting => $value)
                                <div class="card">
                                    <div class="card-label">{{ $setting }}</div>
                                    <div class="card-value">{{ $value ?: 'غير محدد' }}</div>
                                </div>
                            @endforeach
                        @else
                            <div class="card">
                                <div class="card-label">لا توجد إعدادات أمان متاحة</div>
                                <div class="card-value">-</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Environment Variables -->
            <div class="section">
                <div class="section-header">🌍 متغيرات البيئة</div>
                <div class="section-content">
                    <div class="grid">
                        @if(isset($environment_vars) && !empty($environment_vars))
                            @foreach($environment_vars as $var => $value)
                                <div class="card">
                                    <div class="card-label">{{ $var }}</div>
                                    <div class="card-value">{{ $value ?: 'غير محدد' }}</div>
                                </div>
                            @endforeach
                        @else
                            <div class="card">
                                <div class="card-label">لا توجد متغيرات بيئة متاحة</div>
                                <div class="card-value">-</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="/admin/file/excel-gateway" class="btn">📁 رفع ملفات Excel</a>
                <a href="/admin/dashboard" class="btn">🏠 العودة للوحة التحكم</a>
                <button onclick="window.print()" class="btn">🖨️ طباعة التقرير</button>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);

        // Copy to clipboard functionality
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('تم نسخ النص إلى الحافظة');
            });
        }

        // Add click to copy functionality to code elements
        document.querySelectorAll('.card-value').forEach(element => {
            element.addEventListener('click', function() {
                copyToClipboard(this.textContent);
            });
        });
    </script>
</body>
</html>
