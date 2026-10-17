<?php
/** Noutăți scrise în panou: carduri, iar articolul întreg se deschide într-o fereastră. */
require_once TEMPLATE_DIR . '/lib.php';
$items = [];
foreach (array_values((array) ($s['items'] ?? [])) as $i => $it) {
    if (trim((string) ($it['title'] ?? '')) === '') { continue; }
    $items[] = [
        'id'      => $i,
        'date'    => (string) ($it['date'] ?? ''),
        'label'   => (string) ($it['label'] ?? ''),
        'title'   => (string) $it['title'],
        'excerpt' => (string) ($it['excerpt'] ?? ''),
        'text'    => (string) ($it['text'] ?? ''),
        'image'   => trim((string) ($it['image'] ?? '')) ?: lv_photo(LV_PHOTOS['access'][$i % 4], 1200),
    ];
}
if (!$items) { return; }
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'noutati') ?>" class="bg-oat px-4 py-20 sm:px-6 sm:py-24 lg:px-10 lg:py-28"
             x-data="{ items: <?= e(json_encode($items, $flags)) ?>, open: null, get cur() { return this.items.find(i => i.id === this.open) || null; } }"
             @keydown.escape.window="open = null">
        <div class="mx-auto max-w-[1460px]">
            <?php if (trim((string) ($s['title'] ?? '')) !== ''): ?>
            <div data-reveal>
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><span class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></span><?php endif; ?>
                <h2 class="mt-4 max-w-3xl pb-1 font-display text-[clamp(40px,7.5vw,76px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950"><?= ts_md($s['title']) ?></h2>
            </div>
            <?php endif; ?>
            <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="(n, i) in items" :key="n.id">
                    <button type="button" @click="open = n.id" class="group overflow-hidden rounded-[26px] border border-pine-900/10 bg-cream text-left shadow-card transition hover:-translate-y-1" :class="i === 0 ? 'md:col-span-2 xl:col-span-2' : ''">
                        <div class="relative overflow-hidden" :class="i === 0 ? 'aspect-[16/8]' : 'aspect-[4/3]'"><img :src="n.image" :alt="n.title" class="h-full w-full object-cover transition duration-700 group-hover:scale-105" loading="lazy"><span x-show="n.label" class="absolute left-4 top-4 rounded-full bg-acid px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.16em] text-pine-950" x-text="n.label"></span></div>
                        <div class="p-5 sm:p-6"><p class="text-xs font-semibold text-pine-900/45" x-text="n.date"></p><h3 class="mt-2 font-display text-2xl font-semibold text-pine-950 sm:text-3xl" x-text="n.title"></h3><p class="mt-2 line-clamp-3 text-sm leading-6 text-pine-900/60" x-text="n.excerpt"></p><span class="mt-4 inline-flex text-sm font-bold text-pine-900">Citește ↗</span></div>
                    </button>
                </template>
            </div>
        </div>

        <div x-cloak x-show="cur" class="fixed inset-0 z-[160] grid place-items-center bg-pine-950/80 p-3 backdrop-blur-sm sm:p-6" @click.self="open = null" role="dialog" aria-modal="true">
            <article class="max-h-[90svh] w-full max-w-3xl overflow-y-auto rounded-[28px] bg-cream text-pine-950 shadow-lift">
                <div class="relative aspect-[16/8] overflow-hidden"><img :src="cur ? cur.image : ''" alt="" class="h-full w-full object-cover"><button type="button" @click="open = null" class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-cream text-pine-950" aria-label="Închide">×</button></div>
                <div class="p-6 sm:p-9"><p class="text-xs font-semibold text-pine-900/45" x-text="cur ? cur.date : ''"></p><h3 class="mt-2 font-display text-4xl font-semibold" x-text="cur ? cur.title : ''"></h3><div class="mt-5 whitespace-pre-line text-base leading-8 text-pine-900/70" x-text="cur ? (cur.text || cur.excerpt) : ''"></div></div>
            </article>
        </div>
    </section>
