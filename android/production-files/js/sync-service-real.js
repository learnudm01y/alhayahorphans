/**
 * Sponsorship Sync Service - PRODUCTION VERSION
 * نسخة تتصل بقاعدة البيانات الحقيقية عبر Laravel API Production
 * VERSION: 2026-01-22-PRODUCTION
 *
 * يتصل بـ: /api/mobile/*
 */

console.log('🚀🚀🚀 LOADING SYNC SERVICE - PRODUCTION VERSION 🚀🚀🚀');

const SyncService = {
    // ========================================
    // إعدادات الاتصال - PRODUCTION
    // ========================================

    // عنوان Laravel API Production
    // 10.0.2.2 = localhost للوصول من Android Emulator
    // للجهاز الحقيقي: استخدم IP الكمبيوتر على الشبكة (مثل 192.168.x.x)
    // للاستضافة الحقيقية: استخدم عنوان الدومين الكامل
    baseUrl: (() => {
        // التحقق من بيئة التشغيل
        const isAndroid = /Android/i.test(navigator.userAgent);
        const isCapacitor = window.Capacitor !== undefined;

        // إذا كان على Android (Emulator أو Capacitor)
        if (isAndroid || isCapacitor) {
            // الاستضافة الحقيقية - مؤسسة الحياة للأيتام
            const androidUrl = 'https://alhayahorphans.org/api/mobile';
            console.log('📱 Running on Android, using:', androidUrl);
            return androidUrl;
        }

        // للمتصفح على الكمبيوتر
        // استخدام الاستضافة الحقيقية أيضاً
        const localUrl = 'https://alhayahorphans.org/api/mobile';
        console.log('💻 Running on Desktop, using:', localUrl);
        return localUrl;
    })(),

    token: null,
    user: null,
    dbName: 'AlhayahSponsorshipsOfflineDB',
    dbVersion: 3,
    db: null,

    // حالة المزامنة
    syncInProgress: false,
    uploadInProgress: false,
    isAppInBackground: false,

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
                resolve(null);
                return;
            }
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
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
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
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
        console.log('🔄 Starting initial sync from PRODUCTION database...');

        try {
            const response = await this.apiRequest('/sync/initial');

            if (response.success) {
                const data = response.data;

                // حفظ في قاعدة البيانات المحلية
                await this.dbClear('sponsors');
                await this.dbClear('statuses');
                await this.dbClear('health_statuses');
                await this.dbClear('cities');
                await this.dbClear('banks');

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
    async performFullSync(progressCallback = null) {
        console.log('🔄 Starting full sponsorships sync from REAL database...');

        try {
            let page = 1;
            let hasMore = true;
            let totalDownloaded = 0;
            let allSponsorships = [];

            // إحصائيات تفصيلية للـ logging
            let statsWithGuardianData = 0;
            let statsWithBankAccounts = 0;
            let statsWithSponsoredName = 0;

            await this.dbClear('sponsorships');

            while (hasMore) {
                if (progressCallback) {
                    progressCallback({
                        progress: totalDownloaded / 100,
                        downloaded: totalDownloaded,
                        page: page
                    });
                }

                const response = await this.apiRequest(`/sponsorships?page=${page}&per_page=100`);

                if (response.success) {
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
                    complete: true
                });
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

        // تطبيق الفلاتر
        if (options.sponsorId) {
            sponsorships = sponsorships.filter(s => s.sponsor_id == options.sponsorId);
        }
        if (options.statusId) {
            sponsorships = sponsorships.filter(s => s.sponsorship_status_id == options.statusId);
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
        // نوع الضمان - قد لا يكون متاحاً حالياً
        try {
            return await this.dbGetAll('sponsorship_types') || [];
        } catch {
            return [];
        }
    },

    async getLocalStats() {
        const sponsorshipsCount = await this.dbCount('sponsorships');
        const pendingCount = await this.dbCount('pending_changes');

        return {
            sponsorships_count: sponsorshipsCount,
            pending_uploads: pendingCount
        };
    },

    async getPendingFiles() {
        return await this.dbGetAll('pending_files');
    },

    // ========================================
    // حفظ التغييرات المحلية
    // ========================================
    async saveLocalChange(sponsorshipId, changes) {
        console.log('💾 Saving local change for sponsorship:', sponsorshipId);

        // تحديث الكفالة محلياً
        const sponsorship = await this.dbGet('sponsorships', parseInt(sponsorshipId));
        if (sponsorship) {
            const updated = { ...sponsorship, ...changes, updated_at: new Date().toISOString() };
            await this.dbPut('sponsorships', updated);
        }

        // إضافة للتغييرات المعلقة
        const pendingChange = {
            id: Date.now(),
            sponsorship_id: sponsorshipId,
            data: changes,
            created_at: new Date().toISOString(),
            synced: false
        };

        await this.dbPut('pending_changes', pendingChange);

        console.log('✅ Change saved locally');
        return { success: true };
    },

    async getPendingChanges() {
        return await this.dbGetAll('pending_changes');
    },

    // ========================================
    // رفع التغييرات للخادم
    // ========================================
    async uploadPendingChanges() {
        console.log('📤 Uploading pending changes to server...');

        try {
            const pendingChanges = await this.dbGetAll('pending_changes');

            if (pendingChanges.length === 0) {
                console.log('✅ No pending changes to upload');
                return { success: true, uploaded: 0 };
            }

            const response = await this.apiRequest('/sync/upload', {
                method: 'POST',
                body: { changes: pendingChanges }
            });

            if (response.success) {
                // حذف التغييرات التي تم رفعها بنجاح
                for (const result of response.results) {
                    if (result.success) {
                        const change = pendingChanges.find(c => c.sponsorship_id === result.sponsorship_id);
                        if (change) {
                            await this.dbDelete('pending_changes', change.id);
                        }
                    }
                }

                console.log(`✅ Uploaded ${response.summary.success} changes`);
                return {
                    success: true,
                    uploaded: response.summary.success,
                    failed: response.summary.failed
                };
            }

            return { success: false, message: response.message };

        } catch (error) {
            console.error('❌ Upload failed:', error);
            return {
                success: false,
                message: 'فشل رفع التغييرات: ' + error.message
            };
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
