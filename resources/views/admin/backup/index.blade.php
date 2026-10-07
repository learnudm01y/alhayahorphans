@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container mt-4" dir="rtl">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>النسخ الاحتياطي</h5>
                    <button id="run-backup-btn" class="btn btn-light btn-sm" @if($running) disabled @endif>
                        <i class="fas fa-play me-1"></i> تشغيل النسخ الاحتياطي
                    </button>
                </div>
                <div class="card-body">
                    <div id="backup-alerts"></div>

                    <div id="backup-progress" class="alert alert-info d-none" role="alert">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-spinner fa-spin me-2"></i><span id="backup-step">جاري التجهيز...</span></span>
                            <small id="backup-elapsed"></small>
                        </div>
                    </div>

                    <div class="row text-center" id="summary-cards">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">الحالة العامة</div>
                                <div class="fw-bold fs-5" id="card-status">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">آخر نسخة احتياطية</div>
                                <div class="fw-bold fs-6" id="card-last">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">المدة</div>
                                <div class="fw-bold fs-6" id="card-duration">—</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">قاعدة البيانات / ملفات المشروع</div>
                                <div class="fw-bold fs-6">
                                    <span id="card-db">—</span> <span class="text-muted">/</span> <span id="card-laravel">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6 class="border-bottom pb-2"><i class="fas fa-images me-2 text-primary"></i>أرشيف الصور (Uploads)</h6>
                                <ul class="list-unstyled mb-0 small">
                                    <li>الحالة: <span class="fw-bold" id="uploads-status">—</span></li>
                                    <li>الملفات: <span id="uploads-files">—</span></li>
                                    <li>الحجم: <span id="uploads-size">—</span></li>
                                    <li>آخر أرشفة: <span id="uploads-last">—</span></li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6 class="border-bottom pb-2"><i class="fas fa-paperclip me-2 text-primary"></i>أرشيف المرفقات (Attachments)</h6>
                                <ul class="list-unstyled mb-0 small">
                                    <li>الحالة: <span class="fw-bold" id="attachments-status">—</span></li>
                                    <li>الملفات: <span id="attachments-files">—</span></li>
                                    <li>الحجم: <span id="attachments-size">—</span></li>
                                    <li>آخر أرشفة: <span id="attachments-last">—</span></li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6 class="border-bottom pb-2"><i class="fas fa-cloud-upload-alt me-2 text-primary"></i>رفع النسخ إلى OneDrive (.backups)</h6>
                                <ul class="list-unstyled mb-0 small">
                                    <li>الحالة: <span class="fw-bold" id="backups-status">—</span></li>
                                    <li>الملفات: <span id="backups-files">—</span></li>
                                    <li>الحجم: <span id="backups-size">—</span></li>
                                    <li>آخر رفع: <span id="backups-last">—</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div id="last-error" class="alert alert-danger d-none"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>آخر عمليات النسخ الاحتياطي</h6>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover table-bordered table-striped text-center align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الحالة</th>
                                <th>المرحلة</th>
                                <th>بدأ</th>
                                <th>انتهى</th>
                                <th>المدة</th>
                                <th>قاعدة البيانات</th>
                                <th>ملفات المشروع</th>
                                <th>Uploads</th>
                                <th>Attachments</th>
                                <th>رفع OneDrive</th>
                                <th>الخطأ</th>
                            </tr>
                        </thead>
                        <tbody id="runs-body">
                            @forelse ($runs as $run)
                            <tr>
                                <td>{{ $run->id }}</td>
                                <td><span class="badge {{ $run->status === 'success' ? 'bg-success' : ($run->status === 'partial' ? 'bg-warning text-dark' : ($run->status === 'failed' ? 'bg-danger' : 'bg-secondary')) }}">{{ $run->status }}</span></td>
                                <td>{{ $run->current_step }}</td>
                                <td>{{ $run->started_at }}</td>
                                <td>{{ $run->completed_at }}</td>
                                <td>{{ $run->duration_seconds ? $run->duration_seconds . ' ث' : '—' }}</td>
                                <td data-bytes="{{ $run->database_size }}">{{ $run->database_status }} —</td>
                                <td data-bytes="{{ $run->laravel_size }}">{{ $run->laravel_status }} —</td>
                                <td>{{ $run->uploads_status }} ({{ $run->uploads_copied ?? 0 }} نُسخ / {{ $run->uploads_files ?? 0 }})</td>
                                <td>{{ $run->attachments_status }} ({{ $run->attachments_copied ?? 0 }} نُسخ / {{ $run->attachments_files ?? 0 }})</td>
                                <td data-bytes="{{ $run->backups_size }}">{{ $run->backups_status }} (رُفع {{ $run->backups_copied ?? 0 }} / سابق {{ $run->backups_skipped ?? 0 }} / فشل {{ $run->backups_failed ?? 0 }}) —</td>
                                <td class="text-end small" style="max-width:260px">{{ \Illuminate\Support\Str::limit($run->error_message, 80) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="12">لا توجد عمليات نسخ احتياطي بعد</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
<script>
(function () {
    const STATUS_LABELS = { running: 'قيد التنفيذ', success: 'ناجح', partial: 'جزئي', failed: 'فشل', disabled: 'معطّل', skipped: 'لا يوجد' };
    const STEP_LABELS = {
        'Preparing': 'جاري التجهيز...',
        'Archiving Uploads': 'أرشفة مجلد uploads إلى OneDrive...',
        'Archiving Attachments': 'أرشفة مجلد attachments إلى OneDrive...',
        'Backing up Database': 'نسخ قاعدة البيانات (MySQL)...',
        'Backing up Laravel': 'نسخ ملفات المشروع (ZIP)...',
                        'Verifying': 'التحقق من الملفات المنتجة...',
                        'Cleaning Old Backups': 'تنظيف النسخ القديمة...',
                        'Archiving Backups': 'رفع نسخ SQL و ZIP إلى OneDrive...',
        'Completed': 'اكتملت العملية', 'Partial': 'اكتملت جزئيًا', 'Failed': 'فشلت العملية'
    };

    function bytes(value) {
        if (value === null || value === undefined || value === '' || value == 0) return '—';
        value = Number(value);
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0;
        while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
        return (Math.round(value * 100) / 100) + ' ' + units[i];
    }

    function statusBadge(status) {
        const cls = status === 'success' ? 'bg-success'
            : status === 'partial' ? 'bg-warning text-dark'
            : status === 'failed' ? 'bg-danger'
            : status === 'running' ? 'bg-info text-dark' : 'bg-secondary';
        return '<span class="badge ' + cls + '">' + (STATUS_LABELS[status] || status) + '</span>';
    }

    function fillSummary(run) {
        if (!run) return;
        const lastArchive = function (prefix) {
            const status = run[prefix + '_status'];
            if (status === 'success' && run.completed_at) return run.completed_at;
            return run.completed_at ? run.completed_at : '—';
        };

        document.getElementById('card-status').innerHTML = statusBadge(run.status);
        document.getElementById('card-last').textContent = run.completed_at || '—';
        document.getElementById('card-duration').textContent = run.duration_seconds ? run.duration_seconds + ' ثانية' : '—';
        document.getElementById('card-db').textContent = bytes(run.database_size);
        document.getElementById('card-laravel').textContent = bytes(run.laravel_size);

        ['uploads', 'attachments', 'backups'].forEach(function (prefix) {
            document.getElementById(prefix + '-status').innerHTML = statusBadge(run[prefix + '_status']);
            document.getElementById(prefix + '-files').textContent =
                (run[prefix + '_files'] !== null ? run[prefix + '_files'] : '—') +
                (run[prefix + '_copied'] !== null
                    ? (prefix === 'backups'
                        ? ' (رُفع ' + run[prefix + '_copied'] + '، محفوظ ' + (run[prefix + '_skipped'] ?? 0) + '، فشل ' + (run[prefix + '_failed'] ?? 0) + ')'
                        : ' (نُسخ ' + run[prefix + '_copied'] + '، تُرك ' + (run[prefix + '_skipped'] ?? 0) + '، فشل ' + (run[prefix + '_failed'] ?? 0) + ')')
                    : '');
            document.getElementById(prefix + '-size').textContent = bytes(run[prefix + '_size']);
            document.getElementById(prefix + '-last').textContent = lastArchive(prefix);
        });

        const err = document.getElementById('last-error');
        if (run.error_message) {
            err.textContent = 'تنبيه: ' + run.error_message;
            err.classList.remove('d-none');
        } else {
            err.classList.add('d-none');
        }
    }

    function showAlert(type, message) {
        document.getElementById('backup-alerts').innerHTML =
            '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    let polling = null;
    let startedAt = null;

    function stopPolling() {
        if (polling) { clearInterval(polling); polling = null; }
        document.getElementById('backup-progress').classList.add('d-none');
        document.getElementById('run-backup-btn').disabled = false;
    }

    function startPolling() {
        startedAt = Date.now();
        document.getElementById('backup-progress').classList.remove('d-none');
        document.getElementById('run-backup-btn').disabled = true;

        polling = setInterval(function () {
            fetch('{{ route('admin.backup.status') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const elapsed = Math.round((Date.now() - startedAt) / 1000);
                    document.getElementById('backup-elapsed').textContent = elapsed + ' ثانية';

                    if (data.current) {
                        document.getElementById('backup-step').textContent =
                            STEP_LABELS[data.current.current_step] || data.current.current_step;
                    }

                    if (!data.running) {
                        stopPolling();
                        fillSummary(data.last);
                        renderRunRow(data.last);
                        if (data.last && data.last.status === 'success') showAlert('success', 'اكتمل النسخ الاحتياطي بنجاح.');
                        else if (data.last && data.last.status === 'partial') showAlert('warning', 'اكتمل النسخ الاحتياطي جزئيًا — راجع التفاصيل.');
                        else if (data.last && data.last.status === 'failed') showAlert('danger', 'فشل النسخ الاحتياطي: ' + (data.last.error_message || ''));
                    }
                })
                .catch(function () {});
        }, 3000);
    }

    function esc(value) {
        if (value === null || value === undefined || value === '') return '—';
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // إضافة/تحديث صف العملية في الجدول فورًا (الجدول يُبنى من الخادم عند التحميل فقط)
    function renderRunRow(run) {
        const tbody = document.getElementById('runs-body');
        if (!tbody || !run || !run.id) return;

        const empty = tbody.querySelector('td[colspan]');
        if (empty && empty.parentElement) empty.parentElement.remove();

        const existing = tbody.querySelector('tr[data-run-id="' + run.id + '"]');
        if (existing) existing.remove();

        const tr = document.createElement('tr');
        tr.setAttribute('data-run-id', run.id);

        const add = function (text) {
            const td = document.createElement('td');
            td.textContent = text;
            tr.appendChild(td);
        };

        add(run.id);
        add('');
        tr.lastChild.innerHTML = statusBadge(run.status);
        add(run.current_step);
        add(run.started_at);
        add(run.completed_at);
        add(run.duration_seconds ? run.duration_seconds + ' ث' : '—');
        add((run.database_status || '—') + ' — ' + bytes(run.database_size));
        add((run.laravel_status || '—') + ' — ' + bytes(run.laravel_size));
        add((run.uploads_status || '—') + ' (' + (run.uploads_copied ?? 0) + ' نُسخ / ' + (run.uploads_files ?? 0) + ')');
        add((run.attachments_status || '—') + ' (' + (run.attachments_copied ?? 0) + ' نُسخ / ' + (run.attachments_files ?? 0) + ')');
        add((run.backups_status || '—') + ' (رُفع ' + (run.backups_copied ?? 0) + ' / سابق ' + (run.backups_skipped ?? 0) + ' / فشل ' + (run.backups_failed ?? 0) + ') — ' + bytes(run.backups_size));
        add(run.error_message ? String(run.error_message).substring(0, 80) : '');

        tr.children[11].className = 'text-end small';
        tr.children[11].style.maxWidth = '260px';

        tbody.insertBefore(tr, tbody.firstChild);
    }

    document.getElementById('run-backup-btn').addEventListener('click', function () {
        if (!confirm('تشغيل النسخ الاحتياطي الآن؟ (قاعدة البيانات + ملفات المشروع + أرشفة الوسائط)')) return;

        document.getElementById('backup-alerts').innerHTML = '';
        startPolling();

        fetch('{{ route('admin.backup.run') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
            .then(function (res) {
                if (res.ok) {
                    // لا نوقف المتابعة: العملية ما زالت تعمل في الخلفية
                    showAlert('info', res.body.message || 'بدأت عملية النسخ الاحتياطي...');
                    fillSummary(res.body.run || null);
                    return;
                }

                stopPolling();
                showAlert('danger', res.body.message || 'فشل تنفيذ النسخ الاحتياطي.');
            })
            .catch(function (e) {
                stopPolling();
                showAlert('danger', 'تعذر الاتصال بالخادم أثناء تنفيذ النسخ الاحتياطي.');
            });
    });

    // تنسيق الأحجام في الجدول
    document.querySelectorAll('#runs-body td[data-bytes]').forEach(function (td) {
        const size = td.getAttribute('data-bytes');
        const text = td.textContent.trim().split('—')[0].trim();
        td.textContent = text + ' — ' + bytes(size);
    });

    // تعبئة البطاقات من آخر عملية عند التحميل
    @if($last)
    fillSummary(@json($last));
    @elseif($running)
    startPolling();
    @endif
})();
</script>
@endpush
