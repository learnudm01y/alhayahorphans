// ════════════════════════════════════════════════════════════════════
// 📡 JavaScript Integration for Upload Status Events
// ════════════════════════════════════════════════════════════════════
//
// هذا الكود يجب إضافته في التطبيق (JavaScript/Vue/React)
// لاستقبال events من FileSyncWorker وتحديث الواجهة في الوقت الفعلي
//
// ════════════════════════════════════════════════════════════════════

import { UploadService } from '@capacitor-community/upload-service';

// ════════════════════════════════════════════════════════════════════
// 🎯 1. إضافة Event Listener عند تحميل التطبيق
// ════════════════════════════════════════════════════════════════════

/**
 * ✨ تفعيل مراقبة أحداث الرفع في الوقت الفعلي
 * يجب استدعاء هذه الدالة عند تحميل التطبيق
 */
function initializeUploadSyncMonitoring() {
    console.log('🔌 Initializing upload sync event listener...');

    // 📡 الاستماع لأحداث تغيير حالة الرفع من FileSyncWorker
    UploadService.addListener('uploadStatusChanged', (event) => {
        console.log('📡 ✅ Upload status changed event received:', event);
        /*
         * Event structure:
         * {
         *   fileId: number,
         *   status: "completed" | "failed",
         *   error?: string
         * }
         */

        // تحديث واجهة المستخدم بناءً على الحدث
        handleUploadStatusChange(event);

        // تحديث الإحصائيات (عدد الملفات المعلقة)
        updateUploadStatistics();

        // إظهار إشعار للمستخدم
        showUploadNotification(event);
    });

    console.log('✅ Upload sync event listener registered successfully');
}

// ════════════════════════════════════════════════════════════════════
// 🔄 2. معالجة حدث تغيير حالة الرفع
// ════════════════════════════════════════════════════════════════════

/**
 * معالجة تغيير حالة ملف معين
 */
function handleUploadStatusChange(event) {
    const { fileId, status, error } = event;

    console.log(`📋 Processing file #${fileId}: ${status}`);

    if (status === 'completed') {
        // ✅ الرفع نجح
        console.log(`✅ File #${fileId} uploaded successfully`);

        // تحديث UI للملف الخاص
        updateFileUI(fileId, 'success');

        // إزالة الملف من قائمة المعلقة إن كان معروضاً
        removeFileFromPendingList(fileId);

    } else if (status === 'failed') {
        // ❌ الرفع فشل بعد 3 محاولات
        console.error(`❌ File #${fileId} upload failed:`, error);

        // تحديث UI للملف بحالة خطأ
        updateFileUI(fileId, 'error', error);

        // عرض رسالة خطأ
        showErrorForFile(fileId, error);
    }
}

// ════════════════════════════════════════════════════════════════════
// 📊 3. تحديث إحصائيات الرفع (عدد الملفات المعلقة)
// ════════════════════════════════════════════════════════════════════

/**
 * ✨ جلب إحصائيات الرفع وتحديث واجهة العداد
 */
async function updateUploadStatistics() {
    try {
        console.log('📊 Fetching upload statistics...');

        // استدعاء الـ Plugin للحصول على الإحصائيات من SQLite
        const stats = await UploadService.getUploadStats();

        console.log('📊 Upload stats:', stats);
        /*
         * Response structure:
         * {
         *   pending: number,
         *   uploading: number,
         *   completed: number,
         *   failed: number,
         *   total: number
         * }
         */

        // تحديث عداد الملفات المعلقة في الواجهة
        updatePendingFilesCounter(stats.pending);

        // تحديث الإحصائيات الكاملة إن كان هناك عنصر لها
        updateFullStatsDisplay(stats);

        return stats;

    } catch (error) {
        console.error('❌ Error fetching upload stats:', error);
        return null;
    }
}

/**
 * تحديث عداد الملفات المعلقة في الواجهة
 * (مثال: badge أو رقم في الإحصائيات)
 */
