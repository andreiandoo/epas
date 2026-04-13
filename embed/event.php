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

// Fetch event data from marketplace-events endpoint (short cache)
$eventData = api_cached('embed_mkt_event_' . $eventSlug, function () use ($eventSlug) {
    return api_get('/marketplace-events/' . urlencode($eventSlug));
}, 60);

$ev = $eventData['data']['event'] ?? null;
// ticket_types can be at top level or inside event (depends on API endpoint)
$ticketTypes = $eventData['data']['ticket_types'] ?? $ev['ticket_types'] ?? [];

if (!$ev) {
    http_response_code(404);
    echo 'Event not found.';
    exit;
}

$pageTitle = ($ev['name'] ?? 'Eveniment') . ' — ' . $orgName;
$posterUrl = $ev['poster_url'] ?? $ev['image_url'] ?? $ev['image'] ?? '';
$coverUrl = $ev['hero_image_url'] ?? $ev['cover_image_url'] ?? $posterUrl;
$description = $ev['description'] ?? '';
$shortDescription = $ev['short_description'] ?? '';
$ticketTerms = $ev['ticket_terms'] ?? '';
$venueName = $ev['venue_name'] ?? '';
$venueCity = isset($ev['venue_city']) ? $ev['venue_city'] : '';
$eventDate = !empty($ev['starts_at']) ? date('d.m.Y', strtotime($ev['starts_at'])) : '';
$eventTime = !empty($ev['starts_at']) ? date('H:i', strtotime($ev['starts_at'])) : '';
if ($eventTime === '00:00') $eventTime = '';
$doorsAt = !empty($ev['doors_open_at']) ? date('H:i', strtotime($ev['doors_open_at'])) : '';
$baseUrl = '/embed/' . htmlspecialchars($organizerSlug);

require_once __DIR__ . '/includes/embed-head.php';
?>

<!-- Inject event data for JS -->
<script>
    window.__EMBED_EVENT__ = <?= json_encode([
        'event' => $ev,
        'ticket_types' => $ticketTypes,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<!-- Back link -->
<a href="<?= $baseUrl ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:<?= $mutedColor ?>;margin-bottom:16px;text-decoration:none;">
    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    Toate evenimentele
</a>

<div style="display:flex;flex-direction:column;gap:0;" id="event-container">

    <!-- Cover image -->
    <?php if ($coverUrl): ?>
    <div style="position:relative;border-radius:16px;overflow:hidden;aspect-ratio:21/9;margin-bottom:20px;">
        <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($ev['name'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
    </div>
    <?php endif; ?>

    <!-- Two-column layout: Info left, Tickets right -->
    <div style="display:flex;gap:24px;flex-wrap:wrap;">

        <!-- Left column: event info -->
        <div style="flex:1;min-width:300px;">
            <h1 style="margin:0;font-size:24px;font-weight:700;color:<?= $textColor ?>;"><?= htmlspecialchars($ev['name'] ?? '') ?></h1>

            <!-- Date, time, venue -->
            <div style="display:flex;flex-direction:column;gap:6px;margin-top:12px;">
                <?php if ($eventDate): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:14px;color:<?= $mutedColor ?>;">
                    <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= $eventDate ?><?= $eventTime ? ' &middot; Ora ' . $eventTime : '' ?><?= $doorsAt ? ' (Deschidere uși: ' . $doorsAt . ')' : '' ?>
                </div>
                <?php endif; ?>
                <?php if ($venueName): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:14px;color:<?= $mutedColor ?>;">
                    <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <?= htmlspecialchars($venueName) ?><?= $venueCity ? ', ' . htmlspecialchars($venueCity) : '' ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if ($description): ?>
            <div style="margin-top:24px;">
                <h2 style="margin:0 0 10px;font-size:18px;font-weight:600;color:<?= $textColor ?>;">Despre eveniment</h2>
                <div style="font-size:14px;color:<?= $mutedColor ?>;line-height:1.7;">
                    <?= $description ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ticket terms -->
            <?php if ($ticketTerms): ?>
            <div style="margin-top:20px;padding:16px;background:<?= $isDark ? '#1e293b' : '#f1f5f9' ?>;border-radius:12px;">
                <h3 style="margin:0 0 8px;font-size:14px;font-weight:600;color:<?= $textColor ?>;">Termeni și condiții bilete</h3>
                <div style="font-size:13px;color:<?= $mutedColor ?>;line-height:1.6;">
                    <?= $ticketTerms ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right column: ticket selector (sticky) -->
        <div style="width:380px;flex-shrink:0;" id="embed-tickets-sidebar">
            <div style="position:sticky;top:70px;">
                <div style="background:<?= $cardBg ?>;border:1px solid <?= $borderColor ?>;border-radius:14px;overflow:hidden;">
                    <div style="padding:14px 16px;background:<?= $isDark ? '#334155' : '#f8fafc' ?>;border-bottom:1px solid <?= $borderColor ?>;">
                        <h2 style="margin:0;font-size:16px;font-weight:700;color:<?= $textColor ?>;">Bilete</h2>
                    </div>
                    <div id="embed-ticket-types" style="padding:4px 0;">
                        <!-- Rendered by JS -->
                        <div style="padding:16px;">
                            <div class="skeleton" style="height:56px;border-radius:10px;margin-bottom:8px;"></div>
                            <div class="skeleton" style="height:56px;border-radius:10px;"></div>
                        </div>
                    </div>

                    <!-- Cart summary -->
                    <div id="embed-cart-summary" style="display:none;padding:14px 16px;border-top:1px solid <?= $borderColor ?>;">
                        <div id="embed-cart-items" style="font-size:13px;color:<?= $mutedColor ?>;"></div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid <?= $borderColor ?>;">
                            <span style="font-weight:600;color:<?= $textColor ?>;">Total</span>
                            <span id="embed-cart-total" style="font-size:18px;font-weight:700;color:<?= htmlspecialchars($accentColor) ?>;">0 RON</span>
                        </div>
                        <button id="embed-add-to-cart-btn" class="embed-btn" style="width:100%;margin-top:10px;" disabled>
                            Adaugă în coș
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Responsive: on mobile, tickets sidebar goes full width -->
<style>
    @media (max-width: 767px) {
        #embed-tickets-sidebar { width: 100% !important; }
    }
</style>

<script src="<?= SITE_URL ?>/embed/assets/js/embed-event.js"></script>

<?php require_once __DIR__ . '/includes/embed-footer.php'; ?>
