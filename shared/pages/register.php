<?php
/** Creare cont (comun tuturor template-urilor): /tenant-client/auth/register prin proxy. */
$pageTitle = t('Creează cont') . ' — ' . SITE_NAME;
$logoText = defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : 'TN';
$siteName = SITE_NAME;
$siteCity = defined('SITE_CITY') ? SITE_CITY : '';
$pageExtraStyles = '
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .input-field { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-primary) / .2); transition: all .3s ease; }
    .input-field:focus { border-color: rgb(var(--c-primary)); outline: none; box-shadow: 0 0 0 3px rgb(var(--c-primary) / .1); }
';
$terms = t('Am citit și accept {terms} și {privacy}.', [
    'terms' => '<a href="/termeni" target="_blank" class="text-primary hover:underline">' . te('termenii de vânzare') . '</a>',
    'privacy' => '<a href="/confidentialitate" target="_blank" class="text-primary hover:underline">' . te('politica de confidențialitate') . '</a>',
]);
include ts_layout('head');
?>
<body class="antialiased min-h-screen" x-data="registerPage()">
<div class="fixed right-4 top-4 z-50"><?= ts_lang_switch('flex gap-1 rounded-full border border-primary/20 bg-surface/80 p-1 text-xs font-bold', 'rounded-full px-3 py-1.5 text-fg/60', '!text-on-primary bg-primary') ?></div>
<div class="min-h-screen flex">
    <div class="hidden lg:block lg:w-1/2 relative">
        <img src="https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=1200&q=80" alt="" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-l from-canvas via-canvas/50 to-transparent"></div>
        <div class="absolute inset-0 bg-accent/20"></div>
        <div class="absolute bottom-12 left-12 right-12 bg-canvas/80 backdrop-blur-md rounded-xl p-8 border border-primary/20">
            <h3 class="font-display text-2xl text-primary mb-4"><?= te('De ce să îți creezi cont?') ?></h3>
            <ul class="space-y-3">
                <?php foreach (['Biletele tale într-un singur loc', 'Plată mai rapidă, cu datele completate', 'Istoricul comenzilor și facturile', 'Abonamente și carduri cadou în cont'] as $b): ?>
                <li class="flex items-center gap-3"><span class="text-primary">✓</span><span class="text-fg/80"><?= te($b) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-6 lg:px-16 py-12 overflow-y-auto">
        <div class="max-w-md mx-auto w-full">
            <a href="/" class="flex items-center gap-3 mb-8">
                <div class="w-12 h-12 rounded-full border-2 border-primary flex items-center justify-center"><span class="font-display text-primary text-xl"><?= e($logoText) ?></span></div>
                <div><p class="font-display text-lg leading-tight"><?= e($siteName) ?></p><p class="text-xs text-primary tracking-widest"><?= e($siteCity) ?></p></div>
            </a>
            <h1 class="font-display text-4xl mb-2"><?= te('Creează cont') ?></h1>
            <p class="text-fg/70 mb-8"><?= te('Creează-ți contul ca să ai biletele și comenzile într-un singur loc.') ?></p>
            <div x-show="error" x-cloak x-text="error" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 mb-4 text-sm" role="alert"></div>
            <form @submit.prevent="submit()">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm text-muted mb-2" for="r-fn"><?= te('Prenume') ?></label><input id="r-fn" type="text" x-model="f.first_name" autocomplete="given-name" class="input-field w-full px-4 py-3 rounded-lg text-fg" required></div>
                        <div><label class="block text-sm text-muted mb-2" for="r-ln"><?= te('Nume') ?></label><input id="r-ln" type="text" x-model="f.last_name" autocomplete="family-name" class="input-field w-full px-4 py-3 rounded-lg text-fg" required></div>
                    </div>
                    <div><label class="block text-sm text-muted mb-2" for="r-em"><?= te('Email') ?></label><input id="r-em" type="email" x-model="f.email" autocomplete="email" class="input-field w-full px-4 py-3 rounded-lg text-fg" required></div>
                    <div><label class="block text-sm text-muted mb-2" for="r-ph"><?= te('Telefon') ?> (<?= te('opțional') ?>)</label><input id="r-ph" type="tel" x-model="f.phone" autocomplete="tel" class="input-field w-full px-4 py-3 rounded-lg text-fg"></div>
                    <div>
                        <label class="block text-sm text-muted mb-2" for="r-pw"><?= te('Parolă') ?></label>
                        <div class="relative">
                            <input id="r-pw" :type="showPassword ? 'text' : 'password'" x-model="f.password" autocomplete="new-password" class="input-field w-full px-4 py-3 rounded-lg text-fg pr-12" placeholder="<?= te('Minimum 8 caractere.') ?>" required minlength="8">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-muted hover:text-primary" :aria-label="showPassword ? TS.t('Ascunde parola') : TS.t('Arată parola')">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="space-y-3 pt-2">
                        <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" x-model="f.terms" class="w-4 h-4 mt-1 rounded border-primary/30 bg-surface text-primary focus:ring-primary"><span class="text-sm text-fg/70"><?= $terms ?></span></label>
                        <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" x-model="f.news" class="w-4 h-4 mt-1 rounded border-primary/30 bg-surface text-primary focus:ring-primary"><span class="text-sm text-fg/70"><?= te('Vreau să primesc pe email noutăți despre evenimentele {name}.', ['name' => SITE_NAME]) ?></span></label>
                    </div>
                    <button type="submit" :disabled="loading" class="btn-primary w-full py-4 rounded-lg flex items-center justify-center gap-2 mt-6">
                        <span x-text="loading ? TS.t('Se creează contul...') : TS.t('Creează cont')"><?= te('Creează cont') ?></span>
                    </button>
                </div>
            </form>
            <p class="text-center text-fg/70 mt-8"><?= te('Ai deja cont?') ?> <a href="/autentificare" class="text-primary hover:underline"><?= te('Autentifică-te') ?></a></p>
        </div>
    </div>
</div>
<script>
function registerPage() {
    return {
        loading: false, showPassword: false, error: '',
        f: { first_name: '', last_name: '', email: '', phone: '', password: '', terms: false, news: false },
        async submit() {
            if (this.loading) return;
            this.error = '';
            if (this.f.password.length < 8) { this.error = TS.t('Parola contului trebuie să aibă cel puțin 8 caractere.'); return; }
            if (!this.f.terms) { this.error = TS.t('Trebuie să accepți termenii pentru a continua.'); return; }
            this.loading = true;
            try {
                const r = await fetch('/api/proxy.php?action=register', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ first_name: this.f.first_name.trim(), last_name: this.f.last_name.trim(), email: this.f.email.trim(), phone: this.f.phone.trim() || null, password: this.f.password, notification_email: this.f.news })
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success && d.data && d.data.token) {
                    TS.Auth.set({ token: d.data.token, user: d.data.user });
                    const next = new URLSearchParams(location.search).get('next');
                    window.location.href = (next && next.startsWith('/')) ? next : '/cont';
                    return;
                }
                const errs = d.errors ? Object.values(d.errors).flat() : [];
                this.error = errs[0] || d.message || TS.t('Contul nu a putut fi creat. Încearcă din nou.');
            } catch (e) { this.error = TS.t('Eroare de conexiune.'); }
            this.loading = false;
        }
    };
}
</script>
</body>
</html>
