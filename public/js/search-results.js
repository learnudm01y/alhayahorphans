(function (window, $) {
    'use strict';

    function element(tag, text, className) {
        const node = document.createElement(tag);
        if (text !== undefined && text !== null) node.textContent = String(text);
        if (className) node.className = className;
        return node;
    }

    function errorMessage(xhr) {
        if (xhr.status === 403) return 'ليس لديك صلاحية عرض السجلات.';
        if (xhr.status === 401 || xhr.status === 419) return 'انتهت الجلسة. حدّث الصفحة وسجل الدخول مجددًا.';
        if (xhr.status === 429) return 'تم بلوغ حد الطلبات. انتظر قليلًا ثم أعد البحث.';
        if (xhr.responseJSON && xhr.responseJSON.errors) return Object.values(xhr.responseJSON.errors).flat().join(' ');
        return xhr.responseJSON?.message || 'تعذر الاتصال بالخادم. يرجى المحاولة مرة أخرى.';
    }

    function mount(root) {
        if (!root) return null;
        if (root.recordSearch) return root.recordSearch;
        const form = root.querySelector('[data-role="form"]');
        const field = name => form.elements.namedItem(name);
        const part = name => root.querySelector('[data-role="' + name + '"]');
        const searchInput = field('search_text');
        let searchRequest, suggestionsRequest, searchTimer, suggestionsTimer;
        let version = 0, destroyed = false, currentFilters = null;
        const otherRequests = new Set();
        const headers = { 'X-CSRF-TOKEN': field('_token').value, 'Accept': 'application/json' };

        function message(text, kind = 'danger') {
            const box = part('message');
            box.className = 'alert alert-' + kind;
            box.textContent = text;
            box.hidden = !text;
        }

        function hideSuggestions() {
            part('suggestions').hidden = true;
            part('suggestions').replaceChildren();
            searchInput.setAttribute('aria-expanded', 'false');
            searchInput.removeAttribute('aria-activedescendant');
        }

        function busy(value) {
            root.setAttribute('aria-busy', String(value));
            part('loading').hidden = !value;
            part('submit').disabled = value;
        }

        function invalidate() {
            version++;
            clearTimeout(searchTimer);
            clearTimeout(suggestionsTimer);
            if (searchRequest) searchRequest.abort();
            if (suggestionsRequest) suggestionsRequest.abort();
            hideSuggestions();
            busy(false);
        }

        function updateScope() {
            const type = field('search_type').value;
            root.querySelectorAll('[data-supported]').forEach(wrapper => {
                const supported = wrapper.dataset.supported;
                const enabled = type === 'all' || supported === 'all' || supported.split(' ').includes(type);
                wrapper.classList.toggle('opacity-50', !enabled);
                wrapper.querySelectorAll('input, select').forEach(input => {
                    input.disabled = !enabled;
                    if (!enabled) input.value = '';
                });
            });
            const status = field('request_status_id');
            let note = 'حالة الطلب: ' + status.selectedOptions[0].textContent + '. ';
            if (type === 'all') {
                note += 'الفلاتر الخاصة بشخص تستبعد الأنواع غير المتوافقة: الميلاد للأحياء، والوفاة للمتوفين، والحالة الصحية للسجلات الرئيسية وأفراد الأسرة، وبقية الفلاتر الشخصية للسجلات الرئيسية فقط.';
            } else {
                note += 'المرشحات غير المتاحة لهذا النوع معطلة. يمكن البحث بالمرشحات دون كتابة اسم.';
            }
            part('scope-note').textContent = note;
        }

        function collectFilters() {
            const values = Object.fromEntries(new FormData(form));
            delete values._token;
            return values;
        }

        function search(page = 1, filters = null) {
            invalidate();
            const token = version;
            const data = { ...(filters || collectFilters()), page };
            currentFilters = { ...data };
            message('');
            part('results').hidden = true;
            busy(true);
            searchRequest = $.ajax({
                url: root.dataset.searchUrl, method: 'POST', headers, data, dataType: 'json',
                success(response) {
                    if (destroyed || token !== version) return;
                    if (!response.success) { message(response.message || 'تعذر تنفيذ البحث.'); return; }
                    render(response.data);
                },
                error(xhr, status) {
                    if (!destroyed && token === version && status !== 'abort') message(errorMessage(xhr));
                },
                complete() { if (!destroyed && token === version) busy(false); }
            });
        }

        function chooseSuggestion(value) {
            searchInput.value = value;
            search();
        }

        function suggestions(query, token) {
            const data = { ...collectFilters(), q: query, limit: 8 };
            suggestionsRequest = $.ajax({
                url: root.dataset.suggestionsUrl, headers, data, dataType: 'json',
                success(response) {
                    if (destroyed || token !== version || query !== searchInput.value.trim()) return;
                    hideSuggestions();
                    if (!response.success || !Array.isArray(response.data)) return;
                    response.data.forEach((value, index) => {
                        const button = element('button', value, 'list-group-item list-group-item-action');
                        button.type = 'button';
                        button.id = part('suggestions').id + '-' + index;
                        button.setAttribute('role', 'option');
                        button.setAttribute('aria-selected', 'false');
                        button.addEventListener('click', () => chooseSuggestion(value));
                        part('suggestions').append(button);
                    });
                    part('suggestions').hidden = response.data.length === 0;
                    searchInput.setAttribute('aria-expanded', String(response.data.length > 0));
                },
                error(xhr, status) {
                    if (!destroyed && token === version && status !== 'abort') hideSuggestions();
                }
            });
        }

        function phoneCell(record) {
            const td = element('td');
            [record.phone, record.alt_phone].forEach((phone, index) => {
                if (!phone || (index === 1 && phone === record.phone)) return;
                const line = element('div');
                const text = element('span', phone);
                text.dir = 'ltr';
                line.append(text);
                if (index === 1) line.append(element('small', ' (بديل)', 'text-muted'));
                td.append(line);
            });
            if (!td.childElementCount) td.textContent = '—';
            if (record.type !== 'main_record' && (record.phone || record.alt_phone)) td.append(element('small', record.phone_label, 'd-block text-muted'));
            return td;
        }

        function detailsRow(record) {
            const row = element('tr', null, 'search-details');
            row.hidden = true;
            const cell = element('td');
            cell.colSpan = 11;
            const list = element('dl');
            const fields = [
                ['المعيل', 'guardian_name'], ['القسم', 'section_name'], ['تاريخ الميلاد', 'birth_date'],
                ['الحالة الصحية', 'health_status_name'], ['صلة القرابة', 'relationship_name'],
                ['الحالة الاجتماعية', 'marital_status_name'], ['المؤهل العلمي', 'education_level'],
                ['حالة العمل', 'employment_status_name'], ['تاريخ الوفاة', 'death_date'], ['سبب الوفاة', 'death_reason']
            ];
            fields.forEach(([label, key]) => {
                if (record[key] !== null && record[key] !== undefined && record[key] !== '') {
                    list.append(element('dt', label), element('dd', record[key]));
                }
            });
            cell.append(list.childElementCount ? list : element('span', 'لا توجد تفاصيل إضافية.'));
            row.append(cell);
            return row;
        }

        function render(result) {
            const body = part('rows');
            body.replaceChildren();
            result.data.forEach(record => {
                const row = element('tr');
                row.append(element('td', record.full_name || 'غير محدد'), element('td', record.identity_number ?? '—'), element('td', record.file_id_number ?? '—'), phoneCell(record));
                row.append(element('td', record.age ?? '—'), element('td', record.gender || '—'), element('td', record.city_name || '—'));
                row.append(element('td', record.address || '—', 'search-address'), element('td', record.request_status_name || '—'), element('td', record.record_type));
                const actions = element('td');
                const details = detailsRow(record);
                const toggle = element('button', 'تفاصيل', 'btn btn-sm btn-outline-info m-1');
                toggle.type = 'button';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.addEventListener('click', () => {
                    details.hidden = !details.hidden;
                    toggle.setAttribute('aria-expanded', String(!details.hidden));
                });
                actions.append(toggle);
                if (record.view_url) {
                    try {
                        const url = new URL(record.view_url, window.location.href);
                        if (url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol)) {
                            const link = element('a', record.type === 'main_record' ? 'عرض الملف' : 'ملف المعيل', 'btn btn-sm btn-primary m-1');
                            link.href = url.href;
                            link.target = '_blank';
                            link.rel = 'noopener';
                            actions.append(link);
                        }
                    } catch (_) { }
                }
                row.append(actions);
                body.append(row, details);
            });
            const pagination = result.pagination;
            part('count').textContent = 'الإجمالي: ' + pagination.total_records + ' — عرض ' + pagination.from + '–' + pagination.to + (result.cached ? ' (مخزن مؤقتًا)' : '');
            if (result.data.length === 0) {
                const row = element('tr');
                const cell = element('td', 'لم يتم العثور على نتائج مطابقة. جرّب تعديل الفلاتر أو اختيار كل الحالات.', 'text-center py-4');
                cell.colSpan = 11;
                row.append(cell);
                body.append(row);
            }
            const nav = part('pagination');
            nav.replaceChildren();
            const buttons = element('div', null, 'd-flex flex-wrap gap-2 justify-content-center');
            function pageButton(label, page, disabled, current = false) {
                const button = element('button', label, 'btn btn-sm ' + (current ? 'btn-primary' : 'btn-outline-primary'));
                button.type = 'button';
                button.disabled = disabled;
                if (current) button.setAttribute('aria-current', 'page');
                button.addEventListener('click', () => search(page, currentFilters));
                buttons.append(button);
            }
            if (pagination.total_pages > 1) {
                pageButton('الأولى', 1, pagination.current_page === 1);
                pageButton('السابق', pagination.current_page - 1, pagination.current_page === 1);
                for (let page = Math.max(1, pagination.current_page - 2); page <= Math.min(pagination.total_pages, pagination.current_page + 2); page++) {
                    pageButton(String(page), page, page === pagination.current_page, page === pagination.current_page);
                }
                pageButton('التالي', pagination.current_page + 1, !pagination.has_more);
                pageButton('الأخيرة', pagination.total_pages, !pagination.has_more);
            }
            nav.append(buttons, element('p', 'الصفحة ' + pagination.current_page + ' من ' + pagination.total_pages, 'text-center text-muted mt-2 mb-0'));
            part('results').hidden = false;
        }

        $(form).on('submit.recordSearch', function (event) { event.preventDefault(); search(); });
        $(form).on('input.recordSearch change.recordSearch', 'input, select', function (event) {
            invalidate();
            currentFilters = null;
            part('results').hidden = true;
            message('');
            if (event.type === 'change' && this.name.startsWith('birth_date') && this.value) {
                field('death_date_from').value = ''; field('death_date_to').value = '';
            }
            if (event.type === 'change' && this.name.startsWith('death_date') && this.value) {
                field('birth_date_from').value = ''; field('birth_date_to').value = '';
            }
            updateScope();
            if (this !== searchInput) return;
            const query = searchInput.value.trim();
            const token = version;
            if (query.length >= 3) {
                suggestionsTimer = setTimeout(() => suggestions(query, token), 250);
                searchTimer = setTimeout(() => search(), 800);
            }
        });
        $(form).on('reset.recordSearch', function () {
            invalidate();
            currentFilters = null;
            part('results').hidden = true;
            part('stats-panel').hidden = true;
            message('');
            queueMicrotask(() => {
                if (destroyed) return;
                searchInput.value = '';
                field('request_status_id').value = '';
                updateScope();
            });
        });
        $(searchInput).on('keydown.recordSearch', function (event) {
            const options = [...part('suggestions').children];
            if (event.key === 'Escape') { hideSuggestions(); return; }
            if (!options.length || part('suggestions').hidden) return;
            let index = options.findIndex(option => option.getAttribute('aria-selected') === 'true');
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                index = index === -1 ? (event.key === 'ArrowDown' ? 0 : options.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options.forEach((option, i) => {
                    option.setAttribute('aria-selected', String(i === index));
                    option.classList.toggle('active', i === index);
                });
                searchInput.setAttribute('aria-activedescendant', options[index].id);
                options[index].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter' && index !== -1) {
                event.preventDefault();
                chooseSuggestion(options[index].textContent);
            }
        });
        function outsideClick(event) {
            if (event.target !== searchInput && !part('suggestions').contains(event.target)) hideSuggestions();
        }
        document.addEventListener('click', outsideClick);
        $(part('filters-toggle')).on('click.recordSearch', function () {
            part('filters').hidden = !part('filters').hidden;
            this.setAttribute('aria-expanded', String(!part('filters').hidden));
        });
        function auxiliary(url, method, success, button) {
            button.disabled = true;
            const request = $.ajax({ url, method, headers, dataType: 'json',
                success(response) { if (!destroyed) success(response); },
                error(xhr, status) { if (!destroyed && status !== 'abort') message(errorMessage(xhr)); },
                complete() { otherRequests.delete(request); if (!destroyed) button.disabled = false; }
            });
            otherRequests.add(request);
        }
        $(part('stats')).on('click.recordSearch', function () {
            auxiliary(root.dataset.statsUrl, 'GET', response => {
                if (!response.success) { message(response.message || 'تعذر تحميل الإحصائيات.'); return; }
                const stats = response.data;
                part('stats-panel').textContent = 'جميع الحالات — السجلات الرئيسية: ' + stats.main_records + '، أفراد الأسرة: ' + stats.family_members + '، المتوفون: ' + stats.deceased_records + '، الإجمالي: ' + stats.total_records + ' — آخر تحديث: ' + stats.last_updated;
                part('stats-panel').hidden = false;
            }, this);
        });
        $(part('clear-cache')).on('click.recordSearch', function () {
            if (!window.confirm('هل تريد إبطال النتائج المخزنة مؤقتًا وجلب بيانات جديدة؟')) return;
            auxiliary(root.dataset.clearUrl, 'POST', response => {
                if (!response.success) { message(response.message || 'تعذر تحديث الذاكرة.'); return; }
                if (currentFilters) search(1, currentFilters);
                else message(response.message, 'success');
            }, this);
        });
        updateScope();
        root.recordSearch = {
            destroy() {
                destroyed = true;
                invalidate();
                otherRequests.forEach(request => request.abort());
                $(form).off('.recordSearch');
                $(root).find('*').off('.recordSearch');
                document.removeEventListener('click', outsideClick);
                delete root.recordSearch;
            }
        };
        if (searchInput.value.trim().length >= 3) search();
        return root.recordSearch;
    }

    function bindModal(selector, contentUrl) {
        const modal = document.querySelector(selector);
        if (!modal) return;
        const content = modal.querySelector('#searchModalContent');
        let request, widget, version = 0;
        $(modal).off('.recordSearch');
        $(modal).on('show.bs.modal.recordSearch', function () {
            const token = ++version;
            if (request) request.abort();
            if (widget) widget.destroy();
            content.replaceChildren(element('p', 'جارٍ تحميل نموذج البحث…', 'text-center py-4'));
            request = $.ajax({ url: contentUrl, dataType: 'html',
                success(html) {
                    if (token !== version) return;
                    $(content).html(html);
                    widget = mount(content.querySelector('[data-record-search]'));
                },
                error(xhr, status) {
                    if (token === version && status !== 'abort') content.replaceChildren(element('p', errorMessage(xhr), 'alert alert-danger'));
                }
            });
        });
        $(modal).on('hide.bs.modal.recordSearch', function () {
            version++;
            if (request) request.abort();
            if (widget) widget.destroy();
            widget = null;
            content.replaceChildren();
        });
    }

    window.RecordSearch = { mount, bindModal };
})(window, jQuery);
