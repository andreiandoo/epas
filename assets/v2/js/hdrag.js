/**
 * bilete.online v2: drag a horizontal strip with the mouse.
 *
 * A touch screen scrolls `overflow-x:auto` by itself; a mouse has nothing to grab unless the
 * strip happens to show a scrollbar, so a rail of cards or a week of dates reads as a dead end.
 * This gives any such strip the behaviour people expect from one: press and drag to scroll,
 * shift-wheel or plain wheel to scroll sideways, and a drag never lands as a click on whatever
 * was under the cursor.
 *
 *   EPHDrag(el)                      // the strip itself scrolls
 *   EPHDrag(el, { onSync: fn })      // fn({ over, atStart, atEnd }) after every change, for arrows
 *
 * Calling it twice on the same element is a no-op, so a page may wire a strip that is rebuilt.
 */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function EPHDrag(box, opts) {
    if (!box || box.dataset.hdrag === '1') return null;
    box.dataset.hdrag = '1';
    opts = opts || {};

    var down = null, moved = false;

    function state() {
      var over = box.scrollWidth - box.clientWidth > 4;
      return {
        over: over,
        atStart: !over || box.scrollLeft <= 2,
        atEnd: !over || box.scrollLeft >= box.scrollWidth - box.clientWidth - 2
      };
    }
    function sync() {
      box.classList.toggle('is-scrollable', state().over);
      if (opts.onSync) opts.onSync(state());
    }

    box.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    if (window.ResizeObserver) {
      try { new ResizeObserver(sync).observe(box); } catch (e) {}
    }

    box.addEventListener('pointerdown', function (e) {
      // Touch already does this natively, and a right-click must stay a right-click.
      if (e.pointerType === 'touch' || e.button !== 0) return;
      if (e.target.closest && e.target.closest('input, textarea, select')) return;
      down = { x: e.clientX, left: box.scrollLeft, id: e.pointerId };
      moved = false;
    });

    box.addEventListener('pointermove', function (e) {
      if (!down || e.pointerId !== down.id) return;
      var dx = e.clientX - down.x;
      if (!moved && Math.abs(dx) > 3) {
        moved = true;
        box.classList.add('is-dragging');
        try { box.setPointerCapture(down.id); } catch (err) {}
      }
      if (moved) box.scrollLeft = down.left - dx;
    });

    ['pointerup', 'pointercancel'].forEach(function (t) {
      box.addEventListener(t, function () {
        if (down) {
          try { box.releasePointerCapture(down.id); } catch (err) {}
        }
        down = null;
        box.classList.remove('is-dragging');
      });
    });

    // Capture phase: swallow the click the drag would otherwise deliver to the card underneath.
    box.addEventListener('click', function (e) {
      if (!moved) return;
      moved = false;
      e.preventDefault();
      e.stopPropagation();
    }, true);

    box.addEventListener('wheel', function (e) {
      if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;   // a real horizontal wheel: leave it
      if (box.scrollWidth - box.clientWidth <= 4) return;
      e.preventDefault();
      box.scrollLeft += e.deltaY;
    }, { passive: false });

    /** Move by roughly a screenful, for the arrow buttons next to the strip. */
    box.epScrollBy = function (dir) {
      box.scrollBy({ left: dir * Math.round(box.clientWidth * 0.8), behavior: reduce ? 'auto' : 'smooth' });
    };
    box.epSync = sync;
    sync();
    return box;
  }

  window.EPHDrag = EPHDrag;
})();
