<?php
require_once __DIR__ . '/includes/boot.php';

$cancelled = ($_GET['plata'] ?? '') === 'anulata' || ($_GET['status'] ?? '') === 'cancelled';

$pageTitle = 'Finalizare comandă — ' . SITE_SHORT;
include __DIR__ . '/includes/head.php';
?>
<main class="wrap" x-data="checkoutPage">
    <div class="page-head">
        <span class="eyebrow">Pasul 2 din 3</span>
        <h1>Date și plată</h1>
        <?= part_flow(2) ?>
    </div>

    <template x-if="cart">
        <form class="split" @submit.prevent="pay()" novalidate>
            <div style="display:grid;gap:24px">
                <?php if ($cancelled): ?>
                <div class="alert">Plata a fost anulată și nu s-a încasat nimic. Biletele sunt încă în coș, poți încerca din nou.</div>
                <?php endif; ?>

                <div class="panel">
                    <div class="panel__head"><h2>Datele tale</h2></div>
                    <div class="panel__body">
                        <div class="fields fields--2">
                            <div class="field">
                                <label for="f-first">Prenume</label>
                                <input id="f-first" type="text" autocomplete="given-name" x-model="form.first_name" :class="bad('first_name') && 'is-bad'" required>
                            </div>
                            <div class="field">
                                <label for="f-last">Nume</label>
                                <input id="f-last" type="text" autocomplete="family-name" x-model="form.last_name" :class="bad('last_name') && 'is-bad'" required>
                            </div>
                            <div class="field">
                                <label for="f-email">Email</label>
                                <input id="f-email" type="email" autocomplete="email" inputmode="email" x-model="form.email" :class="bad('email') && 'is-bad'" required>
                                <small>Aici trimitem biletele.</small>
                            </div>
                            <div class="field">
                                <label for="f-phone">Telefon <span style="font-weight:400;color:var(--muted)">(opțional)</span></label>
                                <input id="f-phone" type="tel" autocomplete="tel" inputmode="tel" x-model="form.phone">
                                <small>Doar pentru anunțuri despre competiție.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel__head"><h2>Plată</h2></div>
                    <div class="panel__body" style="display:grid;gap:16px">
                        <div class="pay">
                            <svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/></svg>
                            <div><b>Card bancar</b><span>Visa, Mastercard · plată securizată</span></div>
                        </div>
                        <div class="note">Site demonstrativ: plata se face printr-un procesator de test, nu se încasează bani reali, iar biletele emise nu sunt valabile la intrare.</div>
                        <label class="check">
                            <input type="checkbox" x-model="form.terms">
                            <span>Am citit și accept termenii de vânzare a biletelor și regulamentul de acces în sală.</span>
                        </label>
                        <label class="check">
                            <input type="checkbox" x-model="form.newsletter">
                            <span>Vreau să primesc pe email anunțurile despre competițiile următoare.</span>
                        </label>
                    </div>
                </div>
            </div>

            <aside class="split__side">
                <div class="panel">
                    <div class="ev-mini">
                        <template x-if="cart.event.poster"><img class="ev-mini__img" :src="cart.event.poster" alt=""></template>
                        <template x-if="!cart.event.poster"><div class="ev-mini__img"></div></template>
                        <div>
                            <b x-text="cart.event.title"></b>
                            <span x-text="cart.event.date + ' · ' + cart.event.place"></span>
                        </div>
                    </div>
                    <div class="sum">
                        <template x-for="item in cart.items" :key="item.ticket_type_id">
                            <div><span x-text="item.qty + ' × ' + item.name"></span><span x-text="lei(item.qty * item.price)"></span></div>
                        </template>
                        <div class="sum__total"><span>Total de plată</span><b x-text="lei(total)"></b></div>
                    </div>
                    <div class="panel__body" style="display:grid;gap:12px">
                        <div class="alert" x-show="error" x-cloak x-text="error" role="alert"></div>
                        <button type="submit" class="btn btn--red btn--block" :disabled="busy">
                            <span x-text="busy ? 'Se procesează…' : 'Plătește ' + lei(total)"></span>
                        </button>
                        <a class="link" href="/cos" style="justify-self:center;font-size:14px">Înapoi la coș</a>
                    </div>
                </div>
            </aside>
        </form>
    </template>

    <template x-if="!cart">
        <div class="panel empty" style="margin:28px 0 96px">
            <h2>Nu ai bilete în coș</h2>
            <p>Alege o competiție din calendar pentru a continua.</p>
            <a class="btn btn--red" href="/competitii">Vezi competițiile</a>
        </div>
    </template>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
