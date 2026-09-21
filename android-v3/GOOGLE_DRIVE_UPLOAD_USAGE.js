// ════════════════════════════════════════════════════════════════════
// 📤 مثال استخدام GoogleDriveUploadPlugin في upload.html
// ════════════════════════════════════════════════════════════════════

/**
 * تفعيل رفع الملفات إلى Google Drive في الخلفية
 * يُربط مع الزر في upload.html
 */
async function uploadToGoogleDrive() {
    console.log('🔄 بدء رفع الملفات إلى Google Drive...');

    try {
        // استدعاء Plugin الجديد
        const result = await GoogleDriveUpload.startBackgroundUpload();

        console.log('✅ نتيجة تفعيل الرفع:', result);

        if (result.success) {
            if (result.pendingFiles === 0) {
                alert('✅ لا توجد ملفات معلقة للرفع');
            } else {
                alert(`✅ تم تفعيل خدمة الرفع في الخلفية\n\n` +
                      `📊 عدد الملفات المعلقة: ${result.pendingFiles}\n\n` +
                      `📤 الرفع سيستمر حتى عند إغلاق التطبيق`);
            }
        } else {
            alert('❌ فشل تفعيل خدمة الرفع: ' + result.message);
        }

    } catch (error) {
        console.error('❌ خطأ في تفعيل الرفع:', error);
        alert('❌ حدث خطأ: ' + error.message);
    }
}

/**
 * الحصول على حالة الرفع الحالية
 */
async function getUploadStatus() {
    try {
        const status = await GoogleDriveUpload.getUploadStatus();

        console.log('📊 حالة الرفع:', status);

        if (status.success) {
            const message = `📊 إحصائيات الرفع:\n\n` +
                          `✅ مرفوع: ${status.uploadedFiles}\n` +
                          `⏳ معلق: ${status.pendingFiles}\n` +
                          `❌ فشل: ${status.failedFiles}\n` +
                          `📦 المجموع: ${status.totalFiles}`;

            alert(message);
            return status;
        }

    } catch (error) {
        console.error('❌ خطأ في الحصول على الحالة:', error);
    }
}

/**
 * إعادة محاولة رفع الملفات الفاشلة
 */
async function retryFailedUploads() {
    console.log('🔄 إعادة محاولة رفع الملفات الفاشلة...');

    try {
        const result = await GoogleDriveUpload.retryFailedUploads();

        console.log('✅ نتيجة إعادة المحاولة:', result);

        if (result.success) {
            if (result.retriedFiles === 0) {
                alert('✅ لا توجد ملفات فاشلة لإعادة محاولتها');
            } else {
                alert(`✅ تم إعادة محاولة ${result.retriedFiles} ملف\n\n` +
                      `📤 الرفع جاري في الخلفية...`);
            }
        }

    } catch (error) {
        console.error('❌ خطأ في إعادة المحاولة:', error);
        alert('❌ حدث خطأ: ' + error.message);
    }
}

/**
 * عرض زر حالة الرفع في الواجهة
 */
function updateUploadButtonUI() {
    const uploadBtn = document.getElementById('uploadBtn');

    if (!uploadBtn) return;

    // الحصول على الحالة وتحديث واجهة الزر
    GoogleDriveUpload.getUploadStatus()
        .then(status => {
            if (status.success && status.pendingFiles > 0) {
                // توجد ملفات معلقة - إظهار badge
                uploadBtn.innerHTML = `
                    <span class="material-icons" data-icon="cloud_upload" aria-label="cloud_upload">cloud_upload</span>
                    رفع الملفات إلى Google Drive
                    <span class="badge">${status.pendingFiles}</span>
                `;
            }
        })
        .catch(err => console.warn('تعذر الحصول على حالة الرفع:', err));
}

// تحديث واجهة الزر عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', updateUploadButtonUI);

// ════════════════════════════════════════════════════════════════════
// 🎯 كيفية الاستخدام في upload.html
// ════════════════════════════════════════════════════════════════════

/*
1. الزر الحالي في upload.html:

<button class="action-btn google" id="uploadBtn" onclick="uploadToGoogleDrive()">
    <span class="material-icons" data-icon="cloud_upload" aria-label="cloud_upload">cloud_upload</span>
    رفع الملفات إلى Google Drive
</button>

2. أضف زر إعادة محاولة الفاشلة:

<button class="action-btn retry" onclick="retryFailedUploads()">
    <span class="material-icons">refresh</span>
    إعادة محاولة الفاشلة
</button>

3. أضف زر عرض الحالة:

<button class="action-btn status" onclick="getUploadStatus()">
    <span class="material-icons">info</span>
    عرض الحالة
</button>

4. أو دمج كل شيء في قائمة منسدلة:

<div class="dropdown">
    <button class="action-btn google" id="uploadBtn">
        <span class="material-icons">cloud_upload</span>
        Google Drive
    </button>
    <div class="dropdown-menu">
        <a href="#" onclick="uploadToGoogleDrive()">رفع الملفات</a>
        <a href="#" onclick="getUploadStatus()">عرض الحالة</a>
        <a href="#" onclick="retryFailedUploads()">إعادة محاولة الفاشلة</a>
    </div>
</div>
*/
