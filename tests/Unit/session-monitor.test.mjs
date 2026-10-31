import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = (await readFile(new URL('../../resources/js/session-monitor.js', import.meta.url), 'utf8'))
    .replace('export function createSessionMonitor', 'function createSessionMonitor')
    + '\nwindow.SessionMonitor = { createSessionMonitor };';

function createBrowser({ status = 'visible', fetchImpl = async () => ({ status: 200, ok: true, json: async () => ({ status: 'current' }) }), accountId = 'user-17', storage = new Map(), channels = [], locks = null, injectFetch = true } = {}) {
    const listeners = new Map();
    const timers = new Map();
    const redirects = [];
    let nextTimer = 0;
    const document = {
        visibilityState: status,
        addEventListener: (name, callback) => listeners.set(`document:${name}`, callback),
        removeEventListener: (name) => listeners.delete(`document:${name}`),
    };
    const windowObject = {
        location: { assign: (url) => redirects.push(url) },
        sessionStorage: {
            setItem: (key, value) => storage.set(key, value),
            getItem: (key) => storage.get(key) ?? null,
        },
        localStorage: {
            setItem: (key, value) => storage.set(key, value),
            getItem: (key) => storage.get(key) ?? null,
            removeItem: (key) => storage.delete(key),
        },
        addEventListener: (name, callback) => listeners.set(`window:${name}`, callback),
        removeEventListener: (name) => listeners.delete(`window:${name}`),
        setInterval: (callback, delay) => {
            const id = ++nextTimer;
            timers.set(id, { callback, delay });
            return id;
        },
        clearInterval: (id) => timers.delete(id),
        setTimeout: (callback, delay) => {
            const id = ++nextTimer;
            timers.set(id, { callback, delay, timeout: true });
            return id;
        },
        clearTimeout: (id) => timers.delete(id),
        BroadcastChannel: class {
            constructor(name) {
                this.name = name;
                this.onmessage = null;
                channels.push(this);
            }
            postMessage(data) {
                for (const channel of channels) {
                    if (channel !== this && channel.name === this.name) channel.onmessage?.({ data });
                }
            }
            close() { this.closed = true; }
        },
        navigator: locks ? { locks } : {},
        fetch: fetchImpl,
    };
    const context = { window: windowObject, document, fetch: fetchImpl, AbortController, Promise, Date, Math, JSON, setTimeout, clearTimeout };
    vm.runInNewContext(source, context);
    const monitorOptions = {
        window: windowObject,
        document,
        accountId,
        endpoint: '/session/status',
        revokedLoginUrl: '/login?session=revoked',
        expiredLoginUrl: '/login?session=expired',
        passwordRenewalUrl: '/password/renew',
        twoFactorChallengeUrl: '/two-factor/challenge',
        twoFactorSettingsUrl: '/two-factor/settings',
    };
    if (injectFetch) monitorOptions.fetch = fetchImpl;
    const monitor = windowObject.SessionMonitor.createSessionMonitor(monitorOptions);
    return { monitor, document, listeners, timers, redirects, storage, channels, window: windowObject };
}

function fireTimer(browser, delay) {
    for (const [id, timer] of browser.timers) {
        if (timer.delay === delay) {
            timer.callback();
            return;
        }
    }
    assert.fail(`No timer scheduled for ${delay}ms`);
}

test('uses the browser fetch when initialized with the app bootstrap options', async () => {
    let calls = 0;
    const browser = createBrowser({
        injectFetch: false,
        fetchImpl: async () => { calls++; return { status: 401, ok: false, json: async () => ({ status: 'expired' }) }; },
    });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 1);
    assert.deepEqual(browser.redirects, ['/login?session=expired']);
});

test('polls immediately and every 30 seconds only while visible, then checks on return', async () => {
    let calls = 0;
    let options;
    const browser = createBrowser({ fetchImpl: async (_url, requestOptions) => { calls++; options = requestOptions; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 1);
    assert.equal(options.headers.Accept, 'application/json');
    assert.equal(options.method, 'GET');
    assert.equal(options.credentials, 'same-origin');
    assert.equal(options.cache, 'no-store');
    assert.equal([...browser.timers.values()].some((timer) => timer.delay === 30_000), true);

    fireTimer(browser, 30_000);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 2);
    browser.document.visibilityState = 'hidden';
    browser.listeners.get('document:visibilitychange')();
    assert.equal([...browser.timers.values()].some((timer) => timer.delay === 30_000), false);
    browser.document.visibilityState = 'visible';
    browser.listeners.get('document:visibilitychange')();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 3);
});

