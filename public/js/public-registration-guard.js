/**
 * حارس حفظ التسجيل العام: يمنع الإرسال المتكرر ويعرض شريط تقدم بنسبة وسرعة الرفع.
 *
 * - لا يحتاج تعديل HTML ولا كود الصفحة: يعترض طلب الحفظ (XHR أو fetch أو إرسال النموذج العادي)
 *   المتجه إلى مسار الحفظ، فيضيف إليه معرّف طلب فريد (X-Request-Id + حقل request_id)،
 *   ويحجب أي طلب حفظ ثانٍ ما دام الأول قيد التنفيذ، ويعرض شريط تقدم فوق الصفحة.
 * - المرحلة 1: رفع من المتصفح للخادم (نسبة وسرعة فعلية). المرحلة 2: معالجة الخادم (تقدّم يُقرأ
 *   من مسار save-progress بنفس معرّف الطلب).
 * - بعد أي فشل يُولَّد معرّف جديد فيستطيع المستخدم إعادة المحاولة؛ وبعد النجاح تعيد إعادة
 *   الإرسال بنفس المعرّف النتيجة السابقة بدل حفظ مكرر.
 *
 * التركيب: ضع <script src="{{ asset('js/public-registration-guard.js') }}"></script>
 * في <head> أو قبل بقية سكربتات الصفحة (يفضّل قبلها ليُثبَّت الاعتراض أولاً).
 *
 * إعدادات اختيارية قبل تحميل الملف:
 *   window.PUBLIC_REGISTRATION_STORE_PATH    = '/users/generalRegistration/store';
 *   window.PUBLIC_REGISTRATION_PROGRESS_URL  = '/users/generalRegistration/save-progress';
 */
