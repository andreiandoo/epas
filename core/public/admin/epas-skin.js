(function () {
  // --------- Heroicons (outline, 20px) ca SVG inline ----------
  const HI = {
    'document-text': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M19.5 14.25v3.375A2.625 2.625 0 0 1 16.875 20.25H7.125A2.625 2.625 0 0 1 4.5 17.625V6.375A2.625 2.625 0 0 1 7.125 3.75h5.25L19.5 10.875v3.375z"/><path stroke-width="1.5" d="M12.375 3.75v4.5A2.625 2.625 0 0 0 15 10.875h4.5"/></svg>',
    'flag': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 3v18"/><path stroke-width="1.5" d="M3 4.5c6 0 6-3 12-3v12c-6 0-6 3-12 3V4.5z"/></svg>',
    'calendar-days': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="1.5"/><path stroke-width="1.5" d="M16 2v4M8 2v4M3 10h18"/></svg>',
    'map-pin': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M12 22s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="11" r="3" stroke-width="1.5"/></svg>',
    'photo': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2" stroke-width="1.5"/><path stroke-width="1.5" d="M3 15l4.5-4.5 3 3L14 10l7 7"/></svg>',
    'pencil-square': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M15.232 5.232l3.536 3.536M4 20h4l10.768-10.768a2.5 2.5 0 0 0-3.536-3.536L4 16v4z"/></svg>',
    'tag': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M20.59 13.41 11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r="1.5" stroke-width="1.5"/></svg>',
    'ticket': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 8a3 3 0 0 1 3-3h12v4a2 2 0 1 0 0 4v4H6a3 3 0 0 1-3-3v-2a2 2 0 1 0 0-4V8z"/></svg>',
    'cog-6-tooth': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M10.325 4.317l.675-1.317h2l.675 1.317 1.517.44.94-.94 1.414 1.414-.94.94.44 1.517L20 9.325v2l-1.317.675-.44 1.517.94.94-1.414 1.414-.94-.94-1.517.44L13.675 20h-2l-.675-1.317-1.517-.44-.94.94-1.414-1.414.94-.94-.44-1.517L4 11.675v-2l1.317-.675.44-1.517-.94-.94L6.23 4.13l.94.94 1.517-.44z"/><circle cx="12" cy="12" r="3" stroke-width="1.5"/></svg>',
    'arrow-path': '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 12a9 9 0 0 1 15.54-5.94M21 12a9 9 0 0 1-15.54 5.94"/><path stroke-width="1.5" d="M3 3v6h6M21 21v-6h-6"/></svg>',
  };
  const icon = (name) => HI[name] || '';

  // --------- Din hook: identifică grid-ul de acțiuni și fă-l sticky jos ----------
  function markActions() {
    document.querySelectorAll('.fi-main form').forEach(function (form) {
      const grids = form.querySelectorAll('.fi-sc.fi-grid');
      let actions = null;
      grids.forEach(function (g) {
        if (g.querySelector('button[type="submit"], .fi-btn[type="submit"]')) actions = g;
      });
      if (actions) actions.classList.add('fi-sticky-actions');
    });
  }

  // --------- Din hook: construiește un meniu de ancore sus, din [data-ep-section] ----------
  function buildFormAnchors() {
    const form = document.querySelector('.fi-main form');
    if (!form) return;

    // Dacă există deja (poate vine dintr-un view custom), nu dublăm.
    const existing = document.querySelector('.ep-form-nav');
    if (existing) return;

    const secs = form.querySelectorAll('[data-ep-section]');
    if (!secs.length) return;

    const wrap = document.createElement('div');
    wrap.className = 'ep-form-nav';

    secs.forEach(function (sec) {
      const id    = sec.getAttribute('id') || sec.getAttribute('data-ep-id') || '';
      const label = sec.getAttribute('data-ep-label') || 'Section';
      const icn   = sec.getAttribute('data-ep-icon') || '';
      if (!id) return;
      const a = document.createElement('a');
      a.href = '#' + id;
      a.innerHTML = (icn ? icon(icn) : '') + '<span>'+label+'</span>';
      wrap.appendChild(a);
    });

    const main = document.querySelector('.fi-main');
    if (main) main.prepend(wrap);
  }

  // --------- Mutarea logicii din boot() – adaugă clasă pe body în funcție de URL ----------
  function applyRouteClasses() {
    const p = (window.location.pathname || '').toLowerCase();
    // aproximăm rutele Filament pentru artists
    if (p.includes('/admin/artists')) {
      document.body.classList.add('ep-route-artists');
    } else {
      document.body.classList.remove('ep-route-artists');
    }
  }

  function start() {
    applyRouteClasses();
    markActions();
    buildFormAnchors();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  if (window.Livewire) {
    try { window.Livewire.hook && window.Livewire.hook('message.processed', start); } catch(e) {}
    try { window.Livewire.on && window.Livewire.on('message.processed', start); } catch(e) {}
  }
})();
