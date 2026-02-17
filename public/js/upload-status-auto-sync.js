/**
 * ══════════════════════════════════════════════════════════════════════════
 * 📡 Upload Status Auto-Sync Service
 * ══════════════════════════════════════════════════════════════════════════
 *
 * المشكلة:
 * عندما يرفع FileSyncWorker ملفاً والتطبيق مغلق، IndexedDB لا يتحدث
 * العدادات تبقى قديمة حتى بعد العودة للتطبيق
 *
 * الحل:
 * 1. مراقبة window.fileUploadStatusUpdated events من Java
 * 2. sync تلقائي مع SQLite عند فتح الصفحة
 * 3. تحديث IndexedDB والعدادات فوراً
 *
 * الاستخدام:
 * <script src="/js/upload-status-auto-sync.js"></script>
 *
 * @version 1.0
 * @date 2026-02-16
 */

(function() {
    'use strict';

    const TAG = 'UploadAutoSync';
    let isInitialized = false;
    let syncInProgress = false;

    console.log(`🚀 ${TAG} - Loading...`);

    /**
     * تهيئة الـ Listener للتحديثات من Java
     */
    function initializeEventListeners() {
        if (isInitialized) {
            console.log(`ℹ️  ${TAG} - Already initialized`);
            return;
        }

        console.log(`🔧 ${TAG} - Initializing event listeners...`);

        // 1️⃣ الاستماع للتحديثات من UploadStatusBridge
        window.addEventListener('fileUploadStatusUpdated', (event) => {
            console.log(`📡 ${TAG} - Received upload status update:`, event.detail);

            const { fileId, status, pending, uploaded } = event.detail;

            // تحديث العدادات فوراً
            updateStatsDisplay(pending, uploaded);

            // تحديث UI للملف المحدد
            updateFileUIElement(fileId, status);

            // إعلام المستمعين الآخرين
            console.log(`✅ ${TAG} - Stats updated: ${pending} pending, ${uploaded} uploaded`);
        });

        // 2️⃣ الاستماع لتحديثات الإحصائيات العامة
        window.addEventListener('uploadStatsUpdated', (event) => {
            console.log(`📊 ${TAG} - Received stats update:`, event.detail);
            const { pending, uploaded } = event.detail;
            updateStatsDisplay(pending, uploaded);
        });

        isInitialized = true;
        console.log(`✅ ${TAG} - Event listeners initialized`);
    }

    /**
     * تحديث عناصر الإحصائيات في الصفحة
     */
    function updateStatsDisplay(pendingCount, uploadedCount) {
        // عناصر عداد الملفات المعلقة
        const pendingElements = [
            'stat-files',
            'statPending',
            'pending-files-count',
            'files-counter'
        ];

        pendingElements.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = pendingCount;
                // إضافة تأثير بصري
                el.classList.add('stats-updated');
                setTimeout(() => el.classList.remove('stats-updated'), 300);
            }
        });

        // عناصر عداد الملفات المرفوعة
        const uploadedElements = [
            'stat-uploaded',
            'statUploaded',
            'uploaded-files-count'
        ];

        uploadedElements.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = uploadedCount;
                el.classList.add('stats-updated');
                setTimeout(() => el.classList.remove('stats-updated'), 300);
            }
        });

        console.log(`🔄 ${TAG} - UI updated: ${pendingCount} pending, ${uploadedCount} uploaded`);
    }

    /**
     * تحديث عنصر UI للملف المحدد
     */
    function updateFileUIElement(fileId, status) {
        const fileElements = document.querySelectorAll(`[data-file-id="${fileId}"]`);

        if (fileElements.length === 0) {
            console.log(`ℹ️  ${TAG} - No UI element found for file ${fileId}`);
            return;
        }

        fileElements.forEach(el => {
            const statusSpan = el.querySelector('.file-status');
            if (statusSpan) {
                const statusText = status === 'completed' ? 'تم الرفع' :
                                 status === 'failed' ? 'فشل' :
                                 status === 'uploading' ? 'جارٍ الرفع' :
                                 'معلق';

                statusSpan.textContent = statusText;
                statusSpan.className = `file-status ${status}`;

                console.log(`✅ ${TAG} - Updated UI for file ${fileId}: ${statusText}`);
            }
        });
    }

    /**
     * مزامنة مع SQLite عند فتح الصفحة
     * للحصول على أحدث حالة من FileSyncWorker
     */
    async function syncWithNativeDatabase() {
        if (syncInProgress) {
            console.log(`⏳ ${TAG} - Sync already in progress, skipping...`);
            return;
        }

        syncInProgress = true;
        console.log(`🔄 ${TAG} - Syncing with native database...`);

        try {
            // استخدام SyncService إذا كان متاحاً
            if (typeof SyncService !== 'undefined' && SyncService.dbGetAll) {
                const allFiles = await SyncService.dbGetAll('files');

                const pending = allFiles.filter(f => !f.uploaded).length;
                const uploaded = allFiles.filter(f => f.uploaded).length;

                updateStatsDisplay(pending, uploaded);

                console.log(`✅ ${TAG} - Sync complete via SyncService`);
                console.log(`   📊 Stats: ${pending} pending, ${uploaded} uploaded`);
            }
            // Fallback إلى FileStorageDB
            else if (typeof FileStorageDB !== 'undefined') {
                const stats = await FileStorageDB.getStats();
                updateStatsDisplay(stats.pending, stats.uploaded || 0);

                console.log(`✅ ${TAG} - Sync complete via FileStorageDB`);
            }
            else {
                console.warn(`⚠️  ${TAG} - Neither SyncService nor FileStorageDB available`);
            }
        } catch (error) {
            console.error(`❌ ${TAG} - Sync failed:`, error);
        } finally {
            syncInProgress = false;
        }
    }

    /**
     * بدء التهيئة التلقائية
     */
    function autoInitialize() {
        console.log(`🎯 ${TAG} - Auto-initializing...`);

        // تهيئة Event Listeners
        initializeEventListeners();

        // مزامنة فورية مع قاعدة البيانات
        setTimeout(() => {
            syncWithNativeDatabase();
        }, 1000); // تأخير 1 ثانية للتأكد من جاهزية IndexedDB

        // مزامنة دورية كل 30 ثانية (احتياطي)
        setInterval(() => {
            console.log(`🔄 ${TAG} - Periodic sync...`);
            syncWithNativeDatabase();
        }, 30000); // كل 30 ثانية
    }

    // تهيئة تلقائية عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInitialize);
    } else {
        autoInitialize();
    }

    // إضافة CSS للتأثير البصري
    const style = document.createElement('style');
    style.textContent = `
        .stats-updated {
            animation: pulse 0.3s ease-in-out;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); color: #4CAF50; }
        }

        .file-status.completed {
            color: #4CAF50;
            font-weight: bold;
        }

        .file-status.failed {
            color: #f44336;
            font-weight: bold;
        }

        .file-status.uploading {
            color: #2196F3;
            font-weight: bold;
        }
    `;
    document.head.appendChild(style);

    console.log(`✅ ${TAG} - Loaded successfully`);

    // تصدير للاستخدام الخارجي
    window.UploadAutoSync = {
        sync: syncWithNativeDatabase,
        updateStats: updateStatsDisplay,
        updateFileUI: updateFileUIElement
    };
})();
