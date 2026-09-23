/**
 * sync-client-v4.js — عميل المزامنة الموحّد v4 للشاشات.
 * يتحدث فقط مع JavaScriptBridgeV4 + UnifiedSyncOrchestratorV4.
 * لا يلمس أي endpoint قديم من v3.
 */
(function () {
    'use strict';

    var PLUGIN = 'JavaScriptBridgeV4';

    function bridge() {
        if (window.Capacitor && window.Capacitor.Plugins
            && window.Capacitor.Plugins[PLUGIN]) {
            return window.Capacitor.Plugins[PLUGIN];
        }
        return null;
    }

    function call(method, args) {
        return new Promise(function (resolve, reject) {
            var b = bridge();
            if (!b || typeof b[method] !== 'function') {
                reject(new Error('JavaScriptBridgeV4.' + method + ' unavailable'));
                return;
            }
            try {
                var p = b[method](args || {});
                if (p && typeof p.then === 'function') {
                    p.then(resolve).catch(reject);
                } else {
                    resolve(p);
                }
            } catch (e) {
                reject(e);
            }
        });
    }

    window.SyncClientV4 = {
        getDashboard: function () { return call('getDashboardSnapshot'); },
        queryReports: function (type, search) {
            return call('queryReportRows', { type: type || 'all', search: search || '' });
        },
        getReportBundle: function (type) { return call('getReportBundle', { type: type }); },
        generateReportPdf: function (type, title) {
            return call('generateReportPdf', { type: type, title: title });
        },
        getPermissions: function (userId) {
            return call('getPermissionsManifest', { user_id: userId });
        },
        hasPermission: function (userId, permission, sensitive) {
            return call('hasPermission', {
                user_id: userId, permission: permission, sensitive: !!sensitive
            });
        },
        queryFileIndex: function (search) {
            return call('queryFileIndex', { search: search || '' });
        },
        queueFileDelete: function (info) { return call('queueFileDelete', info); },
        queueFileRename: function (info) { return call('queueFileRename', info); },
        queueFileMove: function (info) { return call('queueFileMove', info); },
        getSyncHealth: function () { return call('getSyncHealth'); },

        // Conflicts — online-capable, offline falls back to last cached list
        getConflicts: function (status) {
            return new Promise(function (resolve) {
                var base = (window.APP_CONFIG && window.APP_CONFIG.API_URL)
                    || 'https://alhayahorphans.org/api';
                var token = '';
                try {
                    token = localStorage.getItem('api_token') || '';
                } catch (e) {}
                fetch(base + '/mobile/v4/conflicts?status=' + encodeURIComponent(status || 'open'), {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                }).then(function (r) { return r.json(); })
                    .then(function (j) {
                        try { localStorage.setItem('v4_conflicts_cache', JSON.stringify(j)); } catch (e) {}
                        resolve(j);
                    })
                    .catch(function () {
                        var cached = null;
                        try { cached = JSON.parse(localStorage.getItem('v4_conflicts_cache') || 'null'); } catch (e) {}
                        if (cached) { cached.offline = true; resolve(cached); }
                        else resolve({ success: false, offline: true, data: [] });
                    });
            });
        },
        resolveConflict: function (id, decision, notes) {
            var base = (window.APP_CONFIG && window.APP_CONFIG.API_URL)
                || 'https://alhayahorphans.org/api';
            var token = '';
            try { token = localStorage.getItem('api_token') || ''; } catch (e) {}
            return fetch(base + '/mobile/v4/conflicts/' + id + '/resolve', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ decision: decision, notes: notes || null })
            }).then(function (r) { return r.json(); });
        },
        getDeviceHealth: function () {
            var base = (window.APP_CONFIG && window.APP_CONFIG.API_URL)
                || 'https://alhayahorphans.org/api';
            var token = '';
            try { token = localStorage.getItem('api_token') || ''; } catch (e) {}
            return fetch(base + '/mobile/v4/device/health', {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            }).then(function (r) { return r.json(); })
                .catch(function () {
                    var cached = null;
                    try { cached = JSON.parse(localStorage.getItem('v4_devices_cache') || 'null'); } catch (e) {}
                    if (cached) { cached.offline = true; return cached; }
                    return { success: false, offline: true, devices: [] };
                })
                .then(function (j) {
                    if (j && j.success) {
                        try { localStorage.setItem('v4_devices_cache', JSON.stringify(j)); } catch (e) {}
                    }
                    return j;
                });
        }
    };
})();
