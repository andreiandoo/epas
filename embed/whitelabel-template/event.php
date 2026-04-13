<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) { header('Location: ' . BASE_PATH . '/'); exit; }

$eventData = api_cached('wl_evt_' . $slug, function () use ($slug) {
    return api_get('/marketplace-events/' . urlencode($slug));
}, 60);

$ev = $eventData['data']['event'] ?? null;
if (!$ev) { header('Location: ' . BASE_PATH . '/'); exit; }

$ticketTypes = $eventData['data']['ticket_types'] ?? [];
$artists = $eventData['data']['artists'] ?? [];
$venue = $eventData['data']['venue'] ?? [];
$commMode = $eventData['data']['commission_mode'] ?? 'included';
$commRate = (float) ($eventData['data']['commission_rate'] ?? 5);

$pageTitle = ($ev['name'] ?? 'Eveniment') . ' — ' . ORG_NAME;
$coverUrl = $ev['hero_image_url'] ?? $ev['cover_image_url'] ?? $ev['poster_url'] ?? '';
$description = $ev['description'] ?? '';
$ticketTerms = $ev['ticket_terms'] ?? '';
$venueName = $ev['venue_name'] ?? $venue['name'] ?? '';
$venueCity = $ev['venue_city'] ?? $venue['city'] ?? '';
$venueAddr = $venue['address'] ?? '';
$category = $ev['category'] ?? '';
$mapsUrl = $venue['google_maps_url'] ?? '';
$bp = BASE_PATH;
$shareUrl = MARKETPLACE_URL . '/bilete/' . htmlspecialchars($slug);

// Date formatting
$rawDate = $ev['starts_at'] ?? '';
$eventDate = $rawDate ? date('d F Y', strtotime($rawDate)) : '';
$eventTime = $rawDate ? date('H:i', strtotime($rawDate)) : '';
if ($eventTime === '00:00') $eventTime = '';
$doorsAt = !empty($ev['doors_open_at']) ? date('H:i', strtotime($ev['doors_open_at'])) : '';
$dayName = $rawDate ? ['Duminică','Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă'][(int)date('w', strtotime($rawDate))] : '';

$showBackLink = true;
$backUrl = $bp . '/';
$backLabel = 'Toate evenimentele';

require_once __DIR__ . '/includes/head.php';
?>

