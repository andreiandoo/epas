<?php

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: /repertoriu'); exit; }

$resp  = api_get('/tenant-client/events/' . rawurlencode($slug), [], 60);
$event = ($resp['success'] && !empty($resp['data'])) ? tc_norm_event($resp['data']) : null;

if (!$event) {
    http_response_code(404);
    $pageTitle = 'Spectacol negăsit — ' . SITE_NAME;
    include ts_layout('head');
    include ts_layout('header');
    echo '<section class="pt-40 pb-40 px-4 text-center"><h1 class="font-display text-4xl mb-4">Spectacol negăsit</h1><a href="/repertoriu" class="btn-primary px-6 py-3 rounded-lg inline-block mt-4">Vezi repertoriul</a></section>';
    include ts_layout('footer');
    exit;
}

$eventId  = (int) ($event['id'] ?? 0);
$fallback = 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1200&q=80';
$hero     = asset_url($event['hero_image_url'] ?? $event['poster_url'] ?? null, $fallback);
$venueName = $event['venue']['name'] ?? '';
$dateStr = '';
if (!empty($event['start_date'])) {
    $ts = strtotime($event['start_date']);
    if ($ts) { $dateStr = date('d.m.Y', $ts) . (!empty($event['start_time']) ? ' · ' . substr($event['start_time'], 0, 5) : ''); }
}

