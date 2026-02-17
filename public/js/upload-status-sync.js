/**
 * ══════════════════════════════════════════════════════════════
 * 📡 Upload Status Real-Time Sync - JavaScript Side
 * ══════════════════════════════════════════════════════════════
 *
 * يستقبل أحداث تغيير حالة الرفع من FileSyncWorker (Java)
 * ويحدث واجهة المستخدم في الوقت الفعلي
 *
 * ✅ يعمل في: index.html, upload.html
 * 📡 يستقبل events من: UploadServicePlugin.notifyUploadStatusChanged()
 *
 * ══════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    console.log('📡 Upload Status Sync - Initializing...');

    // ═══════════════════════════════════════════════════════════
    // 🔧 Configuration
    // ═══════════════════════════════════════════════════════════

    const CONFIG = {
        AUTO_REFRESH_INTERVAL: 30000, // 30 seconds
        TOAST_DURATION: 3000,
        DEBUG: true // Set false في production
    };

    // ═══════════════════════════════════════════════════════════
    // 📊 Global State
    // ═══════════════════════════════════════════════════════════

    let uploadStats = {
        pending: 0,
        uploading: 0,
        completed: 0,
        failed: 0,
        total: 0
    };

    let isListenerRegistered = false;
    let autoRefreshTimer = null;

    // ═══════════════════════════════════════════════════════════
    // 🎯 Main Initialization
    // ═══════════════════════════════════════════════════════════

    async function init() {
        console.log('🚀 Starting Upload Status Sync initialization...');

        // Check if Capacitor is available
        if (typeof Capacitor === 'undefined') {
            console.warn('⚠️ Capacitor not loaded - Running in browser mode');
            console.warn('   Upload sync will NOT work in browser!');

            // في حالة المتصفح، حمّل الملفات من IndexedDB فقط
            await loadFilesFromIndexedDB();
            return;
        }

        // Check if UploadService plugin is available
        if (typeof UploadService === 'undefined') {
            console.error('❌ UploadService plugin not found!');
            console.error('   Make sure UploadServicePlugin is registered in MainActivity');

            // حمّل من IndexedDB حتى لو لم يكن Plugin متوفراً
            await loadFilesFromIndexedDB();
            return;
        }

        // Register event listener
        registerUploadStatusListener();

        // Initial stats fetch
        fetchAndUpdateStats();

        // 💾 تحميل الملفات من IndexedDB وعرضها
        await loadFilesFromIndexedDB();

        // Setup auto-refresh
        startAutoRefresh();

        console.log('✅ Upload Status Sync initialized successfully');
    }

    // ═══════════════════════════════════════════════════════════
    // 💾 Load Files from IndexedDB
    // ═══════════════════════════════════════════════════════════

    async function loadFilesFromIndexedDB() {
        try {
            // التحقق من وجود FileStorageDB
            if (typeof FileStorageDB === 'undefined') {
                if (CONFIG.DEBUG) {
                    console.log('ℹ️ FileStorageDB not loaded - skipping IndexedDB load');
                }
                return;
            }

            console.log('💾 Loading files from IndexedDB...');

            // جلب جميع الملفات
            const allFiles = await FileStorageDB.getAllFiles();

            if (allFiles.length === 0) {
                if (CONFIG.DEBUG) {
                    console.log('ℹ️ No files found in IndexedDB');
                }
                return;
            }

            console.log(`📦 Found ${allFiles.length} files in IndexedDB`);

            // تصفية الملفات المعلقة (غير المكتملة)
            const pendingFiles = allFiles.filter(file =>
                file.status === 'pending' || file.status === 'uploading'
            );

            if (CONFIG.DEBUG) {
                console.log(`⏳ Found ${pendingFiles.length} pending/uploading files`);
                console.log(`✅ Found ${allFiles.filter(f => f.status === 'completed').length} completed files`);
                console.log(`❌ Found ${allFiles.filter(f => f.status === 'failed').length} failed files`);
            }

            // عرض الملفات في الواجهة
            displayFilesInUI(allFiles);

            // تحديث الإحصائيات من IndexedDB
            const stats = await FileStorageDB.getStats();
            updateStatsInUI(stats);

            console.log('✅ Files loaded from IndexedDB successfully');

        } catch (error) {
            console.error('❌ Error loading files from IndexedDB:', error);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🎨 Display Files in UI
    // ═══════════════════════════════════════════════════════════

    function displayFilesInUI(files) {
        if (CONFIG.DEBUG) {
            console.log(`🎨 Displaying ${files.length} files in UI`);
        }

        // البحث عن container الملفات - دعم متعدد للأسماء المختلفة
        const filesContainer = document.getElementById('filesList') ||  // ✅ الاسم الحقيقي!
                              document.getElementById('files-container') ||
                              document.getElementById('uploads-list') ||
                              document.querySelector('[data-files-container]') ||
                              document.querySelector('.files-list');

        if (!filesContainer) {
            if (CONFIG.DEBUG) {
                console.warn('⚠️ Files container not found in DOM (#filesList, #files-container, #uploads-list)');
                console.warn('   Available elements:', document.querySelectorAll('[id*="file"], [class*="file"]'));
            }
            return;
        }

        if (CONFIG.DEBUG) {
            console.log('✅ Files container found:', filesContainer.id || filesContainer.className);
        }

        // فرز الملفات (الأحدث أولاً)
        files.sort((a, b) => {
            const dateA = new Date(a.updatedAt || a.createdAt);
            const dateB = new Date(b.updatedAt || b.createdAt);
            return dateB - dateA;
        });

        // مسح المحتوى القديم
        filesContainer.innerHTML = '';

        // عرض كل ملف
        files.forEach(file => {
            const fileCard = createFileCard(file);
            filesContainer.appendChild(fileCard);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // 🎴 Create File Card Element
    // ═══════════════════════════════════════════════════════════

    function createFileCard(fileData) {
        const card = document.createElement('div');
        // ✅ استخدام .file-item بدلاً من .file-card لتوافق HTML الموجود
        card.className = `file-item file-status-${fileData.status}`;
        card.setAttribute('data-file-id', fileData.id || fileData.fileId);  // ✅ دعم كل من id و fileId

        // أيقونة الحالة
        let statusIcon = '⏳';
        let statusText = 'في الانتظار';
        let statusClass = 'text-warning';

        if (fileData.status === 'completed') {
            statusIcon = '✅';
            statusText = 'تم الرفع';
            statusClass = 'text-success';
        } else if (fileData.status === 'failed') {
            statusIcon = '❌';
            statusText = 'فشل';
            statusClass = 'text-danger';
        } else if (fileData.status === 'uploading') {
            statusIcon = '⏳';
            statusText = 'جاري الرفع...';
            statusClass = 'text-info';
        }

        // ✅ بناء HTML متوافق مع الهيكل الموجود في التطبيق
        // استخدام نفس الكلاسات: file-item, file-icon, file-info, file-status
        const fileIcon = fileData.type && fileData.type.startsWith('image') ? 'image' : 'videocam';
        const association = fileData.metadata?.association || fileData.association || 'N/A';
        const person = fileData.metadata?.person || fileData.person || 'N/A';

        card.innerHTML = `
            <div class="file-icon">
                <span class="material-icons" data-icon="${fileIcon}" aria-label="${fileIcon}">${fileIcon}</span>
            </div>
            <div class="file-info">
                <div class="file-name">${fileData.fileName || `ملف #${fileData.id || fileData.fileId}`}</div>
                <div class="file-meta">${association} / ${person}</div>
            </div>
            <span class="file-status ${fileData.status}">${statusText}</span>
        `;

        return card;
    }

    // ═══════════════════════════════════════════════════════════
    // 🔧 Helper Functions
    // ═══════════════════════════════════════════════════════════

    function formatFileSize(bytes) {
        if (!bytes) return 'غير معروف';
        if (bytes < 1024) return bytes + ' بايت';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' كيلوبايت';
        return (bytes / 1024 / 1024).toFixed(2) + ' ميجابايت';
    }

    function formatDate(dateString) {
        if (!dateString) return 'غير معروف';
        const date = new Date(dateString);
        return date.toLocaleString('ar-EG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // ═══════════════════════════════════════════════════════════
    // 📡 Event Listener Registration
    // ═══════════════════════════════════════════════════════════

    function registerUploadStatusListener() {
        if (isListenerRegistered) {
            if (CONFIG.DEBUG) console.log('ℹ️ Listener already registered');
            return;
        }

        try {
            console.log('📡 Registering uploadStatusChanged event listener...');

            UploadService.addListener('uploadStatusChanged', (event) => {
                console.log('📡 ✅ Upload status changed event received:', event);
                console.log('   File ID:', event.fileId);
                console.log('   Status:', event.status);
                if (event.error) console.log('   Error:', event.error);

                // Handle the event
                handleUploadStatusChange(event);

                // Refresh stats
                fetchAndUpdateStats();

                // Show notification
                showUploadNotification(event);
            });

            isListenerRegistered = true;
            console.log('✅ Event listener registered successfully');

        } catch (error) {
            console.error('❌ Failed to register event listener:', error);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🔄 Event Handler
    // ═══════════════════════════════════════════════════════════

    async function handleUploadStatusChange(event) {
        const { fileId, status, error } = event;

        if (status === 'completed') {
            console.log(`✅ File #${fileId} uploaded successfully`);

            // 💾 تحديث IndexedDB
            await updateIndexedDBStatus(fileId, 'completed');

            // 🎨 تحديث الواجهة
            updateFileUIToSuccess(fileId);

            // 📊 إرسال event لتحديث الإحصائيات
            window.dispatchEvent(new CustomEvent('uploadStatusChanged', {
                detail: { fileId, status, error }
            }));

        } else if (status === 'failed') {
            console.error(`❌ File #${fileId} upload failed:`, error);

            // 💾 تحديث IndexedDB
            await updateIndexedDBStatus(fileId, 'failed', { error: error });

            // 🎨 تحديث الواجهة
            updateFileUIToError(fileId, error);

            // 📊 إرسال event لتحديث الإحصائيات
            window.dispatchEvent(new CustomEvent('uploadStatusChanged', {
                detail: { fileId, status, error }
            }));

        } else if (status === 'uploading') {
            console.log(`⏳ File #${fileId} is uploading...`);

            // 💾 تحديث IndexedDB
            await updateIndexedDBStatus(fileId, 'uploading');

            // 📊 إرسال event لتحديث الإحصائيات
            window.dispatchEvent(new CustomEvent('uploadStatusChanged', {
                detail: { fileId, status }
            }));

        } else if (status === 'pending') {
            console.log(`⏸️ File #${fileId} is pending...`);

            // 💾 تحديث IndexedDB
            await updateIndexedDBStatus(fileId, 'pending');

            // 📊 إرسال event لتحديث الإحصائيات
            window.dispatchEvent(new CustomEvent('uploadStatusChanged', {
                detail: { fileId, status }
            }));
        }

        // 📊 جلب الإحصائيات المحدثة
        fetchAndUpdateStats();
    }

    // ═══════════════════════════════════════════════════════════
    // 💾 Update IndexedDB Status
    // ═══════════════════════════════════════════════════════════

    async function updateIndexedDBStatus(fileId, status, additionalData = {}) {
        try {
            // التحقق من وجود FileStorageDB
            if (typeof FileStorageDB === 'undefined') {
                console.warn('⚠️ FileStorageDB not loaded - تخطي تحديث IndexedDB');
                console.warn('   تأكد من تحميل file-storage-indexeddb.js قبل upload-status-sync.js');
                return;
            }

            if (CONFIG.DEBUG) {
                console.log(`💾 تحديث IndexedDB للملف #${fileId}:`, status);
            }

            // تحديث الحالة في IndexedDB
            await FileStorageDB.updateFileStatus(fileId, status, additionalData);

            if (CONFIG.DEBUG) {
                console.log(`✅ تم تحديث IndexedDB للملف #${fileId} بنجاح`);
            }

        } catch (error) {
            console.error(`❌ فشل تحديث IndexedDB للملف #${fileId}:`, error);
            // لا نريد إيقاف تحديث الواجهة بسبب فشل IndexedDB
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 📊 Fetch and Update Stats
    // ═══════════════════════════════════════════════════════════

    async function fetchAndUpdateStats() {
        try {
            if (CONFIG.DEBUG) console.log('📊 Fetching upload stats...');

            // ✅ استخدام UploadService.getUploadStats() بدلاً من البحث المحلي
            let stats;

            if (typeof UploadService !== 'undefined') {
                // في التطبيق - جلب من Java
                const result = await UploadService.getUploadStats();
                stats = result;
                if (CONFIG.DEBUG) {
                    console.log('📡 Stats from Java plugin:', stats);
                }
            } else {
                // في المتصفح - جلب من IndexedDB
                if (typeof FileStorageDB !== 'undefined') {
                    stats = await FileStorageDB.getStats();
                } else {
                    console.warn('⚠️ No data source available for stats');
                    stats = {pending: 0, uploading: 0, completed: 0, failed: 0, total: 0};
                }
            }

            if (CONFIG.DEBUG) {
                console.log('📊 Stats received:', stats);
                console.log('   Pending:', stats.pending);
                console.log('   Uploading:', stats.uploading);
                console.log('   Completed:', stats.completed);
                console.log('   Failed:', stats.failed);
            }

            // Update global state
            uploadStats = stats;

            // Update UI
            updateStatsInUI(stats);

            // ✅ إرسال event مخصص للواجهة
            window.dispatchEvent(new CustomEvent('uploadStatsUpdated', { detail: stats }));

            return stats;

        } catch (error) {
            console.error('❌ Error fetching stats:', error);
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🎨 Update UI Elements
    // ═══════════════════════════════════════════════════════════

    function updateStatsInUI(stats) {
        if (CONFIG.DEBUG) console.log('🎨 Updating UI with stats:', stats);

        // Update pending files counter (common element)
        updatePendingFilesCounter(stats.pending);

        // Update full stats display (if exists)
        updateFullStatsDisplay(stats);

        // Update upload button badge
        updateUploadButtonBadge(stats.pending);

        // Update page-specific elements
        updatePageSpecificElements(stats);
    }

    // ═══════════════════════════════════════════════════════════
    // 🔢 Update Pending Files Counter
    // ═══════════════════════════════════════════════════════════

    function updatePendingFilesCounter(pendingCount) {
        // Try multiple possible element IDs
        const possibleIds = [
            'stat-files',
            'pending-files-count',
            'files-counter',
            'upload-counter'
        ];

        let updated = false;

        possibleIds.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                const oldValue = element.textContent;
                element.textContent = pendingCount;

                // Add flash animation
                if (oldValue !== pendingCount.toString()) {
                    element.classList.add('flash-update');
                    setTimeout(() => {
                        element.classList.remove('flash-update');
                    }, 1000);
                }

                updated = true;
                if (CONFIG.DEBUG) console.log(`✅ Updated #${id}: ${pendingCount}`);
            }
        });

        if (!updated && CONFIG.DEBUG) {
            console.warn('⚠️ No pending files counter element found');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 📈 Update Full Stats Display
    // ═══════════════════════════════════════════════════════════

    function updateFullStatsDisplay(stats) {
        const statsMapping = {
            'stat-pending': stats.pending,
            'stat-uploading': stats.uploading,
            'stat-completed': stats.completed,
            'stat-failed': stats.failed,
            'stat-total': stats.total
        };

        let found = false;

        Object.entries(statsMapping).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
                found = true;
            }
        });

        if (found && CONFIG.DEBUG) {
            console.log('✅ Full stats display updated');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🎒 Update Upload Button Badge
    // ═══════════════════════════════════════════════════════════

    function updateUploadButtonBadge(pendingCount) {
        const uploadBtn = document.getElementById('uploadBtn') ||
                         document.querySelector('[data-upload-btn]');

        if (!uploadBtn) return;

        let badge = uploadBtn.querySelector('.badge');

        if (pendingCount > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge badge-danger ms-2';
                uploadBtn.appendChild(badge);
            }
            badge.textContent = pendingCount;
            badge.style.display = 'inline-block';
        } else {
            if (badge) {
                badge.style.display = 'none';
            }
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 📄 Page-Specific Element Updates
    // ═══════════════════════════════════════════════════════════

    function updatePageSpecificElements(stats) {
        // Check current page
        const path = window.location.pathname;

        if (path.includes('upload.html') || path.includes('upload')) {
            updateUploadPageElements(stats);
        } else if (path.includes('index.html') || path === '/') {
            updateIndexPageElements(stats);
        }
    }

    function updateUploadPageElements(stats) {
        if (CONFIG.DEBUG) console.log('📄 Updating upload.html specific elements');

        // Update stats cards if they exist
        const statsCards = document.querySelectorAll('[data-stat-type]');
        statsCards.forEach(card => {
            const type = card.getAttribute('data-stat-type');
            if (stats[type] !== undefined) {
                const valueElement = card.querySelector('[data-stat-value]');
                if (valueElement) {
                    valueElement.textContent = stats[type];
                }
            }
        });
    }

    function updateIndexPageElements(stats) {
        if (CONFIG.DEBUG) console.log('📄 Updating index.html specific elements');

        // Can add index-specific updates here
    }

    // ═══════════════════════════════════════════════════════════
    // 🎨 Update Individual File UI
    // ═══════════════════════════════════════════════════════════

    function updateFileUIToSuccess(fileId) {
        const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);

        if (!fileCard) {
            if (CONFIG.DEBUG) console.warn(`⚠️ File card not found for ID: ${fileId}`);
            return;
        }

        // Remove old states
        fileCard.classList.remove('uploading', 'pending', 'error');
        fileCard.classList.add('success');

        // Update status text
        const statusElement = fileCard.querySelector('.upload-status') ||
                             fileCard.querySelector('.status') ||
                             fileCard.querySelector('[data-status]');

        if (statusElement) {
            statusElement.innerHTML = `
                <span class="text-success">
                    <i class="fas fa-check-circle"></i>
                    تم الرفع بنجاح
                </span>
            `;
        }

        // Update progress bar
        const progressBar = fileCard.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = '100%';
            progressBar.classList.remove('bg-primary', 'bg-warning');
            progressBar.classList.add('bg-success');
        }

        // Add success animation
        fileCard.style.animation = 'pulse-success 0.5s';

        // Auto-remove after 3 seconds
        setTimeout(() => {
            fileCard.style.transition = 'opacity 0.5s, transform 0.5s';
            fileCard.style.opacity = '0';
            fileCard.style.transform = 'translateX(50px)';

            setTimeout(() => {
                fileCard.remove();
                if (CONFIG.DEBUG) console.log(`🗑️ File card #${fileId} removed from DOM`);
            }, 500);
        }, 3000);

        console.log(`✅ Updated file #${fileId} UI to success`);
    }

    function updateFileUIToError(fileId, errorMessage) {
        const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);

        if (!fileCard) {
            if (CONFIG.DEBUG) console.warn(`⚠️ File card not found for ID: ${fileId}`);
            return;
        }

        // Remove old states
        fileCard.classList.remove('uploading', 'pending', 'success');
        fileCard.classList.add('error');

        // Update status text
        const statusElement = fileCard.querySelector('.upload-status') ||
                             fileCard.querySelector('.status') ||
                             fileCard.querySelector('[data-status]');

        if (statusElement) {
            statusElement.innerHTML = `
                <span class="text-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    فشل: ${errorMessage || 'خطأ غير معروف'}
                </span>
            `;
        }

        // Update progress bar
        const progressBar = fileCard.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.classList.remove('bg-primary', 'bg-success');
            progressBar.classList.add('bg-danger');
        }

        // Add shake animation
        fileCard.style.animation = 'shake 0.5s';

        console.error(`❌ Updated file #${fileId} UI to error`);
    }

    // ═══════════════════════════════════════════════════════════
    // 🔔 Show Notification/Toast
    // ═══════════════════════════════════════════════════════════

    function showUploadNotification(event) {
        const { fileId, status, error } = event;

        if (status === 'completed') {
            showToast('✅ تم رفع الملف بنجاح', 'success');
        } else if (status === 'failed') {
            showToast(`❌ فشل رفع الملف: ${error || 'خطأ غير معروف'}`, 'error');
        }
    }

    function showToast(message, type = 'info') {
        console.log(`🔔 Toast: ${message} (${type})`);

        // Try Toastify
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: message,
                duration: CONFIG.TOAST_DURATION,
                gravity: "top",
                position: "right",
                stopOnFocus: true,
                style: {
                    background: type === 'success' ? '#28a745' :
                               type === 'error' ? '#dc3545' : '#007bff',
                }
            }).showToast();
            return;
        }

        // Try SweetAlert2
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: CONFIG.TOAST_DURATION,
                timerProgressBar: true,
            });
            return;
        }

        // Fallback: Simple HTML toast
        const toast = document.createElement('div');
        toast.className = `upload-toast upload-toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            font-size: 14px;
            max-width: 300px;
            animation: slideInRight 0.3s ease-out;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }, CONFIG.TOAST_DURATION);
    }

    // ═══════════════════════════════════════════════════════════
    // 🔄 Auto Refresh
    // ═══════════════════════════════════════════════════════════

    function startAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
        }

        autoRefreshTimer = setInterval(() => {
            if (CONFIG.DEBUG) console.log('🔄 Auto-refreshing stats...');
            fetchAndUpdateStats();
        }, CONFIG.AUTO_REFRESH_INTERVAL);

        console.log(`✅ Auto-refresh started (every ${CONFIG.AUTO_REFRESH_INTERVAL/1000}s)`);
    }

    function stopAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
            console.log('⏹️ Auto-refresh stopped');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🌍 Global API Exposure
    // ═══════════════════════════════════════════════════════════

    window.UploadStatusSync = {
        // Stats & Refresh
        fetchStats: fetchAndUpdateStats,
        startAutoRefresh: startAutoRefresh,
        stopAutoRefresh: stopAutoRefresh,
        getStats: () => uploadStats,
        isActive: () => isListenerRegistered,

        // UI Updates
        showToast: showToast,
        displayFilesInUI: displayFilesInUI,
        loadFilesFromIndexedDB: loadFilesFromIndexedDB,

        // Manual Updates
        updateFileStatus: updateIndexedDBStatus,
        updateFileUISuccess: updateFileUIToSuccess,
        updateFileUIError: updateFileUIToError
    };

    // ═══════════════════════════════════════════════════════════
    // 🚀 Auto-Init on Document Ready
    // ═══════════════════════════════════════════════════════════

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    console.log('📡 Upload Status Sync script loaded');

})();

// ═══════════════════════════════════════════════════════════
// 🎨 CSS Animations (auto-inject)
// ═══════════════════════════════════════════════════════════

(function injectCSS() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes flash {
            0%, 100% { background-color: transparent; }
            50% { background-color: #ffc107; }
        }

        .flash-update {
            animation: flash 0.5s;
        }

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

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .file-card.success {
            border-left: 4px solid #28a745;
        }

        .file-card.error {
            border-left: 4px solid #dc3545;
        }

        .upload-toast {
            animation: slideInRight 0.3s ease-out;
        }

        /* 🎴 File Card Styles */
        .file-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .file-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .file-card-pending {
            border-left: 4px solid #ffc107;
        }

        .file-card-uploading {
            border-left: 4px solid #17a2b8;
        }

        .file-card-completed {
            border-left: 4px solid #28a745;
            opacity: 0.8;
        }

        .file-card-failed {
            border-left: 4px solid #dc3545;
        }

        .file-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .file-icon {
            font-size: 32px;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 4px;
        }

        .file-size {
            font-size: 0.85rem;
            color: #666;
        }

        .file-status {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 12px;
            background: #f8f9fa;
        }

        .file-metadata {
            font-size: 0.8rem;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
        }

        .file-error {
            padding: 8px;
            background: #ffe6e6;
            border-radius: 4px;
            margin-top: 8px;
        }

        .progress {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            transition: width 0.3s ease;
        }

        #files-container:empty::before {
            content: "لا توجد ملفات محفوظة";
            display: block;
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.1rem;
        }
    `;
    document.head.appendChild(style);
})();
