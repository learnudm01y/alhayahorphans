/**
 * نظام البحث السريع مع الاقتراحات
 * تصميم بسيط ولون واحد
 */

class ProfileSearchSuggestions {
    constructor(inputSelector, suggestionsContainerSelector) {
        this.input = document.querySelector(inputSelector);
        this.suggestionsContainer = document.querySelector(suggestionsContainerSelector);
        this.currentTimeout = null;
        this.currentRequest = null;
        this.isVisible = false;

        this.init();
    }

    init() {
        if (!this.input) {
            console.error('عنصر البحث غير موجود');
            return;
        }

        this.createSuggestionsContainer();
        this.bindEvents();
    }

    createSuggestionsContainer() {
        if (!this.suggestionsContainer) {
            // إنشاء حاوي الاقتراحات
            this.suggestionsContainer = document.createElement('div');
            this.suggestionsContainer.className = 'profile-search-suggestions';
            this.suggestionsContainer.style.display = 'none';

            // إضافة الحاوي بعد عنصر البحث مباشرة
            const searchContainer = this.input.closest('.profile-search-container');
            if (searchContainer) {
                searchContainer.appendChild(this.suggestionsContainer);
            } else {
                // في حالة عدم وجود container، أضف بعد البحث مباشرة
                this.input.parentNode.insertBefore(this.suggestionsContainer, this.input.nextSibling);
            }
        }
    }