$pageTitle = ($event['title'] ?? 'Spectacol') . ' — ' . SITE_NAME;
$pageDescription = ts_plain($event['short_description'] ?? '') ?: ts_plain($event['description'] ?? '');
$pageImage = $event['poster_url'] ?? $event['hero_image_url'] ?? null;
$pageSchema = [
    ts_event_schema($event, ['type' => 'TheaterEvent', 'performers' => array_map(fn ($a) => ['name' => $a['name'] ?? ''], (array) ($event['artists'] ?? []))]),
    ts_breadcrumb_schema([['Acasă', '/'], ['Repertoriu', ts_link('catalog')], [$event['title'] ?? '', ts_link('event', ['slug' => $event['slug'] ?? ''])]]),
];
$fallback = 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=1920&q=80';
$poster   = asset_url($event['poster_url'] ?? $event['hero_image_url'] ?? null, 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=600&q=80');
$th        = $event['theater'] ?? [];
$director  = $th['director'] ?? null;
$lead      = $th['lead'] ?? ($event['artists'][0]['name'] ?? null);
$duration  = $th['duration'] ?? null;
$castList  = $th['cast'] ?? [];
$creative  = $th['creative'] ?? [];

// Bilete fără loc (acces general): tipurile active, cu prețul redus dacă există
$types = [];
foreach (($event['ticket_types'] ?? []) as $t) {
    if (($t['status'] ?? 'active') !== 'active' || empty($t['id']) || !empty($t['has_seating'])) { continue; }
    $full = (float) ($t['price'] ?? 0);
    $sale = isset($t['sale_price']) && $t['sale_price'] !== null ? (float) $t['sale_price'] : null;
    $types[] = [
        'id'        => (int) $t['id'],
        'name'      => (string) ($t['name'] ?? 'Bilet'),
        'desc'      => trim(strip_tags((string) ($t['description'] ?? ''))),
        'price'     => ($sale !== null && $sale > 0 && $sale < $full) ? $sale : $full,
        'full'      => $full,
        'available' => (int) ($t['available'] ?? 0),
    ];
}
// Biletele fără loc apar lângă hartă doar dacă există și bilete cu loc (altfel ele sunt toată oferta)
$openTypes = (bool) array_filter((array) ($event['ticket_types'] ?? []), fn ($t) => !empty($t['has_seating']));
// Evenimentul așa cum îl ține coșul comun (commerce.js)
$cartEvent = [
    'id'     => $eventId,
    'slug'   => (string) ($event['slug'] ?? $slug),
    'title'  => (string) ($event['title'] ?? ''),
    'date'   => $dateStr,
    'place'  => $venueName,
    'poster' => $poster,
    'url'    => ts_link('event', ['slug' => $event['slug'] ?? $slug]),
];
include ts_layout('head');
include ts_layout('header');
?>
<!-- Hero -->
<section class="relative pt-20">
    <div class="absolute inset-0 h-[50vh]">
        <img src="<?= e($hero) ?>" alt="<?= e($event['title'] ?? '') ?>" class="w-full h-full object-cover opacity-30">
        <div class="absolute inset-0 bg-gradient-to-t from-canvas via-canvas/80 to-canvas/40"></div>
    </div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 lg:px-8 pt-10 pb-8">
        <a href="/program" class="text-fg/70 hover:text-primary text-sm transition-colors">← Înapoi la program</a>
        <div class="flex flex-col lg:flex-row gap-8 mt-6">
            <!-- Poster -->
            <div class="lg:w-80 flex-shrink-0">
                <img src="<?= e($poster) ?>" alt="<?= e($event['title'] ?? '') ?>" class="w-full rounded-lg border border-primary/20 shadow-2xl aspect-[3/4] object-cover">
            </div>
            <!-- Info -->
            <div class="flex-1">
                <?php if (!empty($event['category']['name'])): ?>
                    <div class="flex items-center gap-3 mb-4"><span class="text-primary"><?= e($event['category']['name']) ?></span></div>
                <?php endif; ?>
                <?php if ($lead): ?><p class="text-primary tracking-wider text-sm mb-2 uppercase"><?= e($lead) ?></p><?php endif; ?>
                <div class="flex items-start justify-between gap-4">
                    <h1 class="font-display text-5xl lg:text-7xl italic mb-3"><?= e($event['title'] ?? 'Spectacol') ?></h1>
                    <button x-data="favBtn('event', <?= $eventId ?>, <?= htmlspecialchars(json_encode(['title' => $event['title'] ?? '', 'slug' => $slug, 'category' => $event['category']['name'] ?? ''], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)" x-init="init()" @click="toggle()" :aria-pressed="on" title="Adaugă la favorite"
                        class="mt-2 grid h-12 w-12 shrink-0 place-items-center rounded-full border transition-colors"
                        :class="on ? 'border-primary bg-primary/15 text-primary' : 'border-primary/25 text-fg/70 hover:border-primary/60 hover:text-primary'">
                        <svg class="h-6 w-6" :fill="on ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 21s-7.5-4.6-10-9.3C.5 8.4 2 4.8 5.3 4.8c2 0 3.3 1.2 4.2 2.5.9-1.3 2.2-2.5 4.2-2.5 3.3 0 4.8 3.6 3.3 6.9C19.5 16.4 12 21 12 21Z"/></svg>
                    </button>
                </div>
                <?php if (!empty($event['subtitle'])): ?>
                    <p class="text-fg/60 font-display text-xl lg:text-2xl italic mb-6"><?= e($event['subtitle']) ?></p>
                <?php endif; ?>
                <?php if (!empty($event['short_description'])): ?>
                    <p class="text-fg/70 text-lg leading-relaxed mb-8 max-w-2xl"><?= e($event['short_description']) ?></p>
                <?php endif; ?>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-4">
                    <?php if ($director): ?><div><p class="text-muted text-sm">Regia</p><p class="font-display text-lg"><?= e($director) ?></p></div><?php endif; ?>
                    <?php if ($lead): ?><div><p class="text-muted text-sm">În rolul principal</p><p class="font-display text-lg"><?= e($lead) ?></p></div><?php endif; ?>
                    <?php if ($duration): ?><div><p class="text-muted text-sm">Durata</p><p class="font-display text-lg"><?= e($duration) ?></p></div><?php endif; ?>
                    <?php if ($venueName): ?><div><p class="text-muted text-sm">Sală</p><p class="font-display text-lg"><?= e($venueName) ?></p></div><?php endif; ?>
                    <?php if ($dateStr): ?><div><p class="text-muted text-sm">Data</p><p class="font-display text-lg"><?= e($dateStr) ?></p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Bilete: harta sălii (locuri numerotate) și, dacă există, biletele fără loc -->
<section id="bilete" class="py-10 px-4 lg:px-8 bg-surface/30" x-data="{ map: null }" @ts-seatmap="map = $event.detail.ok">
    <div class="max-w-7xl mx-auto">
        <div class="divider-ornate mb-8"><span class="text-primary font-display text-xl">Alege locurile</span></div>
        <?php // Harta se încarcă din core; dacă spectacolul nu are sală desenată, rămân biletele simple ?>
        <div x-show="map !== false" data-ts-seatmap-inline="<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>"></div>

        <?php if ($types): ?>
        <div x-show="map === false || <?= $openTypes ? 'true' : 'false' ?>" x-cloak class="max-w-2xl mx-auto" :class="map ? 'mt-10' : ''"
            x-data='ticketPicker(<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>, <?= e(json_encode($types, JSON_UNESCAPED_UNICODE)) ?>)'>
            <p x-show="map" class="text-center text-muted mb-4">Bilete fără loc numerotat</p>
            <div class="space-y-3">
                <template x-for="t in types" :key="t.id">
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-surface/60 border border-primary/10 px-5 py-4">
                        <div class="min-w-0">
                            <p class="font-display text-lg" x-text="t.name"></p>
                            <p class="text-sm text-muted" x-show="t.desc" x-text="t.desc"></p>
                            <p class="mt-1 text-primary font-display">
                                <span x-text="lei(t.price)"></span>
                                <s class="ml-2 text-sm text-muted" x-show="t.full > t.price" x-text="lei(t.full)"></s>
                            </p>
                        </div>
                        <div class="inline-flex items-center rounded-full border border-primary/20">
                            <button type="button" class="h-10 w-10 text-xl disabled:opacity-30" @click="dec(t)" :disabled="!qty[t.id]" :aria-label="'Scade ' + t.name">−</button>
                            <output class="min-w-[2rem] text-center font-semibold" x-text="qty[t.id]"></output>
                            <button type="button" class="h-10 w-10 text-xl disabled:opacity-30" @click="inc(t)" :disabled="qty[t.id] >= limit(t)" :aria-label="'Adaugă ' + t.name">+</button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-surface p-5">
                <div><p class="text-sm text-muted" x-text="ticketsLabel(count)"></p><p data-total class="font-display text-3xl text-primary" x-text="lei(total)"></p></div>
                <button type="button" @click="save()" class="btn-primary rounded-lg px-8 py-4 text-lg" :disabled="!count">Adaugă în coș</button>
            </div>
        </div>
        <?php else: ?>
        <p x-show="map === false" x-cloak class="text-center py-12 text-muted">Biletele pentru acest spectacol nu sunt încă puse în vânzare.</p>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($event['description']) || !empty($castList) || !empty($creative)): ?>