(function () {
    'use strict';

    if (window.__publicRegistrationGuard) { return; }
    window.__publicRegistrationGuard = true;

    var STORE_PATH = String(window.PUBLIC_REGISTRATION_STORE_PATH || '/users/generalRegistration/store').replace(/\/+$/, '');
    var PROGRESS_URL = String(window.PUBLIC_REGISTRATION_PROGRESS_URL || '/users/generalRegistration/save-progress').replace(/\/+$/, '');

    var NativeXhrOpen = XMLHttpRequest.prototype.open;
    var NativeXhrSend = XMLHttpRequest.prototype.send;
    var NativeXhrSetHeader = XMLHttpRequest.prototype.setRequestHeader;
    var nativeFetch = window.fetch ? window.fetch.bind(window) : null;

    // ------------------------------------------------------------------
    // أدوات
    // ------------------------------------------------------------------
    function uuid() {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
        var a = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(a);
        return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }

    function isStoreUrl(url) {
        try {
            var u = new URL(String(url), window.location.href);
            return u.origin === window.location.origin && u.pathname.replace(/\/+$/, '') === STORE_PATH;
        } catch (e) {
            return false;
        }
    }

    function fmtBytes(n) {
        if (!isFinite(n) || n < 0) { n = 0; }
        if (n >= 1073741824) { return (n / 1073741824).toFixed(2) + ' GB'; }
        if (n >= 1048576) { return (n / 1048576).toFixed(1) + ' MB'; }
        if (n >= 1024) { return Math.round(n / 1024) + ' KB'; }
        return Math.round(n) + ' B';
    }

    // ------------------------------------------------------------------
    // الحالة
    // ------------------------------------------------------------------
    var requestId = uuid();   // معرّف واحد لكل «محاولة حفظ»؛ يتجدد بعد الفشل فقط
    var busy = false;
    var inflight = null;      // وعد fetch الجاري (لمن يكرر الاستدعاء)
    var netWeight = 0.1;
    var pollTimer = null;
    var serverState = null;
    var shown = 0;

    // ------------------------------------------------------------------
    // الواجهة
    // ------------------------------------------------------------------
    var overlay, elTitle, elMsg, elFill, elPct, elStats, built = false;

    function buildUi() {
        if (built || !document.body) { return; }
        built = true;

        var style = document.createElement('style');
        style.textContent =
            '#prg-overlay{position:fixed;inset:0;z-index:2147483000;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.65);direction:rtl;font-family:inherit}' +
            '#prg-overlay.prg-show{display:flex}' +
            '#prg-card{width:min(92vw,460px);background:#fff;color:#0f172a;border-radius:14px;padding:22px 22px 18px;box-shadow:0 20px 50px rgba(0,0,0,.35)}' +
            '#prg-title{font-size:18px;font-weight:700;margin:0 0 4px}' +
            '#prg-msg{font-size:14px;color:#475569;min-height:20px;margin:0 0 14px;word-break:break-word}' +
            '#prg-track{height:16px;background:#e2e8f0;border-radius:999px;overflow:hidden}' +
            '#prg-fill{height:100%;width:0;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:999px;transition:width .25s ease}' +
            '#prg-pct{font-size:22px;font-weight:800;margin:10px 0 2px;text-align:center}' +
            '#prg-stats{display:flex;flex-wrap:wrap;gap:6px 16px;justify-content:center;font-size:13px;color:#334155}' +
            '#prg-note{font-size:12px;color:#64748b;text-align:center;margin-top:10px}';
        document.head.appendChild(style);

        overlay = document.createElement('div');
        overlay.id = 'prg-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML =
            '<div id="prg-card">' +
              '<h3 id="prg-title">جاري حفظ الطلب</h3>' +
              '<p id="prg-msg"></p>' +
              '<div id="prg-track"><div id="prg-fill"></div></div>' +
              '<div id="prg-pct">0%</div>' +
              '<div id="prg-stats"></div>' +
              '<div id="prg-note">يرجى عدم إغلاق الصفحة وعدم الضغط مرة أخرى حتى اكتمال الحفظ</div>' +
            '</div>';
        document.body.appendChild(overlay);

        elTitle = overlay.querySelector('#prg-title');
        elMsg = overlay.querySelector('#prg-msg');
        elFill = overlay.querySelector('#prg-fill');
        elPct = overlay.querySelector('#prg-pct');
        elStats = overlay.querySelector('#prg-stats');
    }

    function showOverlay(title) {
        buildUi();
        if (!overlay) { return; }
        shown = 0;
        elTitle.textContent = title || 'جاري حفظ الطلب';
        render(0, 'جاري إرسال البيانات إلى الخادم...', '');
        overlay.classList.add('prg-show');
    }

    function hideOverlay() {
        if (overlay) { overlay.classList.remove('prg-show'); }
    }

    function render(percent, message, statsHtml) {
        if (!built) { return; }
        percent = Math.max(shown, Math.min(100, percent)); // النسبة لا تتراجع
        shown = percent;
        elFill.style.width = percent + '%';
        elPct.textContent = Math.floor(percent) + '%';
        if (message !== undefined) { elMsg.textContent = message; }
        if (statsHtml !== undefined) { elStats.innerHTML = statsHtml; }
    }

    // ------------------------------------------------------------------
    // تقدّم الخادم (مسار save-progress)
    // ------------------------------------------------------------------
    function startPolling() {
        stopPolling();
        var id = requestId;
        pollTimer = setInterval(function () {
            if (!nativeFetch) { return; }
            nativeFetch(PROGRESS_URL + '/' + encodeURIComponent(id), {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
                credentials: 'omit'
            }).then(function (r) { return r.ok ? r.json() : null; })
              .then(function (data) {
                  if (!data || id !== requestId) { return; }
                  serverState = data;
                  renderServer();
                  // الطلب الأول اكتمل لكن ردّه لم يصل (انقطاع): نحوّل المستخدم مباشرة
                  if (data.status === 'done' && data.redirect && !busy) { window.location.href = data.redirect; }
              })
              .catch(function () { /* خطأ مؤقت في الاستعلام: نتجاهله */ });
        }, 800);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function renderServer() {
        var s = serverState;
        if (!s || s.status === 'pending') { return; }
        var parts = [];
        if (s.files_total > 0) {
            parts.push('<span>المرفقات: <b>' + (s.files_done || 0) + ' / ' + s.files_total + '</b></span>');
        }
        elTitle.textContent = 'جاري معالجة الطلب وحفظه';
        render(Math.min(99, netWeight * 100 + (1 - netWeight) * (s.percent || 0)), s.message || '', parts.join(''));
    }

    // ------------------------------------------------------------------
    // تتبّع طلب حفظ (XHR)
    // ------------------------------------------------------------------
    function totalFileBytes(body) {
        var total = 0;
        try {
            if (body && typeof body.forEach === 'function' && window.FormData && body instanceof FormData) {
                body.forEach(function (v) {
                    if (typeof Blob !== 'undefined' && v instanceof Blob && v.size > 0) { total += v.size; }
                });
            }
        } catch (e) { /* ignore */ }
        return total;
    }

    function track(xhr, body) {
        var bytes = totalFileBytes(body);
        netWeight = bytes > 0 ? 0.4 : 0.1;
        serverState = null;
        showOverlay(bytes > 0 ? 'جاري رفع الملفات وحفظ الطلب' : 'جاري حفظ الطلب');

        var samples = [];
        if (xhr.upload) {
            xhr.upload.addEventListener('progress', function (e) {
                if (!e.lengthComputable) { return; }
                var now = Date.now();
                samples.push({ t: now, l: e.loaded });
                while (samples.length > 2 && now - samples[0].t > 2000) { samples.shift(); }
                var first = samples[0], last = samples[samples.length - 1];
                var dt = (last.t - first.t) / 1000;
                var speed = dt > 0 ? (last.l - first.l) / dt : 0;
                var remaining = speed > 0 ? Math.ceil((e.total - e.loaded) / speed) : null;
                var stats =
                    '<span>سرعة الرفع: <b>' + fmtBytes(speed) + '/ث</b></span>' +
                    '<span>المرفوع: <b>' + fmtBytes(e.loaded) + ' / ' + fmtBytes(e.total) + '</b></span>' +
                    (remaining !== null ? '<span>المتبقي: <b>' + remaining + ' ث</b></span>' : '');
                if (!serverState) {
                    render(netWeight * 100 * (e.total > 0 ? e.loaded / e.total : 0), 'جاري رفع البيانات والملفات إلى الخادم...', stats);
                }
            });
            xhr.upload.addEventListener('load', function () {
                render(netWeight * 100, 'تم الرفع إلى الخادم — جاري المعالجة والحفظ...', '');
            });
        }

        // الاستعلام عن تقدّم الخادم من البداية (المعالجة تبدأ بعد اكتمال الرفع)
        startPolling();

        xhr.addEventListener('loadend', function () { settle(xhr); });
    }

    function pollOnce(id) {
        if (!nativeFetch) { return Promise.resolve(null); }
        return nativeFetch(PROGRESS_URL + '/' + encodeURIComponent(id), {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'omit'
        }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
    }

    function settle(xhr) {
        var data = null;
        try { data = JSON.parse(xhr.responseText); } catch (e) { /* ليس JSON */ }

        // الرد غير JSON (مثلاً صفحة بعد إعادة توجيه): نسأل الخادم عن النتيجة الفعلية قبل الحسم
        if (data === null && xhr.status >= 200 && xhr.status < 300) {
            var id = requestId;
            pollOnce(id).then(function (state) {
                if (state) { serverState = state; }
                finish(xhr, data);
            });
            return;
        }

        finish(xhr, data);
    }

    function finish(xhr, data) {
        var status = xhr.status;

        // الطلب الأول ما زال يُعالَج على الخادم (إعادة اتصال): نبقى ننتظر ونتابع التقدّم
        if (status === 409 && data && data.processing) {
            busy = false;
            render(Math.max(shown, 10), (data && data.message) || 'طلبك قيد الحفظ، يرجى الانتظار...', '');
            startPolling();
            return;
        }

        stopPolling();
        var failedByServer = !!(serverState && serverState.status === 'failed');
        var ok = status >= 200 && status < 300 && !(data && data.success === false) && !failedByServer;

        if (ok) {
            render(100, 'تم الحفظ بنجاح', '');
            busy = false;
            // إن لم تُحوِّل الصفحة المستخدم (واجهة تعرض نتيجتها بنفسها) نخفي الشريط
            setTimeout(hideOverlay, 2500);
            return;
        }

        // فشل: نخفي الشريط ونسمح بإعادة المحاولة. لا نُجدّد المعرّف عمداً:
        // الخادم يحرّر المعرّف عند أي فشل، فتُعالَج المحاولة الجديدة عادياً؛ أما إن كان الطلب الأول قد نجح
        // فعلاً وضاع ردّه (انقطاع) فتُعاد النتيجة السابقة بدل حفظ مكرر، وإن كان ما يزال يعمل يردّ 409.
        hideOverlay();
        busy = false;
    }

    // ------------------------------------------------------------------
    // اعتراض XMLHttpRequest
    // ------------------------------------------------------------------
    XMLHttpRequest.prototype.open = function (method, url) {
        this.__prgInfo = { method: String(method || 'GET').toUpperCase(), url: String(url) };
        return NativeXhrOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (body) {
        var info = this.__prgInfo;
        if (info && info.method === 'POST' && isStoreUrl(info.url)) {
            if (busy) {
                // طلب حفظ آخر قيد التنفيذ: لا نكرر الإرسال
                return;
            }
            busy = true;
            try {
                NativeXhrSetHeader.call(this, 'X-Request-Id', requestId);
            } catch (e) { /* ignore */ }
            try {
                if (window.FormData && body instanceof FormData && !body.has('request_id')) {
                    body.append('request_id', requestId);
                }
            } catch (e) { /* ignore */ }
            track(this, body);
        }
        return NativeXhrSend.apply(this, arguments);
    };

    // ------------------------------------------------------------------
    // اعتراض fetch: نُنفّذ طلب الحفظ عبر XHR لنحصل على نسبة الرفع، ونُرجع Response متوافقاً
    // ------------------------------------------------------------------
    function parseHeaders(raw) {
        var h = new Headers();
        (raw || '').trim().split(/[\r\n]+/).forEach(function (line) {
            var i = line.indexOf(':');
            if (i > 0) {
                try { h.append(line.slice(0, i).trim(), line.slice(i + 1).trim()); } catch (e) { /* ignore */ }
            }
        });
        return h;
    }

    if (nativeFetch) {
        window.fetch = function (input, init) {
            var isRequestObject = typeof Request !== 'undefined' && input instanceof Request;
            var url = isRequestObject ? input.url : String(input);
            var method = String((init && init.method) || (isRequestObject ? input.method : 'GET')).toUpperCase();

            if (isRequestObject || method !== 'POST' || !isStoreUrl(url)) {
                return nativeFetch(input, init);
            }

            // طلب حفظ ثانٍ أثناء الأول: نشاركه نفس النتيجة بدل إرسال طلب جديد
            if (busy) {
                if (inflight) {
                    return inflight.then(function (r) { return r.clone(); });
                }
                // طلب حفظ جارٍ عبر قناة أخرى: لا نكرر الإرسال
                return new Promise(function () { /* لا يُحسم؛ الطلب الأول سيُكمل ويحوّل الصفحة */ });
            }

            inflight = new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);

                var headers = (init && init.headers) || {};
                if (typeof Headers !== 'undefined' && headers instanceof Headers) {
                    headers.forEach(function (value, key) { xhr.setRequestHeader(key, value); });
                } else if (Array.isArray(headers)) {
                    headers.forEach(function (pair) { xhr.setRequestHeader(pair[0], pair[1]); });
                } else {
                    Object.keys(headers).forEach(function (key) { xhr.setRequestHeader(key, headers[key]); });
                }

                xhr.onload = function () {
                    resolve(new Response(xhr.responseText, {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        headers: parseHeaders(xhr.getAllResponseHeaders())
                    }));
                };
                xhr.onerror = function () { reject(new TypeError('Network request failed')); };
                xhr.ontimeout = function () { reject(new TypeError('Network request timed out')); };
                xhr.onabort = function () { reject(new DOMException('Aborted', 'AbortError')); };

                xhr.send((init && init.body) || null); // send المعترَضة تضيف المعرّف وتتولى التتبّع
            });

            var clear = function () { inflight = null; };
            inflight.then(clear, clear);

            return inflight.then(function (r) { return r.clone(); });
        };
    }

    // ------------------------------------------------------------------
    // إرسال النموذج العادي (بدون JS) و requestSubmit و form.submit()
    // ------------------------------------------------------------------
    function isStoreForm(form) {
        return !!form && form.tagName === 'FORM' && isStoreUrl(form.getAttribute('action') || '');
    }

    function prepareNativeSubmit(form) {
        var field = form.querySelector('input[name="request_id"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'request_id';
            form.appendChild(field);
        }
        field.value = requestId;

        busy = true;
        netWeight = 0.05;
        serverState = null;
        showOverlay('جاري حفظ الطلب');
        startPolling();
        // الصفحة ستنتقل عند اكتمال الحفظ؛ وإن عادت بخطأ تُحمَّل من جديد فتتصفّر الحالة
    }

    // (أ) مرحلة الالتقاط: نحجب أي إرسال ثانٍ قبل أن يصل الحدث لمعالجات الصفحة
    document.addEventListener('submit', function (e) {
        if (busy && isStoreForm(e.target)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);

    // (ب) مرحلة الفقاعة: الإرسال العادي إن لم تُلغِه الصفحة (إن ألغته وأرسلت بـ XHR/fetch فالاعتراض أعلاه يتولى الباقي)
    document.addEventListener('submit', function (e) {
        if (busy || e.defaultPrevented || !isStoreForm(e.target)) { return; }
        prepareNativeSubmit(e.target);
    }, false);

    // (ج) form.submit() البرمجي لا يطلق حدث submit، فنغلّفه للنماذج الموجّهة لمسار الحفظ
    function patchProgrammaticSubmit() {
        var nativeSubmit = HTMLFormElement.prototype.submit;
        Array.prototype.forEach.call(document.forms, function (form) {
            if (form.__prgPatched || !isStoreForm(form)) { return; }
            form.__prgPatched = true;
            form.submit = function () {
                if (busy) { return; }
                prepareNativeSubmit(form);
                return nativeSubmit.call(form);
            };
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', patchProgrammaticSubmit);
    } else {
        patchProgrammaticSubmit();
    }

    // ------------------------------------------------------------------
    // عودة الصفحة من الكاش (زر الرجوع): نفك الحجب
    // ------------------------------------------------------------------
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            busy = false;
            stopPolling();
            hideOverlay();
            requestId = uuid();
        }
    });
})();
