/**
 * Sponsorship Sync Service v3.0
 *
 * خدمة المزامنة مع دعم:
 * 1. العمل في الخلفية
 * 2. الإشعارات المحلية
 * 3. التخزين الدائم على الجهاز باستخدام IndexedDB
 */

const SyncService = {
    // إعدادات
    baseUrl: (window.APP_CONFIG && window.APP_CONFIG.API_URL) || 'http://10.0.2.2:8000/api',
    token: null,
    user: null,
    dbName: 'AlhayahSponsorshipsDB',
    dbVersion: 4, // ترقية الإصدار لإضافة جدول أنواع الكفالة
    db: null,

    // حالة المزامنة (للعمل في الخلفية)
    syncInProgress: false,
    uploadInProgress: false,
    LocalNotifications: null,
    isAppInBackground: false,

    // المعماريات الجديدة
    tokenInterceptor: null,
    actionManager: null,
    conflictResolver: null,

    // ========================================
    // التهيئة
    // ========================================

    async init() {
        // تحميل Token من التخزين
        this.token = localStorage.getItem('auth_token');
        const userData = localStorage.getItem('user_data');
        if (userData) {
            try {
                this.user = JSON.parse(userData);
            } catch (e) {
                console.error('Failed to parse user data');
            }
        }

        // تحميل عنوان الخادم
        const savedUrl = localStorage.getItem('api_base_url');
        if (savedUrl) {
            this.baseUrl = savedUrl;
        }

        // تهيئة المعماريات الجديدة
        this.tokenInterceptor = new TokenInterceptor(this.baseUrl);
        this.actionManager = new SyncActionManager(this, this);
        this.conflictResolver = new ConflictResolver(this.actionManager);

        // فتح قاعدة البيانات المحلية
        await this.openDatabase();

        // تهيئة الإشعارات المحلية
        await this.initNotifications();

        // تهيئة العمل في الخلفية
        await this.initBackgroundTask();

        // حذف جميع الإشعارات عند بدء التطبيق
        await this.clearAllNotifications();
    },

    // حذف جميع الإشعارات
    async clearAllNotifications() {
        try {
            if (this.LocalNotifications) {
                await this.LocalNotifications.removeAllDeliveredNotifications();
                console.log('All notifications cleared');
            }
        } catch (error) {
            console.log('Failed to clear notifications:', error.message);
        }
    },

    // تهيئة الإشعارات المحلية
    async initNotifications() {
        try {
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.LocalNotifications) {
                this.LocalNotifications = window.Capacitor.Plugins.LocalNotifications;

                // طلب الإذن
                const permission = await this.LocalNotifications.requestPermissions();
                console.log('Notification permission:', permission);

                // إنشاء قناة للإشعارات (Android)
                await this.LocalNotifications.createChannel({
                    id: 'sync_channel',
                    name: 'المزامنة',
                    description: 'إشعارات المزامنة',
                    importance: 4,
                    visibility: 1,
                    sound: 'default',
                    vibration: true
                });
            }
        } catch (error) {
            console.log('Local notifications not available:', error.message);
        }
    },

    // إرسال إشعار محلي (فقط في الخلفية أو للعمليات الهامة)
    async sendNotification(title, body, progress = null, force = false) {
        try {
            if (!this.LocalNotifications) return null;

            // لا ترسل إشعارات إذا كان التطبيق في المقدمة (إلا إذا كانت للملفات أو رفع Google Drive)
            if (!force && !this.isAppInBackground) {
                console.log('App in foreground - skipping notification');
                return null;
            }

            const id = progress !== null ? 1000 : Math.floor(Math.random() * 100000);
            const notificationOptions = {
                notifications: [{
                    id: id,
                    title: title,
                    body: body,
                    channelId: 'sync_channel',
                    ongoing: progress !== null && progress < 100,
                    autoCancel: progress === null || progress >= 100,
                    smallIcon: 'ic_stat_notification',
                    largeIcon: 'ic_launcher',
                    // شريط التقدم الحقيقي بدون extra
                    ...(progress !== null && {
                        progress: {
                            current: Math.round(progress),
                            max: 100
                        }
                    })
                }]
            };

            await this.LocalNotifications.schedule(notificationOptions);
            return id;
        } catch (error) {
            console.log('Failed to send notification:', error.message);
            return null;
        }
    },

    // إخفاء إشعار محدد
    async cancelNotification(id) {
        try {
            if (!this.LocalNotifications) return;
            await this.LocalNotifications.cancel({ notifications: [{ id }] });
        } catch (error) {
            console.log('Failed to cancel notification:', error.message);
        }
    },

    setBaseUrl(url) {
        this.baseUrl = url;
        localStorage.setItem('api_base_url', url);
    },

    isAuthenticated() {
        return !!this.token;
    },

    // ========================================
    // المعاملات المجمعة (Bulk Transactions)
    // ========================================
    async bulkPut(storeName, items) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite', { durability: 'relaxed' });
            const store = transaction.objectStore(storeName);
            
            let completed = 0;
            for (const item of items) {
                const request = store.put(item);
                request.onsuccess = () => completed++;
            }
            
            transaction.oncomplete = () => resolve(completed);
            transaction.onerror = () => reject(transaction.error);
        });
    },

    async bulkDelete(storeName, keys) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite', { durability: 'relaxed' });
            const store = transaction.objectStore(storeName);
            
            let completed = 0;
            for (const key of keys) {
                const request = store.delete(key);
                request.onsuccess = () => completed++;
            }
            
            transaction.oncomplete = () => resolve(completed);
            transaction.onerror = () => reject(transaction.error);
        });
    },

    // ========================================
    // قاعدة البيانات المحلية (IndexedDB)
    // ========================================

    async openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => {
                console.error('Failed to open database');
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                console.log('Database opened successfully');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // جدول الجمعيات
                if (!db.objectStoreNames.contains('sponsors')) {
                    const sponsorsStore = db.createObjectStore('sponsors', { keyPath: 'id' });
                    sponsorsStore.createIndex('name', 'name', { unique: false });
                }

                // جدول حالات الكفالة
                if (!db.objectStoreNames.contains('sponsorship_statuses')) {
                    db.createObjectStore('sponsorship_statuses', { keyPath: 'id' });
                }

                // جدول الكفالات (الرئيسي)
                if (!db.objectStoreNames.contains('sponsorships')) {
                    const sponsorshipsStore = db.createObjectStore('sponsorships', { keyPath: 'id' });
                    sponsorshipsStore.createIndex('sponsor_id', 'sponsor_id', { unique: false });
                    sponsorshipsStore.createIndex('sponsorship_status_id', 'sponsorship_status_id', { unique: false });
                    sponsorshipsStore.createIndex('identity_number', 'identity_number', { unique: false });
                    sponsorshipsStore.createIndex('orphan_name', 'orphan_name', { unique: false });
                }

                // جدول التغييرات المعلقة (للرفع)
                if (!db.objectStoreNames.contains('pending_uploads')) {
                    const pendingStore = db.createObjectStore('pending_uploads', { keyPath: 'id', autoIncrement: true });
                    pendingStore.createIndex('sponsorship_id', 'sponsorship_id', { unique: false });
                    pendingStore.createIndex('created_at', 'created_at', { unique: false });
                }

                // جدول الملفات (للصور)
                if (!db.objectStoreNames.contains('files')) {
                    const filesStore = db.createObjectStore('files', { keyPath: 'id', autoIncrement: true });
                    filesStore.createIndex('sponsorship_id', 'sponsorship_id', { unique: false });
                    filesStore.createIndex('uploaded', 'uploaded', { unique: false });
                }

                // جدول إعدادات المزامنة
                if (!db.objectStoreNames.contains('sync_meta')) {
                    db.createObjectStore('sync_meta', { keyPath: 'key' });
                }

                // جدول أسماء البنوك
                if (!db.objectStoreNames.contains('bank_names')) {
                    db.createObjectStore('bank_names', { keyPath: 'id' });
                }

                // جدول الحالات الصحية
                if (!db.objectStoreNames.contains('health_statuses')) {
                    db.createObjectStore('health_statuses', { keyPath: 'id' });
                }

                // جدول المدن
                if (!db.objectStoreNames.contains('cities')) {
                    db.createObjectStore('cities', { keyPath: 'id' });
                }

                // جدول أنواع الكفالة
                if (!db.objectStoreNames.contains('sponsorship_types')) {
                    db.createObjectStore('sponsorship_types', { keyPath: 'id' });
                }

                console.log('Database schema created');
            };
        });
    },

    // عمليات التخزين العامة
    async dbPut(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    async dbGet(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    async dbGetAll(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    async dbDelete(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },

    async dbClear(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },

    async dbCount(storeName) {
        if (!this.db) {
            console.warn('Database not ready, initializing...');
            await this.openDatabase();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.count();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    // ========================================
    // طلبات API
    // ========================================

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Bypass-Tunnel-Reminder': 'true',
            ...options.headers
        };

        try {
            const response = await this.tokenInterceptor.fetchWithAuth(url, { ...options, headers });
            
            // تحقق إذا كان المحتوى JSON
            let data = null;
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                data = await response.json();
            } else {
                data = { message: await response.text() };
            }

            if (!response.ok) {
                throw new Error(data.message || 'حدث خطأ في الاتصال');
            }

            return data;
        } catch (error) {
            if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                throw new Error('تعذر الاتصال بالخادم');
            }
            throw error;
        }
    },

    // ========================================
    // المصادقة (مع دعم Offline)
    // ========================================

    async login(username, password, deviceId = null) {
        console.log('🔐 بدء عملية تسجيل الدخول للمستخدم:', username);

        // ⚠️ دائماً حاول الاتصال بالسيرفر أولاً (حتى لو كان هناك بيانات محفوظة)
        if (navigator.onLine) {
            try {
                console.log('🌐 متصل بالإنترنت - محاولة تسجيل الدخول عبر السيرفر...');

                const data = await this.request('/mobile/login', {
                    method: 'POST',
                    body: JSON.stringify({ username, password, device_id: deviceId })
                });

                console.log('📡 استجابة السيرفر:', data);

                if (data.success) {
                    // ✅ تسجيل دخول ناجح - حفظ البيانات الجديدة
                    this.token = data.token;
                    this.user = data.user;
                    localStorage.setItem('auth_token', data.token);
                    localStorage.setItem('user_data', JSON.stringify(data.user));
                    localStorage.setItem('token_expires', data.expires_at);

                    // حفظ بيانات الاعتماد الجديدة للدخول Offline
                    await this.saveCredentials(username, password, data.user);

                    console.log('✅ تسجيل دخول ناجح للمستخدم:', data.user.name);
                }

                return data;

            } catch (error) {
                console.error('❌ فشل الاتصال بالسيرفر:', error.message);
                // إذا فشل الاتصال بالسيرفر، حاول الدخول Offline
                console.log('🔄 محاولة تسجيل الدخول offline...');
                return await this.offlineLogin(username, password);
            }
        } else {
            // لا يوجد إنترنت - محاولة الدخول Offline
            console.log('📵 لا يوجد إنترنت - محاولة تسجيل الدخول offline...');
            return await this.offlineLogin(username, password);
        }
    },

    // حفظ بيانات الاعتماد للاستخدام Offline
    async saveCredentials(username, password, user) {
        try {
            // تشفير بسيط لكلمة المرور (في الإنتاج استخدم تشفير أقوى)
            const hashedPassword = await this.hashPassword(password);

            const credentials = {
                key: 'user_credentials',
                username: username,
                password_hash: hashedPassword,
                user: user,
                saved_at: new Date().toISOString()
            };

            await this.dbPut('sync_meta', credentials);
            console.log('Credentials saved for offline login');
        } catch (error) {
            console.error('Failed to save credentials:', error);
        }
    },

    // تجزئة كلمة المرور (hash بسيط)
    async hashPassword(password) {
        // استخدام Web Crypto API للتجزئة
        if (window.crypto && window.crypto.subtle) {
            const encoder = new TextEncoder();
            const data = encoder.encode(password + 'alhayah_salt_2024');
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        }
        // Fallback بسيط
        return btoa(password);
    },

    // تسجيل الدخول Offline
    async offlineLogin(username, password) {
        try {
            console.log('🔐 محاولة تسجيل الدخول Offline للمستخدم:', username);

            const savedCredentials = await this.dbGet('sync_meta', 'user_credentials');

            if (!savedCredentials) {
                console.log('❌ لا توجد بيانات محفوظة للدخول Offline');
                return {
                    success: false,
                    message: 'لا توجد بيانات محفوظة. يجب تسجيل الدخول عبر الإنترنت أولاً'
                };
            }

            // ⚠️ ملاحظة: في وضع Offline، نتحقق فقط من البيانات المحفوظة
            // المستخدم يجب أن يكون قد سجل الدخول عبر الإنترنت مرة واحدة على الأقل

            // التحقق من اسم المستخدم
            if (savedCredentials.username !== username) {
                console.log('⚠️ اسم المستخدم المدخل:', username);
                console.log('⚠️ اسم المستخدم المحفوظ:', savedCredentials.username);
                return {
                    success: false,
                    message: 'لا يمكن تسجيل الدخول بهذا الحساب في وضع عدم الاتصال. يجب الاتصال بالإنترنت أولاً.'
                };
            }

            // التحقق من كلمة المرور
            const hashedPassword = await this.hashPassword(password);
            if (savedCredentials.password_hash !== hashedPassword) {
                return {
                    success: false,
                    message: 'كلمة المرور غير صحيحة'
                };
            }

            // تسجيل الدخول بنجاح Offline
            this.user = savedCredentials.user;
            this.token = localStorage.getItem('auth_token') || 'offline_session';
            localStorage.setItem('auth_token', this.token);
            localStorage.setItem('user_data', JSON.stringify(savedCredentials.user));

            console.log('✅ تسجيل دخول Offline ناجح للمستخدم:', savedCredentials.user.name);

            return {
                success: true,
                message: 'تم تسجيل الدخول (وضع عدم الاتصال)',
                user: savedCredentials.user,
                offline: true
            };

        } catch (error) {
            console.error('❌ خطأ في تسجيل الدخول Offline:', error);
            return {
                success: false,
                message: 'فشل تسجيل الدخول: ' + error.message
            };
        }
    },

    // التحقق من وجود بيانات اعتماد محفوظة
    async hasOfflineCredentials() {
        try {
            const credentials = await this.dbGet('sync_meta', 'user_credentials');
            return !!credentials;
        } catch (error) {
            return false;
        }
    },

    /**
     * التحقق من كلمة المرور محلياً (للعمليات الحساسة مثل الحذف)
     */
    async verifyPassword(password) {
        try {
            const savedCredentials = await this.dbGet('sync_meta', 'user_credentials');

            if (!savedCredentials) {
                return {
                    valid: false,
                    message: 'لا توجد بيانات محفوظة'
                };
            }

            // التحقق من كلمة المرور
            const hashedPassword = await this.hashPassword(password);
            if (savedCredentials.password_hash === hashedPassword) {
                return {
                    valid: true,
                    message: 'كلمة المرور صحيحة'
                };
            } else {
                return {
                    valid: false,
                    message: 'كلمة المرور غير صحيحة'
                };
            }
        } catch (error) {
            console.error('Password verify error:', error);
            return {
                valid: false,
                message: 'خطأ في التحقق من كلمة المرور'
            };
        }
    },

    async logout() {
        try {
            if (this.token && navigator.onLine) {
                await this.request('/mobile/logout', { method: 'POST' });
            }
        } catch (e) {
            console.error('Logout error:', e);
        }

        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        localStorage.removeItem('token_expires');
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('username');
        // حذف credentials المحفوظة للسماح بتسجيل دخول مستخدم جديد
        try {
            await this.dbDelete('sync_meta', 'user_credentials');
            console.log('Saved credentials cleared for new user login');
        } catch (e) {
            console.log('No saved credentials to clear');
        }
    },

    // تسجيل خروج كامل مع حذف جميع البيانات للسماح بتبديل الحساب
    async fullLogout() {
        await this.logout();
        // حذف بيانات الاعتماد المحفوظة
        try {
            await this.dbDelete('sync_meta', 'user_credentials');
        } catch (e) {
            console.log('No credentials to delete');
        }
        console.log('Full logout completed - ready for new user');
    },

    /**
     * الحصول على بيانات المستخدم الحالي
     */
    async getUser() {
        if (this.user && this.token) {
            return {
                ...this.user,
                token: this.token
            };
        }

        // محاولة تحميل من localStorage
        const savedToken = localStorage.getItem('auth_token');
        const userData = localStorage.getItem('user_data');

        if (savedToken && userData) {
            try {
                this.token = savedToken;
                this.user = JSON.parse(userData);
                return {
                    ...this.user,
                    token: this.token
                };
            } catch (e) {
                console.error('Failed to parse user data');
            }
        }

        return null;
    },

    async checkHealth() {
        return await this.request('/mobile/health', { method: 'GET' });
    },

    // ========================================
    // المزامنة الأولية
    // ========================================

    async performInitialSync() {
        try {
            this.syncInProgress = true;

            // إرسال إشعار بدء المزامنة (نحتفظ بالمعرف ليتم إخفاؤه عند الانتهاء)
            const initialNotifId = await this.sendNotification('جاري المزامنة...', 'يتم تحميل البيانات الأساسية', 0);

            const result = await this.request('/mobile/sync/initial');

            if (result.success) {
                let totalItems = 0;
                let processedItems = 0;

                // حساب إجمالي العناصر
                totalItems = result.data.sponsors.length +
                            result.data.sponsorship_statuses.length +
                            (result.data.bank_names?.length || 0) +
                            (result.data.health_statuses?.length || 0) +
                            (result.data.cities?.length || 0) +
                            (result.data.sponsorship_types?.length || 0);

                // حفظ الجمعيات
                for (const sponsor of result.data.sponsors) {
                    await this.dbPut('sponsors', sponsor);
                    processedItems++;
                }
                await this.sendNotification('جاري المزامنة...', `تم حفظ ${result.data.sponsors.length} جمعية`, Math.round((processedItems/totalItems)*100));

                // حفظ حالات الكفالة
                for (const status of result.data.sponsorship_statuses) {
                    await this.dbPut('sponsorship_statuses', status);
                    processedItems++;
                }
                await this.sendNotification('جاري المزامنة...', `تم حفظ ${result.data.sponsorship_statuses.length} حالة كفالة`, Math.round((processedItems/totalItems)*100));

                // حفظ أسماء البنوك
                if (result.data.bank_names) {
                    for (const bank of result.data.bank_names) {
                        await this.dbPut('bank_names', bank);
                        processedItems++;
                    }
                    await this.sendNotification('جاري المزامنة...', `تم حفظ ${result.data.bank_names.length} بنك`, Math.round((processedItems/totalItems)*100));
                }

                // حفظ الحالات الصحية
                if (result.data.health_statuses) {
                    for (const status of result.data.health_statuses) {
                        await this.dbPut('health_statuses', status);
                        processedItems++;
                    }
                    await this.sendNotification('جاري المزامنة...', `تم حفظ ${result.data.health_statuses.length} حالة صحية`, Math.round((processedItems/totalItems)*100));
                }

                // حفظ المدن
                if (result.data.cities) {
                    for (const city of result.data.cities) {
                        await this.dbPut('cities', city);
                        processedItems++;
                    }
                    await this.sendNotification('جاري المزامنة...', `تم حفظ ${result.data.cities.length} مدينة`, Math.round((processedItems/totalItems)*100));
                }

                // حفظ أنواع الكفالة
                if (result.data.sponsorship_types) {
                    for (const type of result.data.sponsorship_types) {
                        await this.dbPut('sponsorship_types', type);
                        processedItems++;
                    }
                }

                // حفظ وقت المزامنة
                await this.dbPut('sync_meta', {
                    key: 'last_initial_sync',
                    value: result.sync_timestamp,
                    statistics: result.data.statistics
                });

                this.syncInProgress = false;

                // إشعار اكتمال المزامنة
                await this.sendNotification('✅ اكتملت المزامنة', `تم تحميل ${totalItems} عنصر بنجاح`);
                // إخفاء إشعار التقدم السابق
                if (initialNotifId) await this.cancelNotification(initialNotifId);

                return {
                    success: true,
                    sponsors_count: result.data.sponsors.length,
                    statuses_count: result.data.sponsorship_statuses.length,
                    banks_count: result.data.bank_names?.length || 0,
                    health_statuses_count: result.data.health_statuses?.length || 0,
                    cities_count: result.data.cities?.length || 0,
                    sponsorship_types_count: result.data.sponsorship_types?.length || 0,
                    statistics: result.data.statistics
                };
            }

            this.syncInProgress = false;
            await this.sendNotification('❌ فشلت المزامنة', result.message);
            if (initialNotifId) await this.cancelNotification(initialNotifId);
            return { success: false, message: result.message };

        } catch (error) {
            console.error('Initial sync failed:', error);
            this.syncInProgress = false;
            await this.sendNotification('❌ فشلت المزامنة', error.message);
            if (initialNotifId) await this.cancelNotification(initialNotifId);
            throw error;
        }
    },

    // ========================================
    // مزامنة الكفالات
    // ========================================

    async syncSponsorships(sponsorId, statusId, options = {}) {
        try {
            // التحقق من اختيار الجمعية والحالة
            if (!sponsorId) {
                throw new Error('يجب اختيار الجمعية أولاً');
            }

            const params = new URLSearchParams();
            params.append('sponsor_id', sponsorId);
            if (statusId !== undefined && statusId !== '') {
                params.append('status_id', statusId);
            }
            if (options.search) {
                params.append('search', options.search);
            }
            if (options.page) {
                params.append('page', options.page);
            }
            if (options.perPage) {
                params.append('per_page', options.perPage);
            }

            // جلب آخر وقت مزامنة للمزامنة التزايدية
            const lastSync = await this.dbGet('sync_meta', `sponsorships_${sponsorId}_${statusId || 'all'}`);
            if (lastSync && options.incremental !== false) {
                params.append('last_sync', lastSync.value);
            }

            const result = await this.request(`/mobile/sync/sponsorships?${params.toString()}`);

            if (result.success) {
                // حفظ الكفالات محلياً مع حل التعارضات
                const resolvedRecords = await this.conflictResolver.resolveBulk(result.data, async (id) => {
                    return await this.dbGet('sponsorships', id);
                });
                
                if (resolvedRecords.length > 0) {
                    await this.bulkPut('sponsorships', resolvedRecords);
                }

                // تحديث وقت المزامنة
                await this.dbPut('sync_meta', {
                    key: `sponsorships_${sponsorId}_${statusId || 'all'}`,
                    value: result.sync_timestamp
                });

                return {
                    success: true,
                    downloaded: result.data.length,
                    total: result.pagination.total,
                    pagination: result.pagination
                };
            }

            return { success: false, message: result.message };

        } catch (error) {
            console.error('Sponsorships sync failed:', error);
            throw error;
        }
    },

    // ========================================
    // المزامنة الكاملة - لزر "مزامنة الآن"
    // ========================================

    async performFullSync(onProgress = null) {
        try {
            this.syncInProgress = true;
            let page = 1;
            let hasMore = true;
            let totalDownloaded = 0;
            let totalRecords = 0;

            // ⚠️ عدد الكفالات قبل المزامنة
            const countBeforeSync = await this.dbCount('sponsorships');
            console.log('📊 عدد الكفالات قبل المزامنة:', countBeforeSync);

            // ⚠️ بدء خدمة الخلفية فقط عند بدء المزامنة الفعلية
            await this.startBackgroundService();

            // إرسال إشعار بدء المزامنة (نحتفظ بالمعرف ليتم إخفاؤه عند الانتهاء)
            const fullNotifId = await this.sendNotification('جاري مزامنة الكفالات...', 'يتم جلب البيانات من الخادم', 0);

            // تعيين Progress Bar في وضع دائري أثناء الجلب الأولي
            await this.setNotificationIndeterminate('جاري مزامنة الكفالات...');

            // التحقق من عدد الكفالات المحلية
            const localCount = await this.dbCount('sponsorships');
            console.log('📊 عدد الكفالات المحلية:', localCount);

            // جلب آخر وقت مزامنة - فقط إذا كانت هناك كفالات محلية
            const lastSyncMeta = await this.dbGet('sync_meta', 'last_full_sync');
            // استخدام last_sync فقط إذا كانت هناك كفالات محلية
            const lastSync = (localCount > 0 && lastSyncMeta) ? lastSyncMeta.value : null;

            console.log('📅 آخر مزامنة:', lastSync || 'مزامنة كاملة جديدة');

            while (hasMore) {
                const params = new URLSearchParams();
                params.append('page', page);
                params.append('per_page', 100);
                if (lastSync) {
                    params.append('last_sync', lastSync);
                }

                console.log('🔄 جلب الصفحة:', page, 'مع last_sync:', lastSync || 'بدون');

                const result = await this.request(`/mobile/sync/full?${params.toString()}`);

                if (result.success) {
                    // حفظ الكفالات مع حل التعارضات
                    const resolvedRecords = await this.conflictResolver.resolveBulk(result.data, async (id) => {
                        return await this.dbGet('sponsorships', id);
                    });
                    
                    if (resolvedRecords.length > 0) {
                        // Sanitize null values for IndexedDB indexes to prevent DataError crashes
                        const sanitizedRecords = resolvedRecords.map(r => {
                            const sanitized = { ...r };
                            if (sanitized.sponsor_id === null || sanitized.sponsor_id === undefined) sanitized.sponsor_id = 0;
                            if (sanitized.sponsorship_status_id === null || sanitized.sponsorship_status_id === undefined) sanitized.sponsorship_status_id = 0;
                            return sanitized;
                        });
                        await this.bulkPut('sponsorships', sanitizedRecords);
                    }

                    // حذف الكفالات المستبعدة (تم الصرف / أرسل للصرف) دفعة واحدة
                    if (result.deleted_ids && result.deleted_ids.length > 0) {
                        await this.bulkDelete('sponsorships', result.deleted_ids);
                    }

                    totalDownloaded += result.data.length;
                    totalRecords = result.pagination.total;
                    hasMore = result.pagination.has_more;
                    page++;

                    // تحديث التقدم
                    const progress = totalRecords > 0 ? Math.round((totalDownloaded / totalRecords) * 100) : 0;

                    // إرسال إشعار التقدم
                    await this.sendNotification('جاري مزامنة الكفالات...', `تم تنزيل ${totalDownloaded} من ${totalRecords}`, progress);

                    // تحديث Progress Bar في إشعار Android
                    await this.updateNotificationProgress(totalDownloaded, totalRecords, `تم تنزيل ${totalDownloaded} من ${totalRecords}`);

                    if (onProgress) {
                        onProgress({
                            downloaded: totalDownloaded,
                            total: totalRecords,
                            progress: progress,
                            page: page - 1,
                            lastPage: result.pagination.last_page
                        });
                    }
                } else {
                    throw new Error(result.message || 'فشل المزامنة');
                }
            }

            // تحديث وقت المزامنة
            await this.dbPut('sync_meta', {
                key: 'last_full_sync',
                value: new Date().toISOString(),
                total_records: totalDownloaded
            });

            this.syncInProgress = false;

            // ⚠️ حساب العدد الفعلي للكفالات المخزنة محلياً بعد المزامنة
            const actualLocalCount = await this.dbCount('sponsorships');

            // ⚠️ حساب الكفالات الجديدة الفعلية (الفرق بين قبل وبعد)
            const newSponsorshipsCount = actualLocalCount - countBeforeSync;

            console.log('📊 إحصائيات المزامنة:', {
                قبل: countBeforeSync,
                بعد: actualLocalCount,
                جديد: newSponsorshipsCount,
                تمTنزيله: totalDownloaded
            });

            // ⚠️ رسالة واضحة للمستخدم
            let message;
            if (totalDownloaded > 0) {
                message = `تم تحميل ${totalDownloaded} كفالة (الإجمالي المحلي: ${actualLocalCount})`;
            } else if (actualLocalCount > 0) {
                message = `البيانات محدّثة - ${actualLocalCount} كفالة محفوظة`;
            } else {
                message = 'لا توجد كفالات للتحميل';
            }

            // إشعار اكتمال المزامنة
            await this.sendNotification('✅ اكتملت المزامنة', message);
            await this.showNotificationComplete(`✅ ${message}`);
            if (fullNotifId) await this.cancelNotification(fullNotifId);

            // ⚠️ إيقاف خدمة الخلفية فوراً بعد انتهاء المزامنة
            await this.stopBackgroundService();

            return {
                success: true,
                downloaded: totalDownloaded,
                total: totalRecords,
                localCount: actualLocalCount,
                newCount: newSponsorshipsCount,
                message: message
            };

        } catch (error) {
            console.error('Full sync failed:', error);
            this.syncInProgress = false;
            await this.sendNotification('❌ فشلت المزامنة', error.message);
            await this.showNotificationComplete(`❌ فشلت المزامنة: ${error.message}`);
            if (fullNotifId) await this.cancelNotification(fullNotifId);
            // ⚠️ إيقاف خدمة الخلفية فوراً عند الفشل
            await this.stopBackgroundService();
            throw error;
        }
    },

    // ========================================
    // جلب البيانات المحلية
    // ========================================

    async getLocalSponsors() {
        return await this.dbGetAll('sponsors');
    },

    async getLocalStatuses() {
        return await this.dbGetAll('sponsorship_statuses');
    },

    async getLocalCities() {
        try {
            return await this.dbGetAll('cities');
        } catch (error) {
            return [];
        }
    },

    async getLocalBankNames() {
        try {
            return await this.dbGetAll('bank_names');
        } catch (error) {
            return [];
        }
    },

    async getLocalHealthStatuses() {
        try {
            return await this.dbGetAll('health_statuses');
        } catch (error) {
            return [];
        }
    },

    async getLocalSponsorshipTypes() {
        try {
            return await this.dbGetAll('sponsorship_types');
        } catch (error) {
            return [];
        }
    },

    async getLocalSponsorships(filters = {}) {
        const all = await this.dbGetAll('sponsorships');

        let filtered = all;

        // فلترة بالجمعية
        if (filters.sponsor_id) {
            filtered = filtered.filter(s => s.sponsor_id == filters.sponsor_id);
        }

        // فلترة بالحالة
        if (filters.status_id !== undefined && filters.status_id !== '') {
            filtered = filtered.filter(s => s.sponsorship_status_id == filters.status_id);
        }

        // بحث
        if (filters.search) {
            const search = filters.search.toLowerCase();
            filtered = filtered.filter(s =>
                (s.orphan_name && s.orphan_name.toLowerCase().includes(search)) ||
                (s.identity_number && s.identity_number.includes(search)) ||
                (s.internal_file_number && s.internal_file_number.includes(search)) ||
                (s.external_file_number && s.external_file_number.includes(search)) ||
                (s.guardian_name && s.guardian_name.toLowerCase().includes(search))
            );
        }

        return filtered;
    },

    // ========================================
    // جلب البيانات بشكل تدريجي (Pagination من IndexedDB)
    // ========================================
    async getLocalSponsorshipsPaginated(filters = {}, page = 1, pageSize = 20) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sponsorships'], 'readonly');
            const store = transaction.objectStore('sponsorships');
            const request = store.openCursor();

            let results = [];
            let count = 0;
            let skipCount = (page - 1) * pageSize;
            let addedCount = 0;

            request.onsuccess = (event) => {
                const cursor = event.target.result;

                if (cursor) {
                    const item = cursor.value;
                    let matches = true;

                    // تطبيق الفلاتر
                    if (filters.sponsor_id && item.sponsor_id != filters.sponsor_id) {
                        matches = false;
                    }

                    if (matches && filters.status_id !== undefined && filters.status_id !== '' &&
                        item.sponsorship_status_id != filters.status_id) {
                        matches = false;
                    }

                    if (matches && filters.search) {
                        const search = filters.search.toLowerCase();
                        matches = (
                            (item.orphan_name && item.orphan_name.toLowerCase().includes(search)) ||
                            (item.identity_number && item.identity_number.includes(search)) ||
                            (item.internal_file_number && item.internal_file_number.includes(search)) ||
                            (item.external_file_number && item.external_file_number.includes(search)) ||
                            (item.guardian_name && item.guardian_name.toLowerCase().includes(search))
                        );
                    }

                    if (matches) {
                        if (count >= skipCount && addedCount < pageSize) {
                            results.push(item);
                            addedCount++;
                        }
                        count++;
                    }

                    // إذا جمعنا العدد المطلوب، توقف
                    if (addedCount >= pageSize) {
                        resolve({
                            data: results,
                            total: count,
                            page: page,
                            pageSize: pageSize,
                            hasMore: true // سنحتاج cursor للتحقق
                        });
                    } else {
                        cursor.continue();
                    }
                } else {
                    // انتهت البيانات
                    resolve({
                        data: results,
                        total: count,
                        page: page,
                        pageSize: pageSize,
                        hasMore: false
                    });
                }
            };

            request.onerror = () => reject(request.error);
        });
    },

    // عد النتائج فقط بدون جلب البيانات (للأداء)
    async countLocalSponsorships(filters = {}) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sponsorships'], 'readonly');
            const store = transaction.objectStore('sponsorships');
            const request = store.openCursor();

            let count = 0;

            request.onsuccess = (event) => {
                const cursor = event.target.result;

                if (cursor) {
                    const item = cursor.value;
                    let matches = true;

                    // تطبيق الفلاتر
                    if (filters.sponsor_id && item.sponsor_id != filters.sponsor_id) {
                        matches = false;
                    }

                    if (matches && filters.status_id !== undefined && filters.status_id !== '' &&
                        item.sponsorship_status_id != filters.status_id) {
                        matches = false;
                    }

                    if (matches && filters.search) {
                        const search = filters.search.toLowerCase();
                        matches = (
                            (item.orphan_name && item.orphan_name.toLowerCase().includes(search)) ||
                            (item.identity_number && item.identity_number.includes(search)) ||
                            (item.internal_file_number && item.internal_file_number.includes(search)) ||
                            (item.external_file_number && item.external_file_number.includes(search)) ||
                            (item.guardian_name && item.guardian_name.toLowerCase().includes(search))
                        );
                    }

                    if (matches) count++;
                    cursor.continue();
                } else {
                    resolve(count);
                }
            };

            request.onerror = () => reject(request.error);
        });
    },

    // جلب الملفات بشكل تدريجي
    async getLocalFilesPaginated(page = 1, pageSize = 30) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['files'], 'readonly');
            const store = transaction.objectStore('files');
            const request = store.openCursor();

            let results = [];
            let count = 0;
            let skipCount = (page - 1) * pageSize;

            request.onsuccess = (event) => {
                const cursor = event.target.result;

                if (cursor) {
                    if (count >= skipCount && results.length < pageSize) {
                        results.push(cursor.value);
                    }
                    count++;

                    if (results.length >= pageSize) {
                        resolve({
                            data: results,
                            total: count,
                            page: page,
                            pageSize: pageSize
                        });
                    } else {
                        cursor.continue();
                    }
                } else {
                    resolve({
                        data: results,
                        total: count,
                        page: page,
                        pageSize: pageSize
                    });
                }
            };

            request.onerror = () => reject(request.error);
        });
    },

    async getLocalSponsorship(id) {
        const result = await this.dbGet('sponsorships', id);
        console.log('🟢 getLocalSponsorship - id:', id);
        console.log('🟢 getLocalSponsorship - bank_accounts:', result?.bank_accounts);
        return result;
    },

    // دالة للوصول للجداول المساعدة
    async getLocalData(tableName) {
        try {
            return await this.dbGetAll(tableName);
        } catch (error) {
            console.error(`Failed to get ${tableName}:`, error);
            return [];
        }
    },

    // ========================================
    // رفع التغييرات
    // ========================================

    async saveLocalChange(sponsorshipId, updates) {
        console.log('🔵 saveLocalChange START - sponsorshipId:', sponsorshipId);
        console.log('🔵 saveLocalChange - updates:', JSON.stringify(updates, null, 2));

        // حفظ التغيير محلياً
        const sponsorship = await this.dbGet('sponsorships', sponsorshipId);
        console.log('🔵 Current sponsorship from DB:', sponsorship ? 'found' : 'NOT FOUND');
        console.log('🔵 Current bank_accounts:', sponsorship?.bank_accounts);

        if (sponsorship) {
            const updated = { ...sponsorship, ...updates, _locallyModified: true };

            // تطبيق تحديثات الحسابات البنكية على bank_accounts المحلية
            if (updates.bank_accounts_updates) {
                const bankUpdates = updates.bank_accounts_updates;
                // إنشاء نسخة من bank_accounts أو مصفوفة جديدة إذا لم تكن موجودة
                updated.bank_accounts = sponsorship.bank_accounts ? JSON.parse(JSON.stringify(sponsorship.bank_accounts)) : [];
                console.log('🔵 Before update - bank_accounts length:', updated.bank_accounts.length);

                Object.keys(bankUpdates).forEach(accountIndex => {
                    const index = parseInt(accountIndex);
                    console.log('🔵 Processing account index:', index);

                    if (updated.bank_accounts[index]) {
                        // تحديث حساب موجود
                        console.log('🔵 Updating existing account at index:', index);
                        Object.keys(bankUpdates[accountIndex]).forEach(field => {
                            updated.bank_accounts[index][field] = bankUpdates[accountIndex][field];
                        });
                    } else {
                        // إضافة حساب جديد
                        console.log('🔵 Adding NEW account at index:', index);
                        const newAccount = {
                            ...bankUpdates[accountIndex],
                            _isNew: true,
                            check_account: 0
                        };
                        // التأكد من أن المصفوفة بالطول الصحيح
                        while (updated.bank_accounts.length <= index) {
                            updated.bank_accounts.push(null);
                        }
                        updated.bank_accounts[index] = newAccount;
                        console.log('🆕 تم إضافة حساب بنكي جديد في IndexedDB:', newAccount);
                    }
                });
                console.log('💳 After update - bank_accounts:', JSON.stringify(updated.bank_accounts));
            }

            await this.dbPut('sponsorships', updated);
            console.log('✅ Sponsorship saved to IndexedDB with bank_accounts:', updated.bank_accounts?.length || 0);

            // التأكد من إضافة person_type للتغييرات إذا كانت موجودة في الكفالة
            if (!updates.person_type && sponsorship.person_type) {
                // فحص إذا كانت التغييرات تتضمن بيانات المكفول
                if (updates.first_name || updates.second_name || updates.third_name ||
                    updates.last_name || updates.identity_number || updates.orphan_gender || updates.birth_date) {
                    updates.person_type = sponsorship.person_type;
                    console.log('📌 تمت إضافة person_type للتغييرات:', sponsorship.person_type);
                }
            }
        }

        // طباعة التغييرات للتأكد
        console.log('💾 saveLocalChange - sponsorshipId:', sponsorshipId);
        console.log('💾 saveLocalChange - updates:', updates);

        // إضافة للتغييرات المعلقة باستخدام Action Queue الجديد
        await this.actionManager.enqueueAction('DATA_UPDATE', updates, sponsorshipId);
    },

    async uploadPendingChanges() {
        this.uploadInProgress = true;
        try {
            await this.actionManager.processQueue();
        } finally {
            this.uploadInProgress = false;
        }
        
        return { success: true }; // TODO: Map detailed results if needed by UI
    },

    async getPendingUploadsCount() {
        return await this.dbCount('pending_uploads');
    },

    // الحصول على قائمة التعديلات المعلقة مع التفاصيل
    async getPendingUploadsList() {
        const pending = await this.dbGetAll('pending_uploads');
        const detailedList = [];

        for (const item of pending) {
            // محاولة جلب معلومات الكفالة
            const sponsorship = await this.dbGet('sponsorships', item.sponsorship_id);
            const orphanName = sponsorship?.orphan_name ||
                              sponsorship?.first_name ||
                              'غير معروف';

            detailedList.push({
                id: item.id,
                sponsorship_id: item.sponsorship_id,
                orphan_name: orphanName,
                updates: item.updates,
                created_at: item.created_at || 'غير محدد',
                field_count: Object.keys(item.updates || {}).length
            });
        }

        return detailedList;
    },

    // ========================================
    // إحصائيات
    // ========================================

    async getLocalStats() {
        const sponsorships = await this.dbCount('sponsorships');
        const pendingUploads = await this.dbCount('pending_uploads');
        const files = await this.dbCount('files');
        const lastSync = await this.dbGet('sync_meta', 'last_initial_sync');

        return {
            sponsorships_count: sponsorships,
            pending_uploads: pendingUploads,
            files_count: files,
            last_sync: lastSync ? lastSync.value : null
        };
    },

    async getServerStats() {
        try {
            return await this.request('/mobile/sync/stats');
        } catch (error) {
            console.error('Failed to get server stats:', error);
            return null;
        }
    },

    // ========================================
    // إدارة الملفات
    // ========================================

    async saveFile(sponsorshipId, fileData, fileName, fileType) {
        const file = {
            sponsorship_id: sponsorshipId,
            file_name: fileName,
            file_type: fileType,
            file_data: fileData,
            uploaded: false,
            created_at: new Date().toISOString()
        };

        return await this.dbPut('files', file);
    },

    async getFilesForSponsorship(sponsorshipId) {
        const all = await this.dbGetAll('files');
        return all.filter(f => f.sponsorship_id === sponsorshipId);
    },

    async getPendingFiles() {
        const all = await this.dbGetAll('files');
        return all.filter(f => !f.uploaded);
    },

    // ========================================
    // العمل في الخلفية (Background Tasks)
    // ========================================

    BackgroundTask: null,

    async initBackgroundTask() {
        try {
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BackgroundTask) {
                this.BackgroundTask = window.Capacitor.Plugins.BackgroundTask;
                console.log('BackgroundTask plugin initialized');
            }
        } catch (error) {
            console.log('BackgroundTask not available:', error.message);
        }
    },

    // بدء المزامنة في الخلفية
    async startBackgroundSync() {
        if (!this.BackgroundTask) {
            await this.initBackgroundTask();
        }

        if (this.BackgroundTask) {
            const taskId = await this.BackgroundTask.beforeExit(async () => {
                console.log('App going to background - continuing sync...');

                // إذا كان هناك رفع معلق، استمر
                if (this.uploadInProgress || this.syncInProgress) {
                    await this.sendNotification('المزامنة تعمل في الخلفية', 'سيتم إشعارك عند الانتهاء');
                }

                // إنهاء المهمة الخلفية
                await this.BackgroundTask.finish({ taskId });
            });
        }
    },

    // تشغيل المزامنة الكاملة في الخلفية
    async runBackgroundFullSync() {
        try {
            // بدء المهمة الخلفية
            await this.startBackgroundSync();

            // تنفيذ المزامنة
            const result = await this.performFullSync();

            return result;
        } catch (error) {
            console.error('Background sync failed:', error);
            throw error;
        }
    },

    // تشغيل رفع التعديلات في الخلفية
    async runBackgroundUpload() {
        try {
            // بدء المهمة الخلفية
            await this.startBackgroundSync();

            // تنفيذ الرفع
            const result = await this.uploadPendingChanges();

            return result;
        } catch (error) {
            console.error('Background upload failed:', error);
            throw error;
        }
    },

    // ========================================
    // نظام طابور الرفع التلقائي (Auto Upload Queue)
    // ========================================

    // حالة نظام الرفع التلقائي
    autoUploadEnabled: true,
    autoUploadInProgress: false,
    autoUploadQueue: [],
    networkStatus: navigator.onLine,
    autoUploadListeners: [],
    BackgroundSyncPlugin: null,

    // تهيئة نظام الرفع التلقائي
    async initAutoUpload() {
        console.log('🚀 تهيئة نظام الرفع التلقائي...');

        // ⚠️ قراءة إعداد الرفع التلقائي المحفوظ أولاً
        this.loadAutoUploadSetting();

        // تهيئة Plugin العمل في الخلفية
        await this.initBackgroundSyncPlugin();

        // مراقبة حالة الاتصال
        window.addEventListener('online', () => {
            console.log('🌐 الإنترنت متصل!');
            this.networkStatus = true;
            this.notifyAutoUploadListeners('online');
            // بدء الرفع التلقائي عند عودة الاتصال فقط إذا كان مفعلاً
            if (this.autoUploadEnabled) {
                this.processAutoUploadQueue();
                this.autoUploadPendingChanges();
            }
        });

        window.addEventListener('offline', () => {
            console.log('📵 الإنترنت غير متصل');
            this.networkStatus = false;
            this.notifyAutoUploadListeners('offline');
        });

        // مراقبة Capacitor Network plugin إذا كان متاحاً
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Network) {
            const Network = window.Capacitor.Plugins.Network;

            Network.addListener('networkStatusChange', (status) => {
                console.log('📡 تغيرت حالة الشبكة:', status);
                this.networkStatus = status.connected;
                this.notifyAutoUploadListeners(status.connected ? 'online' : 'offline');

                if (status.connected && this.autoUploadEnabled) {
                    this.processAutoUploadQueue();
                    this.autoUploadPendingChanges();
                }
            });
        }

        // تحميل الملفات المعلقة من IndexedDB عند البدء
        this.loadPendingFilesToQueue();

        // رفع التحديثات المعلقة عند البدء إذا كان الإنترنت متصلاً وكان الرفع التلقائي مفعلاً
        if (this.networkStatus && this.autoUploadEnabled) {
            setTimeout(() => {
                this.autoUploadPendingChanges();
            }, 2000);
        }

        console.log('✅ نظام الرفع التلقائي جاهز');
    },

    // رفع التحديثات المعلقة تلقائياً
    pendingChangesUploadInProgress: false,

    async autoUploadPendingChanges() {
        // التحقق من الشروط
        if (!this.autoUploadEnabled) {
            console.log('⏸️ الرفع التلقائي معطل');
            return;
        }

        if (!this.networkStatus) {
            console.log('📵 لا يوجد اتصال بالإنترنت - التحديثات المعلقة ستُرفع لاحقاً');
            return;
        }

        if (this.pendingChangesUploadInProgress) {
            console.log('⏳ رفع التحديثات المعلقة جارٍ بالفعل...');
            return;
        }

        // التحقق من وجود تحديثات معلقة
        const pendingCount = await this.getPendingUploadsCount();
        if (pendingCount === 0) {
            console.log('✅ لا توجد تحديثات معلقة');
            return;
        }

        console.log(`📤 بدء رفع ${pendingCount} تحديث معلق تلقائياً...`);
        this.pendingChangesUploadInProgress = true;

        // إشعار المستمعين
        this.notifyAutoUploadListeners('pending_upload_started', {
            count: pendingCount
        });

        try {
            const result = await this.uploadPendingChanges();

            console.log(`📊 انتهى رفع التحديثات: نجح ${result.success}، فشل ${result.failed}`);

            // إشعار المستمعين بالاكتمال
            this.notifyAutoUploadListeners('pending_upload_completed', {
                success: result.success,
                failed: result.failed
            });

        } catch (error) {
            console.error('❌ فشل رفع التحديثات المعلقة:', error);
            this.notifyAutoUploadListeners('pending_upload_failed', {
                error: error.message
            });
        } finally {
            this.pendingChangesUploadInProgress = false;
        }
    },

    // ========================================
    // خدمة العمل في الخلفية (Background Service)
    // ========================================

    // تهيئة Plugin العمل في الخلفية
    async initBackgroundSyncPlugin() {
        try {
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BackgroundSync) {
                this.BackgroundSyncPlugin = window.Capacitor.Plugins.BackgroundSync;
                console.log('✅ تم تهيئة Plugin العمل في الخلفية');

                // التحقق من استثناء تحسين البطارية
                await this.checkBatteryOptimization();

                // ⚠️ لا نبدأ الخدمة تلقائياً - سيتم بدؤها فقط عند الحاجة للمزامنة
                // await this.startBackgroundService();
            } else {
                console.log('ℹ️ Plugin العمل في الخلفية غير متاح (ليس في بيئة Capacitor)');
            }
        } catch (error) {
            console.error('خطأ في تهيئة Plugin العمل في الخلفية:', error);
        }
    },

    // بدء خدمة الخلفية
    async startBackgroundService() {
        try {
            if (this.BackgroundSyncPlugin) {
                const result = await this.BackgroundSyncPlugin.startService();
                console.log('🚀 تم بدء خدمة المزامنة في الخلفية:', result);
                return result;
            }
        } catch (error) {
            console.error('فشل في بدء خدمة الخلفية:', error);
        }
        return null;
    },

    // إيقاف خدمة الخلفية
    async stopBackgroundService() {
        try {
            if (this.BackgroundSyncPlugin) {
                const result = await this.BackgroundSyncPlugin.stopService();
                console.log('⏹️ تم إيقاف خدمة المزامنة في الخلفية:', result);
                return result;
            }
        } catch (error) {
            console.error('فشل في إيقاف خدمة الخلفية:', error);
        }
        return null;
    },

    // ========================================
    // تحديث Progress Bar في إشعار Android
    // ========================================

    // تحديث شريط التقدم في الإشعار
    async updateNotificationProgress(progress, max, status) {
        try {
            if (this.BackgroundSyncPlugin) {
                await this.BackgroundSyncPlugin.updateProgress({
                    progress: progress,
                    max: max,
                    status: status
                });
                console.log(`📊 تحديث إشعار التقدم: ${progress}/${max} - ${status}`);
            }
        } catch (error) {
            console.error('فشل في تحديث إشعار التقدم:', error);
        }
    },

    // تعيين الإشعار في وضع غير محدد (دائري)
    async setNotificationIndeterminate(message) {
        try {
            if (this.BackgroundSyncPlugin) {
                await this.BackgroundSyncPlugin.setIndeterminate({
                    message: message
                });
                console.log(`🔄 إشعار دائري: ${message}`);
            }
        } catch (error) {
            console.error('فشل في تعيين الإشعار الدائري:', error);
        }
    },

    // إظهار إشعار الاكتمال
    async showNotificationComplete(message) {
        try {
            if (this.BackgroundSyncPlugin) {
                await this.BackgroundSyncPlugin.showComplete({
                    message: message
                });
                console.log(`✅ إشعار الاكتمال: ${message}`);
            }
        } catch (error) {
            console.error('فشل في إظهار إشعار الاكتمال:', error);
        }
    },

    // التحقق من استثناء تحسين البطارية
    async checkBatteryOptimization() {
        try {
            if (this.BackgroundSyncPlugin) {
                const result = await this.BackgroundSyncPlugin.isIgnoringBatteryOptimizations();
                console.log('🔋 حالة استثناء البطارية:', result.isIgnoring ? 'مستثنى' : 'غير مستثنى');

                if (!result.isIgnoring) {
                    console.log('⚠️ التطبيق ليس مستثنى من تحسين البطارية - قد يتوقف العمل في الخلفية');
                    // إشعار المستمعين
                    this.notifyAutoUploadListeners('battery_optimization_needed', {});
                }

                return result.isIgnoring;
            }
        } catch (error) {
            console.error('فشل في التحقق من تحسين البطارية:', error);
        }
        return false;
    },

    // طلب استثناء من تحسين البطارية
    async requestBatteryOptimizationExemption() {
        try {
            if (this.BackgroundSyncPlugin) {
                const result = await this.BackgroundSyncPlugin.requestIgnoreBatteryOptimizations();
                console.log('🔋 تم طلب استثناء البطارية:', result);
                return result;
            }
        } catch (error) {
            console.error('فشل في طلب استثناء البطارية:', error);
        }
        return null;
    },

    // فتح إعدادات البطارية
    async openBatterySettings() {
        try {
            if (this.BackgroundSyncPlugin) {
                const result = await this.BackgroundSyncPlugin.openBatterySettings();
                console.log('⚙️ تم فتح إعدادات البطارية');
                return result;
            }
        } catch (error) {
            console.error('فشل في فتح إعدادات البطارية:', error);
        }
        return null;
    },

    // إضافة مستمع لأحداث الرفع التلقائي
    addAutoUploadListener(callback) {
        this.autoUploadListeners.push(callback);
    },

    // إزالة مستمع
    removeAutoUploadListener(callback) {
        this.autoUploadListeners = this.autoUploadListeners.filter(cb => cb !== callback);
    },

    // إرسال إشعار للمستمعين
    notifyAutoUploadListeners(event, data = {}) {
        this.autoUploadListeners.forEach(callback => {
            try {
                callback(event, data);
            } catch (error) {
                console.error('Auto upload listener error:', error);
            }
        });
    },

    // تحميل الملفات المعلقة من IndexedDB إلى الطابور
    async loadPendingFilesToQueue() {
        try {
            const pendingFiles = await this.getPendingFiles();
            console.log(`📂 تم تحميل ${pendingFiles.length} ملف معلق إلى الطابور`);

            // إضافة الملفات غير الموجودة في الطابور
            for (const file of pendingFiles) {
                if (!this.autoUploadQueue.find(f => f.id === file.id)) {
                    this.autoUploadQueue.push(file);
                }
            }

            // بدء المعالجة إذا كان الإنترنت متصلاً
            if (this.networkStatus && this.autoUploadQueue.length > 0) {
                this.processAutoUploadQueue();
            }
        } catch (error) {
            console.error('فشل في تحميل الملفات المعلقة:', error);
        }
    },

    // إضافة ملف إلى طابور الرفع
    async addToAutoUploadQueue(file) {
        console.log('📥 إضافة ملف إلى طابور الرفع:', file.file_name);

        // إضافة للطابور
        this.autoUploadQueue.push(file);

        // إشعار المستمعين
        this.notifyAutoUploadListeners('file_queued', {
            file: file,
            queueLength: this.autoUploadQueue.length
        });

        // بدء المعالجة إذا كان الإنترنت متصلاً ولم يكن هناك رفع جارٍ
        if (this.networkStatus && !this.autoUploadInProgress) {
            this.processAutoUploadQueue();
        } else if (!this.networkStatus) {
            console.log('📵 الملف في الطابور - سيتم رفعه عند عودة الاتصال');
            this.notifyAutoUploadListeners('waiting_network', {
                file: file,
                queueLength: this.autoUploadQueue.length
            });
        }
    },

    // معالجة طابور الرفع
    async processAutoUploadQueue() {
        // التحقق من الشروط
        if (!this.autoUploadEnabled) {
            console.log('⏸️ الرفع التلقائي معطل');
            return;
        }

        if (!this.networkStatus) {
            console.log('📵 لا يوجد اتصال بالإنترنت');
            return;
        }

        if (this.autoUploadInProgress) {
            console.log('⏳ الرفع جارٍ بالفعل...');
            return;
        }

        if (this.autoUploadQueue.length === 0) {
            console.log('✅ طابور الرفع فارغ');
            return;
        }

        this.autoUploadInProgress = true;
        console.log(`🚀 بدء معالجة طابور الرفع (${this.autoUploadQueue.length} ملفات)`);

        // إشعار المستمعين ببدء الرفع
        this.notifyAutoUploadListeners('upload_started', {
            totalFiles: this.autoUploadQueue.length
        });

        let uploaded = 0;
        let failed = 0;
        const totalFiles = this.autoUploadQueue.length;

        // إرسال إشعار للمستخدم
        await this.sendNotification(
            '📤 جاري الرفع التلقائي',
            `يتم رفع ${totalFiles} ملف إلى السحابة...`,
            0,
            true
        );

        // تحديث Progress Bar في إشعار Android
        await this.updateNotificationProgress(0, totalFiles, 'جاري رفع الملفات...');

        while (this.autoUploadQueue.length > 0 && this.networkStatus) {
            const file = this.autoUploadQueue[0];

            try {
                console.log(`📤 رفع الملف: ${file.file_name}`);

                // إشعار بالتقدم
                this.notifyAutoUploadListeners('file_uploading', {
                    file: file,
                    progress: uploaded / totalFiles * 100,
                    uploaded: uploaded,
                    total: totalFiles
                });

                // رفع الملف
                const result = await this.uploadSingleFile(file);

                if (result.success) {
                    // إزالة من الطابور
                    this.autoUploadQueue.shift();
                    uploaded++;

                    console.log(`✅ تم رفع: ${file.file_name}`);

                    // إشعار بنجاح الرفع
                    this.notifyAutoUploadListeners('file_uploaded', {
                        file: file,
                        progress: uploaded / totalFiles * 100,
                        uploaded: uploaded,
                        total: totalFiles,
                        result: result
                    });

                    // تحديث إشعار التقدم
                    await this.sendNotification(
                        '📤 جاري الرفع التلقائي',
                        `تم رفع ${uploaded} من ${totalFiles} ملف`,
                        Math.round(uploaded / totalFiles * 100),
                        true
                    );

                    // تحديث Progress Bar في إشعار Android
                    await this.updateNotificationProgress(uploaded, totalFiles, `تم رفع ${uploaded} من ${totalFiles} ملف`);
                } else {
                    throw new Error(result.message || 'فشل الرفع');
                }

            } catch (error) {
                console.error(`❌ فشل رفع: ${file.file_name}`, error);

                // إزالة من الطابور وزيادة عداد الفشل
                this.autoUploadQueue.shift();
                failed++;

                // إشعار بالفشل
                this.notifyAutoUploadListeners('file_failed', {
                    file: file,
                    error: error.message,
                    uploaded: uploaded,
                    failed: failed,
                    total: totalFiles
                });

                // تحديث Progress Bar حتى في حالة الخطأ
                await this.updateNotificationProgress(uploaded + failed, totalFiles, `خطأ في رفع الملف ${uploaded + failed}`);
            }

            // تأخير قصير بين الملفات
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        this.autoUploadInProgress = false;

        // إرسال إشعار بالإنتهاء
        const successMessage = failed === 0
            ? `✅ تم رفع ${uploaded} ملف بنجاح`
            : `⚠️ تم رفع ${uploaded} ملف، فشل ${failed}`;

        await this.sendNotification('اكتمل الرفع التلقائي', successMessage, 100, true);

        // إظهار إشعار الاكتمال في Android
        await this.showNotificationComplete(successMessage);

        // إشعار المستمعين بالإنتهاء
        this.notifyAutoUploadListeners('upload_completed', {
            uploaded: uploaded,
            failed: failed,
            total: totalFiles
        });

        console.log(`📊 انتهى الرفع التلقائي: نجح ${uploaded}، فشل ${failed}`);
    },

    // رفع ملف واحد
    async uploadSingleFile(file) {
        try {
            // جلب بيانات الكفالة
            const sponsorship = await this.dbGet('sponsorships', file.sponsorship_id);
            if (!sponsorship) {
                throw new Error('الكفالة غير موجودة');
            }

            // جلب اسم الجمعية
            const sponsors = await this.dbGetAll('sponsors');
            const sponsor = sponsors.find(sp => sp.id === sponsorship.sponsor_id);
            const sponsorName = sponsor?.name || 'غير معروف';
            const orphanName = sponsorship.orphan_name || 'غير معروف';

            // إنشاء مسار المجلد
            const folderPath = `alhayah/${sponsorName}/${orphanName}`;

            // رفع الملف عبر الخادم
            const result = await this.request('/mobile/upload-file', {
                method: 'POST',
                body: JSON.stringify({
                    file_name: file.file_name,
                    file_type: file.file_type,
                    file_data: file.file_data,
                    folder_path: folderPath,
                    sponsorship_id: file.sponsorship_id
                })
            });

            if (result.success) {
                // تحديث حالة الملف في قاعدة البيانات
                file.uploaded = true;
                file.google_drive_id = result.file_id;
                file.uploaded_at = new Date().toISOString();
                await this.dbPut('files', file);

                return { success: true, file_id: result.file_id };
            } else {
                return { success: false, message: result.message };
            }
        } catch (error) {
            console.error('خطأ في رفع الملف:', error);
            return { success: false, message: error.message };
        }
    },

    // حفظ ملف مع إضافة تلقائية للطابور
    async saveFileWithAutoUpload(sponsorshipId, fileData, fileName, fileType) {
        // حفظ الملف محلياً أولاً
        const file = {
            sponsorship_id: sponsorshipId,
            file_name: fileName,
            file_type: fileType,
            file_data: fileData,
            uploaded: false,
            created_at: new Date().toISOString()
        };

        const fileId = await this.dbPut('files', file);
        file.id = fileId;

        console.log('💾 تم حفظ الملف محلياً:', fileName);

        // إضافة للطابور للرفع التلقائي
        await this.addToAutoUploadQueue(file);

        return fileId;
    },

    // الحصول على حالة الشبكة
    isOnline() {
        return this.networkStatus;
    },

    // الحصول على حجم طابور الرفع
    getAutoUploadQueueSize() {
        return this.autoUploadQueue.length;
    },

    // تمكين/تعطيل الرفع التلقائي
    setAutoUploadEnabled(enabled) {
        this.autoUploadEnabled = enabled;
        // ⚠️ حفظ الإعداد في localStorage للحفاظ عليه بين الجلسات
        try {
            localStorage.setItem('autoUploadEnabled', JSON.stringify(enabled));
        } catch (e) {
            console.warn('فشل حفظ إعداد الرفع التلقائي:', e);
        }
        console.log(`🔧 الرفع التلقائي: ${enabled ? 'مفعل' : 'معطل'}`);

        if (enabled && this.networkStatus) {
            this.processAutoUploadQueue();
        } else if (!enabled) {
            // إيقاف خدمة الخلفية عند تعطيل الرفع التلقائي
            this.stopBackgroundService();
            console.log('⏹️ تم إيقاف خدمة الخلفية لأن الرفع التلقائي معطل');
        }
    },

    // قراءة إعداد الرفع التلقائي من localStorage
    loadAutoUploadSetting() {
        try {
            const saved = localStorage.getItem('autoUploadEnabled');
            if (saved !== null) {
                this.autoUploadEnabled = JSON.parse(saved);
                console.log(`📥 تم تحميل إعداد الرفع التلقائي: ${this.autoUploadEnabled ? 'مفعل' : 'معطل'}`);
            }
        } catch (e) {
            console.warn('فشل قراءة إعداد الرفع التلقائي:', e);
        }
    }
};

