<?php
/**
 * Operator profile: /operator/{slug} and /organizator/{slug} (v2 design).
 *
 * Server-rendered from `GET /marketplace-events/organizers/{slug}`: a 404 for an unknown operator and a 503 (never a
 * soft 404) when the API can't be reached. That endpoint only lists core events, so the operator's activities are
 * taken from the `/activities` listing by organizer slug and shown first.
 *
 * Top to bottom: hero (avatar, badges, name, tagline, location, followers, rating, actions, socials), tabs
 * (activities / past / about with facts) beside the sidebar (about, quick facts, why bilete.online, contact card),
 * and the contact dialog that sends a message to the operator through the API proxy (operator.js).
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/../includes/page-cache.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$orgResp = api_cached("operator_profile_{$slug}", fn () => api_get('/marketplace-events/organizers/' . rawurlencode($slug)), 300);
$org = !empty($orgResp['success']) && is_array($orgResp['data'] ?? null) && !empty($orgResp['data']['name']) ? $orgResp['data'] : null;
if (!$org && (!empty($orgResp['success']) || stripos((string) ($orgResp['error'] ?? ''), 'not found') !== false)) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';

$v2Styles = ['operator.css'];

if (!$org) {
    // The API couldn't be reached: a temporary error the next visit retries, not a page search engines should drop.
    http_response_code(503);
    header('Retry-After: 120');
    $skipPageCache = true;
    $noindex = true;
    $pageTitleRaw = 'Operator · ' . SITE_NAME;
    $pageDescription = 'Profilul operatorului nu poate fi afișat momentan. Reîncearcă peste câteva minute.';
    include __DIR__ . '/../includes/v2/head.php';
    include __DIR__ . '/../includes/v2/header.php';
    ?>
<main id="main" class="page-main" tabindex="-1">
  <section class="sec op-main" aria-labelledby="op-down-h">
    <div class="wrap">
      <div class="op-empty">
        <span class="op-empty-ic"><?= v2_ic('clock') ?></span>
        <h1 class="op-down-h" id="op-down-h">Datele organizatorului nu sunt disponibile momentan.</h1>
        <p>Reîncearcă peste câteva minute.</p>
        <div class="op-down-cta"><a class="btn btn-primary" href="/operatori">Toți operatorii</a><a class="btn btn-ghost" href="/">Acasă</a></div>
      </div>
    </div>
  </section>
</main>
<?php
    include __DIR__ . '/../includes/v2/footer.php';
    exit;
}

// ------------------------------------------------------------------ helpers
$opText = function ($v): string {
    if (is_array($v) && array_key_exists('name', $v)) {
        $v = $v['name'];
    }
    if (is_array($v)) {
        $v = $v['ro'] ?? $v['en'] ?? reset($v);
    }
    return is_scalar($v) ? trim((string) $v) : '';
};
// Descriptions may carry editor markup: plain text that keeps its paragraphs.
$opPlain = function ($v) use ($opText): string {
    $s = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $opText($v));
    $s = preg_replace('#<(?:br\s*/?|/p|/li|/h[1-6]|/div)>#i', "\n", $s);
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = implode("\n", array_map(fn ($line) => trim(preg_replace('/[ \t]+/u', ' ', $line)), explode("\n", $s)));
    return trim(preg_replace("/\n{3,}/", "\n\n", $s));
};
$opExcerpt = function (string $text, int $max): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:–-') . '…';
};
$opUrl = fn ($u) => is_string($u) && preg_match('#^https?://[^\s"<>]+$#i', $u) ? $u : '';
$opDecimal = fn ($s) => str_replace('.', ',', (string) $s); // the API formats 1.2K and 4.8

$tz = new DateTimeZone('Europe/Bucharest');
$months = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'nov', 'dec'];
$opDate = function ($value) use ($tz): ?DateTimeImmutable {
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value, $tz))->setTimezone($tz);
    } catch (Exception $e) {
        return null;
    }
};
$opDay = fn (DateTimeImmutable $d) => $d->format('j') . ' ' . $months[(int) $d->format('n') - 1];