<!-- Despre spectacol -->
<section class="py-16 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <?php if (!empty($event['description'])): ?>
        <h2 class="font-display text-3xl mb-6">Despre spectacol</h2>
        <div class="prose prose-invert prose-lg max-w-none text-fg/70"><?= $event['description'] ?></div>
        <?php endif; ?>

        <?php if (!empty($castList)): ?>
        <div class="mt-12">
            <h3 class="font-display text-2xl mb-6">Distribuție</h3>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($castList as $c): ?>
                <div class="flex items-center justify-between gap-4 bg-surface/50 rounded-lg px-5 py-4">
                    <?php if (!empty($c['slug'])): ?>
                        <a href="/artist/<?= e($c['slug']) ?>" class="font-display text-lg text-primary hover:text-primary/80 transition-colors"><?= e($c['name'] ?? '') ?></a>
                    <?php else: ?>
                        <p class="font-display text-lg"><?= e($c['name'] ?? '') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($c['role'])): ?><p class="text-muted text-sm text-right"><?= e($c['role']) ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($creative)): ?>
        <div class="mt-12">
            <h3 class="font-display text-2xl mb-6">Echipa creativă</h3>
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach ($creative as $cr): ?>
                <div class="bg-surface/40 rounded-lg p-4">
                    <p class="text-muted text-sm"><?= e($cr['role'] ?? '') ?></p>
                    <?php if (!empty($cr['slug'])): ?>
                        <a href="/artist/<?= e($cr['slug']) ?>" class="font-display text-lg text-primary hover:text-primary/80 transition-colors"><?= e($cr['name'] ?? '') ?></a>
                    <?php else: ?>
                        <p class="font-display text-lg"><?= e($cr['name'] ?? '') ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($event['gallery'])): ?>
<!-- Galerie foto -->
<section class="py-16 px-4 lg:px-8 bg-surface/20">
    <div class="max-w-6xl mx-auto">
        <div class="divider-ornate mb-10"><span class="text-primary font-display text-2xl">Galerie</span></div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <?php foreach ($event['gallery'] as $img): ?>
            <a href="<?= e($img) ?>" target="_blank" class="block aspect-[4/3] overflow-hidden rounded-lg border border-primary/10 group">
                <img src="<?= e($img) ?>" alt="<?= e($event['title'] ?? '') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
include ts_layout('footer');
