<?php
/**
 * User Dashboard Footer
 */
?>

<!-- Footer -->
<footer class="bg-white border-t border-border py-6 mt-8 no-print">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-muted">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Toate drepturile rezervate.</p>
            <div class="flex items-center gap-4">
                <a href="/user/help" class="text-sm text-muted hover:text-primary">Ajutor</a>
                <a href="/termeni" class="text-sm text-muted hover:text-primary">Termeni</a>
                <a href="/confidentialitate" class="text-sm text-muted hover:text-primary">Confidentialitate</a>
            </div>
        </div>
    </div>
</footer>
