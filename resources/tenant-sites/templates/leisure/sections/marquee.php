<?php
/** Banda cu cuvinte care curge (un cuvânt pe rând în panou). */
$words = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['words'] ?? '')))));
if (!$words) { return; }
?>
    <section class="marquee border-y border-pine-900/10 bg-acid py-3 text-pine-950" aria-hidden="true">
        <div class="marquee-track items-center text-[10px] font-bold uppercase tracking-[.22em] sm:text-xs">
            <?php for ($r = 0; $r < 2; $r++): ?>
            <div class="flex items-center">
                <?php foreach ($words as $w): ?>
                <div class="flex items-center gap-5 px-5 sm:gap-8 sm:px-8"><span><?= e($w) ?></span><span class="h-1.5 w-1.5 rounded-full bg-ember"></span></div>
                <?php endforeach; ?>
            </div>
            <?php endfor; ?>
        </div>
    </section>