// ------------------------------------------------------------------ operator
$name = $opText($org['name']);
$initial = mb_strtoupper(mb_substr($name, 0, 1));
$avatar = v2_media_url($org['avatar'] ?? null);
$cover = v2_media_url($org['cover_image'] ?? null);
$verified = !empty($org['verified']);
$isPro = !empty($org['pro']);
$about = $opPlain($org['about'] ?? '');
$tagline = $opExcerpt($opPlain($org['tagline'] ?? ''), 220);
$location = $opText($org['location'] ?? '') ?: 'România';
$stats = is_array($org['stats'] ?? null) ? $org['stats'] : [];
$followers = trim((string) ($stats['followers'] ?? '0'));
$rating = trim((string) ($stats['rating'] ?? '-'));
$social = is_array($org['social'] ?? null) ? $org['social'] : [];
$website = $opUrl($social['website'] ?? '');
$socialLinks = array_values(array_filter([
    ['Facebook', $opUrl($social['facebook'] ?? ''), '<path fill="currentColor" stroke="none" d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>'],
    ['Instagram', $opUrl($social['instagram'] ?? ''), '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>'],
    ['Website', $website, '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'],
], fn ($link) => $link[1] !== ''));

$factIcons = ['calendar' => 'calendar-blank', 'location' => 'map-pin', 'star' => 'star', 'shield' => 'check-circle'];
$facts = [];
foreach ((array) ($org['facts'] ?? []) as $fact) {
    if (!is_array($fact)) {
        continue;
    }
    $factLabel = $opText($fact['label'] ?? '');
    $factValue = $opText($fact['value'] ?? '');
    if ($factLabel === '' || $factValue === '') {
        continue;
    }
    $factIcon = (string) ($fact['icon'] ?? '');
    $facts[] = [$factIcons[$factIcon] ?? 'check', $factLabel, $factIcon === 'star' ? $opDecimal($factValue) : $factValue];
}

// The operator's activities: the organizer endpoint doesn't list them, the activities listing names the organizer.
$activities = [];
for ($listPage = 1; $listPage <= 4; $listPage++) {
    $listResp = api_cached("operator_activities_p{$listPage}", fn () => api_get('/activities', ['per_page' => 50, 'page' => $listPage]), 600);
    foreach ((array) ($listResp['data']['items'] ?? []) as $a) {
        if (is_array($a) && is_array($a['organizer'] ?? null) && ($a['organizer']['slug'] ?? '') === $slug && ($card = v2_activity($a))) {
            $activities[] = $card;
        }
    }
    if ($listPage >= (int) ($listResp['data']['pagination']['last_page'] ?? 1)) {
        break;
    }
}

$events = [];
foreach ((array) ($org['upcomingEvents'] ?? []) as $ev) {
    if (!is_array($ev)) {
        continue;
    }
    $evTitle = $opText($ev['title'] ?? '');
    $evSlug = (string) ($ev['slug'] ?? '');
    if ($evTitle === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $evSlug)) {
        continue;
    }
    $start = $opDate($ev['event_date'] ?? null) ?: $opDate($ev['range_start_date'] ?? null);
    $end = $opDate($ev['range_end_date'] ?? null);
    $when = '';
    if ($start && $end && $end->format('Y-m-d') > $start->format('Y-m-d')) {
        $when = ($start->format('Y-n') === $end->format('Y-n') ? $start->format('j') : $opDay($start)) . ' – ' . $opDay($end);
    }
    $evPrice = $ev['display_price'] ?? $ev['price'] ?? null;
    $events[] = [
        'title' => $evTitle,
        'url' => '/bilete/' . $evSlug,
        'image' => v2_media_url($ev['image'] ?? $ev['poster_url'] ?? null),
        'day' => $start ? $start->format('j') : '',
        'month' => $start ? $months[(int) $start->format('n') - 1] : '',
        'when' => $when,
        'time' => preg_match('/^\d{1,2}:\d{2}$/', (string) ($ev['start_time'] ?? '')) ? (string) $ev['start_time'] : '',
        'venue' => implode(', ', array_filter([$opText($ev['venue_name'] ?? ''), $opText($ev['venue_city'] ?? '')], 'strlen')),
        'category' => $opText($ev['category'] ?? ''),
        'price' => is_numeric($evPrice) && $evPrice > 0 ? v2_thousands((int) ceil((float) $evPrice)) . ' lei' : '',
        'soldOut' => !empty($ev['is_sold_out']) || ($ev['status'] ?? '') === 'soldout',
    ];
}

