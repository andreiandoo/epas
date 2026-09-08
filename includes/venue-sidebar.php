<?php
/**
 * Venue Owner Sidebar with Mobile Overlay
 *
 * Blue-toned skin so the venue-owner shell is visually distinct from
 * the customer (red) and organizer (dark red / slate) surfaces. Layout,
 * spacing and mobile behavior mirror organizer-sidebar.php exactly so
 * a person with multiple roles keeps muscle memory across shells.
 *
 * Variables available:
 * - $currentPage: page slug used for active-item highlighting
 */

$currentPage = $currentPage ?? getCurrentPage();
?>

<!-- Sidebar Overlay (Mobile) -->
<div id="sidebarOverlay" class="fixed inset-0 z-40 sidebar-overlay bg-slate-900/50 lg:hidden" onclick="toggleSidebar()"></div>

<!-- Sidebar. lg:sticky + lg:top-0 + lg:h-screen locks the panel to
     the viewport on desktop so long content pages don't stretch it. -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex flex-col w-64 transform -translate-x-full border-r bg-gradient-to-b from-slate-900 to-slate-800 sidebar lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 border-slate-700">
    <!-- Logo -->
    <div class="px-5 py-3 border-b border-slate-700">
        <a href="/" class="flex items-center gap-2.5">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl" style="background:linear-gradient(135deg, #3b82f6, #1e40af);">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 21V10a1 1 0 011-1h4a1 1 0 011 1v11m0 0h4V6a1 1 0 011-1h4a1 1 0 011 1v15M4 21h16"/></svg>
            </div>
            <div>
                <span class="text-xl font-extrabold text-white"><?= SITE_NAME ?></span>
                <span class="text-[10px] font-semibold block -mt-1" style="color:#93c5fd;">LOCAȚIE</span>
            </div>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-1 overflow-y-auto">
        <a href="/venue/panou" class="sidebar-link <?= $currentPage === 'venue_panou' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 m-2 rounded-xl text-sm font-medium group <?= $currentPage !== 'venue_panou' ? 'text-white' : '' ?>">
            <svg class="<?= $currentPage !== 'venue_panou' ? 'text-muted' : 'text-white' ?> w-5 h-5 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Panou
        </a>

        <a href="/venue/locatii" class="sidebar-link <?= $currentPage === 'venue_locatii' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 m-2 rounded-xl text-sm font-medium group <?= $currentPage !== 'venue_locatii' ? 'text-white' : '' ?>">
            <svg class="<?= $currentPage !== 'venue_locatii' ? 'text-muted' : 'text-white' ?> w-5 h-5 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Locațiile mele
            <span id="nav-venues-count" class="ml-auto px-2 py-0.5 bg-white text-xs font-bold rounded-full" style="color:#1e40af;">0</span>
        </a>

        <a href="/venue/evenimente" class="sidebar-link <?= $currentPage === 'venue_evenimente' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 m-2 rounded-xl text-sm font-medium group <?= $currentPage !== 'venue_evenimente' ? 'text-white' : '' ?>">
            <svg class="<?= $currentPage !== 'venue_evenimente' ? 'text-muted' : 'text-white' ?> w-5 h-5 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Evenimente găzduite
        </a>

        <a href="/venue/utilizare" class="sidebar-link <?= $currentPage === 'venue_utilizare' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 m-2 rounded-xl text-sm font-medium group <?= $currentPage !== 'venue_utilizare' ? 'text-white' : '' ?>">
            <svg class="<?= $currentPage !== 'venue_utilizare' ? 'text-muted' : 'text-white' ?> w-5 h-5 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Utilizare
        </a>

        <a href="/venue/analiza" class="sidebar-link <?= $currentPage === 'venue_analiza' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 m-2 rounded-xl text-sm font-medium group <?= $currentPage !== 'venue_analiza' ? 'text-white' : '' ?>">
            <svg class="<?= $currentPage !== 'venue_analiza' ? 'text-muted' : 'text-white' ?> w-5 h-5 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Analiză
            <span class="px-1.5 py-0.5 ml-auto text-[10px] font-bold rounded-full text-white" style="background:#3b82f6;">NEW</span>
        </a>

        <div class="my-3 border-t border-slate-700"></div>

        <a href="/venue/setari" class="sidebar-link <?= $currentPage === 'venue_setari' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 m-2 rounded-xl text-sm font-medium group <?= $currentPage !== 'venue_setari' ? 'text-white' : '' ?>">
            <svg class="<?= $currentPage !== 'venue_setari' ? 'text-muted' : 'text-white' ?> w-5 h-5 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Setări
        </a>
    </nav>

    <!-- Mobile-only banner: POS + Scan are app-only for venue owners. -->
    <div class="p-3 m-3 rounded-xl border" style="background:rgba(59,130,246,0.1); border-color:rgba(59,130,246,0.25);">
        <div class="flex items-start gap-2">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color:#60a5fa;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            <div>
                <p class="text-xs font-bold text-white">Scan + POS</p>
                <p class="text-[11px] text-white/60 mt-0.5">Pentru scanare bilete și vânzare POS, folosește aplicația AmBilet.</p>
            </div>
        </div>
    </div>
</aside>
