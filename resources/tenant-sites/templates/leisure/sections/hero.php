<?php
/**
 * Hero Nordvale: titlu pe rânduri, butoane, colaj cu imaginea parcului, cardul
 * produsului recomandat (real), programul zilei și cifrele parcului.
 */
require_once TEMPLATE_DIR . '/lib.php';
$GLOBALS['lvDrawer'] = true;
$lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['title'] ?? '')))));
$img = trim((string) ($s['image'] ?? '')) ?: lv_photo('photo-1521336575822-6da63fb45455', 1600);
$hours = trim((string) site('settings.today_hours', ''));
$lastEntry = trim((string) site('settings.last_entry', ''));
$featured = lv_tickets('activity')[0] ?? lv_tickets()[0] ?? null;
$stats = array_values(array_filter((array) ($s['stats'] ?? []), fn ($r) => trim((string) ($r['label'] ?? '')) !== ''));
$badges = array_values(array_filter([trim((string) ($s['badge_1'] ?? '')), trim((string) ($s['badge_2'] ?? ''))]));
?>
<?php if (!empty($s['show_intro'])): ?>
    <div data-intro class="intro-screen grain" aria-hidden="true">
        <div class="relative z-10 w-[min(78vw,480px)]">
            <div class="overflow-hidden">
                <div data-intro-word class="font-display text-[clamp(44px,9vw,96px)] font-semibold leading-none"><?= e(SITE_NAME) ?></div>
            </div>
            <div class="intro-line mt-5 h-px bg-acid"></div>
            <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><div data-intro-copy class="mt-4 text-xs font-semibold uppercase tracking-[.24em] text-white/[.55]"><?= e($s['eyebrow']) ?></div><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
    <div class="fixed inset-x-0 top-0 z-[140] h-[3px]"><div id="page-progress" class="progress-line h-full w-full bg-acid"></div></div>

    <section id="top" class="grain topo-dark relative min-h-[100svh] overflow-hidden bg-pine-950 text-white">
        <div class="absolute inset-0 bg-[linear-gradient(180deg,rgb(var(--c-pine-950)_/_.08),rgb(var(--c-pine-950)_/_.5))]"></div>
        <div class="glow-orbit -left-[140px] top-[20%] h-[420px] w-[420px]"></div>
        <div class="glow-orbit -right-[180px] bottom-[5%] h-[520px] w-[520px]"></div>

        <div class="relative z-10 mx-auto grid min-h-[100svh] max-w-[1540px] gap-10 px-4 pb-10 pt-28 sm:px-6 sm:pt-32 lg:grid-cols-[.92fr_1.08fr] lg:items-center lg:gap-14 lg:px-8 lg:pb-14 lg:pt-28 xl:px-12">
            <div class="min-w-0 lg:pb-10">
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?>
                <div data-hero-kicker class="mb-5 inline-flex max-w-full items-center gap-2 rounded-full border border-white/[.14] bg-white/[.07] px-3 py-2 text-[9px] font-bold uppercase tracking-[.2em] text-white/[.74] sm:px-4 sm:text-[10px] sm:tracking-[.24em]">
                    <span class="pulse-dot h-2 w-2 flex-none rounded-full bg-acid"></span>
                    <span class="truncate"><?= e($s['eyebrow']) ?></span>
                </div>
                <?php endif; ?>

                <h1 class="font-display text-[clamp(50px,13.5vw,94px)] font-semibold leading-[.9] tracking-[-.055em] lg:text-[clamp(72px,7.6vw,126px)]">
                    <?php foreach ($lines as $i => $line): ?>
                    <span data-hero-title class="block pb-1 <?= $i === 1 ? 'text-acid' : ($i === 2 ? 'outline-word italic' : '') ?>"><?= ts_md($line) ?></span>
                    <?php endforeach; ?>
                </h1>

                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?>
                <p data-hero-copy class="mt-7 max-w-xl text-[15px] leading-7 text-white/[.66] sm:text-lg sm:leading-8"><?= ts_md($s['lead']) ?></p>
                <?php endif; ?>

                <div data-hero-cta class="mt-7 grid max-w-[520px] grid-cols-2 gap-2.5 sm:flex sm:flex-wrap sm:gap-3">
                    <?php if (trim((string) ($s['button_label'] ?? '')) !== ''): ?>
                    <button type="button" @click="$dispatch('lv-book')" x-data class="group inline-flex min-h-[52px] items-center justify-center gap-2 whitespace-nowrap rounded-full bg-acid px-4 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-1 sm:px-6 sm:text-base">
                        <?= e($s['button_label']) ?>
                        <svg class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                    <?php endif; ?>
                    <?php if (trim((string) ($s['secondary_label'] ?? '')) !== ''): ?>
                    <a href="<?= e(ts_href($s['secondary_url'] ?? '', '/experiente')) ?>" class="inline-flex min-h-[52px] items-center justify-center whitespace-nowrap rounded-full border border-white/20 px-4 text-sm font-semibold text-white transition hover:bg-white/10 sm:px-6 sm:text-base"><?= e($s['secondary_label']) ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($badges): ?>
                <div data-hero-copy class="mt-8 flex flex-wrap gap-x-5 gap-y-3 text-xs font-semibold text-white/[.56] sm:text-sm">
                    <?php foreach ($badges as $badge): ?>
                    <span class="flex items-center gap-2"><svg class="h-4 w-4 text-acid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg> <?= e($badge) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div data-hero-visual class="relative min-h-[430px] min-w-0 sm:min-h-[560px] lg:min-h-[700px]">
                <div class="hero-mask absolute inset-0 overflow-hidden border border-white/10 bg-pine-850 shadow-lift">
                    <img id="hero-bg" src="<?= e($img) ?>" alt="<?= e(SITE_NAME) ?>" class="h-full w-full object-cover object-center">
                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/10 to-transparent"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-pine-950/[.35] via-transparent to-transparent"></div>
                </div>

                <svg class="pointer-events-none absolute inset-[7%] z-10 h-[78%] w-[86%]" viewBox="0 0 700 660" fill="none" aria-hidden="true">
                    <path id="hero-route" class="route-line" d="M80 545C145 495 103 414 195 382c86-30 144 45 215-4 58-40 23-108 92-147 53-29 109 4 136-77"/>
                    <circle cx="80" cy="545" r="7" fill="rgb(var(--c-primary))"/>
                    <circle cx="638" cy="154" r="7" fill="#F27B4A"/>
                    <g id="route-marker"><circle r="13" fill="rgb(var(--c-pine-900))" stroke="rgb(var(--c-primary))" stroke-width="3"/><circle r="4" fill="rgb(var(--c-primary))"/></g>
                </svg>

                <?php if ($featured): ?>
                <a href="/experienta/<?= e(lv_slug($featured)) ?>" class="absolute bottom-4 left-4 z-20 block max-w-[calc(100%-32px)] rounded-[20px] border border-white/[.15] bg-pine-950/[.78] p-4 text-white shadow-card backdrop-blur-xl transition hover:-translate-y-1 sm:bottom-7 sm:left-7 sm:max-w-[330px] sm:p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-[9px] font-bold uppercase tracking-[.2em] text-acid"><?= e(trim((string) ($s['featured_label'] ?? '')) ?: 'Recomandat') ?></div>
                            <div class="mt-1 truncate font-display text-xl font-semibold sm:text-2xl"><?= e($featured['name']) ?></div>
                        </div>
                        <div class="grid h-11 w-11 flex-none place-items-center rounded-full bg-acid text-pine-950"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg></div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3 border-t border-white/10 pt-4 text-xs">
                        <div class="min-w-0"><span class="block text-white/[.45]">Durată</span><strong class="mt-1 block truncate"><?= e(lv_duration($featured)) ?></strong></div>
                        <div><span class="block text-white/[.45]">Tip</span><strong class="mt-1 block"><?= e(lv_category_label($featured['service_category'] ?? null)) ?></strong></div>
                        <div><span class="block text-white/[.45]">De la</span><strong class="mt-1 block text-acid"><?= e(lv_price((int) $featured['price_cents'])) ?></strong></div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($hours !== ''): ?>
                <div class="absolute right-3 top-5 z-20 hidden rotate-3 rounded-[18px] bg-cream p-4 text-pine-950 shadow-card sm:block lg:right-[-14px] lg:top-[12%]">
                    <div class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-700">Program azi</div>
                    <div class="mt-2 font-display text-3xl font-semibold"><?= e($hours) ?></div>
                    <?php if ($lastEntry !== ''): ?><div class="mt-2 text-[10px] text-pine-700">Ultima intrare <?= e($lastEntry) ?></div><?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="absolute left-[-8px] top-[18%] z-20 hidden items-center gap-2 rounded-full border border-white/[.15] bg-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-[.17em] text-white backdrop-blur-lg md:flex">
                    <span class="pulse-dot h-2 w-2 rounded-full bg-acid"></span> Bilete online
                </div>
            </div>
        </div>

        <?php if ($stats): ?>
        <div class="relative z-10 border-t border-white/10 bg-pine-950/[.35] backdrop-blur-sm">
            <div class="mx-auto grid max-w-[1540px] grid-cols-2 divide-x divide-white/10 px-4 sm:grid-cols-<?= min(4, count($stats)) ?> sm:px-6 lg:px-12">
                <?php foreach (array_slice($stats, 0, 4) as $st): ?>
                <div class="px-3 py-5 sm:px-6 sm:py-6">
                    <div class="font-display text-2xl font-semibold text-white sm:text-3xl"><?= is_numeric($st['value'] ?? null)
                        ? '<span class="js-counter" data-value="' . e((string) (int) $st['value']) . '">' . e(($st['prefix'] ?? '') . '0' . ($st['suffix'] ?? '')) . '</span>'
                        : e(($st['prefix'] ?? '') . ($st['value'] ?? '') . ($st['suffix'] ?? '')) ?></div>
                    <div class="mt-1 text-[10px] font-bold uppercase tracking-[.16em] text-white/[.42] sm:text-xs"><?= e($st['label']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
