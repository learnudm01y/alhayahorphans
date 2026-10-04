@extends('admin.dashboard.toolbars.index')

@push('styles')
<style>
    .sms-phone{max-width:340px;margin-inline:auto;border:10px solid #26262f;border-radius:36px;background:#fff;box-shadow:0 10px 26px rgba(0,0,0,.18);overflow:hidden}
    .sms-phone-bar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.65rem .9rem;background:linear-gradient(135deg,#0b7285,#1098ad);color:#fff}
    .sms-phone-contact{font-weight:600;font-size:1rem;max-width:210px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sms-phone-time{font-size:.8rem;opacity:.9}
    .sms-phone-body{min-height:180px;max-height:300px;overflow:auto;padding:.9rem;background:#eceff3;display:flex}
    .sms-bubble{max-width:92%;padding:.7rem .9rem;border-radius:16px 16px 4px 16px;background:#d3e6ff;color:#0f2a44;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.8;font-size:1.15rem;font-weight:500;box-shadow:0 1px 2px rgba(0,0,0,.15)}
    .sms-bubble:empty::before{content:"اكتب الرسالة لعرضها هنا";color:#868e96;font-style:italic;font-size:.95rem;font-weight:400}
    .sms-phone-input{border-top:1px solid #dee2e6;padding:.65rem .9rem;color:#adb5bd;font-size:.95rem;background:#fff}
</style>
@endpush

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">إرسال الرسائل النصية</h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary fs-6">الرصيد: {{ $credits ?? '—' }}</span>
            <a href="{{ route('admin.sms.templates.index') }}" class="btn btn-outline-secondary btn-sm">القوالب</a>
            <a href="{{ route('admin.sms.groups.index') }}" class="btn btn-outline-secondary btn-sm">المجموعات</a>
            <a href="{{ route('admin.sms.logs') }}" class="btn btn-outline-secondary btn-sm">السجل</a>
        </div>
    </div>

    @if(!$senders)
        <div class="alert alert-warning">
            تعذر جلب أسماء المرسلين من الخدمة. تأكد من قيمة <code>NSMS_TOKEN</code> في ملف <code>.env</code>.
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-single" type="button">فردي</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bulk" type="button">جماعي</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-excel" type="button">من ملف Excel</button></li>
            </ul>
        </div>

        <div class="card-body tab-content">

            {{-- ============ فردي ============ --}}
            <div class="tab-pane fade show active" id="tab-single">
                <div class="mb-3">
                    <label class="form-label">رقم الجوال</label>
                    <input type="text" id="single-mobile" class="form-control" placeholder="0599123456" dir="ltr">
                </div>
                @include('admin.sms._composer', ['id' => 'single', 'phone' => true, 'phoneTitle' => 'رسالة جديدة'])
                <button class="btn btn-primary mt-3" id="single-send">إرسال</button>
                <div id="single-result" class="mt-3 d-none"></div>
            </div>

            {{-- ============ جماعي ============ --}}
            <div class="tab-pane fade" id="tab-bulk">
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label">مجموعات محفوظة</label>
                        <select id="bulk-groups" class="form-select" multiple size="6">
                            @foreach($groups as $g) <option value="{{ $g->id }}">{{ $g->name }}</option> @endforeach
                        </select>
                        <small class="text-muted">Ctrl للاختيار المتعدد</small>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">أو أرقام إضافية (رقم في كل سطر أو بفواصل)</label>
                        <textarea id="bulk-numbers" class="form-control" rows="6" dir="ltr"></textarea>
                    </div>
                </div>
                @include('admin.sms._composer', ['id' => 'bulk', 'phone' => true, 'phoneTitle' => 'جميع المستلمين'])
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-outline-primary" id="bulk-summary">حساب الكلفة</button>
                    <button class="btn btn-primary" id="bulk-send">إرسال</button>
                </div>
                <div id="bulk-result" class="mt-3 d-none"></div>
            </div>

            {{-- ============ Excel ============ --}}
            <div class="tab-pane fade" id="tab-excel">
                <div class="alert alert-info">
                    الصف الأول في الملف = أسماء الأعمدة. استخدمها في الرسالة بين أقواس مثل <code>{name}</code>.
                    يفضّل أسماء أعمدة بدون مسافات (مثل <code>name</code> أو <code>amount</code>). والأفضل تنسيق عمود الجوال كـ Text.
                </div>
                <div class="input-group mb-3">
                    <input type="file" id="xl-file" class="form-control" accept=".xlsx,.xls,.csv">
                    <button class="btn btn-outline-primary" id="xl-upload">رفع وقراءة الملف</button>
                </div>
                <div id="xl-upload-result" class="d-none"></div>

                <div id="xl-step2" class="d-none">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">عمود رقم الجوال</label>
                            <select id="xl-mobile-col" class="form-select"></select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">المتغيرات (اضغط للإدراج في الرسالة)</label>
                            <div id="xl-chips" class="d-flex flex-wrap gap-1"></div>
                        </div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered" id="xl-preview"></table>
                        <small class="text-muted">اضغط على اسم أي عمود في المعاينة (أو على زر المتغير) لإدراجه في نص الرسالة بصيغة <code>{اسم_العمود}</code>.</small>
                    </div>

                    @include('admin.sms._composer', ['id' => 'xl', 'phone' => true, 'phoneTitle' => 'معاينة على أول صف'])
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-outline-primary" id="xl-summary">حساب الكلفة</button>
                        <button class="btn btn-primary" id="xl-send">إرسال</button>
                    </div>
                    <div id="xl-result" class="mt-3 d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
@php
$smsRoutes = [
    'single'      => route('admin.sms.single'),
    'bulkSummary' => route('admin.sms.bulk.summary'),
    'bulkSend'    => route('admin.sms.bulk.send'),
    'xlUpload'    => route('admin.sms.excel.upload'),
    'xlSummary'   => route('admin.sms.excel.summary'),
    'xlSend'      => route('admin.sms.excel.send'),
];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const routes = {!! json_encode($smsRoutes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = id => document.getElementById(id);
    let xlToken = null;
    let xlFirstRow = null;   // صف المعاينة الأول من الملف (يُستبدل في {المتغيرات})

    /* ---------- أدوات ---------- */
    async function post(url, body, isForm = false) {
        const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
        if (!isForm) headers['Content-Type'] = 'application/json';
        try {
            const res = await fetch(url, { method: 'POST', headers, body: isForm ? body : JSON.stringify(body) });
            const data = await res.json().catch(() => ({ message: 'استجابة غير صالحة من السيرفر' }));
            if (data.errors) data.message = Object.values(data.errors)[0][0];
            return { ok: res.ok, data };
        } catch (e) {
            return { ok: false, data: { message: 'تعذر الاتصال بالسيرفر' } };
        }
    }
    function show(el, ok, text) {
        el.className = 'alert mt-3 alert-' + (ok ? 'success' : 'danger');
        el.style.whiteSpace = 'pre-line';
        el.textContent = text;
    }
    function summaryText(d) {
        return [
            `عدد المستلمين: ${d.recipients}` + (d.invalid ? ` (تم تجاهل ${d.invalid} رقم غير صالح)` : ''),
            `المقبول: ${d.accepted} | المرفوض: ${d.rejected}`,
            d.segments != null ? `عدد الأجزاء: ${d.segments}` : null,
            `الكلفة: ${d.cost} | الرصيد المتاح: ${d.available ?? '—'}`,
            d.can_send ? 'الرصيد كافٍ للإرسال ✔' : 'الرصيد غير كافٍ ✖',
        ].filter(Boolean).join('\n');
    }
    const val = id => $(id).value;
    const busy = (btn, on) => { btn.disabled = on; };

    async function confirmAction(text) {
        if (typeof Swal !== 'undefined') {
            const r = await Swal.fire({
                icon: 'question',
                title: 'تأكيد الإرسال',
                text: text,
                showCancelButton: true,
                confirmButtonText: 'نعم، إرسال',
                cancelButtonText: 'إلغاء',
            });
            return r.isConfirmed;
        }
        return confirm(text);
    }

    /* ---------- عدّاد الأحرف والقوالب ---------- */
    function countSms(t) {
        const uni = /[^\x00-\x7F]/.test(t);                 // تقدير: غير GSM = Unicode
        const one = uni ? 70 : 160, multi = uni ? 67 : 153;
        const parts = t.length === 0 ? 0 : (t.length <= one ? 1 : Math.ceil(t.length / multi));
        return `${t.length} حرف — ${parts} جزء تقريبًا (${uni ? 'Unicode' : 'نص عادي'}). الرقم الفعلي يظهر في "حساب الكلفة".`;
    }
    /* معاينة الهاتف: النص كما سيظهر للمستلم (في Excel تُستبدل {المتغيرات} بقيم الصف الأول) */
    function renderBubble(ta) {
        const b = $(ta.id.replace('-message', '-bubble'));
        if (!b) return;
        let text = ta.value;
        if (ta.id === 'xl-message' && xlFirstRow) {
            text = text.replace(/\{([^{}]+)\}/g, (m, col) =>
                Object.prototype.hasOwnProperty.call(xlFirstRow, col) ? String(xlFirstRow[col] ?? '') : m);
        }
        b.textContent = text;
    }

    document.querySelectorAll('.sms-text').forEach(ta => {
        const out = $(ta.id.replace('-message', '-counter'));
        const upd = () => { out.textContent = countSms(ta.value); renderBubble(ta); };
        ta.addEventListener('input', upd); upd();
    });
    document.querySelectorAll('.tpl-select').forEach(sel => sel.addEventListener('change', () => {
        if (!sel.value) return;
        const ta = $(sel.dataset.target); ta.value = sel.value;
        ta.dispatchEvent(new Event('input'));
    }));

    // عنوان المحادثة في معاينة الفردي = رقم المستلم
    $('single-mobile').addEventListener('input', e => {
        $('single-contact').textContent = e.target.value.trim() || 'رسالة جديدة';
    });

    /* ---------- فردي ---------- */
    $('single-send').onclick = async e => {
        busy(e.target, true);
        const { ok, data } = await post(routes.single, {
            mobile: val('single-mobile'), message: val('single-message'), sender: val('single-sender'),
        });
        show($('single-result'), ok && data.ok, data.message || '');
        busy(e.target, false);
    };

    /* ---------- جماعي ---------- */
    const bulkPayload = () => ({
        group_ids: [...$('bulk-groups').selectedOptions].map(o => o.value),
        numbers: val('bulk-numbers'), message: val('bulk-message'), sender: val('bulk-sender'),
    });
    $('bulk-summary').onclick = async e => {
        busy(e.target, true);
        const { ok, data } = await post(routes.bulkSummary, bulkPayload());
        show($('bulk-result'), ok && data.can_send, ok ? summaryText(data) : data.message);
        busy(e.target, false);
    };
    $('bulk-send').onclick = async e => {
        if (!(await confirmAction('هل أنت متأكد من إرسال الرسالة لجميع المستلمين؟'))) return;
        busy(e.target, true);
        const { ok, data } = await post(routes.bulkSend, bulkPayload());
        show($('bulk-result'), ok, data.message);
        busy(e.target, false);
    };

    /* ---------- Excel ---------- */
    $('xl-upload').onclick = async e => {
        const f = $('xl-file').files[0];
        if (!f) return show($('xl-upload-result'), false, 'اختر ملفًا أولًا');
        busy(e.target, true);
        const fd = new FormData(); fd.append('file', f);
        const { ok, data } = await post(routes.xlUpload, fd, true);
        busy(e.target, false);
        if (!ok) { $('xl-step2').classList.add('d-none'); return show($('xl-upload-result'), false, data.message); }

        xlToken = data.token;
        xlFirstRow = data.preview[0] || null;
        show($('xl-upload-result'), true, `تمت قراءة ${data.total} صف و ${data.columns.length} عمود.`);

        // اختيار عمود الجوال تلقائيًا
        const sel = $('xl-mobile-col'); sel.innerHTML = '';
        data.columns.forEach(c => sel.add(new Option(c, c)));
        const guess = data.columns.find(c => /mobile|phone|tel|جوال|موبايل|هاتف|رقم/i.test(c));
        if (guess) sel.value = guess;

        // إدراج {اسم العمود} في نص الرسالة عند الضغط على اسمه في المعاينة أو على زر المتغير
        const insertVar = col => {
            const ta = $('xl-message');
            const s = ta.selectionStart ?? ta.value.length;
            const e2 = ta.selectionEnd ?? s;
            ta.value = ta.value.slice(0, s) + '{' + col + '}' + ta.value.slice(e2);
            ta.focus();
            ta.setSelectionRange(ta.value.length, ta.value.length);
            ta.dispatchEvent(new Event('input'));
        };

        // أزرار المتغيرات
        const chips = $('xl-chips'); chips.innerHTML = '';
        data.columns.forEach(c => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'btn btn-sm btn-outline-secondary'; b.textContent = '{' + c + '}';
            b.onclick = () => insertVar(c);
            chips.appendChild(b);
        });

        // معاينة أول 5 صفوف — الضغط على أي عمود يُدرج متغيره في الرسالة
        const t = $('xl-preview'); t.innerHTML = '';
        const head = t.insertRow();
        data.columns.forEach(c => {
            const th = document.createElement('th');
            th.textContent = c;
            th.title = 'اضغط لإدراج {' + c + '} في نص الرسالة';
            th.style.cursor = 'pointer';
            th.onclick = () => insertVar(c);
            head.appendChild(th);
        });
        data.preview.forEach(r => {
            const tr = t.insertRow();
            data.columns.forEach(c => {
                const td = tr.insertCell();
                td.textContent = r[c] ?? '';
                td.style.cursor = 'pointer';
                td.title = 'اضغط لإدراج {' + c + '}';
                td.onclick = () => insertVar(c);
            });
        });

        $('xl-step2').classList.remove('d-none');
        renderBubble($('xl-message'));   // إعادة الرسم بقيم الصف الأول
    };
    const xlPayload = () => ({
        token: xlToken, mobile_column: val('xl-mobile-col'), message: val('xl-message'), sender: val('xl-sender'),
    });
    $('xl-summary').onclick = async e => {
        busy(e.target, true);
        const { ok, data } = await post(routes.xlSummary, xlPayload());
        show($('xl-result'), ok && data.can_send, ok ? summaryText(data) : data.message);
        busy(e.target, false);
    };
    $('xl-send').onclick = async e => {
        if (!(await confirmAction('هل أنت متأكد من الإرسال لجميع صفوف الملف؟'))) return;
        busy(e.target, true);
        const { ok, data } = await post(routes.xlSend, xlPayload());
        show($('xl-result'), ok, data.message);
        busy(e.target, false);
    };
});
</script>
@endpush
