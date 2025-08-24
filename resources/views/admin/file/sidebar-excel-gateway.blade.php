@extends('admin.dashboard.toolbars.index')

@push('styles')
    <!-- SweetAlert2 CDN -->

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* حاوية Excel Gateway - معزولة التصميم */
        .excel-gateway-wrapper {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            width: 100%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: linear-gradient(-45deg, #1e3c72, #2a5298, #0f4c75, #3282b8);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            border-radius: 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
            min-height: 600px;
        }

        /* خلفية متحركة معزولة داخل الحاوية */
        .excel-gateway-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='100' height='20' viewBox='0 0 100 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpolygon points='50 0 60 40 100 50 60 60 50 100 40 60 0 50 40 40'/%3E%3C/g%3E%3C/svg%3E");
            animation: floatPattern 20s linear infinite;
            pointer-events: none;
            border-radius: 25px;
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

        .excel-gateway-wrapper .container {
            max-width: 100%;
            width: 100%;
            margin: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideInFromTop 0.8s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .excel-gateway-wrapper .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #3282b8 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .excel-gateway-wrapper .header::before {
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

        .excel-gateway-wrapper .header h1 {
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

        .excel-gateway-wrapper .header p {
            position: relative;
            z-index: 2;
            font-size: 1.1em;
            margin-top: 10px;
            opacity: 0.9;
        }

        .excel-gateway-wrapper .content {
            padding: 40px;
            animation: scaleIn 0.8s ease-out 0.2s both;
        }

        .excel-gateway-wrapper .upload-section {
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
            padding: 25px;
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

        .excel-gateway-wrapper #importOptions select {
            width: 100%;
            padding: 12px;
            border: 2px solid #3282b8;
            border-radius: 8px;
            font-size: 1.1em;
            background: white;
            margin: 15px 0;
        }

        .excel-gateway-wrapper .btn {
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

        .excel-gateway-wrapper .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }

        .excel-gateway-wrapper .btn:hover::before {
            left: 100%;
        }

        .excel-gateway-wrapper .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(30, 60, 114, 0.4);
        }

        .excel-gateway-wrapper .btn:active {
            transform: translateY(-1px);
        }

        .excel-gateway-wrapper .button-group {
            text-align: center;
            margin: 40px 0;
            animation: slideInFromTop 0.8s ease-out 0.4s both;
        }

        .excel-gateway-wrapper .error {
            background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
            color: #c62828;
            padding: 20px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 5px solid #f44336;
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.2);
            animation: scaleIn 0.5s ease-out;
        }

        .excel-gateway-wrapper .success {
            background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%);
            color: #2e7d32;
            padding: 20px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 5px solid #4caf50;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.2);
            animation: scaleIn 0.5s ease-out;
        }

        /* Excel-themed icons and decorations - معزولة */
        .excel-gateway-wrapper .excel-icon {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #107c41 0%, #0e6b37 100%);
            border-radius: 6px;
            position: relative;
            margin-left: 10px;
        }

        .excel-gateway-wrapper .excel-icon::before {
            content: 'X';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 18px;
        }

        /* Loading animation - معزولة */
        .excel-gateway-wrapper .loading-spinner {
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

        /* Keyframes للحركة - معزولة داخل الحاوية */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
    </style>
@endpush

@section('content')
<div class="excel-gateway-wrapper">
    <div class="container">
        <div class="header">
            <h1>🚀 Excel Upload Gateway <span class="excel-icon"></span></h1>
            <p>نظام رفع ملفات Excel المتقدم مع الربط التلقائي للبيانات</p>
        </div>

    <div class="content">
            <!-- Top controls: table selector, counters and single button to open duplicates modal -->
            <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div style="display:flex;gap:10px;align-items:center;">
                    <div class="success" style="padding:8px 12px;border-radius:6px;">تمت الإضافة: <span id="insertedCount">0</span></div>
                    <div class="error" style="padding:8px 12px;border-radius:6px;">تم تجاهل المكرر: <span id="skippedCount">0</span></div>
                </div>

                <div style="display:flex;gap:10px;align-items:center;">
                    <label style="font-weight:600;margin-right:6px;">الجدول: </label>
                    <select id="dupTableSelect" name="dup_table" style="padding:8px;border-radius:6px;border:1px solid #ccc;">
                        <option value="data">جدول البيانات (data)</option>
                        <option value="dead_people">dead_people</option>
                        <option value="re_people">re_people</option>
                    </select>
                    <button id="openDuplicatesModalBtn" class="btn">فتح المكرر</button>
                </div>
            </div>
            <!-- Remove the old error display as we'll use SweetAlert -->

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

                <!-- duplicate UI moved to top controls -->

                <!-- Duplicates Modal -->
                <div id="duplicatesModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;">
                    <div style="background:white;padding:20px;border-radius:8px;max-width:900px;margin:40px auto;">
                        <h3>قائمة أرقام الهوية المكررة</h3>
                        <p>يمكنك تنزيلها كملف Excel أو استعراضها هنا.</p>
                        <div style="max-height:300px;overflow:auto;border:1px solid #eee;padding:10px;" id="duplicatesList"></div>
                        <div style="text-align:left;margin-top:10px;">
                            <button id="exportDuplicatesBtn" class="btn">تصدير إلى Excel</button>
                            <button id="closeDuplicatesModalBtn" class="btn">إغلاق</button>
                        </div>
                    </div>
                </div>

            <div class="button-group">
                <a href="/admin/file/php-diagnostic" class="btn">🔧 تشخيص النظام</a>
                <a href="/admin/dashboard" class="btn">🏠 العودة للوحة التحكم</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
    <!-- SweetAlert2 CDN -->
    <script>
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
                // إعادة تفعيل النموذج
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                this.style.opacity = '1';
                this.style.pointerEvents = 'auto';

                if (data.success) {
                    // عرض رسالة النجاح باستخدام SweetAlert
                    let responseHtml = `
                        <div style="text-align: right; direction: rtl; font-family: 'Poppins', sans-serif;">
                            <h4 style="color: #2e7d32; margin-bottom: 15px;">✅ تمت العملية بنجاح</h4>
                            <div style="background: #f1f8e9; padding: 15px; border-radius: 8px; margin: 10px 0;">
                                <p><strong>📊 الرسالة:</strong> ${data.message}</p>
                    `;

                    if (data.files && Array.isArray(data.files)) {
                        responseHtml += `<h5 style="margin: 15px 0 10px 0;">📁 الملفات المعالجة:</h5>`;
                        data.files.forEach(file => {
                            responseHtml += `
                                <div style="background: white; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid #4caf50;">
                                    <p><strong>📄 اسم الملف:</strong> ${file.original_name || 'غير محدد'}</p>
                                    <p><strong>📊 حالة العملية:</strong> ${file.success ? '✅ نجحت' : '❌ فشلت'}</p>
                                    ${file.message ? `<p><strong>💬 الرسالة:</strong> ${file.message}</p>` : ''}
                                    ${file.file_size ? `<p><strong>📏 حجم الملف:</strong> ${(file.file_size / 1024).toFixed(2)} KB</p>` : ''}
                                    ${file.storage_table ? `<p><strong>🗃️ جدول التخزين:</strong> ${file.storage_table}</p>` : ''}
                                </div>
                            `;
                        });
                    }

                    if (data.total_files) {
                        responseHtml += `<p style="margin-top: 15px;"><strong>📈 إجمالي الملفات:</strong> ${data.total_files}</p>`;
                    }
                    if (data.processing_mode) {
                        responseHtml += `<p><strong>⚙️ وضع المعالجة:</strong> ${data.processing_mode === 'file-only' ? 'حفظ الملف فقط' : 'حفظ + استيراد البيانات'}</p>`;
                    }

                    responseHtml += `</div></div>`;

                    Swal.fire({
                        icon: 'success',
                        title: '🎉 نجحت العملية',
                        html: responseHtml,
                        confirmButtonText: 'ممتاز!',
                        background: '#fff',
                        color: '#2e7d32',
                        showConfirmButton: true,
                        width: '600px',
                        timer: false
                    });

                    // إعادة تعيين النموذج
                    this.reset();
                    document.getElementById('importOptions').style.display = 'none';

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

        // --- Duplicate detection JS ---
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const openModalBtn = document.getElementById('openDuplicatesModalBtn');
        const duplicatesModalEl = document.getElementById('duplicatesModal');
        const duplicatesList = document.getElementById('duplicatesList');
        const exportBtn = document.getElementById('exportDuplicatesBtn');
        const closeModalBtn = document.getElementById('closeDuplicatesModalBtn');
        const insertedCountEl = document.getElementById('insertedCount');
        const skippedCountEl = document.getElementById('skippedCount');

        function showModal() { duplicatesModalEl.style.display = 'block'; }
        function hideModal() { duplicatesModalEl.style.display = 'none'; }

        // When user clicks the single "فتح المكرر" button: detect duplicates for the currently selected table and open modal
        openModalBtn && openModalBtn.addEventListener('click', async function() {
            const table = document.getElementById('dupTableSelect')?.value || 'data';
            try {
                const res = await fetch('/admin/duplicates/find', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ table })
                });
                const data = await res.json();
                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: data.message || 'فشل في الفحص' });
                    return;
                }

                const ids = (data.duplicates || []).map(d => d.id).filter(Boolean);
                duplicatesList.innerHTML = '';
                if (ids.length === 0) {
                    duplicatesList.innerHTML = '<div class="success" style="text-align:center;">لا توجد سجلات مكررة</div>';
                } else {
                    ids.forEach(id => {
                        const el = document.createElement('div');
                        el.textContent = id;
                        duplicatesList.appendChild(el);
                    });
                }

                // update counters
                insertedCountEl && (insertedCountEl.textContent = '0');
                skippedCountEl && (skippedCountEl.textContent = ids.length);

                // store ids for export
                exportBtn.dataset.ids = JSON.stringify(ids);

                showModal();
            } catch (err) {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'خطأ', text: err.message || 'فشل الاتصال' });
            }
        });
        closeModalBtn && closeModalBtn.addEventListener('click', function() { hideModal(); });

    // removed separate detect button: open button runs detection based on top selector

        exportBtn && exportBtn.addEventListener('click', async function () {
            const raw = this.dataset.ids || '[]';
            const ids = JSON.parse(raw);
            if (!ids || ids.length === 0) {
                Swal.fire({ icon: 'info', title: 'لا يوجد', text: 'لا توجد أرقام مكررة للتصدير' });
                return;
            }

            const table = document.getElementById('dupTableSelect')?.value || 'data';
            try {
                // call process-insert with cache_ids to store ids server-side and get cache key
                const res = await fetch('/admin/duplicates/process-insert', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ table, rows: [], cache_ids: ids })
                });

                const result = await res.json();
                if (!result.success) {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: result.message || 'فشل إنشاء الملف' });
                    return;
                }

                const cacheKey = result.cache_key;
                if (!cacheKey) {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: 'لم يتم استلام مفتاح للتصدير' });
                    return;
                }

                // download exported file
                window.location.href = '/admin/duplicates/export/' + encodeURIComponent(cacheKey);
            } catch (err) {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'خطأ', text: err.message || 'فشل الاتصال' });
            }
        });
    </script>
@endpush
