<?php
/**
 * Unified footer + document close for the teatru skin.
 */
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Teatrul Național';
$logoText = defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : 'TN';
$year = date('Y');
?>
    <!-- Footer -->
    <footer class="py-12 px-4 lg:px-8 border-t border-gold/10">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-warm-gray">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full border-2 border-gold flex items-center justify-center">
                    <span class="font-display text-gold"><?= htmlspecialchars($logoText) ?></span>
                </div>
                <span>© <?= $year ?> <?= htmlspecialchars($siteName) ?></span>
            </div>
            <div class="flex items-center gap-6">
                <a href="/confidentialitate" class="hover:text-gold transition-colors">Confidențialitate</a>
                <a href="/termeni" class="hover:text-gold transition-colors">Termeni</a>
                <p>Ticketing by <a href="https://tixello.ro" class="text-gold hover:text-gold-light font-semibold">tixello</a></p>
            </div>
        </div>
    </footer>
</body>
</html>
