<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار عرض الوثائق</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            padding: 20px;
            background: #f5f5f5;
        }
        .test-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .log-entry {
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .log-info { background: #e3f2fd; color: #1565c0; }
        .log-success { background: #e8f5e9; color: #2e7d32; }
        .log-error { background: #ffebee; color: #c62828; }
        .log-warning { background: #fff3e0; color: #ef6c00; }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-header text-center">
            <h1><i class="fas fa-file-check me-3"></i>اختبار عرض الوثائق</h1>
            <p class="mb-0">اختبار مباشر لتحميل وعرض الوثائق من API</p>
        </div>

        <div class="row mb-4">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>لوحة التحكم</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">اختر الجمعية:</label>
                            <select class="form-select form-select-lg" id="sponsor_select">
                                <option value="">-- اختر جمعية --</option>
                                @foreach(\App\Models\Sponsor::all() as $sponsor)
                                    <option value="{{ $sponsor->id }}">{{ $sponsor->sponsor_name }} (ID: {{ $sponsor->id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="btn btn-success btn-lg w-100" id="load_btn">
                            <i class="fas fa-download me-2"></i>تحميل الوثائق
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>سجل العمليات (Console)</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="console_log"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>قائمة الوثائق</h5>
                        <span id="docs_count" class="badge bg-light text-dark">0 وثيقة</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th>اسم الوثيقة</th>
                                        <th style="width: 80px;">البادئة</th>
                                        <th style="width: 100px;">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody id="documents_table">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="fas fa-file-circle-question fa-3x mb-3 d-block"></i>
                                            اختر جمعية وانقر على "تحميل الوثائق"
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-code me-2"></i>Response الخام (JSON)</h5>
                    </div>
                    <div class="card-body">
                        <pre id="raw_response" class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        function log(message, type = 'info') {
            const time = new Date().toLocaleTimeString('ar-EG');
            const icons = {
                info: 'fa-info-circle',
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle'
            };

            const html = `
                <div class="log-entry log-${type}">
                    <i class="fas ${icons[type]} me-2"></i>
                    <strong>[${time}]</strong> ${message}
                </div>
            `;

            $('#console_log').prepend(html);
            console.log(`[${type.toUpperCase()}] ${message}`);
        }

        $('#load_btn').click(function() {
            const sponsorId = $('#sponsor_select').val();

            if (!sponsorId) {
                log('⚠️ يجب اختيار جمعية أولاً', 'warning');
                return;
            }

            log(`📥 بدء تحميل الوثائق للجمعية ID: ${sponsorId}`, 'info');

            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري التحميل...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'GET',
                success: function(response) {
                    log('✅ تم استلام الرد من API بنجاح', 'success');

                    // عرض الـ JSON الخام
                    $('#raw_response').text(JSON.stringify(response, null, 2));

                    if (response.success && response.data) {
                        const docs = response.data;
                        log(`✅ تم تحميل ${docs.length} وثيقة`, 'success');

                        // عرض الوثائق في الجدول
                        renderDocuments(docs);

                        // عد الوثائق المفعلة
                        const enabledCount = docs.filter(d => d.is_enabled).length;
                        log(`📊 عدد الوثائق المفعلة: ${enabledCount} من ${docs.length}`, 'info');

                    } else {
                        log('❌ البيانات غير صحيحة في الرد', 'error');
                    }

                    $('#load_btn').prop('disabled', false).html('<i class="fas fa-download me-2"></i>تحميل الوثائق');
                },
                error: function(xhr, status, error) {
                    log(`❌ خطأ في API: ${error}`, 'error');
                    log(`📋 الحالة: ${xhr.status} - ${xhr.statusText}`, 'error');

                    if (xhr.responseJSON) {
                        $('#raw_response').text(JSON.stringify(xhr.responseJSON, null, 2));
                    }

                    $('#load_btn').prop('disabled', false).html('<i class="fas fa-download me-2"></i>تحميل الوثائق');
                }
            });
        });

        function renderDocuments(docs) {
            const tbody = $('#documents_table');
            tbody.empty();

            if (docs.length === 0) {
                tbody.html('<tr><td colspan="4" class="text-center py-4">لا توجد وثائق</td></tr>');
                return;
            }

            docs.forEach(function(doc) {
                const statusBadge = doc.is_enabled
                    ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>مفعل</span>'
                    : '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>معطل</span>';

                const row = `
                    <tr>
                        <td class="fw-bold">${doc.id}</td>
                        <td>
                            <i class="fas fa-file-pdf text-danger me-2"></i>
                            ${doc.description}
                        </td>
                        <td><span class="badge bg-primary">${doc.pref}</span></td>
                        <td>${statusBadge}</td>
                    </tr>
                `;

                tbody.append(row);
            });

            $('#docs_count').text(`${docs.length} وثيقة`);
            log(`📄 تم عرض جميع الوثائق في الجدول`, 'success');
        }

        // تحميل تلقائي عند اختيار جمعية
        $('#sponsor_select').change(function() {
            if ($(this).val()) {
                log(`🔄 تم اختيار الجمعية: ${$(this).find('option:selected').text()}`, 'info');
            }
        });

        // رسالة ترحيب
        log('🚀 نظام اختبار الوثائق جاهز', 'success');
    </script>
</body>
</html>
