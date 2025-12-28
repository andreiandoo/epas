<?php
/**
 * User Dashboard Sidebar
 *
 * Variables available:
 * - $currentPage: Current page name for highlighting
 */

$userMenuItems = [
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['page' => 'tickets', 'label' => 'Biletele mele', 'icon' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'],
    ['page' => 'orders', 'label' => 'Comenzi', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ['page' => 'rewards', 'label' => 'Puncte & Recompense', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['page' => 'watchlist', 'label' => 'Favorite', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
    ['page' => 'profile', 'label' => 'Profil', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ['page' => 'settings', 'label' => 'Setari', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ['page' => 'help', 'label' => 'Ajutor', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
];

$currentPage = $currentPage ?? getCurrentPage();
?>

<aside class="lg:w-64 flex-shrink-0">
    <div class="bg-white rounded-2xl border border-border p-4 sticky top-24">
        <!-- User Info -->
        <div class="flex items-center gap-3 pb-4 border-b border-border mb-4">
            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                <span class="text-lg font-bold text-primary" id="user-initials">--</span>
            </div>
            <div>
                <p class="font-semibold text-secondary" id="user-name">Utilizator</p>
                <p class="text-sm text-muted" id="user-email">email@example.com</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="space-y-1">
            <?php foreach ($userMenuItems as $item):
                $isActive = $currentPage === $item['page'];
                $activeClass = $isActive ? 'bg-primary/10 text-primary font-medium' : 'text-muted hover:bg-surface hover:text-secondary';
            ?>
            <a href="/user/<?= $item['page'] ?>.php" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $activeClass ?> transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <?= $item['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- Logout -->
        <div class="mt-4 pt-4 border-t border-border">
            <button onclick="AmbiletAuth.logout()" class="flex items-center gap-3 px-3 py-2 rounded-lg text-error hover:bg-error/10 transition-colors w-full">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Deconectare
            </button>
        </div>
    </div>
</aside>
