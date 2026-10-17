<?php
/** Întrebări frecvente (din panou), ca <details> accesibile. */
$faqs = array_values(array_filter((array) ($s['faqs'] ?? []), fn ($f) => trim((string) ($f['q'] ?? '')) !== ''));
if (!$faqs) {
    return;
}
?>
<section class="gx-section" id="intrebari">
    <div class="gx-wrap gx-faq">
        <div data-reveal>
            <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="gx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h2 class="gx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
        </div>
        <div class="gx-faq__list">
            <?php foreach ($faqs as $i => $f): ?>
            <details class="gx-faq__item" <?= $i === 0 ? 'open' : '' ?>>
                <summary><?= e($f['q']) ?><i aria-hidden="true">+</i></summary>
                <p><?= nl2br(e($f['a'] ?? '')) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
