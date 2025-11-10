<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page_title ?? 'Excel Upload Gateway' }}</title>
    <!-- SweetAlert2 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(-45deg, #1e3c72, #2a5298, #0f4c75, #3282b8);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='100' height='20' viewBox='0 0 100 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpolygon points='50 0 60 40 100 50 60 60 50 100 40 60 0 50 40 40'/%3E%3C/g%3E%3C/svg%3E");
            animation: floatPattern 20s linear infinite;
            pointer-events: none;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes floatPattern {
            0% { transform: translateX(-100px); }
            100% { transform: translateX(100px); }
        }

        @keyframes slideInFromTop {
            0% { opacity: 0; transform: translateY(-50px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes scaleIn {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(255, 255, 255, 0.3); }
            50% { box-shadow: 0 0 40px rgba(255, 255, 255, 0.5); }
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideInFromTop 0.8s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #3282b8 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255,255,255,0.03) 10px,
                rgba(255,255,255,0.03) 20px
            );
            animation: slidePattern 20s linear infinite;
        }

        @keyframes slidePattern {
            0% { transform: translateX(-20px) translateY(-20px); }
            100% { transform: translateX(20px) translateY(20px); }
        }

        .header h1 {
            margin: 0;
            font-size: 3em;
            font-weight: 700;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
            background: linear-gradient(45deg, #ffffff, #e8f4f8);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            position: relative;
            z-index: 2;
            font-size: 1.1em;
            margin-top: 10px;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
            animation: scaleIn 0.8s ease-out 0.2s both;
        }

        .upload-section {
            background: linear-gradient(135deg, #f8fbff 0%, #e8f4f8 100%);
            border-radius: 15px;
            padding: 40px;
            margin: 30px 0;
            border: 3px solid transparent;
            background-clip: padding-box;
            position: relative;
            animation: pulseGlow 3s ease-in-out infinite;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .upload-section::before {
            content: '';
            position: absolute;
            inset: 0;
            padding: 3px;
            background: linear-gradient(45deg, #1e3c72, #2a5298, #3282b8);
            border-radius: 15px;
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            z-index: -1;
        }

        .upload-section h3 {
            color: #1e3c72;
            font-size: 1.8em;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .upload-section p {
            color: #5a6c7d;
            font-size: 1.1em;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .file-input-wrapper {
            position: relative;
            margin: 25px 0;
        }

        .file-input-wrapper input[type="file"] {
            width: 100%;
            padding: 20px;
            border: 2px dashed #3282b8;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 1.1em;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-input-wrapper input[type="file"]:hover {
            border-color: #1e3c72;
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
        }

        .processing-options {
            background: rgba(255, 255, 255, 0.7);
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            border: 1px solid rgba(50, 130, 184, 0.2);
        }

        .processing-options label {
            display: flex;
            align-items: center;
            margin: 15px 0;
            font-size: 1.1em;
            color: #2c3e50;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .processing-options label:hover {
            color: #1e3c72;
        }

        .processing-options input[type="radio"] {
            margin-left: 12px;
            transform: scale(1.2);
            accent-color: #3282b8;
        }

        #importOptions {
            background: linear-gradient(135deg, #e8f4f8 0%, #f0f8ff 100%);
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            border: 2px solid rgba(50, 130, 184, 0.3);
            animation: scaleIn 0.5s ease-out;
        }

        #importOptions select {
            width: 100%;
            padding: 12px;
            border: 2px solid #3282b8;
            border-radius: 8px;
            font-size: 1.1em;
            background: white;
            margin: 15px 0;
        }

        .btn {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #3282b8 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 30px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            display: inline-block;
            margin: 15px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(30, 60, 114, 0.3);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(30, 60, 114, 0.4);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .button-group {
            text-align: center;
            margin: 40px 0;
            animation: slideInFromTop 0.8s ease-out 0.4s both;
        }

        .error {
            background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
            color: #c62828;
            padding: 20px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 5px solid #f44336;
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.2);
            animation: scaleIn 0.5s ease-out;
        }

        .success {
            background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%);
            color: #2e7d32;
            padding: 20px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 5px solid #4caf50;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.2);
            animation: scaleIn 0.5s ease-out;
        }

        /* Excel-themed icons and decorations */
        .excel-icon {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #107c41 0%, #0e6b37 100%);
            border-radius: 6px;
            position: relative;
            margin-left: 10px;
        }

        .excel-icon::before {
            content: 'X';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 18px;
        }

        /* Loading animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
            vertical-align: middle;
        }

        /* SweetAlert2 RTL Support */
        .rtl-popup {
            text-align: right !important;
            direction: rtl !important;
        }

        .swal2-popup {
            border-radius: 15px !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1) !important;
        }

        .swal2-title {
            font-family: 'Poppins', sans-serif !important;
            font-weight: 600 !important;
        }

        .swal2-html-container {
            font-family: 'Poppins', sans-serif !important;
            line-height: 1.6 !important;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3) !important;
        }

        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(30, 60, 114, 0.4) !important;
        }

        /* Stats Card Styles */
        .stats-card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
            animation: slideInFromTop 0.6s ease-out;
        }

        .stat-item {
            background: linear-gradient(135deg, #fff 0%, #f8fbff 100%);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .stat-item.success {
            border-color: #4caf50;
        }

        .stat-item.success .stat-icon {
            color: #4caf50;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        }

        .stat-item.duplicate {
            border-color: #ff9800;
        }

        .stat-item.duplicate .stat-icon {
            color: #ff9800;
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        }

        .stat-item.failed {
            border-color: #f44336;
        }

        .stat-item.failed .stat-icon {
            color: #f44336;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
        }

        .stat-item.total {
            border-color: #2196f3;
        }

        .stat-item.total .stat-icon {
            color: #2196f3;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            margin: 10px 0;
            background: linear-gradient(135deg, #1e3c72, #3282b8);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: #5a6c7d;
            font-size: 1.1em;
            font-weight: 500;
        }

        /* Modal Styles */
        .details-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .details-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            animation: slideInFromTop 0.4s ease-out;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #3282b8 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.8em;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-tab {
            flex: 1;
            padding: 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            color: #5a6c7d;
            transition: all 0.3s ease;
            position: relative;
        }

        .modal-tab:hover {
            background: rgba(50, 130, 184, 0.1);
        }

        .modal-tab.active {
            color: #1e3c72;
            background: white;
        }

        .modal-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #1e3c72, #3282b8);
        }

        .modal-tab .badge {
            display: inline-block;
            background: linear-gradient(135deg, #1e3c72, #3282b8);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            margin-right: 8px;
        }

        .modal-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .records-table th {
            background: linear-gradient(135deg, #f8fbff 0%, #e8f4f8 100%);
            padding: 15px;
            text-align: right;
            font-weight: 600;
            color: #1e3c72;
            border-bottom: 2px solid #3282b8;
        }

        .records-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            text-align: right;
        }

        .records-table tr:hover {
            background: #f8fbff;
        }

        .record-identifier {
            font-weight: 600;
            color: #2a5298;
            font-family: monospace;
            font-size: 1.1em;
        }

        .record-action {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .record-action.added {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .record-action.updated {
            background: #e3f2fd;
            color: #1976d2;
        }

        .error-message {
            color: #c62828;
            font-size: 0.9em;
            padding: 8px;
            background: #ffebee;
            border-radius: 5px;
            margin-top: 5px;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #9e9e9e;
            font-size: 1.2em;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Import Stats Styles - نفس تصميم الكروت الموجودة */
        .import-stats-container {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .import-stat-card {
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .import-stat-card.success-card {
            background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%);
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .import-stat-card.duplicate-card {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #e65100;
            border-left: 4px solid #ff9800;
        }

        .import-stat-card.failed-card {
            background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .import-stat-card.total-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }

        .stat-number-inline {
            font-size: 1.1em;
            font-weight: 700;
        }

        .view-details-btn-inline {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(30, 60, 114, 0.3);
        }

        .view-details-btn-inline:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Excel Upload Gateway <span class="excel-icon"></span></h1>
            <p>نظام رفع ملفات Excel المتقدم مع الربط التلقائي للبيانات</p>
        </div>

        <div class="content">
            <!-- Import Statistics Cards - في الأعلى -->
            <div class="import-stats-container">
                <div class="import-stat-card success-card">
                    ✅ ناجحة: <span class="stat-number-inline" id="successCount">0</span>
                </div>
                <div class="import-stat-card duplicate-card">
                    📋 مكررة: <span class="stat-number-inline" id="duplicateCount">0</span>
                </div>
                <div class="import-stat-card failed-card">
                    ❌ فاشلة: <span class="stat-number-inline" id="failedCount">0</span>
                </div>
                <div class="import-stat-card total-card">
                    📊 الإجمالي: <span class="stat-number-inline" id="totalCount">0</span>
                </div>
                <button onclick="showDetailsModal()" class="view-details-btn-inline" id="viewDetailsBtn" style="display: none;">
                    🔍 عرض التفاصيل
                </button>
            </div>

            <div class="upload-section">
                <h3>📊 رفع ملفات Excel <span class="excel-icon"></span></h3>
                <p>قم برفع ملفات Excel الخاصة بك مع إمكانية الربط التلقائي بين الجداول. الصيغ المدعومة: .xlsx, .xls, .csv</p>

                <form id="excelUploadForm" action="/admin/file/process-excel" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="file-input-wrapper">
                        <input type="file" name="files[]" multiple accept=".xlsx,.xls,.csv">
                    </div>

                    <div class="processing-options">
                        <label>
                            <input type="radio" name="processing_mode" value="file-only" checked>
                            💾 حفظ الملف فقط
                        </label>
                        <label>
                            <input type="radio" name="processing_mode" value="import-data">
                            🔄 حفظ الملف + استيراد البيانات مع الربط التلقائي
                        </label>
                    </div>

                    <div id="importOptions" style="display: none;">
                        <label>
                            <input type="checkbox" name="enable_excel_import" value="1">
                            ✅ تفعيل استيراد البيانات مع الربط التلقائي
                        </label>
                        <select name="target_table">
                            <option value="data">📋 جدول البيانات الرئيسي</option>
                            <option value="dead_people">⚰️ جدول المتوفين (مع ربط تلقائي)</option>
                            <option value="re_people">👥 جدول افراد الاسرة  (مع ربط تلقائي)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn">🚀 رفع الملفات</button>
                </form>
            </div>

            <div class="button-group">
                <a href="/admin/file/php-diagnostic" class="btn">🔧 تشخيص النظام</a>
                <a href="/admin/dashboard" class="btn">🏠 العودة للوحة التحكم</a>
            </div>
        </div>
    </div>

    <!-- Stats Card (Hidden by default, shown after import) -->
    <div id="statsCard" style="display: none; max-width: 900px; margin: 30px auto;">
        <div class="container">
            <div class="content">
                <h3 style="text-align: center; color: #1e3c72; margin-bottom: 20px;">📊 إحصائيات الاستيراد</h3>
                <div class="stats-card">
                    <div class="stat-item success">
                        <div class="stat-icon">✅</div>
                        <div class="stat-number" id="successCount">0</div>
                        <div class="stat-label">سجل ناجح</div>
                    </div>
                    <div class="stat-item duplicate">
                        <div class="stat-icon">📋</div>
                        <div class="stat-number" id="duplicateCount">0</div>
                        <div class="stat-label">سجل مكرر</div>
                    </div>
                    <div class="stat-item failed">
                        <div class="stat-icon">❌</div>
                        <div class="stat-number" id="failedCount">0</div>
                        <div class="stat-label">سجل فاشل</div>
                    </div>
                    <div class="stat-item total">
                        <div class="stat-icon">📄</div>
                        <div class="stat-number" id="totalCount">0</div>
                        <div class="stat-label">إجمالي السجلات</div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button class="btn" onclick="showDetailsModal()">🔍 عرض التفاصيل الكاملة</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 تفاصيل عملية الاستيراد</h3>
                <button class="modal-close" onclick="closeDetailsModal()">×</button>
            </div>
            <div class="modal-tabs">
                <button class="modal-tab active" data-tab="successful">
                    <span class="badge" id="successBadge">0</span>
                    ✅ السجلات الناجحة
                </button>
                <button class="modal-tab" data-tab="duplicates">
                    <span class="badge" id="duplicateBadge">0</span>
                    📋 السجلات المكررة
                </button>
                <button class="modal-tab" data-tab="failed">
                    <span class="badge" id="failedBadge">0</span>
                    ❌ السجلات الفاشلة
                </button>
            </div>
            <div class="modal-body">
                <div id="successfulTab" class="tab-content active">
                    <h4 style="color: #2e7d32; margin-bottom: 15px;">✅ السجلات التي تم إدخالها بنجاح</h4>
                    <div id="successfulRecords"></div>
                </div>
                <div id="duplicatesTab" class="tab-content">
                    <h4 style="color: #ff9800; margin-bottom: 15px;">📋 السجلات المكررة (موجودة مسبقاً)</h4>
                    <div id="duplicateRecords"></div>
                </div>
                <div id="failedTab" class="tab-content">
                    <h4 style="color: #c62828; margin-bottom: 15px;">❌ السجلات التي فشل إدخالها</h4>
                    <div id="failedRecords"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variable to store import results
        let importResults = null;

        // Modal functions
        function showDetailsModal() {
            document.getElementById('detailsModal').classList.add('show');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('detailsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });

        // Tab switching
        document.querySelectorAll('.modal-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');
                const tabName = this.getAttribute('data-tab');
                document.getElementById(tabName + 'Tab').classList.add('active');
            });
        });

        // Function to update stats card
        function updateStatsCard(importResult) {
            if (!importResult) return;

            // استخراج البيانات الحقيقية من import_result
            const successCount = importResult.imported_rows || 0;
            const duplicateCount = importResult.duplicate_count || 0;
            const failedCount = importResult.failed_count || 0;
            const totalCount = successCount + duplicateCount + failedCount;

            // Update numbers in inline cards
            document.getElementById('successCount').textContent = successCount;
            document.getElementById('duplicateCount').textContent = duplicateCount;
            document.getElementById('failedCount').textContent = failedCount;
            document.getElementById('totalCount').textContent = totalCount;

            // Show view details button
            const viewDetailsBtn = document.getElementById('viewDetailsBtn');
            if (viewDetailsBtn && totalCount > 0) {
                viewDetailsBtn.style.display = 'inline-block';
            }
        }

        // Function to render records table
        function renderRecordsTable(records, type) {
            if (!records || records.length === 0) {
                return '<div class="no-records">📭 لا توجد سجلات من هذا النوع</div>';
            }

            let html = '<table class="records-table">';
            html += '<thead><tr>';
            html += '<th>رقم الصف</th>';
            html += '<th>المعرف</th>';
            html += '<th>البيانات</th>';

            if (type === 'successful') {
                html += '<th>الإجراء</th>';
            } else if (type === 'duplicate') {
                html += '<th>السبب</th>';
            } else if (type === 'failed') {
                html += '<th>الخطأ</th>';
            }

            html += '</tr></thead><tbody>';

            records.forEach(record => {
                html += '<tr>';
                html += `<td><strong>#${record.row}</strong></td>`;
                html += `<td><span class="record-identifier">${record.identifier || 'غير محدد'}</span></td>`;

                // Display record data
                html += '<td>';
                if (record.data && typeof record.data === 'object') {
                    for (let [key, value] of Object.entries(record.data)) {
                        if (value) {
                            html += `<div><strong>${key}:</strong> ${value}</div>`;
                        }
                    }
                } else {
                    html += 'لا توجد بيانات';
                }
                html += '</td>';

                // Add action/reason/error column
                if (type === 'successful') {
                    const actionClass = record.action === 'مضاف' ? 'added' : 'updated';
                    html += `<td><span class="record-action ${actionClass}">${record.action || 'مضاف'}</span></td>`;
                } else if (type === 'duplicate') {
                    html += `<td><small>${record.reason || 'سجل موجود مسبقاً'}</small></td>`;
                } else if (type === 'failed') {
                    html += `<td><div class="error-message">${record.error || 'خطأ غير معروف'}</div>`;
                    if (record.sql_code) {
                        html += `<small style="color: #666;">كود الخطأ: ${record.sql_code}</small>`;
                    }
                    html += '</td>';
                }

                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        }

        // Function to populate modal with data
        function populateModal(importResult) {
            if (!importResult) return;

            importResults = importResult;

            // Populate successful records
            document.getElementById('successfulRecords').innerHTML =
                renderRecordsTable(importResult.successful_records, 'successful');

            // Populate duplicate records
            document.getElementById('duplicateRecords').innerHTML =
                renderRecordsTable(importResult.duplicates, 'duplicate');

            // Populate failed records
            document.getElementById('failedRecords').innerHTML =
                renderRecordsTable(importResult.failed_records, 'failed');
        }

        // SweetAlert configuration for RTL
        Swal.mixin({
            customClass: {
                confirmButton: 'btn',
                cancelButton: 'btn',
                popup: 'rtl-popup'
            },
            buttonsStyling: false,
            reverseButtons: true
        });        // Check for any server-side messages (fallback)
        document.addEventListener('DOMContentLoaded', function() {
            @if(isset($error))
                Swal.fire({
                    icon: 'error',
                    title: '❌ خطأ في العملية',
                    html: `
                        <div style="text-align: right; direction: rtl;">
                            <h4>تفاصيل الخطأ:</h4>
                            <p>{{ $error }}</p>
                        </div>
                    `,
                    confirmButtonText: 'حسناً',
                    background: '#fff',
                    color: '#c62828',
                    showConfirmButton: true,
                    timer: false
                });
            @endif

            @if(session('warning'))
                Swal.fire({
                    icon: 'warning',
                    title: '⚠️ تحذير',
                    html: `
                        <div style="text-align: right; direction: rtl;">
                            <p>{{ session('warning') }}</p>
                        </div>
                    `,
                    confirmButtonText: 'فهمت',
                    background: '#fff',
                    color: '#ff9800',
                    showConfirmButton: true
                });
            @endif
        });

        // Show/hide import options based on processing mode
        document.querySelectorAll('input[name="processing_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const importOptions = document.getElementById('importOptions');
                if (this.value === 'import-data') {
                    importOptions.style.display = 'block';
                    importOptions.style.animation = 'scaleIn 0.5s ease-out';
                } else {
                    importOptions.style.display = 'none';
                }
            });
        });        // Handle form submission with Ajax and SweetAlert
        document.getElementById('excelUploadForm').addEventListener('submit', function(e) {
            e.preventDefault(); // منع الإرسال العادي للنموذج

            const fileInput = this.querySelector('input[type="file"]');
            if (!fileInput.files.length) {
                Swal.fire({
                    icon: 'warning',
                    title: '⚠️ لم تختر ملف',
                    html: `
                        <div style="text-align: right; direction: rtl;">
                            <p>يرجى اختيار ملف واحد على الأقل قبل المتابعة</p>
                            <p>الصيغ المدعومة: .xlsx, .xls, .csv</p>
                        </div>
                    `,
                    confirmButtonText: 'حسناً',
                    background: '#fff',
                    color: '#ff9800'
                });
                return;
            }

            // إعداد بيانات النموذج
            const formData = new FormData(this);
            const processingMode = this.querySelector('input[name="processing_mode"]:checked').value;
            const targetTable = this.querySelector('select[name="target_table"]')?.value || 'غير محدد';
            const importEnabled = this.querySelector('input[name="enable_excel_import"]')?.checked || false;

            let processingText = processingMode === 'file-only' ? 'حفظ الملف فقط' : 'حفظ الملف + استيراد البيانات';

            // عرض رسالة المعالجة
            Swal.fire({
                icon: 'info',
                title: '⏳ جاري المعالجة...',
                html: `
                    <div style="text-align: right; direction: rtl;">
                        <p><strong>🔄 وضع المعالجة:</strong> ${processingText}</p>
                        ${processingMode === 'import-data' ? `<p><strong>🗃️ الجدول المستهدف:</strong> ${targetTable}</p>` : ''}
                        ${processingMode === 'import-data' ? `<p><strong>📊 الاستيراد:</strong> ${importEnabled ? 'مفعل' : 'غير مفعل'}</p>` : ''}
                        <p><strong>📁 عدد الملفات:</strong> ${fileInput.files.length}</p>
                        <div style="margin: 15px 0;">
                            <div class="loading-spinner" style="margin: 0 auto;"></div>
                            <p style="margin-top: 10px;">يرجى الانتظار...</p>
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                background: '#fff',
                color: '#1e3c72'
            });

            // تعطيل النموذج أثناء المعالجة
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '⏳ جاري الرفع... <span class="loading-spinner"></span>';
            submitBtn.disabled = true;
            this.style.opacity = '0.7';
            this.style.pointerEvents = 'none';

            // إرسال الطلب باستخدام Ajax
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('📊 Server Response:', data); // Debug log

                // إعادة تفعيل النموذج
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                this.style.opacity = '1';
                this.style.pointerEvents = 'auto';

                if (data.success) {
                    // استخراج البيانات الحقيقية من import_result
                    let importedRows = 0;
                    let duplicateRows = 0;
                    let failedRows = 0;
                    let hasImportResult = false;

                    if (data.files && data.files.length > 0) {
                        const firstFile = data.files[0];
                        console.log('📁 First File:', firstFile); // Debug log

                        if (firstFile.import_result) {
                            hasImportResult = true;
                            importedRows = firstFile.import_result.imported_rows || 0;
                            duplicateRows = firstFile.import_result.duplicate_count || 0;
                            failedRows = firstFile.import_result.failed_count || 0;

                            console.log('✅ Import Result:', {
                                imported: importedRows,
                                duplicates: duplicateRows,
                                failed: failedRows
                            }); // Debug log

                            // تحديث الكروت بالبيانات الحقيقية
                            updateStatsCard(firstFile.import_result);
                            populateModal(firstFile.import_result);
                        }
                    }

                    // إذا لم يكن هناك بيانات استيراد، استخدم import_summary
                    if (!hasImportResult && data.import_summary) {
                        importedRows = data.import_summary.imported_rows || 0;
                        duplicateRows = data.import_summary.duplicate_rows || 0;
                        failedRows = data.import_summary.failed_rows || 0;

                        // تحديث الكروت من import_summary
                        document.getElementById('successCount').textContent = importedRows;
                        document.getElementById('duplicateCount').textContent = duplicateRows;
                        document.getElementById('failedCount').textContent = failedRows;
                        document.getElementById('totalCount').textContent = importedRows + duplicateRows + failedRows;
                    }

                    // تحديد نوع الرسالة بناءً على النتائج الفعلية الحقيقية
                    const totalProcessed = importedRows + duplicateRows + failedRows;
                    const allFailed = totalProcessed > 0 && importedRows === 0 && failedRows > 0;
                    const allDuplicates = totalProcessed > 0 && importedRows === 0 && duplicateRows > 0 && failedRows === 0;
                    const partialSuccess = importedRows > 0 && (failedRows > 0 || duplicateRows > 0);
                    const fullSuccess = importedRows > 0 && failedRows === 0;
                    const fileOnlyMode = data.processing_mode === 'file-only';

                    let alertIcon = 'success';
                    let alertTitle = '🎉 نجحت العملية';
                    let alertColor = '#2e7d32';

                    // منطق صادق 100% بناءً على البيانات الحقيقية
                    if (allFailed) {
                        alertIcon = 'error';
                        alertTitle = '❌ فشل الاستيراد - جميع السجلات فشلت';
                        alertColor = '#c62828';
                    } else if (allDuplicates) {
                        alertIcon = 'warning';
                        alertTitle = '⚠️ جميع السجلات مكررة - لم يتم إدخال أي سجل جديد';
                        alertColor = '#ff9800';
                    } else if (partialSuccess) {
                        alertIcon = 'warning';
                        alertTitle = `⚠️ نجحت جزئياً - تم إدخال ${importedRows} من ${totalProcessed} سجل`;
                        alertColor = '#ff9800';
                    } else if (fullSuccess && totalProcessed > 0) {
                        alertIcon = 'success';
                        alertTitle = `✅ نجحت تماماً - تم إدخال ${importedRows} سجل بنجاح`;
                        alertColor = '#2e7d32';
                    } else if (fileOnlyMode) {
                        alertIcon = 'info';
                        alertTitle = '📁 تم حفظ الملف فقط';
                        alertColor = '#2196f3';
                    }

                    // عرض رسالة صادقة باستخدام SweetAlert
                    let responseHtml = `
                        <div style="text-align: right; direction: rtl; font-family: 'Poppins', sans-serif;">
                            <h4 style="color: ${alertColor}; margin-bottom: 15px;">${alertTitle}</h4>
                            <div style="background: ${allFailed ? '#ffebee' : (allDuplicates ? '#fff3e0' : '#f1f8e9')}; padding: 15px; border-radius: 8px; margin: 10px 0;">
                                <p><strong>📊 الرسالة:</strong> ${data.message}</p>
                    `;

                    // عرض ملخص الاستيراد إذا كان موجوداً
                    if (totalProcessed > 0) {
                        const summaryColor = allFailed ? '#f44336' : (allDuplicates ? '#ff9800' : (fullSuccess ? '#4caf50' : '#ff9800'));
                        const summaryBg = allFailed ? '#ffebee' : (allDuplicates ? '#fff3e0' : (fullSuccess ? '#e8f5e9' : '#fff8e1'));

                        responseHtml += `
                            <div style="background: ${summaryBg}; padding: 15px; margin: 15px 0; border-radius: 8px; border-right: 4px solid ${summaryColor};">
                                <h5 style="color: #1e3c72; margin-bottom: 10px;">📈 النتائج الفعلية للاستيراد:</h5>
                                <p style="font-size: 1.1em;"><strong>✅ سجلات نجحت:</strong> <span style="color: #4caf50; font-weight: bold;">${importedRows}</span></p>
                                <p style="font-size: 1.1em;"><strong>📋 سجلات مكررة (تم تجاهلها):</strong> <span style="color: #ff9800; font-weight: bold;">${duplicateRows}</span></p>
                                <p style="font-size: 1.1em;"><strong>❌ سجلات فشلت:</strong> <span style="color: #f44336; font-weight: bold;">${failedRows}</span></p>
                                <p style="font-size: 1.1em;"><strong>� الإجمالي:</strong> <span style="font-weight: bold;">${totalProcessed}</span></p>
                                ${data.import_summary?.target_table ? `<p style="margin-top: 10px;"><strong>�🗃️ الجدول المستهدف:</strong> ${data.import_summary.target_table}</p>` : ''}
                            </div>
                        `;

                        // إضافة زر لعرض التفاصيل
                        if (totalProcessed > 0) {
                            responseHtml += `
                                <div style="text-align: center; margin: 15px 0;">
                                    <button onclick="showDetailsModal()" style="background: #1e3c72; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 1.1em; transition: all 0.3s ease;">
                                        🔍 عرض التفاصيل الكاملة لجميع السجلات
                                    </button>
                                </div>
                            `;
                        }
                    }

                    // عرض معلومات الملفات
                    if (data.files && Array.isArray(data.files)) {
                        responseHtml += `<h5 style="margin: 15px 0 10px 0;">📁 الملفات المعالجة:</h5>`;
                        data.files.forEach(file => {
                            const fileSuccess = file.success && (file.import_result ? file.import_result.imported_rows > 0 : true);
                            const statusIcon = fileSuccess ? '✅' : '❌';
                            const statusColor = fileSuccess ? '#4caf50' : '#f44336';
                            const statusText = fileSuccess ? 'نجحت' : 'فشلت';

                            responseHtml += `
                                <div style="background: white; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid ${statusColor};">
                                    <p><strong>📄 اسم الملف:</strong> ${file.original_name || 'غير محدد'}</p>
                                    <p><strong>📊 حالة العملية:</strong> ${statusIcon} ${statusText}</p>
                                    ${file.message ? `<p><strong>💬 التفاصيل:</strong> ${file.message}</p>` : ''}
                                    ${file.file_size ? `<p><strong>📏 حجم الملف:</strong> ${(file.file_size / 1024).toFixed(2)} KB</p>` : ''}
                                </div>
                            `;
                        });
                    }

                    if (data.processing_mode) {
                        const modeText = data.processing_mode === 'file-only' ? '💾 حفظ الملف فقط (بدون استيراد)' : '🔄 حفظ + استيراد البيانات';
                        responseHtml += `<p style="margin-top: 10px;"><strong>⚙️ وضع المعالجة:</strong> ${modeText}</p>`;
                    }

                    responseHtml += `</div></div>`;

                    Swal.fire({
                        icon: alertIcon,
                        title: alertTitle,
                        html: responseHtml,
                        confirmButtonText: 'حسناً، فهمت',
                        background: '#fff',
                        color: alertColor,
                        showConfirmButton: true,
                        width: '750px',
                        timer: false,
                        allowOutsideClick: false
                    });

                    // إعادة تعيين النموذج فقط إذا نجحت العملية فعلاً
                    if (!allFailed) {
                        this.reset();
                        document.getElementById('importOptions').style.display = 'none';
                    }

                } else {
                    // عرض رسالة الخطأ
                    let errorHtml = `
                        <div style="text-align: right; direction: rtl;">
                            <h4>تفاصيل الخطأ:</h4>
                            <p>${data.message}</p>
                    `;

                    if (data.errors && Array.isArray(data.errors)) {
                        errorHtml += `<ul style="text-align: right;">`;
                        data.errors.forEach(error => {
                            errorHtml += `<li>${error}</li>`;
                        });
                        errorHtml += `</ul>`;
                    }

                    errorHtml += `</div>`;

                    Swal.fire({
                        icon: 'error',
                        title: '❌ خطأ في العملية',
                        html: errorHtml,
                        confirmButtonText: 'حسناً',
                        background: '#fff',
                        color: '#c62828',
                        showConfirmButton: true,
                        timer: false
                    });
                }
            })
            .catch(error => {
                // إعادة تفعيل النموذج في حالة الخطأ
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                this.style.opacity = '1';
                this.style.pointerEvents = 'auto';

                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: '❌ خطأ في الاتصال',
                    html: `
                        <div style="text-align: right; direction: rtl;">
                            <p>حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.</p>
                            <p style="color: #666; font-size: 0.9em;">تفاصيل الخطأ: ${error.message}</p>
                        </div>
                    `,
                    confirmButtonText: 'حسناً',
                    background: '#fff',
                    color: '#c62828'
                });
            });
        });

        // Add hover effects to file input
        const fileInput = document.querySelector('input[type="file"]');
        fileInput.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#1e3c72';
            this.style.backgroundColor = 'rgba(30, 60, 114, 0.1)';
        });

        fileInput.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#3282b8';
            this.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
        });

        fileInput.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#3282b8';
            this.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
        });

        // Add file selection feedback
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                let fileList = Array.from(this.files).map(file => `
                    📄 ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                `).join('<br>');

                Swal.fire({
                    icon: 'info',
                    title: '📁 تم اختيار الملفات',
                    html: `
                        <div style="text-align: right; direction: rtl;">
                            <p><strong>عدد الملفات المختارة:</strong> ${this.files.length}</p>
                            <div style="background: #f0f8ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                                ${fileList}
                            </div>
                            <p style="color: #666;">يمكنك الآن اختيار وضع المعالجة والمتابعة</p>
                        </div>
                    `,
                    confirmButtonText: 'فهمت',
                    background: '#fff',
                    color: '#1e3c72',
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
    </script>
</body>
</html>
