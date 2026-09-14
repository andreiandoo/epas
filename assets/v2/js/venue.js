/* bilete.online v2: venue page. The hero photo carousel (the lightbox it opens is attraction.js) and the sticky
   section nav that marks the section being read. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- hero carousel: dots or a swipe pick the photo; the arch opens the lightbox on it ---------- */
  var arch = $('vn-arch');
  var dots = [].slice.call(document.querySelectorAll('.vn-dots [data-slide]'));
  if (arch && dots.length > 1) {
    var slides = [].slice.call(arch.querySelectorAll('.vn-slide'));
    var current = 0;
    var go = function (n) {
      current = (n + slides.length) % slides.length;
      slides.forEach(function (img, k) { img.classList.toggle('is-on', k === current); });
      dots.forEach(function (dot, k) { dot.setAttribute('aria-pressed', String(k === current)); });
      arch.setAttribute('data-gallery', String(current));
    };
    dots.forEach(function (dot) {
      dot.addEventListener('click', function () { go(parseInt(dot.getAttribute('data-slide'), 10) || 0); });
    });
    var touch = null;
    arch.addEventListener('touchstart', function (e) {
      touch = e.touches.length === 1 ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
    }, { passive: true });
    arch.addEventListener('touchend', function (e) {
      if (!touch) return;
      var dx = e.changedTouches[0].clientX - touch.x, dy = e.changedTouches[0].clientY - touch.y;
      touch = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        e.preventDefault(); // a swipe changes the photo instead of opening the lightbox
        go(current + (dx < 0 ? 1 : -1));
      }
    });
  }

  /* ---------- section nav: the link of the section under the nav is current and kept in view ---------- */
  var list = $('vn-nav');
  if (!list) return;
  var links = [].slice.call(list.querySelectorAll('a[href^="#"]'));
  var targets = links.map(function (a) { return $(a.getAttribute('href').slice(1)); });
  var active = null, ticking = false;

  function mark(index) {
    if (index === active) return;
    active = index;
    links.forEach(function (a, k) {
      if (k === index) a.setAttribute('aria-current', 'true');
      else a.removeAttribute('aria-current');
    });
    if (list.scrollWidth > list.clientWidth + 1) {
      var r = links[index].getBoundingClientRect(), box = list.getBoundingClientRect();
      list.scrollBy({ left: (r.left + r.width / 2) - (box.left + box.width / 2), behavior: reduce ? 'auto' : 'smooth' });
    }
  }
  function update() {
    ticking = false;
    var line = list.getBoundingClientRect().bottom + Math.min(window.innerHeight * 0.3, 240);
    var index = 0;
    targets.forEach(function (el, k) { if (el && el.getBoundingClientRect().top <= line) index = k; });
    mark(index);
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
  }, { passive: true });
  window.addEventListener('resize', update);
  update();
})();