    bindEvents() {
        // البحث أثناء الكتابة
        this.input.addEventListener('input', (e) => {
            this.handleInput(e.target.value);
        });

        // إخفاء الاقتراحات عند النقر خارجها
        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) &&
                !this.suggestionsContainer.contains(e.target)) {
                this.hideSuggestions();
            }
        });

        // التنقل بالكيبورد
        this.input.addEventListener('keydown', (e) => {
            this.handleKeyNavigation(e);
        });

        // إظهار الاقتراحات عند التركيز إذا كان هناك نص
        this.input.addEventListener('focus', () => {
            if (this.input.value.trim().length >= 2) {
                this.handleInput(this.input.value);
            }
        });

        // إعادة حساب الموضع عند تغيير حجم النافذة أو التمرير
        window.addEventListener('resize', () => {
            if (this.isVisible) {
                this.positionSuggestions();
            }
        });

        window.addEventListener('scroll', () => {
            if (this.isVisible) {
                this.positionSuggestions();
            }
        });

        // إخفاء الاقتراحات عند الضغط على Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isVisible) {
                this.hideSuggestions();
            }
        });
    }

    handleInput(value) {
        const query = value.trim();

        // إلغاء الطلب السابق والمؤقت
        this.cancelPreviousRequest();

        if (query.length < 2) {
            this.hideSuggestions();
            return;
        }

        // تأخير البحث لتقليل الطلبات
        this.currentTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }

    cancelPreviousRequest() {
        if (this.currentTimeout) {
            clearTimeout(this.currentTimeout);
            this.currentTimeout = null;
        }

        if (this.currentRequest) {
            this.currentRequest.abort();
            this.currentRequest = null;
        }
    }

    performSearch(query) {
        // إظهار مؤشر التحميل
        this.showLoading();

        // إنشاء طلب البحث
        this.currentRequest = new XMLHttpRequest();
        this.currentRequest.open('GET', `/admin/profile-search?query=${encodeURIComponent(query)}`, true);

        this.currentRequest.onreadystatechange = () => {
            if (this.currentRequest.readyState === 4) {
                if (this.currentRequest.status === 200) {
                    try {
                        const response = JSON.parse(this.currentRequest.responseText);
                        this.handleSearchResponse(response);
                    } catch (e) {
                        console.error('خطأ في تحليل استجابة البحث:', e);
                        this.showError('خطأ في تحليل النتائج');
                    }
                } else {
                    this.showError('خطأ في البحث');
                }
                this.currentRequest = null;
            }
        };

        this.currentRequest.onerror = () => {
            this.showError('خطأ في الاتصال');
            this.currentRequest = null;
        };

        this.currentRequest.send();
    }

    handleSearchResponse(response) {
        if (response.success && response.data && response.data.length > 0) {
            this.showSuggestions(response.data);
        } else {
            this.showNoResults();
        }
    }

    showSuggestions(suggestions) {
        let html = '';

        suggestions.forEach((suggestion, index) => {
            const typeLabel = this.getTypeLabel(suggestion.type);

            html += `
                <div class="suggestion-item" data-index="${index}" data-url="${suggestion.url}">
                    <div class="suggestion-title">${this.escapeHtml(suggestion.title)}</div>
                    <div class="suggestion-subtitle">${this.escapeHtml(suggestion.subtitle)}</div>
                    <div class="suggestion-description">${this.escapeHtml(suggestion.description)}</div>
                    <div class="suggestion-type">${typeLabel}</div>
                </div>
            `;
        });

        this.suggestionsContainer.innerHTML = html;
        this.positionSuggestions();
        this.suggestionsContainer.style.display = 'block';
        this.suggestionsContainer.classList.add('visible');
        this.isVisible = true;

        // ربط أحداث النقر
        this.bindSuggestionEvents();
    }

    showLoading() {
        this.suggestionsContainer.innerHTML = `
            <div class="suggestion-loading">
                <div class="loading-text">جاري البحث...</div>
            </div>
        `;
        this.positionSuggestions();
        this.suggestionsContainer.style.display = 'block';
        this.suggestionsContainer.classList.add('visible');
        this.isVisible = true;
    }

    showNoResults() {
        this.suggestionsContainer.innerHTML = `
            <div class="suggestion-no-results">
                <div class="no-results-text">لا توجد نتائج</div>
            </div>
        `;
        this.positionSuggestions();
        this.suggestionsContainer.style.display = 'block';
        this.suggestionsContainer.classList.add('visible');
        this.isVisible = true;
    }

    showError(message) {
        this.suggestionsContainer.innerHTML = `
            <div class="suggestion-error">
                <div class="error-text">${message}</div>
            </div>
        `;
        this.positionSuggestions();
        this.suggestionsContainer.style.display = 'block';
        this.isVisible = true;
    }

    /**
     * حساب وتحديد موضع قائمة الاقتراحات
     */
    positionSuggestions() {
        const inputRect = this.input.getBoundingClientRect();
        const windowHeight = window.innerHeight;
        const windowWidth = window.innerWidth;

        // تحديد العرض حسب حجم الشاشة
        let suggestionsWidth;
        if (windowWidth <= 768) {
            // للشاشات الصغيرة
            suggestionsWidth = Math.min(windowWidth - 40, 350);
            this.suggestionsContainer.style.left = '20px';
            this.suggestionsContainer.style.right = '20px';
            this.suggestionsContainer.style.width = 'auto';
        } else {
            // للشاشات الكبيرة
            suggestionsWidth = Math.min(400, windowWidth - 40);
            this.suggestionsContainer.style.width = suggestionsWidth + 'px';
            this.suggestionsContainer.style.right = 'auto';

            // تحديد الموضع الأفقي
            let left = inputRect.left;
            if (left + suggestionsWidth > windowWidth - 20) {
                left = windowWidth - suggestionsWidth - 20;
            }
            if (left < 20) {
                left = 20;
            }
            this.suggestionsContainer.style.left = left + 'px';
        }

        // تحديد الموضع العمودي
        let top = inputRect.bottom + window.scrollY + 2;
        const suggestionsHeight = 350; // تقدير ارتفاع الاقتراحات

        // إذا لم تكن هناك مساحة كافية أسفل، اعرضها أعلى
        if (inputRect.bottom + suggestionsHeight > windowHeight && inputRect.top > suggestionsHeight) {
            top = inputRect.top + window.scrollY - suggestionsHeight - 2;
        }

        this.suggestionsContainer.style.top = top + 'px';
    }

    hideSuggestions() {
        this.suggestionsContainer.style.display = 'none';
        this.suggestionsContainer.classList.remove('visible');
        this.isVisible = false;
    }

    bindSuggestionEvents() {
        const items = this.suggestionsContainer.querySelectorAll('.suggestion-item');

        items.forEach(item => {
            item.addEventListener('click', () => {
                const url = item.getAttribute('data-url');
                if (url && url !== '#') {
                    window.location.href = url;
                }
            });

            item.addEventListener('mouseenter', () => {
                this.clearActiveItem();
                item.classList.add('active');
            });
        });
    }

    handleKeyNavigation(e) {
        if (!this.isVisible) return;

        const items = this.suggestionsContainer.querySelectorAll('.suggestion-item');
        if (items.length === 0) return;

        let activeIndex = -1;
        items.forEach((item, index) => {
            if (item.classList.contains('active')) {
                activeIndex = index;
            }
        });

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.clearActiveItem();
                const nextIndex = activeIndex < items.length - 1 ? activeIndex + 1 : 0;
                items[nextIndex].classList.add('active');
                break;

            case 'ArrowUp':
                e.preventDefault();
                this.clearActiveItem();
                const prevIndex = activeIndex > 0 ? activeIndex - 1 : items.length - 1;
                items[prevIndex].classList.add('active');
                break;

            case 'Enter':
                e.preventDefault();
                const activeItem = this.suggestionsContainer.querySelector('.suggestion-item.active');
                if (activeItem) {
                    const url = activeItem.getAttribute('data-url');
                    if (url && url !== '#') {
                        window.location.href = url;
                    }
                }
                break;

            case 'Escape':
                this.hideSuggestions();
                this.input.blur();
                break;
        }
    }

    clearActiveItem() {
        const activeItems = this.suggestionsContainer.querySelectorAll('.suggestion-item.active');
        activeItems.forEach(item => item.classList.remove('active'));
    }

    getTypeLabel(type) {
        const labels = {
            'main_record': 'سجل رئيسي',
            'family_member': 'فرد أسرة',
            'deceased': 'متوفي'
        };
        return labels[type] || 'غير محدد';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // تدمير المثيل
    destroy() {
        this.cancelPreviousRequest();
        if (this.suggestionsContainer && this.suggestionsContainer.parentNode) {
            this.suggestionsContainer.parentNode.removeChild(this.suggestionsContainer);
        }

        // إزالة معالجات الأحداث
        if (this.input) {
            this.input.removeEventListener('input', this.handleInput);
            this.input.removeEventListener('keydown', this.handleKeyNavigation);
            this.input.removeEventListener('focus', this.handleFocus);
        }
    }
}

// تهيئة البحث عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // البحث عن حقل search_profiles
    const searchInput = document.querySelector('input[name="search_profiles"]');

    if (searchInput) {
        // إنشاء مثيل البحث
        window.profileSearchSuggestions = new ProfileSearchSuggestions(
            'input[name="search_profiles"]',
            null // سيتم إنشاء الحاوي تلقائياً
        );
    }
});

// تصدير الكلاس للاستخدام الخارجي
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProfileSearchSuggestions;
}
