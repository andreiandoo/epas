<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Coșul meu — ' . SITE_SHORT;
$bodyClass = 'is-light';
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light" x-data="cartPage">
    <section class="phead phead--slim">
        <div class="wrap">
            <span class="label">Pasul 1 din 3</span>
            <h1>Coșul meu</h1>
            <?= part_flow(1) ?>
        </div>
    </section>

    <div class="wrap">
        <template x-if="cart">
            <div class="split">
                <div style="display:grid;gap:20px">
                    <?= part_timer() ?>
                    <div class="panel">
                        <a class="ev-mini" :href="'/competitii/' + cart.event.slug">
                            <template x-if="cart.event.poster"><img class="ev-mini__img" :src="cart.event.poster" alt=""></template>
                            <template x-if="!cart.event.poster"><div class="ev-mini__img"></div></template>
                            <div>
                                <b x-text="cart.event.title"></b>
                                <span x-text="cart.event.date + ' · ' + cart.event.place"></span>
                            </div>
                        </a>
                        <template x-for="item in cart.items" :key="item.ticket_type_id">
                            <div class="line">
                                <div>
                                    <div class="line__name" x-text="item.name"></div>
                                    <div class="line__unit" x-text="lei(item.price) + ' / bilet'"></div>
                                </div>
                                <div class="line__tools">
                                    <div class="qty">
                                        <button type="button" @click="step(item, -1)" :aria-label="'Scade ' + item.name">−</button>
                                        <output x-text="item.qty"></output>
                                        <button type="button" @click="step(item, 1)" :disabled="item.qty >= max" :aria-label="'Adaugă ' + item.name">+</button>
                                    </div>
                                    <div class="line__sum" x-text="lei(item.qty * item.price)"></div>
                                    <button type="button" class="line__del" @click="remove(item)" :aria-label="'Șterge ' + item.name">
                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2m-6 0l1 12h6l1-12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template x-for="seat in cart.seats" :key="seat.seat_uid">
                            <div class="line">
                                <div>
                                    <div class="line__name" x-text="seat.section"></div>
                                    <div class="line__unit" x-text="'Rând ' + seat.row + ' · Loc ' + seat.seat"></div>
                                </div>
                                <div class="line__tools">
                                    <div class="line__sum" x-text="lei(seat.price)"></div>
                                    <button type="button" class="line__del" @click="removeSeat(seat)" :aria-label="'Scoate ' + seat.label">
                                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2m-6 0l1 12h6l1-12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div class="panel__body">
                            <a class="link" :href="'/competitii/' + cart.event.slug + '#bilete'" x-text="cart.seats.length ? 'Alege alte locuri' : 'Adaugă alte tipuri de bilete'"></a>
                        </div>
                    </div>
                </div>

                <aside class="split__side">
                    <div class="panel">
                        <div class="panel__head"><h2>Sumar</h2></div>
                        <?= part_summary() ?>
                        <div class="panel__body">
                            <?= part_btn('Continuă', '/finalizare', 'btn--block') ?>
                            <p class="fine">Plata se face cu cardul, în pasul următor.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </template>

        <template x-if="!cart">
            <div class="panel empty" style="margin:36px 0 96px">
                <h2>Coșul e gol</h2>
                <p>Alege o competiție din calendar și adaugă biletele dorite.</p>
                <?= part_btn('Vezi competițiile', '/competitii') ?>
            </div>
        </template>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
