<?php
/**
 * Sidebar partial for /artist/cont/* pages.
 * Dark-themed (bg-secondary), matches the design template in
 * resources/marketplaces/ambilet/designs/artist/dashboard.html.
 *
 * Active link is highlighted by URL prefix so each page doesn't have to
 * configure the active state manually.
 */
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isActive = function (string $needle) use ($currentPath): bool {
    return str_starts_with($currentPath, $needle);
};

$navItems = [
    [
        'url'    => '/artist/cont/dashboard',
        'label'  => 'Dashboard',
        'icon'   => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    ],
    [
        'url'    => '/artist/cont/detalii',
        'label'  => 'Detalii Artist',
        'icon'   => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3',
    ],
    [
        'url'    => '/artist/cont/evenimente',
        'label'  => 'Evenimente',
        'icon'   => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    ],
    [
        'url'    => '/artist/cont/setari',
        'label'  => 'Setări Cont',
        'icon'   => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ],
];

// Extended Artist (4 module + landing). Linkurile catre module sunt server-side
// vizibile mereu — frontend-ul (artist-cont-shared.js) le marcheaza cu lock daca
// /api/marketplace-client/artist/extended-artist/status returneaza enabled=false.
$extendedArtistNav = [
    [
        'url'    => '/artist/cont/extended-artist',
        'label'  => 'Vizualizare generala',
        'icon'   => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'module' => null,
    ],
    [
        'url'    => '/artist/cont/extended-artist/fan-crm',
        'label'  => 'Fan CRM',
        'icon'   => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        'module' => 'fan_crm',
    ],
    [
        'url'    => '/artist/cont/extended-artist/booking',
        'label'  => 'Booking',
        'icon'   => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'module' => 'booking_marketplace',
    ],
    [
        'url'    => '/artist/cont/extended-artist/epk',
        'label'  => 'Smart EPK',
        'icon'   => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'module' => 'smart_epk',
    ],
    [
        'url'    => '/artist/cont/extended-artist/tour',
        'label'  => 'Tour Optimizer',
        'icon'   => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
        'module' => 'tour_optimizer',
    ],
];
?>
<aside class="fixed left-0 top-0 z-30 hidden h-full w-64 overflow-y-auto bg-secondary text-white lg:block">
    <div class="border-b border-white/10 p-6">
        <a href="/" class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary">
                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
            </div>
            <div>
                <span class="block text-xl font-extrabold leading-none"><?= strtoupper(SITE_NAME) ?></span>
                <span class="mt-1 block text-xs text-white/50">Portal Artist</span>
            </div>
        </a>
    </div>

    <!-- Account card (filled by artist-cont-shared.js) -->
    <div id="artist-account-card" class="mx-4 mt-4 rounded-xl border border-white/10 bg-white/5 p-3">
        <div class="flex items-center gap-3">
            <div id="artist-avatar" class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">
                <span id="artist-avatar-initials">…</span>
            </div>
            <div class="min-w-0 flex-1">
                <p id="artist-account-name" class="truncate text-sm font-semibold text-white">Se încarcă…</p>
                <p id="artist-linked-name" class="truncate text-xs text-white/50"></p>
            </div>
        </div>
    </div>

    <nav class="space-y-1 p-4">
        <?php foreach ($navItems as $item): $active = $isActive($item['url']); ?>
            <a href="<?= $item['url'] ?>" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors <?= $active ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' ?>">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>

        <div class="mt-6 mb-2 flex items-center gap-2 px-4 text-xs font-semibold uppercase tracking-wider text-white/40">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <span>Extended Artist</span>
            <span data-extended-artist-badge class="hidden ml-auto rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-300">ACTIV</span>
        </div>
        <?php foreach ($extendedArtistNav as $item): $active = $isActive($item['url']); ?>
            <a href="<?= $item['url'] ?>"
               data-extended-artist-link
               <?= $item['module'] !== null ? 'data-extended-artist-module="' . htmlspecialchars($item['module']) . '"' : '' ?>
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors <?= $active ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' ?>">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <span class="flex-1"><?= $item['label'] ?></span>
                <?php if ($item['module'] !== null): ?>
                    <svg data-extended-artist-lock class="h-4 w-4 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="absolute bottom-0 left-0 right-0 border-t border-white/10 p-4">
        <button id="artist-logout-btn" class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white/70 transition-colors hover:bg-red-500/20 hover:text-red-300">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Deconectare
        </button>
    </div>
