/**
 * ══════════════════════════════════════════════════════════════
 * 📊 Auto Stats Updater - للاستماع لتحديثات الإحصائيات
 * ══════════════════════════════════════════════════════════════
 *
 * يستمع تلقائياً لـ uploadStatsUpdated و uploadStatusChanged
 * ويحدث جميع عناصر الإحصائيات في الصفحة
 *
 * ✅ يعمل في أي صفحة تحتوي على:
 *    - #stat-files
 *    - #stat-pending
 *    - #stat-uploading
 *    - #stat-completed
 *    - #stat-failed
 *    - #stat-total
 *
 * استخدام:
 *   فقط قم بتحميل هذا السكريبت في صفحتك! سيعمل تلقائياً
 * ══════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    console.log('📊 Auto Stats Updater - Initializing...');

    // ═══════════════════════════════════════════════════════════
    // 🔧 Configuration
    // ═══════════════════════════════════════════════════════════

    const CONFIG = {
        AUTO_FETCH_INTERVAL: 5000, // 5 seconds
        DEBUG: true
    };

    let autoFetchTimer = null;

    // ═══════════════════════════════════════════════════════════
    // 📊 Update Stats in Any Page
    // ═══════════════════════════════════════════════════════════

    function updateStatsElements(stats) {
        const mappings = [
            // ملفات معلقة (العنصر الرئيسي)
            { ids: ['stat-files', 'pending-files-count', 'files-counter'], value: stats.pending },

            // إحصائيات تفصيلية
            { ids: ['stat-pending'], value: stats.pending },
            { ids: ['stat-uploading'], value: stats.uploading },
            { ids: ['stat-completed'], value: stats.completed },
            { ids: ['stat-failed'], value: stats.failed },
            { ids: ['stat-total'], value: stats.total }
        ];

        let updated = false;

        mappings.forEach(mapping => {
            mapping.ids.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    const oldValue = element.textContent;
                    const newValue = String(mapping.value);

                    if (oldValue !== newValue) {
                        element.textContent = newValue;

                        // إضافة animation flash
                        element.classList.add('stats-flash');
                        setTimeout(() => {
                            element.classList.remove('stats-flash');
                        }, 600);

                        if (CONFIG.DEBUG) {
                            console.log(`✅ Updated #${id}: ${oldValue} → ${newValue}`);
                        }
                        updated = true;
                    }
                }
            });
        });

        if (!updated && CONFIG.DEBUG) {
            console.log('ℹ️ No stat elements found in this page');
        }

        return updated;
    }

    // ═══════════════════════════════════════════════════════════
    // 🎧 Event Listeners
    // ═══════════════════════════════════════════════════════════

    // Listen to upload stats updates
    window.addEventListener('uploadStatsUpdated', (event) => {
        if (CONFIG.DEBUG) {
            console.log('📡 uploadStatsUpdated event received:', event.detail);
        }
        updateStatsElements(event.detail);
    });

    // Listen to individual file status changes
    window.addEventListener('uploadStatusChanged', (event) => {
        if (CONFIG.DEBUG) {
            console.log('📡 uploadStatusChanged event received:', event.detail);
        }

        // عند تغيير حالة ملف، اجلب الإحصائيات الجديدة
        fetchStatsNow();
    });

    // ═══════════════════════════════════════════════════════════
    // 📡 Fetch Stats from Source
    // ═══════════════════════════════════════════════════════════

    async function fetchStatsNow() {
        try {
            let stats;

            // Try UploadService first (Capacitor plugin)
            if (typeof UploadService !== 'undefined') {
                stats = await UploadService.getUploadStats();
                if (CONFIG.DEBUG) {
                    console.log('📊 Stats from UploadService:', stats);
                }
            }
            // Fallback to FileStorageDB (IndexedDB)
            else if (typeof FileStorageDB !== 'undefined') {
                stats = await FileStorageDB.getStats();
                if (CONFIG.DEBUG) {
                    console.log('📊 Stats from FileStorageDB:', stats);
                }
            }
            // Fallback to default
            else {
                stats = {
                    pending: 0,
                    uploading: 0,
                    completed: 0,
                    failed: 0,
                    total: 0
                };
                if (CONFIG.DEBUG) {
                    console.warn('⚠️ No stats source available - using defaults');
                }
            }

            updateStatsElements(stats);

        } catch (error) {
            console.error('❌ Error fetching stats:', error);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🔄 Auto Fetch (Polling)
    // ═══════════════════════════════════════════════════════════

    function startAutoFetch() {
        if (autoFetchTimer) {
            clearInterval(autoFetchTimer);
        }

        autoFetchTimer = setInterval(() => {
            fetchStatsNow();
        }, CONFIG.AUTO_FETCH_INTERVAL);

        console.log(`✅ Auto-fetch started (every ${CONFIG.AUTO_FETCH_INTERVAL/1000}s)`);
    }

    function stopAutoFetch() {
        if (autoFetchTimer) {
            clearInterval(autoFetchTimer);
            autoFetchTimer = null;
            console.log('⏹️ Auto-fetch stopped');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🌍 Global API
    // ═══════════════════════════════════════════════════════════

    window.AutoStatsUpdater = {
        fetchNow: fetchStatsNow,
        startAutoFetch: startAutoFetch,
        stopAutoFetch: stopAutoFetch
    };

    // ═══════════════════════════════════════════════════════════
    // 🚀 Auto-Init
    // ═══════════════════════════════════════════════════════════

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        console.log('🚀 Auto Stats Updater - Initializing...');

        // Initial fetch
        fetchStatsNow();

        // Start auto-fetch
        startAutoFetch();

        console.log('✅ Auto Stats Updater - Ready');
    }

    // ═══════════════════════════════════════════════════════════
    // 🎨 Inject CSS Animation
    // ═══════════════════════════════════════════════════════════

    (function injectCSS() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes stats-flash {
                0%, 100% {
                    background-color: transparent;
                    transform: scale(1);
                }
                50% {
                    background-color: #ffc107;
                    transform: scale(1.1);
                    box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
                }
            }

            .stats-flash {
                animation: stats-flash 0.6s ease-in-out;
                display: inline-block;
                padding: 2px 6px;
                border-radius: 4px;
            }
        `;
        document.head.appendChild(style);
    })();

    console.log('📊 Auto Stats Updater script loaded');

})();
