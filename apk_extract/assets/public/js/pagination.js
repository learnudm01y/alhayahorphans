/**
 * ========================================
 * مكون Pagination قابل لإعادة الاستخدام
 * ========================================
 *
 * يوفر نظام pagination مع دعم:
 * - تقسيم البيانات إلى صفحات
 * - التنقل بين الصفحات
 * - تغيير حجم الصفحة
 * - عرض معلومات الصفحة الحالية
 */

class Pagination {
    /**
     * @param {Object} options - خيارات التهيئة
     * @param {Array} options.data - البيانات الكاملة
     * @param {number} options.pageSize - عدد العناصر في الصفحة الواحدة (افتراضي: 20)
     * @param {Function} options.onPageChange - دالة يتم استدعاؤها عند تغيير الصفحة
     * @param {string} options.containerId - معرف العنصر الحاوي للـ pagination
     * @param {Array} options.pageSizeOptions - خيارات حجم الصفحة (افتراضي: [10, 20, 30, 50, 100])
     */
    constructor(options) {
        this.allData = options.data || [];
        this.pageSize = options.pageSize || 20;
        this.currentPage = 1;
        this.onPageChange = options.onPageChange || (() => {});
        this.containerId = options.containerId;
        this.pageSizeOptions = options.pageSizeOptions || [10, 20, 30, 50, 100];

        this.render();
    }

    /**
     * تحديث البيانات
     */
    updateData(newData) {
        this.allData = newData || [];
        this.currentPage = 1; // العودة للصفحة الأولى
        this.render();
    }

    /**
     * الحصول على إجمالي عدد الصفحات
     */
    get totalPages() {
        return Math.ceil(this.allData.length / this.pageSize);
    }

    /**
     * الحصول على بيانات الصفحة الحالية
     */
    getCurrentPageData() {
        const start = (this.currentPage - 1) * this.pageSize;
        const end = start + this.pageSize;
        return this.allData.slice(start, end);
    }

    /**
     * الانتقال إلى صفحة معينة
     */
    goToPage(pageNumber) {
        if (pageNumber < 1 || pageNumber > this.totalPages) {
            return;
        }

        this.currentPage = pageNumber;
        this.render();
        this.onPageChange(this.getCurrentPageData(), this.currentPage);
    }

    /**
     * الانتقال للصفحة السابقة
     */
    previousPage() {
        this.goToPage(this.currentPage - 1);
    }

    /**
     * الانتقال للصفحة التالية
     */
    nextPage() {
        this.goToPage(this.currentPage + 1);
    }

    /**
     * تغيير حجم الصفحة
     */
    changePageSize(newSize) {
        this.pageSize = parseInt(newSize);
        this.currentPage = 1; // العودة للصفحة الأولى
        this.render();
        this.onPageChange(this.getCurrentPageData(), this.currentPage);
    }

    /**
     * الحصول على أرقام الصفحات المراد عرضها
     */
    getPageNumbers() {
        const pages = [];
        const total = this.totalPages;
        const current = this.currentPage;

        if (total <= 7) {
            // عرض كل الصفحات إذا كانت 7 أو أقل
            for (let i = 1; i <= total; i++) {
                pages.push(i);
            }
        } else {
            // عرض ذكي للصفحات
            pages.push(1); // الصفحة الأولى دائماً

            if (current > 3) {
                pages.push('...'); // فاصل
            }

            // الصفحات المحيطة بالصفحة الحالية
            for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
                if (!pages.includes(i)) {
                    pages.push(i);
                }
            }

            if (current < total - 2) {
                pages.push('...'); // فاصل
            }

            if (total > 1) {
                pages.push(total); // الصفحة الأخيرة دائماً
            }
        }

        return pages;
    }

    /**
     * عرض واجهة Pagination
     */
    render() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error(`Pagination container not found: ${this.containerId}`);
            return;
        }

        // إذا لم توجد بيانات، لا نعرض شيء
        if (this.allData.length === 0) {
            container.innerHTML = '';
            return;
        }

        const total = this.totalPages;
        const current = this.currentPage;
        const start = (current - 1) * this.pageSize + 1;
        const end = Math.min(current * this.pageSize, this.allData.length);

        const pageNumbers = this.getPageNumbers();

        container.innerHTML = `
            <div class="pagination-container">
                <!-- معلومات الصفحة -->
                <div class="pagination-info">
                    عرض ${start} - ${end} من أصل ${this.allData.length}
                </div>

                <!-- أزرار التنقل -->
                <div class="pagination-controls">
                    <!-- زر السابق -->
                    <button class="pagination-btn prev"
                            onclick="window['${this.containerId}_pagination'].previousPage()"
                            ${current === 1 ? 'disabled' : ''}>
                        <span class="material-icons">chevron_right</span>
                        السابق
                    </button>

                    <!-- أرقام الصفحات -->
                    ${pageNumbers.map(page => {
                        if (page === '...') {
                            return '<span class="pagination-separator">...</span>';
                        }
                        return `
                            <button class="pagination-btn ${page === current ? 'active' : ''}"
                                    onclick="window['${this.containerId}_pagination'].goToPage(${page})"
                                    ${page === current ? 'disabled' : ''}>
                                ${page}
                            </button>
                        `;
                    }).join('')}

                    <!-- زر التالي -->
                    <button class="pagination-btn next"
                            onclick="window['${this.containerId}_pagination'].nextPage()"
                            ${current === total ? 'disabled' : ''}>
                        التالي
                        <span class="material-icons">chevron_left</span>
                    </button>
                </div>

                <!-- اختيار حجم الصفحة -->
                <div class="page-size-selector">
                    <label>عدد العناصر في الصفحة:</label>
                    <select onchange="window['${this.containerId}_pagination'].changePageSize(this.value)">
                        ${this.pageSizeOptions.map(size =>
                            `<option value="${size}" ${size === this.pageSize ? 'selected' : ''}>${size}</option>`
                        ).join('')}
                    </select>
                </div>
            </div>
        `;

        // حفظ مرجع للكائن في window للوصول إليه من الأزرار
        window[`${this.containerId}_pagination`] = this;
    }

    /**
     * تدمير المكون وتنظيف الذاكرة
     */
    destroy() {
        const container = document.getElementById(this.containerId);
        if (container) {
            container.innerHTML = '';
        }

        // حذف المرجع من window
        if (window[`${this.containerId}_pagination`]) {
            delete window[`${this.containerId}_pagination`];
        }
    }
}

// تصدير للاستخدام العام
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Pagination;
}
