export const landingMotion = {
    breakpoints: {
        desktop: '(min-width: 64rem)',
        mobile: '(max-width: 63.999rem)',
        reducedMotion: '(prefers-reduced-motion: reduce)',
    },
    desktop: {
        initialScale: 2.05,
        initialX: 0,
        initialY: -12,
        backgroundInitialY: -1.5,
        scrollDistanceMultiplier: 1.5,
        scrollDistance: (viewportHeight) => `+=${Math.round(viewportHeight * landingMotion.desktop.scrollDistanceMultiplier)}px`,
    },
    mobile: {
        initialScale: 1.8,
        initialX: 0,
        initialY: -7,
        backgroundInitialY: -1,
        scrollDistanceMultiplier: 1.1,
        scrollDistance: (viewportHeight) => `+=${Math.round(viewportHeight * landingMotion.mobile.scrollDistanceMultiplier)}px`,
    },
    chair: {
        transformOrigin: '50% 20%',
    },
    scrollTrigger: {
        id: 'barberhub-hero',
        start: 'top top',
        scrub: 1,
    },
    background: {
        initialScale: 1.025,
        initialY: -2,
    },
    shade: {
        initialOpacity: 0.12,
    },
    featuresStart: 'top 78%',
    phases: {
        zoomEnd: 0.72,
        introFadeStart: 0.5,
        introFadeEnd: 0.72,
        copyFadeStart: 0.76,
        copyFadeEnd: 1,
    },
    reveal: {
        startY: 35,
        stagger: 0.12,
        duration: 0.7,
        ease: 'power2.out',
    },
};
