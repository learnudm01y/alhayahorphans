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
    baseUrl: 'https://alhayahorphans.org/api',
    token: null,
    user: null,
    dbName: 'AlhayahSponsorshipsDB',
    dbVersion: 3, // ترقية الإصدار لإضافة جدول أنواع الكفالة
    db: null,

    // حالة المزامنة (للعمل في الخلفية)
    syncInProgress: false,
    uploadInProgress: false,
    LocalNotifications: null,

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

        // فتح قاعدة البيانات المحلية
        await this.openDatabase();

        // تهيئة الإشعارات المحلية
        await this.initNotifications();

        // تهيئة العمل في الخلفية
        await this.initBackgroundTask();
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

    // إرسال إشعار محلي
    async sendNotification(title, body, progress = null) {
        try {
            if (!this.LocalNotifications) return;

            const notificationOptions = {
                notifications: [{
                    id: progress !== null ? 1 : Math.floor(Math.random() * 100000),
                    title: title,
                    body: body,
                    channelId: 'sync_channel',
                    ongoing: progress !== null && progress < 100,
                    autoCancel: progress === null || progress >= 100
                }]
            };

            await this.LocalNotifications.schedule(notificationOptions);
        } catch (error) {
            console.log('Failed to send notification:', error.message);
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
            ...options.headers
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, { ...options, headers });
            const data = await response.json();

            if (response.status === 401) {
                this.logout();
                throw new Error('انتهت صلاحية الجلسة');
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
        // التحقق من الاتصال بالإنترنت
        if (!navigator.onLine) {
            // محاولة تسجيل الدخول من البيانات المحفوظة
            return await this.offlineLogin(username, password);
        }

        try {
            const data = await this.request('/mobile/login', {
                method: 'POST',
                body: JSON.stringify({ username, password, device_id: deviceId })
            });

            if (data.success) {
                this.token = data.token;
                this.user = data.user;
                localStorage.setItem('auth_token', data.token);
                localStorage.setItem('user_data', JSON.stringify(data.user));
                localStorage.setItem('token_expires', data.expires_at);

                // حفظ بيانات الاعتماد للدخول Offline (مشفرة)
                await this.saveCredentials(username, password, data.user);
            }

            return data;

        } catch (error) {
            // إذا فشل الاتصال، حاول الدخول Offline
            console.log('Online login failed, trying offline:', error.message);
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
            const savedCredentials = await this.dbGet('sync_meta', 'user_credentials');

            if (!savedCredentials) {
                return {
                    success: false,
                    message: 'لا توجد بيانات محفوظة. يجب تسجيل الدخول عبر الإنترنت أولاً'
                };
            }

            // التحقق من اسم المستخدم
            if (savedCredentials.username !== username) {
                return {
                    success: false,
                    message: 'اسم المستخدم غير صحيح'
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

            console.log('Offline login successful');

            return {
                success: true,
                message: 'تم تسجيل الدخول (وضع عدم الاتصال)',
                user: savedCredentials.user,
                offline: true
            };

        } catch (error) {
            console.error('Offline login error:', error);
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
        // لا نحذف credentials حتى يمكن الدخول مرة أخرى offline
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

            // إرسال إشعار بدء المزامنة
            await this.sendNotification('جاري المزامنة...', 'يتم تحميل البيانات الأساسية', 0);

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
            return { success: false, message: result.message };

        } catch (error) {
            console.error('Initial sync failed:', error);
            this.syncInProgress = false;
            await this.sendNotification('❌ فشلت المزامنة', error.message);
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
                // حفظ الكفالات محلياً
                for (const sponsorship of result.data) {
                    await this.dbPut('sponsorships', sponsorship);
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

            // إرسال إشعار بدء المزامنة
            await this.sendNotification('جاري مزامنة الكفالات...', 'يتم جلب البيانات من الخادم', 0);

            // جلب آخر وقت مزامنة
            const lastSyncMeta = await this.dbGet('sync_meta', 'last_full_sync');
            const lastSync = lastSyncMeta ? lastSyncMeta.value : null;

            while (hasMore) {
                const params = new URLSearchParams();
                params.append('page', page);
                params.append('per_page', 100);
                if (lastSync) {
                    params.append('last_sync', lastSync);
                }

                const result = await this.request(`/mobile/sync/full?${params.toString()}`);

                if (result.success) {
                    // حفظ الكفالات
                    for (const sponsorship of result.data) {
                        await this.dbPut('sponsorships', sponsorship);
                    }

                    // حذف الكفالات المستبعدة (تم الصرف / أرسل للصرف)
                    if (result.deleted_ids && result.deleted_ids.length > 0) {
                        for (const id of result.deleted_ids) {
                            await this.dbDelete('sponsorships', id);
                        }
                    }

                    totalDownloaded += result.data.length;
                    totalRecords = result.pagination.total;
                    hasMore = result.pagination.has_more;
                    page++;

                    // تحديث التقدم
                    const progress = totalRecords > 0 ? Math.round((totalDownloaded / totalRecords) * 100) : 0;

                    // إرسال إشعار التقدم
                    await this.sendNotification('جاري مزامنة الكفالات...', `تم تنزيل ${totalDownloaded} من ${totalRecords}`, progress);

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

            // إشعار اكتمال المزامنة
            await this.sendNotification('✅ اكتملت المزامنة', `تم تنزيل ${totalDownloaded} كفالة بنجاح`);

            return {
                success: true,
                downloaded: totalDownloaded,
                total: totalRecords,
                message: `تم تنزيل ${totalDownloaded} كفالة بنجاح`
            };

        } catch (error) {
            console.error('Full sync failed:', error);
            this.syncInProgress = false;
            await this.sendNotification('❌ فشلت المزامنة', error.message);
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

    async getLocalSponsorship(id) {
        return await this.dbGet('sponsorships', id);
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
        // حفظ التغيير محلياً
        const sponsorship = await this.dbGet('sponsorships', sponsorshipId);
        if (sponsorship) {
            const updated = { ...sponsorship, ...updates, _locallyModified: true };
            await this.dbPut('sponsorships', updated);
        }

        // إضافة للتغييرات المعلقة
        await this.dbPut('pending_uploads', {
            sponsorship_id: sponsorshipId,
            updates: updates,
            created_at: new Date().toISOString()
        });
    },

    async uploadPendingChanges() {
        const pending = await this.dbGetAll('pending_uploads');
        const results = { success: 0, failed: 0, errors: [] };

        if (pending.length === 0) {
            return results;
        }

        this.uploadInProgress = true;
        const totalItems = pending.length;
        let processedItems = 0;

        // إرسال إشعار بدء الرفع
        await this.sendNotification('جاري رفع التعديلات...', `${totalItems} تعديل معلق`, 0);

        for (const change of pending) {
            try {
                await this.request('/mobile/sync/upload', {
                    method: 'POST',
                    body: JSON.stringify({
                        sponsorship_id: change.sponsorship_id,
                        updates: change.updates
                    })
                });

                // حذف من المعلقة
                await this.dbDelete('pending_uploads', change.id);

                // إزالة علامة التعديل المحلي
                const sponsorship = await this.dbGet('sponsorships', change.sponsorship_id);
                if (sponsorship) {
                    delete sponsorship._locallyModified;
                    await this.dbPut('sponsorships', sponsorship);
                }

                results.success++;
                processedItems++;

                // تحديث الإشعار بالتقدم
                const progress = Math.round((processedItems / totalItems) * 100);
                await this.sendNotification('جاري رفع التعديلات...', `تم رفع ${processedItems} من ${totalItems}`, progress);

            } catch (error) {
                results.failed++;
                results.errors.push({
                    sponsorship_id: change.sponsorship_id,
                    error: error.message
                });
                processedItems++;
            }
        }

        this.uploadInProgress = false;

        // إشعار اكتمال الرفع
        if (results.failed === 0) {
            await this.sendNotification('✅ اكتمل الرفع', `تم رفع ${results.success} تعديل بنجاح`);
        } else {
            await this.sendNotification('⚠️ اكتمل الرفع مع أخطاء', `نجح: ${results.success} | فشل: ${results.failed}`);
        }

        return results;
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
            detailedList.push({
                id: item.id,
                sponsorship_id: item.sponsorship_id,
                orphan_name: sponsorship?.orphan_full_name || sponsorship?.name || 'غير معروف',
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
    }
};

// تهيئة عند التحميل
if (typeof window !== 'undefined') {
    window.SyncService = SyncService;

    // تسجيل مستمع للخروج من التطبيق
    document.addEventListener('pause', async () => {
        console.log('App paused - background mode activated');
        if (SyncService.syncInProgress || SyncService.uploadInProgress) {
            await SyncService.startBackgroundSync();
        }
    });

    document.addEventListener('resume', () => {
        console.log('App resumed');
    });
}

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SyncService;
}
