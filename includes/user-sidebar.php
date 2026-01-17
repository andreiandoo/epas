<?php
/**
 * User Dashboard Sidebar
 *
 * Variables available:
 * - $currentPage: Current page name for highlighting
 */

$userMenuItems = [
    ['page' => 'dashboard', 'url' => '/cont', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['page' => 'tickets', 'url' => '/cont/bilete', 'label' => 'Biletele mele', 'icon' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'],
    ['page' => 'orders', 'url' => '/cont/comenzi', 'label' => 'Comenzi', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ['page' => 'rewards', 'url' => '/cont/puncte', 'label' => 'Puncte & Recompense', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['page' => 'gift-cards', 'url' => '/cont/carduri-cadou', 'label' => 'Carduri Cadou', 'icon' => 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7'],
    ['page' => 'referrals', 'url' => '/cont/invitatii', 'label' => 'Invita prieteni', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    ['page' => 'reviews', 'url' => '/cont/recenzii', 'label' => 'Recenziile mele', 'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
    ['page' => 'watchlist', 'url' => '/cont/favorite', 'label' => 'Favorite', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
    ['page' => 'profile', 'url' => '/cont/profil', 'label' => 'Profil', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ['page' => 'settings', 'url' => '/cont/setari', 'label' => 'Setari', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ['page' => 'help', 'url' => '/cont/ajutor', 'label' => 'Ajutor', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
];

$currentPage = $currentPage ?? 'dashboard';
?>

<!-- Mobile Menu Toggle -->
<button id="mobile-sidebar-toggle" class="fixed z-40 justify-center text-white rounded-full flex items-center gap-x-2 shadow-lg lg:hidden bottom-4 right-0 left-0 mx-auto w-[90%] h-14 bg-primary">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    <span class="">Meniul meu</span>
</button>

<!-- Mobile Sidebar Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-black/50 lg:hidden"></div>

<!-- Sidebar -->
<aside id="user-sidebar" class="mobile:z-[1000] fixed lg:static inset-y-0 left-0 z-50 w-72 lg:w-64 bg-white lg:bg-transparent transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out flex-shrink-0 overflow-y-auto">
    <div class="min-h-screen p-4 bg-white lg:rounded-2xl lg:border lg:border-border lg:sticky lg:top-24 lg:min-h-0 mobile:p-0">
        <!-- Mobile Close Button -->
        <button id="close-sidebar" class="absolute p-2 lg:hidden top-4 right-4 text-muted hover:text-secondary">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <!-- User Info -->
        <div class="flex items-center gap-3 pb-4 mt-8 mb-4 border-b border-border lg:mt-0 mobile:px-4 mobile:mt-4 mobile:mb-0">
            <div id="sidebar-user-avatar" class="flex items-center justify-center w-12 h-12 rounded-full bg-gradient-to-br from-primary to-accent">
                <span class="text-lg font-bold text-white" id="sidebar-user-initials">--</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold truncate text-secondary" id="sidebar-user-name">Utilizator</p>
                <p class="text-sm text-muted truncate mobile:max-w-[70%]" id="sidebar-user-email">email@example.com</p>
            </div>
        </div>

        <!-- Points Badge -->
        <div class="p-3 mb-4 bg-gradient-to-r from-primary/10 to-accent/10 rounded-xl mobile:rounded-none">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-accent">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-muted">Punctele tale</p>
                        <p class="font-bold text-secondary" id="sidebar-user-points">0</p>
                    </div>
                </div>
                <a href="/cont/puncte" class="text-xs font-medium text-primary">Vezi</a>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="space-y-1 mobile:px-4">
            <?php foreach ($userMenuItems as $item):
                $isActive = $currentPage === $item['page'];
                $activeClass = $isActive ? 'bg-primary/10 text-primary font-medium' : 'text-muted hover:bg-surface hover:text-secondary';
            ?>
            <a href="<?= $item['url'] ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= $activeClass ?> transition-colors">
                <svg class="flex-shrink-0 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <span class="truncate"><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- Logout -->
        <div class="pt-4 mt-4 border-t border-border mobile:px-4">
            <button onclick="AmbiletAuth.logout(); window.location.href='/';" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-error hover:bg-error/10 transition-colors w-full">
                <svg class="flex-shrink-0 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span>Deconectare</span>
            </button>
        </div>
    </div>
</aside>

<script>
// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('user-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const toggleBtn = document.getElementById('mobile-sidebar-toggle');
    const closeBtn = document.getElementById('close-sidebar');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Update user info from auth
    function updateSidebarUser() {
        if (typeof AmbiletAuth !== 'undefined') {
            const user = AmbiletAuth.getUser();
            if (user) {
                const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || '--';
                const initialsEl = document.getElementById('sidebar-user-initials');
                const nameEl = document.getElementById('sidebar-user-name');
                const emailEl = document.getElementById('sidebar-user-email');
                const pointsEl = document.getElementById('sidebar-user-points');

                if (initialsEl) initialsEl.textContent = initials;
                if (nameEl) nameEl.textContent = user.name || 'Utilizator';
                if (emailEl) emailEl.textContent = user.email || '';
                if (pointsEl) pointsEl.textContent = (user.points || 0).toLocaleString();
            }
        }
    }

    // Try immediately and after a short delay (for async auth loading)
    updateSidebarUser();
    setTimeout(updateSidebarUser, 500);
});
</script>
