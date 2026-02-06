<?php
/**
 * Organizer Sidebar Component
 * Shared sidebar navigation for organizer portal pages
 */

// Get current page for active state
$currentPage = $currentPage ?? 'dashboard';

// Get organizer data (should be set by parent page)
$currentOrganizer = $currentOrganizer ?? [
    'companyName' => 'Live Events SRL',
    'email' => 'contact@liveevents.ro',
    'avatar' => 'https://i.pravatar.cc/40?img=12'
];

// Stats for badges
$stats = $stats ?? [
    'activeEvents' => 5,
    'newOrders' => 12
];

$sidebarSections = [
    [
        'title' => 'Principal',
        'links' => [
            [
                'id' => 'dashboard',
                'url' => '/organizator',
                'label' => 'Dashboard',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>',
                'badge' => null,
                'badgeColor' => null
            ],
            [
                'id' => 'events',
                'url' => '/organizator/evenimente',
                'label' => 'Evenimente',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                'badge' => $stats['activeEvents'],
                'badgeColor' => 'indigo'
            ],
            [
                'id' => 'orders',
                'url' => '/organizator/comenzi',
                'label' => 'Comenzi',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>',
                'badge' => $stats['newOrders'],
                'badgeColor' => 'green'
            ],
            [
                'id' => 'attendees',
                'url' => '/organizator/participanti',
                'label' => 'Participanti',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
                'badge' => null,
                'badgeColor' => null
            ],
            [
                'id' => 'analytics',
                'url' => '/organizator/analiza',
                'label' => 'Analiza & Rapoarte',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                'badge' => null,
                'badgeColor' => null
            ]
        ]
    ],
    [
        'title' => 'Finante',
        'links' => [
            [
                'id' => 'payouts',
                'url' => '/organizator/plati',
                'label' => 'Plati & Incasari',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                'badge' => null,
                'badgeColor' => null
            ]
        ]
    ],
    [
        'title' => 'Instrumente',
        'links' => [
            [
                'id' => 'scanner',
                'url' => '/organizator/scanner',
                'label' => 'Scanner Check-in',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>',
                'badge' => null,
                'badgeColor' => null
            ],
            [
                'id' => 'widget',
                'url' => '/organizator/widget',
                'label' => 'Widget integrare',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>',
                'badge' => null,
                'badgeColor' => null
            ]
        ]
    ],
    [
        'title' => 'Setari',
        'links' => [
            [
                'id' => 'team',
                'url' => '/organizator/echipa',
                'label' => 'Echipa mea',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
                'badge' => null,
                'badgeColor' => null
            ],
            [
                'id' => 'settings',
                'url' => '/organizator/setari',
                'label' => 'Setari cont',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                'badge' => null,
                'badgeColor' => null
            ]
        ]
    ]
];
?>

