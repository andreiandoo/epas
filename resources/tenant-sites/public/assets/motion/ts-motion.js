/*
 * Modulul comun de animații al site-urilor de tenant (UI_RESOURCES.md).
 * Se încarcă doar pe paginile template-ului, nu pe coș / checkout / cont.
 * Folosește Lenis (smooth scroll) și GSAP + ScrollTrigger dacă manifestul le cere.
 *
 * Atribute în markup:
 *   data-reveal              apare la scroll (fade + urcare)
 *   data-reveal="left|right|scale|fade"
 *   data-reveal-delay="0.2"  întârziere în secunde
 *   data-stagger             copiii direcți apar pe rând
 *   data-parallax="0.2"      parallax vertical (fracțiune din înălțimea ferestrei)
 *
 * Cu prefers-reduced-motion: niciun smooth scroll, nicio animație, totul vizibil.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function showAll() {
        root.classList.add('ts-motion-off');
    }

    if (reduced || !window.gsap) {
        showAll();
        return;
    }

    var gsap = window.gsap;
    var ST = window.ScrollTrigger;
    if (ST) {
        gsap.registerPlugin(ST);
    }

    // Smooth scroll, legat de ScrollTrigger
    if (window.Lenis && !('ontouchstart' in window && window.innerWidth < 768)) {
        var lenis = new window.Lenis({ duration: 1.1, smoothWheel: true });
        window.tsLenis = lenis;
        if (ST) {
            lenis.on('scroll', ST.update);
        }
        gsap.ticker.add(function (time) { lenis.raf(time * 1000); });
        gsap.ticker.lagSmoothing(0);
        // Ancorele interne trec prin Lenis
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href^="#"]');
            if (a && a.getAttribute('href').length > 1) {
                var target = document.querySelector(a.getAttribute('href'));
                if (target) { e.preventDefault(); lenis.scrollTo(target, { offset: -90 }); }
            }
        });
    }

    var from = {
        up: { y: 40, opacity: 0 },
        left: { x: -50, opacity: 0 },
        right: { x: 50, opacity: 0 },
        scale: { scale: 0.94, opacity: 0 },
        fade: { opacity: 0 }
    };

    // Tranzițiile CSS pe transform (ex. .show-card:hover) ar încetini animația:
    // le oprim cât rulează GSAP și le dăm înapoi la final.
    function pauseTransitions(els) {
        els.forEach(function (el) { el.style.transition = 'none'; });
        return function () { els.forEach(function (el) { el.style.transition = ''; }); };
    }

    function reveal(el, vars, delay) {
        gsap.from(el, Object.assign({}, vars, {
            duration: 0.9,
            ease: 'power3.out',
            delay: delay || 0,
            clearProps: 'transform,opacity',
            onStart: function () { this._restore = pauseTransitions([el]); },
            onComplete: function () { if (this._restore) { this._restore(); } },
            scrollTrigger: ST ? { trigger: el, start: 'top 88%', once: true } : undefined
        }));
    }

    document.querySelectorAll('[data-reveal]').forEach(function (el) {
        var kind = el.getAttribute('data-reveal') || 'up';
        reveal(el, from[kind] || from.up, parseFloat(el.getAttribute('data-reveal-delay')) || 0);
    });

    document.querySelectorAll('[data-stagger]').forEach(function (el) {
        var kids = Array.prototype.slice.call(el.children);
        if (!kids.length) { return; }
        gsap.from(kids, {
            y: 30, opacity: 0, duration: 0.8, ease: 'power3.out', stagger: 0.08,
            clearProps: 'transform,opacity',
            onStart: function () { this._restore = pauseTransitions(kids); },
            onComplete: function () { if (this._restore) { this._restore(); } },
            scrollTrigger: ST ? { trigger: el, start: 'top 85%', once: true } : undefined
        });
    });

    if (ST) {
        document.querySelectorAll('[data-parallax]').forEach(function (el) {
            var amount = parseFloat(el.getAttribute('data-parallax')) || 0.15;
            gsap.to(el, {
                yPercent: amount * 100,
                ease: 'none',
                scrollTrigger: { trigger: el.parentElement || el, start: 'top top', end: 'bottom top', scrub: true }
            });
        });
        // Imaginile încărcate leneș schimbă înălțimile: recalculăm
        window.addEventListener('load', function () { ST.refresh(); });
    }
})();
