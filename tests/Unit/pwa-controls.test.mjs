import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../../resources/js/pwa.js', import.meta.url), 'utf8');

function run({ userAgent = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36', touchPoints = 0, standalone = false, account = '17', subscribed = false, storage = new Map(), fetchImpl = async () => ({ ok: true }) } = {}) {
    const selectors = ['[data-pwa-controls]', '[data-pwa-install-alert]', '[data-push-alert]', '[data-pwa-dismiss="install"]', '[data-pwa-dismiss="push"]', '[data-pwa-install]', '[data-push-enable]', '[data-push-status]', '[data-ios-install]', '[data-ios-push-install]', '[data-pwa-unavailable]', '[data-pwa-offline]', '[data-push-controls]'];
    const elements = Object.fromEntries(selectors.map((selector) => [selector, element(selector === '[data-pwa-controls]' ? { accountId: account, pushSubscribed: String(subscribed) } : selector === '[data-push-controls]' ? { vapidPublicKey: 'AQID', subscribeUrl: '/push' } : {})]));
    const events = new Map();
    const stored = storage;
    const navigator = { userAgent, maxTouchPoints: touchPoints, standalone, onLine: true, serviceWorker: { register: async () => ({ pushManager: { subscribe: async () => ({ toJSON: () => ({ endpoint: 'https://push.test', keys: {} }) }) } }) } };
    const notificationApi = { permission: 'default', requestPermission: async () => 'granted' };
    const windowObject = { navigator, Notification: notificationApi, PushManager: class {}, MSStream: undefined, isSecureContext: true, matchMedia: () => ({ matches: standalone }), sessionStorage: { getItem: (key) => stored.get(key) ?? null, setItem: (key, value) => stored.set(key, value) }, addEventListener: (name, callback) => events.set(name, callback), atob: (value) => Buffer.from(value, 'base64').toString('binary') };
    const document = { querySelector: (selector) => elements[selector] ?? null };
    vm.runInNewContext(source, { window: windowObject, navigator, Notification: notificationApi, document, fetch: fetchImpl, Uint8Array, Promise, atob: windowObject.atob });
    return { elements, events, stored };
}

function element(dataset = {}) {
    const classes = new Set(['hidden']);
    const listeners = new Map();
    return { dataset, listeners, disabled: false, textContent: '', classList: { add: (name) => classes.add(name), remove: (name) => classes.delete(name), contains: (name) => classes.has(name), toggle: (name, force) => force ? classes.add(name) : classes.delete(name) }, addEventListener: (name, callback) => listeners.set(name, callback) };
}

test('alerts remain hidden on desktop including narrow and touch laptops', () => {
    for (const config of [{ userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', touchPoints: 0 }, { userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', touchPoints: 10 }]) {
        const client = run(config);
        assert.equal(client.events.has('beforeinstallprompt'), false);
        assert.equal(client.elements['[data-push-enable]'].classList.contains('hidden'), true);
    }
});

test('mobile alerts have independent tab-session dismissal controls', () => {
    const client = run();
    assert.equal(client.events.has('beforeinstallprompt'), true);
    assert.equal(typeof client.elements['[data-pwa-dismiss="install"]'].listeners.get('click'), 'function');
    assert.equal(typeof client.elements['[data-pwa-dismiss="push"]'].listeners.get('click'), 'function');
});

test('dismissing one alert leaves the other alert visible', () => {
    const client = run();
    client.elements['[data-pwa-dismiss="install"]'].listeners.get('click')();
    assert.equal(client.elements['[data-pwa-install-alert]'].classList.contains('hidden'), true);
    assert.equal(client.elements['[data-push-alert]'].classList.contains('hidden'), false);
});

test('iPhone, iPadOS desktop user agents, and standalone mode are handled as mobile contexts', async () => {
    const iphone = run({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile' });
    const ipad = run({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15', touchPoints: 5 });
    const standaloneIphone = run({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile', standalone: true });
    const standaloneAndroid = run({ standalone: true });
    assert.equal(iphone.elements['[data-ios-install]'].classList.contains('hidden'), false);
    assert.equal(ipad.elements['[data-ios-install]'].classList.contains('hidden'), false);
    assert.equal(ipad.elements['[data-push-alert]'].classList.contains('hidden'), false);

    for (const standalone of [standaloneIphone, standaloneAndroid]) {
        assert.equal(standalone.elements['[data-pwa-install-alert]'].classList.contains('hidden'), true);
        assert.equal(standalone.elements['[data-push-alert]'].classList.contains('hidden'), false);
        assert.equal(standalone.elements['[data-push-enable]'].classList.contains('hidden'), false);
        await standalone.elements['[data-push-enable]'].listeners.get('click')();
        assert.equal(standalone.elements['[data-push-alert]'].classList.contains('hidden'), true);
    }
});

test('appinstalled hides the installation alert', () => {
    const client = run();
    client.events.get('appinstalled')();
    assert.equal(client.elements['[data-pwa-install-alert]'].classList.contains('hidden'), true);
});

test('manual alert dismissal persists across page loads in this tab', () => {
    const storage = new Map();
    const firstPage = run({ storage });
    firstPage.elements['[data-pwa-dismiss="install"]'].listeners.get('click')();
    firstPage.elements['[data-pwa-dismiss="push"]'].listeners.get('click')();
    const nextPage = run({ storage });
    assert.equal(nextPage.elements['[data-pwa-install-alert]'].classList.contains('hidden'), true);
    assert.equal(nextPage.elements['[data-push-alert]'].classList.contains('hidden'), true);
});

test('server-confirmed subscriptions suppress the push prompt but permission alone does not', () => {
    const confirmed = run({ subscribed: true });
    const unconfirmed = run();
    assert.equal(confirmed.elements['[data-push-alert]'].classList.contains('hidden'), true);
    assert.equal(unconfirmed.elements['[data-push-enable]'].classList.contains('hidden'), false);
});

test('successful push registration hides and persists while failed registration remains retryable', async () => {
    const success = run();
    await success.elements['[data-push-enable]'].listeners.get('click')();
    assert.equal(success.elements['[data-push-alert]'].classList.contains('hidden'), true);
    const laterPage = run({ subscribed: true });
    assert.equal(laterPage.elements['[data-push-alert]'].classList.contains('hidden'), true);

    const failure = run({ fetchImpl: async () => ({ ok: false, status: 500 }) });
    await failure.elements['[data-push-enable]'].listeners.get('click')();
    assert.equal(failure.elements['[data-push-alert]'].classList.contains('hidden'), false);
});
