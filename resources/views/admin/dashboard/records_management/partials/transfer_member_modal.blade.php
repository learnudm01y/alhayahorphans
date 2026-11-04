@push('scriptsCode')
<script>
(function () {
    'use strict';

    var lookupUrl = @json(route('admin.records-management.lookup-guardian'));
    var transferUrl = @json(route('admin.records-management.transfer-family-member'));
    var csrfToken = @json(csrf_token());

    var lookupTimer = null;
    var lookupSeq = 0;
    var state = { status: 'idle', civil: null };

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function digitsOnly(value) {
        return String(value == null ? '' : value).replace(/\D+/g, '');
    }

    function showStatus(html) {
        var box = document.getElementById('transfer-guardian-status');
        if (box) box.innerHTML = html;
    }

    function toggleManualFields(show, civil) {
        var box = document.getElementById('transfer-manual-fields');
        if (!box) return;

        box.style.display = show ? '' : 'none';
        if (!show || !civil) return;

        document.getElementById('transfer-first-name').value = civil.first_name || '';
        document.getElementById('transfer-father-name').value = civil.father_name || '';
        document.getElementById('transfer-grand-father-name').value = civil.grand_father_name || '';
        document.getElementById('transfer-family-name').value = civil.family_name || '';
    }

    function runLookup(identity) {
        var seq = ++lookupSeq;

        showStatus('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span> جارٍ البحث عن المعيل...</div>');

        fetch(lookupUrl + '?identity=' + encodeURIComponent(identity), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (seq !== lookupSeq) return;

                if (!data.success) {
                    state.status = 'error';
                    showStatus('<div class="alert alert-danger py-2 mb-0">' + esc(data.message || 'تعذر البحث عن المعيل') + '</div>');
                    return;
                }

                if (data.status === 'found') {
                    state.status = 'found';
                    toggleManualFields(false);
                    showStatus(
                        '<div class="alert alert-success py-2 mb-0">المعيل الحالي: <strong>' + esc(data.guardian.name) + '</strong>' +
                        '<br>رقم الملف: <strong>' + esc(data.guardian.file_id_number) + '</strong></div>'
                    );
                    return;
                }

                if (data.status === 'duplicate') {
                    state.status = 'duplicate';
                    var files = (data.files || []).map(function (file) {
                        return esc(file.file_id_number) + ' (' + esc(file.name) + ')';
                    }).join('، ');
                    toggleManualFields(false);
                    showStatus('<div class="alert alert-danger py-2 mb-0">رقم الهوية مرتبط بأكثر من ملف: ' + files + '<br>لا يمكن النقل تلقائياً.</div>');
                    return;
                }

                state.status = 'not_found';
                state.civil = data.civil || null;
                toggleManualFields(true, state.civil);

                if (data.civil) {
                    showStatus('<div class="alert alert-warning py-2 mb-0">المعيل غير موجود في سجلات المعيلين.<br>سيتم إنشاء سجل جديد باسم: <strong>' + esc(data.civil.full_name) + '</strong></div>');
                } else {
                    showStatus('<div class="alert alert-warning py-2 mb-0">المعيل غير موجود في سجلات المعيلين ولا في السجل المدني. أدخل اسم المعيل لإنشاء سجل جديد.</div>');
                }
            })
            .catch(function () {
                if (seq !== lookupSeq) return;
                state.status = 'error';
                showStatus('<div class="alert alert-danger py-2 mb-0">تعذر الاتصال بالخادم أثناء البحث.</div>');
            });
    }

    document.body.addEventListener('click', function (event) {
        var button = event.target.closest('.transfer-family-member');
        if (!button) return;

        event.preventDefault();

        var memberId = button.getAttribute('data-member-id');
        if (!memberId) return;

        var memberName = button.getAttribute('data-member-name') || '';

        state = { status: 'idle', civil: null };
        lookupSeq++;
        clearTimeout(lookupTimer);

        Swal.fire({
            title: 'نقل الفرد إلى معيل آخر',
            html:
                '<div class="text-start" style="direction: rtl;">' +
                    '<div class="mb-2 small text-muted">الفرد: <strong>' + esc(memberName) + '</strong></div>' +
                    '<label class="form-label">رقم هوية المعيل الجديد</label>' +
                    '<input id="transfer-guardian-identity" class="form-control mb-2" inputmode="numeric" autocomplete="off" placeholder="أدخل رقم هوية المعيل">' +
                    '<div id="transfer-guardian-status" class="mb-2"></div>' +
                    '<div id="transfer-manual-fields" style="display:none;">' +
                        '<div class="row g-2">' +
                            '<div class="col-6"><label class="form-label small">الاسم الأول</label><input id="transfer-first-name" class="form-control form-control-sm"></div>' +
                            '<div class="col-6"><label class="form-label small">اسم الأب</label><input id="transfer-father-name" class="form-control form-control-sm"></div>' +
                            '<div class="col-6"><label class="form-label small">اسم الجد</label><input id="transfer-grand-father-name" class="form-control form-control-sm"></div>' +
                            '<div class="col-6"><label class="form-label small">اسم العائلة</label><input id="transfer-family-name" class="form-control form-control-sm"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: 'تأكيد النقل',
            cancelButtonText: 'إلغاء',
            focusConfirm: false,
            didOpen: function () {
                var input = document.getElementById('transfer-guardian-identity');

                input.addEventListener('input', function () {
                    var identity = digitsOnly(input.value);
                    if (input.value !== identity) input.value = identity;

                    lookupSeq++;
                    clearTimeout(lookupTimer);
                    state = { status: 'idle', civil: null };
                    toggleManualFields(false);

                    if (identity.length < 6) {
                        showStatus(identity.length ? '<div class="text-muted small">أكمل رقم الهوية للبحث عن المعيل...</div>' : '');
                        return;
                    }

                    showStatus('<div class="text-muted small">جارٍ البحث عن المعيل...</div>');
                    lookupTimer = setTimeout(function () { runLookup(identity); }, 400);
                });

                input.focus();
            },
            preConfirm: function () {
                var identity = digitsOnly(document.getElementById('transfer-guardian-identity').value);

                if (identity.length < 6) {
                    Swal.showValidationMessage('أدخل رقم هوية صحيح للمعيل');
                    return false;
                }

                if (state.status === 'idle' || state.status === 'error') {
                    Swal.showValidationMessage('انتظر حتى يكتمل البحث عن المعيل');
                    return false;
                }

                if (state.status === 'duplicate') {
                    Swal.showValidationMessage('رقم الهوية مرتبط بأكثر من ملف، لا يمكن النقل');
                    return false;
                }

                var payload = {
                    member_id: memberId,
                    guardian_identity: identity,
                    create_guardian: state.status === 'not_found' ? 1 : 0
                };

                if (state.status === 'not_found') {
                    payload.first_name = document.getElementById('transfer-first-name').value.trim();
                    payload.father_name = document.getElementById('transfer-father-name').value.trim();
                    payload.grand_father_name = document.getElementById('transfer-grand-father-name').value.trim();
                    payload.family_name = document.getElementById('transfer-family-name').value.trim();

                    if (!payload.first_name || !payload.family_name) {
                        Swal.showValidationMessage('أدخل الاسم الأول واسم العائلة للمعيل الجديد');
                        return false;
                    }
                }

                return fetch(transferUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                }).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (data) {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'تعذر تنفيذ النقل');
                        }
                        return data;
                    });
                }).catch(function (error) {
                    Swal.showValidationMessage(error && error.message ? error.message : 'تعذر تنفيذ النقل');
                    return false;
                });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            var info = result.value.data || {};
            var message = 'تم نقل الفرد إلى ملف المعيل ' + info.guardian_file_id +
                (info.guardian_name ? ' (' + info.guardian_name + ')' : '') +
                (info.created ? ' بعد إنشاء سجله.' : '.');

            var card = button.closest('.family-member-form') || button.closest('.family-member-card');
            if (card) {
                card.style.transition = 'opacity 400ms';
                card.style.opacity = '0';
                setTimeout(function () {
                    card.remove();

                    var container = document.querySelector('.family-scroll-row');
                    if (container && !container.querySelector('.family-member-card')) {
                        var empty = document.createElement('p');
                        empty.className = 'text-muted';
                        empty.textContent = 'لا يوجد أفراد أسرة.';
                        container.replaceWith(empty);
                    }
                }, 400);
            }

            Swal.fire({
                icon: 'success',
                title: 'تم النقل',
                text: message,
                confirmButtonText: 'حسناً'
            });
        });
    });
})();
</script>
@endpush
