/* competitie.tixello.ro — mișcare: Lenis (scroll lin), GSAP + ScrollTrigger (intrări, galerie
   orizontală, bara de progres), Motion (micro-interacțiuni) și efecte 3D ușoare (tilt + reflex).
   Totul e progresiv: fără biblioteci sau cu „reduce motion”, pagina rămâne completă și statică. */
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var gsap = window.gsap, ST = window.ScrollTrigger;
    var hasGsap = !!(gsap && ST) && !reduce;
    if (gsap && ST) { gsap.registerPlugin(ST); }

    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* ---- Scroll lin ---- */
    var lenis = null;
    if (window.Lenis && !reduce && fine) {
        lenis = new window.Lenis({ duration: 1.1, smoothWheel: true, wheelMultiplier: 1 });
        if (hasGsap) {
            lenis.on('scroll', ST.update);
            gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
            gsap.ticker.lagSmoothing(0);
        } else {
            (function raf(t) { lenis.raf(t); requestAnimationFrame(raf); })(0);
        }
        window.WUKF_LENIS = lenis;
    }
    // Ancorele din pagină (#bilete) derulează lin, cu loc pentru header
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href^="#"]');
        if (!a) { return; }
        var target = a.getAttribute('href').length > 1 && document.querySelector(a.getAttribute('href'));
        if (!target) { return; }
        e.preventDefault();
        if (lenis) { lenis.scrollTo(target, { offset: -90 }); }
        else { window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 90, behavior: reduce ? 'auto' : 'smooth' }); }
    });

    /* ---- Header: solid după primul ecran, ascuns la derulare în jos ---- */
    var head = document.querySelector('.site-head');
    if (head) {
        var lastY = window.scrollY, solidAlways = head.hasAttribute('data-solid');
        var onScroll = function () {
            var y = window.scrollY;
            head.classList.toggle('is-solid', solidAlways || y > 24);
            var open = head.classList.contains('is-open');
            head.classList.toggle('is-hidden', !open && y > 320 && y > lastY + 4);
            if (y < lastY - 4) { head.classList.remove('is-hidden'); }
            lastY = y;
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---- Numărătoare inversă: [data-countdown="2026-11-14T09:00:00"] ---- */
    $$('[data-countdown]').forEach(function (box) {
        var target = new Date(box.getAttribute('data-countdown')).getTime();
        if (isNaN(target)) { return; }
        var cells = { d: box.querySelector('[data-cd="d"]'), h: box.querySelector('[data-cd="h"]'), m: box.querySelector('[data-cd="m"]'), s: box.querySelector('[data-cd="s"]') };
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        function tick() {
            var left = Math.max(0, Math.floor((target - Date.now()) / 1000));
            if (cells.d) { cells.d.textContent = Math.floor(left / 86400); }
            if (cells.h) { cells.h.textContent = pad(Math.floor(left % 86400 / 3600)); }
            if (cells.m) { cells.m.textContent = pad(Math.floor(left % 3600 / 60)); }
            if (cells.s) { cells.s.textContent = pad(left % 60); }
        }
        tick();
        setInterval(tick, 1000);
    });

    /* ---- Efect 3D ușor: înclinare după cursor + reflex ---- */
    if (fine && !reduce) {
        $$('[data-tilt]').forEach(function (el) {
            var max = parseFloat(el.getAttribute('data-tilt')) || 7, raf = null, rx = 0, ry = 0;
            function apply() { raf = null; el.style.transform = 'perspective(1000px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)'; }
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
                ry = (px - .5) * 2 * max; rx = -(py - .5) * 2 * max;
                el.style.setProperty('--gx', (px * 100) + '%');
                el.style.setProperty('--gy', (py * 100) + '%');
                el.style.transition = 'transform .08s linear';
                if (!raf) { raf = requestAnimationFrame(apply); }
            });
            el.addEventListener('pointerleave', function () {
                el.style.transition = 'transform .6s cubic-bezier(.2,.8,.2,1)';
                el.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
            });
        });
    }

    /* ---- Motion: răspuns la apăsare pe butoane ---- */
    if (window.Motion && Motion.animate && !reduce) {
        document.addEventListener('pointerdown', function (e) {
            var b = e.target.closest && e.target.closest('.qty button, .chip');
            if (b && !b.disabled) { Motion.animate(b, { scale: [1, .88, 1] }, { duration: .22 }); }
        });
    }

    /* ---- Titlul mare din footer: se potrivește pe lățime și intră dinspre dreapta când apare footerul.
       Poziția se calculează din locul real al footerului la fiecare derulare, deci nu depinde de
       înălțimea paginii (care se schimbă după ce se încarcă imaginile sau harta de locuri). ---- */
    (function () {
        var big = document.querySelector('.site-foot__big'), foot = document.querySelector('.site-foot');
        var text = big && big.firstElementChild;
        if (!text) { return; }
        var queued = false;
        function fit() {
            text.style.fontSize = '';
            var room = big.clientWidth - 2 * parseFloat(getComputedStyle(big).paddingLeft || 0);
            var size = parseFloat(getComputedStyle(text).fontSize), width = text.scrollWidth;
            if (room > 0 && width > 0) { text.style.fontSize = Math.floor(size * room / width) + 'px'; }
        }
        function place() {
            queued = false;
            if (reduce) { text.style.transform = ''; return; }
            var r = foot.getBoundingClientRect(), vh = window.innerHeight;
            // 0 când footerul abia intră în ecran, 1 când se vede titlul întreg
            var p = Math.max(0, Math.min(1, (vh - r.top) / Math.max(1, Math.min(r.height, vh) * 0.75)));
            var eased = 1 - Math.pow(1 - p, 3);
            text.style.transform = 'translate3d(' + ((1 - eased) * big.clientWidth * 0.6).toFixed(1) + 'px,0,0)';
        }
        function queue() { if (!queued) { queued = true; requestAnimationFrame(place); } }
        fit(); place();
        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', function () { fit(); queue(); });
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { fit(); place(); }); }
    })();

    if (!hasGsap) { return; }

    // Înălțimea paginii se schimbă după încărcare (imagini, hartă de locuri, conținut din Alpine):
    // pozițiile ScrollTrigger se recalculează, altfel animațiile pornesc în locuri greșite.
    if (window.ResizeObserver) {
        var lastH = document.body.scrollHeight, refreshTimer = null;
        new ResizeObserver(function () {
            var h = document.body.scrollHeight;
            if (Math.abs(h - lastH) < 40) { return; }
            lastH = h;
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(function () { ST.refresh(); }, 250);
        }).observe(document.body);
    }

    /* ---- Bara de progres (centurile, de la alb la negru) ---- */
    var bar = document.querySelector('.progress');
    if (bar) {
        gsap.to(bar, { scaleX: 1, ease: 'none', scrollTrigger: { trigger: document.documentElement, start: 'top top', end: 'bottom bottom', scrub: .3 } });
    }

    /* ---- Titluri despărțite pe cuvinte: urcă din mască ---- */
    $$('[data-split]').forEach(function (el) {
        var html = '';
        // Păstrează marcajele <em>/<span> din interior: despărțim doar nodurile de text
        (function walk(node, open, close) {
            node.childNodes.forEach(function (n) {
                if (n.nodeType === 3) {
                    n.textContent.split(/(\s+)/).forEach(function (w) {
                        if (!w) { return; }
                        html += /^\s+$/.test(w) ? ' ' : '<span class="split-w"><span>' + open + w.replace(/&/g, '&amp;').replace(/</g, '&lt;') + close + '</span></span>';
                    });
                } else if (n.nodeType === 1 && n.tagName === 'BR') {
                    html += '<br>';
                } else if (n.nodeType === 1) {
                    var tag = n.tagName.toLowerCase(), cls = n.className ? ' class="' + n.className + '"' : '';
                    walk(n, open + '<' + tag + cls + '>', '</' + tag + '>' + close);
                }
            });
        })(el, '', '');
        el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
        el.innerHTML = html;
        var words = $$('.split-w > span', el);
        var immediate = el.hasAttribute('data-split-now');
        gsap.from(words, {
            yPercent: 115, duration: 1.05, ease: 'expo.out', stagger: .055, delay: immediate ? .15 : 0,
            scrollTrigger: immediate ? undefined : { trigger: el, start: 'top 88%', once: true }
        });
    });

    /* ---- Intrări la derulare ---- */
    $$('[data-reveal]').forEach(function (el) {
        var delay = parseFloat(el.getAttribute('data-reveal')) || 0;
        gsap.from(el, { opacity: 0, y: 36, duration: .9, ease: 'power3.out', delay: delay, scrollTrigger: { trigger: el, start: 'top 90%', once: true } });
    });
    $$('[data-reveal-group]').forEach(function (group) {
        var kids = Array.prototype.slice.call(group.children);
        if (!kids.length) { return; }
        gsap.from(kids, { opacity: 0, y: 44, duration: .9, ease: 'power3.out', stagger: .08, scrollTrigger: { trigger: group, start: 'top 86%', once: true } });
    });
    // Intrarea de pe primul ecran (fără așteptarea derulării)
    $$('[data-enter]').forEach(function (el) {
        var delay = parseFloat(el.getAttribute('data-enter')) || 0;
        gsap.from(el, { opacity: 0, y: 28, duration: 1, ease: 'power3.out', delay: .25 + delay });
    });

    /* ---- Cifre care urcă ---- */
    $$('[data-count]').forEach(function (el) {
        var end = parseFloat(el.getAttribute('data-count')) || 0, obj = { v: 0 };
        el.textContent = '0';
        gsap.to(obj, { v: end, duration: 1.6, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 90%', once: true },
            onUpdate: function () { el.textContent = Math.round(obj.v); } });
    });

    /* ---- Paralaxă ușoară ---- */
    $$('[data-parallax]').forEach(function (el) {
        var amount = parseFloat(el.getAttribute('data-parallax')) || 12;
        gsap.fromTo(el, { yPercent: -amount }, { yPercent: amount, ease: 'none', scrollTrigger: { trigger: el.parentElement, start: 'top bottom', end: 'bottom top', scrub: true } });
    });
    $$('[data-drift]').forEach(function (el) {
        var amount = parseFloat(el.getAttribute('data-drift')) || 18;
        gsap.fromTo(el, { xPercent: amount / 2 }, { xPercent: -amount / 2, ease: 'none', scrollTrigger: { trigger: el.parentElement, start: 'top bottom', end: 'bottom top', scrub: true } });
    });

    /* ---- Galeria orizontală: pe desktop, secțiunea stă fixă și afișele trec lateral ---- */
    var mm = gsap.matchMedia();
    mm.add('(min-width: 1024px)', function () {
        var cleanups = [];
        $$('[data-hscroll]').forEach(function (sec) {
            var track = sec.querySelector('.hs__track'), pin = sec.querySelector('.hs__pin');
            if (!track || !pin) { return; }
            sec.classList.add('is-pinned');
            var dist = function () { return Math.max(0, track.scrollWidth - window.innerWidth); };
            if (dist() < 80) { sec.classList.remove('is-pinned'); return; }
            var tween = gsap.to(track, {
                x: function () { return -dist(); }, ease: 'none',
                scrollTrigger: { trigger: pin, pin: true, scrub: .6, start: 'top top', end: function () { return '+=' + dist(); }, invalidateOnRefresh: true, anticipatePin: 1 }
            });
            cleanups.push(function () {
                if (tween.scrollTrigger) { tween.scrollTrigger.kill(); }
                tween.kill();
                sec.classList.remove('is-pinned');
                gsap.set(track, { clearProps: 'transform' });
            });
        });
        return function () { cleanups.forEach(function (fn) { fn(); }); };
    });

    // Imaginile încărcate târziu schimbă înălțimile: recalculăm pozițiile
    window.addEventListener('load', function () { ST.refresh(); });
})();
