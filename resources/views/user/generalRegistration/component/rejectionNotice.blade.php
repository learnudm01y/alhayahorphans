{{-- رسائل رفض الحفظ القادمة من الخادم: عربية، تسمّي الحقل وموضعه، ولا تعرض أي تفاصيل تقنية. --}}
@php
    $rejection = session('registration_error');
    $rejection = is_array($rejection) ? $rejection : null;
    $rejectionFields = $rejection && !empty($rejection['fields']) && is_array($rejection['fields'])
        ? $rejection['fields']
        : [];
@endphp

@if ($rejection)
    <div class="alert alert-danger border-0 shadow-sm mb-4" dir="rtl" id="registrationRejection" role="alert">
        <div class="d-flex align-items-start">
            <i class="fas fa-exclamation-triangle fs-3 me-3 mt-1"></i>
            <div class="flex-grow-1">
                <h5 class="alert-heading fw-bold mb-2">لم يتم حفظ الطلب</h5>
                <p class="mb-3 fs-5">{{ $rejection['message'] ?? '' }}</p>

                @if (count($rejectionFields) > 0)
                    <p class="fw-bold mb-2">الأسباب:</p>
                    <ul class="list-unstyled mb-3">
                        @foreach ($rejectionFields as $reason)
                            @continue(!is_array($reason))
                            <li class="d-flex align-items-start justify-content-between flex-wrap gap-2 border-bottom border-danger border-opacity-25 pb-2 mb-2">
                                <span>
                                    <span class="fw-bold">{{ $reason['label'] ?? '' }}</span>
                                    <span class="d-block">{{ $reason['message'] ?? '' }}</span>
                                </span>
                                <button type="button"
                                    class="btn btn-sm btn-outline-danger flex-shrink-0 registration-goto"
                                    data-field="{{ $reason['field'] ?? '' }}"
                                    data-tab="{{ $reason['tab'] ?? 'basic' }}"
                                    data-reason="{{ $reason['message'] ?? '' }}">
                                    <i class="fas fa-arrow-left me-1"></i>اذهب إلى الحقل
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($rejection['reference']))
                    <small class="d-block text-muted">
                        رمز المتابعة: <span class="fw-bold">{{ $rejection['reference'] }}</span>
                        — احتفظ به عند التواصل مع الإدارة.
                    </small>
                @endif
            </div>
        </div>
    </div>

    <style>
        .registration-field-error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 .2rem rgba(220, 53, 69, .15) !important;
        }

        .registration-field-reason {
            color: #dc3545;
            font-size: .9rem;
            margin-top: .25rem;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const notice = document.getElementById('registrationRejection');
            if (!notice) return;

            // توحيد صيغتي الاسم: family_members.0.person_id و family_members[0][person_id]
            function normalizeName(name) {
                return String(name || '')
                    .replace(/\[/g, '.')
                    .replace(/\]/g, '')
                    .replace(/\.{2,}/g, '.')
                    .replace(/^\.+|\.+$/g, '');
            }

            function findField(key) {
                const wanted = normalizeName(key);
                if (!wanted) return null;

                const nodes = document.querySelectorAll('#main_form [name]');
                for (let i = 0; i < nodes.length; i++) {
                    if (normalizeName(nodes[i].getAttribute('name')) === wanted) return nodes[i];
                }
                return null;
            }

            function clearMark(el) {
                el.classList.remove('registration-field-error');
                const next = el.nextElementSibling;
                if (next && next.classList.contains('registration-field-reason')) next.remove();
            }

            function markField(el, reason) {
                clearMark(el);
                el.classList.add('registration-field-error');

                if (reason) {
                    const note = document.createElement('div');
                    note.className = 'registration-field-reason';
                    note.textContent = reason;
                    el.insertAdjacentElement('afterend', note);
                }

                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof el.focus === 'function') {
                    try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
                }
            }

            function openTab(tabId, afterShow) {
                const button = document.getElementById(tabId + '-tab');
                if (!button || !window.bootstrap || !bootstrap.Tab) {
                    afterShow();
                    return;
                }

                if (button.classList.contains('active')) {
                    afterShow();
                    return;
                }

                button.addEventListener('shown.bs.tab', function handler() {
                    button.removeEventListener('shown.bs.tab', handler);
                    afterShow();
                });
                bootstrap.Tab.getOrCreateInstance(button).show();
            }

            notice.querySelectorAll('.registration-goto').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const key = btn.getAttribute('data-field') || '';
                    const tabId = btn.getAttribute('data-tab') || 'basic';
                    const reason = btn.getAttribute('data-reason') || '';

                    openTab(tabId, function () {
                        const el = findField(key);
                        if (el) {
                            markField(el, reason);
                            return;
                        }

                        // الحقل غير موجود في الصفحة (بطاقة لم تُنشأ بعد): نفتح التبويب ونوضح السبب
                        const pane = document.getElementById(tabId);
                        if (pane) pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'info',
                                title: 'موضع السبب',
                                text: reason || 'يرجى مراجعة هذا التبويب وتصحيح البيانات.',
                                confirmButtonText: 'حسناً'
                            });
                        }
                    });
                });
            });

            // إزالة التمييز الأحمر بمجرد تصحيح المستخدم للحقل
            document.addEventListener('input', function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('registration-field-error')) {
                    clearMark(e.target);
                }
            });
            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('registration-field-error')) {
                    clearMark(e.target);
                }
            });

            // تنبيه أولي واضح، والقائمة تبقى ظاهرة في الصفحة للرجوع إليها
            const message = notice.querySelector('p.fs-5');
            const reference = notice.querySelector('small span.fw-bold');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'لم يتم حفظ الطلب',
                    text: (message ? message.textContent.trim() : '') +
                        (reference ? '\nرمز المتابعة: ' + reference.textContent.trim() : ''),
                    confirmButtonText: 'سأصحح البيانات',
                    allowOutsideClick: false
                }).then(function () {
                    notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const first = notice.querySelector('.registration-goto');
                    if (first) first.click();
                });
            } else {
                notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
@endif