// تهيئة عند التحميل
if (typeof window !== 'undefined') {
    window.SyncService = SyncService;

    // تسجيل مستمع للخروج من التطبيق
    document.addEventListener('pause', async () => {
        console.log('App paused - background mode activated');
        SyncService.isAppInBackground = true;
        // بدء المزامنة في الخلفية فقط إذا كان هناك عمليات جارية والرفع التلقائي مفعل
        if (SyncService.autoUploadEnabled && (SyncService.syncInProgress || SyncService.uploadInProgress || SyncService.autoUploadInProgress)) {
            await SyncService.startBackgroundSync();
        }
    });

    document.addEventListener('resume', () => {
        console.log('App resumed');
        SyncService.isAppInBackground = false;
        // تحقق من طابور الرفع عند العودة فقط إذا كان الرفع التلقائي مفعلاً
        if (SyncService.autoUploadEnabled) {
            SyncService.processAutoUploadQueue();
        }
    });

    // تهيئة نظام الرفع التلقائي عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', () => {
        // تهيئة نظام الرفع التلقائي بعد تهيئة SyncService
        // ⚠️ ملاحظة: initAutoUpload ستقرأ إعداد autoUploadEnabled من localStorage
        // ولن تبدأ أي عمليات إلا إذا كان الإعداد مفعلاً
        setTimeout(() => {
            if (SyncService.db) {
                // قراءة الإعداد أولاً قبل التهيئة
                SyncService.loadAutoUploadSetting();
                // تهيئة النظام (بدون بدء عمليات تلقائية إذا كان معطلاً)
                SyncService.initAutoUpload();
            }
        }, 1000);
    });
}

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SyncService;
}

