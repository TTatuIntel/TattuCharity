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
    const header     = $('#header');
    const fab        = $('#floatingDonate');
    const backToTop  = $('.back-to-top');
    const pillNav    = $('#mobilePillNav');
    const themeBtn   = $('.theme-toggle');
    function onScroll() {
        const y = window.scrollY;
        const scrolled = y > 60;
        if (header)    header.classList.toggle('header-scrolled', scrolled);
        if (pillNav)   pillNav.classList.toggle('is-scrolled', scrolled);
        if (themeBtn)  themeBtn.classList.toggle('is-scrolled', scrolled);
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
        function start() { stop(); _progTimer = setInterval(next, 3500); }
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
     *  Programs - 3D coverflow rotation
     * ========================================================= */
    let _cfTimer = null;
    function initProgramsCoverflow() {
        const grid = $('.programs-grid');
        if (!grid) return;
        const cards = $$('.program-card', grid);
        if (cards.length < 2) return;

        let active = 0;
        const N = cards.length;

        function position() {
            cards.forEach((c, i) => {
                let d = i - active;
                // wrap to shortest signed distance so it cycles cleanly
                if (d >  N / 2) d -= N;
                if (d < -N / 2) d += N;
                c.setAttribute('data-cf', Math.abs(d) <= 2 ? String(d) : 'hide');
            });
        }
        function next()  { active = (active + 1) % N; position(); }
        function start() { stop(); _cfTimer = setInterval(next, 3500); }
        function stop()  { if (_cfTimer) { clearInterval(_cfTimer); _cfTimer = null; } }

        position();
        start();

        const wrap = $('.programs-scroller');
        if (wrap) {
            wrap.addEventListener('mouseenter', stop);
            wrap.addEventListener('mouseleave', start);
            wrap.addEventListener('focusin',  stop);
            wrap.addEventListener('focusout', start);
        }
        // Click any card to bring it into focus
        grid.addEventListener('click', e => {
            const card = e.target.closest('.program-card');
            if (!card) return;
            const i = cards.indexOf(card);
            if (i >= 0) { active = i; position(); start(); }
        });
        // Pause when section not visible (saves CPU)
        if ('IntersectionObserver' in window) {
            const sec = $('#programs');
            if (sec) {
                new IntersectionObserver(entries => {
                    entries.forEach(en => en.isIntersecting ? start() : stop());
                }, { threshold: 0.15 }).observe(sec);
            }
        }
    }

    document.addEventListener('content:ready', async () => {
        initRevealObserver();
        initProgramsCoverflow();
        await loadFxRates();
        populateCurrencyPickers();
        renderAmountButtons();
    });
    // Also kick off reveal on initial load (for elements present before content fetch)
    document.addEventListener('DOMContentLoaded', initRevealObserver);

    /* =========================================================
     *  Mobile pill nav scroll-spy
     *  Highlights the pill matching whichever section is most in view.
     * ========================================================= */
    function initPillSpy() {
        const pills = $$('#mobilePillNav a:not(.pill-brand)');
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
