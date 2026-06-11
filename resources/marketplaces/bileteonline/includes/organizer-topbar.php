<?php
/**
 * bilete.online — Organizer dashboard top bar (v3 design).
 *
 * Rendered by each organizer page right after opening the main column div
 * (see organizer-sidebar.php for the layout contract). Provides: mobile menu
 * toggle, catalog search, notifications dropdown, organizer user menu, and a
 * "pending account" banner. Wired to BileteOnlineAuth / BileteOnlineAPI.
 */

// Skip the public header.js / notification components in scripts.php — the
// organizer area has its own chrome.
$skipJsComponents = true;
?>

<!-- Top bar -->
<header class="sticky top-0 z-40 border-b border-ink/10 bg-paper/95 backdrop-blur-md">
    <div class="flex h-16 items-center justify-between gap-3 px-4 lg:px-8">
        <!-- Mobile sidebar toggle -->
        <button type="button" onclick="toggleSidebar()" class="grid h-10 w-10 -ml-2 place-items-center rounded-lg text-ink-soft transition hover:bg-paper-2 lg:hidden" aria-label="Meniu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <!-- Search -->
        <div class="hidden max-w-md flex-1 items-center md:flex">
            <div class="relative w-full" id="org-search-container">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input id="org-search-input" type="text" autocomplete="off" placeholder="Caută activități, participanți…"
                       class="w-full rounded-full border-2 border-ink/10 bg-paper-2/70 py-2.5 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-ink">
                <div id="org-search-results" class="absolute left-0 right-0 top-full z-50 mt-2 hidden max-h-80 overflow-auto rounded-2xl border-2 border-ink bg-paper shadow-deep"></div>
            </div>
        </div>

        <!-- Right actions -->
        <div class="ml-auto flex items-center gap-2">
            <a href="/organizator/events?action=create" class="hidden items-center gap-2 rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d sm:flex">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Activitate nouă
            </a>

            <!-- Notifications -->
            <div class="relative" x-data="{ open: false }" @click.outside="open=false">
                <button type="button" @click="open=!open" class="relative grid h-10 w-10 place-items-center rounded-full text-ink-soft transition hover:bg-paper-2" aria-label="Notificări">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span id="notification-badge" class="absolute right-1.5 top-1.5 hidden h-2.5 w-2.5 rounded-full bg-vermilion"></span>
                </button>
                <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-2xl border-2 border-ink bg-paper shadow-deep">
                    <div class="flex items-center justify-between border-b-2 border-dashed border-ink/15 p-4">
                        <h3 class="font-display text-lg font-bold">Notificări</h3>
                        <span id="notification-count" class="text-xs font-bold text-vermilion">0 noi</span>
                    </div>
                    <div id="notifications-list" class="max-h-80 overflow-y-auto">
                        <div class="p-6 text-center text-sm text-ink-soft">Nu ai notificări noi.</div>
                    </div>
                    <div class="border-t border-ink/10 p-3 text-center">
                        <a href="/organizator/notificari" class="text-sm font-bold text-vermilion underline-wobble">Vezi toate notificările</a>
                    </div>
                </div>
            </div>

            <!-- User menu -->
            <div class="relative" x-data="{ open: false }" @click.outside="open=false">
                <button type="button" @click="open=!open" class="flex items-center gap-2 rounded-full p-1 transition hover:bg-paper-2" aria-haspopup="menu" :aria-expanded="open">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-forest text-sm font-bold text-paper" id="topbar-org-initials">--</span>
                    <svg class="hidden h-4 w-4 text-ink-soft sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-2xl border-2 border-ink bg-paper py-2 shadow-deep" role="menu">
                    <div class="border-b border-ink/10 px-4 py-3">
                        <p class="font-bold leading-tight" id="topbar-org-name">Organizator</p>
                        <p class="truncate text-xs text-ink-soft" id="topbar-org-email"></p>
                    </div>
                    <a href="/organizator/setari" class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition hover:bg-paper-2" role="menuitem">
                        <svg class="h-4 w-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        Setări cont
                    </a>
                    <a href="/organizator/help" class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition hover:bg-paper-2" role="menuitem">
                        <svg class="h-4 w-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Ajutor & suport
                    </a>
                    <div class="my-1 border-t border-ink/10"></div>
                    <button type="button" onclick="if(window.BileteOnlineAuth&&BileteOnlineAuth.logoutOrganizer){BileteOnlineAuth.logoutOrganizer();}else{location.href='/organizator/login';}"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm font-bold text-vermilion transition hover:bg-vermilion/5" role="menuitem">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Deconectare
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Pending account banner -->
<div id="pending-account-banner" class="hidden border-b border-ochre/30 bg-ochre/10">
    <div class="flex items-center justify-between gap-4 px-4 py-3 lg:px-8">
        <div class="flex items-center gap-3">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-ochre/20 text-ochre">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </span>
            <div>
                <p class="text-sm font-bold">Contul tău este în așteptare</p>
                <p class="text-xs text-ink-soft">Completează profilul și încarcă documentele necesare (CI/CUI) pentru a-l activa.</p>
            </div>
        </div>
        <a href="/organizator/setari#contract" class="whitespace-nowrap rounded-full bg-ink px-4 py-2 text-sm font-bold text-paper transition hover:bg-ink-2">Completează acum</a>
    </div>
