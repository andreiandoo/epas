<?php
/** Carduri informative numerotate (acces, facilități, pregătire, programe …), din panou. */
$items = array_values(array_filter((array) ($s['items'] ?? []), fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
if (!$items) { return; }
$dark = ($s['style'] ?? 'light') === 'dark';
$cols = min(4, max(2, count($items)));
$badges = ['bg-acid', 'bg-ember', 'bg-sky', 'bg-cream'];
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'info') ?>" class="<?= $dark ? 'topo-dark relative overflow-hidden bg-pine-950 text-white' : 'paper-grid bg-cream text-pine-950' ?> px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28">
        <div class="relative z-10 mx-auto max-w-[1460px]">
            <div data-reveal class="grid gap-6 lg:grid-cols-[1fr_.8fr] lg:items-end">
                <div>
                    <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><span class="text-[10px] font-bold uppercase tracking-[.24em] <?= $dark ? 'text-acid' : 'text-ember' ?>"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                    <h2 class="mt-4 max-w-3xl pb-1 font-display text-[clamp(40px,7.5vw,76px)] font-semibold leading-[.98] tracking-[-.045em]"><?= ts_md($s['title'] ?? '') ?></h2>
                </div>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="max-w-xl text-base leading-7 <?= $dark ? 'text-white/[.58]' : 'text-pine-900/[.62]' ?> sm:text-lg lg:justify-self-end"><?= ts_md($s['lead']) ?></p><?php endif; ?>
            </div>
            <div class="mt-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-<?= $cols ?>">
                <?php foreach ($items as $i => $it): ?>
                <article data-reveal class="rounded-[26px] border p-5 sm:p-6 <?= $dark ? 'border-white/10 bg-white/[.055]' : 'border-pine-900/10 bg-oat' ?>">
                    <span class="grid h-11 w-11 place-items-center rounded-full font-bold text-pine-950 <?= $badges[$i % 4] ?>"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <h3 class="mt-5 font-display text-2xl font-semibold"><?= e($it['title']) ?></h3>
                    <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="mt-3 text-sm leading-6 <?= $dark ? 'text-white/[.55]' : 'text-pine-900/[.58]' ?>"><?= nl2br(e($it['text'])) ?></p><?php endif; ?>
                    <?php if (trim((string) ($it['link_label'] ?? '')) !== ''): ?><a href="<?= e(ts_href($it['link_url'] ?? '')) ?>" class="mt-4 inline-flex items-center gap-1 text-sm font-bold <?= $dark ? 'text-acid' : 'text-pine-900' ?>"><?= e($it['link_label']) ?> ↗</a><?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
