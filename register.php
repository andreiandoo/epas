<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Creează cont — ' . SITE_NAME;
$logoText = defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : 'TN';
$siteName = SITE_NAME;
$siteCity = defined('SITE_CITY') ? SITE_CITY : '';
$pageExtraStyles = '
    .btn-gold:disabled { opacity: 0.5; cursor: not-allowed; }
    .input-field { background: #1A1A1A; border: 1px solid rgba(212,175,55,.2); transition: all .3s ease; }
    .input-field:focus { border-color: #D4AF37; outline: none; box-shadow: 0 0 0 3px rgba(212,175,55,.1); }
';
include __DIR__ . '/includes/head.php';
?>
<body class="antialiased min-h-screen" x-data="{ loading: false, showPassword: false }">
<div class="min-h-screen flex">
    <!-- Left - Image -->
    <div class="hidden lg:block lg:w-1/2 relative">
        <img src="https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=1200&q=80" alt="" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-l from-midnight via-midnight/50 to-transparent"></div>
        <div class="absolute inset-0 bg-burgundy/20"></div>
        <div class="absolute bottom-12 left-12 right-12 bg-midnight/80 backdrop-blur-md rounded-xl p-8 border border-gold/20">
            <h3 class="font-display text-2xl text-gold mb-4">De ce să îți creezi cont?</h3>
            <ul class="space-y-3">
                <li class="flex items-center gap-3"><span class="text-gold">✓</span><span class="text-ivory/80">Primești biletele pe email instant</span></li>
                <li class="flex items-center gap-3"><span class="text-gold">✓</span><span class="text-ivory/80">Gestionează-ți abonamentele ușor</span></li>
                <li class="flex items-center gap-3"><span class="text-gold">✓</span><span class="text-ivory/80">Premiere în avanpremieră</span></li>
                <li class="flex items-center gap-3"><span class="text-gold">✓</span><span class="text-ivory/80">Salvează spectacolele favorite</span></li>
            </ul>
        </div>
    </div>
    <!-- Right - Form -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-6 lg:px-16 py-12 overflow-y-auto">
        <div class="max-w-md mx-auto w-full">
            <a href="/" class="flex items-center gap-3 mb-8">
                <div class="w-12 h-12 rounded-full border-2 border-gold flex items-center justify-center"><span class="font-display text-gold text-xl"><?= e($logoText) ?></span></div>
                <div><p class="font-display text-lg leading-tight"><?= e($siteName) ?></p><p class="text-xs text-gold tracking-widest"><?= e($siteCity) ?></p></div>
            </a>
            <h1 class="font-display text-4xl mb-2">Creează cont</h1>
            <p class="text-ivory/70 mb-8">Alătură-te comunității noastre de iubitori de teatru.</p>
            <form @submit.prevent="loading = true; setTimeout(() => { window.location.href = '/cont' }, 1200)">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm text-warm-gray mb-2">Prenume</label><input type="text" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Ion" required></div>
                        <div><label class="block text-sm text-warm-gray mb-2">Nume</label><input type="text" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Popescu" required></div>
                    </div>
                    <div><label class="block text-sm text-warm-gray mb-2">Email</label><input type="email" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="ion@email.com" required></div>
                    <div><label class="block text-sm text-warm-gray mb-2">Telefon (opțional)</label><input type="tel" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="07xx xxx xxx"></div>
                    <div>
                        <label class="block text-sm text-warm-gray mb-2">Parolă</label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" class="input-field w-full px-4 py-3 rounded-lg text-ivory pr-12" placeholder="Minim 8 caractere" required minlength="8">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-warm-gray hover:text-gold">
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="space-y-3 pt-2">
                        <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" required class="w-4 h-4 mt-1 rounded border-gold/30 bg-charcoal text-gold focus:ring-gold"><span class="text-sm text-ivory/70">Accept <a href="/termeni" class="text-gold hover:underline">Termenii și condițiile</a> și <a href="/confidentialitate" class="text-gold hover:underline">Politica de confidențialitate</a></span></label>
                        <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-1 rounded border-gold/30 bg-charcoal text-gold focus:ring-gold"><span class="text-sm text-ivory/70">Doresc să primesc noutăți despre spectacole și oferte</span></label>
                    </div>
                    <button type="submit" :disabled="loading" class="btn-gold w-full py-4 rounded-lg flex items-center justify-center gap-2 mt-6">
                        <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="loading ? 'Se creează contul...' : 'Creează cont'"></span>
                    </button>
                </div>
            </form>
            <p class="text-center text-ivory/70 mt-8">Ai deja cont? <a href="/autentificare" class="text-gold hover:underline">Autentifică-te</a></p>
        </div>
    </div>
</div>
</body>
</html>