</div>

<script>
// Populate topbar identity + pending banner from cached organizer data.
(function () {
    try {
        var org = JSON.parse(localStorage.getItem('bileteonline_organizer_data') || 'null');
        if (! org) return;
        var name = org.public_name || org.name || org.company_name || 'Organizator';
        var set = function (id, v) { var el = document.getElementById(id); if (el && v != null) el.textContent = v; };
        set('topbar-org-name', name);
        set('topbar-org-email', org.email || '');
        var parts = String(name).trim().split(/\s+/);
        set('topbar-org-initials', (parts.length > 1 ? (parts[0][0] + parts[parts.length - 1][0]) : name.substring(0, 2)).toUpperCase());
        if (org.status && org.status !== 'active') {
            var b = document.getElementById('pending-account-banner');
            if (b) b.classList.remove('hidden');
        }
    } catch (e) {}
})();

// Catalog instant search (organizer's own activities/events).
window.addEventListener('load', function () {
    var input = document.getElementById('org-search-input');
    var box = document.getElementById('org-search-results');
    if (! input || ! box) return;
    var cache = null, timer = null;

    function fmtDate(d) {
        if (! d) return '';
        var dt = new Date(d); if (isNaN(dt)) return '';
        var m = ['ian','feb','mar','apr','mai','iun','iul','aug','sep','oct','nov','dec'];
        return dt.getDate() + ' ' + m[dt.getMonth()] + ' ' + dt.getFullYear();
    }
    async function load() {
        if (cache) return cache;
        try {
            if (typeof BileteOnlineAPI === 'undefined' || ! BileteOnlineAPI.organizer) return [];
            var r = await BileteOnlineAPI.organizer.getEvents();
            cache = (r && r.data && (r.data.items || r.data)) || [];
            return Array.isArray(cache) ? cache : [];
        } catch (e) { return []; }
    }
    async function run(q) {
        if (q.length < 3) { box.classList.add('hidden'); return; }
        var rows = await load();
        var t = q.toLowerCase();
        var hits = rows.filter(function (e) {
            var name = String(e.name || e.title || '').toLowerCase();
            var city = String(e.venue_city || (e.city && e.city.name) || '').toLowerCase();
            return name.includes(t) || city.includes(t);
        }).slice(0, 8);
        if (hits.length === 0) {
            box.innerHTML = '<div class="p-4 text-center text-sm text-ink-soft">Niciun rezultat.</div>';
        } else {
            box.innerHTML = hits.map(function (e) {
                var title = e.name || e.title || '';
                var sub = [fmtDate(e.starts_at || e.start_date), (e.venue_name || (e.venue && e.venue.name) || '')].filter(Boolean).join(' · ');
                return '<a href="/organizator/event/' + e.id + '?action=edit" class="flex items-center gap-3 border-b border-ink/10 p-3 transition last:border-0 hover:bg-paper-2">'
                    + '<span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></span>'
                    + '<span class="min-w-0 flex-1"><span class="block truncate font-bold">' + title.replace(/</g,'&lt;') + '</span><span class="block truncate text-xs text-ink-soft">' + sub.replace(/</g,'&lt;') + '</span></span></a>';
            }).join('');
        }
        box.classList.remove('hidden');
    }
    input.addEventListener('input', function () { clearTimeout(timer); var q = this.value.trim(); timer = setTimeout(function () { run(q); }, 200); });
    input.addEventListener('focus', function () { if (this.value.trim().length >= 3) run(this.value.trim()); });
    document.addEventListener('click', function (e) { if (! e.target.closest('#org-search-container')) box.classList.add('hidden'); });
});
</script>
