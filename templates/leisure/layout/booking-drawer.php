<?php
/**
 * Sertarul „Rezervare rapidă” al paginilor din secțiuni: alegi produsul real,
 * apoi ziua, ora, durata și persoanele. Se deschide cu $dispatch('lv-book', {id}).
 */
require_once TEMPLATE_DIR . '/lib.php';
$drawerItems = [];
$drawerEvent = null;
foreach (lv_tickets() as $b) {
    $drawerEvent = $drawerEvent ?? $b['event'] ?? null;
    $drawerItems[] = [
        'ticketTypeId' => (int) $b['ticket_type_id'],
        'name'         => (string) $b['name'],
        'category'     => (string) $b['service_category'],
        'caption'      => lv_category_label($b['service_category'] ?? null) . ' · ' . lv_duration($b),
        'priceCents'   => (int) $b['price_cents'],
        'variants'     => array_values((array) ($b['duration_variants'] ?? [])),
    ];
}
if (!$drawerItems) { return; }
$drawerEventJs = [
    'id'     => (int) ($drawerEvent['id'] ?? 0),
    'slug'   => (string) ($drawerEvent['slug'] ?? ''),
    'title'  => SITE_NAME,
    'place'  => (string) ($drawerEvent['venue'] ?? SITE_NAME),
    'poster' => lv_photo(LV_PHOTOS['access'][0], 600),
    'url'    => '/',
];
foreach ($drawerItems as &$it) { $it['event'] = $drawerEventJs; }
unset($it);
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
?>
    <script>
        function lvDrawer() {
            const items = <?= json_encode($drawerItems, $flags) ?>;
            return LVBooking.with(items[0], {
                open: false,
                items: items,
                started: false,
                pick(id) {
                    const next = this.items.find(i => i.ticketTypeId === Number(id)) || this.items[0];
                    if (this.started && next.ticketTypeId === this.lv.ticketTypeId) return;
                    this.started = true;
                    this.lvSetProduct(next);
                },
                show(detail) {
                    this.pick(detail && detail.id ? detail.id : (this.started ? this.lv.ticketTypeId : this.items[0].ticketTypeId));
                    this.open = true;
                    document.body.style.overflow = 'hidden';
                },
                hide() { this.open = false; document.body.style.overflow = ''; }
            });
        }
    </script>
    <div x-data="lvDrawer()" @lv-book.window="show($event.detail)" @keydown.escape.window="hide()">
        <div x-cloak x-show="open" class="fixed inset-0 z-[150]" role="dialog" aria-modal="true" aria-label="Rezervare rapidă">
            <div x-show="open" x-transition.opacity @click="hide()" class="absolute inset-0 bg-pine-950/[.72] backdrop-blur-sm"></div>
            <aside x-show="open" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-300 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="drawer-shadow safe-bottom absolute right-0 top-0 flex h-full w-full max-w-[520px] flex-col overflow-y-auto bg-cream text-pine-950">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-pine-900/10 bg-cream/[.92] px-4 py-4 backdrop-blur-xl sm:px-6">
                    <div><div class="text-[9px] font-bold uppercase tracking-[.18em] text-ember">Rezervare rapidă</div><h2 class="mt-1 font-display text-2xl font-semibold text-pine-950 sm:text-3xl">Construiește vizita</h2></div>
                    <button type="button" @click="hide()" class="grid h-11 w-11 place-items-center rounded-full border border-pine-900/[.12]" aria-label="Închide rezervarea"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="mb-3 flex items-center justify-between"><span class="text-sm font-bold">Ce rezervi?</span></div>
                    <div class="grid gap-2">
                        <template x-for="o in items" :key="o.ticketTypeId">
                            <button type="button" @click="pick(o.ticketTypeId)" class="flex items-center justify-between gap-4 rounded-[18px] border p-4 text-left transition" :class="lv.ticketTypeId === o.ticketTypeId ? 'border-pine-950 bg-pine-950 text-white' : 'border-pine-900/10 bg-white text-pine-950'">
                                <span class="min-w-0"><strong class="block truncate text-sm" x-text="o.name"></strong><span class="mt-1 block text-xs" :class="lv.ticketTypeId === o.ticketTypeId ? 'text-white/[.48]' : 'text-pine-900/[.48]'" x-text="o.caption"></span></span>
                                <strong class="whitespace-nowrap" :class="lv.ticketTypeId === o.ticketTypeId ? 'text-acid' : ''" x-text="'de la ' + lvLei(o.priceCents)"></strong>
                            </button>
                        </template>
                    </div>
<?php $lvCompact = false; include __DIR__ . '/booking-steps.php'; ?>
                </div>

                <div class="sticky bottom-0 mt-auto border-t border-pine-900/10 bg-cream/[.94] p-4 backdrop-blur-xl sm:p-6">
                    <div class="mb-4 flex items-end justify-between gap-4"><div class="min-w-0"><span class="block truncate text-xs text-pine-900/[.45]" x-text="lvWhen || 'Alege ziua'"></span><strong class="mt-1 block text-sm">Total estimat</strong></div><div class="font-display text-3xl font-semibold text-pine-950" x-text="lvTotal"></div></div>
                    <button type="button" :disabled="!lvReady" @click="lvAddToCart()" class="w-full rounded-full bg-acid px-5 py-4 font-bold text-pine-950 shadow-acid transition disabled:cursor-not-allowed disabled:opacity-45">Continuă către coș</button>
                    <p x-show="lvError" x-text="lvError" class="mt-3 text-center text-xs font-semibold text-ember"></p>
                </div>
            </aside>
        </div>
    </div>
