@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header p-4" style="background: #dc3545; border-radius: .5rem .5rem 0 0;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="color: #fff; font-weight: bold; font-size: 1.5rem; letter-spacing: 1px;">
                                <i class="bi bi-list-check me-2" style="color: #fff;"></i>
                                قائمة الوفيات
                            </span>
                            <a href="{{ route('civil-registry.index') }}" class="btn btn-light btn-sm">
                                <i class="bi bi-arrow-right me-1"></i> العودة للسجل المدني
                            </a>
                        </div>

                        <div class="d-flex flex-wrap justify-content-start gap-2">
                            <button type="button" class="btn btn-light btn-sm d-flex align-items-center"
                                    data-bs-toggle="modal" data-bs-target="#deathsImportModal">
                                <i class="bi bi-upload me-1"></i> رفع قائمة الوفيات
                            </button>
                            <a href="{{ route('civil-registry.deaths.template') }}" class="btn btn-outline-light btn-sm d-flex align-items-center">
                                <i class="bi bi-file-earmark-excel me-1"></i> تنزيل القالب
                            </a>
                            <form method="POST" action="{{ route('civil-registry.deaths.merge') }}" id="deathsMergeForm">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm d-flex align-items-center">
                                    <i class="bi bi-arrow-repeat me-1"></i> إعادة الدمج مع السجل المدني
                                </button>
                            </form>
                        </div>

                        <div class="mt-3">
                            <span class="badge bg-light text-dark me-1">الإجمالي: {{ $total }}</span>
                            <span class="badge bg-success me-1">مدموج: {{ $matched }}</span>
                            <span class="badge bg-secondary">غير مدموج: {{ $unmatched }}</span>
                        </div>
                    </div>

                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        {!! $dataTable->table(['class' => 'table table-bordered table-striped text-center align-middle w-100'], true) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal رفع قائمة الوفيات -->
    <div class="modal fade" id="deathsImportModal" tabindex="-1" aria-labelledby="deathsImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deathsImportModalLabel">
                        <i class="bi bi-upload me-2"></i> رفع قائمة الوفيات
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('civil-registry.deaths.import') }}" enctype="multipart/form-data" id="deathsImportForm">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>طريقة الاستخدام:</strong>
                            <ol class="mb-0 mt-2">
                                <li>حمّل <a href="{{ route('civil-registry.deaths.template') }}" target="_blank" class="alert-link">قالب قائمة الوفيات</a> واملأه (رقم الهوية، الاسم، الحالة).</li>
                                <li>اختر ملفاً واحداً أو أكثر ثم اضغط "رفع".</li>
                                <li>تُدمج أرقام الهوية تلقائياً مع السجل المدني (تُعلَّم كمتوفى).</li>
                            </ol>
                        </div>

                        <div class="mb-3">
                            <label for="deaths_excel_files" class="form-label">ملفات Excel <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="deaths_excel_files" name="excel_files[]"
                                   accept=".xlsx,.xls,.csv" multiple required>
                            <div class="form-text">الصيغ المدعومة: XLSX / XLS / CSV (الحد الأقصى 10MB لكل ملف)</div>
                        </div>

                        <div class="alert alert-light border">
                            <strong>الأعمدة المتوقعة:</strong>
                            <span class="text-muted">رقم الهوية (مطلوب)، الاسم، الحالة — يدعم الرؤوس العربية أو الإنجليزية</span>
                        </div>

                        <div id="deathsImportProgress" class="d-none">
                            <div class="card border-0 mb-2">
                                <div class="card-header bg-danger text-white py-2 small">
                                    <i class="bi bi-bar-chart-steps me-2"></i> تقدّم رفع الملفات
                                </div>
                                <div class="card-body py-3">
                                    <div id="deathsImportFileRows"></div>
                                </div>
                            </div>
                            <div id="deathsImportSummary" class="alert d-none mb-0 small"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </button>
                        <button type="submit" class="btn btn-danger" id="deathsImportSubmitBtn">
                            <i class="bi bi-upload"></i> رفع البيانات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تقرير أخطاء الرفع -->
    <div class="modal fade" id="deathsImportErrorsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i> تقرير أخطاء الرفع</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <button type="button" id="deathsErrorsDownloadBtn" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-download me-1"></i> تحميل التقرير (TXT)
                        </button>
                    </div>
                    <div id="deathsErrorsList" class="border rounded p-2 bg-light" style="max-height:400px;overflow:auto;white-space:pre-wrap;font-size:12px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var lastErrors = [];

            function showErrorsReport(err) {
                var lines = err.map(function (s) { return '• ' + s; }).join('\n') || 'لا توجد أخطاء مسجلة.';
                $('#deathsErrorsList').text(lines);
                var modal = $('#deathsImportErrorsModal');
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modal[0]).show();
                } else {
                    modal.modal('show');
                }
            }

            $('#deathsErrorsDownloadBtn').on('click', function () {
                var text = 'تقرير أخطاء رفع قائمة الوفيات\n' +
                    'عدد الأخطاء: ' + lastErrors.length + '\n' +
                    '=============================================\n' +
                    lastErrors.map(function (s) { return '- ' + s; }).join('\n');
                var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'تقرير_أخطاء_الوفيات.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
            });

            // تأكيد إعادة الدجم
            $('#deathsMergeForm').on('submit', function (e) {
                if (!confirm('سيتم تطبيق الوفيات على السجل المدني. هل تريد المتابعة؟')) {
                    e.preventDefault();
                }
            });

            // رفع عدة ملفات واحداً تلو الآخر
            $('#deathsImportForm').on('submit', function (e) {
                e.preventDefault();
                var files = $('#deaths_excel_files')[0] ? $('#deaths_excel_files')[0].files : [];
                if (!files.length) return;

                var total = files.length;
                var summary = { imported: 0, merged: 0, not_found: 0, duplicates: 0, errors: 0 };
                var allErrors = [];
                var token = $('meta[name="csrf-token"]').attr('content');
                var url = @json(route('civil-registry.deaths.import'));

                $('#deathsImportSummary').addClass('d-none').empty();
                $('#deathsImportFileRows').empty();
                $('#deathsImportProgress').removeClass('d-none');
                $('#deathsImportSubmitBtn').prop('disabled', true);

                $.each(files, function (i, file) {
                    var rowId = 'file-row-' + i;
                    var nameSafe = $('<div>').text(file.name).html();
                    $('<div class="row-imp mb-2" id="' + rowId + '">' +
                        '<div class="d-flex justify-content-between small mb-1">' +
                        '<span class="text-truncate me-2">' + nameSafe + '</span>' +
                        '<span class="file-status text-muted text-nowrap">بانتظار الرفع</span>' +
                        '</div>' +
                        '<div class="progress" style="height:14px;">' +
                        '<div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width:0%"></div>' +
                        '</div>' +
                        '</div>').appendTo('#deathsImportFileRows');
                });

                function nextFile(i) {
                    if (i >= total) { finish(); return; }
                    var file = files[i];
                    var $row = $('#file-row-' + i);
                    var $bar = $row.find('.progress-bar');
                    var $status = $row.find('.file-status');

                    var fd = new FormData();
                    fd.append('excel_files[]', file);

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', url, true);
                    xhr.setRequestHeader('X-CSRF-TOKEN', token);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.upload.onprogress = function (ev) {
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            if (pct > 90) pct = 90;
                            $bar.css('width', pct + '%');
                            $status.text('جاري الرفع ' + pct + '%').removeClass('text-muted text-success text-danger').addClass('text-primary');
                        }
                    };
                    xhr.onload = function () {
                        var resp = null;
                        try { resp = JSON.parse(xhr.responseText); } catch (err) {}
                        if (xhr.status >= 200 && xhr.status < 300 && resp && (resp.success || resp.imported > 0 || resp.merged > 0)) {
                            $bar.css('width', '100%').removeClass('bg-danger').addClass('bg-success');
                            $status.text('تم: رفع ' + resp.imported + '، دمج ' + (resp.merged || 0) + '، مكرر ' + (resp.duplicates || 0))
                                   .removeClass('text-muted text-primary text-danger').addClass('text-success');
                            summary.imported += resp.imported || 0;
                            summary.merged += resp.merged || 0;
                            summary.not_found += resp.not_found || 0;
                            summary.duplicates += resp.duplicates || 0;
                            summary.errors += resp.errors_count || 0;
                            if (resp.errors && resp.errors.length) allErrors = allErrors.concat(resp.errors);
                        } else {
                            $bar.css('width', '100%').removeClass('bg-success').addClass('bg-danger');
                            var msg = (resp && (resp.message || (resp.errors || []).join('، '))) || 'فشل معالجة الملف';
                            var short = msg.length > 90 ? msg.substr(0, 90) + '…' : msg;
                            $status.text(short).removeClass('text-muted text-primary').addClass('text-danger');
                            summary.errors += 1;
                        }
                        nextFile(i + 1);
                    };
                    xhr.onerror = function () {
                        $bar.css('width', '100%').removeClass('bg-success').addClass('bg-danger');
                        $status.text('تعذر الاتصال بالخادم').removeClass('text-muted').addClass('text-danger');
                        summary.errors += 1;
                        nextFile(i + 1);
                    };
                    xhr.send(fd);
                }

                function finish() {
                    lastErrors = allErrors;
                    $('#deathsImportSubmitBtn').prop('disabled', false).html('<i class="bi bi-upload"></i> رفع البيانات');
                    $('#deaths_excel_files').val('');

                    if (allErrors.length) showErrorsReport(allErrors);

                    var warnClass = summary.errors && !summary.imported ? 'alert-danger' : 'alert-success';
                    var $sum = $('#deathsImportSummary').removeClass('alert-danger alert-success').addClass(warnClass);
                    var html = '<strong>اكتمل الرفع:</strong> إجمالي مستورد ' + summary.imported +
                        '، دمج مع السجل المدني ' + summary.merged +
                        '، غير موجود ' + summary.not_found +
                        '، مكرر ' + summary.duplicates +
                        '، أخطاء ' + summary.errors + ' — من أصل ' + total + ' ملف.';
                    $sum.html(html);

                    // تحديث الجدول بعد الرفع
                    if (typeof $.fn.DataTable !== 'undefined') {
                        $('#deaths-table').DataTable().ajax.reload(null, false);
                    }
                }

                nextFile(0);
            });
        });
    </script>
@endpush
