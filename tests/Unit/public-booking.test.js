import test from 'node:test';
import assert from 'node:assert/strict';
import { requestVerification, createAvailabilityLoader, initializePublicBooking } from '../../resources/js/public-booking.js';

test('verification sends JSON with CSRF and same-origin credentials', async () => {
    let received;
    const result = await requestVerification('/verify', { email: 'guest@example.com', code: '123456' }, 'csrf', async (url, options) => {
        received = { url, options };
        return { ok: true, json: async () => ({ verified: true }) };
    });
    assert.deepEqual(result, { verified: true });
    assert.equal(received.url, '/verify');
    assert.equal(received.options.credentials, 'same-origin');
    assert.equal(received.options.headers['X-CSRF-TOKEN'], 'csrf');
    assert.deepEqual(JSON.parse(received.options.body), { email: 'guest@example.com', code: '123456' });
});

test('rejected code gives an actionable message without treating it as verified', async () => {
    await assert.rejects(requestVerification('/verify', {}, 'csrf', async () => ({ ok: false, json: async () => ({ verified: false }) })), /Código incorrecto o vencido/);
});

test('network failure is surfaced without claiming email success', async () => {
    await assert.rejects(requestVerification('/issue', {}, 'csrf', async () => { throw new Error('offline'); }), /offline/);
});

const slot = { starts_at: '2026-10-05T10:00', barber_id: 7, barber_name: 'María' };

test('availability fetches the chosen filters without navigation and reports loading then ready', async () => {
    const states = [];
    let request;
    const load = createAvailabilityLoader({
        onState: (state) => states.push(state),
        fetchImplementation: async (url, options) => {
            request = { url, options };
            return { ok: true, json: async () => ({ slots: [slot] }) };
        },
    });
    await load('/availability', { service_id: '2', barber_id: '', date: '2026-10-05' });
    const query = new URL(request.url, 'http://localhost').searchParams;
    assert.equal(query.get('service_id'), '2');
    assert.equal(query.get('date'), '2026-10-05');
    assert.equal(query.has('barber_id'), false);
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.headers.Accept, 'application/json');
    assert.deepEqual(states.map(({ status }) => status), ['loading', 'ready']);
    assert.deepEqual(states[0].slots, []);
    assert.deepEqual(states[1].slots, [slot]);
});

test('obsolete responses cannot replace the current service slots even if abort is ignored', async () => {
    const states = [];
    const pending = [];
    const load = createAvailabilityLoader({
        onState: (state) => states.push(state),
        fetchImplementation: (url, options) => new Promise((resolve) => pending.push({ resolve, options })),
    });
    const first = load('/availability', { service_id: '1', date: '2026-10-05' });
    const second = load('/availability', { service_id: '2', date: '2026-10-06' });
    assert.equal(pending[0].options.signal.aborted, true);
    const current = { ...slot, starts_at: '2026-10-06T11:00' };
    pending[1].resolve({ ok: true, json: async () => ({ slots: [current] }) });
    await second;
    pending[0].resolve({ ok: true, json: async () => ({ slots: [slot] }) });
    await first;
    assert.deepEqual(states.at(-1).slots, [current]);
    assert.equal(states.filter(({ status }) => status === 'ready').length, 1);
});

test('empty and failed availability clear old selection and expose recoverable state', async () => {
    for (const response of [{ ok: true, json: async () => ({ slots: [] }) }, { ok: false, json: async () => ({}) }]) {
        const states = [];
        const load = createAvailabilityLoader({ onState: (state) => states.push(state), fetchImplementation: async () => response });
        await load('/availability', { service_id: '1', date: '2026-10-05' });
        assert.equal(states.at(-1).status, response.ok ? 'empty' : 'error');
        assert.deepEqual(states.at(-1).slots, []);
    }
});

