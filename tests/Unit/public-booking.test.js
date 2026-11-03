import test from 'node:test';
import assert from 'node:assert/strict';
import { requestVerification } from '../../resources/js/public-booking.js';

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
