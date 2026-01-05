<?php
/**
 * User Dashboard Header with Mobile Menu
 *
 * Variables available:
 * - $currentPage: Current page name for highlighting
 */

// Flag to skip JS component loading (header.js/footer.js) in scripts.php
$skipJsComponents = true;

$currentPage = $currentPage ?? 'dashboard';

$userNavItems = [
    ['page' => 'dashboard', 'url' => '/cont', 'label' => 'Dashboard'],
    ['page' => 'tickets', 'url' => '/cont/bilete', 'label' => 'Bilete'],
    ['page' => 'orders', 'url' => '/cont/comenzi', 'label' => 'Comenzi'],
    ['page' => 'watchlist', 'url' => '/cont/favorite', 'label' => 'Favorite'],
    ['page' => 'rewards', 'url' => '/cont/puncte', 'label' => 'Recompense'],
    ['page' => 'profile', 'url' => '/cont/profil', 'label' => 'Profil'],
    ['page' => 'payments', 'url' => '/cont/plati', 'label' => 'Plati'],
    ['page' => 'notifications', 'url' => '/cont/notificari', 'label' => 'Notificari'],
    ['page' => 'settings', 'url' => '/cont/setari', 'label' => 'Setari'],
    ['page' => 'help', 'url' => '/cont/ajutor', 'label' => 'Ajutor'],
];

$userMenuItems = [
    ['page' => 'dashboard', 'url' => '/cont', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['page' => 'tickets', 'url' => '/cont/bilete', 'label' => 'Biletele mele', 'icon' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'],
    ['page' => 'orders', 'url' => '/cont/comenzi', 'label' => 'Comenzile mele', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ['page' => 'watchlist', 'url' => '/cont/favorite', 'label' => 'Favorite', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
    ['page' => 'rewards', 'url' => '/cont/puncte', 'label' => 'Puncte & Recompense', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['page' => 'profile', 'url' => '/cont/profil', 'label' => 'Profilul meu', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ['page' => 'payments', 'url' => '/cont/plati', 'label' => 'Metode de plata', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    ['page' => 'notifications', 'url' => '/cont/notificari', 'label' => 'Notificari', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
    ['page' => 'settings', 'url' => '/cont/setari', 'label' => 'Setari', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ['page' => 'help', 'url' => '/cont/ajutor', 'label' => 'Ajutor', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
];
?>

<!-- Mobile Menu Overlay -->
<div id="menuOverlay" class="fixed inset-0 z-40 overlay bg-black/50 lg:hidden" onclick="toggleMobileMenu()"></div>

<!-- Mobile Menu Sidebar -->
<div id="mobileMenu" class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white menu-mobile w-72 lg:hidden">
    <div class="flex items-center justify-between p-4 border-b border-border">
        <a href="/" class="flex items-center gap-2">
            <img src="/assets/images/ambilet-logo.webp" alt="<?= SITE_NAME ?>" class="hidden w-auto h-9">
            <svg class="w-8 h-8" viewBox="0 0 48 48" fill="none">
                <defs>
                    <linearGradient id="logoGrad" x1="6" y1="10" x2="42" y2="38">
                        <stop stop-color="#A51C30"/>
                        <stop offset="1" stop-color="#C41E3A"/>
                    </linearGradient>
                </defs>
                <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#logoGrad)"/>
                <line x1="17" y1="15" x2="31" y2="15" stroke="white" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="15" y1="19" x2="33" y2="19" stroke="white" stroke-opacity="0.35" stroke-width="1.5" stroke-linecap="round"/>
                <rect x="20" y="27" width="8" height="8" rx="1.5" fill="white"/>
            </svg>
            <div class="text-[22px] font-extrabold flex">
                <span id="logoTextAm" class="<?= $transparentHeader ? 'text-white/85' : 'text-slate-800' ?>">Am</span>
                <span id="logoTextBilet" class="<?= $transparentHeader ? 'text-white' : 'text-primary' ?>">Bilet</span>
            </div>
        </a>
        <button onclick="toggleMobileMenu()" class="p-2 rounded-lg hover:bg-surface">
            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <nav class="flex-1 p-4 space-y-1">
        <?php foreach ($userMenuItems as $item):
            $isActive = $currentPage === $item['page'];
            $activeClass = $isActive ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-surface';
        ?>
        <a href="<?= $item['url'] ?>" class="flex items-center gap-3 px-4 py-3 <?= $activeClass ?> rounded-xl font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/></svg>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-border">
        <a href="#" onclick="AmbiletAuth.logout(); window.location.href='/'; return false;" class="flex items-center gap-3 px-4 py-3 font-medium text-error hover:bg-error/10 rounded-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Deconectare
        </a>
    </div>
</div>

<!-- Header -->
<header class="sticky top-0 z-30 bg-white border-b border-border no-print">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-4">
                <button onclick="toggleMobileMenu()" class="p-2 -ml-2 rounded-lg lg:hidden hover:bg-surface">
                    <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="/" class="flex items-center gap-2">
                    <img src="/assets/images/ambilet-logo.webp" alt="<?= SITE_NAME ?>" class="hidden w-auto h-9">
                    <svg class="w-8 h-8" viewBox="0 0 48 48" fill="none">
                        <defs>
                            <linearGradient id="logoGrad" x1="6" y1="10" x2="42" y2="38">
                                <stop stop-color="#A51C30"/>
                                <stop offset="1" stop-color="#C41E3A"/>
                            </linearGradient>
                        </defs>
                        <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#logoGrad)"/>
                        <line x1="17" y1="15" x2="31" y2="15" stroke="white" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="15" y1="19" x2="33" y2="19" stroke="white" stroke-opacity="0.35" stroke-width="1.5" stroke-linecap="round"/>
                        <rect x="20" y="27" width="8" height="8" rx="1.5" fill="white"/>
                    </svg>
                    <div class="text-[22px] font-extrabold flex">
                        <span id="logoTextAm" class="<?= $transparentHeader ? 'text-white/85' : 'text-slate-800' ?>">Am</span>
                        <span id="logoTextBilet" class="<?= $transparentHeader ? 'text-white' : 'text-primary' ?>">Bilet</span>
                    </div>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="items-center hidden gap-1 lg:flex">
                <?php foreach ($userNavItems as $item):
                    $isActive = $currentPage === $item['page'];
                ?>
                <a href="<?= $item['url'] ?>" class="nav-link px-4 py-5 text-sm font-medium <?= $isActive ? 'active text-primary' : 'text-muted hover:text-secondary' ?>"><?= $item['label'] ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-accent/10 rounded-full">
                    <svg class="w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                    <span id="header-user-points" class="text-sm font-bold text-accent">0</span>
                </div>
                <div id="header-user-avatar" class="flex items-center justify-center rounded-full cursor-pointer w-9 h-9 bg-gradient-to-br from-primary to-accent">
                    <span class="text-sm font-bold text-white">--</span>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('active');
    document.getElementById('menuOverlay').classList.toggle('active');
}
</script>
