import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { initializePublicBooking } from '../../resources/js/public-booking.js';

function node(properties = {}) {
    return {
        children: [], listeners: {}, dataset: {}, value: '', hidden: false, disabled: false, textContent: '',
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
        setAttribute(name, value) { this[name] = value; },
        addEventListener(name, listener) { this.listeners[name] = listener; },
        querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; },
        querySelectorAll(selector) {
            const matches = (child) => selector === child.tagName
                || (selector === 'button[data-calendar-move]' && child.tagName === 'button' && child.dataset.calendarMove !== undefined)
                || (selector === '[name="service_id"]:checked' && child.name === 'service_id' && child.checked);
            return this.children.flatMap((child) => [ ...(matches(child) ? [child] : []), ...(child.querySelectorAll?.(selector) ?? []) ]);
        },
        ...properties,
    };
}

function calendarFixture({ date = '2027-01-30', max = '2027-04-30', responses, fetchOverride, slots = [] } = {}) {
    const service = node({ name: 'service_id', checked: true, value: '4', dataset: { serviceName: 'Corte', servicePrice: '200', serviceDuration: '30' } });
    const dateField = node({ name: 'date', value: date, min: date, max, type: 'date', required: true });
    const dates = node({ hidden: true });
    const picker = node({ open: true });
    const calendar = node();
    const calendarStatus = node();
    const barber = node({ name: 'barber_id', value: '12' });
    const slotList = node();
    const slotStatus = node();
    const serviceField = node();
    const booking = node({ dataset: { emailVerified: 'true', availabilityReady: 'false' }, querySelector: (selector) => selector === '[name="service_id"]' ? serviceField : null });
    const filters = node({ dataset: { availabilityUrl: '/availability' }, reportValidity: () => true });
    filters.querySelector = (selector) => ({
        '[name="barber_id"]': barber, '[name="date"]': dateField, '[name="service_id"]:checked': service,
        '[data-date-options]': dates, '[data-date-picker]': picker, '[data-calendar-grid]': calendar,
        '[data-calendar-status]': calendarStatus, '[data-slot-fieldset]': node(),
    })[selector] ?? null;
    const selected = node();
    const submit = node();
    booking.querySelector = (selector) => selector === '[data-slot-submit]' ? submit : selector === '[name="service_id"]' ? serviceField : null;
    const nodes = { '[data-availability]': filters, '[data-public-booking]': booking, '[data-slot-list]': slotList, '[data-slot-status]': slotStatus, '[data-selected-slot]': selected };
    const preview = Object.fromEntries(['service', 'duration', 'price', 'date', 'time', 'barber'].map((key) => [key, node()]));
    const document = {
        querySelector: (selector) => nodes[selector] ?? null,
        querySelectorAll: (selector) => selector === '[data-availability]' ? [filters] : selector.startsWith('[data-booking-') ? [preview[selector.slice(14, -1)]] : [],
        createElement: (tagName) => node({ tagName }),
    };
    const requests = [];
    const fetch = async (url, options) => {
        requests.push(new URL(url, 'http://localhost'));
        if (fetchOverride) return fetchOverride(url, options);
        if (new URL(url, 'http://localhost').pathname === '/broken') return { ok: false, json: async () => ({}) };
        const query = new URL(url, 'http://localhost').searchParams;
        if (!query.has('end_date')) return { ok: true, json: async () => ({ slots }) };
        const days = responses?.(query) ?? Array.from({ length: 31 }, (_, index) => ({ date: `2027-01-${String(index + 1).padStart(2, '0')}`, available: true }));
        return { ok: true, json: async () => ({ days }) };
    };
    initializePublicBooking(document, fetch);
    return { dateField, dates, picker, calendar, calendarStatus, requests, filters, slotList, booking, submit, service, barber };
}

test('enhanced calendar removes the native date picker from focus and keeps the GET date value', () => {
    const page = calendarFixture();
    assert.notEqual(page.dateField.type, 'date');
    assert.equal(page.dateField.tabIndex, -1);
    assert.equal(page.dateField.value, '2027-01-30');
    assert.ok(page.requests.some((request) => request.searchParams.get('service_id') === '4' && request.searchParams.get('barber_id') === '12'));
});

test('an available date remains selectable and renders the existing slot response', async () => {
    const page = calendarFixture({ slots: [{ starts_at: '2027-01-31T10:00', barber_id: 12, barber_name: 'Ana' }] });
    await new Promise((resolve) => setImmediate(resolve));
    const free = page.dates.children.find((button) => button.dataset.date === '2027-01-31');
    assert.equal(free.disabled, false);
    await free.listeners.click();
    assert.equal(page.dateField.value, '2027-01-31');
    assert.equal(page.slotList.children.length, 1);
});

