import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeBarbershopLocationSearch } from '../../resources/js/barbershop-location-search.js';

function element(properties = {}) {
    return {
        children: [], listeners: {}, dataset: {}, value: '', hidden: false, disabled: false, textContent: '',
        addEventListener(name, listener) { this.listeners[name] = listener; },
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
        setAttribute(name, value) { this[name] = value; },
        ...properties,
    };
}

function createPage(fetchImplementation) {
    const fields = Object.fromEntries(['country_code', 'region', 'city', 'street_address', 'latitude', 'longitude'].map((name) => [name, element({ name, value: `existing-${name}`, dispatches: [], dispatchEvent(event) { this.dispatches.push(event.type); } })]));
    const search = element({ disabled: false });
    const query = element({ value: 'Calle del Sol 42' });
    const status = element();
    const choices = element();
    const confirm = element({ disabled: true });
    const root = element({ dataset: { endpoint: '/barbershop-location/search', csrfToken: 'csrf', barbershopSlug: 'barberia' }, querySelector(selector) {
        return ({ '[data-location-search-query]': query, '[data-location-search-button]': search, '[data-location-search-status]': status,
            '[data-location-search-results]': choices, '[data-location-search-confirm]': confirm, ...Object.fromEntries(Object.entries(fields).map(([name, field]) => [`[name="${name}"]`, field])) })[selector] ?? null;
    } });
    const document = { querySelectorAll: (selector) => selector === '[data-barbershop-location-search]' ? [root] : [], createElement: (tagName) => element({ tagName }) };
    initializeBarbershopLocationSearch(document, fetchImplementation);
    return { fields, search, query, status, choices, confirm, root };
}

const result = { label: 'Calle del Sol 42, Guadalajara', country_code: 'MX', region: 'Jalisco', city: 'Guadalajara', street_address: 'Calle del Sol 42', latitude: '20.1', longitude: '-103.2' };

test('search requires an explicit click and only explicit confirmation updates editable location inputs', async () => {
    let calls = 0;
    let request;
    const page = createPage(async (url, options) => {
        calls++;
        request = { url, options };
        return { ok: true, json: async () => ({ results: [result] }) };
    });

    assert.equal(calls, 0);
    await page.search.listeners.click({ preventDefault() {} });
    assert.equal(calls, 1);
    assert.equal(request.url, '/barbershop-location/search');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf');
    assert.deepEqual(JSON.parse(request.options.body), { q: 'Calle del Sol 42', barbershop_slug: 'barberia' });
    assert.equal(page.fields.city.value, 'existing-city');
    assert.equal(page.choices.children.length, 1);
    const confirmChoice = page.choices.children[0].children[0];
    confirmChoice.listeners.click();
    assert.equal(page.fields.city.value, 'existing-city');
    await page.confirm.listeners.click({ preventDefault() {} });
    assert.equal(page.fields.city.value, 'Guadalajara');
    assert.equal(page.fields.street_address.value, 'Calle del Sol 42');
    assert.equal(page.fields.latitude.value, '20.1');
    assert.deepEqual(page.fields.city.dispatches, ['input', 'change']);
});

test('late results cannot replace current query and failures preserve existing field values', async () => {
    const pending = [];
    const page = createPage((url, options) => new Promise((resolve) => pending.push({ resolve, options })));
    const first = page.search.listeners.click({ preventDefault() {} });
    assert.equal(page.search.disabled, true);
    await page.search.listeners.click({ preventDefault() {} });
    assert.equal(pending.length, 1);
    page.query.value = 'Avenida nueva 27';
    page.query.listeners.input();
    const second = page.search.listeners.click({ preventDefault() {} });
    pending[0].resolve({ ok: true, json: async () => ({ results: [result] }) });
    await first;
    assert.equal(page.choices.children.length, 0);
    pending[1].resolve({ ok: false, json: async () => ({ message: 'No disponible' }) });
    await second;
    assert.equal(page.fields.city.value, 'existing-city');
    assert.match(page.status.textContent, /manual/i);
});

test('empty results and untrusted labels stay textual and leave manual fields unchanged', async () => {
    const page = createPage(async () => ({ ok: true, json: async () => ({ results: [{ ...result, label: '<img src=x onerror=alert(1)>' }] }) }));
    await page.search.listeners.click({ preventDefault() {} });
    assert.equal(page.choices.children[0].children[0].textContent, '<img src=x onerror=alert(1)>');
    assert.equal(page.fields.city.value, 'existing-city');
    page.query.listeners.input();
    const emptyPage = createPage(async () => ({ ok: true, json: async () => ({ results: [] }) }));
    await emptyPage.search.listeners.click({ preventDefault() {} });
    assert.match(emptyPage.status.textContent, /No se encontraron resultados/);
    assert.equal(emptyPage.fields.city.value, 'existing-city');
});

test('typing never triggers lookup and a new lookup clears old choices only', async () => {
    let calls = 0;
    const page = createPage(async () => { calls++; return { ok: true, json: async () => ({ results: [result] }) }; });
    page.query.listeners?.input?.();
    assert.equal(calls, 0);
    await page.search.listeners.click({ preventDefault() {} });
    assert.equal(page.choices.children.length, 1);
    page.query.value = 'Otra búsqueda válida';
    page.query.listeners.input();
    await page.search.listeners.click({ preventDefault() {} });
    assert.equal(page.fields.street_address.value, 'existing-street_address');
    assert.equal(page.choices.children.length, 1);
});
