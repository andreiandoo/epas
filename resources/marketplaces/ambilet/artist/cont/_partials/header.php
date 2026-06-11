<?php
/**
 * Mobile-only top header for /artist/cont/* pages. The desktop sidebar
 * (sidebar.php) replaces this above lg breakpoint.
 */
?>
<header class="lg:hidden flex items-center justify-between px-4 py-3 bg-white border-b border-border">
    <a href="/" class="flex items-center gap-2">
        <div class="flex items-center justify-center w-8 h-8 bg-primary rounded-lg">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
            </svg>
        </div>
        <span class="text-base font-bold text-secondary">Cont Artist</span>
    </a>
    <button id="mobile-menu-btn" class="p-2 rounded-lg text-secondary hover:bg-surface" aria-label="Meniu">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</header>

<!-- Mobile slide-out menu (toggled by mobile-menu-btn) -->
<div id="mobile-menu-overlay" class="hidden fixed inset-0 z-40 bg-black/50 lg:hidden"></div>
<div id="mobile-menu" class="hidden fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl lg:hidden">
    <?php require __DIR__ . '/sidebar.php'; ?>
</div>

<script>
(function() {
    const btn = document.getElementById('mobile-menu-btn');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');
    if (!btn || !menu || !overlay) return;

    const toggle = (open) => {
        if (open) {
            menu.classList.remove('hidden');
            overlay.classList.remove('hidden');
        } else {
            menu.classList.add('hidden');
            overlay.classList.add('hidden');
        }
    };

    btn.addEventListener('click', () => toggle(true));
    overlay.addEventListener('click', () => toggle(false));
})();
</script>
