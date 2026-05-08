/* Tattu Care - main interactions (compact dropdown, scroll-reveal, bank reveal) */
(function () {
    'use strict';

    const API = (window.TATTU_API || '/backend/api/');
    const $   = (s, c = document) => c.querySelector(s);
    const $$  = (s, c = document) => Array.from(c.querySelectorAll(s));

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

    /* ---------------- Sticky header / back-to-top / floating donate ---------------- */
    const header   = $('#header');
    const fab      = $('#floatingDonate');
    const backToTop = $('.back-to-top');
    function onScroll() {
        const y = window.scrollY;
        if (header)    header.classList.toggle('header-scrolled', y > 60);
        if (backToTop) backToTop.classList.toggle('visible', y > 300);
        // (FAB stays visible; do not hide on scroll-up)
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

    /* ---------------- Smooth scroll ---------------- */
    document.addEventListener('click', e => {
        const a = e.target.closest('a[href^="#"]');
        if (!a) return;
        const id = a.getAttribute('href');
        if (id === '#' || id.length < 2) return;
        const target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        setMenuOpen(false);
        const headerH = header ? header.offsetHeight : 0;
        window.scrollTo({
            top: target.getBoundingClientRect().top + window.pageYOffset - headerH + 1,
            behavior: 'smooth',
        });
    });

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
    function buildSlider(rootSel, slideSel, dotsSel, autoMs = 7000) {
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
            $$('.reveal, .reveal-stagger').forEach(el => el.classList.add('in-view'));
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
        $$('.reveal, .reveal-stagger').forEach(el => io.observe(el));
    }

    /* =========================================================
     *  Programs scroller — nav buttons
     * ========================================================= */
    document.addEventListener('click', e => {
        const btn = e.target.closest('.programs-nav');
        if (!btn) return;
        const wrap = btn.closest('.programs-scroller');
        const grid = wrap && wrap.querySelector('.programs-grid');
        if (!grid) return;
        const card = grid.querySelector('.program-card');
        const step = (card ? card.getBoundingClientRect().width : 280) + 14;
        grid.scrollBy({ left: btn.classList.contains('next') ? step : -step, behavior: 'smooth' });
    });

    /* =========================================================
     *  Impact: click-to-expand modal
     * ========================================================= */
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
    }
    document.addEventListener('click', e => {
        const card = e.target.closest('.impact-card');
        if (card) {
            const idx = Number(card.getAttribute('data-impact-idx') || 0);
            const list = window.__IMPACT_STORIES__ || [];
            openImpactExpand(list[idx]);
            return;
        }
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

    document.addEventListener('content:ready', () => {
        initRevealObserver();
    });
    // Also kick off reveal on initial load (for elements present before content fetch)
    document.addEventListener('DOMContentLoaded', initRevealObserver);

    /* =========================================================
     *  Mobile pill nav scroll-spy
     *  Highlights the pill matching whichever section is most in view.
     * ========================================================= */
    function initPillSpy() {
        const pills = $$('#mobilePillNav a');
        if (!pills.length) return;
        const sections = pills
            .map(a => document.querySelector(a.getAttribute('href')))
            .filter(Boolean);
        if (!sections.length) return;

        function setActive(id) {
            pills.forEach(p => p.classList.toggle('active', p.getAttribute('href') === '#' + id));
        }

        if (!('IntersectionObserver' in window)) {
            setActive(sections[0].id);
            return;
        }

        // Track ratios so we can pick the most-visible section
        const ratios = new Map();
        const io = new IntersectionObserver(entries => {
            entries.forEach(en => ratios.set(en.target.id, en.intersectionRatio));
            let bestId = null, bestR = 0;
            ratios.forEach((r, id) => { if (r > bestR) { bestR = r; bestId = id; } });
            if (bestId && bestR > 0) setActive(bestId);
        }, {
            // Trigger when section's center area is in the viewport, ignoring header/pill-nav offsets
            rootMargin: '-30% 0px -45% 0px',
            threshold: [0, .25, .5, .75, 1],
        });
        sections.forEach(s => io.observe(s));

        // Auto-scroll the active pill into view when the user navigates by scrolling
        let lastActive = '';
        const watch = setInterval(() => {
            const cur = pills.find(p => p.classList.contains('active'));
            if (cur && cur.dataset.pill !== lastActive) {
                lastActive = cur.dataset.pill;
                cur.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        }, 400);
        // (interval is harmless on desktop too; pill nav is hidden via CSS there)
    }
    document.addEventListener('DOMContentLoaded', initPillSpy);

})();
