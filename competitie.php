<?php
require_once __DIR__ . '/includes/boot.php';

$slug = preg_replace('/[^a-z0-9\-]/i', '', (string) ($_GET['slug'] ?? ''));
$ev   = $slug !== '' ? tc_event($slug, 60) : null;

if (!$ev) {
    http_response_code(404);
    $pageTitle = 'Competiție negăsită — ' . SITE_NAME;
    include __DIR__ . '/includes/head.php'; ?>
    <main class="wrap">
        <div class="panel empty" style="margin:56px 0 96px">
            <h2>Competiția nu a fost găsită</h2>
            <p>E posibil ca pagina să fi fost mutată sau competiția să se fi încheiat.</p>
            <a class="btn" href="/competitii">Vezi calendarul</a>
        </div>
    </main>
    <?php include __DIR__ . '/includes/footer.php';
    exit;
}

$place   = ev_place($ev);
$time    = ev_time($ev);
$venue   = $ev['venue'] ?? [];
$soldOut = !empty($ev['is_sold_out']);
$off     = !empty($ev['is_cancelled']);

// Tipurile de bilete, în forma folosită de selector (prețul efectiv = cel redus, dacă există)
$types = [];
foreach (($ev['ticket_types'] ?? []) as $t) {
    if (($t['status'] ?? 'active') !== 'active') { continue; }
    $full = (float) ($t['price'] ?? 0);
    $sale = isset($t['sale_price']) && $t['sale_price'] !== null ? (float) $t['sale_price'] : null;
    $types[] = [
        'id'        => (int) $t['id'],
        'name'      => (string) $t['name'],
        'desc'      => trim(strip_tags((string) ($t['description'] ?? ''))),
        'price'     => ($sale !== null && $sale > 0 && $sale < $full) ? $sale : $full,
        'full'      => $full,
        'available' => (int) ($t['available'] ?? 0),
    ];
}
usort($types, fn ($a, $b) => $a['id'] <=> $b['id']);   // ordinea din admin
$canBuy = $types && !$soldOut && !$off;

// Datele despre competiție păstrate în coș (afișate în coș și la finalizare)
$cartEvent = [
    'id'     => (int) $ev['id'],
    'slug'   => $ev['slug'],
    'title'  => $ev['title'],
    'date'   => ev_date_label($ev),
    'place'  => $place,
    'poster' => $ev['poster_url'] ?? null,
];

$mapQuery = trim(($venue['name'] ?? '') . ' ' . ($venue['address'] ?? '') . ' ' . ($venue['city'] ?? ''));

$activeNav       = 'events';
$pageTitle       = $ev['title'] . ' — bilete | ' . SITE_SHORT;
$pageDescription = trim(strip_tags((string) ($ev['short_description'] ?? ''))) ?: ('Bilete la ' . $ev['title'] . ', ' . ev_date_label($ev) . ', ' . $place . '.');
if (!empty($ev['poster_url'])) { $pageImage = $ev['poster_url']; }
$bodyClass = $canBuy ? 'has-mobile-bar' : '';

