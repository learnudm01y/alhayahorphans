<div class="collapse" id="face-matching-panel">
    <div class="card border-primary mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h5 class="fw-bold text-primary mb-1"><i class="fas fa-user-check me-2"></i>فحص تطابق الوجوه</h5>
                    <p class="text-muted mb-0">للصور من النوع نفسه وللشخص نفسه فقط. هذه النسخة للفحص والمعايرة، ولا تنفذ أي حذف.</p>
                </div>
                <span class="badge bg-light-primary text-primary">Dry run</span>
            </div>

            <div class="alert alert-info py-2">
                تُستبعد الصور بلا وجه واضح أو متعددة الوجوه، وتبقى الحالات المشكوك فيها. نسبة التشابه المعروضة مشتقة من مسافة البصمة وليست احتمالاً للتطابق.
            </div>

            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-5">
                    <label for="face-scan-person" class="form-label">رقم هوية واحد (اختياري)</label>
                    <input type="text" class="form-control" id="face-scan-person" maxlength="64" autocomplete="off" placeholder="اتركه فارغاً لفحص كل المجموعات">
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-primary" id="face-scan-start">
                        <i class="fas fa-play me-2"></i>بدء فحص جديد
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('attachment-audit.face-trash.index') }}">
                        <i class="fas fa-trash-restore me-2"></i>سلة فحص الوجوه
                    </a>
                </div>
            </div>

            <div id="face-scan-status" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>الحالة: <span id="face-scan-status-label">—</span> <span class="text-muted">#<span id="face-scan-id"></span></span></strong>
                    <span id="face-scan-progress-label">0%</span>
                </div>
                <div class="progress mb-3" style="height: 12px;">
                    <div class="progress-bar" id="face-scan-progress" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="mb-3">
                    <span class="badge bg-secondary" id="face-scan-trashed" style="display:none;"></span>
                </div>
                <div class="row g-2 mb-3 text-center">
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><strong id="face-scan-images">0 / 0</strong><br><small class="text-muted">الصور</small></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><strong id="face-scan-groups">0 / 0</strong><br><small class="text-muted">المجموعات</small></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><strong id="face-scan-duplicates">0</strong><br><small class="text-muted">تطابق مؤكد</small></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><strong id="face-scan-review">0</strong><br><small class="text-muted">مراجعة يدوية</small></div></div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-outline-warning" id="face-scan-stop" style="display:none;"><i class="fas fa-pause me-2"></i>إيقاف مؤقت</button>
                    <button class="btn btn-outline-success" id="face-scan-resume" style="display:none;"><i class="fas fa-play me-2"></i>استئناف</button>
                </div>
                <div class="alert alert-danger py-2" id="face-scan-error" style="display:none;"></div>
                <div class="alert alert-warning py-2" id="face-scan-expired" style="display:none;">انتهت صلاحية هذه النتائج. ابدأ فحصاً جديداً.</div>

                <div id="face-results-section" style="display:none;">
                    <hr>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h6 class="fw-bold mb-0">نتائج الفحص</h6>
                        <div class="btn-group" role="group" aria-label="تصفية النتائج">
                            <button type="button" class="btn btn-sm btn-primary face-result-filter" data-decision="auto_duplicate">تطابق مؤكد</button>
                            <button type="button" class="btn btn-sm btn-outline-primary face-result-filter" data-decision="review">مراجعة يدوية</button>
                        </div>
                    </div>
                    <div id="face-results-loading" class="text-center py-3" style="display:none;"><span class="spinner-border spinner-border-sm"></span></div>
                    <div id="face-results"></div>
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary btn-sm" id="face-results-more" style="display:none;">تحميل المزيد</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .face-match-card { border: 1px solid #e4e6ef; border-radius: .65rem; padding: .75rem; height: 100%; }
    .face-match-image { width: 100%; height: 230px; object-fit: contain; background: #f5f8fa; border-radius: .45rem; }
    .face-match-result { border-bottom: 1px dashed #e4e6ef; padding: 1rem 0; }
</style>

@push('scriptsCode')
<script>
$(document).ready(function() {
    let faceScan = null;
    let facePollTimer = null;
    let faceDecision = 'auto_duplicate';
    let faceResultPage = 1;
    let faceResultLastPage = 1;
    let faceRenderedGroups = -1;

    const faceRoutes = {
        start: @json(route('attachment-audit.face-scans.store')),
        latest: @json(route('attachment-audit.face-scans.latest')),
        show: @json(route('attachment-audit.face-scans.show', ['scan' => '__SCAN__'])),
        stop: @json(route('attachment-audit.face-scans.stop', ['scan' => '__SCAN__'])),
        resume: @json(route('attachment-audit.face-scans.resume', ['scan' => '__SCAN__']))
    };

    function faceRoute(template, id) {
        return template.replace('__SCAN__', id);
    }

    function faceEscape(value) {
        return $('<div>').text(value ?? '').html();
    }

    function faceStatusLabel(status) {
        return ({
            queued: 'في الانتظار',
            running: 'قيد الفحص',
            stopping: 'جارٍ التوقف',
            paused: 'متوقف مؤقتاً',
            completed: 'مكتمل',
            failed: 'تعذر الفحص'
        })[status] || status;
    }

    function faceIsActive(scan) {
        return ['queued', 'running', 'stopping'].includes(scan.status);
    }

    function renderFaceScan(scan) {
        faceScan = scan;
        $('#face-scan-status').show();
        $('#face-scan-id').text(scan.id);
        $('#face-scan-status-label').text(faceStatusLabel(scan.status));
        $('#face-scan-progress').css('width', scan.progress + '%').attr('aria-valuenow', scan.progress);
        $('#face-scan-progress-label').text(scan.progress + '%');
        $('#face-scan-images').text(scan.processed_images + ' / ' + scan.total_images);
        $('#face-scan-groups').text(scan.processed_groups + ' / ' + scan.total_groups);
        $('#face-scan-duplicates').text(scan.duplicate_count);
        $('#face-scan-review').text(scan.review_count);
        const trashed = Number(scan.trashed_count || 0);
        $('#face-scan-trashed').toggle(trashed > 0).text('نُقل إلى السلة: ' + trashed);
        $('#face-scan-stop').toggle(scan.can_stop);
        $('#face-scan-resume').toggle(scan.can_resume);
        $('#face-scan-start, #face-scan-person').prop('disabled', faceIsActive(scan));
        $('#face-scan-error').toggle(Boolean(scan.last_error)).text(scan.last_error || '');
        $('#face-scan-expired').toggle(scan.expired);
        $('#face-results-section').toggle(!scan.expired && (scan.processed_groups > 0 || scan.status === 'completed'));

        if (!scan.expired && ((scan.processed_groups !== faceRenderedGroups && scan.processed_groups > 0)
            || (scan.status === 'completed' && faceRenderedGroups === -1))) {
            faceRenderedGroups = scan.processed_groups;
            loadFaceResults(1, false);
        }
    }

    function watchFaceScan() {
        clearTimeout(facePollTimer);
        if (!faceScan || !faceIsActive(faceScan)) return;

        facePollTimer = setTimeout(function() {
            $.get(faceRoute(faceRoutes.show, faceScan.id))
                .done(function(response) {
                    if (!response.success) return;
                    renderFaceScan(response.data);
                    watchFaceScan();
                })
                .fail(function() {
                    facePollTimer = setTimeout(watchFaceScan, 6000);
                });
        }, 3000);
    }

    function loadLatestFaceScan() {
        $.get(faceRoutes.latest).done(function(response) {
            if (!response.ready) {
                $('#face-scan-start').prop('disabled', true);
                return;
            }
            if (!response.data) return;

            renderFaceScan(response.data);
            if (!response.data.expired && (faceIsActive(response.data) || response.data.duplicate_count || response.data.review_count)) {
                $('#face-matching-panel').collapse('show');
            }
            watchFaceScan();
        });
    }

    $('#face-scan-start').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true);
        faceRenderedGroups = -1;
        $('#face-results').empty();

        $.ajax({
            url: faceRoutes.start,
            method: 'POST',
            data: { person: $('#face-scan-person').val() }
        }).done(function(response) {
            renderFaceScan(response.data);
            watchFaceScan();
        }).fail(function(xhr) {
            Swal.fire('تعذر بدء الفحص', xhr.responseJSON?.message || 'حدث خطأ غير متوقع', 'error');
            $button.prop('disabled', false);
        });
    });

    $('#face-scan-stop').on('click', function() {
        if (!faceScan) return;
        $.post(faceRoute(faceRoutes.stop, faceScan.id)).done(function(response) {
            renderFaceScan(response.data);
            clearTimeout(facePollTimer);
        }).fail(function(xhr) {
            Swal.fire('تعذر الإيقاف', xhr.responseJSON?.message || 'حدث خطأ غير متوقع', 'error');
        });
    });

    $('#face-scan-resume').on('click', function() {
        if (!faceScan) return;
        $.post(faceRoute(faceRoutes.resume, faceScan.id)).done(function(response) {
            renderFaceScan(response.data);
            watchFaceScan();
        }).fail(function(xhr) {
            Swal.fire('تعذر الاستئناف', xhr.responseJSON?.message || 'حدث خطأ غير متوقع', 'error');
        });
    });

    $('.face-result-filter').on('click', function() {
        faceDecision = $(this).data('decision');
        $('.face-result-filter').removeClass('btn-primary').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary');
        loadFaceResults(1, false);
    });

    $('#face-results-more').on('click', function() {
        if (faceResultPage < faceResultLastPage) loadFaceResults(faceResultPage + 1, true);
    });

    $('#face-results').on('click', '.face-restore-btn', function() {
        const $btn = $(this);
        $btn.prop('disabled', true);
        $.post($btn.data('restore-url')).done(function(response) {
            Swal.fire('تم الاسترجاع', response.message || 'استُعيد المرفق إلى مكانه.', 'success');
            loadFaceResults(faceResultPage, false);
        }).fail(function(xhr) {
            Swal.fire('تعذر الاسترجاع', xhr.responseJSON?.message || 'حدث خطأ غير متوقع', 'error');
            $btn.prop('disabled', false);
        });
    });

    function loadFaceResults(page, append) {
        if (!faceScan || faceScan.expired) return;
        $('#face-results-loading').show();
        $('#face-results-more').hide();

        $.get(faceScan.results_url, { decision: faceDecision, page: page }).done(function(response) {
            const html = response.data.map(faceResultHtml).join('');
            if (append) {
                $('#face-results').append(html);
            } else {
                $('#face-results').html(html || '<div class="text-center text-muted py-4">لا توجد نتائج في هذا التصنيف حتى الآن.</div>');
            }
            faceResultPage = response.meta.current_page;
            faceResultLastPage = response.meta.last_page;
            $('#face-results-more').toggle(faceResultPage < faceResultLastPage);
        }).always(function() {
            $('#face-results-loading').hide();
        });
    }

    function faceResultHtml(result) {
        const automatic = result.decision === 'auto_duplicate';
        const firstLabel = automatic ? 'الأقدم' : 'مراجعة يدوية — يبقى';
        const secondLabel = automatic ? 'الأحدث — يبقى' : 'مراجعة يدوية — يبقى';
        const similarity = (result.display_similarity * 100).toFixed(1);

        return `<div class="face-match-result" data-result-id="${result.id}">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <span class="badge ${automatic ? 'bg-success' : 'bg-warning text-dark'}">${automatic ? 'تطابق مؤكد آلياً' : 'يحتاج مراجعة'}</span>
                <small class="text-muted">الشخص ${faceEscape(result.person)} | المسافة ${faceEscape(result.distance)} | تشابه العرض ${similarity}% وليس احتمالاً</small>
            </div>
            <div class="row g-3">
                <div class="col-md-6">${faceAttachmentHtml(result.attachment, firstLabel, automatic ? 'bg-light-danger text-danger' : 'bg-light-warning text-warning')}</div>
                <div class="col-md-6">${faceAttachmentHtml(result.matched_attachment, secondLabel, automatic ? 'bg-light-success text-success' : 'bg-light-warning text-warning')}</div>
            </div>
        </div>`;
    }

    function faceAttachmentHtml(attachment, label, badgeClass) {
        if (attachment.trashed) {
            return `<div class="face-match-card text-center">
                <span class="badge bg-secondary mb-2">${label === 'الأقدم' ? 'الأقدم — حُذف إلى السلة' : 'حُذف إلى السلة'} — قابل للاسترجاع</span>
                <div class="text-muted small mb-2">الملف محفوظ في السلة حتى ${faceEscape(attachment.restore_deadline_at || '')}<br>ولم يُحذف نهائياً.</div>
                <button type="button" class="btn btn-sm btn-outline-primary face-restore-btn" data-restore-url="${faceEscape(attachment.restore_url)}">
                    <i class="fas fa-undo me-1"></i>استرجاع الآن
                </button>
            </div>`;
        }

        if (attachment.missing) {
            return `<div class="face-match-card text-center text-muted">المرفق #${attachment.id} لم يعد موجوداً</div>`;
        }

        return `<div class="face-match-card">
            <span class="badge ${badgeClass} mb-2">${label}</span>
            <a href="${faceEscape(attachment.image_url)}" target="_blank" rel="noopener">
                <img class="face-match-image" loading="lazy" src="${faceEscape(attachment.image_url)}" alt="مرفق #${attachment.id}">
            </a>
            <div class="small text-muted mt-2">
                مرفق #${attachment.id} | ${faceEscape(attachment.document_type || 'صورة')}<br>
                ${faceEscape(attachment.created_at || '')}${attachment.file_size ? ' | ' + faceSize(attachment.file_size) : ''}
            </div>
        </div>`;
    }

    function faceSize(bytes) {
        const size = Number(bytes);
        if (!Number.isFinite(size)) return '';
        return size >= 1048576 ? (size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(size / 1024)) + ' KB';
    }

    loadLatestFaceScan();
});
</script>
@endpush
