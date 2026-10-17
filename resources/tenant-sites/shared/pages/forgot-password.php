<?php
/**
 * Parolă uitată: cere linkul de setare a parolei (storefront/password-link).
 * Răspunsul e același oricare ar fi adresa, ca să nu se poată afla ce emailuri au cont.
 */
$pageTitle = t('Recuperare parolă') . ' — ' . SITE_NAME;
$pageExtraHead = '<meta name="robots" content="noindex">';
$pageExtraStyles = ts_commerce_styles();
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-32 pb-24 px-4 min-h-[70vh] flex items-center" x-data="{ email: '', busy: false, sent: false, error: '',
        async send() {
            this.error = '';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.email.trim())) { this.error = TS.t('Adresa de email nu pare corectă.'); return; }
            this.busy = true;
            try {
                const r = await fetch('/api/proxy.php?action=password-link', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: this.email.trim() }) });
                if (r.status === 429) { this.error = TS.t('Prea multe încercări. Mai încearcă peste un minut.'); }
                else { this.sent = true; }
            } catch (e) { this.error = TS.t('Eroare de conexiune.'); }
            this.busy = false;
        } }">
    <div class="max-w-md mx-auto w-full">
        <h1 class="font-display text-4xl mb-2 text-center"><?= te('Recuperare parolă') ?></h1>
        <p class="text-muted text-center mb-8"><?= te('Îți trimitem pe email un link cu care îți setezi o parolă nouă.') ?></p>
        <form x-show="!sent" class="space-y-4 bg-surface/40 rounded-2xl p-8" @submit.prevent="send()" novalidate>
            <label class="block"><span class="mb-2 block text-sm text-muted"><?= te('Email') ?></span>
                <input type="email" x-model="email" autocomplete="email" required class="ts-input"></label>
            <p x-show="error" x-cloak x-text="error" class="text-sm text-red-400" role="alert"></p>
            <button type="submit" class="btn-primary w-full py-3 rounded-lg" :disabled="busy" x-text="busy ? TS.t('Se trimite…') : TS.t('Trimite linkul')"><?= te('Trimite linkul') ?></button>
            <p class="text-sm text-muted text-center pt-2"><a href="/autentificare" class="text-primary hover:text-primary-light"><?= te('Înapoi la autentificare') ?></a></p>
        </form>
        <div x-show="sent" x-cloak class="rounded-2xl bg-surface/40 p-8 text-center" role="status">
            <p class="font-display text-2xl mb-2"><?= te('Verifică emailul') ?></p>
            <p class="text-muted"><?= te('Dacă adresa are un cont la noi, linkul ajunge în câteva minute. Uită-te și în Spam.') ?></p>
            <a href="/autentificare" class="btn-primary mt-6 inline-block rounded-lg px-6 py-3"><?= te('Înapoi la autentificare') ?></a>
        </div>
    </div>
</section>
<?php include ts_layout('footer');
