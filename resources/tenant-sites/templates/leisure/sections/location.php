<?php
/** Cum ajungi: adresa (din secțiune sau din Setări template), hartă încorporată și indicații. */
$address = trim((string) (($s['address'] ?? '') ?: site('settings.address', '')));
$tips = array_values(array_filter((array) ($s['tips'] ?? []), fn ($t) => trim((string) ($t['title'] ?? '')) !== ''));
if ($address === '' && !$tips) { return; }
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'acces') ?>" class="bg-oat px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28">
        <div class="mx-auto grid max-w-[1460px] gap-8 <?= $address !== '' ? 'lg:grid-cols-[.85fr_1.15fr]' : '' ?> lg:items-stretch">
            <div data-reveal class="topo-dark grain relative overflow-hidden rounded-[30px] bg-pine-950 p-6 text-white sm:p-9">
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><span class="text-[10px] font-bold uppercase tracking-[.24em] text-acid"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                <h2 class="mt-4 pb-1 font-display text-[clamp(38px,6vw,64px)] font-semibold leading-[.98] tracking-[-.045em]"><?= ts_md($s['title'] ?? '') ?></h2>
                <?php if ($address !== ''): ?><p class="mt-5 text-base leading-7 text-white/[.66]"><?= e($address) ?></p><?php endif; ?>
                <div class="relative z-10 mt-8 space-y-3">
                    <?php foreach ($tips as $t): ?>
                    <div class="rounded-[20px] border border-white/10 bg-white/[.055] p-4"><strong class="block text-sm"><?= e($t['title']) ?></strong><?php if (trim((string) ($t['text'] ?? '')) !== ''): ?><p class="mt-1 text-xs leading-5 text-white/[.55]"><?= e($t['text']) ?></p><?php endif; ?></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($address !== ''): ?><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($address)) ?>" target="_blank" rel="noopener" class="relative z-10 mt-8 inline-flex items-center gap-2 rounded-full bg-acid px-5 py-3 text-sm font-bold text-pine-950">Deschide în hărți ↗</a><?php endif; ?>
            </div>
            <?php if ($address !== ''): ?>
            <div data-reveal class="min-h-[380px] overflow-hidden rounded-[30px] border border-pine-900/10 bg-cream shadow-card">
                <iframe title="Hartă" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="h-full min-h-[380px] w-full" src="https://maps.google.com/maps?q=<?= e(rawurlencode($address)) ?>&amp;z=13&amp;output=embed"></iframe>
            </div>
            <?php endif; ?>
        </div>
    </section>
