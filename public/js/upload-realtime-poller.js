/**
 * ══════════════════════════════════════════════════════════════════════════
 * 🔄 Upload Real-Time Poller - قوة إجبارية للتزامن!
 * ══════════════════════════════════════════════════════════════════════════
 *
 * المشكلة الأصلية:
 * - العداد (#stat-files) لا يتحدث بعد رفع الملفات
 * - حالة الملف تبقى "معلق" بدلاً من "تم الرفع"
 * - Java events لا تصل دائماً
 *
 * الحل - 3 طبقات من التزامن:
 * ✅ Layer 1: Direct JavaScript injection من Java (UploadStatusBridge)
 * ✅ Layer 2: Capacitor events (uploadStatusChanged)
 * ✅ Layer 3: Aggressive polling كـ FALLBACK (هذا الملف!)
 *
 * كيف يعمل:
 * - يفحص IndexedDB كل ثانية
 * - يحدث العداد والقوائم تلقائياً
 * - يعمل حتى لو فشلت Layers 1 & 2
 *
 * @version 4.0 - NUCLEAR FALLBACK
 * @date 2026-02-15
 */

(function() {
    'use strict';

    console.log('');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('🔄 Upload Real-Time Poller - NUCLEAR FALLBACK MODE');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

    // ════════════════════════════════════════════════════════════════════
    // 🔧 Configuration - يمكن تغييرها حسب الحاجة
    // ════════════════════════════════════════════════════════════════════

    const CONFIG = {
        POLL_INTERVAL: 2000,           // فحص كل 2 ثانية (aggressive!)
        FAST_POLL_INTERVAL: 500,       // فحص سريع أثناء الرفع النشط
        FLASH_DURATION: 300,           // مدة flash animation
        DEBUG: true                    // طباعة logs
    };

    let previousStats = null;
    let pollTimer = null;
    let isFastPolling = false;

    // ════════════════════════════════════════════════════════════════════
    // 🎨 CSS Animation للتأثير البصري
    // ════════════════════════════════════════════════════════════════════

    function injectCSS() {
        if (document.getElementById('upload-poller-styles')) return;

        const style = document.createElement('style');
        style.id = 'upload-poller-styles';
        style.textContent = `
            @keyframes stats-flash-green {
                0%, 100% { background-color: transparent; transform: scale(1); }
                50% { background-color: #4CAF50; color: white; transform: scale(1.15); }
            }
            @keyframes stats-flash-red {
                0%, 100% { background-color: transparent; transform: scale(1); }
                50% { background-color: #f44336; color: white; transform: scale(1.15); }
            }
            .stats-flash-green {
                animation: stats-flash-green ${CONFIG.FLASH_DURATION}ms ease-in-out;
            }
            .stats-flash-red {
                animation: stats-flash-red ${CONFIG.FLASH_DURATION}ms ease-in-out;
            }
            .file-status.completed {
                background: #4CAF50 !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 12px;
                font-weight: bold;
            }
            .file-status.pending {
                background: #FF9800 !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 12px;
            }
            .file-status.failed {
                background: #f44336 !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 12px;
            }
        `;
        document.head.appendChild(style);
        console.log('✅ CSS injected');
    }

    // ════════════════════════════════════════════════════════════════════
    // 📊 Core Function: Poll Stats من IndexedDB
    // ════════════════════════════════════════════════════════════════════

    async function pollStats() {
        try {
            // فحص وجود FileStorageDB
            if (typeof FileStorageDB === 'undefined') {
                if (CONFIG.DEBUG) {
                    console.warn('⚠️ FileStorageDB not available - waiting...');
                }
                return;
            }

            // ⚡ جلب الإحصائيات من IndexedDB
            const stats = await FileStorageDB.getStats();

            if (CONFIG.DEBUG) {
                console.log('📊 Poll result:', stats);
            }

            // ═══════════════════════════════════════════════════════════
            // 1️⃣ تحديث العداد (#stat-files)
            // ═══════════════════════════════════════════════════════════

            updateStatElements(stats);

            // ═══════════════════════════════════════════════════════════
            // 2️⃣ تحديث قائمة الملفات (file-status)
            // ═══════════════════════════════════════════════════════════

            await updateFileList();

            // ═══════════════════════════════════════════════════════════
            // 3️⃣ تحديث سرعة Polling حسب النشاط
            // ═══════════════════════════════════════════════════════════

            adjustPollingSpeed(stats);

            // ═══════════════════════════════════════════════════════════
            // 4️⃣ حفظ الحالة السابقة
            // ═══════════════════════════════════════════════════════════

            previousStats = stats;

        } catch (error) {
            console.error('❌ Poll error:', error);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // 🎯 Update UI Elements
    // ════════════════════════════════════════════════════════════════════

    function updateStatElements(stats) {
        const statElementIds = [
            'stat-files',
            'pending-files-count',
            'files-counter',
            'stat-pending'
        ];

        statElementIds.forEach(id => {
            const element = document.getElementById(id);
            if (!element) return;

            const oldValue = parseInt(element.textContent) || 0;
            const newValue = stats.pending;

            if (oldValue !== newValue) {
                element.textContent = newValue;

                // Flash animation
                const flashClass = newValue < oldValue ? 'stats-flash-green' : 'stats-flash-red';
                element.classList.add(flashClass);
                setTimeout(() => element.classList.remove(flashClass), CONFIG.FLASH_DURATION);

                console.log(`✅ Updated #${id}: ${oldValue} → ${newValue}`);

                // Dispatch event للـ listeners الأخرى
                window.dispatchEvent(new CustomEvent('uploadStatsUpdated', {
                    detail: stats
                }));
            }
        });

        // Update other stat types
        updateStatElement('stat-uploading', stats.uploading);
        updateStatElement('stat-completed', stats.completed);
        updateStatElement('stat-failed', stats.failed);
        updateStatElement('stat-total', stats.total);
    }

    function updateStatElement(id, value) {
        const element = document.getElementById(id);
        if (element && element.textContent !== String(value)) {
            element.textContent = value;
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // 📋 Update File List UI
    // ════════════════════════════════════════════════════════════════════

    async function updateFileList() {
        try {
            if (typeof FileStorageDB === 'undefined') return;

            const allFiles = await FileStorageDB.getAllFiles();

            allFiles.forEach(file => {
                // البحث عن الملف في DOM باستخدام data-file-id
                const fileElements = document.querySelectorAll(`[data-file-id="${file.id}"]`);

                fileElements.forEach(elem => {
                    const statusSpan = elem.querySelector('.file-status');
                    if (!statusSpan) return;

                    const currentText = statusSpan.textContent.trim();
                    let newText = '';
                    let newClass = '';

                    switch(file.status) {
                        case 'completed':
                            newText = 'تم الرفع';
                            newClass = 'file-status completed';
                            break;
                        case 'failed':
                            newText = 'فشل';
                            newClass = 'file-status failed';
                            break;
                        case 'uploading':
                            newText = 'جاري الرفع';
                            newClass = 'file-status uploading';
                            break;
                        default:
                            newText = 'معلق';
                            newClass = 'file-status pending';
                    }

                    if (currentText !== newText) {
                        statusSpan.textContent = newText;
                        statusSpan.className = newClass;

                        console.log(`✅ Updated file #${file.id} status: ${currentText} → ${newText}`);

                        // Dispatch event للملف المحدد
                        window.dispatchEvent(new CustomEvent('fileStatusUpdated', {
                            detail: { fileId: file.id, status: file.status }
                        }));
                    }
                });
            });

        } catch (error) {
            console.error('❌ Failed to update file list:', error);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // ⚡ Adjust Polling Speed
    // ════════════════════════════════════════════════════════════════════

    function adjustPollingSpeed(stats) {
        const hasActiveUploads = stats.uploading > 0 || stats.pending > 0;

        if (hasActiveUploads && !isFastPolling) {
            // Switch to FAST polling
            console.log('⚡ Switching to FAST polling (active uploads detected)');
            isFastPolling = true;
            restartPolling();
        } else if (!hasActiveUploads && isFastPolling) {
            // Switch back to NORMAL polling
            console.log('🐌 Switching to NORMAL polling (no active uploads)');
            isFastPolling = false;
            restartPolling();
        }
    }

    function restartPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        const interval = isFastPolling ? CONFIG.FAST_POLL_INTERVAL : CONFIG.POLL_INTERVAL;
        pollTimer = setInterval(pollStats, interval);
    }

    // ════════════════════════════════════════════════════════════════════
    // 🚀 Initialize Poller
    // ════════════════════════════════════════════════════════════════════

    function init() {
        console.log('🚀 Initializing Upload Real-Time Poller...');

        // Inject CSS
        injectCSS();

        // Poll فوري
        pollStats();

        // Start polling interval
        const interval = CONFIG.POLL_INTERVAL;
        pollTimer = setInterval(pollStats, interval);

        console.log(`✅ Poller started - polling every ${interval}ms`);
        console.log('✅ Will automatically switch to fast mode (500ms) during active uploads');
        console.log('');

        // Listen for manual triggers
        window.addEventListener('uploadStatusChanged', (event) => {
            console.log('📡 Received uploadStatusChanged event - triggering immediate poll');
            pollStats();
        });

        window.addEventListener('uploadStatsUpdated', (event) => {
            if (CONFIG.DEBUG) {
                console.log('📡 Received uploadStatsUpdated event:', event.detail);
            }
        });
    }

    // ════════════════════════════════════════════════════════════════════
    // 🏁 Auto-start
    // ════════════════════════════════════════════════════════════════════

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ════════════════════════════════════════════════════════════════════
    // 🌐 Export للاستخدام الخارجي
    // ════════════════════════════════════════════════════════════════════

    window.UploadRealtimePoller = {
        pollNow: pollStats,
        enableFastMode: () => {
            isFastPolling = true;
            restartPolling();
        },
        disableFastMode: () => {
            isFastPolling = false;
            restartPolling();
        },
        getStats: () => previousStats
    };

    console.log('✅ UploadRealtimePoller ready - window.UploadRealtimePoller available');
    console.log('');

})();
