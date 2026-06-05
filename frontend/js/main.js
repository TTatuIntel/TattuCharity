/* Tattu Care - main interactions (compact dropdown, scroll-reveal, bank reveal) */
(function () {
    'use strict';

    const API = (window.TATTU_API || '/backend/api/');
    const T = window.TATTU_TIMING || {};
    const DYNAMIC_MS = T.programs || window.TATTU_DYNAMIC_MS || 3600;
    const $   = (s, c = document) => c.querySelector(s);
    const $$  = (s, c = document) => Array.from(c.querySelectorAll(s));

    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    function isPageReload() {
        const nav = performance.getEntriesByType('navigation')[0];
        return nav && nav.type === 'reload';
    }

    /* ---------------- Toast ---------------- */
    function ensureToastContainer() {
        let host = $('.toast-container');
        if (!host) {
            host = document.createElement('div');
            host.className = 'toast-container';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        return host;
    }
    function toast(message, type = 'success', timeout = 4200) {
        const host = ensureToastContainer();
        const el = document.createElement('div');
        el.className = 'toast' + (type === 'success' ? '' : ' ' + type);
        el.textContent = message;
        host.appendChild(el);
        setTimeout(() => {
            el.style.transition = 'opacity .3s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 300);
        }, timeout);
    }
    window.toast = toast;

    /* ---------------- Visitor interest (session) for dynamic hero ---------------- */
    function trackUserInterest(sectionId) {
        const key = String(sectionId || '').replace(/^#/, '');
        if (!key || key === 'home') return;
        try {
            const store = JSON.parse(sessionStorage.getItem('tattu_interests') || '{}') || {};
            store[key] = (store[key] || 0) + 1;
            sessionStorage.setItem('tattu_interests', JSON.stringify(store));
            document.dispatchEvent(new CustomEvent('tattu:interest', { detail: { section: key } }));
        } catch (_) { /* ignore */ }
    }

    /* ---------------- Sticky header / back-to-top / floating donate ---------------- */
    const header     = $('#header');
    const fab        = $('#floatingDonate');
    const backToTop  = $('.back-to-top');
    const bottomNav  = $('#mobileBottomNav');
    const themeBtn   = $('.theme-toggle');
    function onScroll() {
        const y = window.scrollY;
        const hero = $('#home');
        const heroThreshold = hero ? Math.min(hero.offsetHeight * 0.55, 120) : 60;
        const scrolled = y > heroThreshold;
        if (header)    header.classList.toggle('header-scrolled', scrolled);
        if (themeBtn)  themeBtn.classList.toggle('is-scrolled', scrolled);
        if (backToTop) backToTop.classList.toggle('visible', y > 300);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---------------- Mobile menu ---------------- */
    const mobileMenu      = $('.mobile-menu');
    const mobileMenuBtn   = $('.mobile-menu-btn');
    const mobileMenuClose = $('.mobile-menu-close');
    const overlay         = $('.overlay');
    function setMenuOpen(open) {
        if (!mobileMenu) return;
        mobileMenu.classList.toggle('active', open);
        if (overlay) overlay.classList.toggle('active', open);
        document.body.style.overflow = open ? 'hidden' : '';
        if (mobileMenuBtn) mobileMenuBtn.setAttribute('aria-expanded', String(open));
    }
    if (mobileMenuBtn)   mobileMenuBtn.addEventListener('click', () => setMenuOpen(true));
    if (mobileMenuClose) mobileMenuClose.addEventListener('click', () => setMenuOpen(false));
    if (overlay)         overlay.addEventListener('click', () => setMenuOpen(false));
    document.addEventListener('keyup', e => { if (e.key === 'Escape') setMenuOpen(false); });

    const mobileNavMore = $('#mobileNavMore');
    if (mobileNavMore) mobileNavMore.addEventListener('click', () => setMenuOpen(true));

    /* ---------------- Page-like section navigation ---------------- */
    const navProgress = $('#navProgress');
    const PAGE_TITLES = {
        home: 'Home',
        events: 'Events',
        programs: 'Programs',
        impact: 'Impact',
        team: 'Team',
        partners: 'Partners',
        about: 'About',
        donation: 'Donate',
        contact: 'Contact',
    };
    let navLock = false;
    let currentSection = 'home';
    const IDLE_RESET_MS = Number(window.TATTU_IDLE_MS) || 30000;
    let _idleTimer = null;

    function orgSiteName() {
        return (window.__SITE_CONTENT__?.organization?.name) || 'Tattu Care';
    }

    function setPageTitle(sectionId) {
        const label = PAGE_TITLES[sectionId] || (sectionId.charAt(0).toUpperCase() + sectionId.slice(1));
        document.title = `${label} — ${orgSiteName()}`;
    }

    function clearPageActive() {
        $$('main > section[id], main > .trust-bar').forEach(el => el.classList.remove('is-page-active'));
    }

    function finishNavTransition(target, sectionId, push) {
        const main = $('#main');
        clearPageActive();
        if (target) target.classList.add('is-page-active');
        currentSection = sectionId;
        if (main) main.style.opacity = '';
        document.body.classList.remove('is-navigating');
        document.body.classList.add('is-navigating-done');
        setPageTitle(sectionId);
        if (push) history.pushState({ section: sectionId }, '', '#' + sectionId);
        setTimeout(() => document.body.classList.remove('is-navigating-done'), 400);
        navLock = false;
        syncPillActive(sectionId);
    }

    function syncPillActive(sectionId) {
        const pills = $$('#mobileBottomNav a[data-pill]');
        if (!pills.length) return;
        pills.forEach(p => p.classList.toggle('active', p.getAttribute('href') === '#' + sectionId));
    }

    function resetToHome({ clearHash = true } = {}) {
        navLock = false;
        window.scrollTo({ top: 0, behavior: 'auto' });
        clearPageActive();
        const home = $('#home');
        if (home) home.classList.add('is-page-active');
        currentSection = 'home';
        setPageTitle('home');
        syncPillActive('home');
        if (clearHash && location.hash) {
            const clean = location.pathname + location.search;
            history.replaceState({ section: 'home' }, '', clean);
        }
    }

    function navigateToHash(hash, { push = true, instant = false } = {}) {
        if (navLock) return;
        const id = hash.startsWith('#') ? hash : '#' + hash;
        if (id === '#' || id.length < 2) return;
        const target = document.querySelector(id);
        if (!target) return;

        navLock = true;
        setMenuOpen(false);
        const sectionId = id.slice(1);
        trackUserInterest(sectionId);

        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const headerH = header ? header.offsetHeight : 0;
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        const offset = headerH + (isMobile ? 4 : 1);
        const top = Math.max(0, target.getBoundingClientRect().top + window.pageYOffset - offset);

        if (prefersReduced || instant) {
            window.scrollTo({ top, behavior: 'auto' });
            finishNavTransition(target, sectionId, push);
            return;
        }

        document.body.classList.add('is-navigating');
        if (navProgress) navProgress.style.width = '';

        setTimeout(() => {
            window.scrollTo({ top, behavior: 'smooth' });
            let done = false;
            const complete = () => {
                if (done) return;
                done = true;
                finishNavTransition(target, sectionId, push);
            };
            const fallback = setTimeout(complete, 720);
            if ('onscrollend' in window) {
                window.addEventListener('scrollend', () => {
                    clearTimeout(fallback);
                    complete();
                }, { once: true });
            }
        }, 140);
    }

    document.addEventListener('click', e => {
        const a = e.target.closest('a[href^="#"]');
        if (!a) return;
        const id = a.getAttribute('href');
        if (id === '#' || id.length < 2) return;
        if (!document.querySelector(id)) return;
        e.preventDefault();
        navigateToHash(id);
    });

    window.addEventListener('popstate', () => {
        const hash = location.hash;
        if (hash && hash.length > 1 && document.querySelector(hash)) {
            navigateToHash(hash, { push: false, instant: false });
        } else {
            resetToHome({ clearHash: false });
        }
    });

    resetToHome();

    document.addEventListener('content:ready', () => {
        if (isPageReload()) {
            resetToHome();
        } else {
            const hash = location.hash;
            if (hash && hash.length > 1 && document.querySelector(hash)) {
                setTimeout(() => navigateToHash(hash, { push: false, instant: true }), 50);
            } else {
                resetToHome();
            }
        }
        resetIdleTimer();
    });

    function resetIdleTimer() {
        if (_idleTimer) {
            clearTimeout(_idleTimer);
            _idleTimer = null;
        }
        if (document.hidden) return;
        _idleTimer = setTimeout(() => {
            if (currentSection && currentSection !== 'home' && !navLock && !isDropdownOpen()) {
                navigateToHash('#home', { push: false, instant: false });
            }
        }, IDLE_RESET_MS);
    }

    function markUserActivity() {
        resetIdleTimer();
    }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'wheel', 'scroll'].forEach(evt => {
        document.addEventListener(evt, markUserActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) resetIdleTimer();
    });

    /* ---------------- Smooth scroll ---------------- */
    /* (hash links handled above with page transitions) */

    /* ---------------- Theme toggle ---------------- */
    const themeToggle = $('.theme-toggle');
    const root        = document.documentElement;
    if (localStorage.getItem('tattu-theme') === 'dark') root.dataset.theme = 'dark';
    function syncThemeIcon() {
        if (!themeToggle) return;
        const i = themeToggle.querySelector('i');
        if (!i) return;
        i.className = root.dataset.theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    syncThemeIcon();
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            root.dataset.theme = root.dataset.theme === 'dark' ? '' : 'dark';
            localStorage.setItem('tattu-theme', root.dataset.theme || 'light');
            syncThemeIcon();
        });
    }

    /* ---------------- Back to top ---------------- */
    if (backToTop) backToTop.addEventListener('click', () =>
        window.scrollTo({ top: 0, behavior: 'smooth' }));

    /* =========================================================
     *  Donation dropdown
     * ========================================================= */
    const dropdown = $('#donationDropdown');

    function isDropdownOpen() { return dropdown && dropdown.classList.contains('active'); }
    function openDropdown(preset) {
        if (!dropdown) return;
        dropdown.classList.add('active');
        if (fab) fab.classList.add('dropdown-open');
        if (preset != null) {
            const amount = dropdown.querySelector('.pay-panel.active input[name="amount"]');
            if (amount) amount.value = preset;
        }
        const first = dropdown.querySelector('.pay-panel.active input');
        if (first) setTimeout(() => first.focus(), 220);
    }
    function closeDropdown() {
        if (!dropdown) return;
        dropdown.classList.remove('active');
        if (fab) fab.classList.remove('dropdown-open');
    }
    function toggleDropdown(preset) {
        isDropdownOpen() ? closeDropdown() : openDropdown(preset);
    }
    window.openDonation  = openDropdown;
    window.closeDonation = closeDropdown;

    document.addEventListener('keyup', e => { if (e.key === 'Escape' && isDropdownOpen()) closeDropdown(); });

    // Close on outside click (anything not inside the dropdown or the FAB)
    document.addEventListener('click', e => {
        if (!isDropdownOpen()) return;
        if (e.target.closest('#donationDropdown')) return;
        if (e.target.closest('.donate-trigger, #floatingDonate, .amount-btn')) return;
        closeDropdown();
    });

    /* Triggers + tabs + amount buttons + close-X + bank actions */
    document.addEventListener('click', e => {
        const closeBtn = e.target.closest('#donationDropdown .close-modal');
        if (closeBtn) { closeDropdown(); return; }

        const trigger = e.target.closest('.donate-trigger, [data-action="donate"]');
        if (trigger) { e.preventDefault(); toggleDropdown(); return; }

        const amt = e.target.closest('.amount-btn');
        if (amt) {
            e.preventDefault();
            $$('.amount-btn').forEach(b => b.classList.remove('selected'));
            amt.classList.add('selected');
            const custom = $('#custom-amount');
            if (custom) custom.value = '';
            openDropdown(amt.getAttribute('data-amount'));
            return;
        }

        // Pay-method tab
        const tab = e.target.closest('.pay-tab');
        if (tab && dropdown) {
            const key = tab.getAttribute('data-pay-tab');
            $$('.pay-tab', dropdown).forEach(t => t.classList.toggle('active', t === tab));
            $$('.pay-panel', dropdown).forEach(p =>
                p.classList.toggle('active', p.getAttribute('data-pay-panel') === key));
            return;
        }

        // "Contact us" link inside dropdown
        if (e.target.matches('[data-close-dropdown]')) {
            setTimeout(closeDropdown, 60);
        }

        // Bank: reveal account number
        const reveal = e.target.closest('[data-reveal]');
        if (reveal && dropdown) {
            const key = reveal.getAttribute('data-reveal');
            const el  = dropdown.querySelector(`[data-bank-secret="${key}"]`);
            if (el && el.dataset.full) {
                if (el.classList.contains('revealed')) {
                    el.classList.remove('revealed');
                    el.textContent = el.dataset.masked;
                    reveal.querySelector('i').className = 'fas fa-eye';
                } else {
                    el.textContent = el.dataset.full;
                    el.classList.add('revealed');
                    reveal.querySelector('i').className = 'fas fa-eye-slash';
                }
            }
            return;
        }

        // Bank: copy account number (without revealing)
        const copy = e.target.closest('[data-copy]');
        if (copy && dropdown) {
            const key = copy.getAttribute('data-copy');
            const el  = dropdown.querySelector(`[data-bank-secret="${key}"]`);
            const value = (el && el.dataset.full) || '';
            if (value && navigator.clipboard) {
                navigator.clipboard.writeText(value).then(() => {
                    copy.classList.add('copied');
                    const icon = copy.querySelector('i');
                    const prev = icon.className;
                    icon.className = 'fas fa-check';
                    toast('Account number copied');
                    setTimeout(() => {
                        copy.classList.remove('copied');
                        icon.className = prev;
                    }, 1500);
                }).catch(() => toast('Could not copy', 'error'));
            }
        }
    });

    document.addEventListener('input', e => {
        if (e.target && e.target.id === 'custom-amount') {
            $$('.amount-btn').forEach(b => b.classList.remove('selected'));
        }
    });

    /* Submit handler shared by all 3 payment forms */
    async function submitPayment(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const method    = form.getAttribute('data-method') || 'momo';
        const fd        = new FormData(form);
        const payload = {
            method,
            amount:    Number(fd.get('amount') || 0),
            currency:  FX_STATE.selected || 'USD',
            name:      String(fd.get('name')      || '').trim(),
            phone:     String(fd.get('phone')     || '').replace(/\s+/g, ''),
            email:     String(fd.get('email')     || '').trim(),
            reference: String(fd.get('reference') || '').trim(),
        };

        if (payload.amount < 1) { toast('Please enter an amount.', 'error'); return; }
        if ((method === 'momo' || method === 'airtel') && !/^256\d{9}$/.test(payload.phone)) {
            toast('Phone must start with 256 + 9 digits, e.g. 256770000000', 'error');
            return;
        }
        if (method === 'bank' && !payload.email) {
            toast('Please provide an email so we can confirm.', 'error');
            return;
        }

        const orig = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        try {
            const res  = await fetch(API + 'donate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const body = await res.json().catch(() => ({}));
            if (res.ok && body.success) {
                toast(body.message || 'Thank you for your donation!');
                form.reset();
                closeDropdown();
            } else {
                toast(body.message || 'Could not process the donation.', 'error');
            }
        } catch (err) {
            toast('Network error. Please check your connection.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = orig;
        }
    }
    document.addEventListener('submit', e => {
        const form = e.target.closest('.pay-form');
        if (!form) return;
        e.preventDefault();
        submitPayment(form);
    });

    /* ---------- Bank details: render with masked account number ---------- */
    function maskNumber(n) {
        const s = String(n || '').replace(/\s+/g, '');
        if (s.length <= 4) return s;
        return '•'.repeat(Math.max(4, s.length - 4)) + ' ' + s.slice(-4);
    }
    document.addEventListener('content:ready', e => {
        const data = e.detail;
        if (!data || !data.donation || !data.donation.bank) return;
        const bank = data.donation.bank;
        $$('[data-bank]').forEach(el => {
            const key = el.getAttribute('data-bank');
            el.textContent = bank[key] || '-';
        });
        const secret = $('[data-bank-secret="accountNumber"]');
        if (secret) {
            const full = bank.accountNumber || '';
            const masked = maskNumber(full);
            secret.dataset.full   = full;
            secret.dataset.masked = masked;
            secret.textContent    = masked;
            secret.classList.remove('revealed');
        }
    });

    /* =========================================================
     *  Other forms (contact, newsletter)
     * ========================================================= */
    const contactForm = $('#contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async e => {
            e.preventDefault();
            const fb  = contactForm.querySelector('.form-feedback');
            const btn = contactForm.querySelector('button[type="submit"]');
            const data = {
                name:    contactForm.name.value.trim(),
                email:   contactForm.email.value.trim(),
                message: contactForm.message.value.trim(),
            };
            if (!data.name || !data.email || !data.message) {
                if (fb) { fb.textContent = 'Please fill in all fields.'; fb.className = 'form-feedback error'; }
                return;
            }
            btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Sending…';
            try {
                const res  = await fetch(API + 'contact.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });
                const body = await res.json().catch(() => ({}));
                if (res.ok && body.success) {
                    if (fb) { fb.textContent = body.message; fb.className = 'form-feedback success'; }
                    contactForm.reset();
                } else {
                    if (fb) { fb.textContent = body.message || 'Could not send message.'; fb.className = 'form-feedback error'; }
                }
            } catch (err) {
                if (fb) { fb.textContent = 'Network error.'; fb.className = 'form-feedback error'; }
            } finally {
                btn.disabled = false; btn.textContent = orig;
            }
        });
    }

    const newsForm = $('#newsletterForm');
    if (newsForm) {
        newsForm.addEventListener('submit', async e => {
            e.preventDefault();
            const btn  = newsForm.querySelector('button[type="submit"]');
            const data = { email: newsForm.email.value.trim() };
            if (!data.email) { toast('Please enter your email.', 'error'); return; }
            btn.disabled = true; const orig = btn.textContent; btn.textContent = '…';
            try {
                const res  = await fetch(API + 'newsletter.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });
                const body = await res.json().catch(() => ({}));
                if (res.ok && body.success) {
                    toast(body.message || 'Subscribed!');
                    newsForm.reset();
                } else {
                    toast(body.message || 'Could not subscribe.', 'error');
                }
            } catch (err) {
                toast('Network error.', 'error');
            } finally {
                btn.disabled = false; btn.textContent = orig;
            }
        });
    }

    /* =========================================================
     *  Slider (Impact stories)
     * ========================================================= */
    function buildSlider(rootSel, slideSel, dotsSel, autoMs = DYNAMIC_MS) {
        const root = $(rootSel);
        if (!root) return;
        let idx = 0, timer = null;
        const slides = () => $$(slideSel, root);
        const dots   = () => dotsSel ? $$(dotsSel + ' button') : [];
        function show(i) {
            const all = slides();
            if (!all.length) return;
            idx = (i + all.length) % all.length;
            all.forEach((s, k) => s.classList.toggle('active', k === idx));
            dots().forEach((d, k) => d.classList.toggle('active', k === idx));
        }
        function next() { show(idx + 1); }
        function prev() { show(idx - 1); }
        function start() { stop(); timer = setInterval(next, autoMs); }
        function stop()  { if (timer) { clearInterval(timer); timer = null; } }

        const prevBtn = $(rootSel + ' [data-slider="prev"]');
        const nextBtn = $(rootSel + ' [data-slider="next"]');
        if (prevBtn) prevBtn.addEventListener('click', () => { prev(); start(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { next(); start(); });
        document.addEventListener('click', e => {
            const dot = e.target.closest(`${dotsSel} button`);
            if (dot) { show(Number(dot.dataset.slide || 0)); start(); }
        });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        start();
    }

    /* =========================================================
     *  Scroll reveal (IntersectionObserver)
     * ========================================================= */
    function initRevealObserver() {
        if (!('IntersectionObserver' in window)) {
            $$('.reveal, .reveal-stagger, .reveal-fade').forEach(el => el.classList.add('in-view'));
            return;
        }
        const io = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        $$('.reveal, .reveal-stagger, .reveal-fade').forEach(el => io.observe(el));
    }

    /* =========================================================
     *  Programs — auto-rotating spotlight carousel
     * ========================================================= */
    let _progTimer = null;
    function initProgramsSpotlight() {
        const grid  = $('.programs-grid');
        const dots  = $('[data-programs-dots]');
        if (!grid) return;
        const cards = $$('.program-card', grid);
        if (cards.length < 2) return;

        let idx = 0;
        const dotEls = dots ? $$('button', dots) : [];

        function spotlight(i) {
            idx = (i + cards.length) % cards.length;
            cards.forEach((c, k) => c.classList.toggle('spotlight', k === idx));
            dotEls.forEach((d, k) => d.classList.toggle('active', k === idx));
            // Smoothly bring the spotlighted card into view
            cards[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
        function next() { spotlight(idx + 1); }
        function start() { stop(); _progTimer = setInterval(next, DYNAMIC_MS); }
        function stop()  { if (_progTimer) { clearInterval(_progTimer); _progTimer = null; } }

        spotlight(0);
        start();

        // Hover/focus pauses; click selects
        const wrap = $('.programs-scroller');
        if (wrap) {
            wrap.addEventListener('mouseenter', stop);
            wrap.addEventListener('mouseleave', start);
            wrap.addEventListener('focusin',  stop);
            wrap.addEventListener('focusout', start);
        }
        grid.addEventListener('click', e => {
            const card = e.target.closest('.program-card');
            if (!card) return;
            const i = cards.indexOf(card);
            if (i >= 0) { spotlight(i); start(); }
        });
        if (dots) {
            dots.addEventListener('click', e => {
                const b = e.target.closest('button[data-program-go]');
                if (!b) return;
                spotlight(Number(b.getAttribute('data-program-go') || 0));
                start();
            });
        }
        // Pause when section not visible (saves CPU)
        if ('IntersectionObserver' in window) {
            const sec = $('#programs');
            if (sec) {
                new IntersectionObserver(entries => {
                    entries.forEach(en => en.isIntersecting ? start() : stop());
                }, { threshold: 0.1 }).observe(sec);
            }
        }
    }

    /* =========================================================
     *  Shared interaction pause/resume helper
     * ========================================================= */
    function createInteractionController({ pause, resume, delayMs = DYNAMIC_MS }) {
        let resumeTimer = null;
        return {
            pause() {
                if (resumeTimer) { clearTimeout(resumeTimer); resumeTimer = null; }
                pause();
            },
            resumeAfter(delay = delayMs) {
                if (resumeTimer) clearTimeout(resumeTimer);
                resumeTimer = setTimeout(() => { resumeTimer = null; resume(); }, delay);
            },
            resumeNow() {
                if (resumeTimer) { clearTimeout(resumeTimer); resumeTimer = null; }
                resume();
            },
        };
    }

    function debounce(fn, ms = 200) {
        let t = null;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }

    /* =========================================================
     *  Shared horizontal content rail (Impact, Events, Team, Partners)
     *  Step-and-hold: center each card, pause to read, then slide to next.
     * ========================================================= */
    function initContentRail(cfg) {
        const wrap  = $(cfg.wrap);
        const track = $(cfg.track, wrap || document);
        if (!wrap || !track) return null;

        const originalCount = Number(track.dataset.originalCount) || 0;
        if (!originalCount) return null;

        const cardSelector = cfg.cardSelector;
        const idxAttr = cfg.idxAttr;
        const dotAttr = cfg.dotAttr;
        const direction = cfg.direction === -1 ? -1 : 1;
        const cycleMs = cfg.cardPauseMs ?? T.story ?? 3600;
        const scrollMs = cfg.scrollMs ?? T.scroll ?? 520;
        const holdMs = Math.max(400, cycleMs - scrollMs);
        const resumeDelay = cfg.resumeDelay ?? T.resume ?? 3200;

        let oneSetHTML = '';
        let setWidth   = 0;
        let offset     = 0;
        let currentIdx = 0;
        let interactionPaused = false;
        let stepTimer  = null;
        let isAnimating = false;
        let inView = true;

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let autoEnabled = !reducedMotion && originalCount > 1;

        wrap.style.setProperty('--rail-hold-ms', `${cycleMs}ms`);

        function getCards() { return $$(cardSelector, track); }

        function getGap() {
            return parseFloat(getComputedStyle(track).gap) || 10;
        }

        function measureSetWidth() {
            const items = getCards();
            if (items.length < originalCount) return 0;
            const last = items[originalCount - 1];
            return last.offsetLeft + last.offsetWidth + getGap();
        }

        function cloneFill() {
            const items = getCards();
            if (!items.length) return;
            if (!oneSetHTML) {
                oneSetHTML = items.slice(0, originalCount).map(c => c.outerHTML).join('');
            }
            track.innerHTML = oneSetHTML;
            setWidth = measureSetWidth();
            const minSets = cfg.minCloneSets ?? 2;
            while (getCards().length < originalCount * minSets) {
                track.insertAdjacentHTML('beforeend', oneSetHTML);
            }
            setWidth = measureSetWidth();
            normalizeOffset();
            applyTransform();
        }

        function normalizeOffset() {
            if (setWidth <= 0) return;
            while (offset >= setWidth) offset -= setWidth;
            while (offset < 0) offset += setWidth;
        }

        function applyTransform() {
            track.style.transform = `translate3d(${-offset}px, 0, 0)`;
        }

        function clearStepTimer() {
            if (stepTimer) { clearTimeout(stepTimer); stepTimer = null; }
        }

        function canAutoAdvance() {
            return autoEnabled && inView && !interactionPaused && !isAnimating && originalCount > 1;
        }

        function offsetToCenterCard(card) {
            return card.offsetLeft + card.offsetWidth / 2 - wrap.clientWidth / 2;
        }

        function findCardByIdx(idx) {
            const matches = getCards().filter(c => Number(c.getAttribute(idxAttr)) === idx);
            if (!matches.length) return null;
            const wrapCenter = wrap.getBoundingClientRect().left + wrap.clientWidth / 2;
            let best = matches[0];
            let bestDist = Infinity;
            matches.forEach(card => {
                const rect = card.getBoundingClientRect();
                const dist = Math.abs((rect.left + rect.width / 2) - wrapCenter);
                if (dist < bestDist) { bestDist = dist; best = card; }
            });
            return best;
        }

        function animateOffset(target, duration = scrollMs, callback) {
            const start = offset;
            let delta = target - start;
            if (setWidth > 0) {
                if (delta > setWidth / 2) delta -= setWidth;
                if (delta < -setWidth / 2) delta += setWidth;
            }
            const startTime = performance.now();
            function step(now) {
                const t = Math.min(1, (now - startTime) / duration);
                const ease = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                offset = start + delta * ease;
                normalizeOffset();
                applyTransform();
                if (t < 1) requestAnimationFrame(step);
                else {
                    updateDots();
                    if (callback) callback();
                }
            }
            requestAnimationFrame(step);
        }

        function centerCard(card, callback) {
            if (!card) return;
            isAnimating = true;
            animateOffset(offsetToCenterCard(card), scrollMs, () => {
                isAnimating = false;
                currentIdx = Number(card.getAttribute(idxAttr) || 0);
                updateDots();
                if (callback) callback();
            });
        }

        function updateDots() {
            const dotsEl = cfg.dotsSelector ? $(cfg.dotsSelector) : null;
            if (dotsEl) {
                $$('.carousel-dot', dotsEl).forEach(dot => {
                    const i = Number(dot.getAttribute(dotAttr));
                    const on = i === currentIdx;
                    dot.classList.toggle('is-active', on);
                    dot.setAttribute('aria-current', on ? 'true' : 'false');
                });
            }
            getCards().forEach(card => {
                const idx = Number(card.getAttribute(idxAttr) || 0);
                card.classList.toggle('is-rail-active', idx === currentIdx);
            });
        }

        function moveToIndex(idx, onDone) {
            currentIdx = ((idx % originalCount) + originalCount) % originalCount;
            const card = findCardByIdx(currentIdx);
            if (!card) return;
            isAnimating = true;
            animateOffset(offsetToCenterCard(card), scrollMs, () => {
                isAnimating = false;
                updateDots();
                if (onDone) onDone();
            });
        }

        function scheduleHold() {
            clearStepTimer();
            if (!canAutoAdvance()) return;
            stepTimer = setTimeout(advanceStep, holdMs);
        }

        function advanceStep() {
            if (!canAutoAdvance()) return;
            moveToIndex(currentIdx + direction, scheduleHold);
        }

        function goToIndex(idx) {
            ic.pause();
            moveToIndex(idx, () => ic.resumeAfter(resumeDelay));
        }

        function stepManual(stepDir) {
            ic.pause();
            moveToIndex(currentIdx + stepDir, () => ic.resumeAfter(resumeDelay));
        }

        function isNavigableCardClick(card, e) {
            if (!card) return false;
            if (card.matches('a[href]')) return true;
            const link = e.target.closest('a[href]');
            if (!link) return false;
            const href = link.getAttribute('href') || '';
            return href && href !== '#';
        }

        const ic = createInteractionController({
            pause: () => { interactionPaused = true; clearStepTimer(); },
            resume: () => { interactionPaused = false; scheduleHold(); },
            delayMs: resumeDelay,
        });

        cloneFill();
        const first = findCardByIdx(0);
        if (first) {
            offset = offsetToCenterCard(first);
            normalizeOffset();
            applyTransform();
        }
        currentIdx = 0;
        updateDots();
        scheduleHold();

        const controls = cfg.controlsSelector ? $(cfg.controlsSelector) : null;
        const dotsEl = cfg.dotsSelector ? $(cfg.dotsSelector) : null;
        const prevBtn = $(cfg.prevSelector || '.carousel-prev', wrap);
        const nextBtn = $(cfg.nextSelector || '.carousel-next', wrap);
        const autoBtn = cfg.autoSelector ? $(cfg.autoSelector) : null;
        const dotLabel = cfg.dotLabel || (i => `Item ${i + 1}`);

        if (originalCount < 2) {
            if (controls) controls.hidden = true;
            if (prevBtn) prevBtn.hidden = true;
            if (nextBtn) nextBtn.hidden = true;
            autoEnabled = false;
            clearStepTimer();
        } else if (dotsEl) {
            dotsEl.innerHTML = Array.from({ length: originalCount }, (_, i) =>
                `<button type="button" class="carousel-dot" ${dotAttr}="${i}" role="tab" aria-label="${dotLabel(i)}"></button>`
            ).join('');
            $$(`[${dotAttr}]`, dotsEl).forEach(btn => {
                btn.addEventListener('click', () => goToIndex(Number(btn.getAttribute(dotAttr))));
            });
            updateDots();
        }

        if (autoBtn) {
            if (originalCount < 2) autoBtn.hidden = true;
            autoBtn.addEventListener('click', () => {
                autoEnabled = !autoEnabled;
                autoBtn.setAttribute('aria-pressed', autoEnabled ? 'true' : 'false');
                autoBtn.title = autoEnabled ? 'Pause auto-scroll' : 'Resume auto-scroll';
                const icon = autoBtn.querySelector('i');
                if (icon) icon.className = autoEnabled ? 'fas fa-pause' : 'fas fa-play';
                if (autoEnabled) scheduleHold();
                else clearStepTimer();
            });
        }

        wrap.addEventListener('click', e => {
            if (e.target.closest('.carousel-btn, .carousel-dot')) return;
            const card = e.target.closest(cardSelector);
            if (!card || isNavigableCardClick(card, e)) return;
            if (typeof cfg.onCardClick === 'function') {
                cfg.onCardClick(card, { pause: () => ic.pause(), resumeAfter: ms => ic.resumeAfter(ms), centerCard });
                return;
            }
            ic.pause();
            centerCard(card, () => ic.resumeAfter(resumeDelay));
        });

        if (prevBtn) prevBtn.addEventListener('click', () => stepManual(-direction));
        if (nextBtn) nextBtn.addEventListener('click', () => stepManual(direction));

        let dragX = 0;
        let dragActive = false;
        wrap.addEventListener('pointerdown', e => {
            if (e.target.closest('.carousel-btn, .carousel-dot')) return;
            dragActive = true;
            dragX = e.clientX;
        });
        wrap.addEventListener('pointerup', e => {
            if (!dragActive) return;
            dragActive = false;
            const delta = e.clientX - dragX;
            if (Math.abs(delta) > 40) stepManual(delta > 0 ? -direction : direction);
        });
        wrap.addEventListener('pointercancel', () => { dragActive = false; });

        const onVisibility = () => {
            if (document.hidden) clearStepTimer();
            else if (inView) scheduleHold();
        };
        document.addEventListener('visibilitychange', onVisibility);

        if (cfg.sectionId && 'IntersectionObserver' in window) {
            const sec = $('#' + cfg.sectionId);
            if (sec) {
                new IntersectionObserver(entries => {
                    entries.forEach(en => {
                        inView = en.isIntersecting;
                        if (inView) scheduleHold();
                        else clearStepTimer();
                    });
                }, { threshold: cfg.ioThreshold ?? 0.1 }).observe(sec);
            }
        }

        const onResize = debounce(() => {
            cloneFill();
            const card = findCardByIdx(currentIdx);
            if (card) {
                offset = offsetToCenterCard(card);
                normalizeOffset();
                applyTransform();
            }
            updateDots();
            if (canAutoAdvance()) scheduleHold();
        }, 250);
        window.addEventListener('resize', onResize);
        window.addEventListener('orientationchange', onResize);

        return {
            pause: () => ic.pause(),
            resumeAfter: ms => ic.resumeAfter(ms),
            resumeNow: () => ic.resumeNow(),
            centerCard,
            goToIndex,
            destroy() {
                clearStepTimer();
                window.removeEventListener('resize', onResize);
                window.removeEventListener('orientationchange', onResize);
                document.removeEventListener('visibilitychange', onVisibility);
            },
        };
    }

    /* =========================================================
     *  Impact: seamless JS carousel + click-to-expand modal
     * ========================================================= */
    let _impactCarousel = null;

    function initImpactCarousel() {
        const rail = initContentRail({
            wrap: '.impact-track-wrap',
            track: '.impact-track',
            cardSelector: '.impact-card:not(.impact-skeleton)',
            idxAttr: 'data-impact-idx',
            dotAttr: 'data-impact-dot',
            dotsSelector: '[data-impact-dots]',
            controlsSelector: '[data-impact-controls]',
            autoSelector: '[data-impact-auto]',
            prevSelector: '.impact-prev, .carousel-prev',
            nextSelector: '.impact-next, .carousel-next',
            sectionId: 'impact',
            dotLabel: i => `Story ${i + 1}`,
            cardPauseMs: T.story,
            minCloneSets: 2,
            onCardClick(card, api) {
                api.pause();
                const idx = Number(card.getAttribute('data-impact-idx') || 0);
                const list = window.__IMPACT_STORIES__ || [];
                api.centerCard(card, () => openImpactExpand(list[idx]));
            },
        });
        if (!rail) return null;

        return {
            resumeAfterClose() { rail.resumeAfter(T.resume || 3200); },
            destroy() { rail.destroy(); },
        };
    }

    const impactExpand = $('#impactExpand');
    function openImpactExpand(story) {
        if (!impactExpand || !story) return;
        const img   = $('[data-expand-img]',   impactExpand);
        const title = $('[data-expand-title]', impactExpand);
        const text  = $('[data-expand-text]',  impactExpand);
        const quote = $('[data-expand-quote]', impactExpand);
        if (img)   img.style.backgroundImage = story.image ? `url("${story.image.replace(/"/g,'%22')}")` : '';
        if (title) title.textContent = story.title || '';
        if (text)  text.textContent  = story.text  || '';
        if (quote) {
            if (story.quote) {
                quote.style.display = '';
                quote.innerHTML = `"${String(story.quote).replace(/[<>]/g,'')}"` +
                    (story.quoteAuthor ? ` <strong>— ${String(story.quoteAuthor).replace(/[<>]/g,'')}</strong>` : '');
            } else {
                quote.style.display = 'none';
            }
        }
        impactExpand.classList.add('active');
        impactExpand.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeImpactExpand() {
        if (!impactExpand) return;
        impactExpand.classList.remove('active');
        impactExpand.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (_impactCarousel) _impactCarousel.resumeAfterClose();
    }
    document.addEventListener('click', e => {
        if (e.target.closest('#impactExpand .impact-expand-close')) { closeImpactExpand(); return; }
        if (e.target === impactExpand) closeImpactExpand();
    });
    document.addEventListener('keyup', e => {
        if (e.key === 'Escape' && impactExpand && impactExpand.classList.contains('active')) closeImpactExpand();
    });
    document.addEventListener('keydown', e => {
        // Enter/Space on focused card
        if ((e.key === 'Enter' || e.key === ' ') && document.activeElement?.classList.contains('impact-card')) {
            e.preventDefault();
            document.activeElement.click();
        }
    });

    /* =========================================================
     *  Multi-currency support (live FX + UGX charge preview)
     * ========================================================= */
    const FX_STATE = { rates: {}, charge: 'UGX', selected: 'USD' };

    const FRIENDLY_NAMES = {
        USD: 'US Dollar', EUR: 'Euro', GBP: 'British Pound', AUD: 'Australian Dollar',
        CAD: 'Canadian Dollar', NZD: 'NZ Dollar', CHF: 'Swiss Franc', JPY: 'Japanese Yen',
        CNY: 'Chinese Yuan', INR: 'Indian Rupee', ZAR: 'S. African Rand', NGN: 'Nigerian Naira',
        UGX: 'Ugandan Shilling', KES: 'Kenyan Shilling', TZS: 'Tanzanian Shilling',
        RWF: 'Rwandan Franc', BIF: 'Burundian Franc', GHS: 'Ghanaian Cedi',
        EGP: 'Egyptian Pound', AED: 'UAE Dirham', SAR: 'Saudi Riyal', XAF: 'CFA Franc',
        XOF: 'West African CFA',
    };

    function fxConvert(amount, from, to) {
        if (!amount || from === to) return Number(amount) || 0;
        const r = FX_STATE.rates;
        if (!r[from] || !r[to]) return amount;
        return (Number(amount) / r[from]) * r[to];
    }
    function fmtMoney(amount, currency) {
        const n = Number(amount) || 0;
        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency', currency, maximumFractionDigits: 2
            }).format(n);
        } catch (_) {
            return `${currency} ${n.toLocaleString()}`;
        }
    }
    function friendlyRound(v) {
        if (v < 1)     return Math.round(v * 100) / 100;
        if (v < 10)    return Math.round(v);
        if (v < 100)   return Math.round(v / 5)   * 5;
        if (v < 1000)  return Math.round(v / 10)  * 10;
        if (v < 10000) return Math.round(v / 100) * 100;
        return Math.round(v / 1000) * 1000;
    }

    async function loadFxRates() {
        try {
            const res  = await fetch(API + 'fx.php', { credentials: 'same-origin' });
            const body = await res.json();
            if (body && body.success && body.rates) {
                FX_STATE.rates  = body.rates;
                FX_STATE.charge = body.charge || 'UGX';
            }
        } catch (err) {
            console.warn('FX rates unavailable, using static fallback', err);
            // Minimal hard-coded fallback so the UI still works offline
            FX_STATE.rates = { USD:1, EUR:0.92, GBP:0.79, UGX:3700, KES:130, TZS:2500, RWF:1300,
                               AUD:1.5, CAD:1.36, NZD:1.65, CHF:0.88, JPY:150, INR:83, ZAR:18.5, NGN:1500 };
        }
    }

    function detectInitialCurrency(supported) {
        // 1) localStorage 2) navigator.language fallback 3) USD
        const saved = localStorage.getItem('tattu-currency');
        if (saved && supported.includes(saved)) return saved;
        // Map common locales to currency
        const localeToCurrency = {
            'en-US': 'USD', 'en-GB': 'GBP', 'en-AU': 'AUD', 'en-CA': 'CAD',
            'en-NZ': 'NZD', 'en-IN': 'INR', 'en-ZA': 'ZAR', 'en-NG': 'NGN',
            'en-KE': 'KES', 'en-UG': 'UGX', 'en-TZ': 'TZS', 'en-RW': 'RWF',
            'fr-FR': 'EUR', 'de-DE': 'EUR', 'es-ES': 'EUR', 'it-IT': 'EUR',
            'ja-JP': 'JPY', 'zh-CN': 'CNY',
        };
        const langs = (navigator.languages || [navigator.language || 'en-US']);
        for (const l of langs) {
            const c = localeToCurrency[l] || localeToCurrency[l.split('-')[0]];
            if (c && supported.includes(c)) return c;
        }
        return supported.includes('USD') ? 'USD' : supported[0];
    }

    function buildCurDropdown(host, supported, initial) {
        host.classList.add('cur-dd');
        host.setAttribute('data-open', 'false');
        host.innerHTML = `
            <button type="button" class="cur-dd-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span class="cur-code">${initial}</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <ul class="cur-dd-menu" role="listbox">
                ${supported.map(code => `
                    <li role="option" data-value="${code}" class="${code === initial ? 'selected' : ''}">
                        <span><strong>${code}</strong></span>
                        <small>${FRIENDLY_NAMES[code] || code}</small>
                    </li>`).join('')}
            </ul>`;
    }

    function setCurrencyEverywhere(code) {
        FX_STATE.selected = code;
        localStorage.setItem('tattu-currency', code);
        $$('[data-currency-picker]').forEach(host => {
            const trig = host.querySelector('.cur-dd-trigger .cur-code');
            if (trig) trig.textContent = code;
            $$('li', host).forEach(li => li.classList.toggle('selected', li.getAttribute('data-value') === code));
            host.setAttribute('data-open', 'false');
            const btn = host.querySelector('.cur-dd-trigger');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
        renderAmountButtons();
        refreshAllChargePreviews();
    }

    function populateCurrencyPickers() {
        const supported = (window.__DONATION_BASE__ && window.__DONATION_BASE__.supportedCurrencies)
                       || ['USD','EUR','GBP','UGX','KES'];
        const initial = detectInitialCurrency(supported);
        FX_STATE.selected = initial;
        $$('[data-currency-picker]').forEach(host => buildCurDropdown(host, supported, initial));
    }

    /* Toggle / select / outside-click for the custom dropdown (event delegation) */
    document.addEventListener('click', e => {
        const trig = e.target.closest('.cur-dd-trigger');
        if (trig) {
            const dd = trig.closest('.cur-dd');
            const wasOpen = dd.getAttribute('data-open') === 'true';
            // Close any others first
            $$('.cur-dd[data-open="true"]').forEach(d => {
                d.setAttribute('data-open', 'false');
                const b = d.querySelector('.cur-dd-trigger');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
            dd.setAttribute('data-open', wasOpen ? 'false' : 'true');
            trig.setAttribute('aria-expanded', wasOpen ? 'false' : 'true');
            return;
        }
        const opt = e.target.closest('.cur-dd-menu li');
        if (opt) {
            setCurrencyEverywhere(opt.getAttribute('data-value'));
            return;
        }
        // Click outside: close all
        if (!e.target.closest('.cur-dd')) {
            $$('.cur-dd[data-open="true"]').forEach(d => {
                d.setAttribute('data-open', 'false');
                const b = d.querySelector('.cur-dd-trigger');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        }
    });
    document.addEventListener('keyup', e => {
        if (e.key === 'Escape') {
            $$('.cur-dd[data-open="true"]').forEach(d => {
                d.setAttribute('data-open', 'false');
                const b = d.querySelector('.cur-dd-trigger');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        }
    });

    function renderAmountButtons() {
        const host = $('[data-content="donation.amounts"]');
        const base = window.__DONATION_BASE__;
        if (!host || !base) return;
        const selected = FX_STATE.selected;
        const baseCur  = base.currency || 'USD';
        const cur      = selected;
        // Convert each base amount to selected currency, rounded friendly
        const converted = base.amounts.map(a => friendlyRound(fxConvert(a, baseCur, cur)));
        host.innerHTML = converted.map(a =>
            `<button type="button" class="amount-btn" data-amount="${a}">${cur} ${a.toLocaleString()}</button>`
        ).join('') + `<input type="number" id="custom-amount" placeholder="Custom (${cur})" min="1" aria-label="Custom amount">`;

        // Update goal/raised display in selected currency
        const goalEl   = $('[data-content="donation.goal"]');
        const raisedEl = $('[data-content="donation.raised"]');
        if (goalEl)   goalEl.textContent   = `${cur} ${friendlyRound(fxConvert(base.goal,   baseCur, cur)).toLocaleString()}`;
        if (raisedEl) raisedEl.textContent = `${cur} ${friendlyRound(fxConvert(base.raised, baseCur, cur)).toLocaleString()}`;
    }

    function chargePreviewFor(amount) {
        if (!amount || !Number(amount)) return '';
        const sel    = FX_STATE.selected;
        const charge = FX_STATE.charge;
        if (sel === charge) return '';
        const ugx = fxConvert(amount, sel, charge);
        if (!ugx || isNaN(ugx)) return '';
        return `≈ <strong>${fmtMoney(ugx, charge)}</strong> will be charged via Mobile Money`;
    }
    function refreshAllChargePreviews() {
        $$('.pay-form').forEach(form => {
            const amt = form.querySelector('input[name="amount"]');
            const pre = form.querySelector('[data-charge-preview]');
            if (!pre) return;
            const html = chargePreviewFor(amt ? amt.value : '');
            pre.innerHTML = html;
            pre.classList.toggle('empty', !html);
        });
    }
    document.addEventListener('input', e => {
        if (e.target && e.target.matches('.pay-form input[name="amount"]')) {
            const form = e.target.closest('.pay-form');
            const pre  = form && form.querySelector('[data-charge-preview]');
            if (!pre) return;
            const html = chargePreviewFor(e.target.value);
            pre.innerHTML = html;
            pre.classList.toggle('empty', !html);
        }
    });

    /* =========================================================
     *  Programs - 3D coverflow rotation (enhanced)
     * ========================================================= */
    let _cfTimer = null;
    function initProgramsCoverflow() {
        const grid = $('.programs-grid');
        if (!grid) return;
        $$('.program-card-ghost', grid).forEach(g => g.remove());

        const cards = $$('.program-card:not(.program-skeleton)', grid);
        if (cards.length < 2) return;

        let active = 0;
        const N = cards.length;

        function maxSlot() {
            if (window.innerWidth <= 768) return 1;
            if (window.innerWidth <= 1024) return 2;
            return 3;
        }

        function addGhosts() {
            if ($$('.program-card-ghost', grid).length) return;
            const leftGhost  = cards[N - 1].cloneNode(true);
            const rightGhost = cards[0].cloneNode(true);
            leftGhost.classList.add('program-card-ghost');
            rightGhost.classList.add('program-card-ghost');
            leftGhost.setAttribute('data-ghost', 'left');
            rightGhost.setAttribute('data-ghost', 'right');
            leftGhost.setAttribute('tabindex', '-1');
            rightGhost.setAttribute('tabindex', '-1');
            leftGhost.setAttribute('data-cf', '-4');
            rightGhost.setAttribute('data-cf', '4');
            grid.appendChild(leftGhost);
            grid.appendChild(rightGhost);
        }

        function updateProgramDots() {
            const dotsEl = $('[data-programs-dots]');
            if (!dotsEl) return;
            $$('.carousel-dot', dotsEl).forEach(dot => {
                const i = Number(dot.getAttribute('data-program-dot'));
                const on = i === active;
                dot.classList.toggle('is-active', on);
                dot.setAttribute('aria-current', on ? 'true' : 'false');
            });
        }

        function position() {
            const slot = maxSlot();
            cards.forEach((c, i) => {
                let d = i - active;
                if (d >  N / 2) d -= N;
                if (d < -N / 2) d += N;
                if (Math.abs(d) <= slot) {
                    c.setAttribute('data-cf', String(d));
                } else {
                    c.setAttribute('data-cf', d > 0 ? String(slot + 1) : String(-(slot + 1)));
                }
            });
            updateProgramDots();
        }

        let autoEnabled = N >= 2;
        function next()  { active = (active + 1) % N; position(); }
        function prev()  { active = (active - 1 + N) % N; position(); }
        const cfMs = T.programs || DYNAMIC_MS;
        function start() { stop(); if (autoEnabled && N >= 2) _cfTimer = setInterval(next, cfMs); }
        function stop()  { if (_cfTimer) { clearInterval(_cfTimer); _cfTimer = null; } }

        const ic = createInteractionController({
            pause: stop,
            resume: start,
            delayMs: T.resume || 3200,
        });

        addGhosts();
        position();
        start();

        const wrap = $('.programs-scroller');
        if (wrap) {
            let dragX = 0;
            let dragActive = false;
            wrap.addEventListener('pointerdown', e => {
                if (e.target.closest('.carousel-btn')) return;
                dragActive = true;
                dragX = e.clientX;
            });
            wrap.addEventListener('pointerup', e => {
                if (!dragActive) return;
                dragActive = false;
                const delta = e.clientX - dragX;
                if (Math.abs(delta) > 40) (delta > 0 ? prev : next)();
            });
            wrap.addEventListener('pointercancel', () => { dragActive = false; });
        }

        const controls = $('[data-programs-controls]');
        const dotsEl = $('[data-programs-dots]');
        const prevBtn = $('.programs-prev', wrap) || $('.carousel-prev', wrap);
        const nextBtn = $('.programs-next', wrap) || $('.carousel-next', wrap);
        const autoBtn = $('[data-programs-auto]');

        if (N < 2) {
            if (controls) controls.hidden = true;
            if (prevBtn) prevBtn.hidden = true;
            if (nextBtn) nextBtn.hidden = true;
            autoEnabled = false;
            stop();
        } else {
            if (dotsEl) {
                dotsEl.innerHTML = cards.map((_, i) =>
                    `<button type="button" class="carousel-dot" data-program-dot="${i}" role="tab" aria-label="Program ${i + 1}"></button>`
                ).join('');
                $$('[data-program-dot]', dotsEl).forEach(btn => {
                    btn.addEventListener('click', () => {
                        ic.pause();
                        active = Number(btn.getAttribute('data-program-dot'));
                        position();
                        ic.resumeAfter(T.resume || 3200);
                    });
                });
            }
        }

        if (autoBtn) {
            if (N < 2) autoBtn.hidden = true;
            autoBtn.addEventListener('click', () => {
                autoEnabled = !autoEnabled;
                autoBtn.setAttribute('aria-pressed', autoEnabled ? 'true' : 'false');
                autoBtn.title = autoEnabled ? 'Pause auto-rotate' : 'Resume auto-rotate';
                const icon = autoBtn.querySelector('i');
                if (icon) icon.className = autoEnabled ? 'fas fa-pause' : 'fas fa-play';
                autoEnabled ? start() : stop();
            });
        }

        if (prevBtn) prevBtn.addEventListener('click', () => { prev(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { next(); });

        grid.addEventListener('click', e => {
            const card = e.target.closest('.program-card:not(.program-skeleton):not(.program-card-ghost)');
            if (!card) return;
            const i = cards.indexOf(card);
            if (i >= 0) {
                ic.pause();
                active = i;
                position();
                ic.resumeAfter(T.resume || 3200);
            }
        });

        let programsInView = true;
        if ('IntersectionObserver' in window) {
            const sec = $('#programs');
            if (sec) {
                new IntersectionObserver(entries => {
                    entries.forEach(en => {
                        programsInView = en.isIntersecting;
                        programsInView ? ic.resumeNow() : ic.pause();
                    });
                }, { threshold: 0.15 }).observe(sec);
            }
        }

        const onProgramsVisibility = () => {
            if (document.hidden) stop();
            else if (programsInView && autoEnabled) start();
        };
        document.addEventListener('visibilitychange', onProgramsVisibility);

        const onProgramsResize = debounce(() => {
            position();
            if (autoEnabled && programsInView && !document.hidden) start();
        }, 200);
        window.addEventListener('resize', onProgramsResize);
        window.addEventListener('orientationchange', onProgramsResize);
    }

    /* =========================================================
     *  Events, Team, Partners — shared content rail
     * ========================================================= */
    let _eventsRail = null;
    let _teamRail = null;
    let _partnersRail = null;

    function initEventsRail() {
        return initContentRail({
            wrap: '.events-rail',
            track: '.events-track',
            cardSelector: '.event-rail-card',
            idxAttr: 'data-event-idx',
            dotAttr: 'data-event-dot',
            dotsSelector: '[data-events-dots]',
            controlsSelector: '[data-events-controls]',
            autoSelector: '[data-events-auto]',
            prevSelector: '.events-prev',
            nextSelector: '.events-next',
            sectionId: 'events',
            dotLabel: i => `Event ${i + 1}`,
            cardPauseMs: T.story,
            minCloneSets: 2,
        });
    }

    function initTeamRail() {
        return initContentRail({
            wrap: '.team-rail',
            track: '.team-track',
            cardSelector: '.team-rail-card',
            idxAttr: 'data-team-idx',
            dotAttr: 'data-team-dot',
            dotsSelector: '[data-team-dots]',
            controlsSelector: '[data-team-controls]',
            prevSelector: '.team-prev',
            nextSelector: '.team-next',
            sectionId: 'team',
            dotLabel: i => `Team member ${i + 1}`,
            ioThreshold: 0.2,
            cardPauseMs: T.story,
            minCloneSets: 2,
        });
    }

    function initPartnersRail() {
        return initContentRail({
            wrap: '.partners-rail',
            track: '.partners-track',
            cardSelector: '.partner-rail-card',
            idxAttr: 'data-partner-idx',
            dotAttr: 'data-partner-dot',
            dotsSelector: '[data-partners-dots]',
            controlsSelector: '[data-partners-controls]',
            prevSelector: '.partners-prev',
            nextSelector: '.partners-next',
            sectionId: 'partners',
            dotLabel: i => `Partner ${i + 1}`,
            ioThreshold: 0.15,
            direction: -1,
            cardPauseMs: T.compact,
            minCloneSets: 2,
        });
    }

    document.addEventListener('content:ready', async () => {
        initRevealObserver();
        if (_impactCarousel) _impactCarousel.destroy();
        _impactCarousel = initImpactCarousel();
        initProgramsCoverflow();
        if (_eventsRail) _eventsRail.destroy();
        _eventsRail = initEventsRail();
        if (_teamRail) _teamRail.destroy();
        _teamRail = initTeamRail();
        if (_partnersRail) _partnersRail.destroy();
        _partnersRail = initPartnersRail();
        await loadFxRates();
        populateCurrencyPickers();
        renderAmountButtons();
    });
    // Also kick off reveal on initial load (for elements present before content fetch)
    document.addEventListener('DOMContentLoaded', initRevealObserver);

    /* =========================================================
     *  Mobile bottom nav scroll-spy (no horizontal scrolling)
     * ========================================================= */
    function initPillSpy() {
        const pills = $$('#mobileBottomNav a[data-pill]');
        if (!pills.length) return;
        const sections = pills
            .map(a => document.querySelector(a.getAttribute('href')))
            .filter(Boolean);
        if (!sections.length) return;

        function setActive(id) {
            if (navLock || currentSection === 'home' && window.scrollY < 80 && id !== 'home') return;
            pills.forEach(p => p.classList.toggle('active', p.getAttribute('href') === '#' + id));
            if (!navLock && PAGE_TITLES[id]) setPageTitle(id);
        }

        if (!('IntersectionObserver' in window)) {
            setActive(sections[0].id);
            return;
        }

        const ratios = new Map();
        let lastTracked = '';
        const io = new IntersectionObserver(entries => {
            entries.forEach(en => ratios.set(en.target.id, en.intersectionRatio));
            let bestId = null, bestR = 0;
            ratios.forEach((r, id) => { if (r > bestR) { bestR = r; bestId = id; } });
            if (bestId && bestR > 0) {
                setActive(bestId);
                if (bestId !== lastTracked && bestR >= 0.35) {
                    lastTracked = bestId;
                    trackUserInterest(bestId);
                }
            }
        }, {
            rootMargin: '-20% 0px -35% 0px',
            threshold: [0, .25, .5, .75, 1],
        });
        sections.forEach(s => io.observe(s));
    }
    document.addEventListener('DOMContentLoaded', initPillSpy);

})();