// Date structurate pentru motoarele de căutare
[$startDay, $endDay] = ev_span($ev);
$ld = [
    '@context' => 'https://schema.org', '@type' => 'SportsEvent',
    'name' => $ev['title'], 'sport' => 'Karate',
    'startDate' => $startDay ? $startDay . ($time ? 'T' . $time : '') : null,
    'endDate' => $endDay ?: $startDay,
    'eventStatus' => 'https://schema.org/' . ($off ? 'EventCancelled' : 'EventScheduled'),
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'image' => $ev['poster_url'] ?? null,
    'description' => $pageDescription,
    'location' => ['@type' => 'Place', 'name' => $venue['name'] ?? '', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $venue['city'] ?? '', 'streetAddress' => $venue['address'] ?? '', 'addressCountry' => 'RO']],
    'organizer' => ['@type' => 'SportsOrganization', 'name' => SITE_NAME, 'url' => SITE_FEDERATION],
    'offers' => array_map(fn ($t) => [
        '@type' => 'Offer', 'name' => $t['name'], 'price' => $t['price'], 'priceCurrency' => 'RON',
        'availability' => 'https://schema.org/' . ($soldOut ? 'SoldOut' : 'InStock'),
        'url' => 'https://' . TENANT_HOST . '/competitii/' . $ev['slug'],
    ], $types),
];
$pageExtraHead = '<script type="application/ld+json">' . json_encode(array_filter($ld), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

include __DIR__ . '/includes/head.php';
?>
<main x-data='ticketPicker(<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>, <?= e(json_encode($types, JSON_UNESCAPED_UNICODE)) ?>)'>
    <section class="ev-hero on-dark">
        <div class="wrap">
            <div class="ev-hero__grid">
                <div>
                    <nav class="crumbs" aria-label="Ești aici"><a href="/">Acasă</a> / <a href="/competitii">Competiții</a></nav>
                    <?php if ($off): ?>
                        <p style="margin-top:18px"><span class="tag tag--red">Anulată</span></p>
                    <?php elseif (!empty($ev['category']['name'])): ?>
                        <p style="margin-top:18px"><span class="tag"><?= e($ev['category']['name']) ?></span></p>
                    <?php endif; ?>
                    <h1><?= e($ev['title']) ?></h1>
                    <dl class="facts">
                        <div>
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
                            <div><dt>Data</dt><dd><?= e(ev_date_label($ev)) ?><?= $time ? ' · de la ' . e($time) : '' ?></dd></div>
                        </div>
                        <div>
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.2 7-11.5A7 7 0 005 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                            <div><dt>Locația</dt><dd><?= e($place) ?></dd></div>
                        </div>
                    </dl>
                </div>
                <div class="ev-hero__poster"><?= part_poster($ev, 'eager') ?></div>
            </div>
        </div>
    </section>

    <div class="wrap">
        <div class="ev-body">
            <article>
                <div class="prose">
                    <h2>Despre competiție</h2>
                    <?php if (!empty($ev['description'])): ?>
                        <?= $ev['description'] /* HTML redactat în admin */ ?>
                    <?php else: ?>
                        <p><?= e($pageDescription) ?></p>
                    <?php endif; ?>
                </div>

                <ul class="infolist">
                    <li><span>Data</span><div><?= e(ev_date_label($ev)) ?><?= $time ? ', acces public de la ' . e($time) : '' ?></div></li>
                    <li><span>Locația</span><div>
                        <?= e($venue['name'] ?? '') ?><?= !empty($venue['address']) ? ', ' . e($venue['address']) : '' ?><?= !empty($venue['city']) ? ', ' . e($venue['city']) : '' ?>
                        <?php if ($mapQuery !== ''): ?>
                            <br><a class="link" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($mapQuery)) ?>" target="_blank" rel="noopener">Deschide harta</a>
                        <?php endif; ?>
                    </div></li>
                    <li><span>Bilete</span><div>Electronice, trimise pe email imediat după plată. Fiecare bilet are un cod QR unic, scanat la intrare.</div></li>
                    <li><span>Organizator</span><div><?= e(SITE_NAME) ?> · <a class="link" href="<?= e(SITE_FEDERATION) ?>" target="_blank" rel="noopener">wukf.ro</a></div></li>
                </ul>
            </article>

            <aside class="ev-buy" id="bilete">
                <div class="buy">
                    <div class="buy__head"><h2>Bilete</h2><span>Acces general</span></div>
                    <?php if ($canBuy): ?>
                        <template x-for="t in types" :key="t.id">
                            <div class="tt">
                                <div>
                                    <div class="tt__name" x-text="t.name"></div>
                                    <div class="tt__desc" x-show="t.desc" x-text="t.desc"></div>
                                    <div class="tt__price">
                                        <span x-text="lei(t.price)"></span>
                                        <s x-show="t.full > t.price" x-text="lei(t.full)" style="font-size:16px;color:var(--muted);font-weight:700"></s>
                                    </div>
                                </div>
                                <div class="qty">
                                    <button type="button" @click="dec(t)" :disabled="qty[t.id] === 0" :aria-label="'Scade ' + t.name">−</button>
                                    <output x-text="qty[t.id]"></output>
                                    <button type="button" @click="inc(t)" :disabled="qty[t.id] >= limit(t)" :aria-label="'Adaugă ' + t.name">+</button>
                                </div>
                            </div>
                        </template>
                        <div class="buy__foot">
                            <div class="total"><span>Total</span><b x-text="lei(total)"></b></div>
                            <button type="button" class="btn btn--red btn--block" :class="count === 0 && 'is-off'" @click="save()">Continuă spre coș</button>
                            <p class="fine">Maximum <?= 10 ?> bilete de același tip pe comandă. Prețurile includ toate taxele.</p>
                        </div>
                    <?php else: ?>
                        <div class="buy__foot">
                            <p style="font-weight:600;margin-bottom:6px">
                                <?= $off ? 'Competiția a fost anulată.' : ($soldOut ? 'Biletele s-au epuizat.' : 'Biletele nu sunt încă în vânzare.') ?>
                            </p>
                            <p class="fine" style="margin-top:0">Urmărește calendarul pentru celelalte competiții ale sezonului.</p>
                            <a class="btn btn--block" style="margin-top:16px" href="/competitii">Vezi calendarul</a>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>

    <?php if ($canBuy): ?>
    <div class="mobile-bar">
        <div>
            <small x-text="count ? (count === 1 ? '1 bilet' : count + ' bilete') : 'Bilete de la'"></small>
            <b x-text="count ? lei(total) : '<?= e(lei(min(array_column($types, 'price')))) ?>'"></b>
        </div>
        <a class="btn btn--red btn--sm" href="#bilete" x-show="count === 0">Alege bilete</a>
        <button type="button" class="btn btn--red btn--sm" x-show="count > 0" x-cloak @click="save()">Spre coș</button>
    </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
