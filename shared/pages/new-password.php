<?php
/**
 * Setarea parolei din linkul primit pe email (/parola-noua?c=&e=&s=):
 * storefront/password-set prin proxy, apoi autentificare.
 */
$pageTitle = t('Parolă nouă') . ' — ' . SITE_NAME;
$pageExtraHead = '<meta name="robots" content="noindex">';
$pageExtraStyles = ts_commerce_styles();
$link = ['c' => (int) ($_GET['c'] ?? 0), 'e' => (int) ($_GET['e'] ?? 0), 's' => preg_replace('/[^a-f0-9]/', '', (string) ($_GET['s'] ?? ''))];
$valid = $link['c'] > 0 && $link['e'] > 0 && strlen($link['s']) === 64;
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-32 pb-24 px-4 min-h-[70vh] flex items-center" x-data="{ link: <?= e(json_encode($link)) ?>, p1: '', p2: '', busy: false, done: false, error: '',
        async save() {
            this.error = '';
            if (this.p1.length < 8) { this.error = TS.t('Parola contului trebuie să aibă cel puțin 8 caractere.'); return; }
            if (this.p1 !== this.p2) { this.error = TS.t('Cele două parole nu sunt identice.'); return; }
            this.busy = true;
            try {
                const r = await fetch('/api/proxy.php?action=password-set', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({}, this.link, { password: this.p1, password_confirmation: this.p2 })) });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success) {
                    if (d.data && d.data.token) { TS.Auth.set({ token: d.data.token, user: d.data.user }); }
                    this.done = true;
                } else { this.error = d.error || d.message || TS.t('Linkul a expirat sau a fost deja folosit. Cere unul nou.'); }
            } catch (e) { this.error = TS.t('Eroare de conexiune.'); }
            this.busy = false;
        } }">
    <div class="max-w-md mx-auto w-full">
        <h1 class="font-display text-4xl mb-2 text-center"><?= te('Parolă nouă') ?></h1>
        <?php if (!$valid): ?>
        <div class="rounded-2xl bg-surface/40 p-8 text-center">
            <p class="text-muted mb-6"><?= te('Linkul a expirat sau a fost deja folosit. Cere unul nou.') ?></p>
            <a href="/recuperare-parola" class="btn-primary inline-block rounded-lg px-6 py-3"><?= te('Cere alt link') ?></a>
        </div>
        <?php else: ?>
        <p class="text-muted text-center mb-8"><?= te('Alege parola cu care intri în cont.') ?></p>
        <form x-show="!done" class="space-y-4 bg-surface/40 rounded-2xl p-8" @submit.prevent="save()" novalidate>
            <label class="block"><span class="mb-2 block text-sm text-muted"><?= te('Parolă nouă') ?></span>
                <input type="password" x-model="p1" autocomplete="new-password" minlength="8" required class="ts-input"></label>
            <label class="block"><span class="mb-2 block text-sm text-muted"><?= te('Repetă parola') ?></span>
                <input type="password" x-model="p2" autocomplete="new-password" minlength="8" required class="ts-input"></label>
            <p class="text-xs text-muted"><?= te('Minimum 8 caractere.') ?></p>
            <p x-show="error" x-cloak x-text="error" class="text-sm text-red-400" role="alert"></p>
            <button type="submit" class="btn-primary w-full py-3 rounded-lg" :disabled="busy" x-text="busy ? TS.t('Se salvează…') : TS.t('Salvează parola')"><?= te('Salvează parola') ?></button>
        </form>
        <div x-show="done" x-cloak class="rounded-2xl bg-surface/40 p-8 text-center" role="status">
            <p class="font-display text-2xl mb-2"><?= te('Parola a fost salvată.') ?></p>
            <a :href="'/autentificare?next=/cont'" class="btn-primary mt-4 inline-block rounded-lg px-6 py-3"><?= te('Intră în cont') ?></a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php include ts_layout('footer');
