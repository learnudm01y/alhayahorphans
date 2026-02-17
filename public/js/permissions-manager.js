/**
 * 🔐 نظام طلب الصلاحيات المتسلسل
 *
 * يقوم بعرض جميع الصلاحيات المطلوبة بشكل متسلسل عند تسجيل الدخول
 * لضمان حصول التطبيق على كل الصلاحيات اللازمة لعمل FileSyncWorker
 * ومنع مشكلة ForegroundServiceStartNotAllowedException
 *
 * الاستخدام:
 * <script src="/js/permissions-manager.js"></script>
 * <script>
 *   // عند تسجيل الدخول أو عند بدء التطبيق
 *   PermissionsManager.requestAllPermissions();
 * </script>
 *
 * v1.0 - فبراير 2026
 */

(function() {
    'use strict';

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 📋 تعريف الصلاحيات المطلوبة
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    const PERMISSIONS = [
        {
            id: 'notifications',
            title: '🔔 الإشعارات',
            description: 'لإظهار تنبيهات عند رفع الملفات ونقل البيانات بنجاح',
            reason: 'حتى تعرف فوراً عندما يتم رفع الفيديوهات والملفات بنجاح',
            required: true
        },
        {
            id: 'camera',
            title: '📷 الكاميرا',
            description: 'لتصوير الفيديوهات والصور للأيتام والمكفولين',
            reason: 'ضروري لتوثيق الزيارات وتصوير الأيتام',
            required: true
        },
        {
            id: 'audio',
            title: '🎤 الصوت',
            description: 'لتسجيل الصوت مع الفيديوهات',
            reason: 'حتى تتمكن من تسجيل فيديوهات بالصوت',
            required: true
        },
        {
            id: 'storage',
            title: '💾 التخزين',
            description: 'للوصول إلى الملفات والفيديوهات المحفوظة على الجهاز',
            reason: 'لحفظ الفيديوهات ورفعها للسيرفر عند توفر الإنترنت',
            required: true
        },
        {
            id: 'batteryOptimization',
            title: '🔋 تحسين البطارية',
            description: 'لضمان رفع الملفات حتى مع إغلاق الشاشة',
            reason: 'حتى لا يتوقف رفع الملفات عند إغلاق التطبيق أو إقفال الشاشة',
            required: true
        }
    ];

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🎨 أنماط CSS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    const CSS = `
        .permissions-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
            padding: 20px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .permissions-modal {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.4s ease-out;
            color: white;
            direction: rtl;
            text-align: right;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .permissions-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .permissions-icon {
            font-size: 60px;
            margin-bottom: 15px;
            animation: bounce 1s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .permissions-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .permissions-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .permission-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin: 15px 0;
            transition: all 0.3s ease;
        }

        .permission-card.active {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .permission-card.granted {
            background: rgba(76, 175, 80, 0.3);
            border-color: rgba(76, 175, 80, 0.6);
        }

        .permission-card.denied {
            background: rgba(244, 67, 54, 0.3);
            border-color: rgba(244, 67, 54, 0.6);
        }

        .permission-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .permission-title-text {
            font-size: 18px;
            font-weight: bold;
            flex: 1;
        }

        .permission-status {
            font-size: 24px;
        }

        .permission-description {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .permission-reason {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-style: italic;
            border-left: 3px solid rgba(255, 255, 255, 0.5);
        }

        .permissions-progress {
            text-align: center;
            margin: 20px 0;
        }

        .progress-bar-container {
            background: rgba(255, 255, 255, 0.2);
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-bar {
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }

        .progress-text {
            font-size: 14px;
            font-weight: bold;
            margin-top: 5px;
        }

        .permissions-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-permission {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn-grant {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
        }

        .btn-grant:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
        }

        .btn-grant:active {
            transform: translateY(0);
        }

        .btn-skip {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-skip:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .btn-complete {
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.4);
        }

        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.6);
        }

        .permissions-summary {
            text-align: center;
            padding: 20px;
        }

        .summary-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .summary-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .summary-text {
            font-size: 15px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    `;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 📦 المتغيرات العامة
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    let currentIndex = 0;
    let permissionsStatus = {};
    let onCompleteCallback = null;
    let overlayElement = null;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🏗️ إنشاء الواجهة
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    function createUI() {
        console.log('🎨 Creating Permissions UI...');

        // إضافة CSS
        if (!document.getElementById('permissions-styles')) {
            const styleElement = document.createElement('style');
            styleElement.id = 'permissions-styles';
            styleElement.textContent = CSS;
            document.head.appendChild(styleElement);
        }

        // إنشاء div أساسي
        overlayElement = document.createElement('div');
        overlayElement.className = 'permissions-overlay';
        overlayElement.innerHTML = `
            <div class="permissions-modal">
                <div class="permissions-header">
                    <div class="permissions-icon">🔐</div>
                    <h2 class="permissions-title">صلاحيات التطبيق</h2>
                    <p class="permissions-subtitle">نحتاج موافقتك على بعض الصلاحيات لضمان عمل التطبيق بشكل صحيح</p>
                </div>

                <div class="permissions-progress">
                    <div class="progress-bar-container">
                        <div class="progress-bar" id="permission-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="progress-text" id="permission-progress-text">0 من ${PERMISSIONS.length}</div>
                </div>

                <div id="permissions-list"></div>

                <div class="permissions-actions" id="permissions-actions">
                    <button class="btn-permission btn-grant" id="btn-grant">
                        ✅ منح الصلاحية
                    </button>
                    <button class="btn-permission btn-skip" id="btn-skip" style="display: none;">
                        ⏭️ تخطي
                    </button>
                </div>

                <div id="permissions-summary" style="display: none;"></div>
            </div>
        `;

        document.body.appendChild(overlayElement);

        // إنشاء بطاقات الصلاحيات
        renderPermissionCards();

        // ربط الأزرار
        document.getElementById('btn-grant').onclick = handleGrantClick;
        document.getElementById('btn-skip').onclick = handleSkipClick;

        // تحديث الواجهة
        updateUI();
    }

    function renderPermissionCards() {
        const listElement = document.getElementById('permissions-list');
        listElement.innerHTML = '';

        PERMISSIONS.forEach((permission, index) => {
            const card = document.createElement('div');
            card.className = 'permission-card';
            card.id = `permission-card-${permission.id}`;
            card.innerHTML = `
                <div class="permission-header">
                    <div class="permission-title-text">${permission.title}</div>
                    <div class="permission-status" id="status-${permission.id}">⏳</div>
                </div>
                <div class="permission-description">${permission.description}</div>
                <div class="permission-reason">💡 ${permission.reason}</div>
            `;
            listElement.appendChild(card);
        });
    }

    function updateUI() {
        const progress = (currentIndex / PERMISSIONS.length) * 100;

        document.getElementById('permission-progress-bar').style.width = progress + '%';
        document.getElementById('permission-progress-text').textContent =
            `${currentIndex} من ${PERMISSIONS.length}`;

        // تحديث حالة البطاقات
        PERMISSIONS.forEach((permission, index) => {
            const card = document.getElementById(`permission-card-${permission.id}`);
            const status = document.getElementById(`status-${permission.id}`);

            card.classList.remove('active', 'granted', 'denied');

            if (index === currentIndex) {
                card.classList.add('active');
                status.textContent = '⏳';
            } else if (index < currentIndex) {
                if (permissionsStatus[permission.id]) {
                    card.classList.add('granted');
                    status.textContent = '✅';
                } else {
                    card.classList.add('denied');
                    status.textContent = '❌';
                }
            } else {
                status.textContent = '⏳';
            }
        });

        // إخفاء/إظهار الأزرار
        if (currentIndex >= PERMISSIONS.length) {
            showSummary();
        }
    }

    function showSummary() {
        document.getElementById('permissions-actions').style.display = 'none';

        const grantedCount = Object.values(permissionsStatus).filter(Boolean).length;
        const allGranted = grantedCount === PERMISSIONS.length;

        const summaryElement = document.getElementById('permissions-summary');
        summaryElement.style.display = 'block';
        summaryElement.innerHTML = `
            <div class="summary-icon">${allGranted ? '🎉' : '⚠️'}</div>
            <div class="summary-title">
                ${allGranted ? 'تم منح جميع الصلاحيات!' : 'تحذير: بعض الصلاحيات غير ممنوحة'}
            </div>
            <div class="summary-text">
                ${allGranted
                    ? 'رائع! التطبيق الآن جاهز للعمل بكامل قدراته. يمكنك رفع الملفات حتى مع إغلاق الشاشة.'
                    : `تم منح ${grantedCount} من ${PERMISSIONS.length} صلاحيات فقط. قد لا يعمل التطبيق بشكل صحيح، خاصة رفع الملفات في الخلفية.`
                }
            </div>
            <div class="permissions-actions" style="margin-top: 20px;">
                <button class="btn-permission btn-complete" onclick="PermissionsManager.close()">
                    ${allGranted ? '✅ ابدأ الاستخدام' : '⚠️ متابعة بنقص صلاحيات'}
                </button>
            </div>
        `;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🎬 معالجات الأحداث
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    async function handleGrantClick() {
        const permission = PERMISSIONS[currentIndex];

        console.log(`🙏 Requesting permission: ${permission.id}`);

        // عرض مؤشر التحميل
        const btn = document.getElementById('btn-grant');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ جاري الطلب... <span class="loading-spinner"></span>';
        btn.disabled = true;

        try {
            // طلب الصلاحية من Capacitor Plugin
            const result = await PermissionsManager.requestPermissionNative(permission.id);

            permissionsStatus[permission.id] = result.granted;

            console.log(`${result.granted ? '✅' : '❌'} Permission ${permission.id}: ${result.granted ? 'granted' : 'denied'}`);

            // الانتقال للصلاحية التالية
            currentIndex++;

            btn.innerHTML = originalText;
            btn.disabled = false;

            updateUI();

        } catch (error) {
            console.error(`❌ Error requesting permission ${permission.id}:`, error);

            // نعتبرها مرفوضة في حالة الخطأ
            permissionsStatus[permission.id] = false;
            currentIndex++;

            btn.innerHTML = originalText;
            btn.disabled = false;

            updateUI();
        }
    }

    function handleSkipClick() {
        const permission = PERMISSIONS[currentIndex];
        console.log(`⏭️ Skipping permission: ${permission.id}`);

        permissionsStatus[permission.id] = false;
        currentIndex++;
        updateUI();
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🌍 الواجهة البرمجية العامة (Public API)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    window.PermissionsManager = {
        /**
         * فحص جميع الصلاحيات (بدون طلبها)
         */
        async checkPermissions() {
            console.log('🔍 Checking all permissions...');

            if (typeof Capacitor === 'undefined' || !window.PermissionsManager) {
                console.warn('⚠️ Capacitor not loaded - running in browser mode');
                return {
                    allGranted: false,
                    permissions: {}
                };
            }

            try {
                const result = await PermissionsManager.checkAllPermissions();
                console.log('📊 Permissions status:', result);
                return result;
            } catch (error) {
                console.error('❌ Error checking permissions:', error);
                return {
                    allGranted: false,
                    permissions: {}
                };
            }
        },

        /**
         * طلب صلاحية واحدة من Java Plugin
         */
        async requestPermissionNative(permissionId) {
            if (typeof Capacitor === 'undefined' || !window.PermissionsManager) {
                throw new Error('Capacitor not available');
            }

            try {
                const result = await PermissionsManager.requestPermission({
                    permission: permissionId
                });

                return result;
            } catch (error) {
                console.error(`❌ Error requesting ${permissionId}:`, error);
                throw error;
            }
        },

        /**
         * طلب جميع الصلاحيات بشكل متسلسل (الواجهة الرئيسية)
         */
        async requestAllPermissions(onComplete) {
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            console.log('🔐 Starting sequential permissions request');
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            onCompleteCallback = onComplete;
            currentIndex = 0;
            permissionsStatus = {};

            // إنشاء الواجهة
            createUI();

            // فحص الصلاحيات الحالية أولاً
            try {
                const status = await this.checkPermissions();

                // تحديث حالة الصلاحيات الممنوحة مسبقاً
                PERMISSIONS.forEach(permission => {
                    if (status[permission.id]) {
                        permissionsStatus[permission.id] = true;
                    }
                });

            } catch (error) {
                console.error('❌ Error checking initial permissions:', error);
            }
        },

        /**
         * إغلاق الواجهة
         */
        close() {
            if (overlayElement) {
                overlayElement.style.animation = 'fadeIn 0.3s ease-out reverse';

                setTimeout(() => {
                    overlayElement.remove();
                    overlayElement = null;

                    if (onCompleteCallback) {
                        onCompleteCallback(permissionsStatus);
                    }
                }, 300);
            }
        },

        /**
         * الحصول على حالة الصلاحيات الحالية
         */
        getStatus() {
            return permissionsStatus;
        }
    };

    console.log('✅ PermissionsManager JavaScript loaded successfully');
    console.log('📖 Usage: PermissionsManager.requestAllPermissions()');

})();
