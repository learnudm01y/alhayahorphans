/**
 * ===============================================
 * نظام مزامنة البيانات مع Laravel API
 * ===============================================
 * يستخدم BackgroundSyncPlugin لمزامنة بيانات IndexedDB مع السيرفر
 *
 * الاستخدام:
 * 1. استدعِ DataSyncManager.init() في بداية التطبيق
 * 2. استدعِ DataSyncManager.queueDataForSync() بعد حفظ أي بيانات في IndexedDB
 */

class DataSyncManager {

    /**
     * تهيئة نظام المزامنة
     */
    static async init() {
        console.log('🔄 Initializing DataSyncManager...');

        // التحقق من وجود BackgroundSync plugin
        if (!window.BackgroundSync) {
            console.error('❌ BackgroundSync plugin not found!');
            console.error('   Make sure BackgroundSyncPlugin is registered in MainActivity.java');
            return false;
        }

        console.log('✅ BackgroundSync plugin found');
        console.log('   Available methods:', Object.keys(window.BackgroundSync));

        // الحصول على الحالة الأولية
        try {
            const status = await window.BackgroundSync.getSyncStatus();
            console.log('📊 Initial sync status:', status);
            console.log(`   Pending: ${status.pending}`);
            console.log(`   Uploaded: ${status.uploaded}`);
            console.log(`   Failed: ${status.failed}`);

            // إذا كانت هناك بيانات منتظرة، ابدأ المزامنة
            if (status.pending > 0) {
                console.log(`🚀 Starting sync for ${status.pending} pending items...`);
                await window.BackgroundSync.startService();
            }

        } catch (error) {
            console.error('❌ Error getting sync status:', error);
        }

        return true;
    }

    /**
     * إضافة بيانات لقائمة المزامنة
     *
     * @param {string} dataType - نوع البيانات (sponsorship, payment, orphan...)
     * @param {object} data - البيانات (JavaScript object)
     * @param {string} endpoint - API endpoint (مثال: /api/mobile/sponsorships/sync)
     * @returns {Promise<object>} - نتيجة الإضافة
     */
    static async queueDataForSync(dataType, data, endpoint) {
        console.log('📤 Queueing data for sync...');
        console.log('   Type:', dataType);
        console.log('   Endpoint:', endpoint);
        console.log('   Data:', data);

        if (!window.BackgroundSync) {
            console.error('❌ BackgroundSync plugin not available');
            return { success: false, error: 'Plugin not available' };
        }

        try {
            // إضافة للقائمة
            const result = await window.BackgroundSync.addDataToQueue({
                dataType: dataType,
                dataJson: JSON.stringify(data),
                endpoint: endpoint
            });

            console.log('✅ Data queued successfully:', result);
            console.log('   Queue ID:', result.id);

            // بدء المزامنة تلقائياً
            console.log('🚀 Starting sync service...');
            await window.BackgroundSync.startService();

            return result;

        } catch (error) {
            console.error('❌ Failed to queue data:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * الحصول على إحصائيات المزامنة
     */
    static async getStatus() {
        if (!window.BackgroundSync) {
            return null;
        }

        try {
            const status = await window.BackgroundSync.getSyncStatus();
            return status;
        } catch (error) {
            console.error('❌ Error getting status:', error);
            return null;
        }
    }

    /**
     * إعادة محاولة البيانات الفاشلة
     */
    static async retryFailed() {
        if (!window.BackgroundSync) {
            console.error('❌ BackgroundSync not available');
            return;
        }

        try {
            const result = await window.BackgroundSync.retryFailedData();
            console.log(`✅ Retried ${result.retried} failed items`);

            // بدء المزامنة
            await window.BackgroundSync.startService();

            return result;
        } catch (error) {
            console.error('❌ Error retrying:', error);
        }
    }

    /**
     * حذف البيانات المكتملة
     */
    static async clearCompleted() {
        if (!window.BackgroundSync) {
            console.error('❌ BackgroundSync not available');
            return;
        }

        try {
            const result = await window.BackgroundSync.clearCompletedData();
            console.log(`🗑️ Cleared ${result.cleared} completed items`);
            return result;
        } catch (error) {
            console.error('❌ Error clearing:', error);
        }
    }
}

// ===============================================
// دمج مع sync-service.js الموجود
// ===============================================

/**
 * استخدام في sync-service.js عند حفظ البيانات
 */
async function saveLocalChangeWithSync(sponsorshipId, updates) {
    console.log('💾 saveLocalChangeWithSync - sponsorshipId:', sponsorshipId);
    console.log('💾 saveLocalChangeWithSync - updates:', updates);

    // 1. حفظ في IndexedDB كالمعتاد
    await saveLocalChange(sponsorshipId, updates);

    // 2. إضافة لقائمة المزامنة مع السيرفر
    try {
        const sponsorshipData = await getLocalSponsorship(sponsorshipId);

        await DataSyncManager.queueDataForSync(
            'sponsorship',
            sponsorshipData,
            '/api/mobile/sync/upload'
        );

        console.log('✅ Sponsorship queued for server sync');

    } catch (error) {
        console.error('❌ Failed to queue for sync:', error);
    }
}

/**
 * استخدام في photo-handler.js عند رفع صورة
 */
async function uploadPhotoWithSync(sponsorshipId, photoFile) {
    console.log('📸 uploadPhotoWithSync - sponsorshipId:', sponsorshipId);

    // 1. حفظ الصورة في IndexedDB
    const photoId = await savePhotoToIndexedDB(sponsorshipId, photoFile);

    // 2. رفع الملف (نظام الملفات)
    await UploadService.addFileToQueue({
        file: photoFile,
        photoId: photoId,
        // ... باقي المعاملات
    });

    // 3. إضافة بيانات الصورة لقائمة المزامنة (نظام البيانات)
    try {
        await DataSyncManager.queueDataForSync(
            'photo_metadata',
            {
                sponsorship_id: sponsorshipId,
                photo_id: photoId,
                uploaded_at: new Date().toISOString()
            },
            '/api/mobile/sync/photos/metadata'
        );

        console.log('✅ Photo metadata queued for sync');

    } catch (error) {
        console.error('❌ Failed to queue photo metadata:', error);
    }
}

// ===============================================
// تهيئة تلقائية عند تحميل الصفحة
// ===============================================
document.addEventListener('deviceready', async () => {
    console.log('📱 Device ready - initializing DataSyncManager...');

    const initialized = await DataSyncManager.init();

    if (initialized) {
        console.log('✅ DataSyncManager ready');

        // عرض الإحصائيات كل 10 ثواني
        setInterval(async () => {
            const status = await DataSyncManager.getStatus();
            if (status && (status.pending > 0 || status.failed > 0)) {
                console.log('📊 Sync status:', status);
            }
        }, 10000);
    }
});

// ===============================================
// Exports للاستخدام في ملفات أخرى
// ===============================================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DataSyncManager;
}
