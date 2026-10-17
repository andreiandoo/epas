<?php
/**
 * Bara de cookies comună (componenta cookieBar din commerce.js), pe tokenii temei.
 * Template-urile cu CSS propriu își pot face markup-ul lor peste aceeași componentă
 * (vezi templates/competitie/layout/footer.php). „Setări cookies” din subsol trimite
 * evenimentul site:cookie-settings, care redeschide bara cu detaliile.
 */
function ts_cookie_bar(): string {
    $L = [];
    foreach (['Cookie-uri și date stocate în browser', 'Folosim strict ce e necesar ca site-ul să funcționeze (coșul, contul, rezervarea locurilor). Cu acordul tău, organizatorul poate măsura vizitele și eficiența reclamelor.',
        'Detalii', 'Necesare', 'coș, autentificare, rezervarea locurilor. Nu pot fi oprite.', 'Analiză', 'statistici anonime despre folosirea site-ului.',
        'Marketing', 'măsurarea reclamelor (de exemplu Meta sau Google).', 'Personalizează', 'Salvează alegerea', 'Doar necesare', 'Accept toate'] as $i => $k) {
        $L[$i] = te($k);
    }
    return <<<HTML
<div x-data="cookieBar" x-show="open" x-cloak @site:cookie-settings.window="reopen()" role="dialog" aria-modal="false" aria-labelledby="ts-cookie-title"
     class="fixed inset-x-3 bottom-3 z-[150] mx-auto max-w-3xl rounded-2xl border border-fg/10 bg-surface p-5 shadow-2xl sm:inset-x-6 sm:bottom-6 sm:p-6">
    <b id="ts-cookie-title" class="block font-display text-lg">{$L[0]}</b>
    <p class="mt-2 text-sm text-fg/70">{$L[1]} <a href="/cookies" class="text-primary underline">{$L[2]}</a></p>
    <div x-show="details" x-cloak class="mt-4 grid gap-3 text-sm">
        <label class="flex items-start gap-3"><input type="checkbox" checked disabled class="mt-1 accent-current"><span><b>{$L[3]}</b>: {$L[4]}</span></label>
        <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" x-model="analytics" class="mt-1"><span><b>{$L[5]}</b>: {$L[6]}</span></label>
        <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" x-model="marketing" class="mt-1"><span><b>{$L[7]}</b>: {$L[8]}</span></label>
    </div>
    <div class="mt-5 flex flex-wrap gap-2">
        <button type="button" class="rounded-lg border border-fg/20 px-4 py-2.5 text-sm hover:border-fg/40" @click="details = !details" x-show="!details">{$L[9]}</button>
        <button type="button" class="rounded-lg border border-fg/20 px-4 py-2.5 text-sm hover:border-fg/40" @click="save()" x-show="details" x-cloak>{$L[10]}</button>
        <button type="button" class="rounded-lg border border-fg/20 px-4 py-2.5 text-sm hover:border-fg/40" @click="choose(false)">{$L[11]}</button>
        <button type="button" class="btn-primary rounded-lg px-5 py-2.5 text-sm" @click="choose(true)">{$L[12]}</button>
    </div>
</div>
HTML;
}

/** Consent Mode: totul refuzat până alege vizitatorul; alegerea salvată se aplică înaintea oricărui script de măsurare. */
function ts_consent_default(): string {
    return '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
        . '(function(){var c=null;try{c=JSON.parse(localStorage.getItem("site_consent")||"null")}catch(e){}'
        . 'var y=function(o){return o?"granted":"denied"};gtag("consent","default",{ad_storage:y(c&&c.marketing),'
        . 'ad_user_data:y(c&&c.marketing),ad_personalization:y(c&&c.marketing),analytics_storage:y(c&&c.analytics),'
        . 'functionality_storage:"granted",security_storage:"granted"});})();</script>';
}
