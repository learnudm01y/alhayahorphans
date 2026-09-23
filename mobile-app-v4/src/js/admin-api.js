/**
 * admin-api.js — طبقة API موحدة لشاشات لوحة التحكم v4
 * تعمل online (fetch) + offline (SyncClientV4 bridge + localStorage cache)
 */
(function () {
    'use strict';

    var PLUGIN = 'JavaScriptBridgeV4';
    var CACHE_PREFIX = 'v4_admin_cache_';

    function getBase() {
        return (window.APP_CONFIG && window.APP_CONFIG.API_URL)
            || 'https://alhayahorphans.org/api';
    }

    function getToken() {
        try { return localStorage.getItem('api_token') || ''; }
        catch (e) { return ''; }
    }

    function bridge() {
        if (window.Capacitor && window.Capacitor.Plugins
            && window.Capacitor.Plugins[PLUGIN]) {
            return window.Capacitor.Plugins[PLUGIN];
        }
        return null;
    }

    function callBridge(method, args) {
        return new Promise(function (resolve, reject) {
            var b = bridge();
            if (!b || typeof b[method] !== 'function') {
                reject(new Error('Bridge.' + method + ' unavailable'));
                return;
            }
            try {
                var p = b[method](args || {});
                if (p && typeof p.then === 'function') p.then(resolve).catch(reject);
                else resolve(p);
            } catch (e) { reject(e); }
        });
    }

    function cacheSet(key, data) {
        try { localStorage.setItem(CACHE_PREFIX + key, JSON.stringify(data)); }
        catch (e) {}
    }

    function cacheGet(key) {
        try { return JSON.parse(localStorage.getItem(CACHE_PREFIX + key) || 'null'); }
        catch (e) { return null; }
    }

    function http(method, path, body) {
        var url = getBase() + path;
        var opts = {
            method: method,
            headers: {
                'Authorization': 'Bearer ' + getToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        };
        if (body && method !== 'GET') opts.body = JSON.stringify(body);
        if (method === 'GET' && body) {
            var qs = Object.keys(body).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]);
            }).join('&');
            url += (url.indexOf('?') >= 0 ? '&' : '?') + qs;
        }
        return fetch(url, opts).then(function (r) { return r.json(); });
    }

    function withCache(key, fetcher) {
        return fetcher().then(function (j) {
            if (j && (j.success || j.data || j.snapshot)) cacheSet(key, j);
            return j;
        }).catch(function () {
            var cached = cacheGet(key);
            if (cached) { cached.offline = true; return cached; }
            return { success: false, offline: true };
        });
    }

    window.AdminAPI = {
        // ---- Dashboard ----
        getDashboard: function () {
            if (bridge()) return callBridge('getDashboardSnapshot');
            return withCache('dashboard', function () {
                return http('GET', '/mobile/v4/admin/dashboard-snapshot');
            });
        },

        // ---- Generic data query (via bridge or HTTP) ----
        query: function (type, params) {
            if (bridge() && type !== 'search') {
                return callBridge('queryReportRows', { type: type, search: (params && params.search) || '' });
            }
            return withCache('query_' + type + '_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/admin/reports-source', Object.assign({ type: type }, params || {}));
            });
        },

        // ---- Records ----
        getRecords: function (params) {
            return withCache('records_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/records', params);
            });
        },
        getRecord: function (id) {
            return withCache('record_' + id, function () {
                return http('GET', '/mobile/v4/records/' + id);
            });
        },
        createRecord: function (data) {
            return http('POST', '/mobile/v4/records', data);
        },
        updateRecord: function (id, data) {
            return http('PUT', '/mobile/v4/records/' + id, data);
        },
        deleteRecord: function (id) {
            return http('DELETE', '/mobile/v4/records/' + id);
        },

        // ---- Search ----
        search: function (params) {
            return withCache('search_' + JSON.stringify(params || {}), function () {
                return http('POST', '/mobile/v4/search-records', params);
            });
        },
        searchSuggestions: function (q) {
            return http('GET', '/mobile/v4/search-suggestions', { q: q });
        },

        // ---- Categories (generic CRUD) ----
        getCategories: function (table, params) {
            return withCache('cat_' + table + '_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/categories/' + table, params);
            });
        },
        createCategory: function (table, data) {
            return http('POST', '/mobile/v4/categories/' + table, data);
        },
        updateCategory: function (table, id, data) {
            return http('PUT', '/mobile/v4/categories/' + table + '/' + id, data);
        },
        deleteCategory: function (table, id) {
            return http('DELETE', '/mobile/v4/categories/' + table + '/' + id);
        },

        // ---- Sponsorships ----
        getSponsorships: function (params) {
            return withCache('sponsorships_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/sponsorships', params);
            });
        },
        getSponsored: function (params) {
            return withCache('sponsored_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/sponsorships/sponsored', params);
            });
        },
        getUnsponsored: function (params) {
            return withCache('unsponsored_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/sponsorships/unsponsored', params);
            });
        },
        createSponsorship: function (data) {
            return http('POST', '/mobile/v4/sponsorships', data);
        },
        updateSponsorship: function (id, data) {
            return http('PUT', '/mobile/v4/sponsorships/' + id, data);
        },
        deleteSponsorship: function (id) {
            return http('DELETE', '/mobile/v4/sponsorships/' + id);
        },
        updateSponsorshipStatus: function (id, status) {
            return http('POST', '/mobile/v4/sponsorships/' + id + '/update-status', { status: status });
        },

        // ---- Sponsors (Associations) ----
        getSponsors: function (params) {
            return withCache('sponsors_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/sponsors', params);
            });
        },
        createSponsor: function (data) {
            return http('POST', '/mobile/v4/sponsors', data);
        },
        updateSponsor: function (id, data) {
            return http('PUT', '/mobile/v4/sponsors/' + id, data);
        },
        deleteSponsor: function (id) {
            return http('DELETE', '/mobile/v4/sponsors/' + id);
        },

        // ---- Users & Roles ----
        getUsers: function (params) {
            return withCache('users_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/users', params);
            });
        },
        createUser: function (data) {
            return http('POST', '/mobile/v4/users', data);
        },
        getRoles: function () {
            return withCache('roles', function () {
                return http('GET', '/mobile/v4/roles');
            });
        },
        createRole: function (data) {
            return http('POST', '/mobile/v4/roles', data);
        },
        updateRole: function (id, data) {
            return http('PUT', '/mobile/v4/roles/' + id, data);
        },
        deleteRole: function (id) {
            return http('DELETE', '/mobile/v4/roles/' + id);
        },

        // ---- Files ----
        getFiles: function (params) {
            return withCache('files_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/files', params);
            });
        },
        getFolders: function (params) {
            return withCache('folders_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/files/folders', params);
            });
        },
        getDuplicates: function (params) {
            return withCache('duplicates_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/files/duplicates', params);
            });
        },
        deleteFile: function (id) {
            if (bridge()) return callBridge('queueFileDelete', { id: id });
            return http('DELETE', '/mobile/v4/files/' + id);
        },
        queryFileIndex: function (search) {
            if (bridge()) return callBridge('queryFileIndex', { search: search || '' });
            return withCache('fileidx_' + search, function () {
                return http('GET', '/mobile/v4/files/index', { search: search });
            });
        },

        // ---- Civil Registry ----
        getCivilPersons: function (params) {
            return withCache('civil_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/civil-registry', params);
            });
        },
        searchCivil: function (params) {
            return http('GET', '/mobile/v4/civil-registry/search', params);
        },
        downloadCivilTemplate: function () {
            return fetch(getBase() + '/mobile/v4/civil-registry/import-template', {
                headers: { 'Authorization': 'Bearer ' + getToken() }
            }).then(function (r) { return r.blob(); });
        },
        validateCivilImport: function (formData) {
            return fetch(getBase() + '/mobile/v4/civil-registry/validate-import', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + getToken(), 'Accept': 'application/json' },
                body: formData
            }).then(function (r) { return r.json(); });
        },

        // ---- User Requests ----
        getUserRequests: function (params) {
            return withCache('userreq_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/user-requests', params);
            });
        },
        changeUserRequestStatus: function (id, status) {
            return http('POST', '/mobile/v4/user-requests/change-status', { id: id, status: status });
        },

        // ---- Audit ----
        getAudit: function (params) {
            return withCache('audit_' + JSON.stringify(params || {}), function () {
                return http('GET', '/mobile/v4/audit', params);
            });
        },

        // ---- Permissions ----
        getPermissions: function (userId) {
            if (bridge()) return callBridge('getPermissionsManifest', { user_id: userId });
            return withCache('perms_' + userId, function () {
                return http('GET', '/mobile/v4/admin/permissions-manifest', { user_id: userId });
            });
        },
        hasPermission: function (userId, perm, sensitive) {
            if (bridge()) return callBridge('hasPermission', { user_id: userId, permission: perm, sensitive: !!sensitive });
            var manifest = cacheGet('perms_' + userId);
            if (manifest && manifest.permissions) {
                return Promise.resolve(manifest.permissions.indexOf(perm) >= 0);
            }
            return Promise.resolve(false);
        },

        // ---- Profile ----
        updateProfile: function (data) {
            return http('POST', '/mobile/v4/profile/update', data);
        },
        updatePassword: function (data) {
            return http('POST', '/mobile/v4/profile/update-password', data);
        },

        // ---- Helpers ----
        http: http,
        cacheGet: cacheGet,
        cacheSet: cacheSet
    };
})();
