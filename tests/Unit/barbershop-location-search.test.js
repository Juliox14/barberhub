import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeBarbershopLocationSearch } from '../../resources/js/barbershop-location-search.js';

const NAMES = ['country_code', 'region', 'city', 'street_address', 'latitude', 'longitude'];

function element(properties = {}) {
    return {
        children: [], listeners: {}, dataset: {}, value: '', disabled: false, textContent: '',
        addEventListener(name, listener) { this.listeners[name] = listener; },
        append(...children) { this.children.push(...children); },
        setAttribute(name, value) { this[name] = value; },
        dispatchEvent(event) { this.dispatched.push(event.type); },
        dispatched: [],
        ...properties,
    };
}

function place(components = [
    { types: ['country'], shortText: 'mx', longText: 'México' },
    { types: ['administrative_area_level_1'], longText: 'Jalisco' },
    { types: ['locality'], longText: 'Guadalajara' },
    { types: ['route'], longText: 'Avenida Vallarta' },
    { types: ['street_number'], longText: '123' },
    { types: ['subpremise'], longText: '4B' },
]) {
    return { addressComponents: components, location: { get lat() { throw new Error('Google coordinates must not be read.'); }, get lng() { throw new Error('Google coordinates must not be read.'); } }, fetchFields: async (options) => { assert.deepEqual(options, { fields: ['addressComponents'] }); } };
}

function setup({ key = 'browser-key', selected = place(), fetchFields, withMaps = true } = {}) {
    let selectedPlace = selected;
    const instances = [];
    const maps = { importLibrary: async (name) => {
        assert.equal(name, 'places');
        return { PlaceAutocompleteElement: class {
            constructor() { this.listeners = {}; instances.push(this); }
            addEventListener(name, listener) { this.listeners[name] = listener; }
            setAttribute(name, value) { this[name] = value; }
            select() { this.listeners['gmp-select']({ placePrediction: { toPlace: () => selectedPlace } }); }
            fail() { this.listeners['gmp-error']?.(); }
        } };
    } };
    if (fetchFields) selected.fetchFields = fetchFields;
    const fields = Object.fromEntries(NAMES.map((name) => [name, element({ name, value: `existing-${name}` })]));
    const widgetContainer = element();
    const status = element();
    const root = element({ dataset: { apiKey: key }, querySelector(selector) {
        return ({ '[data-location-search-widget]': widgetContainer, '[data-location-search-status]': status,
            ...Object.fromEntries(Object.entries(fields).map(([name, field]) => [`[name="${name}"]`, field])) })[selector] ?? null;
    } });
    const timers = new Map();
    let timerId = 0;
    const window = {
        ...(withMaps ? { google: { maps } } : {}),
        setTimeout(callback, duration) { const id = ++timerId; timers.set(id, { callback, duration }); return id; },
        clearTimeout(id) { timers.delete(id); },
    };
    const scripts = [];
    const document = {
        defaultView: window,
        querySelectorAll: () => [root],
        createElement: () => element({ src: '', async: false, onerror: null }),
        head: { append(script) { scripts.push(script); } },
    };
    return {
        fields, widgetContainer, status, instances, root, document, window, maps, scripts, timers,
        setSelected(value) { selectedPlace = value; },
        advanceTimers() { for (const timer of [...timers.values()]) { timer.callback(); } },
    };
}

const tick = () => new Promise((resolve) => setImmediate(resolve));

test('missing browser key leaves all manual fields untouched and skips SDK loading', async () => {
    const page = setup({ key: '' });
    await initializeBarbershopLocationSearch(page.document);
    assert.equal(page.scripts.length, 0);
    assert.equal(page.widgetContainer.children.length, 0);
    assert.equal(page.fields.city.value, 'existing-city');
    assert.match(page.status.textContent, /administrador/i);
});

test('script loader uses an encoded runtime key and callback, then mounts the Places widget', async () => {
    const page = setup({ withMaps: false, key: 'browser key&restricted' });
    const loading = initializeBarbershopLocationSearch(page.document);
    assert.equal(page.scripts.length, 1);
    const script = page.scripts[0];
    const url = new URL(script.src);
    assert.equal(url.origin, 'https://maps.googleapis.com');
    assert.equal(url.pathname, '/maps/api/js');
    assert.equal(url.searchParams.get('key'), 'browser key&restricted');
    assert.equal(url.searchParams.get('language'), 'es');
    assert.equal(url.searchParams.get('loading'), 'async');
    assert.equal(script.async, true);
    const callbackName = url.searchParams.get('callback');
    assert.equal(typeof page.window[callbackName], 'function');
    page.window.google = { maps: page.maps };
    page.window[callbackName]();
    await loading;
    assert.equal(page.widgetContainer.children.length, 1);
    assert.equal(callbackName in page.window, false);
});

test('simultaneous eligible roots share one SDK script and each receive a widget', async () => {
    const page = setup({ withMaps: false });
    const second = setup({ withMaps: false });
    page.document.querySelectorAll = () => [page.root, second.root];
    const loading = initializeBarbershopLocationSearch(page.document);
    assert.equal(page.scripts.length, 1);
    const callbackName = new URL(page.scripts[0].src).searchParams.get('callback');
    page.window.google = { maps: page.maps };
    page.window[callbackName]();
    await loading;
    assert.equal(page.widgetContainer.children.length, 1);
    assert.equal(second.widgetContainer.children.length, 1);
});

