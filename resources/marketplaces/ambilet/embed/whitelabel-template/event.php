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
// Share URL = current organizer site, not marketplace
$shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_PATH . '/' . $slug;

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
<section class="event-hero" style="max-width:1200px;margin:0 auto;">
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
<div class="event-layout" style="max-width:1200px;margin:0 auto;">

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

<!-- Share buttons -->
<div style="max-width:1200px;margin:0 auto;padding:32px 40px;display:flex;align-items:center;gap:16px;">
  <span style="font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">Distribuie</span>
  <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;border:1px solid var(--border);color:var(--text-muted);transition:border-color .2s,color .2s;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='';this.style.color=''">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
  </a>
  <a href="https://wa.me/?text=<?= urlencode($ev['name'] . ' — ' . $shareUrl) ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;border:1px solid var(--border);color:var(--text-muted);transition:border-color .2s,color .2s;" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='';this.style.color=''">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.625-1.467A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818c-2.168 0-4.18-.588-5.916-1.613l-.424-.252-2.742.87.863-2.681-.276-.44A9.793 9.793 0 012.182 12c0-5.423 4.395-9.818 9.818-9.818 5.423 0 9.818 4.395 9.818 9.818 0 5.423-4.395 9.818-9.818 9.818z"/></svg>
  </a>
  <button onclick="navigator.clipboard?.writeText('<?= htmlspecialchars($shareUrl) ?>');this.textContent='Copiat!';setTimeout(()=>this.textContent='Copiază link',1500)" style="display:flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid var(--border);border-radius:100px;background:none;color:var(--text-muted);font-size:12px;font-weight:500;cursor:pointer;transition:border-color .2s,color .2s;font-family:var(--font-body);" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='';this.style.color=''">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
    Copiază link
  </button>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="<?= $bp ?>/assets/js/event.js"></script>
