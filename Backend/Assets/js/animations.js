/*
 * animations.js — Vanilla utilities for reveal-on-scroll, parallax hero, count-up
 * - Uses IntersectionObserver for reveals (threshold ~0.15)
 * - rAF-throttled scroll for parallax
 * - Respects prefers-reduced-motion
 *
 * How to use:
 *  - Add `.reveal-on-scroll` to elements or parent groups
 *  - Optionally add `data-reveal-stagger="40"` (ms) on a group
 *  - Add `.parallax-hero` to hero section and optional `data-parallax-strength="0.12"`
 *  - For counters add `.animate-count` and `data-target="1234"`
 */
(function () {
    'use strict';

    const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function initRevealOnScroll() {
        if (prefersReduced) {
            // Immediately reveal
            document.querySelectorAll('.reveal-on-scroll').forEach(el => el.classList.add('is-revealed'));
            return;
        }

        const observerOptions = { threshold: 0.16 };
        const io = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                // If group has stagger data, apply delay to children
                const stagger = Number(el.dataset.revealStagger || el.closest('[data-reveal-stagger]')?.dataset.revealStagger || 40);
                if (el.children && el.children.length > 0 && el.classList.contains('reveal-stagger')) {
                    Array.from(el.children).forEach((child, idx) => {
                        child.style.transitionDelay = `${(idx + 1) * stagger}ms`;
                        child.classList.add('is-revealed');
                    });
                }

                // If this element is a container for item reveals (e.g. card row), reveal children if they have class
                if (el.classList.contains('reveal-on-scroll') && el.dataset.revealChildren === 'true') {
                    Array.from(el.querySelectorAll('.reveal-item')).forEach((child, idx) => {
                        child.style.transitionDelay = `${((child.dataset.revealIndex|0) || idx) * (Number(el.dataset.revealStagger) || 40)}ms`;
                        child.classList.add('is-revealed');
                    });
                }

                el.classList.add('is-revealed');
                obs.unobserve(el);
            });
        }, observerOptions);

        document.querySelectorAll('.reveal-on-scroll').forEach(el => {
            io.observe(el);
        });
    }

    // Simple count up
    function initCountUp() {
        if (prefersReduced) {
            document.querySelectorAll('.animate-count').forEach(el => {
                const target = Number(el.dataset.target || el.textContent.replace(/[^0-9.-]/g, '')) || 0;
                el.textContent = target.toLocaleString();
            });
            return;
        }

        const io = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                const target = Number(el.dataset.target || el.textContent.replace(/[^0-9.-]/g, '')) || 0;
                const duration = Number(el.dataset.duration) || 1100;
                let start = null;
                function step(ts) {
                    if (!start) start = ts;
                    const progress = Math.min((ts - start) / duration, 1);
                    const value = Math.floor(progress * target);
                    el.textContent = value.toLocaleString();
                    if (progress < 1) requestAnimationFrame(step);
                    else el.textContent = target.toLocaleString();
                }
                requestAnimationFrame(step);
                obs.unobserve(el);
            });
        }, { threshold: 0.6 });

        document.querySelectorAll('.animate-count').forEach(el => io.observe(el));
    }

    // Parallax hero — translate inner content modestly
    function initParallaxHero() {
        if (prefersReduced) return;

        const hero = document.querySelector('.parallax-hero');
        if (!hero) return;

        const copy = hero.querySelector('.fun-hero-copy');
        const doodles = hero.querySelector('.hero-doodles');
        const strength = Number(hero.dataset.parallaxStrength) || 0.12; // proportion of viewport

        let lastScroll = window.scrollY;
        let ticking = false;

        function onFrame() {
            const rect = hero.getBoundingClientRect();
            const vh = window.innerHeight || document.documentElement.clientHeight;
            // fraction from -1..1 where 0 means top of hero at top of viewport
            const visible = Math.max(0, Math.min(1, (vh - rect.top) / (vh + rect.height)));
            const offset = (visible - 0.5) * 2; // -1..1
            const translate = offset * (strength * vh);

            if (copy) copy.style.transform = `translateY(${translate * 0.25}px)`;
            if (doodles) doodles.style.transform = `translateY(${translate * 0.5}px)`;
            // darken overlay as user scrolls down
            const darken = Math.min(0.85, Math.max(0, -rect.top / (rect.height * 0.7)));
            hero.style.setProperty('--scroll-darken', String(darken));

            ticking = false;
        }

        function onScroll() {
            lastScroll = window.scrollY;
            if (!ticking) {
                window.requestAnimationFrame(onFrame);
                ticking = true;
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        // initial frame
        requestAnimationFrame(onFrame);
    }

    function init() {
        try {
            initRevealOnScroll();
            initCountUp();
            initParallaxHero();
        } catch (e) {
            // fail gracefully
            console.error('animations.js init error', e);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
