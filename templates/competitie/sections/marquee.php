<?php
$words = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['words'] ?? '')))));
if (!$words) {
    return;
}
?>
<div class="marquee" aria-hidden="true">
    <div class="marquee__track">
        <?php for ($i = 0; $i < 2; $i++): foreach ($words as $w): ?>
            <span><?= e($w) ?></span>
        <?php endforeach; endfor; ?>
    </div>
</div>
