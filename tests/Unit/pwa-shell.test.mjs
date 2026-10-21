import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const serviceWorkerSource = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');

function createServiceWorker({ fetchImpl = async () => new Response('private response'), cachedOfflinePage = new Response('offline') } = {}) {
    const listeners = new Map();
    const cacheEntries = [];
    const deletedCaches = [];
    const cache = { add: async (path) => cacheEntries.push(path) };
    const context = {
        Response,
        Promise,
        caches: {
            open: async () => cache,
            keys: async () => ['other-app-cache', 'barberhub-shell-old', 'barberhub-shell-v1'],
            delete: async (name) => deletedCaches.push(name),
            match: async (path) => path === '/offline.html' ? cachedOfflinePage : undefined,
        },
        fetch: fetchImpl,
        self: {
            clients: { claim: async () => {} },
            skipWaiting: async () => {},
            addEventListener: (name, callback) => listeners.set(name, callback),
        },
    };
    vm.runInNewContext(serviceWorkerSource, context);

    return { listeners, cacheEntries, deletedCaches };
}

test('installation caches only the static offline explanation and activation only removes this app old cache', async () => {
    const worker = createServiceWorker();
    let installWork;
    worker.listeners.get('install')({ waitUntil: (promise) => { installWork = promise; } });
    await installWork;

    let activationWork;
    worker.listeners.get('activate')({ waitUntil: (promise) => { activationWork = promise; } });
    await activationWork;

    assert.deepEqual(worker.cacheEntries, ['/offline.html']);
    assert.deepEqual(worker.deletedCaches, ['barberhub-shell-old']);
});

test('failed authenticated navigation returns an explicit offline page without storing its response', async () => {
    const worker = createServiceWorker({ fetchImpl: async () => { throw new Error('offline'); } });
    let responsePromise;
    worker.listeners.get('fetch')({
        request: { mode: 'navigate', method: 'GET', url: 'https://example.test/barbershops/private/appointments' },
        respondWith: (promise) => { responsePromise = promise; },
    });

    const response = await responsePromise;

    assert.equal(await response.text(), 'offline');
    assert.deepEqual(worker.cacheEntries, []);
});

test('non-navigation requests are left to the browser network without interception', () => {
    const worker = createServiceWorker();
    let intercepted = false;
    worker.listeners.get('fetch')({
        request: { mode: 'cors', method: 'GET', url: 'https://example.test/api/private' },
        respondWith: () => { intercepted = true; },
    });

    assert.equal(intercepted, false);
});
