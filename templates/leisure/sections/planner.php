<?php
/**
 * Planificatorul „cum vrei să te simți”: fiecare stare (din panou) trimite spre o
 * categorie de produse reale; prețul „de la” și rezervarea vin din core.
 */
require_once TEMPLATE_DIR . '/lib.php';
$GLOBALS['lvDrawer'] = true;
$icons = [
    '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V8m0 0C9 8 6 6 6 3c3 0 6 2 6 5Zm0 4c3 0 6-2 6-5-3 0-6 2-6 5Z"/></svg>',
    '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 19 7-14 4 8 3-5 4 11H3Z"/></svg>',
    '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="3"/><circle cx="16.5" cy="9" r="2.5"/><path d="M2 21v-2a6 6 0 0 1 12 0v2m0-4.5a5 5 0 0 1 8 4V21"/></svg>',
];
$moods = [];
foreach (array_values((array) ($s['moods'] ?? [])) as $i => $m) {
    if (trim((string) ($m['name'] ?? '')) === '') { continue; }
    $cat = (string) ($m['category'] ?? 'access');
    $items = lv_tickets($cat) ?: lv_tickets();
    $cheapest = $items ? min(array_map(fn ($b) => (int) $b['price_cents'], $items)) : 0;
    $moods[] = [
        'id'          => 'm' . $i,
        'name'        => (string) $m['name'],
        'caption'     => (string) ($m['caption'] ?? ''),
        'title'       => (string) (($m['title'] ?? '') ?: $m['name']),
        'description' => (string) ($m['description'] ?? ''),
        'tags'        => array_values(array_filter(array_map('trim', explode(',', (string) ($m['tags'] ?? ''))))),
        'duration'    => (string) ($m['duration'] ?? ''),
        'price'       => $cheapest ? lv_price($cheapest) : '',
        'image'       => trim((string) ($m['image'] ?? '')) ?: lv_photo(LV_PHOTOS[$cat][0] ?? LV_PHOTOS['access'][0], 1000),
        'icon'        => $icons[$i % 3],
        'book'        => $items ? (int) $items[0]['ticket_type_id'] : 0,
    ];
}
if (!$moods) { return; }
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'planner') ?>" class="paper-grid bg-cream py-20 sm:py-28 lg:py-36" x-data="{ moods: <?= e(json_encode($moods, $flags)) ?>, sel: <?= min(1, count($moods) - 1) ?>, get m() { return this.moods[this.sel] || this.moods[0]; } }">
        <div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
            <div data-reveal class="grid gap-8 lg:grid-cols-[.78fr_1.22fr] lg:items-end">
                <div>
                    <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></div><?php endif; ?>
                    <h2 class="mt-4 max-w-3xl pb-1 font-display text-[clamp(42px,9vw,86px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950 lg:text-[clamp(56px,6vw,94px)]"><?= ts_md($s['title'] ?? '') ?></h2>
                </div>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="max-w-xl text-base leading-7 text-pine-900/[.62] sm:text-lg sm:leading-8 lg:justify-self-end"><?= ts_md($s['lead']) ?></p><?php endif; ?>
            </div>

            <div class="mt-12 grid gap-5 lg:mt-16 lg:grid-cols-[.72fr_1.28fr]">
                <div data-reveal class="rounded-[28px] bg-pine-950 p-5 text-white sm:p-7 lg:p-8">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-[.2em] text-white/[.45]">01 · Ritmul zilei</span>
                        <span class="rounded-full border border-white/[.12] px-3 py-1 text-[9px] font-bold uppercase tracking-[.16em] text-acid">1 minut</span>
                    </div>
                    <div class="mt-7 space-y-2.5">
                        <template x-for="(mood, i) in moods" :key="mood.id">
                            <button type="button" @click="sel = i" class="group flex w-full items-center gap-4 rounded-[18px] border p-3.5 text-left transition sm:p-4" :class="sel === i ? 'border-acid bg-acid text-pine-950' : 'border-white/10 bg-white/[.035] text-white hover:border-white/25'">
                                <span class="grid h-10 w-10 flex-none place-items-center rounded-full" :class="sel === i ? 'bg-pine-950 text-acid' : 'bg-white/[.08] text-acid'" x-html="mood.icon"></span>
                                <span class="min-w-0 flex-1"><strong class="block truncate text-sm sm:text-base" x-text="mood.name"></strong><span class="mt-0.5 block truncate text-xs" :class="sel === i ? 'text-pine-900/[.65]' : 'text-white/[.45]'" x-text="mood.caption"></span></span>
                                <svg class="h-4 w-4 flex-none transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                        </template>
                    </div>
                </div>

                <div data-reveal class="relative overflow-hidden rounded-[28px] bg-oat p-5 sm:p-8 lg:p-10">
                    <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full border border-pine-900/10"></div>
                    <div class="absolute -right-7 -top-7 h-32 w-32 rounded-full border border-pine-900/10"></div>
                    <div class="relative z-10 grid h-full gap-8 md:grid-cols-[1fr_.9fr] md:items-end">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-700">Recomandarea noastră</span>
                            <h3 class="mt-4 font-display text-4xl font-semibold leading-tight text-pine-950 sm:text-5xl" x-text="m.title"></h3>
                            <p class="mt-4 max-w-lg text-sm leading-7 text-pine-900/[.62] sm:text-base" x-text="m.description"></p>
                            <div class="mt-7 flex flex-wrap gap-2">
                                <template x-for="tag in m.tags" :key="tag"><span class="rounded-full border border-pine-900/[.12] bg-cream px-3 py-2 text-[10px] font-bold uppercase tracking-[.13em] text-pine-800" x-text="tag"></span></template>
                            </div>
                            <button type="button" x-show="m.book" @click="$dispatch('lv-book', { id: m.book })" class="mt-8 inline-flex items-center gap-2 rounded-full bg-pine-950 px-5 py-3.5 text-sm font-bold text-white transition hover:-translate-y-1"><?= e(trim((string) ($s['button_label'] ?? '')) ?: 'Rezervă') ?> <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                        </div>
                        <div class="relative min-h-[290px] overflow-hidden rounded-[22px] sm:min-h-[360px]">
                            <img :src="m.image" :alt="m.title" class="absolute inset-0 h-full w-full object-cover transition duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950/[.65] via-transparent to-transparent"></div>
                            <div class="absolute bottom-4 left-4 right-4 rounded-[16px] border border-white/[.15] bg-pine-950/[.65] p-4 text-white backdrop-blur-lg">
                                <div x-show="m.duration" class="flex items-center justify-between"><span class="text-xs text-white/[.55]">Durată recomandată</span><strong x-text="m.duration"></strong></div>
                                <div x-show="m.price" class="mt-2 flex items-center justify-between"><span class="text-xs text-white/[.55]">De la</span><strong class="text-acid" x-text="m.price"></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
