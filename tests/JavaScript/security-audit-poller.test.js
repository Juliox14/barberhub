import test from 'node:test';
import assert from 'node:assert/strict';
import { createSecurityAuditPoller } from '../../resources/js/security-audit-poller.js';

function harness(fetcher) {
    const listeners = { document: new Map(), window: new Map() };
    const timers = new Map();
    function register(target, name, callback, options = {}) {
        const handlers = listeners[target].get(name) ?? [];
        handlers.push({ callback, once: options.once === true });
        listeners[target].set(name, handlers);
    }
    function dispatch(target, name, event = {}) {
        const handlers = listeners[target].get(name) ?? [];
        for (const handler of [...handlers]) {
            handler.callback(event);
            if (handler.once) {
                listeners[target].set(name, handlers.filter((candidate) => candidate !== handler));
            }
        }
    }
    function unregister(target, name, callback) {
        const handlers = listeners[target].get(name) ?? [];
        listeners[target].set(name, handlers.filter((handler) => handler.callback !== callback));
    }
    let nextTimer = 0;
    const status = { textContent: '' };
    const results = {
        innerHTML: '<p>before</p>',
        focused: false,
        contains() { return this.focused; },
    };
    const root = {
        dataset: { endpoint: '/platform/security-audit-logs?page=2' },
        querySelector(selector) {
            return selector === '[data-audit-status]' ? status : results;
        },
    };
    const document = {
        visibilityState: 'visible',
        activeElement: null,
        addEventListener(name, callback, options) { register('document', name, callback, options); },
        removeEventListener(name, callback) { unregister('document', name, callback); },
    };
    const window = {
        fetch: fetcher,
        addEventListener(name, callback, options) { register('window', name, callback, options); },
        removeEventListener(name, callback) { unregister('window', name, callback); },
        setInterval(callback, delay) { timers.set(++nextTimer, { callback, delay, interval: true }); return nextTimer; },
        clearInterval(id) { timers.delete(id); },
        setTimeout(callback, delay) { timers.set(++nextTimer, { callback, delay }); return nextTimer; },
        clearTimeout(id) { timers.delete(id); },
    };
    const poller = createSecurityAuditPoller({ root, document, window });
    return { poller, root, results, status, document, listeners, timers, dispatch };
}

const response = (html, status = 200, redirected = false) => ({
    ok: status >= 200 && status < 300,
    status,
    redirected,
    json: async () => ({ html }),
});

test('refresh updates results in place using the current page endpoint', async () => {
    const calls = [];
    const app = harness(async (url, options) => {
        calls.push({ url, options });
        return response('<p>new fragment with page 2</p>');
    });

    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(app.results.innerHTML, '<p>new fragment with page 2</p>');
    assert.equal(calls[0].url, '/platform/security-audit-logs?page=2');
    assert.equal(calls[0].options.headers.Accept, 'application/json');
    assert.equal(calls[0].options.credentials, 'same-origin');
    assert.equal(app.status.textContent, 'Actualizado automáticamente cada 15 segundos.');
});

test('polling pauses while hidden, resumes visibly, and never overlaps requests', async () => {
    let resolveFetch;
    let count = 0;
    const app = harness(() => {
        count += 1;
        return new Promise((resolve) => { resolveFetch = resolve; });
    });
    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));
    await app.poller.refresh();
    assert.equal(count, 1);

    app.document.visibilityState = 'hidden';
    app.dispatch('document', 'visibilitychange');
    assert.equal(app.status.textContent, 'Actualización pausada mientras la pestaña está oculta.');
    resolveFetch(response('<p>stale result</p>'));
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(app.results.innerHTML, '<p>before</p>');

    app.document.visibilityState = 'visible';
    app.dispatch('document', 'visibilitychange');
    assert.equal(count, 2);
    app.poller.stop();
});

test('failed refresh retains last good content and authorization loss stops polling', async () => {
    let result = response('<p>updated once</p>');
    const app = harness(async () => result);
    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(app.results.innerHTML, '<p>updated once</p>');

    result = response('login form', 403);
    await app.poller.refresh();
    assert.equal(app.results.innerHTML, '<p>updated once</p>');
    assert.match(app.status.textContent, /permiso de administrador/);
    assert.equal([...app.timers.values()].some((timer) => timer.interval), false);
});

test('BFCache pagehide and pageshow repeatedly resume polling without duplicate timers or stale results', async () => {
    let requests = 0;
    const app = harness((_url, { signal }) => {
        requests += 1;
        if (requests === 1) {
            return new Promise((resolve) => signal.addEventListener('abort', () => resolve(response('<p>stale response</p>'))));
        }
        return Promise.resolve(response(`<p>request ${requests}</p>`));
    });
    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));

    app.dispatch('window', 'pagehide', { persisted: true });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(app.results.innerHTML, '<p>before</p>');
    app.dispatch('window', 'pageshow', { persisted: true });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(requests, 2);
    assert.equal(app.results.innerHTML, '<p>request 2</p>');
    assert.equal([...app.timers.values()].filter((timer) => timer.interval).length, 1);

    app.dispatch('window', 'pagehide', { persisted: true });
    app.dispatch('window', 'pageshow', { persisted: true });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(requests, 3);
    assert.equal([...app.timers.values()].filter((timer) => timer.interval).length, 1);
});

test('auth-loss remains terminal after BFCache restoration and generic restart attempts', async () => {
    let requests = 0;
    const app = harness(async () => {
        requests += 1;
        return response('login', 403);
    });
    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));
    app.dispatch('window', 'pagehide', { persisted: true });
    app.dispatch('window', 'pageshow', { persisted: true });
    app.document.visibilityState = 'hidden';
    app.dispatch('document', 'visibilitychange');
    app.document.visibilityState = 'visible';
    app.dispatch('document', 'visibilitychange');
    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(requests, 1);
    assert.equal([...app.timers.values()].filter((timer) => timer.interval).length, 0);
});

test('a hung request times out, retains content, and reports the failed refresh', async () => {
    const app = harness((_url, options) => new Promise((_resolve, reject) => {
        options.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
    }));
    app.poller.start();
    const timeout = [...app.timers.values()].find((timer) => timer.delay === 10_000);
    timeout.callback();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(app.results.innerHTML, '<p>before</p>');
    assert.match(app.status.textContent, /Se conserva la información visible/);
});

test('focused results are not replaced until the user leaves the table', async () => {
    const app = harness(async () => response('<p>new result</p>'));
    app.results.focused = true;
    app.document.activeElement = {};
    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(app.results.innerHTML, '<p>before</p>');
    assert.match(app.status.textContent, /cuando termines de usar la tabla/);
});

test('network failure keeps content and reports a recoverable status', async () => {
    const app = harness(async () => { throw new Error('offline'); });
    app.poller.start();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(app.results.innerHTML, '<p>before</p>');
    assert.match(app.status.textContent, /Se conserva la información visible/);
});
