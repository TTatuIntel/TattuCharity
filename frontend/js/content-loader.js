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

        const hero = c.hero || {};
        setText('[data-content="hero.title"]',        hero.title);
        setText('[data-content="hero.subtitle"]',     hero.subtitle);
        setText('[data-content="hero.primaryCta"]',   hero.primaryCtaText);
        setText('[data-content="hero.secondaryCta"]', hero.secondaryCtaText);
        const heroSection = $('#home');
        if (heroSection && hero.image) {
            heroSection.style.backgroundImage =
                `linear-gradient(rgba(0,0,0,.6), rgba(0,0,0,.6)), url("${hero.image}")`;
        }

        const about = c.about || {};
        setText('[data-content="about.heading"]', about.heading);
        const aboutPara = $('[data-content="about.paragraphs"]');
        if (aboutPara && Array.isArray(about.paragraphs)) {
            aboutPara.innerHTML = about.paragraphs.map(p => `<p>${esc(p)}</p>`).join('');
        }
        setAttr('[data-content="about.image"]', 'src', about.image);

        const statsHost = $('[data-content="stats"]');
        if (statsHost && Array.isArray(c.stats)) {
            statsHost.innerHTML = c.stats.map(s => `
                <div class="stat-item">
                    <div class="stat-number"><i class="fas ${esc(s.icon || 'fa-check')}"></i>${esc(s.number)}</div>
                    <div class="stat-text">${esc(s.label)}</div>
                </div>
            `).join('');
        }

        const progHost = $('[data-content="programs"]');
        if (progHost && Array.isArray(c.programs)) {
            progHost.innerHTML = c.programs.map(p => `
                <article class="program-card">
                    ${p.image ? `<div class="program-img"><img src="${esc(p.image)}" alt="${esc(p.title)}" loading="lazy"></div>` : ''}
                    <div class="program-content">
                        <div class="program-icon"><i class="fas ${esc(p.icon || 'fa-hand-holding-heart')}"></i></div>
                        <h3 class="program-title">${esc(p.title)}</h3>
                        <p class="program-desc">${esc(p.description)}</p>
                        ${p.stats ? `<p class="program-stats">${esc(p.stats)}</p>` : ''}
                    </div>
                </article>
            `).join('');
        }

        const impactHost = $('[data-content="impactStories"]');
        if (impactHost && Array.isArray(c.impactStories)) {
            impactHost.innerHTML = c.impactStories.map((s, i) => `
                <div class="impact-slide ${i === 0 ? 'active' : ''}">
                    <div class="impact-story">
                        <div class="impact-img">
                            <img src="${esc(s.image)}" alt="${esc(s.title)}" loading="lazy">
                        </div>
                        <div class="impact-text">
                            <h3>${esc(s.title)}</h3>
                            <p>${esc(s.text)}</p>
                            ${s.quote ? `<div class="testimonial">"${esc(s.quote)}"${s.quoteAuthor ? ` - <strong>${esc(s.quoteAuthor)}</strong>` : ''}</div>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            const dots = $('[data-content="impactDots"]');
            if (dots) {
                dots.innerHTML = c.impactStories.map((_, i) =>
                    `<button type="button" data-slide="${i}" class="${i === 0 ? 'active' : ''}" aria-label="Story ${i+1}"></button>`
                ).join('');
            }
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

    document.addEventListener('DOMContentLoaded', loadContent);
})();
