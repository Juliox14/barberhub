import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const stylesheet = readFileSync(new URL('../../resources/css/public-booking.css', import.meta.url), 'utf8');
const mobileRules = stylesheet.match(/@media \(max-width: 1023px\) \{([\s\S]*?)\n\}/)?.[1] ?? '';

test('mobile wizard displays the panel matching each step and reserves summary for contact step', () => {
    for (const step of [1, 2, 3]) {
        assert.ok(
            mobileRules.includes(`[data-wizard-enhanced][data-wizard-step="${step}"] [data-step-panel]:not([data-step-panel="${step}"])`),
            `step ${step} should hide every panel except its matching panel`,
        );
    }

    assert.ok(mobileRules.includes('[data-wizard-enhanced][data-wizard-step="4"] .bh-public-flow'));
    assert.ok(!mobileRules.includes('[data-wizard-enhanced][data-wizard-step="3"] .bh-public-flow'));
    assert.match(mobileRules, /\.bh-public-wizard:not\(\[data-wizard-enhanced\]\) \.bh-public-progress \{ display: none; \}/);
    assert.match(stylesheet, /@media \(min-width: 1024px\) \{[\s\S]*?\.bh-public-mobile-bar \{ display: none; \}/);
});
