/**
 * crud-helper.js — مساعد CRUD موحّد لشاشات لوحة التحكم v4
 * يوفر: جداول، نماذج، تأكيد حذف، ترقيم، فلاتر، إشعارات
 */
(function () {
    'use strict';

    function $(sel) { return document.querySelector(sel); }
    function $$(sel) { return document.querySelectorAll(sel); }

    function el(tag, attrs, children) {
        var e = document.createElement(tag);
        if (attrs) Object.keys(attrs).forEach(function (k) {
            if (k === 'className') e.className = attrs[k];
            else if (k === 'text') e.textContent = attrs[k];
            else if (k === 'html') e.innerHTML = attrs[k];
            else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2), attrs[k]);
            else e.setAttribute(k, attrs[k]);
        });
        if (children) children.forEach(function (c) {
            if (typeof c === 'string') e.appendChild(document.createTextNode(c));
            else if (c) e.appendChild(c);
        });
        return e;
    }

    function toast(msg, type) {
        var t = document.getElementById('toast');
        if (!t) {
            t = el('div', { id: 'toast', className: 'toast' });
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.className = 'toast toast-' + (type || 'info');
        t.style.display = 'block';
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.style.display = 'none'; }, 3000);
    }

    function confirmDelete(name) {
        return window.confirm('هل أنت متأكد من حذف "' + (name || 'هذا العنصر') + '"؟ لا يمكن التراجع.');
    }

    // ---- Table renderer ----
    function renderTable(container, columns, rows, options) {
        options = options || {};
        if (!container) return;
        container.innerHTML = '';

        if (!rows || rows.length === 0) {
            container.appendChild(el('div', { className: 'empty', text: options.emptyText || 'لا توجد بيانات' }));
            return;
        }

        var table = el('table', { className: 'data' });
        var thead = el('thead');
        var trh = el('tr');
        columns.forEach(function (c) {
            trh.appendChild(el('th', { text: c.label || c.key }));
        });
        if (options.actions) trh.appendChild(el('th', { text: 'إجراءات' }));
        thead.appendChild(trh);
        table.appendChild(thead);

        var tbody = el('tbody');
        rows.forEach(function (row, idx) {
            var tr = el('tr');
            columns.forEach(function (c) {
                var td = el('td');
                var val = row[c.key];
                if (c.render) td.innerHTML = c.render(val, row, idx);
                else td.textContent = val != null ? val : '—';
                tr.appendChild(td);
            });
            if (options.actions) {
                var tdA = el('td');
                var divA = el('div', { className: 'row-actions' });
                if (options.actions.view) {
                    divA.appendChild(el('button', {
                        className: 'btn btn-sm btn-outline',
                        text: 'عرض',
                        onClick: function () { options.actions.view(row); }
                    }));
                }
                if (options.actions.edit) {
                    divA.appendChild(el('button', {
                        className: 'btn btn-sm',
                        text: 'تعديل',
                        onClick: function () { options.actions.edit(row); }
                    }));
                }
                if (options.actions.delete) {
                    divA.appendChild(el('button', {
                        className: 'btn btn-sm btn-danger',
                        text: 'حذف',
                        onClick: function () {
                            if (confirmDelete(row[c.key] || row.name)) options.actions.delete(row);
                        }
                    }));
                }
                tdA.appendChild(divA);
                tr.appendChild(tdA);
            }
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        container.appendChild(table);
    }

    // ---- Form builder ----
    function renderForm(container, fields, values, onSubmit) {
        if (!container) return;
        container.innerHTML = '';
        var form = el('form', { className: 'v4-form' });

        fields.forEach(function (f) {
            var wrap = el('div', { className: 'form-group' });
            wrap.appendChild(el('label', { text: f.label + (f.required ? ' *' : '') }));

            var input;
            if (f.type === 'select') {
                input = el('select', { name: f.name });
                if (f.placeholder) input.appendChild(el('option', { value: '', text: f.placeholder }));
                (f.options || []).forEach(function (opt) {
                    var o = el('option', { value: opt.value != null ? opt.value : opt, text: opt.label || opt });
                    if (values && values[f.name] != null && String(values[f.name]) === String(opt.value != null ? opt.value : opt)) {
                        o.selected = true;
                    }
                    input.appendChild(o);
                });
            } else if (f.type === 'textarea') {
                input = el('textarea', { name: f.name, rows: f.rows || 3 });
                if (values && values[f.name] != null) input.value = values[f.name];
            } else if (f.type === 'checkbox') {
                input = el('input', { type: 'checkbox', name: f.name });
                if (values && values[f.name]) input.checked = true;
            } else {
                input = el('input', { type: f.type || 'text', name: f.name });
                if (values && values[f.name] != null) input.value = values[f.name];
                if (f.placeholder) input.placeholder = f.placeholder;
            }
            if (f.required) input.required = true;
            wrap.appendChild(input);
            form.appendChild(wrap);
        });

        var btnRow = el('div', { className: 'form-actions' });
        btnRow.appendChild(el('button', { type: 'submit', className: 'btn', text: 'حفظ' }));
        form.appendChild(btnRow);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var data = {};
            fields.forEach(function (f) {
                var inp = form.querySelector('[name="' + f.name + '"]');
                if (!inp) return;
                if (f.type === 'checkbox') data[f.name] = inp.checked;
                else data[f.name] = inp.value;
            });
            onSubmit(data);
        });

        container.appendChild(form);
    }

    // ---- Modal ----
    function showModal(title, contentEl, actions) {
        var overlay = el('div', { className: 'modal-overlay' });
        var modal = el('div', { className: 'modal' });
        modal.appendChild(el('div', { className: 'modal-header' }, [
            el('h3', { text: title }),
            el('button', { className: 'modal-close', text: '×', onClick: hide })
        ]));
        var body = el('div', { className: 'modal-body' });
        if (typeof contentEl === 'string') body.innerHTML = contentEl;
        else body.appendChild(contentEl);
        modal.appendChild(body);
        if (actions && actions.length) {
            var foot = el('div', { className: 'modal-footer' });
            actions.forEach(function (a) {
                foot.appendChild(el('button', {
                    className: 'btn ' + (a.className || ''),
                    text: a.label,
                    onClick: function () { a.onClick(hide); }
                }));
            });
            modal.appendChild(foot);
        }
        overlay.appendChild(modal);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) hide(); });
        document.body.appendChild(overlay);
        function hide() { overlay.remove(); }
        return { hide: hide, modal: modal };
    }

    // ---- Pagination ----
    function renderPagination(container, page, totalPages, onPage) {
        if (!container) return;
        container.innerHTML = '';
        if (totalPages <= 1) return;
        var nav = el('div', { className: 'pagination' });
        if (page > 1) {
            nav.appendChild(el('button', {
                className: 'btn btn-sm btn-outline', text: 'السابق',
                onClick: function () { onPage(page - 1); }
            }));
        }
        nav.appendChild(el('span', { className: 'page-info', text: 'صفحة ' + page + ' من ' + totalPages }));
        if (page < totalPages) {
            nav.appendChild(el('button', {
                className: 'btn btn-sm btn-outline', text: 'التالي',
                onClick: function () { onPage(page + 1); }
            }));
        }
        container.appendChild(nav);
    }

    // ---- Online/Offline badge ----
    function setOnlineBadge() {
        var b = document.getElementById('mode-badge');
        if (!b) return;
        if (navigator.onLine) { b.textContent = 'Online'; b.className = 'badge badge-ok'; }
        else { b.textContent = 'Offline'; b.className = 'badge badge-offline'; }
    }
    window.addEventListener('online', setOnlineBadge);
    window.addEventListener('offline', setOnlineBadge);

    window.CrudHelper = {
        $: $, $$: $$, el: el,
        toast: toast,
        confirmDelete: confirmDelete,
        renderTable: renderTable,
        renderForm: renderForm,
        showModal: showModal,
        renderPagination: renderPagination,
        setOnlineBadge: setOnlineBadge
    };
})();
