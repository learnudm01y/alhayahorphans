<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تشخيص شامل - حفظ الوثائق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .test-card {
            border-left: 4px solid #007bff;
        }
        .success-card {
            border-left-color: #28a745 !important;
        }
        .error-card {
            border-left-color: #dc3545 !important;
        }
        .result-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-stethoscope me-2"></i>تشخيص شامل - نظام حفظ الوثائق</h2>
            </div>
        </div>

        <div class="row">
            <!-- الخطوة 1: فحص البنية -->
            <div class="col-md-6">
                <div class="card test-card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>الخطوة 1: فحص البنية الأساسية</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary btn-sm" onclick="checkStructure()">
                            <i class="fas fa-play me-1"></i>فحص
                        </button>
                        <div class="result-box mt-3" id="structure-result">انتظار...</div>
                    </div>
                </div>
            </div>

            <!-- الخطوة 2: جلب الإعدادات -->
            <div class="col-md-6">
                <div class="card test-card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-download me-2"></i>الخطوة 2: جلب الإعدادات</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-info btn-sm" onclick="fetchSettings()">
                            <i class="fas fa-play me-1"></i>جلب
                        </button>
                        <div class="result-box mt-3" id="fetch-result">انتظار...</div>
                    </div>
                </div>
            </div>

            <!-- الخطوة 3: اختبار الحفظ -->
            <div class="col-md-6">
                <div class="card test-card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-save me-2"></i>الخطوة 3: اختبار الحفظ</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label">اختر الوثائق المفعلة:</label>
                            <div id="docs-checkboxes" class="mb-2"></div>
                        </div>
                        <button class="btn btn-success btn-sm" onclick="testSave()">
                            <i class="fas fa-save me-1"></i>حفظ
                        </button>
                        <div class="result-box mt-3" id="save-result">انتظار...</div>
                    </div>
                </div>
            </div>

            <!-- الخطوة 4: التحقق من قاعدة البيانات -->
            <div class="col-md-6">
                <div class="card test-card mb-3">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>الخطوة 4: فحص قاعدة البيانات</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-warning btn-sm" onclick="checkDatabase()">
                            <i class="fas fa-search me-1"></i>فحص
                        </button>
                        <div class="result-box mt-3" id="db-result">انتظار...</div>
                    </div>
                </div>
            </div>

            <!-- الخطوة 5: فحص Logs -->
            <div class="col-12">
                <div class="card test-card mb-3">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>الخطوة 5: سجلات الأخطاء</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-danger btn-sm" onclick="checkLogs()">
                            <i class="fas fa-eye me-1"></i>عرض السجلات
                        </button>
                        <div class="result-box mt-3" id="logs-result" style="max-height: 400px;">انتظار...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- زر تشغيل جميع الاختبارات -->
        <div class="row">
            <div class="col-12 text-center mb-4">
                <button class="btn btn-lg btn-primary" onclick="runAllTests()">
                    <i class="fas fa-play-circle me-2"></i>تشغيل جميع الاختبارات
                </button>
            </div>
        </div>
    </div>

    <script>
        const sponsorId = 5;
        let allDocs = [];

        function log(elementId, message, isError = false) {
            const element = $(`#${elementId}`);
            const timestamp = new Date().toLocaleTimeString('ar-EG');
            const prefix = isError ? '❌' : '✅';
            element.html(`[${timestamp}] ${prefix} ${message}\n\n${element.html()}`);

            // تغيير لون البطاقة
            const card = element.closest('.card');
            card.removeClass('success-card error-card');
            card.addClass(isError ? 'error-card' : 'success-card');
        }

        async function checkStructure() {
            log('structure-result', 'جاري فحص البنية...');

            try {
                // 1. فحص CSRF Token
                const token = $('meta[name="csrf-token"]').attr('content');
                log('structure-result', `CSRF Token: ${token ? 'موجود' : 'غير موجود'}`);

                // 2. فحص jQuery
                log('structure-result', `jQuery Version: ${$.fn.jquery}`);

                // 3. فحص Sponsor ID
                log('structure-result', `Sponsor ID: ${sponsorId}`);

                // 4. فحص الاتصال
                const response = await fetch('/admin/sponsors/fields-management/data', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                log('structure-result', `الاتصال بالسيرفر: ${response.ok ? 'نجح' : 'فشل'}`);

            } catch (error) {
                log('structure-result', `خطأ: ${error.message}`, true);
            }
        }

        async function fetchSettings() {
            log('fetch-result', 'جاري جلب الإعدادات...');

            try {
                const response = await $.ajax({
                    url: `/admin/sponsors/${sponsorId}/documents`,
                    method: 'GET'
                });

                log('fetch-result', `عدد الوثائق: ${response.data.length}`);
                log('fetch-result', JSON.stringify(response, null, 2));

                allDocs = response.data;
                displayDocsCheckboxes();

            } catch (error) {
                log('fetch-result', `خطأ: ${error.responseText || error.message}`, true);
            }
        }

        function displayDocsCheckboxes() {
            let html = '';
            allDocs.forEach(doc => {
                html += `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="doc${doc.id}" value="${doc.id}" ${doc.is_enabled ? 'checked' : ''}>
                        <label class="form-check-label" for="doc${doc.id}">${doc.id}-${doc.description}</label>
                    </div>
                `;
            });
            $('#docs-checkboxes').html(html);
        }

        async function testSave() {
            log('save-result', 'جاري الحفظ...');

            const documents = [];
            $('#docs-checkboxes input[type="checkbox"]').each(function() {
                documents.push({
                    document_type_id: parseInt($(this).val()),
                    is_enabled: $(this).is(':checked')
                });
            });

            log('save-result', `عدد الوثائق: ${documents.length}`);
            log('save-result', `البيانات المرسلة: ${JSON.stringify(documents)}`);

            try {
                const response = await $.ajax({
                    url: `/admin/sponsors/${sponsorId}/documents`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify({ documents: documents }),
                    dataType: 'json'
                });

                log('save-result', 'الحفظ نجح!');
                log('save-result', JSON.stringify(response, null, 2));

                // تحديث قاعدة البيانات فوراً
                setTimeout(checkDatabase, 500);

            } catch (error) {
                log('save-result', `خطأ في الحفظ: ${error.responseText || error.message}`, true);
                if (error.responseJSON) {
                    log('save-result', JSON.stringify(error.responseJSON, null, 2), true);
                }
            }
        }

        async function checkDatabase() {
            log('db-result', 'جاري فحص قاعدة البيانات...');

            try {
                const response = await $.ajax({
                    url: `/admin/sponsors/${sponsorId}/check-field-settings`,
                    method: 'GET'
                });

                log('db-result', JSON.stringify(response, null, 2));

            } catch (error) {
                log('db-result', `خطأ: ${error.responseText || error.message}`, true);
            }
        }

        async function checkLogs() {
            log('logs-result', 'جاري جلب السجلات...');

            // محاكاة جلب السجلات (يجب إنشاء route لهذا)
            log('logs-result', 'يرجى فحص ملف storage/logs/laravel.log يدوياً');
        }

        async function runAllTests() {
            await checkStructure();
            await new Promise(resolve => setTimeout(resolve, 1000));
            await fetchSettings();
            await new Promise(resolve => setTimeout(resolve, 1000));
            // testSave يجب تشغيله يدوياً
            await checkDatabase();
        }

        // تشغيل تلقائي عند التحميل
        $(document).ready(function() {
            runAllTests();
        });
    </script>
</body>
</html>