function updatePendingFilesCounter(pendingCount) {
    console.log(`🔢 Updating pending files counter: ${pendingCount}`);

    // ✨ مثال 1: تحديث badge في زر الرفع
    const uploadButton = document.getElementById('uploadBtn');
    if (uploadButton) {
        const badge = uploadButton.querySelector('.badge');
        if (badge) {
            badge.textContent = pendingCount;
            badge.style.display = pendingCount > 0 ? 'inline-block' : 'none';
        } else if (pendingCount > 0) {
            // إنشاء badge جديد
            const newBadge = document.createElement('span');
            newBadge.className = 'badge badge-danger';
            newBadge.textContent = pendingCount;
            uploadButton.appendChild(newBadge);
        }
    }

    // ✨ مثال 2: تحديث عنصر في الإحصائيات
    const statFilesElement = document.getElementById('stat-files');
    if (statFilesElement) {
        statFilesElement.textContent = pendingCount;

        // تأثير بصري عند التغيير
        statFilesElement.classList.add('flash-update');
        setTimeout(() => {
            statFilesElement.classList.remove('flash-update');
        }, 1000);
    }

    // ✨ مثال 3: تحديث في Vue/React component (إن كان موجوداً)
    if (window.app && window.app.uploadStats) {
        window.app.uploadStats.pending = pendingCount;
    }
}

/**
 * تحديث عرض الإحصائيات الكاملة
 */
function updateFullStatsDisplay(stats) {
    // تحديث جميع عناصر الإحصائيات
    const statsElements = {
        'stat-pending': stats.pending,
        'stat-uploading': stats.uploading,
        'stat-completed': stats.completed,
        'stat-failed': stats.failed,
        'stat-total': stats.total
    };

    Object.entries(statsElements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });
}

// ════════════════════════════════════════════════════════════════════
// 🎨 4. تحديث واجهة المستخدم للملف الخاص
// ════════════════════════════════════════════════════════════════════

/**
 * تحديث UI للملف بعد تغيير حالته
 */
function updateFileUI(fileId, status, errorMessage = null) {
    console.log(`🎨 Updating UI for file #${fileId}: ${status}`);

    // البحث عن عنصر الملف في الواجهة
    const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);

    if (!fileCard) {
        console.warn(`⚠️ File card not found for ID: ${fileId}`);
        return;
    }

    // إزالة جميع حالات السابقة
    fileCard.classList.remove('uploading', 'success', 'error', 'pending');

    if (status === 'success') {
        // ✅ حالة النجاح
        fileCard.classList.add('success');

        const statusElement = fileCard.querySelector('.upload-status');
        if (statusElement) {
            statusElement.innerHTML = `
                <span class="text-success">
                    <i class="fas fa-check-circle"></i>
                    تم الرفع بنجاح
                </span>
            `;
        }

        // إخفاء شريط التقدم
        const progressBar = fileCard.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = '100%';
            progressBar.classList.add('bg-success');
            setTimeout(() => {
                progressBar.closest('.progress')?.remove();
            }, 2000);
        }

        // إضافة تأثير بصري
        fileCard.style.animation = 'pulse-success 0.5s';

    } else if (status === 'error') {
        // ❌ حالة الخطأ
        fileCard.classList.add('error');

        const statusElement = fileCard.querySelector('.upload-status');
        if (statusElement) {
            statusElement.innerHTML = `
                <span class="text-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    فشل الرفع: ${errorMessage || 'خطأ غير معروف'}
                </span>
            `;
        }

        // تلوين شريط التقدم بالأحمر
        const progressBar = fileCard.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.classList.remove('bg-primary');
            progressBar.classList.add('bg-danger');
        }

        // إضافة تأثير اهتزاز
        fileCard.style.animation = 'shake 0.5s';
    }
}

/**
 * إزالة الملف من قائمة المعلقة
 */
function removeFileFromPendingList(fileId) {
    const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);

    if (fileCard && fileCard.closest('.pending-files-section')) {
        // تأثير Fade Out ثم إزالة
        fileCard.style.transition = 'opacity 0.5s, transform 0.5s';
        fileCard.style.opacity = '0';
        fileCard.style.transform = 'translateX(50px)';

        setTimeout(() => {
            fileCard.remove();
        }, 500);
    }
}

// ════════════════════════════════════════════════════════════════════
// 🔔 5. إظهار إشعارات للمستخدم
// ════════════════════════════════════════════════════════════════════

/**
 * إظهار إشعار للمستخدم بنتيجة الرفع
 */
function showUploadNotification(event) {
    const { fileId, status, error } = event;

    if (status === 'completed') {
        // إشعار نجاح
        showToast('✅ تم رفع الملف بنجاح', 'success');

    } else if (status === 'failed') {
        // إشعار خطأ
        showToast(`❌ فشل رفع الملف: ${error || 'خطأ غير معروف'}`, 'error');
    }
}

/**
 * إظهار رسالة Toast (يمكن استخدام مكتبة مثل Toastify)
 */
