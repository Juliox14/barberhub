import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const appEntry = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');

test('application entry retains public booking but no longer loads the retired Google SDK module', () => {
    assert.match(appEntry, /import\('\.\/public-booking\.js'\)/);
    assert.doesNotMatch(appEntry, /barbershop-location-search/);
    assert.doesNotMatch(appEntry, /maps\.googleapis\.com/);
});