function element(properties = {}) {
    return {
        children: [], listeners: {}, dataset: {}, value: '', hidden: false, disabled: false, textContent: '',
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
        setAttribute(name, value) { this[name] = value; },
        addEventListener(name, listener) { this.listeners[name] = listener; },
        querySelector(selector) {
            for (const child of this.children) {
                if (child.tagName === selector) return child;
                const found = child.querySelector?.(selector);
                if (found) return found;
            }
            return null;
        },
        ...properties,
    };
}

test('changing filters updates the actual form, clears stale slots and keeps contact fields intact', async () => {
    const selectedService = element({ value: '2', dataset: { serviceName: 'Corte clásico', servicePrice: '250.00', serviceDuration: '45' } });
    const date = element({ value: '2026-10-05', min: '2026-10-05', max: '2026-10-10' });
    const dates = element({ hidden: true });
    const picker = element({ open: true });
    const barber = element({ value: '' });
    const contact = element({ value: 'Sin perder mis datos' });
    const list = element({ children: [element({ checked: true, value: 'stale' })] });
    const status = element();
    const selected = element();
    const serviceField = element({ value: '1' });
    const submit = element();
    const fieldset = element();
    const booking = element({ dataset: { emailVerified: 'true', availabilityReady: 'true' }, children: [contact], querySelector: (selector) => ({ '[name="service_id"]': serviceField, '[name="name"]': contact, '[data-slot-submit]': submit })[selector] ?? null });
    const root = element({
        dataset: { availabilityUrl: '/availability' },
        reportValidity: () => true,
        querySelector: (selector) => ({ '[name="service_id"]:checked': selectedService, '[name="date"]': date, '[name="barber_id"]': barber, '[data-date-options]': dates, '[data-date-picker]': picker, '[data-slot-fieldset]': fieldset })[selector] ?? null,
    });
    const preview = Object.fromEntries(['service', 'duration', 'price', 'date', 'time', 'barber'].map((key) => [key, element()]));
    const mobilePrice = element();
    const nodes = { '[data-availability]': root, '[data-public-booking]': booking, '[data-slot-list]': list, '[data-slot-status]': status, '[data-selected-slot]': selected };
    const document = {
        querySelector: (selector) => nodes[selector] ?? null,
        querySelectorAll: (selector) => {
            const key = selector.match(/^\[data-booking-(.+)\]$/)?.[1];
            return preview[key] ? (key === 'price' ? [preview.price, mobilePrice] : [preview[key]]) : nodes[selector] ? [nodes[selector]] : [];
        },
        createElement: (tagName) => element({ tagName }),
    };
    initializePublicBooking(document, async () => ({ ok: true, json: async () => ({ slots: [slot] }) }));
    assert.equal(dates.hidden, false);
    assert.equal(picker.open, false);
    assert.equal(dates.children.length, 5);
    assert.equal(dates.children[0]['aria-pressed'], 'true');
    assert.equal(dates.children[0].children[0].textContent, 'lun');
    assert.equal(dates.children[0].children[1].textContent, '05');
    assert.equal(preview.service.textContent, 'Corte clásico');
    assert.equal(preview.price.textContent, '250.00');
    assert.equal(mobilePrice.textContent, '250.00');
    assert.equal(preview.duration.textContent, '45 min');
    assert.equal(preview.date.textContent, '05/10/2026');
    assert.equal(preview.time.textContent, 'Elige un horario');
    let prevented = false;
    await root.listeners.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(serviceField.value, '2');
    assert.equal(contact.value, 'Sin perder mis datos');
    assert.equal(list.children.length, 1);
    assert.equal(list.children[0].children[0].value, '7|2026-10-05T10:00');
    assert.equal(list.children[0].children[0].checked, undefined);
    assert.equal(list.children[0].children[0].form, 'public-booking-form');
    assert.equal(booking.hidden, false);
    assert.equal(submit.disabled, false);
    assert.equal(list['aria-busy'], 'false');
    const choice = list.children[0];
    const input = choice.children[0];
    input.closest = () => choice;
    list.listeners.change({ target: input });
    assert.equal(selected.textContent, '10:00 con María');
    assert.equal(preview.time.textContent, '10:00');
    assert.equal(preview.barber.textContent, 'María');
    await root.listeners.change({ target: input });
    assert.equal(preview.time.textContent, '10:00');
    assert.equal(preview.barber.textContent, 'María');
    booking.dataset.emailVerified = 'false';
    await dates.children[4].listeners.click();
    assert.equal(submit.disabled, true);
    assert.equal(date.value, '2026-10-09');
    assert.equal(preview.date.textContent, '09/10/2026');
    assert.equal(preview.time.textContent, 'Elige un horario');
    assert.equal(contact.value, 'Sin perder mis datos');
    assert.equal(dates.children[4]['aria-pressed'], 'true');
    booking.dataset.emailVerified = 'true';
    date.value = date.max;
    await root.listeners.change({ target: { name: 'date' } });
    assert.equal(submit.disabled, false);
    assert.equal(dates.children.length, 1);
    assert.equal(dates.children[0].dataset.date, date.max);
    selectedService.value = '3';
    selectedService.dataset = { serviceName: 'Barba', servicePrice: '180.00', serviceDuration: '30' };
    await root.listeners.change({ target: { name: 'service_id' } });
    assert.equal(serviceField.value, '3');
    assert.equal(preview.service.textContent, 'Barba');
    assert.equal(preview.price.textContent, '180.00');
    assert.equal(mobilePrice.textContent, '180.00');
    assert.equal(preview.time.textContent, 'Elige un horario');
    assert.equal(preview.barber.textContent, 'Profesional pendiente');
    assert.equal(booking.querySelector('[name="name"]').value, 'Sin perder mis datos');
    root.reportValidity = () => false;
    await root.listeners.change({ target: { name: 'date' } });
    assert.equal(list.children.length, 0);
    assert.equal(serviceField.value, '');
    assert.equal(submit.disabled, true);
    assert.equal(fieldset.disabled, true);
    assert.equal(booking.hidden, false);
    assert.equal(contact.value, 'Sin perder mis datos');
});

