<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار نظام إدارة الملفات المكررة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/duplicate-files-enhanced.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-vials me-2"></i>
                            اختبار نظام إدارة الملفات المكررة
                        </h4>
                    </div>
                    <div class="card-body">

                        <!-- اختبار إنشاء بيانات وهمية -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-database me-2"></i>
                                    إنشاء بيانات اختبار
                                </h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-primary" onclick="createTestData()">
                                        <i class="fas fa-plus me-1"></i>
                                        إنشاء ملفات مكررة للاختبار
                                    </button>
                                    <button class="btn btn-outline-success" onclick="createImageDuplicates()">
                                        <i class="fas fa-image me-1"></i>
                                        إنشاء صور مكررة
                                    </button>
                                    <button class="btn btn-outline-info" onclick="createDocumentDuplicates()">
                                        <i class="fas fa-file-alt me-1"></i>
                                        إنشاء مستندات مكررة
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="clearTestData()">
                                        <i class="fas fa-trash me-1"></i>
                                        مسح بيانات الاختبار
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- اختبار النظام -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="text-success mb-3">
                                    <i class="fas fa-cogs me-2"></i>
                                    اختبار وظائف النظام
                                </h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-success" onclick="testPagination()">
                                        <i class="fas fa-list me-1"></i>
                                        اختبار pagination
                                    </button>
                                    <button class="btn btn-info" onclick="testSearch()">
                                        <i class="fas fa-search me-1"></i>
                                        اختبار البحث
                                    </button>
                                    <button class="btn btn-warning" onclick="testFilters()">
                                        <i class="fas fa-filter me-1"></i>
                                        اختبار المرشحات
                                    </button>
                                    <button class="btn btn-danger" onclick="testBulkDelete()">
                                        <i class="fas fa-trash-alt me-1"></i>
                                        اختبار الحذف المتعدد
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- روابط سريعة -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="text-info mb-3">
                                    <i class="fas fa-external-link-alt me-2"></i>
                                    روابط سريعة
                                </h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="/admin/duplicate-files" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-folder-open me-1"></i>
                                        صفحة إدارة الملفات المكررة
                                    </a>
                                    <a href="/admin/file-manager" class="btn btn-secondary" target="_blank">
                                        <i class="fas fa-hdd me-1"></i>
                                        مدير الملفات
                                    </a>
                                    <button class="btn btn-outline-primary" onclick="showDuplicateModal()">
                                        <i class="fas fa-window-maximize me-1"></i>
                                        اختبار Modal
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- معلومات النظام -->
                        <div class="row">
                            <div class="col-12">
                                <h5 class="text-secondary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    معلومات النظام
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">الإحصائيات الحالية</h6>
                                                <div id="currentStats">
                                                    جاري التحميل...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">حالة النظام</h6>
                                                <div id="systemStatus">
                                                    جاري الفحص...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- سجل الأحداث -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h5 class="text-muted mb-3">
                                    <i class="fas fa-history me-2"></i>
                                    سجل أحداث الاختبار
                                </h5>
                                <div class="card border-0 bg-dark text-light">
                                    <div class="card-body">
                                        <pre id="testLog" style="max-height: 300px; overflow-y: auto; font-size: 0.875rem;">
