<?php
/** Informații practice: carduri cu pictogramă (transport, camping, plăți, acces …). */
$items = array_values(array_filter((array) ($s['items'] ?? []), fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
if (!$items) {
    return;
}
$icons = [
    'bus' => '<path d="M5 16V6a3 3 0 013-3h8a3 3 0 013 3v10M5 16h14M5 16v3m14-3v3M5 10h14"/><circle cx="8.5" cy="13" r=".8"/><circle cx="15.5" cy="13" r=".8"/>',
    'tent' => '<path d="M3 20L12 4l9 16H3zM12 4v16M9 20l3-6 3 6"/>',
    'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h3"/>',
    'access' => '<circle cx="12" cy="4.5" r="1.5"/><path d="M8 8h8M12 8v6l-3 6M12 14l3 6"/>',
    'age' => '<circle cx="12" cy="12" r="9"/><path d="M9 9h1v6M14 9a1.5 1.5 0 110 3 1.5 1.5 0 110 3"/>',
    'rules' => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
    'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
];
?>
<section class="fx-section fx-section--alt" id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'info') ?>">
    <div class="fx-wrap">
        <div class="fx-head2" data-reveal>
            <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h2 class="fx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
        </div>
        <div class="fx-info" data-stagger>
            <?php foreach ($items as $it): ?>
            <article class="fx-info__card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$it['icon'] ?? 'info'] ?? $icons['info'] ?></svg>
                <h3><?= e($it['title']) ?></h3>
                <p><?= nl2br(e($it['text'] ?? '')) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
