@extends('admin.dashboard.toolbars.index')

@push('styles')
<style>
    /* تحسين مظهر الـ Modal */
    .modal-xl {
        max-width: 95%;
    }

    .modal-body {
        padding: 1.5rem;
    }

    /* تحسين الـ DataTable في الصفحة الرئيسية */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        margin: 0.5rem 0;
    }

    /* تحسين الأزرار */
    .btn-info:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
    }

    /* تحسين modal التفاصيل */
    #recordDetailsModal .modal-body {
        padding: 0;
    }

    #recordDetailsModal .modal-body .container-fluid {
        padding: 1.5rem;
    }

    /* إخفاء navbar و sidebars في modal */
    #recordDetailsModal .navbar,
    #recordDetailsModal .sidebar,
    #recordDetailsModal .main-sidebar,
    #recordDetailsModal .content-header {
        display: none !important;
    }

    /* تحسين الجداول داخل modal التفاصيل */
    #recordDetailsModal .table {
        margin-bottom: 0;
    }

    #recordDetailsModal .card {
        border: none;
        box-shadow: none;
    }
</style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">سجل الإدارة</h3>
                        <div class="card-tools">
                            <div class="d-flex gap-2">
                                @canany(['عرض قسم إدارة التسجيلات', 'عرض البيانات المدخلة'])
                                <button type="button" class="btn btn-info btn-sm mt-4" data-bs-toggle="modal" data-bs-target="#searchModal">
                                    <i class="fas fa-search"></i> البحث الشامل في السجلات
                                </button>
                                @endcanany

                                @can('تصدير السجلات')
                                <!-- زر التصدير الرئيسي - Excel مع 6 Sheets (المعيلين، المتوفين، أفراد الأسرة، المرفقات، الحسابات البنكية، حقول Portal) -->
                                <a href="{{ route('admin.records.management.exportAllCSV') }}" class="btn btn-success btn-sm mt-4" title="تصدير Excel شامل - 6 sheets مع جداول منفصلة للحسابات البنكية وحقول Portal">
                                    <i class="fas fa-file-excel"></i> تصدير Excel (6 sheets)
                                </a>

                                <!-- زر التصدير الخلفي — يبني الملف في الطابور ويحمّله جاهزًا (يتجنب خطأ 524) -->
                                <button type="button" id="btnBackgroundExport" class="btn btn-primary btn-sm mt-4" title="تصدير في الخلفية — لا انقطاع حتى مع البيانات الكبيرة">
                                    <i class="fas fa-file-excel"></i> تصدير Excel (خلفي — بدون انقطاع)
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                       {!! $dataTable->table(['class' => 'table table-bordered table-striped text-center align-middle w-100'], true) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @canany(['عرض قسم إدارة التسجيلات', 'عرض البيانات المدخلة'])
    <!-- Modal البحث الشامل -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="searchModalLabel">
                        <i class="fas fa-search"></i> البحث الشامل في السجلات
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                    <!-- سيتم تحميل محتوى البحث هنا -->
                    <div id="searchModalContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                            <p class="mt-3 text-muted">جاري تحميل نموذج البحث...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إغلاق
                    </button>
                    <a href="{{ route('search.records.index') }}" class="btn btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> فتح في صفحة منفصلة
                    </a>
                </div>
            </div>
        </div>
    </div>

    @endcanany

    <!-- نافذة تقدّم التصدير الخلفي -->
    <div class="modal fade" id="bgExportModal" tabindex="-1" aria-labelledby="bgExportModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="bgExportModalLabel">
                        <i class="fas fa-file-excel"></i> تصدير البيانات المدخلة (6 شيتات)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="bgExportCloseBtn"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- شريط التقدّم -->
                    <div class="progress mb-3" style="height: 22px;">
                        <div id="bgExportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>

                    <!-- الحالة الحالية -->
                    <p id="bgExportStatusText" class="mb-2 text-muted">
                        <i class="fas fa-info-circle"></i> جارٍ التحضير...
                    </p>

                    <!-- رسالة الخطأ (تظهر عند الفشل) -->
                    <div id="bgExportError" class="alert alert-danger d-none">
                        <i class="fas fa-exclamation-triangle"></i> <span id="bgExportErrorText"></span>
                    </div>

                    <!-- رابط التحميل (يظهر عند الاكتمال) -->
                    <div id="bgExportDone" class="alert alert-success d-none">
                        <i class="fas fa-check-circle"></i> اكتمل التصدير — الملف جاهز.
                        <a id="bgExportDownloadLink" href="#" class="btn btn-success btn-sm mt-2 d-block">
                            <i class="fas fa-download"></i> تحميل الملف
                        </a>
                    </div>

                    <!-- تنبيه عدم عمل الطابور -->
                    <div id="bgExportQueueHint" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-circle"></i>
                        لم تبدأ المهمة بعد. تأكد أن عامل الطابور يعمل على الاستضافة:
                        <code dir="ltr">php artisan queue:work</code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scriptsCode')
    <script src="{{ asset('js/search-results.js') }}?v={{ filemtime(public_path('js/search-results.js')) }}"></script>
    {!! $dataTable->scripts() !!}
    <script>
        $(document).ready(function() {
            // التأكد من أن dropdowns تعمل بشكل صحيح
            $(document).on('click', '.dropdown-toggle', function(e) {
                e.preventDefault();
                $(this).dropdown('toggle');
            });

            // إعادة تهيئة Bootstrap dropdowns بعد تحديث DataTable
            $('#recordsmanagemente-table').on('draw.dt', function() {
                // إعادة تهيئة dropdowns
                var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
                var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                    return new bootstrap.Dropdown(dropdownToggleEl);
                });
            });

            RecordSearch.bindModal('#searchModal', '{{ route("search.records.modal") }}');

            /* ==================================================================
               التصدير الخلفي (Background Export) — يتجنب خطأ Cloudflare 524
               يبدأ مهمة في الطابور ثم يستطلع الحالة دورياً حتى الاكتمال
               ================================================================== */
            (function () {
                const $btn        = $('#btnBackgroundExport');
                const $modal      = $('#bgExportModal');
                const $bar        = $('#bgExportProgressBar');
                const $statusText = $('#bgExportStatusText');
                const $error      = $('#bgExportError');
                const $errorText  = $('#bgExportErrorText');
                const $done       = $('#bgExportDone');
                const $dlLink     = $('#bgExportDownloadLink');
                const $queueHint  = $('#bgExportQueueHint');
                const $closeBtn   = $('#bgExportCloseBtn');

                let pollTimer   = null;
                let startedAt   = null;
                const START_TIMEOUT = 20000; // 20 ثانية — إن لم تبدأ المهمة نعرض تنبيه الطابور

                const STATUS_URL   = '{{ route("admin.records.management.exportRecordsStatus") }}';
                const START_URL    = '{{ route("admin.records.management.exportRecordsBackground") }}';
                const CSRF_TOKEN   = $('meta[name="csrf-token"]').attr('content');

                function resetUI() {
                    $bar.css('width', '0%').text('0%').removeClass('bg-danger').addClass('progress-bar-animated');
                    $statusText.html('<i class="fas fa-info-circle"></i> جارٍ التحضير...');
                    $error.addClass('d-none');
                    $done.addClass('d-none');
                    $queueHint.addClass('d-none');
                    $closeBtn.prop('disabled', true);
                }

                function stopPolling() {
                    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
                }

                function setFailed(message) {
                    stopPolling();
                    $bar.removeClass('progress-bar-animated').addClass('bg-danger');
                    $statusText.html('<i class="fas fa-times-circle text-danger"></i> فشل التصدير');
                    $errorText.text(message || 'حدث خطأ غير معروف');
                    $error.removeClass('d-none');
                    $closeBtn.prop('disabled', false);
                }

                function setCompleted(status) {
                    stopPolling();
                    $bar.css('width', '100%').text('100%').removeClass('progress-bar-animated');
                    $statusText.html('<i class="fas fa-check-circle text-success"></i> اكتمل التصدير');
                    if (status && status.download_url) {
                        $dlLink.attr('href', status.download_url);
                    }
                    $done.removeClass('d-none');
                    $closeBtn.prop('disabled', false);
                }

                function updateProgress(status) {
                    if (!status) return;
                    const stage = status.stage;
                    const total = status.total || 6;
                    const processed = status.processed || 0;
                    const pct = Math.min(100, Math.round((processed / total) * 100));

                    if (stage === 'queued') {
                        $bar.css('width', '3%').text('');
                        $statusText.html('<i class="fas fa-clock"></i> في انتظار عامل الطابور...');
                    } else if (stage === 'running') {
                        $bar.css('width', pct + '%').text(pct + '%');
                        const msg = status.message || 'جارٍ البناء...';
                        $statusText.html('<i class="fas fa-spinner fa-spin"></i> ' + msg + ' (' + processed + '/' + total + ' شيت)');
                    } else if (stage === 'completed') {
                        setCompleted(status);
                    } else if (stage === 'failed') {
                        setFailed(status.message);
                    }
                }

                function poll() {
                    $.ajax({
                        url: STATUS_URL,
                        method: 'GET',
                        success: function (res) {
                            if (res.success && res.status) {
                                const stage = res.status.stage;

                                // إن بقيت "في الانتظار" تجاوز المهلة → تنبيه الطابور
                                if ((stage === 'queued') && (Date.now() - startedAt > START_TIMEOUT)) {
                                    $queueHint.removeClass('d-none');
                                }

                                if (stage === 'completed') { setCompleted(res.status); return; }
                                if (stage === 'failed')    { setFailed(res.status.message); return; }

                                updateProgress(res.status);
                                pollTimer = setTimeout(poll, 3000); // استطلاع كل 3 ثوانٍ
                            } else {
                                // لا يوجد سجل حالة بعد — قد لا تكون المهمة بدأت
                                if (Date.now() - startedAt > START_TIMEOUT) {
                                    $queueHint.removeClass('d-none');
                                    stopPolling();
                                    $closeBtn.prop('disabled', false);
                                } else {
                                    pollTimer = setTimeout(poll, 3000);
                                }
                            }
                        },
                        error: function (xhr) {
                            // 404 = لا يوجد حالة بعد
                            if (xhr.status === 404) {
                                if (Date.now() - startedAt > START_TIMEOUT) {
                                    $queueHint.removeClass('d-none');
                                    stopPolling();
                                    $closeBtn.prop('disabled', false);
                                } else {
                                    pollTimer = setTimeout(poll, 3000);
                                }
                            } else {
                                setFailed('تعذّر متابعة الحالة (كود ' + xhr.status + ')');
                            }
                        }
                    });
                }

                $btn.on('click', function (e) {
                    e.preventDefault();
                    resetUI();
                    $modal.modal('show');
                    startedAt = Date.now();

                    $.ajax({
                        url: START_URL,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                        success: function (res) {
                            if (res.success) {
                                $statusText.html('<i class="fas fa-spinner fa-spin"></i> تم بدء التصدير — جارٍ البناء في الخلفية...');
                                pollTimer = setTimeout(poll, 2000);
                            } else {
                                setFailed(res.message);
                            }
                        },
                        error: function (xhr) {
                            let msg = 'تعذّر بدء التصدير';
                            if (xhr.status === 409) {
                                // يوجد تصدير جارٍ — نتابعه
                                $statusText.html('<i class="fas fa-spinner fa-spin"></i> يوجد تصدير جارٍ — جارٍ المتابعة...');
                                pollTimer = setTimeout(poll, 2000);
                                return;
                            }
                            if (xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; }
                            setFailed(msg);
                        }
                    });
                });

                // تنظيف الاستطلاع عند إغلاق النافذة
                $modal.on('hidden.bs.modal', function () {
                    stopPolling();
                });
            })();
        });
    </script>
@endpush
