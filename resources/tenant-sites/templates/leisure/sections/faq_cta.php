<?php
/** Întrebări frecvente (din panou) + bannerul final cu rezervare. */
require_once TEMPLATE_DIR . '/lib.php';
$GLOBALS['lvDrawer'] = true;
$faqs = array_values(array_filter((array) ($s['faqs'] ?? []), fn ($f) => trim((string) ($f['q'] ?? '')) !== ''));
$ctaImg = trim((string) ($s['image'] ?? '')) ?: lv_photo('photo-1511497584788-876760111969', 1800);
?>
    <section class="bg-cream py-20 sm:py-28 lg:py-36" x-data="{ open: null }">
        <div class="mx-auto max-w-[1260px] px-4 sm:px-6 lg:px-10">
            <?php if ($faqs): ?>
            <div class="grid gap-12 lg:grid-cols-[.72fr_1.28fr] lg:gap-20">
                <div data-reveal><?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></div><?php endif; ?><h2 class="mt-4 pb-1 font-display text-[clamp(42px,9vw,72px)] font-semibold leading-[.98] tracking-[-.04em] text-pine-950"><?= ts_md($s['title'] ?? '') ?></h2><?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="mt-6 text-base leading-7 text-pine-900/60"><?= ts_md($s['lead']) ?></p><?php endif; ?></div>
                <div data-reveal class="border-t border-pine-900/[.12]">
                    <?php foreach ($faqs as $i => $f): ?>
                    <div class="border-b border-pine-900/[.12]">
                        <button type="button" @click="open = open === <?= $i ?> ? null : <?= $i ?>" class="flex w-full items-center justify-between gap-5 py-5 text-left sm:py-7"><span class="font-display text-xl font-semibold text-pine-950 sm:text-2xl"><?= e($f['q']) ?></span><span class="grid h-9 w-9 flex-none place-items-center rounded-full border border-pine-900/[.12] transition" :class="open === <?= $i ?> ? 'rotate-45 bg-pine-950 text-acid' : ''"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span></button>
                        <div x-show="open === <?= $i ?>" x-collapse x-cloak><p class="max-w-2xl pb-6 text-sm leading-7 text-pine-900/[.62] sm:text-base"><?= e($f['a'] ?? '') ?></p></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (trim((string) ($s['cta_title'] ?? '')) !== ''): ?>
            <div data-reveal class="grain relative <?= $faqs ? 'mt-20 sm:mt-28' : '' ?> overflow-hidden rounded-[30px] bg-pine-950 text-white">
                <img src="<?= e($ctaImg) ?>" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40" loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-r from-pine-950 via-pine-950/80 to-pine-950/25"></div>
                <div class="relative z-10 max-w-3xl px-5 py-14 sm:px-10 sm:py-20 lg:px-16 lg:py-24">
                    <?php if (trim((string) ($s['cta_eyebrow'] ?? '')) !== ''): ?><div class="text-[10px] font-bold uppercase tracking-[.24em] text-acid"><?= e($s['cta_eyebrow']) ?></div><?php endif; ?>
                    <h2 class="mt-4 pb-1 font-display text-[clamp(42px,10vw,82px)] font-semibold leading-[.98] tracking-[-.045em]"><?= ts_md($s['cta_title']) ?></h2>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <?php if (trim((string) ($s['cta_button_label'] ?? '')) !== ''): ?><button type="button" @click="$dispatch('lv-book')" class="rounded-full bg-acid px-6 py-4 font-bold text-pine-950 transition hover:-translate-y-1"><?= e($s['cta_button_label']) ?></button><?php endif; ?>
                        <?php if (trim((string) ($s['cta_secondary_label'] ?? '')) !== ''): ?><a href="<?= e(ts_href($s['cta_secondary_url'] ?? '', '/planifica')) ?>" class="rounded-full border border-white/20 px-6 py-4 text-center font-semibold text-white transition hover:bg-white/10"><?= e($s['cta_secondary_label']) ?></a><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
