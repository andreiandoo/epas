<?php
/** Povestea locului: text, două cifre, link și colajul cu două imagini + nota de teren. */
require_once TEMPLATE_DIR . '/lib.php';
$imgA = trim((string) ($s['image'] ?? '')) ?: lv_photo('photo-1441974231531-c6227db76b6e', 1400);
$imgB = trim((string) ($s['image_2'] ?? '')) ?: lv_photo('photo-1473448912268-2022ce9509d8', 1000);
$stats = [];
foreach ([1, 2] as $n) {
    if (trim((string) ($s['stat' . $n . '_value'] ?? '')) !== '') {
        $stats[] = [$s['stat' . $n . '_value'], (string) ($s['stat' . $n . '_label'] ?? '')];
    }
}
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'despre-parc') ?>" class="relative overflow-hidden bg-cream py-20 sm:py-28 lg:py-40">
        <div class="absolute inset-0 paper-grid opacity-70"></div>
        <div class="relative z-10 mx-auto grid max-w-[1460px] gap-14 px-4 sm:px-6 lg:grid-cols-12 lg:items-center lg:px-10">
            <div data-reveal class="lg:col-span-5 lg:pr-10">
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></div><?php endif; ?>
                <h2 class="mt-4 pb-1 font-display text-[clamp(44px,10vw,84px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950"><?= ts_md($s['title'] ?? '') ?></h2>
                <?= ts_paragraphs($s['text'] ?? '', 'mt-6 text-base leading-8 text-pine-900/[.64] sm:text-lg') ?>
                <?php if ($stats): ?>
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4">
                    <?php foreach ($stats as $i => [$v, $l]): ?>
                    <div class="rounded-[20px] p-4 sm:p-5 <?= $i === 1 ? 'bg-pine-950 text-white' : 'bg-oat' ?>"><div class="font-display text-3xl font-semibold sm:text-4xl <?= $i === 1 ? 'text-acid' : 'text-pine-950' ?>"><?= e($v) ?></div><div class="mt-2 text-xs <?= $i === 1 ? 'text-white/[.52]' : 'text-pine-900/[.52]' ?>"><?= e($l) ?></div></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (trim((string) ($s['link_label'] ?? '')) !== ''): ?>
                <a href="<?= e(ts_href($s['link_url'] ?? '', '/despre')) ?>" class="mt-8 inline-flex items-center gap-2 border-b border-pine-950 pb-1 text-sm font-bold text-pine-950"><?= e($s['link_label']) ?> <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg></a>
                <?php endif; ?>
            </div>

            <div class="relative min-h-[520px] sm:min-h-[680px] lg:col-span-7 lg:min-h-[760px]">
                <div data-reveal class="image-mask-a absolute left-0 top-0 h-[72%] w-[78%] overflow-hidden shadow-lift">
                    <img id="reserve-image-a" src="<?= e($imgA) ?>" alt="" class="h-full w-full object-cover" loading="lazy">
                </div>
                <div data-reveal class="image-mask-b absolute bottom-0 right-0 h-[55%] w-[58%] overflow-hidden border-[8px] border-cream shadow-lift sm:border-[12px]">
                    <img id="reserve-image-b" src="<?= e($imgB) ?>" alt="" class="h-full w-full object-cover" loading="lazy">
                </div>
                <?php if (trim((string) ($s['note_text'] ?? '')) !== ''): ?>
                <div data-reveal class="absolute bottom-[14%] left-[4%] z-20 max-w-[230px] -rotate-3 bg-acid p-4 shadow-card sm:max-w-[280px] sm:p-6">
                    <?php if (trim((string) ($s['note_label'] ?? '')) !== ''): ?><div class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-700"><?= e($s['note_label']) ?></div><?php endif; ?>
                    <p class="mt-3 font-display text-xl font-semibold leading-snug text-pine-950 sm:text-2xl"><?= e($s['note_text']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
