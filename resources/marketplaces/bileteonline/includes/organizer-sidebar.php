<?php
/**
 * bilete.online — Organizer dashboard sidebar (v3 design).
 *
 * Opens <body> (head.php only emits up to </head>) and the flex layout shell,
 * then renders the dark v3 sidebar (<aside>). The page that includes this file
 * then renders:
 *
 *   require_once includes/config.php;
 *   $currentPage = 'dashboard'; $pageTitle = '…';
 *   require_once includes/head.php;               // <head> only
 *   require_once includes/organizer-sidebar.php;  // <body> + <aside>
 *   <div class="flex flex-1 flex-col min-w-0 min-h-screen">
 *       require_once includes/organizer-topbar.php;
 *       <main class="flex-1">… page content …</main>
 *       require_once includes/organizer-footer.php;   // optional
 *   </div>
 *   require_once includes/scripts.php;            // closes </body></html>
 *
 * Variables a page may set before include:
 *   $currentPage — active nav slug (dashboard|events|participants|sales|
 *                  finance|documents|services|promo|widgets|billing|settings|
 *                  support|help|raport)
 *   $bodyClass   — extra <body> classes
 *
 * Auth + data come from BileteOnlineAuth / BileteOnlineAPI (loaded in head.php).
 */

$currentPage = $currentPage ?? '';
$bodyClass   = $bodyClass ?? '';

// Active/inactive link classes (v3: vermilion active pill on the dark sidebar).
$navLink = function (string $page) use ($currentPage): string {
    $active = $currentPage === $page;
    return 'flex items-center gap-3 mx-2 my-1 rounded-xl px-4 py-3 text-sm font-bold transition group '
        . ($active
            ? 'bg-vermilion text-paper shadow-[0_14px_25px_-18px_rgba(232,69,39,.9)]'
            : 'text-paper/70 hover:bg-paper/10 hover:text-paper');
};
$navIcon = function (string $page) use ($currentPage): string {
    return 'w-5 h-5 shrink-0 ' . ($currentPage === $page ? 'text-paper' : 'text-paper/55 group-hover:text-paper');
};
?>
<body class="min-h-screen flex bg-paper text-ink font-sans antialiased selection:bg-vermilion selection:text-paper<?= $bodyClass ? ' ' . htmlspecialchars($bodyClass, ENT_QUOTES) : '' ?>">

