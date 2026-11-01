import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeRecaptchaModal } from '../../resources/js/recaptcha-modal.js';

class FakeElement {
    constructor(dataset = {}) {
        this.dataset = dataset;
        this.hidden = false;
        this.style = {};
        this.value = '';
        this.textContent = '';
        this.listeners = new Map();
        this.attributes = new Map();
        this.focused = false;
    }

    addEventListener(name, callback) {
        this.listeners.set(name, [...(this.listeners.get(name) ?? []), callback]);
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    focus() {
        this.focused = true;
        this.ownerDocument.activeElement = this;
    }

    dispatch(name, event = {}) {
        (this.listeners.get(name) ?? []).forEach((callback) => callback(event));
    }
}

function fixture({ required = true, token = '' } = {}) {
    const wrapper = new FakeElement();
    const dialog = new FakeElement();
    const trigger = new FakeElement();
    const closeButton = new FakeElement();
    const status = new FakeElement();
    const challenge = new FakeElement({
        callback: 'barberHubRecaptchaSuccess',
        expiredCallback: 'barberHubRecaptchaExpired',
        errorCallback: 'barberHubRecaptchaError',
    });
    const tokenField = new FakeElement();
    tokenField.value = token;
    const form = new FakeElement();
    const window = {};
    const document = {
        activeElement: null,
        querySelectorAll: (selector) => selector === '[data-recaptcha-modal]' && required ? [wrapper] : [],
    };
    [wrapper, dialog, trigger, closeButton, status, challenge, tokenField, form].forEach((element) => {
        element.ownerDocument = document;
    });
    wrapper.closest = (selector) => selector === 'form' ? form : null;
    wrapper.querySelector = (selector) => ({
        '[data-recaptcha-dialog]': dialog,
        '[data-recaptcha-open]': trigger,
        '[data-recaptcha-close]': closeButton,
        '[data-recaptcha-status]': status,
        '.g-recaptcha': challenge,
    })[selector] ?? null;
    form.querySelector = (selector) => selector === '[name="g-recaptcha-response"]' ? tokenField
        : selector === '[data-recaptcha-open]' ? trigger : null;

    initializeRecaptchaModal(document, window);

    return { wrapper, dialog, trigger, closeButton, status, challenge, tokenField, form, window, document };
}

test('opens on load, closes without losing the token, returns focus, and reopens', () => {
    const app = fixture({ token: 'verified-token' });

    assert.equal(app.wrapper.hidden, false);
    assert.equal(app.wrapper.style.display, 'flex');
    assert.equal(app.dialog.focused, true);
    assert.equal(app.trigger.hidden, true);
    assert.equal(app.trigger.style.display, 'none');

    app.closeButton.dispatch('click');
    assert.equal(app.wrapper.hidden, true);
    assert.equal(app.wrapper.style.display, 'none');
    assert.equal(app.trigger.hidden, false);
    assert.equal(app.trigger.style.display, 'block');
    assert.equal(app.document.activeElement, app.trigger);
    assert.equal(app.tokenField.value, 'verified-token');

    app.trigger.dispatch('click');
    assert.equal(app.wrapper.hidden, false);
    assert.equal(app.wrapper.style.display, 'flex');
    assert.equal(app.trigger.style.display, 'none');
    assert.equal(app.dialog.focused, true);

    app.wrapper.dispatch('keydown', { key: 'Escape', preventDefault() { this.prevented = true; } });
    assert.equal(app.wrapper.hidden, true);
    assert.equal(app.wrapper.style.display, 'none');
    assert.equal(app.document.activeElement, app.trigger);
});

test('blocks submission without a provider token and allows the original form with one', () => {
    const app = fixture();
    let prevented = false;
    const missingTokenEvent = { preventDefault() { prevented = true; } };

    app.closeButton.dispatch('click');
    app.form.dispatch('submit', missingTokenEvent);
    assert.equal(prevented, true);
    assert.equal(app.wrapper.hidden, false);
    assert.equal(app.wrapper.style.display, 'flex');

    prevented = false;
    app.tokenField.value = 'verified-token';
    app.form.dispatch('submit', { preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
});

test('provider success closes without auto-submitting and expiry or provider errors reopen for retry', () => {
    const app = fixture();
    let submitted = false;
    app.form.submit = () => { submitted = true; };

    app.window.barberHubRecaptchaSuccess('provider-token');
    assert.equal(app.wrapper.hidden, true);
    assert.equal(app.wrapper.style.display, 'none');
    assert.equal(app.tokenField.value, 'provider-token');
    assert.equal(app.status.textContent, 'Verificación completada. Continúa para enviar el formulario.');
    assert.equal(submitted, false);
    app.form.dispatch('submit', { preventDefault() { submitted = true; } });
    assert.equal(submitted, false);

    app.window.barberHubRecaptchaExpired();
    assert.equal(app.wrapper.hidden, false);
    assert.equal(app.wrapper.style.display, 'flex');
    assert.equal(app.tokenField.value, '');
    assert.equal(app.status.textContent, 'La verificación venció. Inténtalo de nuevo.');

    app.closeButton.dispatch('click');
    assert.equal(app.wrapper.style.display, 'none');
    app.window.barberHubRecaptchaError();
    assert.equal(app.wrapper.hidden, false);
    assert.equal(app.wrapper.style.display, 'flex');
    assert.equal(app.status.textContent, 'No se pudo completar la verificación. Inténtalo de nuevo.');
});

test('does not initialize or mutate pages without a required challenge', () => {
    const app = fixture({ required: false });

    assert.deepEqual(Object.keys(app.window), []);
    assert.equal(app.wrapper.listeners.size, 0);
});
