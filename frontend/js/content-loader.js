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
    function setHrefAll(sel, value) {
        const valid = value && value !== '#';
        $$(sel).forEach(el => {
            if (valid) {
                el.href = value;
                el.classList.remove('hidden');
                el.removeAttribute('aria-hidden');
            } else {
                el.classList.add('hidden');
                el.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function parseEventDate(str) {
        if (!str || typeof str !== 'string') return null;
        const iso = str.trim().match(/^(\d{4}-\d{2}-\d{2})/);
        if (iso) {
            const d = new Date(iso[1] + 'T12:00:00');
            return isNaN(d.getTime()) ? null : d;
        }
        const d = new Date(str);
        return isNaN(d.getTime()) ? null : d;
    }

    function getUserInterests() {
        try {
            return JSON.parse(sessionStorage.getItem('tattu_interests') || '{}') || {};
        } catch {
            return {};
        }
    }

    /** Merge admin hero slides with auto-promoted upcoming / featured events. */
    function buildHeroSlides(c) {
        const hero = c.hero || {};
        const manual = (Array.isArray(hero.slides) && hero.slides.length)
            ? hero.slides.filter(s => s && (s.image || s.title || s.subtitle))
            : [{ image: hero.image, title: hero.title, subtitle: hero.subtitle }];

        if (hero.autoPromoteEvents === false) {
            return manual.map(s => ({ ...s, source: 'manual', interest: 'home' }));
        }

        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const promoteDays = Number(hero.eventPromoteDays) || 60;
        const cutoff = new Date(now.getTime() + promoteDays * 86400000);

        const eventSlides = [];
        (c.events || []).forEach(ev => {
            if (ev.featureOnHero === false || ev.status === 'archived') return;

            const start = parseEventDate(ev.startDate) || parseEventDate(ev.date);
            const end   = parseEventDate(ev.endDate) || start;
            const isFeatured = ev.featured === true;
            const isOngoing  = ev.status === 'ongoing' ||
                (start && end && start <= now && end >= now);
            const isUpcoming = start && start >= now && start <= cutoff;

            if (!isFeatured && !isOngoing && !isUpcoming) {
                if (end && end < now) return;
                if (!start) return;
            }

            let priority = 100;
            if (isFeatured) priority = 1000;
            else if (isOngoing) priority = 600;
            else if (start) priority = 400 - Math.min(350, Math.floor((start - now) / 86400000));

            eventSlides.push({
                source: 'event',
                interest: 'events',
                title: ev.heroTitle || ev.title || '',
                subtitle: ev.heroSubtitle || ev.description || '',
                image: ev.image || hero.image || '',
                priority,
            });
        });

        eventSlides.sort((a, b) => b.priority - a.priority);

        const seen = new Set();
        const merged = [];
        [...eventSlides, ...manual.map(s => ({ ...s, source: 'manual', interest: 'home' }))].forEach(s => {
            const key = (s.title || '') + '|' + (s.subtitle || '').slice(0, 40);
            if (seen.has(key)) return;
            seen.add(key);
            merged.push(s);
        });
        return merged.length ? merged : manual;
    }

    function pickNextHeroIndex(current, slides) {
        const interests = getUserInterests();
        const eventIdx = slides.map((s, i) => (s.source === 'event' ? i : -1)).filter(i => i >= 0);
        if (eventIdx.length && (interests.events || 0) >= 1 && Math.random() < 0.38) {
            const next = eventIdx.find(i => i > current);
            return next !== undefined ? next : eventIdx[0];
        }
        if (eventIdx.length && (interests.contact || 0) >= 2 && Math.random() < 0.22) {
            return eventIdx[0];
        }
        return (current + 1) % slides.length;
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
            const timing = window.TATTU_TIMING || {};
            const titleEl = $('[data-content="hero.title"]');
            const subEl   = $('[data-content="hero.subtitle"]');
            startHeroRotation([{
                title: titleEl ? titleEl.textContent : '',
                subtitle: subEl ? subEl.textContent : '',
                image: '',
            }], timing.hero || 4000);
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

        setHrefAll('[data-content="org.facebook"]',  org.facebook);
        setHrefAll('[data-content="org.twitter"]',   org.twitter);
        setHrefAll('[data-content="org.instagram"]', org.instagram);
        setHrefAll('[data-content="org.linkedin"]',  org.linkedin);
        setHrefAll('[data-content="org.email-link"]',
            org.email ? 'mailto:' + org.email : null);
        setHrefAll('[data-content="org.phone-link"]',
            org.phone ? 'tel:' + org.phone.replace(/\s+/g, '') : null);

        // ---- Hero (with optional rotating slides) ----
        const hero = c.hero || {};
        setText('[data-content="hero.primaryCta"]',   hero.primaryCtaText);
        setText('[data-content="hero.secondaryCta"]', hero.secondaryCtaText);
        const heroSlides = buildHeroSlides(c);
        const timing = window.TATTU_TIMING || {};
        startHeroRotation(heroSlides, Number(hero.rotateMs) || timing.hero || 4000);

        // ---- About (with optional rotating slides) ----
        const about = c.about || {};
        setText('[data-content="about.heading"]', about.heading);
        const aboutSlides = (Array.isArray(about.slides) && about.slides.length)
            ? about.slides.filter(s => s && (s.image || (Array.isArray(s.paragraphs) && s.paragraphs.length)))
            : [{ image: about.image, paragraphs: about.paragraphs }];
        startAboutRotation(aboutSlides, Number(about.rotateMs) || timing.about || 3600);

        const statsHost = $('[data-content="stats"]');
        const statsSec = $('.stats-floating');
        const statsDisplay = c.statsDisplay || {};
        const showStatsSection = statsDisplay.showSection !== false;
        const maxStats = Math.min(4, Math.max(1, Number(statsDisplay.maxItems) || 4));
        let statsItems = [];
        if (statsDisplay.source === 'programs' && Array.isArray(c.programs) && c.programs.length) {
            statsItems = c.programs.slice(0, maxStats).map(p => ({
                icon: p.icon || 'fa-hand-holding-heart',
                number: p.stats || '',
                label: p.title || '',
            })).filter(s => s.label || s.number);
        } else if (Array.isArray(c.stats)) {
            statsItems = c.stats
                .filter(s => s && s.enabled !== false)
                .slice(0, maxStats);
        }
        if (statsHost && showStatsSection && statsItems.length) {
            statsHost.dataset.statCount = String(statsItems.length);
            statsHost.innerHTML = statsItems.map(s => `
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas ${esc(s.icon || 'fa-check')}"></i></div>
                    <div class="stat-body">
                        <div class="stat-number">${esc(s.number)}</div>
                        <div class="stat-text">${esc(s.label)}</div>
                    </div>
                </div>
            `).join('');
            if (statsSec) statsSec.classList.remove('hidden');
        } else {
            if (statsHost) {
                statsHost.innerHTML = '';
                delete statsHost.dataset.statCount;
            }
            if (statsSec) statsSec.classList.add('hidden');
        }

        const progHost = $('[data-content="programs"]');
        const progSec = $('#programs');
        if (progHost && Array.isArray(c.programs) && c.programs.length) {
            progHost.innerHTML = c.programs.map((p, i) => `
                <article class="program-card" tabindex="0" data-program-idx="${i}"${i === 0 ? ' data-cf="0"' : ''}>
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
                </article>
            `).join('');
            if (progSec) progSec.classList.remove('hidden');
        } else if (progSec) {
            progSec.classList.add('hidden');
        }

        const impactHost = $('[data-content="impactStories"]');
        const impactSec = $('#impact');
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
            impactHost.innerHTML = c.impactStories.map((s, i) => cardHTML(s, i)).join('');
            impactHost.dataset.originalCount = String(c.impactStories.length);
            window.__IMPACT_STORIES__ = c.impactStories;
            if (impactSec) impactSec.classList.remove('hidden');
        } else if (impactSec) {
            impactSec.classList.add('hidden');
        }

        const teamHost = $('[data-content="team"]');
        const teamSec = $('#team');
        if (teamHost && Array.isArray(c.team) && c.team.length) {
            teamHost.innerHTML = c.team.map((t, i) => `
                <article class="rail-card team-rail-card" data-team-idx="${i}" role="listitem" tabindex="0">
                    <div class="rail-card-img team-rail-photo">
                        ${t.image
                            ? `<img src="${esc(t.image)}" alt="${esc(t.name)}" loading="lazy">`
                            : `<span class="rail-img-fallback"><i class="fas fa-user" aria-hidden="true"></i></span>`}
                    </div>
                    <div class="rail-card-body">
                        <h3>${esc(t.name)}</h3>
                        <p class="rail-card-role">${esc(t.role)}</p>
                        ${t.bio ? `<p class="rail-card-text">${esc(t.bio)}</p>` : ''}
                    </div>
                </article>
            `).join('');
            teamHost.dataset.originalCount = String(c.team.length);
            if (teamSec) teamSec.classList.remove('hidden');
        } else {
            if (teamHost) teamHost.innerHTML = '';
            if (teamSec) teamSec.classList.add('hidden');
        }

        const partnersHost = $('[data-content="partners"]');
        const partners = (c.trust && Array.isArray(c.trust.partners)) ? c.trust.partners : [];
        const partnersSec = $('#partners');
        if (partnersHost && partners.length) {
            partnersHost.innerHTML = partners.map((p, i) => {
                const name = esc(p.name || 'Partner');
                const logoInner = p.logo
                    ? `<img src="${esc(p.logo)}" alt="${name}" loading="lazy">`
                    : `<span class="rail-img-fallback"><i class="fas fa-handshake" aria-hidden="true"></i></span>`;
                const body = `
                    <div class="rail-card-img partner-rail-logo">${logoInner}</div>
                    <div class="rail-card-body">
                        <h3>${name}</h3>
                        ${p.url ? `<span class="rail-card-cta">Visit <i class="fas fa-external-link-alt"></i></span>` : ''}
                    </div>`;
                if (p.url) {
                    return `<a class="rail-card partner-rail-card" href="${esc(p.url)}" target="_blank" rel="noopener noreferrer" data-partner-idx="${i}" role="listitem">${body}</a>`;
                }
                return `<article class="rail-card partner-rail-card" data-partner-idx="${i}" role="listitem" tabindex="0">${body}</article>`;
            }).join('');
            partnersHost.dataset.originalCount = String(partners.length);
            if (partnersSec) partnersSec.classList.remove('hidden');
        } else {
            if (partnersHost) partnersHost.innerHTML = '';
            if (partnersSec) partnersSec.classList.add('hidden');
        }

        const d = c.donation || {};
        setText('[data-content="donation.note"]',         d.note);
        setText('[data-content="donation.merchantCode"]', d.merchantCode);
        // Stored values are interpreted as the BASE currency in content.json
        // (default USD). Frontend will re-render in the active picker currency.
        window.__DONATION_BASE__ = {
            currency: d.currency || 'USD',
            goal:     Number(d.goal   || 0),
            raised:   Number(d.raised || 0),
            amounts:  Array.isArray(d.amounts) ? d.amounts.map(Number) : [10, 25, 50, 100],
            supportedCurrencies: Array.isArray(d.supportedCurrencies) && d.supportedCurrencies.length
                ? d.supportedCurrencies
                : ['USD','EUR','GBP','UGX','KES'],
        };
        const fill = $('[data-content="donation.progressFill"]');
        if (fill) {
            const goal = window.__DONATION_BASE__.goal;
            const raised = window.__DONATION_BASE__.raised;
            const pct = goal > 0 ? Math.min(100, Math.round((raised / goal) * 100)) : 0;
            requestAnimationFrame(() => { fill.style.width = pct + '%'; });
        }
        // The currency picker + amount buttons are rendered dynamically by main.js
        // once FX rates are fetched; main.js dispatches when ready.

        const eventsHost = $('[data-content="events"]');
        const eventsSec = $('#events');
        if (eventsHost && Array.isArray(c.events)) {
            renderEventsRail(eventsHost, c.events);
            if (eventsSec) eventsSec.classList.remove('hidden');
        } else if (eventsSec) {
            eventsSec.classList.add('hidden');
        }

        const t = c.trust || {};
        setText('[data-content="trust.country"]', t.registrationCountry || '');
        const trustBar = $('#trustBar') || $('.trust-bar');
        if (trustBar) {
            trustBar.classList.toggle('hidden', t.showTrustBar !== true);
        }
        const showFooterLegal = t.showFooterLegal === true;
        $$('[data-footer-legal]').forEach(el => { el.hidden = !showFooterLegal; });
        $$('.footer-bottom').forEach(el => {
            el.classList.toggle('footer-bottom--compact', !showFooterLegal);
        });
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
     *  Events — horizontal rail cards (matches Impact)
     * ========================================================= */
    function renderEventRailCard(ev, i, status) {
        const items = Array.isArray(ev.items) ? ev.items : [];
        const featured = !!ev.featured;
        const isDone = ev.status === 'completed';
        const preview = items.slice(0, 3);
        const more = items.length - preview.length;
        const statusSlug = String(ev.status || 'upcoming').toLowerCase().replace(/[^a-z]/g, '');
        const thumb = ev.image
            ? `<img src="${esc(ev.image)}" alt="" loading="lazy">`
            : `<span class="rail-img-fallback"><i class="fas fa-calendar-alt"></i></span>`;
        return `
        <article class="rail-card event-rail-card${isDone ? ' event-rail-card--completed' : ''}" data-event-idx="${i}" role="listitem" tabindex="0">
            <div class="rail-card-img">
                ${thumb}
                <span class="rail-badge rail-badge--${esc(statusSlug)}">${esc(status)}</span>
                ${featured ? '<span class="rail-badge rail-badge--featured" title="Featured"><i class="fas fa-star"></i></span>' : ''}
            </div>
            <div class="rail-card-body">
                <h3>${esc(ev.title)}</h3>
                <p class="rail-card-meta"><i class="fas fa-calendar-day"></i> ${esc(ev.date)}</p>
                ${ev.location ? `<p class="rail-card-meta"><i class="fas fa-map-marker-alt"></i> ${esc(ev.location)}</p>` : ''}
                <p class="rail-card-text">${esc(ev.description)}</p>
                ${preview.length ? `
                <ul class="rail-chips" aria-label="Items needed">
                    ${preview.map(item => `<li>${esc(item)}</li>`).join('')}
                    ${more > 0 ? `<li class="rail-chip-more">+${more}</li>` : ''}
                </ul>` : ''}
                <a href="${esc(ev.ctaLink || '#contact')}" class="rail-card-cta">${esc(ev.ctaText || 'Get Involved')} <i class="fas fa-arrow-right"></i></a>
            </div>
        </article>`;
    }

    function renderEventsRail(host, events) {
        const statusLabels = { upcoming: 'Upcoming', ongoing: 'Ongoing', completed: 'Completed', archived: 'Archived' };
        const visible = events
            .filter(ev => ev.status !== 'archived')
            .sort((a, b) => (b.featured ? 1 : 0) - (a.featured ? 1 : 0));

        const emptyEl = $('[data-events-empty]');
        const railWrap = $('.events-rail');
        const controls = $('[data-events-controls]');
        const sec = $('#events');

        if (!visible.length) {
            host.innerHTML = '';
            delete host.dataset.originalCount;
            if (emptyEl) emptyEl.hidden = false;
            if (railWrap) railWrap.hidden = true;
            if (controls) controls.hidden = true;
            if (sec) sec.classList.add('events--compact');
            return;
        }

        if (emptyEl) emptyEl.hidden = true;
        if (railWrap) railWrap.hidden = false;
        if (controls) controls.hidden = false;
        if (sec) sec.classList.remove('events--compact');

        host.innerHTML = visible.map((ev, i) =>
            renderEventRailCard(ev, i, statusLabels[ev.status] || 'Upcoming')
        ).join('');
        host.dataset.originalCount = String(visible.length);
        window.__EVENTS__ = visible;
    }

    /* =========================================================
     *  HERO slide rotation - crossfade between background layers
     * ========================================================= */
    let _heroTimer = null;
    let _heroAbort = null;

    function startHeroRotation(slides, intervalMs) {
        if (_heroTimer) { clearInterval(_heroTimer); _heroTimer = null; }
        if (_heroAbort) { _heroAbort.abort(); _heroAbort = null; }
        _heroAbort = new AbortController();
        const sig = _heroAbort.signal;

        const section = $('#home');
        const titleEl = $('[data-content="hero.title"]');
        const subEl   = $('[data-content="hero.subtitle"]');
        const content = $('[data-hero-content]');
        const layerA  = $('[data-hero-bg="a"]');
        const layerB  = $('[data-hero-bg="b"]');
        const dotsHost= $('[data-hero-dots]');
        if (!section || !titleEl || !subEl || !layerA || !layerB) return;

        slides = (Array.isArray(slides) ? slides : []).filter(s => s && (s.image || s.title || s.subtitle));
        if (!slides.length) return;

        const timing = window.TATTU_TIMING || {};
        const readResumeMs = timing.resume || 3200;

        let active = 'a';
        let idx = 0;
        let resumeTimer = null;
        let inView = true;

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

        function stop() {
            if (_heroTimer) { clearInterval(_heroTimer); _heroTimer = null; }
            if (resumeTimer) { clearTimeout(resumeTimer); resumeTimer = null; }
        }
        function schedule() {
            stop();
            if (slides.length <= 1 || !inView || document.hidden) return;
            _heroTimer = setInterval(() => show(pickNextHeroIndex(idx, slides), true), intervalMs);
        }
        function pauseForReading() {
            stop();
        }
        function resumeAfterReading(delay = readResumeMs) {
            if (resumeTimer) clearTimeout(resumeTimer);
            resumeTimer = setTimeout(() => {
                resumeTimer = null;
                schedule();
            }, delay);
        }

        if (dotsHost) {
            dotsHost.addEventListener('click', e => {
                const b = e.target.closest('[data-hero-go]');
                if (!b) return;
                pauseForReading();
                show(Number(b.dataset.heroGo), true);
                resumeAfterReading(readResumeMs);
            }, { signal: sig });
        }

        const hasFineHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (hasFineHover) {
            const readingHosts = [content, dotsHost].filter(Boolean);
            readingHosts.forEach(host => {
                host.addEventListener('mouseenter', pauseForReading, { signal: sig });
                host.addEventListener('mouseleave', () => resumeAfterReading(), { signal: sig });
            });
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) stop();
            else if (inView) schedule();
        }, { signal: sig });

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(entries => {
                entries.forEach(en => {
                    inView = en.isIntersecting;
                    if (inView && !document.hidden) schedule();
                    else stop();
                });
            }, { threshold: 0.2 }).observe(section);
        }

        buildDots();
        paintLayer('a', slides[0]);
        layerB.classList.remove('is-active');
        layerA.classList.add('is-active');
        setText(slides[0]);
        active = 'a';
        idx = 0;
        syncDots();
        schedule();
    }

    /* =========================================================
     *  ABOUT — user-controlled slides (read at your own pace)
     * ========================================================= */
    let _aboutTimer = null;
    function startAboutRotation(slides, intervalMs) {
        if (_aboutTimer) { clearInterval(_aboutTimer); _aboutTimer = null; }
        const section  = $('#about');
        const content  = $('[data-about-content]');
        const imgWrap  = $('[data-about-img-wrap]');
        const img      = $('[data-content="about.image"]');
        const paraEl   = $('[data-about-paragraphs]');
        const dotsHost = $('[data-about-dots]');
        const controls = $('[data-about-controls]');
        const indexEl  = $('[data-about-index]');
        const prevBtn  = $('.about-prev');
        const nextBtn  = $('.about-next');
        if (!img || !paraEl) return;

        let idx = 0;
        let userPinned = false;
        let resumeTimer = null;

        function paintParagraphs(arr) {
            if (!Array.isArray(arr)) arr = [];
            paraEl.innerHTML = arr.map(p => `<p>${esc(p)}</p>`).join('');
        }
        function buildDots() {
            if (!dotsHost) return;
            dotsHost.innerHTML = slides.map((_, i) =>
                `<button type="button" class="about-dot" data-about-go="${i}" role="tab" aria-label="Story ${i + 1}" aria-selected="${i === 0 ? 'true' : 'false'}"></button>`
            ).join('');
        }
        function syncChrome() {
            const multi = slides.length > 1;
            if (controls) controls.hidden = !multi;
            if (dotsHost) dotsHost.style.display = multi ? '' : 'none';
            if (prevBtn) prevBtn.hidden = !multi;
            if (nextBtn) nextBtn.hidden = !multi;
            if (dotsHost) {
                $$('.about-dot', dotsHost).forEach((b, i) => {
                    const on = i === idx;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            }
            if (indexEl && multi) indexEl.textContent = `${idx + 1} / ${slides.length}`;
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
            syncChrome();
        }
        function stop() {
            if (_aboutTimer) { clearInterval(_aboutTimer); _aboutTimer = null; }
            if (resumeTimer) { clearTimeout(resumeTimer); resumeTimer = null; }
        }
        function schedule() {
            stop();
            if (slides.length <= 1 || userPinned) return;
            _aboutTimer = setInterval(() => show(idx + 1, true), intervalMs);
        }
        function pinAndShow(i) {
            userPinned = true;
            stop();
            show(i, true);
        }
        function pauseForReading() {
            stop();
        }
        function resumeAfterReading(delay = (window.TATTU_TIMING || {}).about || 3600) {
            if (userPinned) return;
            if (resumeTimer) clearTimeout(resumeTimer);
            resumeTimer = setTimeout(() => {
                resumeTimer = null;
                schedule();
            }, delay);
        }

        if (dotsHost) {
            dotsHost.addEventListener('click', e => {
                const b = e.target.closest('[data-about-go]');
                if (!b) return;
                pinAndShow(Number(b.dataset.aboutGo));
            });
        }
        if (prevBtn) prevBtn.addEventListener('click', () => pinAndShow(idx - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => pinAndShow(idx + 1));

        const hasFineHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (hasFineHover) {
            const readingHost = content || section;
            if (readingHost) {
                readingHost.addEventListener('mouseenter', pauseForReading);
                readingHost.addEventListener('mouseleave', () => resumeAfterReading());
            }
            if (imgWrap) {
                imgWrap.addEventListener('mouseenter', pauseForReading);
                imgWrap.addEventListener('mouseleave', () => resumeAfterReading());
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) stop();
            else if (!userPinned) schedule();
        });

        if (section && 'IntersectionObserver' in window) {
            new IntersectionObserver(entries => {
                entries.forEach(en => {
                    if (en.isIntersecting) {
                        if (!userPinned) schedule();
                    } else {
                        stop();
                    }
                });
            }, { threshold: 0.15 }).observe(section);
        }

        buildDots();
        if (slides[0]) {
            if (slides[0].image) img.src = slides[0].image;
            paintParagraphs(slides[0].paragraphs);
        }
        idx = 0;
        syncChrome();
        schedule();
    }

    function renderSkeletons() {
        const progHost = $('[data-content="programs"]');
        if (progHost && !progHost.children.length) {
            progHost.innerHTML = Array.from({ length: 3 }, () => `
                <article class="program-card program-skeleton" aria-hidden="true">
                    <div class="skeleton-block skeleton-img"></div>
                    <div class="program-content">
                        <div class="skeleton-block skeleton-icon"></div>
                        <div class="skeleton-block skeleton-title"></div>
                        <div class="skeleton-block skeleton-text"></div>
                    </div>
                </article>
            `).join('');
        }
        const impactHost = $('[data-content="impactStories"]');
        if (impactHost && !impactHost.children.length) {
            impactHost.innerHTML = Array.from({ length: 3 }, () => `
                <article class="impact-card impact-skeleton" aria-hidden="true">
                    <div class="skeleton-block skeleton-img"></div>
                    <div class="impact-card-body">
                        <div class="skeleton-block skeleton-title"></div>
                        <div class="skeleton-block skeleton-text"></div>
                    </div>
                </article>
            `).join('');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderSkeletons();
        loadContent();
    });
})();
