<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) { header('Location: /'); exit; }

$eventData = api_cached('wl_event_' . $slug, function () use ($slug) {
    return api_get('/marketplace-events/' . urlencode($slug));
}, 60);

$ev = $eventData['data']['event'] ?? null;
if (!$ev) { header('Location: /'); exit; }

$ticketTypes = $eventData['data']['ticket_types'] ?? $ev['ticket_types'] ?? [];
$artists = $eventData['data']['artists'] ?? [];
$venue = $eventData['data']['venue'] ?? [];
$commissionMode = $eventData['data']['commission_mode'] ?? 'included';
$commissionRate = (float) ($eventData['data']['commission_rate'] ?? 5);

$pageTitle = ($ev['name'] ?? 'Eveniment') . ' — ' . ORG_NAME;
$coverUrl = $ev['hero_image_url'] ?? $ev['cover_image_url'] ?? $ev['poster_url'] ?? '';
$description = $ev['description'] ?? '';
$ticketTerms = $ev['ticket_terms'] ?? '';
$venueName = $ev['venue_name'] ?? $venue['name'] ?? '';
$venueCity = $ev['venue_city'] ?? $venue['city'] ?? '';
$venueAddress = $venue['address'] ?? '';
$eventDate = !empty($ev['starts_at']) ? date('d.m.Y', strtotime($ev['starts_at'])) : '';
$eventTime = !empty($ev['starts_at']) ? date('H:i', strtotime($ev['starts_at'])) : '';
if ($eventTime === '00:00') $eventTime = '';
$doorsAt = !empty($ev['doors_open_at']) ? date('H:i', strtotime($ev['doors_open_at'])) : '';
$shareUrl = MARKETPLACE_URL . '/bilete/' . htmlspecialchars($slug);

require_once __DIR__ . '/includes/head.php';
?>

