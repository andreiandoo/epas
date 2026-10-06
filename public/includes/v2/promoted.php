<?php
/**
 * viaqui.com v2: paid placements ("Promovare activitate" / "Promovare locație" bought in /organizator/servicii).
 *
 * The job for api_cached_many() of each page, the card shape and the section markup, shared by the homepage
 * ("Recomandate pentru tine"), the category page ("Promovate în …") and the city page ("Populare în …").
 * Items come from core /activities-module/promoted, which only lists what is paid for and running today. An empty
 * list (nothing promoted, or a core without the endpoint yet) hides the section: nothing here invents content.
 *
 * Every card says "Promovat": a paid position is labelled as such.
 *
 * Requires v2/helpers.php.
 */

/** Job for api_cached_many(): placement home_hero | home_recommendations | category | city. */
function v2_promoted_job(string $placement, array $params = []): array
{
    $params = array_filter(['placement' => $placement] + $params, fn ($v) => $v !== null && $v !== '');
    return [
        'key' => 'v2_promoted_' . $placement . '_' . md5(json_encode($params)),
        'endpoint' => '/activities-module/promoted',
        'params' => $params,
        'ttl' => 120,
    ];
}

/** Cards from one /activities-module/promoted response. */
function v2_promoted_items($response): array
{
    $raw = !empty($response['success']) ? ($response['data']['items'] ?? []) : [];
    $out = [];
    foreach ((array) $raw as $i) {
        if (!is_array($i) || !($n = v2_promoted_card($i))) {
            continue;
        }
        $out[] = $n;
    }
    return $out;
}

/** Card shape (the same fields the .xp cards use). Null for anything without a title or a link on this site. */
function v2_promoted_card(array $i): ?array
{
    $title = navFlatName($i['title'] ?? '');
    $href = (string) ($i['href'] ?? '');
    if ($title === '' || !preg_match('#^/(experienta|locatie)/[a-z0-9-]+$#', $href)) {
        return null;
    }
    $isLocation = ($i['kind'] ?? '') === 'location';
    $cityName = navFlatName($i['city']['name'] ?? '');
    $locName = navFlatName($i['location']['name'] ?? '');
    return [
        'kind' => $isLocation ? 'location' : 'product',
        'title' => $title,
        'href' => $href,
        'image' => v2_media_url($i['image'] ?? null),
        'cat' => navFlatName($i['category']['name'] ?? '') ?: ($isLocation ? 'Venue' : 'Experience'),
        'place' => $locName !== '' && $cityName !== '' ? $locName . ', ' . $cityName : ($locName ?: $cityName),
        'city' => $cityName,
        'subtitle' => mb_substr(trim(strip_tags((string) navFlatName($i['subtitle'] ?? ''))), 0, 140),
        'price' => !empty($i['price_from_cents']) ? (int) round($i['price_from_cents'] / 100) : 0,
        'dur' => v2_duration((int) ($i['duration_minutes'] ?? 0)),
    ];
}

/**
 * Section with the promoted cards on a rail. $o: id (unique on the page), kicker, title, intro, class (extra section
 * class). Nothing is printed without items.
 */
function v2_promoted_section(array $items, array $o): void
{
    if (!$items) {
        return;
    }
    $id = preg_replace('/[^a-z0-9-]/', '', (string) ($o['id'] ?? 'promo'));
    ?>
  <section class="sec promo<?= !empty($o['class']) ? ' ' . v2_e($o['class']) : '' ?>" aria-labelledby="<?= $id ?>-h">
    <div class="wrap">
      <div class="sec-head">
        <div class="promo-head">
          <?php if (!empty($o['kicker'])): ?><p class="kicker"><?= v2_e($o['kicker']) ?></p><?php endif; ?>
          <h2 id="<?= $id ?>-h"><?= v2_e($o['title'] ?? 'Recomandate') ?></h2>
          <?php if (!empty($o['intro'])): ?><p class="promo-intro"><?= v2_e($o['intro']) ?></p><?php endif; ?>
        </div>
        <?php if (count($items) > 3): ?>
        <div class="rail-btns" data-for="<?= $id ?>-rail">
          <button class="rail-btn" type="button" data-dir="-1" aria-label="Previous"><?= v2_ic('arrow-left') ?></button>
          <button class="rail-btn" type="button" data-dir="1" aria-label="Next"><?= v2_ic('arrow-right') ?></button>
        </div>
        <?php endif; ?>
      </div>
      <ul class="rail promo-rail" id="<?= $id ?>-rail" data-drag>
        <?php foreach ($items as $n => $a): ?>
        <li class="xp promo-xp"><a href="<?= v2_e($a['href']) ?>">
          <span class="xp-media"><?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $n) ?><span class="promo-tag">Promoted</span></span>
          <span class="xp-body">
            <span class="xp-cat"><?= v2_e($a['cat']) ?></span>
            <span class="xp-title" title="<?= v2_e($a['title']) ?>"><?= v2_e($a['title']) ?></span>
            <span class="xp-meta"><?php if ($a['place']): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['place']) ?></span><?php endif; ?><?php if ($a['dur']): ?><span><?= v2_ic('clock') ?><?= v2_e($a['dur']) ?></span><?php endif; ?></span>
            <span class="xp-foot"><span class="xp-avail"><?= v2_ic('arrow-right') ?><span class="xp-avail-t"><?= $a['kind'] === 'location' ? 'See the venue' : 'See the experience' ?></span></span><?php if ($a['price']): ?><span class="xp-price">de la<b><?= v2_thousands($a['price']) ?> lei</b></span><?php endif; ?></span>
          </span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
    <?php
}

/** The "Promovat" pill for cards in the regular lists (grids of activities or locations). */
function v2_promoted_tag(): string
{
    return '<span class="promo-tag">Promoted</span>';
}
