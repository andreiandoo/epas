<?php
/**
 * Faceted activity search — /cauta  (F5).
 *
 * Server-side faceted search over the Activities API. The header command-search
 * + every "Caută" CTA land here with ?q=. Facets (city, category, price,
 * interests, traveler types, sort) are plain query-string toggles, so the page
 * is fully crawlable + shareable and needs no client-side state. Also backs the
 * discovery landings /interese/{slug} and /pentru-cine/{slug} (preset facets).
 */

$pageCacheTTL = 120;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

// ---- Input ----
$q        = isset($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';
$cityF    = (isset($_GET['city']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['city'])) ? $_GET['city'] : '';
$catF     = (isset($_GET['category']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['category'])) ? $_GET['category'] : '';
$intF     = array_values(array_filter(array_map('trim', explode(',', (string) ($_GET['interests'] ?? '')))));
$travF    = array_values(array_filter(array_map('trim', explode(',', (string) ($_GET['traveler_types'] ?? '')))));
$priceAllowed = [50, 100, 200, 500];
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceAllowed, true)) ? (int) $_GET['max_price'] : null;
$sortAllowed = ['recommended', 'cheapest', 'soon'];
$sort     = (isset($_GET['sort']) && in_array($_GET['sort'], $sortAllowed, true)) ? $_GET['sort'] : 'recommended';
$page     = max(1, (int) ($_GET['page'] ?? 1));

// ---- Fetch ----
$params = ['per_page' => 24, 'page' => $page];
if ($q !== '')   $params['search'] = $q;
if ($cityF)      $params['city'] = $cityF;
if ($catF)       $params['category'] = $catF;
if ($intF)       $params['interests'] = implode(',', $intF);
if ($travF)      $params['traveler_types'] = implode(',', $travF);
if ($maxPrice)   $params['max_price_ron'] = $maxPrice;
if ($sort !== 'recommended') $params['sort'] = $sort;

