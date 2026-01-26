/**
 * Sponsorship Sync Service - PRODUCTION ONLINE VERSION
 * نسخة تتصل بالسيرفر الحقيقي - SponsorshipSyncController
 * VERSION: 2026-01-23-PRODUCTION-ONLINE
 *
 * يتصل بـ: https://alhayahorphans.org/api/mobile (SponsorshipSyncController)
 */

console.log('🚀🚀🚀 LOADING SYNC SERVICE - PRODUCTION ONLINE VERSION 🚀🚀🚀');

const SyncService = {
    // ========================================
    // إعدادات الاتصال - PRODUCTION ONLINE
    // ========================================

    // عنوان السيرفر الحقيقي - SponsorshipSyncController
    baseUrl: 'https://alhayahorphans.org/api/mobile',

    token: null,
    user: null,
    dbName: 'AlhayahSponsorshipsOfflineDB',
    dbVersion: 3,
    db: null,

    // حالة المزامنة
    syncInProgress: false,
    uploadInProgress: false,
    isAppInBackground: false,

    // Android Background Service Support
    androidServiceActive: false,

    // Progress Notification
    progressNotificationId: 1000,
    currentProgressPercent: 0,

    // حالة التهيئة
    initPromise: null,
    isInitialized: false,

    // ========================================
    // التهيئة
    // ========================================
    async init() {
        // إذا كانت التهيئة قيد التنفيذ، انتظرها
        if (this.initPromise) {
            return this.initPromise;
        }

        // إذا تمت التهيئة بالفعل
        if (this.isInitialized && this.db) {
            return this;
        }

        this.initPromise = this._doInit();
        return this.initPromise;
    },

    async _doInit() {
        console.log('🔧 Initializing SyncService (REAL DATABASE MODE)');
        console.log('📡 API Base URL:', this.baseUrl);

        // فتح قاعدة البيانات المحلية
        await this.openDatabase();

        // استرجاع التوكن والمستخدم من التخزين
        this.token = localStorage.getItem('auth_token');
        const savedUser = localStorage.getItem('auth_user');
        if (savedUser) {
            try {
                this.user = JSON.parse(savedUser);
            } catch (e) {
                this.user = null;
            }
        }

        this.isInitialized = true;
        console.log('✅ SyncService initialized');
        return this;
    },

    // التأكد من التهيئة قبل أي عملية
    async ensureInitialized() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
    },

    // ========================================
    // قاعدة البيانات المحلية (IndexedDB)
    // ========================================
    async openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = (event) => {
                console.error('❌ Database error:', event.target.error);
                reject(event.target.error);
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                console.log('✅ Database opened successfully');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                console.log('📦 Upgrading database schema...');

                // جداول البيانات المرجعية
                const stores = [
                    'sponsors',
                    'statuses',
                    'health_statuses',
                    'cities',
                    'banks',
                    'sponsorship_types',
                    'sponsorships',
                    'pending_changes',
                    'pending_files',
                    'sync_meta'
                ];

                stores.forEach(storeName => {
                    if (!db.objectStoreNames.contains(storeName)) {
                        db.createObjectStore(storeName, { keyPath: 'id', autoIncrement: storeName === 'pending_changes' || storeName === 'pending_files' });
                    }
                });
            };
        });
    },

    // عمليات قاعدة البيانات
    async dbPut(storeName, data) {
        await this.ensureInitialized();
        return new Promise((resolve, reject) => {
            if (!this.db) {
                console.error('❌ قاعدة البيانات غير متاحة');
                reject(new Error('قاعدة البيانات غير متاحة'));
                return;
            }

            // التحقق من وجود object store
            if (!this.db.objectStoreNames.contains(storeName)) {
                console.error(`❌ Object store '${storeName}' غير موجود`);
                reject(new Error(`Object store '${storeName}' غير موجود`));
                return;
            }

            try {
                const transaction = this.db.transaction([storeName], 'readwrite');
                transaction.onerror = (event) => {
                    console.error(`❌ خطأ في transaction لـ ${storeName}:`, event);
                    reject(new Error('فشل في معاملة قاعدة البيانات'));
                };

                const store = transaction.objectStore(storeName);
                const request = store.put(data);

                request.onsuccess = () => {
                    console.log(`✅ تم حفظ البيانات في ${storeName} بنجاح، ID:`, data.id);
                    resolve(request.result);
                };

                request.onerror = () => {
                    console.error(`❌ خطأ في حفظ البيانات في ${storeName}:`, request.error);
                    reject(request.error);
                };
            } catch (error) {
                console.error(`❌ استثناء في dbPut لـ ${storeName}:`, error);
                reject(error);
            }
        });
    },

    async dbGet(storeName, key) {
        await this.ensureInitialized();
        return new Promise((resolve, reject) => {
            if (!this.db) {
                resolve(null);
                return;
            }
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    async dbGetAll(storeName) {
        await this.ensureInitialized();
        return new Promise((resolve, reject) => {
            if (!this.db) {
                resolve([]);
                return;
            }
            // التحقق من وجود object store
            if (!this.db.objectStoreNames.contains(storeName)) {
                console.warn(`⚠️ Object store '${storeName}' لا يوجد`);
                resolve([]);
                return;
            }
            try {
                const transaction = this.db.transaction([storeName], 'readonly');
                const store = transaction.objectStore(storeName);
                const request = store.getAll();
                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => reject(request.error);
            } catch (error) {
                console.error(`❌ خطأ في الوصول لـ ${storeName}:`, error);
                resolve([]);
            }
        });
    },

    async dbDelete(storeName, key) {
        await this.ensureInitialized();
        return new Promise((resolve, reject) => {
            if (!this.db) {
                resolve();
                return;
            }
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },

    async dbClear(storeName) {
        await this.ensureInitialized();
        return new Promise((resolve, reject) => {
            if (!this.db) {
                resolve();
                return;
            }
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },

    async dbCount(storeName) {
        await this.ensureInitialized();
        return new Promise((resolve, reject) => {
            if (!this.db) {
                resolve(0);
                return;
            }
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.count();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    // ========================================
    // إدارة الملفات والصور
    // ========================================
    async saveFile(sponsorshipId, fileData, fileName, mimeType = 'image/jpeg', category = 'general') {
        try {
            await this.ensureInitialized();

            const fileRecord = {
                id: Date.now() + '_' + Math.random().toString(36).substring(7),
                sponsorship_id: sponsorshipId,
                fileName: fileName,
                mimeType: mimeType,
                category: category,
                fileData: fileData,
                timestamp: new Date().toISOString(),
                uploaded: false,
                size: fileData.length || 0
            };

            await this.dbPut('pending_files', fileRecord);
            console.log('✅ تم حفظ الملف:', fileName);
            return fileRecord;
        } catch (error) {
            console.error('❌ خطأ في حفظ الملف:', error);
            throw error;
        }
    },

    async saveFileWithAutoUpload(sponsorshipId, fileData, fileName, mimeType = 'image/jpeg') {
        try {
            // حفظ الملف محلياً أولاً
            const fileRecord = await this.saveFile(sponsorshipId, fileData, fileName, mimeType, 'auto_upload');

            // محاولة رفع فوري إذا كان هناك اتصال
            if (navigator.onLine) {
                this.uploadFileToServer(fileRecord).catch(error => {
                    console.warn('فشل الرفع الفوري، سيتم المحاولة لاحقاً:', error.message);
                });
            }

            return fileRecord;
        } catch (error) {
            console.error('❌ خطأ في حفظ الملف مع الرفع التلقائي:', error);
            throw error;
        }
    },

    async uploadFileToServer(fileRecord) {
        try {
            if (!this.token) {
                throw new Error('المستخدم غير مسجل دخول');
            }

            const response = await this.apiRequest('/upload-file', {
                method: 'POST',
                body: {
                    sponsorship_id: fileRecord.sponsorship_id,
                    file_name: fileRecord.fileName,
                    file_type: fileRecord.mimeType,
                    file_data: fileRecord.fileData,
                    category: fileRecord.category || 'general'
                }
            });

            if (response.success) {
                // تحديث حالة الملف كمرفوع
                await this.dbDelete('pending_files', fileRecord.id);
                console.log('✅ تم رفع الملف بنجاح:', fileRecord.fileName);
                return response;
            } else {
                throw new Error(response.message || 'فشل رفع الملف');
            }

        } catch (error) {
            console.error('❌ فشل رفع الملف:', error);
            throw error;
        }
    },

    // ========================================
    // إدارة التخزين المحلي للعمل offline
    // ========================================
    async ensureOfflineDataIntegrity() {
        try {
            await this.ensureInitialized();

            // التحقق من وجود قاعدة البيانات أولاً
            if (!this.db) {
                console.warn('⚠️ قاعدة البيانات غير مهيأة - إعادة التهيئة...');
                await this.openDatabase();
                if (!this.db) {
                    console.error('❌ فشل في تهيئة قاعدة البيانات');
                    return false;
                }
            }

            // فحص وجود البيانات الأساسية
            const sponsors = await this.dbGetAll('sponsors') || [];
            const statuses = await this.dbGetAll('statuses') || [];
            const cities = await this.dbGetAll('cities') || [];
            const banks = await this.dbGetAll('banks') || [];
            const healthStatuses = await this.dbGetAll('health_statuses') || [];
            const sponsorships = await this.dbGetAll('sponsorships') || [];

            const missingData = [];
            if (sponsors.length === 0) missingData.push('sponsors');
            if (statuses.length === 0) missingData.push('statuses');
            if (cities.length === 0) missingData.push('cities');
            if (banks.length === 0) missingData.push('banks');
            if (healthStatuses.length === 0) missingData.push('health_statuses');

            if (missingData.length > 0) {
                console.warn('⚠️ بيانات مرجعية مفقودة:', missingData);
                return false;
            }

            console.log('✅ بيانات offline مكتملة:', {
                sponsors: sponsors.length,
                sponsorships: sponsorships.length,
                statuses: statuses.length,
                cities: cities.length,
                banks: banks.length,
                healthStatuses: healthStatuses.length
            });
            return true;
        } catch (error) {
            console.error('❌ خطأ في فحص بيانات offline:', error);

            // إذا كان الخطأ متعلق بـ object stores، جرب إعادة إنشاء قاعدة البيانات
            if (error.message && error.message.includes('object stores')) {
                console.warn('🔄 محاولة إعادة إنشاء قاعدة البيانات...');
                try {
                    // مسح قاعدة البيانات القديمة
                    if (this.db) {
                        this.db.close();
                        this.db = null;
                    }
                    // زيادة رقم الإصدار وإعادة الإنشاء
                    this.dbVersion = this.dbVersion + 1;
                    await this.openDatabase();
                    console.log('✅ تم إعادة إنشاء قاعدة البيانات');
                    return false; // ارجع false للإشارة أن البيانات تحتاج تحميل
                } catch (recreateError) {
                    console.error('❌ فشل في إعادة إنشاء قاعدة البيانات:', recreateError);
                }
            }
            return false;
        }
    },

    // ========================================
    // HTTP Requests
    // ========================================
    async apiRequest(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            console.log(`📡 API Request: ${options.method || 'GET'} ${url}`);

            const response = await fetch(url, {
                method: options.method || 'GET',
                headers,
                body: options.body ? JSON.stringify(options.body) : undefined
            });

            const data = await response.json();

            if (!response.ok) {
                console.error(`❌ API Error:`, data);
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            console.log(`✅ API Response:`, data);
            return data;

        } catch (error) {
            console.error(`❌ API Request Failed:`, error);
            throw error;
        }
    },

    // ========================================
    // المصادقة
    // ========================================
    async login(username, password) {
        console.log('🔐 ========================================');
        console.log('🔐 بدء عملية تسجيل الدخول');
        console.log('🔐 اسم المستخدم:', username);
        console.log('🔐 عنوان الخادم:', this.baseUrl);
        console.log('🔐 ========================================');

        // دائماً نحاول الاتصال بالخادم أولاً
        try {
            console.log('📡 إرسال طلب تسجيل الدخول للخادم...');

            const response = await this.apiRequest('/login', {
                method: 'POST',
                body: { username, password }
            });

            console.log('📡 استجابة الخادم:', response);

            if (response.success) {
                this.token = response.token;
                this.user = response.user;

                // حفظ في التخزين المحلي
                localStorage.setItem('auth_token', this.token);
                localStorage.setItem('auth_user', JSON.stringify(this.user));
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('username', this.user.name);

                // حفظ بيانات الاعتماد للوضع غير المتصل
                await this.dbPut('sync_meta', {
                    id: 'user_credentials',
                    username: username,
                    user: this.user,
                    savedAt: new Date().toISOString()
                });

                console.log('✅ تم تسجيل الدخول بنجاح:', this.user.name);

                return {
                    success: true,
                    user: this.user,
                    message: 'تم تسجيل الدخول بنجاح',
                    source: 'server'
                };
            } else {
                console.log('❌ رفض الخادم تسجيل الدخول:', response.message);
                return {
                    success: false,
                    message: response.message || 'فشل تسجيل الدخول'
                };
            }

        } catch (error) {
            console.error('❌ خطأ في الاتصال بالخادم:', error.message);
            console.log('🔄 الخادم غير متاح، محاولة الدخول من الذاكرة المؤقتة...');

            // فقط في حالة فشل الاتصال (وليس رفض كلمة المرور)
            // نحاول الدخول من الذاكرة المؤقتة
            const offlineResult = await this.offlineLogin(username, password);
            return offlineResult;
        }
    },

    async offlineLogin(username, password) {
        console.log('🔄 محاولة تسجيل الدخول من الذاكرة المؤقتة...');
        console.log('🔄 اسم المستخدم المطلوب:', username);

        const savedCredentials = await this.dbGet('sync_meta', 'user_credentials');

        console.log('🔄 بيانات محفوظة:', savedCredentials ? {
            username: savedCredentials.username,
            userId: savedCredentials.user?.id,
            savedAt: savedCredentials.savedAt
        } : 'لا توجد');

        if (!savedCredentials) {
            console.log('❌ لا توجد بيانات اعتماد محفوظة');
            return {
                success: false,
                message: 'لا توجد بيانات دخول محفوظة - يجب الاتصال بالخادم أولاً'
            };
        }

        // التحقق من تطابق اسم المستخدم
        if (savedCredentials.username !== username) {
            console.log('❌ اسم المستخدم غير متطابق');
            console.log('   المحفوظ:', savedCredentials.username);
            console.log('   المُدخل:', username);
            return {
                success: false,
                message: `اسم المستخدم غير صحيح (الحساب المحفوظ: ${savedCredentials.username})`
            };
        }

        // ⚠️ ملاحظة: في وضع offline لا يمكن التحقق من كلمة المرور
        // لأن كلمة المرور مشفرة على الخادم فقط
        console.log('⚠️ وضع offline - لا يمكن التحقق من كلمة المرور');
        console.log('✅ السماح بالدخول للمستخدم المحفوظ:', savedCredentials.username);

        this.user = savedCredentials.user;
        this.token = 'offline-token-' + Date.now();

        localStorage.setItem('auth_token', this.token);
        localStorage.setItem('auth_user', JSON.stringify(this.user));
        localStorage.setItem('isLoggedIn', 'true');

        return {
            success: true,
            user: this.user,
            offline: true,
            source: 'cache',
            message: 'تم تسجيل الدخول (وضع عدم الاتصال) ⚠️'
        };
    },

    async logout() {
        this.token = null;
        this.user = null;

        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('username');

        console.log('👋 Logged out');
        return { success: true };
    },

    isAuthenticated() {
        return !!(this.token && this.user);
    },

    async hasOfflineCredentials() {
        try {
            const credentials = await this.dbGet('sync_meta', 'user_credentials');
            return !!credentials;
        } catch {
            return false;
        }
    },

    // ========================================
    // فحص صحة الاتصال
    // ========================================
    async checkHealth() {
        try {
            const response = await this.apiRequest('/health');
            return response;
        } catch (error) {
            return { status: 'unhealthy', error: error.message };
        }
    },

    setBaseUrl(url) {
        // السماح بتغيير الـ baseUrl للجهاز الحقيقي
        if (url && url.startsWith('http')) {
            this.baseUrl = url;
            localStorage.setItem('custom_api_url', url);
            console.log('📡 Base URL changed to:', this.baseUrl);
        } else {
            console.log('📡 Base URL remains:', this.baseUrl);
        }
    },

    // استرجاع URL محفوظ مسبقاً
    loadSavedBaseUrl() {
        const savedUrl = localStorage.getItem('custom_api_url');
        if (savedUrl) {
            this.baseUrl = savedUrl;
            console.log('📡 Loaded saved Base URL:', this.baseUrl);
        }
    },

    // ========================================
    // المزامنة الأولية - جلب البيانات المرجعية
    // ========================================
    async performInitialSync() {
        console.log('🔄 Starting initial sync from REAL database...');

        try {
            // التحقق من وجود البيانات المرجعية - بما في ذلك أنواع الكفالة
            const existingSponsors = await this.dbGetAll('sponsors');
            const existingStatuses = await this.dbGetAll('statuses');
            const existingTypes = await this.dbGetAll('sponsorship_types');

            // إذا كانت البيانات الأساسية موجودة، لا نحذف شيء - ترجع العدد الفعلي
            if (existingSponsors.length > 0 && existingStatuses.length > 0) {
                const healthStatuses = await this.dbGetAll('health_statuses');
                const cities = await this.dbGetAll('cities');
                const banks = await this.dbGetAll('banks');

                console.log('✅ البيانات المرجعية موجودة بالفعل - تخطي إعادة التحميل');
                console.log('📦 أنواع الكفالات المحفوظة:', existingTypes.length);

                return {
                    success: true,
                    sponsors_count: existingSponsors.length,
                    statuses_count: existingStatuses.length,
                    health_statuses_count: healthStatuses.length,
                    cities_count: cities.length,
                    banks_count: banks.length,
                    sponsorship_types_count: existingTypes.length,
                    message: 'استخدام البيانات المرجعية الموجودة'
                };
            }

            console.log('📥 تحميل البيانات المرجعية من الخادم...');

            const response = await this.apiRequest('/sync/initial');

            if (response.success) {
                const data = response.data;

                // حفظ في قاعدة البيانات المحلية - فقط عند عدم وجود البيانات
                await this.dbClear('sponsors');
                await this.dbClear('statuses');
                await this.dbClear('health_statuses');
                await this.dbClear('cities');
                await this.dbClear('banks');
                await this.dbClear('sponsorship_types');

                for (const sponsor of data.sponsors) {
                    await this.dbPut('sponsors', sponsor);
                }
                for (const status of data.statuses) {
                    await this.dbPut('statuses', status);
                }
                for (const hs of data.health_statuses) {
                    await this.dbPut('health_statuses', hs);
                }
                for (const city of data.cities) {
                    await this.dbPut('cities', city);
                }
                for (const bank of data.banks) {
                    await this.dbPut('banks', bank);
                }
                // حفظ أنواع الكفالات
                if (data.sponsorship_types && data.sponsorship_types.length > 0) {
                    for (const type of data.sponsorship_types) {
                        await this.dbPut('sponsorship_types', type);
                    }
                    console.log(`✅ تم حفظ ${data.sponsorship_types.length} نوع كفالة`);
                }

                // حفظ وقت المزامنة
                await this.dbPut('sync_meta', {
                    id: 'last_initial_sync',
                    timestamp: new Date().toISOString(),
                    counts: response.counts
                });

                // حفظ بيانات المستخدم للدخول offline
                if (this.user) {
                    await this.dbPut('sync_meta', {
                        id: 'user_credentials',
                        username: this.user.name,
                        user: this.user
                    });
                }

                console.log('✅ Initial sync completed:', response.counts);

                return {
                    success: true,
                    sponsors_count: data.sponsors?.length || 0,
                    statuses_count: data.statuses?.length || 0,
                    health_statuses_count: data.health_statuses?.length || 0,
                    cities_count: data.cities?.length || 0,
                    banks_count: data.banks?.length || 0,
                    sponsorship_types_count: data.sponsorship_types?.length || 0,
                    counts: response.counts
                };
            }

            return { success: false, message: 'فشل المزامنة الأولية' };

        } catch (error) {
            console.error('❌ Initial sync failed:', error);
            return {
                success: false,
                message: 'فشل المزامنة: ' + error.message
            };
        }
    },

    // ========================================
    // مزامنة الكفالات
    // ========================================
    async performFullSync(progressCallback = null, showNotificationProgress = false) {
        console.log('🔄 Starting full sponsorships sync from REAL database...');

        try {
            let page = 1;
            let hasMore = true;
            let totalDownloaded = 0;
            let allSponsorships = [];
            let lastPage = null;

            // إحصائيات تفصيلية للـ logging
            let statsWithGuardianData = 0;
            let statsWithBankAccounts = 0;
            let statsWithSponsoredName = 0;

            await this.dbClear('sponsorships');

            // إظهار Progress Notification إذا مطلوب
            if (showNotificationProgress) {
                await this.showProgressNotification('جاري المزامنة...', 0, 'بدء التحميل...');
            }

            while (hasMore) {
                const response = await this.apiRequest(`/sponsorships?page=${page}&per_page=100`);

                if (response.success) {
                    // تحديث lastPage من pagination
                    if (response.pagination && response.pagination.last_page) {
                        lastPage = response.pagination.last_page;
                    }

                    // حساب النسبة المئوية
                    const progressPercent = lastPage ? ((page / lastPage) * 100) : ((totalDownloaded / 100) * 100);

                    // Progress callback مع معلومات كاملة
                    if (progressCallback) {
                        progressCallback({
                            progress: progressPercent / 100,
                            downloaded: totalDownloaded,
                            total: response.pagination?.total || totalDownloaded,
                            page: page,
                            lastPage: lastPage || page
                        });
                    }

                    // تحديث Progress Notification
                    if (showNotificationProgress) {
                        await this.updateProgressNotification(
                            progressPercent,
                            `صفحة ${page}/${lastPage || page} - ${totalDownloaded} كفالة`
                        );
                    }

                    for (const sponsorship of response.sponsorships) {
                        await this.dbPut('sponsorships', sponsorship);
                        allSponsorships.push(sponsorship);

                        // تسجيل تفصيلي لبيانات المعيل
                        const hasGuardianFirstName = !!sponsorship.guardian_first_name;
                        const hasBankAccounts = sponsorship.bank_accounts && sponsorship.bank_accounts.length > 0;
                        const hasSponsoredName = !!sponsorship.first_name;

                        if (hasGuardianFirstName) statsWithGuardianData++;
                        if (hasBankAccounts) statsWithBankAccounts++;
                        if (hasSponsoredName) statsWithSponsoredName++;

                        // تسجيل مفصل لكل كفالة بها بيانات
                        if (hasGuardianFirstName || hasBankAccounts) {
                            console.log(`📋 كفالة ${sponsorship.id}:`, {
                                orphan_name: sponsorship.orphan_name,
                                guardian_name: sponsorship.guardian_name,
                                guardian_first_name: sponsorship.guardian_first_name,
                                guardian_father_name: sponsorship.guardian_father_name,
                                guardian_phone: sponsorship.guardian_phone,
                                sponsored_first_name: sponsorship.first_name,
                                sponsored_gender: sponsorship.orphan_gender,
                                bank_accounts_count: sponsorship.bank_accounts?.length || 0
                            });
                        }
                    }

                    totalDownloaded += response.sponsorships.length;
                    hasMore = response.pagination.has_more;
                    page++;

                    console.log(`📥 Downloaded page ${page - 1}: ${response.sponsorships.length} sponsorships`);
                } else {
                    hasMore = false;
                }
            }

            // حفظ وقت المزامنة
            await this.dbPut('sync_meta', {
                id: 'last_full_sync',
                timestamp: new Date().toISOString(),
                total: totalDownloaded
            });

            if (progressCallback) {
                progressCallback({
                    progress: 1,
                    downloaded: totalDownloaded,
                    total: totalDownloaded,
                    page: lastPage || page,
                    lastPage: lastPage || page,
                    complete: true
                });
            }

            // تحديث إلى 100%
            if (showNotificationProgress) {
                await this.updateProgressNotification(100, `تم تحميل ${totalDownloaded} كفالة`);
                // إخفاء بعد ثانيتين
                setTimeout(() => this.hideProgressNotification(), 2000);
            }

            // تسجيل الإحصائيات النهائية
            console.log('📊 ===== إحصائيات المزامنة =====');
            console.log(`✅ إجمالي الكفالات: ${totalDownloaded}`);
            console.log(`👤 كفالات ببيانات معيل: ${statsWithGuardianData} (${((statsWithGuardianData/totalDownloaded)*100).toFixed(1)}%)`);
            console.log(`🏦 كفالات بحسابات بنكية: ${statsWithBankAccounts} (${((statsWithBankAccounts/totalDownloaded)*100).toFixed(1)}%)`);
            console.log(`👶 كفالات باسم مكفول: ${statsWithSponsoredName} (${((statsWithSponsoredName/totalDownloaded)*100).toFixed(1)}%)`);
            console.log('=================================');

            return {
                success: true,
                downloaded: totalDownloaded,
                stats: {
                    with_guardian_data: statsWithGuardianData,
                    with_bank_accounts: statsWithBankAccounts,
                    with_sponsored_name: statsWithSponsoredName
                }
            };

        } catch (error) {
            console.error('❌ Full sync failed:', error);

            // إخفاء Progress Notification عند الخطأ
            if (showNotificationProgress) {
                await this.hideProgressNotification();
            }

            return {
                success: false,
                message: 'فشل مزامنة الكفالات: ' + error.message
            };
        }
    },

    // مزامنة مع فلترة
    async syncSponsorships(sponsorId = null, statusId = null) {
        console.log('🔄 Syncing sponsorships with filters...', { sponsorId, statusId });

        try {
            let url = '/sponsorships?per_page=1000';
            if (sponsorId) url += `&sponsor_id=${sponsorId}`;
            if (statusId) url += `&status_id=${statusId}`;

            const response = await this.apiRequest(url);

            if (response.success) {
                // تحديث الكفالات المحلية
                for (const sponsorship of response.sponsorships) {
                    await this.dbPut('sponsorships', sponsorship);
                }

                return {
                    success: true,
                    count: response.sponsorships.length
                };
            }

            return { success: false };

        } catch (error) {
            console.error('❌ Sync sponsorships failed:', error);
            return { success: false, error: error.message };
        }
    },

    // ========================================
    // جلب البيانات المحلية
    // ========================================
    async getLocalSponsorships(options = {}) {
        let sponsorships = await this.dbGetAll('sponsorships');

        // تطبيق الفلاتر - دعم كل من sponsorId و sponsor_id
        if (options.sponsorId || options.sponsor_id) {
            const sponsorId = options.sponsorId || options.sponsor_id;
            sponsorships = sponsorships.filter(s => s.sponsor_id == sponsorId);
            console.log(`🔍 فلترة حسب الجمعية ${sponsorId}: ${sponsorships.length} نتيجة`);
        }
        if (options.statusId || options.status_id) {
            const statusId = options.statusId || options.status_id;
            sponsorships = sponsorships.filter(s => s.sponsorship_status_id == statusId);
            console.log(`🔍 فلترة حسب الحالة ${statusId}: ${sponsorships.length} نتيجة`);
        }
        if (options.search) {
            const searchLower = options.search.toLowerCase();
            sponsorships = sponsorships.filter(s =>
                (s.orphan_name && s.orphan_name.toLowerCase().includes(searchLower)) ||
                (s.guardian_name && s.guardian_name.toLowerCase().includes(searchLower)) ||
                (s.identity_number && s.identity_number.includes(options.search)) ||
                (s.internal_file_number && s.internal_file_number.includes(options.search))
            );
        }

        // ترتيب
        sponsorships.sort((a, b) => new Date(b.updated_at || 0) - new Date(a.updated_at || 0));

        // صفحات
        if (options.page && options.perPage) {
            const start = (options.page - 1) * options.perPage;
            sponsorships = sponsorships.slice(start, start + options.perPage);
        }

        return sponsorships;
    },

    async getLocalSponsorship(id) {
        return await this.dbGet('sponsorships', parseInt(id));
    },

    // جلب بيانات كفالة من السيرفر مباشرة
    async getServerSponsorship(id) {
        try {
            console.log('🌐 جلب بيانات الكفالة من السيرفر:', id);
            const response = await this.apiRequest(`/sponsorship/${id}`);

            if (response.success && response.sponsorship) {
                console.log('✅ تم جلب بيانات الكفالة من السيرفر بنجاح');
                // تحديث البيانات المحلية
                await this.dbPut('sponsorships', response.sponsorship);
                return response.sponsorship;
            }

            console.warn('⚠️ فشل جلب بيانات الكفالة من السيرفر:', response);
            return null;
        } catch (error) {
            console.error('❌ خطأ في جلب بيانات الكفالة من السيرفر:', error);
            return null;
        }
    },

    async getLocalSponsors() {
        return await this.dbGetAll('sponsors');
    },

    async getLocalStatuses() {
        return await this.dbGetAll('statuses');
    },

    async getLocalHealthStatuses() {
        return await this.dbGetAll('health_statuses');
    },

    async getLocalCities() {
        return await this.dbGetAll('cities');
    },

    async getLocalBanks() {
        return await this.dbGetAll('banks');
    },

    // alias للتوافق مع sync-monitor.html
    async getLocalBankNames() {
        return await this.dbGetAll('banks');
    },

    async getLocalSponsorshipTypes() {
        try {
            await this.ensureInitialized();

            // أولاً محاولة الحصول على البيانات من IndexedDB
            const localTypes = await this.dbGetAll('sponsorship_types') || [];

            if (localTypes.length > 0) {
                console.log('✅ استخدام أنواع الكفالات من البيانات المحلية:', localTypes.length);
                return localTypes;
            }

            // إذا لم توجد بيانات محلية، استخراجها من الكفالات الموجودة
            const sponsorships = await this.dbGetAll('sponsorships') || [];
            const typesSet = new Set();

            sponsorships.forEach(sp => {
                if (sp.sponsorship_type) {
                    typesSet.add(sp.sponsorship_type);
                }
                if (sp.type) {
                    typesSet.add(sp.type);
                }
            });

            const extractedTypes = Array.from(typesSet).map((type, index) => ({
                id: index + 1,
                name: type,
                name_ar: type,
                name_en: type
            }));

            if (extractedTypes.length > 0) {
                // حفظ الأنواع المستخرجة محلياً
                for (const type of extractedTypes) {
                    await this.dbPut('sponsorship_types', type);
                }
                console.log('✅ تم استخراج أنواع الكفالات من البيانات الموجودة:', extractedTypes.length);
                return extractedTypes;
            }

            // إذا لم توجد أي أنواع، إنشاء أنواع افتراضية
            console.warn('⚠️ لم يتم العثور على أي أنواع كفالات - إنشاء أنواع افتراضية');
            const defaultTypes = [
                { id: 1, name: 'كفالة شاملة', name_ar: 'كفالة شاملة', name_en: 'Full Sponsorship' },
                { id: 2, name: 'كفالة تعليمية', name_ar: 'كفالة تعليمية', name_en: 'Educational Sponsorship' },
                { id: 3, name: 'كفالة صحية', name_ar: 'كفالة صحية', name_en: 'Medical Sponsorship' },
                { id: 4, name: 'كفالة غذائية', name_ar: 'كفالة غذائية', name_en: 'Food Sponsorship' }
            ];

            for (const type of defaultTypes) {
                await this.dbPut('sponsorship_types', type);
            }

            console.log('✅ تم إنشاء أنواع كفالات افتراضية:', defaultTypes.length);
            return defaultTypes;

        } catch (error) {
            console.error('❌ خطأ في جلب أنواع الكفالات:', error);
            return [];
        }
    },

    async getLocalStats() {
        const sponsorshipsCount = await this.dbCount('sponsorships');
        const pendingCount = await this.dbCount('pending_changes');
        const filesCount = await this.dbCount('pending_files');

        return {
            sponsorships_count: sponsorshipsCount,
            pending_uploads: pendingCount,
            files_count: filesCount
        };
    },

    async getPendingFiles() {
        return await this.dbGetAll('pending_files');
    },

    async uploadPendingFiles() {
        try {
            const pendingFiles = await this.dbGetAll('pending_files') || [];
            console.log('📤 بدء رفع الملفات المعلقة:', pendingFiles.length);

            if (pendingFiles.length === 0) {
                console.log('✅ لا توجد ملفات معلقة للرفع');
                return { success: true, uploaded: 0 };
            }

            let uploadedCount = 0;
            let failedCount = 0;

            for (const fileRecord of pendingFiles) {
                try {
                    await this.uploadFileToServer(fileRecord);
                    uploadedCount++;
                    console.log(`✅ تم رفع الملف: ${fileRecord.fileName}`);
                } catch (error) {
                    console.error(`❌ فشل رفع الملف ${fileRecord.fileName}:`, error);
                    failedCount++;
                }

                // انتظار قصير بين الرفعات
                await new Promise(resolve => setTimeout(resolve, 1000));
            }

            console.log(`📊 نتائج رفع الملفات: نجح ${uploadedCount}, فشل ${failedCount}`);

            return {
                success: uploadedCount > 0,
                uploaded: uploadedCount,
                failed: failedCount,
                total: pendingFiles.length
            };

        } catch (error) {
            console.error('❌ خطأ عام في رفع الملفات المعلقة:', error);
            return { success: false, error: error.message };
        }
    },

    async getPendingUploadsList() {
        try {
            const pendingChanges = await this.dbGetAll('pending_changes') || [];
            const pendingFiles = await this.dbGetAll('pending_files') || [];

            // إضافة معلومات اليتيم لكل تغيير
            const enhancedChanges = await Promise.all(
                pendingChanges.map(async (change) => {
                    // البحث عن معلومات الكفالة
                    const sponsorship = await this.dbGet('sponsorships', parseInt(change.sponsorship_id));

                    return {
                        ...change,
                        orphan_name: sponsorship?.orphan_name ||
                                   change.data?.orphan_name ||
                                   `كفالة رقم ${change.sponsorship_id}`,
                        type_description: change.type === 'full_update' ? 'تحديث كامل' : 'تحديث جزئي'
                    };
                })
            );

            return {
                changes: enhancedChanges,
                files: pendingFiles || [],
                totalChanges: pendingChanges.length,
                totalFiles: (pendingFiles || []).length
            };
        } catch (error) {
            console.error('❌ خطأ في جلب قائمة التحديثات المعلقة:', error);
            return {
                changes: [],
                files: [],
                totalChanges: 0,
                totalFiles: 0
            };
        }
    },

    // ========================================
    // دوال مطلوبة لـ upload.html
    // ========================================
    isOnline() {
        return navigator.onLine;
    },

    initAutoUpload() {
        console.log('🚀 تهيئة الرفع التلقائي');
        return Promise.resolve();
    },

    addAutoUploadListener(callback) {
        this.autoUploadCallback = callback;
    },

    getAutoUploadQueueSize() {
        return this.getPendingFiles().then(files => files.length).catch(() => 0);
    },

    setAutoUploadEnabled(enabled) {
        this.autoUploadEnabled = enabled;
        localStorage.setItem('autoUploadEnabled', enabled ? 'true' : 'false');
    },

    isAutoUploadEnabled() {
        return localStorage.getItem('autoUploadEnabled') === 'true';
    },

    // ========================================
    // حفظ التغييرات المحلية
    // ========================================
    async saveLocalChange(sponsorshipId, allData) {
        console.log('💾 حفظ جميع بيانات الكفالة:', sponsorshipId);
        console.log('📝 البيانات المُرسلة:', Object.keys(allData));

        try {
            await this.ensureInitialized();

            // تحديث الكفالة محلياً - حفظ البيانات حتى لو لم تكن موجودة محلياً
            let sponsorship = await this.dbGet('sponsorships', parseInt(sponsorshipId));

            if (sponsorship) {
                console.log('🔄 تحديث كفالة موجودة محلياً');
                const updated = { ...sponsorship, ...allData, updated_at: new Date().toISOString() };
                await this.dbPut('sponsorships', updated);
                console.log('✅ تم تحديث الكفالة محلياً بنجاح');
            } else {
                console.log('🆕 إنشاء كفالة جديدة محلياً');
                // إنشاء كفالة جديدة بالبيانات المرسلة
                const newSponsorship = {
                    id: parseInt(sponsorshipId),
                    ...allData,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString()
                };
                await this.dbPut('sponsorships', newSponsorship);
                console.log('✅ تم إنشاء الكفالة محلياً بنجاح');
            }

            // إضافة للتغييرات المعلقة - إرسال جميع البيانات
            const pendingChange = {
                id: Date.now() + Math.random(), // تفادي تضارب الـ IDs
                sponsorship_id: parseInt(sponsorshipId),
                data: allData, // جميع البيانات وليس فقط المعدلة
                type: 'full_update', // تحديث كامل
                created_at: new Date().toISOString(),
                synced: false
            };

            await this.dbPut('pending_changes', pendingChange);
            console.log('✅ تم حفظ التغيير في قائمة الانتظار');

            // التحقق من النجاح
            const savedSponsorship = await this.dbGet('sponsorships', parseInt(sponsorshipId));
            if (!savedSponsorship) {
                throw new Error('فشل في حفظ البيانات محلياً!');
            }

            console.log('✅ تم حفظ جميع البيانات محلياً بنجاح');

            // 🚀 محاولة رفع فوري للتغييرات إذا كان متصل
            if (navigator.onLine) {
                console.log('🌐 الجهاز متصل - محاولة رفع فوري...');
                setTimeout(() => {
                    this.uploadPendingChanges().catch(error => {
                        console.warn('⚠️ فشل الرفع الفوري:', error);
                    });
                }, 1000); // انتظار ثانية قبل الرفع
            } else {
                console.log('📴 الجهاز غير متصل - سيتم الرفع عند عودة الاتصال');
            }

            return { success: true };
        } catch (error) {
            console.error('❌ خطأ في حفظ البيانات محلياً:', error);
            return { success: false, error: error.message };
        }
    },

    // Alias for compatibility
    async saveSponsorshipData(sponsorshipId, data) {
        return await this.saveLocalChange(sponsorshipId, data);
    },

    // ========================================
    // تشخيص قاعدة البيانات المحلية
    // ========================================
    async debugDatabase() {
        console.log('🔍 تشخيص قاعدة البيانات المحلية...');

        try {
            await this.ensureInitialized();

            if (!this.db) {
                console.error('❌ قاعدة البيانات غير متاحة');
                return { status: 'error', message: 'قاعدة البيانات غير متاحة' };
            }

            console.log('📊 معلومات قاعدة البيانات:');
            console.log(`- الاسم: ${this.db.name}`);
            console.log(`- الإصدار: ${this.db.version}`);
            console.log(`- Object Stores:`, Array.from(this.db.objectStoreNames));

            const counts = {};
            for (const storeName of this.db.objectStoreNames) {
                try {
                    counts[storeName] = await this.dbCount(storeName);
                } catch (error) {
                    counts[storeName] = `خطأ: ${error.message}`;
                }
            }

            console.log('📈 عدد السجلات في كل جدول:', counts);

            // اختبار حفظ واسترجاع بيانات تجريبية
            const testData = { id: 'test_' + Date.now(), name: 'اختبار', timestamp: new Date().toISOString() };

            try {
                await this.dbPut('sync_meta', testData);
                const retrieved = await this.dbGet('sync_meta', testData.id);

                if (retrieved && retrieved.name === 'اختبار') {
                    console.log('✅ اختبار الحفظ والاسترجاع نجح');
                    await this.dbDelete('sync_meta', testData.id);
                    return { status: 'success', counts };
                } else {
                    console.error('❌ فشل في اختبار الحفظ والاسترجاع');
                    return { status: 'error', message: 'فشل في اختبار الحفظ والاسترجاع', counts };
                }
            } catch (error) {
                console.error('❌ خطأ في اختبار الحفظ:', error);
                return { status: 'error', message: 'خطأ في اختبار الحفظ: ' + error.message, counts };
            }

        } catch (error) {
            console.error('❌ خطأ في تشخيص قاعدة البيانات:', error);
            return { status: 'error', message: error.message };
        }
    },

    // التحقق من حفظ كفالة محددة
    async verifySponsorshipSaved(sponsorshipId) {
        try {
            const savedSponsorship = await this.dbGet('sponsorships', parseInt(sponsorshipId));
            const pendingChanges = await this.dbGetAll('pending_changes');
            const relatedChanges = pendingChanges.filter(change =>
                change.sponsorship_id == sponsorshipId || change.sponsorship_id == parseInt(sponsorshipId)
            );

            console.log(`🔍 التحقق من حفظ الكفالة ${sponsorshipId}:`);
            console.log('- الكفالة المحفوظة:', !!savedSponsorship);
            console.log('- التغييرات المعلقة:', relatedChanges.length);

            if (savedSponsorship) {
                console.log('- آخر تحديث:', savedSponsorship.updated_at);
                console.log('- بعض البيانات:', {
                    orphan_name: savedSponsorship.orphan_name,
                    guardian_name: savedSponsorship.guardian_name,
                    identity_number: savedSponsorship.identity_number
                });
            }

            return {
                saved: !!savedSponsorship,
                sponsorship: savedSponsorship,
                pendingChanges: relatedChanges.length,
                changes: relatedChanges
            };
        } catch (error) {
            console.error('❌ خطأ في التحقق من الكفالة:', error);
            return { error: error.message };
        }
    },

    async getPendingChanges() {
        return await this.dbGetAll('pending_changes');
    },

    // ========================================
    // رفع التغييرات للخادم
    // ========================================
    async uploadPendingChanges() {
        console.log('📤 بدء رفع التغييرات المعلقة للسيرفر...');

        try {
            const pendingChanges = await this.dbGetAll('pending_changes');
            console.log('📊 عدد التغييرات المعلقة:', pendingChanges.length);

            if (pendingChanges.length === 0) {
                console.log('✅ لا توجد تغييرات معلقة للرفع');
                return { success: true, uploaded: 0 };
            }

            let uploadedCount = 0;
            let failedCount = 0;

            // رفع كل تغيير منفصل لتجنب مشاكل bulk upload
            for (const change of pendingChanges) {
                try {
                    console.log(`📤 رفع تغيير للكفالة ${change.sponsorship_id}...`);

                    const response = await this.apiRequest(`/update-sponsorship`, {
                        method: 'POST',
                        body: {
                            sponsorship_id: change.sponsorship_id,
                            updates: change.data
                        }
                    });

                    if (response.success || response.sponsorship) {
                        // حذف التغيير بعد رفعه بنجاح
                        await this.dbDelete('pending_changes', change.id);
                        uploadedCount++;
                        console.log(`✅ تم رفع تغيير للكفالة ${change.sponsorship_id}`);
                    } else {
                        console.error(`❌ فشل رفع تغيير للكفالة ${change.sponsorship_id}:`, response);
                        failedCount++;
                    }
                } catch (changeError) {
                    console.error(`❌ خطأ في رفع تغيير للكفالة ${change.sponsorship_id}:`, changeError);
                    failedCount++;
                }

                // انتظار قصير بين الرفعات لتجنب الضغط على السيرفر
                await new Promise(resolve => setTimeout(resolve, 500));
            }

            console.log(`📊 نتائج الرفع: نجح ${uploadedCount}, فشل ${failedCount}`);

            return {
                success: uploadedCount > 0,
                uploaded: uploadedCount,
                failed: failedCount,
                total: pendingChanges.length
            };

        } catch (error) {
            console.error('❌ خطأ عام في رفع التغييرات:', error);
            return { success: false, error: error.message };
        }
    },

    // ========================================
    // Android Background Service Support
    // ========================================
    async startAndroidBackgroundService() {
        try {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.BackgroundSync) {
                const result = await Capacitor.Plugins.BackgroundSync.startService();
                this.androidServiceActive = true;
                console.log('✅ Android Background Service started:', result);
                return true;
            }
            console.warn('⚠️ BackgroundSync plugin غير متاح');
            return false;
        } catch (error) {
            console.error('❌ Failed to start Android service:', error);
            return false;
        }
    },

    async stopAndroidBackgroundService() {
        try {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.BackgroundSync) {
                const result = await Capacitor.Plugins.BackgroundSync.stopService();
                this.androidServiceActive = false;
                console.log('✅ Android Background Service stopped:', result);
                return true;
            }
            return false;
        } catch (error) {
            console.error('❌ Failed to stop Android service:', error);
            return false;
        }
    },

    // ========================================
    // Push Notifications Support with Android Progress
    // ========================================

    // إظهار Progress Notification في شريط الإشعارات
    async showProgressNotification(title, percent, body = '') {
        try {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.LocalNotifications) {
                const { LocalNotifications } = Capacitor.Plugins;

                this.currentProgressPercent = percent;

                // تنسيق النص ليظهر في شريط الإشعارات
                const progressText = body ? `${body} (${Math.round(percent)}%)` : `${Math.round(percent)}%`;

                await LocalNotifications.schedule({
                    notifications: [{
                        id: this.progressNotificationId,
                        title: title,
                        body: progressText,
                        actionTypeId: "SYNC_PROGRESS",
                        ongoing: true, // يبقى في شريط الإشعارات
                        autoCancel: false,
                        silent: true,
                        smallIcon: 'ic_stat_sync',
                        extra: {
                            progress: Math.round(percent),
                            max: 100,
                            isProgress: true
                        },
                        schedule: { at: new Date(Date.now() + 100) } // فورياً
                    }]
                });

                console.log(`📊 Progress: ${Math.round(percent)}% - ${title} - ${body}`);
                return true;
            } else {
                console.warn('⚠️ Capacitor LocalNotifications غير متاح');
                return false;
            }
        } catch (error) {
            console.error('❌ خطأ في إظهار progress notification:', error);
            return false;
        }
    },

    // تحديث Progress Notification
    async updateProgressNotification(percent, body = '') {
        await this.showProgressNotification('جاري المزامنة...', percent, body);
    },

    // إخفاء Progress Notification
    async hideProgressNotification() {
        try {
            // إظهار رسالة اكتمال باستخدام Android service
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.BackgroundSync) {
                await Capacitor.Plugins.BackgroundSync.showComplete({
                    message: 'تمت المزامنة بنجاح'
                });
                console.log('✅ Android service completion shown');
            }

            // Fallback لحذف LocalNotifications
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.LocalNotifications) {
                const { LocalNotifications } = Capacitor.Plugins;
                await LocalNotifications.cancel({
                    notifications: [{ id: this.progressNotificationId }]
                });
                console.log('✅ Progress notification hidden');
            }
        } catch (error) {
            console.error('❌ Failed to hide progress notification:', error);
        }
    },

    async sendNotification(title, body, options = {}) {
        try {
            // تحقق من توفر Capacitor LocalNotifications
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.LocalNotifications) {
                const { LocalNotifications } = Capacitor.Plugins;

                await LocalNotifications.schedule({
                    notifications: [{
                        title: title,
                        body: body,
                        id: options.id ? Math.abs(parseInt(options.id) % 2147483647) : Math.abs(Date.now() % 2147483647),
                        schedule: { at: new Date(Date.now() + 100) },
                        sound: null,
                        attachments: null,
                        actionTypeId: "",
                        extra: options.extra || null
                    }]
                });

                console.log('📬 Notification sent:', title);
                return true;
            } else {
                console.warn('⚠️ LocalNotifications not available');
                return false;
            }
        } catch (error) {
            console.error('❌ Failed to send notification:', error);
            return false;
        }
    },

    async requestNotificationPermission() {
        try {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.LocalNotifications) {
                const { LocalNotifications } = Capacitor.Plugins;
                const result = await LocalNotifications.requestPermissions();
                console.log('📬 Notification permission:', result.display);
                return result.display === 'granted';
            }
            return false;
        } catch (error) {
            console.error('❌ Failed to request notification permission:', error);
            return false;
        }
    },

    async cancelNotification(id = null) {
        try {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.LocalNotifications) {
                const { LocalNotifications } = Capacitor.Plugins;
                const notificationId = id || this.progressNotificationId;

                await LocalNotifications.cancel({
                    notifications: [{ id: notificationId }]
                });

                console.log('✅ تم إلغاء الإشعار:', notificationId);
                return true;
            }
            return false;
        } catch (error) {
            console.error('❌ Failed to cancel notification:', error);
            return false;
        }
    },

    // ========================================
    // Background Sync Support
    // ========================================
    backgroundSyncInterval: null,
    backgroundSyncEnabled: false,

    async startBackgroundSync(intervalMinutes = 15, showNotificationProgress = true) {
        // بدء Android Background Service للـ progress notifications
        if (showNotificationProgress) {
            await this.startAndroidBackgroundService();
        }

        // طلب إذن الإشعارات أولاً
        await this.requestNotificationPermission();

        // إيقاف أي مزامنة سابقة
        this.stopBackgroundSync();

        this.backgroundSyncEnabled = true;
        localStorage.setItem('backgroundSyncEnabled', 'true');
        localStorage.setItem('backgroundSyncInterval', intervalMinutes.toString());

        console.log(`🔄 Starting background sync every ${intervalMinutes} minutes`);

        // مزامنة فورية مع Progress Notification
        await this.performBackgroundSyncCycle(showNotificationProgress);

        // جدولة مزامنة دورية
        this.backgroundSyncInterval = setInterval(async () => {
            await this.performBackgroundSyncCycle(showNotificationProgress);
        }, intervalMinutes * 60 * 1000);

        await this.sendNotification(
            'المزامنة التلقائية نشطة',
            `سيتم رفع وتنزيل التحديثات كل ${intervalMinutes} دقيقة`
        );
    },

    stopBackgroundSync() {
        if (this.backgroundSyncInterval) {
            clearInterval(this.backgroundSyncInterval);
            this.backgroundSyncInterval = null;
            this.backgroundSyncEnabled = false;
            localStorage.setItem('backgroundSyncEnabled', 'false');
            console.log('⏹️ Background sync stopped');

            this.sendNotification(
                'تم إيقاف المزامنة التلقائية',
                'لن يتم رفع أو تنزيل التحديثات تلقائياً'
            );
        }
    },

    // Auto-start background sync عند فتح التطبيق
    async autoStartBackgroundSync() {
        const enabled = localStorage.getItem('backgroundSyncEnabled');
        const interval = parseInt(localStorage.getItem('backgroundSyncInterval') || '15');

        if (enabled === 'true' && this.isAuthenticated()) {
            console.log('🚀 Auto-starting background sync...');
            await this.startBackgroundSync(interval, true);
        }
    },

    async performBackgroundSyncCycle(showNotificationProgress = false) {
        try {
            console.log('🔄 Background sync cycle started');

            if (showNotificationProgress) {
                await this.showProgressNotification('المزامنة التلقائية', 0, 'بدء المزامنة...');
            }

            // === الخطوة 1: رفع التغييرات المعلقة ===
            const pendingCount = await this.dbCount('pending_changes');
            if (pendingCount > 0) {
                if (showNotificationProgress) {
                    await this.updateProgressNotification(15, `رفع ${pendingCount} تحديث...`);
                }

                const uploadResult = await this.uploadPendingChanges();

                if (uploadResult.success && uploadResult.uploaded > 0) {
                    await this.sendNotification(
                        'تم رفع التحديثات',
                        `تم رفع ${uploadResult.uploaded} تحديث بنجاح`
                    );
                }
            }

            // === الخطوة 2: رفع الملفات المعلقة ===
            const pendingFilesCount = await this.dbCount('pending_files');
            if (pendingFilesCount > 0) {
                if (showNotificationProgress) {
                    await this.updateProgressNotification(35, `رفع ${pendingFilesCount} ملف...`);
                }

                const filesUploadResult = await this.uploadPendingFiles();

                if (filesUploadResult.success && filesUploadResult.uploaded > 0) {
                    await this.sendNotification(
                        'تم رفع الملفات',
                        `تم رفع ${filesUploadResult.uploaded} ملف إلى Google Drive`
                    );
                }
            }

            if (showNotificationProgress) {
                await this.updateProgressNotification(60, 'جاري تنزيل التحديثات...');
            }

            // === الخطوة 3: تنزيل التحديثات الجديدة ===
            const lastSync = await this.dbGet('sync_meta', 'last_full_sync');
            if (lastSync && lastSync.timestamp) {
                // جلب الكفالات المحدثة بعد آخر مزامنة
                const response = await this.apiRequest(
                    `/sponsorships?last_sync=${lastSync.timestamp}&per_page=100`
                );

                if (response.success && response.sponsorships.length > 0) {
                    if (showNotificationProgress) {
                        await this.updateProgressNotification(80, `تنزيل ${response.sponsorships.length} كفالة...`);
                    }

                    // حفظ في IndexedDB
                    for (const sponsorship of response.sponsorships) {
                        await this.dbPut('sponsorships', sponsorship);
                    }

                    await this.sendNotification(
                        'تم تنزيل تحديثات جديدة',
                        `${response.sponsorships.length} كفالة محدثة`
                    );
                }
            }

            if (showNotificationProgress) {
                await this.updateProgressNotification(100, 'تمت المزامنة بنجاح');
                setTimeout(() => this.hideProgressNotification(), 2000);
            }

            console.log('✅ Background sync cycle completed');

        } catch (error) {
            console.error('❌ Background sync failed:', error);

            if (showNotificationProgress) {
                await this.hideProgressNotification();
            }

            await this.sendNotification(
                'فشلت المزامنة التلقائية',
                error.message
            );
        }
    },

    // ========================================
    // إحصائيات المزامنة
    // ========================================
    async getSyncStats() {
        const lastInitialSync = await this.dbGet('sync_meta', 'last_initial_sync');
        const lastFullSync = await this.dbGet('sync_meta', 'last_full_sync');
        const pendingCount = await this.dbCount('pending_changes');
        const sponsorshipsCount = await this.dbCount('sponsorships');

        return {
            lastInitialSync: lastInitialSync?.timestamp,
            lastFullSync: lastFullSync?.timestamp,
            pendingChanges: pendingCount,
            totalSponsorships: sponsorshipsCount
        };
    },

    // ========================================
    // Android Progress Notifications
    // ========================================

    // بدء خدمة Android لـ progress bar
    async startAndroidBackgroundService() {
        try {
            // التحقق من توفر Capacitor و BackgroundSync plugin
            if (typeof Capacitor !== 'undefined' &&
                Capacitor.Plugins &&
                Capacitor.Plugins.BackgroundSync &&
                typeof Capacitor.Plugins.BackgroundSync.start === 'function') {

                await Capacitor.Plugins.BackgroundSync.start({
                    title: 'مزامنة البيانات',
                    text: 'جاري تحديث البيانات...'
                });

                this.androidServiceActive = true;
                console.log('✅ Android Background Service started');
                return true;
            } else {
                console.warn('⚠️ BackgroundSync plugin غير متاح - استخدام LocalNotifications كـ fallback');
                // استخدام LocalNotifications كـ fallback
                if (typeof Capacitor !== 'undefined' &&
                    Capacitor.Plugins &&
                    Capacitor.Plugins.LocalNotifications) {

                    await Capacitor.Plugins.LocalNotifications.schedule({
                        notifications: [{
                            id: this.progressNotificationId,
                            title: 'مزامنة البيانات',
                            body: 'جاري تحديث البيانات...',
                            ongoing: true,
                            autoCancel: false,
                            silent: true
                        }]
                    });
                    console.log('✅ استخدم LocalNotifications كـ fallback');
                    return true;
                }
                console.log('📱 تشغيل في المتصفح - لا توجد إشعارات');
                return false;
            }
        } catch (error) {
            console.error('❌ Failed to start Android service:', error);
            return false;
        }
    },

    // إيقاف خدمة Android
    async stopAndroidBackgroundService() {
        try {
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins.BackgroundSync && this.androidServiceActive) {
                await Capacitor.Plugins.BackgroundSync.stop();
                this.androidServiceActive = false;
                console.log('✅ Android Background Service stopped');
                return true;
            }
            return false;
        } catch (error) {
            console.error('❌ خطأ في إيقاف Android service:', error);
            return false;
        }
    },

    // إظهار Progress Notification في Android باستخدام Background Service
    async showProgressNotification(title, percent, body = '') {
        try {
            this.currentProgressPercent = percent;

            // 🤖 أولوية لاستخدام Android Background Service للـ progress bar الحقيقي
            if (typeof Capacitor !== 'undefined' &&
                Capacitor.Plugins &&
                Capacitor.Plugins.BackgroundSync &&
                typeof Capacitor.Plugins.BackgroundSync.updateProgress === 'function') {

                await Capacitor.Plugins.BackgroundSync.updateProgress({
                    progress: Math.round(percent),
                    max: 100,
                    status: body ? `${title}: ${body}` : title
                });

                console.log(`📊 Android Progress: ${Math.round(percent)}% - ${title}`);
                return true;
            }

            // 📱 Fallback إلى LocalNotifications إذا لم يعمل Android service
            if (typeof Capacitor !== 'undefined' &&
                Capacitor.Plugins &&
                Capacitor.Plugins.LocalNotifications) {

                const progressText = body ? `${body} (${Math.round(percent)}%)` : `${Math.round(percent)}%`;

                await Capacitor.Plugins.LocalNotifications.schedule({
                    notifications: [{
                        id: this.progressNotificationId,
                        title: title,
                        body: progressText,
                        ongoing: true,
                        autoCancel: false,
                        silent: true,
                        extra: {
                            progress: Math.round(percent),
                            max: 100,
                            isProgress: true
                        }
                    }]
                });

                console.log(`📱 Fallback Progress: ${Math.round(percent)}% - ${title}`);
                return true;
            }

            // 🌐 إذا كان في المتصفح، اطبع في console فقط
            console.log(`🌐 Browser Progress: ${Math.round(percent)}% - ${title} - ${body || ''}`);
            return false;
        } catch (error) {
            console.error('❌ خطأ في إظهار progress notification:', error);
            return false;
        }
    },

    // تحديث Progress Notification
    async updateProgressNotification(percent, body = '') {
        return await this.showProgressNotification('جاري المزامنة...', percent, body);
    },

    // إخفاء Progress Notification
    async hideProgressNotification() {
        try {
            // Android Background Service completion
            if (typeof Capacitor !== 'undefined' &&
                Capacitor.Plugins &&
                Capacitor.Plugins.BackgroundSync &&
                typeof Capacitor.Plugins.BackgroundSync.showComplete === 'function' &&
                this.androidServiceActive) {

                await Capacitor.Plugins.BackgroundSync.showComplete({
                    title: 'مزامنة مكتملة',
                    text: 'تم تحديث البيانات بنجاح'
                });

                // إيقاف الخدمة بعد 3 ثوانٍ
                setTimeout(async () => {
                    await this.stopAndroidBackgroundService();
                }, 3000);
                return true;
            }

            // LocalNotifications fallback - إلغاء الإشعار فوراً
            if (typeof Capacitor !== 'undefined' &&
                Capacitor.Plugins &&
                Capacitor.Plugins.LocalNotifications) {

                const { LocalNotifications } = Capacitor.Plugins;

                // إظهار إشعار الإكمال أولاً
                await LocalNotifications.schedule({
                    notifications: [{
                        id: this.progressNotificationId + 1,
                        title: 'مزامنة مكتملة',
                        body: 'تم تحديث البيانات بنجاح',
                        autoCancel: true,
                        silent: false
                    }]
                });

                // إلغاء إشعار التقدم
                await LocalNotifications.cancel({
                    notifications: [{ id: this.progressNotificationId }]
                });

                console.log('✅ تم إخفاء progress notification واستبداله بإشعار الإكمال');
                return true;
            }

            console.log('📱 Progress notification hidden (browser mode)');
            return false;
        } catch (error) {
            console.error('❌ خطأ في إخفاء progress notification:', error);
            // محاولة إلغاء الإشعار على أي حال
            try {
                if (typeof Capacitor !== 'undefined' &&
                    Capacitor.Plugins &&
                    Capacitor.Plugins.LocalNotifications) {

                    await Capacitor.Plugins.LocalNotifications.cancel({
                        notifications: [{ id: this.progressNotificationId }]
                    });
                }
            } catch (cancelError) {
                console.error('❌ فشل في إلغاء الإشعار:', cancelError);
            }
            return false;
        }
    },

    // ========================================
    // تصدير للتوافق مع الكود القديم
    // ========================================
    async getLocalData(type) {
        switch (type) {
            case 'sponsors': return this.getLocalSponsors();
            case 'statuses': return this.getLocalStatuses();
            case 'health_statuses': return this.getLocalHealthStatuses();
            case 'cities': return this.getLocalCities();
            case 'banks':
            case 'bank_names': // للتوافق مع الكود القديم
                return this.getLocalBanks();
            default: return [];
        }
    }
};

// تهيئة تلقائية
SyncService.init().then(() => {
    console.log('📱 SyncService loaded (REAL DATABASE MODE) - Ready to connect to Laravel API');
    console.log('   📡 API URL:', SyncService.baseUrl);
    console.log('   🔧 Available functions:', Object.keys(SyncService).filter(k => typeof SyncService[k] === 'function').join(', '));
});

// تصدير
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SyncService;
}

// إتاحة SyncService للنافذة
if (typeof window !== 'undefined') {
    window.SyncService = SyncService;
}
