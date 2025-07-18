<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار الإحصائيات الحقيقية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bg-gradient-success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🔍 اختبار الإحصائيات الحقيقية</h1>

        <!-- Real Database Statistics Card -->
        <div class="row mb-4">
            <div class="col-12 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-database me-2"></i>
                                    الإحصائيات الحقيقية من قاعدة البيانات
                                </h5>
                                <small class="opacity-75">العدد الفعلي للملفات المكررة المحفوظة في النظام</small>
                            </div>
                            <button class="btn btn-outline-light btn-sm" id="refreshRealStatsBtn">
                                <i class="fas fa-sync-alt me-1"></i>
                                تحديث
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="border-end">
                                    <h3 class="text-success mb-1" id="realTotalFiles">-</h3>
                                    <small class="text-muted">إجمالي الملفات</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="border-end">
                                    <h3 class="text-primary mb-1" id="realActiveFiles">-</h3>
                                    <small class="text-muted">الملفات النشطة</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="border-end">
                                    <h3 class="text-warning mb-1" id="realExpiredFiles">-</h3>
                                    <small class="text-muted">الملفات المنتهية</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="border-end">
                                    <h3 class="text-info mb-1" id="realTotalImages">-</h3>
                                    <small class="text-muted">الصور</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="border-end">
                                    <h3 class="text-secondary mb-1" id="realTotalDocuments">-</h3>
                                    <small class="text-muted">المستندات</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <h3 class="text-dark mb-1" id="realTotalSize">-</h3>
                                <small class="text-muted">المساحة المستخدمة</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Information -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>معلومات التشخيص</h5>
                    </div>
                    <div class="card-body">
                        <div id="debugInfo"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class RealStatsTest {
            constructor() {
                console.log('🚀 إنشاء RealStatsTest');
                this.init();
                this.loadRealStatistics();
            }

            init() {
                // Initialize event listeners
                $('#refreshRealStatsBtn').on('click', () => this.loadRealStatistics());

                // Set CSRF token for all AJAX requests
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
            }

            addDebugInfo(message) {
                const debugInfo = $('#debugInfo');
                const timestamp = new Date().toLocaleTimeString('ar-SA');
                debugInfo.append(`<div class="mb-2"><small class="text-muted">${timestamp}</small> ${message}</div>`);
            }

            async loadRealStatistics() {
                try {
                    console.log('🔄 تحميل الإحصائيات الحقيقية من قاعدة البيانات...');
                    this.addDebugInfo('🔄 بدء تحميل الإحصائيات الحقيقية...');

                    // إظهار loading state
                    $('#realTotalFiles, #realActiveFiles, #realExpiredFiles, #realTotalImages, #realTotalDocuments, #realTotalSize').text('...');

                    const url = '{{ route("admin.duplicate.files.real.statistics") }}';
                    this.addDebugInfo(`📡 استدعاء URL: ${url}`);

                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    this.addDebugInfo(`📊 حالة الاستجابة: ${response.status} ${response.statusText}`);
                    console.log('📡 استجابة الخادم:', response.status, response.statusText);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('✅ البيانات المستلمة:', data);
                    this.addDebugInfo(`✅ البيانات المستلمة: <pre>${JSON.stringify(data, null, 2)}</pre>`);

                    if (data.success) {
                        console.log('📊 تحديث الإحصائيات الحقيقية:', data.data);
                        this.updateRealStatistics(data.data);
                        this.addDebugInfo('✅ تم تحديث الإحصائيات بنجاح');
                    } else {
                        throw new Error(data.message || 'فشل في تحميل الإحصائيات الحقيقية');
                    }
                } catch (error) {
                    console.error('❌ خطأ في تحميل الإحصائيات الحقيقية:', error);
                    this.addDebugInfo(`❌ خطأ: ${error.message}`);

                    // إظهار خطأ في العناصر
                    $('#realTotalFiles, #realActiveFiles, #realExpiredFiles, #realTotalImages, #realTotalDocuments, #realTotalSize').text('خطأ');
                }
            }

            updateRealStatistics(statistics) {
                console.log('🔄 تحديث عناصر الإحصائيات الحقيقية:', statistics);
                this.addDebugInfo('🔄 تحديث عناصر DOM...');

                $('#realTotalFiles').text(statistics.total_files || 0);
                $('#realActiveFiles').text(statistics.active_files || 0);
                $('#realExpiredFiles').text(statistics.expired_files || 0);
                $('#realTotalImages').text(statistics.total_images || 0);
                $('#realTotalDocuments').text(statistics.total_documents || 0);
                $('#realTotalSize').text(this.formatFileSize(statistics.total_size || 0));

                console.log('✅ تم تحديث جميع عناصر الإحصائيات الحقيقية');
                this.addDebugInfo('✅ تم تحديث جميع عناصر DOM');
            }

            formatFileSize(bytes) {
                if (!bytes || bytes === 0) return '0 بايت';

                const sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
                const i = Math.floor(Math.log(bytes) / Math.log(1024));

                return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
            }
        }

        // Initialize when document is ready
        $(document).ready(function() {
            console.log('🚀 تحميل صفحة اختبار الإحصائيات الحقيقية');
            window.realStatsTest = new RealStatsTest();
        });
    </script>
</body>
</html>
