<?php
require_once __DIR__ . '/includes/boot.php';

$cancelled = ($_GET['plata'] ?? '') === 'anulata' || ($_GET['status'] ?? '') === 'cancelled';

$pageTitle = 'Date și plată — ' . SITE_SHORT;
$bodyClass = 'is-light';
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light" x-data="checkoutPage">
    <section class="phead phead--slim">
        <div class="wrap">
            <span class="label">Pasul 2 din 3</span>
            <h1>Date și plată</h1>
            <?= part_flow(2) ?>
        </div>
    </section>

    <div class="wrap">
        <template x-if="cart">
            <form class="split" @submit.prevent="pay()" novalidate>
                <div style="display:grid;gap:20px">
                    <?= part_timer() ?>

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
                                    <input id="f-email" type="email" autocomplete="email" inputmode="email" spellcheck="false" autocapitalize="off"
                                           x-model="form.email" :class="bad('email') && 'is-bad'"
                                           @paste="noPaste($event)" @drop="noPaste($event)" @copy.prevent @cut.prevent required>
                                    <small>Aici trimitem biletele.</small>
                                </div>
                                <div class="field">
                                    <label for="f-email2">Confirmă emailul</label>
                                    <input id="f-email2" type="email" autocomplete="off" inputmode="email" spellcheck="false" autocapitalize="off"
                                           x-model="form.email2" :class="{ 'is-bad': confirmState() === 'bad', 'is-good': confirmState() === 'good' }"
                                           @paste="noPaste($event)" @drop="noPaste($event)" aria-describedby="f-email2-hint" required>
                                    <small id="f-email2-hint" :class="{ 'is-bad': confirmState() === 'bad', 'is-good': confirmState() === 'good' }"
                                           x-text="confirmState() === 'good' ? 'Adresele sunt identice.' : (confirmState() === 'bad' ? 'Adresele nu sunt identice.' : 'Scrie adresa încă o dată, de mână.')"></small>
                                </div>
                                <div class="field">
                                    <label for="f-phone">Telefon <em>(opțional)</em></label>
                                    <input id="f-phone" type="tel" autocomplete="tel" inputmode="tel" x-model="form.phone">
                                    <small>Doar pentru anunțuri despre competiție.</small>
                                </div>
                            </div>
                            <div class="note" style="margin-top:18px" x-show="pasteWarn" x-cloak role="status">
                                Lipirea e dezactivată pentru adresa de email. Scrie adresa de mână în ambele câmpuri: așa prindem o greșeală de tastare înainte să pleci fără bilete.
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel__head"><h2>Plată</h2></div>
                        <div class="panel__body" style="display:grid;gap:18px">
                            <div class="pay">
                                <i><svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/></svg></i>
                                <div><b>Card bancar</b><span>Visa, Mastercard · plată securizată</span></div>
                                <span class="pay__dot" aria-hidden="true"></span>
                            </div>
                            <div class="note">Site demonstrativ: plata trece printr-un procesator de test, nu se încasează bani reali, iar biletele emise nu sunt valabile la intrare.</div>
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
                        <div class="sum" style="padding-bottom:4px">
                            <template x-for="item in cart.items" :key="item.ticket_type_id">
                                <div><span x-text="item.qty + ' × ' + item.name"></span><span x-text="lei(item.qty * item.price)"></span></div>
                            </template>
                        </div>
                        <div style="border-top:1px solid var(--line)"><?= part_summary() ?></div>
                        <div class="panel__body" style="display:grid;gap:14px">
                            <div class="alert" x-show="error" x-cloak x-text="error" role="alert"></div>
                            <button type="submit" class="btn btn--block" :disabled="busy || quoting">
                                <span x-text="busy ? 'Se procesează…' : 'Plătește ' + lei(total)"></span>
                            </button>
                            <a class="link" href="/cos" style="justify-self:center;font-size:15px">Înapoi la coș</a>
                        </div>
                    </div>
                </aside>
            </form>
        </template>

        <template x-if="!cart">
            <div class="panel empty" style="margin:36px 0 96px">
                <h2>Nu ai bilete în coș</h2>
                <p>Alege o competiție din calendar pentru a continua.</p>
                <?= part_btn('Vezi competițiile', '/competitii') ?>
            </div>
        </template>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