function verificationPage(fetchImplementation, availabilityReady = 'true', timers = {}) {
    const issue = element();
    const verify = element({ disabled: true });
    const codeControls = element({ hidden: true });
    const status = element();
    const resendStatus = element();
    const email = element({ value: 'guest@example.com', reportValidity: () => true });
    const code = element({ value: '123456', disabled: true });
    const controls = element();
    const submit = element();
    const csrf = element({ value: 'csrf' });
    const nodes = { '[data-issue-code]': issue, '[data-verify-code]': verify, '[data-code-status]': status, '[data-resend-status]': resendStatus,
        '[name="email"]': email, '[data-email-code]': code, '[data-verification-controls]': controls, '[data-code-controls]': codeControls, '[data-slot-submit]': submit, '[name="_token"]': csrf };
    const form = element({
        dataset: { issueUrl: '/issue', verifyUrl: '/verify', emailVerified: 'false', availabilityReady },
        querySelector: (selector) => nodes[selector] ?? null,
    });
    const document = { querySelectorAll: (selector) => selector === '[data-public-booking]' ? [form] : [] };
    initializePublicBooking(document, fetchImplementation, timers);
    return { issue, verify, status, resendStatus, email, code, controls, codeControls, submit };
}

test('only confirmed email hides verification controls and changing address requires a new code', async () => {
    const requests = [];
    const page = verificationPage(async (url, options) => {
        requests.push({ url, payload: JSON.parse(options.body) });
        return { ok: true, json: async () => url === '/verify' ? { verified: true } : { status: 'Código enviado.' } };
    });

    assert.equal(page.submit.disabled, true);
    assert.equal(page.codeControls.hidden, true);
    assert.equal(page.code.disabled, true);
    assert.equal(page.verify.disabled, true);
    page.code.value = 'abc';
    await page.verify.listeners.click();
    assert.equal(requests.length, 0);
    assert.match(page.status.textContent, /código de 6 dígitos/);
    await page.issue.listeners.click();
    assert.equal(page.controls.hidden, false);
    assert.equal(page.codeControls.hidden, false);
    assert.equal(page.code.disabled, false);
    assert.equal(page.status.textContent, 'Código enviado.');
    assert.equal(page.resendStatus.textContent, 'Puedes solicitar otro código en 300 segundos.');
    assert.equal(page.submit.disabled, true);
    page.code.value = '123456';
    await page.verify.listeners.click();
    assert.equal(page.controls.hidden, true);
    assert.equal(page.code.disabled, true);
    assert.match(page.status.textContent, /Correo verificado/);
    assert.equal(page.submit.disabled, false);
    assert.deepEqual(requests, [
        { url: '/issue', payload: { email: 'guest@example.com' } },
        { url: '/verify', payload: { email: 'guest@example.com', code: '123456' } },
    ]);

    page.email.listeners.input();
    assert.equal(page.controls.hidden, true);
    assert.equal(page.submit.disabled, false);
    page.email.value = 'other@example.com';
    page.email.listeners.input();
    assert.equal(page.controls.hidden, false);
    assert.equal(page.codeControls.hidden, true);
    assert.equal(page.code.disabled, true);
    assert.equal(page.code.value, '');
    assert.match(page.status.textContent, /verifica la nueva dirección/);
    assert.equal(page.submit.disabled, true);
    assert.equal(requests.length, 2);
    await page.issue.listeners.click();
    assert.equal(page.controls.hidden, false);
    page.code.value = '654321';
    await page.verify.listeners.click();
    assert.equal(page.controls.hidden, true);
    assert.deepEqual(requests.at(-1), { url: '/verify', payload: { email: 'other@example.com', code: '654321' } });
    page.email.value = 'autofill@example.com';
    page.email.listeners.change();
    assert.equal(page.controls.hidden, false);
    assert.equal(page.codeControls.hidden, true);
    assert.equal(page.code.disabled, true);
    assert.equal(page.code.value, '');
    assert.equal(requests.length, 4);
    assert.equal(page.submit.disabled, true);
});

