/**
 * Scout Search JavaScript - Fixed Version
 * محرك البحث السريع بتقنية Laravel Scout
 */

class ScoutSearchEngine {
    constructor() {
        this.searchTimeout = null;
        this.suggestionTimeout = null;
        this.currentRequest = null;
        this.baseUrl = '/admin/scout';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        this.cache = new Map();

        this.init();
    }

    init() {
        this.bindEvents();
        this.setupAutoComplete();
    }

    bindEvents() {
        // البحث السريع
        const searchBtn = document.getElementById('scoutSearchBtn');
        if (searchBtn) {
            searchBtn.addEventListener('click', () => this.performInstantSearch());
        }

        // البحث عند الضغط على Enter
        const searchInput = document.getElementById('scoutSearchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.performInstantSearch();
                }
            });

            // إضافة event للـ input للاقتراحات
            searchInput.addEventListener('input', (e) => this.handleSearchInput(e));

            // إخفاء الاقتراحات عند فقدان التركيز
            searchInput.addEventListener('blur', () => {
                setTimeout(() => this.hideSuggestions(), 200);
            });
        }

        // البحث المتقدم
        const advancedBtn = document.getElementById('scoutAdvancedSearchBtn');
        if (advancedBtn) {
            advancedBtn.addEventListener('click', () => this.performAdvancedSearch());
        }

        // تبديل الفلاتر المتقدمة
        const toggleBtn = document.getElementById('scoutToggleAdvanced');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleAdvancedFilters());
        }

        // مسح البحث
        const clearBtn = document.getElementById('scoutClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearSearch());
        }
    }

    setupAutoComplete() {
        const searchInput = document.getElementById('scoutSearchInput');
        if (!searchInput) return;

        // إنشاء container للاقتراحات
        if (!document.getElementById('scoutSuggestions')) {
            const suggestionsDiv = document.createElement('div');
            suggestionsDiv.id = 'scoutSuggestions';
            suggestionsDiv.className = 'scout-suggestions';
            suggestionsDiv.style.display = 'none';
            searchInput.parentNode.appendChild(suggestionsDiv);
        }
    }

    handleSearchInput(e) {
        const query = e.target.value.trim();

        // إلغاء البحث السابق
        if (this.suggestionTimeout) {
            clearTimeout(this.suggestionTimeout);
        }

        // إخفاء الاقتراحات إذا كان النص فارغ أو قصير جداً
        if (query.length < 2) {
            this.hideSuggestions();
            return;
        }

        // تأخير البحث لتجنب الطلبات المتكررة
        this.suggestionTimeout = setTimeout(() => {
            this.getSuggestions(query);
        }, 300);
    }

    async performInstantSearch() {
        const query = document.getElementById('scoutSearchInput')?.value.trim();

        if (!query) {
            this.showMessage('يرجى إدخال كلمة البحث', 'warning');
            return;
        }

        this.showLoading();
        this.hideMessage();

        try {
            const requestData = {
                query: query,
                limit: 50
            };

            const response = await this.makeRequest('GET', '/instant-search', requestData);

            if (response && response.success) {
                this.displayResults(response);
                this.updateStats(response);
                this.hideSuggestions();
            } else {
                this.showMessage(response?.message || 'حدث خطأ في البحث', 'error');
            }
        } catch (error) {
            console.error('خطأ في البحث السريع:', error);
            this.showMessage('خطأ في الاتصال: ' + error.message, 'error');
        } finally {
            this.hideLoading();
        }
    }

    async performAdvancedSearch() {
        const query = document.getElementById('scoutSearchInput')?.value.trim();

        if (!query) {
            this.showMessage('يرجى إدخال كلمة البحث', 'warning');
            return;
        }

        this.showLoading();
        this.hideMessage();

        try {
            const filters = this.getFilters();
            const requestData = {
                query: query,
                ...filters
            };

            const response = await this.makeRequest('POST', '/advanced-search', requestData);

            if (response && response.success) {
                this.displayResults(response);
                this.updateStats(response);
                this.hideSuggestions();
            } else {
                this.showMessage(response?.message || 'حدث خطأ في البحث المتقدم', 'error');
            }
        } catch (error) {
            console.error('خطأ في البحث المتقدم:', error);
            this.showMessage('خطأ في الاتصال: ' + error.message, 'error');
        } finally {
            this.hideLoading();
        }
    }

    async getSuggestions(query) {
        try {
            // التحقق من الـ cache أولاً
            const cacheKey = `suggestions_${query}`;
            if (this.cache.has(cacheKey)) {
                const cachedData = this.cache.get(cacheKey);
                this.displaySuggestions(cachedData);
                return;
            }

            const response = await this.makeRequest('GET', '/suggestions', { term: query });

            if (response && Array.isArray(response)) {
                this.cache.set(cacheKey, response);
                this.displaySuggestions(response);
            } else {
                this.hideSuggestions();
            }
        } catch (error) {
            console.warn('خطأ في جلب الاقتراحات:', error);
            this.hideSuggestions();
        }
    }

    displaySuggestions(suggestions) {
        const suggestionsContainer = document.getElementById('scoutSuggestions');
        if (!suggestionsContainer || !suggestions || suggestions.length === 0) {
            this.hideSuggestions();
            return;
        }

        let html = '';
        suggestions.slice(0, 5).forEach(suggestion => {
            html += `
                <div class="scout-suggestion-item" onclick="scoutSearchEngine.selectSuggestion('${suggestion.value}')">
                    <strong>${suggestion.label}</strong>
                    <small class="text-muted">${suggestion.description || ''}</small>
                </div>
            `;
        });

        suggestionsContainer.innerHTML = html;
        suggestionsContainer.style.display = 'block';
    }

    hideSuggestions() {
        const suggestionsContainer = document.getElementById('scoutSuggestions');
        if (suggestionsContainer) {
            suggestionsContainer.style.display = 'none';
        }
    }

    selectSuggestion(value) {
        const searchInput = document.getElementById('scoutSearchInput');
        if (searchInput) {
            searchInput.value = value;
            this.hideSuggestions();
            this.performInstantSearch();
        }
    }

    getFilters() {
        return {
            gender: document.getElementById('scoutGenderFilter')?.value || '',
            birth_year: document.getElementById('scoutBirthYearFilter')?.value || '',
            city: document.getElementById('scoutCityFilter')?.value || '',
            limit: parseInt(document.getElementById('scoutLimitFilter')?.value) || 100
        };
    }

    displayResults(response) {
        console.log('📊 displayResults called with:', response);

        const resultsContainer = document.getElementById('scoutSearchResults');
        const resultsBody = document.getElementById('scoutSearchResultsBody');
        const noResults = document.getElementById('scoutNoResults');

        // دعم تنسيقات استجابة متعددة
        let data = response.results || response.data || response;

        // إذا كانت البيانات object وليست array
        if (data && typeof data === 'object' && !Array.isArray(data)) {
            if (data.results && Array.isArray(data.results)) {
                data = data.results;
            } else if (data.data && Array.isArray(data.data)) {
                data = data.data;
            } else {
                data = [data];
            }
        }

        console.log('📋 Final processed data:', data);
        console.log('📋 Data type:', typeof data);
        console.log('📋 Is array:', Array.isArray(data));
        console.log('📋 Data length:', Array.isArray(data) ? data.length : 'not array');

        if (!data || (Array.isArray(data) && data.length === 0)) {
            console.log('❌ لا توجد بيانات - إخفاء النتائج');
            if (resultsContainer) resultsContainer.style.display = 'none';
            if (noResults) noResults.style.display = 'block';
            return;
        }

        console.log('✅ يوجد بيانات - عرض النتائج');
        if (noResults) noResults.style.display = 'none';
        if (resultsContainer) resultsContainer.style.display = 'block';

        // بناء جدول النتائج مع تنسيق محسن
        let html = '';
        const results = Array.isArray(data) ? data : [data];

        results.forEach((person, index) => {
            console.log(`📝 Processing person ${index + 1}:`, person);

            // بناء الاسم الكامل بطرق متعددة
            let fullName = '';
            if (person.full_name) {
                fullName = person.full_name;
            } else if (person.CI_FIRST_ARB || person.CI_FATHER_ARB || person.CI_FAMILY_ARB) {
                fullName = [
                    person.CI_FIRST_ARB,
                    person.CI_FATHER_ARB,
                    person.CI_GRAND_FATHER_ARB,
                    person.CI_FAMILY_ARB
                ].filter(name => name && name.trim()).join(' ');
            } else if (person.first_name || person.father_name || person.family_name) {
                fullName = [
                    person.first_name,
                    person.father_name,
                    person.grand_father_name,
                    person.family_name
                ].filter(name => name && name.trim()).join(' ');
            } else {
                fullName = person.name || 'غير محدد';
            }

            const personId = person.ID || person.id || index;
            const editUrl = person.edit_url || `/admin/persons/${personId}/edit`;
            const idNum = person.CI_ID_NUM || person.id_num || 'غير محدد';
            const birthDate = person.CI_BIRTH_DT || person.birth_date || 'غير محدد';
            const city = person.CITY || person.city || 'غير محدد';
            const motherName = person.MOTHER_NAME1 || person.mother_name || 'غير محدد';

            // تحديد الجنس
            let gender = 'غير محدد';
            if (person.CI_SEX_CD == 1 || person.CI_SEX_CD === 'M' || person.gender === 'ذكر') {
                gender = 'ذكر';
            } else if (person.CI_SEX_CD == 2 || person.CI_SEX_CD === 'F' || person.gender === 'أنثى') {
                gender = 'أنثى';
            }

            html += `
                <tr class="scout-result-row animate__animated animate__fadeIn" data-person-id="${personId}" style="animation-delay: ${index * 0.1}s;">
                    <td class="text-center">
                        <a href="${editUrl}" class="btn btn-sm btn-outline-primary" title="تحرير البيانات">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                    <td>
                        <span class="badge badge-secondary">${idNum}</span>
                    </td>
                    <td>
                        <strong class="text-primary">${fullName}</strong>
                        ${gender !== 'غير محدد' ? `<br><small class="text-muted"><i class="fas fa-user"></i> ${gender}</small>` : ''}
                    </td>
                    <td>
                        <i class="fas fa-calendar text-success"></i> ${birthDate}
                    </td>
                    <td>
                        <i class="fas fa-map-marker-alt text-info"></i> ${city}
                    </td>
                    <td>
                        <i class="fas fa-female text-pink"></i> ${motherName}
                    </td>
                </tr>
            `;
        });

        console.log('🔨 Generated HTML length:', html.length);
        console.log('🔨 Generated HTML preview:', html.substring(0, 300) + '...');

        if (resultsBody) {
            resultsBody.innerHTML = html;
            console.log('✅ HTML inserted into resultsBody');

            // إضافة تأثيرات بصرية
            const rows = resultsBody.querySelectorAll('.scout-result-row');
            console.log('🎭 Added animations to', rows.length, 'rows');
        } else {
            console.log('❌ resultsBody element not found!');
            console.log('Available elements:', document.querySelectorAll('[id*="scout"], [id*="Scout"], [id*="search"], [id*="Search"]'));
        }

        // التأكد من عرض الحاوي
        if (resultsContainer) {
            resultsContainer.style.display = 'block';
            console.log('✅ Results container shown');
        } else {
            console.log('❌ Results container not found!');
        }

        // تحديث الإحصائيات
        this.updateStats({
            total: results.length,
            search_time: response.search_time || '< 0.1'
        });
    }

    updateStats(response) {
        // تحديث إحصائيات البحث
        const statsElement = document.getElementById('scoutSearchStats');
        if (statsElement && response) {
            const count = response.total || response.data?.length || 0;
            const time = response.search_time || 0;
            statsElement.innerHTML = `
                <span class="badge bg-success me-2">
                    ${count} نتيجة
                </span>
                <span class="badge bg-info">
                    ${time} ثانية
                </span>
            `;
        }
    }

    toggleAdvancedFilters() {
        const filtersContainer = document.getElementById('scoutAdvancedFilters');
        const toggleBtn = document.getElementById('scoutToggleAdvanced');

        if (filtersContainer && toggleBtn) {
            const isHidden = filtersContainer.style.display === 'none';
            filtersContainer.style.display = isHidden ? 'block' : 'none';
            toggleBtn.innerHTML = isHidden ?
                '<i class="fas fa-chevron-up"></i> إخفاء الفلاتر' :
                '<i class="fas fa-chevron-down"></i> إظهار الفلاتر';
        }
    }

    clearSearch() {
        const searchInput = document.getElementById('scoutSearchInput');
        if (searchInput) {
            searchInput.value = '';
        }

        // إخفاء النتائج
        const resultsContainer = document.getElementById('scoutSearchResults');
        const noResults = document.getElementById('scoutNoResults');

        if (resultsContainer) resultsContainer.style.display = 'none';
        if (noResults) noResults.style.display = 'none';

        // مسح الإحصائيات
        const statsElement = document.getElementById('scoutSearchStats');
        if (statsElement) {
            statsElement.innerHTML = '';
        }

        this.hideSuggestions();
    }

    showLoading() {
        const loadingElement = document.getElementById('scoutLoadingIndicator');
        if (loadingElement) {
            loadingElement.style.display = 'block';
        }

        // تعطيل أزرار البحث
        const buttons = ['scoutSearchBtn', 'scoutAdvancedSearchBtn'];
        buttons.forEach(btnId => {
            const btn = document.getElementById(btnId);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري البحث...';
            }
        });
    }

    hideLoading() {
        const loadingElement = document.getElementById('scoutLoadingIndicator');
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }

        // إعادة تفعيل الأزرار
        const searchBtn = document.getElementById('scoutSearchBtn');
        if (searchBtn) {
            searchBtn.disabled = false;
            searchBtn.innerHTML = '<i class="fas fa-search"></i> بحث سريع';
        }

        const advancedBtn = document.getElementById('scoutAdvancedSearchBtn');
        if (advancedBtn) {
            advancedBtn.disabled = false;
            advancedBtn.innerHTML = '<i class="fas fa-filter"></i> بحث متقدم';
        }
    }

    showMessage(message, type = 'info') {
        const messageElement = document.getElementById('scoutMessage');
        if (messageElement) {
            const alertClass = type === 'error' ? 'alert-danger' :
                             type === 'warning' ? 'alert-warning' :
                             type === 'success' ? 'alert-success' : 'alert-info';

            messageElement.className = `alert ${alertClass}`;
            messageElement.innerHTML = message;
            messageElement.style.display = 'block';
        }
    }

    hideMessage() {
        const messageElement = document.getElementById('scoutMessage');
        if (messageElement) {
            messageElement.style.display = 'none';
        }
    }

    async makeRequest(method, endpoint, data = {}) {
        try {
            let url = this.baseUrl + endpoint;
            let options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (this.csrfToken) {
                options.headers['X-CSRF-TOKEN'] = this.csrfToken;
            }

            if (method === 'GET') {
                const params = new URLSearchParams(data);
                url += '?' + params.toString();
            } else {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('خطأ في الطلب:', error);
            throw error;
        }
    }
}

// تهيئة محرك البحث عند تحميل الصفحة
let scoutSearchEngine;

document.addEventListener('DOMContentLoaded', function() {
    scoutSearchEngine = new ScoutSearchEngine();
});

// دالة لفتح modal البحث
function openScoutSearchModal() {
    const modal = document.getElementById('scoutSearchModal');
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // التركيز على حقل البحث
        setTimeout(() => {
            const searchInput = document.getElementById('scoutSearchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }, 300);
    }
}
