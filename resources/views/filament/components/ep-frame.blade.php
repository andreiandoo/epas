{{-- Cadrul panoului marketplace (desktop): meniul din stânga și bara de atenție din dreapta pe toată
     înălțimea, bara de sus între ele. Meniul din stânga stă îngust (doar iconițe) și se deschide la hover
     peste conținut, sau rămâne deschis dacă operatorul îl fixează din ☰. Același fundal peste tot.
     Randat în <head>, ca stilurile să fie gata înainte de conținut. Telefonul rămâne pe layout-ul obișnuit. --}}
<script>
    (function () {
        var html = document.documentElement;
        if (window.matchMedia('(min-width: 1024px)').matches) {
            html.classList.add('ep-frame');
            html.classList.remove('ep-rail-collapsed'); // vechiul mod „doar iconițe” e înlocuit de cadru
            try { if (localStorage.getItem('ep-nav-pinned') === '1') html.classList.add('ep-nav-pinned'); } catch (e) {}
        }
    })();
</script>
<style>
    :root {
        --ep-top: 56px; --ep-left: 64px; --ep-left-open: 272px; --ep-right: 56px;
        --ep-frame-bg: #ffffff; --ep-frame-line: #e3e7ec; --ep-frame-fg: #17202a; --ep-frame-muted: #66717f; --ep-frame-hover: #f2f4f7;
    }
    .dark {
        --ep-frame-bg: #0c0f13; --ep-frame-line: #161a21; --ep-frame-fg: #e6eaef; --ep-frame-muted: #8a95a3; --ep-frame-hover: #1a2027;
    }
    .ep-frame-brand, .ep-gicon { display: none; }

    @media (min-width: 1024px) {
        html.ep-frame { --ep-left-now: var(--ep-left); }
        html.ep-frame.ep-nav-pinned { --ep-left-now: var(--ep-left-open); }
        html.ep-frame body { border-top: 0 !important; }

        /* ---- bara de sus: între cele două laterale ---- */
        html.ep-frame .fi-custom-topbar {
            position: fixed !important; top: 0 !important; left: var(--ep-left-now) !important; right: var(--ep-right) !important;
            height: var(--ep-top); margin: 0 !important; padding: 0 16px 0 8px !important; z-index: 37 !important;
            background: var(--ep-frame-bg) !important; border-bottom: 1px solid var(--ep-frame-line) !important;
            display: flex; align-items: center; transition: left .18s cubic-bezier(.2,.8,.2,1);
        }
        /* epas-skin face bara transparentă pe dark și îi pune umbră; în cadru are fundalul barelor laterale */
        html.ep-frame body .fi-custom-topbar.fi-custom-topbar.fi-custom-topbar.fi-custom-topbar { background-color: var(--ep-frame-bg) !important; background-image: none !important; box-shadow: none !important; }
        html.ep-frame .fi-custom-topbar > .ep-topbar-row { width: 100%; max-width: none; height: var(--ep-top); align-items: center; gap: 12px; }
        html.ep-frame .fi-custom-topbar > .ep-topbar-row > * { align-self: center; }
        html.ep-frame .ep-frame-brand img { height: 34px; width: auto; display: block; }
        html.ep-frame .ep-frame-brand {
            display: inline-flex; align-items: center; flex: none; height: 36px; padding: 0 4px; margin-right: 2px;
            font-size: 19px; font-weight: 800; letter-spacing: -.01em; line-height: 1; color: #10b981; text-decoration: none; white-space: nowrap;
        }
        html.ep-frame .ep-sidebar-toggle { width: 36px !important; height: 36px !important; border-radius: 9px; color: var(--ep-frame-muted) !important; }
        html.ep-frame .ep-sidebar-toggle:hover, html.ep-frame .ep-sidebar-toggle[aria-pressed="true"] { background: var(--ep-frame-hover) !important; color: var(--ep-frame-fg) !important; }

        /* Dreapta barei: limbă, site public, cont (și comutatorul de marketplace) */
        html.ep-frame .fi-custom-topbar .ep-tb-btn {
            height: 36px; padding: 0 10px !important; gap: 8px; border-radius: 9px !important; border: 1px solid transparent !important;
            background: transparent !important; color: var(--ep-frame-muted) !important; font-size: 13px; font-weight: 500;
        }
        html.ep-frame .fi-custom-topbar .ep-tb-btn:hover, html.ep-frame .fi-custom-topbar .ep-tb-btn:focus-visible {
            background: var(--ep-frame-hover) !important; color: var(--ep-frame-fg) !important; outline: none;
        }
        html.ep-frame .fi-custom-topbar .ep-tb-btn > svg:last-child { width: 14px; height: 14px; opacity: .6; }
        html.ep-frame .fi-custom-topbar .ep-tb-lang svg { width: 18px; height: 18px; border-radius: 3px; }
        html.ep-frame .fi-custom-topbar .ep-tb-lang .uppercase { font-size: 12px; letter-spacing: .04em; }
        html.ep-frame .fi-custom-topbar .ep-tb-link { width: 36px; padding: 0 !important; justify-content: center; }
        html.ep-frame .fi-custom-topbar .ep-tb-link span { display: none !important; }
        html.ep-frame .fi-custom-topbar .ep-tb-link svg { width: 17px; height: 17px; }
        html.ep-frame .fi-custom-topbar .fi-user-menu { padding-left: 12px; margin-left: 4px; border-left: 1px solid var(--ep-frame-line); }
        html.ep-frame .fi-custom-topbar .ep-tb-user { padding: 0 6px 0 4px !important; color: var(--ep-frame-fg) !important; }
        html.ep-frame .fi-custom-topbar .ep-tb-user .fi-avatar { width: 28px !important; height: 28px !important; border-radius: 50%; box-shadow: 0 0 0 2px var(--ep-frame-line); }
        html.ep-frame .fi-custom-topbar .ep-tb-switch { color: #f2b53d !important; border-color: color-mix(in srgb, #f2b53d 35%, transparent) !important; }
        html.ep-frame .fi-custom-topbar .ep-tb-panel {
            background: var(--ep-frame-bg) !important; border: 1px solid var(--ep-frame-line) !important; border-radius: 12px !important;
            box-shadow: 0 16px 40px rgba(0,0,0,.28) !important; padding: 6px !important; margin-top: 10px !important;
        }
        html.ep-frame .fi-custom-topbar .ep-tb-panel a, html.ep-frame .fi-custom-topbar .ep-tb-panel button[type="submit"] { border-radius: 8px; }
        html.ep-frame .fi-custom-topbar .ep-tb-panel a:hover, html.ep-frame .fi-custom-topbar .ep-tb-panel button[type="submit"]:hover { background: var(--ep-frame-hover) !important; }
        html.ep-frame .fi-custom-topbar .ep-tb-panel .border-b { border-color: var(--ep-frame-line) !important; }

        /* ---- conținutul: lasă loc cadrului ---- */
        html.ep-frame .fi-layout { padding-left: var(--ep-left-now); transition: padding-left .18s cubic-bezier(.2,.8,.2,1); }
        html.ep-frame .fi-main-ctn { padding-top: calc(var(--ep-top) + 20px); }

        /* ---- meniul din stânga: pe toată înălțimea ---- */
        html.ep-frame .fi-sidebar.fi-main-sidebar {
            position: fixed !important; top: 0 !important; left: 0 !important; bottom: 0 !important;
            height: 100dvh !important; width: var(--ep-left) !important;
            translate: none !important; z-index: 39 !important;
            background: var(--ep-frame-bg) !important; border-right: 1px solid var(--ep-frame-line);
            box-shadow: none !important; overflow-x: hidden;
        }
        html.ep-frame.ep-nav-pinned .fi-sidebar.fi-main-sidebar { width: var(--ep-left-open) !important; }
        /* Deschis la hover: lățimea se schimbă instant, dar e decupat la 64px; apoi decupajul se retrage
           (fără reflow, ca un sertar). Umbra rămâne în afara decupajului. */
        html.ep-frame.ep-nav-open:not(.ep-nav-pinned) .fi-sidebar.fi-main-sidebar {
            width: var(--ep-left-open) !important;
            clip-path: inset(0 calc(var(--ep-left-open) - var(--ep-left)) 0 0);
            transition: clip-path .24s cubic-bezier(.2,.8,.2,1), box-shadow .24s ease;
        }
        html.ep-frame.ep-nav-open.ep-nav-reveal:not(.ep-nav-pinned) .fi-sidebar.fi-main-sidebar {
            clip-path: inset(0 -80px 0 0);
            box-shadow: 24px 0 48px rgba(0,0,0,.32) !important;
        }
        html.ep-frame.ep-nav-open:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-label,
        html.ep-frame.ep-nav-open:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group-label,
        html.ep-frame.ep-nav-open:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group-collapse-btn,
        html.ep-frame.ep-nav-open:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-badge-ctn {
            opacity: 0; transition: opacity .14s ease;
        }
        html.ep-frame.ep-nav-open.ep-nav-reveal:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-label,
        html.ep-frame.ep-nav-open.ep-nav-reveal:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group-label,
        html.ep-frame.ep-nav-open.ep-nav-reveal:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group-collapse-btn,
        html.ep-frame.ep-nav-open.ep-nav-reveal:not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-badge-ctn {
            opacity: 1; transition-delay: .08s;
        }
        @media (prefers-reduced-motion: reduce) {
            html.ep-frame .fi-sidebar.fi-main-sidebar, html.ep-frame .fi-main-sidebar * { transition: none !important; }
        }
        html.ep-frame .fi-main-sidebar .fi-sidebar-header,
        html.ep-frame .fi-main-sidebar .ep-sidebar-logo { display: none !important; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-nav { background: transparent !important; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-footer { display: none !important; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-nav { padding-top: 12px !important; scrollbar-width: none; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-nav::-webkit-scrollbar { width: 0; }
        html.ep-frame:is(.ep-nav-open, .ep-nav-pinned) .fi-main-sidebar .fi-sidebar-nav { scrollbar-width: thin; scrollbar-color: color-mix(in srgb, var(--ep-frame-muted) 45%, transparent) transparent; }
        html.ep-frame:is(.ep-nav-open, .ep-nav-pinned) .fi-main-sidebar .fi-sidebar-nav::-webkit-scrollbar { width: 6px; }
        html.ep-frame:is(.ep-nav-open, .ep-nav-pinned) .fi-main-sidebar .fi-sidebar-nav::-webkit-scrollbar-thumb { background: color-mix(in srgb, var(--ep-frame-muted) 45%, transparent); border-radius: 6px; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-item-btn,
        html.ep-frame .fi-main-sidebar .fi-sidebar-group-btn { white-space: nowrap; }

        /* Iconița grupei (adăugată din script) */
        html.ep-frame .fi-main-sidebar .ep-gicon { display: inline-grid; place-items: center; width: 24px; height: 24px; flex: none; color: #10b981; }
        html.ep-frame .fi-main-sidebar .ep-gicon svg { width: 22px; height: 22px; }
        /* iconița proprie a grupei Chat, pe aceeași culoare */
        html.ep-frame .fi-main-sidebar .fi-sidebar-group-btn > svg:first-child { color: #10b981 !important; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-group.fi-active .ep-gicon,
        html.ep-frame .fi-main-sidebar .fi-sidebar-group-btn:hover .ep-gicon { color: #34d399; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-group-btn { gap: 10px; }

        /* Închis: o iconiță pe grupă (ca la Chat), elementele grupei apar la deschidere */
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-label,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group-label,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group-collapse-btn,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-grouped-border,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .ep-trigger-arrow,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-badge-ctn,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-badge,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-footer,
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group.ep-has-gicon .fi-sidebar-group-items { display: none !important; }
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group-btn { justify-content: center; padding: 8px 0 !important; border-radius: 8px; }
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group.fi-active .fi-sidebar-group-btn { background: var(--ep-frame-hover); }
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-item-btn { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
        html.ep-frame:not(.ep-nav-open):not(.ep-nav-pinned) .fi-main-sidebar .fi-sidebar-group { padding-left: 0 !important; padding-right: 0 !important; }

        /* Meniul secundar (Microservicii, Comunicări…): același fundal, pe toată înălțimea */
        html.ep-frame .ep-secondary-sidebar {
            top: 0 !important; height: 100dvh !important; z-index: 38 !important;
            background: var(--ep-frame-bg) !important; border-right: 1px solid var(--ep-frame-line) !important;
        }
        html.ep-frame .ep-secondary-sidebar .ep-secondary-sidebar-header,
        html.ep-frame .ep-secondary-sidebar nav,
        html.ep-frame .ep-secondary-sidebar .fi-sidebar-nav { background: transparent !important; }

        /* Bara de atenție din dreapta: pe toată înălțimea */
        html.ep-frame .epc-rail, html.ep-frame .epc-drawer { top: 0 !important; }
        html.ep-frame .epc-rail { z-index: 42 !important; }
        html.ep-frame .epc-drawer { z-index: 41 !important; }

        /* Tooltip-ul vechi pentru modul „doar iconițe” nu mai e necesar */
        html.ep-frame .ep-rail-tooltip { display: none !important; }
    }
</style>
<script>
    (function () {
        if (window.__epFrameInit) return;
        window.__epFrameInit = true;
        var html = document.documentElement;
        var openT = null, closeT = null;

        // Iconițe pentru grupele de meniu (Filament nu permite iconiță și pe grupă, și pe elemente).
        var P = 'fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"';
        var GICONS = {
            sales: '<svg ' + P + '><path d="M6 7h12l-1 13H7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>',
            organizers: '<svg ' + P + '><path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-5h6v5M8 9.5h.01M12 9.5h.01M16 9.5h.01M8 13h.01M12 13h.01M16 13h.01"/></svg>',
            places: '<svg ' + P + '><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
            shop: '<svg ' + P + '><path d="M4 9l1.5-5h13L20 9"/><path d="M4 9v11h16V9"/><path d="M4 9a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M10 20v-5h4v5"/></svg>',
            content: '<svg ' + P + '><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/></svg>',
            tools: '<svg ' + P + '><path d="M15 4a5 5 0 0 0-4.6 6.9l-6.9 6.9a1.8 1.8 0 0 0 2.6 2.6l6.9-6.9A5 5 0 0 0 20 9l-3 3-3-1-1-3 3-3z"/></svg>',
            settings: '<svg ' + P + '><circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M2.5 12h3M18.5 12h3M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1"/></svg>',
            help: '<svg ' + P + '><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5v.01"/></svg>',
            reports: '<svg ' + P + '><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
            finance: '<svg ' + P + '><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h3"/></svg>',
            marketing: '<svg ' + P + '><path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12"/></svg>',
            users: '<svg ' + P + '><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 20a6.5 6.5 0 0 0-2.5-5.1"/></svg>',
            folder: '<svg ' + P + '><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>'
        };
        var MAP = [
            [/sales|v[aâ]nz[aă]ri|comenzi|orders/i, 'sales'],
            [/organizer|organizator/i, 'organizers'],
            [/loca[tț]i|venue|loc/i, 'places'],
            [/shop|magazin/i, 'shop'],
            [/content|con[tț]inut|blog/i, 'content'],
            [/tools|unelte|instrumente/i, 'tools'],
            [/settings|set[aă]ri|configur/i, 'settings'],
            [/help|ajutor|suport|support/i, 'help'],
            [/report|rapoart|statistic|analytic/i, 'reports'],
            [/financ|factur|plat|decont|billing|payout/i, 'finance'],
            [/marketing|campan|newsletter|comunic/i, 'marketing'],
            [/user|utilizator|clien|customer|echip|team|artist/i, 'users']
        ];
        function iconFor(label) {
            for (var i = 0; i < MAP.length; i++) if (MAP[i][0].test(label)) return GICONS[MAP[i][1]];
            return GICONS.folder;
        }
        function decorateGroups() {
            document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group').forEach(function (g) {
                var btn = g.querySelector(':scope > .fi-sidebar-group-btn');
                var label = btn && btn.querySelector('.fi-sidebar-group-label');
                if (!btn || !label) return;
                g.classList.add('ep-has-gicon');
                // Grupele care au deja iconiță din Filament (ex. Chat) o păstrează.
                if (btn.querySelector('svg:not(.fi-icon-btn-icon)') && !btn.querySelector('.ep-gicon')) {
                    var own = btn.firstElementChild;
                    if (own && own.tagName.toLowerCase() === 'svg') return;
                }
                if (btn.querySelector('.ep-gicon')) return;
                var span = document.createElement('span');
                span.className = 'ep-gicon';
                span.setAttribute('aria-hidden', 'true');
                span.innerHTML = iconFor(label.textContent.trim());
                btn.insertBefore(span, label);
                btn.setAttribute('title', label.textContent.trim());
            });
        }

        function framed() { return html.classList.contains('ep-frame'); }
        function pinned() { return html.classList.contains('ep-nav-pinned'); }
        function secondaryOpen() { return document.body && document.body.classList.contains('ep-secondary-sidebar-open'); }
        function repositionSecondary() { setTimeout(function () { try { window.dispatchEvent(new Event('resize')); } catch (e) {} }, 200); }
        var hideT = null;
        function open() {
            clearTimeout(closeT);
            clearTimeout(hideT);
            if (pinned()) return;
            if (html.classList.contains('ep-nav-open')) { html.classList.add('ep-nav-reveal'); return; }
            clearTimeout(openT);
            openT = setTimeout(function () {
                // Întâi forma deschisă (decupată la 64px), apoi în cadrul următor se dezvăluie.
                html.classList.add('ep-nav-open');
                requestAnimationFrame(function () { requestAnimationFrame(function () { html.classList.add('ep-nav-reveal'); }); });
                repositionSecondary();
            }, 110);
        }
        function close() {
            clearTimeout(openT);
            if (secondaryOpen()) return; // meniul principal rămâne deschis lângă cel secundar
            clearTimeout(closeT);
            closeT = setTimeout(function () {
                html.classList.remove('ep-nav-reveal');
                // După ce s-a strâns decupajul, revenim la forma doar cu iconițe.
                hideT = setTimeout(function () { if (!html.classList.contains('ep-nav-reveal')) html.classList.remove('ep-nav-open'); }, 240);
            }, 220);
        }

        // ☰ din bara de sus: fixează meniul deschis (alegerea fiecărui operator, ținută minte).
        window.epToggleNavPin = function () {
            var on = !pinned();
            html.classList.toggle('ep-nav-pinned', on);
            html.classList.remove('ep-nav-open', 'ep-nav-reveal');
            try { localStorage.setItem('ep-nav-pinned', on ? '1' : '0'); } catch (e) {}
            syncToggle();
            repositionSecondary();
        };
        function syncToggle() {
            document.querySelectorAll('.ep-sidebar-toggle').forEach(function (b) {
                b.setAttribute('aria-pressed', pinned() ? 'true' : 'false');
                b.setAttribute('title', pinned() ? 'Meniul rămâne deschis. Click ca să se deschidă doar la hover.' : 'Ține meniul din stânga deschis');
            });
        }

        // Ascultători pe document, nu pe meniu: Filament re-randează meniul când deschizi o grupă,
        // iar ascultătorii puși direct pe element s-ar pierde.
        function inMenu(el) { return !!(el && el.closest && el.closest('.fi-main-sidebar, #ep-secondary-sidebar')); }
        document.addEventListener('mouseover', function (e) {
            if (!framed()) return;
            if (inMenu(e.target)) open();
        });
        document.addEventListener('mouseout', function (e) {
            if (!framed() || !inMenu(e.target)) return;
            if (!inMenu(e.relatedTarget)) close();
        });
        document.addEventListener('focusin', function (e) { if (framed() && inMenu(e.target)) open(); });
        // Plasă de siguranță: dacă mouse-ul e clar în afara meniului și meniul a rămas deschis, îl închidem.
        var lastCheck = 0;
        document.addEventListener('mousemove', function (e) {
            if (!framed() || pinned() || !html.classList.contains('ep-nav-open') || secondaryOpen()) return;
            var now = Date.now();
            if (now - lastCheck < 150) return;
            lastCheck = now;
            var side = document.querySelector('.fi-sidebar.fi-main-sidebar');
            if (side && e.clientX > side.getBoundingClientRect().right + 12 && !inMenu(e.target)) close();
        }, { passive: true });

        function bind() {
            syncToggle();
            if (!framed()) return;
            decorateGroups();
            var side = document.querySelector('.fi-sidebar.fi-main-sidebar');
            if (!side || side.__epFrameBound) return;
            side.__epFrameBound = true;
            if (document.body && !document.body.__epFrameObs) {
                document.body.__epFrameObs = true;
                new MutationObserver(function () {
                    var s = document.querySelector('.fi-sidebar.fi-main-sidebar');
                    if (!secondaryOpen() && !(s && s.matches(':hover'))) close();
                }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
            }
            // Filament re-randează meniul la unele acțiuni; refacem iconițele grupelor.
            new MutationObserver(function () { decorateGroups(); }).observe(side, { childList: true, subtree: true });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && html.classList.contains('ep-nav-open') && !secondaryOpen()) html.classList.remove('ep-nav-open', 'ep-nav-reveal');
        });
        window.addEventListener('resize', function () {
            var desk = window.matchMedia('(min-width: 1024px)').matches;
            if (desk !== framed()) {
                html.classList.toggle('ep-frame', desk);
                if (!desk) html.classList.remove('ep-nav-open');
                bind();
            }
        });
        document.addEventListener('DOMContentLoaded', bind);
        document.addEventListener('livewire:navigated', bind);
        if (document.readyState !== 'loading') bind();
    })();
</script>
