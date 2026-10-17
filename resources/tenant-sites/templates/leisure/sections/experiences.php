<?php
/** Grila produselor reale (acces, activități, închirieri), cu filtru pe categorie. */
require_once TEMPLATE_DIR . '/lib.php';
$limit = max(3, (int) ($s['limit'] ?? 5));
$items = [];
$cats = [];
foreach (array_slice(lv_tickets(), 0, $limit) as $b) {
    $cat = (string) $b['service_category'];
    $cats[$cat] = true;
    $desc = trim(strip_tags((string) ($b['description'] ?? '')));
    $items[] = [
        'id'       => (int) $b['ticket_type_id'],
        'category' => $cat,
        'level'    => lv_category_label($cat),
        'duration' => lv_duration($b),
        'eyebrow'  => 'de la ' . lv_price((int) $b['price_cents']),
        'title'    => (string) $b['name'],
        'copy'     => $desc,
        'image'    => lv_image($b, 1400),
        'url'      => '/experienta/' . lv_slug($b),
    ];
}
if (!$items) { return; }
$labels = ['access' => 'Acces', 'activity' => 'Activități', 'rental' => 'Închirieri'];
$filters = [['id' => 'all', 'label' => 'Toate']];
foreach ($labels as $id => $label) {
    if (isset($cats[$id])) { $filters[] = ['id' => $id, 'label' => $label]; }
}
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'experiente') ?>" class="relative overflow-hidden bg-pine-950 py-20 text-white sm:py-28 lg:py-36" x-data="{ items: <?= e(json_encode($items, $flags)) ?>, cat: 'all', get list() { return this.cat === 'all' ? this.items : this.items.filter(i => i.category === this.cat); } }">
        <div class="absolute inset-0 topo-dark opacity-70"></div>
        <div class="relative z-10 mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
            <div data-reveal class="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><div class="text-[10px] font-bold uppercase tracking-[.24em] text-acid"><?= e($s['eyebrow']) ?></div><?php endif; ?>
                    <h2 class="mt-4 max-w-4xl pb-1 font-display text-[clamp(44px,10vw,88px)] font-semibold leading-[.98] tracking-[-.045em]"><?= ts_md($s['title'] ?? '') ?></h2>
                </div>
                <?php if (count($filters) > 2): ?>
                <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide lg:max-w-[460px] lg:flex-wrap lg:justify-end">
                    <?php foreach ($filters as $f): ?>
                    <button type="button" @click="cat = '<?= e($f['id']) ?>'" class="flex-none whitespace-nowrap rounded-full border px-4 py-2.5 text-xs font-bold transition" :class="cat === '<?= e($f['id']) ?>' ? 'border-acid bg-acid text-pine-950' : 'border-white/[.14] text-white/[.62] hover:border-white/[.35] hover:text-white'"><?= e($f['label']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-2 xl:grid-cols-12">
                <template x-for="(item, index) in list" :key="item.id">
                    <article class="experience-card min-w-0" :class="index === 0 ? 'xl:col-span-7' : index === 1 ? 'xl:col-span-5' : 'xl:col-span-4'">
                        <a :href="item.url" class="experience-card__inner group relative block min-h-[420px] overflow-hidden rounded-[24px] border border-white/10 sm:min-h-[470px]" :class="index < 2 ? 'xl:min-h-[610px]' : 'xl:min-h-[470px]'">
                            <img :src="item.image" :alt="item.title" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/[.12] to-transparent"></div>
                            <div class="absolute left-4 top-4 flex items-center gap-2 sm:left-5 sm:top-5">
                                <span class="rounded-full bg-cream px-3 py-2 text-[9px] font-bold uppercase tracking-[.16em] text-pine-950" x-text="item.level"></span>
                                <span class="rounded-full border border-white/[.18] bg-pine-950/[.38] px-3 py-2 text-[9px] font-bold uppercase tracking-[.16em] text-white backdrop-blur" x-text="item.duration"></span>
                            </div>
                            <div class="absolute bottom-0 left-0 right-0 p-5 sm:p-7">
                                <div class="text-[9px] font-bold uppercase tracking-[.2em] text-acid" x-text="item.eyebrow"></div>
                                <div class="mt-2 flex items-end justify-between gap-4">
                                    <div class="min-w-0"><h3 class="font-display text-3xl font-semibold sm:text-4xl" x-text="item.title"></h3><p class="mt-2 line-clamp-2 max-w-lg text-sm leading-6 text-white/[.62]" x-text="item.copy"></p></div>
                                    <span class="grid h-12 w-12 flex-none place-items-center rounded-full bg-acid text-pine-950 transition group-hover:rotate-45 sm:h-14 sm:w-14"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg></span>
                                </div>
                            </div>
                        </a>
                    </article>
                </template>
            </div>

            <?php if (trim((string) ($s['button_label'] ?? '')) !== ''): ?>
            <div class="mt-10 text-center"><a href="<?= e(ts_href($s['button_url'] ?? '', '/experiente')) ?>" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/10"><?= e($s['button_label']) ?> <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a></div>
            <?php endif; ?>
        </div>
    </section>
