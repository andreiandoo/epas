<?php
/** Fragmente reutilizate pe mai multe pagini. */

const ICON_ARROW = '<svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
const ICON_PIN   = '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.2 7-11.5A7 7 0 005 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
const ICON_CAL   = '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>';
const ICON_CHECK = '<svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';

/** Buton-link cu săgeată. */
function part_btn(string $label, string $href, string $cls = ''): string {
    return '<a class="btn ' . $cls . '" href="' . e($href) . '">' . e($label) . '<span class="btn__arrow">' . ICON_ARROW . '</span></a>';
}

/** Afișul competiției sau placeholder tipografic când lipsește. */
function part_poster(array $ev, string $loading = 'lazy'): string {
    $src = $ev['poster_url'] ?? ($ev['hero_image_url'] ?? null);
    if ($src) {
        return '<img src="' . e($src) . '" alt="Afiș ' . e($ev['title'] ?? '') . '" loading="' . $loading . '" decoding="async">';
    }
    return '<div class="poster-ph"><span>' . e($ev['title'] ?? '') . '</span></div>';
}

/** Card de competiție (afiș + detalii), cu înclinare 3D la cursor. */
function part_card(array $ev): string {
    $d    = ev_date_parts($ev);
    $from = $ev['price_from'] ?? null;
    ob_start(); ?>
    <a class="pcard" href="/competitii/<?= e($ev['slug']) ?>" data-tilt="6">
        <div class="pcard__media">
            <?= part_poster($ev) ?>
            <span class="pcard__date"><b><?= e($d['days']) ?></b><span><?= e($d['month'] . ' ' . $d['year']) ?></span></span>
            <span class="pcard__kind"><?= e(ev_kind($ev)) ?></span>
        </div>
        <div class="pcard__body">
            <span class="pcard__title"><?= e($ev['title']) ?></span>
            <span class="pcard__place"><?= ICON_PIN ?><?= e(ev_place($ev)) ?></span>
            <span class="pcard__foot">
                <?php if (!empty($ev['is_sold_out'])): ?>
                    <span>Bilete epuizate</span>
                <?php elseif ($from !== null): ?>
                    <span>de la <b><?= e(lei($from)) ?></b></span>
                <?php else: ?>
                    <span>Bilete în curând</span>
                <?php endif; ?>
                <span class="pcard__go"><?= ICON_ARROW ?></span>
            </span>
        </div>
        <span class="pcard__glare" aria-hidden="true"></span>
    </a>
    <?php return ob_get_clean();
}

/** Numărătoare inversă până la startul competiției (o actualizează fx.js). */
function part_countdown(array $ev): string {
    $iso = ev_start_iso($ev);
    if (!$iso || (ev_days_left($ev) ?? -1) < 0) { return ''; }
    $cells = ['d' => 'zile', 'h' => 'ore', 'm' => 'min', 's' => 'sec'];
    $out = '<div class="cd" data-countdown="' . e($iso) . '" role="timer" aria-label="Timp rămas până la start">';
    foreach ($cells as $k => $label) {
        $out .= '<div class="cd__cell"><b data-cd="' . $k . '">–</b><span>' . $label . '</span></div>';
    }
    return $out . '</div>';
}

/** Indicatorul de pași Coș → Date și plată → Confirmare ($on = 1..3; 4 = toate încheiate). */
function part_flow(int $on): string {
    $out = '<ol class="flow">';
    foreach (['Coș', 'Date și plată', 'Confirmare'] as $i => $label) {
        $n    = $i + 1;
        $cls  = $n < $on ? 'is-done' : ($n === $on ? 'is-on' : '');
        $mark = $n < $on ? '✓' : (string) $n;
        $out .= '<li class="' . $cls . '"' . ($n === $on ? ' aria-current="step"' : '') . '><i>' . $mark . '</i>' . $label . '</li>';
    }
    return $out . '</ol>';
}

/** Timerul de rezervare din coș și din pagina de plată. */
function part_timer(): string {
    return <<<'HTML'
<div class="timer" x-data="cartTimer" x-show="on" x-cloak :class="low && 'is-low'" role="timer" aria-live="off">
    <div class="timer__ring" aria-hidden="true">
        <svg viewBox="0 0 46 46"><circle class="t-bg" cx="23" cy="23" r="19"/><circle class="t-fg" cx="23" cy="23" r="19" stroke-dasharray="119.4" :stroke-dashoffset="dash"/></svg>
    </div>
    <div>
        <b x-text="clock">15:00</b>
        <span x-text="low ? 'Grăbește-te: rezervarea expiră imediat.' : 'Biletele îți sunt rezervate în acest timp.'"></span>
    </div>
</div>
HTML;
}

/** Sumarul de preț (subtotal, reducere, taxă de procesare, total) + codul de reducere. */
function part_summary(bool $withCoupon = true): string {
    $coupon = !$withCoupon ? '' : <<<'HTML'
<div class="coupon">
    <template x-if="coupon">
        <div class="coupon__on">
            <span>Cod aplicat: <b x-text="coupon.code"></b></span>
            <button type="button" @click="removeCoupon()">Scoate</button>
        </div>
    </template>
    <template x-if="!coupon">
        <div>
            <button type="button" class="coupon__toggle" @click="couponOpen = !couponOpen" :aria-expanded="couponOpen">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12l-8 8-9-9V4h7l10 8z"/><circle cx="7.5" cy="7.5" r="1.3"/></svg>
                Ai un cod de reducere?
            </button>
            <form x-show="couponOpen" x-cloak @submit.prevent="applyCoupon()">
                <div class="coupon__row">
                    <input type="text" x-model="couponInput" aria-label="Cod de reducere" placeholder="COD" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="50">
                    <button type="submit" class="btn btn--sm" :disabled="couponBusy" x-text="couponBusy ? 'Verific…' : 'Aplică'">Aplică</button>
                </div>
                <p class="coupon__msg is-bad" x-show="couponError" x-text="couponError" role="alert"></p>
            </form>
        </div>
    </template>
</div>
HTML;

    return <<<HTML
<div class="sum">
    <div><span x-text="ticketsLabel(count)"></span><span x-text="lei(subtotal)"></span></div>
    <div class="is-off" x-show="discount > 0" x-cloak><span>Reducere</span><span x-text="'− ' + lei(discount)"></span></div>
    <div x-show="fee > 0" x-cloak><span>Taxă de procesare a plății <small x-show="feeHint" x-text="'(' + feeHint + ')'"></small></span><span x-text="lei(fee)"></span></div>
    <div x-show="!(fee > 0)"><span>Taxe de procesare</span><span>incluse</span></div>
    <div class="sum__total"><span>Total de plată</span><b x-text="lei(total)"></b></div>
</div>
{$coupon}
HTML;
}
