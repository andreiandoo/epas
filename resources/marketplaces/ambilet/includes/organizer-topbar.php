<?php
/**
 * Organizer Dashboard Top Header Bar
 */

// Flag to skip JS component loading (header.js/footer.js) in scripts.php
$skipJsComponents = true;
?>

<!-- Top Header -->
<header class="bg-white border-b border-border sticky top-0 z-30">
    <div class="flex items-center justify-between px-4 lg:px-8 h-16">
        <!-- Mobile menu button -->
        <button onclick="toggleSidebar()" class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-surface transition-colors">
            <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <!-- Search -->
        <div class="hidden md:flex items-center flex-1 max-w-md">
            <div class="relative w-full">
                <svg class="w-5 h-5 text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" placeholder="Cauta evenimente, bilete, participanti..."
                    class="w-full pl-10 pr-4 py-2.5 bg-surface border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
            </div>
        </div>

        <!-- Right Actions -->
        <div class="flex items-center gap-2">
            <!-- Quick Create -->
            <a href="/organizer/events?action=create" class="btn btn-primary hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Eveniment nou
            </a>

            <!-- Notifications -->
            <div class="dropdown relative">
                <button onclick="this.parentElement.classList.toggle('active')" class="p-2 rounded-xl hover:bg-surface transition-colors relative">
                    <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span id="notification-badge" class="hidden absolute top-1 right-1 w-2.5 h-2.5 bg-primary rounded-full badge-pulse"></span>
                </button>
                <div class="dropdown-menu absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-border overflow-hidden">
                    <div class="p-4 border-b border-border flex items-center justify-between">
                        <h3 class="font-semibold text-secondary">Notificari</h3>
                        <span id="notification-count" class="text-xs text-primary font-medium">0 noi</span>
                    </div>
                    <div id="notifications-list" class="max-h-80 overflow-y-auto">
                        <div class="p-6 text-center text-muted text-sm">
                            Nu ai notificari noi
                        </div>
                    </div>
                    <div class="p-3 border-t border-border text-center">
                        <a href="/organizer/notifications" class="text-sm text-primary font-medium">Vezi toate notificarile</a>
                    </div>
                </div>
            </div>

            <!-- User Menu -->
            <div class="dropdown relative">
                <button onclick="this.parentElement.classList.toggle('active')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-surface transition-colors">
                    <div class="w-9 h-9 bg-primary/10 rounded-full flex items-center justify-center">
                        <span id="topbar-org-initials" class="text-sm font-bold text-primary">--</span>
                    </div>
                    <svg class="w-4 h-4 text-muted hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="dropdown-menu absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-border py-2 overflow-hidden">
                    <div class="px-4 py-3 border-b border-border">
                        <p id="topbar-org-name" class="font-semibold text-secondary">Organizator</p>
                        <p id="topbar-org-email" class="text-xs text-muted">email@example.com</p>
                    </div>
                    <a href="/organizer/settings" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Setari cont
                    </a>
                    <a href="/organizer/help" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Ajutor & suport
                    </a>
                    <hr class="my-2 border-border">
                    <button onclick="AmbiletAuth.logoutOrganizer()" class="flex items-center gap-2 px-4 py-2 text-sm text-error hover:bg-surface w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Deconectare
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
