{{-- مودال "إرسال رسالة للمكفول": اسم المكفول + جوال قابل للتعديل + رابط الدخول السريع + قوالب الرسائل --}}
@can('إرسال رسائل نصية')
<div class="modal fade" id="smsQuickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h2 class="fw-bold text-gray-800">
                    <i class="ki-duotone ki-message-text fs-1 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    إرسال رسالة للمكفول
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <div class="modal-body py-10 px-lg-17">
                <div id="smsq-loading" class="text-center py-10">
                    <span class="spinner-border spinner-border-lg text-primary"></span>
                    <div class="text-muted fs-7 mt-3">جارٍ تحميل بيانات المكفول...</div>
                </div>

                <div id="smsq-content" class="d-none">
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">اسم المكفول</label>
                            <input type="text" id="smsq-name" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                            <input type="text" id="smsq-mobile" class="form-control" dir="ltr"
                                   placeholder="0599123456" maxlength="30">
                            <small class="text-muted">قابل للتعديل قبل الإرسال</small>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label">رابط الدخول السريع لتحديث البيانات</label>
                        <div class="input-group">
                            <input type="text" id="smsq-link" class="form-control" dir="ltr" readonly>
                            <button class="btn btn-light" type="button" id="smsq-copy" title="نسخ الرابط">
                                <i class="ki-duotone ki-copy fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                            <button class="btn btn-outline-primary" type="button" id="smsq-insert-link"
                                    title="إدراج الرابط في نص الرسالة">إدراج الرابط</button>
                        </div>
                        <small id="smsq-link-hint" class="text-muted"></small>
                    </div>

                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">اسم المرسل <span class="text-danger">*</span></label>
                            <select id="smsq-sender" class="form-select"></select>
                            <small id="smsq-sender-hint" class="text-danger d-none">
                                تعذر جلب أسماء المرسلين من الخدمة، تحقق من قيمة NSMS_TOKEN في ملف .env
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">قوالب الرسائل</label>
                            <select id="smsq-template" class="form-select">
                                <option value="">— الرسالة الافتراضية —</option>
                            </select>
                            <small class="text-muted">المتغيرات المدعومة: <code>{name}</code> و<code>{link}</code> و<code>{file}</code></small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">نص الرسالة <span class="text-danger">*</span></label>
                        <textarea id="smsq-message" class="form-control" rows="6" maxlength="2000"></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted" id="smsq-counter"></small>
                            <button class="btn btn-link btn-sm p-0" type="button" id="smsq-insert-name">إدراج اسم المكفول</button>
                        </div>
                    </div>

                    <div id="smsq-result" class="d-none"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-success" id="smsq-send" disabled>
                    <i class="ki-duotone ki-message-text fs-4 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    إرسال الرسالة
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('smsQuickModal');
    if (!modalEl) return;

    const $ = id => document.getElementById(id);
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const routes = {
        edit: id => '/admin/sponsorships/' + id + '/edit',
        composer: @json(route('admin.sms.composerData')),
        single: @json(route('admin.sms.single')),
    };
    const DEFAULT_MESSAGE = 'تحديث بيانات اليتيم\nاليتيم: {name}\nنرجو تحديث البيانات من خلال الرابط السريع:\n{link}';

    let composer = null;                       // {templates, senders}
    let current = { id: null, name: '', link: '', file: '', phone: '' };
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
        $('smsq-counter').textContent = countSms($('smsq-message').value);
    }

    /** استبدال المتغيرات في القالب بالقيم الفعلية */
    function applyVars(text) {
        return String(text || '')
            .replace(/\{name\}/g, current.name || '')
            .replace(/\{link\}/g, current.link || '')
            .replace(/\{file\}/g, current.file || '');
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
        const box = $('smsq-result');
        box.className = 'alert mt-3 alert-' + (ok ? 'success' : 'danger');
        box.style.whiteSpace = 'pre-line';
        box.textContent = message || '';
    }

    function fillSenders() {
        const sel = $('smsq-sender');
        sel.innerHTML = '';
        const senders = (composer && composer.senders) || [];
        senders.forEach(s => sel.add(new Option(s, s)));
        $('smsq-sender-hint').classList.toggle('d-none', senders.length > 0);
        $('smsq-send').disabled = senders.length === 0;
    }

    function fillTemplates() {
        const sel = $('smsq-template');
        sel.innerHTML = '<option value="">— الرسالة الافتراضية —</option>';
        ((composer && composer.templates) || []).forEach(t => {
            const opt = new Option(t.title, t.body || '');
            opt.dataset.templateId = t.id;
            sel.add(opt);
        });
    }

    function applyTemplate() {
        const sel = $('smsq-template');
        const body = sel.value;
        $('smsq-message').value = body ? applyVars(body) : applyVars(DEFAULT_MESSAGE);
        updateCounter();
    }

    async function loadRow(id) {
        $('smsq-loading').classList.remove('d-none');
        $('smsq-content').classList.add('d-none');
        $('smsq-result').className = 'd-none';
        $('smsq-message').value = '';
        $('smsq-mobile').value = '';
        $('smsq-link').value = '';
        $('smsq-link-hint').textContent = '';
        $('smsq-template').value = '';
        $('smsq-send').disabled = true;

        if (!composer) {
            const c = await fetchJson(routes.composer);
            composer = c.ok ? c.data : { templates: [], senders: [] };
            fillTemplates();
            fillSenders();
        }

        const r = await fetchJson(routes.edit(id));
        const f = r.ok ? (r.data || {}) : {};

        current.name = f.orphan_name || current.name || '';
        current.file = String(f.internal_file_number || f.external_file_number || '').trim();
        current.phone = String(f.guardian_phone || f.guardian_alt_phone || '').trim();

        const identity = String(f.identity_number || '').trim();
        const base = @json(rtrim(url('/'), '/'));
        current.link = (identity && current.file) ? base + '/s/' + identity + '-' + current.file : '';

        $('smsq-name').value = current.name;
        $('smsq-mobile').value = current.phone;
        $('smsq-link').value = current.link || '— لا يوجد رابط (رقم الهوية أو رقم الملف غير مكتمل) —';
        $('smsq-link-hint').textContent = current.link
            ? 'يفتح صفحة تحديث البيانات مباشرة دون تسجيل دخول.'
            : 'لا يمكن توليد الرابط: تأكد من رقم الهوية ورقم الملف الداخلي.';

        applyTemplate();

        // إعادة تفعيل زر الإرسال بحسب توفر أسماء المرسلين
        const senders = (composer && composer.senders) || [];
        $('smsq-send').disabled = senders.length === 0;

        $('smsq-loading').classList.add('d-none');
        $('smsq-content').classList.remove('d-none');
        if (!r.ok) showResult(false, 'تعذر جلب بيانات المكفول، يمكنك إدخال البيانات يدويًا.');
    }

    /* ---- فتح المودال من زر الصف ---- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.send-sms-sponsorship');
        if (!btn) return;

        current = {
            id: btn.dataset.id,
            name: btn.dataset.name || '',
            link: '',
            file: '',
            phone: '',
        };

        if (!window.bootstrap) return;
        getModal().show();
        loadRow(current.id);
    });

    /* ---- أزرار داخل المودال ---- */
    $('smsq-template').addEventListener('change', applyTemplate);
    $('smsq-message').addEventListener('input', updateCounter);
    $('smsq-insert-link').addEventListener('click', () => {
        if (!current.link) return;
        insertAtCursor($('smsq-message'), current.link);
    });
    $('smsq-insert-name').addEventListener('click', () => {
        if (!current.name) return;
        insertAtCursor($('smsq-message'), current.name);
    });
    $('smsq-copy').addEventListener('click', async () => {
        if (!current.link) return;
        try {
            await navigator.clipboard.writeText(current.link);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'success', title: 'تم نسخ الرابط', timer: 1200, showConfirmButton: false });
            }
        } catch (err) {
            $('smsq-link').select();
            document.execCommand('copy');
        }
    });

    $('smsq-send').addEventListener('click', async function () {
        if (sending) return;

        const mobile = $('smsq-mobile').value.trim();
        const message = $('smsq-message').value.trim();
        const sender = $('smsq-sender').value;

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
