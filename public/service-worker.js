const CACHE_NAME = 'barberhub-shell-v1';
const OFFLINE_PAGE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        await cache.add(OFFLINE_PAGE);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names
            .filter((name) => name.startsWith('barberhub-shell-') && name !== CACHE_NAME)
            .map((name) => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.mode !== 'navigate' || request.method !== 'GET') {
        return;
    }

    event.respondWith(fetch(request).catch(async () => {
        const offlinePage = await caches.match(OFFLINE_PAGE);

        return offlinePage || new Response('Sin conexión. Conéctate a internet y vuelve a cargar.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }));
});
