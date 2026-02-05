/**
 * API Service for Sponsorships Mobile App - OFFLINE VERSION
 *
 * This version uses mock data for testing without server connection
 */

const ApiService = {
    // Base URL - للوضع الـ offline نستخدم السيرفر المحلي أو mock
    baseUrl: 'http://127.0.0.1:8000/api',

    // Offline mode flag
    offlineMode: true,

    // Authentication token
    token: null,

    // User data
    user: null,

    // Mock Data - بيانات وهمية للاختبار
    mockData: {
        // المستخدم
        user: {
            id: 1,
            name: 'مستخدم اختبار',
            email: 'admin@gmail.com',
            username: 'admin'
        },

        // الجمعيات
        sponsors: [
            { id: 1, sponsor_name: 'جمعية الإحسان الخيرية' },
            { id: 2, sponsor_name: 'جمعية البر والإحسان' },
            { id: 3, sponsor_name: 'جمعية الأيتام الفلسطينية' },
            { id: 4, sponsor_name: 'مؤسسة الرحمة الدولية' }
        ],

        // حالات الكفالة
        sponsorshipStatuses: [
            { id: 1, description: 'مفعل' },
            { id: 2, description: 'متوقف' },
            { id: 3, description: 'محدث' },
            { id: 4, description: 'انتظار الصرف' },
            { id: 5, description: 'ملغي' }
        ],

        // البنوك
        banks: [
            { id: 1, bank_name: 'بنك الإسكان' },
            { id: 2, bank_name: 'بنك القاهرة عمان' },
            { id: 3, bank_name: 'البنك الإسلامي الفلسطيني' },
            { id: 4, bank_name: 'بنك فلسطين' },
            { id: 5, bank_name: 'بنك الأردن' }
        ],

        // الكفالات - مع أنواع مختلفة للاختبار
        sponsorships: [
            {
                id: 1001,
                internal_file_number: 'TEST001',
                relation_id_number: 'REL001',
                person_type: 'family_member',
                orphan_name: 'أحمد محمد علي الاختبار',
                identity_number: '401234567',
                guardian_name: 'محمد علي سعيد الاختبار',
                guardian_identity_number: '801234567',
                sponsor_id: 1,
                sponsorship_status_id: 1,
                orphan_first_name: 'أحمد',
                orphan_father_name: 'محمد',
                orphan_grandfather_name: 'علي',
                orphan_family_name: 'الاختبار',
                guardian_first_name: 'محمد',
                guardian_father_name: 'علي',
                guardian_grandfather_name: 'سعيد',
                guardian_family_name: 'الاختبار',
                guardian_phone: '0591234567',
                guardian_phone2: '0597654321',
                guardian_detailed_address: 'غزة - الرمال - شارع الجلاء',
                orphan_phone: '',
                orphan_phone2: '',
                orphan_detailed_address: '',
                birth_date: '2015-03-20',
                orphan_gender: 'ذكر',
                bank_accounts: [
                    { id: 1, bank_name: 4, iban_usd: 'PS92PALS0000000001234USD', iban_shekel: 'PS92PALS0000000001234ILS' }
                ]
            },
            {
                id: 1002,
                internal_file_number: 'TEST002',
                relation_id_number: 'REL002',
                person_type: 'breadwinner',
                orphan_name: 'سامي فؤاد عبدالله المعيل',
                identity_number: '802345678',
                guardian_name: 'سامي فؤاد عبدالله المعيل',
                guardian_identity_number: '802345678',
                sponsor_id: 2,
                sponsorship_status_id: 1,
                orphan_first_name: 'سامي',
                orphan_father_name: 'فؤاد',
                orphan_grandfather_name: 'عبدالله',
                orphan_family_name: 'المعيل',
                guardian_first_name: 'سامي',
                guardian_father_name: 'فؤاد',
                guardian_grandfather_name: 'عبدالله',
                guardian_family_name: 'المعيل',
                guardian_phone: '0592345678',
                guardian_phone2: '0598765432',
                guardian_detailed_address: 'غزة - النصيرات - المخيم',
                orphan_phone: '0592345678',
                orphan_phone2: '0598765432',
                orphan_detailed_address: 'غزة - النصيرات - المخيم',
                birth_date: '1985-07-15',
                orphan_gender: 'ذكر',
                bank_accounts: []
            },
            {
                id: 1003,
                internal_file_number: 'TEST003',
                relation_id_number: 'REL003',
                person_type: 'deceased_father',
                orphan_name: 'خالد إبراهيم محمد الشهيد',
                identity_number: '803456789',
                guardian_name: '',
                guardian_identity_number: '',
                sponsor_id: 3,
                sponsorship_status_id: 1,
                orphan_first_name: 'خالد',
                orphan_father_name: 'إبراهيم',
                orphan_grandfather_name: 'محمد',
                orphan_family_name: 'الشهيد',
                guardian_first_name: '',
                guardian_father_name: '',
                guardian_grandfather_name: '',
                guardian_family_name: '',
                guardian_phone: '',
                guardian_phone2: '',
                guardian_detailed_address: '',
                orphan_phone: '0593456789',
                orphan_phone2: '0599876543',
                orphan_detailed_address: 'خانيونس - حي الأمل - بجوار المسجد',
                birth_date: '1970-01-01',
                orphan_gender: 'ذكر',
                bank_accounts: []
            },
            {
                id: 1004,
                internal_file_number: 'TEST004',
                relation_id_number: 'REL004',
                person_type: 'deceased_mother',
                orphan_name: 'فاطمة أحمد محمود المتوفية',
                identity_number: '804567890',
                guardian_name: '',
                guardian_identity_number: '',
                sponsor_id: 4,
                sponsorship_status_id: 1,
                orphan_first_name: 'فاطمة',
                orphan_father_name: 'أحمد',
                orphan_grandfather_name: 'محمود',
                orphan_family_name: 'المتوفية',
                guardian_first_name: '',
                guardian_father_name: '',
                guardian_grandfather_name: '',
                guardian_family_name: '',
                guardian_phone: '',
                guardian_phone2: '',
                guardian_detailed_address: '',
                orphan_phone: '0594567890',
                orphan_phone2: '0590987654',
                orphan_detailed_address: 'رفح - تل السلطان - قرب المدرسة',
                birth_date: '1975-06-15',
                orphan_gender: 'أنثى',
                bank_accounts: []
            },
            {
                id: 1005,
                internal_file_number: 'TEST005',
                relation_id_number: '',
                person_type: 'orphan',
                orphan_name: 'يتيم جديد بدون ربط',
                identity_number: '405678901',
                guardian_name: '',
                guardian_identity_number: '',
                sponsor_id: 1,
                sponsorship_status_id: 4,
                orphan_first_name: 'يتيم',
                orphan_father_name: 'جديد',
                orphan_grandfather_name: 'بدون',
                orphan_family_name: 'ربط',
                guardian_first_name: '',
                guardian_father_name: '',
                guardian_grandfather_name: '',
                guardian_family_name: '',
                guardian_phone: '',
                guardian_phone2: '',
                guardian_detailed_address: '',
                orphan_phone: '',
                orphan_phone2: '',
                orphan_detailed_address: '',
                birth_date: '2018-09-10',
                orphan_gender: 'ذكر',
                bank_accounts: []
            }
        ],

        // سجل التعديلات المحلية
        pendingChanges: []
    },

    /**
     * Initialize the service
     */
    init() {
        console.log('🔧 Initializing API Service (OFFLINE MODE)');

        // Load token from storage
        this.token = localStorage.getItem('auth_token');
        const userData = localStorage.getItem('user_data');
        if (userData) {
            try {
                this.user = JSON.parse(userData);
            } catch (e) {
                console.error('Failed to parse user data');
            }
        }

        // Load mock sponsorships from localStorage if exists
        const savedSponsorships = localStorage.getItem('mock_sponsorships');
        if (savedSponsorships) {
            try {
                this.mockData.sponsorships = JSON.parse(savedSponsorships);
            } catch (e) {
                console.error('Failed to parse saved sponsorships');
            }
        }

        // Load pending changes
        const pendingChanges = localStorage.getItem('pending_changes');
        if (pendingChanges) {
            try {
                this.mockData.pendingChanges = JSON.parse(pendingChanges);
            } catch (e) {
                console.error('Failed to parse pending changes');
            }
        }
    },

    /**
     * Set base URL
     */
    setBaseUrl(url) {
        this.baseUrl = url;
        localStorage.setItem('api_base_url', url);
    },

    /**
     * Toggle offline mode
     */
    setOfflineMode(enabled) {
        this.offlineMode = enabled;
        localStorage.setItem('offline_mode', enabled ? 'true' : 'false');
        console.log(`📴 Offline mode: ${enabled ? 'ON' : 'OFF'}`);
    },

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!this.token;
    },

    /**
     * Make API request - with offline fallback
     */
    async request(endpoint, options = {}) {
        // في الوضع الـ offline، نستخدم البيانات المحلية
        if (this.offlineMode) {
            return this.handleOfflineRequest(endpoint, options);
        }

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
            const response = await fetch(url, {
                ...options,
                headers
            });

            const data = await response.json();

            if (response.status === 401) {
                this.logout();
                throw new Error('انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.');
            }

            if (!response.ok) {
                throw new Error(data.message || 'حدث خطأ في الاتصال');
            }

            return data;

        } catch (error) {
            if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                console.warn('⚠️ Server unreachable, switching to offline mode');
                this.setOfflineMode(true);
                return this.handleOfflineRequest(endpoint, options);
            }
            throw error;
        }
    },

    /**
     * Handle offline requests with mock data
     */
    handleOfflineRequest(endpoint, options = {}) {
        console.log(`📴 Offline request: ${endpoint}`);

        const method = options.method || 'GET';
        const body = options.body ? JSON.parse(options.body) : {};

        // تسجيل الدخول
        if (endpoint === '/mobile/login' && method === 'POST') {
            return this.mockLogin(body);
        }

        // تسجيل الخروج
        if (endpoint === '/mobile/logout' && method === 'POST') {
            return this.mockLogout();
        }

        // الصحة
        if (endpoint === '/mobile/health') {
            return { status: 'healthy', mode: 'offline', timestamp: new Date().toISOString() };
        }

        // الجمعيات
        if (endpoint === '/mobile/sponsors') {
            return { success: true, data: this.mockData.sponsors };
        }

        // حالات الكفالة
        if (endpoint === '/mobile/sponsorship-statuses') {
            return { success: true, data: this.mockData.sponsorshipStatuses };
        }

        // البنوك
        if (endpoint.includes('/banks')) {
            return { success: true, data: this.mockData.banks };
        }

        // الكفالات
        if (endpoint.includes('/sync/sponsorships') && !endpoint.includes('/sync/sponsorship/')) {
            return this.mockGetSponsorships(endpoint);
        }

        // تفاصيل كفالة
        if (endpoint.match(/\/sync\/sponsorship\/\d+/)) {
            const id = parseInt(endpoint.split('/').pop());
            return this.mockGetSponsorshipDetails(id);
        }

        // رفع المزامنة
        if (endpoint === '/mobile/sync/upload' && method === 'POST') {
            return this.mockUploadSync(body);
        }

        // المزامنة الكاملة
        if (endpoint === '/mobile/sync/full') {
            return this.mockFullSync();
        }

        // إحصائيات
        if (endpoint === '/sync/stats') {
            return this.mockGetStats();
        }

        return { success: false, message: 'Endpoint not implemented in offline mode' };
    },

    /**
     * Mock Login
     */
    mockLogin(credentials) {
        console.log('🔐 Mock login:', credentials.username);

        // قبول أي بيانات دخول في الوضع الـ offline
        const token = 'offline_token_' + Date.now();
        this.token = token;
        this.user = this.mockData.user;

        localStorage.setItem('auth_token', token);
        localStorage.setItem('user_data', JSON.stringify(this.mockData.user));

        return {
            success: true,
            token: token,
            user: this.mockData.user,
            message: 'تم تسجيل الدخول بنجاح (وضع offline)'
        };
    },

    /**
     * Mock Logout
     */
    mockLogout() {
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        return { success: true, message: 'تم تسجيل الخروج' };
    },

    /**
     * Mock Get Sponsorships
     */
    mockGetSponsorships(endpoint) {
        const url = new URL('http://dummy' + endpoint);
        const page = parseInt(url.searchParams.get('page')) || 1;
        const perPage = parseInt(url.searchParams.get('per_page')) || 10;
        const search = url.searchParams.get('search') || '';

        let filtered = [...this.mockData.sponsorships];

        // تطبيق البحث
        if (search) {
            const searchLower = search.toLowerCase();
            filtered = filtered.filter(sp =>
                (sp.orphan_name || '').toLowerCase().includes(searchLower) ||
                (sp.guardian_name || '').toLowerCase().includes(searchLower) ||
                (sp.identity_number || '').includes(search) ||
                (sp.internal_file_number || '').includes(search)
            );
        }

        // Pagination
        const start = (page - 1) * perPage;
        const paginated = filtered.slice(start, start + perPage);

        return {
            success: true,
            data: paginated,
            meta: {
                current_page: page,
                per_page: perPage,
                total: filtered.length,
                last_page: Math.ceil(filtered.length / perPage)
            }
        };
    },

    /**
     * Mock Get Sponsorship Details
     */
    mockGetSponsorshipDetails(id) {
        const sponsorship = this.mockData.sponsorships.find(sp => sp.id === id);

        if (!sponsorship) {
            throw new Error('الكفالة غير موجودة');
        }

        return {
            success: true,
            sponsorship: sponsorship
        };
    },

    /**
     * Mock Upload Sync
     */
    mockUploadSync(data) {
        console.log('📤 Mock upload sync:', data);

        const { sponsorship_id, updates } = data;

        // البحث عن الكفالة وتحديثها
        const index = this.mockData.sponsorships.findIndex(sp => sp.id === sponsorship_id);

        if (index === -1) {
            throw new Error('الكفالة غير موجودة');
        }

        // تطبيق التحديثات
        const sponsorship = this.mockData.sponsorships[index];

        // تحديث الأسماء
        if (updates.first_name) sponsorship.orphan_first_name = updates.first_name;
        if (updates.second_name) sponsorship.orphan_father_name = updates.second_name;
        if (updates.third_name) sponsorship.orphan_grandfather_name = updates.third_name;
        if (updates.last_name) sponsorship.orphan_family_name = updates.last_name;

        // تحديث الاسم الكامل
        if (updates.first_name || updates.second_name || updates.third_name || updates.last_name) {
            sponsorship.orphan_name = `${updates.first_name || sponsorship.orphan_first_name} ${updates.second_name || sponsorship.orphan_father_name} ${updates.third_name || sponsorship.orphan_grandfather_name} ${updates.last_name || sponsorship.orphan_family_name}`.trim();
        }

        // تحديث بيانات المعيل
        if (updates.guardian_first_name) sponsorship.guardian_first_name = updates.guardian_first_name;
        if (updates.guardian_father_name) sponsorship.guardian_father_name = updates.guardian_father_name;
        if (updates.guardian_grandfather_name) sponsorship.guardian_grandfather_name = updates.guardian_grandfather_name;
        if (updates.guardian_family_name) sponsorship.guardian_family_name = updates.guardian_family_name;

        if (updates.guardian_first_name || updates.guardian_father_name || updates.guardian_grandfather_name || updates.guardian_family_name) {
            sponsorship.guardian_name = `${updates.guardian_first_name || sponsorship.guardian_first_name} ${updates.guardian_father_name || sponsorship.guardian_father_name} ${updates.guardian_grandfather_name || sponsorship.guardian_grandfather_name} ${updates.guardian_family_name || sponsorship.guardian_family_name}`.trim();
        }

        // تحديث الحقول الأخرى
        if (updates.identity_number) sponsorship.identity_number = updates.identity_number;
        if (updates.guardian_identity_number) sponsorship.guardian_identity_number = updates.guardian_identity_number;
        if (updates.guardian_phone) sponsorship.guardian_phone = updates.guardian_phone;
        if (updates.guardian_phone2) sponsorship.guardian_phone2 = updates.guardian_phone2;
        if (updates.guardian_detailed_address) sponsorship.guardian_detailed_address = updates.guardian_detailed_address;
        if (updates.orphan_phone) sponsorship.orphan_phone = updates.orphan_phone;
        if (updates.orphan_phone2) sponsorship.orphan_phone2 = updates.orphan_phone2;
        if (updates.orphan_detailed_address) sponsorship.orphan_detailed_address = updates.orphan_detailed_address;
        if (updates.birth_date) sponsorship.birth_date = updates.birth_date;
        if (updates.orphan_gender) sponsorship.orphan_gender = updates.orphan_gender;
        if (updates.person_type) sponsorship.person_type = updates.person_type;

        // حفظ التحديثات
        this.mockData.sponsorships[index] = sponsorship;
        localStorage.setItem('mock_sponsorships', JSON.stringify(this.mockData.sponsorships));

        // إضافة للتغييرات المعلقة
        this.mockData.pendingChanges.push({
            sponsorship_id,
            updates,
            timestamp: new Date().toISOString()
        });
        localStorage.setItem('pending_changes', JSON.stringify(this.mockData.pendingChanges));

        return {
            success: true,
            message: 'تم تحديث البيانات بنجاح (وضع offline)',
            sync_timestamp: new Date().toISOString(),
            pending_sync: true
        };
    },

    /**
     * Mock Full Sync
     */
    mockFullSync() {
        return {
            success: true,
            data: {
                sponsors: this.mockData.sponsors,
                sponsorship_statuses: this.mockData.sponsorshipStatuses,
                banks: this.mockData.banks,
                sponsorships: this.mockData.sponsorships
            },
            sync_timestamp: new Date().toISOString()
        };
    },

    /**
     * Mock Get Stats
     */
    mockGetStats() {
        return {
            success: true,
            stats: {
                total_sponsorships: this.mockData.sponsorships.length,
                pending_changes: this.mockData.pendingChanges.length,
                last_sync: localStorage.getItem('last_sync') || null
            }
        };
    },

    // ========================================
    // Public API Methods
    // ========================================

    async login(username, password, deviceId = null) {
        if (this.offlineMode) {
            return this.mockLogin({ username, password });
        }

        const data = await this.request('/mobile/login', {
            method: 'POST',
            body: JSON.stringify({ username, password, device_id: deviceId })
        });

        if (data.success) {
            this.token = data.token;
            this.user = data.user;
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_data', JSON.stringify(data.user));
        }

        return data;
    },

    async logout() {
        if (this.offlineMode) {
            return this.mockLogout();
        }

        try {
            if (this.token) {
                await this.request('/mobile/logout', { method: 'POST' });
            }
        } catch (e) {
            console.error('Logout error:', e);
        }

        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
    },

    async checkHealth() {
        return await this.request('/mobile/health', { method: 'GET' });
    },

    async getSponsors() {
        return await this.request('/mobile/sponsors');
    },

    async getSponsorshipStatuses() {
        return await this.request('/mobile/sponsorship-statuses');
    },

    async getBanks() {
        return await this.request('/mobile/banks');
    },

    async getSponsorships(options = {}) {
        const params = new URLSearchParams();
        if (options.page) params.append('page', options.page);
        if (options.perPage) params.append('per_page', options.perPage);
        if (options.search) params.append('search', options.search);

        const queryString = params.toString();
        return await this.request(`/mobile/sync/sponsorships${queryString ? '?' + queryString : ''}`);
    },

    async getSponsorshipDetails(id) {
        return await this.request(`/mobile/sync/sponsorship/${id}`);
    },

    async uploadSyncData(sponsorshipId, updates) {
        return await this.request('/mobile/sync/upload', {
            method: 'POST',
            body: JSON.stringify({ sponsorship_id: sponsorshipId, updates })
        });
    },

    /**
     * Get pending changes count
     */
    getPendingChangesCount() {
        return this.mockData.pendingChanges.length;
    },

    /**
     * Clear pending changes
     */
    clearPendingChanges() {
        this.mockData.pendingChanges = [];
        localStorage.removeItem('pending_changes');
    },

    /**
     * Reset mock data to defaults
     */
    resetMockData() {
        localStorage.removeItem('mock_sponsorships');
        localStorage.removeItem('pending_changes');
        this.init();
        console.log('🔄 Mock data reset to defaults');
    },

    // ========================================
    // Utility Functions
    // ========================================

    normalizeArabic(text) {
        if (!text) return '';
        text = text.replace(/[\u064B-\u065F]/g, '');
        text = text.replace(/[أإآٱ]/g, 'ا');
        text = text.replace(/[ىئ]/g, 'ي');
        text = text.replace(/ة/g, 'ه');
        text = text.replace(/ؤ/g, 'و');
        return text.trim();
    },

    formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('ar-SA');
    },

    // ========================================
    // Cached Data Methods (للتوافق مع الكود الأصلي)
    // ========================================

    getCachedAssociations() {
        if (this.offlineMode) {
            return this.mockData.sponsors;
        }
        const cached = localStorage.getItem('associations');
        return cached ? JSON.parse(cached) : [];
    },

    getCachedStatuses() {
        if (this.offlineMode) {
            return this.mockData.sponsorshipStatuses;
        }
        const cached = localStorage.getItem('sponsorship_statuses');
        return cached ? JSON.parse(cached) : [];
    },

    getCachedOrphanCounts() {
        const cached = localStorage.getItem('orphan_counts');
        return cached ? JSON.parse(cached) : {};
    },

    async getInitialSync() {
        if (this.offlineMode) {
            return {
                success: true,
                data: {
                    associations: this.mockData.sponsors,
                    sponsorship_statuses: this.mockData.sponsorshipStatuses
                }
            };
        }
        return await this.request('/mobile/sync/initial');
    },

    async getOrphans(options = {}) {
        return await this.getSponsorships(options);
    },

    async getOrphanDetails(registrationId) {
        return await this.getSponsorshipDetails(registrationId);
    }
};

// Initialize on load
ApiService.init();

// Log mode
console.log('📱 API Service loaded in OFFLINE mode');
console.log('📊 Mock sponsorships:', ApiService.mockData.sponsorships.length);

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ApiService;
}