<!-- Sidebar overlay (mobile) -->
<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 z-40 hidden bg-ink/60 backdrop-blur-sm lg:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-paper/10 bg-ink text-paper transition-transform duration-300 lg:static lg:translate-x-0">
    <!-- Brand -->
    <div class="px-5 py-4 border-b border-paper/10">
        <a href="/" class="flex items-center gap-2.5 group">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-vermilion text-paper rotate-[-4deg] transition group-hover:rotate-[4deg]">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M9 7v10" stroke-dasharray="2 2"/></svg>
            </span>
            <span class="leading-none">
                <span class="block font-display text-xl font-bold">bilete<span class="text-vermilion">.</span>online</span>
                <span class="mt-0.5 block font-mono text-[10px] tracking-[.18em] text-paper/45">ORGANIZATOR</span>
            </span>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-2">
        <a href="/organizator/panou" class="<?= $navLink('dashboard') ?>">
            <svg class="<?= $navIcon('dashboard') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>
        <a href="/organizator/events" class="<?= $navLink('events') ?>">
            <svg class="<?= $navIcon('events') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Activități
            <span id="nav-events-count" class="ml-auto rounded-full bg-paper px-2 py-0.5 text-xs font-bold text-ink">0</span>
        </a>
        <a href="/organizator/participanti" class="<?= $navLink('participants') ?>">
            <svg class="<?= $navIcon('participants') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Participanți
        </a>
        <a href="/organizator/vanzari" class="<?= $navLink('sales') ?>">
            <svg class="<?= $navIcon('sales') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Vânzări
        </a>
        <a href="/organizator/sold" class="<?= $navLink('finance') ?>">
            <svg class="<?= $navIcon('finance') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Sold
        </a>
        <a href="/organizator/documente" class="<?= $navLink('documents') ?>">
            <svg class="<?= $navIcon('documents') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Documente
        </a>

        <!-- Marketing -->
        <div class="mt-4 border-t border-paper/10 pt-4">
            <p class="px-5 mb-1 font-mono text-[10px] font-semibold uppercase tracking-[.18em] text-paper/40">Marketing</p>
            <a href="/organizator/servicii" class="<?= $navLink('services') ?>">
                <svg class="<?= $navIcon('services') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Servicii extra
                <span class="ml-auto rounded-full bg-ochre/20 px-2 py-0.5 text-[10px] font-bold text-ochre">NOU</span>
            </a>
            <a href="/organizator/promo" class="<?= $navLink('promo') ?>">
                <svg class="<?= $navIcon('promo') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                Coduri promoționale
            </a>
            <a href="/organizator/widget-uri" class="<?= $navLink('widgets') ?>">
                <svg class="<?= $navIcon('widgets') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                Widget-uri embed
            </a>
        </div>

        <!-- Settings -->
        <div class="mt-4 border-t border-paper/10 pt-4">
            <p class="px-5 mb-1 font-mono text-[10px] font-semibold uppercase tracking-[.18em] text-paper/40">Setări</p>
            <a href="/organizator/facturare" class="<?= $navLink('billing') ?>">
                <svg class="<?= $navIcon('billing') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Facturare
            </a>
            <a href="/organizator/setari" class="<?= $navLink('settings') ?>">
                <svg class="<?= $navIcon('settings') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Cont & companie
            </a>
            <a href="/organizator/suport" class="<?= $navLink('support') ?>">
                <svg class="<?= $navIcon('support') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Tichete suport
                <span id="nav-support-open-count" class="ml-auto hidden rounded-full bg-ochre px-2 py-0.5 text-xs font-bold text-ink">0</span>
            </a>
            <a href="/organizator/help" class="<?= $navLink('help') ?>">
                <svg class="<?= $navIcon('help') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Centru de ajutor
            </a>
        </div>
    </nav>

    <!-- Organizer profile -->
    <div class="sticky bottom-0 border-t border-paper/10 bg-ink p-3">
        <div class="flex items-center gap-3 p-2">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-paper text-xs font-bold text-ink" id="sidebar-org-initials">--</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold" id="sidebar-org-name">Organizator</p>
                <p class="truncate text-xs text-paper/50" id="sidebar-org-plan">—</p>
            </div>
            <button type="button" onclick="if(window.BileteOnlineAuth&&BileteOnlineAuth.logoutOrganizer){BileteOnlineAuth.logoutOrganizer();}else{location.href='/organizator/login';}"
                    class="grid h-8 w-8 place-items-center rounded-lg text-paper/55 transition hover:bg-vermilion/15 hover:text-vermilion" title="Deconectare" aria-label="Deconectare">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </button>
        </div>
    </div>
</aside>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('hidden');
}

// Gate the organizer area + hydrate the sidebar (name/plan/initials + activity
// count) once auth.js + api.js are ready.
window.addEventListener('load', async function () {
    let tries = 0;
    while ((typeof BileteOnlineAuth === 'undefined' || typeof BileteOnlineAPI === 'undefined') && tries < 20) {
        await new Promise(r => setTimeout(r, 100));
        tries++;
    }
    if (typeof BileteOnlineAuth === 'undefined') return;

    // Gate: send non-organizers to the organizer login.
    try {
        if (BileteOnlineAuth.requireOrganizerAuth && ! BileteOnlineAuth.requireOrganizerAuth()) return;
    } catch (e) {}

    const setText = (id, v) => { const el = document.getElementById(id); if (el && v != null) el.textContent = v; };
    const applyOrg = (o) => {
        if (! o) return;
        const name = o.public_name || o.name || o.company_name || 'Organizator';
        setText('sidebar-org-name', name);
        setText('sidebar-org-initials', (name || '?').trim().substring(0, 2).toUpperCase());
        setText('sidebar-org-plan', o.plan_name || (o.organizer_type === 'leisure' ? 'Leisure venue' : '—'));
    };

    // Instant fill from cached organizer data.
    try { applyOrg((BileteOnlineAuth.getOrganizerData && BileteOnlineAuth.getOrganizerData()) || {}); } catch (e) {}

    if (! BileteOnlineAPI || ! BileteOnlineAPI.organizer) return;

    // Authoritative org profile.
    try {
        const me = await BileteOnlineAPI.organizer.getProfile();
        const o = (me && me.data && (me.data.organizer || me.data)) || null;
        if (o) {
            applyOrg(o);
            try { localStorage.setItem('bileteonline_organizer_data', JSON.stringify(o)); } catch (e) {}
        }
    } catch (e) {}

    // Catalog count badge.
    try {
        const r = await BileteOnlineAPI.organizer.getEvents();
        const items = (r && r.data && (r.data.items || r.data)) || [];
        const navCount = document.getElementById('nav-events-count');
        if (navCount) navCount.textContent = Array.isArray(items) ? items.length : 0;
    } catch (e) {}
});
</script>