test('CSS visibly grays both disabled date controls', async () => {
    const css = await readFile(new URL('../../resources/css/public-booking.css', import.meta.url), 'utf8');
    assert.match(css, /\.bh-public-date:disabled\s*\{[^}]*background:\s*#e5e7eb/s);
    assert.match(css, /\.bh-public-calendar__day:disabled\s*\{[^}]*background:\s*#e5e7eb/s);
});

test('unavailable dates are disabled and a refreshed empty slot response clears the chosen date', async () => {
    const page = calendarFixture({ date: '2027-01-29', responses: () => Array.from({ length: 31 }, (_, index) => ({
        date: `2027-01-${String(index + 1).padStart(2, '0')}`,
        available: `2027-01-${String(index + 1).padStart(2, '0')}` !== '2027-01-30',
    })) });
    await new Promise((resolve) => setImmediate(resolve));
    const closed = page.dates.children.find((button) => button.dataset.date === '2027-01-30');
    assert.equal(closed.disabled, true);
    await page.filters.listeners.submit({ preventDefault() {} });
    assert.equal(page.dateField.value, '');
    assert.equal(page.slotList.children.length, 0);
    assert.equal(page.booking.dataset.availabilityReady, 'false');
    assert.equal(page.submit.disabled, true);
});

test('zero-availability month reports an empty state and disables every in-range day', async () => {
    const page = calendarFixture({ responses: (query) => {
        const start = new Date(`${query.get('date')}T12:00:00Z`);
        const end = new Date(`${query.get('end_date')}T12:00:00Z`);
        return Array.from({ length: Math.round((end - start) / 86400000) + 1 }, (_, index) => {
            const day = new Date(start);
            day.setUTCDate(day.getUTCDate() + index);
            return { date: day.toISOString().slice(0, 10), available: false };
        });
    } });
    await new Promise((resolve) => setImmediate(resolve));
    assert.match(page.calendarStatus.textContent, /No hay días con horarios/);
    assert.equal(page.dateField.value, '');
    assert.ok(page.calendar.children[3].children.filter((button) => button.dataset.date).every((button) => button.disabled));
});

test('calendar request failures are distinct and open the calendar status', async () => {
    const page = calendarFixture();
    page.requests.length = 0;
    const originalQuery = page.filters.dataset.availabilityUrl;
    page.filters.dataset.availabilityUrl = '/broken';
    await new Promise((resolve) => setImmediate(resolve));
    assert.match(page.calendarStatus.textContent, /No pudimos consultar/);
    assert.equal(page.picker.open, true);
    page.filters.dataset.availabilityUrl = originalQuery;
});

test('month navigation requests bounded ranges and disables navigation outside min and max', async () => {
    const page = calendarFixture({ date: '2027-01-30', max: '2027-02-10' });
    const [previous, , next] = page.calendar.children;
    assert.equal(previous.disabled, true);
    assert.equal(next.disabled, false);
    await next.listeners.click();
    await new Promise((resolve) => setImmediate(resolve));
    assert.ok(page.requests.some((request) => request.searchParams.get('date') === '2027-02-01' && request.searchParams.get('end_date') === '2027-02-10'));
    assert.equal(page.calendar.children[2].disabled, true);
});

test('service changes query days for the new pair instead of carrying prior eligibility', async () => {
    const page = calendarFixture();
    await new Promise((resolve) => setImmediate(resolve));
    page.service.value = '9';
    await page.filters.listeners.change({ target: { name: 'service_id' } });
    assert.ok(page.requests.some((request) => request.searchParams.get('service_id') === '9' && request.searchParams.has('end_date')));
});

test('late calendar responses cannot overwrite a newer month response', async () => {
    const pending = [];
    const page = calendarFixture({ fetchOverride: (url, options) => new Promise((resolve) => pending.push({ url, options, resolve })) });
    const next = page.calendar.children[2];
    next.listeners.click();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(pending.length, 2);
    const rangeDays = (url, available) => {
        const query = new URL(url, 'http://localhost').searchParams;
        const start = new Date(`${query.get('date')}T12:00:00Z`);
        const end = new Date(`${query.get('end_date')}T12:00:00Z`);
        return Array.from({ length: Math.round((end - start) / 86400000) + 1 }, (_, index) => {
            const day = new Date(start);
            day.setUTCDate(day.getUTCDate() + index);
            return { date: day.toISOString().slice(0, 10), available };
        });
    };
    pending[1].resolve({ ok: true, json: async () => ({ days: rangeDays(pending[1].url, true) }) });
    await new Promise((resolve) => setImmediate(resolve));
    pending[0].resolve({ ok: true, json: async () => ({ days: rangeDays(pending[0].url, false) }) });
    await new Promise((resolve) => setImmediate(resolve));
    const februaryTenth = page.calendar.children[3].children.find((button) => button.dataset.date === '2027-02-10');
    assert.equal(februaryTenth.disabled, false);
});

test('five-date strip loads next-month eligibility instead of disabling unknown dates', async () => {
    const page = calendarFixture({ date: '2027-01-30', max: '2027-04-30', responses: (query) => {
        const start = new Date(`${query.get('date')}T12:00:00Z`);
        const end = new Date(`${query.get('end_date')}T12:00:00Z`);
        return Array.from({ length: Math.round((end - start) / 86400000) + 1 }, (_, index) => {
            const current = new Date(start);
            current.setUTCDate(current.getUTCDate() + index);
            return { date: current.toISOString().slice(0, 10), available: true };
        });
    } });
    await new Promise((resolve) => setImmediate(resolve));
    const februarySecond = page.dates.children.find((button) => button.dataset.date === '2027-02-02');
    assert.equal(februarySecond.disabled, false);
    assert.ok(page.requests.some((request) => request.searchParams.get('date') === '2027-02-01' && request.searchParams.get('end_date') === '2027-02-03'));
});
