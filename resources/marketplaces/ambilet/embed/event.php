<?php
/**
 * Embed: Event detail + ticket purchase.
 * URL: /embed/{organizer-slug}/{event-slug}
 */
require_once __DIR__ . '/includes/embed-init.php';

$eventSlug = $_GET['slug'] ?? '';
if (!$eventSlug) {
    http_response_code(400);
    echo 'Missing event slug.';
    exit;
}

// Fetch event data
$eventData = api_cached('embed_event_' . $eventSlug, function () use ($eventSlug) {
    return api_get('/events/' . urlencode($eventSlug));
}, 60);

$ev = $eventData['data']['event'] ?? null;
if (!$ev) {
    http_response_code(404);
    echo 'Event not found.';
    exit;
}

$pageTitle = ($ev['name'] ?? 'Eveniment') . ' — ' . $orgName;

require_once __DIR__ . '/includes/embed-head.php';
?>

<!-- Inject event data for JS -->
<script>
    window.__EMBED_EVENT__ = <?= json_encode($eventData['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<!-- Back link -->
<a href="/embed/<?= htmlspecialchars($organizerSlug) ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:<?= $mutedColor ?>;margin-bottom:16px;">
    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Toate evenimentele
</a>

<div style="display:flex;flex-direction:column;gap:24px;" id="event-container">
    <!-- Event header -->
    <?php
    $posterUrl = $ev['poster_url'] ?? $ev['image_url'] ?? $ev['image'] ?? '';
    $coverUrl = $ev['hero_image_url'] ?? $ev['cover_image_url'] ?? $posterUrl;
    ?>
    <?php if ($coverUrl): ?>
    <div style="position:relative;border-radius:16px;overflow:hidden;aspect-ratio:21/9;">
        <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($ev['name'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
    </div>
    <?php endif; ?>

    <div style="display:flex;flex-direction:column;gap:8px;">
        <h1 style="margin:0;font-size:24px;font-weight:700;color:<?= $textColor ?>;"><?= htmlspecialchars($ev['name'] ?? '') ?></h1>

        <?php
        $dateStr = '';
        if (!empty($ev['starts_at'])) {
            $dateStr = date('d.m.Y', strtotime($ev['starts_at']));
            $timeStr = date('H:i', strtotime($ev['starts_at']));
            if ($timeStr !== '00:00') $dateStr .= ' &middot; ' . $timeStr;
        }
        $venueName = $ev['venue_name'] ?? '';
        ?>
        <?php if ($dateStr): ?>
        <p style="margin:0;font-size:14px;color:<?= $mutedColor ?>;display:flex;align-items:center;gap:6px;">
            <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <?= $dateStr ?>
        </p>
        <?php endif; ?>
        <?php if ($venueName): ?>
        <p style="margin:0;font-size:14px;color:<?= $mutedColor ?>;display:flex;align-items:center;gap:6px;">
            <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?= htmlspecialchars($venueName) ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Ticket types (rendered by JS) -->
    <div id="embed-tickets-section">
        <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:<?= $textColor ?>;">Bilete</h2>
        <div id="embed-ticket-types" style="display:flex;flex-direction:column;gap:8px;">
            <div class="skeleton" style="height:64px;border-radius:12px;"></div>
            <div class="skeleton" style="height:64px;border-radius:12px;"></div>
        </div>
    </div>

    <!-- Cart summary + Add to cart -->
    <div id="embed-cart-summary" style="display:none;padding:16px;background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:12px;">
        <div id="embed-cart-items" style="font-size:14px;"></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid <?= $borderColor ?>;">
            <span style="font-weight:600;color:<?= $textColor ?>;">Total</span>
            <span id="embed-cart-total" style="font-size:18px;font-weight:700;color:<?= htmlspecialchars($accentColor) ?>;">0 RON</span>
        </div>
        <button id="embed-add-to-cart-btn" class="embed-btn" style="width:100%;margin-top:12px;" disabled>
            Adaugă în coș
        </button>
    </div>

    <!-- Description -->
    <?php if (!empty($ev['description'])): ?>
    <div>
        <h2 style="margin:0 0 8px;font-size:18px;font-weight:600;color:<?= $textColor ?>;">Descriere</h2>
        <div style="font-size:14px;color:<?= $mutedColor ?>;line-height:1.6;">
            <?= $ev['description'] ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="<?= SITE_URL ?>/embed/assets/js/embed-event.js"></script>

<?php require_once __DIR__ . '/includes/embed-footer.php'; ?>