<script>
    window.__WL_EVENT__ = <?= json_encode([
        'event' => $ev,
        'ticket_types' => $ticketTypes,
        'commission_mode' => $commissionMode,
        'commission_rate' => $commissionRate,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<a href="<?= BASE_PATH ?>/" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);margin-bottom:16px;">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Toate evenimentele
</a>

<?php if ($coverUrl): ?>
<div style="border-radius:16px;overflow:hidden;aspect-ratio:21/9;margin-bottom:20px;">
    <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($ev['name'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
</div>
<?php endif; ?>

<div style="display:flex;gap:24px;flex-wrap:wrap;" class="wl-two-col">
    <!-- Left: event info -->
    <div style="flex:1;min-width:300px;">
        <h1 style="font-size:24px;font-weight:700;"><?= htmlspecialchars($ev['name'] ?? '') ?></h1>

        <div style="display:flex;flex-direction:column;gap:6px;margin-top:12px;">
            <?php if ($eventDate): ?>
            <div class="wl-card-meta" style="font-size:14px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?= $eventDate ?><?= $eventTime ? ' &middot; Ora ' . $eventTime : '' ?><?= $doorsAt ? ' (Uși: ' . $doorsAt . ')' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($venueName): ?>
            <div class="wl-card-meta" style="font-size:14px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?= htmlspecialchars($venueName) ?><?= $venueCity ? ', ' . htmlspecialchars($venueCity) : '' ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Share -->
        <div style="margin-top:14px;padding:8px 12px;background:var(--card);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:8px;">
            <svg width="14" height="14" style="color:var(--muted);flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            <input type="text" value="<?= htmlspecialchars($shareUrl) ?>" readonly onclick="this.select();navigator.clipboard?.writeText(this.value);" class="wl-input" style="border:none;padding:0;font-size:12px;cursor:pointer;">
        </div>

        <?php if ($description): ?>
        <div style="margin-top:24px;">
            <h2 class="wl-section-title">Despre eveniment</h2>
            <div style="font-size:14px;color:var(--muted);line-height:1.7;"><?= $description ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($artists)): ?>
        <div style="margin-top:28px;">
            <h2 class="wl-section-title">Artiști</h2>
            <?php foreach ($artists as $artist): ?>
            <div class="wl-section" style="display:flex;gap:14px;">
                <?php if ($artist['image_url'] ?? ''): ?>
                <img src="<?= htmlspecialchars($artist['image_url']) ?>" alt="" style="width:72px;height:72px;border-radius:12px;object-fit:cover;flex-shrink:0;">
                <?php endif; ?>
                <div>
                    <div style="font-weight:700;font-size:15px;"><?= htmlspecialchars($artist['name'] ?? '') ?></div>
                    <?php if ($artist['is_headliner'] ?? false): ?>
                    <span style="font-size:10px;font-weight:700;color:#f59e0b;background:#fef3c7;padding:2px 6px;border-radius:4px;">HEADLINER</span>
                    <?php endif; ?>
                    <?php if ($artist['bio'] ?? ''): ?>
                    <p style="font-size:13px;color:var(--muted);margin-top:4px;line-height:1.5;"><?= htmlspecialchars($artist['bio']) ?></p>
                    <?php endif; ?>
                    <?php $social = $artist['social_links'] ?? []; if (!empty($social)): ?>
                    <div style="display:flex;gap:10px;margin-top:6px;">
                        <?php foreach ($social as $platform => $url): if (!$url) continue; ?>
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="font-size:12px;color:var(--muted);"><?= ucfirst($platform) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($venueName || !empty($venue)): ?>
        <div style="margin-top:28px;">
            <h2 class="wl-section-title">Locație</h2>
            <div class="wl-section">
                <div style="font-weight:700;font-size:16px;"><?= htmlspecialchars($venueName) ?></div>
                <?php if ($venueAddress || $venueCity): ?>
                <div style="font-size:13px;color:var(--muted);margin-top:4px;"><?= htmlspecialchars($venueAddress) ?><?= $venueAddress && $venueCity ? ', ' : '' ?><?= htmlspecialchars($venueCity) ?></div>
                <?php endif; ?>
                <?php if (!empty($venue['description'])): ?>
                <p style="font-size:13px;color:var(--muted);margin-top:8px;line-height:1.5;"><?= $venue['description'] ?></p>
                <?php endif; ?>
                <?php if (!empty($venue['google_maps_url'])): ?>
                <a href="<?= htmlspecialchars($venue['google_maps_url']) ?>" target="_blank" class="wl-btn wl-btn-outline" style="margin-top:10px;padding:8px 14px;font-size:13px;">Deschide în Google Maps</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ticketTerms): ?>
        <div style="margin-top:20px;padding:16px;background:var(--bg);border-radius:12px;">
            <h3 style="font-size:14px;font-weight:600;margin-bottom:8px;">Termeni și condiții bilete</h3>
            <div style="font-size:13px;color:var(--muted);line-height:1.6;"><?= $ticketTerms ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: tickets (sticky) -->
    <div style="width:380px;flex-shrink:0;" class="wl-sidebar">
        <div style="position:sticky;top:70px;">
            <div class="wl-section" style="padding:0;overflow:hidden;">
                <div style="padding:14px 16px;background:var(--bg);border-bottom:1px solid var(--border);">
                    <h2 style="font-size:16px;font-weight:700;">Bilete</h2>
                </div>
                <div id="wl-ticket-types" style="padding:8px;">
                    <div class="wl-skeleton" style="height:56px;margin:8px;"></div>
                    <div class="wl-skeleton" style="height:56px;margin:8px;"></div>
                </div>
                <div id="wl-cart-summary" style="display:none;padding:14px 16px;border-top:1px solid var(--border);">
                    <div id="wl-cart-items" style="font-size:13px;color:var(--muted);"></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
                        <span style="font-weight:600;">Total</span>
                        <span id="wl-cart-total" style="font-size:18px;font-weight:700;color:var(--accent);">0 RON</span>
                    </div>
                    <button id="wl-add-btn" class="wl-btn" style="width:100%;margin-top:10px;" disabled>Adaugă în coș</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="<?= BASE_PATH ?>/assets/js/event.js"></script>
