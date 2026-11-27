{{-- Cadrul panoului marketplace (desktop): bară sus fixă pe toată lățimea, meniu stânga îngust
     care se deschide la hover peste conținut, bara de atenție în dreapta. Același fundal pe toate trei.
     Randat în <head>, ca stilurile să fie gata înainte de conținut. Telefonul rămâne pe layout-ul obișnuit. --}}
<script>
    (function () {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            document.documentElement.classList.add('ep-frame');
            // Vechiul mod „doar iconițe” e înlocuit de cadru.
            document.documentElement.classList.remove('ep-rail-collapsed');
        }
    })();
</script>
<style>
    :root {
        --ep-top: 56px; --ep-left: 64px; --ep-left-open: 272px;
        --ep-frame-bg: #ffffff; --ep-frame-line: #e3e7ec; --ep-frame-fg: #17202a; --ep-frame-muted: #66717f; --ep-frame-hover: #f2f4f7;
    }
    .dark {
        --ep-frame-bg: #0c0f13; --ep-frame-line: #2b333d; --ep-frame-fg: #e6eaef; --ep-frame-muted: #8a95a3; --ep-frame-hover: #1a2027;
    }
    .ep-frame-brand { display: none; }

    @media (min-width: 1024px) {
        html.ep-frame body { border-top: 0 !important; }

        /* ---- bara de sus: fixă, pe toată lățimea ---- */
        html.ep-frame .fi-custom-topbar {
            position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important;
            height: var(--ep-top); margin: 0 !important; padding: 0 16px 0 0 !important; z-index: 40 !important;
            background: var(--ep-frame-bg) !important; border-bottom: 1px solid var(--ep-frame-line) !important;
            display: flex; align-items: center;
        }
        html.ep-frame .fi-custom-topbar > .ep-topbar-row { width: 100%; max-width: none; }
        html.ep-frame .ep-sidebar-toggle { display: none !important; }
        html.ep-frame .ep-frame-brand {
            display: flex; align-items: center; flex: none; width: 220px; height: var(--ep-top);
            padding-left: 16px; overflow: hidden;
        }
        html.ep-frame .ep-frame-brand .ep-sidebar-logo { display: flex; align-items: center; }
        html.ep-frame .ep-frame-brand img { max-height: 30px; width: auto; }

        /* ---- conținutul: lasă loc cadrului ---- */
        html.ep-frame .fi-layout { padding-left: var(--ep-left); }
        html.ep-frame .fi-main-ctn { padding-top: var(--ep-top); }

        /* ---- meniul din stânga: îngust, se deschide peste conținut ---- */
        html.ep-frame .fi-sidebar.fi-main-sidebar {
            position: fixed !important; top: var(--ep-top) !important; left: 0 !important; bottom: 0 !important;
            height: calc(100dvh - var(--ep-top)) !important; width: var(--ep-left) !important;
            translate: none !important; z-index: 39 !important;
            background: var(--ep-frame-bg) !important; border-right: 1px solid var(--ep-frame-line);
            box-shadow: none !important; overflow-x: hidden; transition: width .18s cubic-bezier(.2,.8,.2,1), box-shadow .18s ease;
        }
        html.ep-frame.ep-nav-open .fi-sidebar.fi-main-sidebar {
            width: var(--ep-left-open) !important;
            box-shadow: 24px 0 48px rgba(0,0,0,.28) !important;
        }
        html.ep-frame .fi-main-sidebar .fi-sidebar-header,
        html.ep-frame .fi-main-sidebar .ep-sidebar-logo { display: none !important; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-nav,
        html.ep-frame .fi-main-sidebar .fi-sidebar-footer { background: transparent !important; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-nav { padding-top: 10px !important; scrollbar-width: none; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-nav::-webkit-scrollbar { width: 0; }
        html.ep-frame.ep-nav-open .fi-main-sidebar .fi-sidebar-nav { scrollbar-width: thin; scrollbar-color: color-mix(in srgb, var(--ep-frame-muted) 45%, transparent) transparent; }
        html.ep-frame.ep-nav-open .fi-main-sidebar .fi-sidebar-nav::-webkit-scrollbar { width: 6px; }
        html.ep-frame.ep-nav-open .fi-main-sidebar .fi-sidebar-nav::-webkit-scrollbar-thumb { background: color-mix(in srgb, var(--ep-frame-muted) 45%, transparent); border-radius: 6px; }

        /* Închis: doar iconițe, centrate */
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-item-label,
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-group-label,
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-group-collapse-btn,
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-item-grouped-border,
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .ep-trigger-arrow,
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-item-badge-ctn,
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-badge,
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-footer { display: none !important; }
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-group-btn { justify-content: center; padding-left: 0 !important; padding-right: 0 !important; }
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-item-btn { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-group { padding-left: 0 !important; padding-right: 0 !important; }
        html.ep-frame:not(.ep-nav-open) .fi-main-sidebar .fi-sidebar-group-items { display: flex !important; flex-direction: column; }
        html.ep-frame .fi-main-sidebar .fi-sidebar-item-btn,
        html.ep-frame .fi-main-sidebar .fi-sidebar-group-btn { white-space: nowrap; }

        /* Meniul secundar (Microservicii, Comunicări…) pornește sub bara de sus */
        html.ep-frame .ep-secondary-sidebar { top: var(--ep-top) !important; height: calc(100dvh - var(--ep-top)) !important; z-index: 38 !important; }

        /* Bara de atenție din dreapta pornește și ea sub bara de sus */
        html.ep-frame .epc-rail, html.ep-frame .epc-drawer { top: var(--ep-top) !important; }

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

        function framed() { return html.classList.contains('ep-frame'); }
        function secondaryOpen() { return document.body && document.body.classList.contains('ep-secondary-sidebar-open'); }
        function open() {
            clearTimeout(closeT);
            if (html.classList.contains('ep-nav-open')) return;
            openT = setTimeout(function () {
                html.classList.add('ep-nav-open');
                setTimeout(function () { try { window.dispatchEvent(new Event('resize')); } catch (e) {} }, 200);
            }, 120);
        }
        function close() {
            clearTimeout(openT);
            // Cât e deschis meniul secundar, meniul principal rămâne deschis lângă el.
            if (secondaryOpen()) return;
            closeT = setTimeout(function () { html.classList.remove('ep-nav-open'); }, 260);
        }

        function bind() {
            if (!framed()) return;
            var side = document.querySelector('.fi-sidebar.fi-main-sidebar');
            if (!side || side.__epFrameBound) return;
            side.__epFrameBound = true;
            side.addEventListener('mouseenter', open);
            side.addEventListener('mouseleave', close);
            side.addEventListener('focusin', open);
            side.addEventListener('focusout', function (e) { if (!side.contains(e.relatedTarget)) close(); });
            var sec = document.getElementById('ep-secondary-sidebar');
            if (sec && !sec.__epFrameBound) {
                sec.__epFrameBound = true;
                sec.addEventListener('mouseenter', function () { clearTimeout(closeT); });
            }
            // Când se închide meniul secundar, închidem și meniul principal.
            if (document.body && !document.body.__epFrameObs) {
                document.body.__epFrameObs = true;
                new MutationObserver(function () {
                    if (!secondaryOpen() && !side.matches(':hover')) close();
                }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
            }
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && html.classList.contains('ep-nav-open') && !secondaryOpen()) html.classList.remove('ep-nav-open');
        });
        window.addEventListener('resize', function () {
            var desk = window.matchMedia('(min-width: 1024px)').matches;
            html.classList.toggle('ep-frame', desk);
            if (!desk) html.classList.remove('ep-nav-open');
            bind();
        });
        document.addEventListener('DOMContentLoaded', bind);
        document.addEventListener('livewire:navigated', bind);
        if (document.readyState !== 'loading') bind();
    })();
</script>
