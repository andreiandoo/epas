<?php
/**
 * Date și plată (comun tuturor template-urilor). Logica: checkoutPage din
 * commerce.js; trimite comanda prin proxy (action=checkout).
 */
$pageTitle = 'Date și plată — ' . SITE_NAME;
$pageExtraHead = '<meta name="robots" content="noindex">';
$pageExtraStyles = ts_commerce_styles()
    . '.pay-opt{border:2px solid rgb(var(--c-fg) / .1);transition:border-color .2s,background .2s;cursor:pointer}'
    . '.pay-opt.is-on{border-color:rgb(var(--c-primary));background:rgb(var(--c-primary) / .06)}'
    . '.btn-primary:disabled{opacity:.5;cursor:not-allowed}';
$cancelled = ($_GET['plata'] ?? '') === 'anulata';
$methods = tc_payment_methods();
$gami = tc_gamification();
$eventsUrl = ts_link('events');
include ts_layout('head');
include ts_layout('header');
?>
<main class="pt-32 pb-24 px-4 lg:px-8" x-data="checkoutPage">
    <div class="max-w-6xl mx-auto">
        <div class="mb-10 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="font-display text-4xl lg:text-5xl">Date și plată</h1>
            <?= ts_part_flow(2) ?>
        </div>

        <template x-if="cart">
            <form class="grid gap-8 lg:grid-cols-[1fr_380px]" @submit.prevent="pay()" novalidate>
                <div class="space-y-6">
                    <?= ts_part_timer() ?>
                    <?php if ($cancelled): ?>
                    <div class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-5 py-4 text-sm">Plata a fost anulată și nu s-a încasat nimic. Biletele sunt încă în coș, poți încerca din nou.</div>
                    <?php endif; ?>

                    <section class="rounded-xl border border-primary/10 bg-surface/60 p-6">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-display text-2xl">Datele tale</h2>
                            <a href="/autentificare?next=/finalizare" class="text-sm text-primary hover:text-primary-light" x-show="!user">Am deja cont</a>
                            <span class="text-sm text-fg/60" x-show="user" x-cloak>Conectat ca <b x-text="user ? (user.name || user.email) : ''"></b></span>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="block"><span class="mb-2 block text-sm text-muted">Prenume</span>
                                <input type="text" autocomplete="given-name" x-model="form.first_name" class="ts-input" :class="bad('first_name') && 'is-bad'" required></label>
                            <label class="block"><span class="mb-2 block text-sm text-muted">Nume</span>
                                <input type="text" autocomplete="family-name" x-model="form.last_name" class="ts-input" :class="bad('last_name') && 'is-bad'" required></label>
                            <label class="block"><span class="mb-2 block text-sm text-muted">Email</span>
                                <input type="email" autocomplete="email" inputmode="email" spellcheck="false" autocapitalize="off" x-model="form.email" class="ts-input" :class="bad('email') && 'is-bad'"
                                       @paste="noPaste($event)" @drop="noPaste($event)" @copy.prevent @cut.prevent required>
                                <small class="mt-1 block text-xs text-muted" x-text="usesAccountEmail() ? 'Adresa contului tău. Aici trimitem biletele.' : 'Aici trimitem biletele.'"></small></label>
                            <label class="block" x-show="!usesAccountEmail()"><span class="mb-2 block text-sm text-muted">Confirmă emailul</span>
                                <input type="email" autocomplete="off" inputmode="email" spellcheck="false" autocapitalize="off" x-model="form.email2" class="ts-input"
                                       :class="{ 'is-bad': confirmState() === 'bad', 'is-good': confirmState() === 'good' }" @paste="noPaste($event)" @drop="noPaste($event)" required>
                                <small class="mt-1 block text-xs" :class="confirmState() === 'bad' ? 'text-red-400' : (confirmState() === 'good' ? 'text-emerald-400' : 'text-muted')"
                                       x-text="confirmState() === 'good' ? 'Adresele sunt identice.' : (confirmState() === 'bad' ? 'Adresele nu sunt identice.' : 'Scrie adresa încă o dată, de mână.')"></small></label>
                            <label class="block"><span class="mb-2 block text-sm text-muted">Telefon <em class="not-italic text-fg/40">(opțional)</em></span>
                                <input type="tel" autocomplete="tel" inputmode="tel" x-model="form.phone" class="ts-input"></label>
                        </div>
                        <div class="mt-6 rounded-lg border border-primary/10 p-4" x-show="!user">
                            <label class="ts-check text-sm"><input type="checkbox" x-model="form.create_account">
                                <span><b>Creează-mi și un cont cu aceste date.</b> Găsești oricând biletele în contul tău, fără să cauți prin email.</span></label>
                            <label class="mt-4 block max-w-xs" x-show="form.create_account" x-cloak><span class="mb-2 block text-sm text-muted">Parolă pentru cont</span>
                                <input type="password" autocomplete="new-password" minlength="8" x-model="form.password" class="ts-input" :class="tried && !passwordOk() && 'is-bad'">
                                <small class="mt-1 block text-xs text-muted">Minimum 8 caractere.</small></label>
                        </div>
                        <p class="mt-4 rounded-lg bg-primary/10 px-4 py-3 text-sm" x-show="pasteWarn" x-cloak role="status">Lipirea e dezactivată pentru adresa de email. Scrie adresa de mână în ambele câmpuri: așa prindem o greșeală de tastare înainte să rămâi fără bilete.</p>
                    </section>

                    <section class="rounded-xl border border-primary/10 bg-surface/60 p-6 space-y-5">
                        <h2 class="font-display text-2xl">Plată</h2>
                        <div class="grid gap-3">
                            <?php foreach ($methods as $m): $id = (string) ($m['id'] ?? 'card'); ?>
                            <label class="pay-opt flex items-center gap-4 rounded-xl p-4" :class="payMethod === <?= e(json_encode($id)) ?> && 'is-on'">
                                <input type="radio" class="sr-only" name="pay" value="<?= e($id) ?>" x-model="payMethod">
                                <svg class="h-7 w-7 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/></svg>
                                <span><b class="block"><?= e($m['name'] ?? 'Card') ?></b><?php if (!empty($m['hint'])): ?><span class="text-sm text-muted"><?= e($m['hint']) ?></span><?php endif; ?>
                                <?php if (!empty($m['surcharge_percent'])): ?><span class="block text-xs text-muted">+<?= e(rtrim(rtrim(number_format((float) $m['surcharge_percent'], 2, ',', ''), '0'), ',')) ?>% la plată</span><?php endif; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="ts-check text-sm"><input type="checkbox" x-model="form.terms">
                            <span>Am citit și accept <a href="/termeni" target="_blank" class="text-primary underline">termenii de vânzare</a> și <a href="/confidentialitate" target="_blank" class="text-primary underline">politica de confidențialitate</a>.</span></label>
                        <label class="ts-check text-sm"><input type="checkbox" x-model="form.newsletter">
                            <span>Vreau să primesc pe email noutăți despre evenimentele <?= e(SITE_NAME) ?>.</span></label>
                    </section>
                </div>

                <aside class="lg:sticky lg:top-28 h-fit rounded-xl border border-primary/10 bg-surface/60 p-6">
                    <div class="flex items-center gap-4 border-b border-primary/10 pb-4 mb-4">
                        <template x-if="cart.event.poster"><img :src="cart.event.poster" alt="" class="h-16 w-12 rounded object-cover"></template>
                        <div><p class="font-display text-lg" x-text="cart.event.title"></p><p class="text-xs text-muted" x-text="[cart.event.date, cart.event.place].filter(Boolean).join(' · ')"></p></div>
                    </div>
                    <div class="space-y-1 text-sm mb-4">
                        <template x-for="item in cart.items" :key="TS.Cart.lineKey(item)"><div class="flex justify-between text-fg/70"><span x-text="item.qty + ' × ' + item.name + ((item.when || item.visit_date) ? ' (' + (item.when || [item.visit_date, item.slot_time].filter(Boolean).join(' ')) + ')' : '')"></span><span x-text="lei(item.qty * item.price)"></span></div></template>
                        <template x-for="seat in cart.seats" :key="seat.seat_uid"><div class="flex justify-between text-fg/70"><span x-text="seat.label"></span><span x-text="lei(seat.price)"></span></div></template>
                    </div>
                    <?= ts_part_summary() ?>
                    <?php if (!empty($gami['enabled']) && !empty($gami['earn_percentage'])): ?>
                    <p class="mt-4 rounded-lg bg-primary/10 px-4 py-3 text-sm">Primești <b x-text="Math.floor(total * <?= (float) $gami['earn_percentage'] ?> / 100)"></b> <?= e($gami['points_name'] ?? 'puncte') ?> pentru această comandă.</p>
                    <?php endif; ?>
                    <p class="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-300" x-show="error" x-cloak x-text="error" role="alert"></p>
                    <button type="submit" class="btn-primary mt-5 w-full rounded-lg py-4" :disabled="busy || quoting">
                        <span x-text="busy ? 'Se procesează…' : 'Plătește ' + lei(total)"></span>
                    </button>
                    <a href="/cos" class="mt-3 block text-center text-sm text-fg/60 hover:text-fg">Înapoi la coș</a>
                </aside>
            </form>
        </template>

        <template x-if="!cart">
            <div class="rounded-xl border border-primary/10 bg-surface/40 px-6 py-20 text-center">
                <h2 class="font-display text-3xl mb-3">Nu ai bilete în coș</h2>
                <p class="text-fg/60 mb-8">Alege un eveniment din program pentru a continua.</p>
                <a href="<?= e($eventsUrl) ?>" class="btn-primary inline-block rounded-lg px-8 py-4">Vezi programul</a>
            </div>
        </template>
    </div>
</main>
<div x-data="toastHost" x-show="show" x-cloak x-transition class="fixed bottom-6 left-1/2 z-[120] -translate-x-1/2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-on-primary shadow-xl" x-text="msg" role="status"></div>
<?php include ts_layout('footer');