<script>
    window.__WL_EVENT__ = <?= json_encode([
        'event' => $ev,
        'ticket_types' => $ticketTypes,
        'commission_mode' => $commMode,
        'commission_rate' => $commRate,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<!-- EVENT HERO -->
<section class="event-hero">
  <?php if ($coverUrl): ?>
  <div class="event-hero-img" style="background-image:url('<?= htmlspecialchars($coverUrl) ?>');"></div>
  <?php else: ?>
  <div class="event-hero-img"></div>
  <?php endif; ?>
  <div class="event-hero-bg"></div>
  <div class="event-hero-spotlight"></div>
  <div class="event-hero-content">
    <div class="breadcrumb">
      <a href="<?= $bp ?>/"><?= htmlspecialchars(ORG_NAME) ?></a>
      <?php if ($category): ?>
      <span class="breadcrumb-sep">›</span>
      <span><?= htmlspecialchars($category) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($category): ?>
    <div class="event-category"><?= htmlspecialchars($category) ?></div>
    <?php endif; ?>
    <h1><strong><?= htmlspecialchars($ev['name'] ?? '') ?></strong></h1>
    <?php if (!empty($artists)): ?>
    <div class="event-subtitle">cu <?= htmlspecialchars(implode(', ', array_map(fn($a) => $a['name'] ?? '', $artists))) ?></div>
    <?php endif; ?>
    <div class="event-chips">
      <?php if ($eventDate): ?>
      <div class="chip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <strong><?= $dayName ?>, <?= $eventDate ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($eventTime): ?>
      <div class="chip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <strong><?= $eventTime ?></strong><?= $doorsAt ? ' · acces de la ' . $doorsAt : '' ?>
      </div>
      <?php endif; ?>
      <?php if ($venueName): ?>
      <div class="chip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <strong><?= htmlspecialchars($venueName) ?></strong><?= $venueCity ? ' · ' . htmlspecialchars($venueCity) : '' ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- TWO-COLUMN LAYOUT -->
<div class="event-layout">

  <!-- LEFT: DETAILS -->
  <div class="event-left">

    <?php if ($description): ?>
    <div class="section-label">Despre eveniment</div>
    <div class="event-desc"><?= $description ?></div>
    <?php endif; ?>

    <?php if (!empty($artists)): ?>
    <div class="section-label">Artiști</div>
    <div class="artists">
      <?php foreach ($artists as $artist): ?>
      <div class="artist-chip">
        <?php if (!empty($artist['image_url'])): ?>
        <div class="artist-avatar"><img src="<?= htmlspecialchars($artist['image_url']) ?>" alt=""></div>
        <?php else: ?>
        <div class="artist-avatar-placeholder"><?= mb_substr($artist['name'] ?? '?', 0, 1) ?></div>
        <?php endif; ?>
        <div>
          <div class="artist-name"><?= htmlspecialchars($artist['name'] ?? '') ?></div>
          <?php if ($artist['is_headliner'] ?? false): ?>
          <div class="artist-role" style="color:var(--accent);">Headliner</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="section-label">Detalii eveniment</div>
    <div class="info-grid">
      <?php if ($eventDate): ?>
      <div class="info-card">
        <div class="info-card-label">Data</div>
        <div class="info-card-value"><?= date('d F', strtotime($rawDate)) ?></div>
        <div class="info-card-sub"><?= $dayName ?> <?= date('Y', strtotime($rawDate)) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($eventTime): ?>
      <div class="info-card">
        <div class="info-card-label">Ora</div>
        <div class="info-card-value"><?= $eventTime ?></div>
        <div class="info-card-sub"><?= $doorsAt ? 'Acces de la ' . $doorsAt : '' ?></div>
      </div>
      <?php endif; ?>
      <?php if ($venueName): ?>
      <div class="info-card" style="grid-column:1/-1;">
        <div class="info-card-label">Locație</div>
        <div class="info-card-value"><?= htmlspecialchars($venueName) ?></div>
        <div class="info-card-sub"><?= htmlspecialchars($venueAddr) ?><?= $venueAddr && $venueCity ? ', ' : '' ?><?= htmlspecialchars($venueCity) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($mapsUrl || $venueName): ?>
    <a href="<?= $mapsUrl ? htmlspecialchars($mapsUrl) : '#' ?>" target="_blank" class="map-placeholder">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      <span><?= htmlspecialchars($venueName) ?><?= $venueAddr ? ' · ' . htmlspecialchars($venueAddr) : '' ?></span>
      <?php if ($mapsUrl): ?>
      <span style="font-size:12px;color:var(--text-dim);">Deschide în Maps →</span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if ($ticketTerms): ?>
    <div class="notice" style="margin-top:32px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div style="font-size:12px;"><?= $ticketTerms ?></div>
    </div>
    <?php endif; ?>

  </div>

  <!-- RIGHT: TICKET SELECTOR (rendered by JS) -->
  <div class="event-right">
    <div>
      <div class="ticket-panel-title">Alege biletele</div>
      <?php if ($eventDate): ?>
      <div class="ticket-panel-date">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= $dayName ?>, <?= $eventDate ?><?= $eventTime ? ' · ' . $eventTime : '' ?>
      </div>
      <?php endif; ?>
    </div>

    <div id="wl-ticket-types">
      <!-- Rendered by event.js -->
    </div>

    <!-- Voucher -->
    <div>
      <div class="section-label" style="font-size:10px;margin-bottom:10px;">Cod voucher</div>
      <div class="voucher-row">
        <input class="voucher-input" type="text" id="wl-voucher" placeholder="Introdu codul...">
        <button class="voucher-btn" id="wl-voucher-btn">Aplică</button>
      </div>
      <div id="wl-voucher-msg" style="display:none;margin-top:8px;font-size:12px;"></div>
    </div>

    <!-- Order summary -->
    <div class="order-summary" id="wl-order-summary" style="display:none;"></div>

    <!-- CTA -->
    <button class="btn-primary" id="wl-add-btn" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      Continuă spre finalizare
    </button>
    <div class="btn-note">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Plată securizată · Bilet trimis instant pe email
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="<?= $bp ?>/assets/js/event.js"></script>
