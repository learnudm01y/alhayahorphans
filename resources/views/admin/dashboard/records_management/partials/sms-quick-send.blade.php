{{-- مودال "إرسال رسالة لولي الأمر" من صفحة عرض السجل: جوال قابل للتعديل + قوالب الرسائل + إرسال عبر admin.sms.single --}}
@can('إرسال رسائل نصية')
@php
    $smsRecordName = trim(($data->data_first_name ?? '') . ' ' . ($data->data_father_name ?? '') . ' ' . ($data->data_grand_father_name ?? '') . ' ' . ($data->data_family_name ?? ''));
    $smsRecordPrimaryPhone = (string) ($data->data_phone_number ?? '');
    $smsRecordAltPhone = (string) ($data->data_alt_phone_number ?? '');
@endphp
<div class="modal fade" id="recordSmsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h2 class="fw-bold text-gray-800">
                    <i class="bi bi-chat-dots text-success me-2"></i>
                    إرسال رسالة لولي الأمر
                </h2>
                <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>

            <div class="modal-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">اسم ولي الأمر</label>
                        <input type="text" id="rsms-name" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                        <input type="text" id="rsms-mobile" class="form-control" dir="ltr" maxlength="30">
                        <button type="button" class="btn btn-link btn-sm p-0 d-none" id="rsms-swap"></button>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">اسم المرسل <span class="text-danger">*</span></label>
                        <select id="rsms-sender" class="form-select"></select>
                        <small id="rsms-sender-hint" class="text-danger d-none">
                            تعذر جلب أسماء المرسلين من الخدمة، تحقق من قيمة NSMS_TOKEN في ملف .env
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">قوالب الرسائل</label>
                        <select id="rsms-template" class="form-select">
                            <option value="">— اكتب الرسالة يدوياً —</option>
                        </select>
                        <small class="text-muted">المتغيرات المدعومة: <code>{name}</code> و<code>{file}</code></small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">نص الرسالة <span class="text-danger">*</span></label>
                    <textarea id="rsms-message" class="form-control" rows="6" maxlength="2000"></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted" id="rsms-counter"></small>
                        <button class="btn btn-link btn-sm p-0" type="button" id="rsms-insert-name">إدراج الاسم</button>
                    </div>
                </div>

                <div id="rsms-result" class="d-none"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-success" id="rsms-send" disabled>
                    <i class="bi bi-send me-1"></i>
                    إرسال الرسالة
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('recordSmsModal');
    if (!modalEl) return;

    const $ = id => document.getElementById(id);
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const routes = {
        composer: @json(route('admin.sms.composerData')),
        single: @json(route('admin.sms.single')),
    };
    const phones = {
        primary: @json($smsRecordPrimaryPhone),
        alt: @json($smsRecordAltPhone),
    };
    let usingAlt = false;

    let composer = null;
    let sending = false;

    const getModal = () => (window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null);

    async function fetchJson(url, options) {
        try {
            const res = await fetch(url, Object.assign({
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            }, options || {}));
            const data = await res.json().catch(() => ({}));
            return { ok: res.ok, data };
        } catch (e) {
            return { ok: false, data: { message: 'تعذر الاتصال بالسيرفر' } };
        }
    }

    function countSms(text) {
        const uni = /[^\x00-\x7F]/.test(text);
        const one = uni ? 70 : 160, multi = uni ? 67 : 153;
        const parts = text.length === 0 ? 0 : (text.length <= one ? 1 : Math.ceil(text.length / multi));
        return text.length + ' حرف — ' + parts + ' جزء تقريبًا' + (uni ? ' (Unicode)' : '');
    }

    function updateCounter() {
        $('rsms-counter').textContent = countSms($('rsms-message').value);
    }

    function applyVars(text) {
        return String(text || '')
            .replace(/\{name\}/g, @json($smsRecordName))
            .replace(/\{file\}/g, @json((string) ($data->file_id_number ?? '')));
    }

    function insertAtCursor(textarea, value) {
        const s = textarea.selectionStart ?? textarea.value.length;
        const e = textarea.selectionEnd ?? s;
        textarea.value = textarea.value.slice(0, s) + value + textarea.value.slice(e);
        textarea.focus();
        textarea.setSelectionRange(s + value.length, s + value.length);
        updateCounter();
    }

    function showResult(ok, message) {
        const box = $('rsms-result');
        box.className = 'alert mt-3 alert-' + (ok ? 'success' : 'danger');
        box.style.whiteSpace = 'pre-line';
        box.textContent = message || '';
    }

    function fillSenders() {
        const sel = $('rsms-sender');
        sel.innerHTML = '';
        const senders = (composer && composer.senders) || [];
        senders.forEach(s => sel.add(new Option(s, s)));
        const def = composer && composer.default_sender;
        if (def && senders.includes(def)) sel.value = def;
        $('rsms-sender-hint').classList.toggle('d-none', senders.length > 0);
        $('rsms-send').disabled = senders.length === 0;
    }

    function fillTemplates() {
        const sel = $('rsms-template');
        sel.innerHTML = '<option value="">— اكتب الرسالة يدوياً —</option>';
        ((composer && composer.templates) || []).forEach(t => {
            sel.add(new Option(t.title, t.body || ''));
        });
    }

    function applyTemplate() {
        const body = $('rsms-template').value;
        if (body) {
            $('rsms-message').value = applyVars(body);
            updateCounter();
        }
    }

    function updateSwapButton() {
        const btn = $('rsms-swap');
        const hasAlt = phones.alt !== '';
        btn.classList.toggle('d-none', !hasAlt);
        if (!hasAlt) return;
        btn.textContent = usingAlt ? 'استخدام الجوال الأساسي' : 'استخدام الجوال الإضافي';
    }

    async function openModal() {
        if (!window.bootstrap) return;
        getModal().show();

        usingAlt = false;
        $('rsms-name').value = @json($smsRecordName);
        $('rsms-mobile').value = phones.primary;
        $('rsms-message').value = '';
        $('rsms-template').value = '';
        $('rsms-result').className = 'd-none';
        $('rsms-send').disabled = true;
        updateSwapButton();
        updateCounter();

        if (!composer) {
            const c = await fetchJson(routes.composer);
            composer = c.ok ? c.data : { templates: [], senders: [] };
            fillTemplates();
            fillSenders();
        }

        const senders = (composer && composer.senders) || [];
        $('rsms-send').disabled = senders.length === 0;
    }

    document.getElementById('sms-record-open').addEventListener('click', openModal);

    $('rsms-template').addEventListener('change', applyTemplate);
    $('rsms-message').addEventListener('input', updateCounter);
    $('rsms-insert-name').addEventListener('click', () => {
        const name = @json($smsRecordName);
        if (name) insertAtCursor($('rsms-message'), name);
    });
    $('rsms-swap').addEventListener('click', () => {
        usingAlt = !usingAlt;
        $('rsms-mobile').value = usingAlt ? phones.alt : phones.primary;
        updateSwapButton();
    });

    $('rsms-send').addEventListener('click', async function () {
        if (sending) return;

        const mobile = $('rsms-mobile').value.trim();
        const message = $('rsms-message').value.trim();
        const sender = $('rsms-sender').value;

        if (!mobile) { showResult(false, 'أدخل رقم الجوال أولاً.'); return; }
        if (!sender) { showResult(false, 'اختر اسم المرسل أولاً.'); return; }
        if (!message) { showResult(false, 'اكتب نص الرسالة أولاً.'); return; }

        if (typeof Swal !== 'undefined') {
            const confirm = await Swal.fire({
                icon: 'question',
                title: 'تأكيد الإرسال',
                html: 'إرسال الرسالة إلى <strong>' + mobile + '</strong>؟',
                showCancelButton: true,
                confirmButtonText: 'نعم، إرسال',
                cancelButtonText: 'إلغاء',
            });
            if (!confirm.isConfirmed) return;
        }

        sending = true;
        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جارٍ الإرسال...';

        const r = await fetchJson(routes.single, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ mobile: mobile, message: message, sender: sender }),
        });

        const ok = r.ok && r.data.ok;
        let resultMsg = r.data.message || (ok ? 'تم الإرسال بنجاح.' : 'فشل الإرسال.');
        if (r.data.errors) resultMsg = Object.values(r.data.errors)[0][0] || resultMsg;
        showResult(ok, resultMsg);

        sending = false;
        btn.disabled = false;
        btn.innerHTML = originalHtml;

        if (ok && typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: 'تم الإرسال', text: r.data.message, timer: 1800, showConfirmButton: false });
        }
    });
});
</script>
@endcan
