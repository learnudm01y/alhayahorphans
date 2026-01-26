/**
 * ===================================================================
 * ONLINE SYNC SERVICE - Clean Architecture
 * ===================================================================
 * النسخة Online النظيفة - بدون offline caching
 * جميع البيانات من السيرفر مباشرة
 */

console.log('🌐🌐🌐 LOADING ONLINE SYNC SERVICE 🌐🌐🌐');

const OnlineService = {
    // Configuration
    baseUrl: 'https://alhayahorphans.org/api/online',
    token: null,
    user: null,

    // Cache مؤقت للصفحة الحالية فقط (يُمسح عند إغلاق التطبيق)
    tempCache: {
        sponsors: null,
        statuses: null,
        sponsorshipTypes: null,
        healthStatuses: null,
        cities: null,
        bankNames: null,
        lastSync: null
    },

    // ========================================
    // Initialization
    // ========================================

    async init() {
        console.log('🔧 Initializing OnlineService (ONLINE MODE)');
        console.log('📡 API Base URL:', this.baseUrl);

        // Load token from localStorage
        this.token = localStorage.getItem('auth_token');
        const userData = localStorage.getItem('user_data');
        if (userData) {
            try {
                this.user = JSON.parse(userData);
            } catch (e) {
                console.error('Failed to parse user data');
            }
        }

        console.log('✅ OnlineService initialized');
    },

    // ========================================
    // API Request Helper
    // ========================================

    async apiRequest(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(this.token && { 'Authorization': `Bearer ${this.token}` })
        };

        console.log('📡 API Request:', options.method || 'GET', url);

        try {
            const response = await fetch(url, {
                ...options,
                headers: options.headers ? { ...headers, ...options.headers } : headers
            });

            if (response.status === 401) {
                throw new Error('انتهت صلاحية الجلسة');
            }

            const data = await response.json();
            console.log('✅ API Response:', data);

            if (!response.ok) {
                throw new Error(data.message || 'فشل الاتصال بالخادم');
            }

            return data;
        } catch (error) {
            console.error('❌ API Request failed:', error.message);
            throw error;
        }
    },

    // ========================================
    // Authentication
    // ========================================

    async login(username, password) {
        try {
            const data = await this.apiRequest('/login', {
                method: 'POST',
                body: JSON.stringify({ username, password })
            });

            if (data.success) {
                this.token = data.token;
                this.user = data.user;
                localStorage.setItem('auth_token', data.token);
                localStorage.setItem('user_data', JSON.stringify(data.user));
                console.log('✅ Login successful:', data.user.name);
            }

            return data;
        } catch (error) {
            console.error('Login failed:', error.message);
            throw error;
        }
    },

    async logout() {
        try {
            await this.apiRequest('/logout', { method: 'POST' });
        } catch (e) {
            console.warn('Logout API call failed, continuing...');
        }

        this.token = null;
        this.user = null;
        this.tempCache = {};
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        console.log('✅ Logged out');
    },

    isAuthenticated() {
        return !!this.token && !!this.user;
    },

    // ========================================
    // Initial Sync - جلب البيانات الأساسية
    // ========================================

    async performInitialSync() {
        try {
            console.log('🔄 Starting initial sync (ONLINE MODE)...');

            const response = await this.apiRequest('/sync/initial');

            if (response.success) {
                // حفظ مؤقت في الذاكرة فقط (لا يُحفظ في IndexedDB)
                this.tempCache.sponsors = response.data.sponsors || [];
                this.tempCache.statuses = response.data.sponsorship_statuses || [];
                this.tempCache.sponsorshipTypes = response.data.sponsorship_types || [];
                this.tempCache.healthStatuses = response.data.health_statuses || [];
                this.tempCache.cities = response.data.cities || [];
                this.tempCache.bankNames = response.data.bank_names || [];
                this.tempCache.lastSync = new Date().toISOString();

                console.log('✅ Initial sync completed:', {
                    sponsors: this.tempCache.sponsors.length,
                    statuses: this.tempCache.statuses.length,
                    sponsorshipTypes: this.tempCache.sponsorshipTypes.length,
                    healthStatuses: this.tempCache.healthStatuses.length,
                    cities: this.tempCache.cities.length,
                    bankNames: this.tempCache.bankNames.length
                });

                return {
                    success: true,
                    counts: {
                        sponsors: this.tempCache.sponsors.length,
                        statuses: this.tempCache.statuses.length,
                        sponsorshipTypes: this.tempCache.sponsorshipTypes.length,
                        healthStatuses: this.tempCache.healthStatuses.length,
                        cities: this.tempCache.cities.length,
                        bankNames: this.tempCache.bankNames.length
                    }
                };
            }

            throw new Error('فشل المزامنة الأولية');

        } catch (error) {
            console.error('❌ Initial sync failed:', error.message);
            throw error;
        }
    },

    // ========================================
    // Sponsorships - جلب الكفالات مباشرة من السيرفر
    // ========================================

    async getSponsorships(page = 1, filters = {}) {
        try {
            const params = new URLSearchParams({
                page: page,
                per_page: filters.perPage || 50,
                ...(filters.sponsorId && { sponsor_id: filters.sponsorId }),
                ...(filters.statusId && { status_id: filters.statusId }),
                ...(filters.search && { search: filters.search })
            });

            const response = await this.apiRequest(`/sponsorships?${params.toString()}`);

            return response;
        } catch (error) {
            console.error('Failed to fetch sponsorships:', error.message);
            throw error;
        }
    },

    async getSponsorshipDetails(id) {
        try {
            const response = await this.apiRequest(`/sponsorship/${id}`);
            return response;
        } catch (error) {
            console.error('Failed to fetch sponsorship details:', error.message);
            throw error;
        }
    },

    // ========================================
    // Lookup Data - من الـ cache المؤقت
    // ========================================

    getLocalSponsors() {
        return this.tempCache.sponsors || [];
    },

    getLocalStatuses() {
        return this.tempCache.statuses || [];
    },

    getLocalSponsorshipTypes() {
        return this.tempCache.sponsorshipTypes || [];
    },

    getLocalHealthStatuses() {
        return this.tempCache.healthStatuses || [];
    },

    getLocalCities() {
        return this.tempCache.cities || [];
    },

    getLocalBankNames() {
        return this.tempCache.bankNames || [];
    },

    // ========================================
    // Updates - تحديث البيانات مباشرة
    // ========================================

    async updateSponsorship(sponsorshipId, updates) {
        try {
            const response = await this.apiRequest('/update-sponsorship', {
                method: 'POST',
                body: JSON.stringify({
                    sponsorship_id: sponsorshipId,
                    updates: updates
                })
            });

            console.log('✅ Sponsorship updated successfully');
            return response;
        } catch (error) {
            console.error('Failed to update sponsorship:', error.message);
            throw error;
        }
    },

    async uploadPhoto(sponsorshipId, photoType, photoFile) {
        try {
            const formData = new FormData();
            formData.append('sponsorship_id', sponsorshipId);
            formData.append('photo_type', photoType); // 'orphan' or 'guardian'
            formData.append('photo', photoFile);

            const response = await this.apiRequest('/update-photo', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`
                    // Don't set Content-Type, let browser set it with boundary
                },
                body: formData
            });

            console.log('✅ Photo uploaded successfully');
            return response;
        } catch (error) {
            console.error('Failed to upload photo:', error.message);
            throw error;
        }
    },

    async uploadFile(file, metadata = {}) {
        try {
            const formData = new FormData();
            formData.append('file', file);
            Object.keys(metadata).forEach(key => {
                formData.append(key, metadata[key]);
            });

            const response = await this.apiRequest('/upload-file', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`
                },
                body: formData
            });

            return response;
        } catch (error) {
            console.error('File upload failed:', error.message);
            throw error;
        }
    },

    // ========================================
    // Push Notifications
    // ========================================

    async registerDevice(deviceToken, deviceId, platform = 'android') {
        try {
            const response = await this.apiRequest('/register-device', {
                method: 'POST',
                body: JSON.stringify({
                    device_token: deviceToken,
                    device_id: deviceId,
                    platform: platform
                })
            });

            console.log('✅ Device registered for push notifications');
            return response;
        } catch (error) {
            console.error('Device registration failed:', error.message);
            throw error;
        }
    },

    async unregisterDevice(deviceId) {
        try {
            await this.apiRequest('/unregister-device', {
                method: 'POST',
                body: JSON.stringify({ device_id: deviceId })
            });

            console.log('✅ Device unregistered');
        } catch (error) {
            console.error('Device unregistration failed:', error.message);
        }
    },

    // ========================================
    // Health Check
    // ========================================

    async checkHealth() {
        try {
            const response = await this.apiRequest('/health');
            return response;
        } catch (error) {
            console.error('Health check failed:', error.message);
            throw error;
        }
    }
};

// Initialize on load
if (typeof window !== 'undefined') {
    window.OnlineService = OnlineService;
    OnlineService.init();
}

console.log('📱 OnlineService loaded (ONLINE MODE) - No offline caching');
console.log('   📡 API URL:', OnlineService.baseUrl);
console.log('   🔧 Mode: Direct API calls only');
