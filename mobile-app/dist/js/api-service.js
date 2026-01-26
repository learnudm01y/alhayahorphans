/**
 * API Service for Sponsorships Mobile App
 *
 * Handles all communication with the Laravel backend
 */

const ApiService = {
    // Base URL - يتم تغييره حسب البيئة
    baseUrl: 'https://alhayahorphans.org/api',

    // Authentication token
    token: null,

    // User data
    user: null,

    /**
     * Initialize the service
     */
    init() {
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
    },

    /**
     * Set base URL
     */
    setBaseUrl(url) {
        this.baseUrl = url;
        localStorage.setItem('api_base_url', url);
    },

    /**
     * Get stored base URL
     */
    getBaseUrl() {
        const stored = localStorage.getItem('api_base_url');
        if (stored) {
            this.baseUrl = stored;
        }
        return this.baseUrl;
    },

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!this.token;
    },

    /**
     * Make API request
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        // Add auth token if available
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const data = await response.json();

            // Handle 401 - unauthorized
            if (response.status === 401) {
                this.logout();
                throw new Error('انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.');
            }

            // Handle other errors
            if (!response.ok) {
                throw new Error(data.message || 'حدث خطأ في الاتصال');
            }

            return data;

        } catch (error) {
            if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                throw new Error('تعذر الاتصال بالخادم. تحقق من اتصال الإنترنت.');
            }
            throw error;
        }
    },

    // ========================================
    // Authentication APIs
    // ========================================

    /**
     * Login user
     */
    async login(username, password, deviceId = null) {
        const data = await this.request('/mobile/login', {
            method: 'POST',
            body: JSON.stringify({ username, password, device_id: deviceId })
        });

        if (data.success) {
            this.token = data.token;
            this.user = data.user;

            // Save to storage
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_data', JSON.stringify(data.user));
            localStorage.setItem('token_expires', data.expires_at);
        }

        return data;
    },

    /**
     * Logout user
     */
    async logout() {
        try {
            if (this.token) {
                await this.request('/mobile/logout', { method: 'POST' });
            }
        } catch (e) {
            console.error('Logout error:', e);
        }

        // Clear local data
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        localStorage.removeItem('token_expires');
    },

    /**
     * Check API health
     */
    async checkHealth() {
        return await this.request('/mobile/health', { method: 'GET' });
    },

    // ========================================
    // Initial Sync APIs
    // ========================================

    /**
     * Get all initial sync data (associations + statuses + counts)
     */
    async getInitialSync() {
        const data = await this.request('/mobile/sync/initial');

        if (data.success) {
            // Cache the data locally
            localStorage.setItem('associations', JSON.stringify(data.data.associations));
            localStorage.setItem('sponsorship_statuses', JSON.stringify(data.data.sponsorship_statuses));
            localStorage.setItem('orphan_counts', JSON.stringify(data.data.orphan_counts));
            localStorage.setItem('last_sync', data.sync_timestamp);
        }

        return data;
    },

    /**
     * Get associations (sponsors)
     */
    async getAssociations() {
        return await this.request('/mobile/associations');
    },

    /**
     * Get sponsorship statuses
     */
    async getSponsorshipStatuses() {
        return await this.request('/mobile/sponsorship-statuses');
    },

    // ========================================
    // Orphans APIs
    // ========================================

    /**
     * Get orphans with filters
     */
    async getOrphans(options = {}) {
        const params = new URLSearchParams();

        if (options.statusId !== undefined && options.statusId !== '') {
            params.append('status_id', options.statusId);
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

        const queryString = params.toString();
        return await this.request(`/mobile/orphans${queryString ? '?' + queryString : ''}`);
    },

    /**
     * Get orphan details
     */
    async getOrphanDetails(registrationId) {
        return await this.request(`/mobile/orphan/${registrationId}`);
    },

    // ========================================
    // Cached Data Access
    // ========================================

    /**
     * Get cached associations
     */
    getCachedAssociations() {
        const data = localStorage.getItem('associations');
        return data ? JSON.parse(data) : [];
    },

    /**
     * Get cached sponsorship statuses
     */
    getCachedStatuses() {
        const data = localStorage.getItem('sponsorship_statuses');
        return data ? JSON.parse(data) : [];
    },

    /**
     * Get cached orphan counts
     */
    getCachedOrphanCounts() {
        const data = localStorage.getItem('orphan_counts');
        return data ? JSON.parse(data) : [];
    },

    /**
     * Get last sync timestamp
     */
    getLastSync() {
        return localStorage.getItem('last_sync');
    },

    // ========================================
    // Utility Functions
    // ========================================

    /**
     * Normalize Arabic text for search
     */
    normalizeArabic(text) {
        if (!text) return '';

        // Remove diacritics (tashkeel)
        text = text.replace(/[\u064B-\u065F]/g, '');

        // Normalize alef variations
        text = text.replace(/[أإآٱ]/g, 'ا');

        // Normalize ya and alef maqsura
        text = text.replace(/[ىئ]/g, 'ي');

        // Normalize ta marbuta
        text = text.replace(/ة/g, 'ه');

        // Normalize waw with hamza
        text = text.replace(/ؤ/g, 'و');

        return text.trim();
    },

    /**
     * Format date for display
     */
    formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('ar-SA');
    }
};

// Initialize on load
ApiService.init();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ApiService;
}
