<?php
/**
 * New Site Footer - Tailwind CSS Version
 *
 * No inline styles - uses Tailwind CSS classes
 * Cookie consent moved to separate file
 */

// Social media links (can be overridden before including)
$socialLinks = $socialLinks ?? [
    'facebook' => 'https://facebook.com/ambilet',
    'instagram' => 'https://instagram.com/ambilet',
    'youtube' => 'https://youtube.com/ambilet',
    'tiktok' => 'https://tiktok.com/@ambilet'
];
$currentYear = date('Y');
?>

<!-- Footer -->
<footer class="relative overflow-hidden text-white bg-gradient-to-b from-slate-900 to-slate-800">
    <!-- Decorative background elements -->
    <div class="footer-glow-top"></div>
    <div class="footer-glow-bottom"></div>

    <!-- Newsletter Section -->
    <div class="bg-gradient-to-r from-primary to-[#7f1627] py-12 px-6 relative z-10">
        <div class="absolute top-0 right-0 w-[300px] h-full footer-newsletter-pattern opacity-50"></div>
        <div class="relative z-10 flex flex-col items-center justify-between gap-10 mx-auto max-w-7xl lg:flex-row">
            <div class="flex-1 text-center lg:text-left">
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/15 rounded-full text-xs font-semibold text-white mb-3">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Fii primul care află
                </div>
                <h2 class="text-2xl lg:text-[28px] font-extrabold text-white mb-2">Nu rata niciun eveniment!</h2>
                <p class="text-base text-white/80">Primește recomandări personalizate și oferte exclusive direct în inbox.</p>
            </div>
            <form id="newsletterForm" class="flex flex-col flex-1 w-full max-w-md gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <svg class="absolute w-5 h-5 -translate-y-1/2 left-4 top-1/2 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input type="email" name="email" placeholder="Adresa ta de email" required
                        class="w-full pl-12 pr-5 py-4 bg-white/15 border border-white/20 rounded-xl text-[15px] text-white placeholder-white/50 outline-none focus:border-white focus:bg-white/20 transition-all">
                </div>
                <button type="submit" class="px-7 py-4 bg-white border-none rounded-xl text-primary text-[15px] font-semibold cursor-pointer hover:-translate-y-0.5 hover:shadow-lg transition-all whitespace-nowrap flex items-center justify-center gap-2">
                    Abonează-te
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
            </form>
            <p id="newsletterMessage" class="hidden mt-4 text-white/80"></p>
        </div>
    </div>

    <!-- Main Footer -->
    <div class="relative z-10 px-6 py-16">
        <div class="mx-auto max-w-7xl">
            <!-- Footer Grid -->
            <div class="grid grid-cols-1 gap-8 mb-6 md:grid-cols-2 lg:grid-cols-5 lg:gap-12">
                <!-- Brand Column -->
                <div class="text-center lg:col-span-1 lg:text-left">
                    <a href="/" class="inline-flex items-center gap-2.5 mb-4">
                        <img src="/assets/images/ambilet-logo.webp" alt="<?= SITE_NAME ?>" class="w-auto h-10 brightness-0 invert">
                    </a>
                    <p class="mb-6 text-sm leading-relaxed text-white/60">
                        Platforma ta de încredere pentru bilete la evenimente. Descoperă concerte, festivaluri, spectacole și experiențe unice în toată România.
                    </p>
                    <div class="flex gap-2.5 justify-center lg:justify-start">
                        <a href="<?= htmlspecialchars($socialLinks['facebook']) ?>" target="_blank" rel="noopener" class="w-10 h-10 flex items-center justify-center bg-white/10 border border-white/10 rounded-xl text-white/70 hover:bg-primary hover:border-primary hover:text-white hover:-translate-y-0.5 transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="<?= htmlspecialchars($socialLinks['instagram']) ?>" target="_blank" rel="noopener" class="w-10 h-10 flex items-center justify-center bg-white/10 border border-white/10 rounded-xl text-white/70 hover:bg-primary hover:border-primary hover:text-white hover:-translate-y-0.5 transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                            </svg>
                        </a>
                        <a href="<?= htmlspecialchars($socialLinks['tiktok']) ?>" target="_blank" rel="noopener" class="w-10 h-10 flex items-center justify-center bg-white/10 border border-white/10 rounded-xl text-white/70 hover:bg-primary hover:border-primary hover:text-white hover:-translate-y-0.5 transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                            </svg>
                        </a>
                        <a href="<?= htmlspecialchars($socialLinks['youtube']) ?>" target="_blank" rel="noopener" class="w-10 h-10 flex items-center justify-center bg-white/10 border border-white/10 rounded-xl text-white/70 hover:bg-primary hover:border-primary hover:text-white hover:-translate-y-0.5 transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/>
                                <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="#0F172A"/>
                            </svg>
                        </a>
                    </div>
                    <div class="flex items-center justify-center gap-2 pt-5 mt-5 border-t border-white/10 lg:justify-start">
                        <span class="text-xs text-white/40">Powered by</span>
                        <a href="https://tixello.com" target="_blank" class="flex items-center gap-1 text-xs font-bold transition-colors text-white/60 hover:text-white">
                            <img src="https://tixello.com/wp-content/themes/tix/assets/images/tixello-white.svg" alt="Tixello" class="h-5"/>
                        </a>
                    </div>
                </div>

                <!-- Evenimente Column -->
                <div>
                    <h4 class="mb-5 text-sm font-bold tracking-wider text-white uppercase">Evenimente</h4>
                    <ul class="space-y-3">
                        <li><a href="/evenimente" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Toate evenimentele</a></li>
                        <li><a href="/concerte" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Concerte</a></li>
                        <li><a href="/festivaluri" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Festivaluri</a></li>
                        <li><a href="/teatru" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Teatru & Spectacole</a></li>
                        <li><a href="/sport" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Sport</a></li>
                        <li><a href="/comedie" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Stand-up Comedy</a></li>
                        <li><a href="/calendar" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Calendar evenimente</a></li>
                    </ul>
                </div>

                <!-- Descoperă Column -->
                <div>
                    <h4 class="mb-5 text-sm font-bold tracking-wider text-white uppercase">Descoperă</h4>
                    <ul class="space-y-3">
                        <li><a href="/orase" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Orașe</a></li>
                        <li><a href="/locatii" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Locații</a></li>
                        <li><a href="/artisti" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Artiști</a></li>
                        <li><a href="/organizatori" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Organizatori</a></li>
                        <li>
                            <a href="/card-cadou" class="inline-flex items-center gap-2 text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">
                                Carduri cadou
                                <span class="px-2 py-0.5 bg-primary rounded text-[10px] font-bold text-white uppercase">Nou</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Companie Column -->
                <div>
                    <h4 class="mb-5 text-sm font-bold tracking-wider text-white uppercase">Companie</h4>
                    <ul class="space-y-3">
                        <li><a href="/despre" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Despre noi</a></li>
                        <li><a href="/press" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Press Kit</a></li>
                        <li><a href="/blog" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Blog</a></li>
                        <li><a href="/parteneri" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Parteneri</a></li>
                        <li><a href="/ajutor" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Centru de ajutor</a></li>
                        <li><a href="/faq" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Întrebări frecvente</a></li>
                    </ul>
                </div>

                <!-- Organizatori Column -->
                <div>
                    <h4 class="mb-5 text-sm font-bold tracking-wider text-white uppercase">Organizatori</h4>
                    <ul class="space-y-3">
                        <li><a href="/organizatori" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Vinde bilete</a></li>
                        <li><a href="/organizator/inregistrare" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Înregistrare organizator</a></li>
                        <li><a href="/organizator/login" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Login organizator</a></li>
                        <li><a href="/ghid-organizator" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Ghid organizatori</a></li>
                        <li><a href="/comisioane" class="inline-block text-sm transition-all text-white/60 hover:text-white hover:translate-x-1">Comisioane</a></li>
                    </ul>
                </div>
            </div>

            <!-- Middle Section -->
            <div class="flex flex-col items-center justify-between gap-6 py-8 mb-8 border-t border-b lg:flex-row border-white/10">
                <!-- Trust Badges -->
                <div class="flex flex-wrap items-center justify-center gap-6">
                    <div class="flex items-center gap-2.5 px-4 py-2.5 bg-white/5 rounded-xl">
                        <div class="flex items-center justify-center rounded-lg w-9 h-9 bg-emerald-500/20 text-emerald-500">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <div class="text-xs text-white/60">
                            <strong class="block text-[13px] text-white font-semibold">Plăți securizate</strong>
                            SSL 256-bit encryption
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 px-4 py-2.5 bg-white/5 rounded-xl">
                        <div class="flex items-center justify-center rounded-lg w-9 h-9 bg-emerald-500/20 text-emerald-500">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div class="text-xs text-white/60">
                            <strong class="block text-[13px] text-white font-semibold">Bilete garantate</strong>
                            100% autentice
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 px-4 py-2.5 bg-white/5 rounded-xl">
                        <div class="flex items-center justify-center rounded-lg w-9 h-9 bg-emerald-500/20 text-emerald-500">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="text-xs text-white/60">
                            <strong class="block text-[13px] text-white font-semibold">Suport rapid</strong>
                            Răspundem în maxim 24h
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="flex items-center gap-3">
                    <span class="mr-2 text-xs text-white/50">Plătești cu:</span>
                    <div class="w-12 h-[30px] bg-white/10 rounded-md flex items-center justify-center text-[10px] font-bold text-white/70">VISA</div>
                    <div class="w-12 h-[30px] bg-white/10 rounded-md flex items-center justify-center text-[10px] font-bold text-white/70">MC</div>
                    <div class="w-12 h-[30px] bg-white/10 rounded-md flex items-center justify-center text-[10px] font-bold text-white/70">GPay</div>
                    <div class="w-12 h-[30px] bg-white/10 rounded-md flex items-center justify-center text-[10px] font-bold text-white/70">Apple</div>
                </div>
            </div>

            <!-- Bottom Section -->
            <div class="flex flex-col flex-wrap items-center justify-between gap-5 lg:flex-row">
                <div class="text-[13px] text-white/50 text-center lg:text-left">
                    &copy; <?= $currentYear ?> <a href="/" class="transition-colors text-white/70 hover:text-white"><?= SITE_NAME ?></a>. Toate drepturile rezervate.
                    SC Ambilet.ro SRL • J40/7859/2017 • CUI 37653424
                </div>

                <div class="flex flex-wrap items-center justify-center gap-6">
                    <a href="/termeni" class="text-[13px] text-white/50 hover:text-white transition-colors">Termeni și Condiții</a>
                    <a href="/confidentialitate" class="text-[13px] text-white/50 hover:text-white transition-colors">Confidențialitate</a>
                    <a href="/cookies" class="text-[13px] text-white/50 hover:text-white transition-colors">Cookies</a>
                    <a href="/gdpr" class="text-[13px] text-white/50 hover:text-white transition-colors">Drepturile GDPR</a>
                    <a href="/anpc" class="text-[13px] text-white/50 hover:text-white transition-colors">ANPC</a>
                </div>

                <div class="flex items-center gap-4">
                    <button class="flex items-center gap-2 px-3.5 py-2 bg-white/10 border border-white/10 rounded-lg text-white/70 text-[13px] hover:bg-white/15 hover:text-white transition-all">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                        Română
                    </button>
                    <button onclick="CookieConsent.openSettings()" class="flex items-center gap-1.5 px-3.5 py-2 bg-transparent border border-white/20 rounded-lg text-white/60 text-[13px] hover:border-white/40 hover:text-white transition-all">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        Setări cookies
                    </button>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button class="back-to-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Înapoi sus">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="18 15 12 9 6 15"/>
    </svg>
</button>

<!-- Cookie Consent Component -->
<?php include __DIR__ . '/cookie-consent.php'; ?>

<script>
// Newsletter form handler
document.getElementById('newsletterForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const email = form.email.value;
    const message = document.getElementById('newsletterMessage');
    const button = form.querySelector('button');

    button.disabled = true;
    button.innerHTML = '<span class="inline-block w-4 h-4 mr-2 border-2 rounded-full border-primary animate-spin border-t-transparent"></span> Se trimite...';

    try {
        // Try to submit to API
        if (typeof AmbiletAPI !== 'undefined') {
            await AmbiletAPI.post('/newsletter/subscribe', { email });
        }
        message.textContent = 'Te-ai abonat cu succes! Vei primi cele mai tari evenimente.';
        message.classList.remove('hidden', 'text-error');
        message.classList.add('text-white');
        form.reset();
    } catch (error) {
        message.textContent = error.message || 'A apărut o eroare. Încearcă din nou.';
        message.classList.remove('hidden', 'text-white');
        message.classList.add('text-error');
    } finally {
        button.disabled = false;
        button.innerHTML = 'Abonează-te <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
    }
});
</script>
