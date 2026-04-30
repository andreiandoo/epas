<?php
/**
 * Sidebar partial for /artist/cont/* pages.
 * Highlights the current section based on the request URI so the active
 * link styling stays in sync without per-page configuration.
 */
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isActive = function (string $needle) use ($currentPath): bool {
    return str_starts_with($currentPath, $needle);
};
?>
<aside class="hidden lg:flex lg:flex-col w-72 bg-white border-r border-border min-h-screen">
    <div class="p-6 border-b border-border">
        <a href="/" class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-primary rounded-xl">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-secondary">Cont Artist</p>
                <p class="text-xs text-muted"><?= SITE_NAME ?></p>
            </div>
        </a>
    </div>

    <!-- Account card (filled in by JS once /artist/account responds) -->
    <div id="artist-account-card" class="p-4 m-4 border rounded-xl border-border bg-surface">
        <div class="flex items-center gap-3">
            <div id="artist-avatar" class="flex items-center justify-center w-12 h-12 text-white rounded-full bg-primary">
                <span id="artist-avatar-initials" class="text-sm font-semibold">…</span>
            </div>
            <div class="flex-1 min-w-0">
                <p id="artist-account-name" class="text-sm font-semibold text-secondary truncate">Se încarcă…</p>
                <p id="artist-linked-name" class="text-xs text-muted truncate"></p>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
        <?php
        $items = [
            ['/artist/cont/dashboard', 'Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['/artist/cont/detalii', 'Detalii', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
            ['/artist/cont/evenimente', 'Evenimente', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['/artist/cont/setari', 'Setări', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        ];
        foreach ($items as [$url, $label, $iconPath]):
            $active = $isActive($url);
        ?>
            <a href="<?= $url ?>" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors <?= $active ? 'bg-primary/10 text-primary' : 'text-secondary hover:bg-primary/5' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconPath ?>"/>
                </svg>
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-border">
        <button id="artist-logout-btn" class="flex items-center w-full gap-3 px-3 py-2.5 text-sm font-medium transition-colors rounded-lg text-secondary hover:bg-red-50 hover:text-red-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Ieșire din cont
        </button>
    </div>
</aside>
