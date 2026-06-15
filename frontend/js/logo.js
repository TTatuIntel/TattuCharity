/* Tattu Care — animated logo (white heart, green ring, centred water) */
(function () {
    'use strict';

    const HEART = 'M24 41.5C24 41.5 6.5 27.5 6.5 17.2C6.5 11 11.5 6.2 18 6.2C21.1 6.2 23.1 7.8 24 10.2C24.9 7.8 26.9 6.2 30 6.2C36.5 6.2 41.5 11 41.5 17.2C41.5 27.5 24 41.5 24 41.5Z';
    const DROP  = 'M24 17.2C20.2 17.2 17.2 20.8 17.2 25.2C17.2 30.8 24 37.2 24 37.2C24 37.2 30.8 30.8 30.8 25.2C30.8 20.8 27.8 17.2 24 17.2Z';

    let logoUid = 0;

    function buildSvg(reduced) {
        const uid = ++logoUid;
        const waterId = `logoWater${uid}`;
        const anim = reduced ? ' logo-svg--static' : '';

        return `<svg class="logo-svg${anim}" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="${waterId}" x1="24" y1="17" x2="24" y2="38" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#5dade2"/>
                    <stop offset="55%" stop-color="#3498db"/>
                    <stop offset="100%" stop-color="#2471a3"/>
                </linearGradient>
            </defs>
            <g class="logo-heart-group">
                <path class="logo-heart" fill="#ffffff" stroke="#2ecc71" stroke-width="2.4" stroke-linejoin="round" d="${HEART}"/>
            </g>
            <g class="logo-water-group">
                <path class="logo-drop" fill="url(#${waterId})" d="${DROP}"/>
                <ellipse class="logo-drop-shine" cx="21.4" cy="23.2" rx="2" ry="2.8" fill="#d6eaf8" opacity=".9"/>
            </g>
        </svg>`;
    }

    function replaceImg(img) {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const size = img.getAttribute('width') || img.getAttribute('height') || '56';
        const wrap = document.createElement('span');
        wrap.className = 'logo-mark' + (img.classList.contains('logo-mark--lg') ? ' logo-mark--lg' : '');
        if (img.dataset.logoSize === 'footer') wrap.classList.add('logo-mark--footer');
        wrap.style.width = `${size}px`;
        wrap.style.height = `${size}px`;
        wrap.innerHTML = buildSvg(reduced);
        const svg = wrap.querySelector('svg');
        if (svg) {
            svg.setAttribute('width', size);
            svg.setAttribute('height', size);
        }
        img.replaceWith(wrap);
    }

    function mountAnimatedLogos() {
        const sel = 'img.logo-icon, .footer-brand-icon img[src*="icon.svg"], .login-logo img[src*="icon.svg"], img.sidebar-logo-icon[src*="icon.svg"]';
        document.querySelectorAll(sel).forEach(replaceImg);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountAnimatedLogos);
    } else {
        mountAnimatedLogos();
    }
})();
