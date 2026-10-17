<?php
/** Întrebări frecvente (din panou), ca <details> accesibile. */
$faqs = array_values(array_filter((array) ($s['faqs'] ?? []), fn ($f) => trim((string) ($f['q'] ?? '')) !== ''));
if (!$faqs) {
    return;
}
?>
<section class="fx-section" id="intrebari">
    <div class="fx-wrap fx-faq">
        <div data-reveal>
            <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h2 class="fx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
        </div>
        <div class="fx-faq__list">
            <?php foreach ($faqs as $i => $f): ?>
            <details class="fx-faq__item" <?= $i === 0 ? 'open' : '' ?>>
                <summary><?= e($f['q']) ?><i aria-hidden="true">+</i></summary>
                <p><?= nl2br(e($f['a'] ?? '')) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
