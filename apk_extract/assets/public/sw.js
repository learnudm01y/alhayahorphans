/**
 * Service Worker for AlHayah Sponsorships App
 * Enables offline functionality
 */

const CACHE_NAME = 'alhayah-sponsorships-v28';
const OFFLINE_PAGES = [
    '/',
    '/index.html',
    '/login.html',
    '/data.html',
    '/detail.html',
    '/photography.html',
    '/upload.html',
    '/sync-monitor.html',
    '/css/material-icons.css',
    '/css/icons-fallback.css',
    '/css/tajawal-fonts.css',
    '/js/sync-service.js',
    '/js/api-service.js',
    '/assets/logo.png'
];

// Install event - cache offline pages
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Caching offline pages');
            return cache.addAll(OFFLINE_PAGES).catch(err => {
                console.error('[SW] Failed to cache some resources:', err);
                // Don't fail installation if some resources fail
                return Promise.resolve();
            });
        })
    );
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip API requests - always go to network
    if (event.request.url.includes('/api/')) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((response) => {
            if (response) {
                // Return cached version
                return response;
            }

            // Not in cache, fetch from network
            return fetch(event.request).then((networkResponse) => {
                // Cache the new response if it's successful
                if (networkResponse && networkResponse.status === 200) {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                }
                return networkResponse;
            }).catch(() => {
                // Network failed, return offline page if available
                if (event.request.mode === 'navigate') {
                    return caches.match('/index.html');
                }
            });
        })
    );
});

// Background sync for data upload
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);
    if (event.tag === 'sync-sponsorships' || event.tag === 'sync-pending-actions') {
        event.waitUntil(syncSponsorships());
    }
});

async function syncSponsorships() {
    try {
        // This will be handled by sync-service.js
        console.log('[SW] Syncing sponsorships in background');
        // Send message to all clients to trigger sync
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'BACKGROUND_SYNC',
                action: 'sync-sponsorships'
            });
        });
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
        throw error;
    }
}
