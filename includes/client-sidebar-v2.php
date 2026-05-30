<?php
/**
 * bilete.online — v2 client account sidebar.
 *
 * Drop-in partial used by every page under /cont/*. The main site
 * header.php renders the public chrome; this sits in the LEFT column
 * of a two-column grid inside the page. Each page opens its own grid:
 *
 *   <div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
 *     <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">
 *       <?php $currentClientPage = 'dashboard'; include 'includes/client-sidebar-v2.php'; ?>
 *       <main class="min-w-0">
 *         ... page content ...
 *       </main>
 *     </div>
 *   </div>
 *
 * Variables a page can set before include:
 *   $currentClientPage — slug of the active nav item (for aria-current)
 *   $sidebarCounts     — optional array of badge values, e.g.
 *                        ['tickets'=>3,'orders'=>12,'points'=>820]
 *
 * User name / initials / email / points are hydrated client-side from
 * BileteOnlineAuth + BileteOnlineAPI.customer.getPoints() (sidebar badge
 * stays fresh even when localStorage is stale).
 */

$currentClientPage = $currentClientPage ?? 'dashboard';
$sidebarCounts     = $sidebarCounts ?? [];

$navItems = [
    ['key' => 'dashboard',       'url' => '/cont',                  'label' => 'Dashboard',        'badge' => 'badgeFor("dashboard")'],
    ['key' => 'tickets',         'url' => '/cont/bilete',           'label' => 'Biletele mele',    'badge' => 'badgeFor("tickets")'],
    ['key' => 'orders',          'url' => '/cont/comenzi',          'label' => 'Comenzile mele',   'badge' => 'badgeFor("orders")'],
    ['key' => 'points',          'url' => '/cont/puncte',           'label' => 'Punctele mele',    'badge' => 'badgeFor("points")'],
    ['key' => 'recommendations', 'url' => '/cont/recomandari',      'label' => 'Recomandări',      'badge' => '"nou"'],
    ['key' => 'support',         'url' => '/cont/tichete-support',  'label' => 'Tichete support',  'badge' => 'badgeFor("support")'],
    ['key' => 'settings',        'url' => '/cont/setari',           'label' => 'Setări',           'badge' => '"⚙"'],
];
?>

<!-- v2 client sidebar (desktop) -->
<aside class="hidden lg:block sticky top-28">
    <div class="rounded-[2rem] border-2 border-ink bg-paper p-4 shadow-ticket">
        <!-- User card -->
        <div class="flex items-center gap-3 p-3 rounded-2xl bg-paper-2 border border-ink/10">
            <span data-user-initials class="grid place-items-center w-12 h-12 rounded-full bg-vermilion text-paper font-display text-2xl font-bold">?</span>
            <div class="min-w-0">
                <p data-user-name class="font-display text-2xl font-bold leading-none truncate">Client</p>
                <p data-user-email class="text-sm text-ink-soft truncate">—</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="mt-4 space-y-1 text-sm font-bold">
            <?php foreach ($navItems as $item):
                $isActive = $currentClientPage === $item['key'];
                $aria = $isActive ? ' aria-current="page"' : '';
                $activeCls = $isActive ? 'bg-ink text-paper' : 'hover:bg-ink hover:text-paper';
            ?>
                <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>"<?= $aria ?> class="<?= $activeCls ?> flex items-center justify-between gap-3 rounded-2xl px-4 py-3 transition">
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <span class="text-xs opacity-70" x-text="<?= $item['badge'] ?>"></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Logout -->
        <button onclick="if(window.BileteOnlineAuth&&BileteOnlineAuth.logoutCustomer){BileteOnlineAuth.logoutCustomer();}else if(window.BileteOnlineAuth&&BileteOnlineAuth.logout){BileteOnlineAuth.logout();}else{location.href='/';}"
                class="mt-4 w-full flex items-center justify-center gap-2 rounded-2xl border-2 border-ink/15 px-4 py-3 font-bold text-vermilion hover:border-vermilion hover:bg-vermilion/5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Deconectare
        </button>

        <!-- Tip card -->
        <div class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4 text-sm">
            <p class="font-bold text-forest">💡 Tip</p>
            <p class="mt-1 text-ink-soft">Activează notificările push pentru a primi reminder înainte de evenimentele tale.</p>
        </div>
    </div>
</aside>

