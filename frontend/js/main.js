/* Tattu Care - main interactions */
(function () {
    'use strict';

    const API = (window.TATTU_API || '/backend/api/');

    const $  = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

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
    function toast(message, type = 'success', timeout = 4500) {
        const host = ensureToastContainer();
        const el = document.createElement('div');
        el.className = 'toast ' + (type === 'success' ? '' : type);
        el.textContent = message;
        host.appendChild(el);
        setTimeout(() => {
            el.style.transition = 'opacity .3s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 300);
        }, timeout);
    }
    window.toast = toast;

    /* ---------------- Sticky header / back-to-top ---------------- */
    const header = $('#header');
    function onScroll() {
        if (header) header.classList.toggle('header-scrolled', window.scrollY > 60);
        const back = $('.back-to-top');
        if (back) back.classList.toggle('visible', window.scrollY > 300);
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
        const dark = root.dataset.theme === 'dark';
        i.className = dark ? 'fas fa-sun' : 'fas fa-moon';
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
    const back = $('.back-to-top');
    if (back) back.addEventListener('click', () =>
        window.scrollTo({ top: 0, behavior: 'smooth' }));

    /* ---------------- Donation modal ---------------- */
    const modal       = $('#donationModal');
    const closeModal  = modal ? modal.querySelector('.close-modal') : null;
    const donorForm   = $('#mobileMoneyForm');
    const donorAmount = $('#donationAmount');

    function openDonation(preset) {
        if (!modal) return;
        if (preset && donorAmount) donorAmount.value = preset;
        document.body.style.overflow = 'hidden';
        modal.style.display = 'block';
        requestAnimationFrame(() => modal.classList.add('active'));
        const first = modal.querySelector('input, button');
        if (first) setTimeout(() => first.focus(), 200);
    }
    function closeDonation() {
        if (!modal) return;
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 280);
    }
    window.openDonation  = openDonation;
    window.closeDonation = closeDonation;

    if (closeModal) closeModal.addEventListener('click', closeDonation);
    if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeDonation(); });
    document.addEventListener('keyup', e => { if (e.key === 'Escape') closeDonation(); });

    document.addEventListener('click', e => {
        const trigger = e.target.closest('.donate-trigger, .donate-btn, .mobile-donate-btn, [data-action="donate"]');
        if (trigger && !trigger.closest('#donationModal')) {
            e.preventDefault();
            openDonation();
            return;
        }
        const amt = e.target.closest('.amount-btn');
        if (amt) {
            e.preventDefault();
            $$('.amount-btn').forEach(b => b.classList.remove('selected'));
            amt.classList.add('selected');
            const v = amt.getAttribute('data-amount');
            const custom = $('#custom-amount');
            if (custom) custom.value = '';
            openDonation(v);
        }
    });

    document.addEventListener('input', e => {
        if (e.target && e.target.id === 'custom-amount') {
            $$('.amount-btn').forEach(b => b.classList.remove('selected'));
        }
    });

    if (donorForm) {
        donorForm.addEventListener('submit', async e => {
            e.preventDefault();
            const submitBtn = donorForm.querySelector('button[type="submit"]');
            const fd = new FormData(donorForm);
            const payload = {
                amount: Number(fd.get('amount') || 0),
                name:   String(fd.get('name')   || '').trim(),
                phone:  String(fd.get('phone')  || '').replace(/\s+/g, ''),
                email:  String(fd.get('email')  || '').trim(),
            };

            if (payload.amount < 1) { toast('Please enter an amount.', 'error'); return; }
            if (!/^256\d{9}$/.test(payload.phone)) {
                toast('Phone must start with 256 and be 12 digits, e.g. 256770000000', 'error');
                return;
            }

            const original = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing…';
            try {
                const res  = await fetch(API + 'donate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const body = await res.json().catch(() => ({}));
                if (res.ok && body.success) {
                    toast(body.message || 'Thank you for your donation!');
                    donorForm.reset();
                    closeDonation();
                } else {
                    toast(body.message || 'Could not process the donation. Please try again.', 'error');
                }
            } catch (err) {
                toast('Network error. Please check your connection.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = original;
            }
        });
    }

    /* ---------------- Contact form ---------------- */
    const contactForm = $('#contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async e => {
            e.preventDefault();
            const fb = contactForm.querySelector('.form-feedback');
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

    /* ---------------- Newsletter ---------------- */
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

    /* ---------------- Slider ---------------- */
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

    document.addEventListener('content:ready', () => {
        buildSlider('.impact-slideshow', '.impact-slide', '[data-content="impactDots"]', 7000);
    });

})();
