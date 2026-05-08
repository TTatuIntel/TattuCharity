/* Loads {API}/content.php and populates [data-content="..."] elements. */
(function () {
    'use strict';

    const $   = (sel, ctx = document) => ctx.querySelector(sel);
    const $$  = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

    function setText(sel, value) {
        const el = $(sel);
        if (el && value !== undefined && value !== null) el.textContent = value;
    }
    function setAttr(sel, attr, value) {
        const el = $(sel);
        if (el && value) el.setAttribute(attr, value);
    }
    function setHref(sel, value) {
        const el = $(sel);
        if (el && value) el.href = value;
    }

    async function loadContent() {
        try {
            const url  = (window.TATTU_API || '/backend/api/') + 'content.php';
            const res  = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            window.__SITE_CONTENT__ = data;
            render(data);
            document.dispatchEvent(new CustomEvent('content:ready', { detail: data }));
        } catch (err) {
            console.error('Could not load site content:', err);
            document.dispatchEvent(new CustomEvent('content:ready', { detail: null }));
        }
    }

    function render(c) {
        if (!c) return;
        const org = c.organization || {};

        document.title = `${org.name || 'Tattu Care'} - ${org.tagline || ''}`.trim();
        $$('[data-content="org.name"]').forEach(el => el.textContent = org.name || '');
        $$('[data-content="org.tagline"]').forEach(el => el.textContent = org.tagline || '');
        $$('[data-content="org.phone"]').forEach(el => el.textContent = org.phone || '');
        $$('[data-content="org.email"]').forEach(el => el.textContent = org.email || '');
        $$('[data-content="org.address"]').forEach(el => el.textContent = org.address || '');
        $$('[data-content="org.registrationNumber"]').forEach(el => el.textContent = org.registrationNumber || '');
        $$('[data-content="org.founded"]').forEach(el => el.textContent = org.founded || '');

        const yearEl = $('[data-content="year"]');
        if (yearEl) yearEl.textContent = new Date().getFullYear();

        setHref('[data-content="org.facebook"]',  org.facebook);
        setHref('[data-content="org.twitter"]',   org.twitter);
        setHref('[data-content="org.instagram"]', org.instagram);
        setHref('[data-content="org.linkedin"]',  org.linkedin);
        setHref('[data-content="org.email-link"]',
            org.email ? 'mailto:' + org.email : null);
        setHref('[data-content="org.phone-link"]',
            org.phone ? 'tel:' + org.phone.replace(/\s+/g, '') : null);

        // ---- Hero (with optional rotating slides) ----
        const hero = c.hero || {};
        setText('[data-content="hero.primaryCta"]',   hero.primaryCtaText);
        setText('[data-content="hero.secondaryCta"]', hero.secondaryCtaText);
        const heroSlides = (Array.isArray(hero.slides) && hero.slides.length)
            ? hero.slides.filter(s => s && (s.image || s.title || s.subtitle))
            : [{ image: hero.image, title: hero.title, subtitle: hero.subtitle }];
        startHeroRotation(heroSlides, Number(hero.rotateMs) || 6500);

        // ---- About (with optional rotating slides) ----
        const about = c.about || {};
        setText('[data-content="about.heading"]', about.heading);
        const aboutSlides = (Array.isArray(about.slides) && about.slides.length)
            ? about.slides.filter(s => s && (s.image || (Array.isArray(s.paragraphs) && s.paragraphs.length)))
            : [{ image: about.image, paragraphs: about.paragraphs }];
        startAboutRotation(aboutSlides, Number(about.rotateMs) || 8000);

        const statsHost = $('[data-content="stats"]');
        if (statsHost && Array.isArray(c.stats)) {
            statsHost.innerHTML = c.stats.map(s => `
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas ${esc(s.icon || 'fa-check')}"></i></div>
                    <div class="stat-body">
                        <div class="stat-number">${esc(s.number)}</div>
                        <div class="stat-text">${esc(s.label)}</div>
                    </div>
                </div>
            `).join('');
        }

        const progHost = $('[data-content="programs"]');
        if (progHost && Array.isArray(c.programs) && c.programs.length) {
            const cardHTML = (p) => `
                <article class="program-card" tabindex="0">
                    ${p.image ? `
                        <div class="program-img">
                            <img src="${esc(p.image)}" alt="${esc(p.title)}" loading="lazy">
                            <div class="program-img-overlay"></div>
                        </div>` : ''}
                    <div class="program-content">
                        <div class="program-icon"><i class="fas ${esc(p.icon || 'fa-hand-holding-heart')}"></i></div>
                        <h3 class="program-title">${esc(p.title)}</h3>
                        <p class="program-desc">${esc(p.description)}</p>
                        ${p.stats ? `<p class="program-stats">${esc(p.stats)}</p>` : ''}
                    </div>
                </article>`;
            // duplicate the list for seamless infinite marquee
            progHost.innerHTML = c.programs.map(cardHTML).join('') +
                                 c.programs.map(cardHTML).join('');
        }

        const impactHost = $('[data-content="impactStories"]');
        if (impactHost && Array.isArray(c.impactStories) && c.impactStories.length) {
            const cardHTML = (s, idx) => `
                <article class="impact-card" tabindex="0" role="listitem"
                         data-impact-idx="${idx}">
                    <div class="impact-card-img">
                        ${s.image ? `<img src="${esc(s.image)}" alt="${esc(s.title)}" loading="lazy">` : ''}
                    </div>
                    <div class="impact-card-body">
                        <h3>${esc(s.title)}</h3>
                        <p>${esc(s.text)}</p>
                        <span class="impact-card-cta">Read story <i class="fas fa-arrow-right"></i></span>
                    </div>
                </article>`;
            // duplicate the list for a seamless infinite scroll
            impactHost.innerHTML =
                c.impactStories.map((s, i) => cardHTML(s, i)).join('') +
                c.impactStories.map((s, i) => cardHTML(s, i)).join('');
            // expose for the click handler
            window.__IMPACT_STORIES__ = c.impactStories;
        }

        const teamHost = $('[data-content="team"]');
        if (teamHost && Array.isArray(c.team) && c.team.length) {
            teamHost.innerHTML = c.team.map(t => `
                <div class="team-card">
                    <div class="team-photo">
                        ${t.image ? `<img src="${esc(t.image)}" alt="${esc(t.name)}" loading="lazy">` : `<i class="fas fa-user"></i>`}
                    </div>
                    <h3 class="team-name">${esc(t.name)}</h3>
                    <p class="team-role">${esc(t.role)}</p>
                    ${t.bio ? `<p class="team-bio">${esc(t.bio)}</p>` : ''}
                </div>
            `).join('');
        } else {
            const teamSec = $('#team');
            if (teamSec) teamSec.classList.add('hidden');
        }

        const d = c.donation || {};
        setText('[data-content="donation.note"]',         d.note);
        setText('[data-content="donation.merchantCode"]', d.merchantCode);
        const goal   = Number(d.goal   || 0);
        const raised = Number(d.raised || 0);
        const cur    = d.currency || 'USD';
        setText('[data-content="donation.goal"]',   `${cur} ${goal.toLocaleString()}`);
        setText('[data-content="donation.raised"]', `${cur} ${raised.toLocaleString()}`);
        const fill = $('[data-content="donation.progressFill"]');
        if (fill) {
            const pct = goal > 0 ? Math.min(100, Math.round((raised / goal) * 100)) : 0;
            requestAnimationFrame(() => { fill.style.width = pct + '%'; });
        }
        const amountsHost = $('[data-content="donation.amounts"]');
        if (amountsHost && Array.isArray(d.amounts)) {
            amountsHost.innerHTML = d.amounts.map(a =>
                `<button type="button" class="amount-btn" data-amount="${Number(a)}">${cur} ${Number(a).toLocaleString()}</button>`
            ).join('') + `<input type="number" id="custom-amount" placeholder="Custom (${cur})" min="1" aria-label="Custom amount">`;
        }

        const eventsHost = $('[data-content="events"]');
        if (eventsHost && Array.isArray(c.events)) {
            eventsHost.innerHTML = c.events.map(ev => `
                <div class="event-card">
                    <div class="event-header">
                        <h3 class="event-title">${esc(ev.title)}</h3>
                        <p class="event-date">${esc(ev.date)}</p>
                    </div>
                    <div class="event-content">
                        <p class="event-desc">${esc(ev.description)}</p>
                        ${Array.isArray(ev.items) && ev.items.length ? `
                          <div class="event-items">
                            <h4>Items Needed:</h4>
                            <ul>${ev.items.map(i => `<li>${esc(i)}</li>`).join('')}</ul>
                          </div>` : ''}
                    </div>
                    <div class="event-footer">
                        <a href="${esc(ev.ctaLink || '#contact')}" class="btn btn-outline">${esc(ev.ctaText || 'Get Involved')}</a>
                    </div>
                </div>
            `).join('');
        }

        const t = c.trust || {};
        setText('[data-content="trust.country"]', t.registrationCountry || '');
        const annualLink = $('[data-content="trust.annualReport"]');
        if (annualLink) {
            if (t.annualReportUrl) {
                annualLink.href = t.annualReportUrl;
                annualLink.classList.remove('hidden');
            } else {
                annualLink.classList.add('hidden');
            }
        }
    }

    /* =========================================================
     *  HERO slide rotation - crossfade between background layers
     * ========================================================= */
    let _heroTimer = null;
    function startHeroRotation(slides, intervalMs) {
        if (_heroTimer) { clearInterval(_heroTimer); _heroTimer = null; }
        const section = $('#home');
        const titleEl = $('[data-content="hero.title"]');
        const subEl   = $('[data-content="hero.subtitle"]');
        const content = $('[data-hero-content]');
        const layerA  = $('[data-hero-bg="a"]');
        const layerB  = $('[data-hero-bg="b"]');
        const dotsHost= $('[data-hero-dots]');
        if (!section || !titleEl || !subEl || !layerA || !layerB) return;

        let active = 'a';
        let idx = 0;

        function paintLayer(layerKey, slide) {
            const el = layerKey === 'a' ? layerA : layerB;
            if (!el) return;
            el.style.backgroundImage = slide.image ? `url("${slide.image}")` : '';
        }
        function setText(slide) {
            if (titleEl) titleEl.textContent = slide.title    || '';
            if (subEl)   subEl.textContent   = slide.subtitle || '';
        }
        function buildDots() {
            if (!dotsHost) return;
            dotsHost.innerHTML = slides.map((_, i) =>
                `<button type="button" data-hero-go="${i}" aria-label="Slide ${i+1}" class="${i===0?'active':''}"></button>`
            ).join('');
            // remove dots when only one slide
            dotsHost.style.display = slides.length > 1 ? '' : 'none';
        }
        function syncDots() {
            if (!dotsHost) return;
            $$('button', dotsHost).forEach((b, i) => b.classList.toggle('active', i === idx));
        }
        function show(i, animate = true) {
            idx = (i + slides.length) % slides.length;
            const slide = slides[idx];
            const nextKey = active === 'a' ? 'b' : 'a';
            paintLayer(nextKey, slide);

            if (animate) content && content.classList.add('fade-out');
            // Bring next layer in
            const nextEl = nextKey === 'a' ? layerA : layerB;
            const curEl  = active  === 'a' ? layerA : layerB;
            requestAnimationFrame(() => {
                nextEl.classList.add('is-active');
                curEl.classList.remove('is-active');
            });
            setTimeout(() => {
                setText(slide);
                content && content.classList.remove('fade-out');
            }, animate ? 380 : 0);
            active = nextKey;
            syncDots();
        }

        // Wire dot clicks
        if (dotsHost) {
            dotsHost.addEventListener('click', e => {
                const b = e.target.closest('[data-hero-go]');
                if (!b) return;
                show(Number(b.dataset.heroGo), true);
                schedule();
            });
        }

        // Pause on hover
        section.addEventListener('mouseenter', stop);
        section.addEventListener('mouseleave', schedule);

        function schedule() {
            stop();
            if (slides.length <= 1) return;
            _heroTimer = setInterval(() => show(idx + 1, true), intervalMs);
        }
        function stop() { if (_heroTimer) { clearInterval(_heroTimer); _heroTimer = null; } }

        // Init
        buildDots();
        // First paint without fade
        paintLayer('a', slides[0]);
        layerA.classList.add('is-active');
        setText(slides[0]);
        active = 'a';
        idx = 0;
        schedule();
    }

    /* =========================================================
     *  ABOUT slide rotation - crossfade image + paragraphs
     * ========================================================= */
    let _aboutTimer = null;
    function startAboutRotation(slides, intervalMs) {
        if (_aboutTimer) { clearInterval(_aboutTimer); _aboutTimer = null; }
        const wrap   = $('[data-about-img-wrap]');
        const img    = $('[data-content="about.image"]');
        const paraEl = $('[data-about-paragraphs]');
        const dotsHost = $('[data-about-dots]');
        if (!img || !paraEl) return;

        let idx = 0;

        function paintParagraphs(arr) {
            if (!Array.isArray(arr)) arr = [];
            paraEl.innerHTML = arr.map(p => `<p>${esc(p)}</p>`).join('');
        }
        function buildDots() {
            if (!dotsHost) return;
            dotsHost.innerHTML = slides.map((_, i) =>
                `<button type="button" data-about-go="${i}" aria-label="View ${i+1}" class="${i===0?'active':''}"></button>`
            ).join('');
            dotsHost.style.display = slides.length > 1 ? '' : 'none';
        }
        function syncDots() {
            if (!dotsHost) return;
            $$('button', dotsHost).forEach((b, i) => b.classList.toggle('active', i === idx));
        }
        function show(i, animate = true) {
            idx = (i + slides.length) % slides.length;
            const s = slides[idx];
            if (animate) {
                img.classList.add('fade-out');
                paraEl.classList.add('fade-out');
            }
            setTimeout(() => {
                if (s.image) img.src = s.image;
                paintParagraphs(s.paragraphs);
                img.classList.remove('fade-out');
                paraEl.classList.remove('fade-out');
            }, animate ? 380 : 0);
            syncDots();
        }
        if (dotsHost) {
            dotsHost.addEventListener('click', e => {
                const b = e.target.closest('[data-about-go]');
                if (!b) return;
                show(Number(b.dataset.aboutGo), true);
                schedule();
            });
        }
        if (wrap) {
            wrap.addEventListener('mouseenter', stop);
            wrap.addEventListener('mouseleave', schedule);
        }
        function schedule() {
            stop();
            if (slides.length <= 1) return;
            _aboutTimer = setInterval(() => show(idx + 1, true), intervalMs);
        }
        function stop() { if (_aboutTimer) { clearInterval(_aboutTimer); _aboutTimer = null; } }

        buildDots();
        // First paint without animation
        if (slides[0]) {
            if (slides[0].image) img.src = slides[0].image;
            paintParagraphs(slides[0].paragraphs);
        }
        idx = 0;
        schedule();
    }

    document.addEventListener('DOMContentLoaded', loadContent);
})();