<!-- Mobile drawer (opens via a button each page renders in its main header) -->
<aside id="client-mobile-drawer" class="lg:hidden fixed inset-0 z-[60] bg-ink/60 backdrop-blur-sm hidden">
    <div class="absolute right-0 top-0 bottom-0 w-[88%] max-w-sm bg-paper p-5 overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <span data-user-initials class="grid place-items-center w-10 h-10 rounded-full bg-vermilion text-paper font-bold">?</span>
                <div class="min-w-0"><p data-user-name class="font-bold truncate">Client</p><p data-user-email class="text-xs text-ink-soft truncate">—</p></div>
            </div>
            <button onclick="document.getElementById('client-mobile-drawer').classList.add('hidden')" class="grid place-items-center w-10 h-10 rounded-full bg-ink text-paper font-bold">×</button>
        </div>
        <nav class="space-y-1 text-sm font-bold">
            <?php foreach ($navItems as $item):
                $isActive = $currentClientPage === $item['key'];
                $activeCls = $isActive ? 'bg-ink text-paper' : 'bg-paper-2';
            ?>
                <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>" class="<?= $activeCls ?> flex items-center justify-between gap-3 rounded-2xl px-4 py-3"><?= htmlspecialchars($item['label']) ?><span class="text-xs opacity-70" x-text="<?= $item['badge'] ?>"></span></a>
            <?php endforeach; ?>
        </nav>
        <button onclick="if(window.BileteOnlineAuth&&BileteOnlineAuth.logoutCustomer){BileteOnlineAuth.logoutCustomer();}else if(window.BileteOnlineAuth&&BileteOnlineAuth.logout){BileteOnlineAuth.logout();}else{location.href='/';}" class="mt-5 w-full rounded-2xl border-2 border-vermilion px-4 py-3 font-bold text-vermilion">Deconectare</button>
    </div>
</aside>

<script>
/**
 * Hydrate user info + sidebar badges. Runs after Alpine has booted so
 * any x-data on the page already has its initial state.
 */
(function () {
    function applyUser(user) {
        if (! user) return;
        const name = (user.first_name || '') + (user.last_name ? ' ' + user.last_name : '');
        const fallback = user.name || name || user.email || 'Client';
        const initials = (fallback || '?').split(/\s+/).filter(Boolean).map(s => s[0]).join('').slice(0, 2).toUpperCase() || '?';
        document.querySelectorAll('[data-user-initials]').forEach(el => el.textContent = initials);
        document.querySelectorAll('[data-user-name]').forEach(el => el.textContent = name.trim() || fallback);
        document.querySelectorAll('[data-user-email]').forEach(el => el.textContent = user.email || '—');
    }
    window.BO_CLIENT_BADGES = window.BO_CLIENT_BADGES || {};
    window.BO_CLIENT_BADGES_DEFAULTS = window.BO_CLIENT_BADGES_DEFAULTS || <?= json_encode($sidebarCounts ?: new \stdClass()) ?>;
    window.badgeFor = function (key) {
        if (window.BO_CLIENT_BADGES && window.BO_CLIENT_BADGES[key] != null) return window.BO_CLIENT_BADGES[key];
        if (window.BO_CLIENT_BADGES_DEFAULTS && window.BO_CLIENT_BADGES_DEFAULTS[key] != null) return window.BO_CLIENT_BADGES_DEFAULTS[key];
        return '';
    };

    function init() {
        // Hydrate from cached auth first (instant)
        try {
            if (window.BileteOnlineAuth && typeof BileteOnlineAuth.getUser === 'function') {
                applyUser(BileteOnlineAuth.getUser());
            }
        } catch (e) {}

        // Then refresh from /customer/me + dashboard stats so badges are live
        if (! window.BileteOnlineAPI || ! window.BileteOnlineAPI.customer) return;
        if (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && ! BileteOnlineAuth.isLoggedIn()) return;

        BileteOnlineAPI.customer.getProfile && BileteOnlineAPI.customer.getProfile()
            .then(r => {
                // /customer/me returns { success, data: { customer: {...} } }.
                // Older builds returned the customer flat at data.* — handle both.
                const u = (r && r.data && (r.data.customer || r.data)) || null;
                if (u) applyUser(u);
            })
            .catch(() => {});

        BileteOnlineAPI.customer.getDashboardStats && BileteOnlineAPI.customer.getDashboardStats()
            .then(r => {
                if (! r || ! r.data) return;
                const s = r.data.stats || r.data;
                window.BO_CLIENT_BADGES = {
                    tickets: s.upcoming_tickets_count ?? s.tickets_count ?? '',
                    orders:  s.orders_count ?? s.total_orders ?? '',
                    points:  s.points_balance ?? (s.points && s.points.balance) ?? '',
                    support: s.open_support_tickets ?? '',
                };
                window.dispatchEvent(new CustomEvent('bo-client-badges-updated'));
            }).catch(() => {});
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
