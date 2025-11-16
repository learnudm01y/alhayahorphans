@extends('admin.dashboard.toolbars.index')

@push('styles')
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* تصميم بسيط ومصغر */
        .excel-gateway-wrapper {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            width: 100%;
            max-width: 900px;
            margin: 15px auto;
            padding: 0;
        }

        .excel-gateway-wrapper .container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .excel-gateway-wrapper .header {
            background: #2196f3;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }

        .excel-gateway-wrapper .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .excel-gateway-wrapper .header p {
            font-size: 13px;
            margin: 5px 0 0 0;
            opacity: 0.95;
        }

        .excel-gateway-wrapper .content {
            padding: 20px;
        }

        .excel-gateway-wrapper .upload-section {
            background: #f9f9f9;
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 25px;
            margin: 15px 0;
            text-align: center;
        }

        animation: pulseGlow 3s ease-in-out infinite;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .excel-gateway-wrapper .upload-section::before {
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

        .excel-gateway-wrapper .upload-section h3 {
            color: #1e3c72;
            font-size: 1.8em;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .excel-gateway-wrapper .upload-section p {
            color: #5a6c7d;
            font-size: 1.1em;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .excel-gateway-wrapper .file-input-wrapper {
            position: relative;
            margin: 25px 0;
        }

        .excel-gateway-wrapper .file-input-wrapper input[type="file"] {
            width: 100%;
            padding: 20px;
            border: 2px dashed #3282b8;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 1.1em;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .excel-gateway-wrapper .file-input-wrapper input[type="file"]:hover {
            border-color: #1e3c72;
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
        }

        .excel-gateway-wrapper .processing-options {
            background: rgba(255, 255, 255, 0.7);
            padding-right: 6px;
            padding-left: 6px;
            border-radius: 12px;
            margin: 25px 0;
            border: 1px solid rgba(50, 130, 184, 0.2);
        }

        .excel-gateway-wrapper .processing-options label {
            display: flex;
            align-items: center;
            margin: 15px 0;
            font-size: 1.1em;
            color: #2c3e50;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .excel-gateway-wrapper .processing-options label:hover {
            color: #1e3c72;
        }

        .excel-gateway-wrapper .processing-options input[type="radio"] {
            margin-left: 12px;
            transform: scale(1.2);
            accent-color: #3282b8;
        }

        .excel-gateway-wrapper #importOptions {
            background: linear-gradient(135deg, #e8f4f8 0%, #f0f8ff 100%);
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            border: 2px solid rgba(50, 130, 184, 0.3);
            animation: scaleIn 0.5s ease-out;
        }

        .excel-gateway-wrapper .upload-section h2,
        .excel-gateway-wrapper .upload-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .excel-gateway-wrapper .upload-section p {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }

        /* تحسين حقل رفع الملف */
        .file-input-wrapper input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #2196f3;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-input-wrapper input[type="file"]:hover {
            background: #f5f5f5;
            border-color: #1976d2;
        }

        /* تحسين الخيارات */
        .processing-options label:hover {
            background: #e3f2fd !important;
            border-color: #2196f3 !important;
        }

        .processing-options input[type="radio"]:checked+span {
            color: #2196f3;
            font-weight: 600;
        }

        .processing-options label:has(input:checked) {
            background: #e3f2fd !important;
            border-color: #2196f3 !important;
        }

        .excel-gateway-wrapper .btn {
            background: #2196f3;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .excel-gateway-wrapper .btn:hover {
            background: #1976d2;
        }

        .excel-gateway-wrapper .button-group {
            text-align: center;
            margin: 20px 0;
        }

        .excel-gateway-wrapper .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 3px solid #f44336;
            font-size: 13px;
        }

        .excel-gateway-wrapper .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 3px solid #4caf50;
            font-size: 13px;
        }

        /* Loading animation - معزولة */
        .excel-gateway-wrapper .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;

            /* Loading Spinner - بسيط */
            .loading-spinner {
                display: inline-block;
                width: 24px;
                height: 24px;
                border: 3px solid rgba(33, 150, 243, 0.3);
                border-radius: 50%;
                border-top-color: #2196f3;
                animation: spin 1s ease-in-out infinite;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* Import Stats - مصغر */
            .import-stats-container {
                display: flex;
                gap: 10px;
                margin: 15px 0;
                flex-wrap: wrap;
            }

            .import-stat-card {
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 600;
                border-left: 3px solid;
            }

            .import-stat-card.success-card {
                background: #e8f5e9;
                color: #2e7d32;
                border-color: #4caf50;
            }

            .import-stat-card.duplicate-card {
                background: #fff3e0;
                color: #e65100;
                border-color: #ff9800;
            }

            .import-stat-card.failed-card {
                background: #ffebee;
                color: #c62828;
                border-color: #f44336;
            }

            .import-stat-card.total-card {
                background: #e3f2fd;
                color: #1565c0;
                border-color: #2196f3;
            }

            .stat-number-inline {
                font-weight: 700;
            }

            .view-details-btn-inline {
                background: #2196f3;
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 12px;
                cursor: pointer;
            }

            .view-details-btn-inline:hover {
                background: #1976d2;
            }

            /* Modal Styles - معزول بالكامل */
            .modal {
                display: none;
                position: fixed;
                z-index: 99999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.7);
                overflow: hidden;
                /* منع scroll الخلفية */
            }

            .modal.show {
                display: flex !important;
                align-items: center;
                justify-content: center;
            }

            /* منع التفاعل مع العناصر خلف المودال */
            body.modal-open {
                overflow: hidden !important;
                position: fixed;
                width: 100%;
                height: 100%;
            }

            .validation-modal-content {
                background: white;
                border-radius: 6px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
                overflow: hidden;
                display: flex;
                flex-direction: column;
                position: relative;
                z-index: 100000;
                width: 90%;
                max-width: 1200px;
                max-height: 90vh;
                margin: auto;
            }

            /* Responsive Modal Sizing */
            @media (min-width: 1920px) {
                .validation-modal-content {
                    width: 70%;
                    max-width: 1600px;
                }
            }

            @media (min-width: 1400px) and (max-width: 1919px) {
                .validation-modal-content {
                    width: 75%;
                    max-width: 1400px;
                }
            }

            @media (min-width: 1200px) and (max-width: 1399px) {
                .validation-modal-content {
                    width: 85%;
                    max-width: 1200px;
                }
            }

            @media (min-width: 992px) and (max-width: 1199px) {
                .validation-modal-content {
                    width: 90%;
                    max-width: 1000px;
                }
            }

            @media (min-width: 768px) and (max-width: 991px) {
                .validation-modal-content {
                    width: 92%;
                    max-width: 750px;
                }
            }

            @media (max-width: 767px) {
                .validation-modal-content {
                    width: 95%;
                    max-width: 100%;
                    max-height: 95vh;
                    border-radius: 4px;
                }
            }

            @media (max-width: 480px) {
                .validation-modal-content {
                    width: 98%;
                    max-height: 98vh;
                    border-radius: 2px;
                }
            }

            /* Custom Scrollbar */
            #warningsList::-webkit-scrollbar,
            #invalidRowsList::-webkit-scrollbar,
            #failedRowsList::-webkit-scrollbar,
            #validationInvalidList::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }

            #warningsList::-webkit-scrollbar-thumb,
            #invalidRowsList::-webkit-scrollbar-thumb,
            #failedRowsList::-webkit-scrollbar-thumb,
            #validationInvalidList::-webkit-scrollbar-thumb {
                background: #bbb;
                border-radius: 4px;
            }

            #warningsList::-webkit-scrollbar-thumb:hover,
            #invalidRowsList::-webkit-scrollbar-thumb:hover,
            #failedRowsList::-webkit-scrollbar-thumb:hover,
            #validationInvalidList::-webkit-scrollbar-thumb:hover {
                background: #999;
            }

            #warningsList::-webkit-scrollbar-track,
            #invalidRowsList::-webkit-scrollbar-track,
            #failedRowsList::-webkit-scrollbar-track,
            #validationInvalidList::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            /* ضمان عمل السكرول في منطقة الأخطاء */
            #failedRowsList,
            #validationInvalidList {
                overflow-y: auto !important;
                overflow-x: hidden;
                scroll-behavior: smooth;
                -webkit-overflow-scrolling: touch;
            }

            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
            padding: 10px;
            background: #fafafa;
            border-radius: 8px;
        }

        .invalid-row-item {
            padding: 10px;
            margin-bottom: 8px;
            background: white;
            border-radius: 6px;
            border-right: 3px solid #f44336;
        }

        .invalid-row-item .row-number {
            font-weight: 700;
            color: #c62828;
            margin-bottom: 5px;
        }

        .invalid-row-item .error-detail {
            font-size: 0.9em;
            color: #666;
            margin-right: 15px;
        }

        .validation-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .validation-actions .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .validation-actions .btn-secondary {
            background: #757575;
            color: white;
        }

        .validation-actions .btn-secondary:hover {
            background: #616161;
        }

        /* Details Modal - معزول بالكامل */
        .details-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 99999;
            overflow: hidden;
            /* منع scroll الخلفية */
        }

        .modal-content {
            background: white;
            max-width: 1000px;
            width: 90%;
            max-height: 85vh;
            margin: auto;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 100000;
        }

        /* Responsive Modal Sizing for Details Modal */
        @media (min-width: 1920px) {
            .modal-content {
                width: 70%;
                max-width: 1600px;
            }
        }

        @media (min-width: 1400px) and (max-width: 1919px) {
            .modal-content {
                width: 75%;
                max-width: 1400px;
            }
        }

        @media (min-width: 1200px) and (max-width: 1399px) {
            .modal-content {
                width: 85%;
                max-width: 1200px;
            }
        }

        @media (min-width: 992px) and (max-width: 1199px) {
            .modal-content {
                width: 90%;
                max-width: 1000px;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .modal-content {
                width: 92%;
                max-width: 750px;
            }
        }

        @media (max-width: 767px) {
            .modal-content {
                width: 95%;
                max-width: 100%;
                max-height: 95vh;
                border-radius: 4px;
            }
        }

        @media (max-width: 480px) {
            .modal-content {
                width: 98%;
                max-height: 98vh;
                border-radius: 2px;
                margin: 5px auto;
            }
        }

        .modal-header {
            background: #2196f3;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .close-modal {
            background: transparent;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
        }

        .modal-tabs {
            display: flex;
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }

        .modal-tab {
            flex: 1;
            padding: 12px 16px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .modal-tab:hover {
            background: rgba(33, 150, 243, 0.1);
        }

        .modal-tab.active {
            background: white;
            color: #2196f3;
            border-bottom: 2px solid #2196f3;
        }

        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .records-table th {
            background: #f5f5f5;
            padding: 10px;
            text-align: right;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }

        .records-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: right;
        }

        .records-table tr:hover {
            background: #fafafa;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge.duplicate {
            background: #fff3e0;
            color: #e65100;
        }

        .badge.failed {
            background: #ffebee;
            color: #c62828;
        }

        .no-records {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 14px;
        }

        .record-preview {
            background: #f9f9f9;
            padding: 8px;
            border-radius: 3px;
            font-size: 12px;
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
@endpush

@section('content')
    <div class="excel-gateway-wrapper">
        <div class="container">
            <div class="content">
                <!-- Remove old duplicate detection controls -->
                <div class="upload-section">
                    <h3>📊 رفع ملفات Excel</h3>
                    <p>قم برفع ملفات Excel الخاصة بك مع إمكانية الربط التلقائي بين الجداول. الصيغ المدعومة: .xlsx, .xls,
                        .csv</p>

                    <form id="excelUploadForm" action="/admin/file/process-excel" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <!-- صف واحد: حقل الرفع + الخيارات -->
                        <div style="display: flex; gap: 15px; align-items: flex-start; margin: 20px 0;">
                            <!-- حقل رفع الملف - 40% -->
                            <div style="flex: 0 0 40%;">
                                <div class="file-input-wrapper">
                                    <input type="file" name="files[]" multiple accept=".xlsx,.xls,.csv"
                                        style="font-size: 12px; padding: 30px;">
                                </div>
                            </div>

                            <!-- الخيارات - 55% - صف واحد أفقي -->
                            <div style="flex: 0 0 55%;">
                                <div class="processing-options" style="display: flex; gap: 10px;">
                                    <label
                                        style="flex: 1; display: flex; align-items: center; padding: 8px 12px; background: #f9f9f9; border-radius: 4px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;">
                                        <input type="radio" name="processing_mode" value="file-only" checked
                                            style="margin-left: 6px;">
                                        <span style="font-size: 13px; font-weight: 500;">💾 حفظ الملف فقط</span>
                                    </label>
                                    <label
                                        style="flex: 1; display: flex; align-items: center; padding: 8px 12px; background: #f9f9f9; border-radius: 4px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;">
                                        <input type="radio" name="processing_mode" value="import-data"
                                            style="margin-left: 6px;">
                                        <span style="font-size: 13px; font-weight: 500;">🔄 حفظ الملف + استيراد البيانات مع
                                            الربط التلقائي</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div id="importOptions" style="display: none; margin-top: 15px;">
                            <label style="display: block; margin-bottom: 10px; font-size: 13px;">
                                <input type="checkbox" name="enable_excel_import" value="1" checked>
                                ✅ تفعيل استيراد البيانات مع الربط التلقائي
                            </label>
                            <select name="target_table"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                                <option value="data">📋 جدول البيانات الرئيسي</option>
                                <option value="dead_people">⚰️ جدول المتوفين (مع ربط تلقائي)</option>
                                <option value="re_people">👥 جدول افراد الاسرة (مع ربط تلقائي)</option>
                            </select>
                        </div>

                    <div style="display: flex; gap: 12px; margin-top: 20px; justify-content: center;">
                        <button type="submit" class="btn" style="flex: 0 0 200px; margin: 0;">🚀 رفع الملفات</button>
                        <a href="/admin/file/php-diagnostic" class="btn" style="flex: 0 0 200px; margin: 0; display: flex; align-items: center; justify-content: center; text-decoration: none;">🔧 تشخيص النظام</a>
                    </div>
                    </form>
                </div>
                <!-- Details Modal -->
                <div id="detailsModal" class="details-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>📊 تفاصيل عملية الاستيراد</h2>
                            <button onclick="closeDetailsModal()" class="close-modal">&times;</button>
                        </div>

                        <div class="modal-tabs">
                            <button class="modal-tab active" data-tab="successful">✅ السجلات الناجحة</button>
                            <button class="modal-tab" data-tab="duplicates">📋 السجلات المكررة</button>
                            <button class="modal-tab" data-tab="failed">❌ السجلات الفاشلة</button>
                        </div>

                        <div class="modal-body">
                            <div id="tab-successful" class="tab-content active">
                                <!-- Will be populated by JavaScript -->
                            </div>
                            <div id="tab-duplicates" class="tab-content">
                                <!-- Will be populated by JavaScript -->
                            </div>
                            <div id="tab-failed" class="tab-content">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validation Results Modal - نافذة نتائج التحقق من الملف -->
                <div id="validationModal" class="modal">
                    <div class="modal-content validation-modal-content"
                        style="max-width: 900px; height: 90vh; max-height: 600px;">
                        <!-- Header - مبسط جداً -->
                        <div
                            style="padding: 12px 20px; background: #f5f5f5; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 15px; font-weight: 600;">🔍 نتائج الفحص</span>
                            <button onclick="closeValidationModal()"
                                style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
                        </div>

                        <!-- Body - Layout جانبي -->
                        <div style="display: flex; height: calc(100% - 110px); overflow: hidden;">
                            <!-- Main Content - 60% -->
                            <div
                                style="flex: 0 0 60%; padding: 15px; overflow: hidden; border-left: 1px solid #ddd; display: flex; flex-direction: column;">
                                <!-- Statistics - مصغرة -->
                                <div
                                    style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 15px;">
                                    <div style="background: #e3f2fd; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">إجمالي</div>
                                        <div style="font-size: 18px; font-weight: 600;" id="val-total-rows">0</div>
                                    </div>
                                    <div style="background: #e8f5e9; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">صحيحة</div>
                                        <div style="font-size: 18px; font-weight: 600; color: #4caf50;" id="val-valid-rows">
                                            0</div>
                                    </div>
                                    <div style="background: #ffebee; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">خاطئة</div>
                                        <div style="font-size: 18px; font-weight: 600; color: #f44336;"
                                            id="val-invalid-rows">0</div>
                                    </div>
                                    <div style="background: #fff3e0; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">تحذيرات</div>
                                        <div style="font-size: 18px; font-weight: 600; color: #ff9800;" id="val-warnings">
                                            0</div>
                                    </div>
                                </div>

                                <!-- Message -->
                                <div id="validationMessage"
                                    style="padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 15px;"></div>

                                <!-- Invalid Rows with Pagination -->
                                <div id="invalidRowsContainer"
                                    style="display: none; flex: 1; overflow: hidden; display: flex; flex-direction: column;">
                                    <h5 style="font-size: 13px; margin: 0 0 8px 0; color: #f44336;">❌ الصفوف الخاطئة:</h5>
                                    <div id="invalidRowsList"
                                        style="flex: 1; overflow-y: auto; font-size: 12px; margin-bottom: 8px;"></div>

                                    <!-- Pagination Controls for Errors -->
                                    <div id="errorsPagination"
                                        style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-top: 1px solid #ddd; font-size: 11px;">
                                        <button onclick="prevErrorsPage()" id="prevErrorsBtn"
                                            style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">السابق</button>
                                        <span id="errorsPaginationInfo" style="color: #666;">صفحة 1 من 1</span>
                                        <button onclick="nextErrorsPage()" id="nextErrorsBtn"
                                            style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">التالي</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Warnings Sidebar - 40% -->
                            <div id="warningsSidebar"
                                style="flex: 0 0 40%; padding: 15px; overflow: hidden; display: flex; flex-direction: column; background: #fafafa;">
                                <h5 style="font-size: 13px; margin: 0 0 10px 0; color: #ff9800;">⚠️ التحذيرات</h5>

                                <!-- Warnings List with Pagination -->
                                <div id="warningsList"
                                    style="flex: 1; overflow-y: auto; margin-bottom: 10px; font-size: 11px;">
                                    <!-- سيتم ملؤها بـ JavaScript -->
                                </div>

                                <!-- Pagination Controls -->
                                <div id="warningsPagination"
                                    style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-top: 1px solid #ddd; font-size: 11px;">
                                    <button onclick="prevWarningsPage()" class="prev-btn"
                                        style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">السابق</button>
                                    <span class="page-info" style="color: #666;">صفحة 1 من 1</span>
                                    <button onclick="nextWarningsPage()" class="next-btn"
                                        style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">التالي</button>
                                </div>
                            </div>
                        </div>

                        <!-- Footer - Action Buttons -->
                        <div
                            style="padding: 12px 20px; background: #f5f5f5; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px;">
                            <button onclick="closeValidationModal()"
                                style="padding: 6px 16px; background: #9e9e9e; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                إلغاء
                            </button>
                            <button onclick="proceedWithImport()" id="proceedImportBtn"
                                style="display: none; padding: 6px 16px; background: #4caf50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                ✅ متابعة الإدخال
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Import Results Modal - نافذة نتائج الإدخال -->
                <div id="importResultsModal" class="modal" onclick="event.target === this && closeImportResultsModal()">
                    <div class="modal-content validation-modal-content" onclick="event.stopPropagation()"
                        style="max-width: 900px; height: 90vh; max-height: 600px;">
                        <!-- Header -->
                        <div style="padding: 12px 20px; background: #f5f5f5; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 15px; font-weight: 600;" id="import-modal-title">📊 نتائج الإدخال</span>
                            <button onclick="closeImportResultsModal()"
                                style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
                        </div>

                        <!-- Body - Layout جانبي -->
                        <div style="display: flex; height: calc(100% - 110px); overflow: hidden;">
                            <!-- Main Content - 60% -->
                            <div style="flex: 0 0 60%; padding: 15px; overflow: hidden; border-left: 1px solid #ddd; display: flex; flex-direction: column;">
                                <!-- Statistics -->
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 15px; flex-shrink: 0;">
                                    <div style="background: #e3f2fd; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">إجمالي</div>
                                        <div style="font-size: 18px; font-weight: 600;" id="imp-total-rows">0</div>
                                    </div>
                                    <div style="background: #e8f5e9; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">تم إدخالها</div>
                                        <div style="font-size: 18px; font-weight: 600; color: #4caf50;" id="imp-success-rows">0</div>
                                    </div>
                                    <div style="background: #ffebee; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">فشلت</div>
                                        <div style="font-size: 18px; font-weight: 600; color: #f44336;" id="imp-failed-rows">0</div>
                                    </div>
                                    <div style="background: #fff3e0; padding: 8px; border-radius: 4px; text-align: center;">
                                        <div style="font-size: 11px; color: #666;">مكررة</div>
                                        <div style="font-size: 18px; font-weight: 600; color: #ff9800;" id="imp-duplicate-rows">0</div>
                                    </div>
                                </div>

                                <!-- Message -->
                                <div id="importResultMessage" style="padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 15px; flex-shrink: 0;"></div>

                                <!-- Failed Rows with Details -->
                                <div id="failedRowsContainer" style="display: none; flex: 1; min-height: 0; display: flex; flex-direction: column;">
                                    <h5 style="font-size: 13px; margin: 0 0 8px 0; color: #f44336; flex-shrink: 0;">❌ الصفوف الفاشلة:</h5>
                                    <div id="failedRowsList" style="flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; font-size: 12px; margin-bottom: 8px;"></div>

                                    <!-- Pagination Controls -->
                                    <div id="failedPagination"
                                        style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-top: 1px solid #ddd; font-size: 11px; flex-shrink: 0;">
                                        <button onclick="prevFailedPage()" id="prevFailedBtn"
                                            style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">السابق</button>
                                        <span id="failedPaginationInfo" style="color: #666;">صفحة 1 من 1</span>
                                        <button onclick="nextFailedPage()" id="nextFailedBtn"
                                            style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">التالي</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Invalid Rows from Validation Sidebar - 40% -->
                            <div id="validationErrorsSidebar"
                                style="flex: 0 0 40%; padding: 15px; overflow: hidden; display: flex; flex-direction: column; background: #fafafa; min-height: 0;">
                                <h5 style="font-size: 13px; margin: 0 0 10px 0; color: #ff5722; flex-shrink: 0;">⚠️ الأخطاء من التحقق</h5>

                                <!-- Invalid Rows List with Pagination -->
                                <div id="validationInvalidList" style="flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; margin-bottom: 10px; font-size: 11px;"></div>

                                <!-- Pagination Controls -->
                                <div id="validationInvalidPagination"
                                    style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-top: 1px solid #ddd; font-size: 11px; flex-shrink: 0;">
                                    <button onclick="prevValidationInvalidPage()"
                                        style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">السابق</button>
                                    <span id="validationInvalidInfo" style="color: #666;">صفحة 1 من 1</span>
                                    <button onclick="nextValidationInvalidPage()"
                                        style="padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px;">التالي</button>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div style="padding: 12px 20px; background: #f5f5f5; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0;">
                            <button onclick="closeImportResultsModal()"
                                style="padding: 6px 16px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                حسناً، فهمت
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scriptsCode')
    <!-- SweetAlert2 CDN -->
    <script>
        // Global variables
        let importResults = null;
        let validationResults = null;
        let validationId = null; // معرف فريد لاسترجاع نتائج التحقق من Session
        let pendingFormData = null;

        // Pagination للتحذيرات
        let allWarnings = [];
        let currentWarningsPage = 1;
        const warningsPerPage = 10; // عدد التحذيرات لكل صفحة

        // Pagination للأخطاء
        let allErrors = [];
        let currentErrorsPage = 1;
        const errorsPerPage = 10; // عدد الأخطاء لكل صفحة

        // Import Results Modal Variables
        let allFailedRows = [];
        let currentFailedPage = 1;
        const failedPerPage = 10;
        let allValidationInvalid = [];
        let currentValidationInvalidPage = 1;
        const validationInvalidPerPage = 10;

        // Validation Modal Functions
        function showValidationModal() {
            const modal = document.getElementById('validationModal');
            modal.style.display = 'flex';
            // منع scroll الخلفية
            document.body.classList.add('modal-open');
            // منع التفاعل مع الخلفية
            document.body.style.overflow = 'hidden';
        }

        function closeValidationModal() {
            const modal = document.getElementById('validationModal');
            modal.style.display = 'none';
            // إعادة تفعيل scroll الخلفية
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';

            validationResults = null;
            pendingFormData = null;
            allWarnings = [];
            currentWarningsPage = 1;
            allErrors = [];
            currentErrorsPage = 1;
        }

        // Import Results Modal Functions
        function showImportResultsModal() {
            const modal = document.getElementById('importResultsModal');
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
        }

        function closeImportResultsModal() {
            const modal = document.getElementById('importResultsModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';

            allFailedRows = [];
            currentFailedPage = 1;
            allValidationInvalid = [];
            currentValidationInvalidPage = 1;
        }

        // Display Import Results in Modal
        function displayImportResults(data) {
            // Extract data
            const importResult = data.import_result || {};
            const validationResult = data.validation_result || {};
            const statistics = validationResult.statistics || {};

            // الحصول على الأعداد الصحيحة
            const totalRows = statistics.total_rows || 0; // الإجمالي من الملف
            const importedRows = importResult.imported_rows || 0; // المدخلة بنجاح
            const failedImportRows = (importResult.failed_records || []).length; // فشلت أثناء الإدخال
            const invalidRows = (validationResult.invalid_rows || []).length; // فشلت في التحقق
            const duplicateRows = (validationResult.invalid_rows || []).filter(row =>
                row.errors && row.errors.some(e => e.type === 'duplicate')
            ).length; // المكررة

            // إجمالي الفاشل = فشل التحقق + فشل الإدخال
            const totalFailedRows = invalidRows + failedImportRows;

            // Update statistics
            document.getElementById('imp-total-rows').textContent = totalRows;
            document.getElementById('imp-success-rows').textContent = importedRows;
            document.getElementById('imp-failed-rows').textContent = totalFailedRows;
            document.getElementById('imp-duplicate-rows').textContent = duplicateRows;

            // Update title and message
            const messageDiv = document.getElementById('importResultMessage');
            const titleSpan = document.getElementById('import-modal-title');

            if (totalFailedRows === 0 && importedRows > 0) {
                titleSpan.textContent = '✅ نجحت العملية';
                messageDiv.style.cssText = 'background: #e8f5e9; color: #2e7d32; border-right: 3px solid #4caf50;';
                messageDiv.textContent = `✅ تم إدخال ${importedRows} صف بنجاح من ${totalRows} صف بدون أي أخطاء!`;
            } else if (importedRows === 0 && totalFailedRows > 0) {
                titleSpan.textContent = '❌ فشلت العملية';
                messageDiv.style.cssText = 'background: #ffebee; color: #c62828; border-right: 3px solid #f44336;';
                messageDiv.textContent = `❌ فشل إدخال جميع الصفوف! (${totalFailedRows} صف فاشل من ${totalRows})`;
            } else {
                titleSpan.textContent = '⚠️ نجحت جزئياً';
                messageDiv.style.cssText = 'background: #fff3e0; color: #e65100; border-right: 3px solid #ff9800;';
                messageDiv.textContent = `⚠️ تم إدخال ${importedRows} صف بنجاح، ${totalFailedRows} صف فشل من إجمالي ${totalRows} صف`;
            }

            // Display failed rows from import (فشل أثناء الإدخال)
            const failedContainer = document.getElementById('failedRowsContainer');
            if (failedImportRows > 0) {
                allFailedRows = importResult.failed_records || [];
                currentFailedPage = 1;
                displayFailedPage();
                failedContainer.style.display = 'flex';
            } else {
                failedContainer.style.display = 'none';
            }

            // Display invalid rows from validation (فشل في التحقق + المكررة)
            const validationSidebar = document.getElementById('validationErrorsSidebar');
            if (invalidRows > 0) {
                allValidationInvalid = validationResult.invalid_rows || [];
                currentValidationInvalidPage = 1;
                displayValidationInvalidPage();
                validationSidebar.style.display = 'flex';
            } else {
                validationSidebar.style.display = 'none';
                document.getElementById('validationInvalidList').innerHTML =
                    '<div style="text-align: center; color: #999; padding: 20px;">لا توجد أخطاء من التحقق</div>';
            }

            showImportResultsModal();
        }

        // Display Failed Rows Page (عرض الأخطاء من الإدخال بشكل مجمّع)
        function displayFailedPage() {
            const failedList = document.getElementById('failedRowsList');

            if (allFailedRows.length === 0) {
                failedList.innerHTML = '<div style="text-align: center; color: #999;">لا توجد صفوف فاشلة</div>';
                document.getElementById('failedPagination').style.display = 'none';
                return;
            }

            // تجميع الأخطاء المتشابهة
            const groupedErrors = {};

            allFailedRows.forEach(failed => {
                // تنظيف رسالة الخطأ
                let errorMsg = failed.error || 'خطأ غير محدد';

                // استخراج السبب الرئيسي من رسالة SQL
                let cleanMessage = errorMsg;
                if (errorMsg.includes('SQLSTATE')) {
                    // استخراج الرسالة الأساسية
                    const match = errorMsg.match(/SQLSTATE\[\d+\]:\s*([^(]+)/);
                    if (match) {
                        cleanMessage = match[1].trim();
                    }
                }

                const groupKey = cleanMessage;

                if (!groupedErrors[groupKey]) {
                    groupedErrors[groupKey] = {
                        message: cleanMessage,
                        fullError: errorMsg,
                        rows: [],
                        identities: [],
                        sqlCode: failed.sql_code || null
                    };
                }

                const rowNum = failed.row || null;
                const identity = failed.identifier || failed.data?.data_id_number || null;

                if (rowNum && !groupedErrors[groupKey].rows.includes(rowNum)) {
                    groupedErrors[groupKey].rows.push(rowNum);
                    if (identity) {
                        groupedErrors[groupKey].identities.push(identity);
                    }
                }
            });

            // عرض الأخطاء المجمّعة
            let html = '';
            Object.values(groupedErrors).forEach(group => {
                const sortedRows = group.rows.sort((a, b) => a - b);
                let rowsText = '';
                if (sortedRows.length <= 10) {
                    rowsText = sortedRows.join(', ');
                } else {
                    rowsText = `${sortedRows.slice(0, 10).join(', ')} ... (+${sortedRows.length - 10} صف)`;
                }

                html += `
                    <div style="background: #ffebee; padding: 8px; margin-bottom: 6px; border-radius: 4px; border-right: 3px solid #d32f2f;">
                        <div style="font-weight: 600; font-size: 11px; margin-bottom: 4px; color: #b71c1c;">
                            🚫 فشل الإدخال: ${group.message}
                        </div>
                        <div style="font-size: 10px; color: #666; background: white; padding: 4px 6px; border-radius: 2px; margin-top: 4px;">
                            📍 الصفوف المتأثرة: <strong>${rowsText}</strong>
                        </div>
                        ${group.identities.length > 0 ? `
                            <div style="font-size: 9px; color: #666; background: white; padding: 4px 6px; border-radius: 2px; margin-top: 3px;">
                                🆔 أرقام الهوية: <strong>${group.identities.slice(0, 5).join(', ')}${group.identities.length > 5 ? ' ...' : ''}</strong>
                            </div>
                        ` : ''}
                        <div style="font-size: 9px; color: #999; margin-top: 3px;">
                            إجمالي الصفوف المتأثرة: <strong>${sortedRows.length}</strong>
                        </div>
                        ${group.sqlCode ? `
                            <div style="font-size: 9px; color: #999; margin-top: 3px;">
                                كود الخطأ: <code style="background: #f5f5f5; padding: 2px 4px; border-radius: 2px;">${group.sqlCode}</code>
                            </div>
                        ` : ''}
                        <details style="margin-top: 4px;">
                            <summary style="cursor: pointer; font-size: 9px; color: #1976d2;">
                                📋 عرض تفاصيل الخطأ الكاملة
                            </summary>
                            <div style="font-size: 9px; color: #666; margin-top: 4px; padding: 6px; background: white; border-radius: 2px; max-height: 100px; overflow-y: auto;">
                                ${group.fullError}
                            </div>
                        </details>
                        <div style="font-size: 9px; color: #c62828; margin-top: 4px; padding: 3px 6px; background: rgba(211,47,47,0.1); border-radius: 2px;">
                            ⚠️ <strong>الحل:</strong> تأكد من صحة البيانات في هذه الصفوف وتوافقها مع قاعدة البيانات
                        </div>
                    </div>
                `;
            });

            failedList.innerHTML = html;

            // إخفاء pagination لأننا نعرض كل شيء مجمعاً
            document.getElementById('failedPagination').style.display = 'none';
        }

        // Display Validation Invalid Page (عرض الأخطاء من التحقق بشكل مجمّع)
        function displayValidationInvalidPage() {
            const invalidList = document.getElementById('validationInvalidList');

            if (allValidationInvalid.length === 0) {
                invalidList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">لا توجد أخطاء من التحقق</div>';
                document.getElementById('validationInvalidPagination').style.display = 'none';
                return;
            }

            // تجميع الأخطاء المتشابهة
            const groupedErrors = {};

            allValidationInvalid.forEach(row => {
                if (row.errors && row.errors.length > 0) {
                    row.errors.forEach(error => {
                        // تحديد نوع الخطأ
                        const errorType = error.type || 'error';
                        const isDuplicate = errorType === 'duplicate';

                        // تنظيف رسالة الخطأ
                        let cleanMessage = error.error || error.message || 'خطأ غير محدد';
                        cleanMessage = cleanMessage.replace(/^الصف\s+\d+:\s*/i, '');
                        cleanMessage = cleanMessage.replace(/\s+في الصف\s+\d+\s*$/i, '');
                        cleanMessage = cleanMessage.trim();

                        if (!cleanMessage) return;

                        // مفتاح فريد للمجموعة
                        const groupKey = `${errorType}_${cleanMessage}`;

                        if (!groupedErrors[groupKey]) {
                            groupedErrors[groupKey] = {
                                message: cleanMessage,
                                type: errorType,
                                severity: error.severity || 'normal',
                                isDuplicate: isDuplicate,
                                rows: [],
                                identities: []
                            };
                        }

                        // إضافة رقم الصف ورقم الهوية
                        const rowNum = row.row || row.row_number;
                        const identity = row.data?.data_id_number || null;

                        if (rowNum && !groupedErrors[groupKey].rows.includes(rowNum)) {
                            groupedErrors[groupKey].rows.push(rowNum);
                            if (identity) {
                                groupedErrors[groupKey].identities.push(identity);
                            }
                        }
                    });
                }
            });

            // عرض الأخطاء المجمّعة
            let html = '';
            Object.values(groupedErrors).forEach(group => {
                const isDuplicate = group.isDuplicate;
                const isCritical = group.severity === 'critical';

                // تحديد الألوان
                const borderColor = isDuplicate ? '#ff9800' : (isCritical ? '#d32f2f' : '#f44336');
                const bgColor = isDuplicate ? '#fff3e0' : (isCritical ? '#ffcdd2' : '#ffebee');
                const textColor = isDuplicate ? '#e65100' : (isCritical ? '#b71c1c' : '#c62828');
                const icon = isDuplicate ? '🔁' : (isCritical ? '🔴' : '❌');

                // ترتيب الصفوف
                const sortedRows = group.rows.sort((a, b) => a - b);
                let rowsText = '';
                if (sortedRows.length <= 10) {
                    rowsText = sortedRows.join(', ');
                } else {
                    rowsText = `${sortedRows.slice(0, 10).join(', ')} ... (+${sortedRows.length - 10} صف)`;
                }

                html += `
                    <div style="background: ${bgColor}; padding: 8px; margin-bottom: 6px; border-radius: 4px; border-right: 3px solid ${borderColor};">
                        <div style="font-weight: 600; font-size: 11px; margin-bottom: 4px; color: ${textColor};">
                            ${icon} ${group.message}
                        </div>
                        <div style="font-size: 10px; color: #666; background: white; padding: 4px 6px; border-radius: 2px; margin-top: 4px;">
                            📍 الصفوف المتأثرة: <strong>${rowsText}</strong>
                        </div>
                        ${group.identities.length > 0 ? `
                            <div style="font-size: 9px; color: #666; background: white; padding: 4px 6px; border-radius: 2px; margin-top: 3px;">
                                🆔 ${isDuplicate ? 'أرقام الهوية المكررة' : 'أرقام الهوية'}: <strong>${group.identities.slice(0, 5).join(', ')}${group.identities.length > 5 ? ' ...' : ''}</strong>
                            </div>
                        ` : ''}
                        <div style="font-size: 9px; color: #999; margin-top: 3px;">
                            إجمالي الصفوف المتأثرة: <strong>${sortedRows.length}</strong>
                        </div>
                        ${isDuplicate ? `
                            <div style="font-size: 9px; color: #e65100; margin-top: 3px; padding: 3px 6px; background: rgba(255,152,0,0.1); border-radius: 2px;">
                                💡 <strong>الحل:</strong> هذه السجلات موجودة مسبقاً في قاعدة البيانات ولن يتم إدخالها
                            </div>
                        ` : isCritical ? `
                            <div style="font-size: 9px; color: #c62828; margin-top: 3px; padding: 3px 6px; background: rgba(211,47,47,0.1); border-radius: 2px;">
                                ⚠️ <strong>الحل:</strong> يجب تصحيح هذه الأخطاء في ملف Excel قبل إعادة المحاولة
                            </div>
                        ` : ''}
                    </div>
                `;
            });

            invalidList.innerHTML = html;

            // إخفاء pagination لأننا نعرض كل شيء مجمعاً
            document.getElementById('validationInvalidPagination').style.display = 'none';
        }

        // Pagination Functions
        function prevFailedPage() {
            if (currentFailedPage > 1) {
                currentFailedPage--;
                displayFailedPage();
            }
        }

        function nextFailedPage() {
            const totalPages = Math.ceil(allFailedRows.length / failedPerPage);
            if (currentFailedPage < totalPages) {
                currentFailedPage++;
                displayFailedPage();
            }
        }

        function prevValidationInvalidPage() {
            if (currentValidationInvalidPage > 1) {
                currentValidationInvalidPage--;
                displayValidationInvalidPage();
            }
        }

        function nextValidationInvalidPage() {
            const totalPages = Math.ceil(allValidationInvalid.length / validationInvalidPerPage);
            if (currentValidationInvalidPage < totalPages) {
                currentValidationInvalidPage++;
                displayValidationInvalidPage();
            }
        }

        // Functions للتنقل بين صفحات التحذيرات
        function prevWarningsPage() {
            if (currentWarningsPage > 1) {
                currentWarningsPage--;
                displayWarningsPage();
            }
        }

        function nextWarningsPage() {
            const totalPages = Math.ceil(allWarnings.length / warningsPerPage);
            if (currentWarningsPage < totalPages) {
                currentWarningsPage++;
                displayWarningsPage();
            }
        }

        // Functions للتنقل بين صفحات الأخطاء
        function prevErrorsPage() {
            if (currentErrorsPage > 1) {
                currentErrorsPage--;
                displayErrorsPage();
            }
        }

        function nextErrorsPage() {
            const totalPages = Math.ceil(allErrors.length / errorsPerPage);
            if (currentErrorsPage < totalPages) {
                currentErrorsPage++;
                displayErrorsPage();
            }
        }

        function displayWarningsPage() {
            const warningsList = document.getElementById('warningsList');

            // عرض التحذيرات
            warningsList.innerHTML = '';

            if (allWarnings.length === 0) {
                warningsList.innerHTML =
                    '<div style="text-align: center; color: #999; padding: 20px;">لا توجد تحذيرات</div>';
            } else {
                // تجميع التحذيرات المتشابهة
                const groupedWarnings = {};

                allWarnings.forEach((warning) => {
                    // تنظيف رسالة التحذير من رقم الصف والأيقونات
                    let cleanMessage = warning.message || 'تحذير';
                    // إزالة الأيقونات (⚠️ وغيرها)
                    cleanMessage = cleanMessage.replace(/[⚠️❌🚫✅✓💡📍🔔]+/g, '').trim();
                    // إزالة "الصف X:" من البداية
                    cleanMessage = cleanMessage.replace(/^الصف\s+\d+:\s*/i, '').trim();

                    // تجاهل التحذيرات الفارغة
                    if (!cleanMessage || cleanMessage === 'تحذير') {
                        return;
                    }

                    // إنشاء مفتاح فريد بناءً على الرسالة النظيفة + action + solution
                    const warningKey = `${cleanMessage}_${warning.action || ''}_${warning.solution || ''}`;

                    if (!groupedWarnings[warningKey]) {
                        groupedWarnings[warningKey] = {
                            message: cleanMessage,
                            action: warning.action || '',
                            solution: warning.solution || '',
                            rows: []
                        };
                    }

                    // إضافة رقم الصف للمجموعة
                    const rowNum = warning.row_number || warning.row;
                    if (rowNum && !groupedWarnings[warningKey].rows.includes(rowNum)) {
                        groupedWarnings[warningKey].rows.push(rowNum);
                    }
                });

                // تحويل المجموعات إلى مصفوفة للـ pagination
                const groupedArray = Object.values(groupedWarnings);
                const totalGroups = groupedArray.length;
                const totalPages = Math.ceil(totalGroups / warningsPerPage);

                // حساب البداية والنهاية للصفحة الحالية
                const startIndex = (currentWarningsPage - 1) * warningsPerPage;
                const endIndex = Math.min(startIndex + warningsPerPage, totalGroups);
                const currentPageGroups = groupedArray.slice(startIndex, endIndex);

                // عرض التحذيرات المجمعة للصفحة الحالية
                currentPageGroups.forEach(group => {
                    const warningDiv = document.createElement('div');
                    warningDiv.style.cssText =
                        'background: white; padding: 8px; margin-bottom: 6px; border-radius: 4px; border-right: 3px solid #ff9800;';

                    // ترتيب وتنسيق أرقام الصفوف
                    let rowsDisplay = '';
                    if (group.rows.length > 0) {
                        const sortedRows = group.rows.sort((a, b) => a - b);
                        if (sortedRows.length <= 10) {
                            rowsDisplay = `<div style="font-size: 10px; color: #666; background: #fff3e0; padding: 4px 6px; border-radius: 2px; margin-top: 4px;">
                                📍 الصفوف: <strong>${sortedRows.join(', ')}</strong>
                            </div>`;
                        } else {
                            rowsDisplay = `<div style="font-size: 10px; color: #666; background: #fff3e0; padding: 4px 6px; border-radius: 2px; margin-top: 4px;">
                                📍 الصفوف: <strong>${sortedRows.slice(0, 10).join(', ')} ... (+${sortedRows.length - 10} صف آخر)</strong>
                            </div>`;
                        }
                        rowsDisplay += `<div style="font-size: 9px; color: #999; margin-top: 3px;">
                            عدد الصفوف المتأثرة: <strong>${sortedRows.length}</strong>
                        </div>`;
                    }

                    warningDiv.innerHTML = `
                        <div style="font-weight: 600; font-size: 11px; margin-bottom: 4px; color: #e65100;">
                            ⚠️ ${group.message}
                        </div>
                        ${group.action ? `<div style="font-size: 10px; color: #666; margin-bottom: 2px;">✓ ${group.action}</div>` : ''}
                        ${group.solution ? `<div style="font-size: 10px; color: #1976d2; margin-bottom: 2px;">💡 ${group.solution}</div>` : ''}
                        ${rowsDisplay}
                    `;
                    warningsList.appendChild(warningDiv);
                });

                // إظهار/إخفاء pagination للتحذيرات
                const warningsPagination = document.getElementById('warningsPagination');
                if (warningsPagination) {
                    if (totalPages > 1) {
                        warningsPagination.style.display = 'flex';

                        // تحديث معلومات الصفحة
                        const pageInfo = warningsPagination.querySelector('.page-info');
                        if (pageInfo) {
                            pageInfo.textContent = `صفحة ${currentWarningsPage} من ${totalPages} (${totalGroups} تحذير)`;
                        }

                        // تحديث أزرار التنقل
                        const prevBtn = warningsPagination.querySelector('.prev-btn');
                        const nextBtn = warningsPagination.querySelector('.next-btn');

                        if (prevBtn) {
                            prevBtn.disabled = currentWarningsPage === 1;
                            prevBtn.style.opacity = currentWarningsPage === 1 ? '0.5' : '1';
                        }

                        if (nextBtn) {
                            nextBtn.disabled = currentWarningsPage === totalPages;
                            nextBtn.style.opacity = currentWarningsPage === totalPages ? '0.5' : '1';
                        }
                    } else {
                        warningsPagination.style.display = 'none';
                    }
                }
            }
        }

        function displayErrorsPage() {
            const errorsList = document.getElementById('invalidRowsList');

            // عرض الأخطاء
            errorsList.innerHTML = '';

            if (allErrors.length === 0) {
                errorsList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">لا توجد أخطاء</div>';
            } else {
                // تجميع الأخطاء المتشابهة
                const groupedErrors = {};

                allErrors.forEach(row => {
                    if (row.errors && row.errors.length > 0) {
                        row.errors.forEach(error => {
                            // تنظيف رسالة الخطأ من رقم الصف
                            let cleanMessage = error.error || error.message || 'خطأ';
                            // إزالة "الصف X:" من البداية
                            cleanMessage = cleanMessage.replace(/^الصف\s+\d+:\s*/i, '');
                            // إزالة " في الصف X" من النهاية
                            cleanMessage = cleanMessage.replace(/\s+في الصف\s+\d+\s*$/i, '');
                            cleanMessage = cleanMessage.trim();

                            // تجاهل الأخطاء الفارغة
                            if (!cleanMessage || cleanMessage === 'خطأ') {
                                return;
                            }

                            // إنشاء مفتاح فريد للخطأ بناءً على الرسالة النظيفة والشدة
                            const errorKey = `${cleanMessage}_${error.severity || 'normal'}`;

                            if (!groupedErrors[errorKey]) {
                                groupedErrors[errorKey] = {
                                    message: cleanMessage,
                                    severity: error.severity || 'normal',
                                    rows: []
                                };
                            }

                            // إضافة رقم الصف للمجموعة (بدون تكرار)
                            if (!groupedErrors[errorKey].rows.includes(row.row_number)) {
                                groupedErrors[errorKey].rows.push(row.row_number);
                            }
                        });
                    }
                });

                // عرض الأخطاء المجمعة
                Object.values(groupedErrors).forEach(group => {
                    const groupDiv = document.createElement('div');

                    const isCritical = group.severity === 'critical';
                    const borderColor = isCritical ? '#d32f2f' : '#f44336';
                    const bgColor = isCritical ? '#ffcdd2' : '#ffebee';

                    groupDiv.style.cssText = `background: ${bgColor}; padding: 8px; margin-bottom: 6px; border-radius: 4px; border-right: 3px solid ${borderColor};`;

                    // ترتيب وتنسيق أرقام الصفوف
                    const sortedRows = group.rows.sort((a, b) => a - b);
                    let rowsText = '';
                    if (sortedRows.length <= 10) {
                        rowsText = sortedRows.join(', ');
                    } else {
                        rowsText = `${sortedRows.slice(0, 10).join(', ')} ... (+${sortedRows.length - 10} صف آخر)`;
                    }

                    groupDiv.innerHTML = `
                        <div style="font-weight: 600; font-size: 11px; margin-bottom: 4px; color: ${isCritical ? '#b71c1c' : '#c62828'};">
                            ${isCritical ? '🚫' : '❌'} ${group.message}
                        </div>
                        <div style="font-size: 10px; color: #666; background: white; padding: 4px 6px; border-radius: 2px; margin-top: 4px;">
                            📍 الصفوف: <strong>${rowsText}</strong>
                        </div>
                        <div style="font-size: 9px; color: #999; margin-top: 3px;">
                            عدد الصفوف المتأثرة: <strong>${sortedRows.length}</strong>
                        </div>
                    `;
                    errorsList.appendChild(groupDiv);
                });
            }

            // إخفاء pagination للأخطاء حيث أصبحنا نعرض كل شيء مجمعاً
            const errorsPagination = document.getElementById('errorsPagination');
            if (errorsPagination) {
                errorsPagination.style.display = 'none';
            }
        }

        function proceedWithImport() {
            if (!validationResults || !validationId) {
                Swal.fire({
                    icon: 'error',
                    title: '❌ خطأ',
                    text: 'لا توجد بيانات التحقق متاحة. يرجى إعادة التحقق من الملف.',
                });
                return;
            }

            closeValidationModal();

            // إنشاء FormData بسيط يحتوي فقط على validation_id (بدون إعادة رفع الملف!)
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('validation_id', validationId);

            // عرض رسالة معالجة
            Swal.fire({
                icon: 'info',
                title: '⏳ جاري الإدخال...',
                html: `
                    <div style="text-align: right; direction: rtl;">
                        <p>جاري إدخال الصفوف الصحيحة فقط من النتائج المحفوظة...</p>
                        <p style="color: #4caf50; font-size: 12px;"></p>
                    </div>
                `,
                showConfirmButton: false,
                allowOutsideClick: false
            });

            // إرسال طلب الإدخال
            fetch('/admin/file/import-validated-excel', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    handleImportResponse(data);
                })
                .catch(error => {
                    console.error('Import error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '❌ خطأ في الإدخال',
                        text: error.message || 'حدث خطأ أثناء إدخال البيانات'
                    });
                });
        }

        function displayValidationResults(result) {
            validationResults = result;

            // Extract data from nested structure
            const report = result.validation_result || result;

            // Update statistics
            const statistics = report.statistics || {};
            document.getElementById('val-total-rows').textContent = statistics.total_rows || 0;
            document.getElementById('val-valid-rows').textContent = statistics.valid_rows || 0;
            document.getElementById('val-invalid-rows').textContent = statistics.invalid_rows || 0;

            // عرض عدد التحذيرات
            const warningsCount = (report.warnings && Array.isArray(report.warnings)) ? report.warnings.length : 0;
            document.getElementById('val-warnings').textContent = warningsCount;

            // Determine message type and content
            const validRows = statistics.valid_rows || 0;
            const invalidRows = statistics.invalid_rows || 0;
            const canProceed = result.can_proceed || false;

            const messageDiv = document.getElementById('validationMessage');

            // التحقق من وجود أخطاء حرجة
            let hasCriticalErrors = false;
            let criticalErrorsCount = 0;
            if (report.invalid_rows) {
                report.invalid_rows.forEach(row => {
                    if (row.errors && row.errors.some(e => e.severity === 'critical')) {
                        hasCriticalErrors = true;
                        criticalErrorsCount++;
                    }
                });
            }

            if (invalidRows === 0 && validRows > 0) {
                messageDiv.style.cssText = 'background: #e8f5e9; color: #2e7d32; border-right: 3px solid #4caf50;';
                let message = `✅ جميع الصفوف صحيحة! يمكنك المتابعة بإدخال ${validRows} صف.`;
                if (warningsCount > 0) {
                    message += ` (${warningsCount} تحذير)`;
                }
                messageDiv.textContent = message;
            } else if (validRows === 0 && invalidRows > 0) {
                messageDiv.style.cssText = 'background: #ffebee; color: #c62828; border-right: 3px solid #f44336;';
                let message = `❌ جميع الصفوف تحتوي على أخطاء!`;
                if (hasCriticalErrors) {
                    message += ` يوجد ${criticalErrorsCount} صف بأخطاء حرجة (حقول إلزامية مفقودة).`;
                }
                messageDiv.textContent = message;
            } else if (validRows > 0 && invalidRows > 0) {
                messageDiv.style.cssText = 'background: #fff3e0; color: #e65100; border-right: 3px solid #ff9800;';
                let message = `⚠️ ${validRows} صف صحيح، ${invalidRows} صف خاطئ.`;
                if (hasCriticalErrors) {
                    message += ` (${criticalErrorsCount} صف بأخطاء حرجة)`;
                }
                if (warningsCount > 0) {
                    message += ` (${warningsCount} تحذير)`;
                }
                messageDiv.textContent = message;
            } else {
                messageDiv.style.cssText = 'background: #ffebee; color: #c62828; border-right: 3px solid #f44336;';
                messageDiv.textContent = `❌ لا توجد بيانات صحيحة للإدخال`;
            }

            // Display warnings with pagination
            if (warningsCount > 0 && report.warnings) {
                allWarnings = report.warnings;
                currentWarningsPage = 1;
                displayWarningsPage();
                document.getElementById('warningsSidebar').style.display = 'flex';

                // إظهار رسالة إذا كان هناك المزيد من التحذيرات
                if (report.has_more_warnings && report.total_warnings_count) {
                    const moreWarningsMsg = document.createElement('div');
                    moreWarningsMsg.style.cssText =
                        'background: #fff3e0; padding: 6px; margin: 8px 0; border-radius: 3px; font-size: 10px; text-align: center; color: #e65100;';
                    moreWarningsMsg.textContent = `⚠️ يوجد ${report.total_warnings_count} تحذير إجمالي (عرض أول 1000 فقط)`;
                    document.getElementById('warningsList').parentElement.insertBefore(
                        moreWarningsMsg,
                        document.getElementById('warningsList')
                    );
                }
            } else {
                allWarnings = [];
                document.getElementById('warningsList').innerHTML =
                    '<div style="text-align: center; color: #999; padding: 20px;">لا توجد تحذيرات</div>';
                document.getElementById('warningsPagination').style.display = 'none';
            }

            // Display invalid rows with pagination
            const invalidRowsContainer = document.getElementById('invalidRowsContainer');

            if (invalidRows > 0 && report.invalid_rows) {
                invalidRowsContainer.style.display = 'flex';
                allErrors = report.invalid_rows;
                currentErrorsPage = 1;
                displayErrorsPage();
                document.getElementById('errorsPagination').style.display = 'flex';

                // إظهار رسالة إذا كان هناك المزيد من الأخطاء
                if (report.has_more_errors && report.total_errors_count) {
                    const moreErrorsMsg = document.createElement('div');
                    moreErrorsMsg.style.cssText =
                        'background: #ffebee; padding: 6px; margin: 8px 0; border-radius: 3px; font-size: 10px; text-align: center; color: #c62828;';
                    moreErrorsMsg.textContent = `⚠️ يوجد ${report.total_errors_count} صف خاطئ إجمالي (عرض أول 1000 فقط)`;
                    document.getElementById('invalidRowsList').parentElement.insertBefore(
                        moreErrorsMsg,
                        document.getElementById('invalidRowsList')
                    );
                }
            } else {
                invalidRowsContainer.style.display = 'none';
            }

            // Show/Hide proceed button
            if (canProceed && validRows > 0) {
                document.getElementById('proceedImportBtn').style.display = 'block';
            } else {
                document.getElementById('proceedImportBtn').style.display = 'none';
            }

            // Show modal
            showValidationModal();
        }

        // Modal management functions
        function showDetailsModal() {
            const modal = document.getElementById('detailsModal');
            modal.style.display = 'block';
            // منع scroll الخلفية
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            modal.style.display = 'none';
            // إعادة تفعيل scroll الخلفية
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside - معطل لمنع الإغلاق العرضي
        document.addEventListener('click', function(e) {
            const detailsModal = document.getElementById('detailsModal');
            // تم تعطيل الإغلاق بالضغط على الخلفية لعزل المودال
            // if (e.target === detailsModal) {
            //     closeDetailsModal();
            // }

            const validationModal = document.getElementById('validationModal');
            // تم تعطيل الإغلاق بالضغط على الخلفية لعزل المودال
            // if (e.target === validationModal) {
            //     closeValidationModal();
            // }
        });

        // منع إغلاق المودال بمفتاح ESC (اختياري - يمكن تفعيله إذا أردت)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // تم تعطيل الإغلاق بمفتاح ESC لعزل المودال
                // يمكن إزالة التعليق للسماح بالإغلاق بـ ESC
                // if (document.getElementById('validationModal').style.display === 'flex') {
                //     closeValidationModal();
                // }
                // if (document.getElementById('detailsModal').style.display === 'block') {
                //     closeDetailsModal();
                // }
            }
        });

        // منع wheel scroll على الخلفية عندما المودال مفتوح
        document.addEventListener('wheel', function(e) {
            if (document.body.classList.contains('modal-open')) {
                // السماح بـ scroll داخل المودال فقط
                const modal = document.getElementById('validationModal');
                const detailsModal = document.getElementById('detailsModal');

                if (!modal?.contains(e.target) && !detailsModal?.contains(e.target)) {
                    e.preventDefault();
                }
            }
        }, {
            passive: false
        });

        // Tab switching
        document.querySelectorAll('.modal-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                const tabName = this.getAttribute('data-tab');
                document.getElementById('tab-' + tabName).classList.add('active');
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

        // Render records table
        function renderRecordsTable(records, type) {
            if (!records || records.length === 0) {
                return '<div class="no-records">لا توجد سجلات</div>';
            }

            let html = '<table class="records-table"><thead><tr>';
            html += '<th>#</th>';
            html += '<th>معرف السجل</th>';
            html += '<th>معاينة البيانات</th>';

            if (type === 'duplicates') {
                html += '<th>سبب التكرار</th>';
            } else if (type === 'failed') {
                html += '<th>سبب الفشل</th>';
            }

            html += '<th>الحالة</th>';
            html += '</tr></thead><tbody>';

            records.forEach((record, index) => {
                html += '<tr>';
                html += `<td>${index + 1}</td>`;
                html += `<td>${record.identifier || 'N/A'}</td>`;
                html += `<td><div class="record-preview">${record.preview || 'لا توجد معاينة'}</div></td>`;

                if (type === 'duplicates') {
                    html += `<td>${record.reason || 'مكرر'}</td>`;
                } else if (type === 'failed') {
                    html += `<td style="color: #c62828;">${record.error || 'خطأ غير معروف'}</td>`;
                }

                let badgeClass = type === 'successful' ? 'success' : (type === 'duplicates' ? 'duplicate' :
                    'failed');
                let badgeText = type === 'successful' ? '✅ ناجح' : (type === 'duplicates' ? '📋 مكرر' : '❌ فشل');
                html += `<td><span class="badge ${badgeClass}">${badgeText}</span></td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        }

        // Populate modal with data
        function populateModal(results) {
            if (!results) return;

            importResults = results;

            // Populate successful records
            const successfulRecords = results.successful_records || [];
            document.getElementById('tab-successful').innerHTML = renderRecordsTable(successfulRecords, 'successful');

            // Populate duplicate records
            const duplicateRecords = results.duplicates || [];
            document.getElementById('tab-duplicates').innerHTML = renderRecordsTable(duplicateRecords, 'duplicates');

            // Populate failed records
            const failedRecords = results.failed_records || [];
            document.getElementById('tab-failed').innerHTML = renderRecordsTable(failedRecords, 'failed');
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
        }); // Check for any server-side messages (fallback)
        document.addEventListener('DOMContentLoaded', function() {
            // منع scroll الخلفية عند فتح المودال
            const importModal = document.getElementById('importResultsModal');
            const validationModal = document.getElementById('validationModal');

            // منع النقر على overlay من إغلاق المودال
            [importModal, validationModal].forEach(modal => {
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            // لا تفعل شيء - المستخدم نقر على الخلفية
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    });

                    // منع scroll الخلفية
                    modal.addEventListener('wheel', function(e) {
                        e.stopPropagation();
                    }, { passive: false });
                }
            });

            @if (isset($error))
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

            @if (session('warning'))
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
        });

        // Handle form submission with Ajax and SweetAlert
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
            const targetTable = this.querySelector('select[name="target_table"]')?.value || 'data';
            const importEnabled = this.querySelector('input[name="enable_excel_import"]')?.checked || false;

            console.log('📋 Form Data:', {
                processingMode,
                targetTable,
                importEnabled,
                filesCount: fileInput.files.length
            });

            // إذا كان الوضع "حفظ الملف فقط" - استخدام المسار القديم
            if (processingMode === 'file-only' || !importEnabled) {
                // Use old flow for file-only mode
                let processingText = 'حفظ الملف فقط';

                Swal.fire({
                    icon: 'info',
                    title: '⏳ جاري المعالجة...',
                    html: `
                        <div style="text-align: right; direction: rtl;">
                            <p><strong>🔄 وضع المعالجة:</strong> ${processingText}</p>
                            <p><strong>� عدد الملفات:</strong> ${fileInput.files.length}</p>
                            <div style="margin: 15px 0;">
                                <div class="loading-spinner" style="margin: 0 auto;"></div>
                            </div>
                        </div>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false
                });

                // تعطيل النموذج
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '⏳ جاري الرفع...';
                submitBtn.disabled = true;

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
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;

                        Swal.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.success ? '✅ تم حفظ الملف' : '❌ فشل حفظ الملف',
                            text: data.message
                        });

                        if (data.success) {
                            this.reset();
                        }
                    })
                    .catch(error => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;

                        Swal.fire({
                            icon: 'error',
                            title: '❌ خطأ',
                            text: error.message || 'حدث خطأ أثناء معالجة الطلب'
                        });
                    });

                return;
            }

            // استخدام مسار التحقق الجديد لوضع الاستيراد
            Swal.fire({
                icon: 'info',
                title: '🔍 جاري فحص الملف...',
                html: `
                    <div style="text-align: right; direction: rtl;">
                        <p>جاري قراءة وتحليل ملف Excel...</p>
                        <p style="font-size: 13px; color: #666;">سيتم التحقق من صحة البيانات قبل الإدخال</p>
                        <div style="margin: 15px 0;">
                            <div class="loading-spinner" style="margin: 0 auto;"></div>
                        </div>
                        <div style="font-size: 12px; color: #999; margin-top: 10px;">
                            💡 قد تستغرق العملية بضع دقائق للملفات الكبيرة
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                allowOutsideClick: false
            });

            // تعطيل النموذج
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '⏳ جاري الفحص...';
            submitBtn.disabled = true;
            this.style.opacity = '0.7';

            // حفظ FormData للاستخدام لاحقاً
            pendingFormData = formData;

            // خطوة 1: التحقق من الملف
            fetch('/admin/file/validate-excel', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // إعادة تفعيل النموذج
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    this.style.opacity = '1';

                    Swal.close();

                    console.log('📊 Validation Response:', data);

                    if (data.success) {
                        // حفظ validation_id من الاستجابة
                        validationId = data.validation_id;
                        console.log('✅ Saved validation_id:', validationId);

                        // عرض نتائج التحقق
                        displayValidationResults(data);
                    } else {
                        // عرض رسالة الخطأ مع التفاصيل
                        let errorDetails = data.message || 'حدث خطأ أثناء التحقق من الملف';

                        if (data.errors) {
                            errorDetails +=
                                '<br><br><ul style="text-align: right; list-style: none; padding: 0;">';
                            Object.keys(data.errors).forEach(key => {
                                if (Array.isArray(data.errors[key])) {
                                    data.errors[key].forEach(error => {
                                        errorDetails += `<li>⚠️ ${error}</li>`;
                                    });
                                }
                            });
                            errorDetails += '</ul>';
                        }

                        Swal.fire({
                            icon: 'error',
                            title: '❌ فشل التحقق',
                            html: `
                            <div style="text-align: right; direction: rtl;">
                                ${errorDetails}
                            </div>
                        `,
                            confirmButtonText: 'حسناً'
                        });
                    }
                })
                .catch(error => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    this.style.opacity = '1';

                    console.error('Validation error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '❌ خطأ في التحقق',
                        text: error.message || 'حدث خطأ أثناء التحقق من الملف'
                    });
                });
        });

        // Handle import response
        function handleImportResponse(data) {
            console.log('📊 Import Response:', data);

            // إغلاق رسالة "جاري الإدخال..." أولاً
            Swal.close();

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

                // عرض النتائج في المودال الجديد
                displayImportResults(data);

                // إعادة تعيين النموذج فقط إذا نجحت العملية فعلاً
                if (!allFailed) {
                    const form = document.getElementById('excelUploadForm');
                    if (form) {
                        form.reset();
                        const importOptions = document.getElementById('importOptions');
                        if (importOptions) {
                            importOptions.style.display = 'none';
                        }
                    }
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
        }

        // Add hover effects to file input - معزول داخل الحاوية
        const excelGateway = document.querySelector('.excel-gateway-wrapper');
        const fileInput = excelGateway.querySelector('input[type="file"]');

        if (fileInput) {
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

            // Add file selection feedback - معزول داخل الحاوية
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
        }
    </script>
@endpush
