import test from 'node:test';
import assert from 'node:assert/strict';
import { gsap } from 'gsap';
import { createDepthTimeline } from '../resources/js/landing.js';
import { landingMotion } from '../resources/js/landing-config.js';

for (const viewport of ['desktop', 'mobile']) {
    test(`${viewport} close-up reveal returns to normal before copy appears and reverses cleanly`, () => {
        const settings = landingMotion[viewport];
        const chair = { scale: 1, xPercent: 0, yPercent: 0 };
        const background = { scale: 1, yPercent: 0 };
        const intro = { autoAlpha: 0 };
        const copy = { autoAlpha: 1 };
        const shade = { opacity: 0 };
        const timeline = createDepthTimeline(gsap, { chair, background, intro, copy, shade }, settings);

        timeline.progress(0);
        assert.equal(landingMotion.chair.transformOrigin, '50% 20%');
        assert.equal(chair.scale, settings.initialScale);
        assert.equal(chair.xPercent, settings.initialX);
        assert.equal(chair.yPercent, settings.initialY);
        assert.equal(intro.autoAlpha, 1);
        assert.equal(copy.autoAlpha, 0);
        assert.equal(background.scale, landingMotion.background.initialScale);
        assert.equal(background.yPercent, settings.backgroundInitialY);
        assert.equal(shade.opacity, landingMotion.shade.initialOpacity);

        timeline.progress(0.35);
        assert.ok(chair.scale < settings.initialScale, 'chair pulls back from the initial upper-backrest close-up');
        assert.equal(intro.autoAlpha, 1);
        assert.equal(copy.autoAlpha, 0);

        timeline.progress(landingMotion.phases.zoomEnd);
        assert.equal(chair.scale, 1);
        assert.equal(chair.xPercent, 0);
        assert.equal(chair.yPercent, 0);
        assert.equal(copy.autoAlpha, 0, 'marketing copy waits until chair reaches normal scale');

        timeline.progress(0.86);
        assert.equal(chair.scale, 1);
        assert.equal(intro.autoAlpha, 0);
        assert.ok(copy.autoAlpha > 0 && copy.autoAlpha < 1);

        timeline.progress(1);
        assert.equal(chair.scale, 1);
        assert.equal(chair.xPercent, 0);
        assert.equal(chair.yPercent, 0);
        assert.equal(intro.autoAlpha, 0);
        assert.equal(copy.autoAlpha, 1);
        assert.equal(background.scale, 1);
        assert.equal(background.yPercent, 0);
        assert.equal(shade.opacity, 0);

        timeline.progress(0.86);
        assert.equal(chair.scale, 1);
        assert.ok(copy.autoAlpha < 1);
        timeline.progress(0);
        assert.equal(chair.scale, settings.initialScale);
        assert.equal(chair.xPercent, settings.initialX);
        assert.equal(chair.yPercent, settings.initialY);
        assert.equal(background.scale, landingMotion.background.initialScale);
        assert.equal(background.yPercent, settings.backgroundInitialY);
        assert.equal(intro.autoAlpha, 1);
        assert.equal(copy.autoAlpha, 0);
        assert.equal(shade.opacity, landingMotion.shade.initialOpacity);
        timeline.kill();
    });
}

test('motion breakpoints depend on width, and pin distance is computed from viewport height', () => {
    assert.equal(landingMotion.breakpoints.desktop, '(min-width: 64rem)');
    assert.equal(landingMotion.breakpoints.mobile, '(max-width: 63.999rem)');
    assert.equal(landingMotion.desktop.scrollDistance(600), '+=900px');
    assert.equal(landingMotion.mobile.scrollDistance(600), '+=660px');
});
