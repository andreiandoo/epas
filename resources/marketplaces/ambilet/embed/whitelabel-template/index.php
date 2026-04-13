<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$pageTitle = ORG_NAME . ' — Evenimente';

// Fetch organizer events
$orgData = api_cached('wl_org_' . ORG_SLUG, function () {
    return api_get('/marketplace-events/organizers/' . urlencode(ORG_SLUG));
}, 300);

$events = $orgData['data']['upcomingEvents'] ?? [];
$orgAvatar = $orgData['data']['avatar'] ?? '';

require_once __DIR__ . '/includes/head.php';
?>

<!-- Events grid -->
<div class="wl-events-grid">
    <?php if (empty($events)): ?>
    <p style="color:var(--muted);grid-column:1/-1;text-align:center;padding:40px 0;">Nu sunt evenimente disponibile momentan.</p>
    <?php else: ?>
    <?php foreach ($events as $event):
        $imgUrl = $event['poster_url'] ?? $event['image'] ?? '';
        $title = $event['title'] ?? '';
        $date = !empty($event['event_date']) ? date('d.m.Y', strtotime($event['event_date'])) : '';
        $time = $event['start_time'] ?? '';
        $venue = $event['venue_name'] ?? '';
        $city = $event['venue_city'] ?? '';
        $price = $event['price'] ?? null;
        $slug = $event['slug'] ?? '';
        $isSoldOut = $event['is_sold_out'] ?? false;
    ?>
    <a href="<?= BASE_PATH ?>/<?= htmlspecialchars($slug) ?>" class="wl-card">
        <?php if ($imgUrl): ?>
        <div style="position:relative;">
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($title) ?>" class="wl-card-img" loading="lazy">
            <?php if ($isSoldOut): ?>
            <div class="wl-card-badge">SOLD OUT</div>
            <?php elseif ($price !== null): ?>
            <div class="wl-card-price">de la <?= number_format($price, 0) ?> RON</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="wl-card-body">
            <h3 class="wl-card-title"><?= htmlspecialchars($title) ?></h3>
            <?php if ($date): ?>
            <p class="wl-card-meta">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?= $date ?><?= $time ? ' &middot; ' . $time : '' ?>
            </p>
            <?php endif; ?>
            <?php if ($venue): ?>
            <p class="wl-card-meta">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?= htmlspecialchars($venue) ?><?= $city ? ', ' . htmlspecialchars($city) : '' ?>
            </p>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
