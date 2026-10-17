<?php
/**
 * Dezabonarea de la newsletterul tenantului, din linkul din fiecare email (/dezabonare?token=).
 * Cere o confirmare cu un click, ca scanerele de linkuri ale serviciilor de email să nu
 * dezaboneze pe nimeni fără voia lui.
 */
$pageTitle = t('Dezabonare') . ' — ' . SITE_NAME;
$pageExtraStyles = ts_commerce_styles();
$token = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['token'] ?? ''));
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-32 pb-24 px-4 min-h-[70vh] flex items-center" x-data="{ token: <?= e(json_encode($token)) ?>, busy: false, done: false, email: '', error: '',
        async go() {
            this.error = ''; this.busy = true;
            try {
                const r = await fetch('/api/proxy.php?action=newsletter-unsubscribe', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: this.token }) });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success) { this.done = true; this.email = d.email || ''; }
                else { this.error = TS.t('Linkul de dezabonare nu mai este valabil.'); }
            } catch (e) { this.error = TS.t('Eroare de conexiune.'); }
            this.busy = false;
        } }">
    <div class="max-w-md mx-auto w-full text-center">
        <h1 class="font-display text-4xl mb-4"><?= te('Dezabonare') ?></h1>
        <?php if ($token === ''): ?>
        <div class="rounded-2xl bg-surface/40 p-8"><p class="text-muted"><?= te('Linkul de dezabonare nu mai este valabil.') ?></p></div>
        <?php else: ?>
        <div x-show="!done" class="rounded-2xl bg-surface/40 p-8">
            <p class="text-muted mb-6"><?= te('Nu mai vrei să primești noutățile {name} pe email?', ['name' => SITE_NAME]) ?></p>
            <button type="button" class="btn-primary w-full py-3 rounded-lg" @click="go()" :disabled="busy" x-text="busy ? TS.t('Un moment…') : TS.t('Da, dezabonează-mă')"><?= te('Da, dezabonează-mă') ?></button>
            <p x-show="error" x-cloak x-text="error" class="mt-4 text-sm text-red-400" role="alert"></p>
        </div>
        <div x-show="done" x-cloak class="rounded-2xl bg-surface/40 p-8" role="status">
            <p class="font-display text-2xl mb-2"><?= te('Te-ai dezabonat.') ?></p>
            <p class="text-muted"><span x-show="email" x-text="email + ' · '"></span><?= te('Nu mai primești newsletterul. Biletele și emailurile despre comenzi vin în continuare.') ?></p>
            <a href="/" class="btn-primary mt-6 inline-block rounded-lg px-6 py-3"><?= te('Înapoi pe site') ?></a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php include ts_layout('footer');
