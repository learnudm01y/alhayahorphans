/**
 * File Path Fixer - إصلاح مسارات الملفات لاستخدام روابط الويب الصحيحة
 * يحول مسارات Windows المطلقة إلى روابط ويب صحيحة باستخدام Laravel
 */

class FilePathFixer {
    constructor() {
        this.baseUrl = window.location.origin;
        this.storageUrl = this.baseUrl + '/storage';
        this.adminRoutePrefix = '/admin';
    }

    /**
     * إصلاح مسار الملف وتحويله إلى رابط ويب
     * @param {string} filePath - المسار الأصلي للملف
     * @param {number} fileId - معرف الملف في قاعدة البيانات
     * @param {string} fileType - نوع الملف (image, document, etc.)
     * @returns {string} - الرابط الصحيح للملف
     */
    fixFilePath(filePath, fileId = null, fileType = 'image') {
        if (!filePath) {
            return null;
        }

        // إذا كان الملف يحتوي على مسار Windows مطلق
        if (filePath.includes('I:\\') || filePath.includes('C:\\') || filePath.includes('\\')) {
            // استخراج اسم المجلد والملف من المسار
            const pathParts = filePath.split('\\').filter(part => part.length > 0);

            // البحث عن مجلد duplicates أو temp
            const duplicatesIndex = pathParts.findIndex(part => part === 'duplicates');
            const tempIndex = pathParts.findIndex(part => part === 'temp');

            if (duplicatesIndex !== -1 && duplicatesIndex < pathParts.length - 1) {
                // بناء المسار النسبي من مجلد duplicates
                const relativePath = pathParts.slice(duplicatesIndex).join('/');
                return `${this.storageUrl}/temp/${relativePath}`;
            } else if (tempIndex !== -1 && tempIndex < pathParts.length - 1) {
                // بناء المسار النسبي من مجلد temp
                const relativePath = pathParts.slice(tempIndex + 1).join('/');
                return `${this.storageUrl}/temp/${relativePath}`;
            }
        }

        // إذا كان المسار يحتوي على /storage/ بالفعل
        if (filePath.includes('/storage/')) {
            return filePath.startsWith('http') ? filePath : this.baseUrl + filePath;
        }

        // إذا كان لدينا معرف الملف، استخدم route للعرض المباشر
        if (fileId && fileType === 'image') {
            return `${this.baseUrl}${this.adminRoutePrefix}/duplicate-files/serve/${fileId}`;
        }

        // في حالة عدم تمكن من التحديد، إرجاع null
        console.warn('تعذر إصلاح مسار الملف:', filePath);
        return null;
    }

    /**
     * إصلاح مسارات الملفات في مصفوفة من البيانات
     * @param {Array} files - مصفوفة الملفات
     * @param {string} pathField - اسم الحقل الذي يحتوي على المسار
     * @param {string} idField - اسم الحقل الذي يحتوي على معرف الملف
     * @returns {Array} - مصفوفة الملفات مع المسارات المُصلحة
     */
    fixFilePathsInArray(files, pathField = 'temp_path', idField = 'id') {
        if (!Array.isArray(files)) {
            return files;
        }

        return files.map(file => {
            if (file[pathField]) {
                const fileType = this.getFileTypeFromMime(file.mime_type);
                const fixedPath = this.fixFilePath(file[pathField], file[idField], fileType);

                if (fixedPath) {
                    file.web_url = fixedPath;
                    if (fileType === 'image') {
                        file.preview_url = fixedPath;
                    }
                }
            }
            return file;
        });
    }

    /**
     * تحديد نوع الملف من MIME type
     * @param {string} mimeType - نوع MIME
     * @returns {string} - نوع الملف
     */
    getFileTypeFromMime(mimeType) {
        if (!mimeType) return 'unknown';

        const mime = mimeType.toLowerCase();

        if (mime.startsWith('image/')) return 'image';
        if (mime.startsWith('video/')) return 'video';
        if (mime.startsWith('audio/')) return 'audio';
        if (mime.includes('pdf') || mime.includes('document') || mime.includes('word')) return 'document';

        return 'other';
    }

    /**
     * إصلاح مسارات الصور في عناصر DOM
     * @param {string} containerSelector - محدد الحاوية
     */
    fixImagePathsInDOM(containerSelector = 'body') {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const images = container.querySelectorAll('img[src*="I:\\"], img[src*="C:\\"]');

        images.forEach(img => {
            const originalSrc = img.src;
            const fileId = img.getAttribute('data-file-id');
            const fixedSrc = this.fixFilePath(originalSrc, fileId, 'image');

            if (fixedSrc) {
                img.src = fixedSrc;
                console.log('تم إصلاح مسار الصورة:', originalSrc, '→', fixedSrc);
            }
        });
    }

    /**
     * مراقبة التغييرات في DOM وإصلاح المسارات تلقائياً
     */
    enableAutoFix() {
        // إصلاح المسارات الموجودة
        this.fixImagePathsInDOM();

        // مراقبة إضافة عناصر جديدة
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // إصلاح الصور في العنصر الجديد
                            const images = node.querySelectorAll ?
                                node.querySelectorAll('img[src*="I:\\"], img[src*="C:\\"]') : [];

                            images.forEach(img => {
                                const originalSrc = img.src;
                                const fileId = img.getAttribute('data-file-id');
                                const fixedSrc = this.fixFilePath(originalSrc, fileId, 'image');

                                if (fixedSrc) {
                                    img.src = fixedSrc;
                                }
                            });
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('✅ تم تفعيل الإصلاح التلقائي لمسارات الملفات');
    }

    /**
     * إصلاح استجابة AJAX التي تحتوي على مسارات ملفات
     * @param {Object} responseData - بيانات الاستجابة
     * @returns {Object} - البيانات مع المسارات المُصلحة
     */
    fixAjaxResponse(responseData) {
        if (!responseData) return responseData;

        // إصلاح الملفات في حقل data.files
        if (responseData.data && responseData.data.files) {
            responseData.data.files = this.fixFilePathsInArray(responseData.data.files);
        }

        // إصلاح الملفات في الحقل الرئيسي
        if (Array.isArray(responseData.files)) {
            responseData.files = this.fixFilePathsInArray(responseData.files);
        }

        // إصلاح ملف واحد
        if (responseData.data && responseData.data.temp_path) {
            const fileType = this.getFileTypeFromMime(responseData.data.mime_type);
            const fixedPath = this.fixFilePath(
                responseData.data.temp_path,
                responseData.data.id,
                fileType
            );

            if (fixedPath) {
                responseData.data.web_url = fixedPath;
                if (fileType === 'image') {
                    responseData.data.preview_url = fixedPath;
                }
            }
        }

        return responseData;
    }
}

// إنشاء مثيل عام للاستخدام
window.FilePathFixer = new FilePathFixer();

// تفعيل الإصلاح التلقائي عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    window.FilePathFixer.enableAutoFix();
});

// تصدير للاستخدام في وحدات ES6
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FilePathFixer;
}
