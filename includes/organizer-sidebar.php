<?php
/**
 * Organizer Dashboard Sidebar with Mobile Overlay
 *
 * Variables available:
 * - $currentPage: Current page name for highlighting
 */

$currentPage = $currentPage ?? getCurrentPage();
?>

<!-- Sidebar Overlay (Mobile) -->
<div id="sidebarOverlay" class="fixed inset-0 z-40 sidebar-overlay bg-black/50 lg:hidden" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex flex-col w-64 transform -translate-x-full bg-white border-r sidebar lg:static border-border lg:translate-x-0">
    <!-- Logo -->
    <div class="px-5 py-3 border-b border-border">
        <a href="/" class="flex items-center gap-2.5">
            <div class="flex items-center justify-center w-10 h-10 bg-primary rounded-xl">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <div>
                <span class="text-xl font-extrabold text-secondary"><?= SITE_NAME ?></span>
                <span class="text-[10px] text-primary font-semibold block -mt-1">ORGANIZATOR</span>
            </div>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <a href="/organizator/panou" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'dashboard' ? 'text-muted' : '' ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>
        <a href="/organizator/events" class="sidebar-link <?= $currentPage === 'events' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'events' ? 'text-muted' : '' ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Evenimente
            <span id="nav-events-count" class="ml-auto px-2 py-0.5 bg-primary/10 text-primary text-xs font-bold rounded-full">0</span>
        </a>
        <a href="/organizator/participanti" class="sidebar-link <?= $currentPage === 'participants' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'participants' ? 'text-muted' : '' ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Participanti
        </a>
        <a href="/organizator/reports" class="sidebar-link <?= $currentPage === 'reports' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'reports' ? 'text-muted' : '' ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Rapoarte
        </a>
        <a href="/organizator/documente" class="sidebar-link <?= $currentPage === 'documents' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'documents' ? 'text-muted' : '' ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Documente
        </a>

        <div class="pt-4 mt-4 border-t border-border">
            <p class="px-4 mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Marketing</p>
            <a href="/organizator/servicii" class="sidebar-link <?= $currentPage === 'services' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'services' ? 'text-muted' : '' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Servicii Extra
                <span class="ml-auto px-2 py-0.5 bg-accent/10 text-accent text-xs font-bold rounded-full">NOU</span>
            </a>
            <a href="/organizator/promo" class="sidebar-link <?= $currentPage === 'promo' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'promo' ? 'text-muted' : '' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                Coduri promotionale
            </a>
        </div>

        <div class="pt-4 mt-4 border-t border-border">
            <p class="px-4 mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Setari</p>
            <a href="/organizator/facturare" class="sidebar-link <?= $currentPage === 'billing' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'billing' ? 'text-muted' : '' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Facturare
            </a>
            <a href="/organizator/setari" class="sidebar-link <?= $currentPage === 'settings' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'settings' ? 'text-muted' : '' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Cont & companie
            </a>
            <a href="/organizator/help" class="sidebar-link <?= $currentPage === 'help' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $currentPage !== 'help' ? 'text-muted' : '' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Ajutor & suport
            </a>
        </div>
    </nav>

    <!-- User Profile -->
    <div class="p-3 border-t border-border">
        <div class="flex items-center gap-3 p-2">
            <div class="flex items-center justify-center rounded-full w-9 h-9 bg-primary/10">
                <span id="sidebar-org-initials" class="text-xs font-bold text-primary">--</span>
            </div>
            <div class="flex-1 min-w-0">
                <p id="sidebar-org-name" class="text-sm font-semibold truncate text-secondary">Organizator</p>
                <p id="sidebar-org-plan" class="text-xs truncate text-muted">Professional Plan</p>
            </div>
            <button onclick="AmbiletAuth.logoutOrganizer()" class="p-1.5 text-muted hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Deconectare">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </button>
        </div>
    </div>
</aside>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
</script>
