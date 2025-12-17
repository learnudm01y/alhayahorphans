<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار النظام الجديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>🧪 اختبار نظام الوثائق الجديد</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    هذه صفحة اختبار مستقلة للتأكد من أن النظام الجديد يعمل بشكل صحيح
                </div>

                <button class="btn btn-primary btn-lg" onclick="testSystem()">
                    <i class="fas fa-play me-2"></i>
                    تشغيل الاختبار
                </button>

                <hr>

                <div id="test_results" class="mt-4"></div>

                <hr>

                <!-- محاكاة الـ UI -->
                <div id="documents_tab">
                    <h4>محاكاة الواجهة:</h4>

                    <div id="documents_loading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2">جاري التحميل...</p>
                    </div>

                    <div id="documents_list">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="50"><input type="checkbox" id="select_all_docs"></th>
                                    <th>ID</th>
                                    <th>الوثيقة</th>
                                    <th>البادئة</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody id="documents_tbody"></tbody>
                        </table>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span id="enabled_count">0</span> وثيقة مفعلة
                            </div>
                            <button class="btn btn-success" id="save_documents_btn">
                                <i class="fas fa-save me-2"></i>
                                حفظ التغييرات
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/documents-management-new.js') }}"></script>

    <script>
        function log(msg, type = 'info') {
            const colors = {
                success: 'success',
                error: 'danger',
                info: 'info',
                warning: 'warning'
            };

            const html = `
                <div class="alert alert-${colors[type]} alert-dismissible fade show" role="alert">
                    <strong>[${new Date().toLocaleTimeString()}]</strong> ${msg}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            $('#test_results').prepend(html);
        }

        function testSystem() {
            log('🚀 بدء الاختبار...', 'info');

            const sponsorId = 5;

            // اختبار التحميل
            log(`📥 اختبار تحميل الوثائق للجمعية ${sponsorId}`, 'info');

            if (typeof loadDocuments === 'function') {
                log('✅ وظيفة loadDocuments موجودة', 'success');
                loadDocuments(sponsorId);

                setTimeout(() => {
                    const docsCount = $('#documents_tbody tr').length;
                    if (docsCount > 0) {
                        log(`✅ تم تحميل ${docsCount} وثيقة بنجاح`, 'success');
                    } else {
                        log('⚠️ لم يتم تحميل أي وثائق', 'warning');
                    }
                }, 2000);
            } else {
                log('❌ وظيفة loadDocuments غير موجودة!', 'error');
            }
        }
    </script>
</body>
</html>
