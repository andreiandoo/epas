<?php
$pageTitle = 'Autentificare — ' . SITE_NAME;
$logoText = defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : 'TN';
$siteName = SITE_NAME;
$siteCity = defined('SITE_CITY') ? SITE_CITY : '';
$pageExtraStyles = '
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .input-field { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-primary) / .2); transition: all .3s ease; }
    .input-field:focus { border-color: rgb(var(--c-primary)); outline: none; box-shadow: 0 0 0 3px rgb(var(--c-primary) / .1); }
    .social-btn { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-primary) / .2); transition: all .3s ease; }
    .social-btn:hover { border-color: rgb(var(--c-primary)); background: rgb(var(--c-primary) / .05); }
';
include ts_layout('head');
?>
<body class="antialiased min-h-screen" x-data="loginPage()">
<div class="min-h-screen flex">
    <!-- Left - Form -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-6 lg:px-16 py-12">
        <div class="max-w-md mx-auto w-full">
            <a href="/" class="flex items-center gap-3 mb-12">
                <div class="w-12 h-12 rounded-full border-2 border-primary flex items-center justify-center"><span class="font-display text-primary text-xl"><?= e($logoText) ?></span></div>
                <div><p class="font-display text-lg leading-tight"><?= e($siteName) ?></p><p class="text-xs text-primary tracking-widest"><?= e($siteCity) ?></p></div>
            </a>
            <h1 class="font-display text-4xl mb-2">Bine ai revenit</h1>
            <p class="text-fg/70 mb-8">Intră în contul tău pentru a vedea biletele și abonamentele.</p>
            <div x-show="error" x-text="error" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 mb-4 text-sm"></div>
            <form @submit.prevent="submit()">
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm text-muted mb-2">Email</label>
                        <input type="email" x-model="email" class="input-field w-full px-4 py-3 rounded-lg text-fg" placeholder="ion@email.com" required>
                    </div>
                    <div>
                        <label class="block text-sm text-muted mb-2">Parolă</label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" x-model="password" class="input-field w-full px-4 py-3 rounded-lg text-fg pr-12" placeholder="••••••••" required>
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-muted hover:text-primary">
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" class="w-4 h-4 rounded border-primary/30 bg-surface text-primary focus:ring-primary"><span class="text-sm text-fg/70">Ține-mă minte</span></label>
                        <a href="/recuperare-parola" class="text-sm text-primary hover:underline">Ai uitat parola?</a>
                    </div>
                    <button type="submit" :disabled="loading" class="btn-primary w-full py-4 rounded-lg flex items-center justify-center gap-2">
                        <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="loading ? 'Se autentifică...' : 'Intră în cont'"></span>
                    </button>
                </div>
            </form>
            <div class="flex items-center gap-4 my-8"><div class="flex-1 h-px bg-primary/20"></div><span class="text-muted text-sm">sau continuă cu</span><div class="flex-1 h-px bg-primary/20"></div></div>
            <div class="grid grid-cols-2 gap-4">
                <button class="social-btn flex items-center justify-center gap-3 py-3 rounded-lg"><span class="text-primary">G</span><span>Google</span></button>
                <button class="social-btn flex items-center justify-center gap-3 py-3 rounded-lg"><svg class="w-5 h-5" fill="#1877F2" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg><span>Facebook</span></button>
            </div>
            <p class="text-center text-fg/70 mt-8">Nu ai cont? <a href="/inregistrare" class="text-primary hover:underline">Creează unul gratuit</a></p>
        </div>
    </div>
    <!-- Right - Image -->
    <div class="hidden lg:block lg:w-1/2 relative">
        <img src="https://images.unsplash.com/photo-1503095396549-807759245b35?w=1200&q=80" alt="" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-r from-canvas via-canvas/50 to-transparent"></div>
        <div class="absolute inset-0 bg-accent/20"></div>
        <div class="absolute bottom-12 left-12 right-12 bg-canvas/80 backdrop-blur-md rounded-xl p-8 border border-primary/20">
            <h3 class="font-display text-2xl text-primary mb-4">Beneficiile contului tău</h3>
            <ul class="space-y-3">
                <li class="flex items-center gap-3"><span class="text-primary">✓</span><span class="text-fg/80">Biletele tale într-un singur loc</span></li>
                <li class="flex items-center gap-3"><span class="text-primary">✓</span><span class="text-fg/80">Acces rapid la checkout</span></li>
                <li class="flex items-center gap-3"><span class="text-primary">✓</span><span class="text-fg/80">Istoric și recomandări personalizate</span></li>
                <li class="flex items-center gap-3"><span class="text-primary">✓</span><span class="text-fg/80">Oferte exclusive pentru membri</span></li>
            </ul>
        </div>
    </div>
</div>
<script>
function loginPage() {
    return {
        loading: false, showPassword: false, error: '',
        email: '', password: '',
        async submit() {
            if (this.loading) return;
            this.loading = true; this.error = '';
            try {
                const r = await fetch('/api/proxy.php?action=login', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: this.email, password: this.password })
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success && d.data && d.data.token) {
                    localStorage.setItem('site_auth', JSON.stringify({ token: d.data.token, user: d.data.user }));
                    const next = new URLSearchParams(location.search).get('next');
                    window.location.href = (next && next.startsWith('/')) ? next : '/cont';
                } else {
                    this.error = (d.message || (d.errors && d.errors.email && d.errors.email[0])) || 'Date de autentificare incorecte.';
                    this.loading = false;
                }
            } catch (e) {
                this.error = 'Eroare de conexiune.'; this.loading = false;
            }
        }
    };
}
</script>
</body>
</html>
