<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Coșul meu — ' . SITE_SHORT;
include __DIR__ . '/includes/head.php';
?>
<main class="wrap" x-data="cartPage">
    <div class="page-head">
        <span class="eyebrow">Pasul 1 din 3</span>
        <h1>Coșul meu</h1>
        <?= part_flow(1) ?>
    </div>

    <template x-if="cart">
        <div class="split">
            <div class="panel">
                <a class="ev-mini" :href="'/competitii/' + cart.event.slug" style="text-decoration:none">
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
                                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5h6v2m-7 0l1 12h6l1-12"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
                <div class="panel__body">
                    <a class="link" :href="'/competitii/' + cart.event.slug">Schimbă biletele</a>
                </div>
            </div>

            <aside class="split__side">
                <div class="panel">
                    <div class="panel__head"><h2>Sumar</h2></div>
                    <div class="sum">
                        <div><span x-text="count === 1 ? '1 bilet' : count + ' bilete'"></span><span x-text="lei(total)"></span></div>
                        <div><span>Taxe de procesare</span><span>incluse</span></div>
                        <div class="sum__total"><span>Total de plată</span><b x-text="lei(total)"></b></div>
                    </div>
                    <div class="panel__body">
                        <a class="btn btn--red btn--block" href="/finalizare">Continuă</a>
                        <p class="fine">Biletele sunt rezervate doar după confirmarea plății.</p>
                    </div>
                </div>
            </aside>
        </div>
    </template>

    <template x-if="!cart">
        <div class="panel empty" style="margin:28px 0 96px">
            <h2>Coșul e gol</h2>
            <p>Alege o competiție din calendar și adaugă biletele dorite.</p>
            <a class="btn btn--red" href="/competitii">Vezi competițiile</a>
        </div>
    </template>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
