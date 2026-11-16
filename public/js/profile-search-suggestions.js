class ProfileSearchSuggestions {
    constructor(input) {
        this.input = input;
        this.version = 0;
        this.events = new AbortController();
        this.container = document.createElement('div');
        this.container.className = 'profile-search-suggestions';
        this.container.id = 'profile-search-results';
        this.container.hidden = true;
        input.closest('.profile-search-container').append(this.container);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', this.container.id);
        input.setAttribute('aria-expanded', 'false');
        const options = { signal: this.events.signal };
        input.addEventListener('input', () => this.search(), options);
        input.addEventListener('focus', () => this.search(), options);
        input.addEventListener('keydown', event => this.keydown(event), options);
        document.addEventListener('click', event => {
            if (event.target !== input && !this.container.contains(event.target)) this.cancel();
        }, options);
    }

    node(tag, text, className) {
        const node = document.createElement(tag);
        if (text !== null && text !== undefined) node.textContent = String(text);
        if (className) node.className = className;
        return node;
    }

    cancel() {
        this.version++;
        clearTimeout(this.timer);
        this.request?.abort();
        this.container.hidden = true;
        this.container.replaceChildren();
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-busy', 'false');
        this.input.removeAttribute('aria-activedescendant');
    }

    search() {
        this.cancel();
        const query = this.input.value.trim();
        if (query.length < 3) return;
        const token = this.version;
        this.timer = setTimeout(() => this.performSearch(query, token), 350);
    }

    async performSearch(query, token) {
        this.request = new AbortController();
        this.showMessage('جارٍ البحث في جميع الحالات…', 'suggestion-loading');
        this.input.setAttribute('aria-busy', 'true');
        const url = new URL(this.input.dataset.searchUrl, window.location.href);
        url.searchParams.set('query', query);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: this.request.signal });
            if (token !== this.version) return;
            if (!response.ok) {
                const errors = { 403: 'ليس لديك صلاحية عرض السجلات.', 401: 'انتهت الجلسة. سجل الدخول مجددًا.', 419: 'انتهت الجلسة. حدّث الصفحة.', 429: 'تم بلوغ حد الطلبات. انتظر قليلًا ثم أعد البحث.', 422: 'أدخل ثلاثة أحرف أو أرقام على الأقل.' };
                throw new Error(errors[response.status] || 'تعذر تنفيذ البحث.');
            }
            const result = await response.json();
            if (token !== this.version || query !== this.input.value.trim()) return;
            if (!result.success) throw new Error(result.message || 'تعذر تنفيذ البحث.');
            this.render(result);
        } catch (error) {
            if (token === this.version && error.name !== 'AbortError') this.showMessage(error.message, 'suggestion-error');
        } finally {
            if (token === this.version) this.input.setAttribute('aria-busy', 'false');
        }
    }

    safeUrl(value) {
        if (!value) return null;
        try {
            const url = new URL(value, window.location.href);
            return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch (_) { return null; }
    }

    render(result) {
        this.container.replaceChildren(this.node('div', 'جميع الحالات — الإجمالي: ' + result.total + ' — عرض ' + result.data.length + (result.cached ? ' (مخزن مؤقتًا)' : ''), 'suggestion-subtitle p-3'));
        const list = this.node('div');
        list.setAttribute('role', 'listbox');
        result.data.forEach((record, index) => {
            const url = this.safeUrl(record.view_url);
            const item = this.node(url ? 'a' : 'div', null, 'suggestion-item');
            item.id = this.container.id + '-' + index;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', 'false');
            if (url) { item.href = url; item.tabIndex = -1; }
            item.append(this.node('div', record.full_name || 'غير محدد', 'suggestion-title'));
            item.append(this.node('div', 'الملف: ' + (record.file_id_number ?? '—') + ' — الهوية: ' + (record.identity_number ?? '—'), 'suggestion-subtitle'));
            [record.phone, record.alt_phone].forEach((phone, phoneIndex) => {
                if (!phone || String(phone) === '0' || (phoneIndex === 1 && phone === record.phone)) return;
                const line = this.node('div', record.phone_label + (phoneIndex === 1 ? ' (بديل): ' : ': '), 'suggestion-description');
                const number = this.node('span', phone);
                number.dir = 'ltr';
                line.append(number);
                item.append(line);
            });
            item.append(this.node('div', record.record_type + ' — الحالة: ' + (record.request_status_name || 'غير محددة'), 'suggestion-type'));
            item.addEventListener('mouseenter', () => this.activate(item));
            list.append(item);
        });
        if (!result.data.length) list.append(this.node('div', 'لا توجد نتائج مطابقة.', 'suggestion-no-results'));
        this.container.append(list);
        const allUrl = this.safeUrl(result.all_results_url);
        if (allUrl) {
            const link = this.node('a', 'عرض البحث الشامل والمرشحات', 'd-block text-center p-3');
            link.href = allUrl;
            this.container.append(link);
        }
        this.show();
    }

    showMessage(text, className) {
        this.container.replaceChildren(this.node('div', text, className));
        this.show();
    }

    show() {
        this.container.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    }

    activate(item) {
        this.container.querySelectorAll('[role="option"]').forEach(option => {
            option.classList.toggle('active', option === item);
            option.setAttribute('aria-selected', String(option === item));
        });
        this.input.setAttribute('aria-activedescendant', item.id);
    }

    keydown(event) {
        if (event.key === 'Escape') { this.cancel(); return; }
        if (this.container.hidden) return;
        const items = [...this.container.querySelectorAll('a[role="option"]')];
        if (!items.length) return;
        const index = items.findIndex(item => item.getAttribute('aria-selected') === 'true');
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = index === -1 ? (event.key === 'ArrowDown' ? 0 : items.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            this.activate(items[next]);
            items[next].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && index !== -1) {
            event.preventDefault();
            items[index].click();
        }
    }

    destroy() {
        this.cancel();
        this.events.abort();
        this.container.remove();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('input[name="search_profiles"]');
    if (input) window.profileSearchSuggestions = new ProfileSearchSuggestions(input);
});
