import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { landingMotion } from './landing-config.js';

gsap.registerPlugin(ScrollTrigger);

export function createDepthTimeline(animationEngine, { chair, background, intro, copy, shade }, settings, scrollTrigger) {
    const timeline = animationEngine.timeline(scrollTrigger ? { scrollTrigger } : {});
    const { zoomEnd, introFadeStart, introFadeEnd, copyFadeStart, copyFadeEnd } = landingMotion.phases;

    timeline
        .fromTo(chair, {
            scale: settings.initialScale,
            xPercent: settings.initialX,
            yPercent: settings.initialY,
        }, {
            scale: 1,
            xPercent: 0,
            yPercent: 0,
            duration: zoomEnd,
            ease: 'none',
            immediateRender: true,
        }, 0)
        .fromTo(background, {
            scale: landingMotion.background.initialScale,
            yPercent: settings.backgroundInitialY,
        }, {
            scale: 1,
            yPercent: 0,
            duration: 1,
            ease: 'none',
            immediateRender: true,
        }, 0)
        .fromTo(intro, { autoAlpha: 1 }, {
            autoAlpha: 0,
            duration: introFadeEnd - introFadeStart,
            ease: 'none',
            immediateRender: true,
        }, introFadeStart)
        .fromTo(copy, { autoAlpha: 0 }, {
            autoAlpha: 1,
            duration: copyFadeEnd - copyFadeStart,
            ease: 'none',
            immediateRender: true,
        }, copyFadeStart)
        .fromTo(shade, { opacity: landingMotion.shade.initialOpacity }, {
            opacity: 0,
            duration: 1,
            ease: 'none',
            immediateRender: true,
        }, 0);

    return timeline;
}

const landing = typeof document === 'undefined' ? null : document.querySelector('[data-bh-landing]');

if (landing) {
    const hero = landing.querySelector('[data-landing-hero]');
    const chair = landing.querySelector('[data-landing-chair]');
    const background = landing.querySelector('[data-landing-background]');
    const intro = landing.querySelector('[data-landing-intro]');
    const copy = landing.querySelector('[data-landing-copy]');
    const shade = landing.querySelector('[data-landing-dim]');
    const featuresSection = landing.querySelector('[data-landing-features]');
    const features = [...landing.querySelectorAll('[data-landing-feature]')];
    const header = landing.querySelector('.bh-landing__header');
    const media = gsap.matchMedia();
    let disposed = false;

    const setupCinematicMotion = (settings) => {
        if (!hero || !chair || !background || !intro || !copy || !shade) {
            return () => {};
        }

        const motionContext = gsap.context(() => {
            gsap.set(chair, { transformOrigin: landingMotion.chair.transformOrigin, force3D: true });
            createDepthTimeline(gsap, { chair, background, intro, copy, shade }, settings, {
                trigger: hero,
                id: landingMotion.scrollTrigger.id,
                start: landingMotion.scrollTrigger.start,
                end: () => settings.scrollDistance(window.innerHeight),
                pin: true,
                pinSpacing: true,
                scrub: landingMotion.scrollTrigger.scrub,
                invalidateOnRefresh: true,
            });
        }, hero);

        return () => motionContext.revert();
    };

    const handleLandingClick = (event) => {
        const menuLink = event.target.closest('.bh-landing__menu a');
        if (menuLink) {
            menuLink.closest('.bh-landing__menu').open = false;
        }

        const link = event.target.closest('a[href^="#"]');

        if (!link || !landing.contains(link)) {
            return;
        }

        const targetId = decodeURIComponent(link.hash.slice(1));
        const target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        event.preventDefault();
        history.pushState(null, '', link.hash);

        const pinnedHero = target === hero
            ? ScrollTrigger.getAll().find((trigger) => trigger.trigger === hero)
            : null;
        const targetTop = pinnedHero
            ? pinnedHero.start
            : window.scrollY + target.getBoundingClientRect().top - (header?.offsetHeight ?? 0);

        window.scrollTo({ top: targetTop, behavior: 'auto' });
    };

    media.add({
        desktop: landingMotion.breakpoints.desktop,
        mobile: landingMotion.breakpoints.mobile,
        reducedMotion: landingMotion.breakpoints.reducedMotion,
    }, (context) => {
        const { desktop, mobile, reducedMotion } = context.conditions;

        if (reducedMotion) {
            return;
        }

        const cleanup = [];
        if (desktop) {
            cleanup.push(setupCinematicMotion(landingMotion.desktop));
        } else if (mobile) {
            cleanup.push(setupCinematicMotion(landingMotion.mobile));
        }

        if (features.length > 0 && featuresSection) {
            gsap.set(features, { autoAlpha: 0, y: landingMotion.reveal.startY });

            gsap.to(features, {
                autoAlpha: 1,
                y: 0,
                stagger: landingMotion.reveal.stagger,
                duration: landingMotion.reveal.duration,
                ease: landingMotion.reveal.ease,
                scrollTrigger: {
                    trigger: featuresSection,
                    start: landingMotion.featuresStart,
                    once: true,
                },
            });
        }

        return () => cleanup.forEach((dispose) => dispose());
    });

    const cleanup = () => {
        if (disposed) {
            return;
        }
        disposed = true;
        landing.removeEventListener('click', handleLandingClick);
        document.removeEventListener('turbo:before-cache', cleanup);
        window.removeEventListener('pagehide', handlePageHide);
        media.revert();
    };

    const handlePageHide = (event) => {
        if (!event.persisted) {
            cleanup();
        }
    };

    landing.addEventListener('click', handleLandingClick);
    document.addEventListener('turbo:before-cache', cleanup);
    window.addEventListener('pagehide', handlePageHide);
    if (import.meta.hot) {
        import.meta.hot.dispose(cleanup);
    }
}
