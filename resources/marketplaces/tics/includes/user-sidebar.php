<?php
/**
 * User Sidebar Component
 * Shared sidebar navigation for user account pages
 */

// Get current page for active state
$currentPage = $currentPage ?? 'dashboard';

// Get user data (should be set by parent page)
$currentUser = $currentUser ?? [
    'firstName' => 'Alexandru',
    'avatarLarge' => 'https://i.pravatar.cc/60?img=68',
    'memberSince' => '2024',
    'points' => 1250,
    'ticketsCount' => 3
];

$sidebarLinks = [
    [
        'id' => 'dashboard',
        'url' => '/cont',
        'label' => 'Dashboard',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>',
        'badge' => null
    ],
    [
        'id' => 'tickets',
        'url' => '/cont/bilete',
        'label' => 'Biletele mele',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',
        'badge' => $currentUser['ticketsCount']
    ],
    [
        'id' => 'orders',
        'url' => '/cont/comenzi',
        'label' => 'Comenzi',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
        'badge' => null
    ],
    [
        'id' => 'favorites',
        'url' => '/cont/favorite',
        'label' => 'Favorite',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>',
        'badge' => null
    ],
    [
        'id' => 'rewards',
        'url' => '/cont/puncte',
        'label' => 'Puncte & Recompense',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'badge' => null
    ],
    'divider',
    [
        'id' => 'settings',
        'url' => '/cont/setari',
        'label' => 'Setari',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'badge' => null
    ],
    [
        'id' => 'help',
        'url' => '/ajutor',
        'label' => 'Ajutor',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'badge' => null
    ]
];
?>

<aside class="lg:w-64 flex-shrink-0">
    <nav class="user-sidebar bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <!-- User Info Header -->
        <div class="p-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <img src="<?= htmlspecialchars($currentUser['avatarLarge']) ?>" class="w-12 h-12 rounded-xl object-cover" alt="<?= htmlspecialchars($currentUser['firstName']) ?>">
                <div>
                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($currentUser['firstName']) ?> M.</h3>
                    <p class="text-sm text-gray-500">Membru din <?= htmlspecialchars($currentUser['memberSince']) ?></p>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="p-2">
            <?php foreach ($sidebarLinks as $link): ?>
                <?php if ($link === 'divider'): ?>
                    <div class="border-t border-gray-100 my-2"></div>
                <?php else:
                    $isActive = $currentPage === $link['id'];
                ?>
                    <a href="<?= htmlspecialchars($link['url']) ?>"
                       class="sidebar-link <?= $isActive ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-sm <?= $isActive ? 'font-medium text-gray-900' : 'text-gray-600' ?> rounded-xl border-l-4 border-transparent">
                        <svg class="w-5 h-5 <?= $isActive ? 'text-indigo-600' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <?= $link['icon'] ?>
                        </svg>
                        <?= htmlspecialchars($link['label']) ?>
                        <?php if ($link['badge']): ?>
                            <span class="ml-auto px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-full"><?= $link['badge'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </nav>
</aside>