جاهز لبدء الاختبار...
                                        </pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the duplicate files modal -->
    @include('file-management.modalDublicateFiles')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // نظام تسجيل الأحداث
        function log(message, type = 'info') {
            const logElement = document.getElementById('testLog');
            const timestamp = new Date().toLocaleTimeString('ar-SA');
            const icons = {
                'info': '🔵',
                'success': '✅',
                'warning': '⚠️',
                'error': '❌'
            };

            const logEntry = `[${timestamp}] ${icons[type]} ${message}\n`;
            logElement.textContent += logEntry;
            logElement.scrollTop = logElement.scrollHeight;
        }

        // إنشاء بيانات اختبار
        async function createTestData() {
            log('بدء إنشاء بيانات اختبار عامة...');

            try {
                const response = await fetch('/admin/file/create-test-duplicates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || 'test',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        count: 25,
                        types: ['image', 'document', 'video', 'other']
                    })
                });

                const data = await response.json();

                if (data.success) {
                    log(`تم إنشاء ${data.created_count} ملف مكرر بنجاح`, 'success');
                    updateStats();
                } else {
                    log(`فشل في إنشاء البيانات: ${data.message}`, 'error');
                }
            } catch (error) {
                log(`خطأ في إنشاء البيانات: ${error.message}`, 'error');
            }
        }

        async function createImageDuplicates() {
            log('بدء إنشاء صور مكررة...');

            try {
                // محاكاة إنشاء صور مكررة
                const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                const testImages = [];

                for (let i = 0; i < 10; i++) {
                    testImages.push({
                        original_name: `test_image_${i + 1}.jpg`,
                        duplicate_name: `duplicate_image_${i + 1}_${Date.now()}.jpg`,
                        mime_type: imageTypes[i % imageTypes.length],
                        file_size: Math.floor(Math.random() * 2000000) + 100000, // 100KB - 2MB
                        temp_path: `temp/duplicates/images/test_image_${i + 1}.jpg`,
                        session_id: 'test_session_images_' + Date.now()
                    });
                }

                log(`تم إنشاء ${testImages.length} صورة مكررة للاختبار`, 'success');
                updateStats();
            } catch (error) {
                log(`خطأ في إنشاء الصور المكررة: ${error.message}`, 'error');
            }
        }

        async function createDocumentDuplicates() {
            log('بدء إنشاء مستندات مكررة...');

            try {
                // محاكاة إنشاء مستندات مكررة
                const docTypes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain'
                ];
                const testDocs = [];

                for (let i = 0; i < 15; i++) {
                    const type = docTypes[i % docTypes.length];
                    const extension = type.includes('pdf') ? 'pdf' :
                                    type.includes('word') ? 'docx' : 'txt';

                    testDocs.push({
                        original_name: `document_${i + 1}.${extension}`,
                        duplicate_name: `duplicate_doc_${i + 1}_${Date.now()}.${extension}`,
                        mime_type: type,
                        file_size: Math.floor(Math.random() * 5000000) + 50000, // 50KB - 5MB
                        temp_path: `temp/duplicates/documents/document_${i + 1}.${extension}`,
                        session_id: 'test_session_docs_' + Date.now()
                    });
                }

                log(`تم إنشاء ${testDocs.length} مستند مكرر للاختبار`, 'success');
                updateStats();
            } catch (error) {
                log(`خطأ في إنشاء المستندات المكررة: ${error.message}`, 'error');
            }
        }

        async function clearTestData() {
            if (!confirm('هل أنت متأكد من حذف جميع بيانات الاختبار؟')) {
                return;
            }

            log('بدء مسح بيانات الاختبار...');

            try {
                const response = await fetch('/admin/duplicate-files/bulk-delete', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || 'test',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        clear_all_test_data: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    log('تم مسح بيانات الاختبار بنجاح', 'success');
                    updateStats();
                } else {
                    log(`فشل في مسح البيانات: ${data.message}`, 'error');
                }
            } catch (error) {
                log(`خطأ في مسح البيانات: ${error.message}`, 'error');
            }
        }

        // اختبار الوظائف
        async function testPagination() {
            log('اختبار pagination...');

            try {
                const response = await fetch('/admin/duplicate-files/paginated?page=1&per_page=10', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    log(`نجح اختبار pagination - تم جلب ${data.data.files.length} ملف`, 'success');
                    log(`صفحة ${data.data.pagination.current_page} من ${data.data.pagination.last_page}`, 'info');
                } else {
                    log(`فشل اختبار pagination: ${data.message}`, 'error');
                }
            } catch (error) {
                log(`خطأ في اختبار pagination: ${error.message}`, 'error');
            }
        }

        async function testSearch() {
            log('اختبار البحث...');

            try {
                const searchTerms = ['test', 'image', 'document', 'duplicate'];

                for (const term of searchTerms) {
                    const response = await fetch(`/admin/duplicate-files/paginated?search=${term}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        log(`البحث عن "${term}": ${data.data.files.length} نتيجة`, 'info');
                    }
                }

                log('انتهى اختبار البحث', 'success');
            } catch (error) {
                log(`خطأ في اختبار البحث: ${error.message}`, 'error');
            }
        }

        async function testFilters() {
            log('اختبار المرشحات...');

            try {
                const filters = [
                    { file_type: 'image', name: 'الصور' },
                    { file_type: 'document', name: 'المستندات' },
                    { status: 'active', name: 'النشطة' },
                    { status: 'expired', name: 'منتهية الصلاحية' }
                ];

                for (const filter of filters) {
                    const params = new URLSearchParams(filter);
                    const response = await fetch(`/admin/duplicate-files/paginated?${params}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        log(`مرشح ${filter.name}: ${data.data.files.length} ملف`, 'info');
                    }
                }

                log('انتهى اختبار المرشحات', 'success');
            } catch (error) {
                log(`خطأ في اختبار المرشحات: ${error.message}`, 'error');
            }
        }

        async function testBulkDelete() {
            log('اختبار الحذف المتعدد...');

            try {
                // جلب بعض الملفات للحذف
                const response = await fetch('/admin/duplicate-files/paginated?per_page=5', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success && data.data.files.length > 0) {
                    const fileIds = data.data.files.map(file => file.id);

                    if (confirm(`هل تريد حذف ${fileIds.length} ملف للاختبار؟`)) {
                        const deleteResponse = await fetch('/admin/duplicate-files/bulk-delete', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || 'test',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ file_ids: fileIds })
                        });

                        const deleteData = await deleteResponse.json();

                        if (deleteData.success) {
                            log(`تم حذف ${deleteData.data.deleted_count} ملف بنجاح`, 'success');
                            updateStats();
                        } else {
                            log(`فشل في الحذف المتعدد: ${deleteData.message}`, 'error');
                        }
                    }
                } else {
                    log('لا توجد ملفات للحذف', 'warning');
                }
            } catch (error) {
                log(`خطأ في اختبار الحذف المتعدد: ${error.message}`, 'error');
            }
        }

        // إظهار modal
        function showDuplicateModal() {
            log('اختبار عرض modal الملفات المكررة...');

            // محاكاة استدعاء modal
            const sessionId = 'test_session_' + Date.now();

            if (typeof showDuplicateFilesModal === 'function') {
                showDuplicateFilesModal(sessionId);
                log('تم فتح modal بنجاح', 'success');
            } else {
                // فتح modal يدوياً
                const modal = new bootstrap.Modal(document.getElementById('duplicateFilesModal'));
                modal.show();
                log('تم فتح modal يدوياً', 'info');
            }
        }

        // تحديث الإحصائيات
        async function updateStats() {
            try {
                const response = await fetch('/admin/duplicate-files/paginated?per_page=1', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success && data.data.statistics) {
                    const stats = data.data.statistics;
                    document.getElementById('currentStats').innerHTML = `
                        <div class="row text-center">
                            <div class="col-3">
                                <strong class="text-primary">${stats.total_files}</strong>
                                <br><small>إجمالي الملفات</small>
                            </div>
                            <div class="col-3">
                                <strong class="text-warning">${stats.total_images}</strong>
                                <br><small>الصور</small>
                            </div>
                            <div class="col-3">
                                <strong class="text-info">${stats.total_documents}</strong>
                                <br><small>المستندات</small>
                            </div>
                            <div class="col-3">
                                <strong class="text-success">${formatFileSize(stats.total_size)}</strong>
                                <br><small>المساحة</small>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('currentStats').innerHTML =
                    '<span class="text-danger">خطأ في جلب الإحصائيات</span>';
            }
        }

        // فحص حالة النظام
        async function checkSystemStatus() {
            try {
                const checks = [
                    { name: 'API متاح', url: '/admin/duplicate-files/paginated?per_page=1' },
                    { name: 'قاعدة البيانات', url: '/admin/test-connection' }
                ];

                let status = '<div class="d-flex flex-column gap-1">';

                for (const check of checks) {
                    try {
                        const response = await fetch(check.url, {
                            headers: { 'Accept': 'application/json' }
                        });

                        const isOk = response.ok;
                        status += `<div class="d-flex align-items-center gap-2">
                            <i class="fas ${isOk ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'}"></i>
                            <span>${check.name}</span>
                        </div>`;
                    } catch (error) {
                        status += `<div class="d-flex align-items-center gap-2">
                            <i class="fas fa-times-circle text-danger"></i>
                            <span>${check.name}</span>
                        </div>`;
                    }
                }

                status += '</div>';
                document.getElementById('systemStatus').innerHTML = status;
            } catch (error) {
                document.getElementById('systemStatus').innerHTML =
                    '<span class="text-danger">خطأ في فحص النظام</span>';
            }
        }

        // دالة مساعدة لتنسيق حجم الملف
        function formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 بايت';

            const sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));

            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        // تشغيل عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            log('تم تحميل صفحة اختبار نظام إدارة الملفات المكررة', 'success');
            updateStats();
            checkSystemStatus();
        });
    </script>
</body>
</html>
