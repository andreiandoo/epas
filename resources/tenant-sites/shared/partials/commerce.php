<?php
/**
 * Bucățile comune ale coșului și ale paginii de plată (pași, timer, sumar cu cod
 * de reducere). Logica e în public/assets/shared/commerce.js (cartPage, checkoutPage,
 * cartTimer); aici doar markup, cu tokenii temei.
 */

/** Pașii comenzii: 1 Coș, 2 Date și plată, 3 Confirmare. */
function ts_part_flow(int $on): string {
    $out = '<ol class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">';
    foreach (['Coș', 'Date și plată', 'Confirmare'] as $i => $label) {
        $n = $i + 1;
        $done = $n < $on;
        $cur = $n === $on;
        $dot = $done ? 'bg-primary text-on-primary' : ($cur ? 'border-2 border-primary text-primary' : 'border border-fg/20 text-fg/40');
        $out .= '<li class="flex items-center gap-2 ' . ($cur ? 'text-fg font-semibold' : 'text-fg/50') . '"' . ($cur ? ' aria-current="step"' : '') . '>'
            . '<span class="grid h-7 w-7 place-items-center rounded-full text-xs ' . $dot . '">' . ($done ? '✓' : $n) . '</span>' . $label . '</li>';
    }
    return $out . '</ol>';
}

/** Timerul de rezervare (cât timp biletele / locurile sunt ținute pentru cumpărător). */
function ts_part_timer(): string {
    return <<<'HTML'
<div x-data="cartTimer" x-show="on" x-cloak class="flex items-center gap-4 rounded-xl border px-5 py-4" :class="low ? 'border-red-500/40 bg-red-500/10' : 'border-primary/20 bg-primary/5'" role="timer" aria-live="off">
    <svg class="h-11 w-11 -rotate-90 shrink-0" viewBox="0 0 46 46" aria-hidden="true">
        <circle cx="23" cy="23" r="19" fill="none" stroke="currentColor" stroke-opacity=".15" stroke-width="4"/>
        <circle cx="23" cy="23" r="19" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="119.4" :stroke-dashoffset="dash" :class="low ? 'text-red-400' : 'text-primary'"/>
    </svg>
    <div>
        <b class="font-display text-2xl tabular-nums" x-text="clock">15:00</b>
        <p class="text-sm text-fg/60" x-text="low ? 'Grăbește-te: rezervarea expiră imediat.' : 'Biletele îți sunt rezervate în acest timp.'"></p>
    </div>
</div>
HTML;
}

/** Sumarul de preț: bilete, reducere, taxă de procesare, total + câmpul pentru cod. */
function ts_part_summary(bool $withCoupon = true): string {
    $coupon = !$withCoupon ? '' : <<<'HTML'
<div class="border-t border-primary/10 pt-4 mt-4">
    <template x-if="coupon">
        <div class="flex items-center justify-between rounded-lg bg-emerald-500/10 px-4 py-3 text-sm">
            <span>Cod aplicat: <b x-text="coupon.code"></b></span>
            <button type="button" class="text-fg/60 hover:text-fg underline" @click="removeCoupon()">Scoate</button>
        </div>
    </template>
    <template x-if="!coupon">
        <div>
            <button type="button" class="flex items-center gap-2 text-sm text-primary hover:text-primary-light" @click="couponOpen = !couponOpen" :aria-expanded="couponOpen">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12l-8 8-9-9V4h7l10 8z"/><circle cx="7.5" cy="7.5" r="1.3"/></svg>
                Ai un cod de reducere?
            </button>
            <div x-show="couponOpen" x-cloak class="mt-3">
                <div class="flex gap-2">
                    <input type="text" x-model="couponInput" @keydown.enter.prevent="applyCoupon()" aria-label="Cod de reducere" placeholder="COD" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="50" class="ts-input flex-1 uppercase">
                    <button type="button" class="btn-primary rounded-lg px-4 text-sm" @click="applyCoupon()" :disabled="couponBusy" x-text="couponBusy ? 'Verific…' : 'Aplică'">Aplică</button>
                </div>
                <p class="mt-2 text-sm text-red-400" x-show="couponError" x-text="couponError" role="alert"></p>
            </div>
        </div>
    </template>
</div>
HTML;

    return <<<HTML
<div class="space-y-2 text-sm">
    <div class="flex justify-between"><span class="text-fg/70" x-text="ticketsLabel(count)"></span><span x-text="lei(subtotal)"></span></div>
    <div class="flex justify-between text-emerald-400" x-show="discount > 0" x-cloak><span>Reducere</span><span x-text="'− ' + lei(discount)"></span></div>
    <div class="flex justify-between" x-show="fee > 0" x-cloak><span class="text-fg/70">Taxă de procesare a plății <small x-show="feeHint" x-text="'(' + feeHint + ')'"></small></span><span x-text="lei(fee)"></span></div>
    <div class="flex justify-between" x-show="!(fee > 0)"><span class="text-fg/70">Taxe de procesare</span><span class="text-fg/70">incluse</span></div>
    <div class="flex items-baseline justify-between border-t border-primary/10 pt-3 mt-3"><span class="font-semibold">Total de plată</span><b class="font-display text-2xl text-primary" x-text="lei(total)"></b></div>
</div>
{$coupon}
HTML;
}

/** Stilurile câmpurilor din formularele de comandă (tokenii temei). */
function ts_commerce_styles(): string {
    return '.ts-input{width:100%;background:rgb(var(--c-canvas));border:1px solid rgb(var(--c-fg) / .15);border-radius:.6rem;padding:.75rem 1rem;color:rgb(var(--c-fg));outline:none;transition:border-color .2s,box-shadow .2s}'
        . '.ts-input:focus{border-color:rgb(var(--c-primary));box-shadow:0 0 0 3px rgb(var(--c-primary) / .15)}'
        . '.ts-input.is-bad{border-color:rgb(248 113 113)}.ts-input.is-good{border-color:rgb(52 211 153)}'
        . '.ts-check{display:flex;gap:.75rem;align-items:flex-start;cursor:pointer}.ts-check input{margin-top:.2rem;width:1rem;height:1rem;accent-color:rgb(var(--c-primary))}'
        . '.ts-qty{display:inline-flex;align-items:center;border:1px solid rgb(var(--c-fg) / .15);border-radius:999px}'
        . '.ts-qty button{width:2.25rem;height:2.25rem;font-size:1.1rem;color:rgb(var(--c-fg))}.ts-qty button:disabled{opacity:.3}'
        . '.ts-qty output{min-width:2rem;text-align:center;font-weight:600}';
}
