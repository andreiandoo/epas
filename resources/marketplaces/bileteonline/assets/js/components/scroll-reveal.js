/**
 * bilete.online — scroll reveal
 * Adds `.in` class when `.rise` or `[data-stagger]` elements enter the viewport.
 * IntersectionObserver, runs once per element.
 */
(function () {
    'use strict';

    function reveal() {
        if (!('IntersectionObserver' in window)) {
            document.querySelectorAll('.rise, [data-stagger]').forEach(function (el) {
                el.classList.add('in');
            });
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('in');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.rise, [data-stagger]').forEach(function (el) {
            io.observe(el);
        });
    }

    if (document.readyState !== 'loading') {
        reveal();
    } else {
        document.addEventListener('DOMContentLoaded', reveal);
    }
})();
