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

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload;
    try {
        payload = event.data.json();
    } catch {
        return;
    }

    const url = typeof payload.url === 'string' ? payload.url : '/';
    const title = typeof payload.title === 'string' ? payload.title : 'Aviso de agenda';
    const body = typeof payload.body === 'string' ? payload.body : 'Tienes una actualización en tu agenda.';

    event.waitUntil(self.registration.showNotification(title, {
        body,
        icon: '/icons/barberhub-192.png',
        badge: '/icons/barberhub-192.png',
        data: { url },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const destination = event.notification.data?.url;
    if (typeof destination !== 'string' || !destination.startsWith('/') || destination.startsWith('//')) {
        return;
    }

    let target;
    try {
        target = new URL(destination, self.location.origin);
    } catch {
        return;
    }

    if (target.origin !== self.location.origin || !/^\/barbershops\/[^/]+\/appointments$/.test(target.pathname)) {
        return;
    }

    event.waitUntil(self.clients.openWindow(target.href));
});
