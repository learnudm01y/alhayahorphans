/**
 * شريط تقدّم حفظ الاستمارة (البوابة العامة للتسجيل)
 *
 * - المرحلة 1: رفع البيانات والملفات من المتصفح إلى السيرفر (نسبة + سرعة فعلية من XHR).
 * - المرحلة 2: معالجة السيرفر (حفظ الاستمارة، رفع كل مرفق إلى التخزين/الدرايف، إنشاء التقرير)
 *   ويُقرأ تقدّمها من endpoint save-progress كل ~0.8 ثانية.
 *
 * التفعيل: أضف <script src="{{ asset('js/portal-save-progress.js') }}"></script> في نهاية صفحة النموذج.
 * يكتشف النموذج تلقائياً عبر الحقل name="sponsorship_id" ولا يحتاج أي تعديل على HTML النموذج.
 * لتغيير رابط الاستعلام: window.PORTAL_SAVE_PROGRESS_URL = '/المسار';
 */
(function () {
    'use strict';

    function init() {
        var idInput = document.querySelector('input[name="sponsorship_id"]');
        var form = idInput ? idInput.form : null;
        if (!form || form.__saveProgressBound) { return; }
        form.__saveProgressBound = true;

        var PROGRESS_URL = String(window.PORTAL_SAVE_PROGRESS_URL || '/users/generalRegistration/save-progress').replace(/\/+$/, '');
        var busy = false;

        // ---------- الواجهة ----------
        var style = document.createElement('style');
        style.textContent =
            '#psp-overlay{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.65);direction:rtl;font-family:inherit}' +
            '#psp-overlay.psp-show{display:flex}' +
            '#psp-card{width:min(92vw,460px);background:#fff;color:#0f172a;border-radius:14px;padding:22px 22px 18px;box-shadow:0 20px 50px rgba(0,0,0,.35)}' +
            '#psp-title{font-size:18px;font-weight:700;margin:0 0 4px}' +
            '#psp-msg{font-size:14px;color:#475569;min-height:20px;margin:0 0 14px;word-break:break-word}' +
            '#psp-track{height:16px;background:#e2e8f0;border-radius:999px;overflow:hidden}' +
            '#psp-fill{height:100%;width:0;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:999px;transition:width .25s ease}' +
            '#psp-card.psp-error #psp-fill{background:#dc2626}' +
            '#psp-pct{font-size:22px;font-weight:800;margin:10px 0 2px;text-align:center}' +
            '#psp-stats{display:flex;flex-wrap:wrap;gap:6px 16px;justify-content:center;font-size:13px;color:#334155}' +
            '#psp-stats span b{font-weight:700}' +
            '#psp-note{font-size:12px;color:#64748b;text-align:center;margin-top:10px}' +
            '#psp-close{display:none;margin:14px auto 0;padding:8px 22px;border:0;border-radius:8px;background:#0f172a;color:#fff;font-size:14px;cursor:pointer}' +
            '#psp-card.psp-error #psp-close{display:block}' +
            '#psp-card.psp-error #psp-note{display:none}';
        document.head.appendChild(style);

        var overlay = document.createElement('div');
        overlay.id = 'psp-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML =
            '<div id="psp-card">' +
              '<h3 id="psp-title">جاري حفظ البيانات</h3>' +
              '<p id="psp-msg"></p>' +
              '<div id="psp-track"><div id="psp-fill"></div></div>' +
              '<div id="psp-pct">0%</div>' +
              '<div id="psp-stats"></div>' +
              '<div id="psp-note">يرجى عدم إغلاق الصفحة حتى اكتمال الحفظ</div>' +
              '<button type="button" id="psp-close">إغلاق</button>' +
            '</div>';
        document.body.appendChild(overlay);

        var card = overlay.querySelector('#psp-card');
        var elTitle = overlay.querySelector('#psp-title');
        var elMsg = overlay.querySelector('#psp-msg');
        var elFill = overlay.querySelector('#psp-fill');
        var elPct = overlay.querySelector('#psp-pct');
        var elStats = overlay.querySelector('#psp-stats');
        overlay.querySelector('#psp-close').addEventListener('click', function () {
            overlay.classList.remove('psp-show');
            card.classList.remove('psp-error');
        });

        // ---------- أدوات ----------
        function fmtBytes(n) {
            if (!isFinite(n) || n < 0) { n = 0; }
            if (n >= 1073741824) { return (n / 1073741824).toFixed(2) + ' GB'; }
            if (n >= 1048576) { return (n / 1048576).toFixed(1) + ' MB'; }
            if (n >= 1024) { return Math.round(n / 1024) + ' KB'; }
            return Math.round(n) + ' B';
        }
        function fmtSpeed(bps) { return fmtBytes(bps) + '/ث'; }

        var shownPercent = 0;
        function render(percent, message, statsHtml) {
            percent = Math.max(shownPercent, Math.min(100, percent)); // لا تراجع
            shownPercent = percent;
            elFill.style.width = percent + '%';
            elPct.textContent = Math.floor(percent) + '%';
            if (message !== undefined) { elMsg.textContent = message; }
            if (statsHtml !== undefined) { elStats.innerHTML = statsHtml; }
        }

        function showError(message) {
            stopPolling();
            busy = false;
            card.classList.add('psp-error');
            elTitle.textContent = 'تعذّر إكمال الحفظ';
            elMsg.textContent = message || 'حدث خطأ غير متوقع أثناء الحفظ. يرجى المحاولة مرة أخرى.';
            elStats.innerHTML = '';
            setSubmitDisabled(false);
        }

        function setSubmitDisabled(disabled) {
            var btns = form.querySelectorAll('button[type="submit"],input[type="submit"]');
            for (var i = 0; i < btns.length; i++) { btns[i].disabled = disabled; }
        }

        // ---------- الاستعلام عن تقدّم السيرفر ----------
        var pollTimer = null;
        var serverState = null;
        function startPolling(token) {
            stopPolling();
            pollTimer = setInterval(function () {
                fetch(PROGRESS_URL + '/' + encodeURIComponent(token), {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store',
                    credentials: 'omit'
                }).then(function (r) { return r.ok ? r.json() : null; })
                  .then(function (data) { if (data) { serverState = data; renderServer(); } })
                  .catch(function () { /* نتجاهل أخطاء الاستعلام المؤقتة */ });
            }, 800);
        }
        function stopPolling() {
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }

        // وزن مرحلة الرفع للمتصفح ضمن الشريط الكلي
        var netWeight = 0.1;
        function renderServer() {
            var s = serverState;
            if (!s || s.status === 'pending') { return; }
            var overall = netWeight * 100 + (1 - netWeight) * (s.percent || 0);
            var parts = [];
            if (s.files_total > 0) {
                parts.push('<span>الملفات: <b>' + (s.files_done || 0) + ' / ' + s.files_total + '</b></span>');
                parts.push('<span>المرفوع للتخزين: <b>' + fmtBytes(s.bytes_done || 0) + ' / ' + fmtBytes(s.bytes_total || 0) + '</b></span>');
                if (s.server_speed_bps > 0) {
                    parts.push('<span>سرعة الرفع للتخزين: <b>' + fmtSpeed(s.server_speed_bps) + '</b></span>');
                }
            }
            elTitle.textContent = 'جاري المعالجة والحفظ';
            render(Math.min(99, overall), s.message || '', parts.join(''));
        }

        // ---------- الإرسال ----------
        function uuid() {
            if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
            var a = new Uint8Array(16);
            (window.crypto || window.msCrypto).getRandomValues(a);
            return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        }

        function start(submitter) {
            if (busy) { return; }
            busy = true;
            shownPercent = 0;
            serverState = null;
            card.classList.remove('psp-error');

            var fd = new FormData(form);
            if (submitter && submitter.name) { fd.append(submitter.name, submitter.value || ''); }
            var token = uuid();
            fd.append('progress_token', token);

            var totalBytes = 0;
            fd.forEach(function (v) { if (v instanceof File && v.size > 0) { totalBytes += v.size; } });
            netWeight = totalBytes > 0 ? 0.4 : 0.1;

            setSubmitDisabled(true);
            overlay.classList.add('psp-show');
            elTitle.textContent = totalBytes > 0 ? 'جاري رفع الملفات' : 'جاري إرسال البيانات';
            render(0, 'جاري إرسال البيانات إلى الخادم...', '');

            var xhr = new XMLHttpRequest();
            xhr.open((form.getAttribute('method') || 'POST').toUpperCase(), form.getAttribute('action') || window.location.href);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            var csrf = document.querySelector('meta[name="csrf-token"]');
            if (csrf) { xhr.setRequestHeader('X-CSRF-TOKEN', csrf.getAttribute('content')); }

            // قياس السرعة على نافذة زمنية ~2 ثانية
            var samples = [];
            var uploadDone = false;
            xhr.upload.onprogress = function (e) {
                if (!e.lengthComputable) { return; }
                var now = Date.now();
                samples.push({ t: now, l: e.loaded });
                while (samples.length > 2 && now - samples[0].t > 2000) { samples.shift(); }
                var first = samples[0], last = samples[samples.length - 1];
                var dt = (last.t - first.t) / 1000;
                var speed = dt > 0 ? (last.l - first.l) / dt : 0;
                var netPct = e.total > 0 ? e.loaded / e.total : 0;
                var remaining = speed > 0 ? Math.ceil((e.total - e.loaded) / speed) : null;
                var stats =
                    '<span>سرعة الرفع: <b>' + fmtSpeed(speed) + '</b></span>' +
                    '<span>المرفوع: <b>' + fmtBytes(e.loaded) + ' / ' + fmtBytes(e.total) + '</b></span>' +
                    (remaining !== null ? '<span>المتبقي: <b>' + remaining + ' ث</b></span>' : '');
                render(netWeight * 100 * netPct, 'جاري رفع البيانات والملفات إلى الخادم...', stats);
            };
            xhr.upload.onload = function () {
                uploadDone = true;
                render(netWeight * 100, 'تم الرفع إلى الخادم — جاري المعالجة والحفظ...', '');
                startPolling(token);
            };

            xhr.onerror = function () {
                showError('انقطع الاتصال أثناء الرفع. تحقق من الإنترنت وحاول مرة أخرى.');
            };
            xhr.onabort = function () { showError('تم إلغاء العملية.'); };

            xhr.onload = function () {
                stopPolling();
                var data = null;
                try { data = JSON.parse(xhr.responseText); } catch (e) { /* ليس JSON */ }

                if (xhr.status >= 200 && xhr.status < 300) {
                    if (data && data.success === false) { return showError(data.message); }
                    render(100, (data && data.message) || 'تم الحفظ بنجاح', '');
                    var target = (data && data.redirect) || (xhr.responseURL && xhr.responseURL !== window.location.href ? xhr.responseURL : null);
                    setTimeout(function () {
                        if (target) { window.location.href = target; } else { window.location.reload(); }
                    }, 400);
                    return;
                }

                var msg = data && data.message;
                if (!msg) {
                    if (xhr.status === 413) { msg = 'حجم الملفات المرسلة أكبر من الحد المسموح على الخادم.'; }
                    else if (xhr.status === 419) { msg = 'انتهت صلاحية الجلسة. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.'; }
                    else if (xhr.status === 401 || xhr.status === 403) { msg = 'غير مصرّح لك بتنفيذ هذه العملية. أعد تسجيل الدخول.'; }
                    else { msg = 'حدث خطأ في الخادم (' + xhr.status + '). يرجى المحاولة مرة أخرى.'; }
                }
                showError(msg);
            };

            xhr.send(fd);
        }

        // ---------- الربط بالنموذج ----------
        // 1) الإرسال العادي (زر submit / requestSubmit) — نحترم أي تحقق سابق يلغي الحدث
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) { return; }
            e.preventDefault();
            start(e.submitter || null);
        });

        // 2) الإرسال البرمجي form.submit() لا يطلق حدث submit، فنعترضه أيضاً
        if (typeof form.submit === 'function') {
            form.submit = function () { start(null); };
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