<!-- Desktop Sidebar -->
<aside id="desktopSidebar" class="fixed left-0 top-0 bottom-0 w-64 bg-white border-r border-gray-200 z-40 flex-col hidden lg:flex">
    <!-- Logo -->
    <div class="p-5 border-b border-gray-100">
        <a href="/organizator" class="flex items-center gap-2">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <span class="text-white font-bold text-lg">T</span>
            </div>
            <div>
                <span class="font-bold text-xl text-gray-900">TICS</span>
                <span class="text-xs text-gray-400 block -mt-1">Organizer Portal</span>
            </div>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
        <?php foreach ($sidebarSections as $section): ?>
            <p class="px-4 py-2 <?= $section !== $sidebarSections[0] ? 'mt-4' : '' ?> text-xs font-semibold text-gray-400 uppercase tracking-wider"><?= htmlspecialchars($section['title']) ?></p>

            <?php foreach ($section['links'] as $link):
                $isActive = $currentPage === $link['id'];
            ?>
                <a href="<?= htmlspecialchars($link['url']) ?>"
                   class="org-sidebar-link <?= $isActive ? 'active font-medium' : 'text-gray-600' ?> flex items-center gap-3 px-4 py-3 text-sm rounded-xl">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?= $link['icon'] ?>
                    </svg>
                    <span class="flex-1"><?= htmlspecialchars($link['label']) ?></span>
                    <?php if ($link['badge']): ?>
                        <span class="badge ml-auto px-2 py-0.5 bg-<?= $link['badgeColor'] ?>-100 text-<?= $link['badgeColor'] ?>-700 text-xs font-medium rounded-full"><?= $link['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-gray-100">
        <div class="flex items-center gap-3">
            <img src="<?= htmlspecialchars($currentOrganizer['avatar']) ?>" class="w-10 h-10 rounded-xl flex-shrink-0" alt="<?= htmlspecialchars($currentOrganizer['companyName']) ?>">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($currentOrganizer['companyName']) ?></p>
                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($currentOrganizer['email']) ?></p>
            </div>
            <div class="flex items-center gap-1">
                <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg" title="Notificari">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </button>
                <a href="/organizator/deconectare" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg" title="Deconectare">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Header -->
<header class="lg:hidden fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-30">
    <div class="flex items-center justify-between px-4 py-3">
        <button onclick="openOrgMobileMenu()" class="p-2 text-gray-600 hover:bg-gray-100 rounded-xl">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <a href="/organizator" class="flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold">T</span>
            </div>
            <span class="font-bold text-lg">TICS</span>
        </a>
        <button class="p-2 text-gray-600 hover:bg-gray-100 rounded-xl relative">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
        </button>
    </div>
</header>

<!-- Mobile Overlay -->
<div id="orgMobileOverlay" class="mobile-overlay lg:hidden fixed inset-0 bg-black/50 z-40" onclick="closeOrgMobileMenu()"></div>

<!-- Mobile Sidebar -->
<aside id="orgMobileSidebar" class="mobile-sidebar lg:hidden fixed left-0 top-0 bottom-0 w-72 bg-white z-50 flex flex-col">
    <!-- Header -->
    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
        <a href="/organizator" class="flex items-center gap-2">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
                <span class="text-white font-bold text-lg">T</span>
            </div>
            <div>
                <span class="font-bold text-xl text-gray-900">TICS</span>
                <span class="text-xs text-gray-400 block -mt-1">Organizer Portal</span>
            </div>
        </a>
        <button onclick="closeOrgMobileMenu()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Mobile Navigation -->
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
        <?php foreach ($sidebarSections as $section): ?>
            <p class="px-4 py-2 <?= $section !== $sidebarSections[0] ? 'mt-4' : '' ?> text-xs font-semibold text-gray-400 uppercase tracking-wider"><?= htmlspecialchars($section['title']) ?></p>

            <?php foreach ($section['links'] as $link):
                $isActive = $currentPage === $link['id'];
            ?>
                <a href="<?= htmlspecialchars($link['url']) ?>"
                   class="org-sidebar-link <?= $isActive ? 'active font-medium' : 'text-gray-600' ?> flex items-center gap-3 px-4 py-3 text-sm rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?= $link['icon'] ?>
                    </svg>
                    <?= htmlspecialchars($link['label']) ?>
                    <?php if ($link['badge']): ?>
                        <span class="ml-auto px-2 py-0.5 bg-<?= $link['badgeColor'] ?>-100 text-<?= $link['badgeColor'] ?>-700 text-xs font-medium rounded-full"><?= $link['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Mobile User Profile -->
    <div class="p-4 border-t border-gray-100">
        <div class="flex items-center gap-3 mb-3">
            <img src="<?= htmlspecialchars($currentOrganizer['avatar']) ?>" class="w-10 h-10 rounded-xl" alt="<?= htmlspecialchars($currentOrganizer['companyName']) ?>">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($currentOrganizer['companyName']) ?></p>
                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($currentOrganizer['email']) ?></p>
            </div>
        </div>
        <a href="/organizator/deconectare" class="flex items-center justify-center gap-2 w-full py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Deconectare
        </a>
    </div>
</aside>

<script>
function openOrgMobileMenu() {
    document.getElementById('orgMobileSidebar').classList.add('open');
    document.getElementById('orgMobileOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeOrgMobileMenu() {
    document.getElementById('orgMobileSidebar').classList.remove('open');
    document.getElementById('orgMobileOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
</script>