test('failed issue requests keep code entry hidden and do not start a cooldown', async () => {
    const page = verificationPage(async () => { throw new Error('offline'); });
    await page.issue.listeners.click();
    assert.equal(page.codeControls.hidden, true);
    assert.equal(page.code.disabled, true);
    assert.equal(page.verify.disabled, true);
    assert.equal(page.issue.disabled, false);
    assert.match(page.status.textContent, /offline/);
});

test('failed or unconfirmed responses keep code controls available without claiming success', async () => {
    for (const response of [
        { ok: false, json: async () => ({ verified: false }) },
        { ok: true, json: async () => ({ verified: false }) },
    ]) {
        const page = verificationPage(async (url) => url === '/issue'
            ? { ok: true, json: async () => ({ status: 'Si el correo es válido, recibirás un código.' }) }
            : response);
        await page.issue.listeners.click();
        await page.verify.listeners.click();
        assert.equal(page.controls.hidden, false);
        assert.equal(page.codeControls.hidden, false);
        assert.equal(page.code.disabled, false);
        assert.equal(page.issue.disabled, true);
        assert.equal(page.verify.disabled, false);
        assert.equal(page.submit.disabled, true);
        assert.doesNotMatch(page.status.textContent, /Correo verificado/);
        assert.match(page.status.textContent, /código|verificar/);
    }
});

test('a late issue response for a changed address cannot reveal controls or overwrite the new request', async () => {
    const pending = [];
    const page = verificationPage((url) => new Promise((resolve) => pending.push({ url, resolve })));
    const old = page.issue.listeners.click();
    assert.equal(page.issue.disabled, true);
    page.email.value = 'new@example.com';
    page.email.listeners.input();
    assert.equal(page.codeControls.hidden, true);
    const current = page.issue.listeners.click();
    assert.equal(pending.length, 2);
    pending[0].resolve({ ok: true, json: async () => ({ status: 'Código enviado.' }) });
    await old;
    assert.equal(page.codeControls.hidden, true);
    assert.equal(page.status.textContent, 'Procesando…');
    pending[1].resolve({ ok: true, json: async () => ({ status: 'Código enviado.' }) });
    await current;
    assert.equal(page.codeControls.hidden, false);
    assert.match(page.resendStatus.textContent, /300 segundos/);
    assert.equal(page.issue.disabled, true);
    assert.equal(page.verify.disabled, false);
});

