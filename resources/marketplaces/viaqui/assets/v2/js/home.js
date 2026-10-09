/* Viaqui v2 homepage. The page is complete without this file: it adds the hero photo rotation, search
   suggestions, the "this week" filter, tabs, the gift-card tilt and, after first paint, the motion layer
   (GSAP + ScrollTrigger, Lenis, Motion, three.js), all self-hosted and listed in #v2-data. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var desktop = matchMedia('(min-width: 1024px) and (pointer: fine)').matches;
  var data = {};
  try { data = JSON.parse(($('#v2-data') || {}).textContent || '{}'); } catch (e) {}
  var libs = data.libs || {};

  /* ---- hero photo rotation ---- */
  var heroImgs = $$('#hero-arch img'), dots = $$('#hero-dots button'), hi = 0, ht;
  // Only the first photo is in the page; the others arrive once the page has loaded, so they do not compete with it.
  function loadHero() {
    heroImgs.forEach(function (im) {
      if (!im.dataset.src) return;
      if (im.dataset.srcset) im.srcset = im.dataset.srcset;
      im.src = im.dataset.src;
      im.removeAttribute('data-src'); im.removeAttribute('data-srcset');
    });
  }
  if (document.readyState === 'complete') setTimeout(loadHero, 1200);
  else window.addEventListener('load', function () { setTimeout(loadHero, 1200); });
  function showHero(i) {
    loadHero();
    hi = i;
    heroImgs.forEach(function (im, k) { im.classList.toggle('is-on', k === i); });
    dots.forEach(function (d, k) { d.setAttribute('aria-pressed', k === i ? 'true' : 'false'); });
    if (dots[i]) { $('#hero-place-n').textContent = dots[i].dataset.name; $('#hero-place-m').textContent = dots[i].dataset.meta; }
  }
  function cycle() {
    clearInterval(ht);
    if (!reduce && heroImgs.length > 1) ht = setInterval(function () { showHero((hi + 1) % heroImgs.length); }, 6500);
  }
  dots.forEach(function (d, k) { d.addEventListener('click', function () { showHero(k); cycle(); }); });
  cycle();

  /* ---- hero search: suggestions from the cities and categories in the page data ---- */
  var q = $('#hs-q'), sug = $('#hs-suggest'), form = $('#hero-search');
  if (q && sug) {
    var pool = (data.cities || []).concat(data.categories || []), active = -1;
    var fold = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var close = function () { sug.hidden = true; q.setAttribute('aria-expanded', 'false'); active = -1; };
    var render = function () {
      var term = fold(q.value.trim());
      sug.textContent = '';
      if (term.length < 2) { close(); return; }
      var hits = pool.filter(function (p) { return fold(p[0]).indexOf(term) !== -1; }).slice(0, 6);
      if (!hits.length) { close(); return; }
      hits.forEach(function (h, i) {
        var a = document.createElement('a');
        a.href = VQ.url(h[2]); a.id = 'hs-sg-' + i; a.setAttribute('role', 'option');
        a.appendChild(document.createTextNode(h[0]));
        var s = document.createElement('small'); s.textContent = h[1]; a.appendChild(s);
        sug.appendChild(a);
      });
      sug.hidden = false; q.setAttribute('aria-expanded', 'true'); active = -1;
    };
    q.addEventListener('input', render);
    q.addEventListener('keydown', function (e) {
      var items = $$('a', sug);
      if (e.key === 'Escape') { close(); return; }
      if (sug.hidden || !items.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach(function (it, i) { it.classList.toggle('is-active', i === active); });
        q.setAttribute('aria-activedescendant', items[active].id);
      } else if (e.key === 'Enter' && active > -1) { e.preventDefault(); location.href = items[active].href; }
    });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
    // empty fields stay out of the address
    form.addEventListener('submit', function () { $$('input,select', form).forEach(function (f) { if (!f.value) f.disabled = true; }); setTimeout(function () { $$('input,select', form).forEach(function (f) { f.disabled = false; }); }, 0); });
  }

  /* ---- this week: filter chips over the server-rendered cards ---- */
  var cards = $('#week-cards');
  if (cards) {
    var items = $$('li', cards), perView = function () { return matchMedia('(max-width: 479px)').matches ? 4 : 8; };
    var apply = function (f, animate) {
      var shown = 0, list = [];
      items.forEach(function (li) {
        var ok = (f === 'all' || li.dataset.k === f) && shown < perView();
        li.hidden = !ok;
        if (ok) { shown++; list.push(li); }
      });
      if (animate && window.Motion && !reduce) Motion.animate(list, { opacity: [0, 1], y: [18, 0] }, { duration: 0.5, delay: Motion.stagger(0.05), ease: [0.2, 0.7, 0.2, 1] });
    };
    cards.classList.add('is-js');
    apply('all', false);
    $$('#week-chips .v-chip').forEach(function (c) {
      c.addEventListener('click', function () {
        $$('#week-chips .v-chip').forEach(function (x) { x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); });
        apply(c.dataset.f, true);
      });
    });
  }

  /* ---- top lists: tabs ---- */
  var tabs = $$('#top-tabs .v-tab');
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) {
        var on = x === t;
        x.setAttribute('aria-selected', on ? 'true' : 'false'); x.tabIndex = on ? 0 : -1;
        document.getElementById(x.getAttribute('aria-controls')).hidden = !on;
      });
    });
    t.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      var n = tabs[(i + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
      n.focus(); n.click();
    });
  });

  /* ---- gift card: tilt with the pointer ---- */
  var stage = $('#gift-stage'), gc = $('#gift-card');
  if (stage && gc && desktop && !reduce) {
    stage.addEventListener('pointermove', function (e) {
      var r = stage.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
      gc.style.setProperty('--ry', ((x - 0.5) * 22) + 'deg'); gc.style.setProperty('--rx', ((0.5 - y) * 16) + 'deg');
      gc.style.setProperty('--mx', (x * 100) + '%'); gc.style.setProperty('--my', (y * 100) + '%');
    });
    stage.addEventListener('pointerleave', function () { gc.style.removeProperty('--ry'); gc.style.removeProperty('--rx'); });
  }

  /* ---- journey rail without the pin (phones, reduced motion): the line follows the swipe ---- */
  var scene = $('#journey-scene'), stops = $('#journey-stops'), line = $('#journey-line'), prog = $('#journey-progress');
  function railProgress() {
    var p = stops.scrollLeft / Math.max(1, stops.scrollWidth - stops.clientWidth);
    prog.style.setProperty('--p', p);
    line.style.strokeDasharray = 1; line.style.strokeDashoffset = Math.max(0, 0.86 - p * 0.86);
  }
  if (stops && line && prog) { stops.addEventListener('scroll', railProgress, { passive: true }); requestAnimationFrame(function () { setTimeout(railProgress, 0); }); }

  if (reduce) return;

  /* ---- motion layer, after first paint ---- */
  function load(src) {
    return new Promise(function (res, rej) {
      if (!src) { rej(); return; }
      var s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s);
    });
  }
  function boot() {
    load(libs.gsap).then(function () { return load(libs.scrollTrigger); }).then(function () {
      gsap.registerPlugin(ScrollTrigger);
      // hero entrance: only while the hero is still on screen
      if (window.scrollY < 80) {
        gsap.from('.v-hero-h i', { yPercent: 105, duration: 1, stagger: 0.09, ease: 'power3.out' });
        gsap.from(['#hero-l1', '#hero-l2'], { strokeDashoffset: 1, duration: 1.8, stagger: 0.25, ease: 'power2.inOut' });
      }
      gsap.to('#hero-arch img', { yPercent: -9, ease: 'none', scrollTrigger: { trigger: '#hero', start: 'top top', end: 'bottom top', scrub: true } });

      // journey: pinned horizontal travel on desktop; elsewhere it stays a swipe rail
      if (desktop && scene && stops) {
        stops.removeEventListener('scroll', railProgress);
        scene.classList.add('is-pinned');
        var dist = function () { return Math.max(0, stops.scrollWidth - window.innerWidth); };
        gsap.set(line, { strokeDasharray: 1, strokeDashoffset: 1 });
        var tl = gsap.timeline({ scrollTrigger: { trigger: scene, start: 'center center', end: function () { return '+=' + dist(); }, pin: true, scrub: 0.6, invalidateOnRefresh: true, onUpdate: function (s) { prog.style.setProperty('--p', s.progress); } } });
        tl.to(stops, { x: function () { return -dist(); }, ease: 'none' }, 0).to(line, { strokeDashoffset: 0, ease: 'none' }, 0);
        $$('.v-stop-ph img', stops).forEach(function (im) {
          gsap.fromTo(im, { xPercent: -5, scale: 1.1 }, { xPercent: 5, scale: 1.1, ease: 'none', scrollTrigger: { trigger: im, containerAnimation: tl, start: 'left right', end: 'right left', scrub: true } });
        });
      }

      $$('[data-count]').forEach(function (el) {
        var o = { v: 0 }, end = +el.dataset.count;
        ScrollTrigger.create({ trigger: el, start: 'top 88%', once: true, onEnter: function () { gsap.to(o, { v: end, duration: 1.6, ease: 'power2.out', onUpdate: function () { el.textContent = Math.round(o.v).toLocaleString(VQ.locale === 'en' ? 'en-GB' : VQ.locale); } }); } });
      });

      // one Lenis instance, driven by the GSAP ticker; base.js pauses it while the mobile menu is open
      if (desktop) {
        load(libs.lenis).then(function () {
          var lenis = new Lenis({ lerp: 0.11 });
          window.v2Lenis = lenis;
          lenis.on('scroll', ScrollTrigger.update);
          gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
          gsap.ticker.lagSmoothing(0);
        }).catch(function () {});
      }
    }).catch(function () {});

    // Motion: staggered reveals for groups that start below the fold (they stay visible if the library fails)
    load(libs.motion).then(function () {
      if (!window.Motion) return;
      $$('[data-vreveal]').forEach(function (g) {
        if (g.getBoundingClientRect().top < window.innerHeight) return;
        var kids = [].slice.call(g.children);
        kids.forEach(function (k) { k.style.opacity = '.001'; });
        Motion.inView(g, function () { Motion.animate(kids, { opacity: [0, 1], y: [28, 0] }, { duration: 0.7, delay: Motion.stagger(0.06), ease: [0.2, 0.7, 0.2, 1] }); }, { margin: '0px 0px -12% 0px' });
      });
    }).catch(function () {});

    // three.js: living contour lines behind the hero. Desktop only; the static pattern stays as the fallback.
    if (desktop && window.scrollY < window.innerHeight) {
      load(libs.three).then(function () {
        var cv = $('#hero-gl'), host = cv.parentNode, r;
        try { r = new THREE.WebGLRenderer({ canvas: cv, alpha: true, antialias: false, powerPreference: 'low-power' }); } catch (e) { return; }
        r.setPixelRatio(Math.min(devicePixelRatio, 1.5));
        var sc = new THREE.Scene(), cam = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
        var u = { t: { value: 0 }, res: { value: new THREE.Vector2(1, 1) }, m: { value: new THREE.Vector2(0.7, 0.4) } };
        var mat = new THREE.ShaderMaterial({
          transparent: true, uniforms: u, vertexShader: 'void main(){gl_Position=vec4(position,1.);}',
          fragmentShader: [
            'precision highp float;uniform float t;uniform vec2 res;uniform vec2 m;',
            'vec2 h(vec2 p){p=vec2(dot(p,vec2(127.1,311.7)),dot(p,vec2(269.5,183.3)));return -1.+2.*fract(sin(p)*43758.5453);}',
            'float n(vec2 p){vec2 i=floor(p),f=fract(p),w=f*f*(3.-2.*f);return mix(mix(dot(h(i),f),dot(h(i+vec2(1,0)),f-vec2(1,0)),w.x),mix(dot(h(i+vec2(0,1)),f-vec2(0,1)),dot(h(i+vec2(1,1)),f-vec2(1,1)),w.x),w.y);}',
            'void main(){vec2 uv=gl_FragCoord.xy/res.xy;vec2 p=uv*vec2(res.x/res.y,1.)*2.2;',
            'float e=n(p+vec2(t*.03,0.))*.9+n(p*2.1-vec2(0.,t*.02))*.35;e+=.35*exp(-6.*distance(uv,m));',
            'float l=abs(fract(e*7.)-.5);float w=fwidth(e*7.)*1.1;float a=1.-smoothstep(0.,w,l);',
            'vec3 c=mix(vec3(.22,.63,.41),vec3(.92,.87,.78),smoothstep(.0,.9,uv.x));',
            'gl_FragColor=vec4(c,a*.17*smoothstep(0.,.25,uv.y));}'
          ].join('\n'), extensions: { derivatives: true }
        });
        sc.add(new THREE.Mesh(new THREE.PlaneGeometry(2, 2), mat));
        function size() { var w = host.clientWidth, hh = host.clientHeight; r.setSize(w, hh, false); u.res.value.set(w * r.getPixelRatio(), hh * r.getPixelRatio()); }
        size(); addEventListener('resize', size);
        host.addEventListener('pointermove', function (e) { var b = host.getBoundingClientRect(); u.m.value.set((e.clientX - b.left) / b.width, 1 - (e.clientY - b.top) / b.height); });
        var vis = true, t0 = performance.now();
        new IntersectionObserver(function (en) { vis = en[0].isIntersecting; }).observe(host);
        (function loop(now) { requestAnimationFrame(loop); if (!vis || document.hidden) return; u.t.value = (now - t0) / 1000; r.render(sc, cam); })(t0);
        cv.classList.add('is-on'); $('.v-hero-topo').style.opacity = '.18';
      }).catch(function () {});
    }
  }
  if ('requestIdleCallback' in window) requestIdleCallback(boot, { timeout: 1500 }); else setTimeout(boot, 400);
})();
