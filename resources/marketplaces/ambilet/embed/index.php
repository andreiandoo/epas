<?php
/**
 * Embed: Event list for an organizer.
 * URL: /embed/{organizer-slug}
 */
require_once __DIR__ . '/includes/embed-init.php';

$pageTitle = $orgName . ' — Evenimente';

// Preload upcoming events from organizer data
$events = $orgData['data']['upcomingEvents'] ?? [];

require_once __DIR__ . '/includes/embed-head.php';
?>

<!-- Organizer header -->
<div style="display:flex; align-items:center; gap:12px; margin-bottom:24px;">
    <?php $avatar = $orgData['data']['avatar'] ?? ''; if ($avatar): ?>
    <img src="<?= htmlspecialchars($avatar) ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid <?= $borderColor ?>;">
    <?php endif; ?>
    <div>
        <h1 style="margin:0;font-size:20px;font-weight:700;color:<?= $textColor ?>;"><?= htmlspecialchars($orgName) ?></h1>
        <p style="margin:2px 0 0;font-size:13px;color:<?= $mutedColor ?>;"><?= count($events) ?> evenimente disponibile</p>
    </div>
</div>

<!-- Events grid -->
<div id="events-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px;">
    <?php if (empty($events)): ?>
    <p style="color:<?= $mutedColor ?>;grid-column:1/-1;text-align:center;padding:40px 0;">Nu sunt evenimente disponibile momentan.</p>
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
        $link = '/embed/' . htmlspecialchars($organizerSlug) . '/' . htmlspecialchars($slug);
    ?>
    <a href="<?= $link ?>" class="embed-card" style="display:block;text-decoration:none;color:inherit;">
        <?php if ($imgUrl): ?>
        <div style="position:relative;aspect-ratio:16/10;overflow:hidden;">
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($title) ?>" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
            <?php if ($isSoldOut): ?>
            <div style="position:absolute;top:8px;right:8px;background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">SOLD OUT</div>
            <?php elseif ($price !== null): ?>
            <div style="position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,0.7);color:#fff;font-size:13px;font-weight:600;padding:4px 10px;border-radius:8px;">de la <?= number_format($price, 0) ?> RON</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div style="padding:12px 14px;">
            <h3 style="margin:0;font-size:15px;font-weight:600;color:<?= $textColor ?>;line-height:1.3;"><?= htmlspecialchars($title) ?></h3>
            <?php if ($date): ?>
            <p style="margin:6px 0 0;font-size:12px;color:<?= $mutedColor ?>;display:flex;align-items:center;gap:4px;">
                <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?= $date ?><?= $time ? ' &middot; ' . $time : '' ?>
            </p>
            <?php endif; ?>
            <?php if ($venue): ?>
            <p style="margin:4px 0 0;font-size:12px;color:<?= $mutedColor ?>;display:flex;align-items:center;gap:4px;">
                <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?= htmlspecialchars($venue) ?><?= $city ? ', ' . htmlspecialchars($city) : '' ?>
            </p>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/embed-footer.php'; ?>