test('email confirmation alone cannot enable booking without available slots', async () => {
    const page = verificationPage(async (url) => ({ ok: true, json: async () => url === '/verify' ? { verified: true } : { status: 'Código enviado.' } }), 'false');
    await page.issue.listeners.click();
    page.code.value = '123456';
    await page.verify.listeners.click();
    assert.equal(page.controls.hidden, true);
    assert.equal(page.submit.disabled, true);
});

function fakeClock() {
    let currentTime = 0;
    let nextId = 0;
    const intervals = new Map();
    return {
        now: () => currentTime,
        setInterval: (callback) => {
            const id = ++nextId;
            intervals.set(id, callback);
            return id;
        },
        clearInterval: (id) => intervals.delete(id),
        advance(milliseconds) {
            currentTime += milliseconds;
            [...intervals.values()].forEach((callback) => callback());
        },
        get activeIntervals() { return intervals.size; },
    };
}

test('resend stays disabled for 299 seconds, enables at 300 seconds, and email switching does not bypass cooldown', async () => {
    const clock = fakeClock();
    let sent = 0;
    const page = verificationPage(async () => {
        sent++;
        return { ok: true, json: async () => ({ status: 'Si el correo es válido, recibirás un código.' }) };
    }, 'true', clock);
    await page.issue.listeners.click();
    assert.equal(sent, 1);
    assert.equal(page.issue.disabled, true);
    assert.equal(page.codeControls.hidden, false);
    clock.advance(299999);
    assert.equal(page.issue.disabled, true);
    clock.advance(1);
    assert.equal(page.issue.disabled, false);
    await page.issue.listeners.click();
    assert.equal(sent, 2);
    page.email.value = 'other@example.com';
    page.email.listeners.input();
    page.email.value = 'guest@example.com';
    page.email.listeners.input();
    assert.equal(page.issue.disabled, true);
});

test('switching away from and back to a cooling email resumes its countdown', async () => {
    const clock = fakeClock();
    const page = verificationPage(async () => ({ ok: true, json: async () => ({ status: 'Código enviado.' }) }), 'true', clock);
    await page.issue.listeners.click();
    page.email.value = 'other@example.com';
    page.email.listeners.input();
    assert.equal(page.issue.disabled, false);
    page.email.value = 'guest@example.com';
    page.email.listeners.input();
    assert.equal(page.issue.disabled, true);
    clock.advance(300000);
    assert.equal(page.issue.disabled, false);
    assert.equal(clock.activeIntervals, 0);
});

test('resend countdown never overwrites verification results and stops after verification', async () => {
    const clock = fakeClock();
    const page = verificationPage(async (url) => ({ ok: true, json: async () => url === '/verify' ? { verified: true } : { status: 'Código enviado.' } }), 'true', clock);
    await page.issue.listeners.click();
    page.code.value = '123456';
    await page.verify.listeners.click();
    const verificationMessage = page.status.textContent;
    assert.match(verificationMessage, /Correo verificado/);
    assert.equal(clock.activeIntervals, 0);
    clock.advance(300000);
    assert.equal(page.status.textContent, verificationMessage);
});

test('verified accounts keep their existing code-free path and still need available slots', () => {
    for (const ready of ['true', 'false']) {
        const submit = element();
        const form = element({
            dataset: { emailVerified: 'true', availabilityReady: ready },
            querySelector: (selector) => selector === '[data-slot-submit]' ? submit : null,
        });
        initializePublicBooking({ querySelectorAll: (selector) => selector === '[data-public-booking]' ? [form] : [] });
        assert.equal(submit.disabled, ready !== 'true');
    }
});
