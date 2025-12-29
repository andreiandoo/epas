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
<footer class="bg-slate-800 px-4 py-4 md:px-6 rounded-b-xl">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 md:gap-5">
        <!-- Left: Copyright & Links -->
        <div class="flex flex-col md:flex-row items-center gap-3 md:gap-6">
            <p class="text-sm text-white/60">&copy; <?= $currentYear ?> <?= SITE_NAME ?>. Toate drepturile rezervate.</p>
            <div class="flex flex-wrap items-center justify-center gap-3 md:gap-5">
                <a href="/ajutor" class="text-xs text-white/60 hover:text-white transition-colors">Ajutor</a>
                <a href="/faq" class="text-xs text-white/60 hover:text-white transition-colors">FAQ</a>
                <a href="/termeni" class="text-xs text-white/60 hover:text-white transition-colors">Termeni</a>
                <a href="/confidentialitate" class="text-xs text-white/60 hover:text-white transition-colors">Confidentialitate</a>
                <a href="/contact" class="text-xs text-white/60 hover:text-white transition-colors">Contact</a>
            </div>
        </div>

        <!-- Right: Powered by & Social -->
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-1.5 text-[11px] text-white/40">
                Powered by
                <a href="https://tixello.com" target="_blank" class="flex items-center gap-1 text-white/50 hover:text-white font-semibold transition-colors">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="3" width="18" height="18" rx="3" fill="#A51C30"/>
                        <text x="12" y="16" text-anchor="middle" fill="white" font-size="10" font-weight="800">T</text>
                    </svg>
                    TIXELLO
                </a>
            </div>
            <div class="flex gap-2">
                <a href="<?= htmlspecialchars($socialLinks['facebook']) ?>" target="_blank" rel="noopener" class="w-7 h-7 flex items-center justify-center bg-white/10 rounded-md text-white/60 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </a>
                <a href="<?= htmlspecialchars($socialLinks['instagram']) ?>" target="_blank" rel="noopener" title="Instagram" class="w-7 h-7 flex items-center justify-center bg-white/10 rounded-md text-white/60 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                </a>
                <a href="<?= htmlspecialchars($socialLinks['tiktok']) ?>" target="_blank" rel="noopener" title="TikTok" class="w-7 h-7 flex items-center justify-center bg-white/10 rounded-md text-white/60 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                </a>
            </div>
        </div>
    </div>
</footer>