$past = [];
foreach ((array) ($org['pastEvents'] ?? []) as $ev) {
    if (!is_array($ev) || ($evTitle = $opText($ev['title'] ?? '')) === '') {
        continue;
    }
    $evDate = $opDate($ev['event_date'] ?? null);
    $past[] = [
        'title' => $evTitle,
        'image' => v2_media_url($ev['image'] ?? $ev['poster_url'] ?? null),
        'date' => $evDate ? $opDay($evDate) . ' ' . $evDate->format('Y') : $opText($ev['date'] ?? ''),
        'venue' => implode(', ', array_filter([$opText($ev['venue_name'] ?? ''), $opText($ev['venue_city'] ?? '')], 'strlen')),
    ];
}

$upcomingCount = count($activities) + count($events);
$pastCount = count($past);
$heroImage = $cover ?: (($activities[0]['image'] ?? null) ?: ($events[0]['image'] ?? null));
$aboutText = $about !== '' ? $about : 'Informații indisponibile momentan.';

// ------------------------------------------------------------------ page
$breadcrumbs = [['name' => 'Acasă', 'url' => '/'], ['name' => 'Operatori', 'url' => '/operatori'], ['name' => $name, 'url' => '/operator/' . $slug]];

$pageTitleRaw = $name . ' — ' . SITE_NAME;
$pageDescription = $tagline !== '' ? $opExcerpt($tagline, 160) : 'Descoperă activitățile ' . $name . ' pe bilete.online.';
$canonicalUrl = SITE_URL . '/operator/' . $slug;
$ogImage = $heroImage ?: ($avatar ?: SITE_URL . '/assets/images/og-default.jpg');

$opClean = function (array $data) use (&$opClean): array {
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = $v = $opClean($v);
        }
        if ($v === null || $v === '' || $v === []) {
            unset($data[$k]);
        }
    }
    return $data;
};
$structuredData = [$opClean([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $name,
    'url' => $canonicalUrl,
    'logo' => $avatar,
    'image' => $heroImage,
    'description' => $pageDescription,
    'address' => $location !== 'România' ? ['@type' => 'PostalAddress', 'addressLocality' => $location, 'addressCountry' => 'RO'] : null,
    'sameAs' => array_column($socialLinks, 1),
]), [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $pos) => [
        '@type' => 'ListItem',
        'position' => $pos + 1,
        'name' => $bc['name'],
        'item' => SITE_URL . $bc['url'],
    ], $breadcrumbs, array_keys($breadcrumbs)),
]];

