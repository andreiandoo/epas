<?php
/**
 * Coșul (comun tuturor template-urilor): bilete fără loc și locuri numerotate,
 * cod de reducere, timer de rezervare. Logica: cartPage din commerce.js.
 */
$pageTitle = 'Coșul tău — ' . SITE_NAME;
$pageExtraHead = '<meta name="robots" content="noindex">';
$pageExtraStyles = ts_commerce_styles();
$eventsUrl = ts_link('events');
include ts_layout('head');
include ts_layout('header');
?>
<main class="pt-32 pb-24 px-4 lg:px-8" x-data="cartPage">
    <div class="max-w-6xl mx-auto">
        <div class="mb-10 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="font-display text-4xl lg:text-5xl">Coșul tău</h1>
            <?= ts_part_flow(1) ?>
        </div>

        <template x-if="cart">
            <div class="grid gap-8 lg:grid-cols-[1fr_380px]">
                <div class="space-y-5">
                    <?= ts_part_timer() ?>
                    <div class="rounded-xl border border-primary/10 bg-surface/60 overflow-hidden">
                        <a class="flex items-center gap-4 p-5 border-b border-primary/10 hover:bg-fg/[.03]" :href="cart.event.url || '<?= e($eventsUrl) ?>'">
                            <template x-if="cart.event.poster"><img :src="cart.event.poster" alt="" class="h-20 w-16 rounded-lg object-cover"></template>
                            <div>
                                <p class="font-display text-xl" x-text="cart.event.title"></p>
                                <p class="text-sm text-muted" x-text="[cart.event.date, cart.event.place].filter(Boolean).join(' · ')"></p>
                            </div>
                        </a>
                        <template x-for="item in cart.items" :key="item.ticket_type_id">
                            <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 border-b border-primary/10">
                                <div>
                                    <p class="font-semibold" x-text="item.name"></p>
                                    <p class="text-sm text-muted" x-text="lei(item.price) + ' / bilet'"></p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="ts-qty">
                                        <button type="button" @click="step(item, -1)" :aria-label="'Scade ' + item.name">−</button>
                                        <output x-text="item.qty"></output>
                                        <button type="button" @click="step(item, 1)" :disabled="item.qty >= max" :aria-label="'Adaugă ' + item.name">+</button>
                                    </div>
                                    <p class="w-24 text-right font-semibold" x-text="lei(item.qty * item.price)"></p>
                                    <button type="button" class="text-fg/40 hover:text-red-400" @click="remove(item)" :aria-label="'Șterge ' + item.name">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2m-6 0l1 12h6l1-12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template x-for="seat in cart.seats" :key="seat.seat_uid">
                            <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-primary/10">
                                <div>
                                    <p class="font-semibold" x-text="seat.section || 'Loc'"></p>
                                    <p class="text-sm text-muted" x-text="seat.row ? ('Rând ' + seat.row + ' · Loc ' + seat.seat) : seat.label"></p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <p class="font-semibold" x-text="lei(seat.price)"></p>
                                    <button type="button" class="text-fg/40 hover:text-red-400" @click="removeSeat(seat)" :aria-label="'Scoate ' + seat.label">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2m-6 0l1 12h6l1-12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div class="px-5 py-4">
                            <a class="text-sm text-primary hover:text-primary-light" :href="cart.event.url || '<?= e($eventsUrl) ?>'" x-text="cart.seats.length ? 'Alege alte locuri' : 'Adaugă alte bilete'"></a>
                        </div>
                    </div>
                </div>

                <aside class="lg:sticky lg:top-28 h-fit rounded-xl border border-primary/10 bg-surface/60 p-6">
                    <h2 class="font-display text-2xl mb-5">Sumar</h2>
                    <?= ts_part_summary() ?>
                    <a href="/finalizare" class="btn-primary mt-6 block rounded-lg py-4 text-center">Continuă</a>
                    <p class="mt-3 text-center text-xs text-muted">Plata se face cu cardul, în pasul următor.</p>
                </aside>
            </div>
        </template>

        <template x-if="!cart">
            <div class="rounded-xl border border-primary/10 bg-surface/40 px-6 py-20 text-center">
                <h2 class="font-display text-3xl mb-3">Coșul e gol</h2>
                <p class="text-fg/60 mb-8">Alege un eveniment din program și adaugă biletele dorite.</p>
                <a href="<?= e($eventsUrl) ?>" class="btn-primary inline-block rounded-lg px-8 py-4">Vezi programul</a>
            </div>
        </template>
    </div>
</main>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="fixed bottom-6 left-1/2 z-[120] -translate-x-1/2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-on-primary shadow-xl" x-text="msg" role="status"></div>
<?php include ts_layout('footer');