$resp = api_cached('search_' . md5(json_encode($params)), fn () => api_get('/activities', $params), 120);
$items = $resp['data']['items'] ?? [];
if (!is_array($items)) $items = [];
$pagination = $resp['data']['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($items)];
$total = (int) ($pagination['total'] ?? count($items));

// ---- Facet option pools ----
$navCities     = navGetCities(24);
$navCategories = navGetCategories(24);
// Interests / traveler types facets derived from the current result set.
$intNames = [];
$travNames = [];
foreach ($items as $a) {
    foreach ((array) ($a['interests'] ?? []) as $i) if (!empty($i['slug'])) $intNames[$i['slug']] = $i['name'] ?? $i['slug'];
    foreach ((array) ($a['traveler_types'] ?? []) as $t) if (!empty($t['slug'])) $travNames[$t['slug']] = $t['name'] ?? $t['slug'];
}

// ---- Query-string helpers (facet toggles) ----
$baseGet = $_GET; unset($baseGet['page']);
$bo_qs = function (array $over) use ($baseGet) {
    $p = array_merge($baseGet, $over);
    foreach ($p as $k => $v) { if ($v === '' || $v === null || $v === []) unset($p[$k]); }
    return $p ? '/cauta?' . http_build_query($p) : '/cauta';
};
$bo_toggle_csv = function (string $key, string $val) use ($baseGet, $bo_qs) {
    $cur = array_values(array_filter(array_map('trim', explode(',', (string) ($baseGet[$key] ?? '')))));
    $cur = in_array($val, $cur, true) ? array_diff($cur, [$val]) : array_merge($cur, [$val]);
    return $bo_qs([$key => implode(',', $cur)]);
};
$bo_has_csv = fn (string $key, string $val) => in_array($val, array_filter(array_map('trim', explode(',', (string) ($baseGet[$key] ?? '')))), true);

$bo_img = function ($u) {
    $u = (string) $u; if ($u === '') return '';
    return str_starts_with($u, 'http') ? $u : rtrim(STORAGE_URL, '/') . '/' . ltrim($u, '/');
};
$durationLabel = function (int $m): string { if ($m <= 0) return ''; if ($m < 60) return $m . ' min'; $h = intdiv($m, 60); $r = $m % 60; return $r ? "{$h}h {$r}m" : "{$h}h"; };
$priceFrom = fn ($c) => $c ? number_format($c / 100, 0, ',', '.') . ' lei' : '';
$cardUrl = fn ($a) => ($a['city']['slug'] ?? '') ? '/' . $a['city']['slug'] . '/' . ($a['slug'] ?? '') : '/activitate/' . ($a['slug'] ?? '');

// ---- Contextual H1 (also serves /interese & /pentru-cine presets) ----
$heading = 'Caută activități';
if ($q !== '') {
    $heading = 'Rezultate pentru „' . $q . '"';
} elseif ($travF && isset($travNames[$travF[0]])) {
    $heading = 'Activități pentru ' . mb_strtolower($travNames[$travF[0]]);
} elseif ($intF && isset($intNames[$intF[0]])) {
    $heading = 'Activități · ' . $intNames[$intF[0]];
} elseif ($catF) {
    foreach ($navCategories as $c) if ($c['slug'] === $catF) { $heading = $c['label']; break; }
}

$activeCount = ($q !== '' ? 1 : 0) + ($cityF ? 1 : 0) + ($catF ? 1 : 0) + count($intF) + count($travF) + ($maxPrice ? 1 : 0);

$pageTitleRaw = ($q !== '' ? $heading : 'Caută activități, experiențe și atracții') . ' | bilete.online';
$pageDescription = 'Caută și filtrează activități pe bilete.online după oraș, categorie, preț, interese și pentru cine. Rezervi online cu bilet QR.';
$canonicalUrl = SITE_URL . '/cauta';
$currentPage = 'cauta';
$cssBundle = 'listing';
$headerContext = ['type' => 'homepage'];
$bodyClass = '';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<section class="border-b border-ink/10 bg-paper">
    <div class="mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:py-10">
        <p class="font-mono text-xs tracking-[.18em] text-vermilion">CĂUTARE</p>
        <h1 class="mt-2 font-display text-5xl font-bold leading-none sm:text-6xl"><?= htmlspecialchars($heading) ?></h1>
        <p class="mt-3 text-ink-soft"><strong><?= $total ?></strong> <?= $total === 1 ? 'rezultat' : 'rezultate' ?></p>

        <form action="/cauta" method="get" class="mt-6 max-w-3xl rounded-full border-2 border-ink bg-paper p-2">
            <div class="flex items-center gap-2">
                <svg viewBox="0 0 24 24" class="ml-3 h-5 w-5 shrink-0 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" class="w-full bg-transparent px-2 py-3 font-bold outline-none placeholder:text-ink-soft/70" placeholder="Caută activități, orașe, experiențe...">
                <?php foreach (['city' => $cityF, 'category' => $catF, 'interests' => implode(',', $intF), 'traveler_types' => implode(',', $travF)] as $hk => $hv): ?>
                    <?php if ($hv !== ''): ?><input type="hidden" name="<?= $hk ?>" value="<?= htmlspecialchars($hv, ENT_QUOTES) ?>"><?php endif; ?>
                <?php endforeach; ?>
                <button class="shrink-0 rounded-full bg-vermilion px-6 py-3 font-bold text-paper transition hover:bg-vermilion-d">Caută</button>
            </div>
        </form>
    </div>
</section>

<section class="mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:py-10">
    <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
        <!-- Facets -->
        <aside class="space-y-6">
            <?php if ($activeCount): ?>
                <a href="<?= htmlspecialchars($bo_qs(['city' => '', 'category' => '', 'interests' => '', 'traveler_types' => '', 'max_price' => '', 'q' => $q]), ENT_QUOTES) ?>" class="inline-flex rounded-full bg-ink px-4 py-2 text-sm font-bold text-paper">Șterge filtrele (<?= $activeCount ?>) ×</a>
            <?php endif; ?>

            <div>
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft mb-3">PREȚ MAXIM</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($priceAllowed as $p): ?>
                        <a href="<?= htmlspecialchars($bo_qs(['max_price' => $maxPrice === $p ? '' : $p]), ENT_QUOTES) ?>" class="rounded-full border-2 px-4 py-2 text-sm font-bold transition <?= $maxPrice === $p ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper-2 hover:border-ink' ?>">sub <?= $p ?> lei</a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($navCities)): ?>
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft mb-3">ORAȘ</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($navCities, 0, 12) as $c): ?>
                        <a href="<?= htmlspecialchars($bo_qs(['city' => $cityF === $c['slug'] ? '' : $c['slug']]), ENT_QUOTES) ?>" class="rounded-full border-2 px-3 py-1.5 text-sm font-bold transition <?= $cityF === $c['slug'] ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper-2 hover:border-ink' ?>"><?= htmlspecialchars($c['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($navCategories)): ?>
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft mb-3">CATEGORIE</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($navCategories, 0, 12) as $c): ?>
                        <a href="<?= htmlspecialchars($bo_qs(['category' => $catF === $c['slug'] ? '' : $c['slug']]), ENT_QUOTES) ?>" class="rounded-full border-2 px-3 py-1.5 text-sm font-bold transition <?= $catF === $c['slug'] ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper-2 hover:border-ink' ?>"><?= htmlspecialchars($c['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($intNames)): ?>
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft mb-3">INTERESE</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($intNames as $s => $n): ?>
                        <a href="<?= htmlspecialchars($bo_toggle_csv('interests', $s), ENT_QUOTES) ?>" class="rounded-full border-2 px-3 py-1.5 text-sm font-bold transition <?= $bo_has_csv('interests', $s) ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper-2 hover:border-ink' ?>"><?= htmlspecialchars($n) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($travNames)): ?>
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft mb-3">PENTRU CINE</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($travNames as $s => $n): ?>
                        <a href="<?= htmlspecialchars($bo_toggle_csv('traveler_types', $s), ENT_QUOTES) ?>" class="rounded-full border-2 px-3 py-1.5 text-sm font-bold transition <?= $bo_has_csv('traveler_types', $s) ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper-2 hover:border-ink' ?>"><?= htmlspecialchars($n) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Results -->
        <div>
            <div class="mb-5 flex items-center justify-between gap-4">
                <p class="text-sm font-bold text-ink-soft"><?= $total ?> rezultate</p>
                <div class="flex gap-2 text-sm font-bold">
                    <?php foreach (['recommended' => 'Recomandate', 'cheapest' => 'Preț', 'soon' => 'Curând'] as $sk => $sl): ?>
                        <a href="<?= htmlspecialchars($bo_qs(['sort' => $sk === 'recommended' ? '' : $sk]), ENT_QUOTES) ?>" class="rounded-full px-3 py-1.5 transition <?= $sort === $sk ? 'bg-ink text-paper' : 'bg-paper-2 hover:bg-ink hover:text-paper' ?>"><?= $sl ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (empty($items)): ?>
                <div class="rounded-[2rem] border-2 border-ink bg-paper p-10 text-center">
                    <p class="font-display text-4xl font-bold leading-none">Niciun rezultat.</p>
                    <p class="mt-3 text-ink-soft">Încearcă alți termeni sau șterge câteva filtre.</p>
                    <a href="/cauta" class="mt-5 inline-flex rounded-full bg-vermilion px-6 py-3 font-bold text-paper">Resetează căutarea</a>
                </div>
            <?php else: ?>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($items as $a): $title = is_array($a['title'] ?? null) ? navFlatName($a['title']) : ($a['title'] ?? ''); ?>
                        <a href="<?= htmlspecialchars($cardUrl($a)) ?>" class="group overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper shadow-deep transition hover:-translate-y-1">
                            <div class="relative h-44 overflow-hidden bg-ink">
                                <?php $img = $bo_img($a['cover_image_url'] ?? ''); if ($img): ?>
                                    <img src="<?= htmlspecialchars($img, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                                <?php else: ?>
                                    <div class="grid h-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-paper"><span class="px-3 text-center font-display text-lg font-bold"><?= htmlspecialchars(mb_substr($title, 0, 22)) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($a['category']['name'])): ?><span class="absolute left-3 top-3 rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink"><?= htmlspecialchars($a['category']['name']) ?></span><?php endif; ?>
                            </div>
                            <div class="p-4">
                                <p class="font-display text-xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion"><?= htmlspecialchars($title) ?></p>
                                <p class="mt-2 text-sm text-ink-soft"><?= htmlspecialchars(trim(($a['city']['name'] ?? '') . (!empty($a['duration_minutes']) ? ' · ' . $durationLabel((int) $a['duration_minutes']) : ''), ' ·')) ?></p>
                                <?php if (!empty($a['cheapest_price_cents'])): ?><p class="mt-3 font-bold"><span class="text-xs font-normal text-ink-soft">de la</span> <?= $priceFrom($a['cheapest_price_cents']) ?></p><?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php $last = (int) ($pagination['last_page'] ?? 1); if ($last > 1): ?>
                    <div class="mt-10 flex flex-wrap items-center justify-center gap-2">
                        <?php for ($p = 1; $p <= min($last, 12); $p++): ?>
                            <a href="<?= htmlspecialchars($bo_qs(['page' => $p === 1 ? '' : $p]), ENT_QUOTES) ?>" class="grid h-11 min-w-11 place-items-center rounded-full border-2 px-3 font-bold transition <?= $page === $p ? 'border-ink bg-ink text-paper' : 'border-ink/15 hover:border-ink' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