</aside>

<!-- Mobile header -->
<header class="fixed left-0 right-0 top-0 z-40 border-b border-border bg-white lg:hidden">
    <div class="flex items-center justify-between px-4 py-3">
        <button id="mobile-menu-btn" class="rounded-lg p-2 hover:bg-surface" aria-label="Meniu">
            <svg class="h-6 w-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <a href="/" class="flex items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary">
                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
            </div>
            <span class="font-bold text-secondary"><?= strtoupper(SITE_NAME) ?></span>
        </a>
        <div class="w-10"></div>
    </div>
</header>

<!-- Mobile slide-out -->
<div id="mobile-menu-overlay" class="fixed inset-0 z-40 hidden bg-black/50 lg:hidden"></div>
<aside id="mobile-menu" class="fixed left-0 top-0 z-50 h-full w-72 -translate-x-full overflow-y-auto bg-secondary text-white shadow-xl transition-transform lg:hidden">
    <div class="flex items-center justify-between border-b border-white/10 p-6">
        <a href="/" class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary">
                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
            </div>
            <span class="text-xl font-extrabold"><?= strtoupper(SITE_NAME) ?></span>
        </a>
        <button id="mobile-menu-close" class="p-2 text-white/70" aria-label="Închide">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <nav class="space-y-1 p-4">
        <?php foreach ($navItems as $item): $active = $isActive($item['url']); ?>
            <a href="<?= $item['url'] ?>" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium <?= $active ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10' ?>">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>

        <div class="mt-6 mb-2 flex items-center gap-2 px-4 text-xs font-semibold uppercase tracking-wider text-white/40">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <span>Extended Artist</span>
            <span data-extended-artist-badge class="hidden ml-auto rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-300">ACTIV</span>
        </div>
        <?php foreach ($extendedArtistNav as $item): $active = $isActive($item['url']); ?>
            <a href="<?= $item['url'] ?>"
               data-extended-artist-link
               <?= $item['module'] !== null ? 'data-extended-artist-module="' . htmlspecialchars($item['module']) . '"' : '' ?>
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium <?= $active ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10' ?>">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <span class="flex-1"><?= $item['label'] ?></span>
                <?php if ($item['module'] !== null): ?>
                    <svg data-extended-artist-lock class="h-4 w-4 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="absolute bottom-0 left-0 right-0 border-t border-white/10 p-4">
        <button class="artist-mobile-logout flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white/70 hover:bg-red-500/20 hover:text-red-300">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Deconectare
        </button>
    </div>
</aside>

<script>
(function() {
    const open = document.getElementById('mobile-menu-btn');
    const close = document.getElementById('mobile-menu-close');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');
    if (!open || !menu) return;

    const show = () => {
        menu.classList.remove('-translate-x-full');
        overlay?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };
    const hide = () => {
        menu.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
        document.body.style.overflow = '';
    };

    open.addEventListener('click', show);
    close?.addEventListener('click', hide);
    overlay?.addEventListener('click', hide);

    // Mobile-menu logout — share the desktop button's click handler later
    // (artist-cont-shared.js wires that on DOMContentLoaded).
    document.querySelectorAll('.artist-mobile-logout').forEach(btn => {
        btn.addEventListener('click', () => {
            if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.logoutArtist) {
                AmbiletAuth.logoutArtist();
            }
        });
    });
})();

// Extended Artist gating: cere statusul si actualizeaza sidebar (badge ACTIV +
// lock icons). Daca artistul nu e logat sau requestul esueaza, lasam linkurile
// in starea default (locked, redirect la landing).
(function() {
    const token = localStorage.getItem('ambilet_artist_token');
    if (!token) return;

    // Toate request-urile API trec prin proxy.php (X-API-Key se adauga server-side).
    fetch('/api/proxy.php?action=artist.extended-artist.status', {
        headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
    })
        .then(r => r.ok ? r.json() : null)
        .then(payload => {
            const enabled = payload?.data?.enabled === true;
            if (!enabled) return;

            document.querySelectorAll('[data-extended-artist-badge]').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('[data-extended-artist-lock]').forEach(el => el.remove());
        })
        .catch(() => {
            // ignored — sidebar ramane in starea default (locked)
        });
})();
</script>
