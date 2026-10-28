// Only this static, self-contained document may be stored by this worker.
const CACHE_PREFIX = 'bcc-offline-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)));
    // Let an update wait until existing pages close.
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((names) => Promise.all(
        names.filter((name) => name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME)
            .map((name) => caches.delete(name)),
    )));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    const bypass = /^\/(?:manage|api|auth|reauth|login|register|logout|forgot-password|reset-password|verify-email|email|confirm-password)(?:\/|$)/;
    if (request.method !== 'GET' || request.mode !== 'navigate'
        || url.origin !== self.location.origin || request.headers.has('X-Inertia')
        || bypass.test(url.pathname)) {
        return;
    }

    // HTTP errors remain server responses. Only a network failure gets fallback.
    event.respondWith(fetch(request).catch(async (error) => {
        const cache = await caches.open(CACHE_NAME);
        const offline = await cache.match(OFFLINE_URL);
        if (offline) return offline;
        throw error;
    }));
});
