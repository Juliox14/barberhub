import test from 'node:test';
import assert from 'node:assert/strict';
import { confirmationMessage, initializePasswordFeedback, passwordRequirements } from '../../resources/js/password-feedback.js';

const keys = ['length', 'uppercase', 'lowercase', 'number', 'symbol'];

class FakeElement {
    constructor(dataset = {}) {
        this.dataset = dataset;
        this.listeners = new Map();
        this.attributes = new Map();
        this.textContent = '';
        this.value = '';
        this.inserted = [];
        this.classList = {
            _set: new Set(),
            add(...names) { names.forEach((name) => this._set.add(name)); },
            remove(...names) { names.forEach((name) => this._set.delete(name)); },
            contains(name) { return this._set.has(name); },
        };
    }

    setAttribute(name, value) { this.attributes.set(name, value); }
    addEventListener(name, callback) {
        this.listeners.set(name, [...(this.listeners.get(name) ?? []), callback]);
    }
    dispatch(name) { (this.listeners.get(name) ?? []).forEach((callback) => callback()); }
    insertAdjacentElement(_position, element) { this.inserted.push(element); }
}

function formFixture({ passwordValue = '', confirmationValue = '', passwordId = 'password', confirmationId = 'password_confirmation' } = {}) {
    const states = Object.fromEntries(keys.map((key) => [key, new FakeElement()]));
    const items = keys.map((key) => ({
        dataset: { passwordRequirement: key },
        querySelector: () => states[key],
    }));
    const password = new FakeElement();
    password.id = passwordId;
    password.name = 'password';
    password.value = passwordValue;
    const confirmation = new FakeElement();
    confirmation.id = confirmationId;
    confirmation.name = 'password_confirmation';
    confirmation.value = confirmationValue;
    const form = new FakeElement();
    form.querySelector = (selector) => selector === 'input[name="password"]' ? password
        : selector === 'input[name="password_confirmation"]' ? confirmation : null;
    const root = new FakeElement({ tone: 'dark' });
    root.list = new FakeElement();
    root.list.classList.add('hidden');
    root.closest = (selector) => selector === 'form' ? form : null;
    root.querySelector = (selector) => selector === 'ul' ? root.list : null;
    root.querySelectorAll = (selector) => selector === '[data-password-requirement]' ? items : [];
    return { root, form, password, confirmation, states, items };
}

function documentFixture(roots) {
    return {
        querySelectorAll: (selector) => selector === '[data-password-requirements]' ? roots : [],
        createElement: () => new FakeElement(),
    };
}

test('password feedback matches Laravel Unicode categories and codepoint minimum length', () => {
    assert.deepEqual(passwordRequirements('Abcdefgh12!').map(({ met }) => met), [false, true, true, true, true]);
    assert.deepEqual(passwordRequirements('Abcdefgh123!').map(({ met }) => met), [true, true, true, true, true]);
    assert.deepEqual(passwordRequirements('Abcdefgh1234x').map(({ met }) => met), [true, true, true, true, false]);
    assert.deepEqual(passwordRequirements('abcdefgh123!').map(({ met }) => met), [true, false, true, true, true]);
    assert.deepEqual(passwordRequirements('ABCDEFGH123!').map(({ met }) => met), [true, true, false, true, true]);
    assert.deepEqual(passwordRequirements('Abcdefghijk!').map(({ met }) => met), [true, true, true, false, true]);
    assert.deepEqual(passwordRequirements('Abcdefgh1234').map(({ met }) => met), [true, true, true, true, false]);
    assert.deepEqual(passwordRequirements('Abcdefghi😊!').map(({ met }) => met), [false, true, true, false, true]);
    assert.deepEqual(passwordRequirements('Abcdefgh١٢٣¡').map(({ met }) => met), [true, true, true, true, true]);
});

test('initializes status from filled fields and updates on either field input or change', () => {
    const fixture = formFixture({ passwordValue: 'InitialPassword123!', confirmationValue: 'InitialPassword123!', passwordId: 'update_password_password', confirmationId: 'update_password_password_confirmation' });
    initializePasswordFeedback(documentFixture([fixture.root]));
    const status = fixture.confirmation.inserted[0];
    assert.equal(status.textContent, 'Las contraseñas coinciden.');
    assert.equal(fixture.states.length.textContent, 'Cumplido');
    assert.equal(fixture.root.list.classList.contains('hidden'), false);
    assert.match(fixture.states.length.className, /text-emerald-300/);

    fixture.password.value = 'ChangedPassword123!';
    fixture.password.dispatch('input');
    assert.equal(status.textContent, 'Las contraseñas no coinciden.');
    fixture.password.value = 'ChangedPassword123!';
    fixture.password.dispatch('change');
    fixture.confirmation.value = 'ChangedPassword123!';
    fixture.confirmation.dispatch('input');
    assert.equal(status.textContent, 'Las contraseñas coinciden.');
    assert.match(status.className, /text-emerald-300/);
    fixture.confirmation.value = 'StillDifferent123!';
    fixture.confirmation.dispatch('change');
    assert.equal(status.textContent, 'Las contraseñas no coinciden.');
    assert.match(status.className, /text-rose-300/);
});

test('leaves empty confirmation neutral, isolates forms, and does not observe unrelated forms', () => {
    const first = formFixture();
    const second = formFixture({ passwordValue: 'ValidPassword123!', confirmationValue: 'mismatch' });
    const unrelatedRoot = new FakeElement();
    unrelatedRoot.closest = () => ({ dataset: {}, querySelector: () => null });
    initializePasswordFeedback(documentFixture([first.root, second.root, unrelatedRoot]));

    assert.equal(first.confirmation.inserted[0].textContent, '');
    assert.equal(second.confirmation.inserted[0].textContent, 'Las contraseñas no coinciden.');
    first.password.value = 'ChangedPassword123!';
    first.password.dispatch('input');
    assert.equal(first.confirmation.inserted[0].textContent, '');
    assert.equal(second.confirmation.inserted[0].textContent, 'Las contraseñas no coinciden.');
    assert.equal(first.form.dataset.passwordFeedbackInitialized, 'true');
    assert.equal(second.form.dataset.passwordFeedbackInitialized, 'true');
    assert.equal(first.password.listeners.get('input').length, 1);

    initializePasswordFeedback(documentFixture([first.root]));
    assert.equal(first.password.listeners.get('input').length, 1);
});

test('confirmation message is neutral when empty and reflects subsequent password changes', () => {
    assert.equal(confirmationMessage('FirstPassword123!', ''), '');
    assert.equal(confirmationMessage('FirstPassword123!', 'FirstPassword123!'), 'Las contraseñas coinciden.');
    assert.equal(confirmationMessage('ChangedPassword123!', 'FirstPassword123!'), 'Las contraseñas no coinciden.');
});