$v2Scripts = ['operator.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = ($heroImage ? '<link rel="preload" as="image" href="' . v2_e($heroImage) . '" fetchpriority="high">' : '')
    . '<script>window.BILETEONLINE = ' . json_encode([
        'siteName' => SITE_NAME,
        'siteUrl' => SITE_URL,
        'apiUrl' => '/api/proxy.php',
        'storageUrl' => STORAGE_URL,
        'env' => API_ENV,
        'locale' => SITE_LOCALE,
        'currency' => 'RON',
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';
$v2ClientData = ['slug' => $slug];

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="op-hero<?= $heroImage ? ' has-cover' : '' ?>" aria-labelledby="op-h">
    <?php if ($heroImage): ?><img class="op-cover" src="<?= v2_e($heroImage) ?>" alt="" fetchpriority="high" decoding="async"><?php endif; ?>
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="op-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="op-in">
      <nav class="crumbs" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $bi => $bc): ?>
          <?php if ($bi > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
          <?php if ($bi < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bc['url']) ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </nav>

      <div class="op-profile">
        <div class="op-avatar"<?= $avatar ? '' : ' aria-hidden="true"' ?>>
          <?php if ($avatar): ?><img src="<?= v2_e($avatar) ?>" alt="Logo <?= v2_e($name) ?>" decoding="async"><?php else: ?><?= v2_e($initial) ?><?php endif; ?>
        </div>

        <div class="op-id">
          <?php if ($verified || $isPro): ?>
          <ul class="op-badges">
            <?php if ($verified): ?><li><?= v2_ic('check-circle') ?>Verificat</li><?php endif; ?>
            <?php if ($isPro): ?><li class="is-pro">PRO</li><?php endif; ?>
          </ul>
          <?php endif; ?>
          <h1 class="op-h" id="op-h"><?= v2_e($name) ?></h1>
          <?php if ($tagline !== ''): ?><p class="op-tagline"><?= v2_e($tagline) ?></p><?php endif; ?>
          <ul class="op-meta">
            <li><?= v2_ic('map-pin') ?><?= v2_e($location) ?></li>
            <?php if ($followers !== '' && $followers !== '0'): ?><li><?= v2_ic('users-three') ?><b><?= v2_e($opDecimal($followers)) ?></b><span>urmăritori</span></li><?php endif; ?>
            <?php if ($rating !== '' && $rating !== '-'): ?><li><?= v2_ic('star') ?><b><?= v2_e($opDecimal($rating)) ?></b><span>rating</span></li><?php endif; ?>
          </ul>
        </div>

        <div class="op-actions">
          <a class="btn btn-light" href="#op-tabs"><?= $upcomingCount ? 'Vezi activitățile' : 'Vezi profilul' ?><?= v2_ic('arrow-right') ?></a>
          <button class="btn btn-outline-light" type="button" data-contact aria-haspopup="dialog" aria-controls="op-contact"><?= v2_ic('envelope-simple') ?>Trimite mesaj</button>
          <?php if ($socialLinks): ?>
          <div class="op-social">
            <?php foreach ($socialLinks as [$socialLabel, $socialUrl, $socialIcon]): ?>
            <a href="<?= v2_e($socialUrl) ?>" target="_blank" rel="noopener nofollow" aria-label="<?= v2_e($socialLabel . ' ' . $name) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $socialIcon ?></svg></a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== TABS + SIDEBAR ===================== -->
  <section class="sec op-main" aria-label="Activitățile și profilul operatorului">
    <div class="wrap op-layout">
      <div>
        <div class="op-tabs" id="op-tabs" role="tablist" data-tabs aria-label="Profilul operatorului">
          <button class="tab" id="op-tab-events" type="button" role="tab" aria-selected="true" aria-controls="op-panel-events"><?= v2_ic('calendar-blank') ?>Activități<span class="op-count"><?= $upcomingCount ?></span></button>
          <button class="tab" id="op-tab-past" type="button" role="tab" aria-selected="false" aria-controls="op-panel-past" tabindex="-1"><?= v2_ic('check-circle') ?>Trecut<span class="op-count"><?= $pastCount ?></span></button>
          <button class="tab" id="op-tab-about" type="button" role="tab" aria-selected="false" aria-controls="op-panel-about" tabindex="-1"><?= v2_ic('user-circle') ?>Despre</button>
        </div>

        <section class="op-panel" id="op-panel-events" role="tabpanel" aria-labelledby="op-tab-events">
          <h2>Activități viitoare</h2>
          <?php if ($upcomingCount): ?>
          <ul class="xp-grid op-cards">
            <?php foreach ($activities as $ai => $card): ?>
            <li class="xp">
              <a href="<?= v2_e($card['href']) ?>">
                <span class="xp-media"><?= $card['image'] ? v2_photo([$card['image'], 0, 0, '']) : v2_fallback($card['title'], $ai) ?></span>
                <span class="xp-body">
                  <span class="xp-cat"><?= v2_e($card['catName'] ?: 'Activitate') ?></span>
                  <span class="xp-title"><?= v2_e($card['title']) ?></span>
                  <span class="xp-meta"><?php if ($card['city'] !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($card['city']) ?></span><?php endif; ?><?php if ($card['dur'] !== ''): ?><span><?= v2_ic('clock') ?><?= v2_e($card['dur']) ?></span><?php endif; ?><?php if ($card['reviews'] > 0): ?><span class="xp-rating"><?= v2_ic('star') ?><?= v2_e(number_format($card['rating'], 1, ',', '')) ?> (<?= v2_thousands($card['reviews']) ?>)</span><?php endif; ?></span>
                  <span class="xp-foot"><span class="xp-go"><?= v2_ic('arrow-right') ?></span><?php if ($card['price'] > 0): ?><span class="xp-price">de la<b><?= v2_thousands($card['price']) ?> lei</b></span><?php endif; ?></span>
                </span>
              </a>
            </li>
            <?php endforeach; ?>
            <?php foreach ($events as $ei => $ev): ?>
            <li class="xp">
              <a href="<?= v2_e($ev['url']) ?>">
                <span class="xp-media">
                  <?= $ev['image'] ? v2_photo([$ev['image'], 0, 0, '']) : v2_fallback($ev['title'], $ei + count($activities)) ?>
                  <?php if ($ev['day'] !== ''): ?><span class="op-date"><b><?= v2_e($ev['day']) ?></b><small><?= v2_e($ev['month']) ?></small></span><?php endif; ?>
                  <?php if ($ev['soldOut']): ?><span class="op-flag">Sold out</span><?php endif; ?>
                </span>
                <span class="xp-body">
                  <span class="xp-cat"><?= v2_e($ev['category'] ?: 'Eveniment') ?></span>
                  <span class="xp-title"><?= v2_e($ev['title']) ?></span>
                  <span class="xp-meta"><?php if ($ev['when'] !== ''): ?><span><?= v2_ic('calendar-blank') ?><?= v2_e($ev['when']) ?></span><?php endif; ?><?php if ($ev['time'] !== ''): ?><span><?= v2_ic('clock') ?><?= v2_e($ev['time']) ?></span><?php endif; ?><?php if ($ev['venue'] !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($ev['venue']) ?></span><?php endif; ?></span>
                  <span class="xp-foot"><span class="xp-go"><?= v2_ic('arrow-right') ?></span><?php if ($ev['soldOut']): ?><span class="xp-soldout">Sold out</span><?php elseif ($ev['price'] !== ''): ?><span class="xp-price">de la<b><?= v2_e($ev['price']) ?></b></span><?php endif; ?></span>
                </span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <div class="op-empty">
            <span class="op-empty-ic"><?= v2_ic('calendar-blank') ?></span>
            <p>Nu sunt activități viitoare momentan.</p>
            <a class="btn btn-ghost" href="/operatori">Vezi alți operatori<?= v2_ic('arrow-right') ?></a>
          </div>
          <?php endif; ?>
        </section>

        <section class="op-panel" id="op-panel-past" role="tabpanel" aria-labelledby="op-tab-past" hidden>
          <h2>Activități trecute</h2>
          <?php if ($past): ?>
          <ul class="op-past">
            <?php foreach ($past as $pi => $item): ?>
            <li>
              <span class="op-past-media"><?= $item['image'] ? v2_photo([$item['image'], 0, 0, '']) : v2_fallback($item['title'], $pi) ?></span>
              <span class="op-past-text">
                <?php if ($item['date'] !== ''): ?><small><?= v2_e($item['date']) ?></small><?php endif; ?>
                <b><?= v2_e($item['title']) ?></b>
                <?php if ($item['venue'] !== ''): ?><span><?= v2_e($item['venue']) ?></span><?php endif; ?>
              </span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <div class="op-empty">
            <span class="op-empty-ic"><?= v2_ic('check-circle') ?></span>
            <p>Nu sunt activități trecute.</p>
          </div>
          <?php endif; ?>
        </section>

        <section class="op-panel" id="op-panel-about" role="tabpanel" aria-labelledby="op-tab-about" hidden>
          <h2>Despre organizator</h2>
          <p class="op-about"><?= v2_e($aboutText) ?></p>
          <?php if ($facts): ?>
          <h3>Informații despre organizator</h3>
          <ul class="op-facts-grid">
            <?php foreach ($facts as [$factIcon, $factLabel, $factValue]): ?>
            <li class="op-fact"><span class="op-fact-ic"><?= v2_ic($factIcon) ?></span><span><small><?= v2_e($factLabel) ?></small><b><?= v2_e($factValue) ?></b></span></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </section>
      </div>

      <aside class="op-side" aria-label="Despre operator">
        <div class="op-box">
          <h3>Despre organizator</h3>
          <p class="op-box-text"><?= v2_e($aboutText) ?></p>
          <?php if (mb_strlen($about) > 280): ?><button class="op-more" type="button" data-open-tab="op-tab-about">Citește tot</button><?php endif; ?>
        </div>

        <?php if ($facts): ?>
        <div class="op-box">
          <h3>Informații rapide</h3>
          <ul class="op-list">
            <?php foreach ($facts as [$factIcon, $factLabel, $factValue]): ?>
            <li><span class="op-list-ic"><?= v2_ic($factIcon) ?></span><span><small><?= v2_e($factLabel) ?></small><b><?= v2_e($factValue) ?></b></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <div class="op-box">
          <h3>De ce bilete.online</h3>
          <ul class="op-list op-trust">
            <li><span class="op-list-ic"><?= v2_ic('check') ?></span><span>Confirmare instant</span></li>
            <li><span class="op-list-ic"><?= v2_ic('qr-code') ?></span><span>Bilet digital cu cod QR</span></li>
            <li><span class="op-list-ic"><?= v2_ic('lock-simple') ?></span><span>Plată securizată</span></li>
            <li><span class="op-list-ic"><?= v2_ic('star') ?></span><span>Puncte bonus la fiecare comandă</span></li>
          </ul>
        </div>

        <div class="op-contact">
          <h3>Interesat de colaborare?</h3>
          <p>Contactează organizatorul pentru activități private sau corporate.</p>
          <button class="btn btn-light" type="button" data-contact aria-haspopup="dialog" aria-controls="op-contact"><?= v2_ic('envelope-simple') ?>Trimite mesaj</button>
          <?php if ($website !== ''): ?><a class="btn btn-outline-light" href="<?= v2_e($website) ?>" target="_blank" rel="noopener nofollow">Website<?= v2_ic('arrow-right') ?></a><?php endif; ?>
        </div>
      </aside>
    </div>
  </section>

  <!-- ===================== CONTACT DIALOG ===================== -->
  <div class="op-modal" id="op-contact" role="dialog" aria-modal="true" aria-labelledby="op-contact-h" aria-describedby="op-contact-to" hidden>
    <div class="op-modal-box">
      <div class="op-modal-top">
        <div>
          <h2 id="op-contact-h">Trimite mesaj</h2>
          <p id="op-contact-to">Către <?= v2_e($name) ?></p>
        </div>
        <button class="icon-btn" type="button" data-close><?= v2_ic('x') ?><span class="sr">Închide</span></button>
      </div>
      <form class="op-form" id="op-form">
        <div class="op-row">
          <div class="op-field"><label for="op-first">Prenume <abbr title="obligatoriu">*</abbr></label><input id="op-first" name="first_name" type="text" autocomplete="given-name" maxlength="100" required placeholder="Prenumele tău"></div>
          <div class="op-field"><label for="op-last">Nume <abbr title="obligatoriu">*</abbr></label><input id="op-last" name="last_name" type="text" autocomplete="family-name" maxlength="100" required placeholder="Numele tău"></div>
        </div>
        <div class="op-field"><label for="op-email">Email <abbr title="obligatoriu">*</abbr></label><input id="op-email" name="email" type="email" autocomplete="email" maxlength="255" required placeholder="email@exemplu.ro"></div>
        <div class="op-field"><label for="op-phone">Telefon</label><input id="op-phone" name="phone" type="tel" autocomplete="tel" maxlength="50" placeholder="07xx xxx xxx"></div>
        <div class="op-field"><label for="op-message">Mesaj <abbr title="obligatoriu">*</abbr></label><textarea id="op-message" name="message" rows="5" maxlength="5000" required placeholder="Scrie mesajul tău aici..."></textarea></div>
        <p class="form-msg" id="op-form-msg" role="status" aria-live="polite" hidden></p>
        <button class="btn btn-primary" id="op-submit" type="submit">Trimite mesajul</button>
      </form>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
