<?php
/** „Ritmul unei zile”: momentele zilei din panou (oră, titlu, text, imagine). */
require_once TEMPLATE_DIR . '/lib.php';
$fallback = LV_PHOTOS['access'];
$moments = [];
foreach (array_values((array) ($s['moments'] ?? [])) as $i => $m) {
    if (trim((string) ($m['title'] ?? '')) === '') { continue; }
    $moments[] = [
        'id'          => 'd' . $i,
        'time'        => (string) ($m['time'] ?? ''),
        'title'       => (string) $m['title'],
        'caption'     => (string) ($m['caption'] ?? ''),
        'description' => (string) ($m['description'] ?? ''),
        'image'       => trim((string) ($m['image'] ?? '')) ?: lv_photo($fallback[$i % count($fallback)], 1200),
    ];
}
if (!$moments) { return; }
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
    <section id="<?= e(trim((string) ($s['anchor'] ?? '')) ?: 'program') ?>" class="bg-oat py-20 sm:py-28 lg:py-36" x-data="{ moments: <?= e(json_encode($moments, $flags)) ?>, active: 0, get cur() { return this.moments[this.active] || this.moments[0]; } }">
        <div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-10">
            <div class="grid gap-10 lg:grid-cols-[.9fr_1.1fr] lg:gap-16">
                <div data-reveal class="lg:sticky lg:top-28 lg:self-start">
                    <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><div class="text-[10px] font-bold uppercase tracking-[.24em] text-ember"><?= e($s['eyebrow']) ?></div><?php endif; ?>
                    <h2 class="mt-4 pb-1 font-display text-[clamp(44px,10vw,82px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950"><?= ts_md($s['title'] ?? '') ?></h2>
                    <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="mt-6 max-w-lg text-base leading-7 text-pine-900/[.62] sm:text-lg sm:leading-8"><?= ts_md($s['lead']) ?></p><?php endif; ?>

                    <div class="mt-9 overflow-hidden rounded-[26px] bg-pine-950 p-2 shadow-lift sm:p-3">
                        <div class="relative min-h-[340px] overflow-hidden rounded-[20px] sm:min-h-[440px] lg:min-h-[520px]">
                            <template x-for="(mo, i) in moments" :key="mo.id">
                                <img x-show="active === i" x-transition.opacity.duration.500ms :src="mo.image" :alt="mo.title" class="absolute inset-0 h-full w-full object-cover">
                            </template>
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950/[.72] via-transparent to-transparent"></div>
                            <div class="absolute bottom-4 left-4 right-4 rounded-[18px] border border-white/[.12] bg-pine-950/[.62] p-4 text-white backdrop-blur-lg sm:bottom-5 sm:left-5 sm:right-5 sm:p-5">
                                <div class="text-[9px] font-bold uppercase tracking-[.18em] text-acid" x-text="cur.time"></div>
                                <div class="mt-1 font-display text-2xl font-semibold sm:text-3xl" x-text="cur.title"></div>
                                <div class="mt-2 text-xs text-white/[.55] sm:text-sm" x-text="cur.caption"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div data-reveal class="border-t border-pine-900/[.12]">
                    <template x-for="(mo, i) in moments" :key="mo.id">
                        <button type="button" @mouseenter="active = i" @focus="active = i" @click="active = i" class="group grid w-full grid-cols-[58px_1fr_auto] gap-3 border-b border-pine-900/[.12] py-6 text-left sm:grid-cols-[90px_1fr_auto] sm:gap-6 sm:py-8" :class="active === i ? 'text-pine-950' : 'text-pine-900/[.46]'">
                            <div class="font-display text-xl font-semibold sm:text-2xl" x-text="mo.time"></div>
                            <div class="min-w-0"><h3 class="font-display text-2xl font-semibold sm:text-4xl" x-text="mo.title"></h3><p class="mt-2 line-clamp-2 max-w-xl text-xs leading-5 sm:text-sm sm:leading-6" x-text="mo.description"></p></div>
                            <div class="grid h-10 w-10 place-items-center rounded-full border transition sm:h-12 sm:w-12" :class="active === i ? 'rotate-45 border-pine-950 bg-pine-950 text-acid' : 'border-pine-900/[.12] group-hover:border-pine-900/30'">
                                <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 17 10-10M8 7h9v9"/></svg>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </section>