test('no eligible roots means no script or timer', async () => {
    const page = setup({ withMaps: false });
    page.document.querySelectorAll = () => [];
    await initializeBarbershopLocationSearch(page.document);
    assert.equal(page.scripts.length, 0);
    assert.equal(page.timers.size, 0);
});

test('script network failure and SDK timeout clean up callback and preserve all manual fields', async () => {
    const networkFailure = setup({ withMaps: false });
    const networkLoading = initializeBarbershopLocationSearch(networkFailure.document);
    const failedScript = networkFailure.scripts[0];
    const failedCallback = new URL(failedScript.src).searchParams.get('callback');
    failedScript.onerror();
    await networkLoading;
    assert.equal(failedScript.onerror, null);
    assert.equal(failedCallback in networkFailure.window, false);
    assert.match(networkFailure.status.textContent, /configuración/i);
    assert.deepEqual(NAMES.map((name) => networkFailure.fields[name].value), NAMES.map((name) => `existing-${name}`));

    const timeout = setup({ withMaps: false });
    const timeoutLoading = initializeBarbershopLocationSearch(timeout.document);
    const timedOutScript = timeout.scripts[0];
    const timeoutCallback = new URL(timedOutScript.src).searchParams.get('callback');
    timeout.advanceTimers();
    await timeoutLoading;
    assert.equal(timedOutScript.onerror, null);
    assert.equal(timeoutCallback in timeout.window, false);
    assert.match(timeout.status.textContent, /configuración/i);
    assert.deepEqual(NAMES.map((name) => timeout.fields[name].value), NAMES.map((name) => `existing-${name}`));
});

test('Google selection requests only addressComponents, fills editable address fields and clears stale coordinates', async () => {
    const page = setup();
    await initializeBarbershopLocationSearch(page.document);
    assert.equal(page.widgetContainer.children.length, 1);
    page.instances[0].select();
    await tick();
    assert.equal(page.fields.country_code.value, 'MX');
    assert.equal(page.fields.region.value, 'Jalisco');
    assert.equal(page.fields.city.value, 'Guadalajara');
    assert.equal(page.fields.street_address.value, 'Avenida Vallarta 123 4B');
    assert.equal(page.fields.latitude.value, '');
    assert.equal(page.fields.longitude.value, '');
    assert.deepEqual(page.fields.latitude.dispatched, ['input', 'change']);
    assert.deepEqual(page.fields.city.dispatched, ['input', 'change']);
    page.fields.city.value = 'Manual correction';
    assert.equal(page.fields.city.value, 'Manual correction');
});

test('selection does not read Google coordinates, and incomplete/invalid address details preserve every field', async () => {
    let fetchMask;
    const incomplete = { addressComponents: [], location: { lat: () => 1, lng: () => 2 }, fetchFields: async (options) => { fetchMask = options.fields; } };
    const page = setup({ selected: incomplete });
    await initializeBarbershopLocationSearch(page.document);
    page.instances[0].select();
    await tick();
    assert.deepEqual(fetchMask, ['addressComponents']);
    assert.deepEqual(NAMES.map((name) => page.fields[name].value), NAMES.map((name) => `existing-${name}`));
    assert.match(page.status.textContent, /manualmente/i);
});

test('Google errors and details rejection preserve manually entered address and coordinates', async () => {
    const page = setup({ fetchFields: async () => { throw new Error('denied'); } });
    await initializeBarbershopLocationSearch(page.document);
    page.instances[0].select();
    await tick();
    assert.deepEqual(NAMES.map((name) => page.fields[name].value), NAMES.map((name) => `existing-${name}`));
    assert.match(page.status.textContent, /manualmente/i);
    page.instances[0].fail();
    assert.match(page.status.textContent, /configuración/i);
});

test('a newer selected suggestion prevents older pending details from overwriting it', async () => {
    const resolvers = [];
    const first = { ...place(), fetchFields: async (options) => { assert.deepEqual(options, { fields: ['addressComponents'] }); await new Promise((resolve) => resolvers.push(resolve)); } };
    const second = { ...place([{ types: ['country'], shortText: 'CO' }, { types: ['route'], longText: 'Carrera 7' }]), fetchFields: async (options) => { assert.deepEqual(options, { fields: ['addressComponents'] }); await new Promise((resolve) => resolvers.push(resolve)); } };
    const page = setup({ selected: first });
    await initializeBarbershopLocationSearch(page.document);
    page.instances[0].select();
    page.setSelected(second);
    page.instances[0].select();
    resolvers[0]();
    resolvers[1]();
    await tick();
    assert.equal(page.fields.country_code.value, 'CO');
    assert.equal(page.fields.street_address.value, 'Carrera 7');
});

test('manual edits made while place details are pending prevent a late overwrite', async () => {
    let resolveDetails;
    const selected = { ...place(), fetchFields: async (options) => { assert.deepEqual(options, { fields: ['addressComponents'] }); await new Promise((resolve) => { resolveDetails = resolve; }); } };
    const page = setup({ selected });
    await initializeBarbershopLocationSearch(page.document);
    page.instances[0].select();
    page.fields.city.value = 'Entered by owner';
    page.fields.city.listeners.input?.();
    resolveDetails();
    await tick();
    assert.equal(page.fields.city.value, 'Entered by owner');
    assert.equal(page.fields.latitude.value, 'existing-latitude');
    assert.equal(page.fields.longitude.value, 'existing-longitude');
});
