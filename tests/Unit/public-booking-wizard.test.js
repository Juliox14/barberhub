import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeBookingWizard } from '../../resources/js/public-booking-wizard.js';

function node(properties = {}) {
    return {
        dataset: {}, listeners: {}, hidden: false, disabled: false, attributes: {},
        addEventListener(type, listener) { this.listeners[type] = listener; },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        focus() { this.focused = true; },
        ...properties,
    };
}

function page({ mobile = true, initialStep = '1' } = {}) {
    const media = node({ matches: mobile });
    const barber = node({ name: 'barber_id', value: '7' });
    const service = node({ value: '2' });
    const date = node({ value: '2026-10-05', validity: { valid: true } });
    const slot = node({ value: '7|2026-10-05T10:00' });
    const booking = node({ dataset: { availabilityReady: 'true', emailVerified: 'true' } });
    const contact = { name: 'Ana', email: 'ana@example.com', phone: '1234567890' };
    const back = node();
    const next = node();
    const progress = node();
    const dates = node();
    const panels = [1, 2, 3, 4].map((step) => node({ dataset: { stepPanel: String(step) } }));
    const items = [1, 2, 3, 4].map((step) => node({ dataset: { progressStep: String(step) } }));
    const nodes = {
        '[name="barber_id"]': barber,
        '[data-wizard-back]': back, '[data-wizard-next]': next,
        '[data-wizard-status]': progress, '[data-public-booking]': booking,
        '[name="service_id"]:checked': service, '[name="date"]': date,
        '[name="slot"]:checked': slot, '[data-date-options]': dates,
    };
    const root = node({
        dataset: { initialStep },
        querySelector: (selector) => nodes[selector] ?? panels.find((panel) => selector === `[data-step-panel="${panel.dataset.stepPanel}"]`) ?? null,
        querySelectorAll: (selector) => selector === '[data-progress-step]' ? items : [],
    });
    const document = {
        defaultView: { matchMedia: () => media },
        querySelectorAll: (selector) => selector === '[data-booking-wizard]' ? [root] : [],
    };
    initializeBookingWizard(document);
    return { root, media, barber, service, date, slot, booking, contact, back, next, progress, panels, items, nodes };
}

test('mobile advances through existing panels and back without resetting selections, contact or verification', () => {
    const view = page();
    assert.equal(view.root.dataset.wizardStep, '1');
    assert.equal(view.back.hidden, true);
    assert.equal(view.items[0].attributes['aria-current'], 'step');
    view.next.listeners.click();
    assert.equal(view.root.dataset.wizardStep, '2');
    assert.equal(view.panels[1].focused, true);
    assert.equal(view.items[0].attributes['aria-current'], undefined);
    assert.equal(view.items[1].attributes['aria-current'], 'step');
    view.next.listeners.click();
    assert.equal(view.root.dataset.wizardStep, '3');
    assert.equal(view.next.hidden, false);
    assert.match(view.progress.textContent, /Paso 3 de 4/);
    view.next.listeners.click();
    assert.equal(view.root.dataset.wizardStep, '4');
    assert.equal(view.next.hidden, true);
    view.back.listeners.click();
    view.back.listeners.click();
    view.back.listeners.click();
    assert.equal(view.root.dataset.wizardStep, '1');
    assert.equal(view.service.value, '2');
    assert.equal(view.date.value, '2026-10-05');
    assert.equal(view.slot.value, '7|2026-10-05T10:00');
    assert.deepEqual(view.contact, { name: 'Ana', email: 'ana@example.com', phone: '1234567890' });
    assert.equal(view.booking.dataset.emailVerified, 'true');
});

test('selecting a professional on mobile advances directly to that professional’s service step', () => {
    const view = page();

    view.root.listeners.change({ target: view.barber });

    assert.equal(view.root.dataset.wizardStep, '2');
    assert.equal(view.panels[1].focused, true);
    assert.match(view.progress.textContent, /Seleccionar servicio/);
});

test('mobile cannot skip a missing service, invalid date, loading availability or missing slot', () => {
    const view = page();
    view.next.listeners.click();
    view.nodes['[name="service_id"]:checked'] = null;
    view.root.listeners.change();
    assert.equal(view.next.disabled, true);
    view.next.listeners.click();
    assert.equal(view.root.dataset.wizardStep, '2');
    view.nodes['[name="service_id"]:checked'] = view.service;
    view.root.listeners.change();
    assert.equal(view.next.disabled, false);
    view.next.listeners.click();
    for (const state of ['false', 'true']) {
        view.booking.dataset.availabilityReady = state;
        view.nodes['[name="slot"]:checked'] = state === 'true' ? null : view.slot;
        view.root.listeners.change();
        assert.equal(view.next.disabled, true);
        view.next.listeners.click();
        assert.equal(view.root.dataset.wizardStep, '3');
    }
    view.nodes['[name="slot"]:checked'] = view.slot;
    view.date.validity.valid = false;
    view.root.listeners.change();
    view.next.listeners.click();
    assert.equal(view.root.dataset.wizardStep, '3');
    view.date.validity.valid = true;
    view.root.listeners.change();
    assert.equal(view.next.disabled, false);
    view.next.listeners.click();
    assert.equal(view.root.dataset.wizardStep, '4');
});

test('desktop is not stepped and resizing restores the mobile position without clearing data', () => {
    const view = page({ mobile: false });
    assert.equal(view.root.dataset.wizardEnhanced, undefined);
    view.media.matches = true;
    view.media.listeners.change();
    assert.equal(view.root.dataset.wizardEnhanced, 'true');
    view.next.listeners.click();
    view.media.matches = false;
    view.media.listeners.change();
    assert.equal(view.root.dataset.wizardEnhanced, undefined);
    view.media.matches = true;
    view.media.listeners.change();
    assert.equal(view.root.dataset.wizardStep, '2');
    assert.equal(view.slot.value, '7|2026-10-05T10:00');
});

test('returned validation errors reopen their step and invalid hidden slot reveals the schedule', () => {
    const view = page({ initialStep: '4' });
    assert.equal(view.root.dataset.wizardStep, '4');
    view.root.listeners.invalid({ target: { name: 'slot' } });
    assert.equal(view.root.dataset.wizardStep, '3');
    assert.equal(view.panels[2].focused, true);
});

test('without matchMedia the original complete booking page remains usable', () => {
    const root = node();
    initializeBookingWizard({ querySelectorAll: () => [root] });
    assert.deepEqual(root.dataset, {});
});
