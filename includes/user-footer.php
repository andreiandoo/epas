<?php
/**
 * User Dashboard Footer
 */
$socialLinks = $socialLinks ?? [
    'facebook' => 'https://facebook.com/ambilet',
    'instagram' => 'https://instagram.com/ambilet',
    'youtube' => 'https://youtube.com/ambilet',
    'tiktok' => 'https://tiktok.com/@ambilet'
];
$currentYear = date('Y');
?>

<!-- User Footer -->
<footer class="px-4 py-4 bg-slate-800 md:px-6 rounded-b-xl mobile:rounded-none">
    <div class="flex flex-col items-center justify-between max-w-6xl gap-4 mx-auto md:flex-row md:gap-5">
        <!-- Left: Copyright & Links -->
        <div class="flex flex-col items-center gap-3 md:flex-row md:gap-6">
            <p class="text-sm text-white/90">&copy; <?= $currentYear ?> <?= SITE_NAME ?>. Toate drepturile rezervate.</p>
            <div class="flex flex-wrap items-center justify-center gap-3 md:gap-5">
                <a href="/ajutor" class="text-xs transition-colors text-white/90 hover:text-white">Ajutor</a>
                <a href="/faq" class="text-xs transition-colors text-white/90 hover:text-white">FAQ</a>
                <a href="/termeni" class="text-xs transition-colors text-white/90 hover:text-white">Termeni</a>
                <a href="/confidentialitate" class="text-xs transition-colors text-white/90 hover:text-white">Confidentialitate</a>
                <a href="/contact" class="text-xs transition-colors text-white/90 hover:text-white">Contact</a>
            </div>
        </div>

        <!-- Right: Powered by & Social -->
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-1.5 text-[11px] text-white/40">
                Powered by
                <a href="https://tixello.com" target="_blank" class="flex items-center gap-1 font-semibold transition-colors text-white/90 hover:text-white">
                    <img src="/assets/images/tixello-logo.svg" width="40" height="12" alt="Tixello" class="h-3 transition-opacity duration-200 ease-in-out opacity-50 hover:opacity-100"/>
                </a>
            </div>
            <div class="flex gap-2">
                <a href="<?= htmlspecialchars($socialLinks['facebook']) ?>" target="_blank" rel="noopener" class="flex items-center justify-center transition-all rounded-md w-7 h-7 bg-white/10 text-white/90 hover:bg-primary hover:text-white">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </a>
                <a href="<?= htmlspecialchars($socialLinks['instagram']) ?>" target="_blank" rel="noopener" title="Instagram" class="flex items-center justify-center transition-all rounded-md w-7 h-7 bg-white/10 text-white/90 hover:bg-primary hover:text-white">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                </a>
                <a href="<?= htmlspecialchars($socialLinks['tiktok']) ?>" target="_blank" rel="noopener" title="TikTok" class="flex items-center justify-center transition-all rounded-md w-7 h-7 bg-white/10 text-white/90 hover:bg-primary hover:text-white">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                </a>
            </div>
        </div>
    </div>
</footer>
