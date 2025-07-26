/**
 * محرك البحث المتقدم للمواطنين
 * Advanced Person Search Engine
 */

class PersonSearchEngine {
    constructor() {
        this.searchModal = document.getElementById('advancedSearchModal');
        this.searchForm = document.getElementById('advancedSearchForm');
        this.resultsTable = null;
        this.currentPage = 1;
        this.perPage = 15;
        this.autocompleteInitialized = false;

        this.initializeEvents();
        this.initializeModal();
    }

    /**
     * تهيئة الأحداث
     */
    initializeEvents() {
        // حدث إرسال النموذج
        if (this.searchForm) {
            this.searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.performSearch();
            });
        }

        // حدث البحث السريع
        const quickSearchBtn = document.getElementById('quickSearchBtn');
        if (quickSearchBtn) {
            quickSearchBtn.addEventListener('click', () => {
                this.performQuickSearch();
            });
        }

        // حدث مسح النموذج
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                this.clearForm();
            });
        }

        // حدث الحصول على الإحصائيات
        const getStatisticsBtn = document.getElementById('getStatisticsBtn');
        if (getStatisticsBtn) {
            getStatisticsBtn.addEventListener('click', () => {
                this.getStatistics();
            });
        }

        // أحداث التصدير
        const exportBtns = document.querySelectorAll('.export-btn');
        exportBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const format = e.target.closest('.export-btn').dataset.format;
                this.exportResults(format);
            });
        });

        // حدث حذف الشخص
        document.addEventListener('click', (e) => {
            if (e.target.closest('.delete-btn')) {
                e.preventDefault();
                this.confirmDelete(e.target.closest('.delete-btn'));
            }
        });
    }

    /**
     * تهيئة المودال
     */
    initializeModal() {
        if (this.searchModal) {
            // إضافة أحداث المودال
            this.searchModal.addEventListener('shown.bs.modal', () => {
                this.onModalShown();
            });

            this.searchModal.addEventListener('hidden.bs.modal', () => {
                this.onModalHidden();
            });
        }

        // تهيئة الاستكمال التلقائي للمرة الأولى
        this.initializeAutocomplete();
    }

    /**
     * عند إظهار المودال
     */
    onModalShown() {
        // إعادة تهيئة الاستكمال التلقائي
        this.initializeAutocomplete();

        // تركيز على حقل البحث
        const searchTermInput = document.getElementById('search_term');
        if (searchTermInput) {
            setTimeout(() => {
                searchTermInput.focus();
            }, 100);
        }

        // إعادة تعيين حالة التهيئة
        this.autocompleteInitialized = true;
    }

    /**
     * عند إخفاء المودال
     */
    onModalHidden() {
        // إخفاء الاقتراحات وإزالتها
        this.hideAutocompleteSuggestions();
        this.removeAutocompleteSuggestions();

        // تنظيف حالة التهيئة
        this.autocompleteInitialized = false;

        // مسح النتائج والإحصائيات
        this.hideResults();
        this.hideStatistics();

        // مسح قيمة حقل البحث
        const searchTermInput = document.getElementById('search_term');
        if (searchTermInput) {
            searchTermInput.value = '';
        }

        console.log('تم تنظيف المودال وإزالة جميع الاقتراحات');
    }

    /**
     * تهيئة الاستكمال التلقائي
     */
    initializeAutocomplete() {
        const searchTermInput = document.getElementById('search_term');
        if (!searchTermInput) return;

        // إزالة المستمعين السابقين لتجنب التكرار
        const newInput = searchTermInput.cloneNode(true);
        searchTermInput.parentNode.replaceChild(newInput, searchTermInput);

        let timeout;
        let currentFocus = -1;

        // إضافة مستمع الإدخال
        newInput.addEventListener('input', (e) => {
            clearTimeout(timeout);
            const value = e.target.value.trim();
            currentFocus = -1;

            if (value.length >= 2) {
                timeout = setTimeout(() => {
                    this.getAutocompleteSuggestions(value);
                }, 150); // تقليل الوقت من 300ms إلى 150ms لسرعة أكبر
            } else {
                this.hideAutocompleteSuggestions();
            }
        });

        // إضافة تنقل بالكيبورد
        newInput.addEventListener('keydown', (e) => {
            const suggestions = document.querySelectorAll('.autocomplete-suggestions .list-group-item');

            if (suggestions.length > 0) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentFocus++;
                    if (currentFocus >= suggestions.length) currentFocus = 0;
                    this.setActiveSuggestion(suggestions, currentFocus);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentFocus--;
                    if (currentFocus < 0) currentFocus = suggestions.length - 1;
                    this.setActiveSuggestion(suggestions, currentFocus);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentFocus > -1) {
                        suggestions[currentFocus].click();
                    }
                } else if (e.key === 'Escape') {
                    this.hideAutocompleteSuggestions();
                    currentFocus = -1;
                }
            }
        });

        // إخفاء الاقتراحات عند فقدان التركيز
        newInput.addEventListener('blur', (e) => {
            // تأخير الإخفاء للسماح بالنقر على الاقتراحات
            setTimeout(() => {
                if (!document.activeElement.closest('.autocomplete-suggestions')) {
                    this.hideAutocompleteSuggestions();
                }
            }, 200);
        });

        // إخفاء الاقتراحات عند النقر خارجها
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-input-group')) {
                this.hideAutocompleteSuggestions();
                currentFocus = -1;
            }
        });

        console.log('تم إعادة تهيئة الاستكمال التلقائي');
    }

    /**
     * تعيين الاقتراح النشط
     */
    setActiveSuggestion(suggestions, index) {
        // إزالة التركيز من جميع الاقتراحات
        suggestions.forEach(item => item.classList.remove('active'));

        // إضافة التركيز للاقتراح المحدد
        if (suggestions[index]) {
            suggestions[index].classList.add('active');
            suggestions[index].scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * تنفيذ البحث الرئيسي
     */
    async performSearch() {
        try {
            this.showLoading(true);
            this.hideStatistics();

            const formData = new FormData(this.searchForm);
            const searchData = Object.fromEntries(formData.entries());

            const response = await fetch('/admin/persons/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(searchData)
            });

            if (!response.ok) {
                throw new Error('حدث خطأ في الاستعلام');
            }

            const data = await response.json();
            this.displayResults(data);
            this.showResults(true);

        } catch (error) {
            console.error('خطأ في البحث:', error);
            this.showError('حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * تنفيذ البحث السريع
     */
    async performQuickSearch() {
        const searchTerm = document.getElementById('search_term').value.trim();

        if (!searchTerm) {
            this.showError('يرجى إدخال كلمة البحث');
            return;
        }

        console.log('🔍 البحث السريع عن:', searchTerm);

        try {
            this.showLoading(true);

            const response = await fetch('/admin/persons/quick-search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ term: searchTerm, limit: 100 }) // زيادة الحد للحصول على نتائج أكثر
            });

            const allResults = await response.json();
            console.log('📡 النتائج الأولية من الخادم:', allResults);

            // تحسين فلترة النتائج محلياً للتأكد من دقة المطابقة
            const searchTermLower = searchTerm.toLowerCase().trim();
            const filteredResults = allResults.filter(person => {
                const fullName = person.full_name ? person.full_name.toString().toLowerCase() : '';
                const idNum = person.ci_id_num ? person.ci_id_num.toString().toLowerCase() : '';

                // التحقق من التطابق الدقيق مع أولوية للتطابق الكامل
                return fullName === searchTermLower ||
                       idNum === searchTermLower ||
                       fullName.includes(searchTermLower) ||
                       idNum.includes(searchTermLower) ||
                       fullName.startsWith(searchTermLower) ||
                       idNum.startsWith(searchTermLower);
            });

            // ترتيب النتائج حسب الأولوية
            filteredResults.sort((a, b) => {
                const aName = (a.full_name || '').toLowerCase();
                const bName = (b.full_name || '').toLowerCase();
                const aId = (a.ci_id_num || '').toString().toLowerCase();
                const bId = (b.ci_id_num || '').toString().toLowerCase();

                // أولوية عالية للتطابق الكامل
                if (aName === searchTermLower || aId === searchTermLower) return -1;
                if (bName === searchTermLower || bId === searchTermLower) return 1;

                // أولوية متوسطة للتطابق في البداية
                if (aName.startsWith(searchTermLower) || aId.startsWith(searchTermLower)) return -1;
                if (bName.startsWith(searchTermLower) || bId.startsWith(searchTermLower)) return 1;

                return 0;
            });

            console.log(`✅ تم فلترة ${filteredResults.length} نتيجة من أصل ${allResults.length}`);

            this.displayQuickResults(filteredResults);
            this.showResults(true);

        } catch (error) {
            console.error('خطأ في البحث السريع:', error);
            this.showError('حدث خطأ أثناء البحث السريع');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * الحصول على الإحصائيات
     */
    async getStatistics() {
        try {
            const formData = new FormData(this.searchForm);
            const searchData = Object.fromEntries(formData.entries());

            const response = await fetch('/admin/persons/search/statistics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(searchData)
            });

            const statistics = await response.json();
            this.displayStatistics(statistics);
            this.showStatistics(true);

        } catch (error) {
            console.error('خطأ في الحصول على الإحصائيات:', error);
            this.showError('حدث خطأ أثناء الحصول على الإحصائيات');
        }
    }

    /**
     * تصدير النتائج
     */
    async exportResults(format) {
        try {
            const formData = new FormData(this.searchForm);
            const searchData = Object.fromEntries(formData.entries());
            searchData.format = format;

            const response = await fetch('/admin/persons/search/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(searchData)
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `search_results_${new Date().getTime()}.${format}`;
                a.click();
                window.URL.revokeObjectURL(url);
            } else {
                throw new Error('فشل في التصدير');
            }

        } catch (error) {
            console.error('خطأ في التصدير:', error);
            this.showError('حدث خطأ أثناء التصدير');
        }
    }

    /**
     * الحصول على اقتراحات الاستكمال التلقائي
     */
    async getAutocompleteSuggestions(term) {
        console.log('🔍 طلب اقتراحات للمصطلح:', term);

        try {
            const response = await fetch('/admin/persons/quick-search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ term: term, limit: 10 }) // زيادة عدد الاقتراحات
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const suggestions = await response.json();
            console.log('📡 استجابة الخادم:', suggestions);

            // التأكد من أن النتيجة هي مصفوفة
            if (!Array.isArray(suggestions)) {
                console.warn('⚠️ النتيجة المستلمة ليست مصفوفة:', suggestions);
                this.showAutocompleteSuggestions([]);
                return;
            }

            console.log(`✅ تم استلام ${suggestions.length} اقتراح من الخادم`);
            this.showAutocompleteSuggestions(suggestions);

        } catch (error) {
            console.error('❌ خطأ في الاستكمال التلقائي:', error);
            // إخفاء الاقتراحات في حالة الخطأ
            this.hideAutocompleteSuggestions();
        }
    }

    /**
     * عرض اقتراحات الاستكمال التلقائي
     */
    showAutocompleteSuggestions(suggestions) {
        console.log('🔍 بدء عرض الاقتراحات:', suggestions);

        const searchTermInput = document.getElementById('search_term');
        if (!searchTermInput) {
            console.error('❌ لم يتم العثور على حقل البحث search_term');
            return;
        }

        // التحقق من صحة البيانات المدخلة
        if (!Array.isArray(suggestions)) {
            console.warn('⚠️ البيانات المستلمة ليست مصفوفة صحيحة:', suggestions);
            return;
        }

        // إزالة الحاوي السابق إن وجد
        const existingContainer = document.querySelector('.autocomplete-suggestions');
        if (existingContainer) {
            console.log('🗑️ إزالة الحاوي السابق');
            existingContainer.remove();
        }

        if (suggestions.length === 0) {
            console.log('📭 لا توجد اقتراحات للعرض');
            return;
        }

        // فلترة الاقتراحات للعرض فقط المطابقة تماماً
        const searchTerm = searchTermInput.value.toLowerCase().trim();
        const filteredSuggestions = suggestions.filter(suggestion => {
            // التأكد من وجود البيانات قبل معالجتها
            const fullName = suggestion.full_name ? suggestion.full_name.toString().toLowerCase() : '';
            const idNum = suggestion.ci_id_num ? suggestion.ci_id_num.toString().toLowerCase() : '';

            // فلترة دقيقة - يجب أن يبدأ النص بما كتبه المستخدم أو يحتوي عليه بشكل دقيق
            return fullName.startsWith(searchTerm) ||
                   fullName.includes(searchTerm) ||
                   idNum.startsWith(searchTerm) ||
                   idNum.includes(searchTerm);
        });

        if (filteredSuggestions.length === 0) {
            console.log('🔍 لا توجد اقتراحات مطابقة للنص المدخل:', searchTerm);
            return;
        }

        // ترتيب النتائج - الأولوية للتطابق الذي يبدأ بنفس النص
        filteredSuggestions.sort((a, b) => {
            const aName = (a.full_name || '').toLowerCase();
            const bName = (b.full_name || '').toLowerCase();
            const aId = (a.ci_id_num || '').toString().toLowerCase();
            const bId = (b.ci_id_num || '').toString().toLowerCase();

            // إعطاء أولوية أعلى للنتائج التي تبدأ بنفس النص
            const aStartsWithName = aName.startsWith(searchTerm);
            const bStartsWithName = bName.startsWith(searchTerm);
            const aStartsWithId = aId.startsWith(searchTerm);
            const bStartsWithId = bId.startsWith(searchTerm);

            if ((aStartsWithName || aStartsWithId) && !(bStartsWithName || bStartsWithId)) return -1;
            if (!(aStartsWithName || aStartsWithId) && (bStartsWithName || bStartsWithId)) return 1;

            return 0;
        });

        console.log(`✅ عدد الاقتراحات المطابقة: ${filteredSuggestions.length}`);

        // العثور على الحاوي الأب المناسب
        let parentContainer = searchTermInput.closest('.input-group');
        if (!parentContainer) {
            parentContainer = searchTermInput.closest('.search-input-group');
        }
        if (!parentContainer) {
            parentContainer = searchTermInput.closest('.form-group');
        }
        if (!parentContainer) {
            parentContainer = searchTermInput.parentNode;
        }

        console.log('📍 الحاوي الأب المختار:', parentContainer);

        // إنشاء حاوي جديد
        const container = document.createElement('div');
        container.className = 'autocomplete-suggestions list-group';

        // تطبيق CSS مباشرة للتأكد من الظهور
        Object.assign(container.style, {
            position: 'absolute',
            top: '100%',
            right: '0',
            left: 'auto',
            zIndex: '9999',
            maxHeight: '250px',
            overflowY: 'auto',
            marginTop: '2px',
            borderRadius: '8px',
            border: '1px solid #dee2e6',
            backgroundColor: '#fff',
            boxShadow: '0 8px 25px rgba(0, 0, 0, 0.15)',
            display: 'block',
            opacity: '0',
            transform: 'translateY(-10px)',
            transition: 'all 0.2s ease',
            minWidth: '300px',
            maxWidth: '400px',
            direction: 'rtl',
            textAlign: 'right'
        });

        // التأكد من أن الحاوي الأب له position relative
        if (parentContainer) {
            parentContainer.style.position = 'relative';
            parentContainer.appendChild(container);
            console.log('📦 تم إضافة الحاوي إلى:', parentContainer.tagName, parentContainer.className);
        } else {
            console.error('❌ لم يتم العثور على حاوي أب مناسب');
            return;
        }

        // إنشاء عناصر الاقتراحات
        filteredSuggestions.forEach((suggestion, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action border-0 py-2';

            // تطبيق CSS مباشرة
            Object.assign(item.style, {
                border: 'none',
                borderBottom: '1px solid #f8f9fa',
                cursor: 'pointer',
                padding: '12px 15px',
                backgroundColor: '#fff',
                color: '#333',
                transition: 'all 0.2s ease',
                textAlign: 'right',
                direction: 'rtl'
            });

            // تمييز النص المطابق
            const highlightedName = this.highlightMatchingText(suggestion.full_name || '', searchTerm);
            const highlightedId = this.highlightMatchingText(suggestion.ci_id_num || '', searchTerm);

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center" style="direction: rtl;">
                    <div class="flex-grow-1 text-end">
                        <div class="fw-bold text-primary" style="text-align: right;">${highlightedName}</div>
                        <small class="text-muted" style="text-align: right;">رقم الهوية: ${highlightedId}</small>
                    </div>
                    <div class="text-start">
                        <small class="text-muted d-block">${suggestion.city || '-'}</small>
                        <small class="text-success">${suggestion.birth_date || ''}</small>
                    </div>
                </div>
            `;

            // إضافة أحداث hover
            item.addEventListener('mouseenter', () => {
                item.style.backgroundColor = '#f8f9fa';
                item.style.transform = 'translateX(2px)'; // تحريك لليمين بدلاً من اليسار
                item.style.borderRight = '3px solid #0d6efd'; // حدود على اليمين
                item.style.borderLeft = 'none';
            });

            item.addEventListener('mouseleave', () => {
                if (!item.classList.contains('active')) {
                    item.style.backgroundColor = '#fff';
                    item.style.transform = 'translateX(0)';
                    item.style.borderRight = 'none';
                    item.style.borderLeft = 'none';
                }
            });

            item.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('🎯 تم اختيار:', suggestion.full_name);
                searchTermInput.value = suggestion.full_name;
                this.hideAutocompleteSuggestions();
                // تركيز على حقل البحث بعد الاختيار
                searchTermInput.focus();
            });

            // إضافة تنقل بالكيبورد
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    item.click();
                } else if (e.key === 'ArrowDown' && index < filteredSuggestions.length - 1) {
                    e.preventDefault();
                    container.children[index + 1].focus();
                } else if (e.key === 'ArrowUp' && index > 0) {
                    e.preventDefault();
                    container.children[index - 1].focus();
                }
            });

            container.appendChild(item);
        });

        // إضافة styling للحدود
        const items = container.children;
        if (items.length > 0) {
            items[0].style.borderRadius = '8px 8px 0 0';
            items[items.length - 1].style.borderBottom = 'none';
            items[items.length - 1].style.borderRadius = '0 0 8px 8px';
        }

        console.log('🎨 تم إنشاء', items.length, 'عنصر اقتراح');

        // عرض الحاوي مع تأثير
        setTimeout(() => {
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
            console.log('✨ تم عرض قائمة الاقتراحات بنجاح');
        }, 10);

        // إضافة معرف للحاوي للمراجعة
        container.setAttribute('data-suggestions-count', filteredSuggestions.length);

        console.log(`🎉 تم عرض ${filteredSuggestions.length} اقتراحات بنجاح`);
    }

    /**
     * تمييز النص المطابق في الاقتراحات
     */
    highlightMatchingText(text, searchTerm) {
        // التأكد من وجود النص ومصطلح البحث
        if (!searchTerm || !text) return text || '';

        // تحويل النص إلى string في حالة كان رقماً
        const textStr = text.toString();

        try {
            const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return textStr.replace(regex, '<mark class="bg-warning text-dark">$1</mark>');
        } catch (error) {
            console.warn('خطأ في تمييز النص:', error);
            return textStr;
        }
    }

    /**
     * إخفاء اقتراحات الاستكمال التلقائي
     */
    hideAutocompleteSuggestions() {
        const container = document.querySelector('.autocomplete-suggestions');
        if (container) {
            console.log('🫥 إخفاء قائمة الاقتراحات');
            container.style.transition = 'all 0.2s ease';
            container.style.opacity = '0';
            container.style.transform = 'translateY(-10px)';

            setTimeout(() => {
                if (container.parentNode) {
                    container.style.display = 'none';
                }
            }, 200);
        }
    }

    /**
     * إزالة حاوي الاقتراحات نهائياً
     */
    removeAutocompleteSuggestions() {
        const container = document.querySelector('.autocomplete-suggestions');
        if (container) {
            console.log('🗑️ إزالة حاوي الاقتراحات نهائياً');
            container.remove();
        }
    }

    /**
     * عرض النتائج
     */
    displayResults(data) {
        const tbody = document.getElementById('searchResultsBody');
        const resultsCount = document.getElementById('resultsCount');

        tbody.innerHTML = '';

        if (data.data && data.data.length > 0) {
            data.data.forEach(person => {
                const row = this.createResultRow(person);
                tbody.appendChild(row);
            });

            resultsCount.textContent = `عُثر على ${data.recordsTotal || data.data.length} نتيجة`;

            // إعداد التنقل إذا كان متاحاً
            if (data.draw) {
                this.setupPagination(data);
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">لا توجد نتائج</td></tr>';
            resultsCount.textContent = 'لا توجد نتائج';
        }
    }

    /**
     * عرض نتائج البحث السريع
     */
    displayQuickResults(results) {
        const tbody = document.getElementById('searchResultsBody');
        const resultsCount = document.getElementById('resultsCount');

        tbody.innerHTML = '';

        if (results.length > 0) {
            results.forEach(person => {
                const row = this.createQuickResultRow(person);
                tbody.appendChild(row);
            });

            resultsCount.textContent = `عُثر على ${results.length} نتيجة (بحث سريع)`;
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">لا توجد نتائج</td></tr>';
            resultsCount.textContent = 'لا توجد نتائج';
        }
    }

    /**
     * إنشاء صف نتيجة
     */
    createResultRow(person) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${person.CI_ID_NUM || '-'}</td>
            <td>${person.full_name || '-'}</td>
            <td>${person.CI_BIRTH_DT || '-'}</td>
            <td>${person.gender_text || '-'}</td>
            <td>${person.city_name || '-'}</td>
            <td>${person.social_status_name || '-'}</td>
            <td>${person.actions || ''}</td>
        `;
        return row;
    }

    /**
     * إنشاء صف نتيجة سريعة
     */
    createQuickResultRow(person) {
        const row = document.createElement('tr');

        // التأكد من وجود ID صحيح - استخدام ID بدلاً من id
        const personId = person.ID || person.id || person.ci_id || person.CI_ID || '';

        console.log('🔗 إنشاء رابط للشخص:', {
            person: person,
            id: personId,
            ID: person.ID,
            full_name: person.full_name
        });

        row.innerHTML = `
            <td>${person.ci_id_num || '-'}</td>
            <td>${person.full_name || '-'}</td>
            <td>${person.birth_date || '-'}</td>
            <td>-</td>
            <td>${person.city || '-'}</td>
            <td>-</td>
            <td>
                ${personId ?
                    `<a href="/admin/persons/${personId}/edit" class="btn btn-sm btn-outline-info"
                       title="عرض وتعديل" target="_blank">
                        <i class="bi bi-eye"></i>
                    </a>` :
                    `<span class="text-muted">لا يوجد رابط</span>`
                }
            </td>
        `;
        return row;
    }

    /**
     * عرض الإحصائيات
     */
    displayStatistics(statistics) {
        document.getElementById('totalCount').textContent = statistics.total || 0;
        document.getElementById('maleCount').textContent = statistics.male_count || 0;
        document.getElementById('femaleCount').textContent = statistics.female_count || 0;
        document.getElementById('citiesCount').textContent = statistics.cities_count || 0;
    }

    /**
     * تأكيد الحذف
     */
    confirmDelete(deleteBtn) {
        const form = deleteBtn.closest('form');

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'لن تتمكن من التراجع عن هذا الإجراء!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'نعم، احذف!',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    /**
     * مسح النموذج
     */
    clearForm() {
        if (this.searchForm) {
            this.searchForm.reset();
        }

        this.hideResults();
        this.hideStatistics();
        this.hideAutocompleteSuggestions();
        this.removeAutocompleteSuggestions();

        // مسح قيمة حقل البحث يدوياً
        const searchTermInput = document.getElementById('search_term');
        if (searchTermInput) {
            searchTermInput.value = '';
        }

        console.log('تم مسح النموذج وإعادة تعيين جميع القيم');
    }

    /**
     * عرض/إخفاء منطقة التحميل
     */
    showLoading(show) {
        const loading = document.getElementById('searchLoading');
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * عرض/إخفاء النتائج
     */
    showResults(show) {
        const results = document.getElementById('searchResults');
        if (results) {
            results.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * إخفاء النتائج
     */
    hideResults() {
        this.showResults(false);
    }

    /**
     * عرض/إخفاء الإحصائيات
     */
    showStatistics(show) {
        const statistics = document.getElementById('statisticsSection');
        if (statistics) {
            statistics.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * إخفاء الإحصائيات
     */
    hideStatistics() {
        this.showStatistics(false);
    }

    /**
     * عرض رسالة خطأ
     */
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: message,
            confirmButtonText: 'موافق'
        });
    }

    /**
     * إعداد التنقل بين الصفحات
     */
    setupPagination(data) {
        // يمكن تنفيذ التنقل هنا إذا لزم الأمر
        console.log('إعداد التنقل:', data);
    }
}

// تهيئة محرك البحث عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    window.personSearchEngine = new PersonSearchEngine();
});

// دالة لفتح modal البحث (للاستخدام من الخارج)
function openAdvancedSearch() {
    // التأكد من وجود المحرك
    if (!window.personSearchEngine) {
        window.personSearchEngine = new PersonSearchEngine();
    }

    const modal = new bootstrap.Modal(document.getElementById('advancedSearchModal'));
    modal.show();

    // إعادة تهيئة الاستكمال التلقائي بعد فتح المودال
    setTimeout(() => {
        if (window.personSearchEngine) {
            window.personSearchEngine.initializeAutocomplete();
        }
    }, 300);
}

// دالة إضافية لإعادة تهيئة الاستكمال التلقائي يدوياً
function reinitializeAutocomplete() {
    if (window.personSearchEngine) {
        window.personSearchEngine.initializeAutocomplete();
        console.log('تم إعادة تهيئة الاستكمال التلقائي يدوياً');
    }
}

// دالة تشخيصية لفحص حالة الاستكمال التلقائي
function debugAutocomplete() {
    console.log('🔍 فحص حالة الاستكمال التلقائي:');

    const searchTermInput = document.getElementById('search_term');
    console.log('📝 حقل البحث:', searchTermInput);

    if (searchTermInput) {
        console.log('📍 موقع حقل البحث:', searchTermInput.getBoundingClientRect());
        console.log('👨‍👩‍👧‍👦 الحاوي الأب:', searchTermInput.parentNode);
        console.log('🎯 القيمة الحالية:', searchTermInput.value);
    }

    const container = document.querySelector('.autocomplete-suggestions');
    console.log('📦 حاوي الاقتراحات:', container);

    if (container) {
        console.log('🎨 CSS للحاوي:', {
            display: container.style.display,
            position: container.style.position,
            zIndex: container.style.zIndex,
            top: container.style.top,
            opacity: container.style.opacity
        });
        console.log('📏 موقع الحاوي:', container.getBoundingClientRect());
        console.log('🔢 عدد العناصر:', container.children.length);
    }

    const engine = window.personSearchEngine;
    console.log('⚙️ محرك البحث:', engine);

    if (engine) {
        console.log('🔧 حالة التهيئة:', engine.autocompleteInitialized);
    }

    console.log('🎯 للاختبار: اكتب في حقل البحث واكتب debugAutocomplete() مرة أخرى');
}
