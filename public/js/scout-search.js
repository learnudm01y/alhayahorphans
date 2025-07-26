/**
 * Scout Search JavaScript
 * محرك البحث السريع بتقنية Laravel Scout
 */

class ScoutSearchEngine {
    constructor() {
        this.searchTimeout = null;
        this.currentRequest = null;
        this.baseUrl = '/admin/scout';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        this.init();
    }

    init() {
        this.bindEvents();
        this.setupAutoComplete();
    }

    bindEvents() {
        // البحث السريع
        document.getElementById('scoutSearchBtn')?.addEventListener('click', () => {
            this.performInstantSearch();
        });

        // البحث عند الضغط على Enter
        document.getElementById('scoutSearchInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.performInstantSearch();
            }
        });

        // البحث المتقدم
        document.getElementById('scoutAdvancedSearchBtn')?.addEventListener('click', () => {
            this.performAdvancedSearch();
        });

        // تبديل الفلاتر المتقدمة
        document.getElementById('scoutToggleAdvanced')?.addEventListener('click', () => {
            this.toggleAdvancedFilters();
        });

        // إعادة تعيين الفلاتر
        document.getElementById('scoutResetFiltersBtn')?.addEventListener('click', () => {
            this.resetFilters();
        });

        // إحصائيات النظام
        document.getElementById('scoutSearchStatsBtn')?.addEventListener('click', () => {
            this.showSystemStats();
        });

        // مسح الكاش
        document.getElementById('scoutClearCacheBtn')?.addEventListener('click', () => {
            this.clearCache();
        });

        // فهرسة البيانات
        document.getElementById('scoutIndexDataBtn')?.addEventListener('click', () => {
            this.indexData();
        });

        // تصدير النتائج
        document.getElementById('scoutExportResults')?.addEventListener('click', () => {
            this.exportResults();
        });
    }

    setupAutoComplete() {
        const searchInput = document.getElementById('scoutSearchInput');
        if (!searchInput) return;

        // البحث التلقائي أثناء الكتابة
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();

            // مسح المؤقت السابق
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            // تأخير البحث لتجنب الطلبات المتكررة
            if (query.length >= 2) {
                this.searchTimeout = setTimeout(() => {
                    this.getSuggestions(query);
                }, 300);
            } else {
                this.hideSuggestions();
            }
        });
    }

    async performInstantSearch() {
        const query = document.getElementById('scoutSearchInput').value.trim();

        if (!query) {
            this.showError('يرجى إدخال كلمة البحث');
            return;
        }

        this.showLoading();
        this.hideError();

        try {
            const response = await this.makeRequest('POST', `${this.baseUrl}/instant-search`, {
                query: query,
                limit: 50
            });

            if (response.success) {
                this.displayResults(response);
                this.updateStats(response);
            } else {
                this.showError(response.message || 'حدث خطأ في البحث');
            }
        } catch (error) {
            this.showError('خطأ في الاتصال: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }

    async performAdvancedSearch() {
        const query = document.getElementById('scoutSearchInput').value.trim();

        if (!query) {
            this.showError('يرجى إدخال كلمة البحث');
            return;
        }

        const filters = this.getFilters();

        this.showLoading();
        this.hideError();

        try {
            const response = await this.makeRequest('POST', `${this.baseUrl}/advanced-search`, {
                query: query,
                ...filters
            });

            if (response.success) {
                this.displayResults(response);
                this.updateStats(response);
            } else {
                this.showError(response.message || 'حدث خطأ في البحث المتقدم');
            }
        } catch (error) {
            this.showError('خطأ في الاتصال: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }

    async getSuggestions(query) {
        try {
            const response = await this.makeRequest('POST', `${this.baseUrl}/suggestions`, {
                query: query,
                limit: 10
            });

            if (response.success && response.suggestions.length > 0) {
                this.displaySuggestions(response.suggestions);
            } else {
                this.hideSuggestions();
            }
        } catch (error) {
            console.warn('خطأ في جلب الاقتراحات:', error);
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
        const resultsContainer = document.getElementById('scoutSearchResults');
        const resultsBody = document.getElementById('scoutSearchResultsBody');
        const noResults = document.getElementById('scoutNoResults');

        if (!response.results || response.results.length === 0) {
            resultsContainer.style.display = 'none';
            noResults.style.display = 'block';
            return;
        }

        noResults.style.display = 'none';
        resultsContainer.style.display = 'block';

        // بناء جدول النتائج
        let html = '';
        response.results.forEach((person, index) => {
            html += `
                <tr class="result-row" data-person-id="${person.id}">
                    <td>${index + 1}</td>
                    <td>
                        <span class="badge bg-primary">${person.id_num || 'غير محدد'}</span>
                    </td>
                    <td>
                        <strong>${person.full_name || 'غير محدد'}</strong>
                        <br><small class="text-muted">
                            ${person.first_name || ''} ${person.father_name || ''} ${person.grand_father_name || ''}
                        </small>
                    </td>
                    <td>${person.mother_name || 'غير محدد'}</td>
                    <td>${person.birth_date || 'غير محدد'}</td>
                    <td>
                        <span class="badge ${person.gender === 'ذكر' ? 'bg-info' : 'bg-warning'}">
                            ${person.gender || 'غير محدد'}
                        </span>
                    </td>
                    <td>${person.city || 'غير محدد'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-details"
                                data-person-id="${person.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        resultsBody.innerHTML = html;

        // ربط أحداث عرض التفاصيل
        document.querySelectorAll('.view-details').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const personId = e.target.closest('button').dataset.personId;
                this.viewPersonDetails(personId);
            });
        });
    }

    updateStats(response) {
        const statsContainer = document.getElementById('scoutSearchStats');

        if (response.search_time !== undefined) {
            document.getElementById('searchTime').textContent = `${response.search_time}ms`;
        }

        if (response.count !== undefined) {
            document.getElementById('resultCount').textContent = response.count;
        }

        if (response.engine) {
            document.getElementById('searchEngine').textContent = response.engine;
        }

        if (response.cached !== undefined) {
            document.getElementById('cacheStatus').textContent = response.cached ? 'مفعل' : 'معطل';
            document.getElementById('cacheStatus').className = response.cached ? 'badge bg-success' : 'badge bg-secondary';
        }

        statsContainer.style.display = 'block';
    }

    async showSystemStats() {
        try {
            const response = await this.makeRequest('GET', `${this.baseUrl}/stats`);

            if (response.success) {
                const statsHtml = `
                    <div class="row">
                        <div class="col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">إجمالي السجلات</h5>
                                    <h3 class="text-primary">${response.stats.total_records.toLocaleString()}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">السجلات المفهرسة</h5>
                                    <h3 class="text-success">${response.stats.indexed_records.toLocaleString()}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">نسبة الفهرسة</h5>
                                    <h3 class="text-info">${response.stats.index_percentage}%</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">محرك البحث</h5>
                                    <h3 class="text-warning">${response.stats.engine}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">آخر تحديث: ${response.stats.last_updated}</small>
                    </div>
                `;

                document.getElementById('scoutSystemStats').innerHTML = statsHtml;
                new bootstrap.Modal(document.getElementById('scoutStatsModal')).show();
            }
        } catch (error) {
            this.showError('خطأ في جلب الإحصائيات: ' + error.message);
        }
    }

    async clearCache() {
        try {
            const response = await this.makeRequest('POST', `${this.baseUrl}/clear-cache`);

            if (response.success) {
                this.showSuccess('تم مسح الكاش بنجاح');
            } else {
                this.showError(response.message || 'فشل في مسح الكاش');
            }
        } catch (error) {
            this.showError('خطأ في مسح الكاش: ' + error.message);
        }
    }

    async indexData() {
        try {
            this.showLoading();
            const response = await this.makeRequest('POST', `${this.baseUrl}/index-data`, {
                batch_size: 1000
            });

            if (response.success) {
                this.showSuccess(`تم فهرسة ${response.result.processed} سجل بنجاح`);
                // إغلاق modal الإحصائيات
                bootstrap.Modal.getInstance(document.getElementById('scoutStatsModal'))?.hide();
            } else {
                this.showError(response.message || 'فشل في فهرسة البيانات');
            }
        } catch (error) {
            this.showError('خطأ في فهرسة البيانات: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }

    toggleAdvancedFilters() {
        const filtersContainer = document.getElementById('scoutAdvancedFilters');
        const isVisible = filtersContainer.style.display !== 'none';
        filtersContainer.style.display = isVisible ? 'none' : 'block';

        const toggleBtn = document.getElementById('scoutToggleAdvanced');
        toggleBtn.innerHTML = isVisible
            ? '<i class="fas fa-cog me-1"></i>إظهار الفلاتر'
            : '<i class="fas fa-times me-1"></i>إخفاء الفلاتر';
    }

    resetFilters() {
        document.getElementById('scoutGenderFilter').value = '';
        document.getElementById('scoutBirthYearFilter').value = '';
        document.getElementById('scoutCityFilter').value = '';
        document.getElementById('scoutLimitFilter').value = '50';
    }

    showLoading() {
        document.getElementById('scoutSearchProgress').style.display = 'block';
        document.getElementById('scoutSearchBtn').disabled = true;
        document.getElementById('scoutAdvancedSearchBtn').disabled = true;
    }

    hideLoading() {
        document.getElementById('scoutSearchProgress').style.display = 'none';
        document.getElementById('scoutSearchBtn').disabled = false;
        document.getElementById('scoutAdvancedSearchBtn').disabled = false;
    }

    showError(message) {
        const errorContainer = document.getElementById('scoutSearchError');
        document.getElementById('scoutErrorMessage').textContent = message;
        errorContainer.style.display = 'block';
    }

    hideError() {
        document.getElementById('scoutSearchError').style.display = 'none';
    }

    showSuccess(message) {
        // يمكن إضافة toast أو alert للنجاح
        console.log('Success:', message);
    }

    async makeRequest(method, url, data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    viewPersonDetails(personId) {
        // فتح تفاصيل الشخص في modal أو صفحة جديدة
        window.open(`/admin/persons/${personId}/details`, '_blank');
    }

    exportResults() {
        // تصدير النتائج الحالية
        const results = document.querySelectorAll('#scoutSearchResultsBody tr');
        if (results.length === 0) {
            this.showError('لا توجد نتائج للتصدير');
            return;
        }

        // يمكن تطوير هذه الوظيفة لاحقاً
        this.showSuccess('وظيفة التصدير ستكون متاحة قريباً');
    }
}

// تشغيل محرك البحث عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    window.scoutSearch = new ScoutSearchEngine();
});

// فتح modal البحث السريع
function openScoutSearchModal() {
    const modal = new bootstrap.Modal(document.getElementById('scoutSearchModal'));
    modal.show();

    // التركيز على حقل البحث
    setTimeout(() => {
        document.getElementById('scoutSearchInput')?.focus();
    }, 500);
}
