import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const serviceWorkerSource = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');
const pwaClientSource = await readFile(new URL('../../resources/js/pwa.js', import.meta.url), 'utf8');

function createPushWorker() {
    const listeners = new Map();
    const context = {
        URL,
        Promise,
        self: {
            location: { origin: 'https://barberhub.test' },
            clients: {
                matchAll: async () => [],
                openWindow: async (url) => { context.openedUrl = url; },
            },
            addEventListener: (name, callback) => listeners.set(name, callback),
        },
    };
    vm.runInNewContext(serviceWorkerSource, context);

    return { context, listeners };
}

async function deliverPush(worker, payload) {
    let pushWork;
    const listener = worker.listeners.get('push');
    assert.equal(typeof listener, 'function', 'service worker handles push events');
    listener({
        data: { json: () => payload },
        waitUntil: (promise) => { pushWork = promise; },
    });
    await pushWork;

    return worker.context.notification;
}

async function clickNotification(worker, notification) {
    let clickWork;
    const listener = worker.listeners.get('notificationclick');
    assert.equal(typeof listener, 'function', 'service worker handles notification clicks');
    listener({
        notification: { ...notification, close() {} },
        waitUntil: (promise) => { clickWork = promise; },
    });
    await clickWork;
}

function createElement(dataset = {}) {
    const listeners = new Map();
    const classes = new Set(['hidden']);

    return {
        dataset,
        listeners,
        disabled: false,
        textContent: '',
        classList: {
            add: (name) => classes.add(name),
            remove: (name) => classes.delete(name),
            toggle: (name, force) => force ? classes.add(name) : classes.delete(name),
            contains: (name) => classes.has(name),
        },
        addEventListener: (name, callback) => listeners.set(name, callback),
    };
}

function createPwaClient({ userAgent = 'Android', standalone = false, permission = 'default', supportsPush = true, fetchImpl = async () => ({ ok: true }) } = {}) {
    const listeners = new Map();
    const pushButton = createElement();
    const pushStatus = createElement();
    const iosPushInstructions = createElement();
    const controls = createElement({ vapidPublicKey: 'AQID', subscribeUrl: '/barbershops/centro/push-subscriptions' });
    const requestPermissionCalls = [];
    const notificationApi = {
        permission,
        requestPermission: async () => {
            requestPermissionCalls.push(true);
            return 'granted';
        },
    };
    const navigator = {
        onLine: true,
        userAgent,
        standalone,
        serviceWorker: supportsPush ? {
            register: async () => ({ pushManager: { subscribe: async () => ({ toJSON: () => ({ endpoint: 'https://push.test', keys: { p256dh: 'key', auth: 'auth' } }) }) } }),
        } : undefined,
    };
    if (!supportsPush) {
        delete navigator.serviceWorker;
    }
    const windowObject = {
        isSecureContext: true,
        MSStream: undefined,
        navigator,
        Notification: notificationApi,
        atob: (value) => Buffer.from(value, 'base64').toString('binary'),
        matchMedia: () => ({ matches: standalone }),
        addEventListener: (name, callback) => listeners.set(name, callback),
    };
    if (supportsPush) {
        windowObject.PushManager = class PushManager {};
    }
    const elements = {
        '[data-pwa-install]': createElement(),
        '[data-ios-install]': createElement(),
        '[data-pwa-unavailable]': createElement(),
        '[data-pwa-offline]': createElement(),
        '[data-push-controls]': controls,
        '[data-push-enable]': pushButton,
        '[data-push-status]': pushStatus,
        '[data-ios-push-install]': iosPushInstructions,
        'meta[name="csrf-token"]': { content: 'csrf-token' },
    };
    const document = { querySelector: (selector) => elements[selector] ?? null };
    const context = {
        document,
        navigator,
        Notification: notificationApi,
        PushManager: supportsPush ? class PushManager {} : undefined,
        fetch: fetchImpl,
        Uint8Array,
        Promise,
        atob: (value) => Buffer.from(value, 'base64').toString('binary'),
        window: windowObject,
    };
    vm.runInNewContext(pwaClientSource, context);

    return { controls, iosPushInstructions, pushButton, pushStatus, requestPermissionCalls };
}

test('push display uses generic appointment copy and a root-relative same-origin agenda path', async () => {
    const worker = createPushWorker();
    worker.context.self.registration = {
        showNotification: async (title, options) => { worker.context.notification = { title, ...options }; },
    };
    const notification = await deliverPush(worker, {
        title: 'Nueva cita asignada',
        body: 'Tienes una cita nueva en tu agenda.',
        url: '/barbershops/centro/appointments?date=2026-10-05',
    });

    assert.equal(notification.title, 'Nueva cita asignada');
    assert.equal(notification.body, 'Tienes una cita nueva en tu agenda.');
    assert.equal(notification.data.url, '/barbershops/centro/appointments?date=2026-10-05');
    assert.doesNotMatch(JSON.stringify(notification), /cliente|client|apellido|nombre/i);
});

test('notification click opens the validated same-origin tenant agenda path', async () => {
    const worker = createPushWorker();
    const notification = { data: { url: '/barbershops/centro/appointments?date=2026-10-05' } };

    await clickNotification(worker, notification);

    assert.equal(worker.context.openedUrl, 'https://barberhub.test/barbershops/centro/appointments?date=2026-10-05');
});

test('notification click rejects protocol-relative and external destinations', async () => {
    for (const url of ['//evil.test/barbershops/centro/appointments?date=2026-10-05', 'https://evil.test/barbershops/centro/appointments?date=2026-10-05']) {
        const worker = createPushWorker();
        await clickNotification(worker, { data: { url } });
        assert.equal(worker.context.openedUrl, undefined);
    }
});

test('push permission is requested only after an explicit button tap', async () => {
    let requestCount = 0;
    const client = createPwaClient({ fetchImpl: async () => { requestCount++; return { ok: true }; } });

    assert.equal(client.requestPermissionCalls.length, 0);
    assert.equal(client.pushButton.classList.contains('hidden'), false);
    await client.pushButton.listeners.get('click')();

    assert.equal(client.requestPermissionCalls.length, 1);
    assert.equal(requestCount, 1);
    assert.match(client.pushStatus.textContent, /activadas para este dispositivo/i);
});

test('push controls explain denied permission and iOS installation requirements', () => {
    const denied = createPwaClient({ permission: 'denied' });
    assert.equal(denied.pushButton.classList.contains('hidden'), true);
    assert.match(denied.pushStatus.textContent, /bloqueadas/i);

    const iphone = createPwaClient({ userAgent: 'iPhone', standalone: false });
    assert.equal(iphone.pushButton.classList.contains('hidden'), true);
    assert.equal(iphone.iosPushInstructions.classList.contains('hidden'), false);
    assert.match(iphone.pushStatus.textContent, /pantalla de inicio/i);

    const unsupported = createPwaClient({ supportsPush: false });
    assert.equal(unsupported.pushButton.classList.contains('hidden'), true);
    assert.match(unsupported.pushStatus.textContent, /no admite notificaciones push/i);
});
