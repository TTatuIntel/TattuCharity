/* Resolves the absolute URL of the backend/api/ directory regardless of
 * which page (or sub-folder) loaded the script. The script itself lives at
 *   <site-root>/frontend/js/api-config.js
 * so backend/api/ is two levels up. */
(function () {
    const me = document.currentScript || (function () {
        const list = document.getElementsByTagName('script');
        return list[list.length - 1];
    })();
    const url = me ? me.src : '';
    window.TATTU_API = new URL('../../backend/api/', url).href;

    /** Readable pacing for all dynamic UI — hold long enough to read, slide smoothly */
    window.TATTU_TIMING = {
        scroll: 520,       // ms — one-card slide animation
        resume: 3200,      // ms — resume auto-play after user interaction
        story: 3600,       // ms — full cycle: impact, events, team (hold + slide)
        compact: 3600,     // ms — full cycle: partners
        programs: 3600,    // ms — programs coverflow step
        hero: 4000,        // ms — hero slide rotation (only section at 4s)
        about: 3600,       // ms — about slide rotation
    };

    /** @deprecated Use TATTU_TIMING — kept for older fallbacks */
    window.TATTU_DYNAMIC_MS = window.TATTU_TIMING.programs;

    /** Push timing into CSS so mobile, tablet, and desktop share one source */
    const root = document.documentElement;
    const t = window.TATTU_TIMING;
    root.style.setProperty('--dyn-interval', `${t.programs}ms`);
    root.style.setProperty('--rail-cycle-ms', `${t.story}ms`);
    root.style.setProperty('--hero-interval', `${t.hero}ms`);
    root.style.setProperty('--about-interval', `${t.about}ms`);
})();