function showToast(message, type = 'info') {
    console.log(`🔔 Toast: ${message} (${type})`);

    // ✨ مثال باستخدام Toastify (إن كان موجوداً)
    if (typeof Toastify !== 'undefined') {
        Toastify({
            text: message,
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: type === 'success' ? '#28a745' :
                             type === 'error' ? '#dc3545' : '#007bff',
        }).showToast();
    }

    // ✨ مثال باستخدام SweetAlert2 (إن كان موجوداً)
    else if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    }

    // ✨ Fallback: عرض بسيط
    else {
        alert(message);
    }
}

/**
 * عرض خطأ خاص بملف معين
 */
function showErrorForFile(fileId, errorMessage) {
    console.error(`❌ File #${fileId} error: ${errorMessage}`);

    // يمكن فتح modal أو عرض تفاصيل الخطأ
}

// ════════════════════════════════════════════════════════════════════
// 📋 6. جلب قائمة الملفات حسب الحالة
// ════════════════════════════════════════════════════════════════════

/**
 * جلب قائمة الملفات المعلقة من SQLite
 */
async function getPendingFiles() {
    try {
        const result = await UploadService.getFilesByStatus({
            status: 'pending'
        });

        console.log('📋 Pending files:', result);
        /*
         * Response structure:
         * {
         *   files: [
         *     {
         *       id: number,
         *       fileName: string,
         *       fileType: string,
         *       photoId: number,
         *       status: string,
         *       retryCount: number,
         *       errorMessage: string,
         *       createdAt: string
         *     },
         *     ...
         *   ],
         *   count: number
         * }
         */

        return result.files;

    } catch (error) {
        console.error('❌ Error fetching pending files:', error);
        return [];
    }
}

/**
 * جلب قائمة الملفات الفاشلة
 */
async function getFailedFiles() {
    try {
        const result = await UploadService.getFilesByStatus({
            status: 'failed'
        });

        return result.files;

    } catch (error) {
        console.error('❌ Error fetching failed files:', error);
        return [];
    }
}

/**
 * عرض قائمة الملفات المعلقة في الواجهة
 */
async function displayPendingFilesList() {
    const files = await getPendingFiles();

    const container = document.getElementById('pending-files-list');
    if (!container) return;

    container.innerHTML = '';

    if (files.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">لا توجد ملفات معلقة</p>';
        return;
    }

    files.forEach(file => {
        const fileCard = createFileCard(file);
        container.appendChild(fileCard);
    });
}

/**
 * إنشاء بطاقة عرض لملف
 */
function createFileCard(file) {
    const card = document.createElement('div');
    card.className = 'file-card card mb-2';
    card.setAttribute('data-file-id', file.id);

    card.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${file.fileName}</strong>
                    <small class="text-muted d-block">${file.fileType}</small>
                </div>
                <div class="upload-status">
                    ${file.status === 'pending' ? '<span class="badge badge-warning">معلق</span>' : ''}
                    ${file.status === 'uploading' ? '<span class="badge badge-info">جاري الرفع...</span>' : ''}
                    ${file.status === 'failed' ? '<span class="badge badge-danger">فشل</span>' : ''}
                </div>
            </div>
            ${file.errorMessage ? `<small class="text-danger">${file.errorMessage}</small>` : ''}
        </div>
    `;

    return card;
}

// ════════════════════════════════════════════════════════════════════
// 🚀 7. التفعيل عند تحميل الصفحة
// ════════════════════════════════════════════════════════════════════

/**
 * ✨ يجب استدعاء هذا عند تحميل التطبيق
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('📱 App loaded - initializing upload sync...');

    // تفعيل مراقبة الأحداث
    initializeUploadSyncMonitoring();

    // تحديث الإحصائيات الأولية
    updateUploadStatistics();

    // عرض قائمة الملفات المعلقة
    displayPendingFilesList();

    console.log('✅ Upload sync initialized successfully');
});

// ════════════════════════════════════════════════════════════════════
// 📦 Export for module systems
// ════════════════════════════════════════════════════════════════════

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeUploadSyncMonitoring,
        updateUploadStatistics,
        getPendingFiles,
        getFailedFiles,
        displayPendingFilesList,
    };
}

// ════════════════════════════════════════════════════════════════════
// 🎨 CSS للتأثيرات البصرية
// ════════════════════════════════════════════════════════════════════

/*
أضف هذا CSS في ملف الأنماط:

@keyframes pulse-success {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 rgba(40, 167, 69, 0);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.flash-update {
    animation: flash 0.5s;
}

@keyframes flash {
    0%, 100% { background-color: transparent; }
    50% { background-color: #ffc107; }
}

.file-card.success {
    border-left: 4px solid #28a745;
}

.file-card.error {
    border-left: 4px solid #dc3545;
}

.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 10px;
}
*/