test('a focused visible tab checks immediately', async () => {
    let calls = 0;
    const browser = createBrowser({ fetchImpl: async () => { calls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    browser.listeners.get('window:focus')();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 2);
});

test('one monitor does not overlap requests and timeout/network errors keep the page open', async () => {
    let finish;
    let calls = 0;
    const browser = createBrowser({ fetchImpl: () => { calls++; return new Promise((resolve) => { finish = resolve; }); } });
    browser.monitor.start();
    fireTimer(browser, 30_000);
    await Promise.resolve();
    assert.equal(calls, 1);
    finish({ status: 503, ok: false });
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(browser.redirects, []);
});

test('a visible sibling takes over polling when the current leader is hidden', async () => {
    const channels = [];
    const storage = new Map();
    let firstCalls = 0;
    let secondCalls = 0;
    const first = createBrowser({ channels, storage, accountId: 'user-17', fetchImpl: async () => { firstCalls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    const second = createBrowser({ channels, storage, accountId: 'user-17', fetchImpl: async () => { secondCalls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    first.monitor.start();
    second.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(firstCalls + secondCalls, 1);

    first.document.visibilityState = 'hidden';
    first.listeners.get('document:visibilitychange')();
    fireTimer(second, 200);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(firstCalls + secondCalls, 2);
    second.monitor.stop();
});

test('native web locks coordinate same-account tabs and hand leadership over', async () => {
    const channels = [];
    const heldLocks = new Set();
    let invalidLockOptions = false;
    const locks = {
        request(name, options, callback) {
            if (options.ifAvailable && 'signal' in options) {
                invalidLockOptions = true;
                return Promise.reject(new TypeError('ifAvailable cannot be combined with signal'));
            }
            if (heldLocks.has(name)) return Promise.resolve(callback(null));
            heldLocks.add(name);
            return Promise.resolve(callback({ name })).finally(() => heldLocks.delete(name));
        },
    };
    let firstCalls = 0;
    let secondCalls = 0;
    const first = createBrowser({ channels, locks, accountId: 'locked-user', fetchImpl: async () => { firstCalls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    const second = createBrowser({ channels, locks, accountId: 'locked-user', fetchImpl: async () => { secondCalls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    first.monitor.start();
    second.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(firstCalls + secondCalls, 1);
    assert.equal(invalidLockOptions, false);

    first.document.visibilityState = 'hidden';
    first.listeners.get('document:visibilitychange')();
    await new Promise((resolve) => setImmediate(resolve));
    fireTimer(second, 200);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(firstCalls + secondCalls, 2);
    second.monitor.stop();
});

test('rapid visibility changes do not fall back while a Web Lock release is pending', async () => {
    const heldLocks = new Set();
    const locks = {
        request(name, _options, callback) {
            if (heldLocks.has(name)) return Promise.resolve(callback(null));
            heldLocks.add(name);
            return Promise.resolve(callback({ name })).finally(() => heldLocks.delete(name));
        },
    };
    let calls = 0;
    const browser = createBrowser({ locks, fetchImpl: async () => { calls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    browser.document.visibilityState = 'hidden';
    browser.listeners.get('document:visibilitychange')();
    browser.document.visibilityState = 'visible';
    browser.listeners.get('document:visibilitychange')();
    assert.equal(browser.window.localStorage.getItem('barberhub-session-user-17-lease'), null);

    await new Promise((resolve) => setImmediate(resolve));
    fireTimer(browser, 200);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 2);
    browser.monitor.stop();
});

test('timeout covers a stalled unauthorized response body and allows a later poll', async () => {
    let calls = 0;
    let finishBody;
    const browser = createBrowser({ fetchImpl: async () => {
        calls++;
        return calls === 1
            ? { status: 401, ok: false, json: () => new Promise((resolve) => { finishBody = resolve; }) }
            : { status: 200, ok: true, json: async () => ({ status: 'current' }) };
    } });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    const timeout = [...browser.timers.entries()].find(([, timer]) => timer.delay === 8_000);
    assert.ok(timeout);
    timeout[1].callback();
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(browser.redirects, []);
    fireTimer(browser, 30_000);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 2);
    finishBody({ status: 'revoked' });
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(browser.redirects, []);
});

test('stopping during response parsing clears timers and ignores stale revocation data', async () => {
    let finishBody;
    const browser = createBrowser({ fetchImpl: async () => ({
        status: 401,
        ok: false,
        json: () => new Promise((resolve) => { finishBody = resolve; }),
    }) });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    browser.monitor.stop();
    finishBody({ status: 'revoked' });
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(browser.redirects, []);
    assert.equal(browser.timers.size, 0);
    assert.equal(browser.listeners.has('window:storage'), false);
});

test('storage ownership is revalidated before another poll and never overwrites a live lease', async () => {
    let calls = 0;
    const browser = createBrowser({ fetchImpl: async () => { calls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    const leaseKey = 'barberhub-session-user-17-lease';
    const foreignLease = { owner: 'other-visible-tab', expires: Date.now() + 40_000 };
    browser.window.localStorage.setItem(leaseKey, JSON.stringify(foreignLease));

    fireTimer(browser, 30_000);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 1);
    assert.equal(JSON.parse(browser.window.localStorage.getItem(leaseKey)).owner, foreignLease.owner);
    assert.equal([...browser.timers.values()].some((timer) => timer.delay === 30_000), false);
});

test('a deferred poll callback cannot retain leadership after a storage conflict event', async () => {
    let calls = 0;
    const browser = createBrowser({ fetchImpl: async () => { calls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    const pollTimer = [...browser.timers.values()].find((timer) => timer.delay === 30_000);
    const leaseKey = 'barberhub-session-user-17-lease';
    const foreignLease = { owner: 'other-visible-tab', expires: Date.now() + 40_000 };
    browser.window.localStorage.setItem(leaseKey, JSON.stringify(foreignLease));

    browser.listeners.get('window:storage')({ key: leaseKey, newValue: JSON.stringify(foreignLease) });
    pollTimer.callback();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 1);
    assert.equal([...browser.timers.values()].some((timer) => timer.delay === 30_000), false);
});

test('a timed-out request is aborted and retried without treating it as revocation', async () => {
    let signal;
    let calls = 0;
    const browser = createBrowser({ fetchImpl: (_url, options) => { calls++; signal = options.signal; return new Promise(() => {}); } });
    browser.monitor.start();
    const timeout = [...browser.timers.entries()].find(([, timer]) => timer.delay === 8_000);
    assert.ok(timeout);
    timeout[1].callback();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(signal.aborted, true);
    fireTimer(browser, 30_000);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 2);
    assert.deepEqual(browser.redirects, []);
});

test('expired authentication receives a generic notice instead of a transfer claim', async () => {
    const browser = createBrowser({ fetchImpl: async () => ({ status: 401, ok: false, json: async () => ({ status: 'expired' }) }) });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(browser.redirects, ['/login?session=expired']);
    assert.equal([...browser.storage.values()].includes('Tu sesión expiró. Inicia sesión nuevamente.'), true);
});

test('malformed unauthorized responses do not imply that the account was revoked', async () => {
    const browser = createBrowser({ fetchImpl: async () => ({ status: 401, ok: false, json: async () => { throw new SyntaxError('invalid JSON'); } }) });
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(browser.redirects, []);
});

test('confirmed revocation redirects to the fixed login path and notifies sibling tabs', async () => {
    let calls = 0;
    const first = createBrowser({ fetchImpl: async () => { calls++; return { status: 401, ok: false, json: async () => ({ status: 'revoked' }) }; } });
    const second = createBrowser({ channels: first.channels, storage: first.storage });
    first.monitor.start();
    second.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 1);
    assert.deepEqual(first.redirects, ['/login?session=revoked']);
    assert.deepEqual(second.redirects, ['/login?session=revoked']);
    assert.equal([...first.storage.values()].includes('Tu sesión se cerró porque iniciaste sesión en otro dispositivo.'), true);
});

test('repeated start and teardown do not multiply monitors or leave interval timers', async () => {
    let calls = 0;
    const browser = createBrowser({ fetchImpl: async () => { calls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    browser.monitor.start();
    browser.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(calls, 1);
    browser.monitor.stop();
    assert.equal(browser.timers.size, 0);
});

test('account channels are isolated and unavailable coordination APIs do not crash', async () => {
    const channels = [];
    const sharedStorage = new Map();
    const first = createBrowser({ channels, storage: sharedStorage, accountId: 'account-a' });
    const second = createBrowser({ channels, storage: sharedStorage, accountId: 'account-b' });
    first.monitor.start();
    second.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(first.channels[0].name === second.channels[1].name, false);

    const unsupported = createBrowser();
    unsupported.window.BroadcastChannel = undefined;
    unsupported.window.navigator.locks = undefined;
    unsupported.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(unsupported.redirects.length, 0);
    unsupported.monitor.stop();

    let fallbackCalls = 0;
    const blockedStorage = createBrowser({ fetchImpl: async () => { fallbackCalls++; return { status: 200, ok: true, json: async () => ({ status: 'current' }) }; } });
    Object.defineProperty(blockedStorage.window, 'localStorage', { get() { throw new Error('storage blocked'); } });
    blockedStorage.window.BroadcastChannel = undefined;
    blockedStorage.monitor.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(fallbackCalls, 1);
    blockedStorage.monitor.stop();
    assert.equal(blockedStorage.timers.size, 0);
});

for (const [status, httpStatus, target] of [
    ['password_expired', 401, '/password/renew'],
    ['two_factor_challenge', 403, '/two-factor/challenge'],
    ['two_factor_required', 403, '/two-factor/settings'],
]) {
    test(`credential restriction ${status} redirects without ending the session`, async () => {
        const first = createBrowser({ fetchImpl: async () => ({ status: httpStatus, json: async () => ({ status, redirect: 'https://untrusted.example' }) }) });
        const second = createBrowser({ channels: first.channels, storage: first.storage });
        first.monitor.start();
        second.monitor.start();
        await new Promise((resolve) => setImmediate(resolve));
        assert.deepEqual(first.redirects, [target]);
        assert.deepEqual(second.redirects, [target]);
        assert.equal(first.timers.size, 0);
        assert.equal(first.storage.get('barberhub-session-notice'), undefined);
    });
}
