<?php
/** Biletele de acces reale (până la 3), cu rezervare directă; opțional un card spre abonamente. */
require_once TEMPLATE_DIR . '/lib.php';
$GLOBALS['lvDrawer'] = true;
$cards = array_slice(lv_tickets('access') ?: lv_tickets(), 0, 3);
$sub = trim((string) ($s['subscription_title'] ?? ''));
if (!$cards && $sub === '') { return; }
$featuredIdx = count($cards) >= 2 ? 1 : 0;
$benefits = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['benefits'] ?? '')))));
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'bilete-acces') ?>" class="relative overflow-hidden bg-pine-950 py-20 text-white sm:py-28 lg:py-36">
        <div class="absolute inset-0 topo-dark opacity-60"></div>
        <div class="relative z-10 mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
            <div data-reveal class="grid gap-7 lg:grid-cols-[1fr_.72fr] lg:items-end">
                <div><?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><div class="text-[10px] font-bold uppercase tracking-[.24em] text-acid"><?= e($s['eyebrow']) ?></div><?php endif; ?><h2 class="mt-4 max-w-4xl pb-1 font-display text-[clamp(44px,10vw,88px)] font-semibold leading-[.98] tracking-[-.045em]"><?= ts_md($s['title'] ?? '') ?></h2></div>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="max-w-xl text-base leading-7 text-white/[.58] sm:text-lg sm:leading-8 lg:justify-self-end"><?= ts_md($s['lead']) ?></p><?php endif; ?>
            </div>

            <div class="mt-12 grid gap-4 md:grid-cols-<?= max(1, min(3, count($cards) + ($sub !== '' ? 1 : 0))) ?>" x-data>
                <?php foreach ($cards as $i => $b): $f = $i === $featuredIdx; $desc = trim(strip_tags((string) ($b['description'] ?? ''))); ?>
                <article data-reveal class="relative flex min-h-[440px] flex-col overflow-hidden rounded-[26px] border p-5 sm:p-7 <?= $f ? 'border-acid bg-acid text-pine-950' : 'border-white/[.12] bg-white/[.045] text-white' ?>">
                    <div class="flex items-center justify-between gap-3"><span class="text-[9px] font-bold uppercase tracking-[.18em] <?= $f ? 'text-pine-700' : 'text-white/[.45]' ?>"><?= e(lv_duration($b)) ?></span><?php if ($f && trim((string) ($s['featured_label'] ?? '')) !== ''): ?><span class="rounded-full bg-pine-950 px-3 py-1.5 text-[8px] font-bold uppercase tracking-[.15em] text-acid"><?= e($s['featured_label']) ?></span><?php endif; ?></div>
                    <h3 class="mt-5 font-display text-3xl font-semibold sm:text-4xl"><?= e($b['name']) ?></h3>
                    <?php if ($desc !== ''): ?><p class="mt-3 min-h-[48px] text-sm leading-6 <?= $f ? 'text-pine-900/[.65]' : 'text-white/[.55]' ?>"><?= e($desc) ?></p><?php endif; ?>
                    <div class="mt-7 flex items-end gap-2"><span class="font-display text-5xl font-semibold sm:text-6xl"><?= e(rtrim(rtrim(number_format(((int) $b['price_cents']) / 100, 2, ',', '.'), '0'), ',')) ?></span><span class="pb-2 text-sm">lei / bilet</span></div>
                    <?php if ($benefits): ?>
                    <div class="mt-7 space-y-3">
                        <?php foreach ($benefits as $ben): ?><div class="flex items-start gap-3 text-sm"><svg class="mt-0.5 h-4 w-4 flex-none <?= $f ? 'text-pine-950' : 'text-acid' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg><span><?= e($ben) ?></span></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <button type="button" @click="$dispatch('lv-book', { id: <?= (int) $b['ticket_type_id'] ?> })" class="mt-auto w-full rounded-full px-5 py-3.5 text-sm font-bold transition hover:-translate-y-1 <?= $f ? 'bg-pine-950 text-white' : 'bg-cream text-pine-950' ?>" style="margin-top:auto"><?= e(trim((string) ($s['button_label'] ?? '')) ?: 'Alege ziua') ?></button>
                </article>
                <?php endforeach; ?>

                <?php if ($sub !== ''): ?>
                <article data-reveal class="relative flex min-h-[440px] flex-col overflow-hidden rounded-[26px] border border-white/[.12] bg-white/[.045] p-5 text-white sm:p-7">
                    <span class="text-[9px] font-bold uppercase tracking-[.18em] text-white/[.45]">Pentru cei care revin</span>
                    <h3 class="mt-5 font-display text-3xl font-semibold sm:text-4xl"><?= e($sub) ?></h3>
                    <?php if (trim((string) ($s['subscription_text'] ?? '')) !== ''): ?><p class="mt-3 text-sm leading-6 text-white/[.55]"><?= e($s['subscription_text']) ?></p><?php endif; ?>
                    <a href="<?= e(ts_link('subscriptions')) ?>" class="mt-auto block w-full rounded-full bg-cream px-5 py-3.5 text-center text-sm font-bold text-pine-950 transition hover:-translate-y-1">Vezi abonamentele</a>
                </article>
                <?php endif; ?>
            </div>
        </div>
    </section>
